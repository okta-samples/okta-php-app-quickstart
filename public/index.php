<?php
require_once(__DIR__.'/../vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();
$dotenv->required([
  'OKTA_OAUTH2_ISSUER',
  'OKTA_OAUTH2_CLIENT_ID',
  'OKTA_OAUTH2_CLIENT_SECRET',
  'OKTA_OAUTH2_REDIRECT_URI',
])->notEmpty();

// Keep the session cookie out of reach of JavaScript and of cross-site requests
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// A very basic router
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
switch($path) {
  case '/':
    index();
    break;
  case '/login':
    start_oauth_flow();
    break;
  case '/authorization-code/callback':
    authorization_code_callback_handler();
    break;
  case '/logout':
    logout();
    break;
  default:
    http_response_code(404);
    echo 'Not Found';
    die();
}

function issuer() {
  // Accept the issuer with or without a trailing slash
  return rtrim($_ENV['OKTA_OAUTH2_ISSUER'], '/');
}

function fail($message, $status = 400) {
  http_response_code($status);
  echo htmlspecialchars($message, ENT_QUOTES);
  die();
}

function menu() {
  ?>
    <nav>
      <a href="/">Home</a>
      <?php if(!empty($_SESSION['okta_id_token'])): ?>
        <form method="post" action="/logout" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
          <button type="submit">Logout</button>
        </form>
      <?php endif; ?>
    </nav>
  <?php
}

function require_login() {
  if(empty($_SESSION['okta_id_token'])) {
    ?>
      <a href="/login">Log In</a>
    <?php
    die();
  }
}

// The ID token comes straight from the token endpoint over TLS, so reading its claims is safe here.
// JWTs are base64url encoded, not plain base64.
function id_token_claims() {
  $parts = explode('.', $_SESSION['okta_id_token']);
  $payload = strtr($parts[1] ?? '', '-_', '+/');
  return json_decode(base64_decode($payload), true) ?: [];
}

function index() {
  require_login();
  $claims = id_token_claims();
  $_SESSION['name'] = $claims['name'] ?? $claims['email'] ?? $claims['sub'] ?? '';
  menu();
  ?>
    Hello, <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES) ?>
  <?php
}

function start_oauth_flow() {
  // Generate a random state parameter for CSRF security
  $_SESSION['oauth_state'] = bin2hex(random_bytes(10));

  // Create the PKCE code verifier and code challenge
  $_SESSION['oauth_code_verifier'] = bin2hex(random_bytes(50));
  $hash = hash('sha256', $_SESSION['oauth_code_verifier'], true);
  $code_challenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

  // Build the authorization URL by starting with the authorization endpoint
  $authorization_endpoint = issuer().'/v1/authorize';
  $authorize_url = $authorization_endpoint.'?'.http_build_query([
    'response_type' => 'code',
    'client_id' => $_ENV['OKTA_OAUTH2_CLIENT_ID'],
    'state' => $_SESSION['oauth_state'],
    'redirect_uri' => $_ENV['OKTA_OAUTH2_REDIRECT_URI'],
    'code_challenge' => $code_challenge,
    'code_challenge_method' => 'S256',
    'scope' => 'openid profile email offline_access',
  ]);

  header('Location: '.$authorize_url);
}


function authorization_code_callback_handler() {

  if(!empty($_GET['error'])) {
    fail("authorization server returned an error: ".$_GET['error']);
  }

  if(empty($_GET['state']) || empty($_SESSION['oauth_state'])
      || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    fail("state does not match");
  }

  if(empty($_GET['code'])) {
    fail("this is unexpected, the authorization server redirected without a code or an error");
  }

  // Exchange the authorization code for an access token and ID token
  // by making a request to the token endpoint
  $token_endpoint = issuer().'/v1/token';

  $ch = curl_init($token_endpoint);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $_GET['code'],
    'code_verifier' => $_SESSION['oauth_code_verifier'] ?? '',
    'redirect_uri' => $_ENV['OKTA_OAUTH2_REDIRECT_URI'],
    'client_id' => $_ENV['OKTA_OAUTH2_CLIENT_ID'],
    'client_secret' => $_ENV['OKTA_OAUTH2_CLIENT_SECRET'],
  ]));
  $body = curl_exec($ch);

  if($body === false) {
    $curl_error = curl_error($ch);
    curl_close($ch);
    fail("could not reach the token endpoint: ".$curl_error, 502);
  }
  curl_close($ch);

  $response = json_decode($body, true);

  if(!is_array($response)) {
    fail("token endpoint returned an invalid response", 502);
  }

  if(isset($response['error'])) {
    fail("token endpoint returned an error: ".$response['error']);
  }

  if(!isset($response['access_token'])) {
    fail("token endpoint did not return an error or an access token", 502);
  }

  // The state and code verifier are single use
  unset($_SESSION['oauth_state'], $_SESSION['oauth_code_verifier']);

  // Start a fresh session ID after login to prevent session fixation
  session_regenerate_id(true);

  // Save the tokens in the session
  $_SESSION['okta_access_token'] = $response['access_token'];

  if(isset($response['refresh_token']))
    $_SESSION['okta_refresh_token'] = $response['refresh_token'];

  if(isset($response['id_token']))
    $_SESSION['okta_id_token'] = $response['id_token'];

  // Used to protect the logout form against CSRF
  $_SESSION['csrf'] = bin2hex(random_bytes(16));

  header('Location: /');

}

function logout() {
  // Logout changes state, so only accept a POST that carries the session's CSRF token
  if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
      || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    fail("invalid logout request", 403);
  }

  $id_token = $_SESSION['okta_id_token'] ?? '';

  $_SESSION = [];
  session_destroy();

  // Sign out of Okta as well. The post logout redirect URI is this app's base URL,
  // which must be listed under "Sign-out redirect URIs" on the Okta app.
  $redirect = parse_url($_ENV['OKTA_OAUTH2_REDIRECT_URI']);
  $base_url = $redirect['scheme'].'://'.$redirect['host'].(isset($redirect['port']) ? ':'.$redirect['port'] : '');

  header('Location: '.issuer().'/v1/logout?'.http_build_query([
    'id_token_hint' => $id_token,
    'post_logout_redirect_uri' => $base_url,
  ]));
}

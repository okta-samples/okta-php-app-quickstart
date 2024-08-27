<?php
require_once(__DIR__.'/../vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

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
  default:
    echo 'not found';
    die();
}

function require_login() {
  if(empty($_SESSION['okta_id_token'])) {
    ?>
      <a href="/login">Log In</a>
    <?php
    die();
  }  
}

function index() {
  require_login();
  $claims = json_decode(base64_decode(explode('.', $_SESSION['okta_id_token'])[1]), true);
  $_SESSION['name'] = $claims['name'];
  ?>
    Hello, <?= htmlspecialchars($_SESSION['name']) ?>
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
  $authorization_endpoint = $_ENV['OKTA_OAUTH2_ISSUER'].'/v1/authorize';
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

  if(empty($_GET['state']) || $_GET['state'] != $_SESSION['oauth_state']) {
    throw new Exception("state does not match");
  }

  if(!empty($_GET['error'])) {
    throw new Exception("authorization server returned an error: ".$_GET['error']);
  }

  if(empty($_GET['code'])) {
    throw new Exception("this is unexpected, the authorization server redirected without a code or an error");
  }

  // Exchange the authorization code for an access token and ID token 
  // by making a request to the token endpoint
  $token_endpoint = $_ENV['OKTA_OAUTH2_ISSUER'].'/v1/token';

  $ch = curl_init($token_endpoint);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $_GET['code'],
    'code_verifier' => $_SESSION['oauth_code_verifier'],
    'redirect_uri' => $_ENV['OKTA_OAUTH2_REDIRECT_URI'],
    'client_id' => $_ENV['OKTA_OAUTH2_CLIENT_ID'],
    'client_secret' => $_ENV['OKTA_OAUTH2_CLIENT_SECRET'],
  ]));
  $response = json_decode(curl_exec($ch), true);

  if(isset($response['error'])) {
    throw new Exception("token endpoint returned an error: ".$response['error']);
  }

  if(!isset($response['access_token'])) {
    throw new Exception("token endpoint did not return an error or an access token");
  }

  // Save the tokens in the session
  $_SESSION['okta_access_token'] = $response['access_token'];

  if(isset($response['refresh_token']))
    $_SESSION['okta_refresh_token'] = $response['refresh_token'];

  if(isset($response['id_token']))
    $_SESSION['okta_id_token'] = $response['id_token'];

  header('Location: /');

}


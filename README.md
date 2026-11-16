# Okta PHP Sample

## Prerequisites

- PHP 8.1 or later with the `curl` and `json` extensions
- [Composer](https://getcomposer.org/)

You will also need an Okta Integrator Free Plan account. To get one, sign up for an [Integrator account](https://developer.okta.com/login). Once you have an account, sign in to your [Integrator account](https://developer.okta.com/login). Next, in the Admin Console:

1. Go to **Applications and Resources > Applications**
2. Click **Create App Integration**
3. Select **OIDC - OpenID Connect** as the sign-in method
4. Select **Web Application** as the application type, then click **Next**
5. Enter an app integration name, e.g. `My PHP App`
6. Configure the redirect URIs:
- Accept the default redirect URI values:
- **Sign-in redirect URIs:** `http://localhost:8080/authorization-code/callback`
- **Sign-out redirect URIs:** `http://localhost:8080`
7. In the **Controlled access** section, select the appropriate access level
8. Click **Save**

Creating an OIDC Web App manually in the Admin Console configures your Okta Org with the application settings. You may also need to configure trusted origins for `http://localhost:8080` in **Security > API > Trusted Origins**.

## Install Dependencies

```
composer install
```

## Set Application Info

Copy the file `.env.example` to `.env` and set:

- `OKTA_OAUTH2_ISSUER`: the issuer URI of your authorization server, for example `https://{yourOktaDomain}/oauth2/default` (no trailing slash needed).
- `OKTA_OAUTH2_CLIENT_ID`: the client ID from your app's **Client Credentials** section.
- `OKTA_OAUTH2_CLIENT_SECRET`: the client secret from the same section.
- `OKTA_OAUTH2_REDIRECT_URI`: the sign-in redirect URI you configured on the app.

```text
OKTA_OAUTH2_ISSUER=https://dev-133337.okta.com/oauth2/default
OKTA_OAUTH2_CLIENT_ID=0oab8eb55Kb9jdMIr5d6
OKTA_OAUTH2_CLIENT_SECRET=myClientSecret
OKTA_OAUTH2_REDIRECT_URI=http://localhost:8080/authorization-code/callback
```

The app won't start if any of these values is missing.

> **Note**: Don't EVER commit `.env` into source control. Add it to the `.gitignore` file.

### Where are my new app's credentials?

After creating the app, you can find the configuration details on the app’s **General** tab:
- **Client ID:** Found in the **Client Credentials** section
- **Client Secret:** Click **Show** in the **Client Credentials** section to reveal
- **Issuer:** Found in the **Issuer URI** field for the authorization server that appears by selecting **Security > API** from the navigation pane.

## Enable Refresh Token

Manually enable Refresh Token on your Okta application to avoid third-party cookies. Sign in to your Okta Developer Edition account. Press the **Admin Console** button to navigate to the Okta Admin Console. In the sidenav, navigate to **Applications** > **Applications** and find the Okta application for this project you created for this project. Edit the application's **General Setting** to enable the **Refresh Token** checkbox. **Save** your changes.


## Run the Application

Run the app with the built-in PHP server:

```
php -S 127.0.0.1:8080 -t public
```

Visit `http://localhost:8080` in your browser and you should be able to sign in. After signing in, use **Logout** to sign out of the app and Okta.

## Use with the API sample

This app signs the user in and stores the access token in the session. It is the kind of front-end that the [okta-php-api-quickstart](https://github.com/okta-samples/okta-php-api-quickstart) API sample needs to get a token for its protected routes. Use the same `OKTA_OAUTH2_ISSUER` for both projects, and see the API's README for how to test it.

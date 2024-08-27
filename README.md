# Okta PHP Sample

## Prerequisites

Before running this sample, you will need an Okta Developer Account. Create one using `okta register`, or configure an existing one with `okta login`.

## Create the Application in Okta

1. Login to your Okta Admin dashboard, e.g. (https://my-account-admin.okta.com/admin/dashboard)
2. Navigate to `Applications > Applications` in the left-hand menu.
3. Click `Create App Integration`.
4. For `Sign-in method` select `OIDC - OpenID Connect` and for `Application Type` select `Web Application`. Click `Next`.
5. Use the following values for application info:
    * For `App integration name` use `okta-php-app-quickstart`.
    * Select `Grant type > Core grants > Refresh Token`.
    * Select `Assignments > Controlled access > Skip group assignment for now`.
    * Leave all other values as default.

## Install Dependencies

```
composer install
```

## Set Application Info

1. Copy the file `.env.example` to `.env` and fill in your Okta app configuration.
    * `OKTA_OAUTH2_ISSUER`: use `https://{myOktaDomain}/oauth2/default`.
    * `OKTA_OAUTH2_CLIENT_ID`: use the value in `Client Credentials > Client ID`.
    * `OKTA_OAUTH2_CLIENT_SECRET`: use the only value in `CLIENT SECRETS`.
    * `OKTA_OAUTH2_REDIRECT_URI`: use the default value (`http://localhost:8080/authorization-code/callback`).

## Run the Application

Run the app with the built-in PHP server:

```
php -S 127.0.0.1:8080 -t public
```

Visit `http://localhost:8080` in your browser and you should be able to sign in.

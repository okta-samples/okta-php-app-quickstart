# Okta PHP Sample

## Prerequisites

Before running this sample, you will need an Okta Integrator Free Plan account. To get one, sign up for an [Integrator account](https://developer.okta.com/login). Once you have an account, sign in to your [Integrator account](https://developer.okta.com/login). Next, in the Admin Console:

1. Go to **Applications > Applications**
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

Copy the file `.env.example` to `.env` and fill in your Okta app configuration.

```text
OKTA_OAUTH2_ISSUER=https://dev-133337.okta.com/oauth2/default
OKTA_OAUTH2_CLIENT_ID=0oab8eb55Kb9jdMIr5d6
OKTA_OAUTH2_CLIENT_SECRET=myClientSecret
OKTA_OAUTH2_REDIRECT_URI=http://localhost:8080/authorization-code/callback
```

> **Note**: Don't EVER commit `.env` into source control. Add it to the `.gitignore` file.

### Where are my new app's credentials?

After creating the app, you can find the configuration details on the app’s **General** tab:
- **Client ID:** Found in the **Client Credentials** section
- **Client Secret:** Click **Show** in the **Client Credentials** section to reveal
- **Issuer:** Found in the **Issuer URI** field for the authorization server that appears by selecting **Security > API** from the navigation pane.

## Enable Refresh Token

Manually enable Refresh Token on your Okta application to avoid third-party cookies. Sign in to your Okta Developer Edition account. Press the **Admin Console** button to navigate to the Okta Admin Console. In the sidenav, navigate to **Applications** > **Applications** and find the Okta application for this project named `okta-go-api-sample`. Edit the application's **General Setting** to enable the **Refresh Token** checkbox. **Save** your changes.


## Run the Application

Run the app with the built-in PHP server:

```
php -S 127.0.0.1:8080 -t public
```

Visit `http://localhost:8080` in your browser and you should be able to sign in.

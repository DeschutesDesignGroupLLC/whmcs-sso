<?php

include_once __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;
use League\Uri\Components\Query;
use League\Uri\Uri;
use League\Uri\UriModifier;
use WHMCS\Authentication\CurrentUser;
use WHMCS\Cookie;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Setting;
use WHMCS\User\Client;
use WHMCS\User\User;
use WHMCS\User\User\UserInvite;

/**
 * Client Area Head Output
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    return <<<'HTML'
	<meta name="robots" content="noindex, nofollow">
HTML;
});

/**
 * Client Area
 */
add_hook('ClientAreaPage', 1, function ($vars) {

    // If the user is on the invite page and not logged in
    $currentUser = new CurrentUser;
    if (! $currentUser->user() && array_key_exists('invite', $vars) && $vars['invite'] instanceof UserInvite) {
        // Create our redirect URL
        $invite = Uri::createFromString()->withPath('index.php')->withQuery(Query::createFromParams([
            'rp' => "/invite/{$vars['invite']->token}",
        ]))->__toString();

        // Store the invite token/redirect URL
        Cookie::set('OktaRedirectUrl', $invite, strtotime('+1 hour'));

        // Create our client services URL
        $clientservices = Uri::createFromString()->withPath('clientarea.php')->withQuery(Query::createFromParams([
            'action' => 'services',
        ]))->__toString();

        // Redirect to login
        header("Location: {$clientservices}");
        exit;
    }
});

/**
 * Client Area Services
 */
add_hook('ClientAreaPageProductsServices', 1, function ($vars) {

    // If we have a redirect URL set
    if ($referer = Cookie::get('OktaRedirectUrl')) {

        // Remove the cookie
        Cookie::delete('OktaRedirectUrl');

        // Redirect to the referer
        header("Location: {$referer}");
        exit;
    }
});

/**
 * Cached WHMCS version details
 */
$whmcsDetails = null;

/**
 * Client Area Login Hook
 */
add_hook('ClientAreaPageLogin', 1, function ($vars) use ($whmcsDetails) {

    // If no user logged in
    $currentUser = new CurrentUser;
    if (! $currentUser->user()) {

        // Try and get our settings
        try {

            // If our cached details are empty
            if (! $whmcsDetails) {

                // Get WHMCS details
                $whmcsDetails = localAPI('WhmcsDetails', []);
            }

            // If we have a version key
            if (is_array($whmcsDetails) && array_key_exists('whmcs', $whmcsDetails)) {

                // Get whmcs version
                $version = $whmcsDetails['whmcs']['version'];
            }

            // Get our domain
            $provider = Setting::where('module', 'sso')->where('setting', 'provider')->firstOrFail();
            $clientid = Setting::where('module', 'sso')->where('setting', 'clientid')->firstOrFail();
            $clientsecret = Setting::where('module', 'sso')->where('setting', 'clientsecret')->firstOrFail();
            $scopes = Setting::where('module', 'sso')->where('setting', 'scopes')->firstOrFail();
            $disablessl = Setting::where('module', 'sso')->where('setting', 'disablessl')->firstOrFail();

            // Get scopes
            $scopes = explode(',', $scopes->value);

            // Start our authentication code flow
            $oidc = new OpenIDConnectClient($provider->value, $clientid->value, $clientsecret->value);

            // If this is the beginning of the authorization request and we don't have an authorization code yet
            if (! $_REQUEST['code']) {

                // Set our redirect URL
                setRedirectUrl();
            }

            // Set our redirect URL
            $oidc->setRedirectURL((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]".'/index.php?rp=/login');

            // Add scopes
            $oidc->addScope($scopes);

            // Set our URL encoding
            $oidc->setUrlEncoding(PHP_QUERY_RFC1738);

            // If disable ssl verification
            if ($disablessl->value) {

                // Disable SSL
                $oidc->setVerifyHost(false);
                $oidc->setVerifyPeer(false);
            }

            // Start auth process
            $authorized = $oidc->authenticate();

            // If we just authorized
            if ($authorized) {

                // Log our auth call
                logModuleCall('sso', 'authorize', ['provider' => $oidc->getProviderURL(), 'client_id' => $oidc->getClientID(), 'redirect_url' => $oidc->getRedirectURL(), 'scope' => $oidc->getScopes()], ['access_token' => $oidc->getAccessToken(), 'id_token' => $oidc->getIdToken()], null, null);
            }

            // Get the subject from the ID token
            $token = $oidc->getIdTokenPayload();

            // Get our user info
            $userinfo = $oidc->requestUserInfo();

            // Log our auth call
            logModuleCall('sso', 'userinfo', ['provider' => $oidc->getProviderURL(), 'access_token' => $oidc->getAccessToken(), 'client_id' => $oidc->getClientID()], ['id_token' => $oidc->getIdToken(), 'user_info' => $userinfo], null, null);

            // Try and see if this user has already logged in
            try {

                // Query the database for a previous login links
                $member = Capsule::table('mod_sso_members')->where('sub', $token->sub)->orderBy('user_id', 'desc')->first();

                // We found a member, try and load the associated user
                $user = User::findOrFail($member->user_id);
            }

            // Unable to find user
            catch (\Exception $exception) {

                // We couldn't load a login link, so try and find the user by their email
                $user = User::where('email', $userinfo->email)->get()->first();
            }

            // If we need to onboard the client
            if (! $user || ($user && $user->clients->isEmpty())) {

                // Create our onboarding data we'll pass in a cookie
                $onboard = [
                    'userinfo' => $userinfo,
                    'user' => $user->id ?? null,
                    'access_token' => $oidc->getAccessToken(),
                    'id_token' => $oidc->getIdToken(),
                ];

                // Store the users email address
                Cookie::set('OktaOnboarding', base64_encode(json_encode($onboard)), strtotime('+1 hour'));

                // Redirect to change password
                header('Location: onboard.php');
                exit;
            }

            // If we get a user
            if ($user) {

                // Try and create an SSO token to log the user in
                try {

                    // Add our SSO link
                    Capsule::table('mod_sso_members')->updateOrInsert([
                        'user_id' => $user->id,
                    ], [
                        'sub' => $token->sub,
                        'access_token' => $oidc->getAccessToken(),
                        'id_token' => $oidc->getIdToken(),
                    ]);

                    // Compose our SSO payload
                    $sso = [
                        'user_id' => $user->id,
                        'destination' => 'clientarea:services'];

                    // Create an SSO login
                    $results = localAPI('CreateSsoToken', $sso);

                    // Log our API call
                    logModuleCall('sso', 'CreateSsoToken', $sso, $results, null, null);

                    // If the result was successful
                    if ($results['result'] === 'success') {

                        // If we get a redirect URL
                        if (array_key_exists('redirect_url', $results)) {

                            // Redirect the user
                            header("Location: {$results['redirect_url']}");
                            exit;
                        }
                    }

                    // We got an error
                    else {

                        // Log our errors
                        logActivity('SSO: WHMCS Local API Error - '.$results['message']);

                        // Forward to error page
                        showError($exception);
                    }
                }

                // Catch an exception
                catch (Exception $exception) {

                    // Log our errors
                    logActivity('SSO: WHMCS Login Exception - '.$exception->getMessage());

                    // Forward to error page
                    showError($exception);
                }
            }
        }

        // Catch our exceptions if we cant find any settings
        catch (ModelNotFoundException $exception) {

            // Log our errors
            logActivity('SSO: Model Exception - '.$exception->getMessage());

            // Forward to error page
            showError($exception);
        }

        // Catch our exceptions if we cant create an OIDC client object
        catch (OpenIDConnectClientException $exception) {

            // Log our errors
            logActivity('SSO: Client Exception - '.$exception->getMessage());

            // Forward to error page
            showError($exception);
        }

        // Catch any exception
        catch (Exception $exception) {

            // Log our errors
            logActivity('SSO: Exception - '.$exception->getMessage());

            // Forward to error page
            showError($exception);
        }
    }
});

/**
 * Delete Client
 */
add_hook('ClientDelete', 1, function ($vars) {

    // Try and delete all SSO login links
    try {

        // Delete all SSO login links
        Capsule::table('mod_sso_members')->where('client_id', '=', $vars['userid'])->delete();
    }

    // Catch any exceptions
    catch (\Exception $e) {
    }
});

/**
 * Client Password Reset
 */
add_hook('ClientAreaPagePasswordReset', 1, function ($vars) {

    // Try and get our settings
    try {

        // Get our redirect URL
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectpassword')->firstOrFail();

        // If we have a valid URL
        if (isset($redirect->value) && $redirect->value !== null) {

            // Redirect to change password
            header("Location: {$redirect->value}");
            exit;
        }
    }

    // Catch any errors
    catch (\Exception $exception) {

        // Got Error
        logActivity('SSO: Reset Password Exception - '.$exception->getMessage());
    }
});

/**
 * Client Change Password
 */
add_hook('ClientAreaPageChangePassword', 1, function ($vars) {

    // Try and get our settings
    try {

        // Get our redirect URL
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectpassword')->firstOrFail();

        // If we have a valid URL
        if (isset($redirect->value) && $redirect->value !== null) {

            // Redirect to change password
            header("Location: {$redirect->value}");
            exit;
        }
    }

    // Catch any errors
    catch (\Exception $exception) {

        // Got Error
        logActivity('SSO: Change Password Exception - '.$exception->getMessage());
    }
});

/**
 * User Logout
 */
add_hook('UserLogout', 1, function ($vars) {

    // Try and get our settings
    try {

        // Get our logout URL
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectlogout')->firstOrFail();

        // If we have a valid URL
        if (isset($redirect->value) && $redirect->value !== null) {

            // Get the member who is logging out
            $member = Capsule::table('mod_sso_members')->where('user_id', $vars['user']->id)->first();

            // Compose logout URL
            $logout = Uri::createFromString($redirect->value);

            // If we are appending the token
            if ($member->id_token) {

                // Append our ID Token
                $token = Query::createFromRFC3986("id_token_hint={$member->id_token}");
                $logout = UriModifier::appendQuery($logout, $token);
            }

            // Redirect
            header("Location: {$logout->__toString()}");
            exit;
        }
    }

    // Catch any errors
    catch (\Exception $exception) {

        // Got Error
        logActivity('SSO: Logout Exception - '.$exception->getMessage());
    }

});

/**
 * Client Registration
 */
add_hook('ClientAreaPageRegister', 1, function ($vars) {

    // Try and get our settings
    try {

        // Get our redirect URL
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectregistration')->firstOrFail();

        // If we have a valid URL
        if (isset($redirect->value) && $redirect->value !== null) {

            // Redirect to registration page
            header("Location: {$redirect->value}");
            exit;
        }
    }

    // Catch any errors
    catch (\Exception $exception) {

        // Got Error
        logActivity('SSO: Register Exception - '.$exception->getMessage());
    }
});

/**
 * Cart Page
 */
add_hook('ClientAreaPageCart', 1, function ($vars) {

    // If on the checkout page and no logged in user
    $currentUser = new CurrentUser;
    if (($_GET['a'] === 'checkout') && ! $currentUser->user()) {

        // Create our redirect URL
        $cart = Uri::createFromString()->withPath('cart.php')->withQuery(Query::createFromParams([
            'a' => 'checkout',
            'e' => 'false',
        ]))->__toString();

        // Store it in a cookie
        Cookie::set('OktaRedirectUrl', $cart, strtotime('+1 hour'));

        // Create our client services URL
        $clientservices = Uri::createFromString()->withPath('clientarea.php')->withQuery(Query::createFromParams([
            'action' => 'services',
        ]))->__toString();

        // Redirect to login
        header("Location: {$clientservices}");
        exit;
    }
});

/**
 * Redirect URL
 *
 * Finds and sets the redirect URL as a cookie which allows the addon
 * to redirect a user back to a previous page once they are authenticated.
 *
 * @return false|string|null
 */
function setRedirectUrl()
{
    // If we have a referer
    if ($_SERVER['HTTP_REFERER']) {

        // Get our incoming and current URIs
        $incoming = Uri::createFromString($_SERVER['HTTP_REFERER']);
        $current = Uri::createFromServer($_SERVER);

        // If the referer is internal, we don't want to redirect back to an external host
        if ($incoming->getHost() === $current->getHost()) {

            // Generate the current URI and the Client Area Services URI
            $request = Uri::createFromString()->withPath($current->getPath())->withQuery($current->getQuery())->__toString();
            $services = Uri::createFromString()->withPath('/clientarea.php')->withQuery('action=services')->__toString();

            // If the current request is not going to the client area services AND it is not coming from the main login page
            if ($request !== $services && $current->getQuery() !== 'rp=/login') {

                // Set our redirection URL cookie
                Cookie::set('OktaRedirectUrl', trim($request, '/'), strtotime('+1 hour'));
            }
        }
    }
}

/**
 * Show Error
 *
 * Forwards the user to an error page displaying the message
 * from the exception argument.
 */
function showError($exception)
{
    $error = Uri::createFromString()->withPath('onboard.php')->withQuery(Query::createFromParams([
        'error' => $exception->getMessage(),
    ]))->__toString();

    // Forware the user to the error page
    header("Location: {$error}");
    exit;
}

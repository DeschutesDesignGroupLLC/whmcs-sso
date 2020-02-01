<?php

// Make sure we're not accessing directly
if ( !defined( "WHMCS" ) ) {
    die("This file cannot be accessed directly");
}

// Include our dependencies
include_once( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' );


use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;
use WHMCS\Module\Addon\Setting;
use WHMCS\User\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Client Area Login Hook
 */
add_hook('ClientAreaPageLogin', 1, function( $vars ) {

    // If no user logged in
    if ( !$_SESSION['uid'] )
    {
        // Try and get our settings
        try
        {
            // Get our domain
            $domain = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'domain' )->firstOrFail();
            $clientid = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'clientid' )->firstOrFail();
            $clientsecret = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'clientsecret' )->firstOrFail();
            $autoauth = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'autoauth' )->firstOrFail();

            // Start our authentication code flow
            $oidc = new OpenIDConnectClient( $domain->value, $clientid->value, $clientsecret->value );
            $oidc->addScope( array( 'profile email openid' ) );
            $oidc->authenticate();

            // Get our email
            $email = $oidc->requestUserInfo('email');

            // Try and retrieve the user by email, if not create a user
            $client = Client::firstOrNew([ 'email' => $email ]);
            $client->email = $email;
            $client->save();

            // If we get a client
            if ( $client )
            {
                // Use AutoAuth to log the user in
                $timestamp = time();
                $hash = sha1( $email . $timestamp . $autoauth->value );
                $url = $vars['systemurl'] . "dologin.php?email=$email&timestamp=$timestamp&hash=$hash&goto=" . urlencode( 'clientarea.php?action=products' );
                header("Location: $url");
                exit;
            }
        }

        // Catch our exceptions if we cant find any settings
        catch ( ModelNotFoundException $exception )
        {
            // Log our errors
            logActivity( 'Auth0SSO Model Exception: ' . $exception->getMessage() );
        }

        // Catch our exceptions if we cant create an OIDC client object
        catch ( OpenIDConnectClientException $exception )
        {
            // Log our errors
            logActivity( 'Auth0SSO Client Exception: ' . $exception->getMessage() );
        }

        // Catch any exception
        catch ( Exception $exception )
        {
            // Log our errors
            logActivity( 'Auth0SSO Exception: ' . $exception->getMessage() );
        }
    }
});

/**
 * Client Logout Hook
 */
add_hook('ClientLogout', 1, function( $vars )
{
    // Try and get our settings
    try
    {
        // Get logout
        $logout = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'fulllogout' )->firstOrFail();

        // If we are wanting a full logout
        if ( $logout->value == 'on' )
        {
            // Get our domain
            $domain = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'domain' )->firstOrFail();
            $clientid = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'clientid' )->firstOrFail();
            $redirect = Setting::where( 'module', 'auth0sso' )->where( 'setting', 'logoutredirect' )->firstOrFail();

            // Logout of URL
            $url = $domain->value . "/v2/logout?client_id=$clientid->value&returnTo=$redirect->value";
            header( "Location: $url" );
        }
    }

    // Catch our exceptions if we cant find any settings
    catch ( ModelNotFoundException $exception )
    {
        // Log our errors
        logActivity( 'Auth0SSO Model Exception: ' . $exception->getMessage() );
    }
});
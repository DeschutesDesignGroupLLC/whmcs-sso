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
            $provider = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'provider' )->firstOrFail();
            $clientid = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'clientid' )->firstOrFail();
            $clientsecret = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'clientsecret' )->firstOrFail();
	        $scopes = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'scopes' )->firstOrFail();
	        $disablessl = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'disablessl' )->firstOrFail();

            // Start our authentication code flow
            $oidc = new OpenIDConnectClient( $provider->value, $clientid->value, $clientsecret->value );

            // Add scopes
            $oidc->addScope( explode( ',', $scopes->value ) );

            // If disable ssl verification
	        if ( $disablessl )
	        {
	        	// Disable SSL
		        $oidc->setVerifyHost( FALSE );
		        $oidc->setVerifyPeer( FALSE );
	        }

	        // Start auth process
            $oidc->authenticate();

	        // Log our auth call
	        logModuleCall( 'oidcsso', 'authorize',
		        array(
			        'provider' => $oidc->getProviderURL(),
			        'client_id' => $oidc->getClientID(),
			        'redirect_url' => $oidc->getRedirectURL(),
			        'scope' => $scopes->value ),
		        array(
			        'access_token' => $oidc->getAccessToken(),
			        'id_token' => $oidc->getIdToken() ),
		        NULL, NULL );

	        // Get our email
	        $userinfo = $oidc->requestUserInfo();

	        // Log our auth call
	        logModuleCall( 'oidcsso', 'userinfo',
		        array(
			        'provider' => $oidc->getProviderURL(),
			        'access_token' => $oidc->getAccessToken(),
			        'client_id' => $oidc->getClientID() ),
		        array(
			        'id_token' => $oidc->getIdToken(),
			        'user_info' => $userinfo ),
		        NULL, NULL );

            // Try and retrieve the user by email, if not create a user
            $client = Client::firstOrNew([ 'email' => $userinfo->email ]);

            // If the user did not exist
	        if ( !$client->exists )
	        {
	        	// If the client did not exist
		        $client->email = $userinfo->email;
		        $client->firstname = $userinfo->given_name ? $userinfo->given_name : 'New';
		        $client->lastname = $userinfo->family_name ? $userinfo->family_name : 'User';
		        $client->save();
	        }

            // If we get a client
            if ( $client )
            {
            	// Try and create an SSO token to log the user in
            	try
	            {
		            // Create an SSO login
		            $results = localAPI( 'CreateSsoToken', array(
			            'client_id' => $client->id,
			            'destination' => 'clientarea:services'
		            ), 'Jon Erickson' );

		            // Log our API call
		            logModuleCall( 'oidcsso', 'CreateSsoToken', array( 'client_id' => $client->id, 'destination' => 'clientarea:services' ), $results, NULL, NULL );

		            // If the result was successful
		            if ($results['result'] == 'success')
		            {
			            // If we get a redirect URL
			            if ( key_exists( 'redirect_url', $results ) )
			            {
				            // Redirect the user
				            header("Location: {$results['redirect_url']}");
			            }
		            }

		            // We got an error
		            else
	                {
		                // Log our errors
		                logActivity( 'WHMCS Local Api Error: ' . $results['error'] );
		            }
	            }

	            // Catch an exception
	            catch ( Exception $exception )
	            {
		            // Log our errors
		            logActivity( 'WHMCS Local Api Exception: ' . $exception->getMessage() );
	            }
            }
        }

        // Catch our exceptions if we cant find any settings
        catch ( ModelNotFoundException $exception )
        {
            // Log our errors
            logActivity( 'OIDC SSO Model Exception: ' . $exception->getMessage() );
        }

        // Catch our exceptions if we cant create an OIDC client object
        catch ( OpenIDConnectClientException $exception )
        {
            // Log our errors
            logActivity( 'OIDC SSO Client Exception: ' . $exception->getMessage() );
        }

        // Catch any exception
        catch ( Exception $exception )
        {
            // Log our errors
            logActivity( 'OIDC SSO Exception: ' . $exception->getMessage() );
        }
    }
});
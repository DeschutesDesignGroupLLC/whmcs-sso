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
use WHMCS\Database\Capsule;
use WHMCS\Cookie;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Client Area Head Output
 */
add_hook('ClientAreaHeadOutput', 1, function($vars) {
	$template = $vars['template'];
	return <<<HTML
    <meta name="robots" content="nofollow">
HTML;

});

/**
 * Client Area Login Hook
 */
add_hook('ClientAreaPageLogin', 1, function( $vars ) {

    // If no user logged in
    if ( !Menu::context('client') )
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

	        // Get scopes
	        $scopes = explode( ',', $scopes->value );

	        // Start our authentication code flow
	        $oidc = new OpenIDConnectClient( $provider->value, $clientid->value, $clientsecret->value );

	        // Set our redirect URL
	        $oidc->setRedirectURL( $vars['systemurl'] . 'clientarea.php' );

	        // Add scopes
	        $oidc->addScope( $scopes );

	        // Set our URL encoding
	        $oidc->setUrlEncoding(PHP_QUERY_RFC1738);

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
			        'scope' => $oidc->getScopes() ),
		        array(
			        'access_token' => $oidc->getAccessToken(),
			        'id_token' => $oidc->getIdToken() ),
		        NULL, NULL );

	        // Get the subject from the ID token
	        $token = $oidc->getIdTokenPayload();

	        // Try and see if this user has already logged in
	        try
	        {
		        // Query the database for a previous login link
		        $member = Capsule::table('mod_oidcsso_members')->where('sub', $token->sub)->first();

		        // We found a member, try and load the associated client
		        $client = Client::findOrFail($member->client_id);
	        }

	        // Unable to find client
	        catch ( \Exception $exception )
	        {
		        // Get our user info
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

		        // We couldn't load a login link, so try and find the user by their email
		        $client = Client::firstOrNew([ 'email' => $userinfo->email ]);

		        // If the user did not exist
		        if ( !$client->exists )
		        {
			        // Add the client
//			        localAPI( 'AddClient', array(
//				        'firstname' => $userinfo->given_name ? $userinfo->given_name : 'New',
//				        'lastname' => $userinfo->family_name ? $userinfo->family_name : 'User',
//				        'email' => $userinfo->email
//			        ), 'Jon Erickson' );

			        $client->email = $userinfo->email;
			        $client->firstname = $userinfo->given_name ? $userinfo->given_name : 'New';
			        $client->lastname = $userinfo->family_name ? $userinfo->family_name : 'User';
			        $client->created_at = time();
			        $client->updated_at = time();
			        $client->datecreated = date("Y-m-d", time());
			        $client->email_verified = 1;
			        $client->allow_sso = 1;
			        $client->save();
		        }

		        // Update the member link
		        Capsule::insert("INSERT INTO `mod_oidcsso_members` (client_id,sub,access_token,id_token) VALUES ('{$client->id}','{$token->sub}','{$oidc->getAccessToken()}','{$oidc->getIdToken()}') ON DUPLICATE KEY UPDATE sub = '{$token->sub}'");
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
			        if ( $results['result'] == 'success' )
			        {
			            // Set the remember me cookie
				        Cookie::set( 'User', $client->id, strtotime( '+1 year', time() ) );

				        // If we get a redirect URL
				        if ( key_exists( 'redirect_url', $results ) )
				        {
					        // Redirect the user
					        header("Location: {$results['redirect_url']}");
					        exit;
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

/**
 * Client Area Services and Products
 */
add_hook('ClientAreaPageProductsServices', 1, function($vars) {

	// Do we have a redirect url store
	if ( isset( $_SESSION['oidc_redirect_url'] ) )
	{
		// Redirect to the stored url
		header("Location: {$_SESSION['oidc_redirect_url']}");
		unset($_SESSION['oidc_redirect_url']);
		exit;
	}
});

/**
 * Delete Client
 */
add_hook('ClientDelete', 1, function($vars) {

	// Try and delete all SSO login links
	try
	{
		// Delete all SSO login links
		Capsule::table('mod_oidcsso_members')->where('client_id', '=', $vars['userid'])->delete();
	}

	// Catch any exceptions
	catch ( \Exception $e ) {}
});

/**
 * Client Password Reset
 */
add_hook('ClientAreaPagePasswordReset', 1, function($vars) {

	// Try and get our settings
	try
	{
		// Get our redirect URL
		$redirect = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'redirectpassword' )->firstOrFail();

		// If we have a valid URL
		if ( isset( $redirect->value ) AND $redirect->value != NULL )
		{
			// Redirect to change password
			header("Location: {$redirect->value}");
			exit;
		}
	}

	// Catch any errors
	catch ( \Exception $exception )
	{
		// Got Error
		logActivity( 'OIDC SSO Reset Password Exception: ' . $exception->getMessage() );
	}
});

/**
 * Client Change Password
 */
add_hook('ClientAreaPageChangePassword', 1, function($vars) {

	// Try and get our settings
	try
	{
		// Get our redirect URL
		$redirect = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'redirectpassword' )->firstOrFail();

		// If we have a valid URL
		if ( isset( $redirect->value ) AND $redirect->value != NULL )
		{
			// Redirect to change password
			header("Location: {$redirect->value}");
			exit;
		}
	}

	// Catch any errors
	catch ( \Exception $exception )
	{
		// Got Error
		logActivity( 'OIDC SSO Change Password Exception: ' . $exception->getMessage() );
	}
});

/**
 * Client Logout Page
 */
add_hook('ClientAreaPageLogout', 1, function($vars) {

	// If the logout redirect URL is set
	if ( isset( $_SESSION['oidcsso_logout_redirect'] ) )
	{
		// Redirect
		header("Location: {$_SESSION['oidcsso_logout_redirect']}");
		unset($_SESSION['oidcsso_logout_redirect']);
		exit;
	}
});

/**
 * Client Logout
 */
add_hook('ClientLogout', 1, function($vars) {

	// Try and get our settings
	try
	{
		// Get our logout URL
		$redirect = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'redirectlogout' )->firstOrFail();

		// If we have a valid URL
		if ( isset( $redirect->value ) AND $redirect->value != NULL )
		{
			// See if we are attaching the token
			$attach = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'logoutidtoken' )->firstOrFail();
			$parameter = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'logoutidtokenparameter' )->firstOrFail();
			$member = Capsule::table('mod_oidcsso_members')->where('client_id', $vars['userid'])->first();

			// Construct the URL
			$logout = $redirect->value;

			// If we are appending the token
			if ( $attach->value AND $parameter->value AND $member->id_token )
			{
				// Attach the ID token
				$logout = rtrim( $logout,"/" ) . http_build_query( [ $parameter->value => $member->id_token ], '', '&' );
			}

			// Store the logout redirect
			$_SESSION['oidcsso_logout_redirect'] = $logout;
		}
	}

	// Catch any errors
	catch ( \Exception $exception )
	{
		// Got Error
		logActivity( 'OIDC SSO Logout Exception: ' . $exception->getMessage() );

	}
});

/**
 * Client Registration
 */
add_hook('ClientAreaPageRegister', 1, function ($vars) {

	// Try and get our settings
	try
	{
		// Get our redirect URL
		$redirect = Setting::where( 'module', 'oidcsso' )->where( 'setting', 'redirectregistration' )->firstOrFail();

		// If we have a valid URL
		if ( isset( $redirect->value ) AND $redirect->value != NULL )
		{
			// Redirect to registration page
			header("Location: {$redirect->value}");
			exit;
		}
	}

	// Catch any errors
	catch ( \Exception $exception )
	{
		// Got Error
		logActivity( 'OIDC SSO Register Exception: ' . $exception->getMessage() );
	}
});

/**
 * Cart Page
 */
add_hook("ClientAreaPageCart",1, function($vars) {

	// If on the checkout page
	if ( $_GET['a'] == 'checkout' )
	{
		// If we have no client
		if ( !Menu::context('client') )
		{
			// Store our redirect url
			Cookie::set( 'OIDCRedirectUrl', $vars['systemurl'] . 'cart.php?a=checkout', strtotime( '+1 hour', time() ) );

			// Redirect to login
			header("Location: {$vars['systemurl']}clientarea.php");
			exit;
		}
	}
});

/**
 * Cart Page
 */
add_hook("ClientAreaPageAffiliates",1, function($vars) {

	// If no one logged in
	if ( !Menu::context('client') )
	{
		// Set a redirect URL and proceed to login
		Cookie::set( 'OIDCRedirectUrl', $vars['systemurl'] . 'affiliates.php', strtotime( '+1 hour', time() ) );
	}
});
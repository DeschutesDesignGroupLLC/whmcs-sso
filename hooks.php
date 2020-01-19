<?php

// Make sure we're not accessing directly
if ( !defined( "WHMCS" ) ) {
    die("This file cannot be accessed directly");
}

// Include our dependencies
include_once( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' );

use Jumbojett\OpenIDConnectClient;

/**
 * Client Area Hook
 */
add_hook('ClientAreaPage', 1, function( $vars ) {

    // If no user logged in
    if ( !$_SESSION['uid'] )
    {
        // Start our authentication code flow
        $oidc = new OpenIDConnectClient( 'https://sso.deschutesdesigngroup.com', CLIENT_ID, CLIENT_SECRET );
        $oidc->addScope( array( 'profile email openid' ) );
        $oidc->authenticate();

        // Get our email
        $email = $oidc->requestUserInfo('email');

        // Use AutoAuth to log the user in
        $timestamp = time();
        $hash = sha1( $email . $timestamp . AUTOAUTH_KEY );
        $url = $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'] . "/dologin.php?email=$email&timestamp=$timestamp&hash=$hash&goto=" . urlencode( 'clientarea.php?action=products' );
        header("Location: $url");
    }
});
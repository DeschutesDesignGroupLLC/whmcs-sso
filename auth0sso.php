<?php

// Make sure we're not accessing directly
if ( !defined( "WHMCS" ) ) {
    die("This file cannot be accessed directly");
}

/**
 * Configuration Settings
 *
 * @return array
 */
function auth0sso_config() {

    // Return our config settings
    return array(
        "name" => "Auth0 Single Sign-On Integration",
        "description" => "A plug and play Single Sign-On (SSO) application for WHMCS powered by Auth0.",
        "version" => "1.0",
        "author" => "Deschutes Design Group LLC",
        "language" => 'english',
        "fields" => array(
            "domain" => array(
                "FriendlyName" => "Auth0 Domain",
                "Type" => "text",
                "Size" => "25",
                "Description" => "<br>Your Auth0 application domain. If you are using a custom domain with Auth0, enter it here. Please make sure to include the scheme (http/s).",
                "Placeholder" => "www.example.auth0.com" ),
            "clientid" => array(
                "FriendlyName" => "Client ID",
                "Type" => "text",
                "Size" => "25",
                "Description" => "<br>Your Auth0 application Client ID." ),
            "clientsecret" => array(
                "FriendlyName" => "Client Secret",
                "Type" => "password",
                "Size" => "25",
                "Description" => "<br>Your Auth0 application Client Secret." ),
            "fulllogout"  => array(
                "FriendlyName" => "Full Logout",
                "Type" => "yesno",
                "Description" => "When a user logs out of WHMCS locally, also log out of Auth0." ),
            "logoutredirect" => array(
                "FriendlyName" => "Logout Redirect",
                "Type" => "text",
                "Size" => "25",
                "Description" => "<br>Enter the URL you would like a user redirected to after they logout. The URL must be configured in Auth0 under Allowed Logout URLs.",
                "Placeholder" => "www.example.com" ),
            "autoauth" => array(
                "FriendlyName" => "AutoAuth Key",
                "Type" => "password",
                "Size" => "25",
                "Description" => "<a target='_blank' href='https://docs.whmcs.com/AutoAuth'>Enable AutoAuth</a><br>Follow the linked instructions to enable Aut0Auth within WHMCS then enter the AutoAuth key here.")
    ));
}
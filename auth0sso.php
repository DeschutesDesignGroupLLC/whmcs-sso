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
                "Placeholder" => "www.example.auth0.com" ),
            "clientid" => array(
                "FriendlyName" => "Client ID",
                "Type" => "text",
                "Size" => "25" ),
            "clientsecret" => array(
                "FriendlyName" => "Client Secret",
                "Type" => "password",
                "Size" => "25" )
    ));
}
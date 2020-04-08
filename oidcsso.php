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
function oidcsso_config() {

    // Return our config settings
    return array(
        "name" => "OIDC Single Sign-On Integration",
        "description" => "A plug and play Single Sign-On (SSO) application for WHMCS powered by OIDC.",
        "version" => "1.0",
        "author" => "Deschutes Design Group LLC",
        "language" => 'english',
        "fields" => array(
            "provider" => array(
                "FriendlyName" => "Provider",
                "Type" => "text",
                "Size" => "25",
                "Description" => "<br>Your OIDC provider domain. Your OIDC provider needs to conform to OIDC Auto Discovery.",
            ),
            "clientid" => array(
                "FriendlyName" => "Client ID",
                "Type" => "text",
                "Size" => "25",
                "Description" => "<br>Your application Client ID."
            ),
            "clientsecret" => array(
                "FriendlyName" => "Client Secret",
                "Type" => "password",
                "Size" => "25",
                "Description" => "<br>Your application Client Secret."
            ),
	        "scopes" => array(
		        "FriendlyName" => "Scopes",
		        "Type" => "text",
		        "Size" => "25",
		        "Description" => "<br>Your application scopes to request. Please separate each scope with a comma - no whitespace."
	        ),
	        "disablessl" => array(
		        "FriendlyName" => "Disable SSL Verification",
		        "Type" => "yesno",
		        "Description" => "In some cases you may need to disable SSL security on your development systems. Note: This is not recommended on production systems."
	        )
        )
    );
}
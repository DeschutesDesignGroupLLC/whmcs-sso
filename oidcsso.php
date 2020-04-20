<?php

// Make sure we're not accessing directly
if ( !defined( "WHMCS" ) ) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

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

/**
 * Function to run when activating the addon
 *
 * @return string[]
 */
function oidcsso_activate()
{
	// Create our custom OIDC members table
	try
	{
		// Create table
		Capsule::schema()->create(
			'mod_oidcsso_members', function ($table) {
				$table->unsignedBigInteger('client_id', false);
				$table->mediumText('sub')->nullable()->default(NULL);
				$table->primary('client_id');
			}
		);

		// Return our message
		return [
			// Supported values here include: success, error or info
			'status' => 'success',
			'description' => 'The addon has been successfully activated.'
		];
	}

	// Catch our errors
	catch ( \Exception $exception )
	{
		// Return our message
		return [
			// Supported values here include: success, error or info
			'status' => 'error',
			'description' => "Unable to activate addon: {$exception->getMessage()}"
		];
	}
}

/**
 * Function to run when deactivating the addon
 *
 * @return string[]
 */
function oidcsso_deactivate()
{
	// Try and drop tables that were created when activating the addon
	try
	{
		// Drop our custom table
		Capsule::schema()->dropIfExists('mod_oidcsso_members');

		// Return our status
		return [
			// Supported values here include: success, error or info
			'status' => 'success',
			'description' => 'The addon has been successfully deactivated.'
		];
	}

	// Catch our errors
	catch ( \Exception $exception )
	{
		// Return our status
		return [
			// Supported values here include: success, error or info
			"status" => "error",
			"description" => "Unable to deactivate addon: {$exception->getMessage()}",
		];
	}
}
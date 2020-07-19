<?php

use WHMCS\Database\Capsule;

// Make sure we're not accessing directly
if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

/**
 * Configuration Settings
 * @return array
 */
function oidcsso_config() {

	// Return our config settings
	return array(
		"name" => "Single Sign-On with Okta",
		"description" => "A plug and play Single Sign-On (SSO) addon for WHMCS enabling your software to integrate with a OIDC equipped identity provider.",
		"version" => "1.2",
		"author" => "Deschutes Design Group LLC",
		"language" => 'english',
		"fields" => array(
			"provider" => array(
				"FriendlyName" => "Provider",
				"Type" => "text",
				"Size" => "25",
				"Description" => "<br>Your OIDC provider domain. This can be your Okta provided domain or a custom domain."
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
			),
			"redirectregistration" => array(
				"FriendlyName" => "Registration URL",
				"Type" => "text",
				"Size" => "25",
				"Description" => "<br>If provided, the client will be taken to this URL when attempting to create an account."),
			"redirectpassword" => array(
				"FriendlyName" => "Change Password URL",
				"Type" => "text",
				"Size" => "25",
				"Description" => "<br>If provided, the client will be taken to this URL to update their password."
			),
			"redirectlogout" => array(
				"FriendlyName" => "Logout URL",
				"Type" => "text",
				"Size" => "25",
				"Description" => "<br>If provided, the client will be taken to this URL when attempting to logout."
			)
		)
	);
}

/**
 * Function to run when activating the addon
 * @return string[]
 */
function oidcsso_activate() {

	// Create our custom OIDC members table
	try {

		// Create table
		Capsule::schema()->create('mod_okta_members', function ($table) {

			$table->unsignedBigInteger('client_id', FALSE);
			$table->mediumText('sub')->nullable()->default(NULL);
			$table->mediumText('access_token')->nullable()->default(NULL);
			$table->mediumText('id_token')->nullable()->default(NULL);
			$table->smallInteger('onboarded')->default(0);
			$table->primary('client_id');
		});

		// Return our message
		return [

			// Supported values here include: success, error or info
			'status' => 'success', 'description' => 'The addon has been successfully activated.'
		];
	}

	// Catch our errors
	catch (\Exception $exception) {

		// Return our message
		return [

			// Supported values here include: success, error or info
			'status' => 'error', 'description' => "Unable to activate addon: {$exception->getMessage()}"
		];
	}
}

/**
 * Function to run when deactivating the addon
 * @return string[]
 */
function oidcsso_deactivate() {

	// Try and drop tables that were created when activating the addon
	try {

		// Drop our custom table
		Capsule::schema()->dropIfExists('mod_okta_members');

		// Return our status
		return [

			// Supported values here include: success, error or info
			'status' => 'success', 'description' => 'The addon has been successfully deactivated.'
		];
	}

	// Catch our errors
	catch (\Exception $exception) {

		// Return our status
		return [

			// Supported values here include: success, error or info
			"status" => "error", "description" => "Unable to deactivate addon: {$exception->getMessage()}"
		];
	}
}

/**
 * Upgrader function
 *
 * @param $vars
 */
function oidcsso_upgrade($vars) {

	// Try to perform these upgrades
	try {

		// Get the currently installed version
		$currentlyInstalledVersion = $vars['version'];

		// Perform SQL schema changes required by the upgrade to version 1.1 of your module
		if ($currentlyInstalledVersion < 1.1) {

			// Get the schema
			$schema = Capsule::schema();

			// Add an onboarded column
			$schema->table('mod_okta_members', function ($table) {
				$table->smallInteger('onboarded')->default(0);
			});
		}

		// Perform SQL schema changes required by the upgrade to version 1.2 of your module
		if ($currentlyInstalledVersion < 1.2) {

			// Rename the members table
			Capsule::schema()->rename('mod_oidcsso_members', 'mod_okta_members');
		}
	}

	// Catch our exceptions
	catch (\Exception $exception) {

		// Log the exception
		logActivity('Okta SSO: Upgrade Exception - ' . $exception->getMessage());
	}
}
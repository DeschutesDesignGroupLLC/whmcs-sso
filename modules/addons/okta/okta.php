<?php

// Make sure we're not accessing directly
if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\User\Client;

/**
 * Configuration Settings
 * @return array
 */
function okta_config() {

	// Return our config settings
	return array(
		"name" => "Single Sign-On with Okta",
		"description" => "A plug and play Single Sign-On (SSO) addon for WHMCS enabling your software to sign-in with Okta.",
		"version" => "1.0.6",
		"author" => "Deschutes Design Group LLC",
		"language" => 'english',
		"fields" => array(
			"provider" => array(
				"FriendlyName" => "Provider",
				"Type" => "text",
				"Size" => "25",
				"Placeholder" => 'https://yourdomain.okta.com/oauth2/default',
				"Description" => "<br>Your authorization server domain. This can be your Okta provided domain or a custom domain."
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
				"Placeholder" => "profile,email",
				"Description" => "<br>Your application scopes to request. Please separate each scope with a comma - no whitespace. (The request will include 'openid' be default)."
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
function okta_activate() {

	// Create our custom Okta members table
	try {

		// Create table
		Capsule::schema()->create('mod_okta_members', function ($table) {
			$table->unsignedBigInteger('user_id', FALSE);
			$table->mediumText('sub')->nullable()->default(NULL);
			$table->mediumText('access_token')->nullable()->default(NULL);
			$table->mediumText('id_token')->nullable()->default(NULL);
			$table->primary('user_id');
		});

		// Return our message
		return [
			'status' => 'success',
			'description' => 'The addon has been successfully activated.'
		];
	}

	// Catch our errors
	catch (\Exception $exception) {

		// Return our message
		return [
			'status' => 'error',
			'description' => "Unable to activate addon: {$exception->getMessage()}"
		];
	}
}

/**
 * Function to run when deactivating the addon
 * @return string[]
 */
function okta_deactivate() {

	// Try and drop tables that were created when activating the addon
	try {

		// Drop our custom table
		Capsule::schema()->dropIfExists('mod_okta_members');

		// Return our status
		return [
			'status' => 'success',
			'description' => 'The addon has been successfully deactivated.'
		];
	}

	// Catch our errors
	catch (\Exception $exception) {

		// Return our status
		return [
			"status" => "error",
			"description" => "Unable to deactivate addon: {$exception->getMessage()}"
		];
	}
}

/**
 * Upgrader function
 *
 * @param $vars
 */
function okta_upgrade($vars) {

	// Try to perform these upgrades
	try {

		// Get the currently installed version
		$currentlyInstalledVersion = $vars['version'];

		// If upgrading to version 1.0.4
		if (version_compare($currentlyInstalledVersion, '1.0.4') < 0) {

			// Add new user id column
			Capsule::schema()->table('mod_okta_members', function ($table) {
				$table->unsignedBigInteger('user_id', FALSE);
			});
		}

		// If upgrading to version 1.0.5
		if (version_compare($currentlyInstalledVersion, '1.0.5') < 0) {

			// Convert all Client IDs to User ID's
			Capsule::table('mod_okta_members')->where('user_id', '0')->orderBy('client_id')->chunk(100, function ($rows) {
				foreach ($rows as $row) {
					try {
						$client = Client::where('id', $row->client_id)->firstOrFail();
						Capsule::table('mod_okta_members')->where('client_id', $row->client_id)->update([
							'user_id' => $client->owner()->id
						]);
					} catch (\Exception $exception) {}
				}
			});

			// Drop table
			if (Capsule::schema()->hasColumn('mod_okta_members', 'client_id')) {
				Capsule::schema()->table('mod_okta_members', function ($table) {
					$table->dropColumn('client_id');
				});
			}

			// Drop table
			if (Capsule::schema()->hasColumn('mod_okta_members', 'onboarding')) {
				Capsule::schema()->table('mod_okta_members', function ($table) {
					$table->dropColumn('onboarding');
				});
			}

			// Assign primary to user_id
			Capsule::schema()->table('mod_okta_members', function ($table) {
				$table->primary('user_id');
			});
		}
	}

	// Catch our exceptions
	catch (\Exception $exception) {

		// Log the exception
		logActivity('Okta SSO: Upgrade Exception - ' . $exception->getMessage());
	}
}

/**
 * Admin Area Output
 *
 * @param $vars
 */
function okta_output($vars) {

	// If we have an unlink action
	if ($_GET['action'] === 'unlink') {

		// If we have clients to unlink
		if ($_POST['selectedclients'] OR $_GET['userid']) {

			// Compose array of clients to delete
			$delete = array_filter(array_merge(is_array($_POST['selectedclients']) ? array_values($_POST['selectedclients']) : array(), array($_GET['userid'])));

			// Try and delete the members
			try {

				// Delete the members
				Capsule::table('mod_okta_members')->whereIn('user_id', $delete)->delete();

				// Redirect to reset
				header('Location: /admin/addonmodules.php?module=okta');
				exit;
			}

			// We got an error
			catch ( \Exception $exception)
			{
				// Log the error
				logActivity('Okta SSO: Unlink Exception - ' . $exception->getMessage());
			}
		}
	}

	// Output our table - start
	echo '<script type="text/javascript" src="/assets/js/jquerytt.js"></script>';
	echo "<form method='post' action='/admin/addonmodules.php?module=okta&action=unlink'>";
	echo '<div class="tablebg"><table id="sortabletbl0" class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3"><tbody>';
	echo '<tr><th width="1%"><input type="checkbox" id="checkall0" data-ol-has-click-handler=""></th><th width="5%"><a href="/admin/addonmodules.php?module=okta&orderby=id">ID</a> <img src="images/desc.gif" class="absmiddle"></th><th width="10%"><a href="/admin/addonmodules.php?module=okta&orderby=firstname">First Name</a></th><th width="10%"><a href="/admin/addonmodules.php?module=okta&orderby=lastname">Last Name</a></th><th width="20%"><a href="/admin/addonmodules.php?module=okta&orderby=email">Email</a></th><th width="15%"><a href="/admin/addonmodules.php?module=okta&orderby=sub">Sub</a></th><th width="30%"><a href="/admin/addonmodules.php?module=okta&orderby=access_token">Access Token</a></th></tr>';

	// Get our client login links
	foreach (Capsule::table('mod_okta_members')->join('tblusers', 'mod_okta_members.user_id', '=', 'tblusers.id')->get() as $link) {

		// Print a row
		echo "<tr>";
		echo "<td><input type='checkbox' name='selectedclients[]' value='$link->user_id' class='checkall'></td>";
		echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->user_id</a></td>";
		echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->first_name</a></td>";
		echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->last_name</a></td>";
		echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->email</a></td>";
		echo "<td>$link->sub</td>";
		echo "<td style='max-width: 100px'><span class='truncate' style='display: block;'>$link->access_token</span></td>";
		echo "</tr>";
	}

	// Output the table - end
	echo '</tbody></table></div>';

	// Output button
	echo 'With Selected: ';
	echo '<input type="submit" value="Unlink Okta Account" class="btn btn-danger">';
	echo '</form>';
}
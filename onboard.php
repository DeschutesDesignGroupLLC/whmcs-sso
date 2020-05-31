<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

define('CLIENTAREA', true);

require __DIR__ . '/init.php';

/**
 * Start the Client Area
 */
$ca = new ClientArea();

/**
 * Set Our Page Title
 */
$ca->setPageTitle('Finish Account Setup');

/**
 * Add Some Breadcrumbs
 */
$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('onboard.php', 'Your Custom Page Name');

/**
 * Initiate the page
 */
$ca->initPage();

/**
 * Require Login
 */
$ca->requireLogin(); // Uncomment this line to require a login to access this page

/**
 * If Logged In
 */
if ($ca->isLoggedIn()) {

	// If we submitted the form
	$action = isset($_REQUEST['action']) AND $_REQUEST['action'] == 'submit' ? $_REQUEST['action'] : NULL;

	// If we have an action
	if ( $action ) {

		// Create our client data array
		$data = array(
			'clientid' => $ca->getUserID(),
			'firstname' => $_POST['firstname'],
			'lastname' => $_POST['lastname'],
			'companyname' => $_POST['companyname'],
			'address1' => $_POST['address1'],
			'address2' => $_POST['address2'],
			'city' => $_POST['city'],
			'state' => $_POST['state'],
			'postcode' => $_POST['postcode'],
			'phonenumber' => $_POST['phonenumber']
		);

		// Update the client
		$results = localAPI('UpdateClient', $data);

		// Set onboarded flag
		Capsule::table('mod_oidcsso_members')->where('client_id', '=', $ca->getUserID())->update(['onboarded' => 1]);

		// Forward to client area
		header('Location: clientarea.php');
	}

	// Form isn't submitted
	else
	{
		/**
		 * See if they still need to onboard
		 */
		$sso_member = Capsule::table('mod_oidcsso_members')->where('client_id', '=', $ca->getUserID())->first();

		// If they haven't onboarded
		if (!$sso_member->onboarded) {

			/**
			 * Get the Client
			 */
			$client = Capsule::table('tblclients')->where('id', '=', $ca->getUserID())->first();

			// If we get a Client
			if ( $client->id )
			{
				/**
				 * Assign Template Variables
				 */
				$ca->assign('clientfirstname', $client->firstname);
				$ca->assign('clientlastname', $client->lastname);
				$ca->assign('clientcompanyname', $client->companyname);
				$ca->assign('clientemail', $client->email);
				$ca->assign('clientphonenumber', $client->phonenumber);
				$ca->assign('clientaddress1', $client->address1);
				$ca->assign('clientaddress2', $client->address2);
				$ca->assign('clientcity', $client->city);
				$ca->assign('clientstate', $client->state);
				$ca->assign('clientpostcode', $client->postcode);
			}

			// Didnt find the client
			else {

				// Redirect to Client Area
				header('Location: clientarea.php');
			}
		}

		// Didnt find the client
		else {

			// Redirect to Client Area
			header('Location: clientarea.php');
		}
	}
}

/**
 * Not Logged In
 */
else {

	// Redirect to client area
	header('Location: clientarea.php');
}

/**
 * Set the template
 */
$ca->setTemplate('/modules/addons/oidcsso/templates/onboard.tpl');

/**
 * Output the page
 */
$ca->output();
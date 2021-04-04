<?php

define('CLIENTAREA', true);

require "init.php";
require "includes/clientfunctions.php";

// Include our dependencies
include_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

use League\Uri\Components\Query;
use League\Uri\Uri;
use Ramsey\Uuid\Uuid;
use WHMCS\ClientArea;
use WHMCS\Cookie;
use WHMCS\Database\Capsule;
use WHMCS\User\Client;
use WHMCS\User\User;

// Create a new client area object
$ca = new ClientArea();

// Initiate the page
$ca->initPage();

// Add some breadcrumbs
$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));

// Get our countries list
$ca->assign("clientcountriesdropdown", getCountriesDropDown());

// Check if we have an error to interpret
if ($_GET['error']) {

	// Set the page title
	$ca->setPageTitle('Error');

	// Add some breadcrumbs
	$ca->addToBreadCrumb('onboard.php', 'Error');

	// Set error
	$ca->assign('errormessage', $_GET['error']);

	// Assign the page template
	$ca->setTemplate('/modules/addons/okta/templates/error.tpl');

	// Output the page
	$ca->output();
}

// Perform our cookie checks
if ($cookie = Cookie::get('OktaOnboarding')) {

	// Decode the cookie
	$onboard = json_decode(base64_decode($cookie), FALSE);

	// Make sure we have our data
	if (!property_exists($onboard, 'userinfo') || !property_exists($onboard, 'user') || !property_exists($onboard, 'access_token') || !property_exists($onboard, 'id_token')) {

		// Compose our error URL
		$error = Uri::createFromString()->withPath('onboard.php')->withQuery(Query::createFromParams([
			'error' => 'Unable to retrieve userdata. Please try logging in again.'
		]))->__toString();

		// Throw error
		header("Location: {$error}");
		exit;
	}
}

// We don't have the proper cookie to work with
else {

	// Compose our error URL
	$error = Uri::createFromString()->withPath('onboard.php')->withQuery(Query::createFromParams([
		'error' => 'Unable to retrieve userdata. Please try logging in again.'
	]))->__toString();

	// Throw error
	header("Location: {$error}");
	exit;
}

// If we submitted the form
$action = isset($_REQUEST['action']) AND $_REQUEST['action'] === 'submit' ? $_REQUEST['action'] : NULL;

// If we have an action
if ($action) {

	// Create our client data array
	$data = array(
		'firstname' => $_POST['firstname'],
		'lastname' => $_POST['lastname'],
		'companyname' => $_POST['companyname'],
		'email' => $onboard->userinfo->email,
		'address1' => $_POST['address1'],
		'address2' => $_POST['address2'],
		'city' => $_POST['city'],
		'state' => $_POST['state'],
		'postcode' => $_POST['postcode'],
		'country' => $_POST['country'],
		'phonenumber' => $_POST['phonenumber'],
		'password2' => Uuid::uuid4()->toString()
	);

	// If we have a user account
	if ($onboard->user) {

		// Attach the user as the owner
		$data['owner_user_id'] = $onboard->user;
	}

	// Fire the API request
	$result = localAPI('AddClient', $data);

	// If we successfully created the user
	if ($result['result'] === 'success' && $result['clientid']) {

		// Try to get our client
		try {

			// Determine which user id to user
			$userId = $onboard->user ?? $result['owner_id'];

			// Set their onboard flag
			Capsule::table('mod_okta_members')->updateOrInsert([
				'user_id' => $userId
			],[
				'sub' => $onboard->userinfo->sub,
				'access_token' => $onboard->access_token,
				'id_token' => $onboard->id_token
			]);

			// Delete the onboarding cookie
			Cookie::delete('OktaOnboarding');

			// Log the activity
			$message = sprintf('Okta SSO: %s %s has finished %s', $data['firstname'], $data['lastname'], $_GET['type'] === 'update' ? 'verifying their account.' : 'onboarding.');
			logActivity($message, $userId);

			// Create our client services URL
			$clientservices = Uri::createFromString()->withPath('clientarea.php')->withQuery(Query::createFromParams([
				'action' => 'services'
			]))->__toString();

			// Redirect to login
			header("Location: {$clientservices}");
			exit;
		}

		// Catch any exception
		catch (\Exception $exception) {

			// Set error
			$result['message'] = 'Unable to find user account.';
		}
	}

	// If we have an AddClient API error
	// Set error
	$ca->assign('errormessage', $result['message']);
}

// Set the page title
$ca->setPageTitle('Onboarding');

// Add some breadcrumbs
$ca->addToBreadCrumb('onboard.php', 'Onboarding');

// Let the form know this is an create request
$ca->assign('actiontype', 'add');

// Set the alert information text
$ca->assign('infomessage', 'We need a little bit more information to create your client account. Please fill in the fields below to finish your account setup.');

// Assign the email var
$ca->assign('clientemail', $onboard->userinfo->email);

// Assign our userinfo
$ca->assign('clientfirstname', $onboard->userinfo->given_name ?? NULL);
$ca->assign('clientlastname', $onboard->userinfo->family_name ?? NULL);

// Set the template
$ca->setTemplate('/modules/addons/okta/templates/onboard.tpl');

// Output the page
$ca->output();
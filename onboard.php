<?php

define('CLIENTAREA', true);

require "init.php";
require "includes/clientfunctions.php";

use WHMCS\User\Client;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Cookie;
use Ramsey\Uuid\Uuid;

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
	$ca->setTemplate('/modules/addons/oidcsso/templates/error.tpl');

	// Output the page
	$ca->output();
}

// Perform our cookie checks
if ($cookie = Cookie::get('OIDCOnboarding')) {

	// Decode the cookie
	$onboard = json_decode(base64_decode($cookie));

	// Make sure we have our data
	if (!property_exists($onboard, 'userinfo') OR !property_exists($onboard, 'client') OR !property_exists($onboard, 'access_token') OR !property_exists($onboard, 'id_token')) {

		// Throw error
		header('Location: onboard.php?error=Unable%20to%20retrieve%20user%20data.%20Please%20try%20logging%20in%20again.');
	}
}

// We don't have the proper cookie to work with
else {

	// Throw error
	header('Location: onboard.php?error=Unable%20to%20retrieve%20user%20data.%20Please%20try%20logging%20in%20again.');
}

// If we submitted the form
$action = isset($_REQUEST['action']) AND $_REQUEST['action'] == 'submit' ? $_REQUEST['action'] : NULL;

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

	// If this is an update
	if ($_GET['type'] == 'update') {

		// Add the client id to the request
		$data['clientid'] = $onboard->client;

		// Fire the API request
		$result = localAPI('UpdateClient', $data, 'Jon Erickson');
	}

	// This is an add/create
	else {

		// Fire the API request
		$result = localAPI('AddClient', $data, 'Jon Erickson');
	}

	// If we successfully created the user
	if ($result['result'] == 'success' and $result['clientid']) {

		// Set their onboard flag
		Capsule::insert("INSERT INTO `mod_okta_members` (client_id,sub,access_token,id_token,onboarded) VALUES ('{$result['clientid']}', '{$onboard->userinfo->sub}', '{$onboard->access_token}', '{$onboard->id_token}', 1) ON DUPLICATE KEY UPDATE sub = '{$onboard->userinfo->sub}', access_token = '{$onboard->access_token}', id_token = '{$onboard->id_token}', onboarded = 1");

		// Delete the onboarding cookie
		Cookie::delete('OIDCOnboarding');

		// Log the activity
		$message = sprintf('Okta SSO: %s %s has finished %s', $data['firstname'], $data['lastname'], $_GET['type'] == 'update' ? 'verifying their account.' : 'onboarding.');
		logActivity($message, $result['clientid']);

		// Redirect to the client area page so we can officially log in
		header('Location: clientarea.php');
		exit;
	}

	// If we have an AddClient API error
	else {

		// Set error
		$ca->assign('errormessage', $result['message']);
	}
}

// If the client is already registered
if ($onboard->client) {

	// Set the page title
	$ca->setPageTitle('Verify Your Account');

	// Add some breadcrumbs
	$ca->addToBreadCrumb('onboard.php', 'Verify Your Account');

	// Let the form know this is an update request
	$ca->assign('actiontype', 'update');

	// Set the alert information text
	$ca->assign('infomessage', 'We found your account. Please verify your information is correct before proceeding.');

	// Try to load the client
	try {

		// Try to load the client
		$client = Client::findOrFail($onboard->client);

		// Set our template variables
		$ca->assign('clientfirstname', $client->firstname);
		$ca->assign('clientcompanyname', $client->companyname);
		$ca->assign('clientlastname', $client->lastname);
		$ca->assign('clientemail', $client->email);
		$ca->assign('clientaddress1', $client->address1);
		$ca->assign('clientaddress2', $client->address2);
		$ca->assign('clientcity', $client->city);
		$ca->assign('clientstate', $client->state);
		$ca->assign('clientpostcode', $client->postcode);
		$ca->assign('clientcountry', $client->country);
		$ca->assign('clientphonenumber', $client->phonenumber);
	}

	// Unable to find the client
	catch (\Exception $exception) {

	}
}

// This is a new user
else {

	// Set the page title
	$ca->setPageTitle('Onboarding');

	// Add some breadcrumbs
	$ca->addToBreadCrumb('onboard.php', 'Onboarding');

	// Let the form know this is an create request
	$ca->assign('actiontype', 'add');

	// Set the alert information text
	$ca->assign('infomessage', 'We need a litte bit more information to create your account. Please fill in the fields below to finish account setup.');

	// Assign the email var
	$ca->assign('clientemail', $onboard->userinfo->email);

	// Assign our userinfo
	$ca->assign('clientfirstname', isset($onboard->userinfo->given_name) ? $onboard->userinfo->given_name : NULL);
	$ca->assign('clientlastname', isset($onboard->userinfo->family_name) ? $onboard->userinfo->family_name : NULL);
}

// Set the template
$ca->setTemplate('/modules/addons/oidcsso/templates/onboard.tpl');

// Output the page
$ca->output();
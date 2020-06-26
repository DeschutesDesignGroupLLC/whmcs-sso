<?php

define('CLIENTAREA', true);

require "init.php";
require "includes/clientfunctions.php";

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Cookie;
use Ramsey\Uuid\Uuid;

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
$ca->addToBreadCrumb('onboard.php', 'Profile Setup');

/**
 * Initiate the page
 */
$ca->initPage();

// Get the user's email
$email = Cookie::get('OIDCOnboarding');

// If we have an email to work with
if ($email) {

	// Assign the email var
	$ca->assign('clientemail', $email);
	$ca->assign("clientcountriesdropdown", getCountriesDropDown());

	// If we submitted the form
	$action = isset($_REQUEST['action']) AND $_REQUEST['action'] == 'submit' ? $_REQUEST['action'] : NULL;

	// If we have an action
	if ( $action ) {

		// Create our client data array
		$data = array(
			'firstname' => $_POST['firstname'],
			'lastname' => $_POST['lastname'],
			'companyname' => $_POST['companyname'],
			'email' => $email,
			'address1' => $_POST['address1'],
			'address2' => $_POST['address2'],
			'city' => $_POST['city'],
			'state' => $_POST['state'],
			'postcode' => $_POST['postcode'],
			'country' => $_POST['country'],
			'phonenumber' => $_POST['phonenumber'],
			'password2' => Uuid::uuid4()->toString()
		);

		// Compose an array of errors and perform our validation checks
		$errors = array();
		$_POST['firstname'] ?: $errors[] = 'You did not enter your first name.';
		$_POST['lastname'] ?: $errors[] = 'You did not enter your last name.';
		$_POST['address1'] ?: $errors[] = 'You did not enter your street address.';
		$_POST['city'] ?: $errors[] = 'You did not enter your city.';
		$_POST['state'] ?: $errors[] = 'You did not enter your state.';
		$_POST['postcode'] ?: $errors[] = 'You did not enter your zip code.';
		$_POST['country'] ?: $errors[] = 'You did not enter your country.';
		$_POST['phonenumber'] ?: $errors[] = 'You did not enter your phone number.';

		// If we got a successful response
		if (empty($errors)) {

			// Add the client
			$add = localAPI('AddClient', $data, 'Jon Erickson' );

			// If we successfully created the user
			if ($add['result'] == 'success' AND $add['clientid']) {

				// Create an SSO login
				$sso = localAPI('CreateSsoToken', ['client_id' => $add['clientid'], 'destination' => 'clientarea:services'], 'Jon Erickson');

				// If the result was successful
				if ($sso['result'] == 'success') {

					// If we get a redirect URL
					if (key_exists( 'redirect_url', $sso))
					{
						// Redirect the user
						header("Location: {$sso['redirect_url']}");
						exit;
					}
				}

				// If we have an CreateSsoToken API error
				else {

					// Add the error
					$errors[] = $sso['error'];
				}
			}

			// If we have an AddClient API error
			else {

				// Add the error
				$errors[] = $add['error'];
			}
		}

		// If we have some errors
		if (!empty($errors)) {

			// Set error
			$ca->assign('errormessage', implode( '<br />', $errors));
		}
	}
}

// We don't have a user email stored in a cooke
else {

}

/**
 * Set the template
 */
$ca->setTemplate('/modules/addons/oidcsso/templates/onboard.tpl');

/**
 * Output the page
 */
$ca->output();
<?php

// Make sure we're not accessing directly
if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

// Include our dependencies
include_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;
use WHMCS\Module\Addon\Setting;
use WHMCS\User\Client;
use WHMCS\Database\Capsule;
use WHMCS\Cookie;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Client Area Head Output
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {

	// Do not allow robots to index and follow links from the client area
	return <<<HTML
	<meta name="robots" content="noindex, nofollow">
HTML;

});

/**
 * Client Area Login Hook
 */
add_hook('ClientAreaPageLogin', 1, function ($vars) {

	// If no user logged in
	if (!Menu::context('client')) {

		// Try and get our settings
		try {

			// Get our domain
			$provider = Setting::where('module', 'okta')->where('setting', 'provider')->firstOrFail();
			$clientid = Setting::where('module', 'okta')->where('setting', 'clientid')->firstOrFail();
			$clientsecret = Setting::where('module', 'okta')->where('setting', 'clientsecret')->firstOrFail();
			$scopes = Setting::where('module', 'okta')->where('setting', 'scopes')->firstOrFail();
			$disablessl = Setting::where('module', 'okta')->where('setting', 'disablessl')->firstOrFail();
			$skiponboarding = Setting::where('module', 'okta')->where('setting', 'skiponboarding')->firstOrFail();

			// Get scopes
			$scopes = explode(',', $scopes->value);

			// Start our authentication code flow
			$oidc = new OpenIDConnectClient($provider->value, $clientid->value, $clientsecret->value);

			// If this is the beginning of the authorization request and we don't have an authorization code yet
			if (!$_REQUEST['code']) {

				// Get our referrer
				getReferer();
			}

			// Set our redirect URL
			$oidc->setRedirectURL((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . '/clientarea.php');

			// Add scopes
			$oidc->addScope($scopes);

			// Set our URL encoding
			$oidc->setUrlEncoding(PHP_QUERY_RFC1738);

			// If disable ssl verification
			if ($disablessl->value) {

				// Disable SSL
				$oidc->setVerifyHost(FALSE);
				$oidc->setVerifyPeer(FALSE);
			}

			// Log our auth call
			logModuleCall('okta', 'authorize', array('provider' => $oidc->getProviderURL(), 'client_id' => $oidc->getClientID(), 'redirect_url' => $oidc->getRedirectURL(), 'scope' => $oidc->getScopes()), array('access_token' => $oidc->getAccessToken(), 'id_token' => $oidc->getIdToken()), NULL, NULL);

			// Start auth process
			$oidc->authenticate();

			// Get the subject from the ID token
			$token = $oidc->getIdTokenPayload();

			// Get our user info
			$userinfo = $oidc->requestUserInfo();

			// Log our auth call
			logModuleCall('okta', 'userinfo', array('provider' => $oidc->getProviderURL(), 'access_token' => $oidc->getAccessToken(), 'client_id' => $oidc->getClientID()), array('id_token' => $oidc->getIdToken(), 'user_info' => $userinfo), NULL, NULL);

			// Try and see if this user has already logged in
			try {

				// Query the database for a previous login links
				$member = Capsule::table('mod_okta_members')->where('sub', $token->sub)->first();

				// We found a member, try and load the associated client
				$client = Client::findOrFail($member->client_id);
			}

			// Unable to find client
			catch (\Exception $exception) {

				// We couldn't load a login link, so try and find the user by their email
				$client = Client::where('email', $userinfo->email)->get()->first();
			}

			// If we are skipping onboarding and we don't have a client
			if ($skiponboarding->value AND !$client) {

				// Create a client and sign them in
				$client = Client::firstOrNew([ 'email' => $userinfo->email ]);

				// If the user did not exist
				if ( !$client->exists )
				{
					// If the client did not exist
					$client->email = $userinfo->email;
					$client->firstname = $userinfo->given_name ? $userinfo->given_name : 'New';
					$client->lastname = $userinfo->family_name ? $userinfo->family_name : 'User';
					$client->created_at = time();
					$client->updated_at = time();
					$client->datecreated = date("Y-m-d", time());
					$client->email_verified = 1;
					$client->allow_sso = 1;
					$client->save();
				}
			}

			// We are not skipping onboarding
			else {

				// If we got a client and they havent onboarded, got a client and they didnt have a login link yet or we didnt find a client, and we are not skipping the process
				if ((!$client OR ($member AND !$member->onboarded) OR ($client AND !$member)) AND !$skiponboarding->value) {

					// Create our onboarding data we'll pass in a cookie
					$onboard = array(
						'userinfo' => $userinfo,
						'client' => $client ? $client->id : NULL,
						'access_token' => $oidc->getAccessToken(),
						'id_token' => $oidc->getIdToken()
					);

					// Store the users email address
					Cookie::set('OktaOnboarding', base64_encode(json_encode($onboard)), strtotime('+1 hour', time()));

					// Redirect to change password
					header("Location: onboard.php");
					exit;
				}
			}

			// If we get a client
			if ($client) {

				// Try and create an SSO token to log the user in
				try {

					// Add our SSO link
					Capsule::insert("INSERT INTO `mod_okta_members` (client_id,sub,access_token,id_token) VALUES ('{$client->id}','{$token->sub}','{$oidc->getAccessToken()}','{$oidc->getIdToken()}') ON DUPLICATE KEY UPDATE sub = '{$token->sub}', access_token = '{$oidc->getAccessToken()}', id_token = '{$oidc->getIdToken()}'");

					// Compose our SSO payload
					$sso = array(
						'client_id' => $client->id,
						'destination' => 'clientarea:services');

					// If we have a redirect URL set
					if (Cookie::get('OktaReferer')) {

						// Set our destination to custom
						$sso['destination'] = 'sso:custom_redirect';
						$sso['sso_redirect_path'] = base64_decode(Cookie::get('OktaReferer'));

						// Remove the cookie
						Cookie::delete('OktaReferer');
					}

					// Create an SSO login
					$results = localAPI('CreateSsoToken', $sso, 'Jon Erickson');

					// Log our API call
					logModuleCall('okta', 'CreateSsoToken', $sso, $results, NULL, NULL);

					// If the result was successful
					if ($results['result'] == 'success') {

						// If we get a redirect URL
						if (key_exists('redirect_url', $results)) {

							// Redirect the user
							header("Location: {$results['redirect_url']}");
							exit;
						}
					}

					// We got an error
					else {

						// Log our errors
						logActivity('Okta SSO: WHMCS Local API Error - ' . $results['message']);

						// Forward to error page
						header('Location: onboard.php?error=' . $exception->getMessage() );
					}
				}

				// Catch an exception
				catch (Exception $exception) {

					// Log our errors
					logActivity('Okta SSO: WHMCS Login Exception - ' . $exception->getMessage());

					// Forward to error page
					header('Location: onboard.php?error=' . $exception->getMessage() );
				}
			}
		}

		// Catch our exceptions if we cant find any settings
		catch (ModelNotFoundException $exception) {

			// Log our errors
			logActivity('Okta SSO: Model Exception - ' . $exception->getMessage());

			// Forward to error page
			header('Location: onboard.php?error=' . $exception->getMessage() );
		}

		// Catch our exceptions if we cant create an OIDC client object
		catch (OpenIDConnectClientException $exception) {

			// Log our errors
			logActivity('Okta SSO: Client Exception - ' . $exception->getMessage());

			// Forward to error page
			header('Location: onboard.php?error=' . $exception->getMessage() );
		}

		// Catch any exception
		catch (Exception $exception) {

			// Log our errors
			logActivity('Okta SSO: Exception - ' . $exception->getMessage());

			// Forward to error page
			header('Location: onboard.php?error=' . $exception->getMessage() );
		}
	}
});

/**
 * Delete Client
 */
add_hook('ClientDelete', 1, function ($vars) {

	// Try and delete all SSO login links
	try {

		// Delete all SSO login links
		Capsule::table('mod_okta_members')->where('client_id', '=', $vars['userid'])->delete();
	}

	// Catch any exceptions
	catch (\Exception $e) {}
});

/**
 * Client Password Reset
 */
add_hook('ClientAreaPagePasswordReset', 1, function ($vars) {

	// Try and get our settings
	try {

		// Get our redirect URL
		$redirect = Setting::where('module', 'okta')->where('setting', 'redirectpassword')->firstOrFail();

		// If we have a valid URL
		if (isset($redirect->value) and $redirect->value != NULL) {

			// Redirect to change password
			header("Location: {$redirect->value}");
			exit;
		}
	}

	// Catch any errors
	catch (\Exception $exception) {

		// Got Error
		logActivity('Okta SSO: Reset Password Exception - ' . $exception->getMessage());
	}
});

/**
 * Client Change Password
 */
add_hook('ClientAreaPageChangePassword', 1, function ($vars) {

	// Try and get our settings
	try {

		// Get our redirect URL
		$redirect = Setting::where('module', 'okta')->where('setting', 'redirectpassword')->firstOrFail();

		// If we have a valid URL
		if (isset($redirect->value) and $redirect->value != NULL) {

			// Redirect to change password
			header("Location: {$redirect->value}");
			exit;
		}
	}

	// Catch any errors
	catch (\Exception $exception) {

		// Got Error
		logActivity('Okta SSO: Change Password Exception - ' . $exception->getMessage());
	}
});

/**
 * Client Logout Page
 */
add_hook('ClientAreaPageLogout', 1, function ($vars) {

	// If the logout redirect URL is set
	if (isset($_SESSION['oktasso_logout_redirect'])) {

		// Redirect
		header("Location: {$_SESSION['oktasso_logout_redirect']}");
		unset($_SESSION['oktasso_logout_redirect']);
		exit;
	}
});

/**
 * Client Logout
 */
add_hook('ClientLogout', 1, function ($vars) {

	// Try and get our settings
	try {

		// Get our logout URL
		$redirect = Setting::where('module', 'okta')->where('setting', 'redirectlogout')->firstOrFail();

		// If we have a valid URL
		if (isset($redirect->value) and $redirect->value != NULL) {

			// Get the member who is logging out
			$member = Capsule::table('mod_okta_members')->where('client_id', $vars['userid'])->first();

			// Construct the URL
			$logout = $redirect->value;

			// If we are appending the token
			if ($member->id_token) {

				// Attach the ID token
				$logout = rtrim($logout, "/") . http_build_query(['id_token_hint' => $member->id_token], '', '&');
			}

			// Store the logout redirect
			$_SESSION['oktasso_logout_redirect'] = $logout;
		}
	}

	// Catch any errors
	catch (\Exception $exception) {

		// Got Error
		logActivity('Okta SSO: Logout Exception - ' . $exception->getMessage());
	}
});

/**
 * Client Registration
 */
add_hook('ClientAreaPageRegister', 1, function ($vars) {

	// Try and get our settings
	try {

		// Get our redirect URL
		$redirect = Setting::where('module', 'okta')->where('setting', 'redirectregistration')->firstOrFail();

		// If we have a valid URL
		if (isset($redirect->value) and $redirect->value != NULL) {

			// Redirect to registration page
			header("Location: {$redirect->value}");
			exit;
		}
	}

	// Catch any errors
	catch (\Exception $exception) {

		// Got Error
		logActivity('Okta SSO: Register Exception - ' . $exception->getMessage());
	}
});

/**
 * Cart Page
 */
add_hook("ClientAreaPageCart", 1, function ($vars) {

	// If on the checkout page
	if ($_GET['a'] == 'checkout') {

		// If we have no client
		if (!Menu::context('client')) {

			// Store our redirect url
			Cookie::set('OktaRedirectUrl', base64_encode('cart.php'), strtotime('+1 hour', time()));

			// Redirect to login
			header("Location: {$vars['systemurl']}clientarea.php");
			exit;
		}
	}
});

/**
 * Affiliate Page
 */
add_hook("ClientAreaPageAffiliates", 1, function ($vars) {

	// If no one logged in
	if (!Menu::context('client')) {

		// Set a redirect URL and proceed to login
		Cookie::set('OktaRedirectUrl', base64_encode('affiliates.php'), strtotime('+1 hour', time()));
	}
});

/**
 * Client Actions List
 */
add_hook('AdminAreaClientSummaryActionLinks', 1, function ($vars) {

	// Compose our action URL
	$url = 'addonmodules.php?module=okta&action=unlink&userid=' . $vars['userid'];

	// Return the action link
    return [
        '<a style="color:#CC0000;" href="' . $url . '">
			<img src="images/icons/delete.png" border="0" align="absmiddle">
			Unlink Okta User Account
		</a>',
    ];
});

/**
 * Referer
 *
 * Finds and sets the referer as a cookie which allos the addon
 * to redirect a user back to page once they are authenticated.
 *
 * @return false|string|null
 */
function getReferer() {

	// Set our referer to empty
	$referer = NULL;

	// Try to get a referrer
	$incoming = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);

	// If we do have a cookie redirect
	if (Cookie::get('OktaRedirectUrl')) {

		// Set our referer to that
		$referer = base64_decode(Cookie::get('OktaRedirectUrl'));
	}

	// If our referrer is from our website
	else if (substr($incoming, 0 - strlen($_SERVER[HTTP_HOST])) == $_SERVER[HTTP_HOST]) {

		// If we have a script name
		if ($_SERVER[REQUEST_URI] and $_SERVER[REQUEST_URI] != '/clientarea.php') {

			// Set our referrer to the request URI
			$referer = trim($_SERVER[REQUEST_URI], '/');
		}
	}

	// If we have a referrer
	if ($referer) {

		// Save it in a cookie
		Cookie::set('OktaReferer', base64_encode($referer));

		// Remove the redirect URL
		Cookie::delete('OktaRedirectUrl');
	}

	// Return the referer
	return $referer;
}
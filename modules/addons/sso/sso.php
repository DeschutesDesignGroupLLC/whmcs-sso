<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ClientController;
use WHMCS\Database\Capsule;
use WHMCS\User\Client;

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

/**
 * @return array
 */
function sso_config()
{
    return [
        'name' => 'Single Sign-On (SSO) for WHMCS',
        'description' => 'A plug and play Single Sign-On (SSO) addon for WHMCS.',
        'version' => '1.0.7',
        'author' => 'Deschutes Design Group LLC',
        'language' => 'english',
        'fields' => [
            'provider' => [
                'FriendlyName' => 'Provider',
                'Type' => 'text',
                'Size' => '25',
                'Placeholder' => 'https://yourdomain.okta.com/oauth2/default',
                'Description' => '<br>Your authorization server domain. This can be your Okta provided domain or a custom domain.',
            ],
            'clientid' => [
                'FriendlyName' => 'Client ID',
                'Type' => 'text',
                'Size' => '25',
                'Description' => '<br>Your application Client ID.',
            ],
            'clientsecret' => [
                'FriendlyName' => 'Client Secret',
                'Type' => 'password',
                'Size' => '25',
                'Description' => '<br>Your application Client Secret.',
            ],
            'scopes' => [
                'FriendlyName' => 'Scopes',
                'Type' => 'text',
                'Size' => '25',
                'Placeholder' => 'profile,email',
                'Description' => "<br>Your application scopes to request. Please separate each scope with a comma - no whitespace. (The request will include 'openid' be default).",
            ],
            'disablessl' => [
                'FriendlyName' => 'Disable SSL Verification',
                'Type' => 'yesno',
                'Description' => 'In some cases you may need to disable SSL security on your development systems. Note: This is not recommended on production systems.',
            ],
            'redirectregistration' => [
                'FriendlyName' => 'Registration URL',
                'Type' => 'text',
                'Size' => '25',
                'Description' => '<br>If provided, the client will be taken to this URL when attempting to create an account.'],
            'redirectpassword' => [
                'FriendlyName' => 'Change Password URL',
                'Type' => 'text',
                'Size' => '25',
                'Description' => '<br>If provided, the client will be taken to this URL to update their password.',
            ],
            'redirectlogout' => [
                'FriendlyName' => 'Logout URL',
                'Type' => 'text',
                'Size' => '25',
                'Description' => '<br>If provided, the client will be taken to this URL when attempting to logout.',
            ],
        ],
    ];
}

/**
 * @return string[]
 */
function sso_activate()
{
    try {
        Capsule::schema()->create('mod_sso_members', function ($table) {
            $table->unsignedBigInteger('user_id', false);
            $table->mediumText('sub')->nullable()->default(null);
            $table->mediumText('access_token')->nullable()->default(null);
            $table->mediumText('id_token')->nullable()->default(null);
            $table->primary('user_id');
        });

        return [
            'status' => 'success',
            'description' => 'The addon has been successfully activated.',
        ];
    } catch (\Exception $exception) {
        return [
            'status' => 'error',
            'description' => "Unable to activate addon: {$exception->getMessage()}",
        ];
    }
}

/**
 * @return string[]
 */
function sso_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_sso_members');

        return [
            'status' => 'success',
            'description' => 'The addon has been successfully deactivated.',
        ];
    } catch (\Exception $exception) {
        return [
            'status' => 'error',
            'description' => "Unable to deactivate addon: {$exception->getMessage()}",
        ];
    }
}

/**
 * @return void
 */
function sso_upgrade($vars)
{
    try {
        $currentlyInstalledVersion = $vars['version'];

        if (version_compare($currentlyInstalledVersion, '1.0.4') < 0) {
            Capsule::schema()->table('mod_sso_members', function ($table) {
                $table->unsignedBigInteger('user_id', false);
            });
        }

        if (version_compare($currentlyInstalledVersion, '1.0.5') < 0) {
            Capsule::table('mod_sso_members')->where('user_id', '0')->orderBy('client_id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $client = Client::where('id', $row->client_id)->firstOrFail();
                        Capsule::table('mod_sso_members')->where('client_id', $row->client_id)->update([
                            'user_id' => $client->owner()->id,
                        ]);
                    } catch (\Exception $exception) {
                    }
                }
            });

            if (Capsule::schema()->hasColumn('mod_sso_members', 'client_id')) {
                Capsule::schema()->table('mod_sso_members', function ($table) {
                    $table->dropColumn('client_id');
                });
            }

            if (Capsule::schema()->hasColumn('mod_sso_members', 'onboarding')) {
                Capsule::schema()->table('mod_sso_members', function ($table) {
                    $table->dropColumn('onboarding');
                });
            }

            if (Capsule::schema()->hasColumn('mod_sso_members', 'onboarded')) {
                Capsule::schema()->table('mod_sso_members', function ($table) {
                    $table->dropColumn('onboarded');
                });
            }

            Capsule::schema()->table('mod_sso_members', function ($table) {
                $table->primary('user_id');
            });
        }
    } catch (\Exception $exception) {
        logActivity('SSO: Upgrade Exception - '.$exception->getMessage());
    }
}

/**
 * @return array
 */
function sso_output($vars)
{
    $action = $_REQUEST['action'] ?? '';

    $dispatcher = new AdminController();

    return $dispatcher->dispatch($action, $vars);
}

/**
 * @return array
 */
function sso_clientarea($vars)
{
    $action = $_REQUEST['action'] ?? '';

    $dispatcher = new ClientController();

    return $dispatcher->dispatch($action, $vars);
}

<?php

namespace App\Services;

use League\Uri\Components\Query;
use League\Uri\Uri;
use League\Uri\UriModifier;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Setting;

class RedirectService
{
    /**
     * @return void
     */
    public function redirectToLocation($location = null)
    {
        header("Location: $location");
        exit;
    }

    /**
     * @return void
     */
    public function redirectToClientArea()
    {
        $this->redirectToLocation('clientarea.php');
    }

    /**
     * @return void
     */
    public function redirectToOnboarding($parameters = [])
    {
        $query = http_build_query(array_merge([
            'm' => 'sso',
            'controller' => 'onboard',
        ], $parameters));

        $this->redirectToLocation("index.php?$query");
    }

    /**
     * @return void
     */
    public function redirectToLogin()
    {
        $this->redirectToLocation('index.php?m=sso&controller=login');
    }

    /**
     * @return void
     */
    public function redirectToError($message)
    {
        $this->redirectToLocation("index.php?m=sso&controller=error&error=$message");
    }

    /**
     * @return void
     */
    public function redirectToPasswordReset()
    {
        try {
            $redirect = Setting::where('module', 'sso')->where('setting', 'redirectpassword')->firstOrFail();

            if (isset($redirect->value) && $redirect->value !== null) {
                $this->redirectToLocation($redirect->value);
            }
        } catch (\Exception $exception) {
            logActivity('SSO: Reset Password Exception - '.$exception->getMessage());
        }
    }

    /**
     * @return void
     */
    public function redirectToRegistration()
    {
        try {
            $redirect = Setting::where('module', 'sso')->where('setting', 'redirectregistration')->firstOrFail();

            if (isset($redirect->value) && $redirect->value !== null) {
                $this->redirectToLocation($redirect->value);
            }
        } catch (\Exception $exception) {
            logActivity('SSO: Register Exception - '.$exception->getMessage());
        }
    }

    /**
     * @return void
     */
    public function redirectToLogout($userId)
    {
        try {
            $redirect = Setting::where('module', 'sso')->where('setting', 'redirectlogout')->firstOrFail();

            if (isset($redirect->value) && $redirect->value !== null) {
                $member = Capsule::table('mod_sso_members')->where('user_id', $userId)->first();

                $logout = Uri::createFromString($redirect->value);

                if ($member->id_token) {
                    $token = Query::createFromRFC3986("id_token_hint={$member->id_token}");
                    $logout = UriModifier::appendQuery($logout, $token);
                }

                $this->redirectToLocation($logout->__toString());
            }
        } catch (\Exception $exception) {
            logActivity('SSO: Logout Exception - '.$exception->getMessage());
        }
    }
}

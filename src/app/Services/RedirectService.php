<?php

namespace DeschutesDesignGroupLLC\App\Services;

use JetBrains\PhpStorm\NoReturn;
use League\Uri\Components\Query;
use League\Uri\Uri;
use League\Uri\UriModifier;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Setting;

class RedirectService
{
    #[NoReturn]
    public function redirectToLocation($location = null): void
    {
        header("Location: $location");
        exit;
    }

    #[NoReturn]
    public function redirectToClientArea(): void
    {
        $this->redirectToLocation('clientarea.php');
    }

    #[NoReturn]
    public function redirectToOnboarding($parameters = []): void
    {
        $query = http_build_query(array_merge([
            'm' => 'sso',
            'controller' => 'onboard',
        ], $parameters));

        $this->redirectToLocation("index.php?$query");
    }

    #[NoReturn]
    public function redirectToLogin(): void
    {
        $this->redirectToLocation('index.php?m=sso&controller=login');
    }

    #[NoReturn]
    public function redirectToError($message): void
    {
        $this->redirectToLocation("index.php?m=sso&controller=error&error=$message");
    }

    public function redirectToPasswordReset(): void
    {
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectpassword')->first();

        if (isset($redirect->value) && $redirect->value !== '') {
            $this->redirectToLocation($redirect->value);
        }
    }

    public function redirectToRegistration(): void
    {
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectregistration')->first();

        if (isset($redirect->value) && $redirect->value !== '') {
            $this->redirectToLocation($redirect->value);
        }
    }

    public function redirectToLogout($userId): void
    {
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectlogout')->first();
        $logoutIdToken = Setting::where('module', 'sso')->where('setting', 'logoutidtoken')->first();

        if (isset($redirect->value) && $redirect->value !== '') {
            $member = Capsule::table('mod_sso_members')->where('user_id', $userId)->first();

            $logout = Uri::createFromString($redirect->value);

            if ($member->id_token && isset($logoutIdToken->value) && $logoutIdToken->value !== '') {
                $token = Query::createFromRFC3986("{$logoutIdToken->value}={$member->id_token}");
                $logout = UriModifier::appendQuery($logout, $token);
            }

            $this->redirectToLocation($logout->__toString());
        }
    }
}

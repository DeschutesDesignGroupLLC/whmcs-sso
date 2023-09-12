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
    public function redirectToLocation($location = null, array $parameters = null): void
    {
        if ($parameters) {
            $query = '?'.http_build_query($parameters);
        } else {
            $query = '';
        }

        $location .= $query;

        header("Location: $location");
        exit;
    }

    #[NoReturn]
    public function redirectToClientArea(): void
    {
        $this->redirectToLocation('clientarea.php');
    }

    #[NoReturn]
    public function redirectToOnboarding(string $error = null): void
    {
        $parameters = [
            'm' => 'sso',
            'controller' => 'onboard',
        ];

        if ($error) {
            $parameters = array_merge($parameters, [
                'error' => $error,
            ]);
        }

        $this->redirectToLocation('index.php', $parameters);
    }

    #[NoReturn]
    public function redirectToLogin(): void
    {
        $this->redirectToLocation('index.php', [
            'm' => 'sso',
            'controller' => 'login',
        ]);
    }

    #[NoReturn]
    public function redirectToError($message): void
    {
        $this->redirectToLocation('index.php', [
            'm' => 'sso',
            'controller' => 'error',
            'error' => $message,
        ]);
    }

    #[NoReturn]
    public function redirectToPasswordReset(): void
    {
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectpassword')->first();

        if (isset($redirect->value) && $redirect->value !== '') {
            $this->redirectToLocation($redirect->value);
        }

        $this->redirectToLogin();
    }

    #[NoReturn]
    public function redirectToRegistration(): void
    {
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectregistration')->first();

        if (isset($redirect->value) && $redirect->value !== '') {
            $url = Uri::createFromString($redirect->value);

            if ($query = $url->getQuery()) {
                parse_str($query, $parameters);

                $cookieService = new CookieService();
                $cookieService->setRegistrationUrlCookie($parameters);
            }

            $this->redirectToLocation($redirect->value);
        }

        $this->redirectToLogin();
    }

    public function redirectToLogout($userId): void
    {
        $redirect = Setting::where('module', 'sso')->where('setting', 'redirectlogout')->first();
        $logoutIdToken = Setting::where('module', 'sso')->where('setting', 'logoutidtoken')->first();
        $logoutRedirectKey = Setting::where('module', 'sso')->where('setting', 'logoutredirectkey')->first();
        $logoutRedirectValue = Setting::where('module', 'sso')->where('setting', 'logoutredirectvalue')->first();

        if (isset($redirect->value) && $redirect->value !== '') {
            $member = Capsule::table('mod_sso_members')->where('user_id', $userId)->first();

            $logout = Uri::createFromString($redirect->value);

            if ($member->id_token && isset($logoutIdToken->value) && $logoutIdToken->value !== '') {
                $token = Query::createFromRFC3986("{$logoutIdToken->value}={$member->id_token}");
                $logout = UriModifier::appendQuery($logout, $token);
            }

            if (isset($logoutRedirectKey->value, $logoutRedirectValue->value) && $logoutRedirectKey->value !== '' && $logoutRedirectValue->value !== '') {
                $redirect = Query::createFromRFC3986("{$logoutRedirectKey->value}={$logoutRedirectValue->value}");
                $logout = UriModifier::appendQuery($logout, $redirect);
            }

            $this->redirectToLocation($logout->__toString());
        }
    }
}

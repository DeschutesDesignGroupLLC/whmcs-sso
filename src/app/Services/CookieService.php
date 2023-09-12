<?php

namespace DeschutesDesignGroupLLC\App\Services;

use WHMCS\Cookie;

class CookieService
{
    protected static string $onboardingCookie = 'SsoOnboarding';

    protected static string $redirectCookie = 'SsoRedirectUrl';

    protected static string $registrationUrlCookie = 'SsoRegistrationUrl';

    public function setOnboardingCookie($userInfo, $userId, $accessToken, $idToken): void
    {
        Cookie::set(static::$onboardingCookie, base64_encode(json_encode([
            'userinfo' => $userInfo,
            'user' => $userId,
            'access_token' => $accessToken,
            'id_token' => $idToken,
        ])), strtotime('+1 hour'));
    }

    public function getOnboardingCookie(): mixed
    {
        return json_decode(base64_decode(Cookie::get(static::$onboardingCookie)), false);
    }

    public function removeOnboardingCookie(): void
    {
        Cookie::delete(static::$onboardingCookie);
    }

    public function setRedirectCookie($url): void
    {
        Cookie::set(static::$redirectCookie, $url, strtotime('+1 hour'));
    }

    public function getRedirectCookie(): mixed
    {
        return Cookie::get(static::$redirectCookie);
    }

    public function removeRedirectCookie(): void
    {
        Cookie::delete(static::$redirectCookie);
    }

    public function setRegistrationUrlCookie(array $query = []): void
    {
        Cookie::set(static::$registrationUrlCookie, base64_encode(json_encode($query)), strtotime('+1 hour'));
    }

    public function getRegistrationUrlCookie(): mixed
    {
        return json_decode(base64_decode(Cookie::get(static::$registrationUrlCookie)), true);
    }

    public function removeRegistrationUrlCookie(): void
    {
        Cookie::delete(static::$registrationUrlCookie);
    }
}

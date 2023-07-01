<?php

namespace App\Services;

use WHMCS\Cookie;

class CookieService
{
    /**
     * @var string
     */
    protected static $onboardingCookie = 'SsoOnboarding';

    /**
     * @var string
     */
    protected static $redirectCookie = 'SsoRedirectUrl';

    /**
     * @return void
     */
    public function setOnboardingCookie($userInfo, $userId, $accessToken, $idToken)
    {
        Cookie::set(static::$onboardingCookie, base64_encode(json_encode([
            'userinfo' => $userInfo,
            'user' => $userId,
            'access_token' => $accessToken,
            'id_token' => $idToken,
        ])), strtotime('+1 hour'));
    }

    /**
     * @return mixed
     */
    public function getOnboardingCookie()
    {
        return json_decode(base64_decode(Cookie::get(static::$onboardingCookie)), false);
    }

    /**
     * @return void
     */
    public function removeOnboardingCookie()
    {
        Cookie::delete(static::$onboardingCookie);
    }

    /**
     * @return void
     */
    public function setRedirectCookie($url)
    {
        Cookie::set(static::$redirectCookie, $url, strtotime('+1 hour'));
    }

    /**
     * @return mixed
     */
    public function getRedirectCookie()
    {
        return Cookie::get(static::$redirectCookie);
    }

    /**
     * @return void
     */
    public function removeRedirectCookie()
    {
        Cookie::delete(static::$redirectCookie);
    }
}

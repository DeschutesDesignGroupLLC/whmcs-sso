<?php

use App\Services\CookieService;
use App\Services\RedirectService;
use League\Uri\Components\Query;
use League\Uri\Uri;
use WHMCS\Authentication\CurrentUser;
use WHMCS\User\Client;
use WHMCS\User\User;
use WHMCS\User\User\UserInvite;

/**
 * Do not all the file to be accessed directly
 */
if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

include_once __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

/**
 * Client Area Head Output
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    return <<<'HTML'
        <meta name="robots" content="noindex, nofollow">
    HTML;
});

/**
 * Client Area
 *
 * If the user is responding to a User Invite, we want to authenticate
 * them first and then redirect them back to the invitation process.
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    $redirectService = new RedirectService();
    $cookieService = new CookieService();
    $currentUser = new CurrentUser;

    if (! $currentUser->user() && array_key_exists('invite', $vars) && $vars['invite'] instanceof UserInvite) {
        $cookieService->setRedirectCookie(Uri::createFromString()->withPath('index.php')->withQuery(Query::createFromParams([
            'rp' => "/invite/{$vars['invite']->token}",
        ]))->__toString());

        $redirectService->redirectToLogin();
    }
});

/**
 * Client Area Services
 *
 * This is the landing page after a user successfully completes SSO. We
 * want to be able to handle any redirects so the user can resume what
 * they were doing prior to initiation of the SSO flow.
 */
add_hook('ClientAreaPageProductsServices', 1, function ($vars) {
    $cookieService = new CookieService();
    $redirectService = new RedirectService();

    if ($redirectUrl = $cookieService->getRedirectCookie()) {
        $cookieService->removeRedirectCookie();

        $redirectService->redirectToLocation($redirectUrl);
    }
});

/**
 * Client Area Login Hook
 */
add_hook('ClientAreaPageLogin', 1, function ($vars) {
    $redirectService = new RedirectService();
    $redirectService->redirectToLogin();
});

/**
 * Delete Client
 */
add_hook('ClientDelete', 1, function ($vars) {
    $ssoService = new \App\Services\SsoService();
    $ssoService->removeSsoConnection($vars['userid']);
});

/**
 * Client Password Reset
 */
add_hook('ClientAreaPagePasswordReset', 1, function ($vars) {
    $redirectService = new RedirectService();
    $redirectService->redirectToPasswordReset();
});

/**
 * Client Change Password
 */
add_hook('ClientAreaPageChangePassword', 1, function ($vars) {
    $redirectService = new RedirectService();
    $redirectService->redirectToPasswordReset();
});

/**
 * Client Registration
 */
add_hook('ClientAreaPageRegister', 1, function ($vars) {
    $redirectService = new RedirectService();
    $redirectService->redirectToRegistration();
});

/**
 * User Logout
 */
add_hook('UserLogout', 1, function ($vars) {
    $redirectService = new RedirectService();
    $redirectService->redirectToLogout($vars['user']->id);
});

/**
 * Cart Page
 *
 * We want to make sure a user is authenticated before checking out.
 * Store the checkout URL, so we can be redirected back here after
 * completion of the SSO flow.
 */
add_hook('ClientAreaPageCart', 1, function ($vars) {
    $redirectService = new RedirectService();
    $cookieService = new CookieService();

    $currentUser = new CurrentUser;
    if (($_GET['a'] === 'checkout') && ! $currentUser->user()) {
        $cookieService->setRedirectCookie(Uri::createFromString()->withPath('cart.php')->withQuery(Query::createFromParams([
            'a' => 'checkout',
            'e' => 'false',
        ]))->__toString());

        $redirectService->redirectToLogin();
    }
});

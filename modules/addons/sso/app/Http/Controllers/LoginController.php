<?php

namespace App\Http\Controllers;

use Exception;
use Jumbojett\OpenIDConnectClient;
use WHMCS\Module\Addon\Setting;

class LoginController extends Controller
{
    protected Setting $provider;

    private Setting $clientId;

    private Setting $clientSecret;

    private Setting $scopes;

    private Setting $disableSsl;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->provider = Setting::where('module', 'sso')->where('setting', 'provider')->firstOrFail();
        $this->clientId = Setting::where('module', 'sso')->where('setting', 'clientid')->firstOrFail();
        $this->clientSecret = Setting::where('module', 'sso')->where('setting', 'clientsecret')->firstOrFail();
        $this->scopes = Setting::where('module', 'sso')->where('setting', 'scopes')->firstOrFail();
        $this->disableSsl = Setting::where('module', 'sso')->where('setting', 'disablessl')->firstOrFail();
    }

    /**
     * @return void
     *
     * @throws \Jumbojett\OpenIDConnectClientException
     */
    public function index()
    {
        try {
            $oidc = new OpenIDConnectClient($this->provider->value, $this->clientId->value, $this->clientSecret->value);
            $oidc->setRedirectURL((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]".'/index.php?m=sso&controller=login');
            $oidc->addScope(explode(',', $this->scopes->value));
            $oidc->setUrlEncoding(PHP_QUERY_RFC1738);

            if ($this->disableSsl->value) {
                $oidc->setVerifyHost(false);
                $oidc->setVerifyPeer(false);
            }

            $oidc->authenticate();

            $token = $oidc->getIdTokenPayload();

            $userInfo = $oidc->requestUserInfo();

            $user = $this->ssoService->findSsoConnection($token->sub, $userInfo->email);

            if (! $user || $user->clients->isEmpty()) {
                $this->cookieService->setOnboardingCookie(
                    $userInfo,
                    ($user->id ?? null),
                    $oidc->getAccessToken(),
                    $oidc->getIdToken());

                $this->redirectService->redirectToOnboarding();
            }

            $this->ssoService->addSsoConnection($user->id, $token->sub, $oidc->getAccessToken(), $oidc->getIdToken());

            $results = localAPI('CreateSsoToken', [
                'user_id' => $user->id,
                'destination' => 'clientarea:services',
            ]);

            if ($results['result'] === 'success' && array_key_exists('redirect_url', $results)) {
                $this->redirectService->redirectToLocation($results['redirect_url']);
            }
        } catch (Exception $exception) {
            logActivity('SSO: Exception - '.$exception->getMessage());

            $this->redirectService->redirectToError($exception->getMessage());
        }
    }
}

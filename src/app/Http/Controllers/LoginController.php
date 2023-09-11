<?php

namespace DeschutesDesignGroupLLC\App\Http\Controllers;

use DeschutesDesignGroupLLC\App\Services\CookieService;
use Exception;
use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;
use WHMCS\Module\Addon\Setting;

class LoginController extends Controller
{
    protected Setting $provider;

    protected Setting $authorizationEndpoint;

    protected Setting $tokenEndpoint;

    protected Setting $userinfoEndpoint;

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
        $this->authorizationEndpoint = Setting::where('module', 'sso')->where('setting', 'authorize_endpoint')->firstOrFail();
        $this->tokenEndpoint = Setting::where('module', 'sso')->where('setting', 'token_endpoint')->firstOrFail();
        $this->userinfoEndpoint = Setting::where('module', 'sso')->where('setting', 'userinfo_endpoint')->firstOrFail();
        $this->clientId = Setting::where('module', 'sso')->where('setting', 'clientid')->firstOrFail();
        $this->clientSecret = Setting::where('module', 'sso')->where('setting', 'clientsecret')->firstOrFail();
        $this->scopes = Setting::where('module', 'sso')->where('setting', 'scopes')->firstOrFail();
        $this->disableSsl = Setting::where('module', 'sso')->where('setting', 'disablessl')->firstOrFail();
    }

    /**
     * @throws OpenIDConnectClientException
     */
    public function index(): void
    {
        try {
            $oidc = new OpenIDConnectClient($this->provider->value);
            $oidc->providerConfigParam([
                'authorization_endpoint' => $this->authorizationEndpoint->value,
                'token_endpoint' => $this->tokenEndpoint->value,
                'userinfo_endpoint' => $this->userinfoEndpoint->value,
            ]);
            $oidc->setClientID($this->clientId->value);
            $oidc->setClientSecret($this->clientSecret->value);
            $oidc->setRedirectURL((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]".'/index.php?m=sso&controller=login');
            $oidc->addScope(explode(',', $this->scopes->value));
            $oidc->setUrlEncoding(PHP_QUERY_RFC1738);

            $cookieService = new CookieService();
            if ($query = $cookieService->getRegistrationUrlCookie()) {
                $oidc->addAuthParam($query);

                $cookieService->removeRegistrationUrlCookie();
            }

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

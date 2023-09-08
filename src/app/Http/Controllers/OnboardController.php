<?php

namespace DeschutesDesignGroupLLC\App\Http\Controllers;

use JetBrains\PhpStorm\NoReturn;

require 'includes/clientfunctions.php';

class OnboardController extends Controller
{
    protected mixed $onboardingInfo;

    protected string $message;

    protected string $error;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->message = $_REQUEST['message'] ?? 'We need a little bit more information to create your account. Please fill in the fields below to finish your account setup.';
        $this->error = $_REQUEST['error'] ?? '';

        if (! $this->onboardingInfo = $this->cookieService->getOnboardingCookie()) {
            $this->redirectService->redirectToLogin();
        }
    }

    public function index($vars): array
    {
        return [
            'pagetitle' => 'Onboarding',
            'breadcrumb' => [
                'index.php?m=sso&controller=onboard' => 'Onboarding',
            ],
            'templatefile' => 'onboard',
            'requirelogin' => false,
            'vars' => [
                'error' => $this->error,
                'message' => $this->message,
                'clientemail' => $this->onboardingInfo->userinfo->email ?? null,
                'clientfirstname' => $this->onboardingInfo->userinfo->given_name ?? null,
                'clientlastname' => $this->onboardingInfo->userinfo->family_name ?? null,
                'clientcountriesdropdown' => getCountriesDropDown(),
            ],
        ];
    }

    #[NoReturn]
    public function store(): void
    {
        $data = [
            'firstname' => $_POST['firstname'],
            'lastname' => $_POST['lastname'],
            'companyname' => $_POST['companyname'],
            'email' => $this->onboardingInfo->userinfo->email,
            'address1' => $_POST['address1'],
            'address2' => $_POST['address2'],
            'city' => $_POST['city'],
            'state' => $_POST['state'],
            'postcode' => $_POST['postcode'],
            'country' => $_POST['country'],
            'phonenumber' => $_POST['phonenumber'],
            'password2' => 'password',
        ];

        if ($this->onboardingInfo->user) {
            $data['owner_user_id'] = $this->onboardingInfo->user;
        }

        $result = $this->clientService->addClient($data);

        if ($result['result'] === 'success' && $result['clientid']) {
            $this->ssoService->addSsoConnection(
                $this->onboardInfo->user ?? $result['owner_id'],
                $this->onboardingInfo->userinfo->sub,
                $this->onboardingInfo->access_token,
                $this->onboardingInfo->id_token
            );

            $this->cookieService->removeOnboardingCookie();

            $this->redirectService->redirectToClientArea();
        }

        $this->redirectService->redirectToOnboarding([
            'error' => $result['message'],
        ]);
    }
}

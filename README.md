# WHMCS Single-Sign On (SSO) Powered by Okta

Turn your online web hosting business into a robust network of connected services by enabling Single Sign-On (SSO) and allowing your clients to use one login for all your applications. WHMCS Single Sign-On (SSO) works by connecting your WHMCS application with Okta. At this time, this application only supports Okta as an Identity Provider (IdP). This application is plug and play and does not require extensive interaction to install.

## Installation

You will need to have created an OpenID Connect application in Okta before installing this addon. Please refer to these [instructions](https://developer.okta.com/docs/guides/add-an-external-idp/saml2/register-app-in-okta/) for help.

###### Before You Begin:

Obtain your Client ID and Client Secret for your OIDC application. These will be needed to configure the addon.

***

1. Upload the contents of the folder to your root WHMCS installation. The 'okta' folder should be located in `/whmcs_root/modules/addons/`.
2. Login to your WHMCS ACP and proceed to Setup -> Addon Modules.
3. Click Activate next to "Single Sign-On with Okta".
4. Select Configure and enter all your Okta application credentials.
5. Now, when you click Login, you will be redirected to your Okta login page and redirected back to the Client Area when authentication finishes. 

## Support
Support can be reached by submitting a ticket at [https://support.deschutesdesigngroup.com](https://support.deschutesdesigngroup.com) or emailing [support@deschutesdesigngroup.com](mailto://support@deschutesdesigngroup.com).
# Single Sign On with Okta (SSO)

Powered with Okta, Single Sign On with Okta allows your clients a seamless and secure login experience using only one set of login credentials. Okta is the leading provider in SSO technology, allowing businesses to efficiently manage their client identities and provide an easy user experience for their customers. By setting up Okta as your central account identity management solution, this application is a one click solution directing your clients to authenticate with a central login page and then redirected back to WHMCS. Registration and password management are also managed within Okta using this application.

## Requirements

- WHMCS Version 8.2 or greater

## Installation

Prior to installing this application, you will need to create a corresponding new OIDC application integration in Okta. Instructions can be found [here](https://developer.okta.com/docs/guides/add-an-external-idp/apple/register-app-in-okta/).

***

1. Download the software from our Client Area.
2. Unzip the file and upload the contents of the folder to your root WHMCS directory. This should create a new 'okta' directory in the `/modules/addons/` folder.
3. Login to your WHMCS administrative panel and proceed to System Settings -> Addon Modules.
4. Click Activate and then Configure next to Single Sign On with Okta addon.
5. Fill in all the applicable fields using the information obtained from your OIDC application integration in Okta.
6. The only scopes needed are profile and email. Separate them with a comma.
7. Lastly, enter all your redirect URLs which will redirect the client to the applicable parts of Okta to handle the associated actions.

## Support
Support can be reached by submitting a ticket at [https://www.deschutesdesigngroup.com/support](https://www.deschutesdesigngroup.com/support) or emailing [support@deschutesdesigngroup.com](mailto://support@deschutesdesigngroup.com).
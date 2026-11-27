<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Xero as a provider of connected accounts (includes/fn/connect.php).
 *
 * The application is a Web app the site owner registers for free on Xero's
 * developer portal (developer.xero.com/app/manage), which shows its client
 * id and secret; the one return address of connected accounts
 * (connect.php) is entered there. The sign-in is Xero's OAuth 2.0 server
 * (https://identity.xero.com/.well-known/openid-configuration): an
 * authorization code with PKCE, the client authenticated with HTTP Basic,
 * offline_access for a refresh token (60 days unused, replaced on each
 * renewal). The access token lives 1,800 seconds.
 *
 * The site holds one Xero connection: the account is not named by the
 * identity token (identity => false; openid is asked as Paraşüt's is), the
 * organisation is read from GET https://api.xero.com/connections once the
 * browser is back (xero_after_connect()). Disconnect revokes the refresh
 * token at Xero's revocation endpoint (RFC 7009), which ends every
 * organisation's connection to the application.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

return array(
    'label'              => 'Xero',
    'icon'               => 'bi-journal-text',
    'authorize_url'      => 'https://login.xero.com/identity/connect/authorize',
    'token_url'          => 'https://identity.xero.com/connect/token',
    'revoke_url'         => 'https://identity.xero.com/connect/revocation',
    'revoke_client_auth' => true,
    'check_url'          => 'https://api.xero.com/connections',
    'issuers'            => array('https://identity.xero.com'),
    'identity_scopes'    => array('openid', 'offline_access'),
    'identity'           => false,
    'account_label'      => 'Xero',
    'nonce'              => false,
    'client_auth'        => 'basic',
    'token_per_scope'    => false,
    'default_ttl'        => 1800,
    'connect_purpose'    => 'xero',
    'listed'             => false,
    'console_url'        => 'https://developer.xero.com/app/manage',
    'options'            => array(),
    'redirect_help'      => lang('Enter this address as a Redirect URI of your application on Xero\'s developer portal.'),
    'setup'              => array(
        lang('Sign in to Xero\'s developer portal with a free Xero account and add a new app of the Web app type.'),
        lang('Enter the site\'s address as the Company or application URL and the redirect address below as its Redirect URI.'),
        lang('Copy the Client id here, generate a secret on the app\'s Configuration page and copy it too, save, then connect Xero under Status and allow the organisation the site\'s sales go to.'),
    ),
);

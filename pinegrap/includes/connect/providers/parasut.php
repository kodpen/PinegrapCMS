<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Paraşüt as a provider of connected accounts (includes/fn/connect.php).
 *
 * The application is a client the site owner gets on Paraşüt's Developer
 * Portal (devhub.parasut.com), which shows its client id and secret. Its
 * redirect address is fixed when the application is made, so the one
 * return address of connected accounts (connect.php) is entered there. The
 * sign-in is Paraşüt's OpenID Connect server at id.parasut.com: an
 * authorization code with PKCE, openid and offline_access (a refresh token
 * that lives while it is used, 30 days after its last use, replaced on each
 * renewal); kc_idp_hint skips the choice of identity provider. The access
 * token lives 300 seconds.
 *
 * The site's environment (parasut_environment()) picks the sign-in server:
 * id-staging.parasut.com for a test client of the portal, the same paths.
 *
 * The site holds one Paraşüt connection: the account is not named by an
 * identity token (identity => false), its company is read from
 * GET /v4/me?include=companies once the browser is back
 * (parasut_devhub_after_connect()). Paraşüt's guide names no revocation
 * address, so Disconnect deletes the connection without one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$parasut_sign_in = parasut_sign_in(parasut_environment());

return array(
    'label'            => 'Paraşüt',
    'icon'             => 'bi-receipt',
    'authorize_url'    => $parasut_sign_in['authorize'],
    'token_url'        => $parasut_sign_in['token'],
    'check_url'        => parasut_api_base(parasut_environment()) . '/v4/me',
    'issuers'          => array($parasut_sign_in['issuer']),
    'identity_scopes'  => array('openid', 'offline_access'),
    'identity'         => false,
    'account_label'    => 'Paraşüt',
    'nonce'            => false,
    'authorize_params' => array(
        'kc_idp_hint' => 'parasut',
    ),
    'client_auth'      => 'post',
    'token_per_scope'  => false,
    'connect_purpose'  => 'parasut',
    'listed'           => false,
    'console_url'      => 'https://devhub.parasut.com',
    'options'          => array(),
    'redirect_help'    => lang('Enter this address as the Redirect URL of your application on Paraşüt\'s Developer Portal. It cannot be changed after the application.'),
    'setup'            => array(
        lang('Apply for API access on Paraşüt\'s Developer Portal and make an application for your own company.'),
        lang('Enter the redirect address below as the application\'s Redirect URL.'),
        lang('Copy the Client ID and the client secret from the portal\'s API Bilgilerim page here, save, then press Connect.'),
    ),
);

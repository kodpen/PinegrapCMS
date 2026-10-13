<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Microsoft (Entra ID: work, school and personal accounts) as a provider of
 * connected accounts (includes/fn/connect.php).
 *
 * The application is an app registration in the Microsoft Entra admin center
 * with a Web redirect address and a client secret. offline_access is what
 * brings a refresh token. An access token is issued for one resource at a
 * time (Graph, Outlook, ...), so tokens are fetched and cached per scope set
 * (token_per_scope) and the refresh token is replaced on every use. The
 * issuer carries the account's directory ID, hence a pattern rather than a
 * fixed address.
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
    'label'            => 'Microsoft',
    'icon'             => 'bi-microsoft',
    'authorize_url'    => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
    'token_url'        => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
    'revoke_url'       => '',
    'userinfo_url'     => 'https://graph.microsoft.com/oidc/userinfo',
    'issuer_pattern'   => '#^https://login\.microsoftonline\.com/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/v2\.0$#',
    'identity_scopes'  => array('openid', 'email', 'profile', 'offline_access', 'https://graph.microsoft.com/User.Read'),
    'default_scopes'   => array('https://graph.microsoft.com/User.Read'),
    'default_tenant'   => 'common',
    'authorize_params' => array(
        'prompt'        => 'select_account',
        'response_mode' => 'query',
    ),
    'client_auth'      => 'post',
    'token_per_scope'  => true,
    'console_url'      => 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
    'options'          => array(
        'tenant' => array(
            'label'   => lang('Directory (tenant)'),
            'help'    => lang('common: work, school and personal accounts. organizations: work and school accounts only. Or the directory ID of your organization.'),
            'default' => 'common',
        ),
    ),
    'setup'            => array(
        lang('In the Microsoft Entra admin center, open App registrations and choose New registration.'),
        lang('Choose who may connect (accounts in any organizational directory and personal Microsoft accounts suits most sites) and add the redirect address below as a Web redirect URI.'),
        lang('Under Certificates & secrets, create a client secret and copy its value here together with the Application (client) ID.'),
        lang('A client secret expires: before it does, create a new one there and enter it here.'),
    ),
);

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GitHub as a provider of connected accounts (includes/fn/connect.php).
 *
 * The application is an OAuth app the site owner registers under GitHub's
 * Developer settings. GitHub is not an OpenID provider: there is no identity
 * token, so who the account is comes from GET /user (its numeric id and its
 * login). An OAuth app's token does not end with time and comes without a
 * refresh token; GitHub withdraws it when the person revokes the app or the
 * token goes unused for a year, and the account is then connected again.
 * The token endpoint lists the granted scopes separated by commas. A token is
 * ended with DELETE /applications/{client_id}/token, authenticated with the
 * application's own id and secret.
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
    'label'            => 'GitHub',
    'icon'             => 'bi-github',
    'authorize_url'    => 'https://github.com/login/oauth/authorize',
    'token_url'        => 'https://github.com/login/oauth/access_token',
    'revoke_url'       => 'https://api.github.com/applications/{client_id}/token',
    'revoke_auth'      => 'basic_json',
    'revoke_method'    => 'DELETE',
    'userinfo_url'     => 'https://api.github.com/user',
    'identity_fields'  => array('id' => 'id', 'label' => array('login')),
    'check_url'        => 'https://api.github.com/user',
    'issuers'          => array(),
    'identity_scopes'  => array(),
    'scope_delimiter'  => ',',
    'nonce'            => false,
    'refresh_optional' => true,
    'default_ttl'      => 157680000,
    'authorize_params' => array(
        'allow_signup' => 'false',
    ),
    'client_auth'      => 'post',
    'token_per_scope'  => false,
    'connect_purpose'  => 'channel_links',
    'console_url'      => 'https://github.com/settings/developers',
    'options'          => array(),
    'setup'            => array(
        lang('In GitHub, open Settings › Developer settings › OAuth Apps and choose New OAuth App (for an organisation: the organisation\'s Settings › Developer settings).'),
        lang('Enter the site\'s address as the Homepage URL and the redirect address below as the Authorization callback URL, then register the application.'),
        lang('Choose Generate a new client secret, and copy the Client ID and the client secret here.'),
        lang('An organisation that restricts third-party applications has to approve this application before its repositories can be linked.'),
    ),
);

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dropbox as a provider of connected accounts (includes/fn/connect.php).
 *
 * The application is a scoped app with App folder access in the Dropbox App
 * Console: it sees only its own folder under Apps, never the rest of the
 * account. token_access_type=offline is what makes Dropbox hand out a refresh
 * token; it does not end with time, only when the account withdraws the
 * permission. The identity token does not carry a nonce, so none is sent;
 * ending a connection uses the access token and ends the refresh token with
 * it.
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
    'label'            => 'Dropbox',
    'icon'             => 'bi-dropbox',
    'authorize_url'    => 'https://www.dropbox.com/oauth2/authorize',
    'token_url'        => 'https://api.dropboxapi.com/oauth2/token',
    'revoke_url'       => 'https://api.dropboxapi.com/2/auth/token/revoke',
    'revoke_auth'      => 'bearer',
    'userinfo_url'     => '',
    'issuers'          => array('https://www.dropbox.com'),
    'identity_scopes'  => array('openid', 'email', 'profile'),
    'nonce'            => false,
    'authorize_params' => array(
        'token_access_type' => 'offline',
    ),
    'client_auth'      => 'post',
    'token_per_scope'  => false,
    'console_url'      => 'https://www.dropbox.com/developers/apps',
    'options'          => array(),
    'setup'            => array(
        lang('In the Dropbox App Console, choose Create app, then Scoped access and App folder.'),
        lang('On the Permissions tab, tick files.metadata.read, files.content.write, openid, email and profile, then Submit.'),
        lang('On the Settings tab, add the redirect address below under Redirect URIs, and copy the App key and the App secret here.'),
        lang('An app in development can connect up to 50 accounts without Dropbox\'s approval; a site\'s own accounts stay well below that.'),
    ),
);

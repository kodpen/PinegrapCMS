<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google as a provider of connected accounts (includes/fn/connect.php) and
 * of sign-in (includes/fn/signin.php).
 *
 * The application is an OAuth client of type "Web application" in Google
 * Cloud Console. access_type=offline with prompt=consent is what makes Google
 * hand out a refresh token every time, include_granted_scopes adds a new
 * feature's scopes to what the account already allowed. A refresh token of an
 * application left in "Testing" ends after seven days, and any refresh token
 * ends after six months unused.
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
    'label'            => 'Google',
    'icon'             => 'bi-google',
    'authorize_url'    => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url'        => 'https://oauth2.googleapis.com/token',
    'revoke_url'       => 'https://oauth2.googleapis.com/revoke',
    'userinfo_url'     => 'https://openidconnect.googleapis.com/v1/userinfo',
    'issuers'          => array('https://accounts.google.com', 'accounts.google.com'),
    'identity_scopes'  => array('openid', 'email', 'profile'),
    'authorize_params' => array(
        'access_type'            => 'offline',
        'prompt'                 => 'consent',
        'include_granted_scopes' => 'true',
    ),
    'client_auth'      => 'post',
    'token_per_scope'  => false,
    'console_url'      => 'https://console.cloud.google.com/apis/credentials',
    'options'          => array(),
    'setup'            => array(
        lang('In Google Cloud Console, open APIs & Services, then Credentials, and create an OAuth client ID of the Web application type.'),
        lang('Add the redirect address below under Authorized redirect URIs.'),
        lang('Copy the client ID and the client secret here.'),
        lang('On the OAuth consent screen, publish the app (In production). While it is in Testing, Google ends every permission after seven days.'),
    ),
    // Sign in with Google. The application is the one of the security
    // settings and the return address google_auth.php, both as they were
    // before Google joined signin.php, so a site's Google Cloud Console needs
    // no change. Signing in asks for no refresh token and lets the person
    // pick an account each time.
    'signin'           => array(
        'order'            => 10,
        'scopes'           => array('openid', 'email', 'profile'),
        'pkce'             => true,
        'return'           => 'google_auth.php',
        'authorize_params' => array(
            'access_type' => 'online',
            'prompt'      => 'select_account',
        ),
    ),
);

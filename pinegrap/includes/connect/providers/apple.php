<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Apple as a provider of sign-in only (includes/fn/signin.php): Sign in with
 * Apple has no account for the site to connect, so Connected accounts does
 * not list it (accounts => false) and its application is set up on the
 * security settings.
 *
 * The application is a Services ID in the Apple Developer account, with the
 * site's domain and return address, and a key enabled for Sign in with Apple.
 * Apple takes no fixed client secret: each code exchange sends a short-lived
 * JWT signed with that key (ES256), built from the team ID, the key ID and
 * the Services ID. Asking for the name and e-mail makes Apple post the answer
 * back (form_post), and the name comes only with a person's first sign-in.
 * Apple does not document PKCE for the web, so none is sent; state and nonce
 * bind the answer to the request.
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
    'label'            => 'Apple',
    'icon'             => 'bi-apple',
    'accounts'         => false,
    'authorize_url'    => 'https://appleid.apple.com/auth/authorize',
    'token_url'        => 'https://appleid.apple.com/auth/token',
    'revoke_url'       => '',
    'userinfo_url'     => '',
    'issuers'          => array('https://appleid.apple.com'),
    'identity_scopes'  => array('name', 'email'),
    'authorize_params' => array(
        'response_mode' => 'form_post',
    ),
    'client_auth'      => 'post',
    'token_per_scope'  => false,
    'console_url'      => 'https://developer.apple.com/account/resources/identifiers/list/serviceId',
    'options'          => array(
        'team_id' => array(
            'label'   => lang('Team ID'),
            'help'    => lang('Ten characters, shown at the top right of the Apple Developer account.'),
            'default' => '',
        ),
        'key_id'  => array(
            'label'   => lang('Key ID'),
            'help'    => lang('Ten characters, shown with the key under Certificates, Identifiers & Profiles, Keys.'),
            'default' => '',
        ),
    ),
    'setup'            => array(
        lang('In the Apple Developer account, under Certificates, Identifiers & Profiles, register a Services ID and turn on Sign in with Apple for it.'),
        lang('Configure it with the primary App ID, the site\'s domain and the return address below.'),
        lang('Under Keys, create a key with Sign in with Apple enabled, download the .p8 file (Apple offers it only once) and paste its contents here with the key ID and your team ID.'),
        lang('People can hide their address behind an Apple relay address. Register the domain the site sends e-mail from under Sign in with Apple for Email Communication, or messages to those addresses do not arrive.'),
    ),
    'signin'           => array(
        'order'         => 30,
        'scopes'        => array('name', 'email'),
        'pkce'          => false,
        'client_secret' => 'apple_jwt',
        'user_field'    => 'user',
    ),
);

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The base purpose of a connected account (includes/fn/connect.php): who the
 * account is, and a lasting permission. A feature that needs more defines its
 * own purpose with its scopes; connecting for it adds them to the same
 * account.
 *
 * A purpose file returns:
 *
 *   label        what the screens call it
 *   description  one sentence on what it lets the site do
 *   owner        'site' (the site's own account), 'person' (each person's
 *                own) or 'any'
 *   scopes       provider key => the scopes it asks for beyond the
 *                provider's identity scopes; a provider not listed cannot
 *                serve it
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
    'label'       => lang('Account'),
    'description' => lang('Who the account is. Features ask for more when they need it.'),
    'owner'       => 'any',
    'scopes'      => array(
        'google'    => array(),
        'microsoft' => array(),
        'dropbox'   => array(),
    ),
);

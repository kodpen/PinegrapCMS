<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Merchant Center through the Merchant API (includes/fn/merchant_center.php):
 * registers the site's Merchant Center product feed as a data source Google
 * fetches every day and reads the account's product issues.
 *
 * content is the only scope the Merchant API takes; it covers the account's
 * data sources and its product statuses.
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
    'label'       => lang('Google Merchant Center'),
    'description' => lang('Gives Google the address of the product feed to fetch every day and shows the product problems Merchant Center reports.'),
    'owner'       => 'site',
    'scopes'      => array(
        'google' => array(
            'https://www.googleapis.com/auth/content',
        ),
    ),
);

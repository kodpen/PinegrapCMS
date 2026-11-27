<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's eBay seller account (includes/api/outbound/connectors/ebay.php):
 * its listings and their stock and prices, its orders, and the business
 * policies and inventory locations a listing names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

return array(
    'label'       => 'eBay',
    'description' => lang('Lists the shop\'s products on eBay and brings its eBay orders in.'),
    'owner'       => 'site',
    'scopes'      => array(
        'ebay'         => pg_ebay_scopes(),
        'ebay_sandbox' => pg_ebay_scopes(),
    ),
);

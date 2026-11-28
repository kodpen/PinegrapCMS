<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's Etsy shop (includes/api/outbound/connectors/etsy.php): its
 * listings and their stock and prices, its orders, and the shipping and
 * processing profiles a listing names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');

return array(
    'label'       => 'Etsy',
    'description' => lang('Lists the shop\'s products on Etsy and brings its Etsy orders in.'),
    'owner'       => 'site',
    'scopes'      => array(
        'etsy' => pg_etsy_scopes(),
    ),
);

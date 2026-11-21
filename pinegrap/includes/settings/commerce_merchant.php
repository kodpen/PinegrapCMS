<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Google Merchant Center part of the Product Feeds card
 * (includes/fn/merchant_center.php): its connection and the way to its page
 * on Connections (connections.php?service=merchant_center), where the
 * Google account is connected, the Merchant Center account and the country
 * are chosen, the feed is registered and the product issues are read.
 *
 * Runs in the scope of includes/settings/commerce.php and leaves the markup in
 * $product_feeds_merchant. The card posts nothing of Merchant Center.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

$product_feeds_merchant = '';

if (pg_merchant_ready()) {

    $merchant_state = pg_merchant_state();

    $product_feeds_merchant = '
                                        <div class="col-12">
                                            <h3 class="pg-group-label mb-1">' . lang('Google Merchant Center') . '</h3>
                                            <div class="form-text">' . lang('Gives Google the address of the product feed to fetch every day and shows the product problems Merchant Center reports.') . '</div>
                                        </div>' . pg_merchant_card_connection_html($merchant_state, is_array(pg_merchant_connection($merchant_state)));
}

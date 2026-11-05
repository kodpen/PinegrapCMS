<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once('edit_offer_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/offer_list_edit.php');

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen; the write is the offer editor's own save (pg_offer_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request(pg_offer_edit_columns(), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_offer_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user']);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);
// The editor saves through api.php and then sends the operator here, leaving
// its confirmation in this form - the same hand-off the other list screens use.
include_once('liveform.class.php');
$liveform = new liveform('view_offers');

// Sorting and searching are done by the table itself in the browser, so the
// query only fixes the initial order.
$rows = db_items(
    "SELECT
        offers.*,
        last_modified_user.user_username AS last_modified_username
    FROM offers
    LEFT JOIN user AS last_modified_user ON offers.user = last_modified_user.user_id
    ORDER BY offers.timestamp DESC");

$products = _pg_offer_products();
$shipping_methods = _pg_offer_shipping_methods();

$offers = array();
$counts = array('all' => 0, 'active' => 0, 'scheduled' => 0, 'expired' => 0, 'disabled' => 0, 'code' => 0, 'auto' => 0, 'incomplete' => 0);
if ($rows) {
    foreach ($rows as $row) {
        $offer = pg_offer_list_entry($row, $products, $shipping_methods);
        $status = $offer['status'];
        $offers[] = $offer;
        $counts['all']++;
        $counts[$status]++;
        $counts[$row['require_code'] ? 'code' : 'auto']++;
        if ($offer['incomplete']) {
            $counts['incomplete']++;
        }
    }
}

// What an operator can change on the list without opening the offer.
$quick_edit = pg_offer_edit_columns();

$orphans = _pg_offer_orphans();
$orphan_count = count($orphans['rules']) + count($orphans['actions']);

echo pg_page_shell(array(
    'title'               => lang('All Offers'),
    'extra classes'       => 'products',
    'icon'                => 'store',
    'heading'             => lang('All Offers'),
    'heading_description' => lang('Campaign offers applied at checkout')));

require('includes/templates/view_offers.php');

// The notice has been shown; it must not survive into the next visit.
$liveform->remove_form();

echo output_footer();

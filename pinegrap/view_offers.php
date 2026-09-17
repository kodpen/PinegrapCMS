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
$user = validate_user();
validate_ecommerce_access($user);
require_once('edit_offer_f.php');
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
$today = date('Y-m-d');

$offers = array();
$counts = array('all' => 0, 'active' => 0, 'scheduled' => 0, 'expired' => 0, 'disabled' => 0, 'code' => 0, 'auto' => 0, 'incomplete' => 0);
if ($rows) {
    foreach ($rows as $row) {
        // The editor's view of the offer gives the chips and the "incomplete"
        // check the same reading the editor itself has.
        $state = _pg_offer_load($row['id']);
        $status = _pg_offer_status($row);
        $incomplete = _pg_offer_is_incomplete($state);
        $condition_chips = array();
        foreach ($state['conditions'] as $condition) {
            $condition_chips[] = _pg_offer_condition_chip($condition, $products);
        }
        $action_chips = array();
        foreach ($state['actions'] as $action) {
            $action_chips[] = array(
                'icon' => _pg_offer_action_icon($action['type']),
                'text' => _pg_offer_action_chip($action, $products));
        }
        $open_ended = ($row['end_date'] == PG_OFFER_OPEN_END_DATE) && ($row['start_date'] <= $today);
        $offers[] = array(
            'id'                => (int) $row['id'],
            'code'              => $row['code'],
            'description'       => $row['description'],
            'status'            => $status,
            'status_label'      => _pg_offer_status_label($status),
            'enabled'           => ($row['status'] == 'enabled'),
            'require_code'      => (int) $row['require_code'],
            'incomplete'        => $incomplete,
            'condition_chips'   => $condition_chips,
            'action_chips'      => $action_chips,
            'upsell'            => ((int) $row['upsell'] == 1) && $state['conditions'],
            'period'            => $open_ended ? lang('Open-ended') : (prepare_form_data_for_output($row['start_date'], 'date') . ' – ' . prepare_form_data_for_output($row['end_date'], 'date')),
            'sentence'          => _pg_offer_sentence($state, $products, $shipping_methods),
            'modified'          => get_relative_time(array('timestamp' => $row['timestamp'])),
            'modified_by'       => $row['last_modified_username']);
        $counts['all']++;
        $counts[$status]++;
        $counts[$row['require_code'] ? 'code' : 'auto']++;
        if ($incomplete) {
            $counts['incomplete']++;
        }
    }
}

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

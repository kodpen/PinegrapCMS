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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');

// A key code enabled or disabled in place on the list (pg_list_edit_request()
// in includes/fn/output.php). Answered in JSON, before anything below draws
// the screen; the write is the one the key code's screen makes
// (pg_key_code_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request(pg_key_code_edit_columns(), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_key_code_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);

    // The code is the row's link to its key code, drawn again around the
    // code the answer redraws.
    if (isset($saved['cells']['code']['html'])) {
        $saved['cells']['code']['html'] = pg_commerce_list_open_link('edit_key_code.php?id=' . (int) $edit['id'], $saved['cells']['code']['html']);
    }

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_key_codes');

// What an operator can change on the list without opening the key code.
$quick_edit = pg_key_code_edit_columns();

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'code'            => lang('Key Code'),
    'offer_code'      => lang('Offer Code'),
    'offer_message'   => lang('Offer Message'),
    'enabled'         => lang('Enabled'),
    'expiration_date' => lang('Expiration Date'),
    'notes'           => lang('Notes'),
    'single_use'      => lang('Single-Use'),
    'report'          => lang('Report'),
    'last_modified'   => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_key_codes', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$sort_map = array(
    'code'            => 'key_codes.code',
    'offer_code'      => 'key_codes.offer_code',
    'offer_message'   => 'offers.description',
    'enabled'         => 'key_codes.enabled',
    'expiration_date' => 'key_codes.expiration_date',
    'notes'           => 'key_codes.notes',
    'single_use'      => 'key_codes.single_use',
    'report'          => 'key_codes.report',
    'last_modified'   => 'key_codes.timestamp',
);

$order_by = pg_list_order_by($sort_levels, $sort_map);

$all_key_codes = db_value("SELECT COUNT(*) FROM key_codes");

// If user requested to export, then export them.
if (($_GET['submit_data'] ?? '') == 'Export Key Codes') {

    // The export does not join offers, so the offer message is looked up
    // per row; one row per key code even when two offers share a code.
    $export_order_by = pg_list_order_by($sort_levels, array_merge($sort_map, array(
        'offer_message' => '(SELECT offers.description FROM offers WHERE offers.code = key_codes.offer_code LIMIT 1)',
    )));

    pg_csv_download_start('key_codes.csv');

    // Output column headings for CSV data.
    echo
        '"key_code",' .
        '"offer_code",' .
        '"enabled",' .
        '"expiration_date",' .
        '"notes",' .
        '"single_use",' .
        '"report",' .
        '"last_modified",' .
        '"last_modified_username"' . "\n";

    // Get all key codes.
    $key_codes = db_items(
        "SELECT
            key_codes.code,
            key_codes.offer_code,
            key_codes.enabled,
            key_codes.expiration_date,
            key_codes.notes,
            key_codes.single_use,
            key_codes.report,
            key_codes.timestamp AS last_modified_timestamp,
            last_modified_user.user_username AS last_modified_username
        FROM key_codes
        LEFT JOIN user AS last_modified_user ON key_codes.user = last_modified_user.user_id
        ORDER BY $export_order_by");

    // Loop through the key codes in order to output CSV data.
    foreach ($key_codes as $key_code) {
        echo pg_key_code_export_line($key_code);
    }

    exit;

// Otherwise the user did not select to export gift cards, so just list gift cards.
} else {

    // Get all key codes.
    $key_codes = db_items(
        "SELECT
            key_codes.id,
            key_codes.code,
            key_codes.offer_code,
            key_codes.enabled,
            key_codes.expiration_date,
            key_codes.notes,
            key_codes.single_use,
            key_codes.report,
            last_modified_user.user_username AS last_modified_username,
            key_codes.timestamp AS last_modified_timestamp,
            offers.id AS offer_id,
            offers.description AS offer_description,
            offers.status AS offer_status,
            offers.start_date AS offer_start_date,
            offers.end_date AS offer_end_date
        FROM key_codes
        LEFT JOIN offers ON key_codes.offer_code = offers.code
        LEFT JOIN user AS last_modified_user ON key_codes.user = last_modified_user.user_id
        ORDER BY $order_by");

    // Get the current date so that later we can figure out if key codes have expired.
    $current_date = date('Y-m-d');

    // Every key code is printed: the list table driver sorts and searches
    // them in the browser (browser mode). The cells a quick edit can change
    // are drawn by the same function that draws them again in the answer to
    // an edit: the code, green when it works at checkout today (enabled, not
    // expired, its offer running), the enabled mark and the last change.
    $list_rows = array();

    foreach ($key_codes as $key_code) {
        $cells = pg_key_code_list_cells($key_code, $current_date);
        $id = (int) $key_code['id'];
        $expires = ($key_code['expiration_date'] != '0000-00-00') ? strtotime($key_code['expiration_date']) : 0;

        $list_rows[] = array(
            'id' => $id,
            'cells' => array(
                'code' => pg_list_cell(pg_commerce_list_open_link('edit_key_code.php?id=' . $id, $cells['code']['html']), $cells['code']['class'], array('data-pg-sort-value' => (string) $key_code['code'])),
                'offer_code' => pg_list_cell($key_code['offer_id']
                    ? '<a class="pg-link-secondary" href="edit_offer.php?id=' . (int) $key_code['offer_id'] . '" title="' . h(lang('Edit Offer')) . '">' . h($key_code['offer_code']) . '</a>'
                    : h($key_code['offer_code'])),
                'offer_message' => pg_list_cell(h($key_code['offer_description'])),
                'enabled' => pg_list_cell($cells['enabled']['html'], $cells['enabled']['class'], pg_list_edit_cell_attributes($quick_edit, 'enabled', $id, !empty($key_code['enabled']) ? '1' : '0') + array('data-pg-sort-value' => !empty($key_code['enabled']) ? '1' : '0')),
                'expiration_date' => pg_list_cell($expires ? get_absolute_time(array('timestamp' => $expires, 'type' => 'date')) : '', 'align-middle', array('data-pg-sort-value' => (string) (int) $expires)),
                'notes' => pg_list_cell(nl2br(h($key_code['notes']))),
                'single_use' => pg_list_cell($key_code['single_use'] ? '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>' : '', 'align-middle text-center', array('data-pg-sort-value' => $key_code['single_use'] ? '1' : '0')),
                'report' => pg_list_cell('<span class="badge fw-light theme-secondary">' . h(($key_code['report'] == 'key_code') ? lang('Key Code') : lang('Offer Code')) . '</span>'),
                'last_modified' => pg_list_cell($cells['last_modified']['html'], $cells['last_modified']['class'], array('data-pg-sort-value' => (string) (int) $key_code['last_modified_timestamp'])),
            ),
        );
    }

    $list = pg_list_table_printed(array(
        'list' => 'view_key_codes',
        'columns' => pg_commerce_list_specs(pg_key_code_list_columns(), $sort_columns, $quick_edit),
        'levels' => $sort_levels,
        'rows' => $list_rows,
        'edit' => array('url' => 'view_key_codes.php', 'columns' => $quick_edit),
    ));

    echo pg_page_shell([
        'title'=> lang('All Key Codes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Key Codes'),
        'heading_description' => lang('Discount and access key codes'),
    ]);

    require('includes/templates/view_key_codes.php');

    echo pg_js_bundle_tags('assets/js/pg_list_table.min.js') . output_footer();

    $liveform->remove();
}
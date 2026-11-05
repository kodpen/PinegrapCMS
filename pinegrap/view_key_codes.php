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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/key_code_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/key_code_list_edit.php');

// Every value of the list the key code's screen changes is a field.
$quick_edit = pg_key_code_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen, through the key code screen's own check and save
// (pg_key_code_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_key_code_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_key_codes');

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

    $key_codes = db_items(pg_key_code_list_sql() . "
        ORDER BY $order_by");

    // Every key code is printed: the list table driver sorts and searches
    // them in the browser (browser mode). The cells are drawn by the same
    // function that draws them again in the answer to an edit.
    $current_date = date('Y-m-d');
    $list_rows = array();

    foreach ($key_codes as $key_code) {
        $list_rows[] = array(
            'id' => (int) $key_code['id'],
            'cells' => pg_key_code_list_row_cells($key_code, $current_date, $quick_edit),
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
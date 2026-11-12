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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/tax_zone_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/tax_zone_list_edit.php');

$quick_edit = pg_tax_zone_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn, with
// the tax zone screen's rights (validate_ecommerce_access()) and checks
// (pg_tax_zone_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_tax_zone_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_tax_zones');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'tax_rate'      => lang('Tax Rate'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_tax_zones', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'tax_zones.name',
    'tax_rate'      => 'tax_zones.tax_rate',
    'last_modified' => 'tax_zones.timestamp',
));

// Every tax zone is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort.
$decimal = pg_number_separators()['decimal'];
$list_rows = array();

foreach ((array) db_items(pg_tax_zone_list_sql() . " ORDER BY " . $order_by) as $tax_zone) {
    $list_rows[] = array('id' => (int) $tax_zone['id'], 'cells' => pg_tax_zone_list_cells($tax_zone, $quick_edit, $decimal));
}

$list = pg_list_table_printed(array(
    'list' => 'view_tax_zones',
    'columns' => pg_tax_zone_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_tax_zones.php', 'columns' => $quick_edit),
));

echo
    pg_page_shell([
        'title'=> lang('All Tax Zones'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All Tax Zones'),
        'heading_description' => lang('All geographic areas where your organization collects tax for any product.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_tax_zone.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_tax_zones'), pg_list_sort_menu('view_tax_zones.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Tax Zones')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

$liveform->remove_form();

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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_method_list_edit.php');

// Every value of the list the shipping method's screen changes is a field;
// the Service field offers the services of the carriers that are set up and
// any other one a method keeps, the Zones field every zone, as the screen's
// Allowed Zones picker.
$quick_edit = pg_shipping_method_edit_columns(pg_shipping_method_quick_services(
    pg_shipping_method_quick_ready(),
    db_values("SELECT DISTINCT service FROM shipping_methods WHERE service != ''")),
    pg_shipping_method_quick_zone_options());

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen, with the screen's rights and checks
// (pg_shipping_method_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_shipping_method_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

// Service and Real-Time Rate are drawn only while a method has a service.
$has_service = pg_shipping_method_list_has_service();

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'                  => lang('Name'),
    'message'               => lang('Message'),
    'code'                  => lang('Code'),
    'service'               => lang('Service'),
    'realtime_rate'         => lang('Real-Time Rate'),
    'base_rate'             => lang('Base Rate'),
    'primary_weight_rate'   => lang('Primary W. Rate'),
    'secondary_weight_rate' => lang('Secondary W. Rate'),
    'item_rate'             => lang('Item Rate'),
    'handling'              => lang('Handling'),
    'transit'               => lang('Transit'),
    'street'                => lang('Street'),
    'po_box'                => lang('PO Box'),
    'protected'             => lang('Protected'),
    'last_modified'         => lang('Last Modified'),
);

if (!$has_service) {
    unset($sort_columns['service'], $sort_columns['realtime_rate']);
}

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_shipping_methods', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'                  => 'shipping_methods.name',
    'message'               => 'shipping_methods.description',
    'code'                  => 'shipping_methods.code',
    'service'               => 'shipping_methods.service',
    'realtime_rate'         => 'shipping_methods.realtime_rate',
    'base_rate'             => 'shipping_methods.base_rate',
    'primary_weight_rate'   => 'shipping_methods.primary_weight_rate',
    'secondary_weight_rate' => 'shipping_methods.secondary_weight_rate',
    'item_rate'             => 'shipping_methods.item_rate',
    'handling'              => 'shipping_methods.handle_days',
    'transit'               => 'shipping_methods.base_transit_days',
    'street'                => 'shipping_methods.street_address',
    'po_box'                => 'shipping_methods.po_box',
    'protected'             => 'shipping_methods.protected',
    'last_modified'         => 'shipping_methods.timestamp',
));

$query = pg_shipping_method_list_sql() . "
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// Every method is printed: the list table driver sorts and searches them in
// the browser (browser mode).
$list_rows = array();
$now = date('Y-m-d H:i:s');

while ($row = mysqli_fetch_assoc($result)) {
    $list_rows[] = array(
        'id' => (int) $row['id'],
        'cells' => pg_shipping_method_list_cells($row, pg_shipping_method_list_zones($row['id']), $quick_edit, $now),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_shipping_methods',
    'columns' => pg_commerce_list_specs(pg_shipping_method_list_columns($has_service), $sort_columns, $quick_edit),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_shipping_methods.php', 'columns' => $quick_edit),
));

echo
pg_page_shell([
        'title'=> lang('All Shipping Methods'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All Shipping Methods'),
        'heading_description' => lang('All shipping options and fees, based on the carrier, product, destination, and arrival date.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_shipping_method.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_shipping_methods'), pg_list_sort_menu('view_shipping_methods.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Shipping Methods')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
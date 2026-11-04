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

// A rate changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen; the write is the one the shipping method's screen makes
// (pg_shipping_method_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request(pg_shipping_method_edit_columns(), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_shipping_method_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

// The rates an operator can change on the list without opening the method.
$quick_edit = pg_shipping_method_edit_columns();

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

$query = "SELECT
            shipping_methods.id,
            shipping_methods.name,
            shipping_methods.description,
            shipping_methods.code,
            shipping_methods.status,
            shipping_methods.start_time,
            shipping_methods.end_time,
            shipping_methods.service,
            shipping_methods.realtime_rate,
            shipping_methods.base_rate,
            shipping_methods.variable_base_rate,
            shipping_methods.base_rate_2,
            shipping_methods.base_rate_2_subtotal,
            shipping_methods.base_rate_3,
            shipping_methods.base_rate_3_subtotal,
            shipping_methods.base_rate_4,
            shipping_methods.base_rate_4_subtotal,
            shipping_methods.primary_weight_rate,
            shipping_methods.secondary_weight_rate,
            shipping_methods.item_rate,
            shipping_methods.handle_days,
            shipping_methods.base_transit_days,
            shipping_methods.street_address,
            shipping_methods.po_box,
            shipping_methods.protected,
            user.user_username as user,
            shipping_methods.timestamp
        FROM shipping_methods
        LEFT JOIN user ON shipping_methods.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// Every method is printed: the list table driver sorts and searches them in
// the browser (browser mode).
$list_rows = array();
$now = date('Y-m-d H:i:s');
$yes = '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>';

while ($row = mysqli_fetch_assoc($result)) {
    $id = (int) $row['id'];

    // Green when checkout offers the method, else red with the reason
    // under the name.
    $method_state = pg_shipping_method_list_state($row, $now);
    $status_color_class = ($method_state === '') ? ' fg-success' : ' fg-danger';
    $output_state = ($method_state === 'Disabled') ? pg_list_status_word(lang('Disabled')) : (($method_state === 'Inactive') ? pg_list_status_word(lang('Inactive')) : '');

    // The cells a quick edit can change, drawn by the same function that
    // draws them again in the answer to an edit: the base rate (each step of
    // a variable one), the item rate and the last change.
    $list_cells = pg_shipping_method_list_cells($row);

    $zones = db_values(
        "SELECT zones.name
        FROM shipping_methods_zones_xref
        LEFT JOIN zones ON shipping_methods_zones_xref.zone_id = zones.id
        WHERE shipping_methods_zones_xref.shipping_method_id = '" . $id . "'
        ORDER BY zones.name");

    $list_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell(pg_commerce_list_open_link('edit_shipping_method.php?id=' . $id, '<span class="d-inline-block text-wrap text-break" style="min-width:12rem;max-width:20rem;">' . h($row['name']) . '</span>') . $output_state, 'align-middle chart_label' . $status_color_class, array('data-pg-sort-value' => (string) $row['name'])),
            'message' => pg_list_cell('<div class="text-wrap text-break" style="min-width:15rem;max-width:24rem;">' . h($row['description']) . '</div>'),
            'code' => pg_list_cell('<span class="badge fg-body fw-light">' . h($row['code']) . '</span>', 'align-middle', array('data-pg-sort-value' => (string) $row['code'])),
            'service' => pg_list_cell(h(get_shipping_service_name($row['service']))),
            'realtime_rate' => pg_list_cell($row['realtime_rate'] ? $yes : '', 'align-middle', array('data-pg-sort-value' => $row['realtime_rate'] ? '1' : '0')),
            'base_rate' => pg_list_cell($list_cells['base_rate']['html'], $list_cells['base_rate']['class'], ($row['variable_base_rate'] ? array() : pg_list_edit_cell_attributes($quick_edit, 'base_rate', $id, pg_format_input_amount($row['base_rate'] / 100))) + array('data-pg-sort-value' => (string) (int) $row['base_rate'])),
            'primary_weight_rate' => pg_list_cell(pg_format_money($row['primary_weight_rate'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle', array('data-pg-sort-value' => (string) (int) $row['primary_weight_rate'])),
            'secondary_weight_rate' => pg_list_cell(pg_format_money($row['secondary_weight_rate'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle', array('data-pg-sort-value' => (string) (int) $row['secondary_weight_rate'])),
            'item_rate' => pg_list_cell($list_cells['item_rate']['html'], $list_cells['item_rate']['class'], pg_list_edit_cell_attributes($quick_edit, 'item_rate', $id, pg_format_input_amount($row['item_rate'] / 100)) + array('data-pg-sort-value' => (string) (int) $row['item_rate'])),
            // Blank instead of zero handling days.
            'handling' => pg_list_cell($row['handle_days'] ? h($row['handle_days']) : '', 'align-middle', array('data-pg-sort-value' => (string) (int) $row['handle_days'])),
            'transit' => pg_list_cell(h($row['base_transit_days'])),
            'street' => pg_list_cell($row['street_address'] ? $yes : '', 'align-middle', array('data-pg-sort-value' => $row['street_address'] ? '1' : '0')),
            'po_box' => pg_list_cell($row['po_box'] ? $yes : '', 'align-middle', array('data-pg-sort-value' => $row['po_box'] ? '1' : '0')),
            'zones' => pg_list_cell(implode(',<br>', array_map('h', $zones))),
            'protected' => pg_list_cell($row['protected'] ? $yes : '', 'align-middle', array('data-pg-sort-value' => $row['protected'] ? '1' : '0')),
            'last_modified' => pg_list_cell($list_cells['last_modified']['html'], $list_cells['last_modified']['class'], array('data-pg-sort-value' => (string) (int) $row['timestamp'])),
        ),
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
        'extra classes'=>'products',
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
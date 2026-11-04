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

// Every zone is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort.
$list_rows = array();
$user = validate_user();
validate_ecommerce_access($user);

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'                  => lang('Name'),
    'base_rate'             => lang('Base Rate'),
    'primary_weight_rate'   => lang('Primary W. Rate'),
    'secondary_weight_rate' => lang('Secondary W. Rate'),
    'item_rate'             => lang('Item Rate'),
    'last_modified'         => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_zones', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'                  => 'zones.name',
    'base_rate'             => 'zones.base_rate',
    'primary_weight_rate'   => 'zones.primary_weight_rate',
    'secondary_weight_rate' => 'zones.secondary_weight_rate',
    'item_rate'             => 'zones.item_rate',
    'last_modified'         => 'zones.timestamp',
));

$query = "SELECT
            zones.id,
            zones.name,
            zones.base_rate,
            zones.primary_weight_rate,
            zones.secondary_weight_rate,
            zones.item_rate,
            user.user_username as user,
            zones.timestamp
        FROM zones
        LEFT JOIN user ON zones.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

while ($row = mysqli_fetch_assoc($result)) {
    $list_rows[] = array(
        'id' => (int) $row['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_zone.php?id=' . (int) $row['id'] . '" data-pg-open>' . h($row['name']) . '</a>'),
            'base_rate' => pg_list_cell(prepare_amount($row['base_rate'] / 100), 'align-middle', array('data-pg-sort-value' => (int) $row['base_rate'])),
            'primary_weight_rate' => pg_list_cell(prepare_amount($row['primary_weight_rate'] / 100), 'align-middle', array('data-pg-sort-value' => (int) $row['primary_weight_rate'])),
            'secondary_weight_rate' => pg_list_cell(prepare_amount($row['secondary_weight_rate'] / 100), 'align-middle', array('data-pg-sort-value' => (int) $row['secondary_weight_rate'])),
            'item_rate' => pg_list_cell(prepare_amount($row['item_rate'] / 100), 'align-middle', array('data-pg-sort-value' => (int) $row['item_rate'])),
            'last_modified' => pg_list_table_when_cell($row['timestamp'], (string) $row['user']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_zones',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'base_rate' => array('heading' => lang('Base Rate'), 'kind' => 'money'),
        'primary_weight_rate' => array('heading' => lang('Primary W. Rate'), 'kind' => 'money'),
        'secondary_weight_rate' => array('heading' => lang('Secondary W. Rate'), 'kind' => 'money'),
        'item_rate' => array('heading' => lang('Item Rate'), 'kind' => 'money'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
pg_page_shell([
        'title'=> lang('All Shipping Zones'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Shipping Zones'),
        'heading_description' => lang('All geographic areas where your organization can ship products.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_zone.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_zones'), pg_list_sort_menu('view_zones.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Shipping Zones')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

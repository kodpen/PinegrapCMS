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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/container_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
$liveform = new liveform('view_containers');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'enabled'       => lang('Enabled'),
    'length'        => lang('Length'),
    'width'         => lang('Width'),
    'height'        => lang('Height'),
    'weight'        => lang('Weight'),
    'cost'          => lang('Cost'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_containers', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'containers.name',
    'enabled'       => 'containers.enabled',
    'length'        => 'containers.length',
    'width'         => 'containers.width',
    'height'        => 'containers.height',
    'weight'        => 'containers.weight',
    'cost'          => 'containers.cost',
    'created'       => 'containers.created_timestamp',
    'last_modified' => 'containers.last_modified_timestamp',
));

$containers = db_items(
    "SELECT
        containers.id,
        containers.name,
        containers.enabled,
        containers.length,
        containers.width,
        containers.height,
        containers.weight,
        containers.cost / 100 AS cost,
        created_user.user_username AS created_username,
        containers.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        containers.last_modified_timestamp
    FROM containers
    LEFT JOIN user AS created_user ON containers.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON containers.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by");

// Every container is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort in the session.
// The icon is hidden from screen readers; the word beside it says the state.
$output_checkmark = '<i class="bi bi-check-circle" aria-hidden="true"></i><span class="visually-hidden">' . lang('Yes') . '</span>';
$output_no = '<span class="visually-hidden">' . lang('No') . '</span>';
$list_rows = array();

// A measure cell: the value with its unit, empty when not set; sorted by
// the number.
$measure_cell = function ($value, $unit) {
    return pg_list_cell(($value > 0) ? pg_container_format_measure($value) . ' ' . $unit : '', 'align-middle', array('data-pg-sort-value' => (float) $value));
};

foreach ($containers as $container) {
    $list_rows[] = array(
        'id' => $container['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_container.php?id=' . (int) $container['id'] . '" data-pg-open>' . h($container['name']) . '</a>', 'align-middle ' . ($container['enabled'] ? 'status_enabled' : 'status_disabled')),
            'enabled' => pg_list_cell($container['enabled'] ? $output_checkmark : $output_no, 'align-middle', array('data-pg-sort-value' => $container['enabled'] ? 1 : 0)),
            'length' => $measure_cell($container['length'], lang('in')),
            'width' => $measure_cell($container['width'], lang('in')),
            'height' => $measure_cell($container['height'], lang('in')),
            'weight' => $measure_cell($container['weight'], lang('lbs')),
            'cost' => pg_list_cell(($container['cost'] > 0) ? pg_format_money($container['cost'], BASE_CURRENCY_SYMBOL) : '', 'align-middle', array('data-pg-sort-value' => (float) $container['cost'])),
            'created' => pg_list_table_when_cell($container['created_timestamp'], $container['created_username'] ?? ''),
            'last_modified' => pg_list_table_when_cell($container['last_modified_timestamp'], $container['last_modified_username'] ?? ''),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_containers',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'enabled' => array('heading' => lang('Enabled'), 'kind' => 'check'),
        'length' => array('heading' => lang('Length'), 'kind' => 'number'),
        'width' => array('heading' => lang('Width'), 'kind' => 'number'),
        'height' => array('heading' => lang('Height'), 'kind' => 'number'),
        'weight' => array('heading' => lang('Weight'), 'kind' => 'number'),
        'cost' => array('heading' => lang('Cost'), 'kind' => 'money'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo
    pg_page_shell([
        'title'=> lang('All Containers'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Containers'),
        'heading_description' => lang('All shipping containers (e.g. boxes) that products are packaged in.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_container.php', 'icon' => 'bi-plus-circle'),
                    'search'  => array(pg_list_table_search_box('view_containers'), pg_list_sort_menu('view_containers.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Containers')) . '
            </div>
        </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form();

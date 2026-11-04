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

$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_ship_date_adjustments');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'zip_code_prefix' => lang('Zip Code Prefix'),
    'shipping_method' => lang('Shipping Method'),
    'adjustment'      => lang('Adjustment'),
    'created'         => lang('Created'),
    'last_modified'   => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_ship_date_adjustments', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'zip_code_prefix' => 'ship_date_adjustments.zip_code_prefix',
    'shipping_method' => 'shipping_methods.name',
    'adjustment'      => 'ship_date_adjustments.adjustment',
    'created'         => 'ship_date_adjustments.created_timestamp',
    'last_modified'   => 'ship_date_adjustments.last_modified_timestamp',
));

$ship_date_adjustments = db_items(
    "SELECT
        ship_date_adjustments.id,
        ship_date_adjustments.zip_code_prefix,
        shipping_methods.name AS shipping_method_name,
        shipping_methods.code AS shipping_method_code,
        ship_date_adjustments.adjustment,
        created_user.user_username AS created_username,
        ship_date_adjustments.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        ship_date_adjustments.last_modified_timestamp
    FROM ship_date_adjustments
    LEFT JOIN shipping_methods ON ship_date_adjustments.shipping_method_id = shipping_methods.id
    LEFT JOIN user AS created_user ON ship_date_adjustments.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON ship_date_adjustments.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by");

// Every adjustment is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($ship_date_adjustments as $item) {

    $output_shipping_method = h($item['shipping_method_name']);

    if ($item['shipping_method_code'] != '') {
        $output_shipping_method .= ' (' . h($item['shipping_method_code']) . ')';
    }

    if ($item['adjustment'] < 0) {
        $days = abs((int)$item['adjustment']);
        $suffix = ($days > 1) ? 's' : '';
        $output_adjustment = lang(array('string' => '{var:1} day{suffix} earlier', 'vars' => array($days), 'suffix' => $suffix));
    } else {
        $days = (int)$item['adjustment'];
        $suffix = ($days > 1) ? 's' : '';
        $output_adjustment = lang(array('string' => '{var:1} day{suffix} later', 'vars' => array($days), 'suffix' => $suffix));
    }

    $list_rows[] = array(
        'id' => (int) $item['id'],
        'cells' => array(
            'zip_code_prefix' => pg_list_cell('<a href="edit_ship_date_adjustment.php?id=' . (int) $item['id'] . '" data-pg-open>' . h($item['zip_code_prefix']) . '</a>'),
            'shipping_method' => pg_list_cell($output_shipping_method),
            'adjustment' => pg_list_cell($output_adjustment, 'align-middle', array('data-pg-sort-value' => (int) $item['adjustment'])),
            'created' => pg_list_table_when_cell($item['created_timestamp'], (string) $item['created_username']),
            'last_modified' => pg_list_table_when_cell($item['last_modified_timestamp'], (string) $item['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_ship_date_adjustments',
    'columns' => array(
        'zip_code_prefix' => array('heading' => lang('Zip Code Prefix')),
        'shipping_method' => array('heading' => lang('Shipping Method')),
        'adjustment' => array('heading' => lang('Adjustment')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo
    pg_page_shell([
        'title'=> lang('All Ship Date Adjustments'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Ship Date Adjustments'),
        'heading_description' => lang('Allow a ship date for a recipient to be adjusted for a particular zip code prefix and shipping method.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_ship_date_adjustment.php', 'icon' => 'bi-plus-circle'),
                    'search'  => array(pg_list_table_search_box('view_ship_date_adjustments'), pg_list_sort_menu('view_ship_date_adjustments.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Ship Date Adjustments')) . '
            </div>
        </div>
    
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

$liveform->remove_form();

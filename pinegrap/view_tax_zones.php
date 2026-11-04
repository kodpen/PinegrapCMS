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

$tax_zones = db_items(
    "SELECT
        tax_zones.id,
        tax_zones.name,
        tax_zones.tax_rate,
        user.user_username AS last_modified_username,
        tax_zones.timestamp AS last_modified_timestamp
    FROM tax_zones
    LEFT JOIN user ON tax_zones.user = user.user_id
    ORDER BY $order_by");

// Every tax zone is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($tax_zones as $tax_zone) {

    // format_tax_rate() trims the stored rate to the decimals it has
    // (20.000 -> 20, 18.500 -> 18.5); the number keeps that many decimals in
    // the panel's separators and the template puts the sign on the side the
    // language writes it (TR "%20").
    $tax_rate = format_tax_rate($tax_zone['tax_rate']);
    $tax_rate_decimals = (strpos($tax_rate, '.') === false) ? 0 : strlen($tax_rate) - strpos($tax_rate, '.') - 1;

    $list_rows[] = array(
        'id' => (int) $tax_zone['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_tax_zone.php?id=' . (int) $tax_zone['id'] . '" data-pg-open>' . h($tax_zone['name']) . '</a>'),
            'tax_rate' => pg_list_cell(h(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($tax_rate, $tax_rate_decimals))))), 'align-middle', array('data-pg-sort-value' => $tax_rate)),
            'last_modified' => pg_list_table_when_cell($tax_zone['last_modified_timestamp'], (string) $tax_zone['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_tax_zones',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'tax_rate' => array('heading' => lang('Tax Rate'), 'kind' => 'number'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo
    pg_page_shell([
        'title'=> lang('All Tax Zones'),
        'extra classes'=>'products',
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

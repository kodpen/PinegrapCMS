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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/country_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/country_list_edit.php');

// Every value the country's screen changes is a field.
$quick_edit = pg_country_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen, with the country screen's rights (validate_ecommerce_access())
// and checks (pg_country_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_country_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

// Every country is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();
$user = validate_user();
validate_ecommerce_access($user);

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'               => lang('Name'),
    'code'               => lang('Code'),
    'zip_code_required'  => lang('Zip Code Required'),
    'transit_adjustment' => lang('Extra Delivery Days'),
    'default'            => lang('Default'),
    'last_modified'      => lang('Last Modified'),
);

$sort_default = array('sort' => 'name', 'order' => 'asc');

$list_state = pg_list_state('view_countries', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'               => 'countries.name',
    'code'               => 'countries.code',
    'zip_code_required'  => 'countries.zip_code_required',
    'transit_adjustment' => 'countries.transit_adjustment_days',
    'default'            => 'countries.default_selected',
    'last_modified'      => 'countries.timestamp',
));

$result = mysqli_query(db::$con, pg_country_list_sql() . " ORDER BY $order_by") or output_error('Query failed.');

$country_rows = array();

while ($row = mysqli_fetch_assoc($result)) {
    $country_rows[] = $row;
}

// The name in the panel's language; two rows with the same name on screen
// carry their code.
$country_rows = pg_country_list_labelled($country_rows);

// Sorted by name first: by the name on screen in the panel language's
// order, not by the stored English name the query orders by ("Türkiye"
// among the T's, not where "Turkey" was).
if ((($sort_levels[0]['sort'] ?? '') === 'name') && $country_rows) {
    $country_rows = pg_geo_sort_by_label($country_rows);

    if (($sort_levels[0]['order'] ?? 'asc') === 'desc') {
        $country_rows = array_reverse($country_rows);
    }
}

foreach ($country_rows as $row) {
    $list_rows[] = array('id' => (int) $row['id'], 'cells' => pg_country_list_cells($row, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_countries',
    'columns' => pg_country_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_countries.php', 'columns' => $quick_edit),
));

echo
    pg_page_shell([
        'title'=> lang('All Countries'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Countries'),
        'heading_description' => lang('All countries that are valid for billing address and shipping address selection.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_country.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_countries'), pg_list_sort_menu('view_countries.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Countries')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

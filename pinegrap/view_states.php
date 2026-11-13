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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/state_list_edit.php');

// Every value the state's screen changes is a field; Country offers the
// countries of the screen's picker.
$quick_edit = pg_state_edit_columns(pg_state_quick_countries());

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen, with the state screen's rights (validate_ecommerce_access()) and
// checks (pg_state_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_state_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

// Every state is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort.
$list_rows = array();
$user = validate_user();
validate_ecommerce_access($user);

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'code'          => lang('Code'),
    'country'       => lang('Country'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'name', 'order' => 'asc');

$list_state = pg_list_state('view_states', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'states.name',
    'code'          => 'states.code',
    'country'       => 'countries.name',
    'last_modified' => 'states.timestamp',
));

$result = mysqli_query(db::$con, pg_state_list_sql() . " ORDER BY $order_by") or output_error('Query failed.');
$state_rows = array();

while ($row = mysqli_fetch_assoc($result)) {
    // The country in the panel's language.
    $state_rows[] = pg_state_list_labelled($row);
}

// Sorted by country first: by the country name on screen in the panel
// language's order, not by the stored English name the query orders by.
// Rows of one country keep the query's order (the further sort levels);
// descending reverses around the stable sort so they keep it there too.
// Sorted by name first: likewise by the panel language's order, as the
// collation sorts Ş among the S's and Ç among the C's.
$first_sort = $sort_levels[0]['sort'] ?? '';
$label_keys = array('country' => 'country_label', 'name' => 'name');

if (isset($label_keys[$first_sort]) && $state_rows) {
    $label_key = $label_keys[$first_sort];

    if (($sort_levels[0]['order'] ?? 'asc') === 'desc') {
        $state_rows = array_reverse(pg_geo_sort_by_label(array_reverse($state_rows), $label_key));
    } else {
        $state_rows = pg_geo_sort_by_label($state_rows, $label_key);
    }
}

foreach ($state_rows as $row) {
    $list_rows[] = array('id' => (int) $row['id'], 'cells' => pg_state_list_cells($row, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_states',
    'columns' => pg_state_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_states.php', 'columns' => $quick_edit),
));

print
pg_page_shell([
        'title'=> lang('All States'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All States'),
        'heading_description' => lang('All states/provinces that are valid for billing address and shipping address selection.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_state.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_states'), pg_list_sort_menu('view_states.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All States')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

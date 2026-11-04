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

$query = "SELECT
            states.id,
            states.name,
            states.code,
            countries.name as country_name,
            countries.code as country_code,
            user.user_username as user,
            states.timestamp
        FROM states
        LEFT JOIN countries ON states.country_id = countries.id
        LEFT JOIN user ON states.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$state_rows = array();

while ($row = mysqli_fetch_assoc($result)) {
    // The country in the panel's language.
    $row['country_label'] = ($row['country_name'] !== null) ? pg_geo_country_label($row['country_name'], $row['country_code']) : '';
    $state_rows[] = $row;
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
    $list_rows[] = array(
        'id' => (int) $row['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_state.php?id=' . (int) $row['id'] . '" data-pg-open>' . h($row['name']) . '</a>'),
            'code' => pg_list_cell(h($row['code'])),
            'country' => pg_list_cell(h($row['country_label'])),
            'last_modified' => pg_list_table_when_cell($row['timestamp'], (string) $row['user']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_states',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'code' => array('heading' => lang('Code')),
        'country' => array('heading' => lang('Country')),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
pg_page_shell([
        'title'=> lang('All States'),
        'extra classes'=>'products',
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

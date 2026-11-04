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

// Every referral source is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_referral_sources');



// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'code'          => lang('Code'),
    'sort_order'    => lang('Sort Order'),
    'last_modified' => lang('Last Modified'),
);

// The order the checkout shows the sources in.
$sort_default = array('sort' => 'sort_order', 'order' => 'asc');

$list_state = pg_list_state('view_referral_sources', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'name',
    'code'          => 'code',
    'sort_order'    => 'referral_sources.sort_order',
    'last_modified' => 'timestamp',
));

$query = "SELECT
            referral_sources.id as id,
            referral_sources.name as name,
            referral_sources.code as code,
            referral_sources.sort_order as sort_order,
            user.user_username as user,
            referral_sources.timestamp as timestamp
        FROM referral_sources
        LEFT JOIN user ON referral_sources.user = user.user_id
        ORDER BY $order_by, referral_sources.name";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {
    $list_rows[] = array(
        'id' => (int) $row['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_referral_source.php?id=' . (int) $row['id'] . '" data-pg-open>' . h($row['name']) . '</a>'),
            'code' => pg_list_cell(h($row['code'])),
            'sort_order' => pg_list_cell((string) (int) $row['sort_order']),
            'last_modified' => pg_list_table_when_cell($row['timestamp'], (string) $row['user']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_referral_sources',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'code' => array('heading' => lang('Code')),
        'sort_order' => array('heading' => lang('Sort Order'), 'kind' => 'number'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
    pg_page_shell([
        'title'=> lang('All Referral Sources'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('All Referral Sources'),
        'heading_description' => lang('All possible answers to the checkout question: "How did you hear about us?"'),
    ])  . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_referral_source.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_referral_sources'), pg_list_sort_menu('view_referral_sources.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . $liveform->output_notices() . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Referral Sources')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

$liveform->remove_form();

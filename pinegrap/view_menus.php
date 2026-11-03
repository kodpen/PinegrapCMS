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
validate_area_access($user, 'designer');

$liveform = new liveform('view_menus');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'effect'        => lang('Effect'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_menus', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'menus.name',
    'effect'        => 'menus.effect',
    'created'       => 'menus.created_timestamp',
    'last_modified' => 'menus.last_modified_timestamp',
));

// get all menus
$query =
    "SELECT
        menus.id,
        menus.name,
        menus.effect,
        menus.created_timestamp,
        menus.last_modified_timestamp,
        menus.last_modified_user_id,
        created_user.user_username as created_username,
        last_modified_user.user_username as last_modified_username
    FROM menus
    LEFT JOIN user as created_user ON menus.created_user_id = created_user.user_id
    LEFT JOIN user as last_modified_user ON menus.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$menus = array();

while ($row = mysqli_fetch_assoc($result)) {
    $menus[] = $row;
}

// Every menu is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort in the session.
$list_rows = array();
$effect_labels = array('' => lang('None'), 'Pop-up' => lang('Pop-up'), 'Accordion' => lang('Accordion'));

foreach ($menus as $menu) {
    $output_link_url = 'view_menu_items.php?id=' . $menu['id'] . '&from=design&send_to=' . urlencode(REQUEST_URL);

    // The effect as the form names it, so the list can be searched by it.
    $output_effect = $effect_labels[(string) $menu['effect']] ?? h($menu['effect']);

    $list_rows[] = array(
        'id' => $menu['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="' . h($output_link_url) . '" data-pg-open>' . h($menu['name']) . '</a>'),
            'effect' => pg_list_cell($output_effect),
            'created' => pg_list_cell(pg_list_when($menu['created_timestamp'], $menu['created_username'] ?? ''), 'align-middle', array('data-pg-sort-value' => (int) $menu['created_timestamp'])),
            'last_modified' => pg_list_cell(pg_list_when($menu['last_modified_timestamp'], $menu['last_modified_username'] ?? ''), 'align-middle', array('data-pg-sort-value' => (int) $menu['last_modified_timestamp'])),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_menus',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'effect' => array('heading' => lang('Effect')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo
pg_page_shell([
        'title'=> lang('All Menus'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Menus'),
        'heading_description' => lang('All shared menus that can be added to any page style and managed by any site manager.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_menu.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_menus'), pg_list_sort_menu('view_menus.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Menus')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form();
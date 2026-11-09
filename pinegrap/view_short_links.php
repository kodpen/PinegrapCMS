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
validate_area_access($user, 'manager');

include_once('liveform.class.php');

// The Recycle Bin and the short link helpers live with the File Manager;
// the screen's form code checks and saves a quick edit.
require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/short_link_list_edit.php');

$quick_edit = pg_short_link_quick_edit_columns(pg_short_link_quick_choices(), pg_short_link_redirects_ready(), pg_short_link_name_max(), pg_short_link_url_max());

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ((int) $user['role'] < 3);
    });

    $saved = pg_short_link_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$liveform = new liveform('view_short_links');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'             => lang('Name'),
    'destination_type' => lang('Destination Type'),
    'created'          => lang('Created'),
    'last_modified'    => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_short_links', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// The name each destination type is shown under; the column sorts by it.
$destination_type_names = pg_short_link_list_type_names();

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'             => 'short_links.name',
    'destination_type' => pg_list_label_order('short_links.destination_type', $destination_type_names),
    'created'          => 'short_links.created_timestamp',
    'last_modified'    => 'short_links.last_modified_timestamp',
));

// Links in the Recycle Bin are left out: they are restored or deleted for good
// in the File Manager, and the edit screen does not open them.
$sql_live = (pg_short_link_live_filter() != '') ? ' AND ' . pg_short_link_live_filter() : '';

// The named links as a CSV file (from,to,code), the form import_short_links.php
// reads back. A one-time link has no name to list.
if (($_GET['export'] ?? '') === 'csv') {
    $export_links = db_items(
        "SELECT
            short_links.name,
            short_links.destination_type,
            short_links.url,
            short_links.tracking_code,
            short_links.page_id,
            short_links.file_id,
            " . (pg_short_link_redirects_ready() ? "short_links.redirect_code," : "") . "
            page.page_name,
            product_groups.address_name AS product_group_address_name,
            products.address_name AS product_address_name,
            files.name AS file_name
        FROM short_links
        LEFT JOIN page ON short_links.page_id = page.page_id
        LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
        LEFT JOIN products ON short_links.product_id = products.id
        LEFT JOIN files ON short_links.file_id = files.id
        WHERE short_links.name <> '' AND short_links.destination_type <> 'workspace_guest'" . $sql_live . "
        ORDER BY short_links.name");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="short-links-' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');

    echo pg_short_link_csv($export_links, PATH);
    exit();
}

// Every link is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort in the session.
$short_links = db_items(pg_short_link_list_sql() . " ORDER BY " . $order_by);

$list_rows = array();

foreach ((array) $short_links as $short_link) {
    $list_rows[] = array('id' => $short_link['id'], 'cells' => pg_short_link_list_cells($short_link, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_short_links',
    'columns' => pg_short_link_list_columns(pg_short_link_redirects_ready(), true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_short_links.php', 'columns' => $quick_edit),
));

print
pg_page_shell(
    array(
        'title'=> lang('Short Links'),
        'extra classes'=>'page',
        'icon'=>'page', 
        'heading'=>lang('Short Links'),
        'heading_description' => lang('Short addresses for pages, product groups, products, files and URLs.'),
        
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => 'add_short_link.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array(
                    (pg_short_link_redirects_ready() ? array('label' => lang('Import'), 'url' => 'import_short_links.php', 'icon' => 'bi-upload') : ''),
                    array('label' => lang('Export'), 'url' => 'view_short_links.php?export=csv', 'icon' => 'bi-download'),
                ),
                'search'    => array(pg_list_table_search_box('view_short_links'), pg_list_sort_menu('view_short_links.php', $sort_columns, $sort_levels, $sort_default)),
                'view'      => (pg_not_found_log_ready() ? '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('View')) . '">
                    <a class="btn btn-text theme-secondary active" href="view_short_links.php" aria-current="page">' . h(lang('Short Links')) . '</a>
                    <a class="btn btn-text theme-secondary" href="view_not_found_log.php">' . h(lang('404 Log')) . '</a>
                </div>' : ''),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('Short Links')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . pg_fields_script() . '
' .
output_footer();

$liveform->remove_form();
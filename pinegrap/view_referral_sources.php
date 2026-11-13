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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/referral_source_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/referral_source_list_edit.php');

$quick_edit = pg_referral_source_quick_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn.
// The rights are the referral source screen's (validate_ecommerce_access()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_referral_source_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

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
    'name'          => 'referral_sources.name',
    'code'          => 'referral_sources.code',
    'sort_order'    => 'referral_sources.sort_order',
    'last_modified' => 'referral_sources.timestamp',
));

foreach ((array) db_items(pg_referral_source_list_sql() . " ORDER BY " . $order_by . ", referral_sources.name") as $source) {
    $list_rows[] = array('id' => (int) $source['id'], 'cells' => pg_referral_source_list_cells($source, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_referral_sources',
    'columns' => pg_referral_source_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_referral_sources.php', 'columns' => $quick_edit),
));

print
    pg_page_shell([
        'title'=> lang('All Referral Sources'),
        'extra classes'=>'setting pg-measure-list',
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

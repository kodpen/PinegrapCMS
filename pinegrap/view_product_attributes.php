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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');

// A name or label changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php), answered in JSON before the screen is drawn and
// checked as the attribute's screen checks it.
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request(pg_product_attribute_edit_columns(), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_product_attribute_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_product_attributes');

// Every attribute is printed: the list table driver sorts and searches them
// in the browser (browser mode). The sort codes are the column keys, kept
// in the session by pg_list_state() and by a heading click.
$quick_edit = pg_product_attribute_edit_columns();
$columns = pg_product_attribute_list_columns();
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');
$list_state = pg_list_state('view_product_attributes', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];
$list_rows = array();

foreach (pg_product_attribute_list_rows() as $row) {
    $list_rows[] = array('id' => $row['id'], 'cells' => pg_product_attribute_list_cells($row, $quick_edit));
}

$list = pg_list_table_printed(array(
    'list' => 'view_product_attributes',
    'columns' => $columns,
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_product_attributes.php', 'columns' => $quick_edit),
));

echo
    pg_page_shell([
        'title'=> lang('All Product Attributes'),
        'extra classes'=>'products pg-measure-list',
        'icon'=>'store',
        'heading'=>lang('All Product Attributes'),
        'heading_description' => lang('All attributes that can be assigned to products.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_product_attribute.php', 'icon' => 'bi-plus-circle'),
                    'search'  => array(pg_list_table_search_box('view_product_attributes'), pg_list_sort_menu('view_product_attributes.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Product Attributes')) . '
            </div>
        </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form();

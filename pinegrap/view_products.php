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
require_once(dirname(__FILE__) . '/seo.php');

// A cell of the list changed in place (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen; the write is the product screen's own (pg_pb_quick_update()).
if (pg_list_edit_requested()) {
    include_once('product_builder.php');

    // A page, folder, contact group or zone is one of the user's choices;
    // they are read only for such a cell, and only once the session is known.
    $choices = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && in_array($_POST['field'] ?? '', pg_pb_quick_edit_choice_codes(), true))
        ? pg_pb_quick_edit_choices(validate_user())
        : null;

    $edit = pg_list_edit_request(pg_pb_quick_edit_columns($choices), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_pb_quick_update($edit['id'], $edit['field'], $edit['value'], $edit['user']);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$liveform = new liveform('view_products');
$user = validate_user();
validate_ecommerce_access($user);

include_once('product_builder.php');

// Two views of the same catalog: every product, or the variant sets that group
// them. Remembered per session like the filter below it, so returning from an
// edit lands on the list the operator was working in rather than resetting to
// the other one.
//
// Handled before anything else on this screen runs: the product query, the
// filters and the export all belong to the product view and none of it is
// needed to draw the other one.
if (isset($_GET['mode'])) {
    $_SESSION['software']['view_products']['mode'] = (($_GET['mode'] ?? '') === 'variant_sets') ? 'variant_sets' : 'products';
}

if (isset($_SESSION['software']['view_products']['mode'])
    && (($_SESSION['software']['view_products']['mode'] ?? '') === 'variant_sets')) {
    pg_pb_variant_sets_screen();
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');

// The view is kept in the session; a name that is not a view on this site
// (an SEO view before its upgrade, a stale value) falls back to all products.
if (isset($_GET['filter']) && $_GET['filter']) {
    $_SESSION['software']['view_products']['filter'] = $_GET['filter'];
}

$filter = pg_product_list_filter_name($_SESSION['software']['view_products']['filter'] ?? '');
$_SESSION['software']['view_products']['filter'] = $filter;

// The view, its columns and its sort (includes/fn/product_list.php).
$view = pg_product_list_site_view($filter);

// A product in the Recycle Bin is not on this list or in its counts: deleting
// one here sends it there, and the Catalog Manager's bin is where it is
// restored or deleted for good.
$catalog_bin = pg_catalog_bin_ready();
$live_products_sql = $catalog_bin ? " AND (products.recycled = '0')" : '';

// if user requested to export products, then export them
if (($_GET['submit_data'] ?? '') == 'Export Products') {

    // export_products_f.php is also what the File Manager and the
    // spreadsheet writer call, so a workbook and a CSV of the same products
    // cannot disagree. The export reads the whole view, in the list's order.
    require_once(dirname(__FILE__) . '/export_products_f.php');

    // The export's query has no impact score, so that level sorts by
    // the last change instead.
    $export_table = pg_products_export_table(
        $view['where'],
        pg_list_order_by($view['sort_levels'], array('impact' => 'products.timestamp') + pg_product_list_sort_sql()) . ', products.id asc');

    pg_products_export_csv($export_table);

    // if at least 1 product was exported, then log activity
    if (count($export_table['rows']) > 0) {
        log_activity(
            lang(array('string' => '{var:1} product(s) were exported', 'vars' => count($export_table['rows']))),
            $_SESSION['sessionusername']);
    }

// else the user did not select to export products, so just list products
} else {
    // Refresh stale SEO scores before the views are counted and the list is
    // fetched (a weak-score view counts only current scores) - newest records
    // first, since those are the likeliest to be on the first screen - capped
    // per request so a large catalog cannot stall the page; leftovers are
    // covered by the nightly job and the refresh button on the Pages screen.
    if (pg_seo_schema_ready()) {
        $stale_product_ids = array();
        $stale_result = mysqli_query(db::$con, "SELECT id FROM products WHERE seo_analysis_current = 0 ORDER BY timestamp DESC LIMIT 200") or output_error('Query failed.');

        while ($stale_row = mysqli_fetch_assoc($stale_result)) {
            $stale_product_ids[] = $stale_row['id'];
        }

        if ($stale_product_ids) {
            // Budgeted for the same reason as the pages screen: this is
            // synchronous work inside the visitor's request.
            pg_seo_recalculate('product', $stale_product_ids, 3);
        }
    }

    // The filter menu, each view with its count.
    $output_filter_options = '';
    $all_products = null;

    foreach (pg_product_list_filters() as $option => $option_view) {
        // The Actions After an Order view lists every product, as All Products does.
        if (($option_view['where'] === '') && ($all_products !== null)) {
            $count = $all_products;
        } else {
            $count = (int) db_value('SELECT COUNT(id) FROM products WHERE ' . (($option_view['where'] !== '') ? $option_view['where'] : '1 = 1') . $live_products_sql);

            if ($option_view['where'] === '') {
                $all_products = $count;
            }
        }

        $output_filter_options .= '<option value="' . h($option) . '"' . (($option == $filter) ? ' selected="selected"' : '') . '>' . h($option_view['label']) . ' (' . pg_format_number($count, 0) . ')</option>';
    }

    // The list table driver prints the first rows; the next ones come from
    // its list_table_rows action (includes/panel/lists.php) as the list
    // scrolls. A cell of an editable column changes in place in its quick
    // edit mode, on this screen's pg_list_edit_request() endpoint above.
    $list_source = pg_product_list_source($user, array('filter' => $filter));

    if ($list_source === null) {
        output_error(lang('Access denied'));
    }

    $list_page = pg_list_table_page($list_source, array(), null);

    if ($list_page === false) {
        output_error(lang('Query failed.'));
    }

    // The bulk actions sit in the list's selection bar, which shows while
    // rows are selected, and post the selection to edit_products.php, which
    // reads it with the list's own query and rights. Modify Selected first
    // asks for the changes in its dialog, whose Save submits the form.
    $output_bulk_buttons = '<button type="button" value="Modify Selected" class="no-submit btn btn-sm btn-solid theme-primary" data-pg-modify-open><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang('Modify Selected') . '</button>'
        . '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h($catalog_bin ? lang(array('string' => 'Selected {var:1} will be moved to the Recycle Bin. They can be restored from the Recycle Bin in the Catalog Manager.', 'vars' => array(lang('products')))) : lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('products'))))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';

    $output = '
    <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                            <a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_product.php"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>' . lang(array('string'=>'Create') ) . '</a>
                            <a class="btn btn-sm btn-text theme-secondary" href="view_products.php?mode=variant_sets"><i class="bi bi-grid me-2" aria-hidden="true"></i>' . lang('Variant Sets') . '</a>
                            <a class="btn btn-sm btn-text theme-secondary" href="import_products.php"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>' . lang(array('string'=>'Import') ) . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <button type="submit" name="submit_data" value="Export Products" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-file-earmark-arrow-down me-2" aria-hidden="true"></i>' . lang(array('string'=>'Export') ) . '</button>
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            <label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                                <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                                <input type="search" class="form-ghost" data-pg-list-search="view_products" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
                            </label>
                            ' . pg_list_sort_menu('view_products.php', $view['sort_columns'], $view['sort_levels'], $view['sort_default']) . '
                            <form id="search_form" action="view_products.php" method="get" class="search_form">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text" title="' . h(lang('Products shown')) . '" for="filter_select"><i class="bi bi-eye" aria-hidden="true"></i></label>
                                    <select id="filter_select" aria-label="' . h(lang('Products shown')) . '" name="filter" class="form-control" title="' . lang('Products shown') . '" onchange="submit_form(\'search_form\')">' . $output_filter_options . '</select>
                                </div>
                            </form>
                </nav>
                <form name="form" id="view_products_form" action="edit_products.php" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="action">
                    <input type="hidden" name="selection">
                    <input type="hidden" name="list_params" value="' . h(encode_json(array('filter' => $filter))) . '">
                    ' . pg_list_table_html($list_source, $list_page, $view['heading'], $output_bulk_buttons) . '
                </form>
            </div>
        </div>
        ' . pg_seo_render_detail_offcanvas() . '
        ' . pg_product_modify_dialog('view_products_form') . '
    ';

    // What a barcode label needs besides the product, which its button
    // carries (pgPrintProductBarcode()).
    $barcode_print_js = '';
    if (defined('BARCODE_ENABLED') && BARCODE_ENABLED) {
        $barcode_print_js = '
        <script>
        window._pgViewBarcodeOpts = {
            apiUrl:        ' . json_encode(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php') . ',
            token:         ' . json_encode($_SESSION['software']['token']) . ',
            labelTemplate: ' . json_encode(BARCODE_LABEL_TEMPLATE ?: null) . '
        };
        </script>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Products'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>$view['heading'],
            'heading_description' => $view['subheading'],
            'head' => (defined('BARCODE_ENABLED') && BARCODE_ENABLED ?
                '<script src="assets/lib/JsBarcode/JsBarcode.all.min.js"></script>' : '')
        )
    ) . '<main id="content" class="container-fluid">' . $output . $barcode_print_js . ($view['show_image'] ? _pg_lightbox_assets_once() : '') . '</main>
    <script>
        // The bulk actions post what the list has selected (edit_products.php
        // resolves it with the list\'s own query and the user\'s rights).
        (function () {
            var root = document.querySelector(\'.pg-list-table[data-pg-list-table="view_products"]\');

            if (root) {
                root.addEventListener(\'pg-list-table:selection\', function (event) {
                    document.form.selection.value = JSON.stringify(event.detail.payload);
                });
            }
        }());
    </script>
    ' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
    ' . output_footer();
    $liveform->remove_form('view_products');
}

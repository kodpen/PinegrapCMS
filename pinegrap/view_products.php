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

    $edit = pg_list_edit_request(pg_pb_quick_edit_columns(), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_pb_quick_update($edit['id'], $edit['field'], $edit['value']);

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

// The view, its columns and its sort (includes/fn/product_list.php). The
// rows come one page at a time from the product_list_rows action.
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
        $plural_suffix = (count($export_table['rows']) == 1) ? '' : 's';
        $was_or_were = (count($export_table['rows']) == 1) ? 'was' : 'were';

        log_activity(count($export_table['rows']) . ' product' . $plural_suffix . ' ' . $was_or_were . ' exported', $_SESSION['sessionusername']);
    }

// else the user did not select to export products, so just list products
} else {
    // The filter menu, each view with its count.
    $output_filter_options = '';
    $all_products = null;

    foreach (pg_product_list_filters() as $option => $option_view) {
        // All Product Actions lists every product, as All Products does.
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

    // Refresh stale SEO scores before the list is fetched - newest records
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

    // The rows come from the product_list_rows action (includes/panel/lists.php).
    $output_list_attributes = ' data-pg-list-action="product_list_rows" data-pg-list-params="' . h(encode_json(array('filter' => $filter))) . '"';

    $output = '
    <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <nav id="button_bar" class="pg-toolbar navigation" aria-label="Button Bar">
                            <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_product.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <a class="btn btn-sm btn-outline theme-secondary m-1" href="view_products.php?mode=variant_sets"><span class="bi bi-grid me-3"></span>' . lang('Variant Sets') . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link pg-link-secondary py-0 m-1" href="import_products.php"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Products" class="btn btn-link pg-link-secondary py-0 m-1"><span class="material-icons me-1">file_download</span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_products.php', $view['sort_columns'], $view['sort_levels'], $view['sort_default']) . '
                            <form id="search_form" action="view_products.php" method="get" class="search_form">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" aria-label="' . h(lang('Content that viewed')) . '" name="filter" class="form-control" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . $output_filter_options . '</select>
                                </div>
                            </form>
                </nav>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="edit_products.php" method="post"> 
                            ' . get_token_field() . '
                            <input type="hidden" name="action">
                            <input type="hidden" name="edit_enabled">
                            <input type="hidden" name="edit_allowed_zones">
                            <input type="hidden" name="edit_disallowed_zones">
                            <input type="hidden" name="edit_change_price_method">
                            <input type="hidden" name="edit_price_value">
                            <input type="hidden" name="edit_inventory">
                            <input type="hidden" name="edit_inventory_quantity_process">
                            <input type="hidden" name="edit_tax_rate_method">
                            <input type="hidden" name="edit_tax_rate_value">
                            <input type="hidden" name="edit_inventory_quantity">
                            <table class="chart table-hover table" style="width:100%;display:none"' . $output_list_attributes . pg_list_edit_table('view_products.php', pg_pb_quick_edit_columns()) . '>
                                <thead>
                                    <tr>' . pg_product_list_headings($view) . '</tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Modify Selected" class=" btn mb-1 mt-1 btn-solid theme-primary disabled" onclick="window.open(\'edit_products.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'edit\',\'product\')"><span class="material-icons me-3">edit</span>' . lang(array('string'=>'Modify Selected') ) . '</button>
                                        <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . h($catalog_bin ? lang(array('string' => 'Selected {var:1} will be moved to the Recycle Bin. They can be restored from the Recycle Bin in the Catalog Manager.', 'vars' => array(lang('products')))) : lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('products'))))) . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        ' . pg_seo_render_detail_offcanvas() . '
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
    ) . '<main id="content" class="container-fluid">' . $output . $barcode_print_js . ($view['show_image'] ? _pg_lightbox_assets_once() : '') . '</main>' . output_footer();
    $liveform->remove_form('view_products');
}

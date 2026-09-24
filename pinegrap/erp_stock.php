<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - stock and cost.
 *
 * What each product has cost the store - the weighted average of the goods
 * on hand and the last purchase price - and what the stock on hand is worth
 * at that average, in the base currency; below it the movements the ERP's
 * documents made. The arithmetic lives in includes/erp/stock.php. Add
 * product_id= to the address to follow one product's movements.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_stock');

if (!erp_stock_ready()) {
    $liveform->mark_error('', lang('Stock and cost come with the software update; run the update to use this screen.'));
}

// A move whose request ended before the count changed is counted now, so the
// figures below are the counts as they are.
erp_stock_apply_pending();

$product_id = max(0, (int) ($_GET['product_id'] ?? 0));
$products = erp_stock_costed_products();
$moves = erp_stock_recent_moves($product_id, 200);
$kinds = erp_stock_kinds();

$output_rows = '';
$total_value = 0;
$product_title = '';

foreach ($products as $row) {
    $name = (trim((string) $row['product_name']) !== '') ? (string) $row['product_name'] : (string) $row['product_sku'];
    $tracked = ((int) $row['inventory'] === 1);
    $value = $tracked ? (int) round((int) $row['avg_cost'] * max(0, (int) $row['inventory_quantity'])) : 0;
    $total_value += $value;

    if ((int) $row['product_id'] === $product_id) {
        $product_title = $name;
    }

    $output_rows .= '
        <tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Stock movements') . '" onclick="window.location.href=\'erp_stock.php?product_id=' . (int) $row['product_id'] . '#erp_stock_moves\'"><i class="bi bi-list-ul"></i></button>
            </td>
            <td style="max-width:280px" class="text-truncate align-middle">' . h($name)
                . ((trim((string) $row['product_sku']) !== '') ? '<div class="small text-body-secondary font-monospace">' . h($row['product_sku']) . '</div>' : '') . '</td>
            <td class="align-middle text-end" data-order="' . ($tracked ? (int) $row['inventory_quantity'] : -1) . '">'
                . ($tracked ? h(erp_quantity_text($row['inventory_quantity'])) : '<span class="text-body-secondary">' . lang('Stock not tracked') . '</span>') . '</td>
            <td class="align-middle text-end" data-order="' . (int) $row['avg_cost'] . '">' . h(erp_money_out((int) $row['avg_cost'])) . '</td>
            <td class="align-middle text-end" data-order="' . (int) $row['last_cost'] . '">' . h(erp_money_out((int) $row['last_cost']))
                . (((string) $row['last_cost_date'] > '0000-00-00') ? '<div class="small text-body-secondary">' . h(prepare_form_data_for_output((string) $row['last_cost_date'], 'date')) . '</div>' : '') . '</td>
            <td class="align-middle text-end fw-bold" data-order="' . $value . '">' . ($tracked ? h(erp_money_out($value)) : '<span class="text-body-secondary fw-normal">—</span>') . '</td>
        </tr>';
}

$output_moves = '';

foreach ($moves as $move) {
    $name = (trim((string) $move['product_name']) !== '') ? (string) $move['product_name'] : (string) $move['product_sku'];
    $document = (trim((string) $move['full_number']) !== '') ? (string) $move['full_number'] : ('#' . (int) $move['invoice_id']);

    $output_moves .= '
        <tr>
            <td class="align-middle text-nowrap" data-sort="' . h(str_replace('-', '', (string) $move['doc_date'])) . sprintf('%07d', (int) $move['id'] % 10000000) . '">' . h(prepare_form_data_for_output((string) $move['doc_date'], 'date')) . '</td>
            <td class="align-middle text-nowrap"><a class="link-body-emphasis" href="edit_erp_invoice.php?id=' . (int) $move['invoice_id'] . '">' . h($document) . '</a></td>
            <td class="align-middle">' . h($kinds[(string) $move['kind']] ?? (string) $move['kind']) . '</td>
            <td class="align-middle"><a class="link-body-emphasis" href="erp_stock.php?product_id=' . (int) $move['product_id'] . '#erp_stock_moves">' . h($name) . '</a></td>
            <td class="align-middle">' . h(erp_stock_effect_text($move)) . '</td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) $move['unit_cost'] . '">' . h(erp_money_out((int) $move['unit_cost'])) . '</td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) $move['cost_total'] . '">' . h(erp_money_out((int) $move['cost_total'])) . '</td>
        </tr>';
}

if ($output_moves === '') {
    $output_moves = '<tr data-pg-sort-fixed><td colspan="7" class="text-center text-body-secondary py-4">' . lang('No movement yet. A purchase invoice with lines linked to products brings goods in.') . '</td></tr>';
}

$uncosted = erp_stock_uncosted_count();
$output_notes = '';

// The products at or below their minimum, the furthest below first.
$output_low = '';
$low_products = function_exists('erp_stock_low_products') ? erp_stock_low_products(20) : array();

if (!empty($low_products)) {
    $low_count = erp_stock_low_count();
    $low_rows = '';

    foreach ($low_products as $low) {
        $low_rows .= '
            <tr>
                <td><a class="link-body-emphasis" href="erp_stock.php?product_id=' . (int) $low['product_id'] . '#erp_stock_moves">' . h(erp_stock_product_label($low)) . '</a></td>
                <td class="text-end text-danger fw-bold">' . h(erp_quantity_text($low['inventory_quantity'])) . '</td>
                <td class="text-end">' . h(erp_quantity_text($low['min_quantity'])) . '</td>
            </tr>';
    }

    $output_low = '
            <div class="card my-4 border-danger-subtle">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-danger fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>' . h(lang(array('string' => 'Low stock: {var:1} product(s)', 'vars' => $low_count))) . '</span>
                    <a class="btn btn-sm btn-outline-danger m-1" href="erp_stock_minimums.php?show=low">' . lang('All of them') . '</a>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>' . lang('Product') . '</th><th class="text-end">' . lang('Stock') . '</th><th class="text-end">' . lang('Minimum') . '</th></tr></thead>
                        <tbody>' . $low_rows . '</tbody>
                    </table>
                </div>
            </div>';
}

if (!erp_stock_documents_on() && erp_stock_ready()) {
    $output_notes .= '<div class="form-text px-3 pb-2">' . lang('Documents do not change stock counts (ERP settings); costs are still kept.') . '</div>';
}

if ($uncosted > 0) {
    $output_notes .= '<div class="form-text px-3 pb-3">' . h(lang(array('string' => '{var:1} product(s) that track stock have no cost yet; a purchase invoice gives them one.', 'vars' => $uncosted))) . '</div>';
}

echo
pg_page_shell(array(
        'title' => lang('Stock and cost'),
        'extra classes' => 'erp erp_stock',
        'icon' => 'erp',
        'heading' => lang('Stock and cost'),
        'heading_description' => lang('What each product has cost and what the stock on hand is worth at that cost, in the base currency; with the movements the documents made.'),
        'cancel' => false,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <div class="pg-toolbar-grow small text-body-secondary text-truncate">
                    ' . h(lang(array('string' => '{var:1} product(s) with a cost; stock on hand worth {var:2}', 'vars' => array(count($products), erp_money_out($total_value))))) . '
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="add_erp_manual_invoice.php?direction=purchase" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-bag-plus me-1"></i>' . lang('New purchase invoice') . '</a>
                ' . ((function_exists('erp_stock_minimums_ready') && erp_stock_minimums_ready()) ? '<a class="btn btn-sm btn-outline-secondary" href="erp_stock_minimums.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-sliders me-1"></i>' . lang('Minimum stock') . '</a>' : '') . '
                ' . ((function_exists('erp_stock_counts_ready') && erp_stock_counts_ready()) ? '<a class="btn btn-sm btn-outline-secondary" href="erp_stock_counts.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-upc-scan me-1"></i>' . lang('Stock counts') . '</a>' : '') . '
            </nav>
            ' . $output_low . '

            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Product') . '</th>
                                <th class="text-end">' . lang('Stock') . '</th>
                                <th class="text-end">' . lang('Average cost') . '</th>
                                <th class="text-end">' . lang('Last purchase') . '</th>
                                <th class="text-end">' . lang('Stock value') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                        <tfoot>
                            <tr>
                                <th></th>
                                <th>' . lang('Total') . '</th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th class="text-end">' . h(erp_money_out($total_value)) . '</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                ' . $output_notes . '
            </div>

            <div class="card my-4" id="erp_stock_moves">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . (($product_id > 0)
                        ? h(lang(array('string' => 'Movements: {var:1}', 'vars' => ($product_title !== '') ? $product_title : ('#' . $product_id))))
                        : lang('Stock movements')) . '</span>
                    ' . (($product_id > 0) ? '<a class="btn btn-sm btn-outline-secondary m-1" href="erp_stock.php#erp_stock_moves">' . lang('All products') . '</a>' : '') . '
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Document') . '</th>
                                <th>' . lang('Movement') . '</th>
                                <th>' . lang('Product') . '</th>
                                <th>' . lang('Stock') . '</th>
                                <th class="text-end">' . lang('Unit cost') . '</th>
                                <th class="text-end">' . lang('Cost') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_moves . '</tbody>
                    </table>
                </div>
                <div class="form-text px-3 pb-3">' . lang('The latest 200 movements, newest first. A sales invoice raised from an order moves nothing here: the order took the goods out of stock.') . '</div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();

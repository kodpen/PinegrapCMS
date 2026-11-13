<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock history: every change of a product's stock at a location, filtered
 * by product, location, reason and period and paged on the server (the list
 * table driver), with the Transfer dialog that moves units between two
 * locations (includes/fn/stock_moves.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$liveform = new liveform('view_stock_moves');

$user = validate_user();
validate_ecommerce_access($user);

require_once(PG_FUNCTIONS_DIR . '/includes/fn/stock_moves.php');

if (!pg_stock_moves_user_may($user)) {
    output_error(lang('Access denied'));
}

if (!pg_stock_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$stock_locations = pg_stock_locations();

// ── The transfer: posted by the dialog, answered with a notice ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    $stock_back = 'view_stock_moves.php' . ((isset($_POST['filters_query']) && is_string($_POST['filters_query']) && preg_match('/^[A-Za-z0-9_\-=&%.+]*$/', $_POST['filters_query']) && ($_POST['filters_query'] !== '')) ? '?' . $_POST['filters_query'] : '');

    foreach (array('transfer_product', 'transfer_from', 'transfer_to', 'transfer_quantity', 'transfer_note') as $stock_field) {
        $liveform->assign_field_value($stock_field, isset($_POST[$stock_field]) && is_string($_POST[$stock_field]) ? $_POST[$stock_field] : '');
    }

    $stock_product = pg_stock_find_product($_POST['transfer_product'] ?? '');

    if (!$stock_product) {
        $liveform->mark_error('transfer_product', pg_stock_transfer_error_text('product'));
    } else {
        $stock_done = pg_stock_transfer(
            (int) $stock_product['id'],
            $_POST['transfer_from'] ?? '',
            $_POST['transfer_to'] ?? '',
            $_POST['transfer_quantity'] ?? '',
            mb_substr(trim((string) ($_POST['transfer_note'] ?? '')), 0, PG_STOCK_NOTE_LENGTH));

        if ($stock_done['ok']) {
            $liveform->remove_form('view_stock_moves');
            $liveform = new liveform('view_stock_moves');
            $liveform->add_notice(lang(array('string' => '{var:1} units of {var:2} were moved.', 'vars' => array((int) $_POST['transfer_quantity'], $stock_product['name']))));
        } else {
            $stock_fields = array('quantity' => 'transfer_quantity', 'same' => 'transfer_to', 'to' => 'transfer_to', 'to_disabled' => 'transfer_to',
                'from' => 'transfer_from', 'not_enough' => 'transfer_from', 'untracked' => 'transfer_product');
            $liveform->mark_error(isset($stock_fields[$stock_done['error']]) ? $stock_fields[$stock_done['error']] : 'transfer_quantity', pg_stock_transfer_error_text($stock_done['error']));
        }
    }

    go($stock_back);
}

// ── The filters, from the address ──
$stock_product_text = isset($_GET['product']) && is_string($_GET['product']) ? trim($_GET['product']) : '';
$stock_product_filter = ($stock_product_text !== '') ? pg_stock_find_product($stock_product_text) : null;
$stock_from_text = isset($_GET['from']) && is_string($_GET['from']) ? trim($_GET['from']) : '';
$stock_to_text = isset($_GET['to']) && is_string($_GET['to']) ? trim($_GET['to']) : '';

$stock_filters = pg_stock_move_filters(array(
    'product_id' => $stock_product_filter ? (int) $stock_product_filter['id'] : 0,
    'location_id' => $_GET['location_id'] ?? '',
    'reason' => $_GET['reason'] ?? '',
    'from' => pg_date_input_iso($stock_from_text),
    'to' => pg_date_input_iso($stock_to_text),
), array_column($stock_locations, 'id'));

// A value the screen cannot read stays in its box, marked, and filters
// nothing.
$stock_invalid = array(
    'product' => ($stock_product_text !== '') && !$stock_product_filter,
    'from' => ($stock_from_text !== '') && (pg_date_input_iso($stock_from_text) === ''),
    'to' => ($stock_to_text !== '') && (pg_date_input_iso($stock_to_text) === ''),
);

$list_source = pg_stock_move_list_source($user, array('filters' => $stock_filters));

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$list_page = pg_list_table_page($list_source, array(), null);

if ($list_page === false) {
    output_error(lang('Query failed.'));
}

$stock_filters_query = http_build_query(array_filter(array(
    'product' => $stock_product_text,
    'location_id' => $stock_filters['location_id'] ? $stock_filters['location_id'] : '',
    'reason' => $stock_filters['reason'],
    'from' => $stock_from_text,
    'to' => $stock_to_text,
), 'strlen'));

$stock_location_options = '<option value="">' . h(lang('All locations')) . '</option>';
$stock_transfer_from = '';
$stock_transfer_to = '';
$stock_default = pg_stock_pick_default($stock_locations);
$stock_posted_from = (string) $liveform->get_field_value('transfer_from');
$stock_posted_to = (string) $liveform->get_field_value('transfer_to');

foreach ($stock_locations as $stock_location) {
    $stock_id = (int) $stock_location['id'];
    $stock_name = h($stock_location['name']) . (((int) $stock_location['enabled'] === 1) ? '' : ' (' . h(lang('Off')) . ')');
    $stock_location_options .= '<option value="' . $stock_id . '"' . (($stock_filters['location_id'] === $stock_id) ? ' selected' : '') . '>' . $stock_name . '</option>';
    $stock_transfer_from .= '<option value="' . $stock_id . '"' . ((($stock_posted_from !== '') ? ((int) $stock_posted_from === $stock_id) : ($stock_id === $stock_default)) ? ' selected' : '') . '>' . $stock_name . '</option>';

    if ((int) $stock_location['enabled'] === 1) {
        $stock_transfer_to .= '<option value="' . $stock_id . '"' . (((int) $stock_posted_to === $stock_id) ? ' selected' : '') . '>' . $stock_name . '</option>';
    }
}

$stock_reason_options = '<option value="">' . h(lang('All reasons')) . '</option>';

foreach (pg_stock_move_reason_labels() as $stock_reason => $stock_label) {
    $stock_reason_options .= '<option value="' . h($stock_reason) . '"' . (($stock_filters['reason'] === $stock_reason) ? ' selected' : '') . '>' . h($stock_label) . '</option>';
}

$stock_has_filter = ($stock_filters_query !== '');
$stock_transfer_open = $liveform->check_form_errors();
$stock_transfer_product = ($stock_transfer_open || ((string) $liveform->get_field_value('transfer_product') !== ''))
    ? (string) $liveform->get_field_value('transfer_product')
    : ($stock_product_filter ? (string) $stock_product_filter['name'] : '');

$stock_field_error = function ($field) use ($liveform) {
    return $liveform->check_field_error($field) ? ' is-invalid' : '';
};

$stock_toolbar = pg_screen_toolbar(array(
    'primary' => $stock_locations
        ? '<button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-bs-toggle="dialog" data-bs-target="#stock_transfer_dialog"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . lang('Transfer') . '</button>'
        : array('label' => lang('Add a stock location'), 'url' => 'settings.php#pgset-stock-locations', 'icon' => 'bi-plus-circle'),
    'search' => '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                    <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-ghost" data-pg-list-search="view_stock_moves" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
                </label>',
));

echo
    pg_page_shell(array(
        'title' => lang('Stock history'),
        'extra classes' => 'stock_moves pg-measure-list',
        'icon' => 'inventory',
        'heading' => lang('Stock history'),
        'heading_description' => lang('Every change of stock at each location: who, where and why.'),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $stock_toolbar . '
            <form id="stock_filter_form" action="view_stock_moves.php" method="get" class="row gx-3 gy-3 align-items-end mb-5">
                <div class="col-12 sm:col-6 lg:col-3">
                    <label class="form-label" for="stock_filter_product">' . lang('Product') . '</label>
                    <input type="text" class="form-control form-control-sm' . ($stock_invalid['product'] ? ' is-invalid' : '') . '" id="stock_filter_product" name="product" value="' . h($stock_product_text) . '" placeholder="' . h(lang('SKU or #id')) . '"' . ($stock_invalid['product'] ? ' aria-describedby="stock_filter_product_error"' : '') . ' />
                    ' . ($stock_invalid['product'] ? '<div class="invalid-feedback" id="stock_filter_product_error">' . lang('No product has this SKU.') . '</div>' : '') . '
                </div>
                <div class="col-6 sm:col-3 lg:col-2">
                    <label class="form-label" for="stock_filter_location">' . lang('Location') . '</label>
                    <select class="form-control form-control-sm" id="stock_filter_location" name="location_id">' . $stock_location_options . '</select>
                </div>
                <div class="col-6 sm:col-3 lg:col-2">
                    <label class="form-label" for="stock_filter_reason">' . lang('Reason') . '</label>
                    <select class="form-control form-control-sm" id="stock_filter_reason" name="reason">' . $stock_reason_options . '</select>
                </div>
                <div class="col-6 sm:col-3 lg:col-2">
                    <label class="form-label" for="stock_filter_from">' . lang('Start') . '</label>
                    <input type="text" class="form-control form-control-sm' . ($stock_invalid['from'] ? ' is-invalid' : '') . '" id="stock_filter_from" name="from" value="' . h($stock_from_text) . '" data-pg-date />
                    ' . ($stock_invalid['from'] ? '<div class="invalid-feedback">' . lang('Enter a valid date.') . '</div>' : '') . '
                </div>
                <div class="col-6 sm:col-3 lg:col-2">
                    <label class="form-label" for="stock_filter_to">' . lang('End') . '</label>
                    <input type="text" class="form-control form-control-sm' . ($stock_invalid['to'] ? ' is-invalid' : '') . '" id="stock_filter_to" name="to" value="' . h($stock_to_text) . '" data-pg-date />
                    ' . ($stock_invalid['to'] ? '<div class="invalid-feedback">' . lang('Enter a valid date.') . '</div>' : '') . '
                </div>
                <div class="col-12 lg:col-1 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-solid theme-secondary">' . lang('Filter') . '</button>
                    ' . ($stock_has_filter ? '<a class="btn btn-sm btn-text theme-secondary" href="view_stock_moves.php">' . lang('Clear filters') . '</a>' : '') . '
                </div>
            </form>
            ' . pg_list_table_html($list_source, $list_page, lang('Stock history')) . '
        </div>
    </div>
</main>
' . ($stock_locations ? '
<dialog class="dialog pg-dialog-centered" id="stock_transfer_dialog" aria-labelledby="stock_transfer_dialog_label"' . ($stock_transfer_open ? ' data-pg-open-on-load' : '') . '>
    <form class="d-contents" action="view_stock_moves.php" method="post">
        <div class="dialog-header">
            <h5 class="dialog-title" id="stock_transfer_dialog_label"><i class="bi bi-arrow-left-right me-3" aria-hidden="true"></i>' . lang('Transfer') . '</h5>
            <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
        </div>
        <div class="dialog-body">
            ' . get_token_field() . '
            <input type="hidden" name="filters_query" value="' . h($stock_filters_query) . '" />
            <p class="form-text mt-0">' . lang('Moves units of one product from one location to another. The product\'s total stays the same.') . '</p>
            <div class="mb-5">
                <label class="form-label" for="transfer_product">' . lang('Product') . '</label>
                <input type="text" class="form-control' . $stock_field_error('transfer_product') . '" id="transfer_product" name="transfer_product" value="' . h($stock_transfer_product) . '" placeholder="' . h(lang('SKU or #id')) . '" required />
            </div>
            <div class="row gx-3 mb-5">
                <div class="col-6">
                    <label class="form-label" for="transfer_from">' . lang('From') . '</label>
                    <select class="form-control' . $stock_field_error('transfer_from') . '" id="transfer_from" name="transfer_from">' . $stock_transfer_from . '</select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="transfer_to">' . lang('To') . '</label>
                    <select class="form-control' . $stock_field_error('transfer_to') . '" id="transfer_to" name="transfer_to">' . $stock_transfer_to . '</select>
                </div>
            </div>
            <div class="mb-5">
                <label class="form-label" for="transfer_quantity">' . lang('Quantity') . '</label>
                <input type="number" min="1" step="1" class="form-control' . $stock_field_error('transfer_quantity') . '" id="transfer_quantity" name="transfer_quantity" value="' . h((string) $liveform->get_field_value('transfer_quantity')) . '" required />
            </div>
            <div class="mb-1">
                <label class="form-label" for="transfer_note">' . lang('Note') . '</label>
                <input type="text" class="form-control" id="transfer_note" name="transfer_note" maxlength="' . PG_STOCK_NOTE_LENGTH . '" value="' . h((string) $liveform->get_field_value('transfer_note')) . '" />
            </div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="btn btn-solid theme-secondary" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
            <button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . lang('Transfer') . '</button>
        </div>
    </form>
</dialog>
<script>
    // A refused transfer comes back with its dialog open and its fields kept.
    (function () {
        var dialog = document.getElementById(\'stock_transfer_dialog\');

        if (dialog && dialog.hasAttribute(\'data-pg-open-on-load\') && window.pgUI) {
            pgUI.dialog.show(dialog);
        }
    }());
</script>' : '') . '
' . pg_fields_script() . '
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form('view_stock_moves');

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one stock count: scan the shelf, correct the counts, apply them. A
 * scanner types the code and Enter, which sends the form; the page comes back
 * with the cursor in the box for the next read. The work lives in
 * includes/erp/stock_counts.php.
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
$liveform = new liveform('erp_stock_count');

$count_id = (int) ($_REQUEST['id'] ?? 0);
$count = erp_stock_count($count_id);

if ($count === null) {
    output_error(lang('The count could not be found.') . ' <a href="erp_stock_counts.php">' . lang('Stock counts') . '</a>');
    exit();
}

$self = PATH . SOFTWARE_DIRECTORY . '/erp_stock_count.php?id=' . $count_id;
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$open = ((string) $count['status'] === 'open') && !$readonly;

if ($_POST) {
    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? 'scan');

    if ($action === 'scan') {
        $quantity = trim((string) ($_POST['quantity'] ?? ''));
        $scanned = erp_stock_count_scan($count_id, (string) ($_POST['code'] ?? ''), ($quantity === '') ? 1 : (int) $quantity, !empty($_POST['set']));

        if ($scanned['success']) {
            $liveform->add_notice(h(lang(array('string' => '{var:1}: {var:2} counted.', 'vars' => array($scanned['product'], $scanned['counted'])))));
        } else {
            $liveform->mark_error('code', h($scanned['error']));
        }

        go($self);
    }

    if ($action === 'save') {
        $saved = erp_stock_count_save($count_id, (array) ($_POST['counted'] ?? array()));

        if (!$saved['success']) {
            $liveform->mark_error('_error', h($saved['error']));
        } else {
            $liveform->add_notice(h(lang('The counts were saved.')));
        }

        go($self);
    }

    if ($action === 'apply') {
        // What is typed in the boxes counts as saved: the operator applies
        // what the screen shows.
        if (isset($_POST['counted'])) {
            $saved = erp_stock_count_save($count_id, (array) $_POST['counted']);

            if (!$saved['success']) {
                $liveform->mark_error('_error', h($saved['error']));
                go($self);
            }
        }

        $applied = erp_stock_count_apply($count_id, (int) $user['id']);

        if (!$applied['success']) {
            $liveform->mark_error('_error', h($applied['error']));
            go($self);
        }

        log_activity(lang(array('string' => 'erp stock count ({var:1}) was applied: {var:2} of {var:3} product(s) changed', 'vars' => array((string) $count['title'], $applied['changed'], $applied['lines']))), $_SESSION['sessionusername']);
        $liveform->add_notice(h(lang(array('string' => 'The count was applied: the stock of {var:1} of {var:2} product(s) changed.', 'vars' => array($applied['changed'], $applied['lines'])))));
        go($self);
    }

    if ($action === 'cancel') {
        if (erp_stock_count_cancel($count_id)) {
            log_activity(lang(array('string' => 'erp stock count ({var:1}) was cancelled', 'vars' => (string) $count['title'])), $_SESSION['sessionusername']);
            $liveform->add_notice(h(lang('The count was cancelled; no stock was changed.')));
        }

        go($self);
    }

    go($self);
}

$statuses = erp_stock_count_statuses();
$status = $statuses[(string) $count['status']] ?? array((string) $count['status'], 'secondary');
$applied = ((string) $count['status'] === 'applied');
$items = erp_stock_count_items($count_id);

$output_rows = '';
$total_diff = 0;

foreach ($items as $item) {
    $name = (trim((string) $item['product_name']) !== '') ? (string) $item['product_name'] : (string) $item['product_sku'];
    $system = $applied ? (int) $item['system_before'] : (int) $item['inventory_quantity'];
    $diff = (int) $item['counted'] - $system;
    $total_diff += abs($diff);

    $output_rows .= '
        <tr>
            <td>' . h($name) . ((trim((string) $item['product_sku']) !== '' && trim((string) $item['product_sku']) !== trim($name)) ? '<div class="small text-body-secondary font-monospace">' . h((string) $item['product_sku']) . '</div>' : '') . '</td>
            <td class="text-end" data-sort="' . $system . '">' . $system . '</td>
            <td style="width:9rem" data-sort="' . (int) $item['counted'] . '">' . ($open
                ? '<input type="text" class="form-control form-control-sm text-end" name="counted[' . (int) $item['product_id'] . ']" value="' . (int) $item['counted'] . '" inputmode="numeric" maxlength="7" aria-label="' . h(lang('Counted')) . '" />'
                : '<span class="d-block text-end">' . (int) $item['counted'] . '</span>') . '</td>
            <td class="text-end fw-semibold ' . (($diff < 0) ? 'text-danger' : (($diff > 0) ? 'text-success' : 'text-body-secondary')) . '" data-sort="' . $diff . '">' . (($diff > 0) ? '+' : '') . $diff . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="4" class="text-center text-body-secondary py-4">' . lang('Nothing counted yet. Scan a barcode or type a product code above.') . '</td></tr>';
}

$form_action = function ($action, $label, $class, $icon, $confirm) use ($count_id) {
    return '<form method="post" action="erp_stock_count.php" class="d-inline">' . get_token_field()
        . '<input type="hidden" name="id" value="' . $count_id . '" /><input type="hidden" name="erp_action" value="' . $action . '" />'
        . '<button type="submit" class="btn btn-sm ' . $class . '" data-confirm-content="' . h($confirm) . '"><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . $label . '</button></form>';
};

echo
pg_page_shell([
    'title' => (string) $count['title'],
    'extra classes' => 'erp erp_stock',
    'icon' => 'erp',
    'heading' => h((string) $count['title']) . ' <span class="badge text-bg-' . h($status[1]) . ' ms-1 align-middle fs-6">' . h($status[0]) . '</span>',
    'heading_description' => $applied
        ? lang('Applied: the stock of the products below was set to what was counted. The stock column is what the store had before.')
        : lang('Scan each piece, or type a code and a quantity. The stock column is what the store has now; applying sets it to what was counted.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_stock_counts.php'),
    'breadcrumb' => array(
        array('label' => lang('Stock counts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_stock_counts.php'),
        array('label' => (string) $count['title']),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            ' . ($open ? '<form method="post" action="erp_stock_count.php" class="card my-4" autocomplete="off">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $count_id . '" />
                <input type="hidden" name="erp_action" value="scan" />
                <div class="card-body row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="code">' . lang('Barcode or product code') . '</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-upc-scan" aria-hidden="true"></i></span>
                            <input type="text" class="form-control" id="code" name="code" maxlength="100" autofocus required />
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="quantity">' . lang('Quantity') . '</label>
                        <input type="text" class="form-control form-control-lg text-end" id="quantity" name="quantity" inputmode="numeric" maxlength="7" placeholder="1" />
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="set" name="set" value="1" />
                            <label class="form-check-label" for="set">' . lang('Set the count to this quantity') . '</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">' . lang('Add to the count') . '</button>
                    </div>
                </div>
            </form>' : '') . '

            <form method="post" action="erp_stock_count.php">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $count_id . '" />
                <div class="card my-4">
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0" data-pg-sort>
                            <thead>
                                <tr>
                                    <th>' . lang('Product') . '</th>
                                    <th class="text-end">' . lang('Stock') . '</th>
                                    <th class="text-end">' . lang('Counted') . '</th>
                                    <th class="text-end">' . lang('Difference') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                    <div class="form-text px-3 pb-3">' . h(lang(array('string' => '{var:1} product(s) on the count; {var:2} piece(s) of difference in all. Products not on the count are left as they are.', 'vars' => array(count($items), $total_diff)))) . '</div>
                </div>
                ' . ($open ? '<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="erp_action" value="save" class="btn my-1 btn-outline-secondary"' . (empty($items) ? ' disabled' : '') . '><i class="bi bi-save me-2" aria-hidden="true"></i>' . lang('Save the counts') . '</button>
                            <button type="submit" name="erp_action" value="apply" class="btn my-1 btn-success"' . (empty($items) ? ' disabled' : '') . ' data-confirm-content="' . h(lang('Set the stock of every product on the count to what was counted? What it had before is kept on the count.')) . '"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>' . lang('Apply the count') . '</button>
                        </div>
                    </div>
                </nav>' : '') . '
            </form>

            ' . ($open ? '<div class="d-flex flex-wrap gap-2 justify-content-end mb-4">
                ' . $form_action('cancel', lang('Cancel the count'), 'btn-outline-secondary', 'bi-x-circle', lang('Cancel this count? No stock is changed.')) . '
            </div>' : '') . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();

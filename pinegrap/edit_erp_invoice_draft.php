<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - edit a draft invoice.
 *
 * A draft is the header and its lines, kept without a number and without a
 * movement on the account. It is rewritten whole on every save, issued when
 * it is ready - which takes the next number and posts the movement in one
 * transaction - or deleted, since it never had anything to reverse. Once
 * issued the same row is read on edit_erp_invoice.php and this screen sends
 * it there.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');
include_once('liveform.class.php');
$liveform = new liveform('edit_erp_invoice_draft');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php';

$invoice_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;

$invoice = ($invoice_id > 0)
    ? db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1")
    : null;

if (!is_array($invoice)) {
    output_error(lang('The invoice could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Invoices') . '</a>');
    exit();
}

// Only a draft is edited here; anything issued is a document and is read.
if ((string) $invoice['status'] !== 'draft') {
    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
}

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . $invoice_id;

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    erp_invoice_form_prefill($liveform, $invoice);

    $stored_rows = (array) db_items("SELECT l.*, COALESCE(NULLIF(p.short_description, ''), p.name) AS product_name
        FROM erp_invoice_items l
        LEFT JOIN products p ON l.product_id = p.id
        WHERE l.invoice_id = '" . $invoice_id . "'
        ORDER BY l.line_no ASC, l.id ASC");

    // The purchase order the draft was made from (10.58): the way back to it.
    $purchase_order_number = ((int) ($invoice['purchase_order_id'] ?? 0) > 0)
        ? (string) db_value("SELECT full_number FROM erp_purchase_orders WHERE id = '" . (int) $invoice['purchase_order_id'] . "' LIMIT 1")
        : '';
    $output_purchase_order = ($purchase_order_number !== '')
        ? '<div class="alert alert-light d-flex align-items-center gap-2 py-2 mb-3" role="status"><i class="bi bi-cart-check" aria-hidden="true"></i><span>'
            . '<a class="link-body-emphasis" href="edit_erp_purchase_order.php?id=' . (int) $invoice['purchase_order_id'] . '">' . h(lang(array('string' => 'From purchase order {var:1}', 'vars' => $purchase_order_number))) . '</a>'
            . ' <span class="text-body-secondary">' . h(lang('The order counts these lines as received once the draft is issued.')) . '</span></span></div>'
        : '';

    echo
    pg_page_shell([
        'title' => lang('Draft Invoice'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
        'heading' => lang('Draft Invoice'),
        'heading_description' => lang('No number has been taken and nothing has been posted to the account. Issue the draft when it is ready.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_invoices.php'),
        'breadcrumb' => array(
            array('label' => lang('Invoices'), 'url' => $list_url),
            array('label' => lang('Draft Invoice')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_purchase_order . '
            ' . ((function_exists('erp_credit_draft_warning') && (($output_credit_warning = erp_credit_draft_warning($invoice)) !== ''))
                ? '<div class="alert alert-warning"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>' . h($output_credit_warning) . '</div>'
                : '') . '
            ' . ((((string) $invoice['doc_type'] === 'invoice') && ((int) $invoice['order_id'] === 0) && !(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) ? '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-outline-secondary" href="add_erp_manual_invoice.php?copy=' . $invoice_id . '"><i class="bi bi-files me-1" aria-hidden="true"></i>' . lang('Copy') . '</a>
            </nav>' : '') . '

            <form name="form" action="edit_erp_invoice_draft.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $invoice_id . '" />
                ' . erp_invoice_form_cards($liveform, array('lines' => erp_invoice_form_lines($liveform, $stored_rows))) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="erp_action" value="draft" class="btn my-1 btn-outline-secondary"><i class="bi bi-save me-2" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save Draft') . '</span></button>
                            <button type="submit" name="erp_action" value="issue" class="btn my-1 btn-success"><i class="bi bi-receipt me-2" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Issue the Invoice') . '</span></button>
                            <button type="submit" name="erp_action" value="delete" class="btn my-1 btn-outline-warning" data-confirm-content="' . lang('Delete this draft? It has no number and nothing has been posted, so nothing is reversed.') . '"><i class="bi bi-trash me-2" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Delete Draft') . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
            ' . erp_account_quick_modal() . '
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();

    // Every field of the form but the lines is one value. One posted as an
    // array counts as not given: (int) read an array account as account 1,
    // (string) wrote "Array" into the notes and trim() stopped the request.
    pg_request_single_values(array_merge(erp_invoice_form_single_fields(), array('erp_action')));

    $action = (string) ($_POST['erp_action'] ?? 'draft');

    if ($action === 'delete') {

        $result = erp_invoice_draft_delete($invoice_id);

        if (!$result['success']) {
            $liveform->mark_error('_error', $result['error']);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp invoice draft ({var:1}) was deleted', 'vars' => $invoice_id)), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_list = new liveform('erp_invoices');
        $liveform_list->add_notice(lang('The draft has been deleted.'));

        go($list_url);
    }

    $liveform->add_fields_to_session();

    $read = erp_invoice_form_read($liveform, $user);

    if (!$read['success']) {
        $liveform->mark_error($read['field'], $read['error']);
        go($self_url);
    }

    $data = $read['data'];

    if ($read['rate_missing'] && ($action === 'issue')) {
        $liveform->mark_error('exchange_rate', erp_invoice_form_rate_missing_message($data['currency'], $data['issue_date']));
        go($self_url);
    }

    // What was typed is saved first in either case: an issue that fails on
    // the number or the account still leaves the draft as the operator last
    // saw it, and the whole thing is one transaction so a failed issue rolls
    // the save back too.
    if (!erp_tx_begin()) {
        $liveform->mark_error('_error', lang('Could not start a database transaction.'));
        go($self_url);
    }

    $saved = erp_invoice_draft_save($data, $invoice_id);

    if (!$saved['success']) {
        erp_tx_rollback();
        $liveform->mark_error($saved['field'] ?? '_error', h($saved['error']));
        go($self_url);
    }

    if ($action === 'issue') {

        $issued = erp_invoice_issue($invoice_id, (int) $user['id']);

        if (!$issued['success'] || !erp_tx_commit()) {
            erp_tx_rollback();
            $liveform->mark_error('_error', $issued['success'] ? erp_db_error() : h($issued['error']));
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp invoice ({var:1}) was created', 'vars' => $issued['full_number'])), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_document = new liveform('edit_erp_invoice');
        $liveform_document->add_notice(lang(array('string' => 'Invoice {var:1} created.', 'vars' => h($issued['full_number']))));

        if (function_exists('erp_credit_issued_warning') && (($credit_warning = erp_credit_issued_warning($invoice_id)) !== '')) {
            $liveform_document->add_warning(h($credit_warning));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    if (!erp_tx_commit()) {
        erp_tx_rollback();
        $liveform->mark_error('_error', erp_db_error());
        go($self_url);
    }

    log_activity(lang(array('string' => 'erp invoice draft ({var:1}) was saved', 'vars' => $invoice_id)), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform->add_notice(lang('The draft has been saved. It has no number yet; issue it when it is ready.'));

    go($self_url);
}

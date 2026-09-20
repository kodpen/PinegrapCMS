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

$invoice_id = (int) ($_REQUEST['id'] ?? 0);

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

    echo
    pg_page_shell([
        'title' => lang('Draft Invoice'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
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

            <form name="form" action="edit_erp_invoice_draft.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $invoice_id . '" />
                ' . erp_invoice_form_cards($liveform, array('lines' => erp_invoice_form_lines($liveform, $stored_rows))) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="erp_action" value="draft" class="btn my-1 btn-outline-secondary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-save me-2"></span><span class="btn-text">' . lang('Save Draft') . '</span></button>
                            <button type="submit" name="erp_action" value="issue" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><span class="bi bi-receipt me-2"></span><span class="btn-text">' . lang('Issue the Invoice') . '</span></button>
                            <button type="submit" name="erp_action" value="delete" class="btn my-1 btn-outline-warning" data-confirm-content="' . lang('Delete this draft? It has no number and nothing has been posted, so nothing is reversed.') . '" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><span class="bi bi-trash me-2"></span><span class="btn-text">' . lang('Delete Draft') . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();

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
        $liveform->mark_error($saved['field'] ?? '_error', $saved['error']);
        go($self_url);
    }

    if ($action === 'issue') {

        $issued = erp_invoice_issue($invoice_id, (int) $user['id']);

        if (!$issued['success'] || !erp_tx_commit()) {
            erp_tx_rollback();
            $liveform->mark_error('_error', $issued['success'] ? erp_db_error() : $issued['error']);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp invoice ({var:1}) was created', 'vars' => $issued['full_number'])), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_document = new liveform('edit_erp_invoice');
        $liveform_document->add_notice(lang(array('string' => 'Invoice {var:1} created.', 'vars' => $issued['full_number'])));

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

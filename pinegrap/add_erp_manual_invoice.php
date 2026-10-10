<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - type an invoice in.
 *
 * A sale made outside the shop, or a supplier's bill: the operator picks the
 * account and types the lines. The form can be kept as a draft - nothing is
 * numbered and nothing is posted - or issued straight away, in which case the
 * number, the totals and the movement on the account land in one transaction.
 *
 * The figures shown while typing are a preview; the module works them out
 * again when the form is saved and the two must agree.
 *
 * ?copy=<id> opens the form filled from another typed-in invoice - issued,
 * cancelled or a draft - with its direction, account, currency, notes and
 * lines. An invoice made from an order is not copied: its lines and returns
 * belong to the order. The copy is dated today, the due date and the rate
 * are left to the account's term and the day's rate, and the supplier's
 * number is not carried over: it belongs to the other document.
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
$liveform = new liveform('add_erp_manual_invoice');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php';
$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_erp_manual_invoice.php';

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    // Read before the prefill writes the form into the session: a form
    // already there is one sent back with an error, and keeps what was typed.
    $fresh = !$liveform->field_in_session('issue_date');

    erp_invoice_form_prefill($liveform, null, (string) ($_GET['direction'] ?? 'sales'));

    $card_options = array();
    $copy_id = max(0, (int) ($_GET['copy'] ?? 0));

    if ($copy_id > 0) {
        $source = db_item("SELECT * FROM erp_invoices WHERE id = '" . $copy_id . "' AND doc_type = 'invoice' AND order_id = 0 LIMIT 1");

        if (!is_array($source)) {
            $liveform->mark_error('', lang('The invoice could not be found.'));
        } else {
            // The stored lines; a form sent back with an error keeps its own
            // (erp_invoice_form_lines() prefers what was posted).
            $card_options['lines'] = erp_invoice_form_lines($liveform, (array) db_items("SELECT l.*, COALESCE(NULLIF(p.short_description, ''), p.name) AS product_name
                FROM erp_invoice_items l
                LEFT JOIN products p ON l.product_id = p.id
                WHERE l.invoice_id = '" . $copy_id . "'
                ORDER BY l.line_no ASC, l.id ASC"));

            if ($fresh) {
                $liveform->assign_field_value('direction', ((string) $source['direction'] === 'purchase') ? 'purchase' : 'sales');
                $liveform->assign_field_value('account_id', (string) (int) $source['account_id']);
                $liveform->assign_field_value('currency', strtoupper(trim((string) $source['currency'])));
                $liveform->assign_field_value('notes', (string) $source['notes']);
                $liveform->add_notice(h(lang(array('string' => 'Copied from invoice {var:1}', 'vars' => ((string) $source['full_number'] !== '') ? (string) $source['full_number'] : ('#' . $copy_id)))));
            }
        }
    }

    // An invoice started from an account's card opens on that account.
    if (((int) ($_GET['account_id'] ?? 0) > 0) && !$liveform->field_in_session('account_id')) {
        $liveform->assign_field_value('account_id', (string) (int) $_GET['account_id']);
    }

    echo
    pg_page_shell([
        'title' => lang('New Invoice'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
        'heading' => lang('New Invoice'),
        'heading_description' => lang('Type in a sales or purchase invoice. Keep it as a draft, or issue it and take the next number.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_invoices.php'),
        'breadcrumb' => array(
            array('label' => lang('Invoices'), 'url' => $list_url),
            array('label' => lang('New Invoice')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_manual_invoice.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                ' . erp_invoice_form_cards($liveform, $card_options) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="erp_action" value="draft" class="btn my-1 btn-outline-secondary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-save me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Save Draft') . '</span></button>
                            <button type="submit" name="erp_action" value="issue" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><i class="bi bi-receipt me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Issue the Invoice') . '</span></button>
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

    $liveform->add_fields_to_session();

    $action = ((string) ($_POST['erp_action'] ?? 'draft') === 'issue') ? 'issue' : 'draft';

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

    if ($action === 'issue') {

        $result = erp_invoice_create_manual($data);

        if (!$result['success']) {
            $liveform->mark_error($result['field'] ?? '_error', $result['error']);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp invoice ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

        // The notice belongs to the screen the user lands on, which is the
        // document itself rather than the register.
        $liveform->remove_form();
        $liveform_document = new liveform('edit_erp_invoice');
        $liveform_document->add_notice(lang(array('string' => 'Invoice {var:1} created.', 'vars' => $result['full_number'])));

        if (function_exists('erp_credit_issued_warning') && (($credit_warning = erp_credit_issued_warning((int) $result['invoice_id'])) !== '')) {
            $liveform_document->add_warning(h($credit_warning));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $result['invoice_id']);
    }

    $result = erp_invoice_draft_save($data, 0);

    if (!$result['success']) {
        $liveform->mark_error($result['field'] ?? '_error', $result['error']);
        go($self_url);
    }

    log_activity(lang(array('string' => 'erp invoice draft ({var:1}) was saved', 'vars' => (int) $result['invoice_id'])), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_draft = new liveform('edit_erp_invoice_draft');
    $liveform_draft->add_notice(lang('The draft has been saved. It has no number yet; issue it when it is ready.'));

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $result['invoice_id']);
}

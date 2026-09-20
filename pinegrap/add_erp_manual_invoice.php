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

    erp_invoice_form_prefill($liveform, null, (string) ($_GET['direction'] ?? 'sales'));

    echo
    pg_page_shell([
        'title' => lang('New Invoice'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
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
                ' . erp_invoice_form_cards($liveform) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="erp_action" value="draft" class="btn my-1 btn-outline-secondary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-save me-2"></span><span class="btn-text">' . lang('Save Draft') . '</span></button>
                            <button type="submit" name="erp_action" value="issue" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><span class="bi bi-receipt me-2"></span><span class="btn-text">' . lang('Issue the Invoice') . '</span></button>
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

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - write a quote.
 *
 * The invoice editor, for an offer: the account, the lines and the date the
 * offer runs to. Saving gives the quote its number; nothing is posted to the
 * account. The work lives in includes/erp/quotes.php.
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
$liveform = new liveform('add_erp_quote');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_quotes.php';
$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_erp_quote.php';

if (!$_POST) {

    erp_invoice_form_prefill($liveform, null, 'sales');

    // A quote started from an account's card opens on that account.
    if (((int) ($_GET['account_id'] ?? 0) > 0) && !$liveform->field_in_session('account_id')) {
        $liveform->assign_field_value('account_id', (string) (int) $_GET['account_id']);
    }

    if (!erp_quotes_ready()) {
        $liveform->mark_error('', lang('Quotes come with the software update; run the update to use them.'));
    }

    echo
    pg_page_shell([
        'title' => lang('New quote'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
        'heading' => lang('New quote'),
        'heading_description' => lang('A priced offer to a customer. It moves no money and no stock; once the customer says yes it becomes an invoice at the prices quoted.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_quotes.php'),
        'breadcrumb' => array(
            array('label' => lang('Quotes'), 'url' => $list_url),
            array('label' => lang('New quote')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_quote.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                ' . erp_invoice_form_cards($liveform, array('quote' => true, 'valid_days' => ERP_QUOTE_VALID_DAYS)) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success"' . (erp_quotes_ready() ? '' : ' disabled') . ' data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Save the quote') . '</span></button>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    // An empty date gives the quote the usual run; the form reader would put
    // the account's payment term there instead.
    $valid_typed = trim((string) $liveform->get_field_value('due_date'));

    $read = erp_invoice_form_read($liveform, $user);

    if (!$read['success']) {
        $liveform->mark_error($read['field'], $read['error']);
        go($self_url);
    }

    $data = $read['data'];

    if ($valid_typed === '') {
        $data['due_date'] = '';
    }

    $result = erp_quote_save($data, 0, (int) $user['id']);

    if (!$result['success']) {
        $liveform->mark_error($result['field'] ?? '_error', $result['error']);
        go($self_url);
    }

    log_activity(lang(array('string' => 'erp quote ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_quote = new liveform('edit_erp_quote');
    $liveform_quote->add_notice(h(lang(array('string' => 'Quote {var:1} saved.', 'vars' => $result['full_number']))));

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_quote.php?id=' . (int) $result['quote_id']);
}

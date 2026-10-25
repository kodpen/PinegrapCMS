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
 * ?copy=<id> opens the editor filled from another quote (account, currency,
 * notes and lines), dated today with the usual run; it is a new open quote
 * and takes its own number when saved.
 *
 * ?account_id=<id> opens it on that account: the account card's "New
 * quote" and a Workspace channel's "New sales quote" (the channel's
 * customer's account, found by ws_channel_sales_quote_account()). The id
 * only chooses the account box's option; the editor lists active accounts
 * alone and saving checks the account again.
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

    // Read before the prefill writes the form into the session: a form
    // already there is one sent back with an error, and keeps what was typed.
    $fresh = !$liveform->field_in_session('issue_date');

    erp_invoice_form_prefill($liveform, null, 'sales');

    $card_options = array('quote' => true, 'valid_days' => ERP_QUOTE_VALID_DAYS);
    // The document to copy and the account card the screen was opened from
    // are one value each: an array (?copy[]=x) cast to 1 and took the first.
    $copy_id = max(0, is_scalar($_GET['copy'] ?? null) ? (int) $_GET['copy'] : 0);

    if ($copy_id > 0) {
        $source = erp_quote($copy_id);

        if ($source === null) {
            $liveform->mark_error('', lang('The quote could not be found.'));
        } else {
            // The stored lines (line_data), not the form as it was posted:
            // they are what was priced and what an invoice would be made of.
            $card_options['lines'] = erp_invoice_form_lines($liveform, erp_quote_editor_rows($source));

            if ($fresh) {
                $liveform->assign_field_value('account_id', (string) (int) $source['account_id']);
                $liveform->assign_field_value('currency', strtoupper(trim((string) $source['currency'])));
                $liveform->assign_field_value('notes', (string) $source['notes']);
                $liveform->add_notice(h(lang(array('string' => 'Copied from quote {var:1}', 'vars' => (string) $source['full_number']))));
            }
        }
    }

    // A quote started from an account's card opens on that account.
    $card_account_id = is_scalar($_GET['account_id'] ?? null) ? (int) $_GET['account_id'] : 0;
    if (($card_account_id > 0) && !$liveform->field_in_session('account_id')) {
        $liveform->assign_field_value('account_id', (string) $card_account_id);
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
                ' . erp_invoice_form_cards($liveform, $card_options) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success"' . (erp_quotes_ready() ? '' : ' disabled') . '><i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Save the quote') . '</span></button>
                    </div>
                </nav>
            </form>
            ' . erp_account_quick_modal() . '
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    // Every field of the form but the lines is one value. One posted as an
    // array counts as not given: (int) read an array account as account 1,
    // (string) wrote "Array" into the notes and trim() stopped the request.
    pg_request_single_values(erp_invoice_form_single_fields());

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
        $liveform->mark_error($result['field'] ?? '_error', h($result['error']));
        go($self_url);
    }

    log_activity(lang(array('string' => 'erp quote ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_quote = new liveform('edit_erp_quote');
    $liveform_quote->add_notice(h(lang(array('string' => 'Quote {var:1} saved.', 'vars' => $result['full_number']))));

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_quote.php?id=' . (int) $result['quote_id']);
}

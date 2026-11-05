<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the account reconciliation letter screen.
 *
 * Pick the balance date, the period the movements are listed for and how
 * long the counterparty has to answer; the letter is previewed as it will
 * print. From there it opens as a PDF, downloads, or goes out by e-mail with
 * the PDF attached. Nothing here changes the books.
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
$liveform = new liveform('erp_reconciliation');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php';

$account_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$account = erp_account($account_id);

if (!is_array($account)) {
    output_error(lang('The account could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Accounts') . '</a>', 404);
    exit();
}

$options = erp_reconciliation_options(array(
    'as_of' => $_REQUEST['as_of'] ?? '',
    'from' => $_REQUEST['from'] ?? '',
    'reply_days' => is_scalar($_REQUEST['reply_days'] ?? null) ? (int) $_REQUEST['reply_days'] : 7,
));

$query = 'id=' . $account_id . '&as_of=' . $options['as_of'] . '&from=' . $options['from'] . '&reply_days=' . $options['reply_days'];
$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_reconciliation.php?' . $query;

// ------------------------------------------------------------------- send
if ($_POST) {

    validate_token_field();

    // The action and the address are one value each. One posted as an array
    // counts as not given: (string) read the address as "Array".
    pg_request_single_values(array('erp_action', 'to'));

    if (($_POST['erp_action'] ?? '') === 'send') {
        $to = trim((string) ($_POST['to'] ?? ''));
        $result = erp_reconciliation_send($account_id, $options, $to, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error($result['field'], h($result['error']));
            $liveform->assign_field_value('to', $to);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp reconciliation letter for account ({var:1}) was e-mailed to {var:2}', 'vars' => array($account['title'], $to))), $_SESSION['sessionusername']);

        $liveform->add_notice(lang(array('string' => 'The reconciliation letter was e-mailed to {var:1}.', 'vars' => h($to))));
    }

    go($self_url);
}

// ---------------------------------------------------------------- display
$balance = erp_reconciliation_balance($account_id, $options['as_of']);
$statement = erp_account_statement($account_id, $options['from'], $options['as_of']);
$movement_count = count($statement['rows']);

$balance_class = ($balance['base'] > 0) ? 'fg-success' : (($balance['base'] < 0) ? 'fg-danger' : 'fg-secondary');
$balance_side = ($balance['base'] > 0) ? lang('owes you') : (($balance['base'] < 0) ? lang('you owe') : '');

$output_balance_fc = '';
if (erp_fx_enabled() && ($balance['currency'] !== '') && ($balance['currency'] !== erp_base_currency())) {
    $output_balance_fc = '<span class="fg-secondary">' . h(erp_money_out_currency(abs($balance['fc']), $balance['currency'])) . ' ' . h($balance['currency']) . '</span>';
}

$pdf_url = 'get_erp_reconciliation_pdf.php?' . h($query);

// The late payment fee over the letter's period, beside the balance: on
// screen only, the letter states what the books hold and a fee is not in
// them until its invoice is issued.
$output_late_fee = function_exists('erp_late_fee_panel') ? erp_late_fee_panel($account, $options['from'], $options['as_of'], 'reconciliation') : '';

if (!$liveform->field_in_session('to')) {
    $liveform->assign_field_value('to', (string) $account['email']);
}

$sender = erp_reconciliation_sender();
$output_send_note = ($sender !== '')
    ? lang(array('string' => 'Sent from {var:1}; replies come back to the same address.', 'vars' => $sender))
    : lang('The store has no e-mail address to send from. Set one under Settings.');


// A read-only accountant sees the preview and the PDF but not the send
// form: validate_erp_access() refuses their POST, so the button only led
// to an error page.
$output_send_card = '';
if (!(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) {
    $output_send_card = '
                    <div class="card mb-7">
                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Send by e-mail') . '</div>
                        <div class="card-body">
                            <form name="form" method="post" action="erp_reconciliation.php">
                                ' . get_token_field() . '
                                <input type="hidden" name="id" value="' . $account_id . '" />
                                <input type="hidden" name="as_of" value="' . h($options['as_of']) . '" />
                                <input type="hidden" name="from" value="' . h($options['from']) . '" />
                                <input type="hidden" name="reply_days" value="' . (int) $options['reply_days'] . '" />
                                <input type="hidden" name="erp_action" value="send" />
                                <div class="form-field mb-5">
                                    <label for="to" class="form-label">' . lang('Recipient e-mail') . '</label>
                                    ' . $liveform->output_field(array('type' => 'email', 'id' => 'to', 'name' => 'to', 'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                                    <div class="form-text">' . h($output_send_note) . '</div>
                                </div>
                                <button type="submit" name="submit_send" value="Send" class="btn btn-solid theme-success w-100"' . (($sender === '') ? ' disabled' : '') . ' data-confirm-content="' . h(lang('The letter shown in the preview will be e-mailed as a PDF to this address. Continue?')) . '"><i class="bi bi-envelope me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Send the letter') . '</span></button>
                            </form>
                        </div>
                    </div>';
}

echo
pg_page_shell([
    'title' => lang('Reconciliation Letter'),
    'extra classes' => 'erp erp_accounts',
    'icon' => 'erp',
    'heading' => lang('Reconciliation Letter'),
    'heading_description' => lang('What the books say this account owes or is owed as of a date, for the counterparty to confirm.'),
    'cancel' => array('enable' => 'true', 'url' => 'edit_erp_account.php?id=' . $account_id),
    'breadcrumb' => array(
        array('label' => lang('Accounts'), 'url' => $list_url),
        array('label' => $account['title'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id),
        array('label' => lang('Reconciliation Letter')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-outline theme-secondary" href="' . $pdf_url . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>
                <a class="btn btn-sm btn-outline theme-secondary" href="' . $pdf_url . '&amp;download=1"><i class="bi bi-download me-1" aria-hidden="true"></i>' . lang('Download') . '</a>
            </nav>
        </div>

        <!--
            The work column: the letter\'s options and the letter as it prints.
            No sticky save bar: nothing is saved here, the one action (send)
            needs its recipient field and stays in its own card.
        -->
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Letter') . '</div>
                <div class="card-body">
                    <form method="get" action="erp_reconciliation.php">
                        <input type="hidden" name="id" value="' . $account_id . '" />
                        <div class="row">
                            <div class="col-12 md:col-4 my-3">
                                <div class="form-field">
                                    <label for="as_of" class="form-label">' . lang('Balance as of') . '</label>
                                    <input type="date" data-pg-date id="as_of" name="as_of" class="form-control" value="' . h($options['as_of']) . '" max="' . h(date('Y-m-d')) . '" />
                                    <div class="form-text">' . lang('The balance and the movements are read as of this date; later postings are left out.') . '</div>
                                </div>
                            </div>
                            <div class="col-12 md:col-4 my-3">
                                <div class="form-field">
                                    <label for="from" class="form-label">' . lang('List movements from') . '</label>
                                    <input type="date" data-pg-date id="from" name="from" class="form-control" value="' . h($options['from']) . '" max="' . h($options['as_of']) . '" />
                                    <div class="form-text">' . lang('Everything before this date is one opening balance.') . '</div>
                                </div>
                            </div>
                            <div class="col-12 md:col-4 my-3">
                                <div class="form-field">
                                    <label for="reply_days" class="form-label">' . lang('Days to reply') . '</label>
                                    <input type="number" id="reply_days" name="reply_days" class="form-control" value="' . (int) $options['reply_days'] . '" min="0" max="' . (int) ERP_RECONCILIATION_MAX_REPLY_DAYS . '" step="1" />
                                    <div class="form-text">' . lang('0 leaves the reply deadline out of the letter.') . '</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-outline theme-primary"><i class="bi bi-arrow-repeat me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Update the preview') . '</span></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold d-flex align-items-center">
                    <span>' . lang('Preview') . '</span>
                    <a class="btn btn-sm btn-link ms-auto" href="' . $pdf_url . '&amp;html=1" target="_blank" rel="noopener">' . lang('Open in a new tab') . '</a>
                </div>
                <div class="card-body p-0">
                    <iframe src="' . $pdf_url . '&amp;html=1" title="' . h(lang('Reconciliation Letter')) . '" class="w-100 border-0 bg-white" style="min-height: 1100px;" loading="lazy"></iframe>
                </div>
            </div>
        </div>

        <!--
            What is read: the balance the letter states, then the send card.
            After the work column in source order so a phone shows the
            options first.
        -->
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Balance') . '</div>
                    <div class="card-body">
                        <div class="fg-secondary small">' . h(lang(array('string' => 'Balance as of {var:1}', 'vars' => prepare_form_data_for_output($options['as_of'], 'date', false)))) . '</div>
                        <div class="d-flex flex-wrap align-items-baseline gap-3 my-3">
                            <span class="h4 mb-0 ' . $balance_class . '">' . h(erp_money_out(abs($balance['base']))) . '</span>
                            <span class="fg-secondary">' . h($balance_side) . '</span>
                        </div>
                        ' . (($output_balance_fc !== '') ? '<div class="mb-3">' . $output_balance_fc . '</div>' : '') . '
                        <div class="fg-secondary small">' . h(lang(array('string' => '{var:1} movement(s) between {var:2} and {var:3}', 'vars' => array($movement_count, prepare_form_data_for_output($options['from'], 'date', false), prepare_form_data_for_output($options['as_of'], 'date', false))))) . '</div>
                    </div>
                    ' . (($output_late_fee !== '') ? '<div class="card-footer bg-reset">' . $output_late_fee . '</div>' : '') . '
                </div>
' . $output_send_card . '
            </div>
        </div>
    </div>
</main>' . get_date_picker_format() . pg_fields_script() .
output_footer();

$liveform->remove_form();

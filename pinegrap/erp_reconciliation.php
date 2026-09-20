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

$account_id = (int) ($_REQUEST['id'] ?? 0);
$account = erp_account($account_id);

if (!is_array($account)) {
    output_error(lang('The account could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Accounts') . '</a>');
    exit();
}

$options = erp_reconciliation_options(array(
    'as_of' => $_REQUEST['as_of'] ?? '',
    'from' => $_REQUEST['from'] ?? '',
    'reply_days' => isset($_REQUEST['reply_days']) ? (int) $_REQUEST['reply_days'] : 7,
));

$query = 'id=' . $account_id . '&as_of=' . $options['as_of'] . '&from=' . $options['from'] . '&reply_days=' . $options['reply_days'];
$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_reconciliation.php?' . $query;

// ------------------------------------------------------------------- send
if ($_POST) {

    validate_token_field();

    if (($_POST['erp_action'] ?? '') === 'send') {
        $to = trim((string) ($_POST['to'] ?? ''));
        $result = erp_reconciliation_send($account_id, $options, $to);

        if (!$result['success']) {
            $liveform->mark_error('to', $result['error']);
            $liveform->assign_field_value('to', $to);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp reconciliation letter for account ({var:1}) was e-mailed to {var:2}', 'vars' => array($account['title'], $to))), $_SESSION['sessionusername']);

        $liveform->add_notice(lang(array('string' => 'The reconciliation letter was e-mailed to {var:1}.', 'vars' => $to)));
    }

    go($self_url);
}

// ---------------------------------------------------------------- display
$balance = erp_reconciliation_balance($account_id, $options['as_of']);
$statement = erp_account_statement($account_id, $options['from'], $options['as_of']);
$movement_count = count($statement['rows']);

$balance_class = ($balance['base'] > 0) ? 'text-success' : (($balance['base'] < 0) ? 'text-danger' : 'text-muted');
$balance_side = ($balance['base'] > 0) ? lang('owes you') : (($balance['base'] < 0) ? lang('you owe') : '');

$output_balance_fc = '';
if (erp_fx_enabled() && ($balance['currency'] !== '') && ($balance['currency'] !== erp_base_currency())) {
    $output_balance_fc = '<span class="text-body-secondary">' . h(erp_money_out_currency(abs($balance['fc']), $balance['currency'])) . ' ' . h($balance['currency']) . '</span>';
}

$pdf_url = 'get_erp_reconciliation_pdf.php?' . h($query);

if (!$liveform->field_in_session('to')) {
    $liveform->assign_field_value('to', (string) $account['email']);
}

$sender = erp_reconciliation_sender();
$output_send_note = ($sender !== '')
    ? lang(array('string' => 'Sent from {var:1}; replies come back to the same address.', 'vars' => $sender))
    : lang('The store has no e-mail address to send from. Set one under Settings.');

echo
pg_page_shell([
    'title' => lang('Reconciliation Letter'),
    'extra_classes' => 'erp erp_accounts',
    'icon' => 'store',
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

            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-outline-secondary m-1" href="' . $pdf_url . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2"></i>' . lang('PDF') . '</a>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="' . $pdf_url . '&amp;download=1"><i class="bi bi-download me-2"></i>' . lang('Download') . '</a>
                    </nav>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-body d-flex flex-wrap align-items-baseline gap-3">
                    <span class="text-uppercase text-body-secondary">' . lang(array('string' => 'Balance as of {var:1}', 'vars' => prepare_form_data_for_output($options['as_of'], 'date', false))) . '</span>
                    <span class="h4 mb-0 ' . $balance_class . '">' . h(erp_money_out(abs($balance['base']))) . '</span>
                    <span class="text-body-secondary">' . h($balance_side) . '</span>
                    ' . $output_balance_fc . '
                    <span class="text-body-secondary ms-auto small">' . h(lang(array('string' => '{var:1} movement(s) between {var:2} and {var:3}', 'vars' => array($movement_count, prepare_form_data_for_output($options['from'], 'date', false), prepare_form_data_for_output($options['as_of'], 'date', false))))) . '</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-4">

            <div class="card mb-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Letter') . '</div>
                <div class="card-body">
                    <form method="get" action="erp_reconciliation.php">
                        <input type="hidden" name="id" value="' . $account_id . '" />
                        <div class="mb-3">
                            <label for="as_of" class="form-label">' . lang('Balance as of') . '</label>
                            <input type="date" id="as_of" name="as_of" class="form-control" value="' . h($options['as_of']) . '" max="' . h(date('Y-m-d')) . '" />
                            <div class="form-text">' . lang('The balance and the movements are read as of this date; later postings are left out.') . '</div>
                        </div>
                        <div class="mb-3">
                            <label for="from" class="form-label">' . lang('List movements from') . '</label>
                            <input type="date" id="from" name="from" class="form-control" value="' . h($options['from']) . '" max="' . h($options['as_of']) . '" />
                            <div class="form-text">' . lang('Everything before this date is one opening balance.') . '</div>
                        </div>
                        <div class="mb-3">
                            <label for="reply_days" class="form-label">' . lang('Days to reply') . '</label>
                            <input type="number" id="reply_days" name="reply_days" class="form-control" value="' . (int) $options['reply_days'] . '" min="0" max="' . (int) ERP_RECONCILIATION_MAX_REPLY_DAYS . '" step="1" />
                            <div class="form-text">' . lang('0 leaves the reply deadline out of the letter.') . '</div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100"><span class="bi bi-arrow-repeat me-2"></span>' . lang('Update the preview') . '</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Send by e-mail') . '</div>
                <div class="card-body">
                    <form name="form" method="post" action="erp_reconciliation.php">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $account_id . '" />
                        <input type="hidden" name="as_of" value="' . h($options['as_of']) . '" />
                        <input type="hidden" name="from" value="' . h($options['from']) . '" />
                        <input type="hidden" name="reply_days" value="' . (int) $options['reply_days'] . '" />
                        <input type="hidden" name="erp_action" value="send" />
                        <div class="mb-3">
                            <label for="to" class="form-label">' . lang('Recipient e-mail') . '</label>
                            ' . $liveform->output_field(array('type' => 'email', 'id' => 'to', 'name' => 'to', 'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                            <div class="form-text">' . h($output_send_note) . '</div>
                        </div>
                        <button type="submit" name="submit_send" value="Send" class="btn btn-success w-100"' . (($sender === '') ? ' disabled' : '') . ' data-confirm-content="' . h(lang('The letter shown in the preview will be e-mailed as a PDF to this address. Continue?')) . '" data-loading-content="' . lang(array('string' => 'Sending')) . '"><span class="bi bi-envelope me-2"></span><span class="btn-text">' . lang('Send the letter') . '</span></button>
                    </form>
                </div>
            </div>

        </div>
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex align-items-center">
                    <span>' . lang('Preview') . '</span>
                    <a class="btn btn-sm btn-link ms-auto" href="' . $pdf_url . '&amp;html=1" target="_blank" rel="noopener">' . lang('Open in a new tab') . '</a>
                </div>
                <div class="card-body p-0">
                    <iframe src="' . $pdf_url . '&amp;html=1" title="' . h(lang('Reconciliation Letter')) . '" class="w-100 border-0 bg-white" style="min-height: 1100px;" loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();

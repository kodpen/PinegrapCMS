<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - write an account's late payment fee on a sales invoice draft, and
 * open it. Posted from the account screen and the reconciliation letter
 * screen; an unissued fee draft of the account is opened instead of a second
 * one. The work lives in includes/erp/late_fee.php.
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

// Every field of the form is one value. One posted as an array counts as not
// given: (int) read an array account as account 1 and wrote its fee draft,
// (string) read the dates as "Array" with a notice.
pg_request_single_values(array('account_id', 'back', 'from', 'as_of'));

$account_id = (int) ($_POST['account_id'] ?? 0);
$back = PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id;

if (!$_POST || ($account_id <= 0)) {
    go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');
}

validate_token_field();

if ((string) ($_POST['back'] ?? '') === 'reconciliation') {
    $back = PATH . SOFTWARE_DIRECTORY . '/erp_reconciliation.php?id=' . $account_id
        . '&as_of=' . rawurlencode((string) ($_POST['as_of'] ?? '')) . '&from=' . rawurlencode((string) ($_POST['from'] ?? ''));
}

$result = erp_late_fee_draft($account_id, (string) ($_POST['from'] ?? ''), (string) ($_POST['as_of'] ?? ''), (int) $user['id']);

if (!$result['success']) {
    $liveform = new liveform(((string) ($_POST['back'] ?? '') === 'reconciliation') ? 'erp_reconciliation' : 'edit_erp_account');
    $liveform->mark_error('_error', h($result['error']));
    go($back);
}

log_activity(lang(array('string' => 'erp late payment fee draft (#{var:1}) was written for account #{var:2}', 'vars' => array($result['invoice_id'], $account_id))), $_SESSION['sessionusername']);

$liveform_draft = new liveform('edit_erp_invoice_draft');
$liveform_draft->add_notice(h(lang('The late payment fee is on this sales invoice draft. Check the line, its tax rate and the notes, then issue it; until it is issued nothing is charged.')));
go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $result['invoice_id']);

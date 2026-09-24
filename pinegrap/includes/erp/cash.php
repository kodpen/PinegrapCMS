<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - tills, bank accounts and the postings that touch them.
 *
 * A receipt is not one write. Money arrives in a till and the customer's debt
 * falls by the same amount, and either half on its own is a lie: the till says
 * there is cash nobody paid, or the customer is still shown as owing money they
 * handed over. Both rows go in one transaction or neither does, which is the
 * whole reason this module asked for InnoDB.
 *
 * Nothing else in the module writes to these two tables. One door means one
 * place to read when a balance is wrong.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The members of erp_cash_transactions.payment_method, in the order the
 * receipt form offers them.
 *
 * Kept next to the write so the form and the column cannot drift apart again:
 * the connection runs without strict mode, and a value outside the enum is
 * stored as the empty member with no error, which is how cheque receipts once
 * lost their method.
 *
 * @return string[]
 */
function erp_cash_payment_methods()
{
    return array('cash', 'transfer', 'card', 'cheque', 'other');
}

/**
 * Append one movement to a till or bank account.
 *
 * Runs inside the caller's transaction, like its counterpart in the ledger.
 *
 * @param array $movement
 * @return int|false
 */
function erp_cash_post($movement)
{
    $cash_account_id = (int) ($movement['cash_account_id'] ?? 0);
    $amount = (int) ($movement['amount'] ?? 0);

    if (($cash_account_id <= 0) || ($amount < 0)) {
        return false;
    }

    // Nothing is written into a locked period, whichever path asked.
    if (function_exists('erp_lock_refusal') && (($refusal = erp_lock_refusal((string) ($movement['doc_date'] ?? date('Y-m-d')))) !== '')) {
        erp_lock_refused($refusal);
        return false;
    }

    // Same rule as the ledger: no currency means the base, and the base needs
    // no conversion.
    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($movement['currency'] ?? $base)));
    $exchange_rate = ($currency === $base) ? 1.0 : (float) ($movement['exchange_rate'] ?? 1);
    $amount_base = isset($movement['amount_base'])
        ? (int) $movement['amount_base']
        : (($currency === $base) ? $amount : erp_to_base($amount, $exchange_rate));

    $ok = erp_query("INSERT INTO erp_cash_transactions
            (cash_account_id, doc_date, direction, amount, currency, exchange_rate,
             exchange_rate_date, exchange_rate_source, amount_base, account_id, doc_type, doc_id,
             payment_method, transfer_pair_id, description, created_by, created_at)
        VALUES (
            '" . $cash_account_id . "',
            '" . escape($movement['doc_date'] ?? date('Y-m-d')) . "',
            '" . escape($movement['direction'] ?? 'in') . "',
            '" . $amount . "',
            '" . escape($currency) . "',
            '" . escape((string) $exchange_rate) . "',
            '" . escape($movement['exchange_rate_date'] ?? ($movement['doc_date'] ?? date('Y-m-d'))) . "',
            '" . escape($movement['exchange_rate_source'] ?? '') . "',
            '" . $amount_base . "',
            '" . (int) ($movement['account_id'] ?? 0) . "',
            '" . escape($movement['doc_type'] ?? '') . "',
            '" . (int) ($movement['doc_id'] ?? 0) . "',
            '" . escape($movement['payment_method'] ?? 'cash') . "',
            '" . (int) ($movement['transfer_pair_id'] ?? 0) . "',
            '" . escape($movement['description'] ?? '') . "',
            '" . (int) ($movement['created_by'] ?? 0) . "',
            '" . time() . "')");

    if ($ok === false) {
        return false;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Recompute a till's cached balance from its own movements.
 *
 * The opening figure is a column here and not a movement, unlike an account:
 * a till is opened once with what is in the drawer, and that is a property of
 * the till rather than something that happened to it.
 *
 * @param int $cash_account_id
 * @return bool
 */
function erp_cash_refresh_balance($cash_account_id)
{
    $cash_account_id = (int) $cash_account_id;

    if ($cash_account_id <= 0) {
        return false;
    }

    return (erp_query("UPDATE erp_cash_accounts SET
            balance = opening_balance + COALESCE((
                SELECT SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END)
                FROM erp_cash_transactions WHERE cash_account_id = '" . $cash_account_id . "'), 0),
            updated_at = '" . time() . "'
        WHERE id = '" . $cash_account_id . "'") !== false);
}

/**
 * Take money in against a customer, or pay money out to a supplier.
 *
 * The one door for both, because they are the same two rows with the arrows
 * reversed: a receipt is cash in and a credit on the account, a payment is cash
 * out and a debit. Writing them as two functions meant two places to get the
 * transaction wrong.
 *
 * Both rows are tagged with the same doc_type and doc_id - the till movement's
 * own id - so the pair can be found from either side.
 *
 * The money is in the till's currency. With foreign currency switched on that
 * may be a currency other than the base, and then the receipt carries the
 * rate of its own day: the till and the account move by the amount, the base
 * columns by the converted figure. An invoice named for closing has to be in
 * the same currency, because the allocation is measured against it.
 *
 * @param array $data  direction ('collection'|'payment'), account_id,
 *                     cash_account_id, amount (kurus), doc_date, currency,
 *                     exchange_rate, exchange_rate_date, exchange_rate_source,
 *                     payment_method, description, created_by, and optionally
 *                     invoice_id to close that invoice with this money (never
 *                     more than it is short of)
 * @return array ['success' => bool, 'cash_id' => int, 'account_id' => int,
 *                'settled' => int, 'error' => string]
 */
function erp_post_receipt($data)
{
    $is_collection = (($data['direction'] ?? 'collection') === 'collection');
    $account_id = (int) ($data['account_id'] ?? 0);
    $cash_account_id = (int) ($data['cash_account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);

    $fail = function ($message) {
        return array('success' => false, 'cash_id' => 0, 'account_id' => 0, 'error' => $message);
    };

    if ($amount <= 0) {
        return $fail(lang('Enter an amount greater than zero.'));
    }
    if ($account_id <= 0) {
        return $fail(lang('Choose an account.'));
    }
    if ($cash_account_id <= 0) {
        return $fail(lang('Choose a till or bank account.'));
    }

    if (($refusal = erp_lock_refusal((string) ($data['doc_date'] ?? date('Y-m-d')))) !== '') {
        return $fail($refusal);
    }

    // Checked here and not in erp_cash_post(): a cancellation copies the
    // original row's method onto the reversal, and rows written before the
    // enum was widened may hold the empty member. Refusing them there would
    // make those receipts impossible to cancel.
    $payment_method = (string) ($data['payment_method'] ?? 'cash');

    if (!in_array($payment_method, erp_cash_payment_methods(), true)) {
        return $fail(lang('Choose a payment method.'));
    }

    $base = erp_base_currency();
    $currency = erp_fx_enabled() ? strtoupper(trim((string) ($data['currency'] ?? $base))) : $base;
    $exchange_rate = ($currency === $base) ? 1.0 : (float) ($data['exchange_rate'] ?? 0);

    if (!erp_fx_currency_allowed($currency)) {
        return $fail(lang('That currency is not enabled for the ERP.'));
    }
    if ($exchange_rate <= 0) {
        return $fail(lang('Enter an exchange rate greater than zero.'));
    }

    // A till holds one currency; its balance is a plain sum of its movements.
    // Only checked while the feature is on: before it, every till was written
    // with one fixed code whatever the store's base was.
    $till_currency = erp_fx_enabled()
        ? strtoupper(trim((string) db_value("SELECT currency FROM erp_cash_accounts WHERE id = '" . $cash_account_id . "' LIMIT 1")))
        : '';
    if (($till_currency !== '') && ($till_currency !== $currency)) {
        return $fail(lang(array(
            'string' => 'That till or bank account is kept in {var:1}, so it cannot take a movement in {var:2}.',
            'vars' => array($till_currency, $currency),
        )));
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $shared = array(
        'doc_date' => $data['doc_date'] ?? date('Y-m-d'),
        'amount' => $amount,
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'exchange_rate_date' => $data['exchange_rate_date'] ?? ($data['doc_date'] ?? date('Y-m-d')),
        'exchange_rate_source' => ($currency === $base) ? 'base' : (string) ($data['exchange_rate_source'] ?? ''),
        'description' => $data['description'] ?? '',
        'created_by' => $data['created_by'] ?? 0,
    );

    $cash_id = erp_cash_post($shared + array(
        'cash_account_id' => $cash_account_id,
        'direction' => $is_collection ? 'in' : 'out',
        'account_id' => $account_id,
        'payment_method' => $payment_method,
        'doc_type' => $is_collection ? 'collection' : 'payment',
    ));

    if ($cash_id === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The receipt was not saved.') . ' ' . $error);
    }

    // The till movement's id names the pair, so it goes on both rows.
    // The order the money was for, when it was recorded from one. Written
    // apart from the insert so a store that has not run 4.66 yet still
    // records the movement.
    $order_id = (int) ($data['order_id'] ?? 0);
    $order_sql = (($order_id > 0) && erp_overdue_column_exists('erp_cash_transactions', 'order_id'))
        ? ", order_id = '" . $order_id . "'" : '';

    if (erp_query("UPDATE erp_cash_transactions SET doc_id = '" . $cash_id . "'" . $order_sql . " WHERE id = '" . $cash_id . "'") === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The receipt was not saved.') . ' ' . $error);
    }

    $ledger_id = erp_account_post($shared + array(
        'account_id' => $account_id,
        'kind' => $is_collection ? 'collection' : 'payment',
        'direction' => $is_collection ? 'credit' : 'debit',
        'doc_type' => $is_collection ? 'collection' : 'payment',
        'doc_id' => $cash_id,
    ));

    if ($ledger_id === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The receipt was not saved.') . ' ' . $error);
    }

    // Close an invoice with this money, if one was named. The allocation rides
    // inside the same transaction as the movement it belongs to: a receipt that
    // landed without its allocation, or an allocation pointing at a movement
    // that was rolled back, both end up reconciled by hand.
    $invoice_id = (int) ($data['invoice_id'] ?? 0);
    $allocated = 0;

    if ($invoice_id > 0) {

        $invoice = db_item("SELECT id, account_id, direction, currency, full_number, grand_total, grand_total_base, paid_total, status
            FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

        if (!is_array($invoice) || ((int) $invoice['account_id'] !== $account_id)) {
            erp_tx_rollback();
            return $fail(lang('That invoice does not belong to this account.'));
        }

        // The allocation is measured in the invoice's currency, so the money
        // has to be in it too.
        if (erp_fx_enabled() && (strtoupper(trim((string) $invoice['currency'])) !== $currency)) {
            erp_tx_rollback();
            return $fail(lang(array(
                'string' => 'Invoice {var:1} is in {var:2}; record the receipt in that currency.',
                'vars' => array((string) $invoice['full_number'], strtoupper(trim((string) $invoice['currency']))),
            )));
        }

        // Money in closes what was sold; money out closes what was bought.
        if ((string) $invoice['direction'] !== ($is_collection ? 'sales' : 'purchase')) {
            erp_tx_rollback();
            return $fail(lang('That invoice cannot be closed by this kind of movement.'));
        }

        $open = erp_invoice_open_amount($invoice);

        if ($open <= 0) {
            erp_tx_rollback();
            return $fail(lang('That invoice is already closed.'));
        }

        // Never allocate more than the invoice is short of, nor more than was
        // actually collected. Anything over stays on the open account, where it
        // is visible, rather than vanishing into a document it did not pay for.
        $allocated = ($amount < $open) ? $amount : $open;

        if (!erp_settle(array(
            'invoice_id' => $invoice_id,
            'account_txn_id' => $ledger_id,
            'account_id' => $account_id,
            'amount' => $allocated,
            'amount_base' => ($currency === $base) ? $allocated : erp_to_base($allocated, $exchange_rate),
            'doc_date' => $shared['doc_date'],
            'created_by' => $shared['created_by'],
        ))) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail(lang('The receipt was not saved.') . ' ' . $error);
        }

        // Once a foreign invoice is paid off, the base-currency sides no longer
        // agree by whatever the rate did between issue and receipt; the gap is
        // posted here, in the same transaction as the receipt that closed it.
        if (($currency !== $base) && erp_fx_auto_diff()) {
            $status_now = (string) db_value("SELECT status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

            if (($status_now === 'paid') && !erp_fx_post_difference($invoice, (int) $shared['created_by'])) {
                $error = erp_db_error();
                erp_tx_rollback();
                return $fail(lang('The receipt was not saved.') . ' ' . $error);
            }
        }
    }

    if (!erp_cash_refresh_balance($cash_account_id) || !erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The receipt was not saved.') . ' ' . $error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The receipt was not saved.') . ' ' . $error);
    }

    erp_event_receipt($cash_id, 'erp.receipt.created', $invoice_id);

    return array('success' => true, 'cash_id' => $cash_id, 'account_id' => $ledger_id,
        'settled' => $allocated, 'error' => '');
}

/**
 * Move money between two of the shop's own accounts.
 *
 * Two till movements and no ledger entry: nobody's debt changed, the money only
 * moved. They point at each other through transfer_pair_id so the pair reads as
 * one move and can be undone as one.
 *
 * @param array $data  from_id, to_id, amount (kurus), doc_date, description, created_by
 * @return array ['success' => bool, 'out_id' => int, 'in_id' => int, 'error' => string]
 */
function erp_post_transfer($data)
{
    $from_id = (int) ($data['from_id'] ?? 0);
    $to_id = (int) ($data['to_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);

    $fail = function ($message) {
        return array('success' => false, 'out_id' => 0, 'in_id' => 0, 'error' => $message);
    };

    if ($amount <= 0) {
        return $fail(lang('Enter an amount greater than zero.'));
    }
    if (($from_id <= 0) || ($to_id <= 0)) {
        return $fail(lang('Choose both accounts.'));
    }
    if (($refusal = erp_lock_refusal((string) ($data['doc_date'] ?? date('Y-m-d')))) !== '') {
        return $fail($refusal);
    }
    if ($from_id === $to_id) {
        return $fail(lang('Choose two different accounts.'));
    }

    // Money moves between two tills in one currency: a transfer that changed
    // currency would need a rate and a difference of its own, and this is
    // not that. The rows carry the tills' currency so each till's balance
    // stays a plain sum.
    $currency = erp_base_currency();
    $exchange_rate = 1.0;

    if (erp_fx_enabled()) {
        $from_currency = strtoupper(trim((string) db_value("SELECT currency FROM erp_cash_accounts WHERE id = '" . $from_id . "' LIMIT 1")));
        $to_currency = strtoupper(trim((string) db_value("SELECT currency FROM erp_cash_accounts WHERE id = '" . $to_id . "' LIMIT 1")));

        if (($from_currency !== '') && ($to_currency !== '') && ($from_currency !== $to_currency)) {
            return $fail(lang(array(
                'string' => 'The two accounts are kept in different currencies ({var:1} and {var:2}); a transfer has to stay in one.',
                'vars' => array($from_currency, $to_currency),
            )));
        }

        if ($from_currency !== '') {
            $currency = $from_currency;
        }

        if ($currency !== erp_base_currency()) {
            $known = erp_fx_rate_for($currency, (string) ($data['doc_date'] ?? date('Y-m-d')));
            $exchange_rate = is_array($known) ? (float) $known['rate'] : (float) ($data['exchange_rate'] ?? 1);
        }
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $shared = array(
        'doc_date' => $data['doc_date'] ?? date('Y-m-d'),
        'amount' => $amount,
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'description' => $data['description'] ?? '',
        'created_by' => $data['created_by'] ?? 0,
        'doc_type' => 'transfer',
        'payment_method' => 'transfer',
    );

    $out_id = erp_cash_post($shared + array('cash_account_id' => $from_id, 'direction' => 'out'));

    if ($out_id === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The transfer was not saved.') . ' ' . $error);
    }

    $in_id = erp_cash_post($shared + array('cash_account_id' => $to_id, 'direction' => 'in'));

    if ($in_id === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The transfer was not saved.') . ' ' . $error);
    }

    $paired = erp_query("UPDATE erp_cash_transactions
        SET transfer_pair_id = CASE id WHEN '" . $out_id . "' THEN '" . $in_id . "' ELSE '" . $out_id . "' END,
            doc_id = '" . $out_id . "'
        WHERE id IN ('" . $out_id . "', '" . $in_id . "')");

    if (($paired === false)
        || !erp_cash_refresh_balance($from_id)
        || !erp_cash_refresh_balance($to_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The transfer was not saved.') . ' ' . $error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The transfer was not saved.') . ' ' . $error);
    }

    return array('success' => true, 'out_id' => $out_id, 'in_id' => $in_id, 'error' => '');
}

/**
 * One receipt or payment: the till movement with its ledger twin, the till
 * and the account it names, and the reversal that cancelled it, if any.
 *
 * @param int $cash_id
 * @return array|null  null for an id that is not a receipt or a payment
 */
function erp_receipt($cash_id)
{
    $row = db_item("SELECT c.*, t.name AS cash_account_name, t.currency AS till_currency,
            a.title AS account_title,
            (SELECT l.id FROM erp_account_transactions l WHERE l.doc_type = c.doc_type AND l.doc_id = c.id LIMIT 1) AS ledger_id,
            (SELECT r.id FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = c.id LIMIT 1) AS reversal_id
        FROM erp_cash_transactions c
        LEFT JOIN erp_cash_accounts t ON c.cash_account_id = t.id
        LEFT JOIN erp_accounts a ON c.account_id = a.id
        WHERE c.id = '" . (int) $cash_id . "' AND c.doc_type IN ('collection', 'payment')
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Whether a receipt has been cancelled.
 *
 * The reversal row is the fact; there is no flag to fall out of step with it.
 *
 * @param int $cash_id
 * @return bool
 */
function erp_receipt_is_cancelled($cash_id)
{
    return ((int) db_value("SELECT COUNT(*) FROM erp_cash_transactions
        WHERE doc_type = 'cancel' AND doc_id = '" . (int) $cash_id . "'") > 0);
}

/**
 * Cancel a receipt or a payment that should never have been recorded.
 *
 * Both halves are reversed, never deleted: the till gets the opposite
 * movement and so does the account, each with the original's amount,
 * currency, rate and base figure, so the pair nets to nothing whatever the
 * rate is today. The allocations the receipt carried go first, and each
 * invoice they closed is worked out again; a foreign invoice that stops being
 * paid loses the exchange difference its closing posted. The reversal rows
 * are dated today and point back at the receipt through doc_type 'cancel',
 * which is also what marks the receipt as cancelled.
 *
 * @param int    $cash_id
 * @param string $reason
 * @param int    $created_by
 * @return array ['success' => bool, 'reversal_id' => int, 'reopened' => int[], 'error' => string]
 */
function erp_receipt_cancel($cash_id, $reason, $created_by = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'reversal_id' => 0, 'reopened' => array(), 'error' => $message);
    };

    $receipt = erp_receipt((int) $cash_id);

    if ($receipt === null) {
        return $fail(lang('The receipt could not be found.'));
    }

    if ((int) $receipt['reversal_id'] > 0) {
        return $fail(lang('That receipt has already been cancelled.'));
    }

    // A receipt inside a closed period stays: cancelling it would change
    // what was filed.
    if (($refusal = erp_lock_refusal((string) $receipt['doc_date'])) !== '') {
        return $fail($refusal);
    }

    $reason = trim((string) $reason);

    if ($reason === '') {
        return $fail(lang('The reason is required.'));
    }

    $ledger_id = (int) $receipt['ledger_id'];
    $ledger = ($ledger_id > 0) ? db_item("SELECT * FROM erp_account_transactions WHERE id = '" . $ledger_id . "' LIMIT 1") : null;

    if (!is_array($ledger)) {
        return $fail(lang('The receipt could not be found.'));
    }

    $cash_id = (int) $receipt['id'];
    $account_id = (int) $receipt['account_id'];
    $created_by = (int) $created_by;

    $description = mb_substr(lang(array(
        'string' => 'Cancellation of receipt #{var:1} dated {var:2}: {var:3}',
        'vars' => array($cash_id, prepare_form_data_for_output((string) $receipt['doc_date'], 'date'), $reason),
    )), 0, 255);

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    // The allocations first: the invoices they closed have to ask for their
    // money again before the movement that paid them is turned around.
    $reopened = array();

    foreach (erp_txn_settlements($ledger_id) as $settlement) {
        $invoice_id = (int) $settlement['invoice_id'];

        if (erp_query("DELETE FROM erp_settlements WHERE id = '" . (int) $settlement['id'] . "'") === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        if (!erp_invoice_refresh_paid($invoice_id)
            || !erp_settlement_after_reopen($invoice_id, (string) $settlement['invoice_currency'], $created_by)) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail(($error !== '') ? $error : lang('The receipt could not be cancelled.'));
        }

        $reopened[$invoice_id] = $invoice_id;
    }

    $shared = array(
        'doc_date' => date('Y-m-d'),
        'amount' => (int) $receipt['amount'],
        'currency' => (string) $receipt['currency'],
        'exchange_rate' => (float) $receipt['exchange_rate'],
        'exchange_rate_date' => (string) $receipt['exchange_rate_date'],
        'exchange_rate_source' => (string) $receipt['exchange_rate_source'],
        'doc_type' => 'cancel',
        'doc_id' => $cash_id,
        'description' => $description,
        'created_by' => $created_by,
    );

    $reversal_id = erp_cash_post($shared + array(
        'cash_account_id' => (int) $receipt['cash_account_id'],
        'direction' => ((string) $receipt['direction'] === 'in') ? 'out' : 'in',
        'amount_base' => (int) $receipt['amount_base'],
        'account_id' => $account_id,
        'payment_method' => (string) $receipt['payment_method'],
    ));

    if ($reversal_id === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // The account's side turns around too. It is an adjustment, not a
    // collection: nothing was collected, something recorded was withdrawn.
    $posted = erp_account_post($shared + array(
        'account_id' => $account_id,
        'kind' => 'adjustment',
        'direction' => ((string) $ledger['direction'] === 'debit') ? 'credit' : 'debit',
        'amount_base' => (int) $ledger['amount_base'],
    ));

    if ($posted === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_cash_refresh_balance((int) $receipt['cash_account_id']) || !erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    erp_event_receipt($cash_id, 'erp.receipt.cancelled');

    return array('success' => true, 'reversal_id' => (int) $reversal_id, 'reopened' => array_values($reopened), 'error' => '');
}

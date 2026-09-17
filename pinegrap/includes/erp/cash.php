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

    $currency = strtoupper(trim((string) ($movement['currency'] ?? 'TRY')));
    $exchange_rate = (float) ($movement['exchange_rate'] ?? 1);
    $amount_try = isset($movement['amount_try'])
        ? (int) $movement['amount_try']
        : (($currency === 'TRY') ? $amount : erp_to_try($amount, $exchange_rate));

    $ok = erp_query("INSERT INTO erp_cash_transactions
            (cash_account_id, doc_date, direction, amount, currency, exchange_rate,
             exchange_rate_date, amount_try, account_id, doc_type, doc_id,
             payment_method, transfer_pair_id, description, created_by, created_at)
        VALUES (
            '" . $cash_account_id . "',
            '" . escape($movement['doc_date'] ?? date('Y-m-d')) . "',
            '" . escape($movement['direction'] ?? 'in') . "',
            '" . $amount . "',
            '" . escape($currency) . "',
            '" . escape((string) $exchange_rate) . "',
            '" . escape($movement['exchange_rate_date'] ?? ($movement['doc_date'] ?? date('Y-m-d'))) . "',
            '" . $amount_try . "',
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
 * @param array $data  direction ('collection'|'payment'), account_id,
 *                     cash_account_id, amount (kurus), doc_date, currency,
 *                     exchange_rate, payment_method, description, created_by,
 *                     and optionally invoice_id to close that invoice with this
 *                     money (never more than it is short of)
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

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $shared = array(
        'doc_date' => $data['doc_date'] ?? date('Y-m-d'),
        'amount' => $amount,
        'currency' => $data['currency'] ?? 'TRY',
        'exchange_rate' => $data['exchange_rate'] ?? 1,
        'description' => $data['description'] ?? '',
        'created_by' => $data['created_by'] ?? 0,
    );

    $cash_id = erp_cash_post($shared + array(
        'cash_account_id' => $cash_account_id,
        'direction' => $is_collection ? 'in' : 'out',
        'account_id' => $account_id,
        'payment_method' => $data['payment_method'] ?? 'cash',
        'doc_type' => $is_collection ? 'collection' : 'payment',
    ));

    if ($cash_id === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The receipt was not saved.') . ' ' . $error);
    }

    // The till movement's id names the pair, so it goes on both rows.
    if (erp_query("UPDATE erp_cash_transactions SET doc_id = '" . $cash_id . "' WHERE id = '" . $cash_id . "'") === false) {
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

        $invoice = db_item("SELECT id, account_id, direction, currency, grand_total, paid_total, status
            FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

        if (!is_array($invoice) || ((int) $invoice['account_id'] !== $account_id)) {
            erp_tx_rollback();
            return $fail(lang('That invoice does not belong to this account.'));
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
            'amount_try' => $allocated,
            'doc_date' => $shared['doc_date'],
            'created_by' => $shared['created_by'],
        ))) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail(lang('The receipt was not saved.') . ' ' . $error);
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
    if ($from_id === $to_id) {
        return $fail(lang('Choose two different accounts.'));
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $shared = array(
        'doc_date' => $data['doc_date'] ?? date('Y-m-d'),
        'amount' => $amount,
        'currency' => $data['currency'] ?? 'TRY',
        'exchange_rate' => $data['exchange_rate'] ?? 1,
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

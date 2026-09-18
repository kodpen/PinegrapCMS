<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - closing invoices with the money that came in for them.
 *
 * The money movement is not here. A receipt has already credited the account and
 * lowered the balance; what this adds is the allocation - that receipt paid this
 * invoice. Keeping the two apart is the whole point: posting a ledger entry for
 * a settlement would count the same money a second time and the balance would
 * drift away from the till by exactly the amount that was collected.
 *
 * Nothing here matches anything automatically. Paying off the oldest open
 * invoice first is the obvious guess and it is wrong often enough to matter: a
 * customer who paid a specific invoice and finds a different one marked closed
 * cannot reconcile with you, and the correction costs more than the entry saved.
 * The allocation is always somebody's decision.
 *
 * The one allocation nobody decides is the gift card. The part of an order paid
 * with one was paid when the order was placed, against this order and no
 * other, so the invoice raised for it arrives already settled by that amount
 * (erp_settle_gift_card). It is still money in: the customer's account is
 * credited, so the balance shows what is actually left to collect.
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
 * What is still to be collected on an invoice.
 *
 * Two things reduce it, and they are not the same thing: money that came in
 * (settlements) and goods that went back (returns). Both stop it being
 * collectable, so both count here. The status is worked out somewhere else
 * (erp_invoice_refresh_paid) and measures the money against the same figure,
 * so the two never disagree about whether anything is left to collect.
 *
 * A return document has nothing to collect against it; it is the credit.
 *
 * @param array|int $invoice  Invoice row (needs id), or its id
 * @return int  Kurus. Never negative: an over-covered invoice reads as closed.
 */
function erp_invoice_open_amount($invoice)
{
    if (!is_array($invoice)) {
        $invoice = db_item("SELECT id, doc_type, grand_total, paid_total, status FROM erp_invoices
            WHERE id = '" . (int) $invoice . "' LIMIT 1");
    }

    if (!is_array($invoice)) {
        return 0;
    }

    if ((string) $invoice['status'] === 'cancelled') {
        return 0;
    }

    if (isset($invoice['doc_type']) && ((string) $invoice['doc_type'] === 'return')) {
        return 0;
    }

    $open = (int) $invoice['grand_total']
        - (int) $invoice['paid_total']
        - erp_invoice_returned_total((int) ($invoice['id'] ?? 0));

    return ($open > 0) ? $open : 0;
}

/**
 * Work the paid figure out again from the allocations, and set the status to match.
 *
 * Derived rather than incremented, like the balances: a counter that is only ever
 * added to drifts, and once it has drifted there is nothing left to check it
 * against.
 *
 * The money is measured against what the invoice still asks for, which is its
 * total less what has been returned against it - the same figure
 * erp_invoice_open_amount() works from. Measured against the full total, an
 * invoice with a partial return could never be paid: the receipt screen would
 * refuse the missing kurus as "already closed" while the status sat at
 * partially_paid. Only money makes an invoice paid, though: an invoice that
 * was returned in full and never collected on stays issued, because nothing
 * was collected on it.
 *
 * Called after a settlement moves and after a return is raised or cancelled,
 * since both change the answer.
 *
 * A cancelled or draft invoice keeps its status - neither is waiting to be paid.
 *
 * @param int $invoice_id
 * @return bool
 */
function erp_invoice_refresh_paid($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    $invoice = db_item("SELECT grand_total, status, order_id, payment_date, is_internet_sale FROM erp_invoices
        WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return false;
    }

    $paid = (int) db_value("SELECT COALESCE(SUM(amount), 0) FROM erp_settlements
        WHERE invoice_id = '" . $invoice_id . "'");

    $status = (string) $invoice['status'];

    if (($status !== 'cancelled') && ($status !== 'draft')) {
        $due = (int) $invoice['grand_total'] - erp_invoice_returned_total($invoice_id);

        if ($paid <= 0) {
            $status = 'issued';
        } elseif ($paid >= $due) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }
    }

    // The day the invoice was paid off is the day of the receipt that closed
    // it. Only filled in when the document does not already state one: an
    // internet sale carries the gateway's date from the order.
    $payment_date = '';
    if (($status === 'paid') && ((string) ($invoice['payment_date'] ?? '0000-00-00') === '0000-00-00')) {
        $payment_date = (string) db_value("SELECT COALESCE(MAX(doc_date), '') FROM erp_settlements
            WHERE invoice_id = '" . $invoice_id . "'");
    }

    // The date came from a receipt, and the receipt is gone: nothing was
    // collected any more, so the invoice does not say when it was paid. An
    // internet sale keeps its date, which the gateway gave and no receipt
    // did. The order's own paid mark is not touched either way.
    if (($paid <= 0) && ((int) ($invoice['is_internet_sale'] ?? 0) === 0)
        && ((string) ($invoice['payment_date'] ?? '0000-00-00') !== '0000-00-00')) {
        $payment_date = '0000-00-00';
    }

    $updated = (erp_query("UPDATE erp_invoices
        SET paid_total = '" . $paid . "',
            status = '" . escape($status) . "',
            " . (($payment_date !== '') ? "payment_date = '" . escape($payment_date) . "'," : '') . "
            updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "'") !== false);

    // A receipt that settles the invoice is the confirmed payment moment for a
    // bank transfer, so the order is marked paid here, through the same helper
    // the order screen's Payment Received button uses (a no-op once paid_at is
    // set, and it logs the activity). Runs inside the caller's transaction, so
    // it rolls back with the receipt. Only an offline order is touched: a card
    // order already carries the gateway's date.
    if ($updated && ($status === 'paid') && ((int) $invoice['order_id'] > 0) && function_exists('pg_order_mark_paid')) {
        $order_id = (int) $invoice['order_id'];
        $payment_method = (string) db_value("SELECT payment_method FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

        if ($payment_method === 'Offline Payment') {
            pg_order_mark_paid($order_id, 0);
        }
    }

    return $updated;
}

/**
 * Tie a movement on an account to an invoice.
 *
 * Called from inside the caller's transaction - it neither opens nor commits
 * one, because an allocation that survives the receipt it belongs to is worse
 * than no allocation at all.
 *
 * Re-allocating the same movement to the same invoice replaces the earlier
 * figure rather than adding to it (unique key on the pair), so a corrected entry
 * does not quietly pay the invoice twice.
 *
 * @param array $data  invoice_id, account_txn_id, account_id, amount (kurus),
 *                     amount_base, doc_date, created_by
 * @return bool
 */
function erp_settle($data)
{
    $invoice_id = (int) ($data['invoice_id'] ?? 0);
    $txn_id = (int) ($data['account_txn_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);

    if (($invoice_id <= 0) || ($txn_id <= 0) || ($amount <= 0)) {
        return false;
    }

    $amount_base = (int) ($data['amount_base'] ?? $amount);

    $ok = erp_query("INSERT INTO erp_settlements SET
            invoice_id = '" . $invoice_id . "',
            account_txn_id = '" . $txn_id . "',
            account_id = '" . (int) ($data['account_id'] ?? 0) . "',
            doc_date = '" . escape($data['doc_date'] ?? date('Y-m-d')) . "',
            amount = '" . $amount . "',
            amount_base = '" . $amount_base . "',
            created_by = '" . (int) ($data['created_by'] ?? 0) . "',
            created_at = '" . time() . "'
        ON DUPLICATE KEY UPDATE
            amount = '" . $amount . "',
            amount_base = '" . $amount_base . "',
            doc_date = '" . escape($data['doc_date'] ?? date('Y-m-d')) . "'");

    if ($ok === false) {
        return false;
    }

    return erp_invoice_refresh_paid($invoice_id);
}

/**
 * Settle the part of an invoice that was paid with a gift card.
 *
 * Called from inside the transaction that raises the invoice. The gift card is
 * money the shop already holds, so nothing moves through a till; what happens
 * is a credit on the customer's account, of kind collection, and the allocation
 * of that credit to the invoice. Without the credit the account would carry the
 * gift card amount as owed forever; without the allocation the invoice would
 * stay open for money that was paid before it was raised.
 *
 * The movement is marked doc_type gift_card so a cancellation can find and
 * reverse it, and so the receipt screens can tell it from money somebody
 * collected.
 *
 * @param array $data  invoice_id, account_id, amount (kurus), doc_date,
 *                     description, created_by
 * @return bool
 */
function erp_settle_gift_card($data)
{
    $invoice_id = (int) ($data['invoice_id'] ?? 0);
    $account_id = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);

    if (($invoice_id <= 0) || ($account_id <= 0) || ($amount <= 0)) {
        return false;
    }

    $doc_date = (string) ($data['doc_date'] ?? date('Y-m-d'));

    $txn_id = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => $doc_date,
        'kind' => 'collection',
        'direction' => 'credit',
        'amount' => $amount,
        // The order was priced in the store's base currency, so the credit is
        // a base movement and its own base value.
        'currency' => erp_base_currency(),
        'exchange_rate' => 1,
        'exchange_rate_source' => 'base',
        'amount_base' => $amount,
        'doc_type' => 'gift_card',
        'doc_id' => $invoice_id,
        'description' => (string) ($data['description'] ?? ''),
        'created_by' => (int) ($data['created_by'] ?? 0),
    ));

    if ($txn_id === false) {
        return false;
    }

    return erp_settle(array(
        'invoice_id' => $invoice_id,
        'account_txn_id' => $txn_id,
        'account_id' => $account_id,
        'amount' => $amount,
        'amount_base' => $amount,
        'doc_date' => $doc_date,
        'created_by' => (int) ($data['created_by'] ?? 0),
    ));
}

/**
 * Take back the gift card credit posted for an invoice that is being cancelled.
 *
 * Runs inside the caller's transaction. The credit is reversed by an opposite
 * movement rather than deleted, like every other correction in the ledger, and
 * the allocation rows that pointed at it go. The invoice status is left to the
 * caller, which is about to set it to cancelled.
 *
 * @param int $invoice_id
 * @param int $created_by
 * @return bool
 */
function erp_unsettle_gift_card($invoice_id, $created_by = 0)
{
    $invoice_id = (int) $invoice_id;

    $credits = (array) db_items("SELECT id, account_id, doc_date, amount, description
        FROM erp_account_transactions
        WHERE doc_type = 'gift_card' AND doc_id = '" . $invoice_id . "' AND direction = 'credit'");

    foreach ($credits as $credit) {
        $reversed = erp_account_post(array(
            'account_id' => (int) $credit['account_id'],
            'doc_date' => date('Y-m-d'),
            'kind' => 'adjustment',
            'direction' => 'debit',
            'amount' => (int) $credit['amount'],
            'currency' => erp_base_currency(),
            'exchange_rate' => 1,
            'exchange_rate_source' => 'base',
            'amount_base' => (int) $credit['amount'],
            'doc_type' => 'cancel',
            'doc_id' => $invoice_id,
            'description' => lang(array(
                'string' => '{var:1} cancelled',
                'vars' => (string) $credit['description'],
            )),
            'created_by' => (int) $created_by,
        ));

        if ($reversed === false) {
            return false;
        }

        if (erp_query("DELETE FROM erp_settlements
            WHERE invoice_id = '" . $invoice_id . "' AND account_txn_id = '" . (int) $credit['id'] . "'") === false) {
            return false;
        }
    }

    return true;
}

/**
 * How many allocations on an invoice came from money somebody collected.
 *
 * The gift card allocation the invoice posted for itself is left out: it is
 * not a decision anyone took, so it is not a reason to refuse a cancellation.
 *
 * @param int $invoice_id
 * @return int
 */
function erp_invoice_receipt_count($invoice_id)
{
    return (int) db_value("SELECT COUNT(*)
        FROM erp_settlements s
        LEFT JOIN erp_account_transactions t ON s.account_txn_id = t.id
        WHERE s.invoice_id = '" . (int) $invoice_id . "'
          AND (t.id IS NULL OR t.doc_type <> 'gift_card')");
}

/**
 * Undo one allocation. The money stays where it is; only the pairing goes.
 *
 * @param int $settlement_id
 * @return bool
 */
function erp_unsettle($settlement_id)
{
    $settlement_id = (int) $settlement_id;

    $invoice_id = (int) db_value("SELECT invoice_id FROM erp_settlements WHERE id = '" . $settlement_id . "'");

    if ($invoice_id <= 0) {
        return false;
    }

    if (erp_query("DELETE FROM erp_settlements WHERE id = '" . $settlement_id . "'") === false) {
        return false;
    }

    return erp_invoice_refresh_paid($invoice_id);
}

/**
 * The invoices an account still owes money on, oldest first.
 *
 * Oldest first because that is the order somebody reads them in, not because
 * anything here pays them in that order.
 *
 * @param int    $account_id
 * @param int    $limit
 * @param string $direction  'sales' or 'purchase'
 * @return array  Invoice rows with an extra 'open_amount'
 */
function erp_open_invoices($account_id, $limit = 200, $direction = 'sales')
{
    $account_id = (int) $account_id;

    if ($account_id <= 0) {
        return array();
    }

    // Money in closes what was sold; money out closes what was bought.
    $direction = ($direction === 'purchase') ? 'purchase' : 'sales';

    $rows = (array) db_items("SELECT id, doc_type, full_number, issue_date, due_date, currency,
            grand_total, paid_total, status, order_id
        FROM erp_invoices
        WHERE account_id = '" . $account_id . "'
          AND direction = '" . $direction . "'
          AND doc_type = 'invoice'
          AND status IN ('issued', 'partially_paid')
          AND grand_total > paid_total
        ORDER BY issue_date ASC, id ASC
        LIMIT " . (int) $limit);

    $open = array();

    foreach ($rows as $row) {
        // A returned invoice can still read as unpaid; it is not outstanding.
        $row['open_amount'] = erp_invoice_open_amount($row);

        if ($row['open_amount'] > 0) {
            $open[] = $row;
        }
    }

    return $open;
}

/**
 * The allocations made against an invoice, with the movement behind each one.
 *
 * @param int $invoice_id
 * @return array
 */
function erp_invoice_settlements($invoice_id)
{
    // The till is only reached through a receipt: a movement of another kind
    // (a gift card credit, say) has a doc_id of its own that is not a till row.
    return (array) db_items("SELECT s.*, t.description, t.kind, t.doc_type AS source_type,
            t.doc_id AS cash_id, c.name AS cash_account_name
        FROM erp_settlements s
        LEFT JOIN erp_account_transactions t ON s.account_txn_id = t.id
        LEFT JOIN erp_cash_transactions ct ON t.doc_id = ct.id AND t.doc_type = ct.doc_type
        LEFT JOIN erp_cash_accounts c ON ct.cash_account_id = c.id
        WHERE s.invoice_id = '" . (int) $invoice_id . "'
        ORDER BY s.doc_date ASC, s.id ASC");
}

/**
 * The allocations made from one movement, with the invoice behind each one.
 *
 * @param int $account_txn_id
 * @return array
 */
function erp_txn_settlements($account_txn_id)
{
    return (array) db_items("SELECT s.*, i.full_number, i.status AS invoice_status, i.currency AS invoice_currency,
            i.grand_total, i.paid_total, i.doc_type AS invoice_doc_type
        FROM erp_settlements s
        LEFT JOIN erp_invoices i ON s.invoice_id = i.id
        WHERE s.account_txn_id = '" . (int) $account_txn_id . "'
        ORDER BY s.doc_date ASC, s.id ASC");
}

/**
 * How much of a movement has not been tied to an invoice, in its own currency.
 *
 * @param int $account_txn_id
 * @return int  Kurus, never negative
 */
function erp_txn_unallocated($account_txn_id)
{
    $account_txn_id = (int) $account_txn_id;

    $amount = (int) db_value("SELECT amount FROM erp_account_transactions WHERE id = '" . $account_txn_id . "' LIMIT 1");
    $allocated = (int) db_value("SELECT COALESCE(SUM(amount), 0) FROM erp_settlements WHERE account_txn_id = '" . $account_txn_id . "'");

    return max(0, $amount - $allocated);
}

/**
 * Remove one allocation, as an operation of its own.
 *
 * The money stays on the account, unallocated; the invoice asks for it
 * again. A gift card allocation is refused - it was posted by the invoice
 * for itself and is reversed with that invoice, not on its own. If the
 * invoice was foreign and no longer counts as paid, the exchange difference
 * that its closing posted is reversed too.
 *
 * @param int $settlement_id
 * @param int $created_by
 * @return array ['success' => bool, 'invoice_id' => int, 'error' => string]
 */
function erp_settlement_remove($settlement_id, $created_by = 0)
{
    $settlement_id = (int) $settlement_id;

    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'error' => $message);
    };

    $settlement = db_item("SELECT s.*, t.doc_type AS source_type, i.currency, i.status
        FROM erp_settlements s
        LEFT JOIN erp_account_transactions t ON s.account_txn_id = t.id
        LEFT JOIN erp_invoices i ON s.invoice_id = i.id
        WHERE s.id = '" . $settlement_id . "' LIMIT 1");

    if (!is_array($settlement)) {
        return $fail(lang('The allocation could not be found.'));
    }

    if ((string) $settlement['source_type'] === 'gift_card') {
        return $fail(lang('A gift card allocation is removed with its invoice.'));
    }

    if ((string) $settlement['status'] === 'cancelled') {
        return $fail(lang('That invoice has been cancelled.'));
    }

    $invoice_id = (int) $settlement['invoice_id'];

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    if (!erp_unsettle($settlement_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(($error !== '') ? $error : lang('The allocation could not be removed.'));
    }

    if (!erp_settlement_after_reopen($invoice_id, (string) $settlement['currency'], $created_by)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(($error !== '') ? $error : lang('The allocation could not be removed.'));
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'invoice_id' => $invoice_id, 'error' => '');
}

/**
 * What follows an allocation being taken off a foreign-currency invoice.
 *
 * The status has already been worked out again; if the invoice is no longer
 * paid, the exchange difference its closing posted is reversed. Runs inside
 * the caller's transaction.
 *
 * @param int    $invoice_id
 * @param string $currency  The invoice's currency
 * @param int    $created_by
 * @return bool
 */
function erp_settlement_after_reopen($invoice_id, $currency, $created_by = 0)
{
    if (strtoupper(trim((string) $currency)) === erp_base_currency()) {
        return true;
    }

    $status = (string) db_value("SELECT status FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if ($status === 'paid') {
        return true;
    }

    return erp_fx_reverse_difference((int) $invoice_id, $created_by);
}

/**
 * Tie part of a receipt to an invoice, after the fact.
 *
 * Everything erp_post_receipt() checks when it allocates at recording time is
 * checked here: same account, money going the right way for the document,
 * the same currency, an invoice that is still open, and never more than the
 * receipt has left unallocated. The base figure is taken at the receipt's own
 * rate, as it would have been on the day.
 *
 * @param array $data  invoice_id, account_txn_id, amount (kurus), created_by
 * @return array ['success' => bool, 'allocated' => int, 'error' => string]
 */
function erp_settlement_allocate($data)
{
    $invoice_id = (int) ($data['invoice_id'] ?? 0);
    $txn_id = (int) ($data['account_txn_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $created_by = (int) ($data['created_by'] ?? 0);

    $fail = function ($message) {
        return array('success' => false, 'allocated' => 0, 'error' => $message);
    };

    if ($amount <= 0) {
        return $fail(lang('Enter an amount greater than zero.'));
    }

    $txn = db_item("SELECT t.*, (SELECT r.id FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = t.doc_id LIMIT 1) AS reversal_id
        FROM erp_account_transactions t
        WHERE t.id = '" . $txn_id . "' AND t.doc_type IN ('collection', 'payment') LIMIT 1");

    if (!is_array($txn)) {
        return $fail(lang('The receipt could not be found.'));
    }

    if ((int) $txn['reversal_id'] > 0) {
        return $fail(lang('That receipt has been cancelled.'));
    }

    $invoice = db_item("SELECT id, account_id, direction, doc_type, currency, full_number, grand_total, grand_total_base, paid_total, status
        FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return $fail(lang('The invoice could not be found.'));
    }

    if ((int) $invoice['account_id'] !== (int) $txn['account_id']) {
        return $fail(lang('That invoice does not belong to this account.'));
    }

    if ((string) $invoice['direction'] !== (((string) $txn['doc_type'] === 'collection') ? 'sales' : 'purchase')) {
        return $fail(lang('That invoice cannot be closed by this kind of movement.'));
    }

    if (((string) $invoice['doc_type'] !== 'invoice') || !in_array((string) $invoice['status'], array('issued', 'partially_paid'), true)) {
        return $fail(lang('That invoice is already closed.'));
    }

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) $txn['currency']));

    if (erp_fx_enabled() && (strtoupper(trim((string) $invoice['currency'])) !== $currency)) {
        return $fail(lang(array(
            'string' => 'Invoice {var:1} is in {var:2}; record the receipt in that currency.',
            'vars' => array((string) $invoice['full_number'], strtoupper(trim((string) $invoice['currency']))),
        )));
    }

    // The unique pair means an allocation to this invoice from this receipt
    // replaces the earlier figure, so what it already holds counts as free
    // on both sides: on the receipt and on the invoice.
    $held = (int) db_value("SELECT COALESCE(SUM(amount), 0) FROM erp_settlements
        WHERE account_txn_id = '" . $txn_id . "' AND invoice_id = '" . $invoice_id . "'");

    $open = erp_invoice_open_amount($invoice) + $held;

    if ($open <= 0) {
        return $fail(lang('That invoice is already closed.'));
    }

    if ($amount > $open) {
        return $fail(lang('That amount is more than the invoice is short of.'));
    }

    if ($amount > erp_txn_unallocated($txn_id) + $held) {
        return $fail(lang('That amount is more than is unallocated on this receipt.'));
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $settled = erp_settle(array(
        'invoice_id' => $invoice_id,
        'account_txn_id' => $txn_id,
        'account_id' => (int) $txn['account_id'],
        'amount' => $amount,
        'amount_base' => ($currency === $base) ? $amount : erp_to_base($amount, (float) $txn['exchange_rate']),
        'doc_date' => (string) $txn['doc_date'],
        'created_by' => $created_by,
    ));

    if (!$settled) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(($error !== '') ? $error : lang('The allocation could not be saved.'));
    }

    // A foreign invoice that is paid off now carries the gap between what was
    // invoiced and what was collected in the base currency.
    if (($currency !== $base) && erp_fx_auto_diff()) {
        $status_now = (string) db_value("SELECT status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");
        if (($status_now === 'paid') && !erp_fx_post_difference($invoice, $created_by)) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'allocated' => $amount, 'error' => '');
}

/**
 * A proposal for spreading a receipt's unallocated money over the account's
 * open invoices, oldest first.
 *
 * Nothing is written here. The proposal fills a form the operator reads and
 * changes before saving, so the allocation stays their decision; this only
 * saves the typing when the obvious answer is the right one.
 *
 * @param int $account_txn_id
 * @return array  invoice_id => amount (kurus) for every open invoice in the receipt's currency, 0 where nothing is left
 */
function erp_settlement_suggest($account_txn_id)
{
    $txn = db_item("SELECT account_id, doc_type, currency FROM erp_account_transactions
        WHERE id = '" . (int) $account_txn_id . "' LIMIT 1");

    if (!is_array($txn)) {
        return array();
    }

    $left = erp_txn_unallocated((int) $account_txn_id);
    $currency = strtoupper(trim((string) $txn['currency']));
    $suggestion = array();

    foreach (erp_open_invoices((int) $txn['account_id'], 200, ((string) $txn['doc_type'] === 'collection') ? 'sales' : 'purchase') as $invoice) {
        if (erp_fx_enabled() && (strtoupper(trim((string) $invoice['currency'])) !== $currency)) {
            continue;
        }

        $share = min($left, (int) $invoice['open_amount']);
        $suggestion[(int) $invoice['id']] = $share;
        $left -= $share;
    }

    return $suggestion;
}

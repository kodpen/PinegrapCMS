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

    $invoice = db_item("SELECT grand_total, status, order_id, payment_date FROM erp_invoices
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
 *                     amount_try, doc_date, created_by
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

    $amount_try = (int) ($data['amount_try'] ?? $amount);

    $ok = erp_query("INSERT INTO erp_settlements SET
            invoice_id = '" . $invoice_id . "',
            account_txn_id = '" . $txn_id . "',
            account_id = '" . (int) ($data['account_id'] ?? 0) . "',
            doc_date = '" . escape($data['doc_date'] ?? date('Y-m-d')) . "',
            amount = '" . $amount . "',
            amount_try = '" . $amount_try . "',
            created_by = '" . (int) ($data['created_by'] ?? 0) . "',
            created_at = '" . time() . "'
        ON DUPLICATE KEY UPDATE
            amount = '" . $amount . "',
            amount_try = '" . $amount_try . "',
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
        'currency' => 'TRY',
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
        'amount_try' => $amount,
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
            'currency' => 'TRY',
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
 * @param int $account_id
 * @param int $limit
 * @return array  Invoice rows with an extra 'open_amount'
 */
function erp_open_invoices($account_id, $limit = 200)
{
    $account_id = (int) $account_id;

    if ($account_id <= 0) {
        return array();
    }

    $rows = (array) db_items("SELECT id, doc_type, full_number, issue_date, due_date, currency,
            grand_total, paid_total, status, order_id
        FROM erp_invoices
        WHERE account_id = '" . $account_id . "'
          AND direction = 'sales'
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

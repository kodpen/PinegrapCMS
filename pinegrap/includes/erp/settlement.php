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
 * collectable, so both count here, while only the first makes the invoice
 * "paid" - which is why the status is worked out somewhere else.
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
 * A cancelled or draft invoice keeps its status - neither is waiting to be paid.
 *
 * @param int $invoice_id
 * @return bool
 */
function erp_invoice_refresh_paid($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    $invoice = db_item("SELECT grand_total, status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return false;
    }

    $paid = (int) db_value("SELECT COALESCE(SUM(amount), 0) FROM erp_settlements
        WHERE invoice_id = '" . $invoice_id . "'");

    $status = (string) $invoice['status'];

    if (($status !== 'cancelled') && ($status !== 'draft')) {
        if ($paid <= 0) {
            $status = 'issued';
        } elseif ($paid >= (int) $invoice['grand_total']) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }
    }

    return (erp_query("UPDATE erp_invoices
        SET paid_total = '" . $paid . "',
            status = '" . escape($status) . "',
            updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "'") !== false);
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
    return (array) db_items("SELECT s.*, t.description, t.kind, t.doc_id AS cash_id,
            c.name AS cash_account_name
        FROM erp_settlements s
        LEFT JOIN erp_account_transactions t ON s.account_txn_id = t.id
        LEFT JOIN erp_cash_transactions ct ON t.doc_id = ct.id
        LEFT JOIN erp_cash_accounts c ON ct.cash_account_id = c.id
        WHERE s.invoice_id = '" . (int) $invoice_id . "'
        ORDER BY s.doc_date ASC, s.id ASC");
}

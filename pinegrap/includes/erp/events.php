<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - telling the outside world what the module just did.
 *
 * Every event the module announces goes through erp_event(), which hands it to
 * the API's webhook queue and never delivers anything itself: a slow
 * subscriber must not hold up a receipt. The event names are declared in
 * includes/erp/api.php (erp_webhook_events()); a name that is not declared
 * there cannot be subscribed to, and the queue drops it without a word.
 *
 * The payload builders read the record again rather than trusting what the
 * caller had in hand, so every subscriber sees the same shape for the same
 * event whichever screen or endpoint caused it.
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
 * Queue one event for the applications subscribed to it.
 *
 * Safe to call from inside a ledger transaction: the queue row rides on the
 * same connection and rolls back with the receipt it announces. Safe when the
 * API is not installed: without the queue there is nobody to tell.
 *
 * @param string $event    'erp.invoice.created' and the like
 * @param array  $payload
 * @return int  Rows queued (one per subscription)
 */
function erp_event($event, $payload)
{
    if (!defined('PG_INIT_LOADED') || !defined('PG_FUNCTIONS_DIR')) {
        return 0;
    }

    // The audit trail keeps every announced event, with the API or without
    // it (includes/erp/audit.php).
    if (function_exists('erp_audit_event')) {
        erp_audit_event($event, $payload);
    }

    // The bell and the devices, for the events the store asked to be told
    // about as they happen (includes/erp/alerts.php).
    if (function_exists('erp_alert_event')) {
        erp_alert_event($event, $payload);
    }

    $webhooks = PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php';

    if (!function_exists('api_webhook_enqueue')) {
        if (!file_exists($webhooks)) {
            return 0;
        }
        require_once($webhooks);
    }

    if (!function_exists('api_webhook_enqueue')) {
        return 0;
    }

    return (int) api_webhook_enqueue((string) $event, (array) $payload);
}

/**
 * The date columns as the wire wants them: null for the empty date.
 *
 * @param string $value  Y-m-d or 0000-00-00
 * @return string|null
 */
function erp_event_date($value)
{
    $value = (string) $value;

    return (($value === '') || ($value === '0000-00-00')) ? null : $value;
}

/**
 * Announce an invoice: issued, paid or cancelled.
 *
 * @param int    $invoice_id
 * @param string $event  'erp.invoice.created' | 'erp.invoice.paid' | 'erp.invoice.cancelled' | 'erp.invoice.edoc_changed'
 * @return int
 */
function erp_event_invoice($invoice_id, $event)
{
    $invoice = db_item("SELECT id, full_number, direction, doc_type, status, account_id, order_id,
            parent_invoice_id, issue_date, due_date, currency, grand_total, paid_total,
            edoc_provider, edoc_status, gib_number, gib_uuid
        FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return 0;
    }

    // The document as issued is kept once the request that issued it is
    // over (includes/erp/archive.php).
    if (($event === 'erp.invoice.created') && function_exists('erp_archive_defer')) {
        erp_archive_defer('invoice', (int) $invoice['id']);
    }

    // The customer's copy by e-mail, where the store sends invoices on their
    // own (includes/erp/mail.php): on the issue, or on GIB's acceptance.
    if (in_array($event, array('erp.invoice.created', 'erp.invoice.edoc_changed'), true) && function_exists('erp_mail_auto_consider')) {
        erp_mail_auto_consider((int) $invoice['id'], $event);
    }

    return erp_event($event, array(
        'id' => (int) $invoice['id'],
        'number' => (string) $invoice['full_number'],
        'direction' => (string) $invoice['direction'],
        'doc_type' => (string) $invoice['doc_type'],
        'status' => (string) $invoice['status'],
        'account_id' => (int) $invoice['account_id'],
        'order_id' => (int) $invoice['order_id'],
        'parent_invoice_id' => (int) $invoice['parent_invoice_id'],
        'issue_date' => erp_event_date($invoice['issue_date']),
        'due_date' => erp_event_date($invoice['due_date']),
        'currency' => (string) $invoice['currency'],
        'grand_total' => (int) $invoice['grand_total'],
        'paid_total' => (int) $invoice['paid_total'],
        'edoc' => array(
            'provider' => (string) $invoice['edoc_provider'],
            'status' => (string) $invoice['edoc_status'],
            'gib_number' => (string) $invoice['gib_number'],
            'gib_uuid' => (string) $invoice['gib_uuid'],
        ),
    ));
}

/**
 * Announce a receipt or a payment: recorded or cancelled.
 *
 * @param int    $cash_id  The till movement's id (what the receipt screen calls the receipt)
 * @param string $event    'erp.receipt.created' | 'erp.receipt.cancelled'
 * @param int    $invoice_id  The invoice the money was named for, when one was
 * @return int
 */
function erp_event_receipt($cash_id, $event, $invoice_id = 0)
{
    $receipt = db_item("SELECT id, doc_type, doc_date, amount, currency, amount_base, account_id,
            cash_account_id, payment_method, description
        FROM erp_cash_transactions
        WHERE id = '" . (int) $cash_id . "' AND doc_type IN ('collection', 'payment') LIMIT 1");

    if (!is_array($receipt)) {
        return 0;
    }

    return erp_event($event, array(
        'id' => (int) $receipt['id'],
        'direction' => (string) $receipt['doc_type'],
        'date' => erp_event_date($receipt['doc_date']),
        'amount' => (int) $receipt['amount'],
        'currency' => (string) $receipt['currency'],
        'amount_base' => (int) $receipt['amount_base'],
        'account_id' => (int) $receipt['account_id'],
        'cash_account_id' => (int) $receipt['cash_account_id'],
        'payment_method' => (string) $receipt['payment_method'],
        'invoice_id' => (int) $invoice_id,
        'description' => (string) $receipt['description'],
    ));
}

/**
 * Announce an account that was just opened.
 *
 * @param int $account_id
 * @return int
 */
function erp_event_account($account_id)
{
    $account = db_item("SELECT id, kind, title, tax_number, contact_id, currency
        FROM erp_accounts WHERE id = '" . (int) $account_id . "' LIMIT 1");

    if (!is_array($account)) {
        return 0;
    }

    return erp_event('erp.account.created', array(
        'id' => (int) $account['id'],
        'kind' => (string) $account['kind'],
        'title' => (string) $account['title'],
        'tax_number' => (string) $account['tax_number'],
        'contact_id' => (int) $account['contact_id'],
        'currency' => (string) $account['currency'],
    ));
}

/**
 * Announce a delivery note that was just issued.
 *
 * @param int $waybill_id
 * @return int
 */
function erp_event_waybill($waybill_id)
{
    $waybill = db_item("SELECT id, full_number, status, account_id, order_id, invoice_id, issue_date, ship_date, carrier_title
        FROM erp_waybills WHERE id = '" . (int) $waybill_id . "' LIMIT 1");

    if (!is_array($waybill)) {
        return 0;
    }

    if (function_exists('erp_archive_defer')) {
        erp_archive_defer('waybill', (int) $waybill['id']);
    }

    return erp_event('erp.waybill.created', array(
        'id' => (int) $waybill['id'],
        'number' => (string) $waybill['full_number'],
        'status' => (string) $waybill['status'],
        'account_id' => (int) $waybill['account_id'],
        'order_id' => (int) $waybill['order_id'],
        'invoice_id' => (int) $waybill['invoice_id'],
        'issue_date' => erp_event_date($waybill['issue_date']),
        'ship_date' => erp_event_date($waybill['ship_date']),
        'carrier_title' => (string) $waybill['carrier_title'],
    ));
}

/**
 * Announce an expense: recorded, paid or cancelled. Called inside the
 * writer's transaction, after the row has its final state for that step, so
 * an expense recorded as paid announces itself as paid.
 *
 * @param int    $expense_id
 * @param string $event  'erp.expense.created' | 'erp.expense.paid' | 'erp.expense.cancelled'
 * @return int
 */
function erp_event_expense($expense_id, $event)
{
    $expense = db_item("SELECT e.id, e.expense_date, e.category_id, c.name AS category_name, e.supplier, e.supplier_tax_number,
            e.document_no, e.description, e.currency, e.net_amount, e.tax_amount, e.total_amount, e.total_base,
            e.tax_deductible, e.due_date, e.status, e.paid_date, e.cash_account_id, e.cash_id
        FROM erp_expenses e
        LEFT JOIN erp_expense_categories c ON c.id = e.category_id
        WHERE e.id = '" . (int) $expense_id . "' LIMIT 1");

    if (!is_array($expense)) {
        return 0;
    }

    return erp_event($event, array(
        'id' => (int) $expense['id'],
        'date' => erp_event_date($expense['expense_date']),
        'category_id' => (int) $expense['category_id'],
        'category_name' => (string) $expense['category_name'],
        'supplier' => (string) $expense['supplier'],
        'supplier_tax_number' => (string) $expense['supplier_tax_number'],
        'document_no' => (string) $expense['document_no'],
        'description' => (string) $expense['description'],
        'currency' => (string) $expense['currency'],
        'net_amount' => (int) $expense['net_amount'],
        'tax_amount' => (int) $expense['tax_amount'],
        'total_amount' => (int) $expense['total_amount'],
        'total_base' => (int) $expense['total_base'],
        'tax_deductible' => ((int) $expense['tax_deductible'] === 1),
        'due_date' => erp_event_date($expense['due_date']),
        'status' => (string) $expense['status'],
        'paid_date' => erp_event_date($expense['paid_date']),
        'cash_account_id' => (int) $expense['cash_account_id'],
        'cash_id' => (int) $expense['cash_id'],
    ));
}

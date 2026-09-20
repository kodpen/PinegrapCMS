<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - what the module adds to the external API.
 *
 * This file is the module's whole contact surface with the API: the routes,
 * the events, the objects and the permissions live here, in the module's own
 * folder, so that adding an ERP endpoint never means editing
 * includes/api/schema.php, scopes.php, openapi.php or outbound/webhooks.php.
 * The API merges the four functions below into its own lists (see
 * includes/api/modules.php) and only while ERP_ENABLED is on: a switched-off
 * module has no endpoints, no events and no permission to hand out.
 *
 * The handlers and the presenters are in api_resources.php next to this file;
 * this one stays a table, the way schema.php is a table.
 *
 * Two permissions, mirroring the panel: 'erp' is the ledger - accounts,
 * invoices, delivery notes - and 'erp_cash' is the money - receipts, payments
 * and the tills - because in the panel the till is a separate right and an
 * application must not hold more than its owner could delegate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The module's own helpers: the API entry does not load them for us.
require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api_resources.php');

/**
 * The permission rows on the Application Access screen.
 *
 * @return array
 */
function erp_scope_groups()
{
    return array(
        'erp' => array(
            'label' => lang('ERP'),
            'description' => lang('Accounts, invoices and delivery notes'),
            'icon' => 'bi-journal-text',
            'read' => 'erp:read',
            'write' => 'erp:write',
        ),
        'erp_cash' => array(
            'label' => lang('ERP cash'),
            'description' => lang('Receipts, payments and the tills'),
            'icon' => 'bi-cash-coin',
            'read' => 'erp_cash:read',
            'write' => 'erp_cash:write',
        ),
    );
}

/**
 * Which of the module's scopes an application owner may delegate.
 *
 * The API caps every application at what its owner holds today
 * (api_owner_scopes()); this answers for the module's own scopes, from the
 * owner row the API loaded. The till is a separate right in the panel, so a
 * key cannot hold the cash scopes unless its owner does. Called by the API
 * once it consults modules for the ceiling; until then it is simply not
 * called.
 *
 * @param array $owner  role, manage_erp, manage_erp_cash
 * @return array  Scope strings
 */
function erp_owner_scopes($owner)
{
    $role = (int) ($owner['role'] ?? 9);
    $scopes = array();

    if (($role < 3) || !empty($owner['manage_erp'])) {
        $scopes[] = 'erp:read';
        $scopes[] = 'erp:write';

        if (($role < 3) || !empty($owner['manage_erp_cash'])) {
            $scopes[] = 'erp_cash:read';
            $scopes[] = 'erp_cash:write';
        }
    }

    return $scopes;
}

/**
 * The events an application may subscribe to. Queued from the module's own
 * functions through erp_event() (includes/erp/events.php), so every screen
 * and every endpoint that does the thing announces it.
 *
 * @return array
 */
function erp_webhook_events()
{
    return array(
        'erp.account.created' => 'An ERP account (customer or supplier card) was opened',
        'erp.invoice.created' => 'An ERP invoice or return invoice was issued',
        'erp.invoice.paid' => 'An ERP invoice was paid in full',
        'erp.invoice.cancelled' => 'An ERP invoice was cancelled',
        'erp.receipt.created' => 'A receipt or a payment was recorded in the ERP',
        'erp.receipt.cancelled' => 'A receipt or a payment was cancelled in the ERP',
        'erp.waybill.created' => 'An ERP delivery note was issued',
    );
}

/**
 * The objects the endpoints answer with, and the function that declares each
 * one's fields (beside the presenter that builds the same shape).
 *
 * @return array
 */
function erp_openapi_objects()
{
    return array(
        'ErpAccount' => 'erp_api_account_schema',
        'ErpAccountTransaction' => 'erp_api_account_transaction_schema',
        'ErpInvoice' => 'erp_api_invoice_schema',
        'ErpReceipt' => 'erp_api_receipt_schema',
        'ErpCashAccount' => 'erp_api_cash_account_schema',
        'ErpWaybill' => 'erp_api_waybill_schema',
        'ErpDocument' => 'erp_api_document_schema',
    );
}

/**
 * The route rows, written the way includes/api/schema.php writes its own.
 *
 * Money is a whole number of minor units (kurus) everywhere, dates that name a
 * day are YYYY-MM-DD strings, moments are ISO-8601 UTC - the same conventions
 * as the rest of the API.
 *
 * @return array
 */
function erp_api_routes()
{
    $paging = array(
        array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => 'Rows per page.'),
        array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
        array('name' => 'include_count', 'in' => 'query', 'type' => 'bool', 'description' => 'Also count every matching row. Costs an extra query, so it is off by default.'),
    );

    return array(

        /* ----- Accounts ---------------------------------------------------- */

        array(
            'id' => 'erp.accounts.list',
            'method' => 'GET',
            'path' => '/erp/accounts',
            'scope' => 'erp:read',
            'handler' => 'erp_api_accounts_list',
            'returns' => array('list' => 'ErpAccount'),
            'summary' => 'List ERP accounts',
            'description' => 'Customer and supplier cards with their live balance. balance is in the store currency, positive when the account owes the store (a receivable) and negative when the store owes it. Pass tax_number or contact_id to find one card before creating another; pass updated_since to fetch only what changed.',
            'params' => array_merge(array(
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the title, the tax number or the e-mail address.'),
                array('name' => 'kind', 'in' => 'query', 'type' => 'enum', 'values' => array('customer', 'supplier', 'both'), 'description' => 'customer and supplier each include cards marked both.'),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('active', 'passive')),
                array('name' => 'tax_number', 'in' => 'query', 'type' => 'string', 'max_length' => 11, 'description' => 'Exact match on the tax or identity number.'),
                array('name' => 'contact_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The card linked to this customer (contacts record).'),
                array('name' => 'with_balance', 'in' => 'query', 'type' => 'bool', 'description' => 'Only cards whose balance is not zero.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only cards changed at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.accounts.get',
            'method' => 'GET',
            'path' => '/erp/accounts/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_accounts_get',
            'returns' => 'ErpAccount',
            'summary' => 'One ERP account',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.accounts.transactions',
            'method' => 'GET',
            'path' => '/erp/accounts/{id}/transactions',
            'scope' => 'erp:read',
            'handler' => 'erp_api_accounts_transactions',
            'returns' => array('list' => 'ErpAccountTransaction'),
            'summary' => 'An account statement',
            'description' => 'The ledger rows of one account, oldest first: opening balance, invoices, returns, receipts, payments, adjustments and exchange differences. direction is debit when the account came to owe more and credit when it paid or was credited; amount is in the row currency and amount_base in the store currency. The running balance is the caller\'s sum, so a page can be resumed with the cursor.',
            'params' => array_merge(array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Rows dated on or after this day.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Rows dated on or before this day.'),
                array('name' => 'kind', 'in' => 'query', 'type' => 'enum', 'values' => array('opening', 'invoice', 'return', 'collection', 'payment', 'adjustment', 'writeoff', 'fx_diff')),
            ), $paging),
        ),

        array(
            'id' => 'erp.accounts.create',
            'method' => 'POST',
            'path' => '/erp/accounts',
            'scope' => 'erp:write',
            'handler' => 'erp_api_accounts_create',
            'returns' => 'ErpAccount',
            'summary' => 'Open an ERP account',
            'description' => 'Opens one customer or supplier card. A tax_number already on an active card is refused with 422 and the existing id in the message, so an integration that syncs an accounting system looks first with GET /erp/accounts?tax_number= and does not open the same company twice. contact_id ties the card to a customer record; a customer already tied to another card is refused. opening_balance posts the opening entry in the same call: positive when the account owes the store. Send an Idempotency-Key so a retried call does not open two cards.',
            'params' => array(
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'required' => true, 'description' => 'The name on the card: the person or the company.'),
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('customer', 'supplier', 'both'), 'description' => 'customer unless said otherwise.'),
                array('name' => 'is_person', 'in' => 'body', 'type' => 'bool', 'description' => 'A person (identity number) rather than a company (tax number). On by default.'),
                array('name' => 'tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 11, 'description' => 'Tax number (10 digits) or identity number (11 digits).'),
                array('name' => 'tax_office', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'email', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'phone', 'in' => 'body', 'type' => 'string', 'max_length' => 50),
                array('name' => 'address', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'district', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'city', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'country_code', 'in' => 'body', 'type' => 'string', 'max_length' => 2, 'description' => 'ISO 3166-1 alpha-2. The store\'s own country when left out.'),
                array('name' => 'postcode', 'in' => 'body', 'type' => 'string', 'max_length' => 20),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'The currency the card is kept in. The store currency when left out; another one only when the ERP has foreign currency switched on.'),
                array('name' => 'contact_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The customer record (see /customers) this card belongs to.'),
                array('name' => 'payment_days', 'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 3650, 'description' => 'Days from an invoice date to its due date. 0 leaves it to the store default.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
                array('name' => 'opening_balance', 'in' => 'body', 'type' => 'money', 'description' => 'Minor units, in the card currency. Positive: the account owes the store. Negative: the store owes the account.'),
                array('name' => 'opening_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The day the opening balance is dated. Today when left out.'),
            ),
        ),

        array(
            'id' => 'erp.accounts.update',
            'method' => 'POST',
            'also_accepts' => array('PATCH'),
            'path' => '/erp/accounts/{id}',
            'scope' => 'erp:write',
            'handler' => 'erp_api_accounts_update',
            'returns' => 'ErpAccount',
            'summary' => 'Change an ERP account',
            'description' => 'Changes only the fields sent; the rest of the card stays as it is. The currency cannot be changed here: a card with movements is kept in the currency they were written in. contact_id ties the card to a customer record, null or 0 unties it; a customer already tied to another card is refused. PATCH is accepted beside POST.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('customer', 'supplier', 'both')),
                array('name' => 'is_person', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 11, 'description' => 'Tax number (10 digits) or identity number (11 digits). Empty clears it.'),
                array('name' => 'tax_office', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'email', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'phone', 'in' => 'body', 'type' => 'string', 'max_length' => 50),
                array('name' => 'address', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'district', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'city', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'country_code', 'in' => 'body', 'type' => 'string', 'max_length' => 2),
                array('name' => 'postcode', 'in' => 'body', 'type' => 'string', 'max_length' => 20),
                array('name' => 'contact_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'The customer record this card belongs to; null or 0 unties it.'),
                array('name' => 'payment_days', 'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 3650),
                array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('active', 'passive'), 'description' => 'A passive card is kept with its history but offered nowhere new.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
            ),
        ),

        array(
            'id' => 'erp.accounts.reconciliation',
            'method' => 'GET',
            'path' => '/erp/accounts/{id}/reconciliation',
            'scope' => 'erp:read',
            'handler' => 'erp_api_accounts_reconciliation',
            'returns' => 'ErpDocument',
            'summary' => 'A reconciliation letter as a PDF',
            'description' => 'The account reconciliation letter (cari mutabakat mektubu) the panel produces: the balance as of a day, the movements of the period, the agree / disagree boxes. Nothing is written or sent; the letter is the ledger\'s reading on that day, base64 encoded in the answer for an integration that mails or files it. The panel\'s own screen can e-mail it directly.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'as_of', 'in' => 'query', 'type' => 'datetime', 'description' => 'The day the balance is stated for. Today when left out; a future day is not a statement and falls back to today.'),
                array('name' => 'from', 'in' => 'query', 'type' => 'datetime', 'description' => 'The first day of the movements listed. The first day of the as_of year when left out.'),
                array('name' => 'reply_days', 'in' => 'query', 'type' => 'int', 'min' => 0, 'max' => 90, 'description' => 'Days the counterparty is given to object. 0 leaves the deadline sentence out. 7 when left out.'),
            ),
        ),

        /* ----- Invoices ---------------------------------------------------- */

        array(
            'id' => 'erp.invoices.list',
            'method' => 'GET',
            'path' => '/erp/invoices',
            'scope' => 'erp:read',
            'handler' => 'erp_api_invoices_list',
            'returns' => array('list' => 'ErpInvoice'),
            'summary' => 'List ERP invoices',
            'description' => 'Issued invoices, return invoices and purchase invoices with their lines and totals; drafts are not on the wire because a draft is not a document yet. Cursor paged by the moment the record last changed, so updated_since plus the cursor is how an accounting integration keeps itself in step: a payment that closes an invoice changes it, and it shows up again.',
            'params' => array_merge(array(
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'order_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The store order the invoice was written from.'),
                array('name' => 'number', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'Exact match on the full document number, PGF2026000000012 for example.'),
                array('name' => 'direction', 'in' => 'query', 'type' => 'enum', 'values' => array('sales', 'purchase')),
                array('name' => 'doc_type', 'in' => 'query', 'type' => 'enum', 'values' => array('invoice', 'return')),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('issued', 'partially_paid', 'paid', 'cancelled'), 'description' => 'issued means open and unpaid.'),
                array('name' => 'open', 'in' => 'query', 'type' => 'bool', 'description' => 'Only invoices still carrying an open amount (issued or partially paid).'),
                array('name' => 'issued_from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Issue date on or after this day.'),
                array('name' => 'issued_to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Issue date on or before this day.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only invoices changed at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.invoices.get',
            'method' => 'GET',
            'path' => '/erp/invoices/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_invoices_get',
            'returns' => 'ErpInvoice',
            'summary' => 'One ERP invoice',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.invoices.document',
            'method' => 'GET',
            'path' => '/erp/invoices/{id}/document',
            'scope' => 'erp:read',
            'handler' => 'erp_api_invoices_document',
            'returns' => 'ErpDocument',
            'summary' => 'The invoice as a PDF',
            'description' => 'The printed document, the same file the panel downloads, base64 encoded inside the JSON answer. For an integration that mails the invoice to the customer or files it in a document store.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        /* ----- Receipts and payments --------------------------------------- */

        array(
            'id' => 'erp.receipts.list',
            'method' => 'GET',
            'path' => '/erp/receipts',
            'scope' => 'erp_cash:read',
            'handler' => 'erp_api_receipts_list',
            'returns' => array('list' => 'ErpReceipt'),
            'summary' => 'List receipts and payments',
            'description' => 'Money taken in from customers (direction collection) and paid out to suppliers (direction payment), each with the till it moved through and the invoices it was allocated to. A cancelled receipt stays in the list with cancelled true; the reversal that cancelled it is a till movement of its own and is not a receipt. Cursor paged by id, oldest first.',
            'params' => array_merge(array(
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'cash_account_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The till or bank account, see /erp/cash-accounts.'),
                array('name' => 'direction', 'in' => 'query', 'type' => 'enum', 'values' => array('collection', 'payment')),
                array('name' => 'payment_method', 'in' => 'query', 'type' => 'enum', 'values' => array('cash', 'transfer', 'card', 'cheque', 'other')),
                array('name' => 'from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Dated on or after this day.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Dated on or before this day.'),
                array('name' => 'created_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only receipts recorded at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.receipts.get',
            'method' => 'GET',
            'path' => '/erp/receipts/{id}',
            'scope' => 'erp_cash:read',
            'handler' => 'erp_api_receipts_get',
            'returns' => 'ErpReceipt',
            'summary' => 'One receipt or payment',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.receipts.create',
            'method' => 'POST',
            'path' => '/erp/receipts',
            'scope' => 'erp_cash:write',
            'handler' => 'erp_api_receipts_create',
            'returns' => 'ErpReceipt',
            'summary' => 'Record a receipt or a payment',
            'description' => 'Writes the money into a till and onto the account in one transaction, the way the receipt screen does: a bank feed or a marketplace settlement report can post what arrived. Name invoice_id to close that invoice with this money; never more than it is short of is allocated, the rest stays on the account. The invoice must belong to the account, be open, and for a collection be a sales invoice. The application owner must hold the ERP cash right in the panel. Send an Idempotency-Key: a bank line posted twice is a balance nobody can reconcile.',
            'params' => array(
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'cash_account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The till or bank account the money moved through.'),
                array('name' => 'amount', 'in' => 'body', 'type' => 'money', 'required' => true, 'description' => 'Minor units, greater than zero, in the till currency.'),
                array('name' => 'direction', 'in' => 'body', 'type' => 'enum', 'values' => array('collection', 'payment'), 'description' => 'collection takes money in from a customer; payment pays a supplier. collection when left out.'),
                array('name' => 'date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The day of the movement. Today when left out.'),
                array('name' => 'payment_method', 'in' => 'body', 'type' => 'enum', 'values' => array('cash', 'transfer', 'card', 'cheque', 'other'), 'description' => 'transfer when left out.'),
                array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'What the bank line or the report said. Shows on the statement.'),
                array('name' => 'invoice_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The invoice to close with this money.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'Only with foreign currency switched on: the till currency, with exchange_rate against the store currency.'),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. Required when currency is not the store currency.'),
            ),
        ),

        array(
            'id' => 'erp.receipts.cancel',
            'method' => 'POST',
            'path' => '/erp/receipts/{id}/cancel',
            'scope' => 'erp_cash:write',
            'handler' => 'erp_api_receipts_cancel',
            'returns' => 'ErpReceipt',
            'summary' => 'Cancel a receipt or a payment',
            'description' => 'Reverses the movement rather than deleting it: the till and the account each get the opposite entry, dated today, and every invoice the receipt had closed is open again by that amount. The receipt then answers with cancelled true. A receipt already cancelled is refused. The reason is kept on the reversal, the way the panel asks for it.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 200, 'required' => true, 'description' => 'Why it is cancelled: a bounced cheque, a bank line posted twice, the wrong account.'),
            ),
        ),

        /* ----- Tills ------------------------------------------------------- */

        array(
            'id' => 'erp.cash_accounts.list',
            'method' => 'GET',
            'path' => '/erp/cash-accounts',
            'scope' => 'erp_cash:read',
            'handler' => 'erp_api_cash_accounts_list',
            'returns' => array('list' => 'ErpCashAccount'),
            'summary' => 'List tills and bank accounts',
            'description' => 'Where money is kept, with the live balance of each. An integration reads this once to learn the cash_account_id it posts receipts to.',
            'params' => array(
                array('name' => 'active', 'in' => 'query', 'type' => 'bool', 'description' => 'Limit to active or inactive tills. Both when left out.'),
            ),
        ),

        /* ----- Delivery notes ---------------------------------------------- */

        array(
            'id' => 'erp.waybills.list',
            'method' => 'GET',
            'path' => '/erp/waybills',
            'scope' => 'erp:read',
            'handler' => 'erp_api_waybills_list',
            'returns' => array('list' => 'ErpWaybill'),
            'summary' => 'List delivery notes',
            'description' => 'Internal delivery notes (sevk irsaliyesi) with their lines, the order and the invoice they belong to. Cursor paged by the moment the record last changed.',
            'params' => array_merge(array(
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'order_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'invoice_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('issued', 'cancelled')),
                array('name' => 'shipped_from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Ship date on or after this day.'),
                array('name' => 'shipped_to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Ship date on or before this day.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime'),
            ), $paging),
        ),

        array(
            'id' => 'erp.waybills.get',
            'method' => 'GET',
            'path' => '/erp/waybills/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_waybills_get',
            'returns' => 'ErpWaybill',
            'summary' => 'One delivery note',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.waybills.document',
            'method' => 'GET',
            'path' => '/erp/waybills/{id}/document',
            'scope' => 'erp:read',
            'handler' => 'erp_api_waybills_document',
            'returns' => 'ErpDocument',
            'summary' => 'The delivery note as a PDF',
            'description' => 'The printed note, the same file the panel downloads, base64 encoded inside the JSON answer. A cancelled note prints with its cancellation mark.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

    );
}

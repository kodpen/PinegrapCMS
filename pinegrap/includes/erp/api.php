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
 * invoices, delivery notes, expenses - and 'erp_cash' is the money - receipts,
 * payments and the tills - because in the panel the till is a separate right
 * and an application must not hold more than its owner could delegate. An
 * expense is recorded with the first; paying it, or cancelling one that was
 * paid, moves a till and needs the second as well.
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
            'description' => lang('Accounts, invoices, delivery notes and expenses'),
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
 * @param array $owner  role, manage_erp, manage_erp_cash, manage_erp_readonly
 * @return array  Scope strings
 */
function erp_owner_scopes($owner)
{
    $role = (int) ($owner['role'] ?? 9);
    $scopes = array();

    if (($role < 3) || !empty($owner['manage_erp'])) {
        // The read-only right (the accountant's) delegates reading only.
        $writes = ($role < 3) || empty($owner['manage_erp_readonly']);

        $scopes[] = 'erp:read';

        if ($writes) {
            $scopes[] = 'erp:write';
        }

        if (($role < 3) || !empty($owner['manage_erp_cash'])) {
            $scopes[] = 'erp_cash:read';

            if ($writes) {
                $scopes[] = 'erp_cash:write';
            }
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
        'erp.invoice.edoc_changed' => 'An ERP invoice\'s e-document status changed (sent to the provider, accepted or rejected by GİB)',
        'erp.receipt.created' => 'A receipt or a payment was recorded in the ERP',
        'erp.receipt.cancelled' => 'A receipt or a payment was cancelled in the ERP',
        'erp.waybill.created' => 'An ERP delivery note was issued',
        'erp.expense.created' => 'An ERP expense was recorded (from a screen, the API or a repeating expense)',
        'erp.expense.paid' => 'An ERP expense was paid from a till or bank account',
        'erp.expense.cancelled' => 'An ERP expense was cancelled',
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
        'ErpExpense' => 'erp_api_expense_schema',
        'ErpExpenseCategory' => 'erp_api_expense_category_schema',
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
                array('name' => 'tax_number', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'Exact match on the tax or identity number.'),
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
                array('name' => 'tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'Tax or identity number. Turkish accounts: VKN (10 digits) or TCKN (11 digits) with valid check digits; other countries: letters, digits, spaces, dots, dashes and slashes, as written.'),
                array('name' => 'tax_office', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'email', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'phone', 'in' => 'body', 'type' => 'string', 'max_length' => 50),
                array('name' => 'address', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'district', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'city', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'state', 'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'State or province, for addresses that have one. Not kept in Turkey, where the province is the city: sent for a Turkish address, it fills an empty city.'),
                array('name' => 'country_code', 'in' => 'body', 'type' => 'string', 'max_length' => 2, 'description' => 'ISO 3166-1 alpha-2. The store\'s own country when left out.'),
                array('name' => 'postcode', 'in' => 'body', 'type' => 'string', 'max_length' => 20),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'The currency the card is kept in. The store currency when left out; another one only when the ERP has foreign currency switched on.'),
                array('name' => 'contact_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The customer record (see /customers) this card belongs to.'),
                array('name' => 'payment_days', 'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 3650, 'description' => 'Days from an invoice date to its due date. 0 leaves it to the store default.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
                array('name' => 'credit_limit', 'in' => 'body', 'type' => 'money', 'description' => 'The most the account may owe, in minor units of the store currency. 0 or left out is no limit.'),
                array('name' => 'invoice_email', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Where invoices are e-mailed when that is not the main address.'),
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
                array('name' => 'tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'Tax or identity number, checked by the rules of the account\'s country (see create). Empty clears it.'),
                array('name' => 'tax_office', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'email', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'phone', 'in' => 'body', 'type' => 'string', 'max_length' => 50),
                array('name' => 'address', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'district', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'city', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'state', 'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'State or province, for addresses that have one. Not kept in Turkey, where the province is the city: sent for a Turkish address, it fills an empty city.'),
                array('name' => 'country_code', 'in' => 'body', 'type' => 'string', 'max_length' => 2),
                array('name' => 'postcode', 'in' => 'body', 'type' => 'string', 'max_length' => 20),
                array('name' => 'contact_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'The customer record this card belongs to; null or 0 unties it.'),
                array('name' => 'payment_days', 'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 3650),
                array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('active', 'passive'), 'description' => 'A passive card is kept with its history but offered nowhere new.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
                array('name' => 'credit_limit', 'in' => 'body', 'type' => 'money', 'description' => 'The most the account may owe, in minor units of the store currency. 0 is no limit.'),
                array('name' => 'invoice_email', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Where invoices are e-mailed when that is not the main address. Empty clears it.'),
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

        /* ----- Expenses ---------------------------------------------------- */

        array(
            'id' => 'erp.expense_categories.list',
            'method' => 'GET',
            'path' => '/erp/expense-categories',
            'scope' => 'erp:read',
            'handler' => 'erp_api_expense_categories_list',
            'returns' => array('list' => 'ErpExpenseCategory'),
            'summary' => 'List expense categories',
            'description' => 'The categories an expense is filed under (rent, fuel, software and the like), in the order the panel offers them. An integration reads this once to learn the category_id it records expenses with; a category that is not active is kept for the expenses already in it and offered for no new one.',
            'params' => array(
                array('name' => 'active', 'in' => 'query', 'type' => 'bool', 'description' => 'Limit to active or inactive categories. Both when left out.'),
            ),
        ),

        array(
            'id' => 'erp.expenses.list',
            'method' => 'GET',
            'path' => '/erp/expenses',
            'scope' => 'erp:read',
            'handler' => 'erp_api_expenses_list',
            'returns' => array('list' => 'ErpExpense'),
            'summary' => 'List expenses',
            'description' => 'Receipts for rent, fuel, subscriptions and the like: the costs that are not purchase invoices from an account. Each carries its figures in its own currency and in the store currency, whether its VAT is taken back, how it was paid and whether a picture or PDF of the receipt is kept. A cancelled expense stays in the list with status cancelled. Cursor paged by the moment the record last changed, so updated_since plus the cursor keeps an accounting integration in step: paying or cancelling an expense changes it.',
            'params' => array_merge(array(
                array('name' => 'category_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('unpaid', 'paid', 'cancelled'), 'description' => 'unpaid means recorded and left to pay later.'),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the supplier, its tax number, the receipt number or the description.'),
                array('name' => 'from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Expense date on or after this day.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Expense date on or before this day.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only expenses changed at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.expenses.get',
            'method' => 'GET',
            'path' => '/erp/expenses/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_expenses_get',
            'returns' => 'ErpExpense',
            'summary' => 'One expense',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.expenses.receipt',
            'method' => 'GET',
            'path' => '/erp/expenses/{id}/receipt',
            'scope' => 'erp:read',
            'handler' => 'erp_api_expenses_receipt',
            'returns' => 'ErpDocument',
            'summary' => 'The receipt file of an expense',
            'description' => 'The picture (JPG, PNG, GIF, WEBP) or PDF kept with the expense, base64 encoded inside the JSON answer; content_type says which. When the receipt was replaced this is the file in use; the earlier one stays in the panel. 404 when no file is kept.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.expenses.create',
            'method' => 'POST',
            'path' => '/erp/expenses',
            'scope' => 'erp:write',
            'handler' => 'erp_api_expenses_create',
            'returns' => 'ErpExpense',
            'summary' => 'Record an expense',
            'description' => 'Records one expense the way the panel\'s form does: amount is the total printed on the receipt, VAT included, unless includes_tax is false. tax_amount, when sent, is the VAT as the receipt prints it and wins over the one worked out from tax_rate, provided the rate could give it. The date cannot be after today, and nothing is written into a period the books are locked for. Name cash_account_id to record it as paid from that till in the same call; that needs the erp_cash:write scope and an owner who holds the ERP cash right, and the till must be kept in the expense\'s currency. Otherwise it is left to pay, with due_date if one is given. receipt_base64 keeps a picture or PDF of the receipt with it, at most 10 MB; it is checked before anything is written. Send an Idempotency-Key: a receipt posted twice is a cost counted twice.',
            'params' => array(
                array('name' => 'date', 'in' => 'body', 'type' => 'datetime', 'required' => true, 'description' => 'The day on the receipt. Not after today.'),
                array('name' => 'category_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'An active category, see /erp/expense-categories.'),
                array('name' => 'amount', 'in' => 'body', 'type' => 'money', 'required' => true, 'description' => 'Minor units, greater than zero, in the expense currency. The total with VAT unless includes_tax is false.'),
                array('name' => 'includes_tax', 'in' => 'body', 'type' => 'bool', 'description' => 'Whether amount includes the VAT. On by default: a receipt prints its total.'),
                array('name' => 'tax_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99.999, 'description' => 'VAT rate in percent, 20 for 20%. 0 when left out.'),
                array('name' => 'tax_amount', 'in' => 'body', 'type' => 'money', 'description' => 'The VAT as printed on the receipt, in minor units. Worked out from the rate when left out.'),
                array('name' => 'tax_deductible', 'in' => 'body', 'type' => 'bool', 'description' => 'Whether the store takes the VAT back. When left out, the category decides (on unless the category is marked otherwise); off makes the VAT part of the cost and keeps it out of the deductible VAT the accountant gets.'),
                array('name' => 'supplier', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Who the receipt is from.'),
                array('name' => 'supplier_tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'The supplier\'s tax or identity number, checked like an account\'s.'),
                array('name' => 'document_no', 'in' => 'body', 'type' => 'string', 'max_length' => 64, 'description' => 'The receipt or document number.'),
                array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'due_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'For an expense left to pay: the day it is due.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'Only with foreign currency switched on: the receipt currency, with exchange_rate against the store currency. The store currency when left out.'),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. Required when currency is not the store currency.'),
                array('name' => 'cash_account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Pay it now, from this till or bank account.'),
                array('name' => 'payment_method', 'in' => 'body', 'type' => 'enum', 'values' => array('cash', 'transfer', 'card', 'cheque', 'other'), 'description' => 'With cash_account_id. cash when left out.'),
                array('name' => 'paid_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'With cash_account_id: the day it was paid, not before the expense date and not after today. The expense date when left out.'),
                array('name' => 'receipt_base64', 'in' => 'body', 'type' => 'string', 'description' => 'A picture (JPG, PNG, GIF, WEBP) or PDF of the receipt, base64 encoded. The type is read from the bytes.'),
            ),
        ),

        array(
            'id' => 'erp.expenses.update',
            'method' => 'POST',
            'also_accepts' => array('PATCH'),
            'path' => '/erp/expenses/{id}',
            'scope' => 'erp:write',
            'handler' => 'erp_api_expenses_update',
            'returns' => 'ErpExpense',
            'summary' => 'Change an expense',
            'description' => 'Changes only the fields sent. A paid expense keeps its date and its figures - they are what left the till - so on one only category_id, supplier, supplier_tax_number, document_no, description and tax_deductible can change; sending the others is refused. A cancelled expense, or one dated in a locked period, is not changed. Sending amount alone keeps includes_tax on and works the VAT out again from the rate. PATCH is accepted beside POST.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'date', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'category_id', 'in' => 'body', 'type' => 'int', 'min' => 1),
                array('name' => 'amount', 'in' => 'body', 'type' => 'money'),
                array('name' => 'includes_tax', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'tax_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99.999),
                array('name' => 'tax_amount', 'in' => 'body', 'type' => 'money', 'nullable' => true, 'description' => 'null or an empty value works it out again from the rate.'),
                array('name' => 'tax_deductible', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'supplier', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'supplier_tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 32),
                array('name' => 'document_no', 'in' => 'body', 'type' => 'string', 'max_length' => 64),
                array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'due_date', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'null or an empty value clears it.'),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0),
            ),
        ),

        array(
            'id' => 'erp.expenses.pay',
            'method' => 'POST',
            'path' => '/erp/expenses/{id}/pay',
            'scope' => 'erp_cash:write',
            'handler' => 'erp_api_expenses_pay',
            'returns' => 'ErpExpense',
            'summary' => 'Pay an expense',
            'description' => 'Pays an expense that was left to pay: the money leaves the till in the same transaction that marks the expense paid, the way the expense screen does it. The till must be kept in the expense\'s currency; the day cannot be before the expense, after today or in a locked period. The application owner must hold the ERP cash right. An expense that is not waiting to be paid is refused.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'cash_account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The till or bank account it is paid from.'),
                array('name' => 'payment_method', 'in' => 'body', 'type' => 'enum', 'values' => array('cash', 'transfer', 'card', 'cheque', 'other'), 'description' => 'cash when left out.'),
                array('name' => 'date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The day it was paid. Today when left out.'),
            ),
        ),

        array(
            'id' => 'erp.expenses.cancel',
            'method' => 'POST',
            'path' => '/erp/expenses/{id}/cancel',
            'scope' => 'erp:write',
            'handler' => 'erp_api_expenses_cancel',
            'returns' => 'ErpExpense',
            'summary' => 'Cancel an expense',
            'description' => 'Cancels rather than deletes: the expense stays with status cancelled and counts for nothing. A paid one gets its money back into the till with a movement dated today, which also needs the erp_cash:write scope and the owner\'s ERP cash right. Neither the expense nor its payment may be in a locked period. An expense already cancelled is refused.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'required' => true, 'description' => 'Why it is cancelled: recorded twice, the wrong amount, returned.'),
            ),
        ),

        array(
            'id' => 'erp.expenses.receipt_put',
            'method' => 'POST',
            'path' => '/erp/expenses/{id}/receipt',
            'scope' => 'erp:write',
            'handler' => 'erp_api_expenses_receipt_put',
            'returns' => 'ErpExpense',
            'summary' => 'Keep the receipt file of an expense',
            'description' => 'Keeps a picture or PDF of the receipt with an expense, at most 10 MB; the type is read from the bytes. An expense that already has one answers 409 expense_has_receipt unless replace is true. Replacing keeps the earlier file with the expense, listed as replaced in the panel, and is refused for a cancelled expense or one dated in a locked period: that one has gone to the accountant with the file it had.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'content_base64', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'The file itself, base64 encoded.'),
                array('name' => 'replace', 'in' => 'body', 'type' => 'bool', 'description' => 'Take this file in place of the one kept. Off by default.'),
            ),
        ),

    );
}

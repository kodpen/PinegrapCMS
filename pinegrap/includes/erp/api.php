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
 * The handlers and the presenters are in api_resources.php next to this file,
 * the ones that write invoices and quotes in api_documents.php, the ones
 * that write delivery notes, stock counts and minimums in api_stock.php, and
 * the purchase orders' in api_purchase_orders.php; this one stays a table,
 * the way schema.php is a table.
 *
 * Two permissions, mirroring the panel: 'erp' is the ledger - accounts,
 * invoices, delivery notes, expenses, quotes, stock and the reports - and
 * 'erp_cash' is the money - receipts, payments, the tills and the cheques and
 * notes - because in the panel the till is a separate right and an
 * application must not hold more than its owner could delegate. An
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api_documents.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api_stock.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api_purchase_orders.php');

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
        'erp.waybill.cancelled' => 'An ERP delivery note was cancelled',
        'erp.expense.created' => 'An ERP expense was recorded (from a screen, the API or a repeating expense)',
        'erp.expense.paid' => 'An ERP expense was paid from a till or bank account',
        'erp.expense.cancelled' => 'An ERP expense was cancelled',
        'erp.quote.created' => 'An ERP sales quote was written and took its number',
        'erp.quote.accepted' => 'An ERP sales quote was marked accepted by the customer',
        'erp.quote.rejected' => 'An ERP sales quote was marked rejected by the customer',
        'erp.quote.cancelled' => 'An ERP sales quote was cancelled',
        'erp.quote.reopened' => 'An ERP sales quote was opened again (editable, running from today if it had expired)',
        'erp.quote.invoiced' => 'An ERP sales quote was turned into an invoice draft at the prices quoted',
        'erp.quote.signed' => 'An ERP sales quote was signed by the customer on the screen (an ordinary electronic signature); it is accepted from then on. Also carries signer_name and signed_at',
        'erp.stock_count.applied' => 'An ERP stock count was applied: the store\'s stock of every product on it was set to what was counted. Carries line_count and changed (the products whose stock moved)',
        'erp.purchase_order.created' => 'An ERP purchase order to a supplier was written and took its number',
        'erp.purchase_order.sent' => 'An ERP purchase order went to the supplier (e-mailed, or marked sent by hand); only the first time',
        'erp.purchase_order.received' => 'Everything on an ERP purchase order came in: the purchase invoices made from it carry every line in full',
        'erp.purchase_order.cancelled' => 'An ERP purchase order was cancelled',
        'erp.purchase_order.reopened' => 'A cancelled ERP purchase order was opened again; status is what its purchase invoices give it',
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
        'ErpInvoiceRecurrence' => 'erp_api_invoice_recurrence_schema',
        'ErpReceipt' => 'erp_api_receipt_schema',
        'ErpCashAccount' => 'erp_api_cash_account_schema',
        'ErpWaybill' => 'erp_api_waybill_schema',
        'ErpDocument' => 'erp_api_document_schema',
        'ErpExpense' => 'erp_api_expense_schema',
        'ErpExpenseCategory' => 'erp_api_expense_category_schema',
        'ErpQuote' => 'erp_api_quote_schema',
        'ErpQuoteSignature' => 'erp_api_quote_signature_schema',
        'ErpPurchaseOrder' => 'erp_api_purchase_order_schema',
        'ErpCheque' => 'erp_api_cheque_schema',
        'ErpStockLevel' => 'erp_api_stock_level_schema',
        'ErpStockMove' => 'erp_api_stock_move_schema',
        'ErpStockCount' => 'erp_api_stock_count_schema',
        'ErpStockCountItem' => 'erp_api_stock_count_item_schema',
        'ErpAgingReport' => 'erp_api_aging_report_schema',
        'ErpVatReport' => 'erp_api_vat_report_schema',
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

    // The lines of a typed invoice or a quote. The ERP works the figures out
    // (erp_manual_lines_build()); a client sends what was sold and reads the
    // tax, the discounts and the totals back from the answer.
    $lines = array('name' => 'lines', 'in' => 'body', 'type' => 'list', 'max_items' => 200, 'of' => array(
        'product_id' => 'integer',
        'description' => 'string',
        'quantity' => 'number',
        'unit' => 'string',
        'unit_price' => 'integer',
        'discount_rate' => 'number',
        'tax_rate' => 'number',
        'tax2_rate' => 'number',
        'withholding_code' => 'string',
        'withholding_rate' => 'number',
    ), 'description' => 'The lines, in order. unit_price is required: minor units of the document currency, before tax and discount. quantity is a decimal number written with a point (1.5), 1 when left out. discount_rate, tax_rate, tax2_rate and withholding_rate are percentages (20 for 20%). product_id ties the line to a product, whose name and tax rate fill description and tax_rate when they are left out; without a product both are required. unit is the UN/ECE unit code, C62 (piece) when left out. withholding_code is the VAT withholding code (601 and the like); a code on the list carries its own share, withholding_rate is read only for one that is not. tax2_rate only where the store names a second tax. Totals cannot be sent: the answer carries them.');

    // The same lines, tied to a purchase order's lines: an invoice draft made
    // from an order names the order line each of its lines delivers, and an
    // order names its own lines, so a line sent back stays the same line.
    $draft_lines = $lines;
    $draft_lines['of']['purchase_order_line'] = 'string';
    $draft_lines['description'] .= ' On a purchase draft made from a purchase order (purchase_order_id), purchase_order_line is the key of the order line the line delivers, as the draft gives it: send it back with each line, or the order no longer counts that line as coming in.';

    $order_lines = $lines;
    $order_lines['of']['key'] = 'string';
    $order_lines['description'] .= ' key is the line\'s own key, as the order gives it (lines[].key): send it back with each line that stays, so the purchase invoices and drafts made from the order keep counting against it. A line without one is a new line and is given a key.';

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
            'description' => 'Issued invoices, return invoices and purchase invoices with their lines and totals. Drafts are left out unless status=draft is asked for: a draft has no number yet and may still be thrown away. Cursor paged by the moment the record last changed, so updated_since plus the cursor is how an accounting integration keeps itself in step: a payment that closes an invoice changes it, and it shows up again.',
            'params' => array_merge(array(
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'order_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The store order the invoice was written from.'),
                array('name' => 'purchase_order_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The purchase order the purchase invoice was made from. Its drafts too when status=draft is asked for.'),
                array('name' => 'number', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'Exact match on the full document number, PGF2026000000012 for example.'),
                array('name' => 'direction', 'in' => 'query', 'type' => 'enum', 'values' => array('sales', 'purchase')),
                array('name' => 'doc_type', 'in' => 'query', 'type' => 'enum', 'values' => array('invoice', 'return')),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('draft', 'issued', 'partially_paid', 'paid', 'cancelled'), 'description' => 'issued means open and unpaid; draft lists the drafts, which no other filter shows.'),
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
            'description' => 'An issued invoice, a return, or a draft (status draft, no number yet).',
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
            'description' => 'The printed document, the same file the panel downloads, base64 encoded inside the JSON answer. For an integration that mails the invoice to the customer or files it in a document store. A draft has no document until it is issued and answers 404.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.invoices.create',
            'method' => 'POST',
            'path' => '/erp/invoices',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_create',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Write an invoice draft',
            'description' => 'Saves a sales or purchase invoice as a draft, the way the panel\'s invoice form does: no number is taken, nothing is posted to the account, stock and the books are untouched until POST /erp/invoices/{id}/issue. The ERP works out every line\'s discount, tax and withholding and the totals; they come back in the answer. A draft in another currency may be saved without a rate: the rate recorded for its date is used, or the one of the day it is issued. Send an Idempotency-Key so a retried call does not write two drafts.',
            'params' => array(
                array('name' => 'direction', 'in' => 'body', 'type' => 'enum', 'values' => array('sales', 'purchase'), 'description' => 'sales when left out. A purchase is a supplier\'s bill typed in.'),
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The customer or supplier card, see /erp/accounts.'),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The invoice date. Today when left out.'),
                array('name' => 'due_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'When it is due. Worked out from the account\'s payment days when left out.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'The store currency when left out; another one only when the ERP has foreign currency switched on.'),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. The rate recorded for the issue date when left out.'),
                array('name' => 'supplier_invoice_no', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'A purchase only: the number on the supplier\'s bill.'),
                array('name' => 'supplier_invoice_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'A purchase only: the date on the supplier\'s bill.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000, 'description' => 'Printed on the document.'),
                array_merge($lines, array('required' => true)),
            ),
        ),

        array(
            'id' => 'erp.invoices.update',
            'method' => 'POST',
            'also_accepts' => array('PATCH'),
            'path' => '/erp/invoices/{id}',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_update',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Change an invoice draft',
            'description' => 'Changes only the fields sent; lines, when sent, replace every line of the draft. Only a draft can be changed: an issued invoice answers 409 invoice_not_draft and is corrected with a return. The totals are worked out again from the lines. PATCH is accepted beside POST.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'direction', 'in' => 'body', 'type' => 'enum', 'values' => array('sales', 'purchase')),
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'due_date', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'An empty string works it out again from the account\'s payment days.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. 0 takes the rate recorded for the issue date.'),
                array('name' => 'supplier_invoice_no', 'in' => 'body', 'type' => 'string', 'max_length' => 32),
                array('name' => 'supplier_invoice_date', 'in' => 'body', 'type' => 'datetime', 'nullable' => true),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000),
                $draft_lines,
            ),
        ),

        array(
            'id' => 'erp.invoices.delete',
            'method' => 'DELETE',
            'path' => '/erp/invoices/{id}',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_delete',
            'dry_run' => true,
            'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary' => 'Delete an invoice draft',
            'description' => 'Deletes a draft outright: it never had a number or a movement, so nothing is reversed. An issued invoice answers 409 invoice_not_draft; it is cancelled with POST /erp/invoices/{id}/cancel instead.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.invoices.delete_post',
            'method' => 'POST',
            'path' => '/erp/invoices/{id}/delete',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_delete',
            'dry_run' => true,
            'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary' => 'Delete an invoice draft, addressed as a POST',
            'description' => 'The same deletion as DELETE /erp/invoices/{id}, for clients and servers that cannot send DELETE.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.invoices.issue',
            'method' => 'POST',
            'path' => '/erp/invoices/{id}/issue',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_issue',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Issue an invoice draft',
            'description' => 'Issues the draft: it takes the next number of the series, is posted to the account and moves stock, and it cannot be edited afterwards - a mistake is then put right with a return or a cancellation. The figures are worked out again from the stored lines. Refused with 422 period_locked when the issue date is in a locked period, and with 422 credit_limit when a sale would take the customer past a credit limit the store enforces. A draft in another currency without a rate takes the one recorded for its date. What the store does on its own once an invoice is issued (e-mailing it to the customer, where that is switched on) happens as it does from the panel; sending it to the e-document provider stays a step on the panel\'s invoice screen. An issued invoice answers 409 invoice_not_draft. Try it with X-Dry-Run first.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.invoices.cancel',
            'method' => 'POST',
            'path' => '/erp/invoices/{id}/cancel',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_cancel',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Cancel an issued invoice',
            'description' => 'Cancels an issued invoice or return for good: its movement on the account is reversed with an entry dated today, the stock it moved goes back, and the number stays used. It cannot be undone. Refused with 409 invoice_has_payments while money is allocated to it, 409 invoice_has_returns while a return stands against it, 409 invoice_at_provider once the e-document provider has it (that one is taken back from the panel\'s invoice screen, or corrected with a return), and 422 period_locked when its date is in a locked period. A draft answers 409 invoice_is_draft: delete it instead.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.invoices.return',
            'method' => 'POST',
            'path' => '/erp/invoices/{id}/returns',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_return',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Raise a return against an invoice',
            'description' => 'Issues a return invoice (its own number, in the return series) for the lines and quantities given: the account is credited for a sale or debited for a purchase, and the goods go back to stock the way the invoice moved them. It cannot be undone except by cancelling the return. Name each invoice line by its id (lines[].id of the invoice) with the quantity coming back, or send all true for everything still returnable. Never more than was sold and not yet returned; a full return copies the invoice\'s figures to the kurus. A draft answers 409 invoice_is_draft and a cancelled invoice 409 invoice_cancelled; refused with 422 period_locked when the return\'s date is in a locked period. The answer is the return.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'lines', 'in' => 'body', 'type' => 'list', 'max_items' => 200, 'of' => array('line_id' => 'integer', 'quantity' => 'number'), 'description' => 'Objects of {line_id, quantity}: the invoice line and how much of it comes back, as a decimal number. Lines left out do not come back.'),
                array('name' => 'all', 'in' => 'body', 'type' => 'bool', 'description' => 'true returns everything still returnable; lines is then left out.'),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The date of the return. Today when left out.'),
            ),
        ),

        array(
            'id' => 'erp.invoices.email',
            'method' => 'POST',
            'path' => '/erp/invoices/{id}/email',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_email',
            'dry_run' => true,
            'returns' => array('invoice_id' => 'integer', 'to' => 'string[]', 'official' => 'boolean'),
            'summary' => 'E-mail an invoice',
            'description' => 'Sends an issued sales invoice or return to the customer with its PDF attached - the official copy once GİB has accepted the e-document - from the store\'s address, and records it with the invoice the way the panel does. An e-mail cannot be called back: try it with X-Dry-Run first. A draft, a cancelled document or a purchase invoice answers 409 invoice_not_sendable. A send the mail server refuses answers 503 and is recorded as failed.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'to', 'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'Addresses, separated by commas. The account\'s invoice address, or its main address, when left out.'),
                array('name' => 'subject', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The store\'s default subject when left out.'),
                array('name' => 'message', 'in' => 'body', 'type' => 'string', 'max_length' => 5000, 'description' => 'The covering text. The store\'s default text when left out.'),
            ),
        ),

        array(
            'id' => 'erp.orders.invoice',
            'method' => 'POST',
            'path' => '/erp/orders/{order_id}/invoice',
            'scope' => 'erp:write',
            'handler' => 'erp_api_orders_invoice',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Issue the invoice of a store order',
            'description' => 'Issues the sales invoice of a complete order, dated today, the way the order screen does: the lines, shipping and fees as the customer was charged, posted to the customer\'s account (opened from the order when the customer has none). It takes a number and cannot be edited afterwards. An order has one invoice: one already invoiced answers 409 order_already_invoiced. Refused with 422 period_locked when today is in a locked period. An order\'s invoice is never stopped by a credit limit.',
            'params' => array(
                array('name' => 'order_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        /* ----- Repeating invoices ------------------------------------------ */

        array(
            'id' => 'erp.invoice_recurrences.list',
            'method' => 'GET',
            'path' => '/erp/invoice-recurrences',
            'scope' => 'erp:read',
            'handler' => 'erp_api_invoice_recurrences_list',
            'returns' => array('list' => 'ErpInvoiceRecurrence'),
            'summary' => 'List repeating invoices',
            'description' => 'Sales invoices set to be written again every month, three months, six months or year: from which invoice, the next date, whether each one is left as a draft or issued straight away, and the last one written. last_error says why the last date could not be written, when it could not. Cursor paged by id.',
            'params' => array_merge(array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('active', 'stopped')),
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
            ), $paging),
        ),

        array(
            'id' => 'erp.invoices.recurrence',
            'method' => 'POST',
            'path' => '/erp/invoices/{id}/recurrence',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoices_recurrence',
            'dry_run' => true,
            'returns' => 'ErpInvoiceRecurrence',
            'summary' => 'Repeat an issued invoice',
            'description' => 'Writes the same invoice again on a schedule, with the lines and prices it has now: as a draft to check, or issued straight away (then whatever the store does after an invoice is issued happens too). Only an issued sales invoice typed in the ERP repeats; an order\'s invoice does not. An invoice that already repeats answers 409 invoice_already_repeats: stop it first.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'every_months', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 12, 'required' => true, 'description' => '1, 3, 6 or 12.'),
                array('name' => 'first_date', 'in' => 'body', 'type' => 'datetime', 'required' => true, 'description' => 'The date of the first new invoice, after this one\'s date. Later ones fall on the same day of the month.'),
                array('name' => 'end_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The last date it may write one. Runs until stopped when left out.'),
                array('name' => 'mode', 'in' => 'body', 'type' => 'enum', 'values' => array('draft', 'issue'), 'description' => 'draft when left out.'),
            ),
        ),

        array(
            'id' => 'erp.invoice_recurrences.stop',
            'method' => 'POST',
            'path' => '/erp/invoice-recurrences/{id}/stop',
            'scope' => 'erp:write',
            'handler' => 'erp_api_invoice_recurrences_stop',
            'dry_run' => true,
            'returns' => 'ErpInvoiceRecurrence',
            'summary' => 'Stop a repeating invoice',
            'description' => 'No more invoices are written from it; the ones it wrote stay. A stopped one answers 409 recurrence_stopped and is not started again: repeat the invoice anew.',
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

        array(
            'id' => 'erp.waybills.create',
            'method' => 'POST',
            'path' => '/erp/waybills',
            'scope' => 'erp:write',
            'handler' => 'erp_api_waybills_create',
            'dry_run' => true,
            'returns' => 'ErpWaybill',
            'summary' => 'Write a delivery note',
            'description' => 'Writes a delivery note and gives it the next number of its series; it carries goods and no money. Three ways, as the panel offers them: invoice_id for an issued sales invoice (its account, lines, carrier and shipment date; an invoice written from an order ships as that order\'s note, with the invoice attached), order_id for an order (its customer\'s account, opened from the customer record when there is none, its items, its first recipient\'s address and the carrier and ship date the order screen recorded), or account_id with lines for goods typed by hand. What is sent takes the place of what the invoice or order gives: lines sent are the part that goes out. A recipient left empty is the account at the address on its card. An order or an invoice has one note: a second answers 409 order_already_shipped or 409 invoice_already_shipped; cancel the first to write another. A draft, cancelled or purchase invoice answers 409 invoice_not_shippable. Announced as erp.waybill.created. Try it with X-Dry-Run first.',
            'params' => array(
                array('name' => 'invoice_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The issued sales invoice the goods were sold on.'),
                array('name' => 'order_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The order whose goods go out.'),
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The account the goods go to, for a note typed without an invoice or an order; lines are then required. Not sent with invoice_id or order_id, which bring their own.'),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The note\'s date. Today when left out.'),
                array('name' => 'ship_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The day the goods leave. The note\'s date when left out, or what the order or invoice recorded.'),
                array('name' => 'ship_time', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'The hour the goods leave, HH:MM.'),
                array('name' => 'carrier_title', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'carrier_tax_number', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'For a Turkish store a VKN or TCKN (10 or 11 digits).'),
                array('name' => 'plate', 'in' => 'body', 'type' => 'string', 'max_length' => 20),
                array('name' => 'driver_name', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'driver_id_number', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'Printed on the note; never sent back by the API. For a Turkish store an 11-digit TCKN.'),
                array('name' => 'ship_to_title', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'ship_to_address', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'ship_to_district', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'ship_to_city', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
                array('name' => 'ship_to_state', 'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'Not kept in Turkey, where the province is the city.'),
                array('name' => 'ship_to_country', 'in' => 'body', 'type' => 'string', 'max_length' => 2, 'description' => 'ISO 3166-1 alpha-2. The store\'s own country when left out.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000),
                array('name' => 'lines', 'in' => 'body', 'type' => 'list', 'max_items' => 200, 'of' => array(
                    'product_id' => 'integer',
                    'description' => 'string',
                    'quantity' => 'number',
                    'unit' => 'string',
                ), 'description' => 'The goods, in order. quantity is a decimal number written with a point (1.5), 1 when left out. product_id ties the line to a product, whose name fills description when it is left out; without a product description is required. unit is the UN/ECE unit code, C62 (piece) when left out.'),
            ),
        ),

        array(
            'id' => 'erp.waybills.cancel',
            'method' => 'POST',
            'path' => '/erp/waybills/{id}/cancel',
            'scope' => 'erp:write',
            'handler' => 'erp_api_waybills_cancel',
            'dry_run' => true,
            'returns' => 'ErpWaybill',
            'summary' => 'Cancel a delivery note',
            'description' => 'Cancels the note for good: it keeps its number, prints with its cancellation mark, and its order or invoice can be shipped on a new note. It cannot be undone. A note that has been invoiced answers 409 waybill_invoiced (the invoice is what to cancel or return), one already cancelled 409 waybill_cancelled. Announced as erp.waybill.cancelled.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.waybills.invoice',
            'method' => 'POST',
            'path' => '/erp/waybills/{id}/invoice',
            'scope' => 'erp:write',
            'handler' => 'erp_api_waybills_invoice',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Invoice a delivery note',
            'description' => 'Bills the goods on the note. A note written for an order is billed the way the order is: the order\'s invoice is issued dated today (taking a number, refused for the reasons POST /erp/orders/{order_id}/invoice gives, 422 period_locked among them), or the note is tied to the invoice the order already has, which answers 200 instead of 201. A note typed by hand becomes an invoice draft with its lines and the catalogue prices where a product is known: finish the prices, then POST /erp/invoices/{id}/issue. A note already invoiced answers 409 waybill_invoiced, a cancelled one 409 waybill_cancelled. The answer is the invoice.',
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

        /* ----- Quotes ------------------------------------------------------ */

        array(
            'id' => 'erp.quotes.list',
            'method' => 'GET',
            'path' => '/erp/quotes',
            'scope' => 'erp:read',
            'handler' => 'erp_api_quotes_list',
            'returns' => array('list' => 'ErpQuote'),
            'summary' => 'List quotes',
            'description' => 'Priced offers to customers, with their lines and totals. A quote moves no money, no stock and no tax; once the customer says yes it becomes an invoice draft, and invoice_id then names it. status is what is stored; state is what the quote is today: an open quote past valid_until is expired, and an invoiced one whose draft was deleted reads as accepted again. The status filter works on state. signature says who signed on the screen and when, and whether the quote changed since; null while unsigned. Cursor paged by the moment the record last changed.',
            'params' => array_merge(array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'expired', 'accepted', 'rejected', 'invoiced', 'cancelled'), 'description' => 'The state as the panel shows it: open means still running, expired means open but past valid_until.'),
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'number', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'Exact match on the full quote number.'),
                array('name' => 'issued_from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Quote date on or after this day.'),
                array('name' => 'issued_to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Quote date on or before this day.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only quotes changed at or after this moment.'),
                array('name' => 'signed', 'in' => 'query', 'type' => 'bool', 'description' => 'Limit to quotes the customer signed (true) or has not signed (false).'),
            ), $paging),
        ),

        array(
            'id' => 'erp.quotes.get',
            'method' => 'GET',
            'path' => '/erp/quotes/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_quotes_get',
            'returns' => 'ErpQuote',
            'summary' => 'One quote',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.quotes.document',
            'method' => 'GET',
            'path' => '/erp/quotes/{id}/document',
            'scope' => 'erp:read',
            'handler' => 'erp_api_quotes_document',
            'returns' => 'ErpDocument',
            'summary' => 'The quote as a PDF',
            'description' => 'The printed quote in the store\'s invoice template, the same file the panel downloads and e-mails, base64 encoded inside the JSON answer. A signed quote carries its signature block.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.quotes.create',
            'method' => 'POST',
            'path' => '/erp/quotes',
            'scope' => 'erp:write',
            'handler' => 'erp_api_quotes_create',
            'dry_run' => true,
            'returns' => 'ErpQuote',
            'summary' => 'Write a quote',
            'description' => 'Writes a priced offer to a customer, the way the panel\'s quote form does: it takes the next number of the quote series and is open until valid_until. A quote moves no money, no stock and no tax. The ERP works out every line\'s discount, tax and withholding and the totals; they come back in the answer. Announced as erp.quote.created. Send an Idempotency-Key so a retried call does not write two quotes.',
            'params' => array(
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The customer card, see /erp/accounts.'),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The quote date. Today when left out.'),
                array('name' => 'valid_until', 'in' => 'body', 'type' => 'datetime', 'description' => 'The last day the offer stands. 30 days after the quote date when left out.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'The store currency when left out; another one only when the ERP has foreign currency switched on.'),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. The rate recorded for the quote date when left out.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000, 'description' => 'Printed on the quote.'),
                array_merge($lines, array('required' => true)),
            ),
        ),

        array(
            'id' => 'erp.quotes.update',
            'method' => 'POST',
            'also_accepts' => array('PATCH'),
            'path' => '/erp/quotes/{id}',
            'scope' => 'erp:write',
            'handler' => 'erp_api_quotes_update',
            'dry_run' => true,
            'returns' => 'ErpQuote',
            'summary' => 'Change an open quote',
            'description' => 'Changes only the fields sent; lines, when sent, replace every line. The number stays. Only an open quote can be changed, an expired one included: any other answers 409 quote_not_open - open it again with POST /erp/quotes/{id}/status first, or write a new one. A signed quote that is changed reads as changed since signing. PATCH is accepted beside POST.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'valid_until', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'An empty string sets it to 30 days after the quote date again.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. 0 takes the rate recorded for the quote date.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000),
                $lines,
            ),
        ),

        array(
            'id' => 'erp.quotes.status',
            'method' => 'POST',
            'path' => '/erp/quotes/{id}/status',
            'scope' => 'erp:write',
            'handler' => 'erp_api_quotes_status',
            'dry_run' => true,
            'returns' => 'ErpQuote',
            'summary' => 'Mark a quote accepted, rejected, cancelled or open again',
            'description' => 'What the customer said, the way the panel\'s buttons record it. accepted is taken from an open or expired quote; rejected and cancelled from an open, expired or accepted one; open takes back any of them, and a quote opened again past its date runs for 30 days from today. An invoiced quote is not moved: the invoice is where it goes on from there. A move the quote\'s state does not allow answers 409 quote_status_not_allowed. Announced as erp.quote.accepted, erp.quote.rejected, erp.quote.cancelled or erp.quote.reopened.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('open', 'accepted', 'rejected', 'cancelled'), 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.quotes.invoice',
            'method' => 'POST',
            'path' => '/erp/quotes/{id}/invoice',
            'scope' => 'erp:write',
            'handler' => 'erp_api_quotes_invoice',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Turn a quote into an invoice draft',
            'description' => 'Writes an invoice draft dated today at the prices quoted, with the quote\'s number in its notes, and marks the quote invoiced. The draft takes no number: check it, then POST /erp/invoices/{id}/issue. Taken from an open, expired or accepted quote; one already invoiced, rejected or cancelled answers 409 quote_not_invoiceable. A quote in another currency leaves the rate to the day the draft is issued when none is recorded for today. Deleting the draft makes the quote accepted again. Announced as erp.quote.invoiced. The answer is the draft.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.quotes.email',
            'method' => 'POST',
            'path' => '/erp/quotes/{id}/email',
            'scope' => 'erp:write',
            'handler' => 'erp_api_quotes_email',
            'dry_run' => true,
            'returns' => array('quote_id' => 'integer', 'to' => 'string[]'),
            'summary' => 'E-mail a quote',
            'description' => 'Sends the quote to the customer with its PDF attached, from the store\'s address, and records it with the quote the way the panel does. A rejected or cancelled quote answers 409 quote_not_sendable. An e-mail cannot be called back: try it with X-Dry-Run first. A send the mail server refuses answers 503 and is recorded as failed.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'to', 'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'Addresses, separated by commas. The account\'s invoice address, or its main address, when left out.'),
                array('name' => 'subject', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The store\'s default subject when left out.'),
                array('name' => 'message', 'in' => 'body', 'type' => 'string', 'max_length' => 5000, 'description' => 'The covering text. A sentence naming the valid-until date when left out.'),
            ),
        ),

        /* ----- Purchase orders --------------------------------------------- */

        array(
            'id' => 'erp.purchase_orders.list',
            'method' => 'GET',
            'path' => '/erp/purchase-orders',
            'scope' => 'erp:read',
            'handler' => 'erp_api_purchase_orders_list',
            'returns' => array('list' => 'ErpPurchaseOrder'),
            'summary' => 'List purchase orders',
            'description' => 'What the store ordered from its suppliers, with the lines, the totals and what has come against each line. A purchase order moves no money, no stock and no tax: the goods come in on the purchase invoices made from it, and those move stock and cost. status is open (not sent yet), sent (it went to the supplier), partial (some of the goods came in), received (all of them) or cancelled. partial and received are read from the issued purchase invoices made from the order; a cancelled invoice counts for nothing and a draft counts once it is issued. Per line, received_quantity is what came, drafted_quantity what is on drafts and outstanding_quantity what has not come yet. late is true while goods are still awaited past expected_date. Cursor paged by the moment the record last changed: a purchase invoice issued or cancelled against an order, or a draft made from it, changes it.',
            'params' => array_merge(array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'sent', 'partial', 'received', 'cancelled', 'waiting'), 'description' => 'waiting is open, sent and partial together: the orders whose goods have not all come.'),
                array('name' => 'late', 'in' => 'query', 'type' => 'bool', 'description' => 'Only the orders still waiting for goods past expected_date (true), or all the others (false).'),
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The supplier card.'),
                array('name' => 'number', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'Exact match on the full purchase order number.'),
                array('name' => 'issued_from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Order date on or after this day.'),
                array('name' => 'issued_to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Order date on or before this day.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only orders changed at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.purchase_orders.get',
            'method' => 'GET',
            'path' => '/erp/purchase-orders/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_purchase_orders_get',
            'returns' => 'ErpPurchaseOrder',
            'summary' => 'One purchase order',
            'description' => 'The order with what has come against each line, and the purchase invoices and drafts made from it (invoices).',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.purchase_orders.document',
            'method' => 'GET',
            'path' => '/erp/purchase-orders/{id}/document',
            'scope' => 'erp:read',
            'handler' => 'erp_api_purchase_orders_document',
            'returns' => 'ErpDocument',
            'summary' => 'The purchase order as a PDF',
            'description' => 'The printed order in the store\'s purchase order template, the same file the panel downloads and e-mails, base64 encoded inside the JSON answer.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.purchase_orders.create',
            'method' => 'POST',
            'path' => '/erp/purchase-orders',
            'scope' => 'erp:write',
            'handler' => 'erp_api_purchase_orders_create',
            'dry_run' => true,
            'returns' => 'ErpPurchaseOrder',
            'summary' => 'Write a purchase order',
            'description' => 'Writes an order to a supplier, the way the panel\'s purchase order form does: it takes the next number of the purchase order series and is open until it is sent. A purchase order moves no money, no stock and no tax. The ERP works out every line\'s discount, tax and withholding and the totals; they come back in the answer, and every line is given a key of its own (lines[].key). Announced as erp.purchase_order.created. Send an Idempotency-Key so a retried call does not write two orders.',
            'params' => array(
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The supplier card, see /erp/accounts.'),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The order date. Today when left out.'),
                array('name' => 'expected_date', 'in' => 'body', 'type' => 'datetime', 'description' => 'The day the goods are expected. None when left out.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3, 'description' => 'The store currency when left out; another one only when the ERP has foreign currency switched on.'),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. The rate recorded for the order date when left out.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000, 'description' => 'Printed on the order.'),
                array_merge($lines, array('required' => true)),
            ),
        ),

        array(
            'id' => 'erp.purchase_orders.update',
            'method' => 'POST',
            'also_accepts' => array('PATCH'),
            'path' => '/erp/purchase-orders/{id}',
            'scope' => 'erp:write',
            'handler' => 'erp_api_purchase_orders_update',
            'dry_run' => true,
            'returns' => 'ErpPurchaseOrder',
            'summary' => 'Change a purchase order',
            'description' => 'Changes only the fields sent; lines, when sent, replace every line: send each line that stays with its key. The number stays. Only an open or sent order can be changed; one whose goods have started to come in, one received in full and a cancelled one answer 409 purchase_order_not_editable. PATCH is accepted beside POST.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'account_id', 'in' => 'body', 'type' => 'int', 'min' => 1),
                array('name' => 'issue_date', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'expected_date', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'An empty string clears it.'),
                array('name' => 'currency', 'in' => 'body', 'type' => 'string', 'max_length' => 3),
                array('name' => 'exchange_rate', 'in' => 'body', 'type' => 'decimal', 'min' => 0, 'description' => 'Store currency per one unit of currency. 0 takes the rate recorded for the order date.'),
                array('name' => 'notes', 'in' => 'body', 'type' => 'string', 'max_length' => 5000),
                $order_lines,
            ),
        ),

        array(
            'id' => 'erp.purchase_orders.status',
            'method' => 'POST',
            'path' => '/erp/purchase-orders/{id}/status',
            'scope' => 'erp:write',
            'handler' => 'erp_api_purchase_orders_status',
            'dry_run' => true,
            'returns' => 'ErpPurchaseOrder',
            'summary' => 'Mark a purchase order sent, cancelled or open again',
            'description' => 'sent records that the order went to the supplier by phone or on paper (POST /erp/purchase-orders/{id}/email marks it sent by itself); the first time is kept. It is taken from any order but a cancelled one. cancelled is taken from any order but one received in full; the order keeps its number and lines, and what already came in stays on its purchase invoices. open takes back a cancelled order, which goes back to the status its purchase invoices give it. A move the order\'s status does not allow answers 409 purchase_order_status_not_allowed. Announced as erp.purchase_order.sent (the first time only), erp.purchase_order.cancelled or erp.purchase_order.reopened.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('sent', 'cancelled', 'open'), 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.purchase_orders.invoice',
            'method' => 'POST',
            'path' => '/erp/purchase-orders/{id}/invoice',
            'scope' => 'erp:write',
            'handler' => 'erp_api_purchase_orders_invoice',
            'dry_run' => true,
            'returns' => 'ErpInvoice',
            'summary' => 'Make a purchase invoice draft from a purchase order',
            'description' => 'Writes a purchase invoice draft for what has not come yet and is not already on a draft: the order\'s prices, today\'s date, the supplier\'s payment term, the order\'s number in its notes, and on each line the key of the order line it delivers (lines[].purchase_order_line). Repeatable, one draft per delivery. Match it to the supplier\'s bill with POST /erp/invoices/{id} (its number and date, the quantities that came), then POST /erp/invoices/{id}/issue: stock and cost move then, and the order turns partial or received. A cancelled order, one received in full and one whose every line is already on an invoice or a draft answer 409 purchase_order_not_invoiceable. In another currency the rate recorded for today is used, or the one of the day the draft is issued. Deleting the draft gives its quantities back to the order. The answer is the draft.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.purchase_orders.email',
            'method' => 'POST',
            'path' => '/erp/purchase-orders/{id}/email',
            'scope' => 'erp:write',
            'handler' => 'erp_api_purchase_orders_email',
            'dry_run' => true,
            'returns' => array('purchase_order_id' => 'integer', 'to' => 'string[]'),
            'summary' => 'E-mail a purchase order',
            'description' => 'Sends the order to the supplier with its PDF attached, from the store\'s address, records it with the order the way the panel does and marks the order sent. A cancelled order answers 409 purchase_order_not_sendable. An e-mail cannot be called back: try it with X-Dry-Run first. A send the mail server refuses answers 503 and is recorded as failed.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'to', 'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'Addresses, separated by commas. The account\'s invoice address, or its main address, when left out.'),
                array('name' => 'subject', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The store\'s default subject when left out.'),
                array('name' => 'message', 'in' => 'body', 'type' => 'string', 'max_length' => 5000, 'description' => 'The covering text. A request to confirm the order, with the expected date, when left out.'),
            ),
        ),

        /* ----- Cheques and notes ------------------------------------------- */

        array(
            'id' => 'erp.cheques.list',
            'method' => 'GET',
            'path' => '/erp/cheques',
            'scope' => 'erp_cash:read',
            'handler' => 'erp_api_cheques_list',
            'returns' => array('list' => 'ErpCheque'),
            'summary' => 'List cheques and promissory notes',
            'description' => 'Cheques and notes taken from customers (direction received) and given to suppliers (direction given), with where each one is now: in the portfolio, at the bank, collected, passed on, bounced, given or paid. The money of every step is a till movement of its own; open_cash_id is the receipt or payment that recorded the cheque, close_cash_id the movement that closed it. Cursor paged by the moment the record last changed.',
            'params' => array_merge(array(
                array('name' => 'direction', 'in' => 'query', 'type' => 'enum', 'values' => array('received', 'given')),
                array('name' => 'kind', 'in' => 'query', 'type' => 'enum', 'values' => array('cheque', 'note')),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'portfolio', 'deposited', 'collected', 'endorsed', 'bounced', 'given', 'paid'), 'description' => 'open means still waiting: in the portfolio, at the bank, or given and not yet paid.'),
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'due_from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Due on or after this day.'),
                array('name' => 'due_to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Due on or before this day.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only cheques and notes changed at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.cheques.get',
            'method' => 'GET',
            'path' => '/erp/cheques/{id}',
            'scope' => 'erp_cash:read',
            'handler' => 'erp_api_cheques_get',
            'returns' => 'ErpCheque',
            'summary' => 'One cheque or promissory note',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        /* ----- Stock ------------------------------------------------------- */

        array(
            'id' => 'erp.stock.levels',
            'method' => 'GET',
            'path' => '/erp/stock/levels',
            'scope' => 'erp:read',
            'handler' => 'erp_api_stock_levels',
            'returns' => array('list' => 'ErpStockLevel'),
            'summary' => 'Stock levels against their minimum',
            'description' => 'The products that track stock, with the count the store sells from and the minimum set for each in the ERP. low is true when a minimum is set and the count is at or below it. A product without a minimum has minimum 0 and is never low. Cursor paged by product id.',
            'params' => array_merge(array(
                array('name' => 'low', 'in' => 'query', 'type' => 'bool', 'description' => 'Only products at or below their minimum.'),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the product code or its name.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.stock.minimums',
            'method' => 'POST',
            'path' => '/erp/stock/minimums',
            'scope' => 'erp:write',
            'handler' => 'erp_api_stock_minimums_save',
            'dry_run' => true,
            'returns' => array('changed' => 'integer', 'levels' => 'ErpStockLevel[]'),
            'summary' => 'Set minimum stock',
            'description' => 'Sets the minimum of each product named, the way the panel\'s minimum stock screen does: a whole number of pieces, 0 for none. A product on sale at or below its minimum is in the ERP\'s low stock notice and reorder list. Only products that track stock take a minimum. Read them with GET /erp/stock/levels. changed counts the products whose minimum moved; levels is each product named, as it stands now.',
            'params' => array(
                array('name' => 'minimums', 'in' => 'body', 'type' => 'list', 'max_items' => 500, 'required' => true, 'of' => array(
                    'product_id' => 'integer',
                    'minimum' => 'integer',
                ), 'description' => 'One or more of {product_id, minimum}; each product once.'),
            ),
        ),

        array(
            'id' => 'erp.stock.moves',
            'method' => 'GET',
            'path' => '/erp/stock/moves',
            'scope' => 'erp:read',
            'handler' => 'erp_api_stock_moves',
            'returns' => array('list' => 'ErpStockMove'),
            'summary' => 'Stock movements from documents',
            'description' => 'What purchase invoices, typed sales invoices, their returns and their cancellations did to stock and cost: one row per document line. unit_cost and cost_total are in the store currency. A sales invoice written from an order carries a movement for its cost only - the order already took the goods out - and says so with stock_effect none. Stock counts are not movements and do not appear here; see /erp/stock-counts. Cursor paged by id, oldest first.',
            'params' => array_merge(array(
                array('name' => 'product_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'invoice_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'kind', 'in' => 'query', 'type' => 'enum', 'values' => array('purchase', 'sale', 'purchase_return', 'sales_return', 'cancel')),
                array('name' => 'from', 'in' => 'query', 'type' => 'datetime', 'description' => 'Document date on or after this day.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'datetime', 'description' => 'Document date on or before this day.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.stock_counts.list',
            'method' => 'GET',
            'path' => '/erp/stock-counts',
            'scope' => 'erp:read',
            'handler' => 'erp_api_stock_counts_list',
            'returns' => array('list' => 'ErpStockCount'),
            'summary' => 'List stock counts',
            'description' => 'The shelf counts taken in the ERP: open while counting, applied once the store\'s stock was set to what was counted, or cancelled. items is null in the list, which carries line_count; read one count for its lines. Cursor paged by the moment the record last changed.',
            'params' => array_merge(array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'applied', 'cancelled')),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only counts changed at or after this moment.'),
            ), $paging),
        ),

        array(
            'id' => 'erp.stock_counts.get',
            'method' => 'GET',
            'path' => '/erp/stock-counts/{id}',
            'scope' => 'erp:read',
            'handler' => 'erp_api_stock_counts_get',
            'returns' => 'ErpStockCount',
            'summary' => 'One stock count with its lines',
            'description' => 'The count and every product on it. stock_before is what the store had when the count was applied and difference is counted less that; both are null until the count is applied. stock_now is the product\'s count today.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.stock_counts.create',
            'method' => 'POST',
            'path' => '/erp/stock-counts',
            'scope' => 'erp:write',
            'handler' => 'erp_api_stock_counts_create',
            'dry_run' => true,
            'returns' => 'ErpStockCount',
            'summary' => 'Start a stock count',
            'description' => 'Opens an empty count. Nothing changes in the store until the count is applied: add what is on the shelves with POST /erp/stock-counts/{id}/items, then POST /erp/stock-counts/{id}/apply.',
            'params' => array(
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => '"Count of" and today\'s date when left out.'),
            ),
        ),

        array(
            'id' => 'erp.stock_counts.items',
            'method' => 'POST',
            'path' => '/erp/stock-counts/{id}/items',
            'scope' => 'erp:write',
            'handler' => 'erp_api_stock_counts_items',
            'dry_run' => true,
            'returns' => 'ErpStockCount',
            'summary' => 'Count products on an open count',
            'description' => 'What was found on the shelves, as the panel\'s scanner records it, taken in order and all together or not at all. Each item names its product by product_id or by code (a barcode or the product code) and says what to do: add (the default) adds quantity to what has been counted, 1 when left out, the way one scan does; set makes quantity the count; remove takes the product off the count. Only products that track stock are counted. The store\'s stock does not change until the count is applied. A count that is applied or cancelled answers 409 stock_count_not_open. The answer is the whole count.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'items', 'in' => 'body', 'type' => 'list', 'max_items' => 500, 'required' => true, 'of' => array(
                    'product_id' => 'integer',
                    'code' => 'string',
                    'quantity' => 'integer',
                    'mode' => 'string',
                ), 'description' => 'One or more of {product_id or code, quantity, mode}. mode is add, set or remove. quantity is a whole number of pieces: from 1 to add, from 0 to set; it is not read for remove.'),
            ),
        ),

        array(
            'id' => 'erp.stock_counts.apply',
            'method' => 'POST',
            'path' => '/erp/stock-counts/{id}/apply',
            'scope' => 'erp:write',
            'handler' => 'erp_api_stock_counts_apply',
            'dry_run' => true,
            'returns' => 'ErpStockCount',
            'summary' => 'Apply a stock count',
            'description' => 'Sets the store\'s stock of every product on the count to what was counted, at once; products not on the count are left as they are. It cannot be undone: what each product had before is kept on the count as stock_before, and the count can no longer change. A product counted at 0 goes out of stock. A rehearsal with X-Dry-Run tells how many products would change. A count with nothing on it answers 409 stock_count_empty, one already applied or cancelled 409 stock_count_not_open. Announced as erp.stock_count.applied.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id' => 'erp.stock_counts.cancel',
            'method' => 'POST',
            'path' => '/erp/stock-counts/{id}/cancel',
            'scope' => 'erp:write',
            'handler' => 'erp_api_stock_counts_cancel',
            'dry_run' => true,
            'returns' => 'ErpStockCount',
            'summary' => 'Cancel a stock count',
            'description' => 'Leaves an open count without applying it: no stock changes, and the count can no longer change. A count already applied or cancelled answers 409 stock_count_not_open.',
            'params' => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        /* ----- Reports ----------------------------------------------------- */

        array(
            'id' => 'erp.reports.aging',
            'method' => 'GET',
            'path' => '/erp/reports/aging',
            'scope' => 'erp:read',
            'handler' => 'erp_api_reports_aging',
            'returns' => 'ErpAgingReport',
            'summary' => 'Receivables or payables by age',
            'description' => 'The open invoices as they stood on as_of, added up by how far past their due date they were: not yet due, 1-30, 31-60, 61-90 and over 90 days. Only the payments and returns dated on or before as_of are taken off, so a month end asked for later gives that month end\'s position. Amounts are in the store currency at the booked rates, the figures the account balances are made of; accounts are listed largest claim first. The same figures as the panel\'s aging report.',
            'params' => array(
                array('name' => 'direction', 'in' => 'query', 'type' => 'enum', 'values' => array('sales', 'purchase'), 'default' => 'sales', 'description' => 'sales for what customers owe, purchase for what the store owes suppliers.'),
                array('name' => 'as_of', 'in' => 'query', 'type' => 'datetime', 'description' => 'The day the position is taken on. Today when left out.'),
                array('name' => 'account_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'One account only.'),
            ),
        ),

        array(
            'id' => 'erp.reports.vat',
            'method' => 'GET',
            'path' => '/erp/reports/vat',
            'scope' => 'erp:read',
            'handler' => 'erp_api_reports_vat',
            'returns' => 'ErpVatReport',
            'summary' => 'The VAT report of a period',
            'description' => 'The tax the sales of a period carried less the sales returns (calculated), the tax of the purchases less the returns to suppliers plus the tax on expenses that is taken back (deductible), and the difference - the same figures as the panel\'s tax report and the accountant\'s pack. Broken down by kind of document, by rate, by withholding code and by month. Everything is in the store currency; drafts and cancelled documents count for nothing, and cancelled says how many were left out. Name a month, or from and to; the current month when neither is sent. A period covers at most two years.',
            'params' => array(
                array('name' => 'month', 'in' => 'query', 'type' => 'string', 'max_length' => 7, 'description' => 'YYYY-MM.'),
                array('name' => 'from', 'in' => 'query', 'type' => 'datetime', 'description' => 'The first day of the period, with to.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'datetime', 'description' => 'The last day of the period, with from.'),
            ),
        ),

    );
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the handlers behind the module's API routes, and the shapes they
 * answer with.
 *
 * Reading goes straight to the tables the panel screens read; writing goes
 * through the same functions the screens call (erp_account_save(),
 * erp_post_receipt()), so an application cannot record anything an operator
 * could not, and everything it records announces itself the same way.
 *
 * Every presenter has its schema function beside it, one line per field: the
 * OpenAPI document is generated from the schema, and a field the presenter
 * adds without a line there is a field no client can see.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Shared
   --------------------------------------------------------------------------- */

/**
 * A DATE column on the wire: null for the empty date.
 *
 * @param string $value
 * @return string|null
 */
function erp_api_date($value)
{
    $value = (string) $value;

    return (($value === '') || ($value === '0000-00-00')) ? null : $value;
}

/**
 * A unix moment as the day it falls on, for the DATE columns the filters hit.
 *
 * @param int $unix
 * @return string  Y-m-d
 */
function erp_api_day($unix)
{
    return date('Y-m-d', (int) $unix);
}

/**
 * The cursor of a listing, or a 400 for one this API did not issue.
 *
 * @param array $params
 * @return array|null  ['v' => int, 'i' => int]
 */
function erp_api_cursor($params)
{
    if (!isset($params['cursor']) || ($params['cursor'] === '')) {
        return null;
    }

    $cursor = api_cursor_decode($params['cursor']);

    if ($cursor === null) {
        api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
    }

    return $cursor;
}

/**
 * One page of rows, walked on (sort column, id).
 *
 * @param string $select      Everything up to and including FROM ... JOIN ...
 * @param array  $where       Conditions without the cursor
 * @param string $sort        Column the cursor walks, '' to walk the id alone
 * @param string $id_column   The id column, table-qualified
 * @param array  $params      limit, cursor, include_count
 * @param string $count_from  FROM ... for the count, when it differs from $select's
 * @return array ['rows' => array, 'next_cursor' => string|null, 'total' => int|null, 'limit' => int]
 */
function erp_api_page($select, $where, $sort, $id_column, $params, $count_from)
{
    $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
    $cursor = erp_api_cursor($params);

    $paged = $where;

    if ($cursor !== null) {
        $paged[] = ($sort === '')
            ? $id_column . " > '" . (int) $cursor['i'] . "'"
            : "(" . $sort . " > '" . (int) $cursor['v'] . "' OR (" . $sort . " = '" . (int) $cursor['v'] . "' AND " . $id_column . " > '" . (int) $cursor['i'] . "'))";
    }

    $order = ($sort === '') ? $id_column . " ASC" : $sort . " ASC, " . $id_column . " ASC";

    $rows = (array) db_items($select
        . (empty($paged) ? '' : ' WHERE ' . implode(' AND ', $paged))
        . " ORDER BY " . $order . " LIMIT " . ($limit + 1));

    $has_more = (count($rows) > $limit);

    if ($has_more) {
        array_pop($rows);
    }

    $next_cursor = null;

    if ($has_more && !empty($rows)) {
        $last = $rows[count($rows) - 1];
        $next_cursor = api_cursor_encode(($sort === '') ? 0 : (int) $last['_sort'], (int) $last['id']);
    }

    $total = null;

    if (!empty($params['include_count'])) {
        $total = (int) db_value("SELECT COUNT(*) " . $count_from
            . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));
    }

    return array('rows' => $rows, 'next_cursor' => $next_cursor, 'total' => $total, 'limit' => $limit);
}

/**
 * The application owner as the ERP sees them: id, username and whether they
 * hold the cash right. The API loads the owner with the store's rights only,
 * so the till right is read here, once per request.
 *
 * @return array ['id' => int, 'username' => string, 'cash' => bool]
 */
function erp_api_owner()
{
    static $owner = null;

    if ($owner === null) {
        $app = api_current_app();
        $id = (int) ($app['owner']['id'] ?? 0);
        $role = (int) ($app['owner']['role'] ?? 9);

        $cash = ($role < 3);

        if (!$cash && ($id > 0)) {
            $cash = ((int) db_value("SELECT manage_erp_cash FROM user WHERE user_id = '" . $id . "' LIMIT 1") === 1);
        }

        $owner = array(
            'id' => $id,
            'username' => (string) ($app['owner']['username'] ?? ''),
            'cash' => $cash,
        );
    }

    return $owner;
}

/**
 * The line the panel's own screens leave in the activity log for a write.
 *
 * @param string $what  Translated sentence
 */
function erp_api_log($what)
{
    $app = api_current_app();

    if (function_exists('log_activity')) {
        log_activity($what . ' (' . lang('API') . ': ' . (string) ($app['name'] ?? '') . ', ' . (string) ($app['api_key'] ?? '') . ')',
            (string) ($app['owner']['username'] ?? ''));
    }
}

/* ---------------------------------------------------------------------------
   Accounts
   --------------------------------------------------------------------------- */

function erp_api_account_select()
{
    return "SELECT a.*, a.updated_at AS _sort FROM erp_accounts a";
}

function erp_api_account_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'kind' => (string) $row['kind'],
        'title' => (string) $row['title'],
        'is_person' => ((int) $row['is_person'] === 1),
        'tax_number' => (string) $row['tax_number'],
        'tax_office' => (string) $row['tax_office'],
        'email' => (string) $row['email'],
        'phone' => (string) $row['phone'],
        'address' => array(
            'line_1' => (string) $row['address'],
            'district' => (string) $row['district'],
            'city' => (string) $row['city'],
            'postcode' => (string) $row['postcode'],
            'country' => (string) $row['country_code'],
        ),
        'currency' => (string) $row['currency'],
        'balance' => api_money($row['balance']),
        'balance_fc' => api_money($row['balance_fc']),
        'contact_id' => (int) $row['contact_id'],
        'payment_days' => (int) ($row['payment_days'] ?? 0),
        'status' => (string) $row['status'],
        'notes' => (string) $row['notes'],
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What erp_api_account_present() returns, declared for the OpenAPI document.
function erp_api_account_schema()
{
    return array(
        'id' => 'integer',
        'kind' => 'string',
        'title' => 'string',
        'is_person' => 'boolean',
        'tax_number' => 'string',
        'tax_office' => 'string',
        'email' => 'string',
        'phone' => 'string',
        'address' => array(
            'line_1' => 'string',
            'district' => 'string',
            'city' => 'string',
            'postcode' => 'string',
            'country' => 'string',
        ),
        'currency' => 'string',
        'balance' => 'integer',
        'balance_fc' => 'integer',
        'contact_id' => 'integer',
        'payment_days' => 'integer',
        'status' => 'string',
        'notes' => 'string',
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function erp_api_accounts_list($params)
{
    $where = array();

    if (isset($params['search']) && ($params['search'] !== '')) {
        $search = escape(escape_like($params['search']));
        $where[] = "(a.title LIKE '%" . $search . "%' OR a.tax_number LIKE '%" . $search . "%' OR a.email LIKE '%" . $search . "%')";
    }

    if (isset($params['kind'])) {
        $where[] = ($params['kind'] === 'both')
            ? "a.kind = 'both'"
            : "(a.kind = '" . escape($params['kind']) . "' OR a.kind = 'both')";
    }

    if (isset($params['status'])) {
        $where[] = "a.status = '" . escape($params['status']) . "'";
    }

    if (isset($params['tax_number']) && ($params['tax_number'] !== '')) {
        $where[] = "a.tax_number = '" . escape($params['tax_number']) . "'";
    }

    if (isset($params['contact_id'])) {
        $where[] = "a.contact_id = '" . (int) $params['contact_id'] . "'";
    }

    if (!empty($params['with_balance'])) {
        $where[] = "(a.balance <> 0 OR a.balance_fc <> 0)";
    }

    if (isset($params['updated_since'])) {
        $where[] = "a.updated_at >= '" . (int) $params['updated_since'] . "'";
    }

    $page = erp_api_page(erp_api_account_select(), $where, 'a.updated_at', 'a.id', $params, "FROM erp_accounts a");

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_account_present($row);
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_accounts_get($params)
{
    $row = db_item(erp_api_account_select() . " WHERE a.id = '" . (int) $params['id'] . "' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Account'));
    }

    api_ok(erp_api_account_present($row));
}

function erp_api_account_transaction_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'account_id' => (int) $row['account_id'],
        'date' => erp_api_date($row['doc_date']),
        'kind' => (string) $row['kind'],
        'direction' => (string) $row['direction'],
        'amount' => api_money($row['amount']),
        'currency' => (string) $row['currency'],
        'exchange_rate' => (float) $row['exchange_rate'],
        'amount_base' => api_money($row['amount_base']),
        'doc_type' => (string) $row['doc_type'],
        'doc_id' => (int) $row['doc_id'],
        'description' => (string) $row['description'],
        'created_at' => api_time($row['created_at']),
    );
}

// What erp_api_account_transaction_present() returns.
function erp_api_account_transaction_schema()
{
    return array(
        'id' => 'integer',
        'account_id' => 'integer',
        'date' => 'string?',
        'kind' => 'string',
        'direction' => 'string',
        'amount' => 'integer',
        'currency' => 'string',
        'exchange_rate' => 'number',
        'amount_base' => 'integer',
        'doc_type' => 'string',
        'doc_id' => 'integer',
        'description' => 'string',
        'created_at' => 'string?',
    );
}

function erp_api_accounts_transactions($params)
{
    $account_id = (int) $params['id'];

    if (!is_array(erp_account($account_id))) {
        api_fail_not_found(lang('Account'));
    }

    $where = array("t.account_id = '" . $account_id . "'");

    if (isset($params['from'])) {
        $where[] = "t.doc_date >= '" . escape(erp_api_day($params['from'])) . "'";
    }

    if (isset($params['to'])) {
        $where[] = "t.doc_date <= '" . escape(erp_api_day($params['to'])) . "'";
    }

    if (isset($params['kind'])) {
        $where[] = "t.kind = '" . escape($params['kind']) . "'";
    }

    // The statement order is the day, then the row: the same order the panel
    // prints, so a running balance summed from the pages matches the screen.
    $page = erp_api_page("SELECT t.*, UNIX_TIMESTAMP(t.doc_date) AS _sort FROM erp_account_transactions t",
        $where, 'UNIX_TIMESTAMP(t.doc_date)', 't.id', $params, "FROM erp_account_transactions t");

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_account_transaction_present($row);
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_accounts_create($params)
{
    $owner = erp_api_owner();

    $tax_number = trim((string) ($params['tax_number'] ?? ''));

    if (($tax_number !== '') && !preg_match('/^[0-9]{10,11}$/', $tax_number)) {
        api_fail_validation(lang('The tax number is 10 digits, the identity number 11.'), 'tax_number');
    }

    if ($tax_number !== '') {
        $existing = (int) db_value("SELECT id FROM erp_accounts
            WHERE tax_number = '" . escape($tax_number) . "' AND status = 'active' ORDER BY id ASC LIMIT 1");

        if ($existing > 0) {
            api_fail_validation(lang(array(
                'string' => 'An account with this tax number already exists (#{var:1}).',
                'vars' => $existing,
            )), 'tax_number');
        }
    }

    if (isset($params['email']) && ($params['email'] !== '') && function_exists('validate_email_address') && !validate_email_address($params['email'])) {
        api_fail_validation(lang('That is not an e-mail address.'), 'email');
    }

    $currency = strtoupper(trim((string) ($params['currency'] ?? '')));

    if ($currency === '') {
        $currency = erp_base_currency();
    }

    if (($currency !== erp_base_currency()) && (!erp_fx_enabled() || !erp_fx_currency_allowed($currency))) {
        api_fail_validation(lang('That currency is not enabled for the ERP.'), 'currency');
    }

    $contact_id = (int) ($params['contact_id'] ?? 0);

    if ($contact_id > 0) {
        if ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $contact_id . "'") === 0) {
            api_fail_validation(lang('That customer could not be found.'), 'contact_id');
        }

        $taken = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' ORDER BY id ASC LIMIT 1");

        if ($taken > 0) {
            api_fail_validation(lang(array(
                'string' => 'That customer is already linked to account #{var:1}.',
                'vars' => $taken,
            )), 'contact_id');
        }
    }

    $data = array(
        'id' => 0,
        'title' => (string) $params['title'],
        'kind' => (string) ($params['kind'] ?? 'customer'),
        'is_person' => array_key_exists('is_person', $params) ? !empty($params['is_person']) : true,
        'tax_number' => $tax_number,
        'tax_office' => (string) ($params['tax_office'] ?? ''),
        'email' => (string) ($params['email'] ?? ''),
        'phone' => (string) ($params['phone'] ?? ''),
        'address' => (string) ($params['address'] ?? ''),
        'district' => (string) ($params['district'] ?? ''),
        'city' => (string) ($params['city'] ?? ''),
        'postcode' => (string) ($params['postcode'] ?? ''),
        'currency' => $currency,
        'payment_days' => (int) ($params['payment_days'] ?? 0),
        'notes' => (string) ($params['notes'] ?? ''),
        'created_by' => $owner['id'],
    );

    if (isset($params['country_code']) && (trim((string) $params['country_code']) !== '')) {
        $data['country_code'] = strtoupper(trim((string) $params['country_code']));
    }

    if ($contact_id > 0) {
        $data['contact_id'] = $contact_id;
    }

    $saved = erp_account_save($data);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error']);
    }

    $account_id = (int) $saved['id'];

    if ($contact_id > 0) {
        // Both sides of the link, the way the account form writes it.
        erp_account_link_contact($account_id, $contact_id);
    }

    $opening = (int) ($params['opening_balance'] ?? 0);

    if ($opening !== 0) {
        $opened = erp_account_open(array(
            'account_id' => $account_id,
            'amount' => $opening,
            'doc_date' => isset($params['opening_date']) ? erp_api_day($params['opening_date']) : date('Y-m-d'),
            'currency' => $currency,
            'exchange_rate' => 1,
            'created_by' => $owner['id'],
        ));

        if (empty($opened['success'])) {
            // The card is open; the opening entry is what failed. Said plainly
            // rather than answered 201 as if everything went in.
            api_fail_validation((string) $opened['error'], 'opening_balance');
        }
    }

    erp_api_log(lang(array(
        'string' => 'ERP account #{var:1} ({var:2}) was opened through the API.',
        'vars' => array($account_id, $data['title']),
    )));

    $row = db_item(erp_api_account_select() . " WHERE a.id = '" . $account_id . "' LIMIT 1");

    api_ok(erp_api_account_present($row), 201);
}

/**
 * The card's own columns in the shape erp_account_save() takes, so a partial
 * update can be laid over them: the save writes every column it knows, and a
 * field the caller did not send must keep its value rather than fall to the
 * default.
 *
 * @param array $account  Row from erp_account()
 * @return array
 */
function erp_api_account_save_data($account)
{
    $data = array(
        'id' => (int) $account['id'],
        'title' => (string) $account['title'],
        'kind' => (string) $account['kind'],
        'is_person' => ((int) $account['is_person'] === 1),
        'tax_number' => (string) $account['tax_number'],
        'tax_office' => (string) $account['tax_office'],
        'email' => (string) $account['email'],
        'phone' => (string) $account['phone'],
        'address' => (string) $account['address'],
        'district' => (string) $account['district'],
        'city' => (string) $account['city'],
        'country_code' => (string) $account['country_code'],
        'postcode' => (string) $account['postcode'],
        'currency' => (string) $account['currency'],
        'status' => (string) $account['status'],
        'notes' => (string) $account['notes'],
        'payment_days' => (int) ($account['payment_days'] ?? 0),
    );

    if (array_key_exists('overdue_notify_days', $account)) {
        $data['overdue_notify_days'] = (int) $account['overdue_notify_days'];
    }

    if (array_key_exists('overdue_notify_customer', $account)) {
        $data['overdue_notify_customer'] = ((int) $account['overdue_notify_customer'] === 1);
    }

    return $data;
}

function erp_api_accounts_update($params)
{
    $account_id = (int) $params['id'];
    $account = erp_account($account_id);

    if (!is_array($account)) {
        api_fail_not_found(lang('Account'));
    }

    $owner = erp_api_owner();

    $data = erp_api_account_save_data($account);

    $writable = array('title', 'kind', 'is_person', 'tax_number', 'tax_office', 'email', 'phone',
        'address', 'district', 'city', 'country_code', 'postcode', 'payment_days', 'status', 'notes');

    $changed = array();

    foreach ($writable as $name) {
        if (!array_key_exists($name, $params)) {
            continue;
        }

        $value = $params[$name];

        if (in_array($name, array('title', 'tax_number', 'tax_office', 'email', 'phone', 'address', 'district', 'city', 'country_code', 'postcode', 'notes'), true)) {
            $value = trim((string) $value);
        }

        if ($name === 'country_code') {
            $value = strtoupper($value);
        }

        $data[$name] = $value;
        $changed[] = $name;
    }

    $links_contact = array_key_exists('contact_id', $params);
    $contact_id = $links_contact ? (int) $params['contact_id'] : (int) $account['contact_id'];

    if ($links_contact) {
        $changed[] = 'contact_id';
    }

    if (empty($changed)) {
        api_fail_validation(lang('No writable field was sent.'));
    }

    if (trim((string) $data['title']) === '') {
        api_fail_validation(lang('Enter a name.'), 'title');
    }

    if (($data['tax_number'] !== '') && !preg_match('/^[0-9]{10,11}$/', $data['tax_number'])) {
        api_fail_validation(lang('The tax number is 10 digits, the identity number 11.'), 'tax_number');
    }

    if (in_array('tax_number', $changed, true) && ($data['tax_number'] !== '')) {
        $existing = (int) db_value("SELECT id FROM erp_accounts
            WHERE tax_number = '" . escape($data['tax_number']) . "' AND status = 'active' AND id <> '" . $account_id . "'
            ORDER BY id ASC LIMIT 1");

        if ($existing > 0) {
            api_fail_validation(lang(array(
                'string' => 'An account with this tax number already exists (#{var:1}).',
                'vars' => $existing,
            )), 'tax_number');
        }
    }

    if (($data['email'] !== '') && function_exists('validate_email_address') && !validate_email_address($data['email'])) {
        api_fail_validation(lang('That is not an e-mail address.'), 'email');
    }

    if ($links_contact && ($contact_id > 0) && ($contact_id !== (int) $account['contact_id'])) {
        if ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $contact_id . "'") === 0) {
            api_fail_validation(lang('That customer could not be found.'), 'contact_id');
        }

        $taken = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' AND id <> '" . $account_id . "' ORDER BY id ASC LIMIT 1");

        if ($taken > 0) {
            api_fail_validation(lang(array(
                'string' => 'That customer is already linked to account #{var:1}.',
                'vars' => $taken,
            )), 'contact_id');
        }
    }

    // The link first, so a refused link changes nothing on the card.
    if ($links_contact && ($contact_id !== (int) $account['contact_id'])) {
        $linked = erp_account_link_contact($account_id, $contact_id);

        if (empty($linked['success'])) {
            api_fail_validation((string) $linked['error'], 'contact_id');
        }
    }

    $saved = erp_account_save($data);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP account #{var:1} ({var:2}) was changed through the API: {var:3}.',
        'vars' => array($account_id, $data['title'], implode(', ', $changed)),
    )));

    $row = db_item(erp_api_account_select() . " WHERE a.id = '" . $account_id . "' LIMIT 1");

    api_ok(erp_api_account_present($row));
}

function erp_api_accounts_reconciliation($params)
{
    $account_id = (int) $params['id'];

    if (!is_array(erp_account($account_id))) {
        api_fail_not_found(lang('Account'));
    }

    $options = erp_reconciliation_options(array(
        'as_of' => isset($params['as_of']) ? erp_api_day($params['as_of']) : '',
        'from' => isset($params['from']) ? erp_api_day($params['from']) : '',
        'reply_days' => $params['reply_days'] ?? 7,
    ));

    $pdf = erp_reconciliation_pdf($account_id, $options);

    if ($pdf === false) {
        api_fail(503, 'service_unavailable', lang('The PDF library is not installed.'));
    }

    api_ok(erp_api_document_present(erp_reconciliation_file_name($account_id, $options['as_of']) . '.pdf', 'application/pdf', $pdf));
}

/* ---------------------------------------------------------------------------
   Invoices
   --------------------------------------------------------------------------- */

function erp_api_invoice_select()
{
    return "SELECT i.*, i.updated_at AS _sort, a.title AS live_account_title
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON a.id = i.account_id";
}

/**
 * The lines of a page of invoices in one query, keyed by invoice.
 *
 * @param array $invoice_ids
 * @return array  invoice_id => lines
 */
function erp_api_invoice_lines($invoice_ids)
{
    $invoice_ids = array_values(array_filter(array_map('intval', (array) $invoice_ids)));

    if (empty($invoice_ids)) {
        return array();
    }

    $rows = (array) db_items("SELECT l.*, p.short_description AS product_title, p.name AS product_sku
        FROM erp_invoice_items l
        LEFT JOIN products p ON p.id = l.product_id
        WHERE l.invoice_id IN (" . implode(',', $invoice_ids) . ")
        ORDER BY l.invoice_id ASC, l.line_no ASC, l.id ASC");

    $lines = array();

    foreach ($rows as $row) {
        $lines[(int) $row['invoice_id']][] = erp_api_invoice_line_present($row);
    }

    return $lines;
}

function erp_api_invoice_line_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'line_no' => (int) $row['line_no'],
        'product_id' => (int) $row['product_id'],
        'sku' => (string) ($row['product_sku'] ?? ''),
        'description' => (string) $row['description'],
        'quantity' => (float) $row['quantity'],
        'unit' => (string) $row['unit_code'],
        'unit_price' => api_money($row['unit_price']),
        'discount_rate' => (float) $row['discount_rate'],
        'offer_id' => (int) ($row['offer_id'] ?? 0),
        'offer_discount_rate' => (float) ($row['offer_discount_rate'] ?? 0),
        'discount_amount' => api_money($row['discount_amount']),
        'tax_rate' => (float) $row['tax_rate'],
        'tax_total' => api_money($row['tax_total']),
        'vat_exemption_code' => (string) $row['vat_exemption_code'],
        'withholding_rate' => (float) $row['withholding_rate'],
        'withholding_code' => (string) $row['withholding_code'],
        'line_total' => api_money($row['line_total']),
        'returned_quantity' => (float) $row['returned_qty'],
    );
}

function erp_api_invoice_present($row, $lines)
{
    $open = erp_invoice_open_amount($row);

    return array(
        'id' => (int) $row['id'],
        'number' => (string) $row['full_number'],
        'direction' => (string) $row['direction'],
        'doc_type' => (string) $row['doc_type'],
        'invoice_type' => (string) $row['invoice_type'],
        'status' => (string) $row['status'],
        'account_id' => (int) $row['account_id'],
        'account_title' => (trim((string) ($row['account_title'] ?? '')) !== '') ? (string) $row['account_title'] : (string) ($row['live_account_title'] ?? ''),
        'account_tax_number' => (string) ($row['account_tax_number'] ?? ''),
        'order_id' => (int) $row['order_id'],
        'parent_invoice_id' => (int) $row['parent_invoice_id'],
        'supplier_invoice_no' => (string) $row['supplier_invoice_no'],
        'issue_date' => erp_api_date($row['issue_date']),
        'due_date' => erp_api_date($row['due_date']),
        'currency' => (string) $row['currency'],
        'exchange_rate' => (float) $row['exchange_rate'],
        'totals' => array(
            'subtotal' => api_money($row['subtotal']),
            'discount' => api_money($row['discount_total']),
            'shipping' => api_money($row['shipping_total']),
            'surcharge' => api_money($row['surcharge_total']),
            'gift_card' => api_money($row['gift_card_total']),
            'tax' => api_money($row['tax_total']),
            'withholding' => api_money($row['withholding_total']),
            'grand_total' => api_money($row['grand_total']),
            'grand_total_base' => api_money($row['grand_total_base'] ?? $row['grand_total']),
            'paid' => api_money($row['paid_total']),
            'open' => api_money($open),
        ),
        'is_internet_sale' => ((int) $row['is_internet_sale'] === 1),
        'payment_method' => (string) $row['payment_method'],
        'payment_date' => erp_api_date($row['payment_date']),
        'shipment_date' => erp_api_date($row['shipment_date']),
        'carrier' => array(
            'title' => (string) $row['carrier_title'],
            'tax_number' => (string) $row['carrier_vkn'],
        ),
        'edoc' => array(
            'kind' => (string) $row['edoc_kind'],
            'status' => (string) $row['edoc_status'],
            'gib_number' => (string) $row['gib_number'],
            'gib_uuid' => (string) $row['gib_uuid'],
        ),
        'notes' => (string) $row['notes'],
        'lines' => $lines,
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What erp_api_invoice_present() returns, declared for the OpenAPI document.
function erp_api_invoice_schema()
{
    return array(
        'id' => 'integer',
        'number' => 'string',
        'direction' => 'string',
        'doc_type' => 'string',
        'invoice_type' => 'string',
        'status' => 'string',
        'account_id' => 'integer',
        'account_title' => 'string',
        'account_tax_number' => 'string',
        'order_id' => 'integer',
        'parent_invoice_id' => 'integer',
        'supplier_invoice_no' => 'string',
        'issue_date' => 'string?',
        'due_date' => 'string?',
        'currency' => 'string',
        'exchange_rate' => 'number',
        'totals' => array(
            'subtotal' => 'integer',
            'discount' => 'integer',
            'shipping' => 'integer',
            'surcharge' => 'integer',
            'gift_card' => 'integer',
            'tax' => 'integer',
            'withholding' => 'integer',
            'grand_total' => 'integer',
            'grand_total_base' => 'integer',
            'paid' => 'integer',
            'open' => 'integer',
        ),
        'is_internet_sale' => 'boolean',
        'payment_method' => 'string',
        'payment_date' => 'string?',
        'shipment_date' => 'string?',
        'carrier' => array(
            'title' => 'string',
            'tax_number' => 'string',
        ),
        'edoc' => array(
            'kind' => 'string',
            'status' => 'string',
            'gib_number' => 'string',
            'gib_uuid' => 'string',
        ),
        'notes' => 'string',
        'lines' => array(array(
            'id' => 'integer',
            'line_no' => 'integer',
            'product_id' => 'integer',
            'sku' => 'string',
            'description' => 'string',
            'quantity' => 'number',
            'unit' => 'string',
            'unit_price' => 'integer',
            'discount_rate' => 'number',
            'offer_id' => 'integer',
            'offer_discount_rate' => 'number',
            'discount_amount' => 'integer',
            'tax_rate' => 'number',
            'tax_total' => 'integer',
            'vat_exemption_code' => 'string',
            'withholding_rate' => 'number',
            'withholding_code' => 'string',
            'line_total' => 'integer',
            'returned_quantity' => 'number',
        )),
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function erp_api_invoices_list($params)
{
    // A draft is not a document: it has no number and may still be thrown
    // away. The wire carries what was issued.
    $where = array("i.status <> 'draft'");

    if (isset($params['account_id'])) {
        $where[] = "i.account_id = '" . (int) $params['account_id'] . "'";
    }

    if (isset($params['order_id'])) {
        $where[] = "i.order_id = '" . (int) $params['order_id'] . "'";
    }

    if (isset($params['number']) && ($params['number'] !== '')) {
        $where[] = "i.full_number = '" . escape($params['number']) . "'";
    }

    if (isset($params['direction'])) {
        $where[] = "i.direction = '" . escape($params['direction']) . "'";
    }

    if (isset($params['doc_type'])) {
        $where[] = "i.doc_type = '" . escape($params['doc_type']) . "'";
    }

    if (isset($params['status'])) {
        $where[] = "i.status = '" . escape($params['status']) . "'";
    }

    if (!empty($params['open'])) {
        $where[] = "i.status IN ('issued', 'partially_paid') AND i.doc_type <> 'return'";
    }

    if (isset($params['issued_from'])) {
        $where[] = "i.issue_date >= '" . escape(erp_api_day($params['issued_from'])) . "'";
    }

    if (isset($params['issued_to'])) {
        $where[] = "i.issue_date <= '" . escape(erp_api_day($params['issued_to'])) . "'";
    }

    if (isset($params['updated_since'])) {
        $where[] = "i.updated_at >= '" . (int) $params['updated_since'] . "'";
    }

    $page = erp_api_page(erp_api_invoice_select(), $where, 'i.updated_at', 'i.id', $params, "FROM erp_invoices i");

    $ids = array();

    foreach ($page['rows'] as $row) {
        $ids[] = (int) $row['id'];
    }

    $lines = erp_api_invoice_lines($ids);

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_invoice_present($row, $lines[(int) $row['id']] ?? array());
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

/**
 * One issued invoice row, or a 404. A draft answers 404 too: it is not on the
 * wire until it is a document.
 *
 * @param int $invoice_id
 * @return array
 */
function erp_api_invoice_row($invoice_id)
{
    $row = db_item(erp_api_invoice_select() . " WHERE i.id = '" . (int) $invoice_id . "' AND i.status <> 'draft' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Invoice'));
    }

    return $row;
}

function erp_api_invoices_get($params)
{
    $row = erp_api_invoice_row((int) $params['id']);

    $lines = erp_api_invoice_lines(array((int) $row['id']));

    api_ok(erp_api_invoice_present($row, $lines[(int) $row['id']] ?? array()));
}

function erp_api_document_present($filename, $content_type, $content)
{
    return array(
        'filename' => (string) $filename,
        'content_type' => (string) $content_type,
        'size' => strlen($content),
        'content_base64' => base64_encode($content),
    );
}

// What erp_api_document_present() returns.
function erp_api_document_schema()
{
    return array(
        'filename' => 'string',
        'content_type' => 'string',
        'size' => 'integer',
        'content_base64' => 'string',
    );
}

function erp_api_invoices_document($params)
{
    $row = erp_api_invoice_row((int) $params['id']);

    $html = erp_invoice_html((int) $row['id']);

    if ($html === false) {
        api_fail_not_found(lang('Invoice'));
    }

    $pdf = erp_invoice_pdf($html);

    if ($pdf === false) {
        api_fail(503, 'service_unavailable', lang('The PDF library is not installed.'));
    }

    $file_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['full_number']);

    if (trim($file_name, '_') === '') {
        $file_name = 'invoice_' . (int) $row['id'];
    }

    api_ok(erp_api_document_present($file_name . '.pdf', 'application/pdf', $pdf));
}

/* ---------------------------------------------------------------------------
   Receipts and payments
   --------------------------------------------------------------------------- */

function erp_api_receipt_select()
{
    return "SELECT c.*, c.id AS _sort, t.name AS cash_account_name, a.title AS account_title,
            (SELECT l.id FROM erp_account_transactions l WHERE l.doc_type = c.doc_type AND l.doc_id = c.id LIMIT 1) AS ledger_id,
            (SELECT r.id FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = c.id LIMIT 1) AS reversal_id
        FROM erp_cash_transactions c
        LEFT JOIN erp_cash_accounts t ON t.id = c.cash_account_id
        LEFT JOIN erp_accounts a ON a.id = c.account_id";
}

/**
 * The invoices a page of receipts was allocated to, keyed by ledger row.
 *
 * @param array $ledger_ids
 * @return array  account_txn_id => [{invoice_id, invoice_number, amount}]
 */
function erp_api_receipt_settlements($ledger_ids)
{
    $ledger_ids = array_values(array_filter(array_map('intval', (array) $ledger_ids)));

    if (empty($ledger_ids)) {
        return array();
    }

    $rows = (array) db_items("SELECT s.account_txn_id, s.invoice_id, s.amount, i.full_number
        FROM erp_settlements s
        LEFT JOIN erp_invoices i ON i.id = s.invoice_id
        WHERE s.account_txn_id IN (" . implode(',', $ledger_ids) . ")
        ORDER BY s.id ASC");

    $out = array();

    foreach ($rows as $row) {
        $out[(int) $row['account_txn_id']][] = array(
            'invoice_id' => (int) $row['invoice_id'],
            'invoice_number' => (string) $row['full_number'],
            'amount' => api_money($row['amount']),
        );
    }

    return $out;
}

function erp_api_receipt_present($row, $settlements)
{
    return array(
        'id' => (int) $row['id'],
        'direction' => (string) $row['doc_type'],
        'date' => erp_api_date($row['doc_date']),
        'amount' => api_money($row['amount']),
        'currency' => (string) $row['currency'],
        'exchange_rate' => (float) $row['exchange_rate'],
        'amount_base' => api_money($row['amount_base']),
        'account_id' => (int) $row['account_id'],
        'account_title' => (string) ($row['account_title'] ?? ''),
        'cash_account_id' => (int) $row['cash_account_id'],
        'cash_account_name' => (string) ($row['cash_account_name'] ?? ''),
        'payment_method' => (string) $row['payment_method'],
        'description' => (string) $row['description'],
        'cancelled' => ((int) ($row['reversal_id'] ?? 0) > 0),
        'settlements' => $settlements,
        'created_at' => api_time($row['created_at']),
    );
}

// What erp_api_receipt_present() returns, declared for the OpenAPI document.
function erp_api_receipt_schema()
{
    return array(
        'id' => 'integer',
        'direction' => 'string',
        'date' => 'string?',
        'amount' => 'integer',
        'currency' => 'string',
        'exchange_rate' => 'number',
        'amount_base' => 'integer',
        'account_id' => 'integer',
        'account_title' => 'string',
        'cash_account_id' => 'integer',
        'cash_account_name' => 'string',
        'payment_method' => 'string',
        'description' => 'string',
        'cancelled' => 'boolean',
        'settlements' => array(array(
            'invoice_id' => 'integer',
            'invoice_number' => 'string',
            'amount' => 'integer',
        )),
        'created_at' => 'string?',
    );
}

function erp_api_receipts_list($params)
{
    $where = array("c.doc_type IN ('collection', 'payment')");

    if (isset($params['account_id'])) {
        $where[] = "c.account_id = '" . (int) $params['account_id'] . "'";
    }

    if (isset($params['cash_account_id'])) {
        $where[] = "c.cash_account_id = '" . (int) $params['cash_account_id'] . "'";
    }

    if (isset($params['direction'])) {
        $where[] = "c.doc_type = '" . escape($params['direction']) . "'";
    }

    if (isset($params['payment_method'])) {
        $where[] = "c.payment_method = '" . escape($params['payment_method']) . "'";
    }

    if (isset($params['from'])) {
        $where[] = "c.doc_date >= '" . escape(erp_api_day($params['from'])) . "'";
    }

    if (isset($params['to'])) {
        $where[] = "c.doc_date <= '" . escape(erp_api_day($params['to'])) . "'";
    }

    if (isset($params['created_since'])) {
        $where[] = "c.created_at >= '" . (int) $params['created_since'] . "'";
    }

    $page = erp_api_page(erp_api_receipt_select(), $where, '', 'c.id', $params, "FROM erp_cash_transactions c");

    $ledger_ids = array();

    foreach ($page['rows'] as $row) {
        $ledger_ids[] = (int) $row['ledger_id'];
    }

    $settlements = erp_api_receipt_settlements($ledger_ids);

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_receipt_present($row, $settlements[(int) $row['ledger_id']] ?? array());
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_receipt_row($cash_id)
{
    $row = db_item(erp_api_receipt_select() . " WHERE c.id = '" . (int) $cash_id . "' AND c.doc_type IN ('collection', 'payment') LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Receipt'));
    }

    return $row;
}

function erp_api_receipts_get($params)
{
    $row = erp_api_receipt_row((int) $params['id']);

    $settlements = erp_api_receipt_settlements(array((int) $row['ledger_id']));

    api_ok(erp_api_receipt_present($row, $settlements[(int) $row['ledger_id']] ?? array()));
}

function erp_api_receipts_create($params)
{
    $owner = erp_api_owner();

    // The panel keeps the till behind a right of its own; an application is
    // capped by what its owner holds, and the API's own ceiling does not know
    // this right, so it is read here.
    if (!$owner['cash']) {
        api_fail(403, 'forbidden', lang('The application owner does not hold the ERP cash right.'));
    }

    $account_id = (int) $params['account_id'];

    if (!is_array(erp_account($account_id))) {
        api_fail_validation(lang('That account could not be found.'), 'account_id');
    }

    $till = db_item("SELECT id, is_active FROM erp_cash_accounts WHERE id = '" . (int) $params['cash_account_id'] . "' LIMIT 1");

    if (!is_array($till)) {
        api_fail_validation(lang('That till or bank account could not be found.'), 'cash_account_id');
    }

    if ((int) $till['is_active'] !== 1) {
        api_fail_validation(lang('That till or bank account is not active.'), 'cash_account_id');
    }

    if ((int) $params['amount'] <= 0) {
        api_fail_validation(lang('Enter an amount greater than zero.'), 'amount');
    }

    $direction = (string) ($params['direction'] ?? 'collection');

    $invoice_id = (int) ($params['invoice_id'] ?? 0);

    if ($invoice_id > 0) {
        $invoice = db_item("SELECT id, account_id, status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

        if (!is_array($invoice) || ((string) $invoice['status'] === 'draft')) {
            api_fail_validation(lang('That invoice could not be found.'), 'invoice_id');
        }

        if ((int) $invoice['account_id'] !== $account_id) {
            api_fail_validation(lang('That invoice does not belong to this account.'), 'invoice_id');
        }
    }

    $data = array(
        'direction' => $direction,
        'account_id' => $account_id,
        'cash_account_id' => (int) $params['cash_account_id'],
        'amount' => (int) $params['amount'],
        'doc_date' => isset($params['date']) ? erp_api_day($params['date']) : date('Y-m-d'),
        'payment_method' => (string) ($params['payment_method'] ?? 'transfer'),
        'description' => mb_substr((string) ($params['description'] ?? ''), 0, 255),
        'invoice_id' => $invoice_id,
        'created_by' => $owner['id'],
    );

    if (isset($params['currency']) && (trim((string) $params['currency']) !== '')) {
        $data['currency'] = strtoupper(trim((string) $params['currency']));
        $data['exchange_rate'] = (float) ($params['exchange_rate'] ?? 0);
        $data['exchange_rate_source'] = 'api';
    }

    $posted = erp_post_receipt($data);

    if (empty($posted['success'])) {
        // The receipt function names what it refused; the field is the one it
        // most likely concerns, so a client can point at it.
        api_fail_validation((string) $posted['error'], ($invoice_id > 0) ? 'invoice_id' : 'amount');
    }

    $cash_id = (int) $posted['cash_id'];

    erp_api_log(lang(array(
        'string' => 'ERP receipt #{var:1} for account #{var:2} was recorded through the API.',
        'vars' => array($cash_id, $account_id),
    )));

    $row = erp_api_receipt_row($cash_id);

    $settlements = erp_api_receipt_settlements(array((int) $row['ledger_id']));

    api_ok(erp_api_receipt_present($row, $settlements[(int) $row['ledger_id']] ?? array()), 201);
}

function erp_api_receipts_cancel($params)
{
    $owner = erp_api_owner();

    if (!$owner['cash']) {
        api_fail(403, 'forbidden', lang('The application owner does not hold the ERP cash right.'));
    }

    $row = erp_api_receipt_row((int) $params['id']);

    if ((int) ($row['reversal_id'] ?? 0) > 0) {
        api_fail_validation(lang('That receipt has already been cancelled.'), 'id');
    }

    $reason = trim((string) ($params['reason'] ?? ''));

    if ($reason === '') {
        api_fail_validation(lang('The reason is required.'), 'reason');
    }

    $cancelled = erp_receipt_cancel((int) $row['id'], $reason, $owner['id']);

    if (empty($cancelled['success'])) {
        api_fail_validation((string) $cancelled['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP receipt #{var:1} was cancelled through the API: {var:2}',
        'vars' => array((int) $row['id'], $reason),
    )));

    $row = erp_api_receipt_row((int) $row['id']);

    $settlements = erp_api_receipt_settlements(array((int) $row['ledger_id']));

    api_ok(erp_api_receipt_present($row, $settlements[(int) $row['ledger_id']] ?? array()));
}

/* ---------------------------------------------------------------------------
   Tills
   --------------------------------------------------------------------------- */

function erp_api_cash_account_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'kind' => (string) $row['kind'],
        'currency' => (string) $row['currency'],
        'bank_name' => (string) $row['bank_name'],
        'iban' => (string) $row['iban'],
        'balance' => api_money($row['balance']),
        'is_active' => ((int) $row['is_active'] === 1),
    );
}

// What erp_api_cash_account_present() returns.
function erp_api_cash_account_schema()
{
    return array(
        'id' => 'integer',
        'name' => 'string',
        'kind' => 'string',
        'currency' => 'string',
        'bank_name' => 'string',
        'iban' => 'string',
        'balance' => 'integer',
        'is_active' => 'boolean',
    );
}

function erp_api_cash_accounts_list($params)
{
    $where = array();

    if (isset($params['active'])) {
        $where[] = "is_active = '" . (!empty($params['active']) ? 1 : 0) . "'";
    }

    $rows = (array) db_items("SELECT * FROM erp_cash_accounts"
        . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where))
        . " ORDER BY sort_order ASC, id ASC");

    $out = array();

    foreach ($rows as $row) {
        $out[] = erp_api_cash_account_present($row);
    }

    // A store has a handful of tills, never a page of them.
    api_ok_list($out, max(1, count($out)), null, count($out));
}

/* ---------------------------------------------------------------------------
   Delivery notes
   --------------------------------------------------------------------------- */

function erp_api_waybill_select()
{
    return "SELECT w.*, w.updated_at AS _sort, a.title AS account_title, o.order_number, i.full_number AS invoice_number
        FROM erp_waybills w
        LEFT JOIN erp_accounts a ON a.id = w.account_id
        LEFT JOIN orders o ON o.id = w.order_id
        LEFT JOIN erp_invoices i ON i.id = w.invoice_id";
}

function erp_api_waybill_lines($waybill_ids)
{
    $waybill_ids = array_values(array_filter(array_map('intval', (array) $waybill_ids)));

    if (empty($waybill_ids)) {
        return array();
    }

    $rows = (array) db_items("SELECT l.*, p.name AS product_sku
        FROM erp_waybill_items l
        LEFT JOIN products p ON p.id = l.product_id
        WHERE l.waybill_id IN (" . implode(',', $waybill_ids) . ")
        ORDER BY l.waybill_id ASC, l.line_no ASC, l.id ASC");

    $lines = array();

    foreach ($rows as $row) {
        $lines[(int) $row['waybill_id']][] = array(
            'id' => (int) $row['id'],
            'line_no' => (int) $row['line_no'],
            'product_id' => (int) $row['product_id'],
            'sku' => (string) ($row['product_sku'] ?? ''),
            'description' => (string) $row['description'],
            'quantity' => (float) $row['quantity'],
            'unit' => (string) $row['unit_code'],
        );
    }

    return $lines;
}

function erp_api_waybill_present($row, $lines)
{
    // The driver's identity number stays off the wire: it is on the printed
    // note because the road wants it there, not because an integration does.
    return array(
        'id' => (int) $row['id'],
        'number' => (string) $row['full_number'],
        'status' => (string) $row['status'],
        'account_id' => (int) $row['account_id'],
        'account_title' => (string) ($row['account_title'] ?? ''),
        'order_id' => (int) $row['order_id'],
        'order_number' => (string) ($row['order_number'] ?? ''),
        'invoice_id' => (int) $row['invoice_id'],
        'invoice_number' => (string) ($row['invoice_number'] ?? ''),
        'issue_date' => erp_api_date($row['issue_date']),
        'ship_date' => erp_api_date($row['ship_date']),
        'ship_time' => ((string) $row['ship_time'] === '00:00:00') ? null : substr((string) $row['ship_time'], 0, 5),
        'carrier' => array(
            'title' => (string) $row['carrier_title'],
            'tax_number' => (string) $row['carrier_vkn'],
            'plate' => (string) $row['plate'],
            'driver_name' => (string) $row['driver_name'],
        ),
        'ship_to' => array(
            'title' => (string) $row['ship_to_title'],
            'line_1' => (string) $row['ship_to_address'],
            'district' => (string) $row['ship_to_district'],
            'city' => (string) $row['ship_to_city'],
            'country' => (string) $row['ship_to_country'],
        ),
        'notes' => (string) $row['notes'],
        'lines' => $lines,
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What erp_api_waybill_present() returns, declared for the OpenAPI document.
function erp_api_waybill_schema()
{
    return array(
        'id' => 'integer',
        'number' => 'string',
        'status' => 'string',
        'account_id' => 'integer',
        'account_title' => 'string',
        'order_id' => 'integer',
        'order_number' => 'string',
        'invoice_id' => 'integer',
        'invoice_number' => 'string',
        'issue_date' => 'string?',
        'ship_date' => 'string?',
        'ship_time' => 'string?',
        'carrier' => array(
            'title' => 'string',
            'tax_number' => 'string',
            'plate' => 'string',
            'driver_name' => 'string',
        ),
        'ship_to' => array(
            'title' => 'string',
            'line_1' => 'string',
            'district' => 'string',
            'city' => 'string',
            'country' => 'string',
        ),
        'notes' => 'string',
        'lines' => array(array(
            'id' => 'integer',
            'line_no' => 'integer',
            'product_id' => 'integer',
            'sku' => 'string',
            'description' => 'string',
            'quantity' => 'number',
            'unit' => 'string',
        )),
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function erp_api_waybills_list($params)
{
    $where = array("w.status <> 'draft'");

    if (isset($params['account_id'])) {
        $where[] = "w.account_id = '" . (int) $params['account_id'] . "'";
    }

    if (isset($params['order_id'])) {
        $where[] = "w.order_id = '" . (int) $params['order_id'] . "'";
    }

    if (isset($params['invoice_id'])) {
        $where[] = "w.invoice_id = '" . (int) $params['invoice_id'] . "'";
    }

    if (isset($params['status'])) {
        $where[] = "w.status = '" . escape($params['status']) . "'";
    }

    if (isset($params['shipped_from'])) {
        $where[] = "w.ship_date >= '" . escape(erp_api_day($params['shipped_from'])) . "'";
    }

    if (isset($params['shipped_to'])) {
        $where[] = "w.ship_date <= '" . escape(erp_api_day($params['shipped_to'])) . "'";
    }

    if (isset($params['updated_since'])) {
        $where[] = "w.updated_at >= '" . (int) $params['updated_since'] . "'";
    }

    $page = erp_api_page(erp_api_waybill_select(), $where, 'w.updated_at', 'w.id', $params, "FROM erp_waybills w");

    $ids = array();

    foreach ($page['rows'] as $row) {
        $ids[] = (int) $row['id'];
    }

    $lines = erp_api_waybill_lines($ids);

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_waybill_present($row, $lines[(int) $row['id']] ?? array());
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_waybills_get($params)
{
    $row = db_item(erp_api_waybill_select() . " WHERE w.id = '" . (int) $params['id'] . "' AND w.status <> 'draft' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Delivery Note'));
    }

    $lines = erp_api_waybill_lines(array((int) $row['id']));

    api_ok(erp_api_waybill_present($row, $lines[(int) $row['id']] ?? array()));
}

function erp_api_waybills_document($params)
{
    $row = db_item("SELECT id, full_number FROM erp_waybills WHERE id = '" . (int) $params['id'] . "' AND status <> 'draft' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Delivery Note'));
    }

    $html = erp_waybill_html((int) $row['id']);

    if ($html === false) {
        api_fail_not_found(lang('Delivery Note'));
    }

    $pdf = erp_invoice_pdf($html);

    if ($pdf === false) {
        api_fail(503, 'service_unavailable', lang('The PDF library is not installed.'));
    }

    $file_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['full_number']);

    if (trim($file_name, '_') === '') {
        $file_name = 'waybill_' . (int) $row['id'];
    }

    api_ok(erp_api_document_present($file_name . '.pdf', 'application/pdf', $pdf));
}

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
            'state' => (string) ($row['state'] ?? ''),
            'postcode' => (string) $row['postcode'],
            'country' => (string) $row['country_code'],
        ),
        'currency' => (string) $row['currency'],
        'balance' => api_money($row['balance']),
        'balance_fc' => api_money($row['balance_fc']),
        'contact_id' => (int) $row['contact_id'],
        'payment_days' => (int) ($row['payment_days'] ?? 0),
        'credit_limit' => api_money($row['credit_limit'] ?? 0),
        'invoice_email' => (string) ($row['invoice_email'] ?? ''),
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
            'state' => 'string',
            'postcode' => 'string',
            'country' => 'string',
        ),
        'currency' => 'string',
        'balance' => 'integer',
        'balance_fc' => 'integer',
        'contact_id' => 'integer',
        'payment_days' => 'integer',
        'credit_limit' => 'integer',
        'invoice_email' => 'string',
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

    // Checked by the rules of the account's country (erp_tax_number_check()):
    // a Turkish VKN/TCKN by its digits, any other number as written.
    $tax = erp_tax_number_check((string) ($params['tax_number'] ?? ''), (string) ($params['country_code'] ?? ''));

    if ($tax['error'] !== '') {
        api_fail_validation($tax['error'], 'tax_number');
    }

    $tax_number = $tax['value'];

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
        'state' => (string) ($params['state'] ?? ''),
        'postcode' => (string) ($params['postcode'] ?? ''),
        'currency' => $currency,
        'payment_days' => (int) ($params['payment_days'] ?? 0),
        'notes' => (string) ($params['notes'] ?? ''),
        'created_by' => $owner['id'],
    );

    if (isset($params['credit_limit'])) {
        $data['credit_limit'] = max(0, (int) $params['credit_limit']);
    }

    if (isset($params['invoice_email']) && ($params['invoice_email'] !== '')) {
        if (!filter_var((string) $params['invoice_email'], FILTER_VALIDATE_EMAIL)) {
            api_fail_validation(lang('That is not an e-mail address.'), 'invoice_email');
        }

        $data['invoice_email'] = (string) $params['invoice_email'];
    }

    if (isset($params['country_code']) && (trim((string) $params['country_code']) !== '')) {
        $data['country_code'] = strtoupper(trim((string) $params['country_code']));
    }

    $data = erp_api_account_state_rule($data);

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
 * A Turkish address has no state: the province is the city (il), the way the
 * account form and the CSV import keep it. A state sent for one fills an
 * empty city and is not stored, so a card opened through the API reads like
 * one opened in the panel - on documents and in the tax zone lookup alike.
 *
 * @param array $data  erp_account_save() input; country_code may be absent
 * @return array
 */
function erp_api_account_state_rule($data)
{
    if (erp_account_country((string) ($data['country_code'] ?? '')) !== 'TR') {
        return $data;
    }

    $state = trim((string) ($data['state'] ?? ''));

    if ((trim((string) ($data['city'] ?? '')) === '') && ($state !== '')) {
        $data['city'] = $state;
    }

    $data['state'] = '';

    return $data;
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
        'state' => (string) ($account['state'] ?? ''),
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
        'address', 'district', 'city', 'state', 'country_code', 'postcode', 'payment_days', 'status', 'notes',
        'credit_limit', 'invoice_email');

    $changed = array();

    foreach ($writable as $name) {
        if (!array_key_exists($name, $params)) {
            continue;
        }

        $value = $params[$name];

        if (in_array($name, array('title', 'tax_number', 'tax_office', 'email', 'phone', 'address', 'district', 'city', 'state', 'country_code', 'postcode', 'notes', 'invoice_email'), true)) {
            $value = trim((string) $value);
        }

        if ($name === 'credit_limit') {
            $value = max(0, (int) $value);
        }

        if (($name === 'invoice_email') && ($value !== '') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            api_fail_validation(lang('That is not an e-mail address.'), 'invoice_email');
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

    $tax = erp_tax_number_check((string) $data['tax_number'], (string) ($data['country_code'] ?? ''));

    if ($tax['error'] !== '') {
        api_fail_validation($tax['error'], 'tax_number');
    }

    $data['tax_number'] = $tax['value'];

    $data = erp_api_account_state_rule($data);

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
        'tax2_rate' => (float) ($row['tax2_rate'] ?? 0),
        'tax2_amount' => api_money($row['tax2_amount'] ?? 0),
        'vat_exemption_code' => (string) $row['vat_exemption_code'],
        'withholding_rate' => (float) $row['withholding_rate'],
        'withholding_code' => (string) $row['withholding_code'],
        'withholding_amount' => api_money($row['withholding_amount'] ?? 0),
        'line_total' => api_money($row['line_total']),
        'returned_quantity' => (float) $row['returned_qty'],
    );
}

/**
 * The document type in plain English beside GİB's code (invoice_type): a
 * client outside Turkey reads sale / return / withholding rather than SATIS /
 * IADE / TEVKIFAT. A code without an English name comes through lowercased.
 *
 * @param string $code
 * @return string
 */
function erp_api_invoice_type_code($code)
{
    $map = array(
        'SATIS' => 'sale',
        'IADE' => 'return',
        'TEVKIFAT' => 'withholding',
        'ISTISNA' => 'exempt',
        'OZELMATRAH' => 'special_base',
        'IHRACAT' => 'export',
        'IHRACKAYITLI' => 'export_registered',
    );
    $code = strtoupper(trim((string) $code));

    return isset($map[$code]) ? $map[$code] : strtolower($code);
}

/**
 * The payment method in plain English beside the e-archive code
 * (payment_method): card, transfer, cash_on_delivery, intermediary, other.
 *
 * @param string $code
 * @return string
 */
function erp_api_payment_method_code($code)
{
    $map = array(
        'KREDIKARTI/BANKAKARTI' => 'card',
        'EFT/HAVALE' => 'transfer',
        'KAPIDAODEME' => 'cash_on_delivery',
        'ODEMEARACISI' => 'intermediary',
        'DIGER' => 'other',
    );
    $code = strtoupper(trim((string) $code));

    return isset($map[$code]) ? $map[$code] : strtolower($code);
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
        'invoice_type_code' => erp_api_invoice_type_code($row['invoice_type']),
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
            'tax2' => api_money($row['tax2_total'] ?? 0),
            'withholding' => api_money($row['withholding_total']),
            'grand_total' => api_money($row['grand_total']),
            'grand_total_base' => api_money($row['grand_total_base'] ?? $row['grand_total']),
            'paid' => api_money($row['paid_total']),
            'open' => api_money($open),
        ),
        'is_internet_sale' => ((int) $row['is_internet_sale'] === 1),
        'payment_method' => (string) $row['payment_method'],
        'payment_method_code' => erp_api_payment_method_code($row['payment_method']),
        'payment_date' => erp_api_date($row['payment_date']),
        'shipment_date' => erp_api_date($row['shipment_date']),
        'carrier' => array(
            'title' => (string) $row['carrier_title'],
            'tax_number' => (string) $row['carrier_vkn'],
        ),
        'edoc' => array(
            'kind' => (string) $row['edoc_kind'],
            'status' => (string) $row['edoc_status'],
            'provider' => (string) ($row['edoc_provider'] ?? ''),
            'external_id' => (string) ($row['edoc_external_id'] ?? ''),
            'gib_number' => (string) $row['gib_number'],
            'gib_uuid' => (string) $row['gib_uuid'],
            'sent_at' => api_time($row['edoc_sent_at'] ?? 0),
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
        'invoice_type_code' => 'string',
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
            'tax2' => 'integer',
            'withholding' => 'integer',
            'grand_total' => 'integer',
            'grand_total_base' => 'integer',
            'paid' => 'integer',
            'open' => 'integer',
        ),
        'is_internet_sale' => 'boolean',
        'payment_method' => 'string',
        'payment_method_code' => 'string',
        'payment_date' => 'string?',
        'shipment_date' => 'string?',
        'carrier' => array(
            'title' => 'string',
            'tax_number' => 'string',
        ),
        'edoc' => array(
            'kind' => 'string',
            'status' => 'string',
            'provider' => 'string',
            'external_id' => 'string',
            'gib_number' => 'string',
            'gib_uuid' => 'string',
            'sent_at' => 'datetime|null',
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
            'tax2_rate' => 'number',
            'tax2_amount' => 'integer',
            'vat_exemption_code' => 'string',
            'withholding_rate' => 'number',
            'withholding_code' => 'string',
            'withholding_amount' => 'integer',
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
            'state' => (string) ($row['ship_to_state'] ?? ''),
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
            'state' => 'string',
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

/* ---------------------------------------------------------------------------
   Expenses
   --------------------------------------------------------------------------- */

/**
 * A 503 for a store that has not run the update that brings the expense
 * tables (4.95): the routes are declared, the tables are not there yet.
 */
function erp_api_expenses_ready()
{
    if (!erp_expenses_ready()) {
        api_fail(503, 'service_unavailable', lang('Expenses come with the software update; run the update to use them.'));
    }
}

function erp_api_expense_category_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'code' => (string) $row['code'],
        'sort_order' => (int) $row['sort_order'],
        'active' => ((int) $row['is_active'] === 1),
        'tax_deductible' => !isset($row['tax_deductible']) || ((int) $row['tax_deductible'] === 1),
    );
}

// What erp_api_expense_category_present() returns.
function erp_api_expense_category_schema()
{
    return array(
        'id' => 'integer',
        'name' => 'string',
        'code' => 'string',
        'sort_order' => 'integer',
        'active' => 'boolean',
        'tax_deductible' => 'boolean',
    );
}

function erp_api_expense_categories_list($params)
{
    erp_api_expenses_ready();

    $out = array();

    // erp_expense_categories() writes the default list on a store's first
    // call, as the panel does.
    foreach (erp_expense_categories() as $row) {
        if (isset($params['active']) && (((int) $row['is_active'] === 1) !== !empty($params['active']))) {
            continue;
        }

        $out[] = erp_api_expense_category_present($row);
    }

    // A store has a few dozen categories at most, never a page of them.
    api_ok_list($out, max(1, count($out)), null, count($out));
}

/**
 * The expense rows with their category, their till and the receipt file in
 * use (the newest kept one; a replaced file stays behind it).
 *
 * @return string  SELECT ... FROM ... JOIN ..., for erp_api_page()
 */
function erp_api_expense_select()
{
    $kept = erp_archive_ready();

    return "SELECT e.*, e.updated_at AS _sort, c.name AS category_name, c.code AS category_code, t.name AS cash_account_name"
        . ($kept
            ? ", kf.id AS kept_file_id, kf.type AS kept_type, kf.size AS kept_size, kf.timestamp AS kept_at,
                (SELECT COUNT(*) FROM files f3 WHERE f3.erp_doc_type = 'expense' AND f3.erp_doc_id = e.id) AS kept_versions"
            : ", 0 AS kept_file_id, '' AS kept_type, 0 AS kept_size, 0 AS kept_at, 0 AS kept_versions") . "
        FROM erp_expenses e
        LEFT JOIN erp_expense_categories c ON c.id = e.category_id
        LEFT JOIN erp_cash_accounts t ON t.id = e.cash_account_id"
        . ($kept ? " LEFT JOIN files kf ON kf.id = (SELECT MAX(f2.id) FROM files f2 WHERE f2.erp_doc_type = 'expense' AND f2.erp_doc_id = e.id)" : '');
}

function erp_api_expense_present($row)
{
    $kept = !empty($row['kept_file_id']);

    return array(
        'id' => (int) $row['id'],
        'date' => erp_api_date($row['expense_date']),
        'category_id' => (int) $row['category_id'],
        'category_name' => (string) ($row['category_name'] ?? ''),
        'category_code' => (string) ($row['category_code'] ?? ''),
        'supplier' => (string) $row['supplier'],
        'supplier_tax_number' => (string) $row['supplier_tax_number'],
        'document_no' => (string) $row['document_no'],
        'description' => (string) $row['description'],
        'currency' => (string) $row['currency'],
        'exchange_rate' => (float) $row['exchange_rate'],
        'net_amount' => api_money($row['net_amount']),
        'tax_rate' => (float) $row['tax_rate'],
        'tax_amount' => api_money($row['tax_amount']),
        'total_amount' => api_money($row['total_amount']),
        'net_base' => api_money($row['net_base']),
        'tax_base' => api_money($row['tax_base']),
        'total_base' => api_money($row['total_base']),
        'tax_deductible' => ((int) $row['tax_deductible'] === 1),
        'status' => (string) $row['status'],
        'due_date' => erp_api_date($row['due_date']),
        'paid_date' => erp_api_date($row['paid_date']),
        'cash_account_id' => (int) $row['cash_account_id'],
        'cash_account_name' => (string) ($row['cash_account_name'] ?? ''),
        'payment_method' => (string) $row['payment_method'],
        'cash_id' => (int) $row['cash_id'],
        'cancel_reason' => (string) $row['cancel_reason'],
        'cancelled_at' => api_time($row['cancelled_at']),
        'recurrence_id' => (int) ($row['recurrence_id'] ?? 0),
        'receipt' => array(
            'kept' => $kept,
            'content_type' => $kept ? erp_expense_receipt_mime((string) $row['kept_type']) : '',
            'size' => $kept ? (int) $row['kept_size'] : 0,
            'kept_at' => $kept ? api_time($row['kept_at']) : null,
            'replaced' => $kept ? max(0, (int) $row['kept_versions'] - 1) : 0,
        ),
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What erp_api_expense_present() returns, declared for the OpenAPI document.
function erp_api_expense_schema()
{
    return array(
        'id' => 'integer',
        'date' => 'string?',
        'category_id' => 'integer',
        'category_name' => 'string',
        'category_code' => 'string',
        'supplier' => 'string',
        'supplier_tax_number' => 'string',
        'document_no' => 'string',
        'description' => 'string',
        'currency' => 'string',
        'exchange_rate' => 'number',
        'net_amount' => 'integer',
        'tax_rate' => 'number',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'net_base' => 'integer',
        'tax_base' => 'integer',
        'total_base' => 'integer',
        'tax_deductible' => 'boolean',
        'status' => 'string',
        'due_date' => 'string?',
        'paid_date' => 'string?',
        'cash_account_id' => 'integer',
        'cash_account_name' => 'string',
        'payment_method' => 'string',
        'cash_id' => 'integer',
        'cancel_reason' => 'string',
        'cancelled_at' => 'string?',
        'recurrence_id' => 'integer',
        'receipt' => array(
            'kept' => 'boolean',
            'content_type' => 'string',
            'size' => 'integer',
            'kept_at' => 'string?',
            'replaced' => 'integer',
        ),
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function erp_api_expense_row($expense_id)
{
    $row = db_item(erp_api_expense_select() . " WHERE e.id = '" . (int) $expense_id . "' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Expense'));
    }

    return $row;
}

/**
 * The API's name for a field erp_expense_save() refused.
 *
 * @param string $field
 * @return string|null
 */
function erp_api_expense_field($field)
{
    if ((string) $field === '') {
        return null;
    }

    return ((string) $field === 'expense_date') ? 'date' : (string) $field;
}

/**
 * The bytes of a receipt sent as base64, checked before anything is written:
 * the size, and a type read from the bytes.
 *
 * @param string $value
 * @param string $field
 * @return string
 */
function erp_api_expense_receipt_bytes($value, $field)
{
    // A data: address pasted whole, and the line breaks some encoders add,
    // are both still the file.
    $value = preg_replace('/^data:[a-z0-9.+\/-]+;base64,/i', '', trim((string) $value));
    $bytes = base64_decode(preg_replace('/\s+/', '', (string) $value), true);

    if (($bytes === false) || ($bytes === '')) {
        api_fail_validation(lang(array('string' => '{var:1} is not valid base64.', 'vars' => $field)), $field);
    }

    if (strlen($bytes) > ERP_EXPENSE_FILE_MAX_BYTES) {
        api_fail_validation(lang(array('string' => 'The file can be at most {var:1} MB.', 'vars' => (int) (ERP_EXPENSE_FILE_MAX_BYTES / 1048576))), $field);
    }

    if (erp_expense_receipt_type($bytes) === '') {
        api_fail_validation(lang('Choose a picture (JPG, PNG, WEBP) or a PDF of the receipt.'), $field);
    }

    return $bytes;
}

/**
 * A till that can take a payment: there, and active. The currency is checked
 * by the expense functions, which know the expense's.
 *
 * @param int $cash_account_id
 */
function erp_api_expense_till($cash_account_id)
{
    $till = db_item("SELECT id, is_active FROM erp_cash_accounts WHERE id = '" . (int) $cash_account_id . "' LIMIT 1");

    if (!is_array($till)) {
        api_fail_validation(lang('That till or bank account could not be found.'), 'cash_account_id');
    }

    if ((int) $till['is_active'] !== 1) {
        api_fail_validation(lang('That till or bank account is not active.'), 'cash_account_id');
    }
}

/**
 * Money moves only with the till right: the scope on the key, and the right
 * of the key's owner in the panel, which the API's own ceiling does not know.
 */
function erp_api_expense_cash_gate()
{
    if (!api_has_scope(api_current_scopes(), 'erp_cash:write')) {
        api_fail_scope('erp_cash:write');
    }

    if (!erp_api_owner()['cash']) {
        api_fail(403, 'forbidden', lang('The application owner does not hold the ERP cash right.'));
    }
}

function erp_api_expenses_list($params)
{
    erp_api_expenses_ready();

    $where = array();

    if (isset($params['category_id'])) {
        $where[] = "e.category_id = '" . (int) $params['category_id'] . "'";
    }

    if (isset($params['status'])) {
        $where[] = "e.status = '" . escape($params['status']) . "'";
    }

    if (isset($params['search']) && ($params['search'] !== '')) {
        $search = escape(escape_like($params['search']));
        $where[] = "(e.supplier LIKE '%" . $search . "%' OR e.supplier_tax_number LIKE '%" . $search . "%'"
            . " OR e.document_no LIKE '%" . $search . "%' OR e.description LIKE '%" . $search . "%')";
    }

    if (isset($params['from'])) {
        $where[] = "e.expense_date >= '" . escape(erp_api_day($params['from'])) . "'";
    }

    if (isset($params['to'])) {
        $where[] = "e.expense_date <= '" . escape(erp_api_day($params['to'])) . "'";
    }

    if (isset($params['updated_since'])) {
        $where[] = "e.updated_at >= '" . (int) $params['updated_since'] . "'";
    }

    $page = erp_api_page(erp_api_expense_select(), $where, 'e.updated_at', 'e.id', $params, "FROM erp_expenses e");

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_expense_present($row);
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_expenses_get($params)
{
    erp_api_expenses_ready();

    api_ok(erp_api_expense_present(erp_api_expense_row((int) $params['id'])));
}

function erp_api_expenses_receipt($params)
{
    erp_api_expenses_ready();

    $row = erp_api_expense_row((int) $params['id']);
    $file = erp_archive_file('expense', (int) $row['id']);
    $bytes = ($file !== null) ? @file_get_contents($file['path']) : false;

    if (($bytes === false) || ($bytes === '')) {
        api_fail_not_found(lang('The receipt'));
    }

    // The day and the receipt number, the way the accountant's pack names it.
    $label = ((string) $row['document_no'] !== '') ? (string) $row['document_no'] : ('G' . (int) $row['id']);
    $extension = strtolower((string) $file['type']);
    $file_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['expense_date'] . '_' . $label) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);

    api_ok(erp_api_document_present($file_name, erp_expense_receipt_mime($extension), $bytes));
}

function erp_api_expenses_create($params)
{
    erp_api_expenses_ready();

    $owner = erp_api_owner();
    $pay = isset($params['cash_account_id']);

    foreach (array('payment_method', 'paid_date') as $field) {
        if (!$pay && isset($params[$field])) {
            api_fail_validation(lang(array('string' => '{var:1} is only taken with cash_account_id.', 'vars' => $field)), $field);
        }
    }

    if ($pay) {
        erp_api_expense_cash_gate();
        erp_api_expense_till((int) $params['cash_account_id']);
    }

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($params['currency'] ?? '')));
    $currency = ($currency !== '') ? $currency : $base;

    // The save quietly keeps the store currency while foreign currency is
    // off; a caller who named another one is told instead.
    if (($currency !== $base) && (!erp_fx_enabled() || !erp_fx_currency_allowed($currency))) {
        api_fail_validation(lang('That currency is not enabled for the ERP.'), 'currency');
    }

    // Checked before the expense is written, so a bad file costs nothing.
    $bytes = (isset($params['receipt_base64']) && ($params['receipt_base64'] !== ''))
        ? erp_api_expense_receipt_bytes($params['receipt_base64'], 'receipt_base64')
        : null;

    $data = array(
        'expense_date' => erp_api_day($params['date']),
        'category_id' => (int) $params['category_id'],
        'supplier' => (string) ($params['supplier'] ?? ''),
        'supplier_tax_number' => (string) ($params['supplier_tax_number'] ?? ''),
        'document_no' => (string) ($params['document_no'] ?? ''),
        'description' => (string) ($params['description'] ?? ''),
        'currency' => $currency,
        'exchange_rate' => (float) ($params['exchange_rate'] ?? 0),
        'exchange_rate_source' => 'api',
        'amount' => (int) $params['amount'],
        'includes_tax' => array_key_exists('includes_tax', $params) ? !empty($params['includes_tax']) : true,
        'tax_rate' => (float) ($params['tax_rate'] ?? 0),
        'tax_amount' => isset($params['tax_amount']) ? (int) $params['tax_amount'] : null,
        'tax_deductible' => array_key_exists('tax_deductible', $params) ? !empty($params['tax_deductible']) : erp_expense_category_deductible((int) ($params['category_id'] ?? 0)),
        'due_date' => isset($params['due_date']) ? erp_api_day($params['due_date']) : '',
    );

    if ($pay) {
        $data['pay'] = true;
        $data['cash_account_id'] = (int) $params['cash_account_id'];
        $data['payment_method'] = (string) ($params['payment_method'] ?? 'cash');
        $data['paid_date'] = isset($params['paid_date']) ? erp_api_day($params['paid_date']) : '';
    }

    $saved = erp_expense_save($data, $owner['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_expense_field((string) $saved['field']));
    }

    $expense_id = (int) $saved['id'];

    // The expense is in; a file that then fails to reach the disk shows as
    // receipt.kept false rather than undoing it.
    if ($bytes !== null) {
        erp_expense_keep_receipt($expense_id, $bytes, $owner['id'], false);
    }

    erp_api_log(lang(array(
        'string' => 'ERP expense #{var:1} was recorded through the API.',
        'vars' => $expense_id,
    )));

    api_ok(erp_api_expense_present(erp_api_expense_row($expense_id)), 201);
}

function erp_api_expenses_update($params)
{
    erp_api_expenses_ready();

    $owner = erp_api_owner();
    $existing = erp_expense((int) $params['id']);

    if ($existing === null) {
        api_fail_not_found(lang('Expense'));
    }

    $figure_fields = array('date', 'amount', 'includes_tax', 'tax_rate', 'tax_amount', 'due_date', 'exchange_rate');

    // What left the till is not rewritten; said, rather than dropped.
    if ((string) $existing['status'] === 'paid') {
        foreach ($figure_fields as $field) {
            if (array_key_exists($field, $params)) {
                api_fail_validation(lang('A paid expense keeps its date and its figures: they are what left the till.'), $field);
            }
        }
    }

    $sent = function ($field) use ($params) {
        return array_key_exists($field, $params);
    };
    $due = (string) $existing['due_date'];

    $data = array(
        'expense_date' => $sent('date') ? erp_api_day($params['date']) : (string) $existing['expense_date'],
        'category_id' => $sent('category_id') ? (int) $params['category_id'] : (int) $existing['category_id'],
        'supplier' => $sent('supplier') ? (string) $params['supplier'] : (string) $existing['supplier'],
        'supplier_tax_number' => $sent('supplier_tax_number') ? (string) $params['supplier_tax_number'] : (string) $existing['supplier_tax_number'],
        'document_no' => $sent('document_no') ? (string) $params['document_no'] : (string) $existing['document_no'],
        'description' => $sent('description') ? (string) $params['description'] : (string) $existing['description'],
        'tax_deductible' => $sent('tax_deductible') ? !empty($params['tax_deductible']) : ((int) $existing['tax_deductible'] === 1),
        'currency' => (string) $existing['currency'],
        'exchange_rate' => $sent('exchange_rate') ? (float) $params['exchange_rate'] : (float) $existing['exchange_rate'],
        'exchange_rate_date' => (string) $existing['exchange_rate_date'],
        'exchange_rate_source' => $sent('exchange_rate') ? 'api' : (string) $existing['exchange_rate_source'],
        'due_date' => $sent('due_date')
            ? (((string) $params['due_date'] === '') ? '' : erp_api_day($params['due_date']))
            : (($due > '0000-00-00') ? $due : ''),
    );

    if ($sent('amount') || $sent('includes_tax') || $sent('tax_rate') || $sent('tax_amount')) {
        $includes_tax = $sent('includes_tax') ? !empty($params['includes_tax']) : true;

        // Without a new amount the one kept is used, as a total or as a net
        // to match what includes_tax now says.
        $data['amount'] = $sent('amount')
            ? (int) $params['amount']
            : ($includes_tax ? (int) $existing['total_amount'] : (int) $existing['net_amount']);
        $data['includes_tax'] = $includes_tax;
        $data['tax_rate'] = $sent('tax_rate') ? (float) $params['tax_rate'] : (float) $existing['tax_rate'];
        $data['tax_amount'] = ($sent('tax_amount') && ((string) $params['tax_amount'] !== '')) ? (int) $params['tax_amount'] : null;
    } else {
        // The figures as they are: the kept total, VAT and rate give back
        // the same net to the kurus.
        $data['amount'] = (int) $existing['total_amount'];
        $data['includes_tax'] = true;
        $data['tax_rate'] = (float) $existing['tax_rate'];
        $data['tax_amount'] = (int) $existing['tax_amount'];
    }

    $saved = erp_expense_save($data, $owner['id'], (int) $existing['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_expense_field((string) $saved['field']));
    }

    erp_api_log(lang(array(
        'string' => 'ERP expense #{var:1} was changed through the API.',
        'vars' => (int) $existing['id'],
    )));

    api_ok(erp_api_expense_present(erp_api_expense_row((int) $existing['id'])));
}

function erp_api_expenses_pay($params)
{
    erp_api_expenses_ready();

    $owner = erp_api_owner();

    if (!$owner['cash']) {
        api_fail(403, 'forbidden', lang('The application owner does not hold the ERP cash right.'));
    }

    $row = erp_api_expense_row((int) $params['id']);

    erp_api_expense_till((int) $params['cash_account_id']);

    $paid = erp_expense_pay((int) $row['id'], array(
        'cash_account_id' => (int) $params['cash_account_id'],
        'payment_method' => (string) ($params['payment_method'] ?? 'cash'),
        'paid_date' => isset($params['date']) ? erp_api_day($params['date']) : date('Y-m-d'),
    ), $owner['id']);

    if (empty($paid['success'])) {
        api_fail_validation((string) $paid['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP expense #{var:1} was paid through the API.',
        'vars' => (int) $row['id'],
    )));

    api_ok(erp_api_expense_present(erp_api_expense_row((int) $row['id'])));
}

function erp_api_expenses_cancel($params)
{
    erp_api_expenses_ready();

    $owner = erp_api_owner();
    $row = erp_api_expense_row((int) $params['id']);

    if ((string) $row['status'] === 'cancelled') {
        api_fail_validation(lang('That expense has already been cancelled.'), 'id');
    }

    // Cancelling a paid expense puts its money back into the till.
    if ((string) $row['status'] === 'paid') {
        erp_api_expense_cash_gate();
    }

    $reason = trim((string) ($params['reason'] ?? ''));

    if ($reason === '') {
        api_fail_validation(lang('The reason is required.'), 'reason');
    }

    $cancelled = erp_expense_cancel((int) $row['id'], $reason, $owner['id']);

    if (empty($cancelled['success'])) {
        api_fail_validation((string) $cancelled['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP expense #{var:1} was cancelled through the API: {var:2}',
        'vars' => array((int) $row['id'], $reason),
    )));

    api_ok(erp_api_expense_present(erp_api_expense_row((int) $row['id'])));
}

function erp_api_expenses_receipt_put($params)
{
    erp_api_expenses_ready();

    $owner = erp_api_owner();
    $row = erp_api_expense_row((int) $params['id']);
    $bytes = erp_api_expense_receipt_bytes($params['content_base64'], 'content_base64');

    $kept = erp_expense_keep_receipt((int) $row['id'], $bytes, $owner['id'], !empty($params['replace']));

    if (empty($kept['success'])) {
        switch ((string) $kept['code']) {
            case 'exists':
                api_fail(409, 'expense_has_receipt', lang('This expense already has its receipt. Send replace true to take the new file in its place.'), 'replace');
                break;
            case 'not_ready':
                api_fail(503, 'service_unavailable', (string) $kept['error']);
                break;
            case 'disk':
                api_fail_server((string) $kept['error']);
                break;
            case 'refused':
                api_fail_validation((string) $kept['error'], 'replace');
                break;
            default:
                api_fail_validation((string) $kept['error'], 'content_base64');
        }
    }

    if ($kept['replaced']) {
        erp_api_log(lang(array(
            'string' => 'The receipt of ERP expense #{var:1} was replaced through the API.',
            'vars' => (int) $row['id'],
        )));
    } else {
        erp_api_log(lang(array(
            'string' => 'The receipt of ERP expense #{var:1} was kept through the API.',
            'vars' => (int) $row['id'],
        )));
    }

    api_ok(erp_api_expense_present(erp_api_expense_row((int) $row['id'])));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - accounts: the people and firms the shop owes or is owed by.
 *
 * One table for customers and suppliers, because a party is often both and a
 * shop that keeps two lists ends up reconciling them by hand. kind says which
 * side they are usually on; it does not stop a movement in either direction.
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
 * One account.
 *
 * @param int $id
 * @return array|null
 */
function erp_account($id)
{
    $row = db_item("SELECT * FROM erp_accounts WHERE id = '" . (int) $id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Accounts for a list screen.
 *
 * @param array $filters  kind, status, search
 * @return array
 */
function erp_accounts($filters = array())
{
    $where = array("1 = 1");

    if (!empty($filters['kind'])) {
        // 'both' answers to either side, so asking for customers has to include it.
        $kind = escape($filters['kind']);
        $where[] = "(kind = '" . $kind . "' OR kind = 'both')";
    }

    if (!empty($filters['status'])) {
        $where[] = "status = '" . escape($filters['status']) . "'";
    }

    if (!empty($filters['search'])) {
        $search = escape($filters['search']);
        $where[] = "(title LIKE '%" . $search . "%' OR tax_number LIKE '%" . $search . "%' OR email LIKE '%" . $search . "%')";
    }

    return (array) db_items("SELECT * FROM erp_accounts WHERE " . implode(' AND ', $where) . " ORDER BY title ASC, id ASC");
}

/**
 * The country an account is in when nothing says otherwise.
 *
 * The store names its home country in the countries table (default_selected),
 * the same place the checkout reads it from. Nothing here assumes where the
 * store is; a shop that has not chosen a country gets an empty code.
 *
 * @return string  ISO 3166-1 alpha-2 code, or '' when no country is selected
 */
function erp_default_country_code()
{
    static $code = null;

    if ($code === null) {
        $code = strtoupper(trim((string) db_value("SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1")));
    }

    return $code;
}

/**
 * The country an account's rules are read by: its own, or the store's when
 * it has none. The ERP is used in many countries; a rule of one country's
 * tax authority (a VKN's check digits, a five-digit postcode, a person's
 * name in two parts for GİB) applies only to accounts of that country.
 *
 * @param string $country_code
 * @return string  Two letters, or '' when neither the account nor the store names one
 */
function erp_account_country($country_code = '')
{
    $code = strtoupper(trim((string) $country_code));

    return preg_match('/^[A-Z]{2}$/', $code) ? $code : erp_default_country_code();
}

/**
 * A country's name from the store's country list, or the code when the list
 * does not know it.
 *
 * @param string $code
 * @return string
 */
function erp_country_name($code)
{
    static $names = array();

    $code = strtoupper(trim((string) $code));

    if ($code === '') {
        return '';
    }

    if (!isset($names[$code])) {
        $name = (string) db_value("SELECT name FROM countries WHERE code = '" . escape($code) . "' LIMIT 1");
        $names[$code] = ($name !== '') ? $name : $code;
    }

    return $names[$code];
}

/**
 * A state or province as the code the store's tax zones use (states.code),
 * found by its code or its name; '' when the country has no such state.
 *
 * @param string $state
 * @param string $country_code
 * @return string
 */
function erp_state_code($state, $country_code)
{
    $state = trim((string) $state);
    $country = strtoupper(trim((string) $country_code));

    if (($state === '') || ($country === '')) {
        return '';
    }

    $code = (string) db_value("SELECT s.code FROM states s
        INNER JOIN countries c ON c.id = s.country_id
        WHERE c.code = '" . escape($country) . "' AND (s.code = '" . escape($state) . "' OR s.name = '" . escape($state) . "')
        LIMIT 1");

    return $code;
}

/**
 * How a checkout address lands on the account card. The checkout has a city
 * and a state (province) field. In Turkey the province is what the card and
 * GİB call the city (il) and the checkout's city field holds the district
 * (ilçe); elsewhere the city is the city and the state the state.
 *
 * @param string $city   The checkout's city field
 * @param string $state  The checkout's state field
 * @param string $country_code
 * @return array ['city' => string, 'district' => string, 'state' => string]
 */
function erp_address_from_checkout($city, $state, $country_code)
{
    $city = trim((string) $city);
    $state = trim((string) $state);

    if (erp_account_country($country_code) === 'TR') {
        return array('city' => $state, 'district' => $city, 'state' => '');
    }

    return array('city' => $city, 'district' => '', 'state' => $state);
}

/**
 * The place line of an address, written the way its country writes it:
 * "34710 Kadıköy İstanbul" in Turkey, "Austin, TX 78701" in North America
 * and Australia, "London SW1A 1AA" in Britain, "10115 Berlin" in most of
 * Europe. The country's name follows when it is not the store's own.
 *
 * @param array  $parts  postcode, district, city, state
 * @param string $country_code
 * @param bool   $with_country
 * @return string
 */
function erp_address_locality($parts, $country_code, $with_country = true)
{
    $country = erp_account_country($country_code);
    $postcode = trim((string) ($parts['postcode'] ?? ''));
    $district = trim((string) ($parts['district'] ?? ''));
    $city = trim((string) ($parts['city'] ?? ''));
    $state = trim((string) ($parts['state'] ?? ''));
    $join = function ($items, $glue = ' ') {
        return implode($glue, array_filter(array_map('trim', $items), 'strlen'));
    };

    switch ($country) {
        case 'TR':
            $line = $join(array($postcode, $district, $city, $state));
            break;

        case 'US':
        case 'CA':
        case 'AU':
            $line = $join(array($join(array($district, $city), ', '), $join(array($state, $postcode))), ', ');
            break;

        case 'GB':
        case 'IE':
            $line = $join(array($district, $city, $state, $postcode));
            break;

        default:
            $line = $join(array($district, $join(array($postcode, $city)), $state), ', ');
    }

    if ($with_country && ($country !== '') && ($country !== erp_default_country_code())) {
        $line = $join(array($line, erp_country_name($country)), ', ');
    }

    return $line;
}

/**
 * The seller's place line on a document, from the organization's address in
 * the site settings. The settings hold the country as free text, so it is
 * printed as typed, after the place in the store country's own order.
 *
 * @param array $seller  zip_code, city, state, country
 * @return string
 */
function erp_seller_locality($seller)
{
    $store = erp_account_country('');
    $line = erp_address_locality(array(
        'postcode' => (string) ($seller['zip_code'] ?? ''),
        'city' => (string) ($seller['city'] ?? ''),
        'state' => (string) ($seller['state'] ?? ''),
    ), $store, false);
    $country = trim((string) ($seller['country'] ?? ''));

    if ($country === '') {
        return $line;
    }

    if ($line === '') {
        return $country;
    }

    return $line . (($store === 'TR') ? ' ' : ', ') . $country;
}

/**
 * How long a tax number the account card holds (erp_accounts.tax_number),
 * read from the table so that the width the schema has is the width every
 * check uses.
 *
 * @return int
 */
function erp_tax_number_width()
{
    static $width = null;

    if ($width === null) {
        $width = (int) db_value("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'erp_accounts' AND COLUMN_NAME = 'tax_number' LIMIT 1");
        $width = ($width > 0) ? $width : 11;
    }

    return $width;
}

/**
 * A tax number checked by the rules of its country, and the form it is kept
 * in. One check for every door an account comes through (the form, the
 * counter, the CSV import, the API), so they cannot disagree.
 *
 * Turkey: a VKN (10 digits) or TCKN (11) whose check digits hold, kept as
 * digits. Elsewhere the formats are too many to check (EIN, VAT id, ABN,
 * GSTIN, ...): letters, digits and the usual separators, kept as typed, as
 * long as the card can hold it.
 *
 * @param string $number
 * @param string $country_code  The account's country; the store's when empty
 * @return array ['value' => string, 'error' => string]
 */
function erp_tax_number_check($number, $country_code = '')
{
    $typed = trim((string) $number);

    if ($typed === '') {
        return array('value' => '', 'error' => '');
    }

    if (erp_account_country($country_code) === 'TR') {
        $digits = preg_replace('/\D/', '', $typed);

        if (preg_match('/[^0-9\s.\-]/', $typed) || ((strlen($digits) !== 10) && (strlen($digits) !== 11))) {
            return array('value' => $digits, 'error' => lang('A VKN has 10 digits and a TCKN 11.'));
        }

        if (function_exists('erp_edoc_tax_number_valid') && !erp_edoc_tax_number_valid($digits)) {
            return array('value' => $digits, 'error' => lang('The check digits of this number do not match; it is probably mistyped. GİB refuses a document that carries it.'));
        }

        return array('value' => $digits, 'error' => '');
    }

    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .\/-]*$/u', $typed)) {
        return array('value' => $typed, 'error' => lang('A tax number holds letters, digits, spaces, dots, dashes and slashes.'));
    }

    if (mb_strlen($typed) > erp_tax_number_width()) {
        return array('value' => $typed, 'error' => lang(array('string' => 'Longer than the account card holds ({var:1} characters).', 'vars' => erp_tax_number_width())));
    }

    return array('value' => $typed, 'error' => '');
}

/**
 * What a tax number is called on screen: VKN / TCKN in Turkey, "tax
 * number" elsewhere, where neither word means anything.
 *
 * @param string $country_code  The record's country; the store's when empty
 * @return string
 */
function erp_tax_id_label($country_code = '')
{
    return (erp_account_country($country_code) === 'TR') ? lang('VKN / TCKN') : lang('Tax number');
}

/**
 * Create or update an account.
 *
 * @param array $data  id (0 to create), title, kind, is_person, tax_number, ...
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function erp_account_save($data)
{
    $id = (int) ($data['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));

    if ($title === '') {
        return array('success' => false, 'id' => 0, 'error' => lang('Enter a name.'));
    }

    $columns = array(
        'kind' => in_array(($data['kind'] ?? ''), array('customer', 'supplier', 'both'), true) ? $data['kind'] : 'customer',
        'title' => $title,
        'is_person' => !empty($data['is_person']) ? 1 : 0,
        'tax_number' => trim((string) ($data['tax_number'] ?? '')),
        'tax_office' => trim((string) ($data['tax_office'] ?? '')),
        'email' => trim((string) ($data['email'] ?? '')),
        'phone' => trim((string) ($data['phone'] ?? '')),
        'address' => trim((string) ($data['address'] ?? '')),
        'district' => trim((string) ($data['district'] ?? '')),
        'city' => trim((string) ($data['city'] ?? '')),
        'postcode' => trim((string) ($data['postcode'] ?? '')),
        'currency' => strtoupper(trim((string) ($data['currency'] ?? erp_base_currency()))),
        'status' => (($data['status'] ?? 'active') === 'passive') ? 'passive' : 'active',
        'notes' => trim((string) ($data['notes'] ?? '')),
        // Days from the invoice date to its due date; 0 leaves it to the
        // store's default term.
        'payment_days' => min(3650, max(0, (int) ($data['payment_days'] ?? 0))),
    );

    // A state or province, for addresses that have one; written only when
    // the caller names it and once the upgrade has added it.
    if (array_key_exists('state', $data) && function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'state')) {
        $columns['state'] = mb_substr(trim((string) $data['state']), 0, 100);
    }

    // The country. A new account takes the store's when none is given; an
    // update writes it only when the caller names it, so a form without a
    // country field does not move every foreign account to the store's
    // country on save.
    if (array_key_exists('country_code', $data) || ($id <= 0)) {
        $columns['country_code'] = strtoupper(trim((string) ($data['country_code'] ?? erp_default_country_code())));
    }

    // The contact behind the account. Written only when the caller names it:
    // a save that does not mention the contact keeps the link, rather than
    // quietly cutting it the way every edit once did.
    if (array_key_exists('contact_id', $data)) {
        $columns['contact_id'] = max(0, (int) $data['contact_id']);
    }

    // Days overdue before this account's invoices are announced; 0 leaves it
    // to the store's threshold. Written only once the upgrade has added it.
    if (function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'overdue_notify_days')) {
        $columns['overdue_notify_days'] = min(3650, max(0, (int) ($data['overdue_notify_days'] ?? 0)));
    }

    // Whether the customer is written to when their invoices pass the
    // threshold; left out of $data (a caller that predates the field, the
    // CSV import) keeps the account's current choice.
    if (array_key_exists('overdue_notify_customer', $data)
        && function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'overdue_notify_customer')) {
        $columns['overdue_notify_customer'] = !empty($data['overdue_notify_customer']) ? 1 : 0;
    }

    // Where invoices are e-mailed and whether they go on their own (4.97);
    // left out of $data, the account keeps what it has.
    if (function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'invoice_email')) {
        if (array_key_exists('invoice_email', $data)) {
            $invoice_email = trim((string) $data['invoice_email']);
            $columns['invoice_email'] = (($invoice_email !== '') && filter_var($invoice_email, FILTER_VALIDATE_EMAIL)) ? mb_substr($invoice_email, 0, 255) : '';
        }

        if (array_key_exists('invoice_mail', $data)) {
            $columns['invoice_mail'] = !empty($data['invoice_mail']) ? 1 : 0;
        }
    }

    // The most the account may owe, base-currency kurus, 0 for none (4.98).
    if (array_key_exists('credit_limit', $data)
        && function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'credit_limit')) {
        $columns['credit_limit'] = max(0, (int) $data['credit_limit']);
    }

    $pairs = array();
    foreach ($columns as $column => $value) {
        $pairs[] = $column . " = '" . escape($value) . "'";
    }

    if ($id > 0) {
        $pairs[] = "updated_at = '" . time() . "'";
        $ok = erp_query("UPDATE erp_accounts SET " . implode(', ', $pairs) . " WHERE id = '" . $id . "'");

        return ($ok === false)
            ? array('success' => false, 'id' => $id, 'error' => erp_db_error())
            : array('success' => true, 'id' => $id, 'error' => '');
    }

    $pairs[] = "created_by = '" . (int) ($data['created_by'] ?? 0) . "'";
    $pairs[] = "created_at = '" . time() . "'";
    $pairs[] = "updated_at = '" . time() . "'";

    $ok = erp_query("INSERT INTO erp_accounts SET " . implode(', ', $pairs));

    if ($ok === false) {
        return array('success' => false, 'id' => 0, 'error' => erp_db_error());
    }

    $account_id = (int) mysqli_insert_id(db::$con);

    erp_event_account($account_id);

    return array('success' => true, 'id' => $account_id, 'error' => '');
}

/**
 * The day an invoice issued to an account falls due.
 *
 * The account's own term wins; an account without one takes the store's
 * default; with neither set the document is due on the day it is issued,
 * which is what every document said before terms existed. Read once, when
 * the document is written: a term changed afterwards does not move the due
 * dates of documents already issued.
 *
 * @param int    $account_id
 * @param string $issue_date  Y-m-d
 * @return string  Y-m-d
 */
function erp_account_due_date($account_id, $issue_date)
{
    $issue_date = (string) $issue_date;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date) !== 1) {
        $issue_date = date('Y-m-d');
    }

    $days = 0;
    if ((int) $account_id > 0) {
        $days = (int) db_value("SELECT payment_days FROM erp_accounts WHERE id = '" . (int) $account_id . "' LIMIT 1");
    }
    if ($days <= 0) {
        $days = defined('ERP_DEFAULT_DUE_DAYS') ? (int) ERP_DEFAULT_DUE_DAYS : 0;
    }
    if ($days <= 0) {
        return $issue_date;
    }

    $date = date_create($issue_date);
    if ($date === false) {
        return $issue_date;
    }

    return $date->modify('+' . $days . ' days')->format('Y-m-d');
}

/**
 * Record an account's opening position.
 *
 * The opening figure is a movement like any other and not a column: held in
 * both places it gets counted twice, which is what the first draft of this
 * schema did.
 *
 * @param array $data  account_id, amount (kurus, signed: positive = they owe us),
 *                     doc_date, currency, exchange_rate, exchange_rate_date,
 *                     exchange_rate_source, created_by
 * @return array ['success' => bool, 'error' => string]
 */
function erp_account_open($data)
{
    $account_id = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);

    if ($account_id <= 0) {
        return array('success' => false, 'error' => lang('Choose an account.'));
    }

    $existing = (int) db_value("SELECT COUNT(*) FROM erp_account_transactions
        WHERE account_id = '" . $account_id . "' AND kind = 'opening'");

    if ($existing > 0) {
        return array('success' => false, 'error' => lang('This account already has an opening balance.'));
    }

    if (!erp_tx_begin()) {
        return array('success' => false, 'error' => lang('Could not start a database transaction.'));
    }

    $posted = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => $data['doc_date'] ?? date('Y-m-d'),
        'kind' => 'opening',
        'direction' => ($amount >= 0) ? 'debit' : 'credit',
        'amount' => abs($amount),
        'currency' => $data['currency'] ?? erp_base_currency(),
        'exchange_rate' => $data['exchange_rate'] ?? 1,
        'exchange_rate_date' => $data['exchange_rate_date'] ?? ($data['doc_date'] ?? date('Y-m-d')),
        'exchange_rate_source' => $data['exchange_rate_source'] ?? '',
        'description' => lang('Opening balance'),
        'created_by' => $data['created_by'] ?? 0,
    ));

    if (($posted === false) || !erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return array('success' => false, 'error' => $error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return array('success' => false, 'error' => $error);
    }

    return array('success' => true, 'error' => '');
}

/**
 * Find, or create, the account that stands for a contact.
 *
 * The shop's customer list is `contacts`; the ledger's is `erp_accounts`. They
 * are not the same thing - a supplier has no contact record and a contact who
 * never bought anything needs no ledger - so they are linked rather than
 * merged, from both ends: contacts.erp_account_id and erp_accounts.contact_id.
 *
 * Both ends are written because both are read. The order bridge starts from a
 * contact and needs the account; a statement starts from the account and needs
 * the person.
 *
 * @param int        $contact_id
 * @param int        $created_by
 * @param array|null $order  The order being billed, whose billing block fills
 *                           what a bare card leaves empty on a new account
 * @return int  Account id, 0 when the contact does not exist
 */
function erp_account_for_contact($contact_id, $created_by = 0, $order = null)
{
    $contact_id = (int) $contact_id;

    if ($contact_id <= 0) {
        return 0;
    }

    $existing = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    $data = erp_account_data_from_contact($contact_id);

    if ($data === null) {
        return 0;
    }

    if (is_array($order)) {
        $data = erp_account_data_with_order($data, $order, $contact_id);
    }

    $data['created_by'] = $created_by;
    $result = erp_account_save($data);

    if (!$result['success']) {
        return 0;
    }

    erp_query("UPDATE contacts SET erp_account_id = '" . (int) $result['id'] . "' WHERE id = '" . $contact_id . "'");

    return (int) $result['id'];
}

/**
 * A new account's fields with the gaps the contact card leaves filled from
 * the order's billing block.
 *
 * The checkout normally writes what it asked for onto the card. A customer
 * who chose not to update it, or an order placed against a bare card, would
 * otherwise open an account called "#<contact>" with no address, and the
 * invoice would go out made out to nobody. Only empty fields are filled: a
 * card that names someone keeps its name.
 *
 * @param array $data        erp_account_data_from_contact() output
 * @param array $order       The orders row
 * @param int   $contact_id
 * @return array
 */
function erp_account_data_with_order($data, $order, $contact_id)
{
    $company = trim((string) ($order['billing_company'] ?? ''));
    $person = trim(trim((string) ($order['billing_first_name'] ?? '')) . ' ' . trim((string) ($order['billing_last_name'] ?? '')));

    if (((string) $data['title'] === ('#' . (int) $contact_id)) && (($company !== '') || ($person !== ''))) {
        $data['title'] = ($company !== '') ? $company : $person;
        $data['is_person'] = ($company === '');
    }

    $street = trim(trim((string) ($order['billing_address_1'] ?? '')) . ' ' . trim((string) ($order['billing_address_2'] ?? '')));

    // The address moves as a whole: a street from the order under the card's
    // city would be an address nobody lives at.
    if ((trim((string) $data['address']) === '') && ($street !== '')) {
        $country_code = (trim((string) ($order['billing_country'] ?? '')) !== '')
            ? strtoupper(trim((string) $order['billing_country']))
            : (string) $data['country_code'];
        $place = erp_address_from_checkout((string) ($order['billing_city'] ?? ''), (string) ($order['billing_state'] ?? ''), $country_code);

        $data['address'] = $street;
        $data['city'] = $place['city'];
        $data['district'] = $place['district'];
        $data['state'] = $place['state'];
        $data['postcode'] = trim((string) ($order['billing_zip_code'] ?? ''));
        $data['country_code'] = $country_code;
    }

    if (trim((string) $data['email']) === '') {
        $data['email'] = trim((string) ($order['billing_email_address'] ?? ''));
    }

    if (trim((string) $data['phone']) === '') {
        $data['phone'] = trim((string) ($order['billing_phone_number'] ?? ''));
    }

    return $data;
}

/**
 * What a contact's card says, as the fields of a new account.
 *
 * The same reading whether the account is opened by the order bridge or by an
 * operator who asked for the form filled in: the company name when there is
 * one, because that is who the invoice is made out to, the person's name
 * otherwise. The checkout's city and state fields are read by the contact's
 * country (erp_address_from_checkout()).
 *
 * @param int $contact_id
 * @return array|null  Fields for erp_account_save(), or null when the contact does not exist
 */
function erp_account_data_from_contact($contact_id)
{
    $contact_id = (int) $contact_id;

    $contact = db_item("SELECT id, first_name, last_name, company, email_address, business_phone, mobile_phone,
            business_address_1, business_city, business_state, business_zip_code, business_country,
            tax_number, tax_office
        FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1");

    if (!is_array($contact)) {
        return null;
    }

    $company = trim((string) $contact['company']);
    $person = trim(trim((string) $contact['first_name']) . ' ' . trim((string) $contact['last_name']));
    $country_code = (trim((string) $contact['business_country']) !== '') ? strtoupper(trim((string) $contact['business_country'])) : erp_default_country_code();
    $place = erp_address_from_checkout((string) $contact['business_city'], (string) $contact['business_state'], $country_code);

    return array(
        'kind' => 'customer',
        'title' => ($company !== '') ? $company : ($person !== '' ? $person : ('#' . $contact_id)),
        'is_person' => ($company === ''),
        'tax_number' => (string) $contact['tax_number'],
        'tax_office' => (string) $contact['tax_office'],
        'email' => (string) $contact['email_address'],
        'phone' => (trim((string) $contact['business_phone']) !== '') ? (string) $contact['business_phone'] : (string) $contact['mobile_phone'],
        'address' => (string) $contact['business_address_1'],
        'city' => $place['city'],
        'district' => $place['district'],
        'state' => $place['state'],
        'postcode' => (string) $contact['business_zip_code'],
        'country_code' => $country_code,
        'contact_id' => $contact_id,
    );
}

/**
 * A contact as the account screen shows it: who they are, how to reach them,
 * the panel user behind them if any, how many orders they have placed, and
 * which account they are linked to.
 *
 * @param int $contact_id
 * @return array|null  null when the contact does not exist
 */
function erp_contact_summary($contact_id)
{
    $contact_id = (int) $contact_id;

    if ($contact_id <= 0) {
        return null;
    }

    $contact = db_item("SELECT id, first_name, last_name, company, email_address, business_phone, mobile_phone,
            business_city, business_state, business_country, erp_account_id, image
        FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1");

    if (!is_array($contact)) {
        return null;
    }

    $person = trim(trim((string) $contact['first_name']) . ' ' . trim((string) $contact['last_name']));
    $company = trim((string) $contact['company']);
    $place = erp_address_from_checkout((string) $contact['business_city'], (string) $contact['business_state'], (string) $contact['business_country']);

    // The panel user this contact signs in as, when there is one.
    $user = db_item("SELECT user_id, user_username FROM user WHERE user_contact = '" . $contact_id . "' LIMIT 1");

    // The link is read from the account side, which is the side the ledger
    // trusts; contacts.erp_account_id is a mirror kept for the contact screen.
    $account_id = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' ORDER BY id ASC LIMIT 1");

    return array(
        'id' => $contact_id,
        'name' => ($person !== '') ? $person : (($company !== '') ? $company : ('#' . $contact_id)),
        'person' => $person,
        'company' => $company,
        'email' => trim((string) $contact['email_address']),
        'phone' => (trim((string) $contact['business_phone']) !== '') ? trim((string) $contact['business_phone']) : trim((string) $contact['mobile_phone']),
        'district' => $place['district'],
        'city' => $place['city'],
        'state' => $place['state'],
        'user_id' => is_array($user) ? (int) $user['user_id'] : 0,
        'username' => is_array($user) ? (string) $user['user_username'] : '',
        'orders' => (int) db_value("SELECT COUNT(*) FROM orders WHERE contact_id = '" . $contact_id . "' AND status <> 'incomplete'"),
        'account_id' => $account_id,
        'image' => trim((string) ($contact['image'] ?? '')),
    );
}

/**
 * Contacts matching what was typed, for the account form's search box.
 *
 * Name, company and e-mail address are searched; each hit says which account
 * it is already linked to, so the form can say so before the operator picks
 * a contact that belongs to another card.
 *
 * @param string $query
 * @param int    $limit
 * @return array  Rows: id, name, company, email, city, account_id
 */
function erp_contact_search($query, $limit = 15)
{
    $query = trim((string) $query);

    if ($query === '') {
        return array();
    }

    $like = escape(escape_like(mb_substr($query, 0, 100)));
    $limit = max(1, min(50, (int) $limit));

    $rows = (array) db_items("SELECT c.id, c.first_name, c.last_name, c.company, c.email_address, c.business_city, c.business_state, c.business_country,
            (SELECT a.id FROM erp_accounts a WHERE a.contact_id = c.id ORDER BY a.id ASC LIMIT 1) AS account_id
        FROM contacts c
        WHERE c.first_name LIKE '%" . $like . "%'
            OR c.last_name LIKE '%" . $like . "%'
            OR CONCAT(c.first_name, ' ', c.last_name) LIKE '%" . $like . "%'
            OR c.company LIKE '%" . $like . "%'
            OR c.email_address LIKE '%" . $like . "%'
        ORDER BY c.last_name ASC, c.first_name ASC, c.id ASC
        LIMIT " . $limit);

    $results = array();

    foreach ($rows as $row) {
        $person = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));
        $company = trim((string) $row['company']);
        $place = erp_address_from_checkout((string) $row['business_city'], (string) $row['business_state'], (string) $row['business_country']);

        $results[] = array(
            'id' => (int) $row['id'],
            'name' => ($person !== '') ? $person : (($company !== '') ? $company : ('#' . (int) $row['id'])),
            'company' => $company,
            'email' => trim((string) $row['email_address']),
            'city' => $place['city'],
            'account_id' => (int) $row['account_id'],
        );
    }

    return $results;
}

/**
 * Point an account at a contact, or at none.
 *
 * One contact, one account: a contact already linked to another card is
 * refused, because two ledgers for one customer is the mistake the link
 * exists to prevent. Both sides are written - the account's contact_id is
 * the truth, the contact's erp_account_id is the mirror the contact screen
 * reads - and a contact that used to point at this account is let go.
 *
 * @param int $account_id
 * @param int $contact_id  0 to unlink
 * @return array ['success' => bool, 'error' => string]
 */
function erp_account_link_contact($account_id, $contact_id)
{
    $account_id = (int) $account_id;
    $contact_id = (int) $contact_id;

    if (($account_id <= 0) || !is_array(erp_account($account_id))) {
        return array('success' => false, 'error' => lang('The account could not be found.'));
    }

    if ($contact_id > 0) {
        if ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $contact_id . "'") === 0) {
            return array('success' => false, 'error' => lang('The contact could not be found.'));
        }

        $other = db_item("SELECT id, title FROM erp_accounts WHERE contact_id = '" . $contact_id . "' AND id <> '" . $account_id . "' LIMIT 1");

        if (is_array($other)) {
            return array('success' => false, 'error' => lang(array('string' => 'This contact is already linked to the account "{var:1}". Unlink it there first.', 'vars' => $other['title'])));
        }
    }

    if (erp_query("UPDATE erp_accounts SET contact_id = '" . $contact_id . "', updated_at = '" . time() . "' WHERE id = '" . $account_id . "'") === false) {
        return array('success' => false, 'error' => erp_db_error());
    }

    // The mirror: whoever pointed here and is not the new contact lets go;
    // the new contact points here.
    erp_query("UPDATE contacts SET erp_account_id = 0 WHERE erp_account_id = '" . $account_id . "' AND id <> '" . $contact_id . "'");

    if ($contact_id > 0) {
        erp_query("UPDATE contacts SET erp_account_id = '" . $account_id . "' WHERE id = '" . $contact_id . "'");
    }

    return array('success' => true, 'error' => '');
}

/**
 * Give every contact that has ordered something an account.
 *
 * A shop switching the module on has a customer list already and no appetite
 * for typing it again. Only contacts with an order are taken: a newsletter
 * signup is not a ledger account, and creating one for every address in the
 * book would bury the real ones.
 *
 * Orders whose contact row is gone are not candidates: there is no card to
 * read. A contact that exists but names nobody (no name, no company) is
 * counted as skipped, so the screen can say why the button still shows it.
 *
 * @param int $created_by
 * @return array ['created' => int, 'skipped' => int]
 */
function erp_accounts_sync_contacts($created_by = 0)
{
    $rows = (array) db_items("SELECT DISTINCT orders.contact_id
        FROM orders
        INNER JOIN contacts ON contacts.id = orders.contact_id
        LEFT JOIN erp_accounts ON erp_accounts.contact_id = orders.contact_id
        WHERE orders.contact_id > 0 AND erp_accounts.id IS NULL");

    $created = 0;

    foreach ($rows as $row) {
        if (erp_account_for_contact((int) $row['contact_id'], $created_by) > 0) {
            $created++;
        }
    }

    return array('created' => $created, 'skipped' => count($rows) - $created);
}

/**
 * Copy the account onto an invoice as it reads right now.
 *
 * The card is edited afterwards - a company renames itself, moves, changes
 * its tax office - and a document that reads the card live starts saying
 * something it never said. The copy is taken when the document is issued;
 * a draft copies it too, so the editor shows what will be printed, and copies
 * it again on issue. Address, district and postcode are kept apart (4.64):
 * the templates print them on their own lines and the e-document providers
 * ask for them as separate fields, so folding them into one line only had
 * to be undone again by everyone who read it.
 *
 * Runs inside the caller's transaction and never opens one.
 *
 * @param int $invoice_id
 * @param int $account_id
 * @return bool  false when the account is missing or the write failed
 */
function erp_invoice_snapshot_account($invoice_id, $account_id)
{
    $account = erp_account((int) $account_id);

    if (!is_array($account)) {
        return false;
    }

    // The two 4.64 columns are written only where they exist: code lands on a
    // site before its upgrade runs, and a snapshot that skips them is still a
    // usable snapshot.
    $locality = '';

    if (waf_table_has_column('erp_invoices', 'account_district')) {
        $locality = "
            account_district = '" . escape(mb_substr((string) ($account['district'] ?? ''), 0, 100)) . "',
            account_postcode = '" . escape(mb_substr((string) ($account['postcode'] ?? ''), 0, 20)) . "',";
    }

    return (erp_query("UPDATE erp_invoices SET
            account_title = '" . escape(mb_substr((string) $account['title'], 0, 255)) . "',
            account_tax_number = '" . escape(mb_substr((string) $account['tax_number'], 0, 32)) . "',
            account_tax_office = '" . escape(mb_substr((string) $account['tax_office'], 0, 100)) . "',
            account_address = '" . escape(mb_substr(trim((string) ($account['address'] ?? '')), 0, 255)) . "'," . $locality . "
            account_city = '" . escape(mb_substr((string) $account['city'], 0, 100)) . "',
            " . (waf_table_has_column('erp_invoices', 'account_state') ? "account_state = '" . escape(mb_substr((string) ($account['state'] ?? ''), 0, 100)) . "'," : '') . "
            account_country_code = '" . escape(substr((string) $account['country_code'], 0, 2)) . "',
            account_email = '" . escape(mb_substr((string) $account['email'], 0, 255)) . "'
        WHERE id = '" . (int) $invoice_id . "'") !== false);
}

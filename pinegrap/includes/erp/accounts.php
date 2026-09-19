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
        'country_code' => strtoupper(trim((string) ($data['country_code'] ?? erp_default_country_code()))),
        'postcode' => trim((string) ($data['postcode'] ?? '')),
        'currency' => strtoupper(trim((string) ($data['currency'] ?? erp_base_currency()))),
        'contact_id' => (int) ($data['contact_id'] ?? 0),
        'status' => (($data['status'] ?? 'active') === 'passive') ? 'passive' : 'active',
        'notes' => trim((string) ($data['notes'] ?? '')),
        // Days from the invoice date to its due date; 0 leaves it to the
        // store's default term.
        'payment_days' => min(3650, max(0, (int) ($data['payment_days'] ?? 0))),
    );

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

    return array('success' => true, 'id' => (int) mysqli_insert_id(db::$con), 'error' => '');
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
 * @param int $contact_id
 * @param int $created_by
 * @return int  Account id, 0 when the contact does not exist
 */
function erp_account_for_contact($contact_id, $created_by = 0)
{
    $contact_id = (int) $contact_id;

    if ($contact_id <= 0) {
        return 0;
    }

    $existing = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    $contact = db_item("SELECT id, first_name, last_name, company, email_address, business_phone, mobile_phone,
            business_address_1, business_city, business_state, business_zip_code, business_country,
            tax_number, tax_office
        FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1");

    if (!is_array($contact)) {
        return 0;
    }

    $company = trim((string) $contact['company']);
    $person = trim(trim((string) $contact['first_name']) . ' ' . trim((string) $contact['last_name']));

    $result = erp_account_save(array(
        'kind' => 'customer',
        // A company name when there is one, because that is who the invoice is
        // made out to; the person's name otherwise.
        'title' => ($company !== '') ? $company : ($person !== '' ? $person : ('#' . $contact_id)),
        'is_person' => ($company === ''),
        'tax_number' => $contact['tax_number'],
        'tax_office' => $contact['tax_office'],
        'email' => $contact['email_address'],
        'phone' => (trim((string) $contact['business_phone']) !== '') ? $contact['business_phone'] : $contact['mobile_phone'],
        'address' => $contact['business_address_1'],
        'city' => $contact['business_state'],
        'district' => $contact['business_city'],
        'postcode' => $contact['business_zip_code'],
        'country_code' => (trim((string) $contact['business_country']) !== '') ? $contact['business_country'] : erp_default_country_code(),
        'contact_id' => $contact_id,
        'created_by' => $created_by,
    ));

    if (!$result['success']) {
        return 0;
    }

    erp_query("UPDATE contacts SET erp_account_id = '" . (int) $result['id'] . "' WHERE id = '" . $contact_id . "'");

    return (int) $result['id'];
}

/**
 * Give every contact that has ordered something an account.
 *
 * A shop switching the module on has a customer list already and no appetite
 * for typing it again. Only contacts with an order are taken: a newsletter
 * signup is not a ledger account, and creating one for every address in the
 * book would bury the real ones.
 *
 * @param int $created_by
 * @return array ['created' => int, 'existing' => int]
 */
function erp_accounts_sync_contacts($created_by = 0)
{
    $rows = (array) db_items("SELECT DISTINCT orders.contact_id
        FROM orders
        LEFT JOIN erp_accounts ON erp_accounts.contact_id = orders.contact_id
        WHERE orders.contact_id > 0 AND erp_accounts.id IS NULL");

    $created = 0;

    foreach ($rows as $row) {
        if (erp_account_for_contact((int) $row['contact_id'], $created_by) > 0) {
            $created++;
        }
    }

    return array('created' => $created, 'existing' => count($rows) - $created);
}

/**
 * Copy the account onto an invoice as it reads right now.
 *
 * The card is edited afterwards - a company renames itself, moves, changes
 * its tax office - and a document that reads the card live starts saying
 * something it never said. The copy is taken when the document is issued;
 * a draft copies it too, so the editor shows what will be printed, and copies
 * it again on issue. The postcode and district fold into the address line:
 * the document prints the address as one block.
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

    $locality = trim((string) ($account['postcode'] ?? '') . ' ' . (string) ($account['district'] ?? ''));
    $address = trim((string) ($account['address'] ?? ''));
    if ($locality !== '') {
        $address = ($address !== '') ? ($address . ', ' . $locality) : $locality;
    }

    return (erp_query("UPDATE erp_invoices SET
            account_title = '" . escape(mb_substr((string) $account['title'], 0, 255)) . "',
            account_tax_number = '" . escape(mb_substr((string) $account['tax_number'], 0, 32)) . "',
            account_tax_office = '" . escape(mb_substr((string) $account['tax_office'], 0, 100)) . "',
            account_address = '" . escape(mb_substr($address, 0, 255)) . "',
            account_city = '" . escape(mb_substr((string) $account['city'], 0, 100)) . "',
            account_country_code = '" . escape(substr((string) $account['country_code'], 0, 2)) . "',
            account_email = '" . escape(mb_substr((string) $account['email'], 0, 255)) . "'
        WHERE id = '" . (int) $invoice_id . "'") !== false);
}

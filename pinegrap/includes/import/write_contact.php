<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contact writer of the content import framework (includes/fn/
 * importers.php), used by the Shopify customers file.
 *
 * - An address an existing contact holds is not written again: the item is
 *   skipped and named, the existing contact is left as it is.
 * - opt_in is what the export says the person accepted, and a consent log
 *   line (source "import") records it, granted or refused, as exported.
 * - Each tag is a contact group, found by name or made.
 * - Undo deletes the contact as the contact list does, unless a user account
 *   or an order points at it since: then it stays and the report names it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The contacts columns an item may fill.
 *
 * @return string[]
 */
function pg_import_contact_columns()
{
    return array(
        'first_name', 'last_name', 'email_address', 'company', 'business_address_1', 'business_address_2',
        'business_city', 'business_state', 'business_country', 'business_zip_code', 'business_phone',
        'mobile_phone', 'description',
    );
}

/**
 * The column values of an item: known columns only, each cut to its
 * character column's length (none for a text column), an address that is
 * not one dropped. Pure.
 *
 * @param array             $data
 * @param array<string,int> $lengths pg_import_column_lengths() of contacts
 * @return array<string,string>
 */
function pg_import_contact_values($data, $lengths)
{
    $values = array();

    foreach (pg_import_contact_columns() as $column) {
        $value = trim((string) ($data[$column] ?? ''));
        $values[$column] = isset($lengths[$column]) ? mb_substr($value, 0, $lengths[$column]) : $value;
    }

    if (($values['email_address'] !== '') && !filter_var($values['email_address'], FILTER_VALIDATE_EMAIL)) {
        $values['email_address'] = '';
    }

    return $values;
}

/**
 * The contact group of a tag, made when no group has that name.
 *
 * @param string $name
 * @param int    $user_id
 * @return int
 */
function pg_import_contact_group($name, $user_id)
{
    $name = mb_substr(trim((string) $name), 0, 100);
    $id = (int) db_value("SELECT id FROM contact_groups WHERE name = '" . e($name) . "' ORDER BY id LIMIT 1");

    if ($id > 0) {
        return $id;
    }

    db("INSERT INTO contact_groups (name, description, email_subscription, email_subscription_type,
            created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES ('" . e($name) . "', '', '0', 'open',
            '" . (int) $user_id . "', UNIX_TIMESTAMP(), '" . (int) $user_id . "', UNIX_TIMESTAMP())");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * @param array $item
 * @param array $import
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_contact($item, $import)
{
    static $lengths = null;

    if ($lengths === null) {
        $lengths = pg_import_column_lengths(db_items("SHOW COLUMNS FROM contacts"));
    }

    $data = (array) ($item['data'] ?? array());
    $values = pg_import_contact_values($data, $lengths);
    $user_id = (int) ($import['user_id'] ?? 0);

    if (($values['email_address'] === '') && ($values['first_name'] === '') && ($values['last_name'] === '') && ($values['mobile_phone'] === '')) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('The row names nobody.'));
    }

    if (($values['email_address'] !== '') && ((int) db_value("SELECT id FROM contacts WHERE email_address = '" . e($values['email_address']) . "' LIMIT 1") > 0)) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('A contact with this e-mail address exists already.'));
    }

    $opt_in = (!empty($data['opt_in']) && ($values['email_address'] !== '')) ? 1 : 0;
    $sets = array();

    foreach ($values as $column => $value) {
        $sets[] = $column . " = '" . e($value) . "'";
    }

    db("INSERT INTO contacts SET " . implode(', ', $sets) . ",
        opt_in = '" . $opt_in . "',
        user = '" . $user_id . "',
        timestamp = UNIX_TIMESTAMP()");

    $contact_id = (int) mysqli_insert_id(db::$con);

    if ($contact_id <= 0) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The contact could not be saved.'));
    }

    // The answer the shop had, granted or refused, with the import as where
    // it came from; no address of this request is recorded for it.
    if (($values['email_address'] !== '') && function_exists('pg_consent_log_record')) {
        pg_consent_log_record(array(
            'channel'    => 'email',
            'purpose'    => 'marketing',
            'status'     => $opt_in ? 'granted' : 'refused',
            'source'     => 'import',
            'email'      => $values['email_address'],
            'contact_id' => $contact_id,
            'ip'         => '',
            'user_agent' => '',
        ));
    }

    foreach ((array) ($data['tags'] ?? array()) as $tag) {
        $group_id = pg_import_contact_group($tag, $user_id);

        if ($group_id > 0) {
            db("INSERT IGNORE INTO contacts_contact_groups_xref (contact_id, contact_group_id)
                VALUES ('" . $contact_id . "', '" . $group_id . "')");
        }
    }

    pg_audit_record_many('create', 'contacts', pg_audit_created_rows(array($contact_id)));

    return array('result' => 'made', 'id' => $contact_id, 'reason' => '');
}

/**
 * Deletes an imported contact as the contact list does, unless an account or
 * an order points at it now.
 *
 * @param int $contact_id
 * @return true|array{kept:string}
 */
function pg_import_unwrite_contact($contact_id)
{
    $contact_id = (int) $contact_id;
    $row = db_item("SELECT id, first_name, last_name, email_address FROM contacts WHERE id = '" . $contact_id . "'");

    if (!$row) {
        return true;
    }

    $held = (int) db_value("SELECT COUNT(*) FROM user WHERE user_contact = '" . $contact_id . "'")
        + (int) db_value("SELECT COUNT(*) FROM orders WHERE contact_id = '" . $contact_id . "'");

    if ($held > 0) {
        $name = trim($row['first_name'] . ' ' . $row['last_name']);

        return array('kept' => ($name !== '') ? $name : (string) $row['email_address']);
    }

    $before = pg_audit_fetch_many('contacts', array($contact_id));

    db("DELETE FROM contacts WHERE id = '" . $contact_id . "'");
    db("DELETE FROM contacts_contact_groups_xref WHERE contact_id = '" . $contact_id . "'");
    db("DELETE FROM opt_in WHERE contact_id = '" . $contact_id . "'");

    pg_audit_record_many('delete', 'contacts', $before);

    return true;
}

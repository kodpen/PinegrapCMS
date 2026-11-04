<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Quick edit on the contacts list (view_contacts.php): the values the
 * contact screen (edit_contact.php) changes that the list shows, checked by
 * that screen's rules and rights (includes/forms/contact_form.php) and
 * written to the one column each stands for. Values the list computes or
 * gathers (the picture, the connected user, the groups, the duplicates
 * view's address summary, the last change) stay text.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The contacts table column each quick edit field of a view writes. The
 * address views name their own address's columns (business_ or home_); the
 * duplicates view shows both addresses in one cell, which stays text. Pure.
 *
 * @param string $filter pg_contact_list_filter_name()'s answer
 * @return array field => contacts column
 */
function pg_contact_quick_columns($filter)
{
    $columns = array(
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'company' => 'company',
    );

    $prefix = pg_contact_list_address_prefix($filter);

    if ($prefix !== '') {
        foreach (array('address' => 'address_1', 'address_2' => 'address_2', 'city' => 'city', 'state' => 'state', 'zip_code' => 'zip_code', 'country' => 'country') as $field => $column) {
            $columns[$field] = $prefix . $column;
        }

        return $columns;
    }

    $columns += array(
        'business_phone' => 'business_phone',
        'home_phone' => 'home_phone',
        'mobile_phone' => 'mobile_phone',
        'email' => 'email_address',
    );

    if ($filter !== 'all_duplicate_contacts') {
        $columns['opt_in'] = 'opt_in';
    }

    return $columns;
}

/**
 * The contacts list's cells that change in place in a view, with the
 * contact screen's lengths (its boxes' maxlength). Pure.
 *
 * @param string $filter pg_contact_list_filter_name()'s answer
 * @return array field => definition (pg_list_edit_parse())
 */
function pg_contact_quick_edit_columns($filter)
{
    $labels = array(
        'first_name' => lang('First Name'),
        'last_name' => lang('Last Name'),
        'company' => lang('Company'),
        'address' => lang('Address') . ' 1',
        'address_2' => lang('Address') . ' 2',
        'city' => lang('City'),
        'state' => lang('State'),
        'zip_code' => lang('Zip Code'),
        'country' => lang('Country'),
        'business_phone' => lang('Business Phone'),
        'home_phone' => lang('Home Phone'),
        'mobile_phone' => lang('Mobile Phone'),
        'email' => lang('Email'),
    );

    $definitions = array();

    foreach (array_keys(pg_contact_quick_columns($filter)) as $field) {
        if ($field === 'opt_in') {
            $definitions[$field] = array('type' => 'select', 'label' => lang('Opt-In'), 'options' => array('1' => lang('Yes'), '0' => lang('No')));
        } else {
            $max = ($field === 'email') ? 100 : (($field === 'zip_code') ? 10 : 50);
            $definitions[$field] = array('type' => 'text', 'label' => $labels[$field], 'max' => $max);
        }
    }

    return $definitions;
}

/**
 * The value a contact cell's field opens with.
 *
 * @param string $field  a key of pg_contact_quick_edit_columns()
 * @param array  $row    a record of the list's read (pg_contact_list_select())
 *                       or pg_contact_quick_stored()
 * @param string $filter the list's view
 * @return string
 */
function pg_contact_quick_edit_value($field, $row, $filter)
{
    if ($field === 'opt_in') {
        return !empty($row['opt_in']) ? '1' : '0';
    }

    $columns = pg_contact_quick_columns($filter);

    if (!isset($columns[$field])) {
        return '';
    }

    // The list reads an address view's columns under their short names
    // (address_1, city); the stored row has them under their own.
    $short = ($field === 'address') ? 'address_1' : $field;

    return (string) ($row[$short] ?? ($row[$columns[$field]] ?? ''));
}

/**
 * The column one quick edit writes and its new value, or why the contact
 * screen would not save it: an e-mail address that is no address. Pure.
 *
 * @param string   $field  a key of pg_contact_quick_edit_columns()
 * @param string   $value  as pg_list_edit_parse() read it
 * @param string   $filter the list's view
 * @param callable $valid  the address check (validate_email_address(); tests stub it)
 * @return array array('ok' => bool, 'column' => contacts column, 'value' => stored value, 'message' => the refusal)
 */
function pg_contact_quick_change($field, $value, $filter, $valid = 'validate_email_address')
{
    $columns = pg_contact_quick_columns($filter);

    if (!isset($columns[$field])) {
        return array('ok' => false, 'column' => '', 'value' => '', 'message' => lang('This column cannot be changed here.'));
    }

    $value = trim((string) $value);

    if ($field === 'opt_in') {
        $value = ($value === '1') ? '1' : '0';
    }

    if (($field === 'email') && ($value !== '') && !call_user_func($valid, $value)) {
        return array('ok' => false, 'column' => '', 'value' => '', 'message' => lang('The email address is invalid') . '.');
    }

    return array('ok' => true, 'column' => $columns[$field], 'value' => $value, 'message' => '');
}

/**
 * A contact's values the quick edit compares with, logs and answers, under
 * the contacts table's own column names.
 *
 * @param int $contact_id
 * @return array|null
 */
function pg_contact_quick_stored($contact_id)
{
    $result = db_q(
        "SELECT
            id, first_name, last_name, company, email_address, opt_in,
            business_phone, home_phone, mobile_phone,
            business_address_1, business_address_2, business_city, business_state, business_zip_code, business_country,
            home_address_1, home_address_2, home_city, home_state, home_zip_code, home_country
        FROM contacts
        WHERE id = ?", array((string) (int) $contact_id));
    $stored = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($stored) ? $stored : null;
}

/**
 * Write one quick edit from the contacts list by the contact screen's
 * rules: the editor reaches the contact (validate_contact_access()), an
 * e-mail address is valid, and a new address or opt-in status is given to
 * every contact with that address as the screen's save gives it
 * (pg_contact_form_sync_opt_in()). The row is stamped, the audit trail and
 * the site log told. The answer draws the row as the list does, in the
 * view it shows: a name change redraws the picture's letters too.
 *
 * @param array  $user       the signed-in user
 * @param int    $contact_id the contact changed
 * @param string $field      a key of pg_contact_quick_edit_columns()
 * @param string $value      as pg_list_edit_parse() read it
 * @param string $filter     the list's view
 * @return array the answer for pg_list_edit_reply()
 */
function pg_contact_quick_update($user, $contact_id, $field, $value, $filter)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => $message);
    };

    $stored = pg_contact_quick_stored($contact_id);

    if (!$stored) {
        return $refuse(lang('The contact could not be found.'), 'not_found');
    }

    $contact_id = (int) $stored['id'];

    if (!validate_contact_access($user, $contact_id)) {
        return $refuse(lang('Access denied.'), 'denied');
    }

    $was = pg_contact_quick_edit_value($field, $stored, $filter);

    // The same value again writes nothing and logs nothing.
    if ($was !== (string) $value) {
        $change = pg_contact_quick_change($field, (string) $value, $filter);

        if (!$change['ok']) {
            return $refuse($change['message']);
        }

        $audit_before = pg_audit_fetch('contacts', $contact_id);

        // The column comes from pg_contact_quick_change(), never from the request.
        $updated = db_q(
            "UPDATE contacts SET " . $change['column'] . " = ?, user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?",
            array($change['value'], (string) (int) $user['id'], (string) $contact_id));

        if ($updated === false) {
            return $refuse(lang('The change could not be saved.'), 'failed');
        }

        $after = pg_contact_quick_stored($contact_id) ?: $stored;

        // The other contacts with this address take its opt-in status.
        if (($field === 'email') || ($field === 'opt_in')) {
            pg_contact_form_sync_opt_in($contact_id, (string) $after['email_address'], (string) $after['opt_in']);
        }

        pg_audit_record('update', 'contacts', $contact_id, $audit_before);

        log_activity(lang(array('string' => 'contact ({var:1} {var:2}) was modified', 'vars' => array($after['first_name'], $after['last_name']))), $_SESSION['sessionusername'] ?? '');

        $stored = $after;
    }

    $now = pg_contact_quick_edit_value($field, $stored, $filter);

    // The row as the list draws it, in the view it shows.
    $source = pg_contact_list_source($user, array('filter' => (string) $filter));
    $row = $source ? pg_list_table_record_row($source, $contact_id) : null;
    $answer = array('ok' => true, 'value' => $now)
        + pg_list_table_edit_answer($row, $field, ($row && isset($row['cells'])) ? array_keys($row['cells']) : array());

    // Out of the view now (an address taken out of My Subscribers, say):
    // the cell still says what was stored.
    if (!isset($answer['html'])) {
        $answer['html'] = ($field === 'opt_in') ? h(($now === '1') ? lang('Yes') : lang('No')) : h($now);
    }

    return $answer;
}

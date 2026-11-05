<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Quick edit on the contacts list (view_contacts.php): the values the
 * contact screen (edit_contact.php) changes that the list shows, checked by
 * that screen's rules and rights (includes/forms/contact_form.php) and
 * written to the one column each stands for; the groups through the
 * screen's group save. Values the list computes or gathers (the picture,
 * the connected user, the duplicates view's address summary, the last
 * change) stay text.
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
 * contact screen's lengths (its boxes' maxlength); the contact groups when
 * the user may put a contact in some (pg_contact_quick_group_options()).
 * Pure.
 *
 * @param string $filter pg_contact_list_filter_name()'s answer
 * @param array  $groups group id => name, the groups field's choices
 * @return array field => definition (pg_list_edit_parse())
 */
function pg_contact_quick_edit_columns($filter, array $groups = array())
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

    if ($groups) {
        $definitions['groups'] = array('type' => 'multiselect', 'label' => lang('Contact Groups'), 'options' => $groups);
    }

    return $definitions;
}

/**
 * The groups the contact screen lets this user tick: the ones they may
 * edit, by name, without a group filled by rules (the screen shows those
 * as chips, never as a switch).
 *
 * @param array $user
 * @return array group id => name
 */
function pg_contact_quick_group_options($user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

    $options = array();

    foreach (pg_contact_form_groups($user) as $group) {
        if (($group['kind'] ?? 'static') !== 'dynamic') {
            $options[(string) $group['id']] = (string) $group['name'];
        }
    }

    return $options;
}

/**
 * The groups field's value: the contact's groups among the choices, in the
 * choices' order, comma-separated. A group the user may not tick (another
 * user's, one filled by rules) is not part of it and the save leaves it
 * alone. Pure.
 *
 * @param array $member_ids the ids of every group the contact is in
 * @param array $options    group id => name
 * @return string
 */
function pg_contact_quick_group_value(array $member_ids, array $options)
{
    $member_ids = array_map('strval', $member_ids);
    $value = array();

    foreach (array_keys($options) as $id) {
        if (in_array((string) $id, $member_ids, true)) {
            $value[] = (string) $id;
        }
    }

    return implode(',', $value);
}

/**
 * What the contact screen would post for its group switches when the list
 * picks these groups: a switch on for each chosen group, each group's
 * opt-in switch as stored (on for a group the contact joins, as on the
 * screen). Groups that are not choices post nothing; the save skips the
 * ones the user may not edit. Pure.
 *
 * @param array $chosen  group ids, as pg_list_edit_parse() read them
 * @param array $options group id => name
 * @param array $states  pg_contact_form_group_states()
 * @return array post field => value
 */
function pg_contact_quick_groups_post(array $chosen, array $options, array $states)
{
    $post = array();
    $chosen = array_map('strval', $chosen);

    foreach (array_keys($options) as $id) {
        $id = (string) $id;

        if (in_array($id, $chosen, true)) {
            $post['contact_group_' . $id] = '1';
        }

        $post['contact_group_opt_in_' . $id] = (($states[$id]['opt_in'] ?? ($states[(int) $id]['opt_in'] ?? true)) ? '1' : '');
    }

    return $post;
}

/**
 * The ids of every group a contact is in.
 *
 * @param int $contact_id
 * @return string[]
 */
function pg_contact_quick_member_ids($contact_id)
{
    return array_map('strval', (array) db_values("SELECT contact_group_id FROM contacts_contact_groups_xref WHERE contact_id = '" . (int) $contact_id . "'"));
}

/**
 * Writes the groups picked on the list the way the contact screen saves
 * its group switches (pg_contact_form_save_groups()): a User keeps the
 * contact in one of their groups, joins and leaves are announced, the
 * contact is stamped and the site log told.
 *
 * @param array $user
 * @param array $stored  pg_contact_quick_stored()
 * @param array $chosen  group ids
 * @param array $options pg_contact_quick_group_options()
 * @return string '' when saved (or nothing changed), else the refusal
 */
function pg_contact_quick_groups_write($user, array $stored, array $chosen, array $options)
{
    $contact_id = (int) $stored['id'];

    if (pg_contact_quick_group_value(pg_contact_quick_member_ids($contact_id), $options) === implode(',', $chosen)) {
        return '';
    }

    $groups = pg_contact_form_groups($user);
    $post = pg_contact_quick_groups_post($chosen, $options, pg_contact_form_group_states($contact_id, $groups));

    $may_edit = function ($group_id) use ($user) {
        return validate_contact_group_access($user, $group_id) == true;
    };

    if (!pg_contact_form_keeps_a_group($user, $groups, $post, $may_edit)) {
        return lang('Please select at least one contact group for the contact') . '.';
    }

    pg_contact_form_save_groups($user, $contact_id, pg_contact_form_all_groups(), $post);

    db_q("UPDATE contacts SET user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array((string) (int) $user['id'], (string) $contact_id));

    log_activity(lang(array('string' => 'contact ({var:1} {var:2}) was modified', 'vars' => array($stored['first_name'], $stored['last_name']))), $_SESSION['sessionusername'] ?? '');

    return '';
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
 * @param mixed  $value      as pg_list_edit_parse() read it (a list for the groups)
 * @param string $filter     the list's view
 * @param array  $groups     pg_contact_quick_group_options()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_contact_quick_update($user, $contact_id, $field, $value, $filter, array $groups = array())
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

    if ($field === 'groups') {
        $refusal = $groups ? pg_contact_quick_groups_write($user, $stored, (array) $value, $groups) : lang('This column cannot be changed here.');

        if ($refusal !== '') {
            return $refuse($refusal);
        }

        $now = pg_contact_quick_group_value(pg_contact_quick_member_ids($contact_id), $groups);

        return pg_contact_quick_answer($user, $contact_id, $field, $now, $filter);
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

    return pg_contact_quick_answer($user, $contact_id, $field, pg_contact_quick_edit_value($field, $stored, $filter), $filter);
}

/**
 * The answer to a quick edit: the row as the list draws it, in the view it
 * shows, with the field's new value.
 *
 * @param array  $user
 * @param int    $contact_id
 * @param string $field
 * @param string $now    the value the field opens with next
 * @param string $filter
 * @return array
 */
function pg_contact_quick_answer($user, $contact_id, $field, $now, $filter)
{
    // The row as the list draws it, in the view it shows.
    $source = pg_contact_list_source($user, array('filter' => (string) $filter));
    $row = $source ? pg_list_table_record_row($source, $contact_id) : null;
    $answer = array('ok' => true, 'value' => $now)
        + pg_list_table_edit_answer($row, $field, ($row && isset($row['cells'])) ? array_keys($row['cells']) : array());

    // Out of the view now (an address taken out of My Subscribers, say):
    // the cell still says what was stored.
    if (!isset($answer['html'])) {
        $answer['html'] = ($field === 'opt_in') ? h(($now === '1') ? lang('Yes') : lang('No')) : (($field === 'groups') ? '' : h($now));
    }

    return $answer;
}

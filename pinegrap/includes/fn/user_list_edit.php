<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Quick edit on the users list (view_users.php): the values the user
 * screen (edit_user.php) changes one by one, checked by that screen's
 * rules and rights (includes/fn/user_write.php) and written to the one
 * column each stands for. Values the list computes (the role's site
 * rights, folder and group names, the member link, times) stay text.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

/**
 * The User role's rights the list shows as switches: field (the list's
 * column) => the user row's column. Each stores 'yes' or ''.
 *
 * @return array
 */
function pg_user_quick_rights()
{
    return array(
        'manage_calendars' => 'user_manage_calendars',
        'manage_forms' => 'user_manage_forms',
        'view_visitors' => 'user_manage_visitors',
        'manage_contacts' => 'user_manage_contacts',
        'manage_email' => 'user_manage_emails',
        'manage_ecommerce' => 'user_manage_ecommerce',
    );
}

/**
 * The users list's cells that change in place, keyed by the list's
 * columns: the username and e-mail, the role (an administrator or a
 * designer: a manager's user screen sets no role), the start page and the
 * User role's rights whose module is on. Pure.
 *
 * @param int        $editor_role the signed-in user's role
 * @param string     $filter      pg_user_list_filter()'s answer
 * @param array|null $pages       page id => label for the start page (with
 *                                '0' for none); null leaves the field out
 * @param array|null $modules     'calendars', 'forms', 'ecommerce' => bool;
 *                                null reads CALENDARS, FORMS and ECOMMERCE
 * @return array field => definition (pg_list_edit_parse())
 */
function pg_user_quick_edit_columns($editor_role, $filter, $pages = null, $modules = null)
{
    $editor_role = (int) $editor_role;

    if (($editor_role < 0) || ($editor_role > 2)) {
        return array();
    }

    $on = function ($module, $constant) use ($modules) {
        if (is_array($modules)) {
            return !empty($modules[$module]);
        }

        return !defined($constant) || (constant($constant) === true);
    };

    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));

    $columns = array(
        'username' => array('type' => 'text', 'label' => lang('Username'), 'required' => true, 'max' => 100),
        'email' => array('type' => 'text', 'label' => lang('E-mail Address'), 'required' => true, 'max' => 100),
    );

    if ($editor_role <= 1) {
        $roles = array();

        foreach (pg_user_list_role_labels() as $role => $label) {
            if (pg_user_role_writable($editor_role, $role)) {
                $roles[(string) $role] = $label;
            }
        }

        $columns['role'] = array('type' => 'select', 'label' => lang('Role'), 'options' => $roles);
    }

    if (is_array($pages)) {
        $columns['start_page'] = array('type' => 'select', 'label' => lang('User Start Page'), 'options' => $pages);
    }

    $labels = array(
        'manage_calendars' => lang('Calendar Manager'),
        'manage_forms' => lang('Forms Manager'),
        'view_visitors' => lang('Visitor Manager'),
        'manage_contacts' => lang('Contact Manager'),
        'manage_email' => lang('Campaign Manager'),
        'manage_ecommerce' => lang('Commerce Manager'),
    );

    foreach ($labels as $field => $label) {
        if ((($field === 'manage_calendars') && !$on('calendars', 'CALENDARS'))
            || (($field === 'manage_forms') && !$on('forms', 'FORMS'))
            || (($field === 'manage_ecommerce') && !$on('ecommerce', 'ECOMMERCE'))) {
            continue;
        }

        $columns[$field] = array('type' => 'select', 'label' => $label, 'options' => $yes_no);
    }

    return $columns;
}

/**
 * The start pages a user may be given, as the user screen's picker lists
 * them (select_page()): none first, then the pages by name. Read once per
 * request.
 *
 * @return array page id => label
 */
function pg_user_quick_page_choices()
{
    static $pages = null;

    if ($pages === null) {
        $pages = array('0' => '[' . lang('None') . ']');

        foreach (pg_select_page_options('') as $option) {
            $pages[(string) (int) $option['id']] = (string) $option['label'];
        }
    }

    return $pages;
}

/**
 * The quick edit fields of the users list for the signed-in user in a
 * view: the start page's choices are read only for a view that shows it.
 *
 * @param array|null $user   the signed-in user
 * @param string     $filter pg_user_list_filter()'s answer
 * @return array field => definition
 */
function pg_user_quick_edit_columns_for($user, $filter)
{
    if (!is_array($user) || !isset($user['role']) || !is_numeric($user['role'])) {
        return array();
    }

    return pg_user_quick_edit_columns((int) $user['role'], $filter, ($filter !== 'default') ? pg_user_quick_page_choices() : null);
}

/**
 * The value a user cell's field opens with.
 *
 * @param string $field a key of pg_user_quick_edit_columns()
 * @param array  $row   a record of the list's read (username, email, role,
 *                      user_home, manage_contacts, manage_visitors, ...)
 * @return string
 */
function pg_user_quick_edit_value($field, $row)
{
    $aliases = array('view_visitors' => 'manage_visitors', 'manage_email' => 'manage_emails');
    $rights = pg_user_quick_rights();

    if (isset($rights[$field])) {
        return ((string) ($row[$aliases[$field] ?? $field] ?? '') === 'yes') ? '1' : '0';
    }

    switch ($field) {
        case 'username':
            return (string) ($row['username'] ?? '');
        case 'email':
            return (string) ($row['email'] ?? '');
        case 'role':
            return (string) (int) ($row['role'] ?? 3);
        case 'start_page':
            return (string) (int) ($row['user_home'] ?? 0);
    }

    return '';
}

/**
 * Whether a user's cell is a field in a view: a right only on a User's row
 * (the other roles have every right) and only where the view shows it as a
 * switch, the role where the editor may hand out the present one, the
 * start page while the stored one is among the choices. Pure.
 *
 * @param string $field   a key of pg_user_quick_edit_columns()
 * @param array  $row     a record of the list's read
 * @param string $filter  the list's view
 * @param array  $columns pg_user_quick_edit_columns()
 * @return bool
 */
function pg_user_quick_cell_editable($field, $row, $filter, $columns)
{
    if (!isset($columns[$field])) {
        return false;
    }

    $rights = pg_user_quick_rights();

    if (isset($rights[$field])) {
        if ((int) ($row['role'] ?? 0) !== 3) {
            return false;
        }

        return ($filter === 'default') || (($field === 'manage_email') && ($filter === 'my_contact_managers'));
    }

    if (($field === 'role') || ($field === 'start_page')) {
        return isset($columns[$field]['options'][pg_user_quick_edit_value($field, $row)]);
    }

    return true;
}

/**
 * The column one quick edit writes and its new value, or why the user
 * screen would not save it: a username longer than its column, a role the
 * editor may not hand out, the last administrator's role, a right of a role
 * that has every right. Uniqueness is the caller's (pg_user_write_problem()).
 * Pure.
 *
 * @param array  $stored      pg_user_quick_stored()
 * @param string $field       a key of pg_user_quick_edit_columns()
 * @param string $value       as pg_list_edit_parse() read it
 * @param int    $editor_role
 * @param bool   $last_admin  whether the account is the last enabled administrator
 * @return array array('ok' => bool, 'column' => user column, 'value' => stored value, 'message' => the refusal)
 */
function pg_user_quick_change($stored, $field, $value, $editor_role, $last_admin)
{
    $refuse = function ($message) {
        return array('ok' => false, 'column' => '', 'value' => '', 'message' => $message);
    };
    $pass = function ($column, $value) {
        return array('ok' => true, 'column' => $column, 'value' => (string) $value, 'message' => '');
    };

    $rights = pg_user_quick_rights();

    if (isset($rights[$field])) {
        if ((int) ($stored['role'] ?? 0) !== 3) {
            return $refuse(lang('An administrator, designer or manager has every right; only a User\'s rights change here.'));
        }

        return $pass($rights[$field], ((string) $value === '1') ? 'yes' : '');
    }

    switch ($field) {
        case 'username':
            if (!pg_user_form_text_fits($value)) {
                return $refuse(lang(array('string' => '{var:1} is too long.', 'vars' => lang('Username'))));
            }

            return $pass('user_username', $value);

        case 'email':
            return $pass('user_email', $value);

        case 'role':
            $role = pg_user_form_role((string) $value);

            if (($role === null) || !pg_user_role_writable($editor_role, $role) || !pg_user_role_writable($editor_role, (int) ($stored['role'] ?? 0))) {
                return $refuse(lang('Access denied.'));
            }

            if ($last_admin && ((int) ($stored['role'] ?? 3) === 0) && ($role !== 0)) {
                return $refuse(lang('The last administrator keeps the role.'));
            }

            return $pass('user_role', $role);

        case 'start_page':
            return $pass('user_home', (int) $value);
    }

    return $refuse(lang('This column cannot be changed here.'));
}

/**
 * A user's values as the list's read names them, for the quick edit to
 * compare with, log and answer.
 *
 * @param int $user_id
 * @return array|null
 */
function pg_user_quick_stored($user_id)
{
    $result = db_q(
        "SELECT
            user_id, user_username AS username, user_email AS email, user_role AS role, user_home,
            user_manage_contacts AS manage_contacts, user_manage_visitors AS manage_visitors,
            user_manage_ecommerce AS manage_ecommerce, user_manage_forms AS manage_forms,
            user_manage_calendars AS manage_calendars, user_manage_emails AS manage_emails
        FROM user
        WHERE user_id = ?", array((int) $user_id));
    $stored = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($stored) ? $stored : null;
}

/**
 * The grants a right takes along when the user screen saves it switched
 * off: the contact groups once neither contacts nor campaigns are left, the
 * calendars with the calendar right (while Calendars is on: off, the
 * screen leaves them for when it comes back). Pure.
 *
 * @param string $field a right of pg_user_quick_rights()
 * @param array  $after the user's values after the change (pg_user_quick_stored() names)
 * @param bool   $calendars whether Calendars is on
 * @return string the grant table, '' for none
 */
function pg_user_quick_dropped_grants($field, $after, $calendars)
{
    if (($field === 'manage_contacts') || ($field === 'manage_email')) {
        return ((($after['manage_contacts'] ?? '') !== 'yes') && (($after['manage_emails'] ?? '') !== 'yes')) ? 'users_contact_groups_xref' : '';
    }

    if (($field === 'manage_calendars') && $calendars && (($after['manage_calendars'] ?? '') !== 'yes')) {
        return 'users_calendars_xref';
    }

    return '';
}

/**
 * Write one quick edit from the users list by the user screen's rules: the
 * editor reaches only a user of a lower role (an administrator anyone), the
 * username and e-mail stay unique and valid (pg_user_write_problem()), the
 * role is one the editor may hand out and the last administrator keeps it,
 * a right goes to a User only and, switched off, takes its grants as the
 * screen's save does. The row is stamped, the audit trail and the site log
 * told; renaming one's own account renames the session too. The answer
 * draws the row as the list does, in the view it shows.
 *
 * @param array  $user    the signed-in user
 * @param int    $user_id the account changed
 * @param string $field   a key of pg_user_quick_edit_columns()
 * @param string $value   as pg_list_edit_parse() read it
 * @param string $filter  the list's view
 * @return array the answer for pg_list_edit_reply()
 */
function pg_user_quick_update($user, $user_id, $field, $value, $filter = 'default')
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table_edit.php');

    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => $message);
    };

    $editor_role = (int) $user['role'];
    $stored = pg_user_quick_stored($user_id);

    if (!$stored) {
        return $refuse(lang('The user could not be found.'), 'not_found');
    }

    // The user screen's gate: anyone but an administrator edits only users
    // of a lower role.
    if (($editor_role > 0) && ($editor_role >= (int) $stored['role'])) {
        return $refuse(lang('Access denied.'), 'denied');
    }

    $user_id = (int) $stored['user_id'];
    $columns = pg_user_quick_edit_columns_for($user, $filter);
    $was = pg_user_quick_edit_value($field, $stored);
    $notice = '';

    // The same value again writes nothing and logs nothing.
    if ($was !== (string) $value) {
        $last_admin = ($field === 'role') && ((int) $stored['role'] === 0) && pg_user_is_last_admin($user_id);
        $change = pg_user_quick_change($stored, $field, (string) $value, $editor_role, $last_admin);

        if (!$change['ok']) {
            return $refuse($change['message']);
        }

        if (($field === 'username') || ($field === 'email')) {
            $problem = ($field === 'username')
                ? pg_user_write_problem($change['value'], (string) $stored['email'], $user_id)
                : pg_user_write_problem((string) $stored['username'], $change['value'], $user_id);

            if ($problem !== '') {
                return $refuse(pg_user_write_problem_message($problem) . '.');
            }
        }

        $audit_before = pg_audit_fetch('user', $user_id);

        // The column comes from pg_user_quick_change(), never from the request.
        $updated = db_q(
            "UPDATE user SET " . $change['column'] . " = ?, user_user = ?, user_timestamp = UNIX_TIMESTAMP() WHERE user_id = ?",
            array($change['value'], (int) $user['id'], $user_id));

        if ($updated === false) {
            return $refuse(lang('The change could not be saved.'), 'failed');
        }

        $after = pg_user_quick_stored($user_id) ?: $stored;
        $grants = array_key_exists($field, pg_user_quick_rights()) ? pg_user_quick_dropped_grants($field, $after, defined('CALENDARS') && (CALENDARS == true)) : '';

        // The table name comes from pg_user_quick_dropped_grants().
        if (($grants !== '') && ((int) db_value("SELECT COUNT(*) FROM " . $grants . " WHERE user_id = '" . $user_id . "'") > 0)) {
            db_q("DELETE FROM " . $grants . " WHERE user_id = ?", array($user_id));

            $notice = ($grants === 'users_calendars_xref')
                ? lang('The calendars this user could post to went with the right. Choose them again on the user screen when the right is back.')
                : lang('The contact groups this user could reach went with the right. Choose them again on the user screen when the right is back.');
        }

        pg_audit_record('update', 'user', $user_id, $audit_before);

        // The session keeps its own copy of the username from sign-in.
        if (($field === 'username') && ($user_id === (int) $user['id'])) {
            $_SESSION['sessionusername'] = $change['value'];
        }

        log_activity(lang(array('string' => 'user ({var:1}) was modified', 'vars' => $after['username'])), $_SESSION['sessionusername'] ?? '');

        $stored = $after;
    }

    $now = pg_user_quick_edit_value($field, $stored);

    // The row as the list draws it, in the view it shows.
    $source = pg_user_list_source($user, array('filter' => (string) $filter));
    $row = $source ? pg_list_table_record_row($source, $user_id) : null;
    $answer = array('ok' => true, 'value' => $now)
        + pg_list_table_edit_answer($row, $field, ($row && isset($row['cells'])) ? array_keys($row['cells']) : array());

    // Out of the view now (another role's view, say): the cell still says
    // what was stored.
    if (!isset($answer['html'])) {
        $options = $columns[$field]['options'] ?? null;

        if (array_key_exists($field, pg_user_quick_rights())) {
            $answer['html'] = pg_user_list_mark($now === '1')['html'];
        } else {
            $answer['html'] = is_array($options) ? h(trim((string) ($options[$now] ?? ''))) : h($now);
        }
    }

    if ($notice !== '') {
        $answer['notice'] = $notice;
    }

    return $answer;
}

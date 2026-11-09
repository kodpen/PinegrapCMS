<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * User pickers of the panel: the text a picker shows for a user, who may
 * search the users, and the search user_search_action.php answers a
 * server-search combobox (data-pg-source) with. A short list (staff only)
 * is printed whole and searched in the browser; a list that can hold every
 * account asks this search as the operator types.
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
 * A user as a picker names it: "First Last (username)", the username alone
 * when the contact has no name.
 *
 * @param string|null $first_name
 * @param string|null $last_name
 * @param string|null $username
 * @return string
 */
function pg_user_picker_text($first_name, $last_name, $username)
{
    $name = trim(trim((string) $first_name) . ' ' . trim((string) $last_name));
    $username = trim((string) $username);

    if ($name === '') {
        return $username;
    }

    return ($username === '') ? $name : $name . ' (' . $username . ')';
}

/**
 * Whether a viewer of this role may search the users: the right the users
 * list asks for (view_users.php: 'manager', role 2 or above).
 *
 * @param mixed $role the viewer's role number
 * @return bool
 */
function pg_user_picker_may_search($role)
{
    return is_numeric($role) && ((int) $role >= 0) && ((int) $role <= 2);
}

/**
 * The search condition: every word of the query is found in the username,
 * the e-mail address, the first or the last name (the users list's own
 * search, with İ, I, ı and i as one letter when $charset is given), among
 * the accounts the viewer sees on the users list (an administrator every
 * one, a designer or manager the roles below their own), staff only when
 * asked. Null when the viewer may not search or the query has no word.
 *
 * @param string      $query
 * @param mixed       $viewer_role
 * @param bool        $staff_only true keeps the roles below 3
 * @param string|null $charset    the connection charset CONVERT() names
 *                                ('utf8mb4' or 'utf8'), '' for the plain
 *                                match only; null reads the connection's
 * @return array|null array('sql' => condition, 'params' => its values)
 */
function pg_user_picker_search_where($query, $viewer_role, $staff_only = false, $charset = null)
{
    if (!pg_user_picker_may_search($viewer_role) || !is_string($query)) {
        return null;
    }

    $terms = pg_list_search_terms($query);

    if (!$terms) {
        return null;
    }

    if ($charset === null) {
        $charset = pg_list_search_connection_charset();
    }

    $where = pg_list_search_where($terms, array('user.user_username', 'user.user_email', 'contacts.first_name', 'contacts.last_name'), array(), (string) $charset);

    return array(
        'sql' => '(' . $where['sql'] . ')'
            . pg_panel_search_scope_sql('user_role', (int) $viewer_role, 'user.user_role')
            . ($staff_only ? ' AND (user.user_role < 3)' : ''),
        'params' => $where['params']);
}

/**
 * The users a picker offers for a query: at most $limit enabled accounts by
 * username, each as the id the field stores and the text it shows.
 *
 * @param string $query
 * @param mixed  $viewer_role
 * @param array  $options 'staff' => true for the roles below 3,
 *                        'value' => 'username' to store the username
 *                        instead of the user id, 'limit' => 1 to 50 (30)
 * @return array list of array('id' => int|string, 'text' => string)
 */
function pg_user_picker_search($query, $viewer_role, $options = array())
{
    $search = pg_user_picker_search_where($query, $viewer_role, !empty($options['staff']));

    if ($search === null) {
        return array();
    }

    $by_username = (($options['value'] ?? '') === 'username');
    $limit = max(1, min(50, (int) ($options['limit'] ?? 30)));

    $result = db_q(
        "SELECT user.user_id, user.user_username, contacts.first_name, contacts.last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE " . $search['sql'] . pg_user_enabled_sql('user') . "
        ORDER BY user.user_username
        LIMIT " . $limit,
        $search['params']);

    $results = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $results[] = array(
            'id' => $by_username ? (string) $row['user_username'] : (int) $row['user_id'],
            'text' => pg_user_picker_text($row['first_name'], $row['last_name'], $row['user_username']));
    }

    return $results;
}

/**
 * The text of the option a picker prints for its stored user, so the choice
 * stays shown and posted when the user is outside the list: the picker text
 * of the account, "#id (deleted)" for an id with no account left, an
 * unknown username as it is. '' for nothing stored.
 *
 * @param mixed  $value the stored user id or username
 * @param string $by    'id' or 'username'
 * @return string
 */
function pg_user_picker_label($value, $by = 'id')
{
    if (!is_scalar($value) || (trim((string) $value) === '') || (($by === 'id') && ((int) $value <= 0))) {
        return '';
    }

    $row = db_item(
        "SELECT user.user_username, contacts.first_name, contacts.last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE " . (($by === 'username') ? "user.user_username = '" . e((string) $value) . "'" : "user.user_id = '" . (int) $value . "'") . "
        LIMIT 1");

    if (is_array($row) && isset($row['user_username'])) {
        return pg_user_picker_text($row['first_name'], $row['last_name'], $row['user_username']);
    }

    return ($by === 'username') ? (string) $value : '#' . (int) $value . ' ' . lang('(deleted)');
}

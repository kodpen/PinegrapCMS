<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The users list (view_users.php), shared by the screen and the panel action
 * that pages it (user_list_rows, includes/panel/lists.php).
 *
 * The screen draws the heading, the toolbar and the table's columns from
 * pg_user_list_view(); the rows come one page at a time from the action,
 * which builds the same view, counts and reads that page with
 * pg_user_list_page() and writes each row with pg_user_list_row(). The
 * per-row reads (folder rights, calendars, contact groups, sign-in lock) so
 * run for the rows on screen only.
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
 * The name of a role number.
 *
 * @param mixed $role_id
 * @return string|null
 */
function get_role_name($role_id)
{
    $roles = pg_user_list_role_labels();

    return isset($roles[(int) $role_id]) && is_numeric($role_id) ? $roles[(int) $role_id] : null;
}

/**
 * Role number => role name, as the list shows them.
 *
 * @return array
 */
function pg_user_list_role_labels()
{
    return array(
        0 => lang('Administrator'),
        1 => lang('Designer'),
        2 => lang('Manager'),
        3 => lang('User'),
    );
}

/**
 * The filters the screen's filter menu offers to a user of this role.
 *
 * @param int $role the viewer's role
 * @return array filter => label
 */
function pg_user_list_filters($role)
{
    $filters = array(
        'all_my_users' => lang('All My Users'),
        'my_registered_users' => lang('My Registered Users'),
        'my_private_users' => lang('My Private Users'),
        'my_member_users' => lang('My Member Users'),
        'my_content_managers' => lang('My Content Managers'),
        'my_calendar_managers' => lang('My Calendar Managers'),
        'my_submitted_forms_managers' => lang('My Submitted Forms Managers'),
        'my_visitor_report_managers' => lang('My Visitor Report Managers'),
        'my_contact_managers' => lang('My Contact Managers'),
        'my_campaign_managers' => lang('My Campaign Managers'),
        'my_commerce_managers' => lang('My Commerce Managers'),
    );

    if ($role < 1) {
        $filters['all_site_designers'] = lang('All Site Designers');
        $filters['all_site_administrators'] = lang('All Site Administrators');
    }

    if ($role < 2) {
        $filters['all_site_managers'] = lang('All Site Managers');
    }

    return $filters;
}

/**
 * The filter a request names. 'default' (no filter) shows the role and
 * privilege columns; any name the list does not know lists like All My
 * Users, as it always did, and is named so.
 *
 * @param mixed $filter
 * @return string
 */
function pg_user_list_filter($filter)
{
    if (!is_string($filter) || ($filter === '') || ($filter === 'default')) {
        return 'default';
    }

    $known = array_keys(pg_user_list_filters(0));
    $known[] = 'all_site_managers';

    return in_array($filter, $known, true) ? $filter : 'all_my_users';
}

/**
 * Everything the users list needs to know about one filter for one viewer:
 * heading, sort columns, the table's columns in order and the SQL pieces.
 *
 * @param array  $user   the viewer (validate_user())
 * @param string $filter pg_user_list_filter()'s answer
 * @return array
 */
function pg_user_list_view($user, $filter)
{
    $role = (int) $user['role'];
    $is_default = ($filter == 'default');
    $is_site_staff_filter = in_array($filter, array('all_site_administrators', 'all_site_designers', 'all_site_managers'), true);
    $shows_contact = !$is_default && !$is_site_staff_filter;
    $filter_for_links = h('&filter=' . $filter);

    // The columns this view sorts by, in the order the table shows them. The
    // default view shows the role and privilege columns, every other view the
    // e-mail, start page and last modified ones; pg_list_state() falls back
    // to the default for any column the view does not show. The default view
    // has no Last Modified column, but its rows are listed by it until
    // another sort is picked, so it stays in that view's list (last, as it
    // has no heading) for the sort menu to name it.
    $sort_columns = array('username' => lang('Username'));

    if ($is_default) {
        $sort_columns['role'] = lang('Role');
        $sort_columns['member_user'] = lang('Member User');
        $sort_columns['site_manager'] = lang('Site Manager');
        $sort_columns['site_designer'] = lang('Site Designer');
        $sort_columns['last_online'] = lang('Last Online');
        $sort_columns['last_modified'] = lang('Last Modified');
    } else {
        $sort_columns['email'] = lang('E-mail Address');
        $sort_columns['start_page'] = lang('Start Page');
        $sort_columns['last_modified'] = lang('Last Modified');
        $sort_columns['last_online'] = lang('Last Online');
    }

    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // action's JSON body never fills $_REQUEST, so there it reads the kept one.
    $list_state = pg_list_state('view_users', $sort_columns, $sort_default);
    $sort_levels = $list_state['sorts'];

    // What each sort code orders by. Only codes pg_list_state() let through
    // reach pg_list_order_by(), and it writes nothing but these. Site Manager
    // and Site Designer follow the role, the way their check marks do.
    $sort_sql = array(
        'username'      => 'user.user_username',
        'email'         => 'user.user_email',
        'role'          => 'user.user_role',
        'member_user'   => 'contacts.member_id',
        'site_manager'  => 'user.user_role',
        'site_designer' => 'user.user_role',
        'start_page'    => 'start_page.page_name',
        'last_modified' => 'user.user_timestamp',
        'last_online'   => 'user.user_online_timestamp',
    );

    $heading_link = function ($label, $code) use ($sort_levels, $filter_for_links) {
        return get_column_heading($label, $sort_levels, '', $filter_for_links, $code);
    };

    // The table's columns after the check box and the action column, in
    // order: code => heading markup. A row fills the same codes.
    $columns = array('username' => '<th>' . $heading_link(lang('Username'), 'username') . '</th>');
    $center = '<th style="text-align: center">';

    if ($is_default) {
        $columns['role'] = '<th>' . $heading_link(lang('Role'), 'role') . '</th>';
        $columns['private_folder'] = $center . lang('Private User') . '</th>';
        $columns['member_user'] = $center . $heading_link(lang('Member User'), 'member_user') . '</th>';
        $columns['manage_content'] = $center . lang('Content Manager') . '</th>';
        $columns['manage_calendars'] = $center . lang('Calendar Manager') . '</th>';

        if (FORMS === true) {
            $columns['manage_forms'] = $center . lang('Forms Manager') . '</th>';
        }

        $columns['view_visitors'] = $center . lang('Visitor Manager') . '</th>';
        $columns['manage_contacts'] = $center . lang('Contact Manager') . '</th>';
        $columns['manage_email'] = $center . lang('Campaign Manager') . '</th>';

        if (ECOMMERCE === true) {
            $columns['manage_ecommerce'] = $center . lang('Commerce Manager') . '</th>';
        }

        $columns['manage_users'] = $center . $heading_link(lang('Site Manager'), 'site_manager') . '</th>';
        $columns['edit_design'] = $center . $heading_link(lang('Site Designer'), 'site_designer') . '</th>';
    } else {
        $columns['email'] = '<th>' . $heading_link(lang('E-mail Address'), 'email') . '</th>';
        $columns['start_page'] = '<th>' . $heading_link(lang('Start Page'), 'start_page') . '</th>';
    }

    $all_custom_form_pages = array();

    switch ($filter) {
        case 'my_private_users':
            $columns['private_folder'] = ('<th>' . lang('Private Folders') . '</th>');
            break;

        case 'my_content_managers':
            $columns['manage_content'] = ('<th>' . lang('Folders') . '</th>');
            break;

        case 'my_calendar_managers':
            $columns['manage_calendars'] = ('<th>' . lang('Calendars') . '</th>');
            break;

        case 'my_submitted_forms_managers':
            if (FORMS === true) {
                $columns['manage_forms'] = ('<th>' . lang('Custom Forms') . '</th>');
                $all_custom_form_pages = db_items(
                    "SELECT custom_form_pages.form_name, page.page_folder
                    FROM page
                    LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id");
            }
            break;

        case 'my_contact_managers':
            $columns['manage_contacts'] = ('<th>' . lang('Contact Groups') . '</th>');
            $columns['manage_email'] = ('<th class="text-center">' . lang('Send Campaigns') . '</th>');
            break;

        case 'my_campaign_managers':
            $columns['manage_contacts'] = ('<th>' . lang('Contact Groups') . '</th>');
            $columns['manage_email'] = ('<th class="text-center">' . lang('Send Campaigns Only') . '</th>');
            break;
    }

    if ($shows_contact) {
        $columns['user_contact'] = '<th>' . lang('User\'s Contact') . '</th>';
    }

    if (!$is_default) {
        $columns['last_modified'] = '<th>' . $heading_link(lang('Last Modified'), 'last_modified') . '</th>';
    }

    $columns['last_online'] = '<th>' . $heading_link(lang('Last Online'), 'last_online') . '</th>';

    // Where the rows come from, and the condition of the filter.
    $select_id = 'user.user_id';
    $from = 'user';
    $join_user = '';
    $conditions = array();
    $heading = lang('All My Users');
    $subheading = lang('All users that I have access too.');

    $filter_rules = array(
        'my_registered_users' => array('(user.user_contact IS NOT NULL)', lang('My Registered Users'), lang('All my users created through website registration.')),
        'my_private_users' => array("((user.user_role < '3') OR (aclfolder.aclfolder_rights = '1'))", lang('My Private Users'), lang('All my users that have access to view one or more private folders.')),
        'my_member_users' => array("(contacts.member_id != '')", lang('My Member Users'), lang('All my users that are associated with a contact that contains a member id.')),
        'my_content_managers' => array("((user.user_role < '3') OR (aclfolder.aclfolder_rights = '2'))", lang('My Content Managers'), lang('All my users that have edit access to at least one folder.')),
        'my_calendar_managers' => array("((user.user_role < 3) OR (user.user_manage_calendars = 'yes'))", lang('My Calendar Managers'), lang('All my users that can add events to at least one calendar.')),
        'my_submitted_forms_managers' => array("((user.user_role < 3) OR (user.user_manage_forms = 'yes'))", lang('My Submitted Forms Managers'), lang('All my users that can view and edit data collected by at least one custom form.')),
        'my_visitor_report_managers' => array("((user.user_role < 3) OR (user.user_manage_visitors = 'yes'))", lang('My Visitor Report Managers'), lang('All my users that can view and edit all visitor reports.')),
        'my_contact_managers' => array("((user.user_role < 3) OR (user.user_manage_contacts = 'yes'))", lang('My Contact Managers'), lang('All my users that can view and edit all contacts in at least one contact group.')),
        'my_campaign_managers' => array("((user.user_role < 3) OR (user.user_manage_emails = 'yes'))", lang('My Campaign Managers'), lang('All my users that can send e-mail campaigns to one or more contact groups.')),
        'my_commerce_managers' => array("((user.user_role < 3) OR (user.user_manage_ecommerce = 'yes'))", lang('My Commerce Managers'), lang('All my users that can view and edit all products, product groups, offers, and orders.')),
        'all_site_managers' => array('(user.user_role < 3)', lang('All Site Managers'), lang('All users that can update site settings, and create or import other users and grant them privileges.')),
        'all_site_designers' => array('(user.user_role < 2)', lang('All Site Designers'), lang('All users that can update site settings, site designs, and create or import site managers.')),
        'all_site_administrators' => array('(user.user_role < 1)', lang('All Site Administrators'), lang('All site administrator user accounts.')),
    );

    if (isset($filter_rules[$filter])) {
        list($condition, $heading, $subheading) = $filter_rules[$filter];
        $conditions[] = $condition;
    }

    // The folder filters read aclfolder, one row per folder right.
    if (($filter == 'my_private_users') || ($filter == 'my_content_managers')) {
        $select_id = 'DISTINCT user.user_id';
        $from = 'aclfolder';
        $join_user = 'LEFT JOIN user ON aclfolder.aclfolder_user = user.user_id';
    }

    // An administrator sees every user, anyone else only the users with
    // less power.
    $conditions[] = ($role == 0) ? '(user.user_role >= 0)' : '(user.user_role > ' . $role . ')';

    $select = '';

    if ($is_default) {
        $select .= ', contacts.member_id AS user_member_id';
    } else {
        $select .= ', user.user_home, start_page.page_name AS user_start_page';
    }

    if ($shows_contact) {
        $select .= ', contacts.id as contact_id,
            contacts.salutation as contact_salutation,
            contacts.first_name as contact_first_name,
            contacts.last_name as contact_last_name,
            contacts.nickname as contact_nickname,
            contacts.suffix as contact_suffix';
    }

    $joins = $join_user
        . ($is_site_staff_filter ? '' : ' LEFT JOIN contacts ON user.user_contact = contacts.id')
        . ' LEFT JOIN user AS user_2 ON user.user_user = user_2.user_id'
        . ($is_default ? '' : ' LEFT JOIN page AS start_page ON start_page.page_id = user.user_home');

    // The search box looks in the columns the row shows as text.
    $search_columns = array('user.user_username');
    $search_labels = array();

    if ($is_default) {
        $search_labels[] = array('expr' => 'user.user_role', 'labels' => pg_user_list_role_labels());
    } else {
        $search_columns[] = 'user.user_email';
        $search_columns[] = 'start_page.page_name';
        $search_columns[] = 'user_2.user_username';
    }

    if ($shows_contact) {
        foreach (array('nickname', 'salutation', 'first_name', 'last_name', 'suffix') as $contact_column) {
            $search_columns[] = 'contacts.' . $contact_column;
        }
    }

    // Kept so the last administrator cannot be selected for deletion.
    $number_of_administrators = ($role == 0) ? (int) db_value("SELECT COUNT(user_id) FROM user WHERE user_role = '0'") : 0;

    return array(
        'filter' => $filter,
        'filters' => pg_user_list_filters($role),
        'heading' => $heading,
        'subheading' => $subheading,
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $sort_levels,
        'order_by' => pg_list_order_by($sort_levels, $sort_sql) . ', user.user_id asc',
        'columns' => $columns,
        'select_id' => $select_id,
        'select' => $select,
        'from' => $from,
        'joins' => $joins,
        'where' => 'WHERE ' . implode(' AND ', $conditions),
        'search_columns' => $search_columns,
        'search_labels' => $search_labels,
        'all_custom_form_pages' => $all_custom_form_pages,
        'number_of_administrators' => $number_of_administrators,
    );
}

/**
 * One page of the list: the counts without and with the search, and the
 * rows of the page window.
 *
 * @param array $view   pg_user_list_view()'s answer
 * @param array $terms  pg_list_search_terms()'s answer
 * @param array $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_user_list_page($view, $terms, $window)
{
    $base = 'FROM ' . $view['from'] . ' ' . $view['joins'] . ' ' . $view['where'];
    $search = pg_list_search_where($terms, $view['search_columns'], $view['search_labels']);
    $search_sql = ($search['sql'] !== '') ? ' AND ' . $search['sql'] : '';

    $result = db_q('SELECT COUNT(' . $view['select_id'] . ') ' . $base);

    if (!$result) {
        return false;
    }

    $total = (int) mysqli_fetch_row($result)[0];
    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(' . $view['select_id'] . ') ' . $base . $search_sql, $search['params']);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    $result = db_q(
        'SELECT
            ' . $view['select_id'] . ' as id,
            user.user_username as username,
            user.user_email as email,
            user.user_role as role,
            user.user_password_algo as password_algo,
            user.user_manage_contacts as manage_contacts,
            user.user_manage_visitors as manage_visitors,
            user.user_manage_ecommerce as manage_ecommerce,
            user.user_manage_forms as manage_forms,
            user.user_manage_calendars as manage_calendars,
            user.user_manage_emails as manage_emails,
            user.user_contact as user_contact,
            user_2.user_username as user,
            user.user_online_timestamp as last_seen,
            user.user_timestamp as timestamp' . $view['select'] . '
        ' . $base . $search_sql . '
        ORDER BY ' . $view['order_by'] . pg_list_limit_sql($window),
        $search['params']);

    if (!$result) {
        return false;
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    // The sign-in providers of the accounts on this page, in one query.
    $providers = pg_signin_identities_of(array_column($rows, 'id'));
    $list_rows = array();

    foreach ($rows as $row) {
        $list_rows[] = pg_user_list_row($row, $view, $providers[(int) $row['id']] ?? null);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $list_rows);
}

/**
 * The rows of a query with values (db_q()), or none when it failed.
 *
 * @param string $sql
 * @param array  $params
 * @return array list of rows
 */
function pg_user_list_read($sql, $params)
{
    $result = db_q($sql, $params);
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * The first column of a query with values, as strings.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function pg_user_list_names($sql, $params)
{
    return array_map(function ($row) {
        return (string) reset($row);
    }, pg_user_list_read($sql, $params));
}

/**
 * Whether a name is worth listing.
 *
 * @param mixed $name
 * @return bool
 */
function pg_user_list_not_empty($name)
{
    return (string) $name !== '';
}

/**
 * A drop-down of names in a cell (folders, calendars, forms, groups).
 *
 * @param array  $names already escaped
 * @param string $icon  material icon name
 * @param string $label the menu button's accessible name (the ligature is hidden)
 * @return array pg_list_cell()
 */
function pg_user_list_names_cell($names, $icon, $label)
{
    // Nothing assigned: a dash, not a button that opens an empty menu.
    if (!$names) {
        return pg_list_cell('&mdash;', 'align-middle text-center');
    }

    $items = '';

    foreach ($names as $name) {
        $items .= '<li><span class="menu-text badge fg-body fw-light py-3" >' . $name . '</span></li>';
    }

    return pg_list_cell(
        '<div class="row row-cols-2 align-middle">
            <div class="pg-menu-wrap col">
                <button type="button" class="m-1 btn btn-sm p-1 no-popover pg-menu-caret" data-bs-auto-close="outside" data-bs-strategy="fixed" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h($label) . '"><span class="material-icons d-inline" aria-hidden="true">' . $icon . '</span></button>
                <ul class="menu">' . $items . '</ul>
            </div>
        </div>',
        'align-middle text-center');
}

/**
 * A yes/no cell: the check mark's ligature is hidden and "Yes" is read in
 * its place.
 *
 * @param bool $yes
 * @return array pg_list_cell()
 */
function pg_user_list_mark($yes)
{
    return pg_list_cell($yes
        ? '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>'
        : '&nbsp;', 'align-middle text-center');
}

/**
 * A row's selection checkbox, named after the user. The last administrator
 * cannot be deleted, so its box is drawn disabled.
 *
 * @param int    $id
 * @param string $username
 * @param bool   $selectable
 * @return array pg_list_cell()
 */
function pg_user_list_select_cell($id, $username, $selectable)
{
    $name = ' aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => (string) $username))) . '"';

    return pg_list_cell($selectable
        ? ' <input class="check" type="checkbox" name="users[]" value="' . (int) $id . '"' . $name . ' />'
        : '<input type="checkbox" name="users[]" value="' . (int) $id . '" class="check disabled" disabled="disabled"' . $name . ' />',
        'select-all align-middle text-start');
}

/**
 * One row of the users list.
 *
 * @param array      $row       a row of pg_user_list_page()'s query
 * @param array      $view      pg_user_list_view()'s answer
 * @param array|null $providers the account's sign-in providers
 *                              (pg_signin_identities_of())
 * @return array array('class', 'cells') for pg_list_row_html() / pg_list_row_data()
 */
function pg_user_list_row($row, $view, $providers)
{
    $filter = $view['filter'];
    $id = (int) $row['id'];
    $role = $row['role'];

    $manage = array();

    // An administrator, designer or manager has every one of these rights.
    foreach (array('contacts', 'visitors', 'ecommerce', 'forms', 'emails', 'calendars') as $right) {
        $manage[$right] = ($role < 3) || ($row['manage_' . $right] == 'yes');
    }

    $cells = array();
    $username_cell = h($row['username']);

    // Sign-in lockout, shown beside the name rather than in a column of its
    // own: it is empty on almost every row, and a column of blanks costs a
    // heading and a width on every screen to say nothing.
    $sign_in_lock = pg_login_lock_state($row['username'], $row['email']);

    if ($sign_in_lock['locked']) {
        $minutes = max(1, (int) ceil(($sign_in_lock['until'] - time()) / 60));
        // Printed beside the badge, not in a title: these rows are injected
        // by the server pager, where a hover hint reaches neither keyboard,
        // touch nor screen-reader users.
        $username_cell .= ' <span class="badge theme-danger fw-light">' . lang('Locked') . '</span>'
            . ' <span class="small fg-secondary">' . h(lang(array(
            'string' => 'Clears in {var:1} minute{suffix:1}.',
            'vars'   => $minutes,
            'suffix' => ($minutes == 1) ? '' : 's'))) . '</span>';
    }

    // Google, Microsoft and Apple badges beside the name, next to the
    // sign-in lock, so the admin can see at a glance which accounts can sign
    // in with which provider, and which have no other way in.
    if ($providers !== null) {
        $username_cell .= ' ' . pg_signin_identity_badges($providers, $row['password_algo']);
    }

    $cells['username'] = pg_list_cell($username_cell);

    if ($filter == 'default') {
        $cells['role'] = pg_list_cell('<span class="badge theme-secondary fw-light">' . get_role_name($role) . '</span>');
        $cells['member_user'] = pg_user_list_mark(($row['user_member_id'] ?? '') != '');

        $private_user = db_q("SELECT DISTINCT aclfolder_user FROM aclfolder WHERE ((aclfolder_user = ?) AND (aclfolder_rights = 1))", array((string) $id));
        $private_user = $private_user ? mysqli_fetch_assoc($private_user) : null;
        $cells['private_folder'] = pg_user_list_mark(($role < 3) || (isset($private_user['aclfolder_user']) && ($private_user['aclfolder_user'] != '')));
        $cells['manage_content'] = pg_user_list_mark(($role < 3) || (no_acl_check($id) == true));
        $cells['manage_calendars'] = pg_user_list_mark($manage['calendars']);
        $cells['manage_forms'] = pg_user_list_mark($manage['forms']);
        $cells['view_visitors'] = pg_user_list_mark($manage['visitors']);
        $cells['manage_contacts'] = pg_user_list_mark($manage['contacts']);
        $cells['manage_ecommerce'] = pg_user_list_mark($manage['ecommerce']);
        $cells['manage_users'] = pg_user_list_mark($role < 3);
        $cells['edit_design'] = pg_user_list_mark($role <= 1);
    } else {
        $last_modified_by = ($row['user'] != '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($row['user'])))) : ' ';
        $cells['email'] = pg_list_cell(h($row['email']));
        $cells['start_page'] = pg_list_cell(($row['user_home'] != 0) ? h($row['user_start_page']) : '');
        $cells['last_modified'] = pg_list_cell(get_relative_time(array('timestamp' => $row['timestamp'])) . $last_modified_by);
    }

    if (isset($view['columns']['user_contact'])) {
        if (($row['contact_nickname'] ?? '') != '') {
            $contact_name = h($row['contact_nickname']);
        } else {
            $contact_name = '';

            if (($row['contact_salutation'] ?? '') != '') {
                $contact_name = h($row['contact_salutation']) . ' ';
            }

            if (($row['contact_first_name'] ?? '') != '') {
                $contact_name .= h($row['contact_first_name']) . ' ';
            }

            if (($row['contact_last_name'] ?? '') != '') {
                $contact_name .= h($row['contact_last_name']);
            }

            if (($row['contact_suffix'] ?? '') != '') {
                $contact_name .= ' ' . h($row['contact_suffix']);
            }
        }

        $cells['user_contact'] = ($contact_name != '')
            ? pg_list_cell('<a class="btn btn-link pg-link-secondary py-0 m-1" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . (int) $row['contact_id'] . '">' . $contact_name . '</a>', '')
            : pg_list_cell('&nbsp;');
    }

    $all = pg_list_cell(lang('All'), 'align-middle text-center');

    switch ($filter) {
        case 'my_private_users':
            $cells['private_folder'] = ($role < 3) ? $all : pg_user_list_names_cell(array_map('h', pg_user_list_names(
                "SELECT folder.folder_name
                FROM folder
                LEFT JOIN aclfolder ON aclfolder.aclfolder_folder = folder.folder_id
                WHERE ((aclfolder_user = ?) AND (aclfolder_rights = '1'))
                ORDER BY folder_name ASC", array((string) $id))), 'folder', lang('Folders'));
            break;

        case 'my_content_managers':
            if ($role < 3) {
                $cells['manage_content'] = pg_list_cell(lang('All Pages and Files'));
            } else {
                $names = array();
                $folders = pg_user_list_read(
                    "SELECT folder.folder_name, folder.folder_parent
                    FROM folder
                    LEFT JOIN aclfolder ON aclfolder.aclfolder_folder = folder.folder_id
                    WHERE ((aclfolder_user = ?) AND (aclfolder_rights = '2'))
                    ORDER BY folder_name ASC", array((string) $id));

                // The list stops at the top folder: below it, every folder is in.
                foreach ($folders as $folder) {
                    $names[] = h($folder['folder_name']);

                    if ($folder['folder_parent'] == 0) {
                        break;
                    }
                }

                $cells['manage_content'] = pg_user_list_names_cell($names, 'folder', lang('Folders'));
            }
            break;

        case 'my_calendar_managers':
            $cells['manage_calendars'] = ($role < 3) ? $all : pg_user_list_names_cell(array_map('h', pg_user_list_names(
                "SELECT calendars.name
                FROM users_calendars_xref
                LEFT JOIN calendars ON calendars.id = users_calendars_xref.calendar_id
                WHERE users_calendars_xref.user_id = ?", array((string) $id))), 'today', lang('Calendars'));
            break;

        case 'my_submitted_forms_managers':
            if ($role < 3) {
                $cells['manage_forms'] = $all;
            } else {
                $names = array();
                $folders = get_folders_that_user_has_access_to($id);

                foreach ($view['all_custom_form_pages'] as $custom_form_page) {
                    if (in_array($custom_form_page['page_folder'], $folders) && ($custom_form_page['form_name'] != '')) {
                        $names[] = h($custom_form_page['form_name']);
                    }
                }

                $cells['manage_forms'] = pg_user_list_names_cell($names, 'list_alt', lang('Forms'));
            }
            break;

        case 'my_campaign_managers':
            $cells['manage_email'] = pg_user_list_mark(($role == 3) && ($row['manage_emails'] == 'yes') && ($row['manage_contacts'] != 'yes'));
            break;
    }

    if (($filter == 'my_contact_managers') || ($filter == 'my_campaign_managers')) {
        $cells['manage_contacts'] = ($role < 3) ? $all : pg_user_list_names_cell(array_map('h', array_filter(pg_user_list_names(
            "SELECT contact_groups.name
            FROM users_contact_groups_xref
            LEFT JOIN contact_groups ON contact_groups.id = users_contact_groups_xref.contact_group_id
            WHERE users_contact_groups_xref.user_id = ?", array((string) $id)), 'pg_user_list_not_empty')), 'groups', lang('Contact Groups'));
    }

    if (($filter == 'default') || ($filter == 'my_contact_managers')) {
        $cells['manage_email'] = pg_user_list_mark($manage['emails']);
    }

    $cells['last_online'] = pg_list_cell(($row['last_seen'] >= 1) ? get_relative_time(array('timestamp' => $row['last_seen'])) . ' ' : '[' . lang('Never') . ']');

    // The last administrator cannot be deleted, so it cannot be selected.
    $selectable = ($role != 0) || ($view['number_of_administrators'] > 1);
    $edit_url = 'edit_user.php?id=' . $id . '&send_to=' . h(escape_javascript(urlencode(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php?filter=' . $view['filter'])));

    $row_cells = array(
        pg_user_list_select_cell($id, $row['username'], $selectable),
        pg_list_cell('<button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $edit_url . '\'"><i class="bi bi-pencil"></i></button>',
            'align-middle text-start action-buttons'),
    );

    foreach (array_keys($view['columns']) as $code) {
        $row_cells[] = $cells[$code] ?? pg_list_cell('&nbsp;');
    }

    return array('class' => $selectable ? '' : 'unselectable', 'cells' => $row_cells);
}

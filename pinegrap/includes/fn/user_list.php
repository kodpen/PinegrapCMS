<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The users list (view_users.php) on the list table driver
 * (includes/fn/list_table.php): its source (pg_user_list_source()) serves
 * the screen, the driver's list_table_rows action and Delete Selected
 * (delete_users.php) alike.
 *
 * The screen prints the first rows; the next ones come in batches as the
 * list scrolls, each row written by pg_user_list_row(). The per-row reads
 * (folder rights, calendars, contact groups, sign-in lock) so run for the
 * rows loaded only.
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
 * The filters the screen's filter menu offers to a user of this role. A module
 * switched off in Site Settings loses its filter, as the main menu loses its
 * row; pg_user_list_filter() still accepts the name from the address.
 *
 * @param int $role the viewer's role
 * @param array|null $modules 'calendars', 'forms', 'ecommerce' => bool; null
 *                            reads CALENDARS, FORMS and ECOMMERCE (a constant
 *                            that is not defined counts as on)
 * @return array filter => label
 */
function pg_user_list_filters($role, $modules = null)
{
    if ($modules === null) {
        $modules = array(
            'calendars' => !defined('CALENDARS') || (constant('CALENDARS') === true),
            'forms' => !defined('FORMS') || (constant('FORMS') === true),
            'ecommerce' => !defined('ECOMMERCE') || (constant('ECOMMERCE') === true),
        );
    }

    $filters = array(
        'default' => lang('Roles and Permissions'),
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

    $module_filters = array(
        'calendars' => 'my_calendar_managers',
        'forms' => 'my_submitted_forms_managers',
        'ecommerce' => 'my_commerce_managers',
    );

    foreach ($module_filters as $module => $filter) {
        if (empty($modules[$module])) {
            unset($filters[$filter]);
        }
    }

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
 * The condition, heading and subheading of a named filter. The folder filters
 * ask aclfolder in a subquery, so the list still reads one row per user and an
 * administrator, designer or manager (every folder) is listed without a
 * folder right of their own.
 *
 * @param string $filter pg_user_list_filter()'s answer
 * @return array|null array(condition, heading, subheading); null for
 *                    'default' and 'all_my_users'
 */
function pg_user_list_filter_rule($filter)
{
    $folder_right = function ($rights) {
        return "((user.user_role < '3') OR EXISTS (SELECT 1 FROM aclfolder WHERE (aclfolder.aclfolder_user = user.user_id) AND (aclfolder.aclfolder_rights = '" . $rights . "')))";
    };

    $rules = array(
        'my_registered_users' => array('(user.user_contact > 0)', lang('My Registered Users'), lang('All my users created through website registration.')),
        'my_private_users' => array($folder_right('1'), lang('My Private Users'), lang('All my users that have access to view one or more private folders.')),
        'my_member_users' => array("(contacts.member_id != '')", lang('My Member Users'), lang('All my users that are associated with a contact that contains a member id.')),
        'my_content_managers' => array($folder_right('2'), lang('My Content Managers'), lang('All my users that have edit access to at least one folder.')),
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

    return $rules[(string) $filter] ?? null;
}

/**
 * The users list's columns for the list table driver, in the order the
 * table shows them, each with the SQL it sorts by. The default view shows
 * the role and the rights; every other view the e-mail and start page, and
 * the right it is about as names (folders, calendars, forms, groups). The
 * default view is listed by last change without showing it: its Last
 * Modified column is hidden, so the sort keeps a column to name.
 *
 * @param string $filter pg_user_list_filter()'s answer
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_user_list_columns($filter)
{
    $is_default = ($filter == 'default');
    $is_site_staff_filter = in_array($filter, array('all_site_administrators', 'all_site_designers', 'all_site_managers'), true);

    $columns = array(
        'username' => array('heading' => lang('Username'), 'width' => '16rem', 'kind' => 'link', 'sort' => 'user.user_username', 'editable' => 'text'),
    );

    if ($is_default) {
        $columns['role'] = array('heading' => lang('Role'), 'width' => '10rem', 'kind' => 'badge', 'sort' => 'user.user_role', 'editable' => 'select');
        $columns['private_folder'] = array('heading' => lang('Private User'), 'kind' => 'check');
        $columns['member_user'] = array('heading' => lang('Member User'), 'kind' => 'check', 'sort' => 'contacts.member_id');
        $columns['manage_content'] = array('heading' => lang('Content Manager'), 'kind' => 'check');

        if (defined('CALENDARS') && (CALENDARS === true)) {
            $columns['manage_calendars'] = array('heading' => lang('Calendar Manager'), 'kind' => 'check', 'editable' => 'select');
        }

        if (defined('FORMS') && (FORMS === true)) {
            $columns['manage_forms'] = array('heading' => lang('Forms Manager'), 'kind' => 'check', 'editable' => 'select');
        }

        $columns['view_visitors'] = array('heading' => lang('Visitor Manager'), 'kind' => 'check', 'editable' => 'select');
        $columns['manage_contacts'] = array('heading' => lang('Contact Manager'), 'kind' => 'check', 'editable' => 'select');
        $columns['manage_email'] = array('heading' => lang('Campaign Manager'), 'kind' => 'check', 'editable' => 'select');

        if (defined('ECOMMERCE') && (ECOMMERCE === true)) {
            $columns['manage_ecommerce'] = array('heading' => lang('Commerce Manager'), 'kind' => 'check', 'editable' => 'select');
        }

        // Site Manager and Site Designer follow the role, the way their
        // check marks do.
        $columns['site_manager'] = array('heading' => lang('Site Manager'), 'kind' => 'check', 'sort' => 'user.user_role');
        $columns['site_designer'] = array('heading' => lang('Site Designer'), 'kind' => 'check', 'sort' => 'user.user_role');
    } else {
        $columns['email'] = array('heading' => lang('E-mail Address'), 'width' => '16rem', 'sort' => 'user.user_email', 'editable' => 'text');
        $columns['start_page'] = array('heading' => lang('Start Page'), 'width' => '12rem', 'sort' => 'start_page.page_name', 'editable' => 'select');
    }

    switch ($filter) {
        case 'my_private_users':
            $columns['private_folder'] = array('heading' => lang('Private Folders'), 'width' => '9rem');
            break;

        case 'my_content_managers':
            $columns['manage_content'] = array('heading' => lang('Folders'), 'width' => '9rem');
            break;

        case 'my_calendar_managers':
            $columns['manage_calendars'] = array('heading' => lang('Calendars'), 'width' => '9rem');
            break;

        case 'my_submitted_forms_managers':
            if (defined('FORMS') && (FORMS === true)) {
                $columns['manage_forms'] = array('heading' => lang('Custom Forms'), 'width' => '9rem');
            }
            break;

        case 'my_contact_managers':
            $columns['manage_contacts'] = array('heading' => lang('Contact Groups'), 'width' => '9rem');
            $columns['manage_email'] = array('heading' => lang('Send Campaigns'), 'kind' => 'check', 'editable' => 'select');
            break;

        case 'my_campaign_managers':
            $columns['manage_contacts'] = array('heading' => lang('Contact Groups'), 'width' => '9rem');
            $columns['manage_email'] = array('heading' => lang('Send Campaigns Only'), 'kind' => 'check');
            break;
    }

    if (!$is_default && !$is_site_staff_filter) {
        $columns['user_contact'] = array('heading' => lang('User\'s Contact'), 'width' => '12rem');
    }

    if (!$is_default) {
        $columns['last_modified'] = array('heading' => lang('Last Modified'), 'width' => '12rem', 'kind' => 'date', 'sort' => 'user.user_timestamp');
    }

    $columns['last_online'] = array('heading' => lang('Last Online'), 'width' => '10rem', 'kind' => 'date', 'sort' => 'user.user_online_timestamp');

    if ($is_default) {
        $columns['last_modified'] = array('heading' => lang('Last Modified'), 'width' => '12rem', 'kind' => 'date', 'sort' => 'user.user_timestamp', 'hidden' => true);
    }

    $columns['actions'] = array('heading' => lang('Action'), 'width' => '5rem', 'kind' => 'actions');

    return $columns;
}

/**
 * Everything the users list needs to know about one filter for one viewer:
 * heading, columns, sort and the SQL pieces.
 *
 * @param array  $user   the viewer (validate_user())
 * @param string $filter pg_user_list_filter()'s answer
 * @return array
 */
function pg_user_list_view($user, $filter)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list_edit.php');

    $role = (int) $user['role'];
    $is_default = ($filter == 'default');
    $is_site_staff_filter = in_array($filter, array('all_site_administrators', 'all_site_designers', 'all_site_managers'), true);
    $shows_contact = !$is_default && !$is_site_staff_filter;

    $columns = pg_user_list_columns($filter);
    $sort_columns = pg_list_table_sort_columns(pg_list_table_columns($columns));
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // rows action applies the one the browser sends (pg_list_table_apply_sort()).
    $list_state = pg_list_state('view_users', $sort_columns, $sort_default);

    // The forms view names the forms of the folders a user may edit; every
    // form page is read once for the whole list.
    $all_custom_form_pages = array();

    if (($filter == 'my_submitted_forms_managers') && defined('FORMS') && (FORMS === true)) {
        $all_custom_form_pages = db_items(
            "SELECT custom_form_pages.form_name, page.page_folder
            FROM page
            LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id");
    }

    // The condition of the filter.
    $conditions = array();
    $heading = $is_default ? lang('Roles and Permissions') : lang('All My Users');
    $subheading = $is_default ? lang('All users that I have access to, with their roles and permissions.') : lang('All users that I have access to.');

    $rule = pg_user_list_filter_rule($filter);

    if ($rule !== null) {
        list($condition, $heading, $subheading) = $rule;
        $conditions[] = $condition;
    }

    // An administrator sees every user, anyone else only the users with
    // less power.
    $conditions[] = ($role == 0) ? '(user.user_role >= 0)' : '(user.user_role > ' . $role . ')';

    $select = '';

    if ($is_default) {
        $select .= ', contacts.member_id AS user_member_id';
    } else {
        $select .= ', start_page.page_name AS user_start_page';
    }

    if ($shows_contact) {
        $select .= ', contacts.id as contact_id,
            contacts.salutation as contact_salutation,
            contacts.first_name as contact_first_name,
            contacts.last_name as contact_last_name,
            contacts.nickname as contact_nickname,
            contacts.suffix as contact_suffix';
    }

    // The default view sorts by the member id, so it joins the contact too.
    $from = 'user'
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

    return array(
        'filter' => $filter,
        'filters' => pg_user_list_filters($role),
        'heading' => $heading,
        'subheading' => $subheading,
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $list_state['sorts'],
        'columns' => $columns,
        'select' => $select,
        'from' => $from,
        'where' => implode(' AND ', $conditions),
        'search_columns' => $search_columns,
        'search_labels' => $search_labels,
        'all_custom_form_pages' => $all_custom_form_pages,
        // The cells that change in place (pg_user_quick_edit_columns()).
        'quick_edit' => pg_user_quick_edit_columns_for($user, $filter),
    );
}

/**
 * The users list as the list table driver's source
 * (includes/fn/list_table.php), for the screen, the rows action and Delete
 * Selected alike. It is the screen's gate too: a manager or above
 * (validate_area_access($user, 'manager')).
 *
 * @param array $user   the signed-in user
 * @param array $params the list's parameters: 'filter' (the view)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_user_list_source($user, $params)
{
    if (!isset($user['role']) || !is_numeric($user['role']) || ((int) $user['role'] > 2)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $filter = pg_user_list_filter((is_array($params) && isset($params['filter'])) ? $params['filter'] : 'default');
    $view = pg_user_list_view($user, $filter);

    return pg_list_table_source(array(
        'list' => 'view_users',
        'columns' => $view['columns'],
        'from' => $view['from'],
        'where' => $view['where'],
        'id' => 'user.user_id',
        'id_order' => 'asc',
        'select' => 'user.user_username as username,
            user.user_email as email,
            user.user_role as role,
            user.user_home,
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
            user.user_timestamp as timestamp' . (pg_user_has_disabled_at() ? ',
            user.user_disabled_at' : '') . $view['select'],
        'search' => array('columns' => $view['search_columns'], 'labels' => $view['search_labels']),
        'levels' => $view['sort_levels'],
        // A whole-view selection leaves out the accounts made after it
        // (accounts older than the column read 0, so they stay in).
        'created' => pg_schema_has('user', 'user_created') ? 'user.user_created' : '',
        'rows' => function ($records) use ($view) {
            return pg_user_list_rows($view, $records);
        },
        'params_out' => array('filter' => $view['filter']),
        // Quick edit answers on the screen itself, in the same view.
        'edit' => array('url' => 'view_users.php?filter=' . rawurlencode($view['filter']), 'columns' => $view['quick_edit']),
    ));
}

/**
 * The users Delete Selected acts on: the selection the list table driver
 * posted (JSON), read with the list's own query and rights. A selection of
 * ids is read in the view the list showed, sent beside it; a condition
 * carries its view itself.
 *
 * @param array $user        the signed-in user
 * @param mixed $selection   the posted selection, JSON
 *                           (pg_list_table_selection_parse())
 * @param mixed $list_params the posted list parameters, JSON ('filter')
 * @return array|false|null user ids (empty when nothing or nothing
 *                          reachable was selected), false when the query
 *                          failed, null when the selection holds more than
 *                          10,000 users
 */
function pg_user_list_selected_ids($user, $selection, $list_params)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $selection = pg_list_table_selection_parse(is_string($selection) ? json_decode($selection, true) : null, time());

    if ($selection === null) {
        return array();
    }

    $params = ($selection['mode'] === 'condition') ? $selection['params'] : json_decode(is_string($list_params) ? $list_params : '', true);
    $source = pg_user_list_source($user, is_array($params) ? $params : array());

    if ($source === null) {
        return array();
    }

    $selected = pg_list_table_selected_ids($source, $selection);

    if ($selected === false) {
        return false;
    }

    return $selected['more'] ? null : $selected['ids'];
}

/**
 * The list's rows for a batch of users, with the sign-in providers of the
 * batch read in one query.
 *
 * @param array $view    pg_user_list_view()
 * @param array $records records of the list's read (pg_user_list_source())
 * @return array rows as pg_list_table_page() hands them on
 */
function pg_user_list_rows($view, $records)
{
    $providers = pg_signin_identities_of(array_column($records, 'pg_lt_id'));
    $rows = array();

    foreach ($records as $record) {
        $record['id'] = $record['pg_lt_id'];
        $rows[] = pg_user_list_row($record, $view, $providers[(int) $record['id']] ?? null);
    }

    return $rows;
}

/**
 * The cells that open a user's editor: the name, followed by a
 * double-click or Enter on the row (data-pg-open), and the edit button.
 * The editor returns to the list's view.
 *
 * @param int    $id
 * @param string $username
 * @param string $list_url the list's address with its view
 * @return array array('username' => pg_list_cell(), 'actions' => pg_list_cell())
 */
function pg_user_list_edit_cells($id, $username, $list_url)
{
    $edit_url = h('edit_user.php?id=' . (int) $id . '&send_to=' . urlencode($list_url));

    return array(
        'username' => pg_list_cell('<a class="pg-link-body-emphasis text-decoration-none" data-pg-open href="' . $edit_url . '">' . h($username) . '</a>'),
        'actions' => pg_list_cell('<a class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . h(lang('Edit')) . '" aria-label="' . h(lang('Edit')) . '" href="' . $edit_url . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>',
            'align-middle text-nowrap action-buttons'),
    );
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
 * One row of the users list.
 *
 * @param array      $row       a record of the list's read
 *                              (pg_user_list_source()), 'id' set
 * @param array      $view      pg_user_list_view()'s answer
 * @param array|null $providers the account's sign-in providers
 *                              (pg_signin_identities_of())
 * @return array array('id', 'class', 'cells' => key => pg_list_cell())
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

    $list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php?filter=' . $filter;
    $cells = pg_user_list_edit_cells($id, $row['username'], $list_url);
    $username_cell = $cells['username']['html'];

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

    // A disabled account (no credential signs it in), beside the name like
    // the lock. Never on a row read before the column existed.
    if (pg_user_is_disabled($row)) {
        $username_cell .= ' <span class="badge theme-secondary fw-light">' . lang('Disabled') . '</span>';
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
        $cells['site_manager'] = pg_user_list_mark($role < 3);
        $cells['site_designer'] = pg_user_list_mark($role <= 1);
    } else {
        $cells['email'] = pg_list_cell(h($row['email']));
        $cells['start_page'] = pg_list_cell(($row['user_home'] != 0) ? h($row['user_start_page']) : '');
    }

    // Hidden in the default view until the user shows it.
    $cells['last_modified'] = pg_list_cell(pg_list_when($row['timestamp'], $row['user'] ?? ''));

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

    // A cell that changes in place carries its field
    // (pg_user_quick_cell_editable()); the rest stay text.
    $quick_edit = $view['quick_edit'] ?? array();

    foreach (array('username', 'email', 'role', 'start_page', 'manage_calendars', 'manage_forms', 'view_visitors', 'manage_contacts', 'manage_email', 'manage_ecommerce') as $field) {
        if (isset($cells[$field], $view['columns'][$field]) && pg_user_quick_cell_editable($field, $row, $filter, $quick_edit)) {
            $cells[$field] = pg_list_cell($cells[$field]['html'], $cells[$field]['class'],
                pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_user_quick_edit_value($field, $row)));
        }
    }

    $cells['last_online'] = pg_list_cell(($row['last_seen'] >= 1) ? get_relative_time(array('timestamp' => $row['last_seen'])) . ' ' : '[' . lang('Never') . ']');

    return array('id' => $id, 'class' => '', 'cells' => $cells);
}

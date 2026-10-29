<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contacts list (view_contacts.php), shared by the screen, its export,
 * delete-all and merge paths and the panel action that pages it
 * (contact_list_rows, includes/panel/lists.php).
 *
 * The view comes from the address (filter); the date range, the contact
 * group or, with the advanced filters on, the checked groups and the other
 * boxes live in the session (the screen copies its request into it).
 * pg_contact_list_filter() turns them into one FROM and WHERE with bound
 * values. A user with the user role sees only the contacts of the groups
 * they may edit; the groups they asked for are checked again here, so the
 * action does not rely on the screen having done it.
 *
 * The duplicates view orders a contact tied to a user account before the
 * other copies of its address (pg_contact_list_duplicates_order()); that
 * order is made in PHP, so the view is printed whole on the screen instead
 * of being paged.
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
 * The views the filter menu offers to a user of this role.
 *
 * @param int $role the viewer's role
 * @return array filter => label
 */
function pg_contact_list_filters($role)
{
    $filters = array(
        'all_my_contacts' => lang('All My Contacts'),
        'my_subscribers' => lang('My Subscribers'),
        'my_affiliates' => lang('My Affiliates'),
        'my_customers' => lang('My Customers'),
        'my_members' => lang('My Members'),
        'my_active_members' => lang('My Active Members'),
        'my_expired_members' => lang('My Expired Members'),
        'my_unregistered_members' => lang('My Unregistered Members'),
        'my_contacts_by_user' => lang('My Contacts by User'),
        'my_contacts_by_business_address' => lang('My Contacts by Business Address'),
        'my_contacts_by_home_address' => lang('My Contacts by Home Address'),
    );

    if ($role < 3) {
        $filters['all_duplicate_contacts'] = lang('All Duplicate Contacts');
    }

    return $filters;
}

/**
 * The view a request names: 'default' when none is given or the name is not
 * a view (both list like All My Contacts).
 *
 * @param mixed $filter
 * @return string
 */
function pg_contact_list_filter_name($filter)
{
    if (!is_string($filter) || !array_key_exists($filter, pg_contact_list_filters(0))) {
        return 'default';
    }

    return $filter;
}

/**
 * The address columns' prefix of an address view, '' for the other views.
 *
 * @param string $filter
 * @return string
 */
function pg_contact_list_address_prefix($filter)
{
    if ($filter == 'my_contacts_by_business_address') {
        return 'business_';
    }

    if ($filter == 'my_contacts_by_home_address') {
        return 'home_';
    }

    return '';
}

/**
 * The contact groups the user may edit, by name.
 *
 * @param array $user the viewer (validate_user())
 * @return array list of array('id', 'name')
 */
function pg_contact_list_groups($user)
{
    $groups = array();

    foreach (db_items("SELECT id, name FROM contact_groups ORDER BY name") as $row) {
        if (validate_contact_group_access($user, $row['id']) == true) {
            $groups[] = $row;
        }
    }

    return $groups;
}

/**
 * Brings the list's session filters into shape: defaults for what is not
 * set yet, a user with the user role kept to the groups they may edit, the
 * checked groups of the advanced filters started from the picked group, and
 * date parts that are numbers (one that is not would break the date math
 * for the rest of the session).
 *
 * @param array $user   the viewer (validate_user())
 * @param array $groups pg_contact_list_groups()'s answer
 * @return void
 */
function pg_contact_list_prepare($user, $groups)
{
    $state = &$_SESSION['software']['view_contacts'];
    $all = '[' . lang('All') . ']';

    if (!isset($state['show_contact_groups'])) {
        $state['show_contact_groups'] = false;
    }

    if (!isset($state['advanced_filters'])) {
        $state['advanced_filters'] = false;
    }

    if ($user['role'] == 3) {
        if (isset($state['contact_group']) && ($state['contact_group'] != '') && ($state['contact_group'] != $all)
            && (!is_scalar($state['contact_group']) || (validate_contact_group_access($user, $state['contact_group']) == false))) {
            unset($state['contact_group']);
        }

        if (isset($state['contact_groups']) && is_array($state['contact_groups'])) {
            foreach ($state['contact_groups'] as $key => $value) {
                if (!is_scalar($value) || (validate_contact_group_access($user, $value) == false)) {
                    unset($state['contact_groups'][$key]);
                }
            }
        }
    }

    if (!isset($state['contact_group'])) {
        $state['contact_group'] = $all;
    }

    if (($state['advanced_filters'] == true) && !isset($state['contact_groups'])) {
        $state['contact_groups'] = array();

        if ($state['contact_group'] == $all) {
            foreach ($groups as $group) {
                $state['contact_groups'][] = $group['id'];
            }
        } else {
            $state['contact_groups'][] = $state['contact_group'];
        }
    }

    // The range starts with the oldest contact.
    $oldest = (int) db_value("SELECT MIN(timestamp) FROM contacts");
    $date_defaults = array(
        'start_month' => date('m', $oldest),
        'start_day'   => date('d', $oldest),
        'start_year'  => date('Y', $oldest),
        'stop_month'  => date('m'),
        'stop_day'    => date('d'),
        'stop_year'   => date('Y'),
    );

    foreach ($date_defaults as $key => $value) {
        if (!preg_match('/^\d{1,4}$/', (string) ($state[$key] ?? ''))) {
            $state[$key] = $value;
        }
    }
}

/**
 * A value of the list's session filters as text, '' when unset or not text.
 *
 * @param array  $state
 * @param string $key
 * @return string
 */
function pg_contact_list_value($state, $key)
{
    return (isset($state[$key]) && is_scalar($state[$key])) ? trim((string) $state[$key]) : '';
}

/**
 * The view's own condition, heading and membership label.
 *
 * @param string $filter pg_contact_list_filter_name()'s answer
 * @return array array('join', 'condition', 'params', 'heading', 'subheading',
 *               'membership_label' (or null when the view is no membership view))
 */
function pg_contact_list_view($filter)
{
    $view = array('join' => '', 'condition' => '', 'params' => array(), 'membership_label' => null,
        'heading' => lang('All My Contacts'), 'subheading' => lang('All contacts, subscribers, members, and affiliates that I can edit.'));
    $active = "((contacts.expiration_date >= CURRENT_DATE()) OR (contacts.expiration_date = '0000-00-00'))";
    $named = "(((contacts.first_name != '') AND (contacts.last_name != '')) OR (contacts.company != ''))";

    switch ($filter) {
        case 'my_subscribers':
            $view['condition'] = "((contacts.email_address != '') AND (contacts.opt_in = '1'))";
            $view['heading'] = lang('My Subscribers');
            $view['subheading'] = lang('All my contacts that can be recipients of an email campaign.');
            break;

        case 'my_affiliates':
            $view['condition'] = "(contacts.affiliate_approved = '1')";
            $view['heading'] = lang('My Affiliates');
            $view['subheading'] = lang('All my contacts with approved affiliate status that can receive order commissions for referring visitors.');
            break;

        case 'my_customers':
            $view['join'] = ' LEFT JOIN orders ON orders.contact_id = contacts.id';
            $view['condition'] = "(orders.status != 'incomplete')";
            $view['heading'] = lang('My Customers');
            $view['subheading'] = lang('All my contacts who have submitted orders.');
            break;

        case 'my_members':
            $view['condition'] = "(contacts.member_id != '')";
            $view['membership_label'] = lang('Member');
            $view['heading'] = lang('My Members');
            $view['subheading'] = lang('All my contacts with a member id.');
            break;

        case 'my_active_members':
            $view['condition'] = "((contacts.member_id != '') AND " . $active . ")";
            $view['membership_label'] = lang('Active Member');
            $view['heading'] = lang('My Active Members');
            $view['subheading'] = lang('All my contacts with a member id and a future expiration date.');
            break;

        case 'my_expired_members':
            $view['condition'] = "((contacts.member_id != '') AND (contacts.expiration_date < CURRENT_DATE()) AND (contacts.expiration_date != '0000-00-00'))";
            $view['membership_label'] = lang('Expired Member');
            $view['heading'] = lang('My Expired Members');
            $view['subheading'] = lang('All my contacts with a member id and a lapsed expiration date.');
            break;

        case 'my_unregistered_members':
            $view['condition'] = "((contact_user.user_contact IS NULL) AND (contacts.member_id != '') AND " . $active . ")";
            $view['membership_label'] = lang('Unregistered Member');
            $view['heading'] = lang('My Unregistered Members');
            $view['subheading'] = lang('All my contacts with a member id and valid expiration date, but cannot login due to a missing user account.');
            break;

        case 'my_contacts_by_user':
            $view['condition'] = "(contacts.id = contact_user.user_contact)";
            $view['heading'] = lang('My Contacts by User');
            $view['subheading'] = lang('All my contacts that have also registered through the website.');
            break;

        case 'my_contacts_by_business_address':
        case 'my_contacts_by_home_address':
            $p = 'contacts.' . pg_contact_list_address_prefix($filter);
            $view['condition'] = "(" . $named . " AND ((" . $p . "address_1 != '') AND (" . $p . "city != '') AND (" . $p . "state != '') AND (" . $p . "zip_code != '')))";
            $view['heading'] = ($filter == 'my_contacts_by_business_address') ? lang('My Contacts by Business Address') : lang('My Contacts by Home Address');
            $view['subheading'] = ($filter == 'my_contacts_by_business_address') ? lang('All my contacts that have a business address.') : lang('All my contacts that have a home address.');
            break;

        case 'all_duplicate_contacts':
            // The addresses more than one contact has, compared without case.
            $seen = array();
            $duplicates = array();

            foreach (db_values("SELECT email_address FROM contacts WHERE email_address != '' ORDER BY email_address ASC") as $address) {
                $address = mb_strtolower((string) $address);

                if (isset($seen[$address])) {
                    $duplicates[$address] = $address;
                }

                $seen[$address] = true;
            }

            if ($duplicates) {
                $view['condition'] = '(contacts.email_address IN (' . implode(', ', array_fill(0, count($duplicates), '?')) . '))';
                $view['params'] = array_values($duplicates);
            } else {
                $view['condition'] = '(1 = 0)';
            }

            $view['heading'] = lang('All Duplicate Contacts');
            $view['subheading'] = lang('All contacts that have a duplicate email address.');
            break;
    }

    return $view;
}

/**
 * The list's FROM, WHERE and ORDER BY for one view, from the session
 * filters, with their values in the order the placeholders take them.
 *
 * @param array  $user   the viewer (validate_user())
 * @param string $filter pg_contact_list_filter_name()'s answer
 * @param array  $groups pg_contact_list_groups()'s answer
 * @return array
 */
function pg_contact_list_filter($user, $filter, $groups)
{
    $state = $_SESSION['software']['view_contacts'] ?? array();
    $all = '[' . lang('All') . ']';
    $none = '[' . lang('None') . ']';
    $duplicates = ($filter == 'all_duplicate_contacts');
    $advanced = !$duplicates && (($state['advanced_filters'] ?? false) == true);
    $group_filter = $duplicates ? $all : pg_contact_list_value($state, 'contact_group');
    $view = pg_contact_list_view($filter);

    $conditions = array();
    $params = $view['params'];

    if ($view['condition'] !== '') {
        $conditions[] = $view['condition'];
    }

    if (!$duplicates) {
        $start = mktime(0, 0, 0, (int) $state['start_month'], (int) $state['start_day'], (int) $state['start_year']);
        $stop = mktime(23, 59, 59, (int) $state['stop_month'], (int) $state['stop_day'], (int) $state['stop_year']);
        $conditions[] = '(contacts.timestamp >= ?) AND (contacts.timestamp <= ?)';
        $params[] = $start;
        $params[] = $stop;
    }

    // The contact groups: the checked ones, the picked one, or for a user
    // with the user role every group they may edit.
    $group_conditions = array();
    $group_params = array();

    if ($advanced) {
        $checked = (isset($state['contact_groups']) && is_array($state['contact_groups'])) ? $state['contact_groups'] : array();

        foreach ($checked as $group) {
            if (!is_scalar($group)) {
                continue;
            }

            if ($group == $none) {
                $group_conditions[] = '(contacts_contact_groups_xref.contact_group_id IS NULL)';
            } else {
                $group_conditions[] = '(contacts_contact_groups_xref.contact_group_id = ?)';
                $group_params[] = (string) $group;
            }
        }

        $conditions[] = $group_conditions ? '(' . implode(' OR ', $group_conditions) . ')' : '(0 = 1)';
    } elseif ($group_filter == $all) {
        if ($user['role'] == 3) {
            foreach ($groups as $group) {
                $group_conditions[] = '(contacts_contact_groups_xref.contact_group_id = ?)';
                $group_params[] = (string) $group['id'];
            }

            $conditions[] = $group_conditions ? '(' . implode(' OR ', $group_conditions) . ')' : '(0 = 1)';
        }
    } elseif ($group_filter == $none) {
        $conditions[] = '(contacts_contact_groups_xref.contact_group_id IS NULL)';
    } else {
        $conditions[] = '(contacts_contact_groups_xref.contact_group_id = ?)';
        $group_params[] = $group_filter;
    }

    $params = array_merge($params, $group_params);

    if ($advanced) {
        $boxes = array('salutation', 'first_name', 'last_name', 'suffix', 'nickname', 'company', 'title', 'department', 'office_location',
            'business_address_1', 'business_address_2', 'business_city', 'business_state', 'business_country', 'business_zip_code', 'business_phone', 'business_fax',
            'home_address_1', 'home_address_2', 'home_city', 'home_state', 'home_country', 'home_zip_code', 'home_phone', 'home_fax',
            'mobile_phone', 'email_address', 'website', 'lead_source');

        if ($filter != 'my_subscribers') {
            $boxes[] = 'description';

            $opt_in = pg_contact_list_value($state, 'opt_in_status');

            if ($opt_in == 'opt_in') {
                $conditions[] = "(contacts.opt_in = '1')";
            } elseif ($opt_in == 'opt_out') {
                $conditions[] = "(contacts.opt_in = '0')";
            }
        }

        $boxes[] = 'member_id';

        if (AFFILIATE_PROGRAM == true) {
            $boxes[] = 'affiliate_name';
            $boxes[] = 'affiliate_code';
        }

        foreach ($boxes as $box) {
            $value = pg_contact_list_value($state, $box);

            if ($value !== '') {
                $conditions[] = '(contacts.' . $box . ' LIKE ?)';
                $params[] = pg_list_like_pattern($value);
            }
        }

        // A membership view sets its own membership condition.
        if ($view['membership_label'] === null) {
            $member = "(contacts.member_id != '') AND (contacts.member_id IS NOT NULL)";

            switch (pg_contact_list_value($state, 'membership_status')) {
                case 'member':
                    $conditions[] = '(' . $member . ')';
                    break;

                case 'active_member':
                    $conditions[] = '(' . $member . " AND ((contacts.expiration_date >= CURRENT_DATE()) OR (contacts.expiration_date = '0000-00-00') OR (contacts.expiration_date IS NULL)))";
                    break;

                case 'expired_member':
                    $conditions[] = '(' . $member . " AND (contacts.expiration_date < CURRENT_DATE()) AND (contacts.expiration_date != '0000-00-00') AND (contacts.expiration_date IS NOT NULL))";
                    break;

                case 'unregistered_member':
                    $conditions[] = "((contact_user.user_contact IS NULL) AND (contacts.member_id != '') AND ((contacts.expiration_date >= CURRENT_DATE()) OR (contacts.expiration_date = '0000-00-00')))";
                    break;

                case 'non_member':
                    $conditions[] = "((contacts.member_id = '') OR (contacts.member_id IS NULL))";
                    break;
            }
        }

        $expiration = pg_contact_list_value($state, 'expiration_date');

        if ($expiration !== '') {
            $conditions[] = '(contacts.expiration_date = ?)';
            $params[] = (string) prepare_form_data_for_input($expiration, 'date');
        }
    }

    $sort = pg_contact_list_sort($filter);

    return array(
        'from' => 'FROM contacts
            LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
            LEFT JOIN user AS last_modified_user ON contacts.user = last_modified_user.user_id
            LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id' . $view['join'],
        'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
        'advanced' => $advanced,
        'group_filter' => $group_filter,
        'heading' => $view['heading'],
        'subheading' => $view['subheading'],
        'membership_label' => $view['membership_label'],
    ) + $sort;
}

/**
 * The view's sort columns, its kept sort and the ORDER BY. The duplicates
 * view is always listed by e-mail address; the sort kept for the other
 * views is left as it is.
 *
 * @param string $filter
 * @return array array('sort_columns', 'sort_default', 'sort_levels', 'order_by')
 */
function pg_contact_list_sort($filter)
{
    $prefix = pg_contact_list_address_prefix($filter);
    $sort_columns = array(
        'first_name' => lang('First Name'),
        'last_name'  => lang('Last Name'),
        'company'    => lang('Company'),
    );

    // What each sort code orders by. Only codes pg_list_state() let through
    // reach pg_list_order_by(), and it writes nothing but these.
    $sort_sql = array(
        'first_name'    => 'contacts.first_name',
        'last_name'     => 'contacts.last_name',
        'company'       => 'contacts.company',
        'email'         => 'contacts.email_address',
        'last_modified' => 'contacts.timestamp',
    );

    if ($prefix === '') {
        $sort_columns['email'] = lang('Email');
        $sort_columns['user'] = lang('User');
        $sort_columns['opt_in'] = lang('Opt-In');
        $sort_sql['user'] = 'contact_user.user_username';
        $sort_sql['opt_in'] = 'contacts.opt_in';
    } else {
        $sort_columns['city'] = lang('City');
        $sort_columns['state'] = lang('State');
        $sort_columns['zip_code'] = lang('Zip Code');
        $sort_columns['country'] = lang('Country');

        foreach (array('city', 'state', 'zip_code', 'country') as $column) {
            $sort_sql[$column] = 'contacts.' . $prefix . $column;
        }
    }

    $sort_columns['last_modified'] = lang('Last Modified');
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

    if ($filter == 'all_duplicate_contacts') {
        $sort_levels = array(array('sort' => 'email', 'order' => 'asc'));
    } else {
        // pg_list_state() reads a sort from the request only on the screen;
        // the action's JSON body never fills $_REQUEST.
        $sort_levels = pg_list_state('view_contacts', $sort_columns, $sort_default)['sorts'];
    }

    // The id last keeps the pages from overlapping.
    return array(
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $sort_levels,
        'order_by' => pg_list_order_by($sort_levels, $sort_sql) . ', contacts.id asc',
    );
}

/**
 * The search box's columns: the row's text columns. The contact groups
 * count only when the list shows them, and only the groups the user may
 * edit, as the cell shows.
 *
 * @param string $filter
 * @param bool   $shows_groups
 * @param array  $groups pg_contact_list_groups()'s answer
 * @return array SQL expressions for pg_list_search_where()
 */
function pg_contact_list_search_columns($filter, $shows_groups, $groups)
{
    $columns = array('contacts.first_name', 'contacts.last_name', 'contacts.company', 'last_modified_user.user_username');
    $prefix = pg_contact_list_address_prefix($filter);

    if ($prefix === '') {
        $columns = array_merge($columns, array('contacts.email_address', 'contacts.business_phone', 'contacts.home_phone', 'contacts.mobile_phone', 'contact_user.user_username'));
    } else {
        foreach (array('address_1', 'address_2', 'city', 'state', 'zip_code', 'country') as $column) {
            $columns[] = 'contacts.' . $prefix . $column;
        }
    }

    if ($shows_groups && $groups) {
        $ids = implode(', ', array_map('intval', array_column($groups, 'id')));
        $columns[] = '(SELECT GROUP_CONCAT(search_group.name) FROM contacts_contact_groups_xref AS search_xref'
            . ' LEFT JOIN contact_groups AS search_group ON search_group.id = search_xref.contact_group_id'
            . ' WHERE (search_xref.contact_id = contacts.id) AND (search_xref.contact_group_id IN (' . $ids . ')))';
    }

    return $columns;
}

/**
 * The SELECT list of a list row.
 *
 * @param string $filter
 * @return string
 */
function pg_contact_list_select($filter)
{
    $select = 'contacts.image, contacts.file_id, contacts.id, contacts.first_name, contacts.last_name, contacts.company,
        contacts.business_phone, contacts.home_phone, contacts.mobile_phone, contacts.email_address, contacts.opt_in, ';
    $prefix = pg_contact_list_address_prefix($filter);

    if ($prefix !== '') {
        foreach (array('address_1', 'address_2', 'city', 'state', 'country', 'zip_code') as $column) {
            $select .= 'contacts.' . $prefix . $column . ' as ' . $column . ', ';
        }
    } elseif ($filter == 'all_duplicate_contacts') {
        foreach (array('business_', 'home_') as $kind) {
            foreach (array('address_1', 'address_2', 'city', 'state', 'country', 'zip_code') as $column) {
                $select .= 'contacts.' . $kind . $column . ', ';
            }
        }
    }

    return $select . 'last_modified_user.user_username as last_modified_username,
        contact_user.user_id as user_id,
        contact_user.user_username as user_username,
        contact_user.user_role as user_role,
        contacts.timestamp';
}

/**
 * The duplicates view's order: within each address the copies tied to a
 * user account first, then the others, each in the order read.
 *
 * @param array $contacts rows read by e-mail address
 * @return array
 */
function pg_contact_list_duplicates_order($contacts)
{
    $children = array();

    foreach ($contacts as $key => $contact) {
        if ($contact['user_id'] != '') {
            $children[$key] = $contact;
        }
    }

    $ordered = array();

    foreach ($contacts as $contact) {
        foreach ($children as $key => $child) {
            if ($child['email_address'] == $contact['email_address']) {
                $ordered[] = $child;
                unset($children[$key]);
            }
        }

        if ($contact['user_id'] == '') {
            $ordered[] = $contact;
        }
    }

    return $ordered ? $ordered : $contacts;
}

/**
 * The rows of a list read: the file pictures and the contact groups of all
 * of them in one query each.
 *
 * @param array  $user         the viewer (validate_user())
 * @param string $filter
 * @param array  $groups       pg_contact_list_groups()'s answer
 * @param bool   $shows_groups whether the groups column is filled
 * @param array  $contacts     rows of pg_contact_list_select()
 * @return array list rows for pg_list_row_html() / pg_list_row_data()
 */
function pg_contact_list_rows($user, $filter, $groups, $shows_groups, $contacts)
{
    $file_ids = array_values(array_unique(array_filter(array_map('intval', array_column($contacts, 'file_id')))));
    $files = array();

    if ($file_ids) {
        $result = db_q('SELECT id, name FROM files WHERE id IN (' . implode(', ', array_fill(0, count($file_ids), '?')) . ')', $file_ids);

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $files[(int) $row['id']] = $row['name'];
        }
    }

    $memberships = array();
    $contact_ids = array_values(array_unique(array_map('intval', array_column($contacts, 'id'))));

    if ($shows_groups && $contact_ids) {
        $result = db_q('SELECT contact_id, contact_group_id FROM contacts_contact_groups_xref WHERE contact_id IN (' . implode(', ', array_fill(0, count($contact_ids), '?')) . ')', $contact_ids);

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $memberships[(int) $row['contact_id']][] = (string) $row['contact_group_id'];
        }
    }

    $rows = array();

    foreach ($contacts as $contact) {
        $group_names = array();

        if ($shows_groups) {
            foreach ($groups as $group) {
                if (in_array((string) $group['id'], $memberships[(int) $contact['id']] ?? array(), true)) {
                    $group_names[] = h($group['name']);
                }
            }
        }

        $rows[] = pg_contact_list_row($contact, $user, $filter, implode(',<br />', $group_names), $files[(int) $contact['file_id']] ?? '');
    }

    return $rows;
}

/**
 * An address as one line: the parts that are filled, a comma before the
 * state, the label in front.
 *
 * @param string $label  'Business' or 'Home', translated
 * @param array  $parts  address_1, address_2, city, state, zip_code, country
 * @return string not escaped
 */
function pg_contact_list_address_line($label, $parts)
{
    $line = '';

    foreach (array('address_1', 'address_2', 'city', 'state', 'zip_code', 'country') as $part) {
        $value = (string) ($parts[$part] ?? '');

        if ($value === '') {
            continue;
        }

        if ($line !== '') {
            $line .= ($part == 'state') ? ', ' : ' ';
        }

        $line .= $value;
    }

    return ($line !== '') ? str_replace('  ', ' ', $label . ': ' . $line) : '';
}

/**
 * The name a row's check box reads: the full name, else the company, else
 * the e-mail address.
 *
 * @param array $contact a row of pg_contact_list_select()
 * @return string unescaped
 */
function pg_contact_list_row_name($contact)
{
    $name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));

    if ($name === '') {
        $name = trim($contact['company'] ?? '');
    }

    return ($name !== '') ? $name : (string) ($contact['email_address'] ?? '');
}

/**
 * One row of the contacts list.
 *
 * @param array  $contact     a row of pg_contact_list_select()
 * @param array  $user        the viewer (validate_user())
 * @param string $filter
 * @param string $group_names the contact's groups, escaped
 * @param string $file_name   the picture's file name when the picture is a file
 * @return array array('id', 'class', 'cells')
 */
function pg_contact_list_row($contact, $user, $filter, $group_names, $file_name)
{
    $id = (int) $contact['id'];
    $address_view = (pg_contact_list_address_prefix($filter) !== '');
    $duplicates = ($filter == 'all_duplicate_contacts');
    $edit_url = 'edit_contact.php?id=' . $id . '&send_to=' . h(escape_javascript(urlencode(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php?filter=' . $filter)));
    $lazy = '<img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" alt="" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="';

    if ((int) $contact['file_id'] != 0) {
        $picture = $lazy . PATH . h($file_name) . '" />';
    } elseif ($contact['image']) {
        $picture = $lazy . h($contact['image']) . '" />';
    } else {
        // No picture: the letters of the name (includes/fn/contacts.php).
        $picture = pg_avatar_markup_for($contact['first_name'] ?? '', $contact['last_name'] ?? '', $contact['company'] ?? '', $contact['id'] ?? '', 'pg-avatar-50');
    }

    $cells = array(
        pg_list_cell('<input class="check" type="checkbox" name="contacts[]" value="' . $id . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => pg_contact_list_row_name($contact)))) . '" />', 'select-all align-middle text-start'),
        pg_list_cell('<button type="button" class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'' . $edit_url . '\'"><i class="bi bi-pencil"></i></button>', 'align-middle text-start col-reorder-none action-buttons'),
        pg_list_cell($picture, 'align-middle text-start'),
    );

    $user_cell = '';
    $phones = array();

    if (!$address_view) {
        if ($contact['user_id']) {
            $user_cell = (($user['role'] == 0) || ($user['role'] < $contact['user_role']))
                ? '<a class="pg-link-secondary" href="edit_user.php?id=' . (int) $contact['user_id'] . '">' . h($contact['user_username']) . '</a>'
                : h($contact['user_username']);
        }

        foreach (array('business_phone' => lang('Business'), 'home_phone' => lang('Home'), 'mobile_phone' => lang('Mobile')) as $column => $label) {
            if ($contact[$column] != '') {
                $phones[] = $label . ': ' . h($contact[$column]);
            }
        }
    }

    $email = pg_list_cell('<a class="pg-link-secondary" href="mailto:' . h($contact['email_address']) . '">' . h($contact['email_address']) . '</a>');
    $names = array(pg_list_cell(h($contact['first_name'])), pg_list_cell(h($contact['last_name'])), pg_list_cell(h($contact['company'])));
    $phone = pg_list_cell(implode('<br />', $phones));

    if ($duplicates) {
        $lines = array();

        foreach (array(lang('Business') => 'business_', lang('Home') => 'home_') as $label => $kind) {
            $parts = array();

            foreach (array('address_1', 'address_2', 'city', 'state', 'zip_code', 'country') as $part) {
                $parts[$part] = $contact[$kind . $part] ?? '';
            }

            $line = pg_contact_list_address_line($label, $parts);

            if ($line !== '') {
                $lines[] = h($line);
            }
        }

        $cells[] = $email;
        $cells = array_merge($cells, $names);
        $cells[] = pg_list_cell(implode('<br />', $lines));
        $cells[] = $phone;
        $cells[] = pg_list_cell($user_cell);
    } else {
        $cells = array_merge($cells, $names);

        if ($address_view) {
            $street = h($contact['address_1']) . (($contact['address_2'] != '') ? ',<br />' . h($contact['address_2']) : '');
            $cells[] = pg_list_cell($street);

            foreach (array('city', 'state', 'zip_code', 'country') as $column) {
                $cells[] = pg_list_cell(h($contact[$column]));
            }
        } else {
            $cells[] = $phone;
            $cells[] = $email;
            $cells[] = pg_list_cell($user_cell);
            $cells[] = pg_list_cell($contact['opt_in'] ? '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>' : '', 'align-middle text-center');
        }
    }

    $cells[] = pg_list_cell($group_names);
    $cells[] = pg_list_cell(get_relative_time(array('timestamp' => $contact['timestamp'])) . '<br />' . h($contact['last_modified_username'] ?? ''));

    return array('id' => (string) $id, 'class' => '', 'cells' => $cells);
}

/**
 * One page of the list (not the duplicates view): the counts without and
 * with the search, and the rows of the page window.
 *
 * @param array  $user   the viewer (validate_user())
 * @param string $filter pg_contact_list_filter_name()'s answer
 * @param array  $groups pg_contact_list_groups()'s answer
 * @param array  $list   pg_contact_list_filter()'s answer
 * @param array  $terms  pg_list_search_terms()'s answer
 * @param array  $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_contact_list_page($user, $filter, $groups, $list, $terms, $window)
{
    $shows_groups = (($_SESSION['software']['view_contacts']['show_contact_groups'] ?? false) == true);
    $base = $list['from'] . ' ' . $list['where'];
    $search = pg_list_search_where($terms, pg_contact_list_search_columns($filter, $shows_groups, $groups));
    $search_sql = ($search['sql'] !== '') ? (($list['where'] !== '') ? ' AND ' : ' WHERE ') . $search['sql'] : '';
    $params = array_merge($list['params'], $search['params']);

    $result = db_q('SELECT COUNT(DISTINCT contacts.id) ' . $base, $list['params']);

    if (!$result) {
        return false;
    }

    $total = (int) mysqli_fetch_row($result)[0];
    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(DISTINCT contacts.id) ' . $base . $search_sql, $params);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    $result = db_q('SELECT ' . pg_contact_list_select($filter) . ' ' . $base . $search_sql . ' GROUP BY contacts.id ORDER BY ' . $list['order_by'] . pg_list_limit_sql($window), $params);

    if (!$result) {
        return false;
    }

    $contacts = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => pg_contact_list_rows($user, $filter, $groups, $shows_groups, $contacts));
}

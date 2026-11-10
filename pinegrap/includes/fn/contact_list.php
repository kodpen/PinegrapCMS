<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contacts list (view_contacts.php) on the list table driver
 * (includes/fn/list_table.php), shared by the screen, its export,
 * delete-all and merge paths, the driver's list_table_rows action and the
 * selected-row actions (edit_contacts.php, pg_contact_list_selected_ids()).
 *
 * The view comes from the address (filter); the date range, the contact
 * group or, with the advanced filters on, the checked groups and the other
 * boxes live in the session (the screen copies its request into it).
 * pg_contact_list_filter() turns them into one FROM and WHERE with bound
 * values. A user with the user role sees only the contacts of the groups
 * they may edit; the groups they asked for are checked again here, so the
 * rows action does not rely on the screen having done it.
 *
 * The duplicates view orders a contact tied to a user account before the
 * other copies of its address (pg_contact_list_duplicates_order()); that
 * order is made in PHP, so the view is printed whole (the driver's browser
 * mode) instead of being read in batches.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list_edit.php');

/**
 * Whether this user may merge duplicate contacts: a role above the user role
 * (3). The duplicates view is not offered to role 3 either; the merge action
 * asks this itself, as it runs on whatever filter the request names.
 *
 * @param array $user the user row (role)
 * @return bool
 */
function pg_contact_list_may_merge($user)
{
    return isset($user['role']) && ((int) $user['role'] < 3);
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
        'suppressed' => lang('Suppressed Addresses'),
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
 * The contact groups a mass delete ran on, for its notice and log line: the
 * picked groups (advanced filters) or the one group of the picker. The names
 * are plain text: the log line stores them as they are (Site Log escapes
 * once), and the screen escapes the result where it enters the HTML notice.
 * An id the user has no access to, or of a group deleted meanwhile, is left
 * out.
 *
 * @param bool         $advanced     whether the advanced filters are on
 * @param array|string $picked       the advanced filters' picked group ids
 * @param string       $group_filter the picker's group id, [All] or [None]
 * @param array        $groups       the user's groups (pg_contact_list_groups())
 * @return string plain-text, comma-separated names
 */
function pg_contact_list_deleted_groups_label($advanced, $picked, $group_filter, array $groups)
{
    $none = '[' . lang('None') . ']';
    $all = '[' . lang('All') . ']';

    $names = array();
    foreach ($groups as $group) {
        $names[(string) $group['id']] = (string) $group['name'];
    }

    $label = function ($value) use ($none, $all, $names) {
        $value = is_scalar($value) ? (string) $value : '';

        if (($value === $none) || ($value === $all)) {
            return $value;
        }

        return isset($names[$value]) ? $names[$value] : '';
    };

    if (!$advanced) {
        return $label($group_filter);
    }

    $labels = array();
    foreach ((is_array($picked) ? $picked : array()) as $value) {
        $text = $label($value);

        if ($text !== '') {
            $labels[] = $text;
        }
    }

    return implode(', ', $labels);
}

/**
 * The contact groups the Organize Selected dialog offers: the ones the user
 * may edit and that are filled by hand. A group filled by rules is left
 * out, as on the import screen and the contact card: edit_contacts.php
 * refuses to change it anyway (core/audiences-doors.md).
 *
 * @param array $user the viewer (validate_user())
 * @return array list of array('id', 'name', 'contacts')
 */
function pg_contact_list_organize_groups($user)
{
    $groups = array();

    foreach (db_items("SELECT id, name FROM contact_groups WHERE " . pg_audience_hand_sql() . " ORDER BY name") as $row) {
        if (validate_contact_group_access($user, $row['id']) == true) {
            $row['contacts'] = get_number_of_contacts($row['id']);
            $groups[] = $row;
        }
    }

    return $groups;
}

/**
 * How many contacts a bulk organize really changed: the distinct contact
 * ids added to or removed from any group. A contact that was already in a
 * group it was added to, or not in one it was taken from, is not counted.
 *
 * @param array $moved group id => array('added' => int[], 'removed' => int[])
 * @return int
 */
function pg_contact_list_organized_count(array $moved)
{
    $ids = array();

    foreach ($moved as $contacts) {
        foreach (array('added', 'removed') as $side) {
            if (isset($contacts[$side]) && is_array($contacts[$side])) {
                foreach ($contacts[$side] as $contact_id) {
                    $ids[(int) $contact_id] = true;
                }
            }
        }
    }

    return count($ids);
}

/**
 * The group ids an Organize Selected post names in one field: the dialog
 * posts them as one comma-separated text. Anything else (a field sent as an
 * array) names no group: explode() on it ended the request with a fatal
 * error. The ids are checked later (pg_audience_hand_ids()). Pure.
 *
 * @param mixed $value the posted add_to_contact_groups or remove_from_contact_groups
 * @return array the parts, as text
 */
function pg_contact_list_posted_group_ids($value)
{
    if (!is_string($value) || (trim($value) === '')) {
        return array();
    }

    return explode(',', $value);
}

/**
 * The list's group choices kept to the ones the screen offers: the picked
 * group must be [All], [None] (above the user role) or a group the user may
 * edit, else it falls back to [All]; the advanced filters' checked groups
 * lose any other value. A deleted group, or [Tümü] left from a panel in
 * another language, otherwise stayed in the session: the list came out
 * empty while the picker, with no option to select, showed [All].
 *
 * @param array $state     the list's session values
 * @param array $group_ids the ids of the groups the user may edit
 * @param bool  $may_none  whether [None] is offered (a role above user)
 * @return array the session values
 */
function pg_contact_list_clean_groups(array $state, array $group_ids, $may_none)
{
    $all = '[' . lang('All') . ']';
    $none = '[' . lang('None') . ']';
    $allowed = array();

    foreach ($group_ids as $id) {
        $allowed[(string) $id] = true;
    }

    if ($may_none) {
        $allowed[$none] = true;
    }

    if (isset($state['contact_group'])) {
        $group = is_scalar($state['contact_group']) ? trim((string) $state['contact_group']) : '';

        if (($group !== $all) && !isset($allowed[$group])) {
            unset($state['contact_group']);
        }
    }

    if (isset($state['contact_groups'])) {
        if (!is_array($state['contact_groups'])) {
            unset($state['contact_groups']);
        } else {
            $checked = array();

            foreach ($state['contact_groups'] as $value) {
                if (is_scalar($value) && isset($allowed[(string) $value])) {
                    $checked[] = (string) $value;
                }
            }

            $state['contact_groups'] = $checked;
        }
    }

    if (!isset($state['contact_group'])) {
        $state['contact_group'] = $all;
    }

    return $state;
}

/**
 * The checked groups the advanced filters open with: every group the user
 * may edit, and [None] above the user role, for [All], so opening the
 * filters leaves the list as it was (without [None] the contacts in no
 * group dropped out); the picked group otherwise.
 *
 * @param string $picked    the picked group
 * @param array  $group_ids the ids of the groups the user may edit
 * @param bool   $may_none  whether [None] is offered (a role above user)
 * @return array
 */
function pg_contact_list_initial_checked($picked, array $group_ids, $may_none)
{
    if ($picked != '[' . lang('All') . ']') {
        return array((string) $picked);
    }

    $checked = array();

    foreach ($group_ids as $id) {
        $checked[] = (string) $id;
    }

    if ($may_none) {
        $checked[] = '[' . lang('None') . ']';
    }

    return $checked;
}

/**
 * Brings the list's session filters into shape: defaults for what is not
 * set yet, the groups kept to the ones the user may pick, the checked
 * groups of the advanced filters started from the picked group, and the
 * date range of this request (pg_list_period_state()): unless the user
 * picked one, the oldest contact to today, worked out on every request, so
 * a session open past midnight still lists the contacts added since.
 *
 * @param array    $user    the viewer (validate_user())
 * @param array    $groups  pg_contact_list_groups()'s answer
 * @param array    $request the request's range values (the screen's
 *                          $_REQUEST; the paging action sends none)
 * @param int|null $now     the current time; time() when null
 * @return void
 */
function pg_contact_list_prepare($user, $groups, array $request = array(), $now = null)
{
    $state = &$_SESSION['software']['view_contacts'];

    if (!is_array($state)) {
        $state = array();
    }

    if (!isset($state['show_contact_groups'])) {
        $state['show_contact_groups'] = false;
    }

    if (!isset($state['advanced_filters'])) {
        $state['advanced_filters'] = false;
    }

    $group_ids = array();

    foreach ($groups as $group) {
        $group_ids[] = $group['id'];
    }

    $may_none = ($user['role'] < 3);
    $state = pg_contact_list_clean_groups($state, $group_ids, $may_none);

    if (($state['advanced_filters'] == true) && !isset($state['contact_groups'])) {
        $state['contact_groups'] = pg_contact_list_initial_checked($state['contact_group'], $group_ids, $may_none);
    }

    // The range starts with the oldest contact.
    $oldest = (int) db_value("SELECT MIN(timestamp) FROM contacts");
    $state = pg_list_period_state($state, $request, ($now === null) ? time() : (int) $now, $oldest);
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

        case 'suppressed':
            // Before the bounce record exists nobody is suppressed.
            $view['condition'] = pg_mail_bounces_ready() ? pg_mail_bounce_suppressed_sql('contacts.email_address') : '(1 = 0)';
            $view['heading'] = lang('Suppressed Addresses');
            $view['subheading'] = lang('All my contacts whose e-mail address bounced, complained or was blocked, so no e-mail is sent to them.');
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
            'mobile_phone', 'email_address', 'website', 'lead_source', 'description');

        // The subscribers view only lists contacts that opted in, so its
        // opt-in box is left out; the description box filters in every view.
        if ($filter != 'my_subscribers') {
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

        // The boxes match the way the table search does, with the Turkish
        // I/ı folding (pg_list_search_where()): a plain LIKE missed
        // "Açıklama" for "AÇIKLAMA". The whole box is one term.
        foreach ($boxes as $box) {
            $value = pg_contact_list_value($state, $box);

            if ($value !== '') {
                $box_where = pg_list_search_where(array($value), array('contacts.' . $box));
                $conditions[] = $box_where['sql'];
                $params = array_merge($params, $box_where['params']);
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

        // A date that is no real date is left out (the screen marks the
        // box): handed to the query as it was, MySQL refused the comparison
        // and the list and its export failed.
        $expiration = pg_date_input_iso(pg_contact_list_value($state, 'expiration_date'));

        if ($expiration !== '') {
            $conditions[] = '(contacts.expiration_date = ?)';
            $params[] = $expiration;
        }
    }

    $sort = pg_contact_list_sort($filter);

    $tables = 'contacts
            LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
            LEFT JOIN user AS last_modified_user ON contacts.user = last_modified_user.user_id
            LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id' . $view['join'];

    // 'tables' and 'condition' are the same pieces without their keywords,
    // as the list table driver takes them (pg_contact_list_source()).
    return array(
        'from' => 'FROM ' . $tables,
        'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'tables' => $tables,
        'condition' => $conditions ? implode(' AND ', $conditions) : '',
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

        foreach (pg_contact_list_phone_columns() as $column => $spec) {
            $sort_columns[$column] = $spec['heading'];
            $sort_sql[$column] = $spec['sort'];
        }
    } else {
        $sort_columns['address'] = lang('Address') . ' 1';
        $sort_columns['address_2'] = lang('Address') . ' 2';
        $sort_sql['address'] = 'contacts.' . $prefix . 'address_1';
        $sort_sql['address_2'] = 'contacts.' . $prefix . 'address_2';
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
 * The contacts list's columns for the list table driver, in the order the
 * table shows them, each with the SQL it sorts by (the sort codes the
 * screen kept before, so a kept sort still applies). The picture comes
 * first: it is the link to the editor. The address views show the address
 * instead of the phones, e-mail, user and opt-in; the duplicates view leads
 * with the e-mail address and always shows the groups.
 *
 * @param string $filter       pg_contact_list_filter_name()'s answer
 * @param bool   $shows_groups whether the groups are shown (the toggle)
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_contact_list_columns($filter, $shows_groups)
{
    $prefix = pg_contact_list_address_prefix($filter);
    $duplicates = ($filter == 'all_duplicate_contacts');

    $columns = array('picture' => array('heading' => lang('Image'), 'width' => '5rem', 'kind' => 'image'));

    if ($duplicates) {
        $columns['email'] = array('heading' => lang('Email'), 'width' => '14rem', 'sort' => 'contacts.email_address');
    }

    $columns['first_name'] = array('heading' => lang('First Name'), 'width' => '10rem', 'sort' => 'contacts.first_name');
    $columns['last_name'] = array('heading' => lang('Last Name'), 'width' => '10rem', 'sort' => 'contacts.last_name');
    $columns['company'] = array('heading' => lang('Company'), 'width' => '12rem', 'sort' => 'contacts.company');

    if ($duplicates) {
        $columns['address'] = array('heading' => lang('Address'), 'width' => '16rem');
        $columns += pg_contact_list_phone_columns();
        $columns['user'] = array('heading' => lang('User'), 'width' => '9rem', 'sort' => 'contact_user.user_username');
    } elseif ($prefix !== '') {
        $columns['address'] = array('heading' => lang('Address') . ' 1', 'width' => '14rem', 'sort' => 'contacts.' . $prefix . 'address_1');
        $columns['address_2'] = array('heading' => lang('Address') . ' 2', 'width' => '12rem', 'sort' => 'contacts.' . $prefix . 'address_2');
        $columns['city'] = array('heading' => lang('City'), 'width' => '9rem', 'sort' => 'contacts.' . $prefix . 'city');
        $columns['state'] = array('heading' => lang('State'), 'width' => '8rem', 'sort' => 'contacts.' . $prefix . 'state');
        $columns['zip_code'] = array('heading' => lang('Zip Code'), 'width' => '7rem', 'sort' => 'contacts.' . $prefix . 'zip_code');
        $columns['country'] = array('heading' => lang('Country'), 'width' => '8rem', 'sort' => 'contacts.' . $prefix . 'country');
    } else {
        $columns += pg_contact_list_phone_columns();
        $columns['email'] = array('heading' => lang('Email'), 'width' => '14rem', 'sort' => 'contacts.email_address');
        $columns['user'] = array('heading' => lang('User'), 'width' => '9rem', 'sort' => 'contact_user.user_username');
        $columns['opt_in'] = array('heading' => lang('Opt-In'), 'kind' => 'check', 'sort' => 'contacts.opt_in');
    }

    if ($shows_groups || $duplicates) {
        $columns['groups'] = array('heading' => lang('Contact Groups'), 'width' => '12rem');
    }

    $columns['last_modified'] = array('heading' => lang('Last Modified'), 'width' => '10rem', 'kind' => 'date', 'sort' => 'contacts.timestamp');
    $columns['actions'] = array('heading' => lang('Action'), 'width' => '5rem', 'kind' => 'actions');

    return $columns;
}

/**
 * The three phone columns, one value each, so each changes in place.
 *
 * @return array key => spec
 */
function pg_contact_list_phone_columns()
{
    return array(
        'business_phone' => array('heading' => lang('Business Phone'), 'width' => '10rem', 'sort' => 'contacts.business_phone'),
        'home_phone' => array('heading' => lang('Home Phone'), 'width' => '10rem', 'sort' => 'contacts.home_phone'),
        'mobile_phone' => array('heading' => lang('Mobile Phone'), 'width' => '10rem', 'sort' => 'contacts.mobile_phone'),
    );
}

/**
 * Whether the list shows the contact groups: the toggle kept in the
 * session; the duplicates view always does.
 *
 * @param string $filter
 * @return bool
 */
function pg_contact_list_shows_groups($filter)
{
    return ($filter == 'all_duplicate_contacts') || (($_SESSION['software']['view_contacts']['show_contact_groups'] ?? false) == true);
}

/**
 * The contacts list as the list table driver's source
 * (includes/fn/list_table.php), for the screen, the rows action and the
 * selected-row actions (edit_contacts.php) alike. It is the screen's gate
 * too (validate_contacts_access()). The filters are the ones the screen
 * kept in the session; the duplicates view is small and ordered in PHP, so
 * it is printed whole and sorted in the browser.
 *
 * @param array $user   the signed-in user
 * @param array $params the list's parameters: 'filter' (the view)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_contact_list_source($user, $params)
{
    if (!is_array($user) || (validate_contacts_access($user, true) != true)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $filter = pg_contact_list_filter_name((is_array($params) && isset($params['filter']) && is_string($params['filter'])) ? $params['filter'] : '');
    $groups = pg_contact_list_groups($user);
    pg_contact_list_prepare($user, $groups);
    $list = pg_contact_list_filter($user, $filter, $groups);
    $shows_groups = pg_contact_list_shows_groups($filter);
    $duplicates = ($filter == 'all_duplicate_contacts');
    $quick_edit = pg_contact_quick_edit_columns($filter, pg_contact_quick_group_options($user));

    return pg_list_table_source(array(
        'list' => 'view_contacts',
        'columns' => pg_contact_list_editable_columns(pg_contact_list_columns($filter, $shows_groups), $quick_edit),
        'from' => $list['tables'],
        'where' => $list['condition'],
        'params' => $list['params'],
        'id' => 'contacts.id',
        'id_order' => 'asc',
        'select' => pg_contact_list_select($filter),
        // The group join gives a contact one row per group.
        'group' => true,
        'search' => array('columns' => pg_contact_list_search_columns($filter, $shows_groups, $groups), 'labels' => array()),
        'levels' => $list['sort_levels'],
        'rows' => function ($records) use ($user, $filter, $groups, $shows_groups, $duplicates) {
            return pg_contact_list_rows($user, $filter, $groups, $shows_groups, $duplicates ? pg_contact_list_duplicates_order($records) : $records);
        },
        'all' => $duplicates,
        'params_out' => array('filter' => $filter),
        // The cells that change in place (pg_contact_quick_edit_columns()),
        // written by the list's own screen with the contact screen's checks.
        'edit' => array('url' => 'view_contacts.php?filter=' . rawurlencode($filter), 'columns' => $quick_edit),
    ));
}

/**
 * The list's columns with each one a quick edit field writes marked
 * editable, by the field's input. Without the mark the driver offers no
 * quick edit (pg_list_table_edit_spec()). Pure.
 *
 * @param array $columns    pg_contact_list_columns()
 * @param array $quick_edit pg_contact_quick_edit_columns()
 * @return array
 */
function pg_contact_list_editable_columns(array $columns, array $quick_edit)
{
    foreach ($quick_edit as $field => $definition) {
        if (isset($columns[$field])) {
            $columns[$field]['editable'] = (string) $definition['type'];
        }
    }

    return $columns;
}

/**
 * The contacts a selected-row action acts on: the selection the list table
 * driver posted (JSON), read with the list's own query and rights. A
 * selection of ids is read in the view the list showed, sent beside it; a
 * condition carries its view itself.
 *
 * @param array $user        the signed-in user
 * @param mixed $selection   the posted selection, JSON
 *                           (pg_list_table_selection_parse())
 * @param mixed $list_params the posted list parameters, JSON ('filter')
 * @return array|false|null contact ids (empty when nothing or nothing
 *                          reachable was selected), false when the query
 *                          failed, null when the selection holds more than
 *                          10,000 contacts
 */
function pg_contact_list_selected_ids($user, $selection, $list_params)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $selection = pg_list_table_selection_parse(is_string($selection) ? json_decode($selection, true) : null, time());

    if ($selection === null) {
        return array();
    }

    $params = ($selection['mode'] === 'condition') ? $selection['params'] : json_decode(is_string($list_params) ? $list_params : '', true);
    $source = pg_contact_list_source($user, is_array($params) ? $params : array());

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
 * The link that opens a contact's editor: the picture, named after the
 * contact, followed by a double-click or Enter on the row (data-pg-open).
 * The editor returns to the list's view.
 *
 * @param int    $id
 * @param string $name     the contact's name (pg_contact_list_row_name()), unescaped
 * @param string $list_url the list's address with its view
 * @param string $picture  the picture's markup
 * @return string
 */
function pg_contact_list_edit_link($id, $name, $list_url, $picture)
{
    return '<a class="d-inline-block text-decoration-none" data-pg-open href="' . h('edit_contact.php?id=' . (int) $id . '&send_to=' . urlencode($list_url)) . '"'
        . ' aria-label="' . h(lang(array('string' => 'Edit {var:1}', 'vars' => (string) $name))) . '">' . $picture . '</a>';
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
 * @return array rows as pg_list_table_page() hands them on
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
    $group_options = $shows_groups ? pg_contact_quick_group_options($user) : array();

    foreach ($contacts as $contact) {
        $group_names = array();

        if ($shows_groups) {
            foreach ($groups as $group) {
                if (in_array((string) $group['id'], $memberships[(int) $contact['id']] ?? array(), true)) {
                    $group_names[] = h($group['name']);
                }
            }
        }

        $rows[] = pg_contact_list_row($contact, $user, $filter, implode(',<br />', $group_names), pg_contact_picture_name($contact['file_id'], $files),
            $memberships[(int) $contact['id']] ?? array(), $group_options);
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
 * The name a row's editor link reads: the full name, else the company,
 * else the e-mail address.
 *
 * @param array $contact a row of pg_contact_list_select()
 * @return string unescaped
 */
function pg_contact_list_row_name($contact)
{
    return pg_contact_heading_name($contact);
}

/**
 * One row of the contacts list.
 *
 * @param array  $contact     a row of pg_contact_list_select()
 * @param array  $user        the viewer (validate_user())
 * @param string $filter
 * @param string $group_names the contact's groups, escaped
 * @param string $file_name   the picture's file name when the picture is a file
 * @param array  $member_ids  the ids of every group the contact is in, read
 *                            when the list shows the groups
 * @param array  $group_options the groups field's choices
 *                            (pg_contact_quick_group_options()), empty
 *                            when the groups are not a field
 * @return array array('id', 'class', 'cells' => key => pg_list_cell())
 */
function pg_contact_list_row($contact, $user, $filter, $group_names, $file_name, array $member_ids = array(), array $group_options = array())
{
    $id = (int) $contact['id'];
    $address_view = (pg_contact_list_address_prefix($filter) !== '');
    $duplicates = ($filter == 'all_duplicate_contacts');
    $edit_url = h('edit_contact.php?id=' . $id . '&send_to=' . urlencode(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php?filter=' . $filter));
    $lazy = '<img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" alt="" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="';

    // A picture file deleted in the File Manager leaves file_id behind with
    // no name; that row gets the letters, not PATH alone.
    if (((int) $contact['file_id'] != 0) && ((string) $file_name !== '')) {
        $picture = $lazy . PATH . h($file_name) . '" />';
    } elseif (((int) $contact['file_id'] == 0) && $contact['image']) {
        $picture = $lazy . h($contact['image']) . '" />';
    } else {
        // No picture: the letters of the name (includes/fn/contacts.php).
        $picture = pg_avatar_markup_for($contact['first_name'] ?? '', $contact['last_name'] ?? '', $contact['company'] ?? '', $contact['id'] ?? '', 'pg-avatar-50');
    }

    $cells = array(
        'picture' => pg_list_cell(pg_contact_list_edit_link($id, pg_contact_list_row_name($contact), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php?filter=' . $filter, $picture), 'align-middle text-start'),
        'first_name' => pg_list_cell(h($contact['first_name'])),
        'last_name' => pg_list_cell(h($contact['last_name'])),
        'company' => pg_list_cell(h($contact['company'])),
        'actions' => pg_list_cell('<a class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . h(lang('Edit')) . '" aria-label="' . h(lang('Edit')) . '" href="' . $edit_url . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>', 'align-middle text-nowrap action-buttons'),
    );

    $user_cell = '';

    if (!$address_view) {
        if ($contact['user_id']) {
            $user_cell = (($user['role'] == 0) || ($user['role'] < $contact['user_role']))
                ? '<a class="pg-link-secondary" href="edit_user.php?id=' . (int) $contact['user_id'] . '">' . h($contact['user_username']) . '</a>'
                : h($contact['user_username']);
        }

        foreach (array('business_phone', 'home_phone', 'mobile_phone') as $column) {
            $cells[$column] = pg_list_cell(h($contact[$column]));
        }

        $cells['email'] = pg_list_cell(($contact['email_address'] != '') ? '<a class="pg-link-secondary" href="mailto:' . h($contact['email_address']) . '">' . h($contact['email_address']) . '</a>' : '');
        $cells['user'] = pg_list_cell($user_cell);
    }

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

        $cells['address'] = pg_list_cell(implode('<br />', $lines));
    } elseif ($address_view) {
        $cells['address'] = pg_list_cell(h($contact['address_1']));

        foreach (array('address_2', 'city', 'state', 'zip_code', 'country') as $column) {
            $cells[$column] = pg_list_cell(h($contact[$column]));
        }
    } else {
        $cells['opt_in'] = pg_list_cell($contact['opt_in'] ? '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>' : '', 'align-middle text-center');
    }

    $cells['groups'] = pg_list_cell($group_names);
    $cells['last_modified'] = pg_list_cell(pg_list_when($contact['timestamp'], $contact['last_modified_username'] ?? ''));

    // A cell that changes in place carries its field; the rest stay text.
    // The groups are a field only where the list read them.
    $quick_edit = pg_contact_quick_edit_columns($filter, $group_options);

    foreach (array_keys($quick_edit) as $field) {
        if (isset($cells[$field])) {
            $value = ($field === 'groups') ? pg_contact_quick_group_value($member_ids, $group_options) : pg_contact_quick_edit_value($field, $contact, $filter);
            $cells[$field] = pg_list_cell($cells[$field]['html'], $cells[$field]['class'],
                pg_list_edit_cell_attributes($quick_edit, $field, $id, $value));
        }
    }

    return array('id' => (string) $id, 'class' => '', 'cells' => $cells);
}

/**
 * What each sort code of the contact groups list (view_contact_groups.php)
 * orders by. The type and the description are shown only for a group with
 * an e-mail subscription, so they sort on the shown value: a group without
 * one sorts as blank. Sorted on the stored text, groups whose cells looked
 * empty came first in a descending description sort and were mixed among
 * the Open ones in a type sort (#5262). Pure.
 *
 * @return array  sort code => SQL expression
 */
function pg_contact_group_list_order_map()
{
    return array(
        'name'          => 'contact_groups.name',
        'subscription'  => 'contact_groups.email_subscription',
        'type'          => "IF(contact_groups.email_subscription = 1, contact_groups.email_subscription_type, '')",
        'description'   => "IF(contact_groups.email_subscription = 1, contact_groups.description, '')",
        'created'       => 'contact_groups.created_timestamp',
        'last_modified' => 'contact_groups.last_modified_timestamp',
    );
}

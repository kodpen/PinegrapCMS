<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The submitted forms list (view_submitted_forms.php), shared by the screen,
 * its export and delete-all paths and the panel action that pages it
 * (submitted_form_list_rows, includes/panel/lists.php).
 *
 * The filters live in the session (the screen copies its request into it):
 * the date range, the custom form or, with the advanced filters on, the
 * checked custom forms with their field filters and the other boxes, and a
 * search query. pg_submitted_form_list_filter() turns them into one FROM and
 * WHERE with bound values, together with the folder rule: a manager or above
 * sees every form, anybody else the forms whose custom form sits in a folder
 * they may edit (check_edit_access()). The screen used to read every
 * matching form and test that rule row by row; it is now part of the query,
 * so a page of rows can be read with LIMIT.
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
 * The custom forms the signed-in user may edit, by form name.
 *
 * @return array list of array('id', 'name')
 */
function pg_submitted_form_list_custom_forms()
{
    $rows = db_items(
        "SELECT
            page.page_id,
            page.page_name,
            page.page_folder as folder_id,
            custom_form_pages.form_name
        FROM page
        LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id
        WHERE " . pg_form_page_sql('page') . "
        ORDER BY custom_form_pages.form_name, page.page_name");

    $custom_forms = array();

    foreach ($rows as $row) {
        if (check_edit_access($row['folder_id']) == true) {
            $custom_forms[] = array(
                'id' => $row['page_id'],
                'name' => $row['form_name'] ? $row['form_name'] : $row['page_name']);
        }
    }

    return $custom_forms;
}

/**
 * Fills the list's session filters that are not set yet with their
 * defaults: every custom form, advanced filters off, no search, the last
 * modified date, the last month. A date part that is not a number of up to
 * four digits is replaced too: the date math throws a TypeError for one, and
 * kept in the session it would break the list for the rest of the session.
 *
 * @param array $custom_forms pg_submitted_form_list_custom_forms()'s answer
 * @return void
 */
function pg_submitted_form_list_defaults($custom_forms)
{
    $state = &$_SESSION['software']['forms']['view_submitted_forms'];

    if (!isset($state['custom_form'])) {
        $state['custom_form'] = '[All]';
    }

    if (!isset($state['advanced_filters'])) {
        $state['advanced_filters'] = false;
    }

    if (!isset($state['query'])) {
        $state['query'] = '';
    }

    // The advanced filters start with the custom form the picklist had.
    if (($state['advanced_filters'] == true) && !isset($state['custom_forms'])) {
        $state['custom_forms'] = array();

        if ($state['custom_form'] == '[All]') {
            foreach ($custom_forms as $custom_form) {
                $state['custom_forms'][] = $custom_form['id'];
            }
        } else {
            $state['custom_forms'][] = $state['custom_form'];
        }
    }

    if (!isset($state['date_type'])) {
        $state['date_type'] = 'last modified';
    }

    $month_ago = time() - 2678400;
    $date_defaults = array(
        'start_month' => date('m', $month_ago),
        'start_day'   => date('d', $month_ago),
        'start_year'  => date('Y', $month_ago),
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
 * The folder rule as SQL: '' for a manager or above, else the folders the
 * user may edit (check_edit_access(), which also walks the parents), read
 * once instead of once per form.
 *
 * @param array $user the viewer (validate_user())
 * @return array array('sql' => condition or '', 'params' => values)
 */
function pg_submitted_form_list_access($user)
{
    if ((int) $user['role'] < 3) {
        return array('sql' => '', 'params' => array());
    }

    $folders = array();

    foreach (db_values("SELECT folder_id FROM folder") as $folder_id) {
        if (check_edit_access($folder_id) == true) {
            $folders[] = (string) $folder_id;
        }
    }

    if (!$folders) {
        return array('sql' => '(1 = 0)', 'params' => array());
    }

    return array('sql' => '(page.page_folder IN (' . implode(', ', array_fill(0, count($folders), '?')) . '))', 'params' => $folders);
}

/**
 * A value of the list's session filters as text, '' when unset or not text.
 *
 * @param array  $state
 * @param string $key
 * @return string
 */
function pg_submitted_form_list_value($state, $key)
{
    return (isset($state[$key]) && is_scalar($state[$key])) ? (string) $state[$key] : '';
}

/**
 * The list's FROM, WHERE and ORDER BY from the session filters, with their
 * values in the order the placeholders take them.
 *
 * @param array $user the viewer (validate_user())
 * @return array array('from', 'where', 'params', 'order_by', 'sort_columns',
 *               'sort_default', 'sort_levels', 'date_type_column')
 */
function pg_submitted_form_list_filter($user)
{
    $state = $_SESSION['software']['forms']['view_submitted_forms'] ?? array();
    $advanced = (($state['advanced_filters'] ?? false) == true);
    $date_type_column = (pg_submitted_form_list_value($state, 'date_type') == 'last modified') ? 'last_modified_timestamp' : 'submitted_timestamp';

    $start_timestamp = mktime(0, 0, 0, (int) $state['start_month'], (int) $state['start_day'], (int) $state['start_year']);
    $stop_timestamp = mktime(23, 59, 59, (int) $state['stop_month'], (int) $state['stop_day'], (int) $state['stop_year']);

    $joins = '';
    $join_params = array();
    $conditions = array('(forms.' . $date_type_column . ' >= ?)', '(forms.' . $date_type_column . ' <= ?)');
    $params = array($start_timestamp, $stop_timestamp);

    if ($advanced) {
        $checked = (isset($state['custom_forms']) && is_array($state['custom_forms'])) ? array_values(array_unique(array_filter($state['custom_forms'], 'is_scalar'))) : array();

        if ($checked) {
            $form_conditions = array();
            $data_conditions = array();

            foreach ($checked as $custom_form) {
                $form_conditions[] = '(forms.page_id = ?)';
                $params[] = (string) $custom_form;

                // A field with a filter value joins its data and asks for it.
                $field_conditions = array();
                $result = db_q("SELECT form_fields.id FROM form_fields WHERE form_fields.page_id = ? ORDER BY form_fields.sort_order", array((string) $custom_form));

                while ($result && ($row = mysqli_fetch_assoc($result))) {
                    $field_id = (int) $row['id'];
                    $value = pg_submitted_form_list_value($state, 'form_field_' . $field_id);

                    if ($value !== '') {
                        $joins .= ' LEFT JOIN form_data AS form_data_' . $field_id . ' ON ((forms.id = form_data_' . $field_id . '.form_id) AND (form_data_' . $field_id . '.form_field_id = ?) AND (form_data_' . $field_id . '.data LIKE ?))';
                        $join_params[] = (string) $field_id;
                        $join_params[] = pg_list_like_pattern($value);
                        $field_conditions[] = '(form_data_' . $field_id . '.data IS NOT NULL)';
                    }
                }

                if ($field_conditions) {
                    $data_conditions[] = '(' . implode(' AND ', $field_conditions) . ')';
                }
            }

            $conditions[] = '(' . implode(' OR ', $form_conditions) . ')';

            if ($data_conditions) {
                $conditions[] = '(' . implode(' OR ', $data_conditions) . ')';
            }
        } else {
            // No custom form checked finds no form.
            $conditions[] = '(0 = 1)';
        }
    } elseif (pg_submitted_form_list_value($state, 'custom_form') != '[All]') {
        $conditions[] = '(forms.page_id = ?)';
        $params[] = pg_submitted_form_list_value($state, 'custom_form');
    }

    $query = pg_submitted_form_list_value($state, 'query');

    if ($query !== '') {
        $conditions[] = "(LOWER(CONCAT_WS(',',
            forms.reference_code,
            forms.address_name,
            forms.tracking_code,
            forms.affiliate_code,
            forms.http_referer,
            INET_NTOA(forms.ip_address),
            custom_form_pages.form_name,
            CONCAT(contacts.first_name, ' ', contacts.last_name),
            submitted_user.user_username,
            form_editor_user.user_username)) LIKE ?)";
        $params[] = pg_list_like_pattern(mb_strtolower($query));
    }

    if ($advanced) {
        $boxes = array(
            'reference_code' => 'forms.reference_code',
            'tracking_code'  => 'forms.tracking_code',
            'affiliate_code' => 'forms.affiliate_code',
            'http_referer'   => 'forms.http_referer',
            'ip_address'     => 'INET_NTOA(forms.ip_address)',
            'contact_name'   => "CONCAT(contacts.first_name, ' ', contacts.last_name)",
            'username'       => 'submitted_user.user_username',
            'form_editor'    => 'form_editor_user.user_username',
            'member_id'      => 'contacts.member_id',
        );

        foreach ($boxes as $key => $column) {
            $value = pg_submitted_form_list_value($state, $key);

            if ($value !== '') {
                $conditions[] = '(' . $column . ' LIKE ?)';
                $params[] = pg_list_like_pattern($value);
            }
        }

        $status = pg_submitted_form_list_value($state, 'status');

        if ($status == 'incomplete') {
            $conditions[] = "(forms.complete = '0')";
        } elseif ($status == 'complete') {
            $conditions[] = "(forms.complete = '1')";
        }
    }

    $access = pg_submitted_form_list_access($user);

    if ($access['sql'] !== '') {
        $conditions[] = $access['sql'];
        $params = array_merge($params, $access['params']);
    }

    // The columns this list sorts by, in the order the table shows them.
    // pg_list_state() keeps the choice in the session as a code, so it
    // survives a language change, and falls back to the default for anything
    // else. The action's JSON body never fills $_REQUEST, so there it reads
    // the kept sort.
    $sort_columns = array(
        'custom_form'    => lang('Custom Form'),
        'reference_code' => lang('Reference Code'),
        'status'         => lang('Status'),
        'contact'        => lang('Contact'),
        'user'           => lang('User'),
        'submitted'      => lang('Submitted'),
        'last_modified'  => lang('Last Modified'),
    );

    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');
    $list_state = pg_list_state('view_submitted_forms', $sort_columns, $sort_default);
    $sort_levels = $list_state['sorts'];

    // The contact is ordered by last name and then first name, both in the
    // level's direction, so it is handed to pg_list_order_by() as two levels.
    $order_levels = array();

    foreach ($sort_levels as $level) {
        if ($level['sort'] === 'contact') {
            $order_levels[] = array('sort' => 'contact_last_name', 'order' => $level['order']);
            $order_levels[] = array('sort' => 'contact_first_name', 'order' => $level['order']);
        } else {
            $order_levels[] = $level;
        }
    }

    // What each sort code orders by. Only codes pg_list_state() let through
    // reach pg_list_order_by(), and it writes nothing but these. The id last
    // keeps the pages from overlapping.
    $order_by = pg_list_order_by($order_levels, array(
        'custom_form'        => 'custom_form_pages.form_name',
        'reference_code'     => 'forms.reference_code',
        'status'             => 'forms.complete',
        'contact_last_name'  => 'contacts.last_name',
        'contact_first_name' => 'contacts.first_name',
        'user'               => 'submitted_user.user_username',
        'submitted'          => 'forms.submitted_timestamp',
        'last_modified'      => 'forms.last_modified_timestamp',
    )) . ', forms.id asc';

    return array(
        'from' => 'FROM forms
            LEFT JOIN page ON forms.page_id = page.page_id
            LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
            LEFT JOIN contacts ON forms.contact_id = contacts.id
            LEFT JOIN user AS submitted_user ON forms.user_id = submitted_user.user_id
            LEFT JOIN user AS last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
            LEFT JOIN user AS form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id' . $joins,
        'where' => 'WHERE ' . implode(' AND ', $conditions),
        'params' => array_merge($join_params, $params),
        'order_by' => $order_by,
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $sort_levels,
        'date_type_column' => $date_type_column,
    );
}

/**
 * The search box's columns: the row's text columns, the status through its
 * label. "Complete" is part of "Incomplete", so a word of it finds both, as
 * DataTables' own search did.
 *
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_submitted_form_list_search()
{
    return array(
        'columns' => array(
            'custom_form_pages.form_name',
            'forms.reference_code',
            "CONCAT(contacts.first_name, ' ', contacts.last_name)",
            'submitted_user.user_username',
            'last_modified_user.user_username',
        ),
        'labels' => array(array('expr' => 'forms.complete', 'labels' => array(1 => lang('Complete'), 0 => lang('Incomplete')))),
    );
}

/**
 * One page of the list: the counts without and with the search, and the
 * rows of the page window.
 *
 * @param array $user   the viewer (validate_user())
 * @param array $filter pg_submitted_form_list_filter()'s answer
 * @param array $terms  pg_list_search_terms()'s answer
 * @param array $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_submitted_form_list_page($user, $filter, $terms, $window)
{
    $base = $filter['from'] . ' ' . $filter['where'];
    $search_columns = pg_submitted_form_list_search();
    $search = pg_list_search_where($terms, $search_columns['columns'], $search_columns['labels']);
    $search_sql = ($search['sql'] !== '') ? ' AND ' . $search['sql'] : '';
    $params = array_merge($filter['params'], $search['params']);

    $result = db_q('SELECT COUNT(*) ' . $base, $filter['params']);

    if (!$result) {
        return false;
    }

    $total = (int) mysqli_fetch_row($result)[0];
    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(*) ' . $base . $search_sql, $params);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    $result = db_q(
        "SELECT
            forms.id,
            forms.complete,
            custom_form_pages.form_name,
            forms.reference_code,
            contacts.id as contact_id,
            CONCAT(contacts.first_name, ' ', contacts.last_name) as contact_name,
            submitted_user.user_username as submitted_username,
            forms.submitted_timestamp,
            last_modified_user.user_username as last_modified_username,
            forms.last_modified_timestamp
        " . $base . $search_sql . "
        ORDER BY " . $filter['order_by'] . pg_list_limit_sql($window),
        $params);

    if (!$result) {
        return false;
    }

    $contacts_access = (validate_contacts_access($user, true) == true);
    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = pg_submitted_form_list_row($row, $contacts_access);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}

/**
 * One row of the submitted forms list.
 *
 * @param array $row             a row of pg_submitted_form_list_page()'s query
 * @param bool  $contacts_access whether the viewer may open contacts
 * @return array array('class', 'cells') for pg_list_row_html() / pg_list_row_data()
 */
function pg_submitted_form_list_row($row, $contacts_access)
{
    $id = (int) $row['id'];

    $status = $row['complete']
        ? '<span class="badge theme-success">' . lang('Complete') . '</span>'
        : '<span class="badge theme-warning fg-black">' . lang('Incomplete') . '</span>';

    $contact_name = '';

    if ($row['contact_id']) {
        $contact_name = (trim((string) $row['contact_name']) == '') ? '[' . lang('No Name') . ']' : (string) $row['contact_name'];
    }

    $contact = $contacts_access
        ? '<a class="pg-link-secondary" href="edit_contact.php?id=' . h($row['contact_id']) . '">' . h($contact_name) . '</a>'
        : h($contact_name);

    $submitted_by = ($row['submitted_username'] != '') ? $row['submitted_username'] : '[' . lang('Unknown') . ']';
    $modified_by = ($row['last_modified_username'] != '') ? $row['last_modified_username'] : '[' . lang('Unknown') . ']';

    return array('class' => '', 'cells' => array(
        pg_list_cell('<input class="check" type="checkbox" name="forms[]" value="' . $id . '" class="checkbox" />', 'select-all align-middle text-start'),
        pg_list_cell('<button type="button" class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_submitted_form.php?id=' . $id . '\'"><i class="bi bi-pencil"></i></button>', 'align-middle text-start action-buttons'),
        pg_list_cell(h($row['form_name']), 'chart_label'),
        pg_list_cell(h($row['reference_code'])),
        pg_list_cell($status),
        pg_list_cell($contact),
        pg_list_cell(h($row['submitted_username'] ?? '')),
        pg_list_cell(get_relative_time(array('timestamp' => $row['submitted_timestamp'])) . ' ' . h($submitted_by)),
        pg_list_cell(get_relative_time(array('timestamp' => $row['last_modified_timestamp'])) . ' ' . h($modified_by)),
    ));
}

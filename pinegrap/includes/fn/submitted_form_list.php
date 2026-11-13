<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The submitted forms list (view_submitted_forms.php) on the list table
 * driver (includes/fn/list_table.php), shared by the screen, its export and
 * delete-all paths, the driver's list_table_rows action and Delete Selected
 * (delete_submitted_forms.php, pg_submitted_form_list_selected_ids()).
 *
 * The filters live in the session (the screen copies its request into it):
 * the date range, the custom form or, with the advanced filters on, the
 * checked custom forms with their field filters and the other boxes, and a
 * search query. pg_submitted_form_list_filter() turns them into one FROM and
 * WHERE with bound values, together with the folder rule: a manager or above
 * sees every form, anybody else the forms whose custom form sits in a folder
 * they may edit (check_edit_access()). The screen used to read every
 * matching form and test that rule row by row; it is now part of the query,
 * so the rows can be read in batches.
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
 * The custom forms the signed-in user may edit, by form name, the ones in the
 * Recycle Bin last and marked (pg_submitted_form_list_labels()).
 *
 * @param callable|null $include  decides from a row (page_id, folder_id)
 *                                whether a form is listed; null lists the
 *                                forms whose folder the user may edit
 * @return array list of array('id', 'name', 'binned')
 */
function pg_submitted_form_list_custom_forms($include = null)
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

    $bin_folders = function_exists('pg_recycle_bin_folder_ids') ? array_flip(pg_recycle_bin_folder_ids()) : array();

    $forms = array();

    foreach ($rows as $row) {
        if (($include !== null) ? $include($row) : (check_edit_access($row['folder_id']) == true)) {
            $binned = isset($bin_folders[(int) $row['folder_id']]);
            $page_name = (string) $row['page_name'];

            // A binned page carries a parked name; show the one it had.
            if ($binned) {
                require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
                $original = pg_recycle_parked_original($page_name, $row['page_id']);
                if ($original !== '') {
                    $page_name = $original;
                }
            }

            $forms[] = array(
                'id' => $row['page_id'],
                'form_name' => (string) $row['form_name'],
                'page_name' => $page_name,
                'binned' => $binned);
        }
    }

    return pg_submitted_form_list_labels($forms);
}

/**
 * The names the custom form pickers show. A site whose design was changed
 * keeps older forms with the same name, their pages in the Recycle Bin, so
 * a form name that appears more than once gets its page name, a binned form
 * gets ' [IN RECYCLE BIN]' as the other page pickers do, and the binned
 * forms go after the others (each group keeps its order). A name still
 * shared after that gets ' (#<id>)' on every form that has it.
 *
 * @param array $forms list of array('id', 'form_name', 'page_name', 'binned')
 * @return array list of array('id', 'name', 'binned'), names not escaped
 */
function pg_submitted_form_list_labels($forms)
{
    $count = array();
    foreach ($forms as $form) {
        $base = ($form['form_name'] !== '') ? $form['form_name'] : $form['page_name'];
        $key = mb_strtolower($base, 'UTF-8');
        $count[$key] = ($count[$key] ?? 0) + 1;
    }

    $live = array();
    $binned = array();

    foreach ($forms as $form) {
        $base = ($form['form_name'] !== '') ? $form['form_name'] : $form['page_name'];
        $name = $base;

        if (($count[mb_strtolower($base, 'UTF-8')] > 1) && ($form['page_name'] !== '')) {
            $name .= ' — ' . $form['page_name'];
        }

        if (!empty($form['binned'])) {
            $binned[] = array('id' => $form['id'], 'name' => $name . ' [' . lang('IN RECYCLE BIN') . ']', 'binned' => true);
        } else {
            $live[] = array('id' => $form['id'], 'name' => $name, 'binned' => false);
        }
    }

    $labels = array_merge($live, $binned);

    // Forms whose form and page names are both equal (copies a template or
    // a changed design left in the Recycle Bin) still share a label: each
    // gets its form id.
    $label_count = array();
    foreach ($labels as $label) {
        $key = mb_strtolower($label['name'], 'UTF-8');
        $label_count[$key] = ($label_count[$key] ?? 0) + 1;
    }
    foreach ($labels as $index => $label) {
        if ($label_count[mb_strtolower($label['name'], 'UTF-8')] > 1) {
            $labels[$index]['name'] .= ' (#' . (int) $label['id'] . ')';
        }
    }

    return $labels;
}

/**
 * How many submitted forms each page holds, all of them, read in one query
 * (the Forms screen used to count once per custom form).
 *
 * @return array page id (string) => number of submitted forms
 */
function pg_submitted_form_list_counts()
{
    $counts = array();

    foreach (db_items("SELECT page_id, COUNT(*) AS number FROM forms GROUP BY page_id") as $row) {
        $counts[(string) $row['page_id']] = (int) $row['number'];
    }

    return $counts;
}

/**
 * The submitted forms whose page is not one of the custom forms the pickers
 * offer. A designed form sits on a page of the standard type, so emptying
 * the Recycle Bin deletes its page and keeps its submissions (only a custom
 * form page with submissions refuses to go), and a page whose design no
 * longer draws a form is not a form page any more. The list still shows
 * their rows, so the Forms screen's Custom Form filter offers each such
 * page on its own, to narrow, export or delete them. A manager or above
 * gets them all; anybody else those on a page in a folder they may edit,
 * as the list's folder rule has it.
 *
 * @param array $user         the viewer (validate_user())
 * @param array $custom_forms pg_submitted_form_list_custom_forms()'s answer
 * @param array $counts       pg_submitted_form_list_counts()'s answer
 * @return array pg_submitted_form_list_gone_labels()'s answer
 */
function pg_submitted_form_list_gone_forms($user, $custom_forms, $counts)
{
    $known = array();
    foreach ($custom_forms as $custom_form) {
        $known[(string) $custom_form['id']] = true;
    }

    $ids = array();
    foreach (array_keys($counts) as $id) {
        if (!isset($known[(string) $id])) {
            $ids[] = (string) $id;
        }
    }

    if (!$ids) {
        return array();
    }

    $marks = implode(', ', array_fill(0, count($ids), '?'));
    $pages = array();
    $names = array();

    $result = db_q("SELECT page_id, page_name, page_folder FROM page WHERE page_id IN ($marks)", $ids);
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $pages[(string) $row['page_id']] = $row;
    }

    $result = db_q("SELECT page_id, form_name FROM custom_form_pages WHERE page_id IN ($marks)", $ids);
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $names[(string) $row['page_id']] = (string) $row['form_name'];
    }

    $manager = isset($user['role']) && ((int) $user['role'] < 3);
    $forms = array();

    foreach ($ids as $id) {
        $page = $pages[$id] ?? null;

        if (!$manager && (($page === null) || (check_edit_access($page['page_folder']) == false))) {
            continue;
        }

        $page_name = ($page === null) ? '' : (string) $page['page_name'];

        // A page in the Recycle Bin carries a parked name; show the one it had.
        if ($page_name !== '') {
            require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
            $original = pg_recycle_parked_original($page_name, $id);
            if ($original !== '') {
                $page_name = $original;
            }
        }

        $forms[] = array(
            'id' => $id,
            'form_name' => $names[$id] ?? '',
            'page_name' => $page_name,
            'page_exists' => ($page !== null));
    }

    return pg_submitted_form_list_gone_labels($forms, $custom_forms);
}

/**
 * The picker names of submitted forms whose page is not a form page: the
 * form's name (else the page's, else "Custom Form"), then ' [PAGE DELETED]'
 * when the page is gone or ' [NOT A FORM PAGE]' when it no longer holds a
 * form. A name equal to another one, of these or of $labels, gets
 * ' (#<id>)' as pg_submitted_form_list_labels() does. Pure.
 *
 * @param array $forms  list of array('id', 'form_name', 'page_name', 'page_exists')
 * @param array $labels the picker's other entries (array('id', 'name', ...))
 * @return array list of array('id', 'name', 'binned' => false, 'gone' => true),
 *               names not escaped, in the order of $forms
 */
function pg_submitted_form_list_gone_labels($forms, $labels = array())
{
    $gone = array();

    foreach ($forms as $form) {
        $base = (string) $form['form_name'];

        if ($base === '') {
            $base = ((string) $form['page_name'] !== '') ? (string) $form['page_name'] : lang('Custom Form');
        }

        $gone[] = array(
            'id' => (string) $form['id'],
            'name' => $base . ' [' . (!empty($form['page_exists']) ? lang('NOT A FORM PAGE') : lang('PAGE DELETED')) . ']',
            'binned' => false,
            'gone' => true);
    }

    $count = array();
    foreach (array_merge($labels, $gone) as $label) {
        $key = mb_strtolower($label['name'], 'UTF-8');
        $count[$key] = ($count[$key] ?? 0) + 1;
    }

    foreach ($gone as $index => $label) {
        if ($count[mb_strtolower($label['name'], 'UTF-8')] > 1) {
            $gone[$index]['name'] .= ' (#' . (int) $label['id'] . ')';
        }
    }

    return $gone;
}

/**
 * Every form the Forms screen's Custom Form filter offers, with the number
 * of submitted forms each holds: the custom forms the user may edit, then
 * the forms whose page is gone or no longer a form page. The screen and the
 * list's rows action settle the remembered form against this one list, so
 * a picked form of the second kind is kept on both.
 *
 * @param array $user the viewer (validate_user())
 * @return array array('forms' => list of array('id', 'name', 'binned'
 *               [, 'gone']), 'counts' => pg_submitted_form_list_counts())
 */
function pg_submitted_form_list_filter_forms($user)
{
    $custom_forms = pg_submitted_form_list_custom_forms();
    $counts = pg_submitted_form_list_counts();

    return array(
        'forms' => array_merge($custom_forms, pg_submitted_form_list_gone_forms($user, $custom_forms, $counts)),
        'counts' => $counts);
}

/**
 * The [All] entry's number: the submitted forms of every form offered,
 * which are the rows the list shows for [All] with no date limit. Pure.
 *
 * @param array $forms  pg_submitted_form_list_filter_forms()'s 'forms'
 * @param array $counts pg_submitted_form_list_counts()'s answer
 * @return int
 */
function pg_submitted_form_list_total($forms, $counts)
{
    $total = 0;

    foreach ($forms as $form) {
        $total += $counts[(string) $form['id']] ?? 0;
    }

    return $total;
}

/**
 * The options of a custom form picker built with output_field(): escaped
 * label => page id, every form its own option. The labels are the keys, so
 * a label that is still equal (two forms whose form and page names are the
 * same) gets the form id.
 *
 * @param array $custom_forms pg_submitted_form_list_custom_forms()'s answer
 * @return array
 */
function pg_submitted_form_list_options($custom_forms)
{
    $options = array();

    foreach ($custom_forms as $custom_form) {
        $label = h($custom_form['name']);
        if (isset($options[$label])) {
            $label .= ' (#' . (int) $custom_form['id'] . ')';
        }
        $options[$label] = $custom_form['id'];
    }

    return $options;
}

/**
 * The label of a field's box in the advanced filters: the field's label as
 * the add and edit screens show it, its name when it has none. A long label
 * (a consent sentence) is cut to $limit characters; the title holds the
 * whole label and the name.
 *
 * @param string $label form_fields.label (may hold HTML from older forms)
 * @param string $name  form_fields.name
 * @param int    $limit
 * @return array('text' => shown text, 'title' => full text), not escaped
 */
function pg_submitted_form_list_field_label($label, $name, $limit = 60)
{
    $name = (string) $name;
    $label = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $label), ENT_QUOTES, 'UTF-8')));

    if ($label === '') {
        return array('text' => $name, 'title' => $name);
    }

    $text = $label;
    if (mb_strlen($label, 'UTF-8') > $limit) {
        $text = rtrim(mb_substr($label, 0, $limit - 1, 'UTF-8')) . '…';
    }

    return array('text' => $text, 'title' => ($label === $name) ? $name : $label . ' (' . $name . ')');
}

/**
 * Fills the list's session filters that are not set yet with their
 * defaults: every custom form, advanced filters off, no search, the last
 * modified date, the last month. A remembered custom form that is not in
 * $custom_forms (deleted, or out of the user's reach) goes back to every
 * form, and drops out of the advanced filters' checked forms. A date part
 * that is not a number of up to four digits is replaced too: the date math
 * throws a TypeError for one, and kept in the session it would break the
 * list for the rest of the session.
 *
 * @param array $custom_forms pg_submitted_form_list_custom_forms()'s answer
 * @param array $request      the request's values; the rows action passes
 *                            none, so it reads the range the screen kept
 * @return void
 */
function pg_submitted_form_list_defaults($custom_forms, array $request = array())
{
    $state = &$_SESSION['software']['forms']['view_submitted_forms'];

    $form_ids = array_map('strval', array_column($custom_forms, 'id'));

    // A remembered form that is gone or out of reach goes back to [All].
    $state['custom_form'] = pg_list_filter_known($state['custom_form'] ?? '[All]', $form_ids);

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

    // Gone forms leave the advanced filters' checked forms.
    if (isset($state['custom_forms']) && is_array($state['custom_forms'])) {
        $state['custom_forms'] = array_values(array_filter($state['custom_forms'], function ($id) use ($form_ids) {
            return is_scalar($id) && in_array((string) $id, $form_ids, true);
        }));
    }

    if (!isset($state['date_type'])) {
        $state['date_type'] = 'last modified';
    }

    // A month ago to today unless the user picked a range: the date parts
    // came in with the request before this call, so the screen passes it.
    $state = pg_list_period_state($state, $request, time());
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
 * @return array array('from', 'where', 'tables', 'condition', 'params',
 *               'order_by', 'sort_columns', 'sort_default', 'sort_levels',
 *               'date_type_column')
 */
function pg_submitted_form_list_filter($user)
{
    $state = $_SESSION['software']['forms']['view_submitted_forms'] ?? array();
    $advanced = (($state['advanced_filters'] ?? false) == true);
    $date_type_column = (pg_submitted_form_list_value($state, 'date_type') == 'last modified') ? 'last_modified_timestamp' : 'submitted_timestamp';

    $start_timestamp = mktime(0, 0, 0, (int) $state['start_month'], (int) $state['start_day'], (int) $state['start_year']);
    $stop_timestamp = mktime(23, 59, 59, (int) $state['stop_month'], (int) $state['stop_day'], (int) $state['stop_year']);

    $conditions = array('(forms.' . $date_type_column . ' >= ?)', '(forms.' . $date_type_column . ' <= ?)');
    $params = array($start_timestamp, $stop_timestamp);

    if ($advanced) {
        $checked = (isset($state['custom_forms']) && is_array($state['custom_forms'])) ? array_values(array_unique(array_filter($state['custom_forms'], 'is_scalar'))) : array();

        if ($checked) {
            $form_conditions = array();
            $data_conditions = array();
            $data_params = array();

            foreach ($checked as $custom_form) {
                $form_conditions[] = '(forms.page_id = ?)';
                $params[] = (string) $custom_form;

                // A field with a filter value asks for a stored value holding it.
                $field_conditions = array();
                $result = db_q("SELECT form_fields.id FROM form_fields WHERE form_fields.page_id = ? ORDER BY form_fields.sort_order", array((string) $custom_form));

                while ($result && ($row = mysqli_fetch_assoc($result))) {
                    $field_id = (int) $row['id'];
                    $value = pg_submitted_form_list_value($state, 'form_field_' . $field_id);

                    if ($value !== '') {
                        $condition = pg_submitted_form_list_field_condition($field_id, $value);
                        $field_conditions[] = $condition['sql'];
                        $data_params = array_merge($data_params, $condition['params']);
                    }
                }

                if ($field_conditions) {
                    $data_conditions[] = '(' . implode(' AND ', $field_conditions) . ')';
                }
            }

            $conditions[] = '(' . implode(' OR ', $form_conditions) . ')';

            if ($data_conditions) {
                $conditions[] = '(' . implode(' OR ', $data_conditions) . ')';
                $params = array_merge($params, $data_params);
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

    $payment_condition = pg_submitted_form_list_payment_condition($state, pg_form_payment_ready());

    if ($payment_condition !== '') {
        $conditions[] = $payment_condition;
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
        'form_editor'    => lang('Form Editor'),
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
        'form_editor'        => 'form_editor_user.user_username',
        'submitted'          => 'forms.submitted_timestamp',
        'last_modified'      => 'forms.last_modified_timestamp',
    )) . ', forms.id asc';

    $tables = 'forms
            LEFT JOIN page ON forms.page_id = page.page_id
            LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
            LEFT JOIN contacts ON forms.contact_id = contacts.id
            LEFT JOIN user AS submitted_user ON forms.user_id = submitted_user.user_id
            LEFT JOIN user AS last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
            LEFT JOIN user AS form_editor_user ON forms.form_editor_user_id = form_editor_user.user_id';

    // 'tables' and 'condition' are the same pieces without their keywords,
    // as the list table driver takes them (pg_submitted_form_list_source()).
    return array(
        'from' => 'FROM ' . $tables,
        'where' => 'WHERE ' . implode(' AND ', $conditions),
        'tables' => $tables,
        'condition' => implode(' AND ', $conditions),
        'params' => $params,
        'order_by' => $order_by,
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $sort_levels,
        'date_type_column' => $date_type_column,
    );
}

/**
 * The condition a field's filter value puts on a submitted form: one of its
 * stored values holds the text. A condition, not a join: a check box group
 * keeps one form_data row per ticked box, and a join listed and counted a
 * form once for every row the text matched. Pure.
 *
 * @param int    $field_id
 * @param string $value the filter text
 * @return array array('sql' => condition, 'params' => its values)
 */
function pg_submitted_form_list_field_condition($field_id, $value)
{
    return array(
        'sql' => '(EXISTS (SELECT 1 FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = ?) AND (form_data.data LIKE ?)))',
        'params' => array((string) (int) $field_id, pg_list_like_pattern((string) $value)),
    );
}

/**
 * The search box's columns: the row's text columns, the status through its
 * label. "Complete" is part of "Incomplete", so a word of it finds both.
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
 * The submitted forms list's columns for the list table driver, in the
 * order the table shows them, each with the SQL it sorts by (the sort codes
 * the screen kept before, so a kept sort still applies). The custom form's
 * name comes first: it is the link to the form. The contact sorts by last
 * name, then first name.
 *
 * @param bool $payment whether the forms table has the fee columns
 *                      (pg_form_payment_ready()): adds the Payment column
 * @param bool $edit    whether the list has quick edit: Status and Form
 *                      Editor are fields then
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_submitted_form_list_columns($payment = false, $edit = false)
{
    $columns = array(
        'custom_form' => array('heading' => lang('Custom Form'), 'width' => '16rem', 'kind' => 'link', 'sort' => 'custom_form_pages.form_name'),
        'reference_code' => array('heading' => lang('Reference Code'), 'width' => '10rem', 'sort' => 'forms.reference_code'),
        'status' => array('heading' => lang('Status'), 'width' => '8rem', 'kind' => 'badge', 'sort' => 'forms.complete', 'editable' => 'select'),
        'contact' => array('heading' => lang('Contact'), 'width' => '12rem', 'sort' => "CONCAT(contacts.last_name, ' ', contacts.first_name)"),
        'user' => array('heading' => lang('User'), 'width' => '10rem', 'sort' => 'submitted_user.user_username'),
        'form_editor' => array('heading' => lang('Form Editor'), 'width' => '10rem', 'sort' => 'form_editor_user.user_username', 'editable' => 'text'),
        'submitted' => array('heading' => lang('Submitted'), 'width' => '12rem', 'kind' => 'date', 'sort' => 'forms.submitted_timestamp'),
        'last_modified' => array('heading' => lang('Last Modified'), 'width' => '12rem', 'kind' => 'date', 'sort' => 'forms.last_modified_timestamp'),
        'actions' => array('heading' => lang('Action'), 'width' => '5rem', 'kind' => 'actions'),
    );

    // The cells the submitted form screen changes are fields only on a list
    // with quick edit (includes/fn/submitted_form_list_edit.php).
    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    if (!$payment) {
        return $columns;
    }

    // The fee of a designed form (includes/fn/form_payments.php), right
    // after Status. Not sorted: the kept sort codes stay the screen's own.
    return pg_submitted_form_list_insert_after($columns, 'status', 'payment', array('heading' => lang('Payment'), 'width' => '7rem', 'kind' => 'badge'));
}

/**
 * The submitted forms list as the list table driver's source
 * (includes/fn/list_table.php), for the screen, the rows action and Delete
 * Selected (delete_submitted_forms.php) alike. It is the screen's gate too
 * (validate_forms_access(): a manager or above, or a user who manages
 * forms). The filters are the ones the screen kept in the session.
 *
 * @param array $user   the signed-in user
 * @param array $params the list's parameters (none)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_submitted_form_list_source($user, $params)
{
    if (!is_array($user) || !isset($user['role']) || !is_numeric($user['role'])
        || (((int) $user['role'] > 2) && (($user['manage_forms'] ?? false) != true))) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list_edit.php');

    // The same forms the screen's filter offers, so a picked form whose page
    // is gone is kept here too.
    $filter_forms = pg_submitted_form_list_filter_forms($user);
    pg_submitted_form_list_defaults($filter_forms['forms']);
    $filter = pg_submitted_form_list_filter($user);
    $payment = pg_form_payment_ready();

    // Every form on the list is one the viewer manages (the gate above and
    // the folder rule of the filter), so the list always has quick edit.
    $quick_edit = pg_submitted_form_quick_edit_columns();

    return pg_list_table_source(array(
        'list' => 'view_submitted_forms',
        'columns' => pg_submitted_form_list_columns($payment, true),
        'from' => $filter['tables'],
        'where' => $filter['condition'],
        'params' => $filter['params'],
        'id' => 'forms.id',
        'id_order' => 'asc',
        'select' => "forms.id,
            forms.complete,
            custom_form_pages.form_name,
            forms.reference_code,
            contacts.id as contact_id,
            CONCAT(contacts.first_name, ' ', contacts.last_name) as contact_name,
            submitted_user.user_username as submitted_username,
            form_editor_user.user_username as form_editor_username,
            forms.submitted_timestamp,
            last_modified_user.user_username as last_modified_username,
            forms.last_modified_timestamp" . ($payment ? ',
            forms.order_id,
            forms.paid_at' : ''),
        'search' => pg_submitted_form_list_search(),
        'levels' => $filter['sort_levels'],
        // A whole-list selection leaves out the forms submitted after it.
        'created' => 'forms.submitted_timestamp',
        'rows' => function ($records) use ($user, $quick_edit) {
            return pg_submitted_form_list_rows($user, $records, $quick_edit);
        },
        // The status and the form editor change in place, written by the
        // list's own screen as the submitted form screen writes them.
        'edit' => array('url' => 'view_submitted_forms.php', 'columns' => $quick_edit),
    ));
}

/**
 * The forms Delete Selected acts on: the selection the list table driver
 * posted (JSON), read with the list's own query and rights.
 *
 * @param array $user      the signed-in user
 * @param mixed $selection the posted selection, JSON
 *                         (pg_list_table_selection_parse())
 * @return array|false|null form ids (empty when nothing or nothing
 *                          reachable was selected), false when the query
 *                          failed, null when the selection holds more than
 *                          10,000 forms
 */
function pg_submitted_form_list_selected_ids($user, $selection)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $selection = pg_list_table_selection_parse(is_string($selection) ? json_decode($selection, true) : null, time());

    if ($selection === null) {
        return array();
    }

    $source = pg_submitted_form_list_source($user, array());

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
 * The list's rows for a batch of forms.
 *
 * @param array $user    the viewer (validate_user())
 * @param array $records records of the list's read (pg_submitted_form_list_source())
 * @param array $quick_edit the quick edit fields (pg_submitted_form_quick_edit_columns()),
 *                          empty for none
 * @return array rows as pg_list_table_page() hands them on
 */
function pg_submitted_form_list_rows($user, $records, array $quick_edit = array())
{
    $contacts_access = (validate_contacts_access($user, true) == true);
    $rows = array();

    // edit_contact.php also wants a role-3 viewer's contact in one of their
    // contact groups, so each row asks for its own contact.
    foreach ($records as $record) {
        $row = pg_submitted_form_list_row($record, $contacts_access && pg_can_open_contact($user, $record['contact_id'], $contacts_access));
        $rows[] = $quick_edit ? pg_submitted_form_quick_cells($row, $record, $quick_edit) : $row;
    }

    return $rows;
}

/**
 * One row of the submitted forms list.
 *
 * @param array $row             a record of the list's read
 *                               (pg_submitted_form_list_source())
 * @param bool  $contacts_access whether the viewer may open this row's contact
 * @return array array('id', 'class', 'cells' => key => pg_list_cell())
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

    // contacts.id is NULL when the submission has no contact or it was
    // deleted: print nothing rather than an empty link.
    $contact = ($contacts_access && $row['contact_id'])
        ? '<a class="pg-link-secondary" href="edit_contact.php?id=' . h($row['contact_id']) . '">' . h($contact_name) . '</a>'
        : h($contact_name);

    $submitted_by = ($row['submitted_username'] != '') ? $row['submitted_username'] : '[' . lang('Unknown') . ']';
    $modified_by = ($row['last_modified_username'] != '') ? $row['last_modified_username'] : '[' . lang('Unknown') . ']';

    $edit_url = 'edit_submitted_form.php?id=' . $id;
    $name = trim($row['form_name'] . ' ' . $row['reference_code']);

    // The form's name opens it: a double-click or Enter on the row follows
    // it (data-pg-open).
    $cells = array(
        'custom_form' => pg_list_cell('<a class="pg-link-body-emphasis text-decoration-none" data-pg-open href="' . h($edit_url) . '">' . h(((string) $row['form_name'] !== '') ? $row['form_name'] : '[' . lang('No Name') . ']') . '</a>', 'chart_label'),
        'reference_code' => pg_list_cell(h($row['reference_code'])),
        'status' => pg_list_cell($status),
        'contact' => pg_list_cell($contact),
        'user' => pg_list_cell(h($row['submitted_username'] ?? '')),
        'form_editor' => pg_list_cell(h($row['form_editor_username'] ?? '')),
        'submitted' => pg_list_cell(get_relative_time(array('timestamp' => $row['submitted_timestamp'])) . ' ' . h($submitted_by)),
        'last_modified' => pg_list_cell(get_relative_time(array('timestamp' => $row['last_modified_timestamp'])) . ' ' . h($modified_by)),
    );

    // A record read with the fee columns (pg_form_payment_ready()) has the
    // Payment cell, after Status as in pg_submitted_form_list_columns().
    if (array_key_exists('order_id', $row)) {
        $cells = pg_submitted_form_list_insert_after($cells, 'status', 'payment', pg_list_cell(pg_submitted_form_list_payment_badge($row)));
    }

    $cells['actions'] = pg_list_cell('<a class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . h(lang('Edit')) . '" aria-label="' . h(lang(array('string' => 'Edit {var:1}', 'vars' => $name))) . '" href="' . h($edit_url) . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>', 'align-middle text-nowrap action-buttons');

    return array('id' => $id, 'class' => '', 'cells' => $cells);
}

/**
 * An array with one entry put right after a key (at the end when the key is
 * not there). Pure.
 *
 * @param array  $items
 * @param string $after the key to follow
 * @param string $key
 * @param mixed  $value
 * @return array
 */
function pg_submitted_form_list_insert_after($items, $after, $key, $value)
{
    $out = array();

    foreach ($items as $item_key => $item) {
        $out[$item_key] = $item;

        if ($item_key === $after) {
            $out[$key] = $value;
        }
    }

    $out[$key] = $value;

    return $out;
}

/**
 * The Payment badge of a record: nothing without a fee order, else Paid or
 * Unpaid (pg_form_payment_state()). Pure.
 *
 * @param array $row a record with order_id and paid_at
 * @return string HTML
 */
function pg_submitted_form_list_payment_badge($row)
{
    $state = pg_form_payment_state($row);

    if ($state === 'paid') {
        return '<span class="badge theme-success">' . h(lang('Paid')) . '</span>';
    }

    if ($state === 'unpaid') {
        return '<span class="badge theme-warning fg-black">' . h(lang('Unpaid')) . '</span>';
    }

    return '';
}

/**
 * The Unpaid filter's condition: the records whose fee order is not yet
 * paid, in the simple and the advanced view alike; '' when the filter is off
 * or the forms table has no fee columns. Pure.
 *
 * @param array $state the list's session filters
 * @param bool  $ready pg_form_payment_ready()
 * @return string SQL without bound values
 */
function pg_submitted_form_list_payment_condition($state, $ready)
{
    if (!$ready || (pg_submitted_form_list_value($state, 'payment') !== 'unpaid')) {
        return '';
    }

    return pg_form_payment_unpaid_where('forms');
}

/**
 * The fixed columns the export writes before the field columns, in order;
 * a quiz adds 'quiz_score' after them.
 *
 * @return array
 */
function pg_submitted_form_export_columns()
{
    return array('form_name', 'reference_code', 'status', 'address_name', 'tracking_code', 'affiliate_code', 'http_referer', 'ip_address', 'submitted', 'last_modified', 'member_id', 'form_editor');
}

/**
 * How many leading headings of an imported file are the export's fixed
 * columns: all of them (and 'quiz_score' after them) when the file starts
 * with them in the export's order, else 0. Those columns describe the record,
 * not its fields, so a field named 'status' must not be filled from the
 * record's status.
 *
 * @param array $columns the file's headings
 * @return int
 */
function pg_submitted_form_import_fixed_columns($columns)
{
    $columns = array_values((array) $columns);
    $fixed = pg_submitted_form_export_columns();

    foreach ($fixed as $index => $name) {
        if (!isset($columns[$index]) || (mb_strtolower(trim((string) $columns[$index]), 'UTF-8') !== $name)) {
            return 0;
        }
    }

    $count = count($fixed);
    if (isset($columns[$count]) && (mb_strtolower(trim((string) $columns[$count]), 'UTF-8') === 'quiz_score')) {
        $count++;
    }

    return $count;
}

/**
 * The complete flag of an imported record from the export's status cell:
 * '0' for the incomplete status the export writes (in the panel language or
 * in English), '1' otherwise.
 *
 * @param mixed $cell
 * @return string
 */
function pg_submitted_form_import_complete($cell)
{
    $status = mb_strtolower(trim((string) $cell), 'UTF-8');

    foreach (array('Incomplete', lang('Incomplete')) as $incomplete) {
        if ($status === mb_strtolower($incomplete, 'UTF-8')) {
            return '0';
        }
    }

    return '1';
}

/**
 * An imported cell of a date, date and time or time field as the add screen
 * stores it: '2026-12-20', '2026-12-20 17:05', '17:05'. The export's ISO
 * form, the site's day and month order and the dotted Turkish date are read
 * (pg_date_input_iso()). An empty cell is ''; a value that is no real date
 * or time is null, so the caller leaves the field empty and names the line.
 * Other field types are returned as they are.
 *
 * @param mixed  $value
 * @param string $type the form field's type
 * @return string|null
 */
function pg_submitted_form_import_value($value, $type)
{
    $value = (string) $value;

    if (!in_array($type, array('date', 'date and time', 'time'), true)) {
        return $value;
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if ($type === 'date') {
        $date = pg_date_input_iso($value);
        return ($date !== '') ? $date : null;
    }

    if ($type === 'time') {
        return validate_time($value) ? (string) prepare_form_data_for_input($value, 'time') : null;
    }

    if (!preg_match('/^(\S+?)(?:T|\s+)(\d.*)$/u', $value, $parts)) {
        return null;
    }

    $date = pg_date_input_iso($parts[1]);
    if (($date === '') || !validate_time($parts[2])) {
        return null;
    }

    return $date . ' ' . prepare_form_data_for_input($parts[2], 'time');
}

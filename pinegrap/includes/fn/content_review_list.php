<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Content review list (view_content_review.php) on the list table
 * driver (includes/fn/list_table.php), shared by the screen, the driver's
 * rows action (list_table_rows) and the action file
 * (content_review_action.php).
 *
 * An item is a page outside the Recycle Bin or a record of a collection
 * form (includes/fn/content_review.php). The list is read as branches: the
 * filters of a date or a person drive from content_review, which holds only
 * the items someone set; "All" and "Not set" also page through the items'
 * own tables in their primary key order, so forms, which can be large, is
 * never sorted whole. The person and the date join from content_review by
 * its primary key.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review.php');

/** The review_sort of an item without a date: after every real date. */
const PG_CONTENT_REVIEW_LIST_NO_DATE = '9999-12-31';

/**
 * The filters a role is offered, in order: value => label. A user (role 3)
 * sees only the items they are the person of, so "All" is theirs and
 * "No person" and "Not set" are not offered.
 *
 * @param int $role
 * @return array
 */
function pg_content_review_list_filters($role)
{
    if ((int) $role >= 3) {
        return array(
            'mine'    => lang('Mine'),
            'overdue' => lang('Overdue'),
            'soon'    => lang('Due soon'),
        );
    }

    return array(
        'all'     => lang('All'),
        'overdue' => lang('Overdue'),
        'soon'    => lang('Due soon'),
        'nobody'  => lang('No person'),
        'notset'  => lang('Not set'),
        'mine'    => lang('Mine'),
    );
}

/**
 * The filter in effect: an offered one, else the role's first.
 *
 * @param mixed $value
 * @param int   $role
 * @return string
 */
function pg_content_review_list_filter($value, $role)
{
    $filters = pg_content_review_list_filters($role);

    return (is_string($value) && isset($filters[$value])) ? $value : (string) key($filters);
}

/**
 * Whether a filter pages through the items' own tables ("All", "Not set")
 * rather than through content_review alone.
 *
 * @param string $filter
 * @return bool
 */
function pg_content_review_list_reads_items($filter)
{
    return in_array($filter, array('all', 'notset'), true);
}

/**
 * The condition on content_review (alias cr) of a filter that drives from
 * it, with its values. A user (role 3) is always narrowed to their own
 * items. "All" and "Not set" give the dated part of "All" here; their
 * undated part is pg_content_review_list_branches()'s.
 *
 * @param string $filter
 * @param string $today   Y-m-d, pg_content_review_today()
 * @param int    $user_id the signed-in user
 * @param int    $role
 * @return array array('sql', 'params')
 */
function pg_content_review_list_condition($filter, $today, $user_id, $role)
{
    $soon = date('Y-m-d', strtotime($today . ' +' . PG_CONTENT_REVIEW_DUE_SOON_DAYS . ' days'));
    $sql = array();
    $params = array();

    switch ($filter) {
        case 'overdue':
            $sql[] = 'cr.review_on < ?';
            $params[] = $today;
            break;
        case 'soon':
            $sql[] = 'cr.review_on >= ? AND cr.review_on <= ?';
            $params[] = $today;
            $params[] = $soon;
            break;
        case 'nobody':
            $sql[] = 'cr.review_on IS NOT NULL AND cr.user_id = 0';
            break;
        case 'mine':
            break;
        default:
            $sql[] = 'cr.review_on IS NOT NULL';
    }

    if (($filter === 'mine') || ((int) $role >= 3)) {
        $sql[] = 'cr.user_id = ?';
        $params[] = (string) (int) $user_id;
    }

    return array('sql' => '(' . implode(') AND (', $sql ? $sql : array('1 = 1')) . ')', 'params' => $params);
}

/**
 * The list's id of an item: one number for both kinds (a page even, a
 * record odd), so a selection is a list of whole numbers.
 *
 * @param string $kind
 * @param int    $item_id
 * @return int
 */
function pg_content_review_list_id($kind, $item_id)
{
    return ((int) $item_id * 2) + (($kind === 'record') ? 1 : 0);
}

/**
 * An id of the list back to its item.
 *
 * @param mixed $id
 * @return array|null array('kind', 'item_id'), null for no item
 */
function pg_content_review_list_id_parts($id)
{
    if (!is_scalar($id) || !preg_match('/^[0-9]{1,12}$/', (string) $id)) {
        return null;
    }

    $id = (int) $id;
    $item_id = intdiv($id, 2);

    return ($item_id > 0) ? array('kind' => (($id % 2) === 1) ? 'record' : 'page', 'item_id' => $item_id) : null;
}

/**
 * The SQL of the list's branches, pure: one SELECT per branch with the
 * same columns, and its values. A filter of a date or a person reads the
 * pages and the records content_review holds; "All" adds the pages and
 * records without a date from their own tables, and "Not set" is only
 * those. An undated branch sorts by a constant, so its rows come in the
 * item table's primary key order.
 *
 * @param string $filter
 * @param array  $condition pg_content_review_list_condition()
 * @param array  $parts     'bin' (folder ids of the Recycle Bin), 'records'
 *                          (collections are there), 'has_tree',
 *                          'checked_at' (the page's SEO columns' SQL),
 *                          'traffic' (pg_seo_traffic_join() for page.page_id)
 *                          and 'views' (its views' SQL, NULL without it)
 * @return array list of array('sql', 'params')
 */
function pg_content_review_list_branches($filter, array $condition, array $parts)
{
    $bin = array_values(array_filter(array_map('intval', (array) ($parts['bin'] ?? array()))));
    $page_live = $bin ? 'page.page_folder NOT IN (' . implode(', ', $bin) . ')' : '1 = 1';
    $records = !empty($parts['records']);
    $traffic = (string) ($parts['traffic'] ?? '');
    $views = (string) ($parts['views'] ?? 'NULL');
    $no_date = "'" . PG_CONTENT_REVIEW_LIST_NO_DATE . "'";

    $review = function ($sort) {
        return 'cr.user_id AS user_id, cr.review_on AS review_on, ' . $sort . ' AS review_sort,'
            . ' cr.interval_months AS interval_months, cr.reviewed_at AS reviewed_at, cr.reviewed_by AS reviewed_by';
    };

    $page_select = function ($sort) use ($review, $parts, $views) {
        return 'SELECT page.page_id * 2 AS row_id, page.page_id AS item_id, \'page\' AS item_kind,'
            . ' page.page_name AS item_name, \'\' AS kind_name, page.page_folder AS folder_id,'
            . ' page.page_style AS page_style, ' . ($parts['has_tree'] ?? "'0'") . ' AS has_tree,'
            . ' page.page_timestamp AS changed_at, ' . $review($sort) . ','
            . ' page.seo_score AS seo_score, ' . ($parts['checked_at'] ?? "'0'") . ' AS seo_checked_at,'
            . ' ' . $views . ' AS views';
    };

    // A record's name is its title field, else its reference code
    // (pg_coll_reference_options() names it the same way).
    $record_select = function ($sort) use ($review) {
        return 'SELECT forms.id * 2 + 1 AS row_id, forms.id AS item_id, \'record\' AS item_kind,'
            . ' COALESCE(NULLIF((SELECT form_data.data FROM form_data'
            . ' JOIN form_fields ON form_data.form_field_id = form_fields.id'
            . ' WHERE form_data.form_id = forms.id AND form_fields.rss_field = \'title\' AND form_fields.office_use_only = 0'
            . ' LIMIT 1), \'\'), forms.reference_code) AS item_name,'
            . ' cfp.form_name AS kind_name, form_page.page_folder AS folder_id,'
            . ' 0 AS page_style, \'0\' AS has_tree,'
            . ' forms.last_modified_timestamp AS changed_at, ' . $review($sort) . ','
            . ' NULL AS seo_score, \'0\' AS seo_checked_at, NULL AS views';
    };

    $record_joins = ' JOIN custom_form_pages AS cfp ON (cfp.page_id = forms.page_id) AND (cfp.collection = 1)'
        . ' LEFT JOIN page AS form_page ON form_page.page_id = forms.page_id';

    $branches = array();

    if ($filter !== 'notset') {
        $branches[] = array(
            'sql' => $page_select('COALESCE(cr.review_on, ' . $no_date . ')')
                . ' FROM content_review AS cr JOIN page ON page.page_id = cr.item_id ' . $traffic
                . " WHERE (cr.item_kind = 'page') AND " . $condition['sql'] . ' AND (' . $page_live . ')',
            'params' => $condition['params'],
        );

        if ($records) {
            $branches[] = array(
                'sql' => $record_select('COALESCE(cr.review_on, ' . $no_date . ')')
                    . ' FROM content_review AS cr JOIN forms ON forms.id = cr.item_id' . $record_joins
                    . " WHERE (cr.item_kind = 'record') AND " . $condition['sql'],
                'params' => $condition['params'],
            );
        }
    }

    if (pg_content_review_list_reads_items($filter)) {
        $branches[] = array(
            'sql' => $page_select($no_date)
                . " FROM page LEFT JOIN content_review AS cr ON (cr.item_kind = 'page') AND (cr.item_id = page.page_id) " . $traffic
                . ' WHERE (cr.review_on IS NULL) AND (' . $page_live . ')',
            'params' => array(),
        );

        if ($records) {
            $branches[] = array(
                'sql' => $record_select($no_date)
                    . ' FROM forms' . $record_joins
                    . " LEFT JOIN content_review AS cr ON (cr.item_kind = 'record') AND (cr.item_id = forms.id)"
                    . ' WHERE (cr.review_on IS NULL)',
                'params' => array(),
            );
        }
    }

    return $branches;
}

/**
 * The list's columns. A filter that pages through the items' own tables
 * sorts only by the review date: a sort by name or views there would sort
 * the whole forms table.
 *
 * @param string $filter
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_content_review_list_columns($filter)
{
    $bounded = !pg_content_review_list_reads_items($filter);

    return array(
        'name' => array('heading' => lang('Name'), 'width' => '14rem', 'kind' => 'link', 'sort' => $bounded ? 'item_name' : ''),
        'kind' => array('heading' => lang('Kind'), 'width' => '7rem', 'kind' => 'badge'),
        'person' => array('heading' => lang('Assigned person'), 'width' => '7rem'),
        'review' => array('heading' => lang('Review by'), 'width' => '12rem', 'kind' => 'date', 'sort' => 'review_sort'),
        'reviewed' => array('heading' => lang('Last reviewed'), 'width' => '12rem', 'hidden' => true),
        'last' => array('heading' => lang('Last published'), 'width' => '8rem', 'kind' => 'date'),
        'views' => array('heading' => lang('Views in 30 days'), 'width' => '5rem', 'kind' => 'number', 'sort' => $bounded ? 'views' : ''),
        'seo' => array('heading' => lang('SEO'), 'width' => '6rem', 'kind' => 'badge'),
        'a11y' => array('heading' => lang('Accessibility'), 'width' => '7rem'),
        'actions' => array('heading' => lang('Action'), 'width' => '3.5rem', 'kind' => 'actions'),
    );
}

/**
 * "Last published" of a row, pure: a designer page's newest revision, else
 * the stored change time, labelled "changed".
 *
 * @param int $revision_at the newest page_revisions row's time, 0 for none
 * @param int $changed_at  page_timestamp or last_modified_timestamp
 * @return array array('label' => published|changed|none, 'at' => int)
 */
function pg_content_review_list_last($revision_at, $changed_at)
{
    if ((int) $revision_at > 0) {
        return array('label' => 'published', 'at' => (int) $revision_at);
    }

    return ((int) $changed_at > 0) ? array('label' => 'changed', 'at' => (int) $changed_at) : array('label' => 'none', 'at' => 0);
}

/**
 * The accessibility cell's state, pure: a record has none, a page before
 * the first finished pass is "Not checked", after it the count of
 * checks with findings.
 *
 * @param string $kind
 * @param int    $findings checks with findings on the page
 * @param bool   $pass_done whether a full pass has finished
 * @return string none, unchecked, found, ok
 */
function pg_content_review_list_a11y_state($kind, $findings, $pass_done)
{
    if ($kind !== 'page') {
        return 'none';
    }

    if ((int) $findings > 0) {
        return 'found';
    }

    return $pass_done ? 'ok' : 'unchecked';
}

/**
 * The tone of a date's state, for its pill.
 *
 * @param string $state pg_content_review_state()
 * @return string
 */
function pg_content_review_list_state_tone($state)
{
    $tones = array('overdue' => 'rose', 'due_soon' => 'amber', 'later' => 'teal');

    return isset($tones[$state]) ? $tones[$state] : 'gray';
}

/**
 * The words of a date's state.
 *
 * @param string $state
 * @return string
 */
function pg_content_review_list_state_label($state)
{
    $labels = array('overdue' => lang('Overdue'), 'due_soon' => lang('Due soon'), 'later' => lang('Later'));

    return isset($labels[$state]) ? $labels[$state] : lang('Not set');
}

/**
 * Whether a role may do an action on the list, pure: roles under 3 every
 * one; a user (role 3) only "Mark reviewed", on their own items.
 *
 * @param string $action set_person, set_date, mark_reviewed or clear
 * @param int    $role
 * @return bool
 */
function pg_content_review_list_action_allowed($action, $role)
{
    if (!in_array($action, array('set_person', 'set_date', 'mark_reviewed', 'clear'), true)) {
        return false;
    }

    return ((int) $role < 3) || ($action === 'mark_reviewed');
}

/**
 * The panel users who may be chosen as a person: enabled accounts of the
 * panel roles, by name. Whether one may edit a given item is checked when
 * it is written (pg_content_review_save()).
 *
 * @return array user_id => name
 */
function pg_content_review_list_people()
{
    $disabled = pg_user_has_disabled_at() ? ' AND (user_disabled_at IS NULL OR user_disabled_at = 0)' : '';
    $people = array();

    foreach ((array) db_items('SELECT user_id, user_username FROM user WHERE user_role <= 3' . $disabled . ' ORDER BY user_username LIMIT 1000') as $row) {
        $people[(int) $row['user_id']] = (string) $row['user_username'];
    }

    return $people;
}

/**
 * The batch's side facts in three reads: the names of the people, each
 * designer page's newest revision and each page's accessibility findings.
 *
 * @param array $records the batch's records
 * @return array names, revisions, findings (each id => value)
 */
function pg_content_review_list_facts(array $records)
{
    $user_ids = array();
    $page_ids = array();

    foreach ($records as $record) {
        foreach (array('user_id', 'reviewed_by') as $key) {
            if ((int) $record[$key] > 0) {
                $user_ids[(int) $record[$key]] = true;
            }
        }

        if ($record['item_kind'] === 'page') {
            $page_ids[(int) $record['item_id']] = true;
        }
    }

    $facts = array('names' => array(), 'revisions' => array(), 'findings' => array());

    if ($user_ids) {
        foreach ((array) db_items('SELECT user_id, user_username FROM user WHERE user_id IN (' . implode(', ', array_keys($user_ids)) . ')') as $row) {
            $facts['names'][(int) $row['user_id']] = (string) $row['user_username'];
        }
    }

    if ($page_ids) {
        $in = implode(', ', array_keys($page_ids));

        if (function_exists('pg_page_revisions_ready') && pg_page_revisions_ready()) {
            foreach ((array) db_items('SELECT page_id, MAX(created_at) AS at FROM page_revisions WHERE page_id IN (' . $in . ') GROUP BY page_id') as $row) {
                $facts['revisions'][(int) $row['page_id']] = (int) $row['at'];
            }
        }

        foreach ((array) db_items("SELECT entity_id, COUNT(*) AS found FROM seo_issue WHERE (entity_type = 'page') AND (LEFT(code, 5) = 'a11y_') AND entity_id IN (" . $in . ') GROUP BY entity_id') as $row) {
            $facts['findings'][(int) $row['entity_id']] = (int) $row['found'];
        }
    }

    return $facts;
}

/**
 * One row of the list.
 *
 * @param array $record   a branch row
 * @param array $facts    pg_content_review_list_facts()
 * @param array $context  today, role, pass_done (accessibility), draft
 *                        (the drafts folder's id)
 * @return array array('id', 'class', 'cells')
 */
function pg_content_review_list_row(array $record, array $facts, array $context)
{
    $kind = ($record['item_kind'] === 'record') ? 'record' : 'page';
    $item_id = (int) $record['item_id'];
    $id = pg_content_review_list_id($kind, $item_id);
    $managers = ((int) $context['role'] < 3);
    $dash = '<span class="fg-secondary">&mdash;</span>';
    $name = trim((string) $record['item_name']) !== '' ? (string) $record['item_name'] : '#' . $item_id;

    if ($kind === 'page') {
        $edit_url = pg_page_edit_url(array('page_id' => $item_id, 'page_style' => $record['page_style'], 'has_tree' => !empty($record['has_tree'])));
        $kind_html = '<span class="pg-pill pg-tone-blue">' . h(lang('Page')) . '</span>'
            . ((($context['draft'] > 0) && ((int) $record['folder_id'] === $context['draft'])) ? ' <span class="pg-pill">' . h(lang('Draft')) . '</span>' : '');
    } else {
        $edit_url = 'edit_submitted_form.php?id=' . $item_id;
        $kind_html = '<span class="pg-pill pg-tone-violet" title="' . h((string) $record['kind_name']) . '"><span>' . h((string) $record['kind_name']) . '</span></span>';
    }

    // The person.
    $person_id = (int) $record['user_id'];
    $person = ($person_id > 0) && isset($facts['names'][$person_id])
        ? h($facts['names'][$person_id])
        : '<span class="fg-secondary">' . h(lang('Nobody')) . '</span>';

    // Review by: the date, its state, the interval and the last review.
    $review_on = ($record['review_on'] === null) ? null : (string) $record['review_on'];
    $state = pg_content_review_state($review_on, $context['today']);
    $review = '<span class="pg-pill pg-tone-' . pg_content_review_list_state_tone($state) . '">' . h(pg_content_review_list_state_label($state)) . '</span>';

    if ($review_on !== null) {
        // The site's short date order, as the date field takes it.
        $review = '<span class="pg-tabular me-2">' . h(prepare_form_data_for_output($review_on, 'date', false)) . '</span>' . $review;
    }

    $months = (int) $record['interval_months'];
    $meta = array();

    if ($months > 0) {
        $meta[] = lang(array('string' => 'Every {var:1} months', 'vars' => array($months)));
    }

    if ((int) $record['reviewed_at'] > 0) {
        $by = (int) $record['reviewed_by'];
        $meta[] = ($by > 0) && isset($facts['names'][$by])
            ? lang(array('string' => 'Reviewed {var:1} by {var:2}', 'vars' => array(get_relative_time(array('timestamp' => (int) $record['reviewed_at'])), h($facts['names'][$by]))))
            : lang(array('string' => 'Reviewed {var:1}', 'vars' => array(get_relative_time(array('timestamp' => (int) $record['reviewed_at'])))));
    }

    // The interval and the last review have a column of their own, hidden
    // until it is shown: under the date they made every row two lines wide.
    $reviewed = $meta ? '<span class="pg-text-meta">' . implode(' · ', $meta) . '</span>' : $dash;

    // Last published, or changed.
    $last = pg_content_review_list_last(($kind === 'page') ? ($facts['revisions'][$item_id] ?? 0) : 0, $record['changed_at']);
    $last_html = ($last['label'] === 'none') ? $dash
        : '<span class="pg-text-meta fg-secondary">' . h(($last['label'] === 'published') ? lang('Published') : lang('Changed')) . '</span> '
            . get_relative_time(array('timestamp' => $last['at']));

    // SEO, accessibility and views: read from what the site keeps.
    if ($kind !== 'page') {
        $seo = $dash;
    } elseif ((int) $record['seo_checked_at'] > 0) {
        $seo = pg_seo_render_bar(array('seo_score' => $record['seo_score'], 'seo_checked_at' => $record['seo_checked_at']));
    } else {
        $seo = '<span class="fg-secondary">' . h(lang('Not scored')) . '</span>';
    }

    $findings = ($kind === 'page') ? ($facts['findings'][$item_id] ?? 0) : 0;
    $a11y_state = pg_content_review_list_a11y_state($kind, $findings, !empty($context['pass_done']));

    if ($a11y_state === 'none') {
        $a11y = $dash;
    } elseif ($a11y_state === 'unchecked') {
        $a11y = '<span class="fg-secondary">' . h(lang('Not checked')) . '</span>';
    } elseif ($a11y_state === 'ok') {
        $a11y = '<span class="fg-secondary">' . h(lang('No problem found')) . '</span>';
    } else {
        $a11y_text = h(lang(array('string' => '{var:1} checks', 'vars' => array($findings))));
        $a11y = $managers ? '<a class="pg-link-secondary" href="view_accessibility.php">' . $a11y_text . '</a>' : $a11y_text;
    }

    $views = (($kind === 'page') && ($record['views'] !== null)) ? '<span class="pg-tabular">' . h(pg_format_number((int) $record['views'], 0)) . '</span>' : $dash;

    return array('id' => (string) $id, 'class' => '', 'cells' => array(
        'name' => pg_list_cell('<a class="pg-link-body-emphasis text-decoration-none" data-pg-open href="' . h($edit_url) . '" title="' . h($name) . '">' . h($name) . '</a>', 'align-middle'),
        'kind' => pg_list_cell($kind_html, 'align-middle'),
        'person' => pg_list_cell($person, 'align-middle'),
        'review' => pg_list_cell($review, 'align-middle'),
        'reviewed' => pg_list_cell($reviewed, 'align-middle'),
        'last' => pg_list_cell($last_html, 'align-middle'),
        'seo' => pg_list_cell($seo, 'align-middle'),
        'a11y' => pg_list_cell($a11y, 'align-middle'),
        'views' => pg_list_cell($views, 'align-middle text-end'),
        'actions' => pg_list_cell(pg_content_review_list_menu($id, $kind, $managers, array(
            'user_id' => $person_id,
            'review_on' => (string) $review_on,
            'interval_months' => $months,
        )), 'align-middle text-nowrap'),
    ));
}

/**
 * A row's menu: the actions the role may do, then for a manager on a page
 * the links to Page Speed and Search and audits (speed is not a column).
 *
 * @param int    $id   the list's id of the item
 * @param string $kind
 * @param bool   $managers role under 3
 * @param array  $values   the row's user_id, review_on (as typed) and
 *                         interval_months, for its dialogs (the date ISO)
 * @return string
 */
function pg_content_review_list_menu($id, $kind, $managers, array $values = array())
{
    // The row's values fill its dialog's fields (data-pg-cr-value-<field>).
    $filled = '';

    foreach ($values as $field => $value) {
        $filled .= ' data-pg-cr-value-' . str_replace('_', '-', $field) . '="' . h((string) $value) . '"';
    }

    $item = function ($action, $icon, $label) use ($id, $filled) {
        return '<li><button type="button" class="menu-item" data-pg-cr-action="' . h($action) . '" data-pg-cr-id="' . (int) $id . '"' . $filled . '>'
            . '<i class="bi bi-' . $icon . ' me-3" aria-hidden="true"></i>' . h($label) . '</button></li>';
    };

    $items = $item('mark_reviewed', 'check2-circle', lang('Mark reviewed'));

    if ($managers) {
        $items = $item('set_person', 'person', lang('Set person'))
            . $item('set_date', 'calendar-event', lang('Set review date'))
            . $items
            . $item('clear', 'x-circle', lang('Clear'));

        if ($kind === 'page') {
            $items .= '<li><hr class="menu-divider"></li>'
                . '<li><a class="menu-item" href="visitors.php#speed"><i class="bi bi-speedometer2 me-3" aria-hidden="true"></i>' . h(lang('Page Speed')) . '</a></li>'
                . '<li><a class="menu-item" href="search_audits.php"><i class="bi bi-activity me-3" aria-hidden="true"></i>' . h(lang('Search and audits')) . '</a></li>';
        }
    }

    return '<div class="menu-wrapper">'
        . '<button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-strategy="fixed" aria-expanded="false" aria-label="' . h(lang('Actions')) . '" title="' . h(lang('Actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
        . '<ul class="menu">' . $items . '</ul></div>';
}

/**
 * The list as the list table driver reads it. The screen's gate is the
 * source's: a signed-in panel user; a user (role 3) gets only the items
 * they are the person of.
 *
 * @param array $user   the signed-in user
 * @param array $params filter
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list or the table is not there yet
 */
function pg_content_review_list_source($user, $params)
{
    if (!isset($user['role'], $user['id']) || !is_numeric($user['role']) || ((int) $user['role'] > 3) || !pg_content_review_ready()) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/seo.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $role = (int) $user['role'];
    $params = is_array($params) ? $params : array();
    $filter = pg_content_review_list_filter($params['filter'] ?? null, $role);
    $today = pg_content_review_today();
    $columns = pg_content_review_list_columns($filter);
    $levels = pg_list_state('view_content_review', pg_list_table_sort_columns(pg_list_table_columns($columns)), array('sort' => 'review', 'order' => 'asc'))['sorts'];
    $traffic = pg_seo_traffic_join('page', 'page.page_id');

    $branches = pg_content_review_list_branches($filter, pg_content_review_list_condition($filter, $today, (int) $user['id'], $role), array(
        'bin' => function_exists('pg_recycle_bin_folder_ids') ? pg_recycle_bin_folder_ids() : array(),
        'records' => function_exists('pg_coll_ready') && pg_coll_ready(),
        'has_tree' => pg_multi_page_design_ready() ? "(page.page_tree_json IS NOT NULL AND page.page_tree_json <> '')" : "'0'",
        'checked_at' => pg_seo_schema_ready() ? 'page.seo_checked_at' : "'0'",
        'traffic' => $traffic,
        'views' => ($traffic !== '') ? 'COALESCE(seo_traffic.views, 0)' : 'NULL',
    ));

    $runs = function_exists('pg_cron_health_last_runs') ? pg_cron_health_last_runs() : array();
    $context = array(
        'today' => $today,
        'role' => $role,
        'pass_done' => !empty($runs['seo_a11y_job']),
        'draft' => function_exists('pg_page_draft_folder_id') ? (int) pg_page_draft_folder_id(false) : 0,
    );

    return pg_list_table_source(array(
        'list' => 'view_content_review',
        'columns' => $columns,
        'branches' => $branches,
        'branch_id' => 'item_id',
        'id' => 'row_id',
        'id_order' => 'asc',
        'select' => 'row_id, item_id, item_kind, item_name, kind_name, folder_id, page_style, has_tree, changed_at,'
            . ' user_id, review_on, review_sort, interval_months, reviewed_at, reviewed_by, seo_score, seo_checked_at, views',
        'search' => array('columns' => array('item_name')),
        'levels' => $levels,
        'params_out' => array('filter' => $filter),
        'rows' => function ($records) use ($context) {
            $facts = pg_content_review_list_facts($records);

            return array_map(function ($record) use ($facts, $context) {
                return pg_content_review_list_row($record, $facts, $context);
            }, $records);
        },
    ));
}

/**
 * The values an action posts, read and checked, pure: the person, the date
 * (typed in the site's order or ISO, '' for none) and the interval.
 *
 * @param array $post
 * @return array|null array('user_id', 'review_on' (Y-m-d or null),
 *                    'interval_months'), null when the date is no date
 */
function pg_content_review_list_action_values(array $post)
{
    $user_id = (isset($post['user_id']) && is_scalar($post['user_id']) && preg_match('/^[0-9]{1,10}$/', (string) $post['user_id'])) ? (int) $post['user_id'] : 0;
    $typed = (isset($post['review_on']) && is_scalar($post['review_on'])) ? trim((string) $post['review_on']) : '';
    $review_on = ($typed === '') ? null : pg_date_input_iso($typed);

    if ($review_on === '') {
        return null;
    }

    return array(
        'user_id' => $user_id,
        'review_on' => $review_on,
        'interval_months' => pg_content_review_interval((isset($post['interval_months']) && is_scalar($post['interval_months'])) ? (int) $post['interval_months'] : 0),
    );
}

/**
 * An action on the items of a selection, one write each through the rules
 * of content_review.php: Set person keeps the date and the interval, Set
 * review date keeps the person. A user (role 3) marks only the items they
 * are the person of.
 *
 * @param string $action set_person, set_date, mark_reviewed or clear
 * @param array  $ids    the list's ids (pg_list_table_selected_ids())
 * @param array  $values pg_content_review_list_action_values()
 * @param array  $user   the signed-in user
 * @return array done, person (left: the person may not edit it), refused
 */
function pg_content_review_list_apply($action, array $ids, array $values, array $user)
{
    $counts = array('done' => 0, 'person' => 0, 'refused' => 0);

    foreach ($ids as $id) {
        $item = pg_content_review_list_id_parts($id);

        if ($item === null) {
            $counts['refused']++;
            continue;
        }

        $row = pg_content_review_get($item['kind'], $item['item_id']);

        if (((int) $user['role'] >= 3) && (!$row || ((int) $row['user_id'] !== (int) $user['id']))) {
            $counts['refused']++;
            continue;
        }

        switch ($action) {
            case 'set_person':
                $problem = pg_content_review_save($item['kind'], $item['item_id'], $values['user_id'], $row ? $row['review_on'] : null, $row ? $row['interval_months'] : 0);
                break;
            case 'set_date':
                $problem = pg_content_review_save($item['kind'], $item['item_id'], $row ? (int) $row['user_id'] : 0, $values['review_on'], $values['interval_months']);
                break;
            case 'mark_reviewed':
                $problem = pg_content_review_mark_reviewed($item['kind'], $item['item_id'], (int) $user['id']);
                break;
            default:
                $problem = pg_content_review_clear($item['kind'], $item['item_id']) ? '' : 'table';
        }

        if ($problem === '') {
            $counts['done']++;
        } elseif ($problem === 'person') {
            $counts['person']++;
        } else {
            $counts['refused']++;
        }
    }

    return $counts;
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The recent comments mode of the comments system widget: the newest
 * comments of a few source pages (a topic page's records, a docs page's own
 * thread), each linked to the page that holds it, drawn in the layout a
 * design gives the widget.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: a comments widget whose system_region_config
// carries 'mode' => 'recent' (pg_sw_widget_kind() 'comments_recent').
//
// Loaded by functions.php through require_once, never on its own.
//
// A list never claims the drawn page's thread, reads no form state and
// prints no form: _render_system_widget_comments() hands a list over before
// it looks at the drawn page. Each comment is drawn only when its source page
// is one the visitor could open now, its record (on a page with a record
// view) is one that view would show the visitor and is complete, and the
// scope allows it: published, the signed-in account's own, or awaiting
// approval on a page the visitor may edit. Every gate is part of the SQL
// that picks the comments, before its LIMIT.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ============================================================================
// Options
// ============================================================================

/**
 * The options of a list of recent comments, read from its
 * system_region_config. Pure.
 *
 * - show: 'published' (default: published comments, for every viewer),
 *   'mine' (the signed-in account's own, published or waiting) or 'pending'
 *   (waiting or scheduled, from the source pages the viewer may edit).
 * - page_ids: the source pages, whole numbers above 0 (or strings of
 *   digits), each once, the first five. Anything else ('tab:<key>' before a
 *   save, 0, a sign, a fraction, a list, a bool) is left out.
 * - limit: 5 (default), 10, 20 or 50 comments, after the sources are merged.
 * - order: 'newest' (default) or 'oldest' first, for 'mine' and 'pending';
 *   'published' is always read newest first (pg_comments_recent_read()).
 * - filters: the list's record filters (recent_filters, rows as a form
 *   list stores them), applied only when the list reads one page with a
 *   record view (pg_comments_recent_source_decision()). A 'filters' key a
 *   list carries from another kind is never read, so none applies unseen.
 *
 * @param mixed $cfg
 * @return array 'show', 'page_ids', 'limit', 'order', 'filters'
 */
function pg_comments_recent_options($cfg)
{
    $cfg = is_array($cfg) ? $cfg : array();
    $word = function ($key, $allowed, $default) use ($cfg) {
        return (isset($cfg[$key]) && is_string($cfg[$key]) && in_array($cfg[$key], $allowed, true)) ? $cfg[$key] : $default;
    };

    $limit = 5;

    if (isset($cfg['recent_limit'])) {
        $value = $cfg['recent_limit'];
        $number = null;

        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && ($value !== '') && ctype_digit($value)) {
            $number = (int) $value;
        }

        if (in_array($number, array(5, 10, 20, 50), true)) {
            $limit = $number;
        }
    }

    $ids = array();

    if (isset($cfg['recent_page_ids']) && is_array($cfg['recent_page_ids'])) {
        foreach ($cfg['recent_page_ids'] as $value) {
            $id = 0;

            if (is_int($value)) {
                $id = $value;
            } elseif (is_string($value) && ($value !== '') && ctype_digit($value)) {
                $id = (int) $value;
            }

            if (($id > 0) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }

            if (count($ids) >= 5) {
                break;
            }
        }
    }

    $filters = array();

    if (isset($cfg['recent_filters']) && is_array($cfg['recent_filters'])) {
        foreach ($cfg['recent_filters'] as $filter) {
            if (is_array($filter)) {
                $filters[] = $filter;
            }
        }
    }

    return array(
        'show' => $word('recent_show', array('published', 'mine', 'pending'), 'published'),
        'page_ids' => $ids,
        'limit' => $limit,
        'order' => $word('recent_order', array('newest', 'oldest'), 'newest'),
        'filters' => $filters,
    );
}

// ============================================================================
// Which pages add comments
// ============================================================================

/**
 * Whether get_page.php sends a visitor who is not signed in to the sign-in
 * screen when they open a page of this type, whatever its folder. Pure; the
 * same rule as get_page.php's switch on the page type, which runs before the
 * folder is asked.
 *
 * - 'my account', 'my account profile', 'view order', 'affiliate sign up
 *   form': always.
 * - 'custom form': when the form does not register the visitor itself
 *   (auto_registration 0) and grants a trial membership (membership 1 with
 *   membership_days above 0) or private access to a folder (private with a
 *   private_folder_id). $properties is the form's custom_form_pages row
 *   (get_page_type_properties()); null when the page has none.
 *
 * @param mixed $page_type
 * @param mixed $properties
 * @return bool
 */
function pg_comments_recent_sign_in_page($page_type, $properties)
{
    $page_type = is_string($page_type) ? $page_type : '';

    if (in_array($page_type, array('my account', 'my account profile', 'view order', 'affiliate sign up form'), true)) {
        return true;
    }

    if (($page_type !== 'custom form') || !is_array($properties)) {
        return false;
    }

    $number = function ($key) use ($properties) {
        return (isset($properties[$key]) && is_scalar($properties[$key]) && !is_bool($properties[$key])) ? (int) $properties[$key] : 0;
    };

    if ($number('auto_registration') !== 0) {
        return false;
    }

    return (($number('membership') === 1) && ($number('membership_days') > 0))
        || (($number('private') !== 0) && ($number('private_folder_id') > 0));
}

/**
 * Whether one source page adds comments to the list, and as what. Pure.
 *
 * Facts: exists, binned (in the Recycle Bin), archived (an archived folder),
 * staff (edit access to the page's folder in a live render), view (the
 * visitor could open the page now: check_view_access() without the
 * registration and guest grant in a live render, a public folder in a
 * background render), sign_in_page (the page sends a visitor who is not
 * signed in to the sign-in screen, pg_comments_recent_sign_in_page()),
 * comments_on (the page's comment switch, the Preview's unsaved settings
 * included), page_type, record (a top-level form_item_view widget), form_id
 * (that view's form), nested (a form_item_view below the top level),
 * viewer_filter (the view's filters, or the list's, compare a field with
 * the viewer, pg_sw_filters_compare_viewer()), personal (a live render for
 * a signed-in visitor), show, signed_in (a live render for a signed-in
 * visitor), list_filters (the list has a record filter with a field),
 * sources (the number of pages the list reads).
 *
 * A page with a record view adds that view's records' threads ('record');
 * any other page adds its own thread ('page'). Refusals, in order:
 * 'missing', 'binned', 'archived' (for a visitor who may not edit it),
 * 'closed' (a folder the visitor may not open), 'sign_in' (a page that asks
 * a visitor who is not signed in, and every background render, to sign in
 * first), 'comments_off', 'classic'
 * (a page that keeps its classic comments block,
 * pg_comments_widget_classic_thread()), 'nested' (a page source whose
 * record view is below the top level: its thread cannot be told), 'no_form'
 * (a record view without a form), 'filters' (a list with record filters
 * reads one page with a record view only: the filters name that form's
 * fields), 'viewer_filter' (a view that keeps
 * records per viewer, drawn for nobody in particular: the filter would read
 * '' and match every record sent while signed out), 'not_staff' ('pending'
 * without edit access), 'signed_out' ('mine' for a visitor who is not
 * signed in).
 *
 * @param mixed $facts
 * @return array 'use' (bool), 'kind' ('record' | 'page'), 'reason' ('' when used)
 */
function pg_comments_recent_source_decision($facts)
{
    $facts = is_array($facts) ? $facts : array();
    $on = function ($key) use ($facts) {
        return !empty($facts[$key]);
    };
    $kind = $on('record') ? 'record' : 'page';
    $show = (isset($facts['show']) && is_string($facts['show'])) ? $facts['show'] : 'published';
    $form_id = (isset($facts['form_id']) && is_scalar($facts['form_id']) && !is_bool($facts['form_id'])) ? (int) $facts['form_id'] : 0;
    $refuse = function ($reason) use ($kind) {
        return array('use' => false, 'kind' => $kind, 'reason' => $reason);
    };

    if (!$on('exists')) {
        return $refuse('missing');
    }
    if ($on('binned')) {
        return $refuse('binned');
    }
    if ($on('archived') && !$on('staff')) {
        return $refuse('archived');
    }
    if (!$on('view')) {
        return $refuse('closed');
    }
    if ($on('sign_in_page') && !$on('signed_in')) {
        return $refuse('sign_in');
    }
    if (!$on('comments_on')) {
        return $refuse('comments_off');
    }
    if (pg_comments_widget_classic_thread(isset($facts['page_type']) ? $facts['page_type'] : '', $kind === 'record')) {
        return $refuse('classic');
    }
    if (($kind === 'page') && $on('nested')) {
        return $refuse('nested');
    }
    if (($kind === 'record') && ($form_id <= 0)) {
        return $refuse('no_form');
    }
    if ($on('list_filters') && (($kind !== 'record') || !isset($facts['sources']) || ($facts['sources'] !== 1))) {
        return $refuse('filters');
    }
    if (($kind === 'record') && $on('viewer_filter') && !$on('personal')) {
        return $refuse('viewer_filter');
    }
    if (($show === 'pending') && !$on('staff')) {
        return $refuse('not_staff');
    }
    if (($show === 'mine') && !$on('signed_in')) {
        return $refuse('signed_out');
    }

    return array('use' => true, 'kind' => $kind, 'reason' => '');
}

// ============================================================================
// The gates, written into SQL
// ============================================================================

/**
 * The part of a statement over `forms` that holds a list to the records a
 * record view's access setting shows the viewer, or null when it shows the
 * viewer none (no statement is sent then). Pure.
 *
 * $viewer: signed_in, user_id, live (a render for the visitor's own request;
 * false for a background render), form_staff (edit access to the folder of
 * the view's custom form, in a live render).
 *
 * - 'public', or any value the record view reads as public: ''.
 * - 'logged_in': '' for a signed-in visitor of a live render, else null.
 * - 'submitter_only': '' for who may edit the custom form; a signed-in
 *   visitor gets the records they sent or edit; anyone else, and every
 *   background render, none. A strict part of pg_sw_submitted_form_visible():
 *   the records this browser session sent, the connect-to-contact address
 *   and the watchers are not followed, so a list may show less than the
 *   record page, never more.
 *
 * @param mixed $access the view's access_control
 * @param mixed $viewer
 * @return string|null
 */
function pg_comments_recent_access_sql($access, $viewer)
{
    $viewer = is_array($viewer) ? $viewer : array();
    $access = is_string($access) ? $access : '';
    $live = !empty($viewer['live']);
    $user_id = (isset($viewer['user_id']) && is_scalar($viewer['user_id']) && !is_bool($viewer['user_id'])) ? (int) $viewer['user_id'] : 0;
    $signed_in = $live && !empty($viewer['signed_in']);

    if ($access === 'logged_in') {
        return $signed_in ? '' : null;
    }

    if ($access !== 'submitter_only') {
        return '';
    }

    if (!$live) {
        return null;
    }

    if (!empty($viewer['form_staff'])) {
        return '';
    }

    if (!$signed_in || ($user_id <= 0)) {
        return null;
    }

    return " AND ((forms.user_id = '" . $user_id . "') OR (forms.form_editor_user_id = '" . $user_id . "'))";
}

/**
 * The part of a statement over `comments` that holds a list to its scope,
 * or null when the scope gives the viewer nothing. Pure.
 *
 * - 'published' (and any unknown scope): published comments.
 * - 'mine': the comments the account $user_id wrote, in any state; null for
 *   no account. Never the session's comment ids: a session survives a
 *   sign-in, so on a shared browser one member would read another's. With
 *   $since (the account's creation time, pg_comments_recent_account_since())
 *   only comments written from then on: deleting an account leaves its
 *   comments' created_user_id, and MySQL 5.7 hands the highest user id out
 *   again after a restart, so a new account could otherwise list a deleted
 *   member's comments, waiting ones included, as its own. 0 (unknown) adds
 *   no bound.
 * - 'pending': comments not published yet, scheduled ones included.
 *
 * @param mixed $show
 * @param mixed $user_id
 * @param mixed $since
 * @return string|null
 */
function pg_comments_recent_audience_sql($show, $user_id, $since = 0)
{
    if ($show === 'mine') {
        $id = 0;

        if (is_int($user_id)) {
            $id = $user_id;
        } elseif (is_string($user_id) && ($user_id !== '') && ctype_digit($user_id)) {
            $id = (int) $user_id;
        }

        if ($id <= 0) {
            return null;
        }

        $from = 0;

        if (is_int($since)) {
            $from = $since;
        } elseif (is_string($since) && ($since !== '') && ctype_digit($since)) {
            $from = (int) $since;
        }

        return " AND (comments.created_user_id = '" . $id . "')"
            . (($from > 0) ? " AND (comments.created_timestamp >= '" . $from . "')" : '');
    }

    if ($show === 'pending') {
        return " AND (comments.published = '0')";
    }

    return " AND (comments.published = '1')";
}

// ============================================================================
// The statements
// ============================================================================

/**
 * Whole numbers above 0 of a list, each once. Pure.
 *
 * @param mixed $ids
 * @return int[]
 */
function _pg_comments_recent_ids($ids)
{
    $out = array();

    foreach ((is_array($ids) ? $ids : array()) as $value) {
        $id = 0;

        if (is_int($value)) {
            $id = $value;
        } elseif (is_string($value) && ($value !== '') && ctype_digit($value)) {
            $id = (int) $value;
        }

        if (($id > 0) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * A gate's JOIN and WHERE (pg_sw_list_record_filter_clause()), as text.
 *
 * @param mixed $gate
 * @return array join, where
 */
function _pg_comments_recent_gate($gate)
{
    $gate = is_array($gate) ? $gate : array();

    return array(
        'join' => (isset($gate['join']) && is_string($gate['join'])) ? $gate['join'] : '',
        'where' => (isset($gate['where']) && is_string($gate['where'])) ? $gate['where'] : '',
    );
}

/**
 * The records of a source page whose newest published comment is newest, for
 * the 'published' scope: one statement per record source, read through
 * submitted_form_info (one row per record and page), so its cost follows the
 * records that have comments on the page, never the comments. The record
 * gate is part of it: the view's form, a complete record, the access
 * setting ($access) and the view's filters ($gate). 2k rows are read and
 * every record among them is read on (pg_comments_recent_read()): job.php
 * publishes a scheduled comment by giving it the current time, and writes
 * its older id as the record's newest_comment_id, so the record ranks below
 * records whose newest comment is older. The margin keeps such a record in
 * while it is among the 2k; below them it is listed after the record's next
 * comment. Pure.
 *
 * @param mixed  $page_id the source page
 * @param mixed  $form_id its record view's form
 * @param mixed  $gate    pg_sw_list_record_filter_clause()'s join and where
 * @param string $access  pg_comments_recent_access_sql()
 * @param int    $k       the limit plus one
 * @return string
 */
function pg_comments_recent_candidates_sql($page_id, $form_id, $gate, $access, $k)
{
    $gate = _pg_comments_recent_gate($gate);

    return "SELECT
            recent_info.submitted_form_id,
            recent_info.newest_comment_id,
            forms.reference_code,
            forms.address_name
        FROM submitted_form_info AS recent_info
        INNER JOIN forms ON (forms.id = recent_info.submitted_form_id) AND (forms.page_id = '" . (int) $form_id . "')"
        . $gate['join'] . "
        WHERE (recent_info.page_id = '" . (int) $page_id . "') AND (recent_info.newest_comment_id > 0)
            AND (forms.complete = 1)" . (string) $access . $gate['where'] . "
        ORDER BY recent_info.newest_comment_id DESC
        LIMIT " . (2 * max(1, (int) $k));
}

/**
 * The newest published comments of the records the candidates kept: one
 * statement, one part per record, each reading its thread backward on the
 * idx_thread key. '' without a record. Pure.
 *
 * @param mixed $page_id
 * @param mixed $record_ids
 * @param int   $k
 * @return string
 */
function pg_comments_recent_latest_sql($page_id, $record_ids, $k)
{
    $parts = array();

    foreach (_pg_comments_recent_ids($record_ids) as $record_id) {
        $parts[] = "(SELECT comments.id, comments.page_id, comments.item_id, comments.created_timestamp
            FROM comments
            WHERE (comments.page_id = '" . (int) $page_id . "') AND (comments.item_type = 'submitted_form')
                AND (comments.item_id = '" . $record_id . "') AND (comments.published = '1')
            ORDER BY comments.created_timestamp DESC LIMIT " . max(1, (int) $k) . ")";
    }

    return implode("\nUNION ALL\n", $parts);
}

/**
 * The comments of the page threads ('' / 0) of the page sources, in every
 * scope: one statement, one part per page, on the idx_thread key. '' without
 * a page. Pure.
 *
 * Each part reads past the item_id and published keys: item_id = 0 and
 * published = 1 match most comments of the site, yet the optimizer
 * intersects one of them with page_id and sorts the page's whole thread
 * (about 0.1 s for 50,000 comments; MyISAM takes item_id, InnoDB published)
 * instead of reading idx_thread backward (under 1 ms). Both keys are in
 * every install, so the hint never names a missing key; forcing idx_thread
 * would fail where the 11.0.31 step has not run yet.
 *
 * @param mixed  $page_ids
 * @param string $audience pg_comments_recent_audience_sql()
 * @param string $order    'newest' | 'oldest'
 * @param int    $k
 * @return string
 */
function pg_comments_recent_pages_sql($page_ids, $audience, $order, $k)
{
    $direction = ($order === 'oldest') ? 'ASC' : 'DESC';
    $parts = array();

    foreach (_pg_comments_recent_ids($page_ids) as $page_id) {
        $parts[] = "(SELECT comments.id, comments.page_id, comments.item_id, comments.created_timestamp
            FROM comments IGNORE INDEX (item_id, published)
            WHERE (comments.page_id = '" . $page_id . "') AND (comments.item_type = '') AND (comments.item_id = '0')" . (string) $audience . "
            ORDER BY comments.created_timestamp " . $direction . " LIMIT " . max(1, (int) $k) . ")";
    }

    return implode("\nUNION ALL\n", $parts);
}

/**
 * The comments of a record source for 'mine' and 'pending': one statement
 * with the record gate and the scope. Sorted by the time alone: a MyISAM
 * secondary key carries no primary key, so an id tie-break would keep the
 * created_timestamp key from giving the order; the merge breaks ties by id.
 * Pure.
 *
 * @param mixed  $page_id
 * @param mixed  $form_id
 * @param mixed  $gate
 * @param string $access
 * @param string $audience
 * @param string $order
 * @param int    $k
 * @return string
 */
function pg_comments_recent_direct_sql($page_id, $form_id, $gate, $access, $audience, $order, $k)
{
    $gate = _pg_comments_recent_gate($gate);

    return "SELECT
            comments.id,
            comments.page_id,
            comments.item_id,
            comments.created_timestamp,
            forms.reference_code,
            forms.address_name
        FROM comments
        INNER JOIN forms ON (forms.id = comments.item_id) AND (forms.page_id = '" . (int) $form_id . "')"
        . $gate['join'] . "
        WHERE (comments.page_id = '" . (int) $page_id . "') AND (comments.item_type = 'submitted_form')" . (string) $audience . "
            AND (forms.complete = 1)" . (string) $access . $gate['where'] . "
        ORDER BY comments.created_timestamp " . (($order === 'oldest') ? 'ASC' : 'DESC') . "
        LIMIT " . max(1, (int) $k);
}

/**
 * The drawn comments, by primary key, under the scope once more: a comment
 * moderated since it was picked is left out. Long messages are read only for
 * these. '' without an id. Pure.
 *
 * @param mixed  $ids
 * @param string $audience
 * @return string
 */
function pg_comments_recent_rows_sql($ids, $audience)
{
    $ids = _pg_comments_recent_ids($ids);

    if (!$ids) {
        return '';
    }

    return "SELECT
            comments.id,
            comments.page_id,
            comments.item_type,
            comments.item_id,
            comments.name,
            comments.message,
            comments.published,
            comments.publish_date_and_time,
            comments.publish_cancel,
            comments.featured,
            comments.rating,
            comments.created_user_id,
            comments.created_timestamp,
            user.user_username AS username,
            user.user_badge,
            user.user_badge_label
        FROM comments
        LEFT JOIN user ON comments.created_user_id = user.user_id
        WHERE (comments.id IN (" . implode(',', $ids) . "))" . (string) $audience;
}

/**
 * The title values of the drawn records: the form's RSS title fields and the
 * fields the record views name as their title (lower case, escaped), in
 * field order. Without a title field only the RSS titles are read. '' without
 * a record. Pure.
 *
 * @param mixed $record_ids
 * @param mixed $title_fields
 * @return string
 */
function pg_comments_recent_titles_sql($record_ids, $title_fields)
{
    $ids = _pg_comments_recent_ids($record_ids);

    if (!$ids) {
        return '';
    }

    $names = array();

    foreach ((is_array($title_fields) ? $title_fields : array()) as $field) {
        $field = is_scalar($field) ? strtolower(trim((string) $field)) : '';

        if (($field !== '') && !in_array($field, $names, true)) {
            $names[] = $field;
        }
    }

    $quoted = array();

    foreach ($names as $name) {
        $quoted[] = "'" . e($name) . "'";
    }

    $fields = $quoted
        ? "((form_fields.rss_field = 'title') OR (form_fields.name IN (" . implode(',', $quoted) . ")))"
        : "(form_fields.rss_field = 'title')";

    return "SELECT form_data.form_id, form_fields.name, form_fields.rss_field, form_data.data, form_data.type
        FROM form_data
        LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
        WHERE (form_data.form_id IN (" . implode(',', $ids) . "))
            AND " . $fields . "
        ORDER BY form_data.form_id ASC, form_fields.sort_order ASC, form_data.id ASC";
}

// ============================================================================
// The merge
// ============================================================================

/**
 * The sources' comments as one list: sorted by (created_timestamp, id) in
 * the order asked ('oldest' first, else newest), each comment once, cut to
 * $limit. 'more' says that more remain. An entry that is not an array or has
 * no id above 0 is left out. Pure.
 *
 * @param mixed  $lists  lists of entries: 'id', 'created_timestamp', ...
 * @param mixed  $order
 * @param mixed  $limit
 * @return array 'entries', 'more' (bool)
 */
function pg_comments_recent_merge($lists, $order, $limit)
{
    $all = array();

    foreach ((is_array($lists) ? $lists : array()) as $list) {
        if (!is_array($list)) {
            continue;
        }

        foreach ($list as $entry) {
            $id = is_array($entry) ? _pg_comments_row_int($entry, 'id') : 0;

            if (($id <= 0) || isset($all[$id])) {
                continue;
            }

            $entry['id'] = $id;
            $entry['created_timestamp'] = (int) _pg_comments_row_text($entry, 'created_timestamp');
            $all[$id] = $entry;
        }
    }

    $oldest = ($order === 'oldest');
    $entries = array_values($all);
    usort($entries, function ($a, $b) use ($oldest) {
        if ($a['created_timestamp'] !== $b['created_timestamp']) {
            $cmp = ($a['created_timestamp'] < $b['created_timestamp']) ? -1 : 1;
        } else {
            $cmp = ($a['id'] < $b['id']) ? -1 : (($a['id'] > $b['id']) ? 1 : 0);
        }

        return $oldest ? $cmp : -$cmp;
    });

    $limit = max(1, (int) $limit);

    return array(
        'entries' => array_slice($entries, 0, $limit),
        'more' => (count($entries) > $limit),
    );
}

// ============================================================================
// Flags and tokens
// ============================================================================

/**
 * The list's visibility flags. Pure. Facts: count (the comments drawn),
 * more (more remain than the list shows), signed_in, staff (the viewer may
 * edit at least one source page used). The editor's twin is
 * _sdCommentsRecentFlags() in assets/js/style_designer.js.
 *
 * Every other flag of the thread is absent, so a node shown only when one
 * holds is left out of a list (fail closed).
 *
 * @param mixed $facts
 * @return array the six list flags
 */
function pg_comments_recent_flags($facts)
{
    $facts = is_array($facts) ? $facts : array();
    $on = function ($key) use ($facts) {
        return !empty($facts[$key]);
    };
    $count = (isset($facts['count']) && is_scalar($facts['count']) && !is_bool($facts['count'])) ? (int) $facts['count'] : 0;

    $comments_thread_flags = array(
        'has_comments' => ($count > 0),
        'no_comments' => ($count === 0),
        'has_more_pages' => $on('more'),
        'is_signed_in' => $on('signed_in'),
        'is_signed_out' => !$on('signed_in'),
        'is_staff' => $on('staff'),
    );

    return $comments_thread_flags;
}

/**
 * The row flags a list adds: whether the comment belongs to a record's
 * thread or to a page's own. Pure.
 *
 * @param mixed $kind the source's kind: 'record' | 'page'
 * @return array
 */
function pg_comments_recent_row_flags($kind)
{
    $comments_row_flags = array(
        'comment_on_record' => ($kind === 'record'),
        'comment_on_page' => ($kind === 'page'),
    );

    return $comments_row_flags;
}

/**
 * The tokens a list gives one comment about where it stands, raw (the draw
 * escapes them). Pure.
 *
 * - __comment_url: its permalink on its own page, the thread's address with
 *   comments=all&comment=<id> (pg_comment_view_query()) and #c-<id>; '' for
 *   no address.
 * - __comment_anchor: '' (the comment is drawn on another page).
 * - __comment_thread_title, __comment_thread_url: its record's title and
 *   address, or its page's.
 * - __comment_label: the comment label of its page, singular, translated.
 *
 * @param mixed $comment_id
 * @param mixed $thread_url
 * @param mixed $thread_title
 * @param mixed $label
 * @return array
 */
function pg_comments_recent_link_values($comment_id, $thread_url, $thread_title, $label)
{
    $id = 0;

    if (is_int($comment_id)) {
        $id = $comment_id;
    } elseif (is_string($comment_id) && ($comment_id !== '') && ctype_digit($comment_id)) {
        $id = (int) $comment_id;
    }

    $text = function ($value) {
        return (is_scalar($value) && !is_bool($value)) ? (string) $value : '';
    };
    $url = $text($thread_url);

    $comment_list_links = array(
        '__comment_url' => (($id > 0) && ($url !== '')) ? pg_comment_view_query($url, $id) . '#c-' . $id : '',
        '__comment_anchor' => '',
        '__comment_thread_title' => $text($thread_title),
        '__comment_thread_url' => $url,
        '__comment_label' => $text($label),
    );

    return $comment_list_links;
}

/**
 * The title a list gives a comment's thread. Pure.
 *
 * A record's comment ($view_cfg an array): the title its record page names
 * it by, without the page's title suffix and cut to 255
 * (pg_sw_search_index_record_texts(): the view's title field, else the
 * form's RSS title fields); else its page's title. A page's comment
 * ($view_cfg not an array): the page's title. The page's name when the page
 * has no title.
 *
 * @param mixed $view_cfg       the record view's settings, null for a page
 * @param mixed $rows           the record's values in field order
 * @param mixed $reference_code
 * @param mixed $page_title     already in the language the page is drawn in
 * @param mixed $page_name
 * @return string
 */
function pg_comments_recent_title($view_cfg, $rows, $reference_code, $page_title, $page_name)
{
    $title = '';

    if (is_array($view_cfg)) {
        $texts = pg_sw_search_index_record_texts($view_cfg, is_array($rows) ? $rows : array(), is_scalar($reference_code) ? (string) $reference_code : '');
        $title = (string) $texts['title'];
    }

    if ($title === '') {
        $title = is_scalar($page_title) ? trim((string) $page_title) : '';
    }

    if ($title === '') {
        $title = is_scalar($page_name) ? trim((string) $page_name) : '';
    }

    return $title;
}

// ============================================================================
// What each mode switches off
// ============================================================================

/**
 * The bindings that do nothing in a mode: in a list ('recent') the form, the
 * thread's controls, its sections, the tokens only a thread fills and the
 * flags only a thread sets; in a thread (any other mode) the three tokens and
 * two flags only a list fills. Pure. The editor's twin is
 * _sdCommentsModeOffBindings() in assets/js/style_designer.js; it fades what
 * these lists name.
 *
 * @param mixed $mode
 * @return array 'actions', 'values', 'sections', 'tokens', 'flags'
 */
function pg_comments_mode_off_bindings($mode)
{
    if ($mode === 'recent') {
        return array(
            'actions' => array('comment_form', 'comment_submit', 'comment_reply', 'comment_reply_cancel', 'comment_watch_form', 'comment_watch',
                'comment_unwatch', 'comment_allow_new_form', 'comment_close', 'comment_reopen'),
            'values' => array('comment_message', 'comment_name', 'comment_name_type', 'comment_file', 'comment_watcher'),
            'sections' => array('comment_captcha', 'comment_rating_input', 'comment_publish', 'comment_watchers', 'comment_pagination'),
            'tokens' => array('__comments_title', '__comments_label', '__comments_label_plural', '__comments_intro', '__comments_closed_message',
                '__comments_show_all_url', '__comments_show_featured_url', '__login_url', '__comment_author_name', '__reply_to_name',
                '__watcher_count_text', '__comment_anchor', '__comment_file_name', '__comment_file_url', '__comment_file_size',
                '__comment_seller_reply_html', '__comment_seller_reply_date', '__comment_reply_url', '__comment_parent_id',
                '__comment_parent_name', '__comment_parent_url', '__comment_quote', '__comment_depth', '__comment_reply_count'),
            'flags' => array('comments_open', 'comments_closed', 'can_comment', 'must_sign_in', 'featured_view', 'has_featured', 'name_is_typed',
                'name_is_chosen', 'name_is_fixed', 'rating_on', 'files_on', 'watching_on', 'is_replying', 'is_watching', 'not_watching',
                'can_manage_watchers', 'is_email', 'comment_is_reply', 'comment_has_quote', 'comment_has_replies', 'comment_has_file',
                'comment_is_verified', 'comment_has_seller_reply'),
        );
    }

    return array(
        'actions' => array(),
        'values' => array(),
        'sections' => array(),
        'tokens' => array('__comment_thread_title', '__comment_thread_url', '__comment_label'),
        'flags' => array('comment_on_record', 'comment_on_page'),
    );
}

/**
 * Drop from a widget tree every node whose text, href or src binds a token
 * the mode does not fill (pg_comments_mode_off_bindings()), with what it
 * holds, so a list prints no empty quote and a thread no empty title. The
 * root stays. Returns how many nodes were dropped.
 *
 * @param array $tree
 * @param mixed $mode
 * @return int
 */
function pg_comments_drop_mode_bindings(&$tree, $mode)
{
    if (!is_array($tree)) {
        return 0;
    }

    $off = array_flip(pg_comments_mode_off_bindings($mode)['tokens']);
    $dropped = 0;
    _pg_comments_drop_mode_walk($tree, $off, $dropped, 0);

    return $dropped;
}

/**
 * pg_comments_drop_mode_bindings()'s walk.
 *
 * @param array $node
 * @param array $off     token => true
 * @param int   $dropped
 * @param int   $depth
 * @return void
 */
function _pg_comments_drop_mode_walk(&$node, $off, &$dropped, $depth)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children']) || ($depth > 200)) {
        return;
    }

    $kept = array();

    foreach ($node['children'] as $child) {
        if (is_array($child)) {
            $bindings = (isset($child['props']['_bindings']) && is_array($child['props']['_bindings'])) ? $child['props']['_bindings'] : array();
            $hit = false;

            foreach (array('text', 'href', 'src') as $prop) {
                if (isset($bindings[$prop]) && is_string($bindings[$prop]) && isset($off[$bindings[$prop]])) {
                    $hit = true;
                    break;
                }
            }

            if ($hit) {
                $dropped++;
                continue;
            }

            _pg_comments_drop_mode_walk($child, $off, $dropped, $depth + 1);
        }

        $kept[] = $child;
    }

    $node['children'] = $kept;
}

// ============================================================================
// The default layout
// ============================================================================

/**
 * The default layout of a list of recent comments, node for node the
 * editor's starter (_buildStarterTree('comments_recent') in
 * assets/js/style_designer.js; compared by tests/comments_recent_test.php).
 * No form, no Messages node. Its loop_area is also the server's fallback
 * for a stored layout without one (pg_comments_widget_draw()'s
 * 'default_tree'), drawn in a list-group.
 *
 * @return array
 */
function pg_comments_recent_default_tree()
{
    $node = function ($type, $props = array(), $children = array()) {
        return array('type' => $type, 'props' => $props, 'children' => $children);
    };
    $sem = function ($tag, $props = array(), $children = array()) use ($node) {
        return $node('semantic', array('tag' => $tag) + $props, $children);
    };
    $content = function ($content_type, $props = array()) use ($node) {
        return $node('content', array('contentType' => $content_type) + $props);
    };

    $comment = $sem('div', array('cssClass' => 'list-group-item px-0 py-3', 'customName' => lang('Comment')), array(
        $sem('div', array('cssClass' => 'small text-danger mb-1 pg-hide-if-empty', 'text' => lang('Awaiting approval'), 'customName' => lang('Comment Status'),
            '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'))),
        $content('link', array('text' => lang('How do I reset my password?'), 'href' => '#', 'cssClass' => 'fw-semibold d-block text-decoration-none',
            'customName' => lang('Thread Title'), '_bindings' => array('href' => '__comment_url', 'text' => '__comment_thread_title'))),
        $sem('div', array('cssClass' => 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mt-1', 'customName' => lang('Name Line')), array(
            $sem('span', array('cssClass' => 'fw-semibold text-body', 'text' => lang('Jane Cooper'), '_bindings' => array('text' => '__comment_name'))),
            $sem('span', array('cssClass' => 'badge rounded-pill text-bg-secondary', 'text' => lang('Badge'),
                '_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
            $sem('span', array('cssClass' => 'badge rounded-pill text-bg-primary', 'text' => lang('Featured'),
                '_bindings' => array('eo_visible_if' => 'comment_is_featured'))),
            $sem('span', array('cssClass' => 'pg-hide-if-empty', 'text' => lang('2 days ago'), '_bindings' => array('text' => '__comment_date'))),
        )),
        $sem('div', array('cssClass' => 'text-warning small mt-1', 'customName' => lang('Stars'),
            '_bindings' => array('text' => '__comment_stars_html', 'eo_visible_if' => 'comment_has_rating'))),
        $sem('p', array('cssClass' => 'small mb-0 mt-1', 'text' => lang('Comment text'), 'customName' => lang('Excerpt'),
            '_bindings' => array('text' => '__comment_excerpt'))),
    ));

    return $node('root', array(), array(
        $node('container', array('fluid' => false, 'cssClass' => 'py-4'), array(
            $content('heading', array('tag' => 'h2', 'text' => lang('Recent Comments'), 'cssClass' => 'h5 mb-3')),
            $sem('div', array('cssClass' => 'list-group list-group-flush', 'customName' => lang('Comment List')), array(
                $node('loop_area', array(), array($comment)),
            )),
            $content('paragraph', array('text' => lang('No comments yet.'), 'cssClass' => 'text-body-secondary mb-0',
                '_bindings' => array('eo_visible_if' => 'no_comments'))),
        )),
    ));
}

// ============================================================================
// The readers
// ============================================================================

/**
 * The source pages a list reads, each as the facts its statements need,
 * the refused ones left out. Q0 reads the pages in one statement; the rest
 * (the widgets placed, the components, the folders' access) comes from the
 * request's caches. A refused source says nothing: the list prints the same
 * empty state for no comments, no access, comments off and no page.
 *
 * $viewer: signed_in, user_id, live (pg_comments_widget_audience()'s
 * session: false for a background render).
 *
 * Each source: page_id, kind ('record' | 'page'), page_name, title (its
 * title in the language the page is drawn in, else its name), staff (edit
 * access in a live render), settings (pg_comments_settings()), url (its
 * address), and for a record source form_id, view_cfg, pretty, access
 * (pg_comments_recent_access_sql(), null for closed) and gate (the view's
 * filters and the list's, pg_sw_list_record_filter_clause(); null for
 * closed).
 *
 * @param mixed    $ids     pg_comments_recent_options()'s page_ids
 * @param mixed    $show
 * @param mixed    $viewer
 * @param callable $fetch   runs a SELECT and returns its rows (db_items)
 * @param mixed    $filters pg_comments_recent_options()'s filters: held,
 *                          after the view's own, to the one record source
 * @return array
 */
function pg_comments_recent_sources($ids, $show, $viewer, $fetch = 'db_items', $filters = array())
{
    $ids = _pg_comments_recent_ids($ids);
    $list_filters = is_array($filters) ? array_values(array_filter($filters, 'is_array')) : array();
    $filtered = false;

    foreach ($list_filters as $filter) {
        $filtered = $filtered || (isset($filter['field']) && is_scalar($filter['field']) && (trim((string) $filter['field']) !== ''));
    }

    $viewer = is_array($viewer) ? $viewer : array();
    $live = !empty($viewer['live']);
    $signed_in = $live && !empty($viewer['signed_in']);
    $user_id = $signed_in ? _pg_comments_row_int($viewer, 'user_id') : 0;

    if (!$ids) {
        return array();
    }

    $rows = call_user_func($fetch,
        "SELECT
            page.page_id,
            page.page_name,
            page.page_title,
            page.page_folder,
            page.page_type,
            page.comments,
            page.comments_label,
            page.comments_rating,
            page.comments_show_submitted_date_and_time,
            folder.folder_archived
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE page.page_id IN (" . implode(',', $ids) . ")");
    $by_id = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (is_array($row) && isset($row['page_id'])) {
            $by_id[(int) $row['page_id']] = $row;
        }
    }

    $bin = array_map('intval', (array) pg_recycle_bin_folder_ids());
    $preview = pg_comments_preview_page();
    $sources = array();

    foreach ($ids as $page_id) {
        $row = isset($by_id[$page_id]) ? $by_id[$page_id] : null;
        $facts = array('exists' => is_array($row), 'show' => $show, 'signed_in' => $signed_in, 'personal' => $signed_in);

        if (!is_array($row)) {
            continue;
        }

        $folder = (int) $row['page_folder'];
        $row = pg_comments_page_override($row, $preview);
        $settings = pg_comments_settings($row);
        $staff = $live && (check_edit_access($folder) == true);
        $widget = pg_sw_page_widget($page_id, 'form_item_view');
        $view_cfg = ($widget !== null) ? $widget['cfg'] : array();
        $form_id = 0;

        if ($widget !== null) {
            $form_id = (int) (!empty($view_cfg['custom_form_page_id']) ? $view_cfg['custom_form_page_id']
                : (!empty($view_cfg['form_page_id']) ? $view_cfg['form_page_id'] : 0));
        }

        // get_page.php asks a visitor who is not signed in to sign in before
        // it asks the folder, for some page types; asked only of such a
        // visitor (one query for a custom form's settings).
        $page_type = (string) $row['page_type'];
        $sign_in_page = false;

        if (!$signed_in) {
            $sign_in_page = pg_comments_recent_sign_in_page($page_type,
                ($page_type === 'custom form') ? get_page_type_properties($page_id, 'custom form') : null);
        }

        $facts += array(
            'binned' => in_array($folder, $bin, true),
            'archived' => ((string) $row['folder_archived'] === '1'),
            'staff' => $staff,
            'view' => $live ? (check_view_access($folder) == true) : (get_access_control_type($folder) === 'public'),
            'sign_in_page' => $sign_in_page,
            'comments_on' => $settings['enabled'],
            'page_type' => $page_type,
            'record' => ($widget !== null),
            'form_id' => $form_id,
            'viewer_filter' => ($widget !== null) && pg_sw_filters_compare_viewer(array_merge(pg_sw_record_view_filters($view_cfg), $list_filters)),
            'list_filters' => $filtered,
            'sources' => count($ids),
        );

        // Asked only of a page that would otherwise add its own thread: one
        // query per level of components it places.
        if (($widget === null) && pg_comments_recent_source_decision($facts)['use']) {
            $facts['nested'] = pg_comments_page_nested_record($page_id);
        }

        $decision = pg_comments_recent_source_decision($facts);

        if (!$decision['use']) {
            continue;
        }

        $title = trim((string) pg_tr_text((string) $row['page_title'], 'text', 'seo'));
        $source = array(
            'page_id' => $page_id,
            'kind' => $decision['kind'],
            'page_name' => (string) $row['page_name'],
            'title' => ($title !== '') ? $title : (string) $row['page_name'],
            'staff' => $staff,
            'settings' => $settings,
            'url' => pg_sw_page_link_base($row, $bin),
        );

        if ($decision['kind'] === 'record') {
            $access_type = (isset($view_cfg['access_control']) && in_array($view_cfg['access_control'], array('public', 'submitter_only', 'logged_in'), true))
                ? $view_cfg['access_control'] : 'public';
            $form_staff = false;

            if (($access_type === 'submitter_only') && $live) {
                $form_folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . $form_id . "' LIMIT 1");
                $form_staff = ($form_folder !== null) && ($form_folder !== '') && (check_edit_access($form_folder) == true);
            }

            $clause = pg_sw_list_record_filter_clause($form_id, array('filters' => $list_filters), $view_cfg, $page_id, pg_sw_filter_request());
            $source += array(
                'form_id' => $form_id,
                'view_cfg' => $view_cfg,
                'pretty' => pg_sw_form_pretty_urls($form_id),
                'access' => pg_comments_recent_access_sql($access_type, array('signed_in' => $signed_in, 'user_id' => $user_id, 'live' => $live, 'form_staff' => $form_staff)),
                'gate' => !empty($clause['closed']) ? null : array('join' => (string) $clause['join'], 'where' => (string) $clause['where']),
            );
        }

        $sources[] = $source;
    }

    return $sources;
}

/**
 * The comments a list draws, as merged entries: 'id', 'created_timestamp',
 * 'source' (the page id), 'record_id' (0 for a page's comment),
 * 'reference_code', 'address_name'; and 'more'.
 *
 * - 'published' on a record source: its 2k candidates
 *   (pg_comments_recent_candidates_sql()), then the newest k comments of
 *   every record among them (pg_comments_recent_latest_sql()), so a record
 *   whose newest comment job.php published late is not cut before the merge
 *   sorts by time.
 * - 'mine' and 'pending' on a record source: one statement with the gate
 *   and the scope (pg_comments_recent_direct_sql()).
 * - the page sources: one statement for all (pg_comments_recent_pages_sql()).
 * A source whose access or filters show the viewer nothing, or a scope that
 * gives the viewer nothing, sends no statement. 'published' is read newest
 * first, whatever the order asked: its candidates are picked by their newest
 * comment.
 *
 * @param mixed    $sources pg_comments_recent_sources()
 * @param mixed    $options pg_comments_recent_options()
 * @param mixed    $viewer  signed_in, user_id, live, since (the account's
 *                          creation time for 'mine', 0 when unknown)
 * @param callable $fetch
 * @return array 'entries', 'more'
 */
function pg_comments_recent_read($sources, $options, $viewer, $fetch = 'db_items')
{
    $options = is_array($options) ? $options : array();
    $viewer = is_array($viewer) ? $viewer : array();
    $show = (isset($options['show']) && is_string($options['show'])) ? $options['show'] : 'published';
    $limit = (isset($options['limit']) && is_int($options['limit'])) ? max(1, $options['limit']) : 5;
    $k = $limit + 1;
    $order = (($show !== 'published') && isset($options['order']) && ($options['order'] === 'oldest')) ? 'oldest' : 'newest';
    $signed_in = !empty($viewer['live']) && !empty($viewer['signed_in']);
    $audience = pg_comments_recent_audience_sql($show, $signed_in ? _pg_comments_row_int($viewer, 'user_id') : 0,
        $signed_in ? _pg_comments_row_int($viewer, 'since') : 0);
    $none = array('entries' => array(), 'more' => false);

    if ($audience === null) {
        return $none;
    }

    $lists = array();
    $page_ids = array();
    $entry = function ($row, $source_id, $record_id, $reference_code, $address_name) {
        return array(
            'id' => _pg_comments_row_int($row, 'id'),
            'created_timestamp' => (int) _pg_comments_row_text($row, 'created_timestamp'),
            'source' => (int) $source_id,
            'record_id' => (int) $record_id,
            'reference_code' => (string) $reference_code,
            'address_name' => (string) $address_name,
        );
    };

    foreach ((is_array($sources) ? $sources : array()) as $source) {
        if (!is_array($source) || !isset($source['page_id'], $source['kind'])) {
            continue;
        }

        $page_id = (int) $source['page_id'];

        if ($source['kind'] === 'page') {
            $page_ids[] = $page_id;
            continue;
        }

        if (!array_key_exists('access', $source) || !is_string($source['access']) || !isset($source['gate']) || !is_array($source['gate'])) {
            continue;
        }

        $form_id = isset($source['form_id']) ? (int) $source['form_id'] : 0;
        $list = array();

        if ($show === 'published') {
            $candidates = call_user_func($fetch, pg_comments_recent_candidates_sql($page_id, $form_id, $source['gate'], $source['access'], $k));
            $records = array();

            foreach ((is_array($candidates) ? $candidates : array()) as $candidate) {
                $record_id = _pg_comments_row_int($candidate, 'submitted_form_id');

                if (($record_id <= 0) || isset($records[$record_id])) {
                    continue;
                }

                $records[$record_id] = $candidate;
            }

            if (!$records) {
                continue;
            }

            $rows = call_user_func($fetch, pg_comments_recent_latest_sql($page_id, array_keys($records), $k));

            foreach ((is_array($rows) ? $rows : array()) as $row) {
                $record_id = _pg_comments_row_int($row, 'item_id');

                if (isset($records[$record_id])) {
                    $list[] = $entry($row, $page_id, $record_id, _pg_comments_row_text($records[$record_id], 'reference_code'),
                        _pg_comments_row_text($records[$record_id], 'address_name'));
                }
            }
        } else {
            $rows = call_user_func($fetch, pg_comments_recent_direct_sql($page_id, $form_id, $source['gate'], $source['access'], $audience, $order, $k));

            foreach ((is_array($rows) ? $rows : array()) as $row) {
                $list[] = $entry($row, $page_id, _pg_comments_row_int($row, 'item_id'), _pg_comments_row_text($row, 'reference_code'),
                    _pg_comments_row_text($row, 'address_name'));
            }
        }

        $lists[] = $list;
    }

    if ($page_ids) {
        $rows = call_user_func($fetch, pg_comments_recent_pages_sql($page_ids, $audience, $order, $k));
        $list = array();

        foreach ((is_array($rows) ? $rows : array()) as $row) {
            $list[] = $entry($row, _pg_comments_row_int($row, 'page_id'), 0, '', '');
        }

        $lists[] = $list;
    }

    return $lists ? pg_comments_recent_merge($lists, $order, $limit) : $none;
}

/**
 * When the account $user_id was made: user.user_created, which every screen
 * that makes an account writes once (pg_user_created_stamp()). 0 when it is
 * unknown: no account, an account made before the column existed, or a site
 * that has not run the upgrade that adds it (probed first).
 *
 * @param mixed $user_id
 * @return int
 */
function pg_comments_recent_account_since($user_id)
{
    $id = (is_int($user_id) || (is_string($user_id) && ($user_id !== '') && ctype_digit($user_id))) ? (int) $user_id : 0;

    if (($id <= 0) || !pg_schema_has('user', 'user_created')) {
        return 0;
    }

    return max(0, (int) db_value("SELECT user_created FROM user WHERE user_id = '" . $id . "' LIMIT 1"));
}

/**
 * The drawn comments' rows, by id (pg_comments_recent_rows_sql()). No
 * statement without an id.
 *
 * @param mixed    $ids
 * @param string   $audience
 * @param callable $fetch
 * @return array id => row
 */
function pg_comments_recent_rows($ids, $audience, $fetch = 'db_items')
{
    $sql = pg_comments_recent_rows_sql($ids, $audience);

    if ($sql === '') {
        return array();
    }

    $out = array();

    foreach ((array) call_user_func($fetch, $sql) as $row) {
        $id = is_array($row) ? _pg_comments_row_int($row, 'id') : 0;

        if ($id > 0) {
            $out[$id] = $row;
        }
    }

    return $out;
}

/**
 * The thread title of each drawn comment, as "<page id>:<record id>" =>
 * title (pg_comments_recent_title()). The values of the drawn records are
 * read in one statement (pg_comments_recent_titles_sql()), only for records
 * that passed the gate; page comments read none.
 *
 * @param mixed    $entries pg_comments_recent_read()'s entries
 * @param mixed    $sources pg_comments_recent_sources()
 * @param callable $fetch
 * @return array
 */
function pg_comments_recent_titles($entries, $sources, $fetch = 'db_items')
{
    $by_source = array();

    foreach ((is_array($sources) ? $sources : array()) as $source) {
        if (is_array($source) && isset($source['page_id'])) {
            $by_source[(int) $source['page_id']] = $source;
        }
    }

    $entries = is_array($entries) ? $entries : array();
    $record_ids = array();
    $fields = array();

    foreach ($entries as $entry) {
        $source_id = is_array($entry) ? _pg_comments_row_int($entry, 'source') : 0;
        $record_id = is_array($entry) ? _pg_comments_row_int($entry, 'record_id') : 0;

        if (($record_id <= 0) || !isset($by_source[$source_id]) || ($by_source[$source_id]['kind'] !== 'record')) {
            continue;
        }

        $record_ids[$record_id] = true;
        $view_cfg = isset($by_source[$source_id]['view_cfg']) && is_array($by_source[$source_id]['view_cfg']) ? $by_source[$source_id]['view_cfg'] : array();
        $field = (isset($view_cfg['title_field']) && is_scalar($view_cfg['title_field'])) ? strtolower(trim((string) $view_cfg['title_field'])) : '';

        if ($field !== '') {
            $fields[$field] = true;
        }
    }

    $values = array();

    if ($record_ids) {
        foreach ((array) call_user_func($fetch, pg_comments_recent_titles_sql(array_keys($record_ids), array_keys($fields))) as $row) {
            $form_id = is_array($row) ? _pg_comments_row_int($row, 'form_id') : 0;

            if ($form_id > 0) {
                $values[$form_id][] = $row;
            }
        }
    }

    $out = array();

    foreach ($entries as $entry) {
        $source_id = is_array($entry) ? _pg_comments_row_int($entry, 'source') : 0;
        $record_id = is_array($entry) ? _pg_comments_row_int($entry, 'record_id') : 0;
        $key = $source_id . ':' . $record_id;

        if (!isset($by_source[$source_id]) || isset($out[$key])) {
            continue;
        }

        $source = $by_source[$source_id];
        $record = ($source['kind'] === 'record') && ($record_id > 0);
        $out[$key] = pg_comments_recent_title(
            $record ? (isset($source['view_cfg']) && is_array($source['view_cfg']) ? $source['view_cfg'] : array()) : null,
            ($record && isset($values[$record_id])) ? $values[$record_id] : array(),
            $record ? _pg_comments_row_text($entry, 'reference_code') : '',
            isset($source['title']) ? $source['title'] : '',
            isset($source['page_name']) ? $source['page_name'] : '');
    }

    return $out;
}

// ============================================================================
// The renderer
// ============================================================================

/**
 * Render a comments widget in the recent mode: the newest comments of its
 * source pages, each linked to the page that holds it.
 *
 * Called by _render_system_widget_comments() before it looks at the drawn
 * page: a list never claims a thread, reads no add-comment form state and
 * prints no form, token, CAPTCHA or hidden thread field. It prints nothing
 * in an e-mail, for the search index (other pages' comments would be found
 * under the page that lists them) and without a source page. A background
 * render (the editor's Preview, the SEO pass) reads what a visitor who is
 * not signed in reads, from public folders only.
 *
 * Tokens: ^^__comments_count^^ (the comments drawn); per comment
 * pg_comments_widget_row_values() (each with its own page's settings and
 * staff) and pg_comments_recent_link_values(). Flags:
 * pg_comments_recent_flags(); per comment pg_comments_widget_row_flags() and
 * pg_comments_recent_row_flags().
 *
 * @param array  $tree      the widget's layout, decoded
 * @param int    $widget_id
 * @param array  $cfg
 * @param string $mode
 * @return string
 */
function _render_system_widget_comments_recent($tree, $widget_id, $cfg, $mode = 'preview')
{
    if (!is_array($tree)) {
        return '';
    }

    $ctx = pg_sw_render_context();

    if (!empty($ctx['email']) || (defined('UPDATE_SEARCH_INDEX') && (UPDATE_SEARCH_INDEX === true))) {
        return '';
    }

    $options = pg_comments_recent_options($cfg);

    if (!$options['page_ids']) {
        return '';
    }

    $background = function_exists('pg_seo_rendering') && pg_seo_rendering();
    $audience = pg_comments_widget_audience(false, $background, false, _pg_acct_signed_in());
    $user_id = ($audience['signed_in'] && defined('USER_ID') && is_scalar(USER_ID)) ? (int) USER_ID : 0;
    $signed_in = $audience['signed_in'] && ($user_id > 0);
    $since = ($signed_in && $audience['session'] && ($options['show'] === 'mine')) ? pg_comments_recent_account_since($user_id) : 0;
    $viewer = array('signed_in' => $signed_in, 'user_id' => $user_id, 'live' => $audience['session'], 'since' => $since);

    $sources = pg_comments_recent_sources($options['page_ids'], $options['show'], $viewer, 'db_items', $options['filters']);
    $read = pg_comments_recent_read($sources, $options, $viewer);
    $audience_sql = pg_comments_recent_audience_sql($options['show'], $viewer['signed_in'] ? $user_id : 0, $since);
    $ids = array();

    foreach ($read['entries'] as $entry) {
        $ids[] = $entry['id'];
    }

    $rows_by_id = ($ids && ($audience_sql !== null)) ? pg_comments_recent_rows($ids, $audience_sql) : array();
    $titles = pg_comments_recent_titles($read['entries'], $sources);
    $by_source = array();
    $staff_any = false;

    foreach ($sources as $source) {
        $by_source[$source['page_id']] = $source;
        $staff_any = $staff_any || $source['staff'];
    }

    $badge_label = defined('BADGE_LABEL') ? (string) BADGE_LABEL : '';
    $rows = array();

    foreach ($read['entries'] as $entry) {
        $id = $entry['id'];
        $row = isset($rows_by_id[$id]) ? $rows_by_id[$id] : null;
        $source = isset($by_source[$entry['source']]) ? $by_source[$entry['source']] : null;

        if (!is_array($row) || !is_array($source)) {
            continue;
        }

        // The row read by its id is still the comment the gated statement
        // picked: on the same page, in the same thread.
        $record = ($source['kind'] === 'record');

        if ((_pg_comments_row_int($row, 'page_id') !== $source['page_id'])
            || (_pg_comments_row_text($row, 'item_type') !== ($record ? 'submitted_form' : ''))
            || (_pg_comments_row_int($row, 'item_id') !== ($record ? $entry['record_id'] : 0))
        ) {
            continue;
        }

        $settings = $source['settings'];
        $staff = $source['staff'];
        $own = ($options['show'] === 'mine') && ($user_id > 0) && (_pg_comments_row_int($row, 'created_user_id') === $user_id);
        $state = array(
            'is_staff' => $staff,
            'sees_pending' => $staff,
            'own_ids' => $own ? array($id) : array(),
            'rating_on' => $settings['rating'],
            'badge_label' => $badge_label,
        );
        $flags = pg_comments_widget_row_flags($row, (_pg_comments_row_text($row, 'featured') === '1'), $state, array('depth' => 0))
            + pg_comments_recent_row_flags($source['kind']);
        $label = get_comment_label(array('label' => $settings['label']));
        $env = array(
            'is_staff' => $staff,
            'sees_pending' => $staff,
            'show_date' => $settings['show_date'],
            'label_lower' => mb_strtolower($label),
            'badge_label' => $badge_label,
        );
        $thread_url = $record
            ? pg_sw_record_url($source['page_name'], $entry['reference_code'], $entry['address_name'], !empty($source['pretty']))
            : $source['url'];
        $key = $source['page_id'] . ':' . ($record ? $entry['record_id'] : 0);
        $created = (int) _pg_comments_row_text($row, 'created_timestamp');

        $rows[] = array(
            'id' => $id,
            'flags' => $flags,
            'values' => pg_comments_recent_link_values($id, $thread_url, isset($titles[$key]) ? $titles[$key] : $source['title'], $label)
                + pg_comments_widget_row_values($row, $flags, $env),
            'datetime' => ($settings['show_date'] && ($created > 0)) ? date('Y-m-d H:i:s', $created) : '',
            'depth' => 0,
        );
    }

    $thread_flags = pg_comments_recent_flags(array(
        'count' => count($rows),
        'more' => $read['more'],
        'signed_in' => $viewer['signed_in'],
        'staff' => $staff_any,
    ));

    // The thread's tokens and controls have no place in a list; its Messages
    // node is named for a form nothing posts to, so it prints and uses up
    // nobody's messages.
    pg_comments_drop_mode_bindings($tree, 'recent');
    _pg_inject_messages_node($tree, 'comments_recent');

    return pg_comments_widget_draw($tree, array(
        'recent' => true,
        'default_tree' => pg_comments_recent_default_tree(),
        'widget_id' => (int) $widget_id,
        'thread_flags' => $thread_flags,
        'thread_values' => array('__comments_count' => pg_format_number(count($rows), 0)),
        'rows' => $rows,
        'form' => array('can_comment' => false),
        'controls' => array(
            'can_reply' => false,
            'can_watch' => false,
            'is_staff' => false,
            'share_copied' => lang('Link copied.'),
            'share_prompt' => lang('Copy the link to this comment:'),
        ),
        'sections' => array(),
        'slots' => array(),
        'edit_chips' => $staff_any && pg_sw_edit_chips_on(),
    ));
}

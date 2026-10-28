<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The comments system widget: a page's comment thread and the form that adds
 * a comment to it, drawn in the layout a design gives the widget. The layout
 * is shared by every page that places the widget; the comments and the
 * comment settings stay the page's own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the comments system widget (regionType
// 'comments').
//
// Loaded by functions.php through require_once, never on its own.
//
// The thread a widget shows is the drawn page's (pg_sw_render_context()'s
// 'page_id'), or the record its form_item_view widget shows. Its rules - the
// page's settings, whether it takes new comments, who reads it, which
// comments they read - are the ones the classic comments block of
// get_page_content.php asks (includes/fn/comments.php); the classic block
// leaves a page alone once a widget drew its thread
// (pg_comments_widget_drawn()).
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ============================================================================
// The hand-off: which pages a widget drew in this pass
// ============================================================================

/**
 * The pages whose comment thread a comments widget drew in the current
 * get_page_content() pass, as page id => true.
 *
 * get_page_content() starts each widget pass with an empty record and puts
 * the previous one back afterwards, keeping the pass's own record for its
 * classic comments block: an e-mail or another page drawn while a page is
 * drawn (the search index, the SEO pass) neither adds to the page's record
 * nor clears it. Pass an array to replace the record (the previous one comes
 * back); pass nothing to read it.
 *
 * @param array|null $set
 * @return array
 */
function pg_comments_widget_pages($set = null)
{
    static $pages = array();

    if (is_array($set)) {
        $old = $pages;
        $pages = $set;
        return $old;
    }

    return $pages;
}

/**
 * A page id as the record keys it: a whole number above 0, or a string of
 * digits naming one. Anything else (0, a sign, letters, spaces, an array, a
 * bool, a fraction) is 0, no page.
 *
 * @param mixed $page_id
 * @return int
 */
function _pg_comments_widget_page_key($page_id)
{
    if (is_int($page_id)) {
        return ($page_id > 0) ? $page_id : 0;
    }

    if (is_string($page_id) && ($page_id !== '') && ctype_digit($page_id)) {
        $id = (int) $page_id;
        return ($id > 0) ? $id : 0;
    }

    return 0;
}

/**
 * Claim a page's thread for the widget being drawn: true only for the first
 * claim of a valid page id in this pass. A second comments widget on the same
 * page gets false and prints nothing, and an id that names no page claims
 * nothing.
 *
 * @param mixed $page_id
 * @return bool
 */
function pg_comments_widget_claim($page_id)
{
    $id = _pg_comments_widget_page_key($page_id);

    if ($id <= 0) {
        return false;
    }

    $pages = pg_comments_widget_pages();

    if (isset($pages[$id])) {
        return false;
    }

    $pages[$id] = true;
    pg_comments_widget_pages($pages);

    return true;
}

/**
 * Whether a comments widget drew this page's thread: in $pages when it is an
 * array (the record a pass handed back), else in the current record.
 *
 * @param mixed      $page_id
 * @param array|null $pages
 * @return bool
 */
function pg_comments_widget_drawn($page_id, $pages = null)
{
    $id = _pg_comments_widget_page_key($page_id);

    if ($id <= 0) {
        return false;
    }

    $record = is_array($pages) ? $pages : pg_comments_widget_pages();

    return isset($record[$id]);
}

// ============================================================================
// Who a thread is drawn for, and the editor's Preview
// ============================================================================

/**
 * Who the widget draws a thread for. Pure.
 *
 * A live visitor's request reads and writes the visitor's session: the
 * add-comment form state, the CAPTCHA, the CSRF token, the visitor's own
 * pending comments. The search index and a background render (the editor's
 * Preview, the SEO pass: pg_seo_rendering()) draw what a visitor who is not
 * signed in reads, and never touch the session of the user whose request
 * runs them.
 *
 * @param mixed $search     the page is drawn for the search index
 * @param mixed $background a background render
 * @param mixed $staff      edit access to the page
 * @param mixed $signed_in  a signed-in visitor
 * @return array 'staff', 'signed_in', 'session' (bool)
 */
function pg_comments_widget_audience($search, $background, $staff, $signed_in)
{
    $anonymous = (bool) $search || (bool) $background;

    return array(
        'staff' => (bool) $staff && !$anonymous,
        'signed_in' => (bool) $signed_in && !$anonymous,
        'session' => !$anonymous,
    );
}

/**
 * The comment settings the editor holds for the page it previews, saved or
 * not: array('page_id' => int, 'columns' => column => value), the columns as
 * pg_comment_editor_columns() gives them. pg_designer_preview_widgets() sets
 * it around the Preview and clears it after.
 *
 * Pass an array with a page id to set it; pass anything else to clear it;
 * either way the previous value comes back. Pass nothing to read (null when
 * none is set).
 *
 * @param mixed $set
 * @return array|null
 */
function pg_comments_preview_page($set = null)
{
    static $preview = null;

    if (func_num_args() === 0) {
        return $preview;
    }

    $old = $preview;
    $page_id = (is_array($set) && isset($set['page_id']) && is_scalar($set['page_id']) && !is_bool($set['page_id'])) ? (int) $set['page_id'] : 0;
    $preview = ($page_id > 0)
        ? array('page_id' => $page_id, 'columns' => (isset($set['columns']) && is_array($set['columns'])) ? $set['columns'] : array())
        : null;

    return $old;
}

/**
 * A page row with the editor's unsaved comment settings in place of the
 * stored ones, for the page the Preview draws (pg_comments_preview_page()).
 * Pure.
 *
 * Only a comment setting column (pg_comment_editor_keys()) that the row
 * holds is replaced, as a string, the way the row reads it from the
 * database. Another page's row, or no override, comes back as it is.
 *
 * @param mixed $row      the page row
 * @param mixed $override array('page_id', 'columns'), or null
 * @return mixed
 */
function pg_comments_page_override($row, $override)
{
    if (!is_array($row) || !is_array($override) || !isset($override['page_id'], $override['columns']) || !is_array($override['columns'])) {
        return $row;
    }

    if (!isset($row['page_id']) || !is_scalar($override['page_id']) || ((int) $override['page_id'] <= 0)
        || ((int) $override['page_id'] !== (int) $row['page_id'])) {
        return $row;
    }

    $allowed = array();

    foreach (pg_comment_editor_keys() as $def) {
        foreach ($def['columns'] as $column) {
            $allowed[$column] = true;
        }
    }

    foreach ($override['columns'] as $column => $value) {
        if (isset($allowed[$column]) && array_key_exists($column, $row) && is_scalar($value)) {
            $row[$column] = (string) $value;
        }
    }

    return $row;
}

// ============================================================================
// Display options, the order a thread is drawn in, the featured links
// ============================================================================

/**
 * The widget's display options, read from its system_region_config.
 *
 * - order: 'oldest' (default) or 'newest' first.
 * - featured: 'first' (default: featured top-level comments pinned to the
 *   top, the others after them), 'filter' (the classic block's: only the
 *   featured ones while some exist, until "Show all") or 'none' (the featured
 *   mark is ignored).
 * - layout: 'flat' (default: every comment in the order of the thread, a
 *   reply quoting the comment it answers) or 'threaded' (replies under the
 *   comment they answer); max_depth 1-3 (default 1), the deepest a reply is
 *   drawn (pg_comments_widget_order()).
 * - per_page: 0 (all, default), 10, 20 or 50 top-level comments a page,
 *   each with its replies (pg_comments_widget_paginate()).
 *
 * The words must be strings; max_depth and per_page take whole numbers or
 * strings of digits. Anything else reads as the default.
 *
 * @param mixed $cfg
 * @return array
 */
function pg_comments_widget_options($cfg)
{
    $cfg = is_array($cfg) ? $cfg : array();

    $word = function ($key, $allowed, $default) use ($cfg) {
        return (isset($cfg[$key]) && is_string($cfg[$key]) && in_array($cfg[$key], $allowed, true)) ? $cfg[$key] : $default;
    };
    $number = function ($key) use ($cfg) {
        if (!isset($cfg[$key])) {
            return null;
        }
        if (is_int($cfg[$key])) {
            return $cfg[$key];
        }
        if (is_string($cfg[$key]) && ($cfg[$key] !== '') && ctype_digit($cfg[$key])) {
            return (int) $cfg[$key];
        }
        return null;
    };

    $depth = $number('max_depth');
    $per_page = $number('per_page');

    return array(
        'order' => $word('order', array('oldest', 'newest'), 'oldest'),
        'featured' => $word('featured', array('first', 'filter', 'none'), 'first'),
        'layout' => $word('layout', array('flat', 'threaded'), 'flat'),
        'max_depth' => ($depth === null) ? 1 : min(3, max(1, $depth)),
        'per_page' => in_array($per_page, array(0, 10, 20, 50), true) ? $per_page : 0,
    );
}

/**
 * The order a widget draws a thread's comments in. Pure.
 *
 * The rows come in the thread's order for the layout and max_depth
 * (pg_comments_thread_order(): flat, every comment at depth 0 in the order
 * read; threaded, each top-level comment followed by its replies, capped at
 * max_depth). A unit is an entry at depth 0 with the replies drawn under it,
 * so in the flat layout every comment is a unit of its own. 'newest' turns
 * the units around and keeps each unit's replies oldest first. In the
 * 'first' mode the units whose top-level comment is featured move to the
 * top, keeping their order; a featured reply never moves. 'filter' and
 * 'none' keep the order.
 *
 * Each entry carries 'featured' (the row's featured mark, false in the
 * 'none' mode) and 'quoted': whether the comment quotes the one it answers.
 * In the flat layout every reply whose comment is among the rows quotes it;
 * in the threaded layout only a reply drawn no deeper than its comment (one
 * capped at max_depth) does, since any other sits right under it.
 *
 * @param mixed $rows    comment rows (pg_comments_rows()'s 'rows')
 * @param mixed $options pg_comments_widget_options()
 * @return array entries: 'row', 'id', 'parent_id', 'depth', 'root_id',
 *               'replies', 'featured', 'quoted'
 */
function pg_comments_widget_order($rows, $options)
{
    $options = pg_comments_widget_options($options);
    $entries = pg_comments_thread_order($rows, $options['layout'], $options['max_depth']);
    $depth_of = array();

    foreach ($entries as $entry) {
        if (!isset($depth_of[$entry['id']])) {
            $depth_of[$entry['id']] = $entry['depth'];
        }
    }

    $units = array();

    foreach ($entries as $entry) {
        $parent = $entry['parent_id'];
        $entry['featured'] = ($options['featured'] !== 'none') && _pg_comments_setting_is($entry['row'], 'featured', 1);
        $entry['quoted'] = ($parent > 0)
            && (($options['layout'] !== 'threaded') || (isset($depth_of[$parent]) && ($depth_of[$parent] >= $entry['depth'])));

        if (($entry['depth'] === 0) || !$units) {
            $units[] = array($entry);
        } else {
            $units[count($units) - 1][] = $entry;
        }
    }

    if ($options['order'] === 'newest') {
        $units = array_reverse($units);
    }

    if ($options['featured'] === 'first') {
        $pinned = array();
        $rest = array();

        foreach ($units as $unit) {
            if ($unit[0]['featured'] && ($unit[0]['parent_id'] === 0)) {
                $pinned[] = $unit;
            } else {
                $rest[] = $unit;
            }
        }

        $units = array_merge($pinned, $rest);
    }

    $out = array();

    foreach ($units as $unit) {
        foreach ($unit as $entry) {
            $out[] = $entry;
        }
    }

    return $out;
}

/**
 * The page number a ?comments_page= value asks for: a whole number above 0,
 * or a string of one to six digits naming one. Anything else (0, a sign,
 * spaces, an exponent, more digits, an array, a bool) is 0, no page. Pure.
 *
 * @param mixed $value
 * @return int
 */
function pg_comments_widget_page_param($value)
{
    if (is_int($value)) {
        return ($value > 0) ? $value : 0;
    }

    if (is_string($value) && preg_match('/^[0-9]{1,6}$/', $value)) {
        return max(0, (int) $value);
    }

    return 0;
}

/**
 * One page of a thread's entries. Pure.
 *
 * A page holds $per_page units: an entry at depth 0 with the replies drawn
 * under it (pg_comments_widget_order()), so a reply is never cut from its
 * comment. $per_page 0 (or anything that is not a whole number above 0) puts
 * every entry on one page. The page is $requested (a whole number above 0,
 * a page past the end reads as the last), else the page of the unit that
 * holds the entry whose id is $comment_id, else the first. Entries that are
 * not arrays are skipped.
 *
 * @param mixed $entries     pg_comments_widget_order()
 * @param mixed $per_page
 * @param mixed $requested   pg_comments_widget_page_param()
 * @param mixed $comment_id  the comment the address names (?comment=), 0
 * @return array 'page', 'pages', 'entries' (that page's, in order), 'ids'
 *               (id => true for each of them)
 */
function pg_comments_widget_paginate($entries, $per_page, $requested, $comment_id)
{
    $units = array();

    foreach ((is_array($entries) ? $entries : array()) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $depth = isset($entry['depth']) ? (int) $entry['depth'] : 0;

        if (($depth === 0) || !$units) {
            $units[] = array($entry);
        } else {
            $units[count($units) - 1][] = $entry;
        }
    }

    $per = (is_int($per_page) && ($per_page > 0)) ? $per_page : 0;
    $pages = $per ? max(1, (int) ceil(count($units) / $per)) : 1;
    $page = 1;

    if (is_int($requested) && ($requested > 0)) {
        $page = min($requested, $pages);
    } elseif ($per && is_int($comment_id) && ($comment_id > 0)) {
        foreach ($units as $u => $unit) {
            foreach ($unit as $entry) {
                if (isset($entry['id']) && ((int) $entry['id'] === $comment_id)) {
                    $page = (int) floor($u / $per) + 1;
                    break 2;
                }
            }
        }
    }

    $out = array('page' => $page, 'pages' => $pages, 'entries' => array(), 'ids' => array());

    foreach (($per ? array_slice($units, ($page - 1) * $per, $per) : $units) as $unit) {
        foreach ($unit as $entry) {
            $out['entries'][] = $entry;

            if (isset($entry['id'])) {
                $out['ids'][(int) $entry['id']] = true;
            }
        }
    }

    return $out;
}

/**
 * An address on the current page: $path, the current query parameters
 * ($params, _pg_sw_query_params()) except those named in $drop and any that
 * is not a scalar, then the pairs of $add, each name and value urlencode()d
 * as pg_comments_widget_view_urls() writes them; '?' only when there are
 * pairs, '#$fragment' when it is not ''. Raw: the token escape does h().
 * Pure.
 *
 * @param mixed $path
 * @param mixed $params
 * @param mixed $drop     names to leave out
 * @param mixed $add      name => value to add
 * @param mixed $fragment
 * @return string
 */
function pg_comments_widget_page_url($path, $params, $drop, $add, $fragment)
{
    $drop = is_array($drop) ? $drop : array();
    $pairs = array();

    foreach ((is_array($params) ? $params : array()) as $name => $value) {
        if (in_array((string) $name, $drop, true) || !is_scalar($value)) {
            continue;
        }

        $pairs[] = urlencode((string) $name) . '=' . urlencode((string) $value);
    }

    foreach ((is_array($add) ? $add : array()) as $name => $value) {
        if (is_scalar($value)) {
            $pairs[] = urlencode((string) $name) . '=' . urlencode((string) $value);
        }
    }

    $fragment = (is_scalar($fragment) && !is_bool($fragment)) ? (string) $fragment : '';

    return (is_scalar($path) ? (string) $path : '')
        . ($pairs ? '?' . implode('&', $pairs) : '')
        . (($fragment !== '') ? '#' . $fragment : '');
}

/**
 * The two addresses of the featured filter: 'all' shows every comment
 * (?comments=all), 'featured' goes back to the featured ones. The current
 * query is kept, except ?comments, the comment an address named (?comment=)
 * and the page of the thread (?comments_page=), which belong to another
 * view, and any value that is not a scalar, each name and value
 * urlencode()d as the classic block wrote them. Both end at
 * #software_comments. Raw: the token escape does h().
 *
 * @param mixed $path   the current address without its query
 * @param mixed $params the query parameters (_pg_sw_query_params())
 * @return array 'all', 'featured'
 */
function pg_comments_widget_view_urls($path, $params)
{
    $query = '';

    foreach ((is_array($params) ? $params : array()) as $name => $value) {
        if (in_array((string) $name, array('comments', 'comment', 'comments_page'), true) || !is_scalar($value)) {
            continue;
        }

        $query .= (($query === '') ? '?' : '&') . urlencode((string) $name) . '=' . urlencode((string) $value);
    }

    $base = (is_scalar($path) ? (string) $path : '') . $query;

    return array(
        'all' => $base . (($query === '') ? '?' : '&') . 'comments=all#software_comments',
        'featured' => $base . '#software_comments',
    );
}

// ============================================================================
// Visibility flags ("Show only when")
// ============================================================================

/**
 * The thread's visibility flags. Pure.
 *
 * Facts: allow_new, signed_in, require_login, search_index, count,
 * featured_view, has_featured, featured_mode, staff, name_mode ('typed',
 * 'chosen', 'fixed'), rating_on, files_on, watching_on, pages (the thread's
 * pages, default 1), replying (the form answers a comment), is_watcher,
 * submitter_notified (the record's submitter, told of new comments anyway),
 * manage_watchers (may manage the thread's watchers), email (drawn in a
 * comment e-mail). The search index stores what a visitor who is not
 * signed in reads: no form, no sign-in note, no staff, no watch box.
 *
 * - is_watching / not_watching: watching on for the page, signed in and not
 *   the record's submitter (who is told anyway and is not asked, as on the
 *   classic block); one of them by is_watcher.
 * - can_manage_watchers: watching on, signed in, manage_watchers.
 * - is_email: the widget draws one comment in a comment e-mail
 *   (_pg_comments_widget_email()). The e-mail has no visitor and no form:
 *   every visitor, form, open-or-closed, featured and paging flag is false;
 *   has_comments, rating_on, files_on and watching_on follow the facts.
 *
 * @param mixed $facts
 * @return array the 23 thread flags
 */
function pg_comments_widget_thread_flags($facts)
{
    $facts = is_array($facts) ? $facts : array();
    $on = function ($key) use ($facts) {
        return !empty($facts[$key]);
    };

    $search = $on('search_index');
    $email = $on('email');
    $allow_new = $on('allow_new');
    $signed_in = $on('signed_in') && !$search && !$email;
    $must_sign_in = $allow_new && !$on('signed_in') && $on('require_login') && !$search && !$email;
    $can_comment = $allow_new && !$must_sign_in && !$search && !$email;
    $count = (isset($facts['count']) && is_scalar($facts['count']) && !is_bool($facts['count'])) ? (int) $facts['count'] : 0;
    $mode = (isset($facts['featured_mode']) && is_string($facts['featured_mode'])) ? $facts['featured_mode'] : 'first';
    $name_mode = (isset($facts['name_mode']) && is_string($facts['name_mode'])) ? $facts['name_mode'] : '';
    $pages = (isset($facts['pages']) && is_scalar($facts['pages']) && !is_bool($facts['pages'])) ? (int) $facts['pages'] : 1;
    $can_watch = $on('watching_on') && $signed_in && !$on('submitter_notified');

    $comments_thread_flags = array(
        'comments_open' => $allow_new && !$email,
        'comments_closed' => !$allow_new && !$email,
        'can_comment' => $can_comment,
        'must_sign_in' => $must_sign_in,
        'is_signed_in' => $signed_in,
        'is_signed_out' => !$signed_in && !$email,
        'has_comments' => ($count > 0),
        'no_comments' => ($count === 0),
        'featured_view' => $on('featured_view') && !$email,
        'has_featured' => ($mode !== 'none') && $on('has_featured') && !$email,
        'is_staff' => $on('staff') && !$search && !$email,
        'name_is_typed' => $can_comment && ($name_mode === 'typed'),
        'name_is_chosen' => $can_comment && ($name_mode === 'chosen'),
        'name_is_fixed' => $can_comment && ($name_mode === 'fixed'),
        'rating_on' => $on('rating_on'),
        'files_on' => $on('files_on'),
        'watching_on' => $on('watching_on'),
        'has_more_pages' => ($pages > 1) && !$email,
        'is_replying' => $can_comment && $on('replying'),
        'is_watching' => $can_watch && $on('is_watcher'),
        'not_watching' => $can_watch && !$on('is_watcher'),
        'can_manage_watchers' => $on('watching_on') && $signed_in && $on('manage_watchers'),
        'is_email' => $email,
    );

    return $comments_thread_flags;
}

/**
 * A row column as a string, '' when it is missing or not a value.
 *
 * @param mixed  $row
 * @param string $key
 * @return string
 */
function _pg_comments_row_text($row, $key)
{
    return (is_array($row) && isset($row[$key]) && is_scalar($row[$key]) && !is_bool($row[$key])) ? (string) $row[$key] : '';
}

/**
 * One comment's visibility flags. Pure.
 *
 * $state: is_staff, sees_pending (staff or the record's form editor),
 * own_ids (this browser session's comments), rating_on, badge_label (the
 * site's). A column that is missing or not a value reads as off.
 *
 * - comment_is_own_pending: waiting for approval, not scheduled, and posted
 *   in this session.
 * - comment_is_scheduled: shown only to who sees pending comments.
 * - comment_is_root / comment_is_reply: drawn at depth 0, or deeper ($place
 *   'depth', a whole number from 0; anything else reads 0). In the flat
 *   layout every comment is drawn at depth 0.
 * - comment_has_quote: it quotes the comment it answers ($place 'quoted').
 * - comment_has_replies: replies answer it ($place 'replies' above 0).
 *
 * The internal per-row flag the reply control waits for
 * (pg_comment_reply_ok, pg_comments_widget_draw()) is not one of these: the
 * designer does not offer it.
 *
 * @param mixed $row      the comment row
 * @param mixed $featured pg_comments_widget_order()'s 'featured'
 * @param mixed $state
 * @param mixed $place    'depth', 'quoted', 'replies' (the order's entry)
 * @return array the 14 row flags
 */
function pg_comments_widget_row_flags($row, $featured, $state, $place = null)
{
    $row = is_array($row) ? $row : array();
    $state = is_array($state) ? $state : array();
    $place = is_array($place) ? $place : array();
    $depth = (isset($place['depth']) && is_scalar($place['depth']) && !is_bool($place['depth'])) ? max(0, (int) $place['depth']) : 0;
    $replies = (isset($place['replies']) && is_scalar($place['replies']) && !is_bool($place['replies'])) ? max(0, (int) $place['replies']) : 0;

    $pending = (_pg_comments_row_text($row, 'published') === '0');
    $when = _pg_comments_row_text($row, 'publish_date_and_time');
    $dated = ($when !== '') && ($when !== '0000-00-00 00:00:00');
    $id = _pg_comments_row_int($row, 'id');
    $own = false;

    if ($id > 0 && isset($state['own_ids']) && is_array($state['own_ids'])) {
        foreach ($state['own_ids'] as $own_id) {
            if (is_scalar($own_id) && !is_bool($own_id) && ((int) $own_id === $id)) {
                $own = true;
                break;
            }
        }
    }

    $rating = _pg_comments_row_text($row, 'rating');
    $rating = ($rating !== '' && is_numeric($rating)) ? (int) $rating : 0;
    $badge_label = (isset($state['badge_label']) && is_scalar($state['badge_label'])) ? (string) $state['badge_label'] : '';

    $comments_row_flags = array(
        'comment_is_featured' => (bool) $featured,
        'comment_is_pending' => $pending,
        'comment_is_own_pending' => $pending && $own && !$dated,
        'comment_is_scheduled' => !empty($state['sees_pending']) && $pending && $dated,
        'comment_has_rating' => !empty($state['rating_on']) && ($rating >= 1) && ($rating <= 5),
        'comment_has_file' => (_pg_comments_row_text($row, 'file_name') !== ''),
        'comment_has_badge' => (_pg_comments_row_text($row, 'user_badge') === '1')
            && ((_pg_comments_row_text($row, 'user_badge_label') !== '') || ($badge_label !== '')),
        'comment_is_verified' => ((int) _pg_comments_row_text($row, 'verified_order_id') > 0),
        'comment_has_seller_reply' => (trim(_pg_comments_row_text($row, 'seller_reply')) !== ''),
        'comment_can_edit' => !empty($state['is_staff']),
        'comment_is_root' => ($depth === 0),
        'comment_is_reply' => ($depth > 0),
        'comment_has_quote' => !empty($place['quoted']),
        'comment_has_replies' => ($replies > 0),
    );

    return $comments_row_flags;
}

/**
 * The status line of one comment (^^__comment_status^^), as plain text.
 * Pure. The visitor's own comment awaiting approval says so; a pending
 * comment shown to who sees pending comments says "Not Published", with the
 * time it is scheduled for and whether a newer comment cancels it. Anyone
 * else reads ''. $label_lower and $when go into lang() as they are; the
 * token escape does h().
 *
 * @param mixed  $row_flags    pg_comments_widget_row_flags()
 * @param bool   $sees_pending staff or the record's form editor
 * @param string $label_lower  the comment label in lower case
 * @param string $when         when a scheduled comment is published (relative)
 * @param bool   $cancel       a newer comment cancels the schedule
 * @return string
 */
function pg_comments_widget_status_text($row_flags, $sees_pending, $label_lower, $when, $cancel)
{
    $flags = is_array($row_flags) ? $row_flags : array();

    if (!empty($flags['comment_is_own_pending'])) {
        return lang(array('string' => 'Your {var:1} is awaiting approval', 'vars' => $label_lower));
    }

    if ($sees_pending && !empty($flags['comment_is_pending'])) {
        $text = lang('Not Published');

        if (!empty($flags['comment_is_scheduled'])) {
            $text .= ' (' . lang(array('string' => 'scheduled for {var:1}', 'vars' => $when));

            if ($cancel) {
                $text .= ', ' . lang(array('string' => 'unless a new {var:1} is added first', 'vars' => $label_lower));
            }

            $text .= ')';
        }

        return $text;
    }

    return '';
}

// ============================================================================
// Replies, quotes and the links of one comment
// ============================================================================

/**
 * The words of a comment as a reply quotes them (^^__comment_quote^^): plain
 * text, its white space folded into single spaces. A text longer than
 * $limit characters (at least 20, default 160) is cut there, back to the
 * last space when one lies within its last 30 characters, without trailing
 * spaces or marks (. , ; :), and ends with an ellipsis. Raw: the token escape
 * does h() and writes carets as entities. Not text (an array, a bool, null)
 * or text that is not valid UTF-8 reads as ''. Pure.
 *
 * @param mixed $text  the stored message
 * @param mixed $limit
 * @return string
 */
function pg_comments_quote_excerpt($text, $limit = 160)
{
    if (!is_scalar($text) || is_bool($text)) {
        return '';
    }

    $limit = is_int($limit) ? max(20, $limit) : 160;
    $clean = preg_replace('/\s+/u', ' ', (string) $text);

    if (!is_string($clean)) {
        return '';
    }

    $clean = trim($clean);

    if (mb_strlen($clean, 'UTF-8') <= $limit) {
        return $clean;
    }

    $cut = mb_substr($clean, 0, $limit, 'UTF-8');
    $space = mb_strrpos($cut, ' ', 0, 'UTF-8');

    if (($space !== false) && ($space >= $limit - 30)) {
        $cut = mb_substr($cut, 0, $space, 'UTF-8');
    }

    return rtrim($cut, ' .,;:') . '…';
}

/**
 * The tokens one comment gives about its place in the thread, raw (the
 * token escape does h()). Pure.
 *
 * - __comment_url: the comment's permalink, the current address without
 *   comment, comments_page, comments and reply_to, then comment=<id>, ending
 *   at #c-<id>: it opens the page that holds the comment, featured filter or
 *   not.
 * - __comment_reply_url: the current address with reply_to=<id>, ending at
 *   #software_add_comment (it works without the browser's script), when the
 *   visitor may answer the comment ($reply_ok), else ''.
 * - __comment_parent_id, __comment_parent_name (Anonymous when the comment
 *   answered has no name), __comment_parent_url (its anchor when it is on
 *   this page, else its permalink): '' for a comment that answers none among
 *   the rows read ($parent null).
 * - __comment_quote: the start of the answered comment's words
 *   (pg_comments_quote_excerpt()) when the entry quotes it, else ''.
 * - __comment_depth, __comment_reply_count: as text.
 *
 * @param mixed $entry          pg_comments_widget_order()'s entry
 * @param mixed $parent         the answered comment's row, or null
 * @param bool  $parent_on_page whether that comment is drawn on this page
 * @param mixed $path           the current address without its query
 * @param mixed $params         the query parameters (_pg_sw_query_params())
 * @param bool  $reply_ok       whether the visitor may answer the comment
 * @return array
 */
function pg_comments_widget_link_values($entry, $parent, $parent_on_page, $path, $params, $reply_ok)
{
    $entry = is_array($entry) ? $entry : array();
    $id = _pg_comments_row_int($entry, 'id');
    $parent_id = is_array($parent) ? _pg_comments_row_int($parent, 'id') : 0;
    $parent_name = ($parent_id > 0) ? _pg_comments_row_text($parent, 'name') : '';
    $permalink = function ($comment_id) use ($path, $params) {
        return pg_comments_widget_page_url($path, $params, array('comment', 'comments_page', 'comments', 'reply_to'), array('comment' => $comment_id), 'c-' . $comment_id);
    };

    $comment_links = array(
        '__comment_url' => $permalink($id),
        '__comment_reply_url' => $reply_ok ? pg_comments_widget_page_url($path, $params, array('reply_to'), array('reply_to' => $id), 'software_add_comment') : '',
        '__comment_parent_id' => ($parent_id > 0) ? (string) $parent_id : '',
        '__comment_parent_name' => ($parent_id > 0) ? (($parent_name !== '') ? $parent_name : lang('Anonymous')) : '',
        '__comment_parent_url' => ($parent_id > 0) ? ($parent_on_page ? '#c-' . $parent_id : $permalink($parent_id)) : '',
        '__comment_quote' => (!empty($entry['quoted']) && ($parent_id > 0)) ? pg_comments_quote_excerpt(_pg_comments_row_text($parent, 'message')) : '',
        '__comment_depth' => (string) _pg_comments_row_int($entry, 'depth'),
        '__comment_reply_count' => (string) _pg_comments_row_int($entry, 'replies'),
    );

    return $comment_links;
}

// ============================================================================
// Pieces of markup the renderer builds
// ============================================================================

/**
 * A 16px star, filled or not: the drawings the classic block uses, hidden
 * from screen readers.
 *
 * @param bool $filled
 * @return string
 */
function _pg_comments_star_svg($filled)
{
    if ($filled) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star-fill" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/></svg>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z"/></svg>';
}

/**
 * A rating 1-5 as a whole number, 0 for anything else.
 *
 * @param mixed $rating
 * @return int
 */
function _pg_comments_rating_value($rating)
{
    if (is_int($rating)) {
        $n = $rating;
    } elseif (is_string($rating) && ($rating !== '') && ctype_digit($rating)) {
        $n = (int) $rating;
    } else {
        return 0;
    }

    return (($n >= 1) && ($n <= 5)) ? $n : 0;
}

/**
 * What a comment's stars say once to a screen reader
 * (^^__comment_rating_label^^, the stars part's aria-label): "Rated N out
 * of 5". '' for a rating outside 1-5.
 *
 * @param mixed $rating
 * @return string
 */
function pg_comments_rating_label($rating)
{
    $n = _pg_comments_rating_value($rating);

    return ($n === 0) ? '' : lang(array('string' => 'Rated {var:1} out of 5', 'vars' => $n));
}

/**
 * Give one comment's markup its anchor: the first element (after leading
 * whitespace and comments) gets id="c-<id>", in place of any id it had.
 * Run after pg_sw_uniquify_row_ids(), so the anchor e-mails and share links
 * point at is never suffixed. With $depth (a whole number from 0, or a
 * string of digits), data-pg-comment-depth="<depth>" follows the id, in
 * place of one the design wrote, so a design may indent replies with CSS of
 * its own. Markup without an element, or an id that is not a whole number
 * above 0, comes back as it was.
 *
 * @param mixed $html
 * @param mixed $comment_id
 * @param mixed $depth
 * @return mixed
 */
function pg_comments_row_root_id($html, $comment_id, $depth = null)
{
    $id = (is_int($comment_id) || (is_string($comment_id) && ($comment_id !== '') && ctype_digit($comment_id))) ? (int) $comment_id : 0;

    if (($id <= 0) || !is_string($html)) {
        return $html;
    }

    if (!preg_match('/^(\s*(?:<!--.*?-->\s*)*)<([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s', $html, $m)) {
        return $html;
    }

    $level = null;

    if (is_int($depth) && ($depth >= 0)) {
        $level = $depth;
    } elseif (is_string($depth) && ($depth !== '') && ctype_digit($depth)) {
        $level = (int) $depth;
    }

    $replaced = ($level === null) ? array('id') : array('id', 'data-pg-comment-depth');

    // Walk the attributes one by one, so an "id=" inside another value is
    // left alone.
    $attrs = preg_replace_callback('/(\s+)([^\s=\/>"\']+)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', function ($a) use ($replaced) {
        return in_array(strtolower($a[2]), $replaced, true) ? '' : $a[0];
    }, $m[3]);

    $own = ' id="c-' . $id . '"' . (($level === null) ? '' : ' data-pg-comment-depth="' . $level . '"');

    return $m[1] . '<' . $m[2] . $own . $attrs . '>' . substr($html, strlen($m[0]));
}

/**
 * Point the links of one comment's markup at comment anchors again:
 * pg_sw_uniquify_row_ids() gave every href="#..." the row's suffix, but a
 * comment's anchor (id="c-<id>", pg_comments_row_root_id()) never carries
 * one, so href="#c-<number><this row's suffix>" loses the suffix. Links to
 * the design's own ids keep theirs. Markup that is not a string comes back
 * as it was.
 *
 * @param mixed $html
 * @param mixed $widget_id
 * @param mixed $row_id
 * @return mixed
 */
function pg_comments_row_anchor_links($html, $widget_id, $row_id)
{
    if (!is_string($html) || ($html === '')) {
        return $html;
    }

    $suffix = '_pgw' . (int) $widget_id . 'r' . (int) $row_id;

    return preg_replace('/href="#(c-[0-9]+)' . preg_quote($suffix, '/') . '"/', 'href="#$1"', $html);
}

/**
 * Escape a comments token map once: pg_sw_escape_token_values() (h() for
 * plain text, pg_defuse_php_tags() for the raw keys), then every caret as
 * &#94;, so a visitor's text cannot form a ^^token^^ that a later fill or the
 * sweep would act on. A value is never escaped twice.
 *
 * @param mixed $values token => raw value
 * @return array
 */
function pg_comments_widget_escape($values)
{
    if (!is_array($values)) {
        return array();
    }

    $out = array();

    foreach (pg_sw_escape_token_values($values) as $key => $value) {
        $out[$key] = str_replace('^', '&#94;', (string) $value);
    }

    return $out;
}

// ============================================================================
// Which thread a page shows
// ============================================================================

/**
 * Whether a page keeps its classic comments block, so a comments widget on
 * it neither draws nor claims the thread. Pure.
 *
 * A legacy form item view page shows the record its own settings pick (?r=
 * or the search index's form id), and add_comment.php takes any record
 * there; a catalog, catalog detail or calendar event view page shows the
 * thread of the product, product group or event in the address. The widget
 * does not draw per-item threads, so those pages keep the classic block,
 * which picks the item. A catalog, catalog detail or calendar event view
 * page with a top-level form_item_view widget is the exception: the classic
 * block, like the widget, then reads that record's thread.
 *
 * @param mixed $page_type         the page row's page_type
 * @param bool  $has_record_widget pg_sw_record_comment_context() found one
 * @return bool
 */
function pg_comments_widget_classic_thread($page_type, $has_record_widget)
{
    $page_type = is_string($page_type) ? $page_type : '';

    if ($page_type === 'form item view') {
        return true;
    }

    return !$has_record_widget && (pg_comment_classic_item_types($page_type) !== array());
}

/**
 * Whether a page carries a form_item_view widget below its top level: inside
 * a shared component or another widget. Pure; pg_comments_page_nested_record()
 * reads the components.
 *
 * Walks breadth-first from the page's own placements ($top, depth 0) through
 * $children (component id => the component ids its tree places), each
 * component once, at most 8 levels down. A record widget on the page itself
 * is the one pg_sw_record_comment_context() reads, so only one reached at
 * depth 1 or more counts: the widget cannot tell which record such a page
 * shows, and prints nothing.
 *
 * @param mixed $top      component ids placed on the page
 * @param mixed $children component id => list of component ids
 * @param mixed $types    component id => its regionType ('' for none)
 * @return bool
 */
function pg_comments_nested_record_decision($top, $children, $types)
{
    $children = is_array($children) ? $children : array();
    $types = is_array($types) ? $types : array();
    $queue = array();
    $seen = array();

    foreach ((is_array($top) ? $top : array()) as $id) {
        $id = (is_scalar($id) && !is_bool($id)) ? (int) $id : 0;

        if (($id > 0) && !isset($seen[$id])) {
            $seen[$id] = true;
            $queue[] = array($id, 0);
        }
    }

    for ($i = 0; $i < count($queue); $i++) {
        list($id, $depth) = $queue[$i];

        if (($depth >= 1) && isset($types[$id]) && ($types[$id] === 'form_item_view')) {
            return true;
        }

        if ($depth >= 8 || !isset($children[$id]) || !is_array($children[$id])) {
            continue;
        }

        foreach ($children[$id] as $child) {
            $child = (is_scalar($child) && !is_bool($child)) ? (int) $child : 0;

            if (($child > 0) && !isset($seen[$child])) {
                $seen[$child] = true;
                $queue[] = array($child, $depth + 1);
            }
        }
    }

    return false;
}

/**
 * What one tree places: the shared components and widgets (id => number of
 * placements) and the fixed comments blocks (regions of type comments_block).
 * A placement is not walked into: what a component places is its own tree's
 * (pg_comments_component_census()). Pure.
 *
 * @param mixed $tree
 * @return array 'refs' => id => placements, 'blocks' => int
 */
function pg_comments_tree_census($tree)
{
    $census = array('refs' => array(), 'blocks' => 0);
    _pg_comments_census_walk($tree, $census, 0);

    return $census;
}

/**
 * pg_comments_tree_census()'s walk.
 *
 * @param mixed $node
 * @param array $census
 * @param int   $depth
 */
function _pg_comments_census_walk($node, &$census, $depth)
{
    if (!is_array($node) || ($depth > 200)) {
        return;
    }

    $type = (isset($node['type']) && is_string($node['type'])) ? $node['type'] : '';
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();

    if ($type === 'shared_ref') {
        $sid = (isset($props['sharedId']) && is_scalar($props['sharedId']) && !is_bool($props['sharedId'])) ? (int) $props['sharedId'] : 0;

        if ($sid > 0) {
            $census['refs'][$sid] = (isset($census['refs'][$sid]) ? $census['refs'][$sid] : 0) + 1;
        }

        return;
    }

    if (($type === 'region') && isset($props['regionType']) && ($props['regionType'] === 'comments_block')) {
        $census['blocks']++;
    }

    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            _pg_comments_census_walk($child, $census, $depth + 1);
        }
    }
}

/**
 * The shared components and widgets a page reaches from its own placements
 * ($ids), each read once, breadth first: one query per level, at most 8
 * levels and 500 components, as pg_comments_page_nested_record() always read
 * them.
 *
 * @param mixed $ids the component ids the page places
 * @return array id => array('type' => its kind (pg_sw_widget_kind(): '' for
 *               none, 'comments_recent' for a list of recent comments),
 *               'census' => pg_comments_tree_census() of its tree)
 */
function pg_comments_component_census($ids)
{
    $out = array();
    $asked = array();
    $level = array();

    foreach ((is_array($ids) ? $ids : array()) as $id) {
        $id = (is_scalar($id) && !is_bool($id)) ? (int) $id : 0;

        if ($id > 0) {
            $level[$id] = true;
        }
    }

    $level = array_keys($level);
    $read = 0;

    for ($depth = 0; ($depth < 8) && $level && ($read < 500); $depth++) {
        $now = array();

        foreach ($level as $id) {
            if (!isset($asked[$id])) {
                $asked[$id] = true;
                $now[] = (int) $id;
            }
        }

        if (!$now) {
            break;
        }

        $rows = db_items("SELECT id, tree_json, system_region_config FROM shared_components WHERE id IN (" . implode(',', $now) . ")");
        $next = array();

        foreach ((is_array($rows) ? $rows : array()) as $row) {
            $id = (int) $row['id'];
            $cfg = json_decode((string) $row['system_region_config'], true);
            $census = pg_comments_tree_census(json_decode((string) $row['tree_json'], true));
            // The kind, so a list of recent comments ('comments_recent') is
            // not counted as a second thread.
            $out[$id] = array(
                'type' => pg_sw_widget_kind($cfg),
                'census' => $census,
            );

            foreach (array_keys($census['refs']) as $child) {
                if (!isset($asked[$child])) {
                    $next[$child] = true;
                }
            }

            if (++$read >= 500) {
                break;
            }
        }

        $level = array_keys($next);
    }

    return $out;
}

/**
 * What a page shows of comments, from its own tree's census ($top,
 * pg_comments_tree_census()) and the components it reaches ($components,
 * pg_comments_component_census()). Pure.
 *
 * - widgets: the page's own placements of comments widgets, each counted,
 *   and the comments widgets placed in each component the page reaches
 *   (each component read once, at most 8 levels down). Two placements of one
 *   component that holds a comments widget count that widget once: the
 *   number is an approximation that errs on the low side.
 * - blocks: the fixed comments blocks of the page and of the components it
 *   reaches.
 * - nested_record: a form_item_view widget below the top level
 *   (pg_comments_nested_record_decision()).
 * - top_record: a form_item_view widget placed on the page itself, the one
 *   whose record the thread is then.
 *
 * @param mixed $top
 * @param mixed $components
 * @return array 'widgets' (int), 'blocks' (int), 'nested_record' (bool),
 *               'top_record' (bool)
 */
function pg_comments_page_census_facts($top, $components)
{
    $top = is_array($top) ? $top : array();
    $components = is_array($components) ? $components : array();
    $refs_of = function ($census) {
        $refs = (is_array($census) && isset($census['refs']) && is_array($census['refs'])) ? $census['refs'] : array();
        $out = array();

        foreach ($refs as $id => $count) {
            if ((int) $id > 0) {
                $out[(int) $id] = (is_scalar($count) && !is_bool($count)) ? max(0, (int) $count) : 0;
            }
        }

        return $out;
    };
    $blocks_of = function ($census) {
        return (is_array($census) && isset($census['blocks']) && is_scalar($census['blocks'])) ? max(0, (int) $census['blocks']) : 0;
    };
    $types = array();
    $children = array();

    foreach ($components as $id => $component) {
        $id = (int) $id;
        $types[$id] = (is_array($component) && isset($component['type']) && is_string($component['type'])) ? $component['type'] : '';
        $children[$id] = $refs_of(is_array($component) && isset($component['census']) ? $component['census'] : null);
    }

    $type = function ($id) use ($types) {
        return isset($types[$id]) ? $types[$id] : '';
    };
    $top_refs = $refs_of($top);
    $widgets = 0;
    $blocks = $blocks_of($top);
    $top_record = false;
    $queue = array();
    $seen = array();

    foreach ($top_refs as $id => $count) {
        if ($type($id) === 'comments') {
            $widgets += $count;
        }

        if ($type($id) === 'form_item_view') {
            $top_record = true;
        }

        $seen[$id] = true;
        $queue[] = array($id, 0);
    }

    for ($i = 0; $i < count($queue); $i++) {
        list($id, $depth) = $queue[$i];

        if (isset($components[$id]['census'])) {
            $blocks += $blocks_of($components[$id]['census']);
        }

        if (($depth >= 8) || !isset($children[$id])) {
            continue;
        }

        foreach ($children[$id] as $child => $count) {
            if ($type($child) === 'comments') {
                $widgets += $count;
            }

            if (!isset($seen[$child])) {
                $seen[$child] = true;
                $queue[] = array($child, $depth + 1);
            }
        }
    }

    $child_ids = array();

    foreach ($children as $id => $list) {
        $child_ids[$id] = array_keys($list);
    }

    return array(
        'widgets' => $widgets,
        'blocks' => $blocks,
        'nested_record' => pg_comments_nested_record_decision(array_keys($top_refs), $child_ids, $types),
        'top_record' => $top_record,
    );
}

/**
 * The save warnings a page's comments call for, in order. Pure.
 *
 * None without a comments widget. Then: 'block' (the fixed comments block
 * too: the widget claims the page and the block prints nothing), 'widgets'
 * (more than one: only the first draws), 'nested_record' (a form_item_view
 * below the top level and none on the page: the widget fails closed),
 * 'off' (comments are off for the page).
 *
 * A comment e-mail page ('email_page': another page names it as its
 * submitter or watcher e-mail page) draws no thread of its own: its widget
 * draws the comment an e-mail names, whatever its own comment settings,
 * fixed block or record widget. Only 'widgets' applies to it.
 *
 * @param mixed $facts pg_comments_page_census_facts() plus 'comments_on'
 *                     and 'email_page'
 * @return string[]
 */
function pg_comments_save_warning_codes($facts)
{
    if (!is_array($facts)) {
        return array();
    }

    $widgets = (isset($facts['widgets']) && is_scalar($facts['widgets'])) ? (int) $facts['widgets'] : 0;

    if ($widgets <= 0) {
        return array();
    }

    if (!empty($facts['email_page'])) {
        return ($widgets > 1) ? array('widgets') : array();
    }

    $codes = array();

    if (!empty($facts['blocks'])) {
        $codes[] = 'block';
    }

    if ($widgets > 1) {
        $codes[] = 'widgets';
    }

    if (!empty($facts['nested_record']) && empty($facts['top_record'])) {
        $codes[] = 'nested_record';
    }

    if (empty($facts['comments_on'])) {
        $codes[] = 'off';
    }

    return $codes;
}

/**
 * The words of a save warning (pg_comments_save_warning_codes()), '' for an
 * unknown code. Pure but for the translation.
 *
 * @param string $code
 * @param mixed  $page_name
 * @param mixed  $widgets   the number of comments widgets on the page
 * @return string
 */
function pg_comments_save_warning_text($code, $page_name, $widgets)
{
    $name = is_scalar($page_name) ? (string) $page_name : '';
    $count = is_scalar($widgets) ? (int) $widgets : 0;

    switch ($code) {
        case 'block':
            return lang(array('string' => 'The page "{var:1}" shows a Comments widget and the fixed comments block: only the widget draws the comments.', 'vars' => array($name)));
        case 'widgets':
            return lang(array('string' => 'The page "{var:1}" shows {var:2} Comments widgets: only the first draws the comments.', 'vars' => array($name, $count)));
        case 'nested_record':
            return lang(array('string' => 'The page "{var:1}" places a Form Item View widget inside another component: its Comments widget draws nothing there. Place the Form Item View on the page itself.', 'vars' => array($name)));
        case 'off':
            return lang(array('string' => 'Comments are off for the page "{var:1}": its Comments widget draws nothing. Turn them on in the widget\'s comment settings.', 'vars' => array($name)));
    }

    return '';
}

/**
 * The warnings a page's save gives about comments that are not drawn as the
 * operator may expect: the page's tree (as it is saved), whether the page
 * takes comments, its name and, for a saved page, its id. Reads the
 * components the page reaches only when it places any, and asks whether a
 * saved page is a comment e-mail page (pg_comments_is_email_page()) only
 * once a comments widget is found.
 *
 * @param mixed  $tree
 * @param mixed  $comments_on
 * @param string $page_name
 * @param mixed  $page_id     0 for a page not saved yet
 * @return string[]
 */
function pg_comments_save_warnings($tree, $comments_on, $page_name, $page_id = 0)
{
    $census = pg_comments_tree_census($tree);

    if (!$census['refs']) {
        return array();
    }

    $facts = pg_comments_page_census_facts($census, pg_comments_component_census(array_keys($census['refs'])))
        + array('comments_on' => (bool) $comments_on);
    $facts['email_page'] = ($facts['widgets'] > 0) && pg_comments_is_email_page($page_id);
    $out = array();

    foreach (pg_comments_save_warning_codes($facts) as $code) {
        $out[] = pg_comments_save_warning_text($code, $page_name, $facts['widgets']);
    }

    return $out;
}

/**
 * How the visitor names their comment, as add_comment.php decides it. Pure.
 *
 * - 'typed': signed out, staff (a role below 3), a user with access to the
 *   page's folder, or the record's form editor.
 * - 'chosen': any other member, on a page that lets them pick a name.
 * - 'fixed': any other member: the name pg_comment_member_name() gives.
 *
 * @param mixed $signed_in
 * @param mixed $role          USER_ROLE, '' when there is none
 * @param mixed $folder_access a role-3 user with access to the page's folder
 * @param mixed $form_editor   the record's form editor
 * @param mixed $select_on     comments_allow_user_to_select_name
 * @return string
 */
function pg_comments_widget_name_mode($signed_in, $role, $folder_access, $form_editor, $select_on)
{
    $role = (is_scalar($role) && !is_bool($role)) ? (string) $role : '';

    if (!$signed_in || (($role !== '') && ((int) $role < 3)) || $folder_access || $form_editor) {
        return 'typed';
    }

    return $select_on ? 'chosen' : 'fixed';
}

// ============================================================================
// The add-comment form
// ============================================================================

/**
 * Whether a node of the comment form is bound to a feature that is off, so
 * the site prints nothing for it. The editor's twin is _sdCommentsBindingOff()
 * in assets/js/style_designer.js.
 *
 * $state: files_on, watcher_box (pg_comments_watcher_box()), name_mode,
 * rating_on, captcha_on, can_publish.
 *
 * @param mixed $node
 * @param mixed $state
 * @return bool
 */
function _pg_comments_binding_disabled($node, $state)
{
    if (!is_array($node) || !isset($node['props']['_bindings']) || !is_array($node['props']['_bindings'])) {
        return false;
    }

    $state = is_array($state) ? $state : array();
    $bindings = $node['props']['_bindings'];
    $value = (isset($bindings['value']) && is_string($bindings['value'])) ? $bindings['value'] : '';
    $section = (isset($bindings['section']) && is_string($bindings['section'])) ? $bindings['section'] : '';
    $name_mode = (isset($state['name_mode']) && is_string($state['name_mode'])) ? $state['name_mode'] : '';

    if ($value === 'comment_file') {
        return empty($state['files_on']);
    }
    if ($value === 'comment_watcher') {
        return empty($state['watcher_box']);
    }
    if ($value === 'comment_name') {
        return ($name_mode !== 'typed');
    }
    if ($value === 'comment_name_type') {
        return ($name_mode !== 'chosen');
    }
    if ($section === 'comment_rating_input') {
        return empty($state['rating_on']);
    }
    if ($section === 'comment_captcha') {
        return empty($state['captcha_on']);
    }
    if ($section === 'comment_publish') {
        return empty($state['can_publish']);
    }

    return false;
}

/**
 * The fields the server puts in the comment form, never bindable: the CSRF
 * token field, then send_to, page_id, item_id, item_type and parent_id. The
 * thread comes from the server, never from the visitor's earlier post. Text
 * goes through pg_sw_filter_value_html(); ids are whole numbers (0 for
 * anything that is not a value).
 *
 * @param mixed $token_field get_token_field()
 * @param mixed $send_to     the address to come back to
 * @param mixed $page_id
 * @param mixed $item_type
 * @param mixed $item_id
 * @param mixed $parent_id
 * @return string
 */
function pg_comments_form_hidden_html($token_field, $send_to, $page_id, $item_type, $item_id, $parent_id)
{
    $int = function ($value) {
        return (is_scalar($value) && !is_bool($value)) ? max(0, (int) $value) : 0;
    };
    $text = function ($value) {
        return pg_sw_filter_value_html(is_scalar($value) ? (string) $value : '');
    };

    return (is_scalar($token_field) ? (string) $token_field : '')
        . '<input type="hidden" name="send_to" value="' . $text($send_to) . '">'
        . '<input type="hidden" name="page_id" value="' . $int($page_id) . '">'
        . '<input type="hidden" name="item_id" value="' . $int($item_id) . '">'
        . '<input type="hidden" name="item_type" value="' . $text($item_type) . '">'
        . '<input type="hidden" name="parent_id" value="' . $int($parent_id) . '">';
}

/**
 * The star rating control (the comment_rating_input section): Bootstrap
 * btn-check radios named rating, 1-5, ids comment_rating_1..5, one checked
 * ($checked, else 5 as the classic form). No inline style.
 *
 * @param mixed $checked the rating to check
 * @return string
 */
function pg_comments_rating_input_html($checked)
{
    $n = _pg_comments_rating_value($checked);
    $n = ($n === 0) ? 5 : $n;
    $html = '<div class="btn-group" role="group" aria-label="' . h(lang('Rating')) . '">';

    for ($i = 1; $i <= 5; $i++) {
        $html .= '<input type="radio" class="btn-check" name="rating" id="comment_rating_' . $i . '" value="' . $i . '" autocomplete="off"' . (($i === $n) ? ' checked' : '') . '>'
            . '<label class="btn btn-outline-warning btn-sm" for="comment_rating_' . $i . '">'
            . _pg_comments_star_svg(true) . ' <span aria-hidden="true">' . $i . '</span>'
            . '<span class="visually-hidden">' . h(lang(array('string' => '{var:1} out of 5', 'vars' => $i))) . '</span>'
            . '</label>';
    }

    return $html . '</div>';
}

/**
 * The options of the name select (comment_name_type), for a member who may
 * pick the name: $choices is pg_comment_name_choices(). Keyed by label as
 * the classic block keys them, so equal labels make one option (the later
 * value, in the first one's place). 'anonymous' is shown as
 * $anonymous_label. Values and labels go through pg_sw_filter_value_html().
 *
 * @param mixed $choices         value => stored name
 * @param mixed $selected        the chosen value
 * @param mixed $anonymous_label
 * @return string
 */
function pg_comments_name_options_html($choices, $selected, $anonymous_label)
{
    $options = array();

    foreach ((is_array($choices) ? $choices : array()) as $value => $choice) {
        $label = ((string) $value === 'anonymous') ? (string) $anonymous_label : (is_scalar($choice) ? (string) $choice : '');
        $options[$label] = (string) $value;
    }

    $selected = is_scalar($selected) ? (string) $selected : '';
    $html = '';

    foreach ($options as $label => $value) {
        $html .= '<option value="' . pg_sw_filter_value_html($value) . '"' . (($value === $selected) ? ' selected' : '') . '>'
            . pg_sw_filter_value_html((string) $label) . '</option>';
    }

    return $html;
}

/**
 * The publish options of a staff member's comment (the comment_publish
 * section): now, at a scheduled time or later, the time and whether a newer
 * comment cancels it, wired by data attributes (software.init_comment_forms()
 * in frontend.js). The schedule part carries the hidden attribute until
 * "At a Scheduled Time" is chosen. Its ids are its own: the classic form's
 * stay as they are. $scripts_html (the date picker) goes last.
 *
 * @param mixed  $publish      'now', 'schedule' or 'manual'
 * @param mixed  $when         the scheduled time, as the form takes it
 * @param bool   $cancel
 * @param string $label_lower  the comment label in lower case
 * @param string $scripts_html
 * @return string
 */
function pg_comments_publish_html($publish, $when, $cancel, $label_lower, $scripts_html)
{
    $publish = is_scalar($publish) ? (string) $publish : '';
    $options = array('now' => lang('Now'), 'schedule' => lang('At a Scheduled Time'), 'manual' => lang('Maybe Later'));

    $html = '<div class="pg-comment-publish" data-pg-comment-publish>'
        . '<label class="form-label me-2" for="comment_publish">' . h(lang('Publish')) . '</label>'
        . '<select class="form-select form-select-sm d-inline-block w-auto" id="comment_publish" name="publish" data-pg-comment-publish-select>';

    foreach ($options as $value => $label) {
        $html .= '<option value="' . $value . '"' . (($value === $publish) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $html .= '</select>'
        . '<span class="ms-2" data-pg-comment-publish-schedule' . (($publish === 'schedule') ? '' : ' hidden') . '>'
        . '<input type="text" class="form-control form-control-sm d-inline-block w-auto" id="comment_publish_date_and_time" name="publish_date_and_time" maxlength="19" size="21" value="' . pg_sw_filter_value_html(is_scalar($when) ? (string) $when : '') . '">'
        . '<span class="form-check form-check-inline ms-2">'
        . '<input type="checkbox" class="form-check-input" id="comment_publish_cancel" name="publish_cancel" value="1"' . ($cancel ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="comment_publish_cancel">' . pg_sw_filter_value_html(lang(array('string' => 'Cancel if a new {var:1} is added first.', 'vars' => $label_lower))) . '</label>'
        . '</span>'
        . '</span>'
        . (is_scalar($scripts_html) ? (string) $scripts_html : '')
        . '</div>';

    return $html;
}

/**
 * Whether the form offers "Notify me when a new comment is added": watching
 * is on for the page, the visitor is signed in, is not a watcher yet and is
 * not told of new comments another way (a moderator address, the form
 * editor, a conditional administrator, the record's submitter).
 *
 * @param mixed $watching_on
 * @param mixed $signed_in
 * @param mixed $is_watcher
 * @param mixed $notified
 * @return bool
 */
function pg_comments_watcher_box($watching_on, $signed_in, $is_watcher, $notified)
{
    return $watching_on && $signed_in && !$is_watcher && !$notified;
}

/**
 * Replace the managed attributes of a node with $add, keeping the others.
 *
 * @param array $node
 * @param array $managed lower-case name => true
 * @param array $add     name => value
 * @return void
 */
function _pg_comments_set_attrs(&$node, $managed, $add)
{
    $kept = array();

    foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $attr) {
        if (is_array($attr) && isset($attr['name']) && !isset($managed[strtolower((string) $attr['name'])])) {
            $kept[] = $attr;
        }
    }

    foreach ($add as $name => $value) {
        $kept[] = array('name' => $name, 'value' => (string) $value);
    }

    $node['props']['_attrs'] = $kept;
}

/**
 * The value bindings of the comment form: the handler's field name, the id
 * a control gets when it has none, and the tags it may be.
 *
 * @return array
 */
function _pg_comments_form_fields()
{
    return array(
        'comment_message' => array('name' => 'message', 'id' => 'comment_message', 'tags' => array('textarea', 'input')),
        'comment_name' => array('name' => 'name', 'id' => 'comment_name', 'tags' => array('input')),
        'comment_name_type' => array('name' => 'name_type', 'id' => 'comment_name_type', 'tags' => array('select')),
        'comment_file' => array('name' => 'file', 'id' => 'comment_file', 'tags' => array('input')),
        'comment_watcher' => array('name' => 'watcher', 'id' => 'comment_watcher', 'tags' => array('input')),
    );
}

/**
 * Wire one comment form control to the handler's field.
 *
 * @param array  $node
 * @param string $value its value binding
 * @param array  $field _pg_comments_form_fields() entry
 * @param array  $ctx   pg_comments_apply_form_bindings()'s context
 * @return void
 */
function _pg_comments_wire_control(&$node, $value, $field, $ctx)
{
    $tag = strtolower((string) $node['props']['tag']);
    $id = (isset($node['props']['id']) && is_scalar($node['props']['id'])) ? trim((string) $node['props']['id']) : '';

    if ($id === '') {
        $attrs = _pg_cf_node_attrs($node);
        $id = isset($attrs['id']) ? trim($attrs['id']) : '';
    }

    $node['props']['id'] = ($id !== '') ? $id : $field['id'];

    $add = array('name' => $field['name']);

    if ($value === 'comment_message') {
        $add['required'] = '';

        if ($tag === 'textarea') {
            $node['props']['text'] = '^^pg_comment_form_message^^';
            $node['children'] = array();
        } else {
            $add['type'] = 'text';
            $add['value'] = '^^pg_comment_form_message^^';
        }
    } elseif ($value === 'comment_name') {
        $add['type'] = 'text';
        $add['maxlength'] = '50';
        $add['value'] = '^^pg_comment_form_name^^';
    } elseif ($value === 'comment_name_type') {
        $node['children'] = array(array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '^^pg_comment_form_name_options^^'), 'children' => array()));
    } elseif ($value === 'comment_file') {
        $add['type'] = 'file';
    } elseif ($value === 'comment_watcher') {
        $add['type'] = 'checkbox';
        $add['value'] = '1';

        if (!empty($ctx['watcher_checked'])) {
            $add['checked'] = '';
        }
    }

    _pg_comments_set_attrs($node, array('id' => 1, 'name' => 1, 'required' => 1, 'type' => 1, 'maxlength' => 1, 'value' => 1, 'checked' => 1), $add);
}

/**
 * Whether a node, or any node below it, carries the action binding $action.
 *
 * @param mixed  $node
 * @param string $action
 * @return bool
 */
function _pg_comments_holds_action($node, $action)
{
    if (!is_array($node)) {
        return false;
    }

    if (isset($node['props']['_bindings']['action']) && ($node['props']['_bindings']['action'] === $action)) {
        return true;
    }

    foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
        if (_pg_comments_holds_action($child, $action)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a tree has a loop_area anywhere.
 *
 * @param mixed $node
 * @return bool
 */
function _pg_comments_tree_has_loop($node)
{
    if (!is_array($node)) {
        return false;
    }

    if (isset($node['type']) && ($node['type'] === 'loop_area')) {
        return true;
    }

    foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
        if (_pg_comments_tree_has_loop($child)) {
            return true;
        }
    }

    return false;
}

/**
 * The sections the add-comment form fills on the server. Outside the wired
 * form they would print fields nobody posts.
 *
 * @return array section => true
 */
function _pg_comments_form_sections()
{
    return array('comment_captcha' => true, 'comment_rating_input' => true, 'comment_publish' => true);
}

/**
 * The walk of pg_comments_apply_form_bindings() over one node's children.
 *
 * $found collects the form sections met inside the wired form that the
 * visitor gets: not below a node whose thread flag ($ctx['flags']) is false,
 * and not in the loop_area, which is drawn once per comment.
 *
 * @param array $node
 * @param array $ctx
 * @param bool  $in_form whether $node is inside the wired form
 * @param int   $wired   the forms wired so far
 * @param array $found   section => true
 * @param bool  $shown   whether the visitor gets $node
 * @return void
 */
function _pg_comments_form_walk(&$node, $ctx, $in_form, &$wired, &$found, $shown = true)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
        return;
    }

    $fields = _pg_comments_form_fields();
    $sections = _pg_comments_form_sections();
    $flags = (isset($ctx['flags']) && is_array($ctx['flags'])) ? $ctx['flags'] : null;
    $kept = array();

    foreach ($node['children'] as $child) {
        if (!is_array($child)) {
            $kept[] = $child;
            continue;
        }

        $bindings = (isset($child['props']['_bindings']) && is_array($child['props']['_bindings'])) ? $child['props']['_bindings'] : array();
        $type = isset($child['type']) ? (string) $child['type'] : '';
        $tag = isset($child['props']['tag']) ? strtolower((string) $child['props']['tag']) : '';
        $action = (isset($bindings['action']) && is_string($bindings['action'])) ? $bindings['action'] : '';
        $value = (isset($bindings['value']) && is_string($bindings['value'])) ? $bindings['value'] : '';
        $section = (isset($bindings['section']) && is_string($bindings['section'])) ? $bindings['section'] : '';
        $flag = (isset($bindings['eo_visible_if']) && is_string($bindings['eo_visible_if'])) ? $bindings['eo_visible_if'] : '';
        $child_shown = $shown && ($type !== 'loop_area') && (($flag === '') || ($flags === null) || !empty($flags[$flag]));

        // The form: one per widget, and none for a visitor who cannot comment.
        if (($type === 'semantic') && ($tag === 'form') && ($action === 'comment_form')) {
            if (empty($ctx['can_comment']) || ($wired > 0) || $in_form) {
                continue;
            }

            $wired++;
            $child['props']['id'] = 'software_add_comment';
            $add = array(
                'action' => isset($ctx['action_url']) ? (string) $ctx['action_url'] : '',
                'method' => 'post',
            );

            if (!empty($ctx['multipart'])) {
                $add['enctype'] = 'multipart/form-data';
            }

            $label_slot = (isset($ctx['label_slot']) && is_string($ctx['label_slot']) && preg_match('/^[a-z0-9_]+$/', $ctx['label_slot'])) ? $ctx['label_slot'] : 'pg_comment_form_label';
            $add['data-pg-comment-form'] = '';
            $add['data-pg-comment-label'] = '^^' . $label_slot . '^^';
            _pg_comments_set_attrs($child, array('action' => 1, 'method' => 1, 'enctype' => 1, 'id' => 1, 'novalidate' => 1, 'data-pg-comment-form' => 1, 'data-pg-comment-label' => 1), $add);

            if (!isset($child['children']) || !is_array($child['children'])) {
                $child['children'] = array();
            }

            $form_found = array();
            _pg_comments_form_walk($child, $ctx, true, $wired, $form_found, $child_shown);

            // What add_comment.php needs when the design left a section out
            // (or the visitor does not get it): a staff comment follows the
            // page's publish setting, as the classic form's default does;
            // a CAPTCHA goes in before the box that holds the send button.
            $hidden = isset($ctx['hidden_html']) ? (string) $ctx['hidden_html'] : '';
            $publish = (isset($ctx['publish_default']) && in_array($ctx['publish_default'], array('now', 'manual'), true)) ? $ctx['publish_default'] : '';

            if (($publish !== '') && empty($form_found['comment_publish'])) {
                $hidden .= '<input type="hidden" name="publish" value="' . $publish . '">';
            }

            if (!empty($ctx['state']['captcha_on']) && empty($form_found['comment_captcha'])) {
                $at = count($child['children']);

                foreach ($child['children'] as $i => $form_child) {
                    if (_pg_comments_holds_action($form_child, 'comment_submit')) {
                        $at = $i;
                        break;
                    }
                }

                array_splice($child['children'], $at, 0, array(array('type' => 'semantic',
                    'props' => array('tag' => 'div', 'cssClass' => 'mb-3', '_bindings' => array('section' => 'comment_captcha')),
                    'children' => array())));
            }

            array_unshift($child['children'], array('type' => 'content',
                'props' => array('contentType' => 'custom_html', 'html' => $hidden),
                'children' => array()));

            $kept[] = $child;
            continue;
        }

        // A section the form fills: inside the wired form, or left out.
        if (isset($sections[$section])) {
            if (!$in_form) {
                continue;
            }

            if ($child_shown) {
                $found[$section] = true;
            }

            $kept[] = $child;
            continue;
        }

        // A control bound to one of the handler's fields.
        if (isset($fields[$value])) {
            $is_control = ($type === 'semantic') && in_array($tag, array('input', 'select', 'textarea', 'button'), true);

            if (!$is_control) {
                continue;
            }

            if (!$in_form
                || _pg_comments_binding_disabled($child, isset($ctx['state']) ? $ctx['state'] : array())
                || !in_array($tag, $fields[$value]['tags'], true)
            ) {
                $child['props']['_pg_comment_drop'] = true;
            } else {
                _pg_comments_wire_control($child, $value, $fields[$value], $ctx);
            }

            $kept[] = $child;
            continue;
        }

        // The submit button. Anything else bound to it (a link, a span) would
        // send nothing, so it is left out.
        if ($action === 'comment_submit') {
            $is_button = (($type === 'semantic') && ($tag === 'button'))
                || (($type === 'component') && isset($child['props']['componentType']) && ($child['props']['componentType'] === 'btn'));

            if (!$is_button) {
                continue;
            }

            if (!$in_form) {
                $child['props']['_pg_comment_drop'] = true;
            } else {
                if ($type === 'component') {
                    $child['props']['btnElement'] = 'button';
                    $child['props']['btnType'] = 'submit';
                    _pg_comments_set_attrs($child, array('data-pg-comment-submit' => 1), array('data-pg-comment-submit' => ''));
                } else {
                    _pg_comments_set_attrs($child, array('type' => 1, 'data-pg-comment-submit' => 1), array('type' => 'submit', 'data-pg-comment-submit' => ''));
                }
            }

            $kept[] = $child;
            continue;
        }

        _pg_comments_form_walk($child, $ctx, $in_form, $wired, $found, $child_shown);
        $kept[] = $child;
    }

    $node['children'] = $kept;
}

/**
 * Wire the comment form of a widget tree. Returns the number of forms wired
 * (0 or 1).
 *
 * - A semantic form bound to comment_form posts to add_comment.php: its id is
 *   software_add_comment (the anchor the handler sends the visitor back to),
 *   its action, method and enctype (multipart only with attachments on) are
 *   the server's, and the hidden fields come first in it. A second one, and
 *   any for a visitor who cannot comment, is dropped.
 * - A control bound to comment_message, comment_name, comment_name_type,
 *   comment_file or comment_watcher gets the handler's name, a default id
 *   when it has none, and its value from a slot filled after the draw
 *   (^^pg_comment_form_*^^: internal, so they never start with __). A control
 *   outside the form, of the wrong tag, or switched off
 *   (_pg_comments_binding_disabled()) is dropped with its label and any
 *   wrapper it leaves empty.
 * - A button bound to comment_submit submits the form (locked on submit by
 *   frontend.js); outside the form it is dropped, and so is anything else
 *   bound to it, a link included, which would send nothing.
 * - The sections comment_captcha, comment_rating_input and comment_publish
 *   stay only inside the wired form. When the visitor does not get a
 *   comment_publish section there (left out, or below a false thread flag),
 *   a staff comment still posts publish = $ctx['publish_default'] ('now' or
 *   'manual'; '' for a visitor who may not publish) as a hidden field. When
 *   the CAPTCHA is on and no comment_captcha section is there, one is put in
 *   before the box holding the send button.
 *
 * $ctx: can_comment, action_url, hidden_html, multipart, label_slot,
 * watcher_checked, publish_default, flags (the thread flags; none: every
 * node counts as shown), state (as _pg_comments_binding_disabled() reads
 * it).
 *
 * @param array $tree
 * @param mixed $ctx
 * @return int
 */
function pg_comments_apply_form_bindings(&$tree, $ctx)
{
    if (!is_array($tree)) {
        return 0;
    }

    $ctx = is_array($ctx) ? $ctx : array();
    $wired = 0;
    $found = array();
    _pg_comments_form_walk($tree, $ctx, false, $wired, $found);
    _pg_member_drop_controls($tree, function ($tag, $attrs, $node) {
        return !empty($node['props']['_pg_comment_drop']);
    });

    return $wired;
}

/**
 * Mark every link bound to the sign-in address rel="nofollow". It carries the
 * page in send_to, so every page links to a different sign-in address, and a
 * crawler that follows them all spends the site's crawl budget on the
 * sign-in page. Content links, semantic links and buttons print props.rel.
 *
 * @param array $tree
 * @return void
 */
function pg_comments_stamp_login_links(&$tree)
{
    if (!is_array($tree)) {
        return;
    }

    if (isset($tree['props']['_bindings']['href']) && ($tree['props']['_bindings']['href'] === '__login_url')) {
        $tree['props']['rel'] = _pg_comments_rel_nofollow(isset($tree['props']['rel']) ? $tree['props']['rel'] : '');
    }

    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$child) {
            pg_comments_stamp_login_links($child);
        }
        unset($child);
    }
}

/**
 * A rel value with nofollow among its words, added once.
 *
 * @param mixed $rel
 * @return string
 */
function _pg_comments_rel_nofollow($rel)
{
    $tokens = preg_split('/\s+/', trim(is_string($rel) ? $rel : ''), -1, PREG_SPLIT_NO_EMPTY);

    if (!in_array('nofollow', array_map('strtolower', $tokens), true)) {
        $tokens[] = 'nofollow';
    }

    return implode(' ', $tokens);
}

// ============================================================================
// Replies, the watch box and the staff controls
// ============================================================================

/**
 * Leave out the forms a form would hold: a form inside another form is
 * broken markup the browser ends at the first </form>, so the comment form,
 * the watch form and the open-or-close form below any semantic form, and
 * the watcher list (comment_watchers, which holds forms of its own) below
 * one, are dropped with what they hold.
 *
 * @param array $tree
 * @param bool  $in_form whether $tree is inside a form
 * @return void
 */
function pg_comments_drop_nested_forms(&$tree, $in_form = false)
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) {
        return;
    }

    $forms = array('comment_form' => true, 'comment_watch_form' => true, 'comment_allow_new_form' => true);
    $kept = array();

    foreach ($tree['children'] as $child) {
        if (!is_array($child)) {
            $kept[] = $child;
            continue;
        }

        $bindings = (isset($child['props']['_bindings']) && is_array($child['props']['_bindings'])) ? $child['props']['_bindings'] : array();
        $is_form = isset($child['type']) && ($child['type'] === 'semantic') && isset($child['props']['tag']) && (strtolower((string) $child['props']['tag']) === 'form');
        $action = (isset($bindings['action']) && is_string($bindings['action'])) ? $bindings['action'] : '';
        $section = (isset($bindings['section']) && is_string($bindings['section'])) ? $bindings['section'] : '';

        if ($in_form && (($is_form && isset($forms[$action])) || ($section === 'comment_watchers'))) {
            continue;
        }

        pg_comments_drop_nested_forms($child, $in_form || $is_form);
        $kept[] = $child;
    }

    $tree['children'] = $kept;
}

/**
 * Give a node the hidden attribute's class twin, d-none. Bootstrap's
 * [hidden] rule loses to a display class such as d-flex (both !important,
 * the utility later), and d-none comes after every other display utility.
 * software.init_comment_actions() in frontend.js switches both together.
 *
 * @param array $node
 * @return void
 */
function _pg_comments_hide_class(&$node)
{
    $node['props']['cssClass'] = _pg_unique_classes((isset($node['props']['cssClass']) && is_string($node['props']['cssClass']) ? $node['props']['cssClass'] : '') . ' d-none');
}

/**
 * Keep the "Replying to" box in the page for a visitor who may answer
 * comments, so a reply control can show it without a new request
 * (software.init_comment_actions() in frontend.js). A semantic node shown
 * only while replying (is_replying) loses the flag and gets
 * data-pg-comment-replying, and the hidden attribute while the visitor is
 * not replying ($replying); a semantic node bound to ^^__reply_to_name^^
 * gets data-pg-comment-reply-to-name, the name the browser writes in. Other
 * nodes keep the flag; the loop_area is not walked. A hidden box also gets
 * d-none (_pg_comments_hide_class()). Called only when the
 * visitor may answer: otherwise is_replying is false and drops the box.
 *
 * @param array $tree
 * @param bool  $replying
 * @return void
 */
function pg_comments_mark_reply_boxes(&$tree, $replying)
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) {
        return;
    }

    foreach ($tree['children'] as &$child) {
        if (!is_array($child) || (isset($child['type']) && ($child['type'] === 'loop_area'))) {
            continue;
        }

        $semantic = isset($child['type']) && ($child['type'] === 'semantic');
        $bindings = (isset($child['props']['_bindings']) && is_array($child['props']['_bindings'])) ? $child['props']['_bindings'] : array();

        if ($semantic && isset($bindings['eo_visible_if']) && ($bindings['eo_visible_if'] === 'is_replying')) {
            unset($child['props']['_bindings']['eo_visible_if']);
            $add = array('data-pg-comment-replying' => '');

            if (!$replying) {
                $add['hidden'] = '';
                _pg_comments_hide_class($child);
            }

            _pg_comments_set_attrs($child, array('data-pg-comment-replying' => 1, 'hidden' => 1), $add);
        }

        if ($semantic && isset($bindings['text']) && ($bindings['text'] === '__reply_to_name')) {
            _pg_comments_set_attrs($child, array('data-pg-comment-reply-to-name' => 1), array('data-pg-comment-reply-to-name' => ''));
        }

        pg_comments_mark_reply_boxes($child, $replying);
    }
    unset($child);
}

/**
 * The fields the server puts first in a watch form, a watcher manager's
 * form or the open-or-close form: the CSRF token field, then send_to,
 * page_id, item_id, item_type and action ('add', 'remove', 'allow' or
 * 'disallow'; any other action gives ''), then management=true and the
 * watcher's id when $extra asks. Text goes through
 * pg_sw_filter_value_html(); ids are whole numbers (0 for anything that is
 * not a value).
 *
 * @param mixed $token_field get_token_field()
 * @param mixed $send_to
 * @param mixed $page_id
 * @param mixed $item_type
 * @param mixed $item_id
 * @param mixed $action
 * @param mixed $extra       'management' (bool), 'id' (the watcher's id)
 * @return string
 */
function pg_comments_small_form_hidden_html($token_field, $send_to, $page_id, $item_type, $item_id, $action, $extra = array())
{
    if (!is_string($action) || !in_array($action, array('add', 'remove', 'allow', 'disallow'), true)) {
        return '';
    }

    $extra = is_array($extra) ? $extra : array();
    $int = function ($value) {
        return (is_scalar($value) && !is_bool($value)) ? max(0, (int) $value) : 0;
    };
    $text = function ($value) {
        return pg_sw_filter_value_html(is_scalar($value) ? (string) $value : '');
    };

    $html = (is_scalar($token_field) ? (string) $token_field : '')
        . '<input type="hidden" name="send_to" value="' . $text($send_to) . '">'
        . '<input type="hidden" name="page_id" value="' . $int($page_id) . '">'
        . '<input type="hidden" name="item_id" value="' . $int($item_id) . '">'
        . '<input type="hidden" name="item_type" value="' . $text($item_type) . '">'
        . '<input type="hidden" name="action" value="' . $action . '">';

    if (!empty($extra['management'])) {
        $html .= '<input type="hidden" name="management" value="true">';
    }

    if (isset($extra['id'])) {
        $html .= '<input type="hidden" name="id" value="' . $int($extra['id']) . '">';
    }

    return $html;
}

/**
 * Wire a link or a button to an address and data attributes: a content
 * link or a semantic <a> gets $href (in place of a bound one) and, with
 * $nofollow, rel nofollow added to its rel; a semantic <button> becomes
 * type=button, so it sends no form; a button component drawn as a button
 * (or an input) becomes btnType button, one drawn as a link gets $href and
 * the rel. Each gets the $data attributes. Anything else is left alone and
 * gives false.
 *
 * @param array  $node
 * @param string $href
 * @param bool   $nofollow
 * @param array  $data name => value
 * @return bool
 */
function _pg_comments_wire_link_or_button(&$node, $href, $nofollow, $data)
{
    $type = isset($node['type']) ? (string) $node['type'] : '';
    $tag = isset($node['props']['tag']) ? strtolower((string) $node['props']['tag']) : '';
    $managed = array();

    foreach ($data as $name => $value) {
        $managed[strtolower($name)] = 1;
    }

    $as_link = function (&$node) use ($href, $nofollow, $data, $managed) {
        $node['props']['href'] = $href;
        unset($node['props']['_bindings']['href']);

        if ($nofollow) {
            // A rel the Attributes panel holds prints only while the prop is
            // empty, so it joins the prop that takes its place.
            $rel = (isset($node['props']['rel']) && is_string($node['props']['rel']) && (trim($node['props']['rel']) !== ''))
                ? $node['props']['rel'] : _user_attr_value($node['props'], 'rel');
            $node['props']['rel'] = _pg_comments_rel_nofollow($rel);
        }

        _pg_comments_set_attrs($node, $managed + array('href' => 1), $data);
    };

    if (($type === 'content') && isset($node['props']['contentType']) && ($node['props']['contentType'] === 'link')) {
        $as_link($node);
        return true;
    }

    if (($type === 'semantic') && ($tag === 'a')) {
        $as_link($node);
        return true;
    }

    if (($type === 'semantic') && ($tag === 'button')) {
        _pg_comments_set_attrs($node, $managed + array('type' => 1), array('type' => 'button') + $data);
        return true;
    }

    if (($type === 'component') && isset($node['props']['componentType']) && ($node['props']['componentType'] === 'btn')) {
        $element = isset($node['props']['btnElement']) ? (string) $node['props']['btnElement'] : 'a';

        if (($element === 'button') || ($element === 'input')) {
            $node['props']['btnType'] = 'button';
            _pg_comments_set_attrs($node, $managed, $data);
        } else {
            $node['props']['btnElement'] = 'a';
            $as_link($node);
        }

        return true;
    }

    return false;
}

/**
 * Whether a node is a button that can send a form: a semantic <button> or a
 * button component.
 *
 * @param mixed $node
 * @return bool
 */
function _pg_comments_is_button($node)
{
    if (!is_array($node) || !isset($node['type'])) {
        return false;
    }

    if ($node['type'] === 'semantic') {
        return isset($node['props']['tag']) && (strtolower((string) $node['props']['tag']) === 'button');
    }

    return ($node['type'] === 'component') && isset($node['props']['componentType']) && ($node['props']['componentType'] === 'btn');
}

/**
 * Make a button send its form, as comment_submit does: a semantic button
 * gets type=submit, a button component is drawn as a submit button.
 *
 * @param array $node
 * @return void
 */
function _pg_comments_make_submit(&$node)
{
    if ($node['type'] === 'component') {
        $node['props']['btnElement'] = 'button';
        $node['props']['btnType'] = 'submit';
    } else {
        _pg_comments_set_attrs($node, array('type' => 1), array('type' => 'submit'));
    }
}

/**
 * The walk of pg_comments_apply_control_bindings() over one node's
 * children. $form is the bound small form $node is in: '' (none), 'watch'
 * or 'allow'; $wired counts the small forms wired so far.
 *
 * @param array  $node
 * @param array  $ctx
 * @param string $form
 * @param array  $wired 'watch', 'allow'
 * @return void
 */
function _pg_comments_control_walk(&$node, $ctx, $form, &$wired)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
        return;
    }

    // The buttons that send a small form, the form each belongs to, and
    // whether it applies to the visitor's case.
    $sends = array(
        'comment_watch' => array('watch', empty($ctx['is_watcher'])),
        'comment_unwatch' => array('watch', !empty($ctx['is_watcher'])),
        'comment_close' => array('allow', !empty($ctx['allow_new'])),
        'comment_reopen' => array('allow', empty($ctx['allow_new'])),
    );
    $small_forms = array(
        'comment_watch_form' => array('watch', 'can_watch', 'software_watcher', 'watch_action_url', 'watch_hidden_html'),
        'comment_allow_new_form' => array('allow', 'is_staff', 'software_allow_or_disallow_new_comments', 'allow_action_url', 'allow_hidden_html'),
    );
    $text = function ($key) use ($ctx) {
        return (isset($ctx[$key]) && is_scalar($ctx[$key])) ? (string) $ctx[$key] : '';
    };
    $kept = array();

    foreach ($node['children'] as $child) {
        if (!is_array($child) || (isset($child['type']) && ($child['type'] === 'loop_area'))) {
            $kept[] = $child;
            continue;
        }

        $bindings = (isset($child['props']['_bindings']) && is_array($child['props']['_bindings'])) ? $child['props']['_bindings'] : array();
        $action = (isset($bindings['action']) && is_string($bindings['action'])) ? $bindings['action'] : '';
        $is_form = isset($child['type']) && ($child['type'] === 'semantic') && isset($child['props']['tag']) && (strtolower((string) $child['props']['tag']) === 'form');

        // A small form: one of each, for a visitor it serves.
        if ($is_form && isset($small_forms[$action])) {
            list($kind, $right, $anchor, $url_key, $hidden_key) = $small_forms[$action];

            if (empty($ctx[$right]) || ($wired[$kind] > 0) || ($form !== '')) {
                continue;
            }

            $wired[$kind]++;
            $child['props']['id'] = $anchor;
            _pg_comments_set_attrs($child, array('action' => 1, 'method' => 1, 'enctype' => 1, 'id' => 1, 'novalidate' => 1),
                array('action' => $text($url_key), 'method' => 'post'));

            if (!isset($child['children']) || !is_array($child['children'])) {
                $child['children'] = array();
            }

            array_unshift($child['children'], array('type' => 'content',
                'props' => array('contentType' => 'custom_html', 'html' => $text($hidden_key)),
                'children' => array()));
            _pg_comments_control_walk($child, $ctx, $kind, $wired);
            $kept[] = $child;
            continue;
        }

        // A button that sends a small form: inside its own form, when it
        // applies; a link or anything else would send nothing.
        if (isset($sends[$action])) {
            if (!_pg_comments_is_button($child)) {
                continue;
            }

            if (($form !== $sends[$action][0]) || !$sends[$action][1]) {
                $child['props']['_pg_comment_drop'] = true;
            } else {
                _pg_comments_make_submit($child);
            }

            $kept[] = $child;
            continue;
        }

        // Leaving the comment the visitor was answering.
        if ($action === 'comment_reply_cancel') {
            if (empty($ctx['can_reply'])) {
                if (_pg_comments_is_button($child)) {
                    $child['props']['_pg_comment_drop'] = true;
                    $kept[] = $child;
                }
                continue;
            }

            _pg_comments_set_attrs($child, array('hidden' => 1), array());
            $data = array('data-pg-comment-reply-cancel' => '', 'data-pg-comment-replying' => '');

            if (empty($ctx['replying'])) {
                $data['hidden'] = '';
                _pg_comments_hide_class($child);
            }

            if (_pg_comments_wire_link_or_button($child, $text('cancel_url'), false, $data)) {
                $kept[] = $child;
            }
            continue;
        }

        // Reply and share belong to one comment: outside the loop_area they
        // have none.
        if (($action === 'comment_reply') || ($action === 'comment_share')) {
            if (_pg_comments_is_button($child)) {
                $child['props']['_pg_comment_drop'] = true;
                $kept[] = $child;
            }
            continue;
        }

        _pg_comments_control_walk($child, $ctx, $form, $wired);
        $kept[] = $child;
    }

    $node['children'] = $kept;
}

/**
 * Wire the controls of a widget tree outside its loop_area: the watch form,
 * the open-or-close form and their buttons, and the link or button that
 * leaves a reply. Pure.
 *
 * - A semantic form bound to comment_watch_form posts to
 *   add_or_remove_watcher.php (#software_watcher, the handler's anchor) for
 *   a visitor who may watch ($ctx can_watch), with its hidden fields first;
 *   one bound to comment_allow_new_form posts to
 *   allow_or_disallow_new_comments.php (#software_allow_or_disallow_new_comments)
 *   for staff (is_staff). One of each; a second one, and one inside another
 *   bound form, is dropped.
 * - comment_watch / comment_unwatch send the watch form, the one that
 *   applies by is_watcher; comment_close / comment_reopen send the
 *   open-or-close form by allow_new. Only a button inside its own form
 *   sends: elsewhere, or not applying, it is dropped with its label and an
 *   emptied wrapper; anything else bound to them is dropped.
 * - comment_reply_cancel, on a link or a button, for a visitor who may
 *   answer comments (can_reply): the link goes to cancel_url (the page
 *   without ?reply_to=) and the browser empties the form's parent_id
 *   instead; hidden while the visitor is not replying.
 * - comment_reply and comment_share outside the loop_area answer no comment
 *   and are dropped.
 *
 * $ctx: pg_comments_widget_draw()'s 'controls'.
 *
 * @param array $tree
 * @param mixed $ctx
 * @return void
 */
function pg_comments_apply_control_bindings(&$tree, $ctx)
{
    if (!is_array($tree)) {
        return;
    }

    $ctx = is_array($ctx) ? $ctx : array();
    $wired = array('watch' => 0, 'allow' => 0);
    _pg_comments_control_walk($tree, $ctx, '', $wired);
    _pg_member_drop_controls($tree, function ($tag, $attrs, $node) {
        return !empty($node['props']['_pg_comment_drop']);
    });
}

/**
 * The walk of pg_comments_apply_row_bindings() over one node's children.
 *
 * @param array $node
 * @param array $ctx
 * @return void
 */
function _pg_comments_row_walk(&$node, $ctx)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
        return;
    }

    $outside = array('comment_reply_cancel' => true, 'comment_watch' => true, 'comment_unwatch' => true, 'comment_close' => true, 'comment_reopen' => true);
    $kept = array();

    foreach ($node['children'] as $child) {
        if (!is_array($child)) {
            $kept[] = $child;
            continue;
        }

        $bindings = (isset($child['props']['_bindings']) && is_array($child['props']['_bindings'])) ? $child['props']['_bindings'] : array();
        $action = (isset($bindings['action']) && is_string($bindings['action'])) ? $bindings['action'] : '';
        $section = (isset($bindings['section']) && is_string($bindings['section'])) ? $bindings['section'] : '';
        $drop = function ($child) use (&$kept) {
            if (_pg_comments_is_button($child)) {
                $child['props']['_pg_comment_drop'] = true;
                $kept[] = $child;
            }
        };

        if (isset($outside[$action]) || in_array($action, array('comment_watch_form', 'comment_allow_new_form'), true)
            || in_array($section, array('comment_watchers', 'comment_pagination'), true)
        ) {
            $drop($child);
            continue;
        }

        if ($action === 'comment_reply') {
            if (empty($ctx['can_reply'])
                || !_pg_comments_wire_link_or_button($child, '^^__comment_reply_url^^', true,
                    array('data-pg-comment-reply' => '^^__comment_id^^', 'data-pg-comment-reply-name' => '^^__comment_name^^'))
            ) {
                $drop($child);
                continue;
            }

            // Drawn only on a comment the visitor may answer.
            $kept[] = array('type' => 'root', 'props' => array('_bindings' => array('eo_visible_if' => 'pg_comment_reply_ok')), 'children' => array($child));
            continue;
        }

        if ($action === 'comment_share') {
            $copied = (isset($ctx['share_copied']) && is_scalar($ctx['share_copied'])) ? (string) $ctx['share_copied'] : '';
            $prompt = (isset($ctx['share_prompt']) && is_scalar($ctx['share_prompt'])) ? (string) $ctx['share_prompt'] : '';

            if (_pg_comments_wire_link_or_button($child, '^^__comment_url^^', true, array(
                'data-pg-comment-share' => '',
                'data-pg-comment-url' => '^^__comment_url^^',
                'data-pg-comment-copied' => $copied,
                'data-pg-comment-copy-prompt' => $prompt,
            ))) {
                $kept[] = $child;
            }
            continue;
        }

        _pg_comments_row_walk($child, $ctx);
        $kept[] = $child;
    }

    $node['children'] = $kept;
}

/**
 * Wire the controls of one comment, in the loop_area's template. Pure.
 *
 * - comment_reply, on a link or a button, for a visitor who may answer
 *   comments ($ctx can_reply): the link goes to ^^__comment_reply_url^^
 *   (?reply_to=<id>#software_add_comment, which works without script), rel
 *   nofollow; a button sends nothing; both carry the comment's id and name
 *   for the browser, which fills the form's parent_id instead. It is drawn
 *   only on a comment the visitor may answer: wrapped in a root node shown
 *   by the internal per-row flag pg_comment_reply_ok.
 * - comment_share, on a link or a button: the link goes to the comment's
 *   permalink (^^__comment_url^^), rel nofollow; both carry it with the words
 *   the browser shows once it copied it, or asks with.
 * - The controls of the thread (cancel, watch, unwatch, close, reopen), the
 *   watch and open-or-close forms and the comment_watchers and
 *   comment_pagination sections have no place in a comment and are dropped.
 *
 * @param array $loop_tree a root node holding the loop_area's children
 * @param mixed $ctx       pg_comments_widget_draw()'s 'controls'
 * @return void
 */
function pg_comments_apply_row_bindings(&$loop_tree, $ctx)
{
    if (!is_array($loop_tree)) {
        return;
    }

    _pg_comments_row_walk($loop_tree, is_array($ctx) ? $ctx : array());
    _pg_member_drop_controls($loop_tree, function ($tag, $attrs, $node) {
        return !empty($node['props']['_pg_comment_drop']);
    });
}

/**
 * Name the Messages nodes of the small forms after the handlers they post
 * to: one inside a form bound to comment_watch_form prints the
 * add_or_remove_watcher messages, one inside comment_allow_new_form the
 * allow_or_disallow_new_comments ones. Run after
 * _pg_inject_messages_node($tree, 'add_comment'), which names them all and
 * adds one only to a tree that has none. Pure.
 *
 * When no node is left for add_comment (the design kept a Messages node only
 * in a small form), one is added where _pg_inject_messages_node() adds it:
 * first in the tree's first container, else first in the tree. Otherwise a
 * refused comment's error would print nowhere and be removed unread.
 *
 * @param array $tree
 * @return void
 */
function pg_comments_stamp_form_messages(&$tree)
{
    if (!is_array($tree)) {
        return;
    }

    _pg_comments_stamp_walk($tree, '');

    if (_pg_comments_has_messages($tree, 'add_comment') || empty($tree['children']) || !is_array($tree['children'])) {
        return;
    }

    $messages = array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => '', 'formName' => 'add_comment'), 'children' => array());

    if (isset($tree['children'][0]['type']) && ($tree['children'][0]['type'] === 'container')) {
        $kids = (isset($tree['children'][0]['children']) && is_array($tree['children'][0]['children'])) ? $tree['children'][0]['children'] : array();
        array_unshift($kids, $messages);
        $tree['children'][0]['children'] = $kids;
    } else {
        array_unshift($tree['children'], $messages);
    }
}

/**
 * Whether a tree holds a Messages node named $name. Pure.
 *
 * @param mixed  $node
 * @param string $name
 * @return bool
 */
function _pg_comments_has_messages($node, $name)
{
    if (!is_array($node)) {
        return false;
    }

    if (isset($node['type'], $node['props']['contentType'], $node['props']['formName']) && ($node['type'] === 'content')
        && ($node['props']['contentType'] === 'messages') && ((string) $node['props']['formName'] === $name)
    ) {
        return true;
    }

    foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
        if (_pg_comments_has_messages($child, $name)) {
            return true;
        }
    }

    return false;
}

/**
 * The walk of pg_comments_stamp_form_messages().
 *
 * @param array  $tree
 * @param string $name the handler of the small form $tree is in, '' for none
 * @return void
 */
function _pg_comments_stamp_walk(&$tree, $name)
{
    if (!is_array($tree)) {
        return;
    }

    if (($name !== '') && isset($tree['type']) && ($tree['type'] === 'content')
        && isset($tree['props']['contentType']) && ($tree['props']['contentType'] === 'messages')
    ) {
        $tree['props']['formName'] = $name;
    }

    $forms = array('comment_watch_form' => 'add_or_remove_watcher', 'comment_allow_new_form' => 'allow_or_disallow_new_comments');
    $action = (isset($tree['props']['_bindings']['action']) && is_string($tree['props']['_bindings']['action'])) ? $tree['props']['_bindings']['action'] : '';
    $is_form = isset($tree['type']) && ($tree['type'] === 'semantic') && isset($tree['props']['tag']) && (strtolower((string) $tree['props']['tag']) === 'form');

    if ($is_form && isset($forms[$action])) {
        $name = $forms[$action];
    }

    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$child) {
            _pg_comments_stamp_walk($child, $name);
        }
        unset($child);
    }
}

/**
 * The sentence that says how many people are told of a new comment
 * (^^__watcher_count_text^^), by the classic block's rule: one person who is
 * not the visitor, or more than one; '' otherwise. Raw.
 *
 * @param mixed  $count       watchers, with the form editor and the record's
 *                            submitter when they are counted
 * @param bool   $is_watcher  the visitor is one of them
 * @param string $label_lower the comment label in lower case
 * @return string
 */
function pg_comments_watcher_count_text($count, $is_watcher, $label_lower)
{
    $n = (is_scalar($count) && !is_bool($count)) ? (int) $count : 0;
    $label = is_scalar($label_lower) ? (string) $label_lower : '';

    if (($n === 1) && !$is_watcher) {
        return lang(array('string' => '{var:1} {var:2} will be notified when a {var:3} is added.', 'vars' => array('1', lang('person'), $label)));
    }

    if ($n > 1) {
        return lang(array('string' => '{var:1} {var:2} will be notified when a {var:3} is added.', 'vars' => array(pg_format_number($n, 0), lang('people'), $label)));
    }

    return '';
}

/**
 * An e-mail address with its name part hidden: the first letter, then ***
 * and the domain (j***@example.com); an address without @ keeps its first
 * letter; '' stays ''. Pure.
 *
 * @param mixed $email
 * @return string
 */
function pg_comments_mask_email($email)
{
    $email = (is_scalar($email) && !is_bool($email)) ? trim((string) $email) : '';

    if ($email === '') {
        return '';
    }

    $at = mb_strrpos($email, '@', 0, 'UTF-8');

    if ($at === false) {
        return mb_substr($email, 0, 1, 'UTF-8') . '***';
    }

    return mb_substr($email, 0, min(1, $at), 'UTF-8') . '***' . mb_substr($email, $at, null, 'UTF-8');
}

/**
 * How a watcher is named in the watcher list. Pure.
 *
 * A member by username, with the address in brackets when $show_email and
 * it differs from the username; an e-mail-only watcher by the address when
 * $show_email, else masked (pg_comments_mask_email()). Addresses show only to
 * staff (a role below 3) and to their owner: a member who manages watchers
 * could otherwise read any member's address by adding them.
 *
 * @param mixed $username
 * @param mixed $email
 * @param bool  $show_email
 * @return string
 */
function pg_comments_watcher_label($username, $email, $show_email)
{
    $username = (is_scalar($username) && !is_bool($username)) ? (string) $username : '';
    $email = (is_scalar($email) && !is_bool($email)) ? (string) $email : '';

    if ($username !== '') {
        if ($show_email && ($email !== '') && (mb_strtolower($email, 'UTF-8') !== mb_strtolower($username, 'UTF-8'))) {
            return $username . ' (' . $email . ')';
        }

        return $username;
    }

    if ($email === '') {
        return '';
    }

    return $show_email ? $email : pg_comments_mask_email($email);
}

/**
 * The watcher manager (the comment_watchers section): the people told of a
 * new comment, each watcher with a button that removes it, and a box that
 * adds one by username or e-mail address. Every form posts to
 * add_or_remove_watcher.php as a manager (management=true), which checks the
 * right again and checks a removed id against this page and item. No inline
 * style; every text through pg_sw_filter_value_html().
 *
 * $data: action_url, token_field, send_to, page_id, item_type, item_id,
 * fixed (labels of the people told anyway: the form editor, the record's
 * submitter; not removable), watchers (each 'id' and 'label'), typed (the
 * address a refused add left in the box).
 *
 * @param mixed $data
 * @return string
 */
function pg_comments_watchers_html($data)
{
    $data = is_array($data) ? $data : array();
    $text = function ($key) use ($data) {
        return (isset($data[$key]) && is_scalar($data[$key]) && !is_bool($data[$key])) ? (string) $data[$key] : '';
    };
    $list = function ($key) use ($data) {
        return (isset($data[$key]) && is_array($data[$key])) ? $data[$key] : array();
    };
    $action = pg_sw_filter_value_html($text('action_url'));
    $hidden = function ($what, $extra) use ($text) {
        return pg_comments_small_form_hidden_html($text('token_field'), $text('send_to'), $text('page_id'), $text('item_type'), $text('item_id'), $what, $extra);
    };
    $items = '';

    foreach ($list('fixed') as $label) {
        if (is_scalar($label) && ((string) $label !== '')) {
            $items .= '<li class="fst-italic mb-1">' . pg_sw_filter_value_html((string) $label) . '</li>';
        }
    }

    foreach ($list('watchers') as $watcher) {
        $id = (is_array($watcher) && isset($watcher['id']) && is_scalar($watcher['id'])) ? (int) $watcher['id'] : 0;
        $label = (is_array($watcher) && isset($watcher['label']) && is_scalar($watcher['label'])) ? (string) $watcher['label'] : '';

        if (($id <= 0) || ($label === '')) {
            continue;
        }

        $items .= '<li class="d-flex align-items-center gap-2 mb-1"><span>' . pg_sw_filter_value_html($label) . '</span>'
            . '<form action="' . $action . '" method="post" class="d-inline">'
            . $hidden('remove', array('management' => true, 'id' => $id))
            . '<button type="submit" class="btn btn-sm btn-link text-danger p-0" aria-label="'
            . pg_sw_filter_value_html(lang(array('string' => 'Remove the watcher {var:1}', 'vars' => $label))) . '">&times;</button>'
            . '</form></li>';
    }

    $html = '<div class="pg-comment-watchers">';

    if ($items !== '') {
        $html .= '<ul class="list-unstyled mb-2">' . $items . '</ul>';
    }

    return $html
        . '<form action="' . $action . '" method="post" class="d-flex flex-wrap align-items-center gap-2">'
        . $hidden('add', array('management' => true))
        . '<label class="visually-hidden" for="comment_watcher_add">' . pg_sw_filter_value_html(lang('Username or e-mail address')) . '</label>'
        . '<input type="text" class="form-control form-control-sm w-auto" id="comment_watcher_add" name="username_or_email_address" maxlength="100" placeholder="'
        . pg_sw_filter_value_html(lang('username or email address')) . '" value="' . pg_sw_filter_value_html($text('typed')) . '">'
        . '<button type="submit" class="btn btn-sm btn-outline-secondary">' . pg_sw_filter_value_html(lang('Add Watcher')) . '</button>'
        . '</form></div>';
}

/**
 * Give the watcher list the feedback of add_or_remove_watcher.php when no
 * watch form drawn for the visitor carries it. Pure; run on the tree as the
 * visitor gets it (sections filled, flags applied), before it is drawn.
 *
 * The handler answers every add and remove at #software_watcher with its
 * errors and notices. They print in the watch form, which carries that id
 * and the Messages node re-stamped add_or_remove_watcher. A manager who is
 * not offered the watch form (the record's submitter, told of new comments
 * anyway), or whose design's watch form has no Messages node, would read
 * nothing, and the renderer removes the state after the draw. The first
 * comment_watchers section drawn then gets, first inside it, the anchor when
 * nothing carries the id and a Messages node for add_or_remove_watcher when
 * none is left, as the classic block prints them in its manager area.
 *
 * @param array $tree
 * @return void
 */
function pg_comments_watchers_feedback(&$tree)
{
    if (!is_array($tree)) {
        return;
    }

    $anchor = _pg_comments_has_id($tree, 'software_watcher');
    $messages = _pg_comments_has_messages($tree, 'add_or_remove_watcher');

    if ($anchor && $messages) {
        return;
    }

    $add = array();

    if (!$anchor) {
        $add[] = array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<a id="software_watcher"></a>'), 'children' => array());
    }

    if (!$messages) {
        $add[] = array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => '', 'formName' => 'add_or_remove_watcher'), 'children' => array());
    }

    _pg_comments_watchers_prepend($tree, $add);
}

/**
 * Whether a node of the tree carries the element id $id. Pure.
 *
 * @param mixed  $node
 * @param string $id
 * @return bool
 */
function _pg_comments_has_id($node, $id)
{
    if (!is_array($node)) {
        return false;
    }

    if (isset($node['props']['id']) && is_scalar($node['props']['id']) && ((string) $node['props']['id'] === $id)) {
        return true;
    }

    foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
        if (_pg_comments_has_id($child, $id)) {
            return true;
        }
    }

    return false;
}

/**
 * Put $add first inside the first comment_watchers section of the tree.
 *
 * @param array $node
 * @param array $add
 * @return bool whether a section took them
 */
function _pg_comments_watchers_prepend(&$node, $add)
{
    if (!is_array($node)) {
        return false;
    }

    if (isset($node['props']['_bindings']['section']) && ($node['props']['_bindings']['section'] === 'comment_watchers')) {
        $node['children'] = array_merge($add, (isset($node['children']) && is_array($node['children'])) ? $node['children'] : array());
        return true;
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            if (_pg_comments_watchers_prepend($child, $add)) {
                unset($child);
                return true;
            }
        }
        unset($child);
    }

    return false;
}

// ============================================================================
// The default layout
// ============================================================================

/**
 * The widget's default layout, node for node the editor's starter
 * (_buildStarterTree('comments') in assets/js/style_designer.js; compared by
 * tests/comments_widget_test.php). Its loop_area is also the server's
 * fallback for a stored layout without one (pg_comments_widget_draw()).
 *
 * @return array
 */
function pg_comments_default_tree()
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
    $attrs = function ($name, $value) {
        return array(array('name' => $name, 'value' => $value));
    };

    $comment = $sem('div', array('cssClass' => 'd-flex mb-3', 'customName' => lang('Comment')), array(
        $sem('div', array('cssClass' => 'flex-shrink-0 border-start border-2 ms-2 me-3', 'customName' => lang('Reply Indent'),
            '_bindings' => array('eo_visible_if' => 'comment_is_reply'))),
        $sem('div', array('cssClass' => 'card flex-grow-1', 'customName' => lang('Comment Card')), array(
            $sem('div', array('cssClass' => 'card-body', 'customName' => lang('Comment Body')), array(
                $sem('div', array('cssClass' => 'small text-danger mb-2', 'text' => lang('Awaiting approval'), 'customName' => lang('Comment Status'),
                    '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'))),
                $sem('div', array('cssClass' => 'text-warning mb-1', 'customName' => lang('Stars'),
                    '_bindings' => array('eo_visible_if' => 'comment_has_rating')), pg_sw_part_comment_stars()),
                $sem('div', array('cssClass' => 'd-flex flex-wrap align-items-center gap-1', 'customName' => lang('Name Line')), array(
                    $sem('span', array('cssClass' => 'fw-semibold', 'text' => lang('Jane Cooper'), '_bindings' => array('text' => '__comment_name'))),
                    $sem('span', array('cssClass' => 'small text-body-secondary', 'text' => 'jane',
                        '_bindings' => array('text' => '__comment_staff_name', 'eo_visible_if' => 'is_staff'))),
                    $sem('span', array('cssClass' => 'badge rounded-pill text-bg-secondary', 'text' => lang('Badge'),
                        '_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
                    $sem('span', array('cssClass' => 'badge rounded-pill text-bg-success', 'text' => lang('Verified purchase'),
                        '_bindings' => array('eo_visible_if' => 'comment_is_verified'))),
                    $sem('span', array('cssClass' => 'badge rounded-pill text-bg-primary', 'text' => lang('Featured'),
                        '_bindings' => array('eo_visible_if' => 'comment_is_featured'))),
                )),
                $sem('div', array('cssClass' => 'small text-body-secondary pg-hide-if-empty', 'text' => lang('2 days ago'),
                    '_bindings' => array('text' => '__comment_date'))),
                $sem('div', array('cssClass' => 'border-start border-3 ps-3 my-2 small text-body-secondary', 'customName' => lang('Quote'),
                    '_bindings' => array('eo_visible_if' => 'comment_has_quote')), array(
                    $content('link', array('text' => lang('Jane Cooper'), 'href' => '#', 'cssClass' => 'fw-semibold link-secondary',
                        '_bindings' => array('href' => '__comment_parent_url', 'text' => '__comment_parent_name'))),
                    $sem('div', array('cssClass' => 'pg-hide-if-empty', 'text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote'))),
                )),
                $sem('div', array('cssClass' => 'mt-2', 'customName' => lang('Message'), 'text' => lang('Comment text'),
                    '_bindings' => array('text' => '__comment_message_html'))),
                $sem('div', array('cssClass' => 'small mt-3 d-flex align-items-center gap-2', 'customName' => lang('Attachment'),
                    '_bindings' => array('eo_visible_if' => 'comment_has_file')), array(
                    $content('link', array('text' => 'file.pdf', 'href' => '#', 'cssClass' => '', 'target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                    $sem('span', array('cssClass' => 'text-body-secondary', 'text' => '12 KB', '_bindings' => array('text' => '__comment_file_size'))),
                )),
                $sem('div', array('cssClass' => 'border-start border-3 ps-3 mt-3', 'customName' => lang('Store Reply'),
                    '_bindings' => array('eo_visible_if' => 'comment_has_seller_reply')), array(
                    $sem('div', array('cssClass' => 'small fw-semibold', 'text' => lang('Reply from the store'))),
                    $sem('div', array('cssClass' => 'small text-body-secondary pg-hide-if-empty', '_bindings' => array('text' => '__comment_seller_reply_date'))),
                    $sem('div', array('cssClass' => 'mt-1', 'text' => lang('Reply text'), '_bindings' => array('text' => '__comment_seller_reply_html'))),
                )),
                $sem('div', array('cssClass' => 'd-flex flex-wrap gap-3 mt-3 small', 'customName' => lang('Comment Actions')), array(
                    $content('link', array('text' => lang('Reply'), 'href' => '#', 'cssClass' => 'btn btn-link btn-sm p-0',
                        '_bindings' => array('action' => 'comment_reply'))),
                    $content('link', array('text' => lang('Share'), 'href' => '#', 'cssClass' => 'btn btn-link btn-sm p-0 link-secondary',
                        '_bindings' => array('action' => 'comment_share'))),
                )),
            )),
        )),
    ));

    $form = $sem('form', array('cssClass' => 'card card-body', 'customName' => lang('Comment Form'),
        '_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment')), array(
        $content('messages', array('cssClass' => '')),
        $sem('div', array('cssClass' => 'alert alert-secondary d-flex flex-wrap align-items-center gap-2 py-2 small', 'customName' => lang('Replying To'),
            '_bindings' => array('eo_visible_if' => 'is_replying')), array(
            $sem('span', array('text' => lang('Replying to'))),
            $sem('strong', array('_bindings' => array('text' => '__reply_to_name'))),
            $content('link', array('text' => lang('Cancel'), 'href' => '#', 'cssClass' => 'ms-auto', '_bindings' => array('action' => 'comment_reply_cancel'))),
        )),
        $sem('div', array('cssClass' => 'mb-3'), array(
            $sem('label', array('cssClass' => 'form-label visually-hidden', 'text' => lang('Comment'), '_attrs' => $attrs('for', 'comment_message'))),
            $sem('textarea', array('cssClass' => 'form-control', 'id' => 'comment_message', '_attrs' => $attrs('rows', '5'),
                '_bindings' => array('value' => 'comment_message'))),
        )),
        $sem('div', array('cssClass' => 'mb-3', '_bindings' => array('eo_visible_if' => 'name_is_typed')), array(
            $sem('label', array('cssClass' => 'form-label', 'text' => lang('Your name (optional)'), '_attrs' => $attrs('for', 'comment_name'))),
            $sem('input', array('cssClass' => 'form-control', 'id' => 'comment_name', '_attrs' => $attrs('type', 'text'),
                '_bindings' => array('value' => 'comment_name'))),
        )),
        $sem('div', array('cssClass' => 'mb-3', '_bindings' => array('eo_visible_if' => 'name_is_chosen')), array(
            $sem('label', array('cssClass' => 'form-label', 'text' => lang('Your Name'), '_attrs' => $attrs('for', 'comment_name_type'))),
            $sem('select', array('cssClass' => 'form-select', 'id' => 'comment_name_type', '_bindings' => array('value' => 'comment_name_type'))),
        )),
        $sem('div', array('cssClass' => 'mb-3 small', '_bindings' => array('eo_visible_if' => 'name_is_fixed')), array(
            $sem('span', array('cssClass' => 'me-1', 'text' => lang('Posting as'))),
            $sem('strong', array('_bindings' => array('text' => '__comment_author_name'))),
        )),
        $sem('div', array('cssClass' => 'mb-3', '_bindings' => array('eo_visible_if' => 'rating_on')), array(
            $sem('div', array('cssClass' => 'form-label', 'text' => lang('Rating'))),
            $sem('div', array('_bindings' => array('section' => 'comment_rating_input'))),
        )),
        $sem('div', array('cssClass' => 'mb-3', '_bindings' => array('eo_visible_if' => 'files_on')), array(
            $sem('label', array('cssClass' => 'form-label', 'text' => lang('Attach a File'), '_attrs' => $attrs('for', 'comment_file'))),
            $sem('input', array('cssClass' => 'form-control', 'id' => 'comment_file', '_attrs' => $attrs('type', 'file'),
                '_bindings' => array('value' => 'comment_file'))),
        )),
        $sem('div', array('cssClass' => 'mb-3 small', '_bindings' => array('section' => 'comment_publish'))),
        $sem('div', array('cssClass' => 'form-check mb-3', '_bindings' => array('eo_visible_if' => 'watching_on')), array(
            $sem('input', array('cssClass' => 'form-check-input', 'id' => 'comment_watcher', '_attrs' => $attrs('type', 'checkbox'),
                '_bindings' => array('value' => 'comment_watcher'))),
            $sem('label', array('cssClass' => 'form-check-label', 'text' => lang('Notify me when a new comment is added.'), '_attrs' => $attrs('for', 'comment_watcher'))),
        )),
        $sem('div', array('cssClass' => 'mb-3', '_bindings' => array('section' => 'comment_captcha'))),
        $sem('div', array(), array(
            $sem('button', array('cssClass' => 'btn btn-primary', 'text' => lang('Add comment'), '_attrs' => $attrs('type', 'submit'),
                '_bindings' => array('action' => 'comment_submit'))),
        )),
    ));

    return $node('root', array(), array(
        $node('container', array('fluid' => false, 'cssClass' => 'py-4'), array(
            $sem('div', array('cssClass' => 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', 'customName' => lang('Comments Heading'),
                '_bindings' => array('eo_visible_if' => 'has_comments')), array(
                $content('heading', array('tag' => 'h2', 'text' => lang('Comments'), 'cssClass' => 'h5 mb-0', '_bindings' => array('text' => '__comments_title'))),
                $sem('div', array('cssClass' => 'd-flex gap-3 small pg-hide-if-empty', 'customName' => lang('Featured Links'),
                    '_bindings' => array('eo_visible_if' => 'has_featured')), array(
                    $content('link', array('text' => lang('Show all'), 'href' => '#', 'cssClass' => '', '_bindings' => array('href' => '__comments_show_all_url'))),
                    $content('link', array('text' => lang('Show featured'), 'href' => '#', 'cssClass' => '', '_bindings' => array('href' => '__comments_show_featured_url'))),
                )),
            )),
            $sem('div', array('customName' => lang('Comment List')), array(
                $node('loop_area', array(), array($comment)),
                $sem('div', array('cssClass' => 'mt-3', 'customName' => lang('Pagination'), '_bindings' => array('section' => 'comment_pagination'))),
            )),
            $content('paragraph', array('text' => lang('No comments yet.'), 'cssClass' => 'text-body-secondary', '_bindings' => array('eo_visible_if' => 'no_comments'))),
            $sem('div', array('cssClass' => 'mt-4', 'customName' => lang('Add a comment')), array(
                $content('heading', array('tag' => 'h3', 'text' => lang('Add a comment'), 'cssClass' => 'h6 mb-2', '_bindings' => array('eo_visible_if' => 'comments_open'))),
                $sem('p', array('cssClass' => 'small text-body-secondary mb-2 pg-hide-if-empty', '_bindings' => array('text' => '__comments_intro', 'eo_visible_if' => 'comments_open'))),
                $sem('div', array('cssClass' => 'alert alert-secondary', 'customName' => lang('Closed'),
                    '_bindings' => array('text' => '__comments_closed_message', 'eo_visible_if' => 'comments_closed'))),
                $sem('div', array('cssClass' => 'alert alert-secondary d-flex flex-wrap gap-2', 'customName' => lang('Sign in'),
                    '_bindings' => array('eo_visible_if' => 'must_sign_in')), array(
                    $sem('span', array('text' => lang('Sign in to add your comment.'))),
                    $content('link', array('text' => lang('Sign in'), 'href' => '#', 'cssClass' => 'fw-semibold', 'rel' => 'nofollow',
                        '_bindings' => array('href' => '__login_url'))),
                )),
                $form,
            )),
            $sem('div', array('cssClass' => 'small border-top pt-3 mt-4 d-flex flex-column gap-2 pg-hide-if-empty', 'customName' => lang('Watch'),
                '_bindings' => array('eo_visible_if' => 'watching_on')), array(
                $sem('div', array('cssClass' => 'pg-hide-if-empty', '_bindings' => array('text' => '__watcher_count_text'))),
                $sem('form', array('cssClass' => 'd-flex flex-wrap align-items-center gap-2', 'customName' => lang('Watch Form'),
                    '_bindings' => array('action' => 'comment_watch_form')), array(
                    $content('messages', array('cssClass' => 'w-100')),
                    $sem('button', array('cssClass' => 'btn btn-sm btn-outline-secondary', 'text' => lang('Notify me of new comments'), '_attrs' => $attrs('type', 'submit'),
                        '_bindings' => array('action' => 'comment_watch'))),
                    $sem('button', array('cssClass' => 'btn btn-sm btn-outline-secondary', 'text' => lang('Stop notifying me'), '_attrs' => $attrs('type', 'submit'),
                        '_bindings' => array('action' => 'comment_unwatch'))),
                )),
                $sem('div', array('customName' => lang('Watchers'), '_bindings' => array('section' => 'comment_watchers'))),
            )),
            $sem('div', array('cssClass' => 'd-flex justify-content-end mt-3', 'customName' => lang('Staff Controls'),
                '_bindings' => array('eo_visible_if' => 'is_staff')), array(
                $sem('form', array('customName' => lang('Open or Close'), '_bindings' => array('action' => 'comment_allow_new_form')), array(
                    $sem('button', array('cssClass' => 'btn btn-sm btn-outline-secondary', 'text' => lang('Stop new comments'), '_attrs' => $attrs('type', 'submit'),
                        '_bindings' => array('action' => 'comment_close'))),
                    $sem('button', array('cssClass' => 'btn btn-sm btn-outline-secondary', 'text' => lang('Allow new comments'), '_attrs' => $attrs('type', 'submit'),
                        '_bindings' => array('action' => 'comment_reopen'))),
                )),
            )),
        )),
    ));
}

// ============================================================================
// Drawing
// ============================================================================

/**
 * Draw a comments widget from its tree and the data the renderer gathered.
 * Pure: no query, no request; the tree already carries its Messages node
 * (stamped 'add_comment').
 *
 * $data:
 * - widget_id;
 * - thread_flags (pg_comments_widget_thread_flags());
 * - thread_values: raw text for the __comments_* tokens, __login_url and
 *   __comment_author_name;
 * - rows: each array('id', 'flags' (pg_comments_widget_row_flags()),
 *   'values' (raw __comment_* map), 'datetime' ('Y-m-d H:i:s' or ''),
 *   'depth' (drawn on the row's root as data-pg-comment-depth), 'reply_ok'
 *   (the visitor may answer it: its reply control is drawn));
 * - form: pg_comments_apply_form_bindings()'s context;
 * - controls: can_reply, replying, cancel_url, share_copied, share_prompt
 *   (pg_comments_apply_row_bindings()), can_watch, is_watcher,
 *   watch_action_url, watch_hidden_html, is_staff, allow_new,
 *   allow_action_url, allow_hidden_html
 *   (pg_comments_apply_control_bindings());
 * - sections: comment_captcha, comment_rating_input, comment_publish,
 *   comment_watchers, comment_pagination markup, '' when off (the container
 *   is then dropped);
 * - slots: the form's internal slots, already escaped;
 * - edit_chips: draw the edit pencil on each comment;
 * - recent: a list of recent comments (widgets_comments_recent.php): each
 *   comment stands on another page, so no row gets its anchor
 *   (id="c-<id>", data-pg-comment-depth) or its anchor links, the output has
 *   no #software_comments anchor, and the fallback rows go in a list-group;
 * - default_tree: the default layout whose loop_area is the fallback
 *   (pg_comments_default_tree() when none is given).
 *
 * Each value is escaped once (pg_comments_widget_escape()) and filled in one
 * strtr() pass, so a visitor's text never forms a token. A stored layout
 * without a loop_area (or with an empty one) draws the comments in the
 * default layout's; a list the visitor does not get (below a false thread
 * flag, in a form that is not drawn) draws none. A layout without a
 * comment_pagination section gets the page links right after the comments
 * (after the list, when the comments sit in one). A drawn watcher list
 * carries the watch handler's anchor and messages when no drawn watch form
 * does (pg_comments_watchers_feedback()). A link to a comment's
 * anchor keeps reaching it (pg_comments_row_anchor_links()). The output
 * starts with the #software_comments anchor the featured and page links aim
 * at.
 *
 * @param mixed $tree
 * @param mixed $data
 * @return string
 */
function pg_comments_widget_draw($tree, $data)
{
    if (!is_array($tree)) {
        return '';
    }

    $data = is_array($data) ? $data : array();
    $list = function ($key) use ($data) {
        return (isset($data[$key]) && is_array($data[$key])) ? $data[$key] : array();
    };

    $widget_id = isset($data['widget_id']) ? (int) $data['widget_id'] : 0;
    $recent = !empty($data['recent']);
    $thread_values = $list('thread_values');
    $sections = $list('sections') + array('comment_captcha' => '', 'comment_rating_input' => '', 'comment_publish' => '',
        'comment_watchers' => '', 'comment_pagination' => '');
    $slots = $list('slots');
    $controls = $list('controls');
    $edit_chips = !empty($data['edit_chips']);
    $comments_thread_flags = $list('thread_flags');
    $link_value = function ($key) use ($thread_values) {
        return (isset($thread_values[$key]) && is_scalar($thread_values[$key])) ? (string) $thread_values[$key] : '';
    };

    // Whether the design placed the list: asked before the form and the
    // sections can take a loop_area away with them.
    $designed_loop = _pg_comments_tree_has_loop($tree);
    $form = $list('form');

    if (!isset($form['flags'])) {
        $form['flags'] = $comments_thread_flags;
    }

    pg_comments_drop_nested_forms($tree);

    if (!empty($controls['can_reply'])) {
        pg_comments_mark_reply_boxes($tree, !empty($controls['replying']));
    }

    pg_comments_apply_form_bindings($tree, $form);
    pg_comments_apply_control_bindings($tree, $controls);
    pg_cf_link_labels($tree);
    pg_cf_apply_section_bindings($tree, $sections);
    _pg_member_drop_empty_links($tree, array(
        '__comments_show_all_url' => $link_value('__comments_show_all_url'),
        '__comments_show_featured_url' => $link_value('__comments_show_featured_url'),
        '__login_url' => $link_value('__login_url'),
    ));
    pg_comments_stamp_login_links($tree);

    // With the markers beside a list or table that holds the rows, where the
    // page links may go when the design placed none.
    $split = _split_widget_tree($tree, true);
    $loop_children = $split['loop_children'];
    $fallback = !is_array($loop_children) || !$loop_children;
    // The list went with the form or a section that holds it: no comments.
    $rows = ($designed_loop && !is_array($loop_children)) ? array() : $list('rows');

    if ($fallback) {
        $default = _split_widget_tree((isset($data['default_tree']) && is_array($data['default_tree'])) ? $data['default_tree'] : pg_comments_default_tree());
        $loop_children = $default['loop_children'];
    }

    $static_tree = $split['static_tree'];
    _eo_apply_visibility_bindings($static_tree, $comments_thread_flags);
    pg_comments_watchers_feedback($static_tree);

    $loop_tree = array('type' => 'root', 'props' => array(), 'children' => $loop_children);
    pg_comments_apply_row_bindings($loop_tree, $controls);
    _pg_sw_mark_visibility($loop_tree);
    $loop_tpl = trim(_render_tree_node($loop_tree, 0, 0));
    $edit_token = (strpos($loop_tpl, '^^__comment_edit_url^^') !== false);
    $rows_html = '';

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $comments_row_flags = (isset($row['flags']) && is_array($row['flags'])) ? $row['flags'] : array();
        $values = (isset($row['values']) && is_array($row['values'])) ? $row['values'] : array();
        $datetime = (isset($row['datetime']) && is_string($row['datetime'])) ? $row['datetime'] : '';
        // The reply control waits for an internal flag the designer does not
        // offer: whether the visitor may answer this comment.
        $internal = array('pg_comment_reply_ok' => !empty($row['reply_ok']));
        $html = _pg_sw_resolve_visibility($loop_tpl, $comments_row_flags + $comments_thread_flags + $internal);

        // The date takes the design's format the way a record's date field
        // does, before any visitor text is in the markup. An e-mail fills
        // it itself: the record rules would write a relative time, and the
        // signed-in sender's own time zone, into mail read later elsewhere.
        if (($datetime !== '') && (strpos($html, '^^__comment_datetime^^') !== false)) {
            if (!empty($comments_thread_flags['is_email'])) {
                $html = pg_comments_email_fill_datetime($html, $datetime);
            } else {
                $html = pg_sw_apply_tokens($html, array('__comment_datetime' => $datetime), array(), array(),
                    array('__comment_datetime' => array('label' => '', 'sql' => '', 'type' => 'date and time', 'join' => array())));
            }
        }

        $html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($html, pg_comments_widget_escape($values + $thread_values)));
        $html = pg_sw_hide_empty_fields($html);
        $html = pg_sw_uniquify_row_ids($html, $widget_id, $id);

        if (!$recent) {
            $html = pg_comments_row_root_id($html, $id, isset($row['depth']) ? (int) $row['depth'] : 0);
            $html = pg_comments_row_anchor_links($html, $widget_id, $id);
        }

        $edit_url = (isset($values['__comment_edit_url']) && is_scalar($values['__comment_edit_url'])) ? (string) $values['__comment_edit_url'] : '';

        if ($edit_chips && ($edit_url !== '') && !$edit_token) {
            $html = pg_sw_add_edit_chip($html, $edit_url, lang('Edit the comment'), 'page');
        }

        $rows_html .= $html;
    }

    // The Messages node prints, and uses up, the add_comment form state here.
    $static = trim(_render_tree_node($static_tree, 0, 0));
    $static = pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, pg_comments_widget_escape($thread_values) + $slots));

    if ($fallback && ($rows_html !== '')) {
        $rows_html = '<div class="pg-comments-list' . ($recent ? ' list-group list-group-flush' : '') . '">' . $rows_html . '</div>';
    }

    // The page links, when the design placed no comment_pagination section
    // the visitor gets: right after the comments, outside the list or table
    // that holds them.
    $pagination = is_scalar($sections['comment_pagination']) ? (string) $sections['comment_pagination'] : '';
    $after_list = '';

    if (($pagination !== '') && ($rows_html !== '') && (strpos($static, 'pg-sw-pagination') === false)) {
        if (strpos($static, '<!--pg-loop-after-->') !== false) {
            $after_list = $pagination;
        } else {
            $rows_html .= $pagination;
        }
    }

    $static = str_replace(array('<!--pg-loop-before-->', '<!--pg-loop-after-->'), array('', $after_list), $static);

    // The rows go where the loop_area was. A layout without one gets them in
    // front of the rest; when the design's list was left out (a box the
    // visitor does not get, a form that is not drawn) they go with it, as in
    // every other widget.
    if (strpos($static, '<!--pg-loop-slot-->') !== false) {
        $html = str_replace('<!--pg-loop-slot-->', $rows_html, $static);
    } elseif (!$designed_loop) {
        $html = $rows_html . $static;
    } else {
        $html = $static;
    }

    // After the rows are in: a box the design marked pg-hide-if-empty around
    // the list goes when there are none.
    return ($recent ? '' : '<a id="software_comments"></a>') . pg_sw_hide_empty_fields($html);
}

// ============================================================================
// The comment e-mails
// ============================================================================

/**
 * Whether a comments widget on a comment e-mail page may draw this comment
 * for this e-mail. Pure.
 *
 * Only the submitter and watcher e-mails (includes/fn/mail.php) name a
 * comment, and only to an e-mail page that places a comments widget. The
 * widget draws it when all of these hold, else it prints nothing and the
 * e-mail keeps its fixed comment markup:
 * - the e-mail page is a page id (_pg_comments_widget_page_key()) and the
 *   comment has an id;
 * - the comment is published;
 * - it belongs to a page's own thread (item type '') or to a form record's
 *   ('submitted_form'): product, product group and event threads keep the
 *   fixed markup, as the widget does not draw per-item threads;
 * - $page is the row of the page the comment was posted on;
 * - the e-mail page is that page's watcher e-mail page, or, for a record's
 *   comment, its submitter e-mail page. A comment named to any other page is
 *   not drawn there.
 *
 * @param mixed $comment       the comment's row (pg_comments_email_rows())
 * @param mixed $page          its thread page's row
 * @param mixed $email_page_id the e-mail page being drawn
 * @return bool
 */
function pg_comments_email_decision($comment, $page, $email_page_id)
{
    $email_page = _pg_comments_widget_page_key($email_page_id);

    if (($email_page <= 0) || !is_array($comment) || !is_array($page)) {
        return false;
    }

    if ((_pg_comments_row_int($comment, 'id') <= 0) || (_pg_comments_row_text($comment, 'published') !== '1')) {
        return false;
    }

    $type = _pg_comments_row_text($comment, 'item_type');

    if (!in_array($type, array('', 'submitted_form'), true)) {
        return false;
    }

    $page_id = _pg_comments_row_int($page, 'page_id');

    if (($page_id <= 0) || ($page_id !== _pg_comments_row_int($comment, 'page_id'))) {
        return false;
    }

    $settings = pg_comments_settings($page);

    return ($settings['watcher_email_page_id'] === $email_page)
        || (($type === 'submitted_form') && ($settings['submitter_email_page_id'] === $email_page));
}

/**
 * The one comment of a comment e-mail as the flat layout draws it: depth 0,
 * its own top-level comment, no replies drawn, quoting the comment it
 * answers when that one is given ($parent: a row with another id). The
 * featured mark counts unless the widget's featured mode is 'none'. The
 * shape of pg_comments_widget_order()'s entries. Pure.
 *
 * @param mixed $row           the comment's row
 * @param mixed $parent        the row of the comment it answers, or null
 * @param mixed $featured_mode pg_comments_widget_options()'s 'featured'
 * @return array
 */
function pg_comments_email_entry($row, $parent, $featured_mode)
{
    $row = is_array($row) ? $row : array();
    $id = _pg_comments_row_int($row, 'id');
    $parent_id = is_array($parent) ? _pg_comments_row_int($parent, 'id') : 0;

    if ($parent_id === $id) {
        $parent_id = 0;
    }

    return array(
        'row' => $row,
        'id' => $id,
        'parent_id' => $parent_id,
        'depth' => 0,
        'root_id' => $id,
        'replies' => 0,
        'featured' => ($featured_mode !== 'none') && _pg_comments_setting_is($row, 'featured', 1),
        'quoted' => ($parent_id > 0),
    );
}

/**
 * The tokens of a comment e-mail's comment about its place in the thread,
 * raw (the token escape does h()): pg_comments_widget_link_values()'s keys,
 * every link absolute. Pure but for the translation.
 *
 * - __comment_url: $base . pg_comment_view_query($query, id) . '#c-<id>',
 *   the address of the fixed e-mail's "View or Reply" link: every comment
 *   shows, and a comments widget opens the page of the thread that holds it.
 * - __comment_reply_url: $base . $query with reply_to=<id>, ending at
 *   #software_add_comment, when the comment may be answered there
 *   ($reply_ok), else ''.
 * - __comment_parent_id, __comment_parent_name (Anonymous when it has no
 *   name), __comment_parent_url (its permalink, built the same way): '' for
 *   a comment that answers none given ($parent null).
 * - __comment_quote: the start of the answered comment's words when the
 *   entry quotes it, else ''.
 * - __comment_depth, __comment_reply_count: as text.
 *
 * @param mixed $base     the thread page's absolute address
 *                        (URL_SCHEME . HOSTNAME_SETTING . PATH . its name)
 * @param mixed $query    what the page needs to show the item
 *                        (get_query_string_for_page_url(): '' or '?r=...')
 * @param mixed $entry    pg_comments_email_entry()
 * @param mixed $parent   the answered comment's row, or null
 * @param bool  $reply_ok pg_comments_email_reply_ok()
 * @return array
 */
function pg_comments_email_link_values($base, $query, $entry, $parent, $reply_ok)
{
    $entry = is_array($entry) ? $entry : array();
    $base = (is_scalar($base) && !is_bool($base)) ? (string) $base : '';
    $query = (is_scalar($query) && !is_bool($query)) ? (string) $query : '';
    $id = _pg_comments_row_int($entry, 'id');
    $parent_id = is_array($parent) ? _pg_comments_row_int($parent, 'id') : 0;
    $parent_id = ($parent_id === $id) ? 0 : $parent_id;
    $parent_name = ($parent_id > 0) ? _pg_comments_row_text($parent, 'name') : '';
    $permalink = function ($comment_id) use ($base, $query) {
        return $base . pg_comment_view_query($query, $comment_id) . '#c-' . $comment_id;
    };

    $comment_links = array(
        '__comment_url' => ($id > 0) ? $permalink($id) : '',
        '__comment_reply_url' => ($reply_ok && ($id > 0))
            ? $base . $query . ((mb_strpos($query, '?') === false) ? '?' : '&') . 'reply_to=' . $id . '#software_add_comment'
            : '',
        '__comment_parent_id' => ($parent_id > 0) ? (string) $parent_id : '',
        '__comment_parent_name' => ($parent_id > 0) ? (($parent_name !== '') ? $parent_name : lang('Anonymous')) : '',
        '__comment_parent_url' => ($parent_id > 0) ? $permalink($parent_id) : '',
        '__comment_quote' => (!empty($entry['quoted']) && ($parent_id > 0)) ? pg_comments_quote_excerpt(_pg_comments_row_text($parent, 'message')) : '',
        '__comment_depth' => (string) _pg_comments_row_int($entry, 'depth'),
        '__comment_reply_count' => (string) _pg_comments_row_int($entry, 'replies'),
    );

    return $comment_links;
}

/**
 * Whether a comment e-mail offers a reply link: the thread page takes
 * comments and new ones on this thread, replies are stored (11.0.31,
 * pg_comment_replies_ready()), and the thread page itself draws the
 * comments widget, whose form takes ?reply_to= (the classic block ignores
 * it, so a reply would post as a comment of its own there). The page checks
 * ?reply_to= again when the visitor arrives. Pure.
 *
 * @param mixed $enabled       the thread page's comments are on
 * @param mixed $allow_new     the thread takes new comments
 * @param mixed $replies_ready
 * @param mixed $widget_thread the thread page draws it in a comments widget
 * @return bool
 */
function pg_comments_email_reply_ok($enabled, $allow_new, $replies_ready, $widget_thread)
{
    return (bool) $enabled && (bool) $allow_new && (bool) $replies_ready && (bool) $widget_thread;
}

/**
 * The thread tokens of a comment e-mail, raw: one comment, the thread
 * page's label, intro and closed message; the visitor's (the featured and
 * sign-in links, the name they post as, the comment they answer, the
 * watcher count) are ''. Pure but for the translation.
 *
 * @param mixed $settings pg_comments_settings() of the thread page
 * @return array
 */
function pg_comments_email_thread_values($settings)
{
    $settings = is_array($settings) ? $settings : array();
    $text = function ($key) use ($settings) {
        return (isset($settings[$key]) && is_scalar($settings[$key]) && !is_bool($settings[$key])) ? (string) $settings[$key] : '';
    };
    $label = $text('label');

    return array(
        '__comments_title' => '1 ' . get_comment_label(array('label' => $label, 'number' => 1)),
        '__comments_count' => '1',
        '__comments_label' => get_comment_label(array('label' => $label)),
        '__comments_label_plural' => get_comment_label(array('label' => $label, 'number' => 2)),
        '__comments_intro' => $text('message'),
        '__comments_closed_message' => ($text('closed_message') !== '') ? $text('closed_message') : lang("We're sorry. New comments are no longer being accepted."),
        '__comments_show_all_url' => '',
        '__comments_show_featured_url' => '',
        '__login_url' => '',
        '__comment_author_name' => '',
        '__reply_to_name' => '',
        '__watcher_count_text' => '',
    );
}

/**
 * A comment's stars as characters (^^__comment_stars_text^^): the first
 * $rating filled star characters, then empty ones to five; an e-mail
 * draws them in place of the drawings, which mail clients leave out. ''
 * for a rating outside 1-5.
 * Pure.
 *
 * @param mixed $rating
 * @return string
 */
function pg_comments_stars_text($rating)
{
    $n = _pg_comments_rating_value($rating);

    if ($n === 0) {
        return '';
    }

    return str_repeat("\u{2605}", $n) . str_repeat("\u{2606}", 5 - $n);
}

/**
 * The values a comment e-mail's comment takes in place of a live page's,
 * raw. Pure but for the translation.
 *
 * - __comment_date, __comment_seller_reply_date: the absolute dates the
 *   sender gives ($dates 'date', 'seller_reply_date'), whatever the page's
 *   date setting: "a moment ago" is wrong once the e-mail is read later.
 * - __comment_file_url: the file on the site's address ($site_base, raw:
 *   URL_SCHEME . HOSTNAME_SETTING . PATH) when the comment has one, else
 *   ''.
 * - __comment_staff_name, __comment_edit_url, __comment_status: '', for
 *   nobody is staff in an e-mail and only a published comment is drawn.
 *
 * @param mixed $row       the comment's row
 * @param mixed $flags     pg_comments_widget_row_flags()
 * @param mixed $site_base the site's absolute address
 * @param mixed $dates     'date', 'seller_reply_date'
 * @return array
 */
function pg_comments_email_row_values($row, $flags, $site_base, $dates)
{
    $flags = is_array($flags) ? $flags : array();
    $dates = is_array($dates) ? $dates : array();
    $site_base = (is_scalar($site_base) && !is_bool($site_base)) ? (string) $site_base : '';
    $date = function ($key) use ($dates) {
        return (isset($dates[$key]) && is_scalar($dates[$key]) && !is_bool($dates[$key])) ? (string) $dates[$key] : '';
    };
    $file_name = _pg_comments_row_text($row, 'file_name');

    return array(
        '__comment_date' => $date('date'),
        '__comment_seller_reply_date' => $date('seller_reply_date'),
        '__comment_file_url' => (!empty($flags['comment_has_file']) && ($file_name !== '') && ($site_base !== '')) ? $site_base . encode_url_path($file_name) : '',
        '__comment_staff_name' => '',
        '__comment_edit_url' => '',
        '__comment_status' => '',
    );
}

/**
 * Fill every ^^__comment_datetime^^ of a comment e-mail's row markup with
 * the comment's time ($datetime, 'Y-m-d H:i:s' in the site's zone), escaped
 * as the token escape writes a value. Pure but for the translation of the
 * default format.
 *
 * The design's %%format%% is kept, written with date() in the site's time
 * zone; no format and the 'relative' one take the default display shape
 * (pg_sw_default_date_format()). The record date rules
 * (prepare_form_data_for_output()) are not used: they would freeze a
 * relative time at sending ("a moment ago" once the e-mail is read later)
 * and, for a signed-in sender with a zone of their own, write the tooltip
 * and any zone format (T, e) in the sender's zone, not the recipients'
 * site's. A $datetime that does not read as a time leaves the markup alone
 * for the token sweep.
 *
 * @param mixed $html
 * @param mixed $datetime
 * @return mixed the markup, or $html when it is not a string
 */
function pg_comments_email_fill_datetime($html, $datetime)
{
    if (!is_string($html) || !is_string($datetime) || ($datetime === '')) {
        return $html;
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return $html;
    }

    return preg_replace_callback('/\^\^__comment_datetime\^\^(?:%%(.*?)%%)?/', function ($match) use ($timestamp) {
        // A format typed into the rich-text editor may carry HTML entities,
        // which date() would read as format characters.
        $format = isset($match[1]) ? unhtmlspecialchars($match[1]) : '';

        if (($format === '') || ($format === 'relative')) {
            $format = pg_sw_default_date_format('date and time');
        }

        return h(date($format, $timestamp));
    }, $html);
}

/**
 * Whether the e-mail branch leaves a node out of the widget's tree: a
 * semantic form, input, select, textarea or button, a button component
 * drawn as a button or an input, a node shown only while the form answers
 * a comment (is_replying), and the control that stops answering
 * (comment_reply_cancel). An e-mail holds no form and no control that needs
 * the page's script; links stay. Pure. The canvas twin is
 * _sdCommentsEmailDrops() in assets/js/style_designer.js.
 *
 * @param mixed $node
 * @return bool
 */
function pg_comments_email_drops($node)
{
    if (!is_array($node)) {
        return false;
    }

    $type = (isset($node['type']) && is_string($node['type'])) ? $node['type'] : '';
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    $bindings = (isset($props['_bindings']) && is_array($props['_bindings'])) ? $props['_bindings'] : array();

    if ($type === 'semantic') {
        $tag = (isset($props['tag']) && is_string($props['tag'])) ? strtolower($props['tag']) : '';

        if (in_array($tag, array('form', 'input', 'select', 'textarea', 'button'), true)) {
            return true;
        }
    }

    if (($type === 'component') && isset($props['componentType']) && ($props['componentType'] === 'btn')) {
        $element = (isset($props['btnElement']) && is_string($props['btnElement'])) ? $props['btnElement'] : 'a';

        if (($element === 'button') || ($element === 'input')) {
            return true;
        }
    }

    if (isset($bindings['eo_visible_if']) && ($bindings['eo_visible_if'] === 'is_replying')) {
        return true;
    }

    return isset($bindings['action']) && ($bindings['action'] === 'comment_reply_cancel');
}

/**
 * Whether a node is a control _pg_member_drop_controls() reads: a semantic
 * input, select, textarea or button, or a button component.
 *
 * @param mixed $node
 * @return bool
 */
function _pg_comments_email_is_control($node)
{
    if (!is_array($node) || !isset($node['type'])) {
        return false;
    }

    if ($node['type'] === 'semantic') {
        $tag = (isset($node['props']['tag']) && is_string($node['props']['tag'])) ? strtolower($node['props']['tag']) : '';
        return in_array($tag, array('input', 'select', 'textarea', 'button'), true);
    }

    return ($node['type'] === 'component') && isset($node['props']['componentType']) && ($node['props']['componentType'] === 'btn');
}

/**
 * Take out of a comments widget's tree, before an e-mail draws it, every
 * node pg_comments_email_drops() names. Pure.
 *
 * Forms, the "Replying to" box and the cancel control go with what they
 * hold; fields and buttons go with their <label for> and the wrapper they
 * leave empty (_pg_member_drop_controls()); a wrapper a form leaves empty
 * goes too. The flags already hide most of them in an e-mail: this also
 * removes what a flag keeps for the page's script (a "Replying to" box with
 * the hidden attribute, which mail clients ignore) and any control a
 * design placed outside a form.
 *
 * @param mixed $tree
 * @return void
 */
function pg_comments_email_strip(&$tree)
{
    if (!is_array($tree)) {
        return;
    }

    _pg_comments_email_strip_walk($tree, 0);
    _pg_member_drop_controls($tree, function ($tag, $attrs, $node) {
        return pg_comments_email_drops($node);
    });
}

/**
 * pg_comments_email_strip()'s walk over one node's children: the nodes it
 * drops that are not controls (forms, replying boxes, a cancel link) leave,
 * and a semantic wrapper they leave empty is marked as
 * _pg_member_drop_controls() marks one, so it leaves too.
 *
 * @param array $node
 * @param int   $depth
 * @return void
 */
function _pg_comments_email_strip_walk(&$node, $depth)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children']) || ($depth > 200)) {
        return;
    }

    $kept = array();

    foreach ($node['children'] as $child) {
        if (pg_comments_email_drops($child) && !_pg_comments_email_is_control($child)) {
            continue;
        }

        _pg_comments_email_strip_walk($child, $depth + 1);
        $kept[] = $child;
    }

    if (!$kept && isset($node['type']) && ($node['type'] === 'semantic')) {
        $node['props']['_pg_member_empty'] = true;
    }

    $node['children'] = array_values(array_filter($kept, function ($child) {
        return !(is_array($child) && !empty($child['props']['_pg_member_empty']));
    }));
}

/**
 * What pg_comments_widget_draw() takes to draw one comment in a comment
 * e-mail, from the comment, the published comment it answers (or null), its
 * thread page's row and the widget's options. Pure but for the translation.
 *
 * Nobody reads it: no staff, no pending comment, no own comment, no form,
 * no watch or staff control, no server section; the reply link only when
 * the comment may be answered ($env 'reply_ok'). The facts the renderer
 * reads go in $env: widget_id, allow_new (pg_comments_allow_new()),
 * reply_ok (pg_comments_email_reply_ok()), base and query
 * (pg_comments_email_link_values()), site (the site's absolute address),
 * dates (pg_comments_email_row_values()), badge_label (the site's).
 * ^^__comment_datetime^^ is always given: the comment's time, which the
 * draw writes with pg_comments_email_fill_datetime() (flag is_email).
 *
 * @param mixed $comment the comment's row
 * @param mixed $parent  the answered comment's row, or null
 * @param mixed $page    the thread page's row
 * @param mixed $cfg     the widget's system_region_config
 * @param mixed $env
 * @return array
 */
function pg_comments_email_draw_data($comment, $parent, $page, $cfg, $env)
{
    $comment = is_array($comment) ? $comment : array();
    $parent = is_array($parent) ? $parent : null;
    $env = is_array($env) ? $env : array();
    $settings = pg_comments_settings($page);
    $options = pg_comments_widget_options($cfg);
    $allow_new = !empty($env['allow_new']);
    $reply_ok = !empty($env['reply_ok']);
    $badge_label = (isset($env['badge_label']) && is_scalar($env['badge_label'])) ? (string) $env['badge_label'] : '';
    $entry = pg_comments_email_entry($comment, $parent, $options['featured']);

    $thread_flags = pg_comments_widget_thread_flags(array(
        'email' => true,
        'allow_new' => $allow_new,
        'count' => 1,
        'featured_mode' => $options['featured'],
        'rating_on' => $settings['rating'],
        'files_on' => $settings['attachments'],
        'watching_on' => ($settings['watcher_email_page_id'] > 0),
    ));
    $row_flags = pg_comments_widget_row_flags($comment, $entry['featured'],
        array('is_staff' => false, 'sees_pending' => false, 'own_ids' => array(), 'rating_on' => $settings['rating'], 'badge_label' => $badge_label),
        array('depth' => 0, 'quoted' => $entry['quoted'], 'replies' => 0));
    $created = (int) _pg_comments_row_text($comment, 'created_timestamp');
    $label_lower = mb_strtolower(get_comment_label(array('label' => $settings['label'])));

    $values = array_merge(
        pg_comments_widget_row_values($comment, $row_flags, array('is_staff' => false, 'sees_pending' => false, 'show_date' => false, 'label_lower' => $label_lower, 'badge_label' => $badge_label)),
        pg_comments_email_row_values($comment, $row_flags, isset($env['site']) ? $env['site'] : '', isset($env['dates']) ? $env['dates'] : array()),
        pg_comments_email_link_values(isset($env['base']) ? $env['base'] : '', isset($env['query']) ? $env['query'] : '', $entry, $entry['quoted'] ? $parent : null, $reply_ok));

    return array(
        'widget_id' => isset($env['widget_id']) ? (int) $env['widget_id'] : 0,
        'thread_flags' => $thread_flags,
        'thread_values' => pg_comments_email_thread_values($settings),
        'rows' => array(array(
            'id' => $entry['id'],
            'flags' => $row_flags,
            'values' => $values,
            'datetime' => ($created > 0) ? date('Y-m-d H:i:s', $created) : '',
            'depth' => 0,
            'reply_ok' => $reply_ok,
        )),
        'form' => array('can_comment' => false),
        'controls' => array(
            'can_reply' => $reply_ok,
            'replying' => false,
            'cancel_url' => '',
            'share_copied' => '',
            'share_prompt' => '',
            'can_watch' => false,
            'is_watcher' => false,
            'is_staff' => false,
            'allow_new' => $allow_new,
        ),
        'sections' => array(),
        'slots' => array(),
        'edit_chips' => false,
    );
}

/**
 * Whether a comment e-mail's draw holds the comment: its row's anchor
 * (id="c-<id>", pg_comments_row_root_id()) and a link to its permalink
 * ($comment_url, as the token escape writes it into an href). A design
 * whose list a flag leaves out of an e-mail, or whose comment carries no
 * link to itself (the Share link, or a link bound to ^^__comment_url^^),
 * does not count: the renderer then prints nothing and the e-mail keeps
 * its fixed markup, with the comment and its "View or Reply" link. Pure.
 *
 * @param mixed $html
 * @param mixed $comment_id
 * @param mixed $comment_url the raw __comment_url
 * @return bool
 */
function pg_comments_email_drew($html, $comment_id, $comment_url)
{
    $id = _pg_comments_widget_page_key($comment_id);

    if (!is_string($html) || ($html === '') || ($id <= 0) || !is_string($comment_url) || ($comment_url === '')) {
        return false;
    }

    return (strpos($html, ' id="c-' . $id . '"') !== false)
        && (strpos($html, 'href="' . h($comment_url) . '"') !== false);
}

/**
 * Whether get_page_content() adds a comment e-mail's fixed comment markup
 * ($extra_system_content) to the e-mail page's System region: always,
 * unless the page is drawn for an e-mail and a comments widget on it drew
 * that comment in this pass ($pages, the record the widget pass handed
 * back, pg_comments_widget_pages()). Only the e-mail branch
 * (_pg_comments_widget_email()) claims an e-mail page, and only after it
 * drew the comment with its link, so an e-mail page without a comments
 * widget keeps the markup as before. Pure.
 *
 * @param mixed $email   the page is drawn for an e-mail
 * @param mixed $page_id the page drawn
 * @param mixed $pages
 * @return bool
 */
function pg_comments_email_keeps_fixed_markup($email, $page_id, $pages)
{
    return !($email && is_array($pages) && pg_comments_widget_drawn($page_id, $pages));
}

// ============================================================================
// The renderer
// ============================================================================

/**
 * What a page shows of comments (pg_comments_page_census_facts(): widgets,
 * blocks, nested_record, top_record), from the page's stored tree
 * (pg_page_tree_json(), with its design's fallback) and the components it
 * reaches (pg_comments_component_census(): one query per level of nesting,
 * at most 8 levels and 500 components, none when the page places no
 * component); cached per page for the request, at most 256 pages. No page
 * (an id that is not above 0) shows nothing.
 *
 * @param mixed $page_id
 * @return array
 */
function pg_comments_page_facts($page_id)
{
    static $cache = array();
    $page_id = (is_scalar($page_id) && !is_bool($page_id)) ? (int) $page_id : 0;

    if ($page_id <= 0) {
        return array('widgets' => 0, 'blocks' => 0, 'nested_record' => false, 'top_record' => false);
    }

    if (isset($cache[$page_id])) {
        return $cache[$page_id];
    }

    if (count($cache) >= 256) {
        $cache = array();
    }

    $census = pg_comments_tree_census(json_decode((string) pg_page_tree_json($page_id), true));
    $components = $census['refs'] ? pg_comments_component_census(array_keys($census['refs'])) : array();

    return $cache[$page_id] = pg_comments_page_census_facts($census, $components);
}

/**
 * Whether a page places a form_item_view widget below its top level
 * (pg_comments_page_facts()'s 'nested_record').
 *
 * @param mixed $page_id
 * @return bool
 */
function pg_comments_page_nested_record($page_id)
{
    $facts = pg_comments_page_facts($page_id);

    return $facts['nested_record'];
}

/**
 * Whether a page places a comments widget, on the page itself or in a
 * component it reaches (pg_comments_page_facts()'s 'widgets'). mail.php
 * asks it of a comment e-mail page before naming the comment to it; the
 * e-mail branch asks it of the thread page before offering a reply link.
 *
 * @param mixed $page_id
 * @return bool
 */
function pg_comments_page_has_widget($page_id)
{
    $facts = pg_comments_page_facts($page_id);

    return ($facts['widgets'] > 0);
}

/**
 * Whether another page names this one as its submitter or watcher comment
 * e-mail page: one count over the page table.
 *
 * @param mixed $page_id
 * @return bool
 */
function pg_comments_is_email_page($page_id)
{
    $id = _pg_comments_widget_page_key($page_id);

    if ($id <= 0) {
        return false;
    }

    return ((int) db_value(
        "SELECT COUNT(*)
        FROM page
        WHERE
            (comments_submitter_email_page_id = '" . $id . "')
            OR (comments_watcher_email_page_id = '" . $id . "')") > 0);
}

/**
 * The row of the page whose thread a comments widget draws: its name,
 * folder, type and comment settings, or null when there is no such page.
 *
 * @param int $page_id
 * @return array|null
 */
function _pg_comments_widget_page_row($page_id)
{
    $page = db_item(
        "SELECT
            page_id,
            page_name,
            page_folder,
            page_type,
            comments,
            comments_label,
            comments_message,
            comments_rating,
            comments_allow_new_comments,
            comments_disallow_new_comment_message,
            comments_automatic_publish,
            comments_allow_user_to_select_name,
            comments_require_login_to_comment,
            comments_allow_file_attachments,
            comments_show_submitted_date_and_time,
            comments_administrator_email_to_email_address,
            comments_administrator_email_conditional_administrators,
            comments_submitter_email_page_id,
            comments_watcher_email_page_id,
            comments_watchers_managed_by_submitter
        FROM page
        WHERE page_id = '" . (int) $page_id . "'
        LIMIT 1");

    return is_array($page) ? $page : null;
}

/**
 * The comment a comment e-mail names and the comment it answers, as
 * pg_comments_rows() reads a thread's rows, with its thread (page_id,
 * item_type, item_id) and parent_id (0 before 11.0.31): 'comment' and
 * 'parent', each a row or null. The parent is read only when the comment
 * answers another one. Whether either may be shown is the caller's to
 * decide.
 *
 * @param mixed $comment_id
 * @return array 'comment', 'parent'
 */
function pg_comments_email_rows($comment_id)
{
    $out = array('comment' => null, 'parent' => null);
    $id = _pg_comments_widget_page_key($comment_id);

    if ($id <= 0) {
        return $out;
    }

    $out['comment'] = _pg_comments_email_row($id);
    $parent_id = ($out['comment'] !== null) ? _pg_comments_row_int($out['comment'], 'parent_id') : 0;

    if (($parent_id > 0) && ($parent_id !== $id)) {
        $out['parent'] = _pg_comments_email_row($parent_id);
    }

    return $out;
}

/**
 * One comment's row for pg_comments_email_rows(), or null.
 *
 * @param int $id
 * @return array|null
 */
function _pg_comments_email_row($id)
{
    $row = db_item(
        "SELECT
            comments.id,
            comments.page_id,
            comments.item_type,
            comments.item_id,
            comments.name,
            comments.message,
            files.name as file_name,
            files.size as file_size,
            comments.published,
            comments.publish_date_and_time,
            comments.publish_cancel,
            comments.featured,
            comments.rating,
            " . (pg_reviews_ready() ? "comments.verified_order_id," : "0 AS verified_order_id,") . "
            " . (pg_review_replies_ready() ? "comments.seller_reply, comments.seller_reply_at," : "'' AS seller_reply, 0 AS seller_reply_at,") . "
            user.user_username as username,
            user.user_role,
            user.user_id,
            user.user_badge,
            user.user_badge_label,
            comments.created_timestamp,
            " . (pg_comment_replies_ready() ? "comments.parent_id" : "0 AS parent_id") . "
        FROM comments
        LEFT JOIN files ON comments.file_id = files.id
        LEFT JOIN user ON comments.created_user_id = user.user_id
        WHERE comments.id = '" . (int) $id . "'
        LIMIT 1");

    return (is_array($row) && !empty($row['id'])) ? $row : null;
}

/**
 * The comments widget in a comment e-mail: the one comment the sender names
 * (pg_sw_render_context()'s 'comment_id', which only mail.php's submitter
 * and watcher e-mails pass, to an e-mail page that places the widget),
 * drawn in the widget's layout for nobody.
 *
 * Nothing is drawn, and nothing claimed, for a comment the e-mail page may
 * not draw (pg_comments_email_decision(): unpublished, a product, product
 * group or event thread, an e-mail page that is not the thread page's), nor
 * when the draw leaves the comment or its link out (pg_comments_email_drew():
 * a list shown only to a visitor, a design without a link to the comment):
 * the e-mail then keeps its fixed comment markup. Once drawn, the e-mail
 * page is claimed, so get_page_content() leaves that markup out and a
 * second comments widget on the page prints nothing.
 *
 * The request is the poster's, a moderator's, the job's or an API app's, so
 * the branch reads no session, no form state, no CAPTCHA, no token, no
 * address and no viewer: links are built from the thread page's row on the
 * configured host, dates are absolute, every form and control is stripped
 * (pg_comments_email_strip()).
 *
 * @param array $tree      the widget's decoded tree
 * @param int   $widget_id
 * @param array $cfg       its system_region_config
 * @param array $ctx       pg_sw_render_context()
 * @return string
 */
function _pg_comments_widget_email($tree, $widget_id, $cfg, $ctx)
{
    $email_page = _pg_comments_widget_page_key(isset($ctx['page_id']) ? $ctx['page_id'] : 0);
    $comment_id = _pg_comments_widget_page_key(isset($ctx['comment_id']) ? $ctx['comment_id'] : 0);

    if (($email_page <= 0) || ($comment_id <= 0)) {
        return '';
    }

    $read = pg_comments_email_rows($comment_id);
    $comment = $read['comment'];

    if ($comment === null) {
        return '';
    }

    $page = _pg_comments_widget_page_row(_pg_comments_row_int($comment, 'page_id'));

    if (!pg_comments_email_decision($comment, $page, $email_page)) {
        return '';
    }

    $settings = pg_comments_settings($page);
    $thread_page = (int) $page['page_id'];
    $type = _pg_comments_row_text($comment, 'item_type');
    $item = _pg_comments_row_int($comment, 'item_id');

    // The comment it answers is quoted only when everybody may read it:
    // published and in the same thread.
    $parent = null;

    if ($read['parent'] !== null) {
        $check = pg_comment_parent_ok(_pg_comments_row_int($read['parent'], 'id'), $read['parent'], $thread_page, $type, $item, array());

        if ($check['accept'] && ($check['parent_id'] > 0)) {
            $parent = $read['parent'];
        }
    }

    $allow_new = pg_comments_allow_new($thread_page, $type, $item, $settings['allow_new']);

    // A reply link only where the thread page's own comments widget takes
    // ?reply_to=; a page that keeps its classic block ignores it.
    $reply_ok = pg_comments_email_reply_ok($settings['enabled'], $allow_new, pg_comment_replies_ready(),
        pg_comments_page_has_widget($thread_page) && !pg_comments_widget_classic_thread($page['page_type'], ($type === 'submitted_form')));

    // Absolute dates in the site's time zone, as the fixed markup writes
    // them: the e-mail is read later, by people in other zones. Plain text:
    // the token escape would print the <time> tag around them as words.
    $created = (int) _pg_comments_row_text($comment, 'created_timestamp');
    $reply_at = (int) _pg_comments_row_text($comment, 'seller_reply_at');
    $absolute = function ($timestamp) {
        return get_absolute_time(array('timestamp' => $timestamp, 'size' => 'long', 'timezone_type' => 'site', 'format' => 'plain_text'));
    };

    // Mailed links use the configured hostname, never the Host header of
    // the request that sends the e-mail.
    $site = URL_SCHEME . HOSTNAME_SETTING . PATH;

    $data = pg_comments_email_draw_data($comment, $parent, $page, $cfg, array(
        'widget_id' => (int) $widget_id,
        'allow_new' => $allow_new,
        'reply_ok' => $reply_ok,
        'base' => $site . encode_url_path((string) $page['page_name']),
        'query' => get_query_string_for_page_url(($type === 'submitted_form') ? 'form item view' : (string) $page['page_type'], $item, $type),
        'site' => $site,
        'dates' => array(
            'date' => ($created > 0) ? $absolute($created) : '',
            'seller_reply_date' => ($reply_at > 0) ? $absolute($reply_at) : '',
        ),
        'badge_label' => defined('BADGE_LABEL') ? (string) BADGE_LABEL : '',
    ));

    // As on the page, a node bound to a token only a list of recent
    // comments fills leaves with what it holds.
    pg_comments_email_strip($tree);
    pg_comments_drop_mode_bindings($tree, 'thread');
    $html = pg_comments_widget_draw($tree, $data);

    // Claimed only once the comment and its link are in: the claim is what
    // leaves the fixed markup out of the e-mail.
    if (!pg_comments_email_drew($html, $comment_id, $data['rows'][0]['values']['__comment_url'])) {
        return '';
    }

    if (!pg_comments_widget_claim($email_page)) {
        return '';
    }

    return $html;
}

/**
 * The address of the "Sign in" link for a visitor who must sign in to
 * comment, bringing them back to #software_add_comment: the legacy
 * registration entrance page when the site has one, else the designed
 * sign-in page of the page's design (pg_sw_sign_in_url()), else the
 * registration_entrance.php screen.
 *
 * @param int $page_id
 * @return string
 */
function pg_comments_widget_login_url($page_id)
{
    $back = (string) get_request_uri() . '#software_add_comment';
    $entrance = (int) db_value("SELECT page_id FROM page WHERE page_type = 'registration entrance' LIMIT 1");

    if ($entrance > 0) {
        return OUTPUT_PATH . encode_url_path((string) get_page_name($entrance)) . '?send_to=' . urlencode($back);
    }

    $fallback = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to=' . urlencode($back);

    return pg_sw_sign_in_url($back, $fallback, 'registration entrance', true, (int) $page_id);
}

/**
 * One comment's token values, raw (pg_comments_widget_draw() escapes them).
 * A thread and a list of recent comments both draw them;
 * ^^__comment_excerpt^^ is the start of the comment's own words
 * (pg_comments_quote_excerpt()).
 *
 * $env: is_staff, sees_pending, show_date, label_lower, badge_label.
 *
 * @param array $row   the comment row
 * @param array $flags pg_comments_widget_row_flags()
 * @param array $env
 * @return array
 */
function pg_comments_widget_row_values($row, $flags, $env)
{
    $id = _pg_comments_row_int($row, 'id');
    $is_staff = !empty($env['is_staff']);
    $has_file = !empty($flags['comment_has_file']);
    $has_rating = !empty($flags['comment_has_rating']);
    $file_name = _pg_comments_row_text($row, 'file_name');
    $name = _pg_comments_row_text($row, 'name');
    $reply = _pg_comments_row_text($row, 'seller_reply');
    $reply_at = (int) _pg_comments_row_text($row, 'seller_reply_at');
    $created = (int) _pg_comments_row_text($row, 'created_timestamp');
    $when = '';

    if (!empty($flags['comment_is_scheduled'])) {
        // Lower-case "A moment", which stands in the middle of the sentence.
        $when = str_replace(lang('A moment'), lang('a moment'), get_relative_time(array(
            'timestamp' => strtotime(_pg_comments_row_text($row, 'publish_date_and_time')),
            'format' => 'plain_text',
        )));
    }

    $badge = '';

    if (!empty($flags['comment_has_badge'])) {
        $badge = (_pg_comments_row_text($row, 'user_badge_label') !== '') ? _pg_comments_row_text($row, 'user_badge_label') : (string) $env['badge_label'];
    }

    return array(
        '__comment_id' => (string) $id,
        '__comment_anchor' => 'c-' . $id,
        '__comment_name' => ($name !== '') ? $name : lang('Anonymous'),
        '__comment_staff_name' => $is_staff ? _pg_comments_row_text($row, 'username') : '',
        '__comment_message_html' => convert_text_to_html(_pg_comments_row_text($row, 'message')),
        '__comment_excerpt' => pg_comments_quote_excerpt(_pg_comments_row_text($row, 'message')),
        '__comment_date' => !empty($env['show_date']) ? get_relative_time(array('timestamp' => $created, 'format' => 'plain_text')) : '',
        '__comment_rating' => $has_rating ? (string) (int) _pg_comments_row_text($row, 'rating') : '',
        '__comment_rating_label' => $has_rating ? pg_comments_rating_label(_pg_comments_row_text($row, 'rating')) : '',
        // The stars part draws these in an e-mail (pg_sw_parts_mail_stars()).
        '__comment_stars_text' => $has_rating ? pg_comments_stars_text(_pg_comments_row_text($row, 'rating')) : '',
        '__comment_badge' => $badge,
        '__comment_file_name' => $has_file ? $file_name : '',
        '__comment_file_url' => $has_file ? OUTPUT_PATH . encode_url_path($file_name) : '',
        '__comment_file_size' => $has_file ? convert_bytes_to_string(_pg_comments_row_text($row, 'file_size')) : '',
        '__comment_status' => pg_comments_widget_status_text($flags, !empty($env['sees_pending']), (string) $env['label_lower'], $when,
            (bool) _pg_comments_row_text($row, 'publish_cancel')),
        '__comment_seller_reply_html' => (trim($reply) !== '') ? convert_text_to_html($reply) : '',
        '__comment_seller_reply_date' => ($reply_at > 0) ? get_relative_time(array('timestamp' => $reply_at, 'format' => 'plain_text')) : '',
        '__comment_edit_url' => $is_staff ? pg_sw_backend_edit_url('edit_comment.php', $id) : '',
    );
}

/**
 * Render a 'comments' system widget: the drawn page's comment thread and the
 * form that adds a comment to it.
 *
 * The thread is the page's own, or on a page whose top-level form_item_view
 * widget shows a record, that record's (pg_sw_record_comment_context()); the
 * page's comment settings decide everything else, as on the classic block.
 * In a comment e-mail it draws only the comment the e-mail names
 * (_pg_comments_widget_email()). It prints nothing without a page, for a
 * second comments widget on the same page, with comments off, for a record
 * the visitor may not see, and on a page with a record widget below its top
 * level. The page
 * is claimed before those refusals, so the classic block never draws a
 * second copy of the thread, even when the widget prints nothing. A page
 * that keeps its classic block (pg_comments_widget_classic_thread(): a
 * 'form item view' page, a catalog, catalog detail or calendar event view
 * page without a record widget) is left to it, unclaimed: the widget does
 * not draw per-item threads.
 *
 * The comments come in the widget's order and layout, one page of them
 * (pg_comments_widget_paginate(): ?comments_page=, else the page of the
 * comment ?comment= names); the form answers the comment ?reply_to= (or a
 * refused post) names when the visitor may answer it.
 *
 * Thread tokens: ^^__comments_title^^, ^^__comments_count^^,
 * ^^__comments_label^^, ^^__comments_label_plural^^, ^^__comments_intro^^,
 * ^^__comments_closed_message^^, ^^__comments_show_all_url^^,
 * ^^__comments_show_featured_url^^, ^^__login_url^^,
 * ^^__comment_author_name^^, ^^__reply_to_name^^, ^^__watcher_count_text^^.
 * Row tokens: pg_comments_widget_row_values(),
 * pg_comments_widget_link_values() and ^^__comment_datetime^^. Flags:
 * pg_comments_widget_thread_flags() and pg_comments_widget_row_flags(). The
 * form: pg_comments_apply_form_bindings(); the reply, share, watch and staff
 * controls: pg_comments_apply_control_bindings() and
 * pg_comments_apply_row_bindings().
 *
 * @param mixed  $tree_json
 * @param int    $widget_id
 * @param array  $cfg
 * @param string $mode
 * @return string
 */
function _render_system_widget_comments($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) {
        return '';
    }

    if (!is_array($cfg)) {
        $cfg = array();
    }

    $tree = json_decode((string) $tree_json, true);

    if (!is_array($tree)) {
        return '';
    }

    $ctx = pg_sw_render_context();

    // A list of recent comments is drawn from its source pages, never from
    // the drawn page: it claims no thread and reads no form state. It goes
    // first, also in an e-mail, where it draws nothing: the e-mail branch
    // below would draw the named comment in the list's layout and claim the
    // e-mail page, so the page's own comments widget would draw nothing.
    if (pg_sw_widget_kind($cfg) === 'comments_recent') {
        return _render_system_widget_comments_recent($tree, $widget_id, $cfg, $mode);
    }

    // A comment e-mail draws the one comment its sender names, for nobody.
    if (!empty($ctx['email'])) {
        return _pg_comments_widget_email($tree, (int) $widget_id, $cfg, $ctx);
    }

    $page_id = isset($ctx['page_id']) ? (int) $ctx['page_id'] : 0;

    if ($page_id <= 0) {
        return '';
    }

    $page = _pg_comments_widget_page_row($page_id);

    if (!is_array($page)) {
        return '';
    }

    // The editor's Preview draws the page with the comment settings the
    // editor holds, saved or not.
    $page = pg_comments_page_override($page, pg_comments_preview_page());

    // The thread: the record the page's form_item_view widget shows, else
    // the page's own. The read thread of a page that shows no record is
    // every comment on the page, as the classic block reads it.
    $record = function_exists('pg_sw_record_comment_context') ? pg_sw_record_comment_context($page_id) : null;

    // A classic page that shows one item at a time keeps its classic block,
    // which picks the item: the page is not claimed, so the block still
    // draws that item's thread.
    if (pg_comments_widget_classic_thread($page['page_type'], ($record !== null))) {
        return '';
    }

    // Claimed before any other refusal: the widget owns the page's thread
    // even when it then prints nothing (comments off, a record the visitor
    // may not see, a record widget below the top level).
    if (!pg_comments_widget_claim($page_id)) {
        return '';
    }

    $settings = pg_comments_settings($page);

    if (!$settings['enabled']) {
        return '';
    }

    if ($record !== null) {
        if (empty($record['visible']) || !is_array($record['form'])) {
            return '';
        }

        $post_type = 'submitted_form';
        $post_id = (int) $record['form']['id'];
        $read_thread = array('item_type' => 'submitted_form', 'item_id' => $post_id);
    } else {
        if (pg_comments_page_nested_record($page_id)) {
            return '';
        }

        $post_type = '';
        $post_id = 0;
        $read_thread = null;
    }

    // Who reads it. The search index stores what a visitor who is not signed
    // in reads; a background render (the editor's Preview, the SEO pass)
    // draws the same and leaves the session of the user whose request runs
    // it alone: no form state read or used up, no CAPTCHA issued, no token.
    $search = defined('UPDATE_SEARCH_INDEX') && (UPDATE_SEARCH_INDEX === true);
    $background = function_exists('pg_seo_rendering') && pg_seo_rendering();
    $audience = pg_comments_widget_audience($search, $background, check_edit_access($page['page_folder']) == true, _pg_acct_signed_in());
    $record_form = ($record !== null)
        ? array('form_editor_user_id' => $record['form']['form_editor_user_id'], 'form_editor_username' => $record['form']['form_editor_username'])
        : null;
    $viewer = pg_comments_viewer($audience['staff'], $audience['session'] ? $record_form : null, !$audience['session']);
    $staff = $is_staff = $audience['staff'];
    $signed_in = $audience['signed_in'];
    $user_id = ($audience['session'] && defined('USER_ID') && is_scalar(USER_ID)) ? (int) USER_ID : 0;
    $role = ($audience['session'] && defined('USER_ROLE')) ? USER_ROLE : '';
    $email = ($audience['session'] && defined('USER_EMAIL_ADDRESS')) ? (string) USER_EMAIL_ADDRESS : '';
    $form_editor = !empty($viewer['form_editor']) && $audience['session'];

    // Which comments, and which page of them. A page asked for by number
    // (?comments_page=) wins; else the page that holds the comment the
    // address names (?comment=, as the e-mails and the permalinks write it),
    // which also leaves the featured filter; else the featured view the
    // classic block reads from ?comments. The search index is never paged.
    $target = max(0, pg_comment_parent_posted_id(isset($_GET['comment']) ? $_GET['comment'] : null));
    $wanted = pg_comments_widget_page_param(isset($_GET['comments_page']) ? $_GET['comments_page'] : null);
    $options = pg_comments_widget_options($cfg);

    if ($target > 0) {
        $view = 'all';
    } else {
        $view = (isset($_GET['comments']) && is_string($_GET['comments'])) ? $_GET['comments'] : null;
    }

    $read = pg_comments_rows($page_id, $read_thread, $viewer, array('featured' => $options['featured'], 'view' => $view, 'parent_id' => true, 'thread_key' => true));

    // A product's reviews come newest first and are never pinned or filtered
    // by featured, whatever the widget's order and featured settings say:
    // the order read is kept as it is.
    if (!empty($read['review'])) {
        $options['order'] = 'oldest';
        $options['featured'] = 'none';
    }

    $entries = pg_comments_widget_order($read['rows'], $options);
    $paged = pg_comments_widget_paginate($entries, $search ? 0 : $options['per_page'], $wanted, $target);
    $by_id = array();

    foreach ($read['rows'] as $read_row) {
        $read_id = _pg_comments_row_int($read_row, 'id');

        if (($read_id > 0) && !isset($by_id[$read_id])) {
            $by_id[$read_id] = $read_row;
        }
    }

    $allow_new = pg_comments_allow_new($page_id, $post_type, $post_id, $settings['allow_new']);
    $folder_access = false;

    if ($signed_in && ((string) $role === '3')) {
        $folder_access = (check_folder_access_in_array($page['page_folder'], get_folders_that_user_has_access_to($user_id)) == true);
    }

    $record_editor = ($record !== null) && ($user_id > 0) && ((int) $record['form']['form_editor_user_id'] === $user_id);
    $name_mode = pg_comments_widget_name_mode($signed_in, $role, $folder_access, $record_editor, $settings['select_name']);
    $watching_on = ($settings['watcher_email_page_id'] > 0);

    // The form state is read before the draw: the Messages node uses the
    // whole add_comment entry up when it prints an error, and the comment a
    // refused post answered decides what the form answers now.
    if (!class_exists('liveform')) {
        include_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
    }

    $lf = $audience['session'] ? new liveform('add_comment', $page_id) : null;
    $field = function ($name) use ($lf) {
        $value = $lf ? $lf->get_field_value($name) : null;
        return is_scalar($value) ? (string) $value : '';
    };
    $in_session = $lf && isset($_SESSION['software']['liveforms']['add_comment'][$page_id]);
    // The CSRF token of the forms; none in a render that is not the
    // visitor's own.
    $token = $audience['session'] ? get_token_field() : '';

    $facts = array(
        'allow_new' => $allow_new,
        'signed_in' => $signed_in,
        'require_login' => $settings['require_login'],
        'search_index' => $search,
        'count' => $read['count'],
        'featured_view' => $read['featured_view'],
        'has_featured' => $read['has_featured'],
        'featured_mode' => $options['featured'],
        'staff' => $is_staff,
        'name_mode' => $name_mode,
        'rating_on' => $settings['rating'],
        'files_on' => $settings['attachments'],
        'watching_on' => $watching_on,
    );
    $can_comment = pg_comments_widget_thread_flags($facts)['can_comment'];

    // The comment the form answers: the parent_id a refused post kept, else
    // the one ?reply_to= names; only when the visitor may answer it in the
    // thread the form posts to (pg_comment_reply_to()), so the name of a
    // comment the visitor cannot see never reaches the form. Before 11.0.31
    // nobody answers a comment.
    $can_reply = $can_comment && pg_comment_replies_ready();

    if ($in_session) {
        $reply_value = $field('parent_id');
    } else {
        $reply_value = (isset($_GET['reply_to']) && is_scalar($_GET['reply_to'])) ? (string) $_GET['reply_to'] : '';
    }

    $reply_parent = $can_reply ? pg_comment_reply_to($reply_value, $page_id, $post_type, $post_id, $viewer) : null;

    // Watching. The watch box, the count and the watcher list read the
    // thread the forms post to, as add_or_remove_watcher.php does.
    $post_thread = array('item_type' => $post_type, 'item_id' => $post_id);
    $is_watcher = false;
    $submitter_email = '';
    $submitter_notified = false;
    $manage_watchers = false;
    $editor_counted = false;
    $submitter_counted = false;
    $watcher_count = 0;

    if ($watching_on && !$search) {
        $submitter_email = ($record !== null) ? (string) get_submitter_email_address($post_id) : '';
        $same_as_submitter = ($email !== '') && ($submitter_email !== '') && (mb_strtolower($email) === mb_strtolower($submitter_email));

        if ($signed_in) {
            $who = "(user_id = '" . $user_id . "')" . (($email !== '') ? " || (email_address = '" . e($email) . "')" : '');
            $is_watcher = ((int) db_value("SELECT COUNT(*) FROM watchers WHERE (" . $who . ") AND (page_id = '" . $page_id . "')" . pg_comments_thread_sql($post_thread)) > 0);

            // The record's submitter is told of new comments anyway and is
            // not asked to watch, as on the classic block.
            $submitter_notified = ($record !== null) && ($settings['submitter_email_page_id'] > 0) && $same_as_submitter;

            // Who may manage the watchers: add_or_remove_watcher.php's rule.
            $manage_watchers = $staff || (($record !== null) && ($record_editor
                || ($settings['watchers_by_submitter']
                    && ((($user_id > 0) && ((int) $record['form']['user_id'] === $user_id)) || $same_as_submitter))));
        }

        // The form editor (shown to staff and to the editor) and the record's
        // submitter (when the page mails them) are counted as the classic
        // block counts them.
        $editor_counted = ($record !== null) && ((string) $record['form']['form_editor_username'] !== '') && ($staff || $record_editor);
        $submitter_counted = ($record !== null) && ($settings['submitter_email_page_id'] > 0) && ($submitter_email !== '');
        $watcher_count = (int) db_value("SELECT COUNT(*) FROM watchers WHERE (page_id = '" . $page_id . "')" . pg_comments_thread_sql($post_thread))
            + ($editor_counted ? 1 : 0) + ($submitter_counted ? 1 : 0);
    }

    $thread_flags = pg_comments_widget_thread_flags($facts + array(
        'pages' => $paged['pages'],
        'replying' => ($reply_parent !== null),
        'is_watcher' => $is_watcher,
        'submitter_notified' => $submitter_notified,
        'manage_watchers' => $manage_watchers,
    ));
    $can_publish = ($is_staff || $form_editor) && $can_comment;
    $captcha_on = $can_comment && defined('CAPTCHA') && CAPTCHA;

    // The thread's words and links, raw.
    $label = get_comment_label(array('label' => $settings['label']));
    $label_lower = mb_strtolower($label);
    $count = (int) $read['count'];
    $path = _pg_sw_current_path();
    $params = _pg_sw_query_params();
    $urls = pg_comments_widget_view_urls($path, $params);
    $author = '';

    if (($name_mode === 'fixed') && $can_comment) {
        $author = pg_comment_member_name(defined('USER_USERNAME') ? USER_USERNAME : '', pg_comment_member_contact($user_id), pg_sw_page_is_visual($page_id), false);
    }

    $reply_name = '';

    if ($thread_flags['is_replying'] && ($reply_parent !== null)) {
        $reply_name = _pg_comments_row_text($reply_parent, 'name');
        $reply_name = ($reply_name !== '') ? $reply_name : lang('Anonymous');
    }

    $thread_values = array(
        '__comments_title' => $read['featured_view']
            ? lang('Featured ') . get_comment_label(array('label' => $settings['label'], 'number' => count($entries)))
            : pg_format_number($count, 0) . ' ' . get_comment_label(array('label' => $settings['label'], 'number' => $count)),
        '__comments_count' => pg_format_number($count, 0),
        '__comments_label' => $label,
        '__comments_label_plural' => get_comment_label(array('label' => $settings['label'], 'number' => 2)),
        '__comments_intro' => $settings['message'],
        '__comments_closed_message' => ($settings['closed_message'] !== '') ? $settings['closed_message'] : lang("We're sorry. New comments are no longer being accepted."),
        '__comments_show_all_url' => $read['featured_view'] ? $urls['all'] : '',
        '__comments_show_featured_url' => (($options['featured'] === 'filter') && $read['has_featured'] && !$read['featured_view']) ? $urls['featured'] : '',
        '__login_url' => $thread_flags['must_sign_in'] ? pg_comments_widget_login_url($page_id) : '',
        '__comment_author_name' => $author,
        '__reply_to_name' => $reply_name,
        '__watcher_count_text' => ($watching_on && !$search) ? pg_comments_watcher_count_text($watcher_count, $is_watcher, $label_lower) : '',
    );

    // The comments of this page.
    $badge_label = defined('BADGE_LABEL') ? (string) BADGE_LABEL : '';
    $sees_pending = $is_staff || $form_editor;
    $row_state = array('is_staff' => $is_staff, 'sees_pending' => $sees_pending, 'own_ids' => $viewer['own_ids'], 'rating_on' => $settings['rating'], 'badge_label' => $badge_label);
    $env = array('is_staff' => $is_staff, 'sees_pending' => $sees_pending, 'show_date' => $settings['show_date'], 'label_lower' => $label_lower, 'badge_label' => $badge_label);
    $rows = array();

    foreach ($paged['entries'] as $entry) {
        $row_flags = pg_comments_widget_row_flags($entry['row'], $entry['featured'], $row_state,
            array('depth' => $entry['depth'], 'quoted' => $entry['quoted'], 'replies' => $entry['replies']));
        $created = (int) _pg_comments_row_text($entry['row'], 'created_timestamp');
        $reply_ok = false;

        // A comment may be answered when the post would take it: in the
        // thread the form posts to (a page's read holds every comment on the
        // page, whatever its item) and visible to the visitor.
        if ($can_reply) {
            $check = pg_comment_parent_ok($entry['id'], array(
                'id' => $entry['id'],
                'page_id' => $page_id,
                'item_type' => _pg_comments_row_text($entry['row'], 'item_type'),
                'item_id' => _pg_comments_row_int($entry['row'], 'item_id'),
                'published' => _pg_comments_row_text($entry['row'], 'published'),
            ), $page_id, $post_type, $post_id, $viewer);
            $reply_ok = $check['accept'];
        }

        $parent_row = (($entry['parent_id'] > 0) && isset($by_id[$entry['parent_id']])) ? $by_id[$entry['parent_id']] : null;
        $rows[] = array(
            'id' => $entry['id'],
            'flags' => $row_flags,
            'values' => pg_comments_widget_row_values($entry['row'], $row_flags, $env)
                + pg_comments_widget_link_values($entry, $parent_row, isset($paged['ids'][$entry['parent_id']]), $path, $params, $reply_ok),
            'datetime' => ($settings['show_date'] && ($created > 0)) ? date('Y-m-d H:i:s', $created) : '',
            'depth' => $entry['depth'],
            'reply_ok' => $reply_ok,
        );
    }

    $name_value = $field('name');

    // A signed-in visitor who types a name starts with their own.
    if (!$in_session && $signed_in && ($name_mode === 'typed')) {
        $contact = pg_comment_member_contact($user_id);
        $name_value = pg_contact_display_name($contact['first_name'], $contact['last_name'], $contact['nickname']);
    }

    $options_html = '';

    if (($name_mode === 'chosen') && $can_comment) {
        $options_html = pg_comments_name_options_html(
            pg_comment_name_choices(defined('USER_USERNAME') ? USER_USERNAME : '', pg_comment_member_contact($user_id), pg_sw_page_is_visual($page_id)),
            $field('name_type'),
            lang('Anonymous'));
    }

    $slots = array(
        'pg_comment_form_message' => pg_sw_filter_value_html($field('message')),
        'pg_comment_form_name' => pg_sw_filter_value_html($name_value),
        'pg_comment_form_name_options' => $options_html,
        'pg_comment_form_label' => pg_sw_filter_value_html($label_lower),
    );

    // The watch box of the form: offered to a signed-in visitor who is not
    // told of new comments yet.
    $watcher_box = false;

    if ($can_comment && $watching_on && $signed_in) {
        $notified = (($email !== '') && (mb_strpos(mb_strtolower($settings['admin_email']), mb_strtolower($email)) !== false)) || $form_editor;

        if (!$notified && ($record !== null) && ($email !== '')) {
            if ($settings['conditional_admins']) {
                $notified = ((int) db_value(
                    "SELECT COUNT(*)
                    FROM form_field_options
                    LEFT JOIN form_data ON form_field_options.form_field_id = form_data.form_field_id
                    WHERE
                        (form_field_options.page_id = '" . (int) $record['custom_form_page_id'] . "')
                        AND (form_data.form_id = '" . $post_id . "')
                        AND (form_field_options.email_address LIKE '%" . e(escape_like($email)) . "%')
                        AND (form_data.data = form_field_options.value)") > 0);
            }

            if (!$notified && ($settings['submitter_email_page_id'] > 0)) {
                $notified = (mb_strtolower($email) === mb_strtolower($submitter_email));
            }
        }

        $watcher_box = pg_comments_watcher_box($watching_on, $signed_in, $is_watcher, $notified);
    }

    $sections = array(
        'comment_captcha' => ($captcha_on && $lf) ? (string) get_captcha_fields($lf) : '',
        'comment_rating_input' => ($settings['rating'] && $can_comment) ? pg_comments_rating_input_html($field('rating')) : '',
        'comment_publish' => '',
        'comment_watchers' => '',
        'comment_pagination' => pg_sw_pagination_html('comments_page', $paged['page'], $paged['pages'], 'software_comments'),
    );

    if ($can_publish) {
        if ($field('publish') !== '') {
            $publish = $field('publish');
            $when = $field('publish_date_and_time');
            $cancel = ($field('publish_cancel') === '1');
        } else {
            $publish = $settings['auto_publish'] ? 'now' : 'manual';
            $when = date(((defined('DATE_FORMAT') && (DATE_FORMAT == 'month_day')) ? 'n/j' : 'j/n') . '/Y g:i A', time() + 3600);
            $cancel = true;
        }

        $scripts = '<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js') . '"></script>'
            . get_date_picker_format();
        $sections['comment_publish'] = pg_comments_publish_html($publish, $when, $cancel, $label_lower, $scripts);
    }

    // The watcher manager: the people told of a new comment, for who may
    // manage them. Addresses show only to staff (a role below 3) and to
    // their owner; a member who manages watchers sees the others masked.
    if ($thread_flags['can_manage_watchers']) {
        $show_all = ((string) $role !== '') && ((int) $role < 3);
        $shown = function ($address) use ($show_all, $email) {
            return $show_all || (($email !== '') && (mb_strtolower((string) $address) === mb_strtolower($email)));
        };
        $fixed = array();

        if ($editor_counted) {
            $editor_email = (string) $record['form']['form_editor_email_address'];
            $fixed[] = pg_comments_watcher_label($record['form']['form_editor_username'], $editor_email, $shown($editor_email));
        }

        if ($submitter_counted) {
            $fixed[] = pg_comments_watcher_label('', $submitter_email, $shown($submitter_email));
        }

        $listed = db_items(
            "SELECT
                watchers.id,
                watchers.email_address,
                user.user_username AS username,
                user.user_email AS user_email_address
            FROM watchers
            LEFT JOIN user ON watchers.user_id = user.user_id
            WHERE
                (watchers.page_id = '" . $page_id . "')
                AND (watchers.item_id = '" . $post_id . "')
                AND (watchers.item_type = '" . e($post_type) . "')
            ORDER BY watchers.id ASC");
        $watchers = array();

        foreach ((is_array($listed) ? $listed : array()) as $watcher) {
            $username = isset($watcher['username']) ? (string) $watcher['username'] : '';
            $address = ($username !== '') ? (string) $watcher['user_email_address'] : (string) $watcher['email_address'];
            $watchers[] = array('id' => (int) $watcher['id'], 'label' => pg_comments_watcher_label($username, $address, $shown($address)));
        }

        // The address a refused add left in the box.
        $typed = (new liveform('add_or_remove_watcher', $page_id))->get_field_value('username_or_email_address');
        $sections['comment_watchers'] = pg_comments_watchers_html(array(
            'action_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_or_remove_watcher.php',
            'token_field' => $token,
            'send_to' => get_request_uri(),
            'page_id' => $page_id,
            'item_type' => $post_type,
            'item_id' => $post_id,
            'fixed' => $fixed,
            'watchers' => $watchers,
            'typed' => is_scalar($typed) ? (string) $typed : '',
        ));
    }

    $form = array(
        'can_comment' => $can_comment,
        'action_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_comment.php',
        'hidden_html' => $can_comment
            ? pg_comments_form_hidden_html($token, get_request_uri(), $page_id, $post_type, $post_id, ($reply_parent !== null) ? (int) $reply_parent['id'] : 0)
            : '',
        'multipart' => $settings['attachments'],
        'label_slot' => 'pg_comment_form_label',
        'watcher_checked' => !$lf || !$lf->field_in_session('page_id') || ($field('watcher') === '1'),
        // A staff comment from a form without the publish options follows
        // the page's setting, as the classic form's default does.
        'publish_default' => $can_publish ? ($settings['auto_publish'] ? 'now' : 'manual') : '',
        'state' => array(
            'files_on' => $settings['attachments'],
            'watcher_box' => $watcher_box,
            'name_mode' => $name_mode,
            'rating_on' => $settings['rating'],
            'captcha_on' => $captcha_on,
            'can_publish' => $can_publish,
        ),
    );

    // The reply, cancel and share controls, the watch form and the staff's
    // open-or-close form.
    $can_watch = $thread_flags['is_watching'] || $thread_flags['not_watching'];
    $controls = array(
        'can_reply' => $can_reply,
        'replying' => $thread_flags['is_replying'],
        'cancel_url' => pg_comments_widget_page_url($path, $params, array('reply_to'), array(), 'software_add_comment'),
        'share_copied' => lang('Link copied.'),
        'share_prompt' => lang('Copy the link to this comment:'),
        'can_watch' => $can_watch,
        'is_watcher' => $is_watcher,
        'watch_action_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_or_remove_watcher.php',
        'watch_hidden_html' => $can_watch
            ? pg_comments_small_form_hidden_html($token, get_request_uri(), $page_id, $post_type, $post_id, $is_watcher ? 'remove' : 'add')
            : '',
        'is_staff' => $is_staff,
        'allow_new' => $allow_new,
        'allow_action_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/allow_or_disallow_new_comments.php',
        'allow_hidden_html' => $is_staff
            ? pg_comments_small_form_hidden_html($token, get_request_uri(), $page_id, $post_type, $post_id, $allow_new ? 'disallow' : 'allow')
            : '',
    );

    // A node bound to a token only a list of recent comments fills prints
    // nothing in a thread. The widget's Messages node prints only the
    // add_comment form's errors; the ones in the small forms print their own
    // handler's.
    pg_comments_drop_mode_bindings($tree, 'thread');
    _pg_inject_messages_node($tree, 'add_comment');
    pg_comments_stamp_form_messages($tree);

    $html = pg_comments_widget_draw($tree, array(
        'widget_id' => (int) $widget_id,
        'thread_flags' => $thread_flags,
        'thread_values' => $thread_values,
        'rows' => $rows,
        'form' => $form,
        'controls' => $controls,
        'sections' => $sections,
        'slots' => $slots,
        'edit_chips' => $is_staff && pg_sw_edit_chips_on(),
    ));

    if ($lf) {
        $lf->remove_form();
    }

    if ($watching_on && $audience['session']) {
        (new liveform('add_or_remove_watcher', $page_id))->remove_form();
    }

    return pg_sw_parts_css($html) . $html;
}

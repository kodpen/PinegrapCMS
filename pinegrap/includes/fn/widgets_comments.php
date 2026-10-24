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

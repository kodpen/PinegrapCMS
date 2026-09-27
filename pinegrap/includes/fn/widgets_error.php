<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the error page system widget - the designed page
// a visitor sees for "page not found" and every other site error.
//
// Loaded by functions.php through require_once, never on its own.
//
// output_error() records what went wrong (pg_sw_error_context()) and hands
// over to get_error_screen(). When a designed page carries this widget, that
// page is rendered instead of the legacy "error" page type: an operator who
// built the error page in the editor did so to replace the old one, and the
// starter sites all ship a legacy error page that would otherwise win.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The error being shown in this request: its message (as output_error()
// received it, HTML allowed) and its response code. Called with arguments by
// output_error(), without by the renderer. null until an error happened.
function pg_sw_error_context($message = null, $code = null)
{
    static $stored = null;
    if ($message !== null || $code !== null) {
        $stored = array(
            'message' => is_scalar($message) ? (string)$message : '',
            'code'    => (int)$code,
        );
    }
    return $stored;
}

// The first designed page carrying an error page widget, or 0. Pages in the
// recycle bin do not count (pg_sw_widget_pages() leaves them out).
function pg_sw_error_page_id()
{
    static $page_id = null;
    if ($page_id !== null) return $page_id;
    $page_id = 0;
    // page_tree_json arrived with the multi-page editor; before that upgrade
    // no designed page can carry a widget.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return $page_id;
    $pages = pg_sw_widget_pages('error_page');
    if ($pages) $page_id = (int)$pages[0]['page_id'];
    return $page_id;
}

// A same-site address the visitor came from, as a path, or ''. Another site's
// address, or the address that failed, is not somewhere to send them back to.
function _pg_err_back_url()
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
    if ($ref === '') return '';
    $parts = parse_url($ref);
    if (!is_array($parts) || empty($parts['host'])) return '';
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', (string)$_SERVER['HTTP_HOST'])) : '';
    if ($host === '' || strtolower($parts['host']) !== $host) return '';
    $path = (isset($parts['path']) ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    $path = pg_safe_redirect_path($path, '/__none__');
    if ($path === '/__none__') return '';
    $here = defined('REQUEST_URL') ? (string)REQUEST_URL : '';
    return ($path === $here) ? '' : $path;
}

// ============================================================================
// error_page
// ============================================================================

// Render an 'error_page' system widget.
//
// Shown by get_error_screen() for every site error while this page is the
// site's error page; the error's message and code come from
// pg_sw_error_context(). Opened directly - by a link, or a crawler that
// found the address - there is no error to report, so the widget reads as
// "page not found" and the page answers 404: a page that says "not found"
// and answers 200 is the soft 404 search engines flag.
//
// Visibility flags: is_not_found (code 404), is_other_error (any other
// error), has_error_code (the error carries an HTTP code).
// Tokens: ^^__error_code^^, ^^__error_message^^ (the software's own words,
// as text), ^^__requested_url^^, ^^__site_name^^, ^^__home_url^^,
// ^^__back_url^^ (a same-site referrer; a link bound to it drops when there
// is none).
function _render_system_widget_error_page($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $error = pg_sw_error_context();
    if ($error === null) {
        $code    = 404;
        $message = '';
        // The SEO pass renders pages inside another request (the editor's
        // save among them); the status code there belongs to that request.
        if (!(function_exists('pg_seo_rendering') && pg_seo_rendering()) && !headers_sent()) {
            set_response_code(404);
        }
    } else {
        $code    = (int)$error['code'];
        $message = $error['message'];
    }

    // The message is built by the software (output_error() callers), often
    // with a "go back" link around it. The widget has its own links, so the
    // words are kept and the markup is not.
    $message = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($message), ENT_QUOTES, 'UTF-8')));

    $home_url = defined('PATH') ? PATH : '/';
    $back_url = _pg_err_back_url();
    $is_404   = ($code === 404);

    _eo_apply_visibility_bindings($tree, array(
        'is_not_found'   => $is_404,
        'is_other_error' => !$is_404,
        'has_error_code' => ($code >= 400),
    ));
    _pg_member_drop_empty_links($tree, array('__home_url' => $home_url, '__back_url' => $back_url));
    pg_cf_link_labels($tree);

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));

    return _pg_member_apply_tokens($rendered, array(
        '__error_code'    => ($code >= 400) ? (string)$code : '',
        '__error_message' => h($message),
        '__requested_url' => h(defined('REQUEST_URL') ? (string)REQUEST_URL : ''),
        '__site_name'     => h(_pg_member_site_name()),
        '__home_url'      => h($home_url),
        '__back_url'      => h($back_url),
    ));
}

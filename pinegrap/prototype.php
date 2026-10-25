<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The prototype viewer: one design's draft pages played as a clickable
 * prototype (includes/fn/prototype.php).
 *
 * prototype.php?page=<id> (or ?style=<design id>) is the viewer: for a
 * signed-in administrator, designer or manager it draws a bar (the design's
 * name, its drafts, a hotspots switch, close) around a sandboxed frame.
 *
 * prototype.php?frame=1&... is that frame. It runs with no session and no
 * cookie, admitted by the signed address the viewer made (design, page,
 * item, person, expiry; the person's role is read again on every frame),
 * and draws the draft as a background render: what a signed-out visitor
 * would get once the page is published, with no status code, redirect, page
 * cache copy, visitor statistics, Analytics tag or record view count of its
 * own. Its links are rewritten to stay in the prototype, open the site or go
 * nowhere.
 *
 * Neither request goes through get_page.php: the private gate there is not
 * touched, and a draft's address still asks for the same rights it asked
 * for before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

$pg_proto_frame = isset($_GET['frame']) && is_string($_GET['frame']) && ($_GET['frame'] === '1');

if ($pg_proto_frame) {
    // No session: nothing of the person looking may decide what the page
    // shows, and nothing the page does may land in their session.
    define('PG_NO_SESSION', true);

    // The frame's address is bound to the browser the viewer made it for
    // (pg_proto_frame_bind()): the session cookie's value is kept for that
    // comparison only. The page's own scripts can read the address, never
    // this cookie.
    $pg_proto_client = (isset($_COOKIE[session_name()]) && is_string($_COOKIE[session_name()])) ? $_COOKIE[session_name()] : '';

    // Nor anything their browser holds: the remember-me token would sign
    // them in all the same (initialize_user()), and a consent, currency or
    // device choice would make the page theirs rather than a first visit.
    $_COOKIE = array();

    // The device is fixed here so initialize_device_type() does not answer
    // with a cookie of its own.
    $_SESSION = array('software' => array('device_type' => 'desktop'));
}

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');

$pg_proto_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

// Nothing is sent to the viewer: a form inside the frame cannot submit (the
// sandbox), and nothing else posts here.
if (($pg_proto_method !== 'GET') && ($pg_proto_method !== 'HEAD')) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

// Neither the viewer nor the frame is kept by a browser or a proxy, indexed
// or passed on as the referrer.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');

$pg_proto_viewer = PATH . SOFTWARE_DIRECTORY . '/prototype.php';

if ($pg_proto_frame) {
    // Drawn inside the viewer only.
    header('X-Frame-Options: SAMEORIGIN');

    $parsed = pg_proto_frame_parse($_GET);
    $state = pg_proto_frame_state($parsed, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '', time(), $pg_proto_client);
    unset($pg_proto_client);

    if ($state === 'expired') {
        pg_proto_frame_refuse(lang('This prototype view has expired. Reload the page to draw it again.'), 403);
    }

    // A genuine address asked for by another browser, or by this one after
    // its session changed (signed out, signed in again): refused, and not
    // counted as a forgery.
    if ($state === 'unbound') {
        pg_proto_frame_refuse(lang('This prototype view was made for another browser session. Reload the page to draw it again.'), 403);
    }

    if ($state !== 'ok') {
        pg_proto_frame_offence();
        pg_proto_frame_refuse(lang('This prototype address is not valid.'), 403);
    }

    // The person the address was made for must still be an administrator,
    // designer or manager: a role taken away ends their frames at once.
    $pg_proto_role = db_value("SELECT user_role FROM user WHERE user_id = '" . (int) $parsed['u'] . "'");

    if (($pg_proto_role === null) || !pg_proto_viewer_role_allowed((string) $pg_proto_role)) {
        pg_proto_frame_refuse(lang('This prototype address is not valid.'), 403);
    }

    $pg_proto_pages = pg_proto_design_pages($parsed['s']);
    $pg_proto_page = null;

    foreach ($pg_proto_pages as $page) {
        if ($page['id'] === (int) $parsed['p']) {
            $pg_proto_page = $page;
            break;
        }
    }

    if ($pg_proto_page === null) {
        pg_proto_frame_refuse(lang('This page is no longer a draft of the design.'), 404);
    }

    require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

    // The address the widgets read, as a visitor's request would carry it.
    $_GET = array('page' => $pg_proto_page['name'] . (($parsed['sub'] !== '') ? '/' . $parsed['sub'] : ''));

    if ($parsed['r'] !== '') {
        $_GET['r'] = $parsed['r'];
    }

    // The request the widgets build links to the page itself from
    // (get_request_uri(): language switcher, calendar, sign-in's way back) is
    // the page's own address, not this frame's signed one, which would be
    // drawn into the page as a link and opened outside the sandbox.
    $_SERVER['REQUEST_URI'] = pg_proto_public_uri(PATH, $pg_proto_page['name'], $parsed['sub'], $parsed['r']);
    unset($_SERVER['HTTP_X_REWRITE_URL']);

    // The cookie notice is answered with no: a tag held for consent stays
    // held, and the frame hides the notice (pg_proto_frame_inject()).
    $pg_proto_cookies = array();

    if (function_exists('pg_consent_enabled') && pg_consent_enabled()) {
        $_COOKIE['pg_consent'] = pg_proto_consent_refused(pg_consent_category_letters());
        $pg_proto_cookies['pg_consent'] = $_COOKIE['pg_consent'];
    }

    // A custom form owning its fields asks which page it is drawn on.
    pg_rendered_page($pg_proto_page['id']);

    // A background render, as the SEO pass and the editor's Preview draw:
    // output_error() throws instead of printing its screen, and no widget
    // writes a status code, redirects, counts a record view or logs a
    // missing address.
    $html = '';
    $error = '';

    pg_seo_rendering(true);
    ob_start();

    try {
        $html = (string) get_page_content($pg_proto_page['id'], '', '', 'preview', false, array(), false, 'desktop');
    } catch (Throwable $t) {
        $error = $t->getMessage();
    } finally {
        pg_seo_rendering(false);
        // Whatever a renderer printed on the way is not part of the page.
        ob_end_clean();
    }

    if (($error !== '') || (trim($html) === '')) {
        pg_proto_frame_refuse(lang(array('string' => 'The page could not be drawn: {var:1}', 'vars' => array(($error !== '') ? $error : lang('it is empty.')))), 500);
    }

    // What get_page.php does to a page on its way out: the language pass and
    // switchers, the stamps on local assets.
    if (function_exists('pg_tr_finalize')) {
        $html = pg_tr_finalize($html);
    }

    if (function_exists('pg_version_assets')) {
        $html = pg_version_assets($html);
    }

    // Every link on the page, resolved against the names it can reach; a
    // link into a language directory by the name after it.
    $env = pg_link_resolve_env();
    $languages = (function_exists('pg_tr_prefix_map') && function_exists('pg_tr_ready') && pg_tr_ready()) ? pg_tr_prefix_map() : array();
    $items = array();

    foreach (pg_proto_html_hrefs($html) as $href) {
        $link = pg_link_parse($href, $env);

        if ($link['result'] === null) {
            $split = pg_proto_split_language($link['item'], $languages);
            $items[] = $split['item'];
        }
    }

    $maps = pg_link_maps_for_items($items);
    $proto = array();

    foreach ($pg_proto_pages as $page) {
        $proto[$page['id']] = true;
    }

    $ctx = array(
        'env' => $env,
        'maps' => $maps,
        'form_names' => $maps['form_names'],
        'proto' => $proto,
        'draft_folder' => pg_page_draft_folder_id(false),
        'bin_folders' => array_fill_keys(pg_recycle_bin_folder_ids(), true),
        'current_page' => $pg_proto_page['id'],
        'current_sub' => $parsed['sub'],
        'current_record' => $parsed['r'],
        'languages' => $languages,
        'viewer' => $pg_proto_viewer,
    );

    $strings = array(
        'broken' => lang('This link leads nowhere: the page it names does not exist.'),
        'draft' => lang('This link leads to a draft of another design.'),
        'forms' => lang('Forms do not send anything in a prototype.'),
    );

    $html = pg_proto_rewrite_links($html, function ($href) use ($ctx) {
        return pg_proto_link_target($href, $ctx);
    }, $strings);

    $html = pg_proto_frame_inject($html, $strings, $pg_proto_cookies);

    // Nothing of this answer is kept by the browser.
    header_remove('Set-Cookie');
    header('Content-Type: text/html; charset=utf-8');

    echo $html;
    exit;
}

// The viewer itself (the frame never gets here).
header('X-Frame-Options: DENY');

$user = validate_user();
validate_area_access($user, 'manager');

$pg_proto_page_id = (isset($_GET['page']) && is_string($_GET['page']) && ctype_digit($_GET['page'])) ? (int) $_GET['page'] : 0;
$pg_proto_style_id = (isset($_GET['style']) && is_string($_GET['style']) && ctype_digit($_GET['style'])) ? (int) $_GET['style'] : 0;

if (($pg_proto_style_id <= 0) && ($pg_proto_page_id > 0)) {
    $pg_proto_style_id = (int) db_value("SELECT page_style FROM page WHERE page_id = '" . $pg_proto_page_id . "'");
}

$pg_proto_designs = pg_visual_design_style_ids();

if (($pg_proto_style_id <= 0) || !isset($pg_proto_designs[$pg_proto_style_id])) {
    output_error(lang('The design could not be found.'), 404);
}

$pg_proto_design = (string) db_value("SELECT style_name FROM style WHERE style_id = '" . $pg_proto_style_id . "'");
$pg_proto_pages = pg_proto_design_pages($pg_proto_style_id);
$pg_proto_start = pg_proto_start_page($pg_proto_pages, $pg_proto_page_id);

// The item a pretty address or a record link named, and the section it
// opens at, go with the page they were asked for, not with the one the
// prototype falls back to.
$pg_proto_sub = '';
$pg_proto_record = '';
$pg_proto_anchor = '';

if (($pg_proto_start['id'] > 0) && !$pg_proto_start['fallback'] && ($pg_proto_start['id'] === $pg_proto_page_id)) {
    $pg_proto_sub = pg_proto_clean_text(isset($_GET['sub']) ? $_GET['sub'] : '', 255);
    $pg_proto_record = pg_proto_clean_text(isset($_GET['r']) ? $_GET['r'] : '', 100);
    $pg_proto_anchor = pg_proto_clean_fragment(isset($_GET['anchor']) ? $_GET['anchor'] : '');
}

// One line in the Site Log per design and session, not one per click.
if (empty($_SESSION['software']['prototype_viewed'][$pg_proto_style_id])) {
    $_SESSION['software']['prototype_viewed'][$pg_proto_style_id] = time();
    log_activity(lang(array('string' => 'prototype of design ({var:1}) was viewed', 'vars' => array($pg_proto_design))));
}

$pg_proto_key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
$pg_proto_frame_url = '';
$pg_proto_error = '';

if ($pg_proto_start['id'] > 0) {
    $pg_proto_expires = time() + pg_proto_frame_ttl();
    $pg_proto_signature = pg_proto_frame_sign($pg_proto_style_id, $pg_proto_start['id'], $user['id'], $pg_proto_expires, $pg_proto_key, $pg_proto_sub, $pg_proto_record);

    if ($pg_proto_signature === '') {
        $pg_proto_error = lang('The prototype cannot be drawn because the site has no encryption key.');
    } else {
        // Bound to this browser's session: the frame request carries its
        // cookie, a copy of the address read elsewhere does not.
        $pg_proto_frame_url = pg_proto_frame_url(
            $pg_proto_viewer,
            pg_proto_frame_query($pg_proto_style_id, $pg_proto_start['id'], $user['id'], $pg_proto_expires, $pg_proto_signature, $pg_proto_sub, $pg_proto_record,
                pg_proto_frame_bind($pg_proto_signature, session_id(), $pg_proto_key)),
            $pg_proto_anchor
        );
    }
}

echo pg_proto_shell_html(array(
    'design' => $pg_proto_design,
    'pages' => $pg_proto_pages,
    'current' => $pg_proto_start['id'],
    'fallback' => $pg_proto_start['fallback'],
    'frame_url' => $pg_proto_frame_url,
    'viewer' => $pg_proto_viewer,
    'close_url' => PATH . SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . $pg_proto_style_id . (($pg_proto_start['id'] > 0) ? '&page=' . $pg_proto_start['id'] : ''),
    'software' => PATH . SOFTWARE_DIRECTORY,
    'lang' => lang(array('info' => '')),
    'error' => $pg_proto_error,
));

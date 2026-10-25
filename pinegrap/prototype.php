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
 * prototype.php?link=<token> is the same viewer opened by a share link
 * (includes/fn/prototype_links.php): for anyone holding it, or for a
 * signed-in member of the team, past the link's password when it has one.
 * The parameter is not "token": that name is the panel's form token, which
 * validate_token_field() also reads from the query string. The frame's links
 * come back as prototype.php?l=<link id>, without the token, for the session
 * that opened the link by it.
 *
 * prototype.php?frame=1&... is that frame. It runs with no session and no
 * cookie, admitted by the signed address the viewer made (design, page,
 * item, expiry; and the person, whose role is read again on every frame, or
 * the link by its id, which must still open on every frame), and draws the
 * draft as a background render: what a signed-out visitor would get once the
 * page is published, with no status code, redirect, page cache copy, visitor
 * statistics, Analytics tag or record view count of its own. Its links are
 * rewritten to stay in the prototype, open the site or go nowhere.
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

// A share link's token opens the prototype to whoever holds it, so no log
// written on the way may keep it: the firewall (run by init.php) writes the
// request's address to waf_log and firewall.log, the error log and the
// request's constants read it too. Every copy of the address is written with
// the token replaced before init.php; the token itself is read from $_GET.
define('PG_PROTOTYPE_ENTRY', true);
require_once(dirname(__FILE__) . '/includes/fn/prototype_links.php');

$pg_proto_link_mode = !$pg_proto_frame && (isset($_GET['link']) || isset($_GET['l']));

if (isset($_GET['link'])) {
    // Secure Mode's redirect (init.php) needs the address as it came:
    // REQUEST_URL is made from the written-out copy below.
    define('PG_SECURE_MODE_REDIRECT_URL', pg_proto_link_request_uri($_SERVER));
    $_SERVER = pg_proto_link_redact_server($_SERVER);
}

if ($pg_proto_frame) {
    // No session: nothing of the person looking may decide what the page
    // shows, and nothing the page does may land in their session.
    define('PG_NO_SESSION', true);

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
// sandbox), and nothing else posts here but a share link's password form.
if (($pg_proto_method !== 'GET') && ($pg_proto_method !== 'HEAD') && !(($pg_proto_method === 'POST') && $pg_proto_link_mode)) {
    header('Allow: ' . ($pg_proto_link_mode ? 'GET, HEAD, POST' : 'GET, HEAD'));
    http_response_code(405);
    exit;
}

// Neither the viewer nor the frame is kept by a browser or a proxy, indexed
// or passed on as the referrer.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');

$pg_proto_viewer = PATH . SOFTWARE_DIRECTORY . '/prototype.php';
$pg_proto_key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';

if ($pg_proto_frame) {
    // Drawn inside the viewer only.
    header('X-Frame-Options: SAMEORIGIN');

    $pg_proto_now = time();

    if (isset($_GET['l'])) {
        // Made for a share link, named by its id: the link must still open.
        $parsed = pg_proto_link_frame_parse($_GET);
        $state = pg_proto_link_frame_state($parsed, $pg_proto_key, $pg_proto_now);
    } else {
        $parsed = pg_proto_frame_parse($_GET);
        $state = pg_proto_frame_state($parsed, $pg_proto_key, $pg_proto_now);
    }

    if ($state === 'expired') {
        pg_proto_frame_refuse(lang('This prototype view has expired. Reload the page to draw it again.'), 403);
    }

    if ($state !== 'ok') {
        pg_proto_frame_offence();
        pg_proto_frame_refuse(lang('This prototype address is not valid.'), 403);
    }

    if (isset($parsed['l'])) {
        // The signature names the link; the token is never in the frame's
        // address or its links, since the design's own scripts run here and
        // a tag pasted into the design reports both to its vendor.
        $pg_proto_link = pg_proto_links_ready() ? pg_proto_link_get($parsed['l']) : null;

        // Revoked or run out since the viewer signed the address: the frame
        // ends with it, as the viewer would.
        if (($pg_proto_link === null) || (pg_proto_link_state($pg_proto_link, $pg_proto_now) !== 'ok') || ((int) $pg_proto_link['style_id'] !== (int) $parsed['s'])) {
            pg_proto_frame_refuse(lang('This link no longer works.'), 404);
        }

        // The frame's links lead back through the viewer for the same link,
        // by its id (pg_proto_link_session_opens()).
        $pg_proto_base = pg_proto_link_frame_base($pg_proto_viewer, $parsed['l']);
    } else {
        // The person the address was made for must still be an administrator,
        // designer or manager: a role taken away ends their frames at once.
        $pg_proto_role = db_value("SELECT user_role FROM user WHERE user_id = '" . (int) $parsed['u'] . "'");

        if (($pg_proto_role === null) || !pg_proto_viewer_role_allowed((string) $pg_proto_role)) {
            pg_proto_frame_refuse(lang('This prototype address is not valid.'), 403);
        }

        $pg_proto_base = $pg_proto_viewer;
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

    // A link for anyone draws a draft as a signed-out visitor would get it
    // once published: get_page.php's folder gate is not on the frame's way,
    // so a draft going live behind a sign-in is not drawn at all.
    if (isset($parsed['l']) && !pg_proto_link_page_shown((string) $pg_proto_link['audience'], pg_proto_link_page_access($pg_proto_page['id']))) {
        pg_proto_frame_refuse(lang('This page goes live behind a sign-in, so a link for anyone who has it does not show it.'), 403);
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
    $thrown = null;

    pg_seo_rendering(true);
    ob_start();

    try {
        $html = (string) get_page_content($pg_proto_page['id'], '', '', 'preview', false, array(), false, 'desktop');
    } catch (Throwable $t) {
        $error = $t->getMessage();
        $thrown = $t;
    } finally {
        pg_seo_rendering(false);
        // Whatever a renderer printed on the way is not part of the page.
        ob_end_clean();
    }

    if (($error !== '') || (trim($html) === '')) {
        // A share link's frame opens for anyone holding the link, signed
        // out, and a PHP error's message can name a server file and line:
        // the reason goes to the error log (its address is the page's
        // public one, set above) and the frame only says what happened.
        if (($thrown !== null) && !pg_proto_frame_names_failure(isset($parsed['l']), $error)) {
            pg_error_write(pg_error_entry(
                'error',
                get_class($thrown),
                'Prototype link #' . (int) $parsed['l'] . ', page ' . (int) $pg_proto_page['id'] . ' could not be drawn: ' . $error,
                $thrown->getFile(),
                $thrown->getLine(),
                pg_error_trace_short($thrown->getTrace())));

            pg_proto_frame_refuse(lang('The page could not be drawn.'), 500);
        }

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
        'viewer' => $pg_proto_base,
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

if (!$pg_proto_link_mode) {
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

    $pg_proto_frame_url = '';
    $pg_proto_error = '';

    if ($pg_proto_start['id'] > 0) {
        $pg_proto_expires = time() + pg_proto_frame_ttl();
        $pg_proto_signature = pg_proto_frame_sign($pg_proto_style_id, $pg_proto_start['id'], $user['id'], $pg_proto_expires, $pg_proto_key, $pg_proto_sub, $pg_proto_record);

        if ($pg_proto_signature === '') {
            $pg_proto_error = lang('The prototype cannot be drawn because the site has no encryption key.');
        } else {
            $pg_proto_frame_url = pg_proto_frame_url(
                $pg_proto_viewer,
                pg_proto_frame_query($pg_proto_style_id, $pg_proto_start['id'], $user['id'], $pg_proto_expires, $pg_proto_signature, $pg_proto_sub, $pg_proto_record),
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
    exit;
}

// The viewer opened by a share link (includes/fn/prototype_links.php).
$pg_proto_now = time();

if (isset($_GET['link'])) {
    $pg_proto_token = is_string($_GET['link']) ? $_GET['link'] : '';
    $pg_proto_link = pg_proto_links_ready() ? pg_proto_link_by_token($pg_proto_token) : null;
} else {
    // A link clicked inside the frame names the link by its id alone. It
    // opens only for the session that opened the link by its token, past its
    // gates, and goes on to the link's own address when the token can be read
    // back (pg_proto_link_token()), which the gates below check again.
    $pg_proto_link_id = (is_string($_GET['l']) && ctype_digit($_GET['l'])) ? (int) $_GET['l'] : 0;
    $pg_proto_link = (pg_proto_links_ready() && ($pg_proto_link_id > 0)) ? pg_proto_link_get($pg_proto_link_id) : null;
    $pg_proto_mark = isset($_SESSION['software']['prototype_link_open'][$pg_proto_link_id]) ? $_SESSION['software']['prototype_link_open'][$pg_proto_link_id] : '';

    if (!pg_proto_link_session_opens($pg_proto_mark, $pg_proto_link)) {
        pg_proto_frame_refuse(lang('Open the link you were given again to go on with this prototype.'), 403);
    }

    $pg_proto_token = pg_proto_link_token($pg_proto_link);

    if (($pg_proto_token !== '') && ($pg_proto_method !== 'POST')) {
        $pg_proto_go = (isset($_GET['page']) && is_string($_GET['page']) && ctype_digit($_GET['page'])) ? (int) $_GET['page'] : 0;
        $pg_proto_to = pg_proto_link_viewer_base($pg_proto_viewer, $pg_proto_token);

        if ($pg_proto_go > 0) {
            $pg_proto_to = pg_proto_viewer_url($pg_proto_to, $pg_proto_go,
                pg_proto_clean_text(isset($_GET['sub']) ? $_GET['sub'] : '', 255),
                pg_proto_clean_text(isset($_GET['r']) ? $_GET['r'] : '', 100),
                pg_proto_clean_fragment(isset($_GET['anchor']) ? $_GET['anchor'] : ''));
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . $pg_proto_to, true, 303);
        exit;
    }
}

$pg_proto_state = pg_proto_link_state($pg_proto_link, $pg_proto_now);
$pg_proto_design = null;
$pg_proto_pages = array();

if ($pg_proto_state === 'ok') {
    $pg_proto_design = pg_proto_link_design_name($pg_proto_link['style_id']);
    $pg_proto_pages = ($pg_proto_design !== null) ? pg_proto_design_pages($pg_proto_link['style_id']) : array();

    // The design was deleted, or none of its pages is a draft any more:
    // there is no prototype left to show.
    if (($pg_proto_design === null) || !$pg_proto_pages) {
        $pg_proto_state = 'gone';
    }
}

// Revoked, run out, gone or never made: one answer for all of them, so a
// guess learns nothing. Only a guess counts toward a ban.
if ($pg_proto_state !== 'ok') {
    if (pg_proto_link_offence_due($pg_proto_state, $pg_proto_token)) {
        pg_proto_link_offence();
    }

    pg_proto_frame_refuse(lang('This link no longer works.'), 404);
}

// The viewer's own bar keeps the link's address (the frame cannot read it);
// by its id only where the token cannot be read back.
$pg_proto_base = ($pg_proto_token !== '') ? pg_proto_link_viewer_base($pg_proto_viewer, $pg_proto_token) : pg_proto_link_frame_base($pg_proto_viewer, $pg_proto_link['id']);
$pg_proto_page_id = (isset($_GET['page']) && is_string($_GET['page']) && ctype_digit($_GET['page'])) ? (int) $_GET['page'] : 0;
$pg_proto_here = ($pg_proto_page_id > 0) ? pg_proto_viewer_url($pg_proto_base, $pg_proto_page_id) : $pg_proto_base;

// A team link: a signed-in administrator, designer or manager. The
// signed-out go to sign-in and come back to the same address (built here:
// the request's own address no longer carries the token).
if ((string) $pg_proto_link['audience'] === 'team') {
    if (!isset($_SESSION['sessionuserid']) || ($_SESSION['sessionuserid'] === '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/index.php?send_to=' . urlencode($pg_proto_here));
        exit;
    }

    $pg_proto_link_user = validate_user();

    if (!pg_proto_viewer_role_allowed($pg_proto_link_user['role'])) {
        pg_proto_frame_refuse(lang('This prototype is shared with the team only.'), 403);
    }
}

// A password link: the cookie a right password left, or the form.
if ((string) $pg_proto_link['password_hash'] !== '') {
    $pg_proto_cookie = pg_proto_link_pass_cookie_name($pg_proto_link['id']);
    $pg_proto_passed = pg_proto_link_pass_valid(isset($_COOKIE[$pg_proto_cookie]) ? $_COOKIE[$pg_proto_cookie] : '', $pg_proto_link['id'], (string) $pg_proto_link['password_hash'], $pg_proto_key, $pg_proto_now);

    if (!$pg_proto_passed) {
        $pg_proto_error = '';
        $pg_proto_locked = pg_proto_link_password_locked($pg_proto_link, $pg_proto_now);
        $pg_proto_status = 200;

        if ($pg_proto_method === 'POST') {
            if (pg_post_body_discarded()) {
                $pg_proto_error = lang('Sorry, we could not accept your request.');
                $pg_proto_status = 413;
            } else {
                validate_token_field();

                $pg_proto_result = pg_proto_link_password_try($pg_proto_link, isset($_POST['password']) ? $_POST['password'] : '', $pg_proto_now);

                if ($pg_proto_result === 'ok') {
                    if (!pg_proto_link_set_pass_cookie($pg_proto_link, $pg_proto_now, $pg_proto_viewer)) {
                        pg_proto_frame_refuse(lang('The prototype cannot be drawn because the site has no encryption key.'), 500);
                    }

                    header('Location: ' . URL_SCHEME . HOSTNAME . $pg_proto_here, true, 303);
                    exit;
                }

                $pg_proto_locked = ($pg_proto_result === 'locked');
                $pg_proto_error = $pg_proto_locked ? '' : lang('That password is not right.');
                $pg_proto_status = $pg_proto_locked ? 429 : 403;
            }
        }

        if ($pg_proto_locked) {
            // The time left of a lock already on, or the whole of the one
            // this answer has just set.
            $pg_proto_wait = pg_proto_link_password_locked($pg_proto_link, $pg_proto_now)
                ? ((int) $pg_proto_link['password_locked_until'] - $pg_proto_now)
                : pg_proto_link_lock_seconds();
            $pg_proto_error = lang(array('string' => 'Too many wrong passwords. Try again in {var:1} minutes.', 'vars' => array((int) ceil($pg_proto_wait / 60))));

            // A password sent while locked is refused as such; a plain visit
            // only says so.
            if ($pg_proto_method === 'POST') {
                $pg_proto_status = 429;
                header('Retry-After: ' . $pg_proto_wait);
            }
        }

        http_response_code($pg_proto_status);
        header('Content-Type: text/html; charset=utf-8');

        echo pg_proto_link_password_html(array(
            'action' => $pg_proto_here,
            'token_field' => get_token_field(),
            'error' => $pg_proto_error,
            'locked' => $pg_proto_locked,
            'software' => PATH . SOFTWARE_DIRECTORY,
            'lang' => lang(array('info' => '')),
        ));
        exit;
    }
}

// Past every gate: a form sent again (a reload of the answer) goes back to
// the prototype itself.
if ($pg_proto_method === 'POST') {
    header('Location: ' . URL_SCHEME . HOSTNAME . $pg_proto_here, true, 303);
    exit;
}

// From here on the frame's links, which name the link by its id, lead back
// in for this browser.
pg_proto_link_session_mark($pg_proto_link);

// A use is a person opening the prototype by the shared address, not a move
// to another page inside it, a preview a messenger draws of the link or a
// HEAD request.
$pg_proto_client = function_exists('waf_classify_bot') ? waf_classify_bot(isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '') : array('class' => 'human');

if (pg_proto_link_counts_use($pg_proto_method, $pg_proto_client['class'], $pg_proto_page_id)) {
    pg_proto_link_record_use($pg_proto_link, $pg_proto_now);
}

$pg_proto_start = pg_proto_link_start($pg_proto_pages, $pg_proto_link['start_page_id'], $pg_proto_page_id);
$pg_proto_sub = '';
$pg_proto_record = '';
$pg_proto_anchor = '';

if (($pg_proto_start['id'] > 0) && !$pg_proto_start['fallback'] && ($pg_proto_start['id'] === $pg_proto_page_id)) {
    $pg_proto_sub = pg_proto_clean_text(isset($_GET['sub']) ? $_GET['sub'] : '', 255);
    $pg_proto_record = pg_proto_clean_text(isset($_GET['r']) ? $_GET['r'] : '', 100);
    $pg_proto_anchor = pg_proto_clean_fragment(isset($_GET['anchor']) ? $_GET['anchor'] : '');
}

$pg_proto_frame_url = '';
$pg_proto_error = '';
$pg_proto_expires = $pg_proto_now + pg_proto_frame_ttl();
$pg_proto_signature = pg_proto_link_frame_sign($pg_proto_link['style_id'], $pg_proto_start['id'], $pg_proto_link['id'], $pg_proto_expires, $pg_proto_key, $pg_proto_sub, $pg_proto_record);

if ($pg_proto_signature === '') {
    $pg_proto_error = lang('The prototype cannot be drawn because the site has no encryption key.');
} else {
    $pg_proto_frame_url = pg_proto_frame_url(
        $pg_proto_viewer,
        pg_proto_link_frame_query($pg_proto_link['style_id'], $pg_proto_start['id'], $pg_proto_link['id'], $pg_proto_expires, $pg_proto_signature, $pg_proto_sub, $pg_proto_record),
        $pg_proto_anchor
    );
}

header('Content-Type: text/html; charset=utf-8');

echo pg_proto_shell_html(array(
    'design' => $pg_proto_design,
    'pages' => $pg_proto_pages,
    'current' => $pg_proto_start['id'],
    'fallback' => $pg_proto_start['fallback'],
    'fallback_text' => lang('The page you opened is no longer a draft of this design, so the prototype opens at its start page.'),
    'frame_url' => $pg_proto_frame_url,
    'viewer' => $pg_proto_base,
    'close_url' => '',
    'software' => PATH . SOFTWARE_DIRECTORY,
    'lang' => lang(array('info' => '')),
    'error' => $pg_proto_error,
));

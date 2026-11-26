<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Preview on a phone" on the server: the phone link a designer's press
 * makes, finds again and ends, and the light changed-since address the
 * phone asks whether the draft was saved since it drew it.
 *
 * The phone link is a share link (includes/fn/prototype_links.php) as it
 * is: a prototype_links row of the design, for anyone (or, for a page whose
 * folder keeps it from signed-out visitors, for the team), with no password,
 * comments or approval, living pg_proto_link_phone_seconds() from its
 * making; that span is the only mark it carries. One per person, design and
 * audience is found again while it lives; "End now" revokes it, and so does
 * the design's delete, as for every share link.
 *
 * The changed-since address is prototype.php?watch=1&w=<link id>&p=<page
 * id>&t=<start>&sig=…&b=…&since=<save time>: signed with the site's key over
 * the link's id, the page and the start of the watch, bound to the session
 * cookie of the browser it was made for (as the link's frame is,
 * pg_proto_frame_bind()), and never carrying the token. It runs with no
 * session, reads one row by the link's primary key (the page joined by its
 * own), writes nothing and counts no use of the link. Its answer tells the
 * phone when to ask next: every 10 seconds, every 30 after ten minutes
 * without a save, and stop an hour after the watch began. A link that no
 * longer opens, a page of another design and a forged or foreign address all
 * get the same 404.
 *
 * Loaded by the designer's endpoint (prototype_phone, prototype_phone_end),
 * by prototype.php for the watch, and by the tests.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_PROTOTYPE_ENTRY')) {
    exit;
}

/* ---------------------------------------------------------------------------
   The phone link
   --------------------------------------------------------------------------- */

/**
 * The link a press of "Preview on a phone" makes, in the shape
 * pg_proto_link_issue() takes: no password, comments or approval, eight
 * hours from now, starting on the page being edited (the design's first
 * draft when that page is no draft of it). Pure.
 *
 * @param string $audience      'anyone' or 'team'
 * @param int    $start_page_id the page being edited
 * @param int[]  $draft_ids     the design's drafts
 * @param int    $now
 * @return array|null audience, start_page_id, created_at, expires_at,
 *                    password, allow_comments; null for an unknown audience
 *                    or a design with no drafts
 */
function pg_proto_phone_plan($audience, $start_page_id, $draft_ids, $now)
{
    if (!is_string($audience) || !in_array($audience, pg_proto_link_audiences(), true)) {
        return null;
    }

    $drafts = array();

    foreach ((array) $draft_ids as $id) {
        if ((int) $id > 0) {
            $drafts[(int) $id] = true;
        }
    }

    if (!$drafts) {
        return null;
    }

    $start = (int) $start_page_id;

    return array(
        'audience' => $audience,
        'start_page_id' => isset($drafts[$start]) ? $start : 0,
        'created_at' => (int) $now,
        'expires_at' => (int) $now + pg_proto_link_phone_seconds(),
        'password' => '',
        'allow_comments' => 0,
    );
}

/**
 * The person's phone link of a design that still opens, for one audience:
 * the newest. Pure.
 *
 * @param array[] $rows     the design's links (pg_proto_links_of_design())
 * @param int     $user_id
 * @param string  $audience
 * @param int     $now
 * @return array|null
 */
function pg_proto_phone_pick($rows, $user_id, $audience, $now)
{
    $found = null;

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !pg_proto_link_is_phone($row) || ((int) $row['created_by'] !== (int) $user_id)
            || ((string) $row['audience'] !== (string) $audience) || (pg_proto_link_state($row, $now) !== 'ok')) {
            continue;
        }

        if (($found === null) || ((int) $row['id'] > (int) $found['id'])) {
            $found = $row;
        }
    }

    return $found;
}

/**
 * The page a found phone link opens on: its start page while it is a draft
 * of the design, the first draft when it starts on none; 0 when its start
 * page is no longer a draft (binned, deleted, moved to another design) or
 * the design has none. Pure.
 *
 * @param array $row       prototype_links row
 * @param int[] $draft_ids the design's drafts (pg_proto_design_pages())
 * @return int
 */
function pg_proto_phone_found_start($row, $draft_ids)
{
    $drafts = array();

    foreach ((array) $draft_ids as $id) {
        if ((int) $id > 0) {
            $drafts[] = (int) $id;
        }
    }

    if (!is_array($row) || !$drafts) {
        return 0;
    }

    $start = isset($row['start_page_id']) ? (int) $row['start_page_id'] : 0;

    if ($start === 0) {
        return $drafts[0];
    }

    return in_array($start, $drafts, true) ? $start : 0;
}

/**
 * Whether a found phone link is handed out again: its start page is still a
 * draft (pg_proto_phone_found_start()) and its audience is drawn that page
 * (pg_proto_link_page_shown()). One that is not would open the phone on the
 * lock note or the first draft; it is ended and a new one made, as one
 * whose address can no longer be shown. Pure.
 *
 * @param array|null $row                 the found link
 * @param int[]      $draft_ids           the design's drafts
 * @param string     $access_control_type of the link's start page's folder
 * @return bool
 */
function pg_proto_phone_found_keeps($row, $draft_ids, $access_control_type)
{
    if (pg_proto_phone_found_start($row, $draft_ids) <= 0) {
        return false;
    }

    return pg_proto_link_page_shown(isset($row['audience']) ? (string) $row['audience'] : '', (string) $access_control_type);
}

/**
 * What the phone window shows of its link, with nothing that opens it: the
 * id, audience, start page, hint and the seconds it has left. Pure.
 *
 * @param array $row
 * @param int   $now
 * @return array
 */
function pg_proto_phone_summary($row, $now)
{
    return array(
        'id' => (int) $row['id'],
        'audience' => (string) $row['audience'],
        'start_page_id' => (int) $row['start_page_id'],
        'hint' => (string) $row['token_hint'],
        'expires_at' => (int) $row['expires_at'],
        'seconds_left' => max(0, (int) $row['expires_at'] - (int) $now),
        'phone' => true,
    );
}

/* ---------------------------------------------------------------------------
   The changed-since address
   --------------------------------------------------------------------------- */

/**
 * How long one watch lasts, in seconds: an hour. The phone stops asking
 * then; opening the page again starts a new watch.
 *
 * @return int
 */
function pg_proto_phone_watch_window()
{
    return 3600;
}

/**
 * How long the phone waits before it asks again, in seconds: 10 while the
 * draft was saved in the last ten minutes (or the watch is younger), 30
 * after that. Pure.
 *
 * @param int $started the watch's start
 * @param int $saved   the draft's last save
 * @param int $now
 * @return int
 */
function pg_proto_phone_watch_delay($started, $saved, $now)
{
    return (((int) $now - max((int) $started, (int) $saved)) >= 600) ? 30 : 10;
}

/**
 * The signature of a watch address: the link's id, the page and the start,
 * under its own prefix, so neither a frame's nor a person's signature opens
 * a watch, nor the other way round.
 *
 * @param int    $link_id
 * @param int    $page_id
 * @param int    $started
 * @param string $key ENCRYPTION_KEY
 * @return string 64 hex characters, '' when any part is missing
 */
function pg_proto_phone_watch_sign($link_id, $page_id, $started, $key)
{
    if (((int) $link_id <= 0) || ((int) $page_id <= 0) || ((int) $started <= 0) || ((string) $key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'prototype-phone-watch:' . (int) $link_id . ':' . (int) $page_id . ':' . (int) $started, (string) $key);
}

/**
 * The query string of a watch address, without the leading "?": no token,
 * no session id; b= binds it to the session cookie of the browser that
 * opened the link. '' when it cannot be signed (no key).
 *
 * @param int    $link_id
 * @param int    $page_id
 * @param int    $started now, when the viewer draws the page
 * @param string $key
 * @param string $client the viewer's session id
 * @return string
 */
function pg_proto_phone_watch_query($link_id, $page_id, $started, $key, $client)
{
    $signature = pg_proto_phone_watch_sign($link_id, $page_id, $started, $key);

    if ($signature === '') {
        return '';
    }

    return 'watch=1&w=' . (int) $link_id . '&p=' . (int) $page_id . '&t=' . (int) $started
        . '&sig=' . $signature . '&b=' . rawurlencode(pg_proto_frame_bind($signature, (string) $client, (string) $key));
}

/**
 * Reads a watch address from the request: link, page and start as plain
 * positive whole numbers, a 64-character hex signature; the binding and the
 * save time the phone drew (since) are optional and read as none when
 * malformed. Pure.
 *
 * @param mixed $source $_GET
 * @return array|null w, p, t, since (int), sig, b (string)
 */
function pg_proto_phone_watch_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $parsed = array();

    foreach (array('w', 'p', 't') as $name) {
        $value = isset($source[$name]) ? $source[$name] : null;

        if (!is_string($value) || (preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1)) {
            return null;
        }

        $parsed[$name] = (int) $value;
    }

    $signature = isset($source['sig']) ? $source['sig'] : null;

    if (!is_string($signature) || (preg_match('/^[0-9a-f]{64}$/', $signature) !== 1)) {
        return null;
    }

    $parsed['sig'] = $signature;

    $bind = isset($source['b']) ? $source['b'] : '';
    $parsed['b'] = (is_string($bind) && (preg_match('/^[0-9a-f]{64}$/', $bind) === 1)) ? $bind : '';

    $since = isset($source['since']) ? $source['since'] : '';
    $parsed['since'] = (is_string($since) && (preg_match('/^[1-9][0-9]{0,9}$/', $since) === 1)) ? (int) $since : 0;

    return $parsed;
}

/**
 * What a watch address can do now: 'ok'; 'over' an hour after its start;
 * 'invalid' when it is not this site's signature or starts more than a
 * minute ahead of the clock; 'unbound' when asked from another browser.
 *
 * @param array|null  $parsed pg_proto_phone_watch_parse()
 * @param string      $key
 * @param int         $now
 * @param string|null $client the asking browser's session cookie; null checks
 *                            the signature alone
 * @return string
 */
function pg_proto_phone_watch_state($parsed, $key, $now, $client = null)
{
    if (!is_array($parsed) || !isset($parsed['w'], $parsed['p'], $parsed['t'], $parsed['sig'])) {
        return 'invalid';
    }

    $expected = pg_proto_phone_watch_sign($parsed['w'], $parsed['p'], $parsed['t'], $key);

    if (($expected === '') || !hash_equals($expected, (string) $parsed['sig'])) {
        return 'invalid';
    }

    if ((int) $parsed['t'] > ((int) $now + 60)) {
        return 'invalid';
    }

    if ($client !== null) {
        $bind = pg_proto_frame_bind($parsed['sig'], $client, $key);

        if (($bind === '') || !isset($parsed['b']) || !hash_equals($bind, (string) $parsed['b'])) {
            return 'unbound';
        }
    }

    if (((int) $now - (int) $parsed['t']) >= pg_proto_phone_watch_window()) {
        return 'over';
    }

    return 'ok';
}

/**
 * The watch's one read: the link by its primary key, with the page's last
 * save (pg_phone_saved) joined by the page's own key, and only when the page
 * belongs to the link's design (NULL otherwise). Nothing is written.
 *
 * @param int $link_id
 * @param int $page_id
 * @return string
 */
function pg_proto_phone_watch_sql($link_id, $page_id)
{
    return "SELECT l.*, p.page_timestamp AS pg_phone_saved FROM prototype_links l"
        . " LEFT JOIN page p ON p.page_id = '" . (int) $page_id . "' AND p.page_style = l.style_id"
        . " WHERE l.id = '" . (int) $link_id . "' LIMIT 1";
}

/**
 * The watch's answer from its row: 200 with changed (saved after since),
 * at (the last save), next (seconds to wait) and stop; the same 404 for a
 * link that does not open (unknown, revoked, expired, no phone link), a page
 * not of its design, a forged address and one from another browser. Pure.
 *
 * @param array|null $row    pg_proto_phone_watch_sql()'s row
 * @param array      $parsed pg_proto_phone_watch_parse()
 * @param int        $now
 * @param string     $state  pg_proto_phone_watch_state(), 'ok' or 'over'
 * @return array status, body
 */
function pg_proto_phone_watch_answer($row, $parsed, $now, $state = 'ok')
{
    $gone = array('status' => 404, 'body' => array('ok' => false));

    if ((($state !== 'ok') && ($state !== 'over')) || !is_array($row) || !pg_proto_link_is_phone($row)
        || (pg_proto_link_state($row, $now) !== 'ok') || !isset($row['pg_phone_saved'])) {
        return $gone;
    }

    $saved = (int) $row['pg_phone_saved'];
    $since = (int) $parsed['since'];
    $over = ($state === 'over') || (((int) $now - (int) $parsed['t']) >= pg_proto_phone_watch_window());

    return array('status' => 200, 'body' => array(
        'ok' => true,
        'changed' => ($since > 0) && ($saved > $since),
        'at' => $saved,
        'next' => $over ? 0 : pg_proto_phone_watch_delay($parsed['t'], $saved, $now),
        'stop' => $over,
    ));
}

/**
 * Answers prototype.php?watch=1: checks the address, reads its one row and
 * sends the JSON answer. A forged signature counts as the frame's does
 * (pg_proto_frame_offence()); nothing else is logged or written, and no use
 * of the link is counted.
 *
 * @param array  $get    $_GET
 * @param string $key
 * @param string $client the session cookie the request carried
 * @param int    $now
 * @return void
 */
function pg_proto_phone_watch_run($get, $key, $client, $now)
{
    $parsed = pg_proto_phone_watch_parse($get);
    $state = pg_proto_phone_watch_state($parsed, $key, $now, (string) $client);
    $row = null;

    if ($state === 'invalid') {
        if (is_array($parsed)) {
            pg_proto_frame_offence();
        }
    } elseif (($state !== 'unbound') && pg_proto_links_ready()) {
        $row = db_item(pg_proto_phone_watch_sql($parsed['w'], $parsed['p']));
    }

    $answer = pg_proto_phone_watch_answer(is_array($row) ? $row : null, is_array($parsed) ? $parsed : array(), $now, $state);

    http_response_code($answer['status']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($answer['body']);
}

/* ---------------------------------------------------------------------------
   The designer's endpoint
   --------------------------------------------------------------------------- */

/**
 * The designer endpoint's phone sub-actions (api.php, action designer), for
 * administrators and designers, the roles that make share links:
 * prototype_phone finds the person's phone link of the design again, or
 * makes it (style_id, page_id, audience 'anyone' or 'team'); for anyone, a
 * page whose folder keeps it from signed-out visitors answers team_only and
 * makes nothing. prototype_phone_end revokes the person's phone link
 * (link_id).
 *
 * @param string $sub
 * @param array  $request
 * @param array  $user validate_user()
 * @return array the answer to respond()
 */
function pg_proto_phone_panel($sub, $request, $user)
{
    if (!isset($user['role'], $user['id']) || ((int) $user['role'] > 1)) {
        return array('status' => 'error', 'message' => lang('Permission denied.'));
    }

    if (!pg_proto_links_ready()) {
        return array('status' => 'error', 'message' => lang('This site has not been upgraded to the version that shares prototypes.'));
    }

    $now = time();

    if ($sub === 'prototype_phone_end') {
        $row = pg_proto_link_get(isset($request['link_id']) ? (int) $request['link_id'] : 0);

        if (($row === null) || !pg_proto_link_is_phone($row) || ((int) $row['created_by'] !== (int) $user['id'])) {
            return array('status' => 'error', 'message' => lang('The link could not be found.'));
        }

        pg_proto_link_revoke($row, (int) $user['id'], (string) $user['username']);

        return array('status' => 'success', 'id' => (int) $row['id']);
    }

    $style_id = isset($request['style_id']) ? (int) $request['style_id'] : 0;

    if (pg_proto_link_design_name($style_id) === null) {
        return array('status' => 'error', 'message' => lang('The design could not be found.'));
    }

    $draft_ids = array();
    $pages = pg_proto_design_pages($style_id);

    foreach ($pages as $page) {
        $draft_ids[] = (int) $page['id'];
    }

    $audience = (isset($request['audience']) && ($request['audience'] === 'team')) ? 'team' : 'anyone';
    $plan = pg_proto_phone_plan($audience, isset($request['page_id']) ? (int) $request['page_id'] : 0, $draft_ids, $now);

    if ($plan === null) {
        return array('status' => 'error', 'message' => pg_proto_link_error_message('no_drafts'));
    }

    $row = pg_proto_phone_pick(pg_proto_links_of_design($style_id), (int) $user['id'], $audience, $now);

    // A found link whose own start page no longer opens for its audience
    // (moved behind a sign-in) or is no longer a draft would show the phone
    // the lock note or another page: it is ended, and the press goes on as
    // if none was found.
    if ($row !== null) {
        $found_start = pg_proto_phone_found_start($row, $draft_ids);

        if (!pg_proto_phone_found_keeps($row, $draft_ids, ($found_start > 0) ? pg_proto_link_page_access($found_start) : '')) {
            pg_proto_link_revoke($row, (int) $user['id'], (string) $user['username']);
            $row = null;
        }
    }

    // A link for anyone draws a page only when it goes live where signed-out
    // visitors may see it (pg_proto_link_page_shown()): the window offers a
    // team link instead.
    $start = ($plan['start_page_id'] > 0) ? $plan['start_page_id'] : $draft_ids[0];

    if (!pg_proto_link_page_shown($audience, pg_proto_link_page_access($start))) {
        return array('status' => 'success', 'team_only' => true, 'start_page' => pg_proto_phone_start_name($pages, $start));
    }

    $token = ($row === null) ? '' : pg_proto_link_token($row);

    // A link whose address can no longer be shown (no key, or a key changed
    // since) is not shown again: it is ended and a new one is made, so a
    // person keeps one phone link per design and audience.
    if ($token === '') {
        if ($row !== null) {
            pg_proto_link_revoke($row, (int) $user['id'], (string) $user['username']);
        }

        $made = pg_proto_link_issue($style_id, $plan, (int) $user['id'], (string) $user['username']);

        if ($made === null) {
            // The only error a second press can mend: the window offers
            // "Try again" for this answer alone.
            return array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.'), 'retry' => true);
        }

        $row = $made['row'];
        $token = $made['token'];
    }

    $url = pg_proto_link_url($token);
    $link = pg_proto_phone_summary($row, $now);
    $link['start_page'] = pg_proto_phone_start_name($pages, $link['start_page_id']);

    // The code is drawn here as inline SVG: the address it holds opens the
    // prototype, so it never goes to an image address or another service.
    return array(
        'status' => 'success',
        'link' => $link,
        'url' => $url,
        'qr' => pg_qr_svg($url, 0, array('label' => lang('QR code of the phone preview link'))),
    );
}

/**
 * The name the phone window gives the link's start page: the page's title,
 * else its name; the first draft when the link starts there (0) or on a
 * page no longer among the drafts. Pure.
 *
 * @param array[] $pages         the design's drafts (pg_proto_design_pages())
 * @param int     $start_page_id
 * @return string '' for a design with no drafts
 */
function pg_proto_phone_start_name($pages, $start_page_id)
{
    $first = null;

    foreach ((array) $pages as $page) {
        if (!is_array($page) || !isset($page['id'])) {
            continue;
        }

        if ($first === null) {
            $first = $page;
        }

        if ((int) $page['id'] === (int) $start_page_id) {
            $first = $page;
            break;
        }
    }

    if ($first === null) {
        return '';
    }

    $title = isset($first['title']) ? trim((string) $first['title']) : '';

    return ($title !== '') ? $title : (isset($first['name']) ? (string) $first['name'] : '');
}

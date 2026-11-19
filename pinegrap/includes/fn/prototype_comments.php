<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Comments a guest pins on a design's prototype opened by its share link
 * (prototype.php?link=<token>), and the designer's answers to them.
 *
 * A comment is a row of prototype_comments, never a note in the page's tree:
 * its link, design, draft page, the node it is about and the place inside
 * that node (two percentages kept in hundredths), the name the guest gave,
 * the plain text, the comment it answers (0 for the first of a thread), when
 * it was written, and when and by whom its thread was resolved. The guest's
 * address is not kept. A link takes comments only when its "Allow comments"
 * (prototype_links.allow_comments) is on.
 *
 * A guest writes through prototype_comment.php alone, which admits a post
 * only from the session that opened the link (past its password and its team
 * gate), with that session's form token, from this site's own pages, about a
 * page the link shows, and within the limits below. The link's token is never
 * sent there: the post names the link by its id, and the session's note of
 * the token's hash (pg_proto_link_session_mark()) is what opens it.
 *
 * Comments go with their design: every path that deletes a style row calls
 * pg_proto_comments_delete_design() beside pg_proto_links_revoke_design().
 * A revoked or expired link keeps its comments for the designer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Rules a comment is checked by
   --------------------------------------------------------------------------- */

/**
 * The limits of a guest's comment: the name and text lengths in characters,
 * the comments one address may send in a window, and the open guest comments
 * a link may hold.
 *
 * @return array name, text, burst, burst_window (seconds), open_per_link
 */
function pg_proto_comment_limits()
{
    return array(
        'name' => 60,
        'text' => 1000,
        'burst' => 30,
        'burst_window' => 600,
        'open_per_link' => 200,
    );
}

/**
 * A text as it is kept: a string of valid UTF-8 with its line breaks made
 * \n, every other control character taken out and the ends trimmed. It stays
 * plain text, written out escaped wherever it is shown: markup in it is kept
 * as the characters the guest typed and never drawn. null for a value that
 * is not a string or not UTF-8.
 *
 * @param mixed $value
 * @param bool  $multiline whether line breaks are kept (else made spaces)
 * @return string|null
 */
function pg_proto_comment_plain($value, $multiline)
{
    if (!is_string($value) || !preg_match('//u', $value)) {
        return null;
    }

    $value = str_replace(array("\r\n", "\r"), "\n", $value);

    if (!$multiline) {
        $value = str_replace(array("\n", "\t"), ' ', $value);
    }

    // C0 controls but tab and line feed, DEL, C1 controls, and the
    // direction overrides and isolates that can make a name read as another.
    $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);

    return trim((string) $value);
}

/**
 * A place inside a node as it is kept: a percentage from 0 to 100, given as a
 * number or a decimal string, in hundredths (0 to 10000). null for anything
 * else (out of bounds, not a number, an array, INF, NaN).
 *
 * @param mixed $value
 * @return int|null
 */
function pg_proto_comment_place($value)
{
    if (is_int($value) || is_float($value)) {
        $number = (float) $value;
    } elseif (is_string($value) && preg_match('/^\d{1,3}(\.\d{1,6})?$/', $value)) {
        $number = (float) $value;
    } else {
        return null;
    }

    if (is_nan($number) || is_infinite($number) || ($number < 0) || ($number > 100)) {
        return null;
    }

    return (int) round($number * 100);
}

/**
 * An id as it was posted: an int or digits gives it, nothing (absent or '')
 * gives 0, anything else -1.
 *
 * @param mixed $value
 * @return int
 */
function pg_proto_comment_id($value)
{
    if (($value === null) || ($value === '')) {
        return 0;
    }

    if (is_int($value)) {
        return ($value >= 0) ? $value : -1;
    }

    return (is_string($value) && preg_match('/^[0-9]{1,10}$/', $value)) ? (int) $value : -1;
}

/**
 * Whether a node id has the shape the editor gives one (the page tree's
 * _id): letters, digits, _ and -, at most 64.
 *
 * @param mixed $value
 * @return bool
 */
function pg_proto_comment_node_shape($value)
{
    return is_string($value) && (bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value);
}

/**
 * Whether a posted form token is the session's (constant time; an empty
 * session token matches nothing).
 *
 * @param mixed $session_token
 * @param mixed $posted
 * @return bool
 */
function pg_proto_comment_token_ok($session_token, $posted)
{
    return is_string($session_token) && ($session_token !== '') && is_string($posted)
        && hash_equals($session_token, $posted);
}

/**
 * Whether a session opened a link past its gates: the mark prototype.php
 * writes (pg_proto_link_session_mark()) only after the password and the team
 * gate, holding the hash of the link's own token. It is the password's proof
 * at the door: the cookie a right password leaves is set for prototype.php
 * alone and never reaches prototype_comment.php, and a link's password is
 * never changed after it is made.
 *
 * @param mixed $session    $_SESSION
 * @param array $link       prototype_links row
 * @return bool
 */
function pg_proto_comment_session_opens($session, $link)
{
    $id = (is_array($link) && isset($link['id'])) ? (int) $link['id'] : 0;
    $mark = (is_array($session) && isset($session['software']['prototype_link_open'][$id])) ? $session['software']['prototype_link_open'][$id] : null;

    return ($id > 0) && pg_proto_link_session_opens($mark, $link);
}

/**
 * Which gate of the door a post stops at, in the order they are passed; ''
 * when it passes them all. Pure: the request's facts are read by the caller.
 *
 * method       the request's method
 * same_origin  pg_consent_answer_same_origin() on the request
 * token_ok     pg_proto_comment_token_ok()
 * state        pg_proto_link_state() of the link named, 'gone' when its
 *              design is gone, 'unknown' when there is no such link
 * session      pg_proto_comment_session_opens(): this session opened the
 *              link past its password (the mark is written only there)
 * team_ok      not a team link, or a signed-in role 0-2
 * allowed      the link's "Allow comments"
 * page_shown   the page is one of the link's drafts and the link shows it
 *
 * @param array $facts
 * @return string method, origin, token, link, session, comments_off, page
 */
function pg_proto_comment_door($facts)
{
    $f = array_merge(array(
        'method' => '', 'same_origin' => false, 'token_ok' => false, 'state' => 'unknown',
        'session' => false, 'team_ok' => false, 'allowed' => false, 'page_shown' => false,
    ), (array) $facts);

    if (strtoupper((string) $f['method']) !== 'POST') {
        return 'method';
    }

    if (!$f['same_origin']) {
        return 'origin';
    }

    if (!$f['token_ok']) {
        return 'token';
    }

    // Revoked, expired, gone and unknown: one answer, as the viewer gives.
    if ((string) $f['state'] !== 'ok') {
        return 'link';
    }

    // The session never opened the link past its gates, or was signed out
    // of a team link since: the viewer would not draw it now.
    if (!$f['session'] || !$f['team_ok']) {
        return 'session';
    }

    if (!$f['allowed']) {
        return 'comments_off';
    }

    if (!$f['page_shown']) {
        return 'page';
    }

    return '';
}

/**
 * Which limit refuses a comment that passed the door: 'rate' when its
 * address went past the burst (counted by the caller, this post included),
 * 'full' when the link already holds the open guest comments it may, else ''.
 *
 * @param bool $burst_exceeded
 * @param int  $open_count pg_proto_comments_open_count()
 * @return string
 */
function pg_proto_comment_limit_refusal($burst_exceeded, $open_count)
{
    $limits = pg_proto_comment_limits();

    if ($burst_exceeded) {
        return 'rate';
    }

    if ((int) $open_count >= $limits['open_per_link']) {
        return 'full';
    }

    return '';
}

/**
 * Checks a guest's comment as it was posted and gives the row to write.
 * A first comment brings its node and place; an answer (parent) takes them
 * from the comment it answers, which must be the first of an open thread of
 * the same link and page.
 *
 * input   page_id, node, x, y, name, text, parent
 * $parent the row of the comment answered (pg_proto_comment_get()), or null
 *
 * @param array      $input
 * @param array      $link   prototype_links row
 * @param array|null $parent
 * @return array ok, error (name, text, page, node, place, parent), row
 */
function pg_proto_comment_check($input, $link, $parent = null)
{
    $limits = pg_proto_comment_limits();
    $input = (array) $input;
    $fail = function ($code) {
        return array('ok' => false, 'error' => $code, 'row' => null);
    };

    $name = pg_proto_comment_plain(isset($input['name']) ? $input['name'] : null, false);

    if (($name === null) || ($name === '') || (mb_strlen($name, 'UTF-8') > $limits['name'])) {
        return $fail('name');
    }

    $text = pg_proto_comment_plain(isset($input['text']) ? $input['text'] : null, true);

    if (($text === null) || ($text === '') || (mb_strlen($text, 'UTF-8') > $limits['text'])) {
        return $fail('text');
    }

    $page_id = pg_proto_comment_id(isset($input['page_id']) ? $input['page_id'] : null);
    $parent_id = pg_proto_comment_id(isset($input['parent']) ? $input['parent'] : null);

    if ($page_id <= 0) {
        return $fail('page');
    }

    if ($parent_id < 0) {
        return $fail('parent');
    }

    $row = array(
        'link_id' => (int) $link['id'],
        'style_id' => (int) $link['style_id'],
        'page_id' => (int) $page_id,
        'parent_id' => (int) $parent_id,
        'author_name' => $name,
        'user_id' => 0,
        'body' => $text,
    );

    if ($parent_id > 0) {
        if (!is_array($parent) || ((int) $parent['id'] !== $parent_id) || ((int) $parent['link_id'] !== (int) $link['id'])
            || ((int) $parent['parent_id'] !== 0) || ((int) $parent['resolved_at'] !== 0) || ((int) $parent['page_id'] !== (int) $page_id)) {
            return $fail('parent');
        }

        $row['node_id'] = (string) $parent['node_id'];
        $row['pos_x'] = (int) $parent['pos_x'];
        $row['pos_y'] = (int) $parent['pos_y'];

        return array('ok' => true, 'error' => '', 'row' => $row);
    }

    if (!pg_proto_comment_node_shape(isset($input['node']) ? $input['node'] : null)) {
        return $fail('node');
    }

    $x = pg_proto_comment_place(isset($input['x']) ? $input['x'] : null);
    $y = pg_proto_comment_place(isset($input['y']) ? $input['y'] : null);

    if (($x === null) || ($y === null)) {
        return $fail('place');
    }

    $row['node_id'] = (string) $input['node'];
    $row['pos_x'] = $x;
    $row['pos_y'] = $y;

    return array('ok' => true, 'error' => '', 'row' => $row);
}

/**
 * What the door answers for a refusal, as the viewer shows it.
 *
 * @param string $code pg_proto_comment_door(), pg_proto_comment_check() or
 *                     pg_proto_comment_limit_refusal()
 * @return string
 */
function pg_proto_comment_error_message($code)
{
    switch ((string) $code) {
        case 'name':
            return lang('Write your name, at most 60 characters.');
        case 'text':
            return lang('Write a comment of at most 1,000 characters.');
        case 'rate':
            return lang('Too many comments in a short time. Try again in a few minutes.');
        case 'full':
            return lang('This prototype has too many open comments.');
        case 'comments_off':
            return lang('Comments are not open on this prototype.');
        case 'session':
        case 'token':
            return lang('Open the prototype again from its link, then write your comment.');
        case 'link':
            return lang('This link no longer works.');
    }

    return lang('Sorry, we could not accept your request.');
}

/**
 * The status a refusal is answered with.
 *
 * @param string $code
 * @return int
 */
function pg_proto_comment_error_status($code)
{
    $map = array(
        'method' => 405, 'origin' => 403, 'token' => 403, 'link' => 404, 'session' => 403,
        'comments_off' => 403, 'page' => 404, 'rate' => 429, 'full' => 409, 'size' => 413,
    );

    return isset($map[(string) $code]) ? $map[(string) $code] : 400;
}

/**
 * A comment as the viewer and the designer get it: the place back in
 * percentages, the guest's address never (it is not kept).
 *
 * @param array $row prototype_comments row
 * @return array
 */
function pg_proto_comment_public($row)
{
    return array(
        'id' => (int) $row['id'],
        'link_id' => (int) $row['link_id'],
        'page_id' => (int) $row['page_id'],
        'node' => (string) $row['node_id'],
        'x' => ((int) $row['pos_x']) / 100,
        'y' => ((int) $row['pos_y']) / 100,
        'parent' => (int) $row['parent_id'],
        'name' => (string) $row['author_name'],
        'staff' => ((int) $row['user_id'] > 0),
        'text' => (string) $row['body'],
        'created_at' => (int) $row['created_at'],
        'resolved_at' => (int) $row['resolved_at'],
    );
}

/* ---------------------------------------------------------------------------
   The table
   --------------------------------------------------------------------------- */

/**
 * Does the database carry prototype comments and the links' "Allow
 * comments" (2026.11.0, 11.0.225)?
 *
 * @return bool
 */
function pg_proto_comments_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('prototype_comments', 'body') && pg_schema_has('prototype_links', 'allow_comments');
    }

    return $ready;
}

/**
 * Whether a link takes comments: its "Allow comments" is on and the table
 * is there.
 *
 * @param array $link prototype_links row
 * @return bool
 */
function pg_proto_link_comments_allowed($link)
{
    return is_array($link) && !empty($link['allow_comments']) && pg_proto_comments_ready();
}

/**
 * One comment by its id.
 *
 * @param int $comment_id
 * @return array|null
 */
function pg_proto_comment_get($comment_id)
{
    if (((int) $comment_id <= 0) || !pg_proto_comments_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM prototype_comments WHERE id = '" . (int) $comment_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Writes a checked comment (pg_proto_comment_check()'s row, or an answer's)
 * and gives its id; 0 when it could not be written.
 *
 * @param array $row
 * @param int   $now
 * @return int
 */
function pg_proto_comment_save($row, $now = 0)
{
    if (!pg_proto_comments_ready()) {
        return 0;
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    $ok = db_q(
        "INSERT INTO prototype_comments (link_id, style_id, page_id, node_id, pos_x, pos_y, parent_id, author_name, user_id, body, created_at, resolved_at, resolved_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
        array(
            (int) $row['link_id'], (int) $row['style_id'], (int) $row['page_id'], (string) $row['node_id'],
            (int) $row['pos_x'], (int) $row['pos_y'], (int) $row['parent_id'], (string) $row['author_name'],
            (int) $row['user_id'], (string) $row['body'], $now, isset($row['resolved_at']) ? (int) $row['resolved_at'] : 0,
        )
    );

    return $ok ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * How many guest comments of a link are open (their thread not resolved):
 * what the link's limit counts.
 *
 * @param int $link_id
 * @return int
 */
function pg_proto_comments_open_count($link_id)
{
    if (!pg_proto_comments_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM prototype_comments WHERE link_id = '" . (int) $link_id . "' AND resolved_at = 0 AND user_id = 0");
}

/**
 * The comments of a link, oldest first, as its viewer shows them.
 *
 * @param int $link_id
 * @return array[] prototype_comments rows
 */
function pg_proto_comments_of_link($link_id)
{
    if (!pg_proto_comments_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_comments WHERE link_id = '" . (int) $link_id . "' ORDER BY id LIMIT 2000");

    return is_array($rows) ? $rows : array();
}

/**
 * The comments of one page as a link's viewer shows them, oldest first: read
 * by page and link (idx_page), never the link's whole list, which grows as
 * resolved comments are kept.
 *
 * @param int $link_id
 * @param int $page_id
 * @return array[] prototype_comments rows
 */
function pg_proto_comments_of_link_page($link_id, $page_id)
{
    if (!pg_proto_comments_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_comments WHERE page_id = '" . (int) $page_id . "' AND link_id = '" . (int) $link_id . "' ORDER BY id LIMIT 2000");

    return is_array($rows) ? $rows : array();
}

/**
 * The comments of a draft page over all its design's links, oldest first,
 * as the designer's list shows them.
 *
 * @param int $page_id
 * @return array[] prototype_comments rows
 */
function pg_proto_comments_of_page($page_id)
{
    if (!pg_proto_comments_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_comments WHERE page_id = '" . (int) $page_id . "' ORDER BY id LIMIT 2000");

    return is_array($rows) ? $rows : array();
}

/**
 * The designer's answer in a thread: written under the thread's first
 * comment with its link, page, node and place, the staff member's name and
 * id, and the thread's resolved state. 0 when there is no such thread or
 * the text is empty or too long.
 *
 * @param int    $comment_id the thread's first comment (or any of its own)
 * @param mixed  $text
 * @param int    $user_id
 * @param string $username
 * @return int the answer's id
 */
function pg_proto_comment_answer($comment_id, $text, $user_id, $username)
{
    $limits = pg_proto_comment_limits();
    $root = pg_proto_comment_get($comment_id);

    if ($root && ((int) $root['parent_id'] > 0)) {
        $root = pg_proto_comment_get($root['parent_id']);
    }

    $text = pg_proto_comment_plain($text, true);

    if (!$root || ($text === null) || ($text === '') || (mb_strlen($text, 'UTF-8') > $limits['text']) || ((int) $user_id <= 0)) {
        return 0;
    }

    return pg_proto_comment_save(array(
        'link_id' => (int) $root['link_id'],
        'style_id' => (int) $root['style_id'],
        'page_id' => (int) $root['page_id'],
        'node_id' => (string) $root['node_id'],
        'pos_x' => (int) $root['pos_x'],
        'pos_y' => (int) $root['pos_y'],
        'parent_id' => (int) $root['id'],
        'author_name' => mb_substr((string) $username, 0, $limits['name'], 'UTF-8'),
        'user_id' => (int) $user_id,
        'body' => $text,
        'resolved_at' => (int) $root['resolved_at'],
    ));
}

/**
 * Resolves a thread (or reopens it with $resolved false): its first comment
 * and its answers together, so a link's open count leaves the whole thread.
 *
 * @param int  $comment_id the thread's first comment
 * @param int  $user_id
 * @param bool $resolved
 * @param int  $now
 * @return bool whether a thread was changed
 */
function pg_proto_comment_resolve($comment_id, $user_id, $resolved = true, $now = 0)
{
    $root = pg_proto_comment_get($comment_id);

    if (!$root || ((int) $root['parent_id'] > 0)) {
        return false;
    }

    $now = ((int) $now > 0) ? (int) $now : time();
    $id = (int) $root['id'];

    db("UPDATE prototype_comments SET resolved_at = '" . ($resolved ? $now : 0) . "', resolved_by = '" . ($resolved ? (int) $user_id : 0) . "'
        WHERE id = '" . $id . "' OR parent_id = '" . $id . "'");

    return max(0, (int) mysqli_affected_rows(db::$con)) > 0;
}

/**
 * Deletes a comment; the first of a thread takes its answers with it.
 *
 * @param int $comment_id
 * @return int how many rows were deleted
 */
function pg_proto_comment_delete($comment_id)
{
    $row = pg_proto_comment_get($comment_id);

    if (!$row) {
        return 0;
    }

    $id = (int) $row['id'];
    $sql = ((int) $row['parent_id'] === 0)
        ? "DELETE FROM prototype_comments WHERE id = '" . $id . "' OR parent_id = '" . $id . "'"
        : "DELETE FROM prototype_comments WHERE id = '" . $id . "'";

    db($sql);

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * Deletes every comment of a link (the links list's "Delete this link's
 * comments").
 *
 * @param int $link_id
 * @return int how many rows were deleted
 */
function pg_proto_comments_delete_link($link_id)
{
    if (((int) $link_id <= 0) || !pg_proto_comments_ready()) {
        return 0;
    }

    db("DELETE FROM prototype_comments WHERE link_id = '" . (int) $link_id . "'");

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * The statement that deletes the comments of designs; '' for no design.
 *
 * @param int|int[] $style_ids
 * @return string
 */
function pg_proto_comments_delete_design_sql($style_ids)
{
    $ids = array();

    foreach ((array) $style_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return $ids ? "DELETE FROM prototype_comments WHERE style_id IN (" . implode(',', $ids) . ")" : '';
}

/**
 * Deletes the comments of designs whose style rows are being deleted. Called
 * beside pg_proto_links_revoke_design() on every such path: a later design
 * can be given the same id.
 *
 * @param int|int[] $style_ids
 * @return int how many rows were deleted
 */
function pg_proto_comments_delete_design($style_ids)
{
    $sql = pg_proto_comments_delete_design_sql($style_ids);

    if (($sql === '') || !pg_proto_comments_ready()) {
        return 0;
    }

    db($sql);

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * The statement that reads the stamp of some draft pages; '' for no page.
 * The stamp is the newest comment's id, not its time: a second comment
 * written in the same second changes it too (idx_page is page_id, id).
 *
 * @param int|int[] $page_ids
 * @return string
 */
function pg_proto_comments_page_stamp_sql($page_ids)
{
    $ids = array();

    foreach ((array) $page_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return $ids ? "SELECT MAX(id) FROM prototype_comments WHERE page_id IN (" . implode(',', $ids) . ")" : '';
}

/**
 * The newest comment's id over some draft pages, 0 for none: what the
 * designer's presence answer carries, so the open editor fetches the list
 * only when it changes.
 *
 * @param int|int[] $page_ids
 * @return int
 */
function pg_proto_comments_page_stamp($page_ids)
{
    $sql = pg_proto_comments_page_stamp_sql($page_ids);

    if (($sql === '') || !pg_proto_comments_ready()) {
        return 0;
    }

    return (int) db_value($sql);
}

/**
 * Counts this request's address against the burst limit; true when it went
 * past it, which is logged and counted as a firewall offence (a burst).
 *
 * @return bool
 */
function pg_proto_comment_burst()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    // An address the firewall does not count (a listed or unlisted proxy, a
    // private network) is shared by everyone behind it: counted, one bucket
    // would hold every visitor.
    if (($ip === '') || !waf_ip_is_counted($ip)) {
        return false;
    }

    $limits = pg_proto_comment_limits();
    $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'prototype_comment', $limits['burst'], $limits['burst_window']);

    if (!$exceeded) {
        return false;
    }

    $blocking = (!function_exists('waf_mode') || (waf_mode() === 'block'));

    if (function_exists('waf_log_event')) {
        waf_log_event($blocking ? 'block' : 'would-block', 'rate-prototype-comment', 'rate', 5, 'prototype_comment.php', $limits['burst'] . '/' . (int) round($limits['burst_window'] / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-prototype-comment'));
    }

    if (function_exists('waf_register_offence')) {
        waf_register_offence($ip, 5, $blocking, true);
    }

    return true;
}

/* ---------------------------------------------------------------------------
   The designer's side: the list, the presence stamp, the bell
   --------------------------------------------------------------------------- */

/**
 * The comments of some rows as threads: each first comment (public shape)
 * with its answers under 'answers', both oldest first. An answer whose first
 * comment is not among the rows is left out. Pure.
 *
 * @param array[] $rows prototype_comments rows, in any order
 * @return array[]
 */
function pg_proto_comment_threads($rows)
{
    $roots = array();
    $answers = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !isset($row['id'])) {
            continue;
        }

        if ((int) $row['parent_id'] === 0) {
            $roots[(int) $row['id']] = pg_proto_comment_public($row) + array('answers' => array());
        } else {
            $answers[(int) $row['id']] = $row;
        }
    }

    ksort($roots);
    ksort($answers);

    foreach ($answers as $row) {
        $root = (int) $row['parent_id'];

        if (isset($roots[$root])) {
            $roots[$root]['answers'][] = pg_proto_comment_public($row);
        }
    }

    return array_values($roots);
}

/**
 * The designer's list: the threads grouped by page, the design's pages in
 * the order given and a page that is gone (no name) after them; in a page the
 * open threads first, then the resolved ones, each newest first; the open and
 * resolved counts. A page without comments is left out. Pure.
 *
 * Each thread gets the number the customer sees on its pin ('n'): the
 * viewer reads one page of one link and numbers its threads by the first
 * comment, oldest first, resolved ones counted (pg_proto_comments.js,
 * threads()); so numbers run per page and link. 'link_hint' names the link
 * as the Share prototype dialog lists it, 'links' counts a page's links.
 *
 * @param array[] $threads pg_proto_comment_threads()
 * @param array   $pages   page_id => name, in the order they are listed
 * @param array   $hints   link_id => token_hint
 * @return array[] page_id, name, open, resolved, links, threads
 */
function pg_proto_comment_list_pages($threads, $pages, $hints = array())
{
    $by_page = array();

    foreach ((array) $threads as $thread) {
        $by_page[(int) $thread['page_id']][] = $thread;
    }

    $order = array();

    foreach ((array) $pages as $page_id => $name) {
        if (isset($by_page[(int) $page_id])) {
            $order[(int) $page_id] = (string) $name;
        }
    }

    $gone = array_diff(array_keys($by_page), array_keys($order));
    sort($gone);

    foreach ($gone as $page_id) {
        $order[(int) $page_id] = '';
    }

    $out = array();

    foreach ($order as $page_id => $name) {
        $list = $by_page[$page_id];

        usort($list, function ($a, $b) {
            return (int) $a['id'] - (int) $b['id'];
        });

        $count = array();

        foreach ($list as $i => $thread) {
            $link = (int) $thread['link_id'];
            $count[$link] = isset($count[$link]) ? $count[$link] + 1 : 1;
            $list[$i]['n'] = $count[$link];
            $list[$i]['link_hint'] = isset($hints[$link]) ? (string) $hints[$link] : '';
        }

        usort($list, function ($a, $b) {
            $a_open = ((int) $a['resolved_at'] === 0);
            $b_open = ((int) $b['resolved_at'] === 0);

            if ($a_open !== $b_open) {
                return $a_open ? -1 : 1;
            }

            return (int) $b['id'] - (int) $a['id'];
        });

        $open = 0;

        foreach ($list as $thread) {
            $open += ((int) $thread['resolved_at'] === 0) ? 1 : 0;
        }

        $out[] = array(
            'page_id' => (int) $page_id,
            'name' => $name,
            'open' => $open,
            'resolved' => count($list) - $open,
            'links' => count($count),
            'threads' => $list,
        );
    }

    return $out;
}

/**
 * The designer's list of a design: every comment of its draft pages, over
 * all its links, as pg_proto_comment_list_pages() gives it. The page names
 * are read from the page table; a deleted page's comments stay and are
 * listed without a name. The links' hints come from prototype_links.
 *
 * @param int $style_id
 * @return array[]
 */
function pg_proto_comments_list_of_style($style_id)
{
    if (((int) $style_id <= 0) || !pg_proto_comments_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_comments WHERE style_id = '" . (int) $style_id . "' ORDER BY id LIMIT 2000");
    $rows = is_array($rows) ? $rows : array();
    $ids = array();
    $link_ids = array();

    foreach ($rows as $row) {
        $ids[(int) $row['page_id']] = (int) $row['page_id'];
        $link_ids[(int) $row['link_id']] = (int) $row['link_id'];
    }

    $pages = array();

    if ($ids) {
        $named = db_items("SELECT page_id, page_name FROM page WHERE page_id IN (" . implode(',', $ids) . ") ORDER BY page_name");

        foreach (is_array($named) ? $named : array() as $page) {
            $pages[(int) $page['page_id']] = (string) $page['page_name'];
        }
    }

    $hints = array();

    if ($link_ids) {
        $links = db_items("SELECT id, token_hint FROM prototype_links WHERE id IN (" . implode(',', $link_ids) . ")");

        foreach (is_array($links) ? $links : array() as $link) {
            $hints[(int) $link['id']] = (string) $link['token_hint'];
        }
    }

    return pg_proto_comment_list_pages(pg_proto_comment_threads($rows), $pages, $hints);
}

/**
 * The statement that reads a design's stamp; '' for no design. idx_style
 * (style_id) carries the id, so the newest id is read from the index.
 *
 * @param int $style_id
 * @return string
 */
function pg_proto_comments_style_stamp_sql($style_id)
{
    return ((int) $style_id > 0) ? 'SELECT MAX(id) FROM prototype_comments WHERE style_id = ' . (int) $style_id : '';
}

/**
 * The newest comment's id over a design, 0 for none: what the presence
 * answer carries to a designer, so the editor fetches the list only when it
 * moves. A comment on any page of the design moves it, open as a tab or not.
 *
 * @param int $style_id
 * @return int
 */
function pg_proto_comments_style_stamp($style_id)
{
    $sql = pg_proto_comments_style_stamp_sql($style_id);

    if (($sql === '') || !pg_proto_comments_ready()) {
        return 0;
    }

    return (int) db_value($sql);
}

/**
 * The bell line's words for a guest's comment, in the reader's language:
 * the short title, the text under it (who, on which page) and the whole
 * sentence. An empty name or page (a deleted comment or page) gives a
 * text without them. Pure but for lang().
 *
 * @param string $page_name
 * @param string $guest_name
 * @return array short, brief, title
 */
function pg_proto_comment_bell_words($page_name, $guest_name)
{
    $page_name = (string) $page_name;
    $guest_name = (string) $guest_name;
    $short = lang('New prototype comment');

    if (($page_name !== '') && ($guest_name !== '')) {
        $brief = lang(array('string' => '{var:1} on {var:2}', 'vars' => array($guest_name, $page_name)));
        $title = lang(array('string' => '{var:1} commented on the prototype of {var:2}.', 'vars' => array($guest_name, $page_name)));
    } elseif ($page_name !== '') {
        $brief = $page_name;
        $title = lang(array('string' => 'A comment was written on the prototype of {var:1}.', 'vars' => array($page_name)));
    } else {
        $brief = lang('A comment on a prototype');
        $title = lang('A comment was written on a prototype.');
    }

    return array('short' => $short, 'brief' => $brief, 'title' => $title);
}

/**
 * Where the bell line leads: the design open on the page, with the
 * Comments list open; the pages list when the design is gone.
 *
 * @param int $style_id
 * @param int $page_id
 * @return string
 */
function pg_proto_comment_bell_url($style_id, $page_id)
{
    if ((int) $style_id <= 0) {
        return 'view_pages.php';
    }

    return 'edit_system_style.php?id=' . (int) $style_id . '&page=' . (int) $page_id . '&comments=1';
}

/**
 * Tells the link's maker that a guest wrote on it: one bell line, at most one
 * an hour per link. The hour is a firewall rate counter with the link's id as
 * its subject (as the note lock's mail is counted), so no column on the link
 * keeps it. A staff answer rings nothing.
 *
 * @param array $link prototype_links row
 * @param array $row  the saved prototype_comments row
 * @return bool whether a line was written
 */
function pg_proto_comment_bell($link, $row)
{
    if (!is_array($link) || !is_array($row) || ((int) $row['user_id'] > 0)) {
        return false;
    }

    $maker = isset($link['created_by']) ? (int) $link['created_by'] : 0;

    if ($maker <= 0) {
        return false;
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    if (!pg_notification_targets_available()) {
        return false;
    }

    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded((string) (int) $link['id'], 'prototype-comment-bell', 1, 3600)) {
        return false;
    }

    // The page's name: the words are made when the line is read.
    $page = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . (int) $row['page_id'] . "' LIMIT 1");

    return (bool) db_q("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
        VALUES ('prototype_comment', 'info', ?, ?, ?, '', '0', UNIX_TIMESTAMP())",
        array(mb_substr($page, 0, 250, 'UTF-8'), $maker, (int) $row['id']));
}

/**
 * A bell line's words and address as the bell draws them: the guest's name
 * and the design read through reference_id (the comment), the page's name
 * from the title.
 *
 * @param array $notification
 * @return array short, brief, title, url
 */
function pg_proto_comment_bell_display($notification)
{
    $row = pg_proto_comment_get(isset($notification['reference_id']) ? (int) $notification['reference_id'] : 0);
    $words = pg_proto_comment_bell_words(isset($notification['title']) ? (string) $notification['title'] : '', $row ? (string) $row['author_name'] : '');

    return $words + array('url' => $row ? pg_proto_comment_bell_url($row['style_id'], $row['page_id']) : 'view_pages.php');
}

/* ---------------------------------------------------------------------------
   Make a task: a comment followed up in the Workspace
   --------------------------------------------------------------------------- */

/**
 * Whether the Workspace is on, with its files loaded: they load only when a
 * comment is to become a task or the list asks whether it can.
 *
 * @return bool
 */
function pg_proto_comment_workspace()
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return function_exists('ws_followup_task_open') && function_exists('ws_viewer');
}

/**
 * Whether "Make a task" is offered, pure: only with the Workspace on and
 * the designer on its team (a profile can take a staff account out of it).
 *
 * @param bool $workspace pg_proto_comment_workspace()
 * @param bool $member    the designer's ws_viewer() member right
 * @return bool
 */
function pg_proto_comment_task_offered($workspace, $member)
{
    return (bool) $workspace && (bool) $member;
}

/**
 * Whether this designer is offered "Make a task": what the Comments list
 * answer carries, so the button is drawn only where pressing it can work.
 *
 * @param array $user validate_user()
 * @return bool
 */
function pg_proto_comment_task_offered_to($user)
{
    $workspace = pg_proto_comment_workspace();
    $viewer = $workspace ? ws_viewer($user) : array();

    return pg_proto_comment_task_offered($workspace, !empty($viewer['member']));
}

/**
 * A guest's words as data inside a Workspace text, pure: angle brackets
 * become ‹ › so no tag (<@user:1>, <#order:5>) is read or stored and no
 * markup is formed, and a line loses the start of a titled block or a code
 * fence (::: and ```).
 *
 * @param string $text
 * @return string
 */
function pg_proto_comment_task_words($text)
{
    $text = str_replace(array('<', '>'), array('‹', '›'), (string) $text);

    return trim(preg_replace('/^[ \t]*(?::::|```)/mu', '', $text));
}

/**
 * A guest's words quoted line by line ("> "), as data. Pure.
 *
 * @param string $text
 * @return string
 */
function pg_proto_comment_task_quote($text)
{
    $lines = array();

    foreach (preg_split('/\R/u', pg_proto_comment_task_words($text)) as $line) {
        $lines[] = rtrim('> ' . $line);
    }

    return implode("\n", $lines);
}

/**
 * The follow-up "Make a task" opens, pure (ws_followup_task_data()'s
 * input): the designer's own task on no channel, the comment's page tagged,
 * due today with no reminder (it is due at once), the guest's words quoted
 * under a line that says who wrote them, from outside the team, and where.
 *
 * @param array  $row       the prototype_comments row
 * @param string $page_name
 * @param string $today     Y-m-d
 * @return array
 */
function pg_proto_comment_task_input($row, $page_name, $today)
{
    $page_name = (string) $page_name;
    $name = preg_replace('/\s+/u', ' ', pg_proto_comment_task_words(isset($row['author_name']) ? $row['author_name'] : ''));

    return array(
        'title'          => lang(array('string' => 'Prototype comment on {var:1}', 'vars' => array($page_name))),
        'description'    => lang(array('string' => '{var:1} (guest, outside the team) commented on the prototype of {var:2}:', 'vars' => array($name, $page_name)))
            . "\n" . pg_proto_comment_task_quote(isset($row['body']) ? $row['body'] : ''),
        'refs'           => array(array('type' => 'page', 'id' => (int) $row['page_id'])),
        'channel_id'     => 0,
        'assignees'      => array(),
        'due_date'       => (string) $today,
        'remind_minutes' => null,
    );
}

/**
 * "Make a task" on a comment of the designer's list: the designer's own
 * follow-up, opened as them through ws_followup_task_open() with $once
 * false, so a second comment on the same page, or the same one pressed
 * again, opens a task of its own.
 *
 * @param array $user       validate_user() of the designer
 * @param int   $comment_id
 * @param int   $style_id   the design the list was asked for
 * @return array ok, error, task_id
 */
function pg_proto_comment_task_open(array $user, $comment_id, $style_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'task_id' => 0);
    };

    $row = pg_proto_comment_get($comment_id);

    if (!$row || ((int) $row['style_id'] !== (int) $style_id)) {
        return $fail(lang('This comment is no longer there.'));
    }

    $workspace = pg_proto_comment_workspace();
    $viewer = $workspace ? ws_viewer($user) : array();

    if (!pg_proto_comment_task_offered($workspace, !empty($viewer['member']))) {
        return $fail(lang('A task can be made only with the Workspace on, by a member of its team.'));
    }

    $page_name = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . (int) $row['page_id'] . "' LIMIT 1");

    if ($page_name === '') {
        return $fail(lang('This page is no longer there.'));
    }

    $opened = ws_followup_task_open($viewer, pg_proto_comment_task_input($row, $page_name, date('Y-m-d')), false);

    return $opened['ok']
        ? array('ok' => true, 'error' => '', 'task_id' => (int) $opened['task_id'])
        : $fail((string) $opened['error']);
}


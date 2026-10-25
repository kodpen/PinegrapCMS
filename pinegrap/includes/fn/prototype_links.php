<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Share links of a design's prototype (prototype.php?link=<token>): an
 * address that opens the design's draft pages as a clickable prototype for
 * somebody the viewer would not admit by their own sign-in.
 *
 * A link is a row of prototype_links (2026.11.0, 11.0.35). Its token is 32
 * random bytes written URL-safe; the row keeps the token's SHA-256
 * (token_hash), which a request is looked up by, the token once more
 * encrypted with the site's key (token_secret), so that its maker can be
 * shown the address again, and its first characters (token_hint), which the
 * lists and the Site Log name it by. The token itself is written nowhere
 * else: prototype.php takes it out of every copy of the request's address
 * before init.php runs the firewall, so no log line (waf_log, firewall.log,
 * the error log) keeps it, and the external API's create records its answer
 * under an Idempotency-Key without the address ('idempotency_redact') and
 * the password only as a keyed HMAC in the fingerprint ('secret').
 *
 * A link is for 'anyone' holding it, with an expiry of at most ninety days,
 * or for the 'team' (a signed-in administrator, designer or manager), with
 * an expiry or none. A password is optional; its wrong answers are counted on
 * the row (ten lock it for fifteen minutes), never in the sign-in throttle,
 * whose address bucket would lock the visitor's whole office out of signing
 * in. A right password leaves a cookie for prototype.php alone, holding an
 * HMAC over the link, the password's hash and its own expiry. Links are made,
 * listed, shown again and revoked by administrators and designers (roles
 * 0-1), in the Visual Page Editor and over the external API (never as an MCP
 * tool).
 *
 * Loaded by prototype.php (before init.php, for the address redaction: hence
 * the second gate constant), the designer's endpoints, the external API's
 * design resource, the editor screen and the tests.
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
   Rules a link is made by
   --------------------------------------------------------------------------- */

/**
 * Who a link opens for: anyone holding it, or the team (a signed-in
 * administrator, designer or manager).
 *
 * @return string[]
 */
function pg_proto_link_audiences()
{
    return array('anyone', 'team');
}

/**
 * The longest a link can be made for, in seconds: ninety days.
 *
 * @return int
 */
function pg_proto_link_longest()
{
    return 7776000;
}

/**
 * How long a link can be made for: the short links' choices
 * (pg_short_link_durations()) up to pg_proto_link_longest().
 *
 * @return array seconds => label
 */
function pg_proto_link_durations()
{
    $out = array();

    foreach (pg_short_link_durations() as $seconds => $label) {
        if (((int) $seconds > 0) && ((int) $seconds <= pg_proto_link_longest())) {
            $out[(int) $seconds] = $label;
        }
    }

    return $out;
}

/**
 * How many wrong passwords in a row lock a link's password.
 *
 * @return int
 */
function pg_proto_link_password_limit()
{
    return 10;
}

/**
 * How long a locked password stays locked, in seconds: fifteen minutes.
 *
 * @return int
 */
function pg_proto_link_lock_seconds()
{
    return 900;
}

/**
 * How long a right password is remembered by its cookie, in seconds: twelve
 * hours.
 *
 * @return int
 */
function pg_proto_link_pass_ttl()
{
    return 43200;
}

/**
 * Is this the shape of a link token (pg_short_link_new_token())? Checked
 * before the database is asked.
 *
 * @param mixed $token
 * @return bool
 */
function pg_proto_link_token_shape($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1);
}

/**
 * The first characters of a token, which lists and log lines name a link by.
 *
 * @param string $token
 * @return string
 */
function pg_proto_link_hint($token)
{
    return substr((string) $token, 0, 6);
}

/**
 * A whole number sent as an int or as digits; null for anything else.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_proto_link_whole_number($value)
{
    if (is_int($value)) {
        return ($value >= 0) ? $value : null;
    }

    if (($value === null) || ($value === '')) {
        return 0;
    }

    if (is_string($value) && (preg_match('/^[0-9]{1,10}$/', $value) === 1)) {
        return (int) $value;
    }

    return null;
}

/**
 * Checks what a new link is asked for and writes it out as it would be
 * stored. A link for anyone must run out, at one of the offered durations of
 * at most ninety days; a team link may run for good. The start page is 0
 * (the design's first draft) or one of the design's drafts. A password is
 * optional, 6 to 128 characters, not spaces alone.
 *
 * @param array $input     audience, start_page_id, duration (seconds; 0 or
 *                         empty for none), password ('' for none)
 * @param int[] $draft_ids the design's drafts (pg_proto_design_pages())
 * @param int[] $durations the durations offered, in seconds
 * @param int   $now
 * @return array ok, error (audience, duration, start_page, no_drafts,
 *               password, password_blank), field, link (audience,
 *               start_page_id, expires_at, password)
 */
function pg_proto_link_plan($input, $draft_ids, $durations, $now)
{
    $input = is_array($input) ? $input : array();
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'link' => null);
    };

    $drafts = array();

    foreach ((array) $draft_ids as $id) {
        if ((int) $id > 0) {
            $drafts[(int) $id] = true;
        }
    }

    if (!$drafts) {
        return $fail('no_drafts', 'start_page_id');
    }

    $audience = isset($input['audience']) ? $input['audience'] : '';

    if (!is_string($audience) || !in_array($audience, pg_proto_link_audiences(), true)) {
        return $fail('audience', 'audience');
    }

    $start = pg_proto_link_whole_number(isset($input['start_page_id']) ? $input['start_page_id'] : 0);

    if (($start === null) || (($start !== 0) && !isset($drafts[$start]))) {
        return $fail('start_page', 'start_page_id');
    }

    $duration = pg_proto_link_whole_number(isset($input['duration']) ? $input['duration'] : 0);
    $allowed = array();

    foreach ((array) $durations as $seconds) {
        if (((int) $seconds > 0) && ((int) $seconds <= pg_proto_link_longest())) {
            $allowed[(int) $seconds] = true;
        }
    }

    if (($duration === null) || (($duration === 0) && ($audience === 'anyone')) || (($duration !== 0) && !isset($allowed[$duration]))) {
        return $fail('duration', 'duration');
    }

    $password = isset($input['password']) ? $input['password'] : '';

    // A NUL byte is refused with the length: bcrypt throws on one in PHP 8
    // (password_hash(): a 500 where the dry run answered "created") and cuts
    // the password there in PHP 7.
    if (!is_string($password) || (strpos($password, "\0") !== false) || (($password !== '') && ((mb_strlen($password) < 6) || (mb_strlen($password) > 128)))) {
        return $fail('password', 'password');
    }

    // Kept as given (the password form reads it untrimmed), but spaces alone
    // are refused rather than taken for no password.
    if (($password !== '') && (trim($password) === '')) {
        return $fail('password_blank', 'password');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'link' => array(
        'audience' => $audience,
        'start_page_id' => $start,
        'expires_at' => ($duration > 0) ? ((int) $now + $duration) : 0,
        'password' => $password,
    ));
}

/**
 * The sentence for a refusal of pg_proto_link_plan().
 *
 * @param string $code
 * @return string
 */
function pg_proto_link_error_message($code)
{
    switch ((string) $code) {
        case 'audience':
            return lang('Choose who the link opens for: anyone who has it, or the team.');

        case 'duration':
            return lang('A link for anyone who has it must run out within 90 days. Choose one of the offered times.');

        case 'start_page':
            return lang('The start page must be a draft of this design.');

        case 'no_drafts':
            return lang('This design has no draft pages.');

        case 'password':
            return lang('A password must be 6 to 128 characters long.');

        case 'password_blank':
            return lang('A password cannot be made of spaces only.');
    }

    return lang('Sorry, we could not accept your request.');
}

/* ---------------------------------------------------------------------------
   Where a link stands
   --------------------------------------------------------------------------- */

/**
 * Whether a link opens now: 'ok', 'revoked', 'expired', or 'unknown' for no
 * row. Whether its design and drafts still exist is asked separately (the
 * viewer calls that 'gone').
 *
 * @param array|null $row prototype_links row
 * @param int        $now
 * @return string
 */
function pg_proto_link_state($row, $now)
{
    if (!is_array($row) || empty($row['id'])) {
        return 'unknown';
    }

    if ((int) (isset($row['revoked_at']) ? $row['revoked_at'] : 0) > 0) {
        return 'revoked';
    }

    $expires = (int) (isset($row['expires_at']) ? $row['expires_at'] : 0);

    if (($expires > 0) && ($expires <= (int) $now)) {
        return 'expired';
    }

    return 'ok';
}

/**
 * Whether a link that does not open is counted against the source as a
 * firewall offence: only a guess, a token of the right shape that nobody
 * made. A revoked token was given to whoever holds it, and every click,
 * reload or page change of a viewer still open brings it back, so counting
 * it would ban the people it was shared with (a whole office behind one
 * address) and add nothing against guessing. A token of the wrong shape (cut
 * short in a mail, the *** of a written-out address) never reaches the
 * database and is no guess either. A link that ran out or lost its design is
 * somebody arriving late.
 *
 * @param string $state pg_proto_link_state(), or 'gone'
 * @param mixed  $token the token the request carried
 * @return bool
 */
function pg_proto_link_offence_due($state, $token)
{
    return ((string) $state === 'unknown') && pg_proto_link_token_shape($token);
}

/**
 * The page a link's prototype opens on: the page asked for when it is a
 * draft of the design (a click inside the prototype), else the link's start
 * page when it still is one, else the first draft. fallback says a page
 * asked for was passed over.
 *
 * @param array[] $pages         pg_proto_page_set()
 * @param int     $link_start_id prototype_links.start_page_id (0: the first)
 * @param int     $requested_id  ?page= (0: none)
 * @return array id (0 for no drafts), fallback
 */
function pg_proto_link_start($pages, $link_start_id, $requested_id)
{
    $link_start_id = (int) $link_start_id;
    $requested_id = (int) $requested_id;

    if (!$pages) {
        return array('id' => 0, 'fallback' => false);
    }

    $ids = array();

    foreach ($pages as $page) {
        $ids[(int) $page['id']] = true;
    }

    if (($requested_id > 0) && isset($ids[$requested_id])) {
        return array('id' => $requested_id, 'fallback' => false);
    }

    return array(
        'id' => isset($ids[$link_start_id]) ? $link_start_id : (int) $pages[0]['id'],
        'fallback' => ($requested_id > 0),
    );
}

/**
 * Whether a link draws a draft whose folder on the site (the one it goes
 * live in) has this access control: a team link every draft, as its members
 * open them on the site; a link for anyone only what a signed-out visitor
 * would get once the page is published (a public folder, or a guest one,
 * which asks for no account). The frame runs no folder gate of its own
 * (get_page.php's is not on its way), so a draft going live behind a sign-in
 * would show its record widgets' rows to whoever holds the link.
 *
 * @param string $audience            prototype_links.audience
 * @param string $access_control_type get_access_control_type()
 * @return bool
 */
function pg_proto_link_page_shown($audience, $access_control_type)
{
    if ((string) $audience === 'team') {
        return true;
    }

    return in_array((string) $access_control_type, array('public', 'guest'), true);
}

/**
 * The access control of the folder a draft goes live in
 * (pg_page_draft_publish_folder()), for pg_proto_link_page_shown().
 *
 * @param int $page_id
 * @return string
 */
function pg_proto_link_page_access($page_id)
{
    return (string) get_access_control_type(pg_page_draft_publish_folder((int) $page_id));
}

/**
 * Whether a link's password is locked now.
 *
 * @param array $row
 * @param int   $now
 * @return bool
 */
function pg_proto_link_password_locked($row, $now)
{
    return (int) (isset($row['password_locked_until']) ? $row['password_locked_until'] : 0) > (int) $now;
}

/**
 * When a password stays locked until after this many wrong answers in a row:
 * from the limit on, each wrong answer locks it again; 0 below the limit.
 *
 * @param int $failures
 * @param int $now
 * @return int Unix time, 0 for no lock
 */
function pg_proto_link_lock_after($failures, $now)
{
    return ((int) $failures >= pg_proto_link_password_limit()) ? ((int) $now + pg_proto_link_lock_seconds()) : 0;
}

/**
 * The statement that counts one password attempt before the password is
 * checked, and only while the password is not locked: the lock is read from
 * the row in the database, so requests sent at once each find the lock the
 * one before them wrote, and no burst gets more than one check past the
 * limit. The attempt that reaches the limit locks the password at once (a
 * right password clears it again). The lock is assigned first, from the
 * count before this attempt, so the answer is the same whether the server
 * assigns left to right (MySQL) or all at once (MariaDB's
 * SIMULTANEOUS_ASSIGNMENT).
 *
 * @param int $link_id
 * @param int $now
 * @return string
 */
function pg_proto_link_attempt_sql($link_id, $now)
{
    $now = (int) $now;

    return "UPDATE prototype_links SET password_locked_until = IF(password_failures + 1 >= " . pg_proto_link_password_limit()
        . ", '" . ($now + pg_proto_link_lock_seconds()) . "', password_locked_until), password_failures = password_failures + 1"
        . " WHERE id = '" . (int) $link_id . "' AND password_locked_until <= '" . $now . "'";
}

/**
 * Whether a visit of the viewer counts as a use of the link: a GET (not a
 * HEAD, not the password form) from a person, not a bot or a messenger
 * drawing a preview of the link (waf_classify_bot()), of the shared address
 * itself. A move inside the prototype (a click in the frame, the bar's list,
 * previous and next) names a page and is a request of its own, so without a
 * kept session (cookies refused) each one would count again; the address
 * handed out never names one (pg_proto_link_url()).
 *
 * @param string $method
 * @param string $client_class waf_classify_bot()['class']
 * @param int    $page_id      the page the address names, 0 for none
 * @return bool
 */
function pg_proto_link_counts_use($method, $client_class, $page_id)
{
    return (strtoupper((string) $method) === 'GET') && ((string) $client_class === 'human') && ((int) $page_id === 0);
}

/**
 * What the lists show of a link, with nothing that opens it.
 *
 * @param array $row prototype_links row
 * @param int   $now
 * @return array
 */
function pg_proto_link_summary($row, $now)
{
    $state = pg_proto_link_state($row, $now);
    $password = isset($row['password_hash']) && ((string) $row['password_hash'] !== '');

    return array(
        'id' => (int) $row['id'],
        'style_id' => (int) $row['style_id'],
        'start_page_id' => (int) $row['start_page_id'],
        'audience' => (string) $row['audience'],
        'hint' => (string) $row['token_hint'],
        'password' => $password,
        'locked' => $password && pg_proto_link_password_locked($row, $now),
        'expires_at' => (int) $row['expires_at'],
        'created_by' => (int) $row['created_by'],
        'created_at' => (int) $row['created_at'],
        'revoked_at' => (int) $row['revoked_at'],
        'uses' => (int) $row['uses'],
        'last_used_at' => (int) $row['last_used_at'],
        'state' => ($state === 'ok') ? 'open' : $state,
    );
}

/* ---------------------------------------------------------------------------
   The password's cookie
   --------------------------------------------------------------------------- */

/**
 * The name of the cookie a right password leaves for one link.
 *
 * @param int $link_id
 * @return string
 */
function pg_proto_link_pass_cookie_name($link_id)
{
    return 'pg_proto_pass_' . (int) $link_id;
}

/**
 * The value of that cookie: its expiry and an HMAC over the link, the
 * password's hash and the expiry, so that a new password (or none) ends it
 * and the time cannot be moved on. '' without a key, a link or a password.
 *
 * @param int    $link_id
 * @param string $password_hash prototype_links.password_hash
 * @param int    $expires       Unix time
 * @param string $key           ENCRYPTION_KEY
 * @return string
 */
function pg_proto_link_pass_value($link_id, $password_hash, $expires, $key)
{
    $link_id = (int) $link_id;
    $expires = (int) $expires;

    if (($link_id <= 0) || ($expires <= 0) || ((string) $password_hash === '') || ((string) $key === '')) {
        return '';
    }

    return $expires . '.' . hash_hmac('sha256', 'prototype-link-pass:' . $link_id . ':' . $expires . ':' . (string) $password_hash, (string) $key);
}

/**
 * Whether a cookie value lets its holder past a link's password now.
 *
 * @param mixed  $value
 * @param int    $link_id
 * @param string $password_hash
 * @param string $key
 * @param int    $now
 * @return bool
 */
function pg_proto_link_pass_valid($value, $link_id, $password_hash, $key, $now)
{
    if (!is_string($value) || (preg_match('/^([1-9][0-9]{0,9})\.[0-9a-f]{64}$/', $value, $match) !== 1)) {
        return false;
    }

    $expires = (int) $match[1];

    if (($expires <= (int) $now) || ($expires > ((int) $now + pg_proto_link_pass_ttl() + 60))) {
        return false;
    }

    $expected = pg_proto_link_pass_value($link_id, $password_hash, $expires, $key);

    return ($expected !== '') && hash_equals($expected, $value);
}

/* ---------------------------------------------------------------------------
   The frame of a link
   --------------------------------------------------------------------------- */

/**
 * The signature of a frame address made for a link: design, page, link,
 * expiry, item and record. Its prefix is not the person mode's
 * (pg_proto_frame_sign()), so an address signed for a person never opens as
 * a link's and the other way round.
 *
 * @param mixed  $style_id
 * @param mixed  $page_id
 * @param mixed  $link_id
 * @param mixed  $expires Unix time
 * @param string $key     ENCRYPTION_KEY
 * @param string $sub     the part of a pretty address after the page name
 * @param string $record  a record code (?r=)
 * @return string 64 hex characters, '' when any part is missing
 */
function pg_proto_link_frame_sign($style_id, $page_id, $link_id, $expires, $key, $sub = '', $record = '')
{
    $style_id = (int) $style_id;
    $page_id = (int) $page_id;
    $link_id = (int) $link_id;
    $expires = (int) $expires;

    if (($style_id <= 0) || ($page_id <= 0) || ($link_id <= 0) || ($expires <= 0) || ((string) $key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'prototype-link-frame:' . $style_id . ':' . $page_id . ':' . $link_id . ':' . $expires
        . ':' . rawurlencode((string) $sub) . ':' . rawurlencode((string) $record), (string) $key);
}

/**
 * The query string of a link's frame address, without the leading "?". The
 * token is not in it: the frame runs the design's own scripts, and an
 * analytics or replay tag pasted into the design reports the frame's address
 * (and the page's links) to its vendor. The signature binds the link's id,
 * which the frame reads the link by; b= binds the address to the session of
 * the browser that opened the link (pg_proto_frame_bind()), so a copy those
 * scripts send away opens nothing.
 *
 * @param string $bind pg_proto_frame_bind(), '' for none
 * @return string
 */
function pg_proto_link_frame_query($style_id, $page_id, $link_id, $expires, $signature, $sub = '', $record = '', $bind = '')
{
    $query = 'frame=1'
        . '&s=' . (int) $style_id
        . '&p=' . (int) $page_id
        . '&l=' . (int) $link_id
        . '&e=' . (int) $expires
        . '&sig=' . rawurlencode((string) $signature);

    if ((string) $bind !== '') {
        $query .= '&b=' . rawurlencode((string) $bind);
    }

    if ((string) $sub !== '') {
        $query .= '&sub=' . rawurlencode((string) $sub);
    }

    if ((string) $record !== '') {
        $query .= '&r=' . rawurlencode((string) $record);
    }

    return $query;
}

/**
 * Reads a link's frame address from the request: design, page, link and
 * expiry as plain positive whole numbers and a 64-character hex signature;
 * anything else is no address. A binding that is not 64 hex characters is
 * read as none, as pg_proto_frame_parse() reads it.
 *
 * @param mixed $source $_GET
 * @return array|null s, p, l, e (int), sig, sub, r, b (string)
 */
function pg_proto_link_frame_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $parsed = array();

    foreach (array('s', 'p', 'l', 'e') as $name) {
        $value = isset($source[$name]) ? $source[$name] : null;

        if (!is_string($value) || (preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1) || ((string) (int) $value !== $value)) {
            return null;
        }

        $parsed[$name] = (int) $value;
    }

    $signature = isset($source['sig']) ? $source['sig'] : null;

    if (!is_string($signature) || (preg_match('/^[0-9a-f]{64}$/', $signature) !== 1)) {
        return null;
    }

    $parsed['sig'] = $signature;
    $parsed['sub'] = pg_proto_clean_text(isset($source['sub']) ? $source['sub'] : '', 255);
    $parsed['r'] = pg_proto_clean_text(isset($source['r']) ? $source['r'] : '', 100);

    $bind = isset($source['b']) ? $source['b'] : '';
    $parsed['b'] = (is_string($bind) && (preg_match('/^[0-9a-f]{64}$/', $bind) === 1)) ? $bind : '';

    return $parsed;
}

/**
 * What a link's frame address can do now: 'ok', 'invalid', 'expired' or
 * 'unbound', as pg_proto_frame_state() answers for a person's.
 *
 * @param array|null  $parsed pg_proto_link_frame_parse()
 * @param string      $key
 * @param int         $now
 * @param string|null $client the asking browser's session cookie ('' for
 *                            none); null checks the signature alone
 * @return string
 */
function pg_proto_link_frame_state($parsed, $key, $now, $client = null)
{
    if (!is_array($parsed) || !isset($parsed['s'], $parsed['p'], $parsed['l'], $parsed['e'], $parsed['sig'])) {
        return 'invalid';
    }

    $expected = pg_proto_link_frame_sign($parsed['s'], $parsed['p'], $parsed['l'], $parsed['e'], $key,
        isset($parsed['sub']) ? $parsed['sub'] : '', isset($parsed['r']) ? $parsed['r'] : '');

    if (($expected === '') || !hash_equals($expected, (string) $parsed['sig'])) {
        return 'invalid';
    }

    if ((int) $parsed['e'] > ((int) $now + pg_proto_frame_ttl() + 60)) {
        return 'invalid';
    }

    if ((int) $parsed['e'] < (int) $now) {
        return 'expired';
    }

    // Checked last: only an address that is genuine and in time can be
    // refused as asked for in the wrong browser.
    if ($client !== null) {
        $bind = pg_proto_frame_bind($parsed['sig'], $client, $key);

        if (($bind === '') || !isset($parsed['b']) || !hash_equals($bind, (string) $parsed['b'])) {
            return 'unbound';
        }
    }

    return 'ok';
}

/**
 * The viewer's address for a link: the base its page list, its previous and
 * next buttons and the frame's links build on (pg_proto_viewer_url()).
 *
 * @param string $viewer PATH . SOFTWARE_DIRECTORY . '/prototype.php'
 * @param string $token
 * @return string
 */
function pg_proto_link_viewer_base($viewer, $token)
{
    return (string) $viewer . '?link=' . rawurlencode((string) $token);
}

/**
 * The viewer's address the frame's links build on: the link named by its id,
 * never by its token, which the design's scripts would read off the page.
 * The viewer takes it only from a browser whose session opened the link by
 * its token (pg_proto_link_session_opens()).
 *
 * @param string $viewer  PATH . SOFTWARE_DIRECTORY . '/prototype.php'
 * @param int    $link_id
 * @return string
 */
function pg_proto_link_frame_base($viewer, $link_id)
{
    return (string) $viewer . '?l=' . (int) $link_id;
}

/**
 * Notes in the session that this browser opened a link by its token, past
 * its gates: the hash of the token, under the link's id.
 *
 * @param array $row
 * @return void
 */
function pg_proto_link_session_mark($row)
{
    $_SESSION['software']['prototype_link_open'][(int) $row['id']] = (string) $row['token_hash'];
}

/**
 * Whether the session's note (pg_proto_link_session_mark()) lets a frame
 * link by id open this link: it must hold the hash of the link's own token.
 *
 * @param mixed      $mark $_SESSION['software']['prototype_link_open'][<id>]
 * @param array|null $row
 * @return bool
 */
function pg_proto_link_session_opens($mark, $row)
{
    return is_array($row) && isset($row['token_hash']) && is_string($mark) && ($mark !== '')
        && hash_equals((string) $row['token_hash'], $mark);
}

/* ---------------------------------------------------------------------------
   The token out of the logs
   --------------------------------------------------------------------------- */

/**
 * An address (or a query string alone) with the value of every link
 * parameter written as ***: what a log may keep of a request to the viewer.
 * The name is read as PHP reads it ("li%6Ek", "link[]"), so no spelling of
 * it keeps the token.
 *
 * @param string $uri
 * @return string
 */
function pg_proto_link_redact_uri($uri)
{
    $uri = (string) $uri;
    $question = strpos($uri, '?');
    $head = ($question === false) ? '' : substr($uri, 0, $question + 1);
    $query = ($question === false) ? $uri : substr($uri, $question + 1);
    $pairs = explode('&', $query);
    $changed = false;

    foreach ($pairs as $index => $pair) {
        $equals = strpos($pair, '=');

        if ($equals === false) {
            continue;
        }

        $name = strtolower(urldecode(substr($pair, 0, $equals)));
        $bracket = strpos($name, '[');

        if ($bracket !== false) {
            $name = substr($name, 0, $bracket);
        }

        if (trim($name) === 'link') {
            $pairs[$index] = substr($pair, 0, $equals + 1) . '***';
            $changed = true;
        }
    }

    return $changed ? $head . implode('&', $pairs) : $uri;
}

/**
 * The request's address as get_request_uri() reads it, taken before the
 * token is written out of it: what Secure Mode's redirect in init.php sends
 * the visitor on to (PG_SECURE_MODE_REDIRECT_URL), since REQUEST_URL is made
 * from the written-out copy and would lead to "link=***".
 *
 * @param array $server
 * @return string
 */
function pg_proto_link_request_uri($server)
{
    $server = is_array($server) ? $server : array();

    if (isset($server['HTTP_X_REWRITE_URL'])) {
        return (string) $server['HTTP_X_REWRITE_URL'];
    }

    if (isset($server['REQUEST_URI'])) {
        return (string) $server['REQUEST_URI'];
    }

    $self = isset($server['PHP_SELF']) ? (string) $server['PHP_SELF'] : '';

    return (isset($server['QUERY_STRING']) && ((string) $server['QUERY_STRING'] !== '')) ? $self . '?' . $server['QUERY_STRING'] : $self;
}

/**
 * $_SERVER with every copy of the request's address redacted
 * (pg_proto_link_redact_uri()): what the firewall, the error log and the
 * request's own constants (REQUEST_URL) read it from.
 *
 * @param array $server
 * @return array
 */
function pg_proto_link_redact_server($server)
{
    $server = is_array($server) ? $server : array();

    foreach (array('REQUEST_URI', 'HTTP_X_REWRITE_URL', 'HTTP_X_ORIGINAL_URL', 'UNENCODED_URL', 'QUERY_STRING', 'REDIRECT_QUERY_STRING', 'REDIRECT_URL') as $name) {
        if (isset($server[$name]) && is_string($server[$name])) {
            $server[$name] = pg_proto_link_redact_uri($server[$name]);
        }
    }

    return $server;
}

/* ---------------------------------------------------------------------------
   The password page
   --------------------------------------------------------------------------- */

/**
 * The page a password link answers until the right password is given: the
 * viewer's bar without the design's name, and a form that posts the password
 * to the same address.
 *
 * @param array $view action (the address the form posts to), token_field
 *                    (get_token_field()), error (a sentence, '' for none),
 *                    locked (the form cannot be sent), software
 *                    (PATH . SOFTWARE_DIRECTORY), lang
 * @return string
 */
function pg_proto_link_password_html($view)
{
    $software = isset($view['software']) ? (string) $view['software'] : '';
    $error = isset($view['error']) ? (string) $view['error'] : '';
    $locked = !empty($view['locked']);
    $off = $locked ? ' disabled' : '';
    $version = function ($path) {
        return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
    };

    return '<!DOCTYPE html>
<html lang="' . h(isset($view['lang']) ? (string) $view['lang'] : 'en') . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h(lang('Prototype')) . '</title>
<link rel="stylesheet" href="' . h($software) . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . h($software) . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<style>
html, body { height: 100%; }
body.pg-proto { display: flex; flex-direction: column; margin: 0; background: var(--bs-tertiary-bg); }
.pg-proto-bar { display: flex; align-items: center; gap: .5rem; padding: .375rem 1rem; min-height: 2.75rem; background: var(--bs-body-bg); border-bottom: 1px solid var(--bs-border-color); }
.pg-proto-main { flex: 1 1 auto; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
.pg-proto-pass { width: 100%; max-width: 22rem; }
</style>
</head>
<body class="pg-proto">
<nav class="pg-proto-bar" aria-label="' . h(lang('Prototype')) . '"><span class="badge text-bg-primary">' . h(lang('Prototype')) . '</span></nav>
<main class="pg-proto-main">
<form method="post" action="' . h(isset($view['action']) ? (string) $view['action'] : '') . '" class="pg-proto-pass card shadow-sm">
<div class="card-body">
<h1 class="h5 mb-2"><span class="bi bi-lock me-2" aria-hidden="true"></span>' . h(lang('This prototype is protected with a password')) . '</h1>
<p class="text-body-secondary small mb-3">' . h(lang('Enter the password you were given with the link.')) . '</p>
' . (($error !== '') ? '<div class="alert alert-' . ($locked ? 'warning' : 'danger') . ' small py-2" role="alert">' . h($error) . '</div>' : '') . '
' . (isset($view['token_field']) ? (string) $view['token_field'] : '') . '
<label class="form-label" for="pg-proto-password">' . h(lang('Password')) . '</label>
<input type="password" class="form-control mb-3" id="pg-proto-password" name="password" autocomplete="current-password" maxlength="128" required' . ($locked ? '' : ' autofocus') . $off . '>
<button type="submit" class="btn btn-primary w-100"' . $off . '>' . h(lang('Open the prototype')) . '</button>
</div>
</form>
</main>
</body>
</html>';
}

/* ---------------------------------------------------------------------------
   The store
   --------------------------------------------------------------------------- */

/**
 * Does the database carry share links (2026.11.0, 11.0.35)?
 *
 * @return bool
 */
function pg_proto_links_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('prototype_links', 'token_hash');
    }

    return $ready;
}

/**
 * The address a link is opened by. It leaves the request (it is copied and
 * sent on), so it names the site by its setting, not by the request's host.
 *
 * @param string $token
 * @return string
 */
function pg_proto_link_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/prototype.php?link=' . rawurlencode((string) $token);
}

/**
 * The token as it is kept for showing again: encrypted with the site's key,
 * "<ciphertext>:<iv>", as the calendar feeds keep theirs
 * (ws_calendar_feed_seal()). '' where the site has no key, and the address is
 * then shown once, when it is made.
 *
 * @param string $token
 * @return string
 */
function pg_proto_link_seal($token)
{
    if (!defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '') || !function_exists('openssl_encrypt')) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv((string) $token);

    return $cipher . ':' . $iv;
}

/**
 * A link's token read back from its row; '' when it cannot be (no key, or a
 * key changed since, so the token would no longer match its hash).
 *
 * @param array $row
 * @return string
 */
function pg_proto_link_token($row)
{
    $sealed = isset($row['token_secret']) ? (string) $row['token_secret'] : '';

    if (($sealed === '') || (strpos($sealed, ':') === false) || !defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '') || !function_exists('openssl_decrypt')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);
    $token = decode_ssl_keys($cipher, $iv);

    if (!pg_proto_link_token_shape($token) || !hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
        return '';
    }

    return $token;
}

/**
 * The link a token opens; null for a token of the wrong shape or none made.
 *
 * @param mixed $token
 * @return array|null
 */
function pg_proto_link_by_token($token)
{
    if (!pg_proto_link_token_shape($token)) {
        return null;
    }

    $hash = hash('sha256', $token);
    $row = db_item("SELECT * FROM prototype_links WHERE token_hash = '" . e($hash) . "' LIMIT 1");

    // The lookup compares in the index; the hash is compared once more in
    // constant time.
    return (is_array($row) && hash_equals((string) $row['token_hash'], $hash)) ? $row : null;
}

/**
 * One link by its id.
 *
 * @param int $link_id
 * @return array|null
 */
function pg_proto_link_get($link_id)
{
    $row = db_item("SELECT * FROM prototype_links WHERE id = '" . (int) $link_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The links of a design that were not revoked, newest first.
 *
 * @param int $style_id
 * @return array[]
 */
function pg_proto_links_of_design($style_id)
{
    $rows = db_items("SELECT * FROM prototype_links WHERE style_id = '" . (int) $style_id . "' AND revoked_at = 0 ORDER BY id DESC LIMIT 200");

    return is_array($rows) ? $rows : array();
}

/**
 * The name of a visual design; null when there is no such design.
 *
 * @param int $style_id
 * @return string|null
 */
function pg_proto_link_design_name($style_id)
{
    $style_id = (int) $style_id;
    $designs = pg_visual_design_style_ids();

    if (($style_id <= 0) || !isset($designs[$style_id])) {
        return null;
    }

    $name = db_value("SELECT style_name FROM style WHERE style_id = '" . $style_id . "'");

    return ($name === null) ? null : (string) $name;
}

/**
 * Makes a link (pg_proto_link_plan()'s link) and writes its Site Log line.
 *
 * @param int    $style_id
 * @param array  $link     audience, start_page_id, expires_at, password
 * @param int    $user_id  who made it
 * @param string $username for the Site Log
 * @param string $via      appended to the log line (the API application)
 * @return array|null row, token; null when the password could not be hashed
 */
function pg_proto_link_issue($style_id, $link, $user_id, $username, $via = '')
{
    $password = (string) $link['password'];
    $hash = null;

    if ($password !== '') {
        // PHP 8 throws (ValueError) on a password bcrypt cannot take; the
        // callers answer their usual failure instead of a fatal error.
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
        } catch (Throwable $t) {
            $hash = null;
        }

        if (!is_string($hash) || ($hash === '')) {
            return null;
        }
    }

    $token = pg_short_link_new_token();

    db("INSERT INTO prototype_links
            (style_id, start_page_id, token_hash, token_secret, token_hint, audience, password_hash, expires_at, created_by, created_at)
        VALUES (
            '" . (int) $style_id . "',
            '" . (int) $link['start_page_id'] . "',
            '" . e(hash('sha256', $token)) . "',
            '" . e(pg_proto_link_seal($token)) . "',
            '" . e(pg_proto_link_hint($token)) . "',
            '" . e((string) $link['audience']) . "',
            " . (($hash === null) ? 'NULL' : "'" . e($hash) . "'") . ",
            '" . (int) $link['expires_at'] . "',
            '" . (int) $user_id . "',
            '" . time() . "')");

    $row = pg_proto_link_get((int) mysqli_insert_id(db::$con));

    if ($row === null) {
        return null;
    }

    // Named by its id and hint: the token itself is in no log.
    $vars = array((int) $row['id'], (string) $row['token_hint'], (string) pg_proto_link_design_name($style_id));
    $line = ((string) $row['audience'] === 'anyone')
        ? lang(array('string' => 'prototype link #{var:1} ({var:2}…) of design ({var:3}) was shared with anyone who has it', 'vars' => $vars))
        : lang(array('string' => 'prototype link #{var:1} ({var:2}…) of design ({var:3}) was shared with the team', 'vars' => $vars));

    log_activity($line . (string) $via, (string) $username);

    return array('row' => $row, 'token' => $token);
}

/**
 * Revokes a link: from now on it answers as an unknown one does. Writes its
 * Site Log line.
 *
 * @param array  $row
 * @param int    $user_id
 * @param string $username
 * @param string $via
 * @return bool false when it was revoked already
 */
function pg_proto_link_revoke($row, $user_id, $username, $via = '')
{
    db("UPDATE prototype_links SET revoked_at = '" . time() . "', revoked_by = '" . (int) $user_id . "'
        WHERE id = '" . (int) $row['id'] . "' AND revoked_at = 0");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    $design = (string) pg_proto_link_design_name($row['style_id']);

    log_activity(lang(array('string' => 'prototype link #{var:1} ({var:2}…) of design ({var:3}) was revoked', 'vars' => array((int) $row['id'], (string) $row['token_hint'], $design))) . (string) $via, (string) $username);

    return true;
}

/**
 * The statement that revokes every link of the given designs not revoked
 * yet; '' for no design.
 *
 * @param int|int[] $style_ids
 * @param int       $user_id   0 for none
 * @param int       $now
 * @return string
 */
function pg_proto_links_revoke_design_sql($style_ids, $user_id, $now)
{
    $ids = array();

    foreach ((array) $style_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    if (!$ids) {
        return '';
    }

    return "UPDATE prototype_links SET revoked_at = '" . (int) $now . "', revoked_by = '" . (int) $user_id . "'"
        . " WHERE style_id IN (" . implode(',', $ids) . ") AND revoked_at = 0";
}

/**
 * Ends the links of designs whose style rows are being deleted (the designer's
 * delete, the recycle bin, the styles list, a template replacing the starter
 * site). A link knows its design by id alone, and a later design can be given
 * the same id (InnoDB on MySQL 5.7 sets the next id from the highest one left
 * when the server restarts): its drafts would open for the old links.
 *
 * @param int|int[] $style_ids
 * @param int       $user_id
 * @return int how many links were revoked
 */
function pg_proto_links_revoke_design($style_ids, $user_id = 0)
{
    $sql = pg_proto_links_revoke_design_sql($style_ids, $user_id, time());

    if (($sql === '') || !pg_proto_links_ready()) {
        return 0;
    }

    db($sql);

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * Counts a use of a link: once per browser session, with its time; the
 * address opened again in the same session moves the time on, at most once
 * a minute.
 *
 * @param array $row
 * @param int   $now
 * @return void
 */
function pg_proto_link_record_use($row, $now)
{
    $id = (int) $row['id'];
    $now = (int) $now;

    if (empty($_SESSION['software']['prototype_link_seen'][$id])) {
        $_SESSION['software']['prototype_link_seen'][$id] = $now;
        db("UPDATE prototype_links SET uses = uses + 1, last_used_at = '" . $now . "' WHERE id = '" . $id . "'");
    } elseif ((int) $row['last_used_at'] < ($now - 60)) {
        db("UPDATE prototype_links SET last_used_at = '" . $now . "' WHERE id = '" . $id . "'");
    }
}

/**
 * Checks a password given for a link. The attempt is counted on the row
 * first, and only while the password is not locked
 * (pg_proto_link_attempt_sql()); a locked password is not checked at all. A
 * right one clears the count and the lock. From the limit on, each wrong one
 * leaves the password locked (with a Site Log line) and, like every attempt
 * while it is locked, counts as a firewall offence.
 *
 * @param array  $row
 * @param mixed  $password
 * @param int    $now
 * @return string ok, wrong, locked
 */
function pg_proto_link_password_try($row, $password, $now)
{
    $id = (int) $row['id'];

    // The lock is the database's, not the one in the row this request read
    // when it began: a request sent at the same moment may have set it.
    db(pg_proto_link_attempt_sql($id, $now));

    if (mysqli_affected_rows(db::$con) !== 1) {
        pg_proto_link_offence(429, 'prototype link password locked');
        return 'locked';
    }

    if (is_string($password) && ($password !== '') && password_verify($password, (string) $row['password_hash'])) {
        db("UPDATE prototype_links SET password_failures = 0, password_locked_until = 0 WHERE id = '" . $id . "'");

        return 'ok';
    }

    $failures = (int) db_value("SELECT password_failures FROM prototype_links WHERE id = '" . $id . "'");

    if (pg_proto_link_lock_after($failures, $now) <= 0) {
        return 'wrong';
    }

    $design = (string) pg_proto_link_design_name($row['style_id']);

    log_activity(lang(array('string' => 'the password of prototype link #{var:1} ({var:2}…) of design ({var:3}) was locked for {var:4} minutes after {var:5} wrong passwords', 'vars' => array($id, (string) $row['token_hint'], $design, (int) (pg_proto_link_lock_seconds() / 60), $failures))), '');

    pg_proto_link_offence(429, 'prototype link password locked');

    return 'locked';
}

/**
 * Leaves the cookie of a right password: for prototype.php alone, HttpOnly,
 * SameSite=Lax, Secure on https.
 *
 * @param array  $row
 * @param int    $now
 * @param string $path PATH . SOFTWARE_DIRECTORY . '/prototype.php'
 * @return bool
 */
function pg_proto_link_set_pass_cookie($row, $now, $path)
{
    $expires = (int) $now + pg_proto_link_pass_ttl();
    $value = pg_proto_link_pass_value($row['id'], (string) $row['password_hash'], $expires, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '');

    if ($value === '') {
        return false;
    }

    $name = pg_proto_link_pass_cookie_name($row['id']);
    $secure = (URL_SCHEME === 'https://');

    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, array('expires' => $expires, 'path' => (string) $path, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie($name, $value, $expires, (string) $path . '; samesite=Lax', '', $secure, true);
    }

    $_COOKIE[$name] = $value;

    return true;
}

/**
 * A link that does not open, or a password tried while locked, counted
 * toward a ban like a wrong guest token: the event and the fail2ban line are
 * written (with the address already redacted by prototype.php) and the
 * offence registered. Monitor mode counts it without blocking.
 *
 * @param int    $status the answer's status, for the fail2ban line
 * @param string $why
 * @return void
 */
function pg_proto_link_offence($status = 404, $why = 'prototype link not valid')
{
    if (!function_exists('waf_client_ip') || !isset(db::$con) || !db::$con) {
        return;
    }

    $blocking = (!function_exists('waf_mode') || (waf_mode() === 'block'));

    if (function_exists('waf_log_event')) {
        waf_log_event($blocking ? 'block' : 'would-block', 'prototype-link', 'protocol', 5, 'prototype.php', (string) $why);
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', waf_client_ip(), array('status' => (int) $status, 'rule' => 'prototype-link'));
    }

    if (function_exists('waf_register_offence')) {
        waf_register_offence(waf_client_ip(), 5, $blocking);
    }
}

/* ---------------------------------------------------------------------------
   The editor's Share prototype dialog
   --------------------------------------------------------------------------- */

/**
 * The links of a design as the editor's dialog lists them: what
 * pg_proto_link_summary() says, with the start page's label (the first
 * draft's when the link starts there or its own page is no longer a draft),
 * who made it, and whether its address can be shown again.
 *
 * @param array[] $rows  prototype_links rows
 * @param array[] $pages pg_proto_design_pages()
 * @param int     $now
 * @return array[]
 */
function pg_proto_links_panel_rows($rows, $pages, $now)
{
    $labels = array();

    foreach ((array) $pages as $page) {
        $labels[(int) $page['id']] = ((string) $page['title'] !== '') ? (string) $page['title'] : (string) $page['name'];
    }

    $first = $pages ? (int) $pages[0]['id'] : 0;
    $makers = array();

    foreach ((array) $rows as $row) {
        $makers[(int) $row['created_by']] = '';
    }

    unset($makers[0]);

    if ($makers) {
        foreach (db_items("SELECT user_id, user_username FROM user WHERE user_id IN (" . implode(',', array_map('intval', array_keys($makers))) . ")") as $maker) {
            $makers[(int) $maker['user_id']] = (string) $maker['user_username'];
        }
    }

    $out = array();

    foreach ((array) $rows as $row) {
        $item = pg_proto_link_summary($row, $now);
        $own = isset($labels[$item['start_page_id']]);
        $start = $own ? $item['start_page_id'] : $first;

        $item['start_page'] = isset($labels[$start]) ? $labels[$start] : '';
        $item['start_first'] = !$own;
        $item['created_by_name'] = isset($makers[$item['created_by']]) ? $makers[$item['created_by']] : '';
        $item['can_show'] = ((string) $row['token_secret'] !== '');
        $out[] = $item;
    }

    return $out;
}

/**
 * The designer endpoint's prototype link sub-actions (api.php, action
 * designer): prototype_links (a design's links, its drafts and the expiry
 * choices), prototype_link_create, prototype_link_address (shown again) and
 * prototype_link_revoke. The endpoint opens them to administrators and
 * designers only; the role is checked here once more.
 *
 * @param string $sub
 * @param array  $request
 * @param array  $user validate_user()
 * @return array the answer to respond()
 */
function pg_proto_links_panel($sub, $request, $user)
{
    if (!isset($user['role']) || ((int) $user['role'] > 1)) {
        return array('status' => 'error', 'message' => lang('Permission denied.'));
    }

    if (!pg_proto_links_ready()) {
        return array('status' => 'error', 'message' => lang('This site has not been upgraded to the version that shares prototypes.'));
    }

    $now = time();

    if (($sub === 'prototype_link_address') || ($sub === 'prototype_link_revoke')) {
        $row = pg_proto_link_get(isset($request['link_id']) ? (int) $request['link_id'] : 0);

        if (($row === null) || ((int) $row['revoked_at'] > 0)) {
            return array('status' => 'error', 'message' => lang('The link could not be found.'));
        }

        if ($sub === 'prototype_link_revoke') {
            pg_proto_link_revoke($row, (int) $user['id'], (string) $user['username']);

            return array('status' => 'success', 'id' => (int) $row['id']);
        }

        $token = pg_proto_link_token($row);

        if ($token === '') {
            return array('status' => 'error', 'message' => lang('This address cannot be shown again. Revoke the link and make a new one.'));
        }

        return array('status' => 'success', 'id' => (int) $row['id'], 'url' => pg_proto_link_url($token));
    }

    $style_id = isset($request['style_id']) ? (int) $request['style_id'] : 0;

    if (pg_proto_link_design_name($style_id) === null) {
        return array('status' => 'error', 'message' => lang('The design could not be found.'));
    }

    $pages = pg_proto_design_pages($style_id);

    if ($sub === 'prototype_link_create') {
        $draft_ids = array();

        foreach ($pages as $page) {
            $draft_ids[] = (int) $page['id'];
        }

        $plan = pg_proto_link_plan(array(
            'audience' => isset($request['audience']) ? $request['audience'] : '',
            'start_page_id' => isset($request['start_page_id']) ? $request['start_page_id'] : 0,
            'duration' => isset($request['duration']) ? $request['duration'] : 0,
            'password' => isset($request['password']) ? $request['password'] : '',
        ), $draft_ids, array_keys(pg_proto_link_durations()), $now);

        if (!$plan['ok']) {
            return array('status' => 'error', 'message' => pg_proto_link_error_message($plan['error']), 'field' => $plan['field']);
        }

        $made = pg_proto_link_issue($style_id, $plan['link'], (int) $user['id'], (string) $user['username']);

        if ($made === null) {
            return array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.'));
        }

        $rows = pg_proto_links_panel_rows(array($made['row']), $pages, $now);

        return array('status' => 'success', 'link' => $rows[0], 'url' => pg_proto_link_url($made['token']));
    }

    $page_list = array();

    foreach ($pages as $page) {
        $page_list[] = array('id' => (int) $page['id'], 'label' => ((string) $page['title'] !== '') ? (string) $page['title'] : (string) $page['name']);
    }

    $durations = array();

    foreach (pg_proto_link_durations() as $seconds => $label) {
        $durations[] = array('seconds' => (int) $seconds, 'label' => (string) $label);
    }

    return array(
        'status' => 'success',
        'links' => pg_proto_links_panel_rows(pg_proto_links_of_design($style_id), $pages, $now),
        'pages' => $page_list,
        'durations' => $durations,
        // Without a key the address is shown once, when the link is made.
        'can_show' => (pg_proto_link_seal('probe') !== ''),
    );
}

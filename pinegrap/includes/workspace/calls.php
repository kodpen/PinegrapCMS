<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - voice and video calls, the server's side. Sound and picture go
 * from browser to browser; the site keeps only who is in a call
 * (ws_calls, ws_call_members) and the browsers' short-lived connection
 * messages (ws_call_signals: offer, answer, candidate, and a keep-alive).
 * Nothing of a call's media reaches the server and nothing is recorded.
 *
 * Who takes part: the members of the channel (a direct conversation is a
 * private channel of its two people), and a guest in its own room, who may
 * join a call but not start one. Four people at most, every pair connected.
 *
 * The live reads carry a call (the panel's ws_sync, the guest page's state):
 * a stamp file exists only while a call is live on the site, so an idle
 * channel runs no call query. A call left by everyone, or with no signal for
 * WS_CALL_SILENCE seconds, ends; its signals are deleted then, and any
 * signal after WS_CALL_SIGNAL_LIFE seconds.
 *
 * Every member may share a screen, several at once, one share each: its
 * share and unshare signals, addressed to nobody, say who; a sharer renews
 * its share with the keep-alive, and a member's signals go when it leaves,
 * so its share ends. A raised hand works the same way (hand and unhand),
 * one for each member.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most people in one call: every pair is connected, so the load grows fast. */
define('WS_CALL_MAX_MEMBERS', 4);

/** Seconds without any signal after which a call ends. */
define('WS_CALL_SILENCE', 120);

/** Seconds a signal is kept, read or not. */
define('WS_CALL_SIGNAL_LIFE', 120);

/** Calls one person may start in a minute. */
define('WS_CALL_STARTS_A_MINUTE', 3);

/** Signals one member of a call may send in a minute. */
define('WS_CALL_SIGNALS_A_MINUTE', 300);

/** The longest signal body, in bytes (a session description with video). */
define('WS_CALL_SIGNAL_MAX', 32768);

/** The most signals one read hands over. */
define('WS_CALL_SIGNAL_READ', 100);

/** Seconds between two clean-ups while a call is live. */
define('WS_CALL_SWEEP_EVERY', 10);

/** Seconds a new call rings the other person of a conversation of two. */
define('WS_CALL_RING', 45);

/**
 * Seconds a screen share holds without its sharer renewing it (the browser
 * renews it with every keep-alive, every 30 seconds: PING_EVERY in
 * assets/js/workspace_calls.js), so a closed tab's share does not stay on
 * the others' screens.
 */
define('WS_CALL_SHARE_LIFE', 75);

/**
 * Seconds a raised hand holds without its member renewing it (with the
 * keep-alive, as a share), so a closed tab's hand comes down.
 */
define('WS_CALL_HAND_LIFE', 75);

/**
 * Seconds a member may go unheard (no signal of its own, not even the
 * keep-alive every 30 seconds) before the clean-up takes it out of the
 * call: a tab closed without its leave arriving, a phone that lost the
 * page. Its place and its share free up long before the whole call would
 * fall silent.
 */
define('WS_CALL_MEMBER_SILENCE', 90);

/**
 * Does the database carry calls (2026.11.0, 11.0.243)?
 *
 * @return bool
 */
function ws_calls_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_calls') && pg_schema_has('ws_call_members') && pg_schema_has('ws_call_signals');
    }

    return $ready;
}

/**
 * The kinds of a call.
 *
 * @return string[]
 */
function ws_call_kinds()
{
    return array('voice', 'video');
}

/**
 * The kinds of a signal. A ping is the keep-alive, addressed to nobody; so
 * are share (the sender shows its screen, renewed with each keep-alive) and
 * unshare (it stopped).
 *
 * @return string[]
 */
function ws_call_signal_kinds()
{
    return array('offer', 'answer', 'candidate', 'ping', 'share', 'unshare', 'hand', 'unhand');
}

/**
 * Is a signal of this kind addressed to nobody (the keep-alive, the share
 * and hand marks)? Pure.
 *
 * @param string $kind
 * @return bool
 */
function ws_call_signal_to_nobody($kind)
{
    return in_array((string) $kind, array('ping', 'share', 'unshare', 'hand', 'unhand'), true);
}

// ── The stamp that keeps an idle channel's reads free ──

/**
 * The stamp file that exists while a call is live on the site.
 *
 * @return string
 */
function ws_calls_stamp_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/ws_calls_live';
}

/**
 * Can the stamp be trusted: its folder is there and can be written?
 *
 * @return bool
 */
function ws_calls_stamp_usable()
{
    static $usable = null;

    if ($usable === null) {
        $directory = dirname(ws_calls_stamp_path());
        $usable = is_dir($directory) && (function_exists('pg_dir_writable') ? pg_dir_writable($directory) : is_writable($directory));
    }

    return $usable;
}

/**
 * Whether a read must look for a call at all. Pure but for the file check.
 *
 * @param bool $usable ws_calls_stamp_usable()
 * @param bool $stamp  the stamp file is there
 * @return bool
 */
function ws_calls_may_be_live($usable, $stamp)
{
    // Without a folder to keep the stamp in, every read asks the database:
    // a call must never go unseen.
    return !$usable || $stamp;
}

/**
 * Is a call possibly live on the site? One file check, no database.
 *
 * @return bool
 */
function ws_calls_live_somewhere()
{
    return ws_calls_may_be_live(ws_calls_stamp_usable(), is_file(ws_calls_stamp_path()));
}

/**
 * Lays the stamp down: a call went live.
 */
function ws_calls_stamp_set()
{
    if (ws_calls_stamp_usable()) {
        @file_put_contents(ws_calls_stamp_path(), (string) time(), LOCK_EX);
        clearstatcache(true, ws_calls_stamp_path());
    }
}

/**
 * Takes the stamp away when no call is live any more.
 */
function ws_calls_stamp_clear_if_idle()
{
    if (!ws_calls_stamp_usable() || !is_file(ws_calls_stamp_path())) {
        return;
    }

    if (!db_value("SELECT id FROM ws_calls WHERE state = 'live' LIMIT 1")) {
        @unlink(ws_calls_stamp_path());
        clearstatcache(true, ws_calls_stamp_path());
    }
}

// ── The rules, pure ──

/**
 * May this party take part in calls of this channel? Pure.
 *
 * A party is a person (kind user, member: they have a membership row in the
 * channel) or a guest (kind guest, channel_id: the room the guest belongs
 * to, read_only: the channel was shared with them to read only).
 *
 * @param array      $party   kind, member | channel_id, read_only
 * @param array|null $channel the ws_channels row
 * @param bool       $start   the party is starting a call
 * @return string '' when it may, else the refusal
 */
function ws_call_party_check($party, $channel, $start)
{
    if (!is_array($channel) || ((int) ($channel['archived_at'] ?? 0) > 0)) {
        return lang('This call is not open to you.');
    }

    // A mailbox's room carries mail and a discussion lives in its channel:
    // neither holds a call of its own.
    if (in_array((string) ($channel['kind'] ?? ''), array('mailbox', 'thread'), true)) {
        return lang('Calls are not available in this channel.');
    }

    if ((string) ($party['kind'] ?? '') === 'guest') {
        if ((int) ($party['channel_id'] ?? 0) !== (int) $channel['id']) {
            return lang('This call is not open to you.');
        }

        if (!empty($party['read_only'])) {
            return lang('This conversation was shared with you to read only.');
        }

        return $start ? lang('A guest can join a call, not start one.') : '';
    }

    if ((string) ($party['kind'] ?? '') !== 'user' || empty($party['member'])) {
        return lang('Only the members of the channel take part in its calls.');
    }

    return '';
}

/**
 * May one more join this call? Pure.
 *
 * @param array|null $call       the ws_calls row
 * @param int        $live_count members in it now
 * @return string '' when they may, else the refusal
 */
function ws_call_join_check($call, $live_count)
{
    if (!is_array($call) || ((string) $call['state'] !== 'live')) {
        return lang('This call has ended.');
    }

    if ((int) $live_count >= WS_CALL_MAX_MEMBERS) {
        return lang('This call is full.');
    }

    return '';
}

/**
 * Is a signal well formed and addressed within its call? Pure.
 *
 * @param array      $from  the sender's live ws_call_members row
 * @param array|null $to    the addressee's live row, null for a ping
 * @param string     $kind
 * @param string     $body
 * @return string '' when it may be sent, else the refusal
 */
function ws_call_signal_check($from, $to, $kind, $body)
{
    if (!in_array((string) $kind, ws_call_signal_kinds(), true)) {
        return lang('Invalid request.');
    }

    if (strlen((string) $body) > WS_CALL_SIGNAL_MAX) {
        return lang('The connection message is too long.');
    }

    // The keep-alive and the share marks are read by nobody.
    if (ws_call_signal_to_nobody($kind)) {
        return '';
    }

    if (!is_array($to) || ((int) $to['call_id'] !== (int) $from['call_id']) || ((int) $to['left_at'] > 0)
        || ((int) $to['id'] === (int) $from['id'])) {
        return lang('That person is not in this call.');
    }

    return '';
}

/**
 * Who shares a screen in a call now? Pure. Each member's newest share or
 * unshare mark decides for that member: a share by a member still in the
 * call, renewed within WS_CALL_SHARE_LIFE seconds. A member's marks go when
 * it leaves.
 *
 * @param array[] $marks    share/unshare signals, oldest first: from_member_id, kind, created_at
 * @param int[]   $live_ids the member ids in the call now
 * @param int     $now
 * @return int[] the sharers' member ids, ascending
 */
function ws_call_sharers_from($marks, $live_ids, $now)
{
    $newest = array();

    foreach ((array) $marks as $mark) {
        $newest[(int) $mark['from_member_id']] = $mark;
    }

    $live = array_map('intval', (array) $live_ids);
    $sharers = array();

    foreach ($newest as $member_id => $mark) {
        if (((string) $mark['kind'] === 'share') && ((int) $mark['created_at'] > ((int) $now - WS_CALL_SHARE_LIFE))
            && in_array((int) $member_id, $live, true)) {
            $sharers[] = (int) $member_id;
        }
    }

    sort($sharers);

    return $sharers;
}

/**
 * Whose hands are raised in a call now? Pure. Each member's newest hand or
 * unhand mark decides for that member: a hand by a member still in the
 * call, renewed within WS_CALL_HAND_LIFE seconds. A hand is the member's
 * own, so nobody lowers another's.
 *
 * @param array[] $marks    hand/unhand signals, newest first: from_member_id, kind, created_at
 * @param int[]   $live_ids the member ids in the call now
 * @param int     $now
 * @return int[] the member ids with a raised hand, in member order
 */
function ws_call_hands_from($marks, $live_ids, $now)
{
    $live_ids = array_map('intval', (array) $live_ids);
    $seen = array();
    $hands = array();

    foreach ((array) $marks as $mark) {
        $member_id = (int) $mark['from_member_id'];

        if (isset($seen[$member_id])) {
            continue;
        }

        $seen[$member_id] = true;

        if (((string) $mark['kind'] === 'hand') && ((int) $mark['created_at'] > ((int) $now - WS_CALL_HAND_LIFE))
            && in_array($member_id, $live_ids, true)) {
            $hands[] = $member_id;
        }
    }

    sort($hands);

    return $hands;
}

/**
 * May this member start or renew a share, or stop one? Pure. A member may
 * share whoever else shares (one share each); stopping is the sharer's own,
 * and from a member not sharing it changes nothing (null: write nothing).
 *
 * @param array  $from    the sender's live ws_call_members row
 * @param int[]  $sharers ws_call_sharers_from()
 * @param string $kind    share | unshare
 * @return string|null '' to write the mark, null to write nothing
 */
function ws_call_share_check($from, $sharers, $kind)
{
    if ((string) $kind === 'unshare') {
        return in_array((int) $from['id'], array_map('intval', (array) $sharers), true) ? '' : null;
    }

    return '';
}

/**
 * Has a count of the last minute reached its limit? Pure.
 *
 * @param int $count
 * @param int $limit
 * @return bool
 */
function ws_call_over_limit($count, $limit)
{
    return (int) $count >= (int) $limit;
}

/**
 * Has a call gone silent: nothing started, joined or signalled in it for
 * WS_CALL_SILENCE seconds? Pure.
 *
 * @param array $call           the ws_calls row
 * @param int   $last_signal_at its newest signal, 0 for none
 * @param int   $last_join_at   its newest join, 0 for none
 * @param int   $now
 * @return bool
 */
function ws_call_is_silent($call, $last_signal_at, $last_join_at, $now)
{
    $heard = max((int) $call['started_at'], (int) $last_signal_at, (int) $last_join_at);

    return $heard < ((int) $now - WS_CALL_SILENCE);
}

/**
 * Has a member gone unheard too long: nothing of its own since it joined,
 * or since its last signal, for WS_CALL_MEMBER_SILENCE seconds? Pure.
 *
 * @param array $member         its live ws_call_members row
 * @param int   $last_signal_at its own newest signal, 0 for none
 * @param int   $now
 * @return bool
 */
function ws_call_member_is_gone($member, $last_signal_at, $now)
{
    $heard = max((int) $member['joined_at'], (int) $last_signal_at);

    return $heard < ((int) $now - WS_CALL_MEMBER_SILENCE);
}

/**
 * What a join does with the party's place: a new one, a refusal while it
 * is in the call in another window (two windows of one person would be one
 * member to the others, and their connections would cross), or that
 * window's place taken over when the person chose Join here. Pure.
 *
 * @param array|null $mine  the party's live row in the call
 * @param bool       $again the person chose Join here
 * @return string new | elsewhere | replace
 */
function ws_call_join_place($mine, $again)
{
    if (!is_array($mine)) {
        return 'new';
    }

    return $again ? 'replace' : 'elsewhere';
}

/**
 * Is a window's place the party's live row? A window names its member id
 * with a leave and a signal, so a window whose place another one took over
 * neither leaves nor speaks for the new one. No id (0): the party's row.
 * Pure.
 *
 * @param array|null $mine      the party's live row
 * @param int        $member_id the place the window holds, 0 when not named
 * @return bool
 */
function ws_call_member_matches($mine, $member_id)
{
    if (!is_array($mine)) {
        return false;
    }

    return ((int) $member_id === 0) || ((int) $member_id === (int) $mine['id']);
}

/**
 * May this person end the call for everyone? Its starter, or whoever may
 * manage the channel. A guest never. Pure.
 *
 * @param array $party
 * @param array $call
 * @param bool  $manages ws_can_manage_channel() for a person
 * @return bool
 */
function ws_call_may_end($party, $call, $manages)
{
    if ((string) ($party['kind'] ?? '') !== 'user') {
        return false;
    }

    return ((int) $party['id'] === (int) $call['started_by']) || $manages;
}

/**
 * A call as the screens and the API read it. Pure.
 *
 * @param array    $call    the ws_calls row
 * @param array[]  $members its live ws_call_members rows
 * @param string[] $names   'user:<id>' / 'guest:<id>' => name
 * @param int[]    $sharers the member ids sharing a screen
 * @param int[]    $hands   the member ids with a raised hand
 * @return array
 */
function ws_call_present($call, $members, $names, $sharers = array(), $hands = array())
{
    $people = array();

    foreach ((array) $members as $member) {
        $guest = (int) $member['guest_id'] > 0;
        $key = $guest ? 'guest:' . (int) $member['guest_id'] : 'user:' . (int) $member['user_id'];

        $people[] = array(
            'member_id' => (int) $member['id'],
            'kind'      => $guest ? 'guest' : 'person',
            'user_id'   => $guest ? 0 : (int) $member['user_id'],
            'name'      => (string) ($names[$key] ?? ''),
            'joined_at' => (int) $member['joined_at'],
        );
    }

    return array(
        'id'         => (int) $call['id'],
        'channel_id' => (int) $call['channel_id'],
        'kind'       => (string) $call['kind'],
        'started_by' => (int) $call['started_by'],
        'started_at' => (int) $call['started_at'],
        'members'    => $people,
        'sharers'    => array_values(array_map('intval', (array) $sharers)),
        'hands'      => array_values(array_map('intval', (array) $hands)),
    );
}

// ── Reading ──

/**
 * One call.
 *
 * @param int $call_id
 * @return array|null
 */
function ws_call($call_id)
{
    $row = db_item("SELECT * FROM ws_calls WHERE id = '" . (int) $call_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The live call of a channel.
 *
 * @param int $channel_id
 * @return array|null
 */
function ws_call_live($channel_id)
{
    $row = db_item("SELECT * FROM ws_calls WHERE channel_id = '" . (int) $channel_id . "' AND state = 'live' ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The people in a call now.
 *
 * @param int $call_id
 * @return array[]
 */
function ws_call_members_live($call_id)
{
    return (array) db_items("SELECT * FROM ws_call_members WHERE call_id = '" . (int) $call_id . "' AND left_at = 0 ORDER BY id");
}

/**
 * The party's own live row in a call.
 *
 * @param array $party
 * @param int   $call_id
 * @return array|null
 */
function ws_call_member_of($party, $call_id)
{
    $column = ((string) $party['kind'] === 'guest') ? 'guest_id' : 'user_id';
    $row = db_item("SELECT * FROM ws_call_members WHERE call_id = '" . (int) $call_id . "' AND " . $column . " = '" . (int) $party['id'] . "' AND left_at = 0 ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Who shares a screen in a call now (ws_call_sharers_from()). Only the marks
 * young enough to hold a share are read: an older one is overruled by a
 * newer mark or has lapsed.
 *
 * @param int     $call_id
 * @param array[] $members its live ws_call_members rows
 * @return int[] the sharers' member ids
 */
function ws_call_sharers($call_id, $members)
{
    $now = time();
    $marks = db_items("SELECT from_member_id, kind, created_at FROM ws_call_signals
        WHERE call_id = '" . (int) $call_id . "' AND to_member_id = 0 AND kind IN ('share', 'unshare')
        AND created_at > '" . ($now - WS_CALL_SHARE_LIFE) . "'
        ORDER BY id LIMIT 500");
    $live_ids = array();

    foreach ((array) $members as $member) {
        $live_ids[] = (int) $member['id'];
    }

    return ws_call_sharers_from(is_array($marks) ? $marks : array(), $live_ids, $now);
}

/**
 * Whose hands are raised in a call now (ws_call_hands_from()): the call's
 * hand marks of the last WS_CALL_HAND_LIFE seconds (idx_call).
 *
 * @param int     $call_id
 * @param array[] $members its live ws_call_members rows
 * @return int[]
 */
function ws_call_hands($call_id, $members)
{
    $now = time();
    $live_ids = array();

    foreach ((array) $members as $member) {
        $live_ids[] = (int) $member['id'];
    }

    $marks = db_items("SELECT from_member_id, kind, created_at FROM ws_call_signals
        WHERE call_id = '" . (int) $call_id . "' AND created_at > '" . ($now - WS_CALL_HAND_LIFE) . "'
        AND to_member_id = 0 AND kind IN ('hand', 'unhand')
        ORDER BY id DESC LIMIT " . WS_CALL_SIGNAL_READ);

    return ws_call_hands_from(is_array($marks) ? $marks : array(), $live_ids, $now);
}

/**
 * The names of a call's members, as the channel knows them.
 *
 * @param array[] $members
 * @return string[] 'user:<id>' / 'guest:<id>' => name
 */
function ws_call_member_names($members)
{
    $names = array();
    $user_ids = array();

    foreach ((array) $members as $member) {
        if ((int) $member['guest_id'] > 0) {
            $guest = ws_guest((int) $member['guest_id']);
            $names['guest:' . (int) $member['guest_id']] = $guest ? (string) $guest['name'] : '';
        } else {
            $user_ids[] = (int) $member['user_id'];
        }
    }

    foreach (($user_ids ? ws_people($user_ids) : array()) as $person) {
        $names['user:' . (int) $person['id']] = (string) $person['name'];
    }

    return $names;
}

/**
 * A person as a party of a call in a channel.
 *
 * @param array $viewer
 * @param array $channel
 * @return array
 */
function ws_call_party_person($viewer, $channel)
{
    return array(
        'kind'   => 'user',
        'id'     => (int) $viewer['id'],
        'member' => is_array($channel) && (bool) ws_channel_membership((int) $channel['id'], (int) $viewer['id']),
    );
}

/**
 * A guest as a party of a call (ws_guest_context()).
 *
 * @param array $context
 * @return array
 */
function ws_call_party_guest($context)
{
    return array(
        'kind'       => 'guest',
        'id'         => (int) $context['guest']['id'],
        'channel_id' => (int) $context['channel']['id'],
        'read_only'  => ws_guest_reads_only($context['guest']),
    );
}

// ── Writing ──

/**
 * Holds one channel's calls while a start or a join counts its places.
 *
 * @param int $channel_id
 * @return bool
 */
function ws_call_lock($channel_id)
{
    return (int) db_value("SELECT GET_LOCK('pg_ws_call_" . (int) $channel_id . "', 5)") === 1;
}

function ws_call_unlock($channel_id)
{
    db("SELECT RELEASE_LOCK('pg_ws_call_" . (int) $channel_id . "')");
}

/**
 * A new row of a party in a call.
 *
 * @param array $party
 * @param int   $call_id
 * @param int   $now
 * @return int the member id
 */
function ws_call_member_add($party, $call_id, $now)
{
    $guest = (string) $party['kind'] === 'guest';

    db_q("INSERT INTO ws_call_members (call_id, user_id, guest_id, joined_at, left_at) VALUES (?, ?, ?, ?, 0)",
        array((int) $call_id, $guest ? 0 : (int) $party['id'], $guest ? (int) $party['id'] : 0, (int) $now));

    return (int) mysqli_insert_id(db::$con);
}

/**
 * A member is out of its call: its row closed and its signals deleted, so
 * its screen share ends with them.
 *
 * @param int $member_id
 * @param int $now
 */
function ws_call_member_out($member_id, $now)
{
    db("UPDATE ws_call_members SET left_at = '" . (int) $now . "' WHERE id = '" . (int) $member_id . "' AND left_at = 0");
    db("DELETE FROM ws_call_signals WHERE to_member_id = '" . (int) $member_id . "' OR from_member_id = '" . (int) $member_id . "'");
}

/**
 * Starts a call in a channel, or joins the one already live there.
 *
 * @param array      $party
 * @param array|null $channel
 * @param string     $kind voice | video
 * @return array ok, error, call_id, member_id
 */
function ws_call_start($party, $channel, $kind)
{
    if (!ws_calls_ready()) {
        return array('ok' => false, 'error' => lang('Calls are not available yet.'));
    }

    if (!ws_calls_on()) {
        return array('ok' => false, 'error' => lang('Calls are turned off under Site Settings › Channels › Calls.'));
    }

    $error = ws_call_party_check($party, $channel, true);

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    if (!in_array((string) $kind, ws_call_kinds(), true)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $now = time();
    $started = (int) db_value("SELECT COUNT(*) FROM ws_calls WHERE started_by = '" . (int) $party['id'] . "' AND started_at > '" . ($now - 60) . "'");

    if (ws_call_over_limit($started, WS_CALL_STARTS_A_MINUTE)) {
        return array('ok' => false, 'error' => lang('Too many calls started. Please wait a minute and try again.'));
    }

    if (!ws_call_lock($channel['id'])) {
        return array('ok' => false, 'error' => lang('Please try again in a moment.'));
    }

    ws_calls_sweep(true);
    $live = ws_call_live($channel['id']);

    if ($live) {
        ws_call_unlock($channel['id']);

        // A call is already on here: the person joins it.
        return ws_call_join($party, $channel, (int) $live['id']);
    }

    db_q("INSERT INTO ws_calls (channel_id, started_by, kind, state, started_at, ended_at) VALUES (?, ?, ?, 'live', ?, 0)",
        array((int) $channel['id'], (int) $party['id'], (string) $kind, $now));
    $call_id = (int) mysqli_insert_id(db::$con);
    $member_id = $call_id ? ws_call_member_add($party, $call_id, $now) : 0;

    ws_call_unlock($channel['id']);

    if (!$call_id || !$member_id) {
        return array('ok' => false, 'error' => lang('The call could not be started.'));
    }

    ws_calls_stamp_set();
    ws_call_line_post_started(array('channel_id' => (int) $channel['id'], 'kind' => (string) $kind, 'started_by' => (int) $party['id']));

    return array('ok' => true, 'error' => '', 'call_id' => $call_id, 'member_id' => $member_id);
}

/**
 * Joins a live call. A party already in it is refused (code
 * call_elsewhere: it is in the call in another window), unless it chose
 * Join here: that window's place is then taken over by a new one, so the
 * others connect to this window and the old one reads that it moved.
 *
 * @param array      $party
 * @param array|null $channel
 * @param int        $call_id
 * @param bool       $again   Join here
 * @return array ok, error, call_id, member_id (code on a refusal)
 */
function ws_call_join($party, $channel, $call_id, $again = false)
{
    if (!ws_calls_ready()) {
        return array('ok' => false, 'error' => lang('Calls are not available yet.'));
    }

    if (!ws_calls_on()) {
        return array('ok' => false, 'error' => lang('Calls are turned off under Site Settings › Channels › Calls.'));
    }

    $call = ws_call($call_id);

    if (!$call || !is_array($channel) || ((int) $call['channel_id'] !== (int) $channel['id'])) {
        return array('ok' => false, 'error' => lang('This call has ended.'));
    }

    $error = ws_call_party_check($party, $channel, false);

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    if (!ws_call_lock($channel['id'])) {
        return array('ok' => false, 'error' => lang('Please try again in a moment.'));
    }

    $call = ws_call($call_id);
    $mine = (is_array($call) && ((string) $call['state'] === 'live')) ? ws_call_member_of($party, $call_id) : null;
    $place = ws_call_join_place($mine, $again);

    if ($place === 'elsewhere') {
        ws_call_unlock($channel['id']);

        return array('ok' => false, 'error' => lang('You are already in this call in another window.'), 'code' => 'call_elsewhere');
    }

    // The old window's place goes and this one takes a new place: the count
    // stays the same, so the limit is not asked again.
    if ($place === 'replace') {
        ws_call_member_out((int) $mine['id'], time());
        $error = '';
    } else {
        $error = ws_call_join_check($call, count(ws_call_members_live($call_id)));
    }

    $member_id = ($error === '') ? ws_call_member_add($party, $call_id, time()) : 0;

    ws_call_unlock($channel['id']);

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    return $member_id ? array('ok' => true, 'error' => '', 'call_id' => (int) $call_id, 'member_id' => $member_id)
        : array('ok' => false, 'error' => lang('The call could not be joined.'));
}

/**
 * Leaves a call. The last one out ends it. A window that names its place
 * leaves only that place: one whose place another window took over leaves
 * nothing.
 *
 * @param array $party
 * @param int   $call_id
 * @param int   $member_id the window's place, 0 when not named
 * @return array ok, error, ended
 */
function ws_call_leave($party, $call_id, $member_id = 0)
{
    $call = ws_calls_ready() ? ws_call($call_id) : null;
    $mine = $call ? ws_call_member_of($party, $call_id) : null;

    if (!ws_call_member_matches($mine, $member_id)) {
        return array('ok' => true, 'error' => '', 'ended' => !$call || ((string) $call['state'] !== 'live'));
    }

    ws_call_member_out((int) $mine['id'], time());

    $ended = false;

    if (!ws_call_members_live($call_id)) {
        ws_call_end_row($call);
        $ended = true;
    }

    return array('ok' => true, 'error' => '', 'ended' => $ended);
}

/**
 * Ends a call for everyone: its starter or whoever manages the channel.
 *
 * @param array $party
 * @param array $call
 * @param bool  $manages
 * @return array ok, error
 */
function ws_call_end($party, $call, $manages)
{
    if (!is_array($call) || ((string) $call['state'] !== 'live')) {
        return array('ok' => true, 'error' => '');
    }

    if (!ws_call_may_end($party, $call, $manages)) {
        return array('ok' => false, 'error' => lang('Only the person who started the call or a manager of the channel can end it for everyone.'));
    }

    ws_call_end_row($call);

    return array('ok' => true, 'error' => '');
}

/**
 * The call is over: everyone out, its signals deleted, the stamp taken away
 * when it was the last live call, and its line written in the channel
 * (call_lines.php) by the one request that ended it.
 *
 * @param array $call
 * @param int   $declined_by the user who declined it, 0 for none
 */
function ws_call_end_row($call, $declined_by = 0)
{
    $now = time();
    $id = (int) $call['id'];

    db("UPDATE ws_calls SET state = 'ended', ended_at = '" . $now . "' WHERE id = '" . $id . "' AND state = 'live'");
    $ended_here = (mysqli_affected_rows(db::$con) > 0);
    db("UPDATE ws_call_members SET left_at = '" . $now . "' WHERE call_id = '" . $id . "' AND left_at = 0");
    db("DELETE FROM ws_call_signals WHERE call_id = '" . $id . "'");

    ws_calls_stamp_clear_if_idle();

    if ($ended_here) {
        ws_call_line_post_ended($call, $now, $declined_by);
    }
}

/**
 * A signal from one member of a call to another (or a keep-alive).
 *
 * @param array  $party
 * @param int    $call_id
 * @param int    $to_member_id 0 for a ping
 * @param string $kind
 * @param string $body
 * @param int    $member_id    the sending window's place, 0 when not named
 * @return array ok, error, signal_id
 */
function ws_call_signal_send($party, $call_id, $to_member_id, $kind, $body, $member_id = 0)
{
    $call = ws_calls_ready() ? ws_call($call_id) : null;
    $from = ($call && ((string) $call['state'] === 'live')) ? ws_call_member_of($party, $call_id) : null;

    if (!ws_call_member_matches($from, $member_id)) {
        return array('ok' => false, 'error' => lang('You are not in this call.'));
    }

    $kind = (string) $kind;
    $to = null;

    if (!ws_call_signal_to_nobody($kind)) {
        $to = db_item("SELECT * FROM ws_call_members WHERE id = '" . (int) $to_member_id . "'");
        $to = is_array($to) ? $to : null;
    }

    $error = ws_call_signal_check($from, $to, $kind, (string) $body);

    if ($error !== '') {
        return array('ok' => false, 'error' => $error);
    }

    $now = time();
    $sent = (int) db_value("SELECT COUNT(*) FROM ws_call_signals WHERE from_member_id = '" . (int) $from['id'] . "' AND created_at > '" . ($now - 60) . "'");

    if (ws_call_over_limit($sent, WS_CALL_SIGNALS_A_MINUTE)) {
        return array('ok' => false, 'error' => lang('Too many connection messages. Please wait a moment.'));
    }

    if (($kind === 'share') || ($kind === 'unshare')) {
        return ws_call_share_send($call, $from, $kind, $now);
    }

    db_q("INSERT INTO ws_call_signals (call_id, from_member_id, to_member_id, kind, body, created_at) VALUES (?, ?, ?, ?, ?, ?)",
        array((int) $call_id, (int) $from['id'], $to ? (int) $to['id'] : 0, $kind, ws_call_signal_to_nobody($kind) ? '' : (string) $body, $now));

    return array('ok' => true, 'error' => '', 'signal_id' => (int) mysqli_insert_id(db::$con));
}

/**
 * A share or unshare mark. Each member's marks decide only its own share,
 * so two members sharing at once both have theirs.
 *
 * @param array  $call
 * @param array  $from the sender's live row
 * @param string $kind share | unshare
 * @param int    $now
 * @return array ok, error, signal_id
 */
function ws_call_share_send($call, $from, $kind, $now)
{
    $signal_id = 0;

    if (ws_call_share_check($from, ws_call_sharers((int) $call['id'], ws_call_members_live($call['id'])), $kind) === '') {
        db_q("INSERT INTO ws_call_signals (call_id, from_member_id, to_member_id, kind, body, created_at) VALUES (?, ?, 0, ?, '', ?)",
            array((int) $call['id'], (int) $from['id'], $kind, (int) $now));
        $signal_id = (int) mysqli_insert_id(db::$con);
    }

    return array('ok' => true, 'error' => '', 'signal_id' => $signal_id);
}

/**
 * The signals sent to one member, after the last one it holds. Nobody else
 * reads them; they stay until the clean-up (WS_CALL_SIGNAL_LIFE), so the
 * sender's count of the last minute holds them all.
 *
 * @param int $member_id
 * @param int $after the newest signal id the member already holds
 * @return array[] id, from_member_id, kind, body
 */
function ws_call_signals_for($member_id, $after)
{
    $member_id = (int) $member_id;

    if ($member_id <= 0) {
        return array();
    }

    $signals = array();

    foreach ((array) db_items("SELECT id, from_member_id, kind, body FROM ws_call_signals
        WHERE to_member_id = '" . $member_id . "' AND id > '" . max(0, (int) $after) . "'
        ORDER BY id LIMIT " . WS_CALL_SIGNAL_READ) as $row) {
        $signals[] = array(
            'id'             => (int) $row['id'],
            'from_member_id' => (int) $row['from_member_id'],
            'kind'           => (string) $row['kind'],
            'body'           => (string) $row['body'],
        );
    }

    return $signals;
}

/**
 * The clean-up: silent calls end, old signals go, and the stamp is taken
 * away when nothing is live. Runs from the live reads while a call is live,
 * at most every WS_CALL_SWEEP_EVERY seconds.
 *
 * @param bool $force run now, whatever the last run
 */
function ws_calls_sweep($force = false)
{
    if (!ws_calls_ready()) {
        return;
    }

    $now = time();
    $path = ws_calls_stamp_path();

    if (!$force && ws_calls_stamp_usable() && is_file($path) && ((int) @filemtime($path) > ($now - WS_CALL_SWEEP_EVERY))) {
        return;
    }

    if (ws_calls_stamp_usable() && is_file($path)) {
        @touch($path, $now);
        clearstatcache(true, $path);
    }

    db("DELETE FROM ws_call_signals WHERE created_at < '" . ($now - WS_CALL_SIGNAL_LIFE) . "'");

    // Members unheard too long are out (a tab closed without its leave);
    // the last one out ends the call.
    $emptied = array();

    foreach ((array) db_items("SELECT m.id, m.call_id, m.joined_at,
            (SELECT MAX(s.created_at) FROM ws_call_signals s WHERE s.from_member_id = m.id) AS last_signal_at
        FROM ws_call_members m JOIN ws_calls c ON c.id = m.call_id
        WHERE c.state = 'live' AND m.left_at = 0") as $member) {
        if (ws_call_member_is_gone($member, (int) $member['last_signal_at'], $now)) {
            ws_call_member_out((int) $member['id'], $now);
            $emptied[(int) $member['call_id']] = true;
        }
    }

    foreach (array_keys($emptied) as $call_id) {
        $call = ws_call($call_id);

        if ($call && ((string) $call['state'] === 'live') && !ws_call_members_live($call_id)) {
            ws_call_end_row($call);
        }
    }

    foreach ((array) db_items("SELECT c.*,
            (SELECT MAX(s.created_at) FROM ws_call_signals s WHERE s.call_id = c.id) AS last_signal_at,
            (SELECT MAX(m.joined_at) FROM ws_call_members m WHERE m.call_id = c.id) AS last_join_at
        FROM ws_calls c WHERE c.state = 'live'") as $call) {
        if (ws_call_is_silent($call, (int) $call['last_signal_at'], (int) $call['last_join_at'], $now)) {
            ws_call_end_row($call);
        }
    }

    ws_calls_stamp_clear_if_idle();
}

/**
 * The call part of a live read: the channel's live call and, for a party in
 * it, the signals sent to them. null when no call is live on the site, so
 * an idle channel's read asks the database nothing more.
 *
 * @param array $party
 * @param int   $channel_id
 * @param int   $signal_after the newest signal id the reader holds
 * @return array|null call (or null), member_id, signals
 */
function ws_call_live_part($party, $channel_id, $signal_after)
{
    if (!ws_calls_live_somewhere() || !ws_calls_ready()) {
        return null;
    }

    // Without a stamp to keep the time of the last clean-up in, it runs
    // only where a call is live, so an idle channel's read stays one row.
    $call = ws_call_live($channel_id);

    if ($call || ws_calls_stamp_usable()) {
        ws_calls_sweep();
        $call = $call ? ws_call_live($channel_id) : null;
    }

    if (!$call) {
        return array('call' => null, 'member_id' => 0, 'signals' => array());
    }

    $members = ws_call_members_live($call['id']);
    $mine = ws_call_member_of($party, $call['id']);

    return array(
        'call'      => ws_call_present($call, $members, ws_call_member_names($members), ws_call_sharers((int) $call['id'], $members),
            ws_call_hands((int) $call['id'], $members)),
        'member_id' => $mine ? (int) $mine['id'] : 0,
        'signals'   => $mine ? ws_call_signals_for((int) $mine['id'], (int) $signal_after) : array(),
    );
}

// ── Ringing (the channel screen) ──

/**
 * The addresses the browsers ask for their public route (STUN): the Calls
 * card's, else WS_CALL_DEFAULT_STUN. A third party the browsers reach, never
 * the site; nothing of a call's media passes it. This list goes into every
 * Workspace screen's config, so it never holds the relay (TURN) or any
 * credential: those come only with a granted start or join
 * (ws_call_member_ice()).
 *
 * @return array[] RTCIceServer entries
 */
function ws_call_ice_servers()
{
    return ws_call_ice_servers_from(ws_calls_settings(), '', time());
}

/**
 * Does a live call ring this person? Only in a conversation of two people,
 * while the one who started it waits alone in it, for WS_CALL_RING seconds.
 * Pure.
 *
 * @param array $call         the ws_calls row
 * @param int   $viewer_id
 * @param int[] $live_users   user ids of the call's live members
 * @param int   $member_count people with a membership row in the channel
 * @param int   $now
 * @return bool
 */
function ws_call_rings($call, $viewer_id, $live_users, $member_count, $now)
{
    if (!is_array($call) || ((string) $call['state'] !== 'live') || ((int) $member_count !== 2)) {
        return false;
    }

    if (((int) $call['started_by'] === (int) $viewer_id) || ((int) $call['started_at'] <= ((int) $now - WS_CALL_RING))) {
        return false;
    }

    $live_users = array_map('intval', (array) $live_users);

    return ($live_users === array((int) $call['started_by']));
}

/**
 * May this person decline a call? While its starter waits alone in a
 * conversation of two, whatever the time: declining then ends it, as nobody
 * else was asked. Pure.
 *
 * @param array $party
 * @param array $call
 * @param int[] $live_users
 * @param int   $member_count
 * @return bool
 */
function ws_call_may_decline($party, $call, $live_users, $member_count)
{
    if (((string) ($party['kind'] ?? '') !== 'user') || empty($party['member'])) {
        return false;
    }

    // Read at the moment it started, so the ring's time does not count.
    return ws_call_rings($call, (int) $party['id'], $live_users, $member_count, (int) ($call['started_at'] ?? 0));
}

/**
 * The user ids of a call's live members (guests left out).
 *
 * @param array[] $members ws_call_members rows
 * @return int[]
 */
function ws_call_live_users($members)
{
    $ids = array();

    foreach ((array) $members as $member) {
        if ((int) $member['guest_id'] === 0) {
            $ids[] = (int) $member['user_id'];
        }
    }

    return $ids;
}

/**
 * People with a membership row in a channel.
 *
 * @param int $channel_id
 * @return int
 */
function ws_call_channel_people($channel_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_channel_members WHERE channel_id = '" . (int) $channel_id . "'");
}

/**
 * The calls ringing a person now, in any channel of theirs: the part of the
 * channel screen's look (ws_sync) that rings them where a mention would.
 * null while no call is live on the site, so an idle look asks nothing.
 *
 * @param array $viewer
 * @return array[]|null call_id, channel_id, kind, caller_id, caller
 */
function ws_call_rings_for($viewer)
{
    if (!ws_calls_live_somewhere() || !ws_calls_ready()) {
        return null;
    }

    $now = time();
    $rings = array();

    // A guest room's call rings nobody: its guest joins from the guest
    // page, and the team from the room's head, as in a group.
    foreach ((array) db_items("SELECT c.* FROM ws_calls c
        INNER JOIN ws_channel_members me ON me.channel_id = c.channel_id AND me.user_id = '" . (int) $viewer['id'] . "'
        INNER JOIN ws_channels ch ON ch.id = c.channel_id AND ch.kind <> 'guest'
        WHERE c.state = 'live' AND c.started_by <> '" . (int) $viewer['id'] . "' AND c.started_at > '" . ($now - WS_CALL_RING) . "'
        ORDER BY c.id LIMIT 5") as $call) {
        $live_users = ws_call_live_users(ws_call_members_live($call['id']));

        if (ws_call_rings($call, (int) $viewer['id'], $live_users, ws_call_channel_people($call['channel_id']), $now)) {
            $rings[] = array(
                'call_id'    => (int) $call['id'],
                'channel_id' => (int) $call['channel_id'],
                'kind'       => (string) $call['kind'],
                'caller_id'  => (int) $call['started_by'],
                'caller'     => (string) ws_person_name($call['started_by']),
            );
        }
    }

    return $rings;
}

/**
 * Declines a call that rings the person: it ends, and the caller's screen
 * reads that nobody answered.
 *
 * @param array $party
 * @param array $call
 * @return array ok, error
 */
function ws_call_decline($party, $call)
{
    if (!is_array($call) || ((string) $call['state'] !== 'live')) {
        return array('ok' => true, 'error' => '');
    }

    $live_users = ws_call_live_users(ws_call_members_live($call['id']));

    // A guest room's call is never rung, so never declined: its guest may
    // be in it while the caller is the room's only person.
    if (ws_channel_is_guest(ws_channel((int) $call['channel_id'])) || !ws_call_may_decline($party, $call, $live_users, ws_call_channel_people($call['channel_id']))) {
        return array('ok' => false, 'error' => lang('This call is not open to you.'));
    }

    ws_call_end_row($call, (int) $party['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The texts of the call buttons, the Join button, the ringing card and the
 * call window
 * (assets/js/workspace_calls.js).
 *
 * @return array key => text
 */
function ws_call_js_strings()
{
    return array(
        'call_voice'      => lang('Voice call'),
        'call_video'      => lang('Video call'),
        'call_ringing'    => ws_js_template('{var:1} is calling you', 1),
        'call_ringing_video' => ws_js_template('{var:1} is calling you with video', 1),
        'call_accept'     => lang('Accept'),
        'call_decline'    => lang('Decline'),
        'call_calling'    => lang('Calling…'),
        'call_connecting' => lang('Connecting…'),
        'call_mute'       => lang('Mute'),
        'call_unmute'     => lang('Unmute'),
        'call_camera_off' => lang('Turn camera off'),
        'call_camera_on'  => lang('Turn camera on'),
        'call_devices'    => lang('Microphone and camera'),
        'call_microphone' => lang('Microphone'),
        'call_camera'     => lang('Camera'),
        'call_speaker'    => lang('Speaker or headphones'),
        'call_hang_up'    => lang('Hang up'),
        'call_open'       => lang('Open the conversation'),
        'call_ended'      => lang('The call ended.'),
        'call_unanswered' => lang('The call was not answered.'),
        'call_lost'       => lang('The connection was lost.'),
        'call_busy'       => lang('You are already in a call.'),
        'call_denied'     => lang('Microphone or camera permission was denied.'),
        'call_unsupported' => lang('Calls are not supported in this browser.'),
        'call_join'       => lang('Join the call'),
        'call_join_count' => ws_js_template('Join the call ({var:1}/{var:2})', 2),
        'call_full'       => lang('This call is full.'),
        'call_waiting'    => lang('Waiting for others…'),
        'call_you'        => lang('You'),
        'call_share'      => lang('Share screen'),
        'call_share_stop' => lang('Stop sharing'),
        'call_sharing'    => ws_js_template('{var:1} is sharing', 1),
        'call_share_show' => ws_js_template('Show the screen of {var:1}', 1),
        'call_self_sharing' => lang('You are sharing your screen'),
        'call_smaller'    => lang('Make smaller'),
        'call_larger'     => lang('Make larger'),
        'call_full_screen' => lang('Full screen'),
        'call_full_exit'  => lang('Exit full screen'),
        'call_join_here'  => lang('Join here'),
        'call_elsewhere'  => lang('You are already in this call in another window.'),
        'call_moved'      => lang('The call goes on in another window.'),
        'call_new_tab'    => lang('Opened in a new tab, so the call goes on.'),
        'call_hand_raise' => lang('Raise hand'),
        'call_hand_lower' => lang('Lower hand'),
        'call_hand_up'    => lang('Hand raised'),
        'call_weak'       => lang('Weak connection'),
        'call_muted_talk' => lang('You are muted'),
        'call_pin'        => lang('Show large'),
        'call_unpin'      => lang('Unpin'),
        'call_more'       => lang('More'),
        'call_self_hide'  => lang('Hide my picture'),
        'call_self_show'  => lang('Show my picture'),
    );
}

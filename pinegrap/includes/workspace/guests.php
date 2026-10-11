<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - talking with a guest: somebody with no account on the site.
 *
 * Staff (roles 0-2) start a conversation with a guest and the guest gets a
 * room of their own: a channel of kind "guest" for that conversation alone,
 * so nothing said in the team's own channels can reach them, and everything
 * written in the room is written knowing the guest reads it. The room stays;
 * what is temporary is the guest's way in.
 *
 * The way in is a short link (short_links, the site's own mechanism): a
 * random token as the address, kept only as its SHA-256 (token_hash), either
 * for one opening (the first browser that opens it holds the room and the
 * link is spent) or until a time chosen when it is made. Nobody but staff
 * makes one, and only here, from the workspace. The router sends the token
 * on to workspace_guest.php behind a "#", so it is in no server log, and the
 * page claims it with a POST: a link preview that fetches the address does
 * not spend a one-time link.
 *
 * The claim opens a guest session (ws_guest_sessions): a cookie of its own,
 * kept only as a hash, that opens nothing of the panel. Every request of the
 * guest reads session, guest, link and room again; ending the conversation,
 * a link running out and the room being archived all close it.
 *
 * A channel of the team can be shared the same way (ws_channel_share()):
 * the guest is a ws_guests row of that channel, comes in by the same kind of
 * link and reads the channel's conversation through the same page, either
 * to read only or to read and write (ws_guests.access). Nothing else of the
 * team opens to them: not the channel's tabs, its discussions, its files
 * beyond the ones posted in the conversation, nor any other channel. The
 * channel stays the team's; ending a share closes that guest's way in and
 * leaves the channel as it is. While a share is open the assistants are not
 * asked in the channel, as in a guest's room.
 *
 * The guest reads the room as staff do: the messages drawn by the same
 * engine (tables, sums, code, titled blocks, checklists), the files and
 * pictures, polls and task cards staff put in it. They write text with bold,
 * italics and strike-through, answer a message, leave an emoji, vote in a
 * poll and tick a checklist. They add nothing else: no files, no tags, no
 * commands, no tables, polls or tasks. Tags in what staff write are read to
 * the guest as a name (a person) or a kind (a record), never as a link into
 * the panel. The assistants are never asked in a guest room.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The cookie the guest session is carried in. */
define('WS_GUEST_COOKIE', 'pg_ws_guest');

/** How long a session from a one-time link lasts without being used, in seconds. */
define('WS_GUEST_IDLE', 30 * 86400);

/** The longest a timed link can be made for, in seconds. */
define('WS_GUEST_LONGEST', 90 * 86400);

/** The most a guest message can hold. */
define('WS_GUEST_MESSAGE_MAX', 2000);

/** The emoji a guest may leave. */
define('WS_GUEST_EMOJI', '👍,❤️,😂,😮,😢,🙏,✅,👏');

/**
 * Does the database carry guests (2026.4.5, 5.112)?
 *
 * @return bool
 */
function ws_guests_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_guests', 'ws_guest_sessions')") === 2)
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'short_links' AND COLUMN_NAME = 'token_hash'") > 0)
            && (strpos((string) db_value("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_channels' AND COLUMN_NAME = 'kind'"), "'guest'") !== false);
    }

    return $ready;
}

/**
 * Can a channel of the team be shared with a guest (2026.4.8, 8.82)?
 *
 * @return bool
 */
function ws_shares_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_guests_ready() && function_exists('waf_table_has_column') && waf_table_has_column('ws_guests', 'access');
    }

    return $ready;
}

/**
 * Does this guest only read? A guest of a room writes; a guest a channel
 * was shared with may have been given reading only.
 *
 * @param array $guest
 * @return bool
 */
function ws_guest_reads_only($guest)
{
    return is_array($guest) && ((string) ($guest['access'] ?? 'write') === 'read');
}

/**
 * May this person talk with guests: start a conversation, give a new link,
 * end it? Staff only.
 *
 * @param array $viewer
 * @return bool
 */
function ws_can_host_guests($viewer)
{
    return ws_guests_ready() && !empty($viewer['member']) && ((int) $viewer['role'] <= 2);
}

/**
 * Is this a guest room?
 *
 * @param array|null $channel
 * @return bool
 */
function ws_channel_is_guest($channel)
{
    return is_array($channel) && ((string) ($channel['kind'] ?? '') === 'guest');
}

/**
 * One guest.
 *
 * @param int $guest_id
 * @return array|null
 */
function ws_guest($guest_id)
{
    if (!ws_guests_ready() || ((int) $guest_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_guests WHERE id = '" . (int) $guest_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The guest of a room.
 *
 * @param int $channel_id
 * @return array|null
 */
function ws_guest_for_channel($channel_id)
{
    if (!ws_guests_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_guests WHERE channel_id = '" . (int) $channel_id . "' ORDER BY id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * A guest as a message sender: named, marked as a guest, with letters of
 * their own.
 *
 * @param int $guest_id
 * @return array
 */
function ws_guest_sender($guest_id)
{
    static $cache = array();

    $guest_id = (int) $guest_id;

    if (!isset($cache[$guest_id])) {
        $guest = ws_guest($guest_id);
        $name = $guest ? (string) $guest['name'] : lang('Guest');

        $cache[$guest_id] = array(
            'id'       => 0,
            'guest_id' => $guest_id,
            'name'     => $name,
            'username' => '',
            'role'     => 3,
            'title'    => lang('Guest'),
            'avatar'   => function_exists('pg_initials_avatar_url') ? pg_initials_avatar_url(pg_initials($name, ''), 'contact', 'guest' . $guest_id) : '',
            'avatar_kind' => 'contact',
            'presence' => 'offline',
            'guest'    => true,
        );
    }

    return $cache[$guest_id];
}

/**
 * The address a token opens.
 *
 * @param string $token
 * @return string
 */
function ws_guest_link_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . $token;
}

/**
 * Gives the guest a new way in. The links given before that are still open
 * are closed: one guest, one link.
 *
 * @param array  $viewer
 * @param array  $guest
 * @param string $mode     once | timed
 * @param int    $duration seconds, for a timed link
 * @return array ok, error, url, link_id
 */
function ws_guest_link_issue($viewer, $guest, $mode, $duration = 0)
{
    $fail = function ($message) {
        return array('ok' => false, 'error' => $message, 'url' => '', 'link_id' => 0);
    };

    if (!ws_can_host_guests($viewer)) {
        return $fail(lang('Only staff can talk with guests.'));
    }

    $mode = ($mode === 'timed') ? 'timed' : 'once';
    $duration = (int) $duration;

    if (($mode === 'timed') && (!isset(pg_short_link_durations()[$duration]) || ($duration > WS_GUEST_LONGEST))) {
        return $fail(lang('Choose how long the link is valid.'));
    }

    $now = time();
    $token = pg_short_link_new_token();
    $expires = ($mode === 'timed') ? $now + $duration : 0;

    // Links given before are closed, and so are the sessions they opened: a
    // new link is also what is given for a lost phone.
    db("UPDATE short_links SET expires_at = '" . $now . "'
        WHERE destination_type = 'workspace_guest' AND ws_guest_id = '" . (int) $guest['id'] . "'
        AND (expires_at = 0 OR expires_at > '" . $now . "')");
    db("UPDATE ws_guest_sessions SET ended_at = '" . $now . "' WHERE guest_id = '" . (int) $guest['id'] . "' AND ended_at = 0");

    db("INSERT INTO short_links (name, destination_type, link_mode, token_hash, token_hint, expires_at, ws_guest_id,
            created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES ('', 'workspace_guest', '" . $mode . "', '" . hash('sha256', $token) . "', '" . e(substr($token, 0, 6)) . "',
            '" . $expires . "', '" . (int) $guest['id'] . "',
            '" . (int) $viewer['id'] . "', '" . $now . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

    $link_id = (int) mysqli_insert_id(db::$con);

    if ($link_id <= 0) {
        return $fail(lang('The link could not be made.'));
    }

    // In a channel of the team everybody is told who reads along from now,
    // and whether they may write.
    $room = ws_channel($guest['channel_id']);

    if ($room && !ws_channel_is_guest($room)) {
        $how = ws_guest_reads_only($guest) ? lang('to read only') : lang('to read and write');

        ws_message_system($guest['channel_id'], ($mode === 'timed')
            ? lang(array('string' => '{var:1} shared this channel with {var:2} ({var:3}), with a link valid until {var:4}', 'vars' => array('<@user:' . (int) $viewer['id'] . '>', (string) $guest['name'], $how, date('d.m.Y H:i', $expires))))
            : lang(array('string' => '{var:1} shared this channel with {var:2} ({var:3}), with a one-time link', 'vars' => array('<@user:' . (int) $viewer['id'] . '>', (string) $guest['name'], $how))));
    } else {
        ws_message_system($guest['channel_id'], ($mode === 'timed')
            ? lang(array('string' => '{var:1} gave {var:2} a new link, valid until {var:3}', 'vars' => array('<@user:' . (int) $viewer['id'] . '>', (string) $guest['name'], date('d.m.Y H:i', $expires))))
            : lang(array('string' => '{var:1} gave {var:2} a new one-time link', 'vars' => array('<@user:' . (int) $viewer['id'] . '>', (string) $guest['name']))));
    }

    log_activity(lang(array('string' => 'a workspace guest link was made for ({var:1})', 'vars' => (string) $guest['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'url' => ws_guest_link_url($token), 'link_id' => $link_id);
}

/**
 * Starts a conversation with a guest: a room of their own, with the person
 * who starts it (and the staff they choose) in it, and the first link.
 *
 * @param array $viewer
 * @param array $data guest_name, topic, mode (once | timed), duration, members (ids)
 * @return array ok, error, field, channel_id, url
 */
function ws_guest_start($viewer, $data)
{
    $fail = function ($message, $field = '') {
        return array('ok' => false, 'error' => $message, 'field' => $field, 'channel_id' => 0, 'url' => '');
    };

    if (!ws_can_host_guests($viewer)) {
        return $fail(lang('Only staff can talk with guests.'));
    }

    $name = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($data['guest_name'] ?? '')), 0, 60));
    $topic = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($data['topic'] ?? '')), 0, 120));

    if ($name === '') {
        return $fail(lang('Write the name of the guest.'), 'guest_name');
    }

    // A name of its own among the open channels.
    $base = ws_channel_clean_name(lang('Guest') . ' · ' . $name . (($topic !== '') ? ' · ' . $topic : ''));
    $room = $base;

    for ($i = 2; ws_channel_name_taken($room) && ($i < 100); $i++) {
        $room = ws_channel_clean_name(mb_substr($base, 0, 74) . ' (' . $i . ')');
    }

    $created = ws_channel_create($viewer, array('name' => $room, 'kind' => 'private', 'topic' => $topic));

    if (!$created['ok']) {
        return $fail($created['error'], ($created['field'] === 'name') ? 'topic' : $created['field']);
    }

    $channel_id = (int) $created['channel_id'];

    db("UPDATE ws_channels SET kind = 'guest', claude_access = 2" . (function_exists('ws_ai_schema_ready') && ws_ai_schema_ready() ? ', ai_access = 2' : '') . "
        WHERE id = '" . $channel_id . "'");

    // Staff only, in a guest room.
    $members = array();

    foreach ((array) ($data['members'] ?? array()) as $user_id) {
        $rights = ws_rights_for_id((int) $user_id);

        if (!empty($rights['member']) && ((int) $rights['role'] <= 2)) {
            $members[] = (int) $user_id;
        }
    }

    if (!empty($members)) {
        ws_channel_add_members($viewer, ws_channel($channel_id), $members, false);
    }

    db("INSERT INTO ws_guests (channel_id, name, created_by, created_at)
        VALUES ('" . $channel_id . "', '" . e($name) . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    $guest = ws_guest((int) mysqli_insert_id(db::$con));

    if (!$guest) {
        return $fail(lang('The conversation could not be started.'));
    }

    $link = ws_guest_link_issue($viewer, $guest, (string) ($data['mode'] ?? 'once'), (int) ($data['duration'] ?? 0));

    if (!$link['ok']) {
        return $fail($link['error'], 'duration');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'channel_id' => $channel_id, 'url' => $link['url']);
}

/**
 * Gives the guest of a room a new link; a room that was ended is opened
 * again for it.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $mode
 * @param int    $duration
 * @return array ok, error, url
 */
function ws_guest_relink($viewer, $channel, $mode, $duration)
{
    $guest = ws_channel_is_guest($channel) ? ws_guest_for_channel($channel['id']) : null;

    if (!$guest || !ws_can_host_guests($viewer) || !ws_channel_membership($channel['id'], $viewer['id'])) {
        return array('ok' => false, 'error' => lang('Only staff in this room can give its guest a link.'), 'url' => '');
    }

    if ((int) $guest['ended_at'] > 0) {
        db("UPDATE ws_guests SET ended_at = 0, ended_by = 0 WHERE id = '" . (int) $guest['id'] . "'");
    }

    if ((int) $channel['archived_at'] > 0) {
        db("UPDATE ws_channels SET archived_at = 0 WHERE id = '" . (int) $channel['id'] . "'");
    }

    $link = ws_guest_link_issue($viewer, ws_guest($guest['id']), $mode, $duration);

    return array('ok' => $link['ok'], 'error' => $link['error'], 'url' => $link['url']);
}

/**
 * Ends the conversation: the guest's links and sessions are closed and the
 * room is archived, kept as the record of what was said. A new link opens
 * it again.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ok, error
 */
function ws_guest_end($viewer, $channel)
{
    $guest = ws_channel_is_guest($channel) ? ws_guest_for_channel($channel['id']) : null;

    if (!$guest || !ws_can_host_guests($viewer) || !ws_channel_membership($channel['id'], $viewer['id'])) {
        return array('ok' => false, 'error' => lang('Only staff in this room can end the conversation.'));
    }

    $now = time();

    db("UPDATE ws_guests SET ended_at = '" . $now . "', ended_by = '" . (int) $viewer['id'] . "' WHERE id = '" . (int) $guest['id'] . "'");
    db("UPDATE ws_guest_sessions SET ended_at = '" . $now . "' WHERE guest_id = '" . (int) $guest['id'] . "' AND ended_at = 0");
    db("UPDATE short_links SET expires_at = '" . $now . "'
        WHERE destination_type = 'workspace_guest' AND ws_guest_id = '" . (int) $guest['id'] . "'
        AND (expires_at = 0 OR expires_at > '" . $now . "')");

    ws_message_system($channel['id'], lang(array('string' => '{var:1} ended the conversation with {var:2}', 'vars' => array('<@user:' . (int) $viewer['id'] . '>', (string) $guest['name']))));

    db("UPDATE ws_channels SET archived_at = '" . $now . "' WHERE id = '" . (int) $channel['id'] . "'");

    log_activity(lang(array('string' => 'the workspace conversation with a guest ({var:1}) was ended', 'vars' => (string) $guest['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '');
}

/**
 * What the channel screen shows about a guest room: who the guest is, where
 * their link stands, when they were last there, and what the reader may do.
 *
 * @param array $viewer
 * @param array $channel
 * @return array|null
 */
function ws_guest_channel_state($viewer, $channel)
{
    if (!ws_channel_is_guest($channel) || !ws_guests_ready()) {
        return null;
    }

    $guest = ws_guest_for_channel($channel['id']);

    if (!$guest) {
        return null;
    }

    $now = time();
    $link = db_item("SELECT link_mode, expires_at, used_at, created_timestamp FROM short_links
        WHERE destination_type = 'workspace_guest' AND ws_guest_id = '" . (int) $guest['id'] . "'
        ORDER BY id DESC LIMIT 1");

    $status = 'none';
    $label = lang('No link has been given.');

    if (is_array($link)) {
        $expires = (int) $link['expires_at'];

        if (($expires > 0) && ($expires <= $now)) {
            $status = 'closed';
            $label = lang('The link is closed.');
        } elseif ((string) $link['link_mode'] === 'once') {
            $status = ((int) $link['used_at'] > 0) ? 'used' : 'open';
            $label = ((int) $link['used_at'] > 0)
                ? lang(array('string' => 'The one-time link was opened on {var:1}; the guest goes on in that browser.', 'vars' => date('d.m.Y H:i', (int) $link['used_at'])))
                : lang('A one-time link is waiting to be opened.');
        } else {
            $status = 'open';
            $label = lang(array('string' => 'The link is valid until {var:1}.', 'vars' => date('d.m.Y H:i', $expires)));
        }
    }

    $seen = (int) $guest['last_seen_at'];
    $host = ws_can_host_guests($viewer) && (bool) ws_channel_membership($channel['id'], $viewer['id']);

    return array(
        'name'       => (string) $guest['name'],
        'ended'      => ((int) $guest['ended_at'] > 0),
        'status'     => $status,
        'label'      => $label,
        'seen'       => ($seen > 0) ? ws_time_label($seen) : '',
        // The page asks every few seconds and the time is written once a
        // minute at most, so a minute and a half means here.
        'online'     => ($seen > ($now - 90)),
        'can_host'   => $host,
        'durations'  => $host ? ws_guest_duration_options() : array(),
    );
}

/* ---------------------------------------------------------------------------
   A channel of the team shared with a guest
   --------------------------------------------------------------------------- */

/**
 * May this person share the channel with somebody outside the team? Staff
 * who may manage it (a private one only from inside), while it is open.
 * Neither a guest's room nor a discussion is shared.
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_channel_can_share($viewer, $channel)
{
    return ws_shares_ready() && ws_can_host_guests($viewer) && is_array($channel)
        && in_array((string) $channel['kind'], array('public', 'private'), true)
        && ((int) $channel['archived_at'] === 0)
        && ws_can_manage_channel($viewer, $channel);
}

/**
 * The guests a channel of the team is shared with, newest first.
 *
 * @param int  $channel_id
 * @param bool $open_only leave the ended shares out
 * @return array[]
 */
function ws_channel_share_guests($channel_id, $open_only = false)
{
    if (!ws_shares_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_guests
        WHERE channel_id = '" . (int) $channel_id . "'" . ($open_only ? " AND ended_at = 0" : '') . "
        ORDER BY ended_at = 0 DESC, id DESC LIMIT 50");
}

/**
 * Is a channel of the team open to a guest now: a share not ended whose link
 * still opens (a one-time link that was opened keeps the guest in)? The
 * assistants are not asked in it meanwhile, as in a guest's room.
 *
 * @param array $channel
 * @return bool
 */
function ws_channel_share_open($channel)
{
    static $cache = array();

    if (!ws_shares_ready() || !is_array($channel) || ws_channel_is_guest($channel)) {
        return false;
    }

    $channel_id = (int) $channel['id'];

    if (!isset($cache[$channel_id])) {
        $cache[$channel_id] = (int) db_value("SELECT COUNT(*) FROM ws_guests g
            INNER JOIN short_links l ON l.ws_guest_id = g.id AND l.destination_type = 'workspace_guest'
            WHERE g.channel_id = '" . $channel_id . "' AND g.ended_at = 0
            AND (l.expires_at = 0 OR l.expires_at > '" . time() . "')") > 0;
    }

    return $cache[$channel_id];
}

/**
 * Shares a channel of the team with somebody who has no account: a guest of
 * the channel, to read only or to read and write, and their first link.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data guest_name, access (read | write), mode (once | timed), duration
 * @return array ok, error, field, url
 */
function ws_channel_share($viewer, $channel, $data)
{
    $fail = function ($message, $field = '') {
        return array('ok' => false, 'error' => $message, 'field' => $field, 'url' => '');
    };

    if (!ws_channel_can_share($viewer, $channel)) {
        return $fail(lang('Only staff who may manage this channel can share it.'));
    }

    $name = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($data['guest_name'] ?? '')), 0, 60));

    if ($name === '') {
        return $fail(lang('Write the name of the guest.'), 'guest_name');
    }

    $access = ((string) ($data['access'] ?? 'read') === 'write') ? 'write' : 'read';

    db("INSERT INTO ws_guests (channel_id, name, access, created_by, created_at)
        VALUES ('" . (int) $channel['id'] . "', '" . e($name) . "', '" . $access . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    $guest = ws_guest((int) mysqli_insert_id(db::$con));

    if (!$guest) {
        return $fail(lang('The channel could not be shared.'));
    }

    $link = ws_guest_link_issue($viewer, $guest, (string) ($data['mode'] ?? 'once'), (int) ($data['duration'] ?? 0));

    if (!$link['ok']) {
        db("DELETE FROM ws_guests WHERE id = '" . (int) $guest['id'] . "'");

        return $fail($link['error'], 'duration');
    }

    log_activity(lang(array('string' => 'workspace channel ({var:1}) was shared with a guest ({var:2})', 'vars' => array((string) $channel['name'], $name))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'field' => '', 'url' => $link['url']);
}

/**
 * One guest of a shared channel, for those who may manage the share.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $guest_id
 * @return array|null
 */
function ws_channel_share_guest($viewer, $channel, $guest_id)
{
    $guest = ws_guest((int) $guest_id);

    if (!$guest || ((int) $guest['channel_id'] !== (int) $channel['id']) || ws_channel_is_guest($channel)
        || !ws_shares_ready() || !ws_can_host_guests($viewer) || !ws_can_manage_channel($viewer, $channel)) {
        return null;
    }

    return $guest;
}

/**
 * Gives a guest of a shared channel a new link (an ended share opens again
 * with it), or ends the share: the guest's links and sessions are closed,
 * the channel stays as it is.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param int    $guest_id
 * @param string $what     relink | end
 * @param string $mode     once | timed (relink)
 * @param int    $duration seconds (relink)
 * @return array ok, error, url
 */
function ws_channel_share_change($viewer, $channel, $guest_id, $what, $mode = 'once', $duration = 0)
{
    $guest = ws_channel_share_guest($viewer, $channel, $guest_id);

    if (!$guest) {
        return array('ok' => false, 'error' => lang('Only staff who may manage this channel can share it.'), 'url' => '');
    }

    if ($what === 'relink') {
        if ((int) $channel['archived_at'] > 0) {
            return array('ok' => false, 'error' => lang('This channel is archived. It can be read but not written in.'), 'url' => '');
        }

        if ((int) $guest['ended_at'] > 0) {
            db("UPDATE ws_guests SET ended_at = 0, ended_by = 0 WHERE id = '" . (int) $guest['id'] . "'");
        }

        $link = ws_guest_link_issue($viewer, ws_guest($guest['id']), $mode, $duration);

        return array('ok' => $link['ok'], 'error' => $link['error'], 'url' => $link['url']);
    }

    $now = time();

    db("UPDATE ws_guests SET ended_at = '" . $now . "', ended_by = '" . (int) $viewer['id'] . "' WHERE id = '" . (int) $guest['id'] . "'");
    db("UPDATE ws_guest_sessions SET ended_at = '" . $now . "' WHERE guest_id = '" . (int) $guest['id'] . "' AND ended_at = 0");
    db("UPDATE short_links SET expires_at = '" . $now . "'
        WHERE destination_type = 'workspace_guest' AND ws_guest_id = '" . (int) $guest['id'] . "'
        AND (expires_at = 0 OR expires_at > '" . $now . "')");

    ws_message_system($channel['id'], lang(array('string' => '{var:1} stopped sharing this channel with {var:2}', 'vars' => array('<@user:' . (int) $viewer['id'] . '>', (string) $guest['name']))));

    log_activity(lang(array('string' => 'workspace channel ({var:1}) is no longer shared with a guest ({var:2})', 'vars' => array((string) $channel['name'], (string) $guest['name']))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'url' => '');
}

/**
 * What the channel screen shows about the guests a channel is shared with:
 * who, to read or to write, where their link stands, whether they are here,
 * and what the reader may do. Null when nobody outside the team reads it.
 *
 * @param array $viewer
 * @param array $channel
 * @return array|null
 */
function ws_channel_shares_state($viewer, $channel)
{
    if (!ws_shares_ready() || ws_channel_is_guest($channel)) {
        return null;
    }

    $host = ws_channel_can_share($viewer, $channel) || (ws_can_host_guests($viewer) && ws_can_manage_channel($viewer, $channel));
    $now = time();
    $guests = array();

    foreach (ws_channel_share_guests($channel['id'], !$host) as $guest) {
        $link = db_item("SELECT link_mode, expires_at, used_at FROM short_links
            WHERE destination_type = 'workspace_guest' AND ws_guest_id = '" . (int) $guest['id'] . "'
            ORDER BY id DESC LIMIT 1");
        $expires = is_array($link) ? (int) $link['expires_at'] : 0;
        $closed = ((int) $guest['ended_at'] > 0) || !is_array($link) || (($expires > 0) && ($expires <= $now));

        if ($closed && !$host) {
            continue;
        }

        if ((int) $guest['ended_at'] > 0) {
            $label = lang('The share has ended.');
        } elseif ($closed) {
            $label = lang('The link is closed.');
        } elseif ((string) $link['link_mode'] === 'once') {
            $label = ((int) $link['used_at'] > 0)
                ? lang(array('string' => 'The one-time link was opened on {var:1}; the guest goes on in that browser.', 'vars' => date('d.m.Y H:i', (int) $link['used_at'])))
                : lang('A one-time link is waiting to be opened.');
        } else {
            $label = lang(array('string' => 'The link is valid until {var:1}.', 'vars' => date('d.m.Y H:i', $expires)));
        }

        $seen = (int) $guest['last_seen_at'];

        $guests[] = array(
            'id'     => (int) $guest['id'],
            'name'   => (string) $guest['name'],
            'access' => ws_guest_reads_only($guest) ? 'read' : 'write',
            'open'   => !$closed,
            'label'  => $label,
            'seen'   => ($seen > 0) ? ws_time_label($seen) : '',
            'online' => !$closed && ($seen > ($now - 90)),
        );
    }

    $open = array_filter($guests, function ($guest) { return $guest['open']; });

    if (empty($open) && (!$host || empty($guests))) {
        return null;
    }

    return array(
        'guests'    => $guests,
        'open'      => count($open),
        'can_host'  => $host,
        'durations' => $host ? ws_guest_duration_options() : array(),
    );
}

/* ---------------------------------------------------------------------------
   The guest's side (workspace_guest.php)
   --------------------------------------------------------------------------- */

/**
 * The link a token opens, if it is a guest link that is still open.
 *
 * @param string $token
 * @return array|null the short link row
 */
function ws_guest_link_for_token($token)
{
    if (!ws_guests_ready() || !preg_match('/^[A-Za-z0-9_-]{43}$/', (string) $token)) {
        return null;
    }

    $link = db_item("SELECT * FROM short_links WHERE token_hash = '" . hash('sha256', (string) $token) . "' AND destination_type = 'workspace_guest' LIMIT 1");

    return is_array($link) ? $link : null;
}

/**
 * Why a link does not let the guest in, '' when it does.
 *
 * @param array|null $link
 * @param array|null $guest
 * @param array|null $channel
 * @return string
 */
function ws_guest_refusal($link, $guest, $channel)
{
    // A guest's own room, or a channel of the team shared with them.
    if (!$link || !$guest || !$channel || !in_array((string) $channel['kind'], array('guest', 'public', 'private'), true)
        || (!ws_channel_is_guest($channel) && !ws_shares_ready())) {
        return lang('This link is not valid.');
    }

    if (((int) $guest['ended_at'] > 0) || ((int) $channel['archived_at'] > 0)) {
        return lang('This conversation has ended.');
    }

    if (((int) $link['expires_at'] > 0) && ((int) $link['expires_at'] <= time())) {
        return lang('This link is no longer valid.');
    }

    return '';
}

/**
 * The token a guest arrives with, taken: a one-time link is spent here, by
 * the first to claim it; a session is opened and its cookie set.
 *
 * @param string $token
 * @return array ok, error, csrf
 */
function ws_guest_claim($token)
{
    $link = ws_guest_link_for_token($token);
    $guest = $link ? ws_guest($link['ws_guest_id']) : null;
    $channel = $guest ? ws_channel($guest['channel_id']) : null;
    $refusal = ws_guest_refusal($link, $guest, $channel);

    if ($refusal !== '') {
        ws_guest_offence();

        return array('ok' => false, 'error' => $refusal, 'csrf' => '');
    }

    $now = time();

    if ((string) $link['link_mode'] === 'once') {
        db("UPDATE short_links SET used_at = '" . $now . "', use_count = use_count + 1, last_used_at = '" . $now . "'
            WHERE id = '" . (int) $link['id'] . "' AND used_at = 0");

        if (mysqli_affected_rows(db::$con) !== 1) {
            return array('ok' => false, 'error' => lang('This link has already been used. Ask for a new one.'), 'csrf' => '');
        }
    } else {
        db("UPDATE short_links SET use_count = use_count + 1, last_used_at = '" . $now . "' WHERE id = '" . (int) $link['id'] . "'");
    }

    $secret = bin2hex(random_bytes(32));
    $csrf = bin2hex(random_bytes(16));
    $expires = ((int) $link['expires_at'] > 0) ? (int) $link['expires_at'] : 0;

    db("INSERT INTO ws_guest_sessions (guest_id, link_id, session_hash, csrf, ip, user_agent_hash, created_at, last_seen_at, expires_at)
        VALUES ('" . (int) $guest['id'] . "', '" . (int) $link['id'] . "', '" . hash('sha256', $secret) . "', '" . $csrf . "',
            '" . e(ws_guest_ip_prefix()) . "', '" . e(sha1((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) . "', '" . $now . "', '" . $now . "', '" . $expires . "')");

    db("UPDATE ws_guests SET last_seen_at = '" . $now . "' WHERE id = '" . (int) $guest['id'] . "'");

    ws_guest_cookie($secret, $expires);

    // For the rest of this request, which reads the room it just opened.
    $GLOBALS['ws_guest_claimed_secret'] = $secret;

    ws_message_system($channel['id'], lang(array('string' => '{var:1} joined the conversation', 'vars' => (string) $guest['name'])));

    return array('ok' => true, 'error' => '', 'csrf' => $csrf);
}

/**
 * Sets (or, with an empty secret, clears) the guest's cookie.
 *
 * @param string $secret
 * @param int    $expires 0 for WS_GUEST_IDLE from now
 */
function ws_guest_cookie($secret, $expires = 0)
{
    $until = ($secret === '') ? time() - 3600 : (($expires > 0) ? $expires : time() + WS_GUEST_IDLE);
    $secure = (URL_SCHEME === 'https://');

    if (PHP_VERSION_ID >= 70300) {
        setcookie(WS_GUEST_COOKIE, $secret, array('expires' => $until, 'path' => PATH, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie(WS_GUEST_COOKIE, $secret, $until, PATH . '; samesite=Lax', '', $secure, true);
    }
}

/**
 * The first three parts of the guest's address (IPv4), or the first four
 * groups (IPv6): enough to tell two places apart, not a person.
 *
 * @return string
 */
function ws_guest_ip_prefix()
{
    $ip = function_exists('waf_client_ip') ? (string) waf_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (strpos($ip, ':') !== false) {
        return implode(':', array_slice(explode(':', $ip), 0, 4)) . '::';
    }

    $parts = explode('.', $ip);

    return (count($parts) === 4) ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0' : '';
}

/**
 * A wrong or spent token counts against the address, the way a wrong API
 * key does: a run of them ends in the firewall's own ban.
 */
function ws_guest_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * The guest behind this request: session, guest and room, all still open.
 *
 * @param bool $fresh read again rather than from this request's first look
 * @return array|null session, guest, channel
 */
function ws_guest_context($fresh = false)
{
    static $context = false;

    if (($context !== false) && !$fresh) {
        return $context;
    }

    $context = null;
    $secret = (string) ($_COOKIE[WS_GUEST_COOKIE] ?? '');

    if (!ws_guests_ready() || !preg_match('/^[a-f0-9]{64}$/', $secret)) {
        return null;
    }

    $now = time();
    $session = db_item("SELECT * FROM ws_guest_sessions WHERE session_hash = '" . hash('sha256', $secret) . "' LIMIT 1");

    if (!is_array($session) || ((int) $session['ended_at'] > 0)
        || (((int) $session['expires_at'] > 0) && ((int) $session['expires_at'] <= $now))
        || (((int) $session['expires_at'] === 0) && ((int) $session['last_seen_at'] < ($now - WS_GUEST_IDLE)))) {
        return null;
    }

    // The link the session came in by: spending a one-time link is what
    // opened the session; closing a link (a new one given, the conversation
    // ended, a timed one running out) is what closes it.
    $link = db_item("SELECT * FROM short_links WHERE id = '" . (int) $session['link_id'] . "'");
    $guest = ws_guest($session['guest_id']);
    $channel = $guest ? ws_channel($guest['channel_id']) : null;

    if (ws_guest_refusal(is_array($link) ? $link : null, $guest, $channel) !== '') {
        return null;
    }

    // Seen: written once a minute at most.
    if ((int) $session['last_seen_at'] < ($now - 60)) {
        db("UPDATE ws_guest_sessions SET last_seen_at = '" . $now . "' WHERE id = '" . (int) $session['id'] . "'");
        db("UPDATE ws_guests SET last_seen_at = '" . $now . "' WHERE id = '" . (int) $guest['id'] . "'");
    }

    return $context = array('session' => $session, 'guest' => $guest, 'channel' => $channel);
}

/**
 * A guest's words as they are kept: tags and anything that reads as markup
 * of the panel taken out, trimmed, at most WS_GUEST_MESSAGE_MAX characters.
 *
 * @param string $text
 * @return string
 */
function ws_guest_clean_text($text)
{
    $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

    // Tags (<@user:1>, <#order:5>, <!…>) are the panel's: a guest writes none.
    $text = preg_replace('/<[@#!][a-z_]*:?\d*>/i', '', $text);
    $text = str_replace(array('<', '>'), array('‹', '›'), $text);

    // Titled blocks, code fences and commands are not a guest's either.
    $text = preg_replace('/^\s*(:::|```|\/)/mu', '', $text);

    return trim(mb_substr($text, 0, WS_GUEST_MESSAGE_MAX));
}

/**
 * A message as the guest reads it: the words with bold, italics and
 * strike-through, line breaks and plain links; a person tagged by name, a
 * record by its kind, nothing that leads into the panel.
 *
 * @param string $body
 * @return string HTML
 */
function ws_guest_render($body)
{
    $names = array();

    if (preg_match_all('/<@user:(\d+)>/', (string) $body, $found)) {
        foreach (array_unique($found[1]) as $user_id) {
            $names[(int) $user_id] = ws_person_name((int) $user_id);
        }
    }

    $text = preg_replace_callback('/<@user:(\d+)>/', function ($match) use ($names) {
        return '@' . ($names[(int) $match[1]] ?? '');
    }, (string) $body);

    // A record is named by its kind alone (an order, a product), in the
    // words of the site: what it is, never which one or where.
    static $kinds = null;

    if ($kinds === null) {
        $kinds = array();

        if (function_exists('ws_ref_types')) {
            $every = array('calendars' => true, 'contacts' => true, 'ecommerce' => true, 'erp' => true, 'erp_cash' => true, 'forms' => true, 'users' => true);

            foreach (ws_ref_types($every) as $key => $type) {
                $kinds[$key] = (string) $type['label'];
            }
        }
    }

    $text = preg_replace_callback('/<#([a-z_]+):\d+>/', function ($match) use ($kinds) {
        return '[' . ($kinds[$match[1]] ?? str_replace('_', ' ', $match[1])) . ']';
    }, $text);

    $text = preg_replace('/<[@#!][^>]*>/', '', $text);

    // Checklists and titled blocks read as their plain lines.
    $text = preg_replace('/^\s*- \[( |x)\]\s*/mi', '• ', $text);
    $text = preg_replace('/^\s*:::.*$/m', '', $text);

    $html = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');

    // Addresses are set aside first, so the marks below cannot reach into
    // them; then the marks the staff's screen reads the same way (render.php):
    // **bold**, _italics_, ~~strike-through~~.
    $links = array();
    $html = preg_replace_callback('#https?://[^\s<>"]{2,500}#i', function ($match) use (&$links) {
        $url = rtrim($match[0], '.,;:!?)');
        $links[] = '<a href="' . $url . '" target="_blank" rel="nofollow noopener noreferrer">' . $url . '</a>';

        return "\x01" . (count($links) - 1) . "\x01" . substr($match[0], strlen($url));
    }, $html);

    $html = preg_replace('/\*\*([^*\n]{1,300})\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/~~([^~\n]{1,300})~~/', '<del>$1</del>', $html);
    $html = preg_replace('/(?<![\p{L}\p{N}_])_([^_\n]{1,300}?)_(?![\p{L}\p{N}_])/u', '<em>$1</em>', $html);

    $html = preg_replace_callback("/\x01([0-9]+)\x01/", function ($match) use ($links) {
        return $links[(int) $match[1]] ?? '';
    }, $html);

    return nl2br($html, false);
}

/**
 * The tags of texts as the guest reads them: a person by name, a record or
 * a task by its kind alone, none of them a link into the panel.
 *
 * @param string[] $bodies
 * @return array ws_refs_resolve()-shaped, for ws_render_body()
 */
function ws_guest_refs($bodies)
{
    static $kinds = null;

    if ($kinds === null) {
        $kinds = array();

        if (function_exists('ws_ref_types')) {
            $every = array('calendars' => true, 'contacts' => true, 'ecommerce' => true, 'erp' => true, 'erp_cash' => true, 'forms' => true, 'users' => true);

            foreach (ws_ref_types($every) as $key => $type) {
                $kinds[$key] = array('label' => (string) $type['label'], 'icon' => (string) $type['icon']);
            }
        }

        $kinds['task'] = array('label' => lang('Task'), 'icon' => 'bi-check2-square');
    }

    $refs = array();

    foreach ((array) $bodies as $body) {
        foreach (ws_tokens((string) $body) as $token) {
            $key = $token['type'] . ':' . $token['id'];

            if (isset($refs[$key])) {
                continue;
            }

            $person = ($token['type'] === 'user');

            $refs[$key] = array(
                'type'   => $token['type'],
                'id'     => (int) $token['id'],
                'label'  => $person ? '@' . ws_person_name((int) $token['id']) : ($kinds[$token['type']]['label'] ?? str_replace('_', ' ', $token['type'])),
                'url'    => '',
                'meta'   => '',
                'status' => '',
                'known'  => true,
                'icon'   => $person ? 'bi-person' : ($kinds[$token['type']]['icon'] ?? ''),
            );
        }
    }

    return $refs;
}

/**
 * The messages of the guest's room as the guest reads them: drawn by the
 * same engine the staff's screen uses - formatting, tables, sums, code,
 * titled blocks, checklists - with the files and pictures, the polls and the
 * task cards staff put in the room. What the guest writes is drawn with the
 * few marks a guest may use. The room's system lines are the staff's.
 *
 * @param array $context
 * @param int   $after  only the ones after this id
 * @param int   $limit
 * @return array[]
 */
function ws_guest_messages($context, $after = 0, $limit = 80)
{
    $channel_id = (int) $context['channel']['id'];
    $guest_id = (int) $context['guest']['id'];

    // The current conversation only: what came before a clear is an earlier
    // version the team keeps (eras.php).
    $floor = function_exists('ws_channel_floor') ? ws_channel_floor($channel_id) : 0;

    $rows = array_reverse((array) db_items("SELECT id, parent_id, sender_kind, sender_id, kind, body, task_id, file_id, file_name, edited_at, created_at
        FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND deleted_at = 0
        AND kind IN ('message', 'decision', 'note', 'task') AND sender_kind IN ('user', 'guest')
        AND id > '" . max((int) $after, $floor) . "'
        ORDER BY id DESC LIMIT " . max(1, min(200, (int) $limit))));

    $ids = array_map(function ($row) { return (int) $row['id']; }, $rows);
    $reactions = array();

    if (!empty($ids)) {
        foreach ((array) db_items("SELECT message_id, sender_kind, sender_id, emoji FROM ws_reactions
            WHERE message_id IN (" . implode(',', $ids) . ") ORDER BY id") as $row) {
            $key = (string) $row['emoji'];
            $message_id = (int) $row['message_id'];

            if (!isset($reactions[$message_id][$key])) {
                $reactions[$message_id][$key] = array('emoji' => $key, 'count' => 0, 'mine' => false);
            }

            $reactions[$message_id][$key]['count']++;

            if (($row['sender_kind'] === 'guest') && ((int) $row['sender_id'] === $guest_id)) {
                $reactions[$message_id][$key]['mine'] = true;
            }
        }
    }

    $staff_rows = array_filter($rows, function ($row) { return $row['sender_kind'] === 'user'; });
    $refs = ws_guest_refs(array_map(function ($row) { return (string) $row['body']; }, $staff_rows));
    $checks = function_exists('ws_checks_map') ? ws_checks_map($ids) : array();
    $polls = function_exists('ws_polls_map') ? ws_polls_map($ids, array('id' => 0, 'role' => 3, 'member' => false, 'guest_id' => $guest_id)) : array();

    // Approval requests staff made in the room, to read: a guest is never
    // asked (approvals.php).
    $approvals = function_exists('ws_approvals_map') ? ws_approvals_map($ids, array('id' => 0, 'role' => 3, 'member' => false, 'guest_id' => $guest_id,
        'ecommerce' => false, 'contacts' => false, 'erp' => false, 'erp_cash' => false, 'forms' => false, 'calendars' => false, 'users' => false)) : array();

    // A checklist that became tasks is ticked by staff: the ticks move them.
    $listed = empty($ids) || !function_exists('ws_task_work_ready') || !ws_task_work_ready() ? array()
        : array_flip(array_map('intval', (array) db_values("SELECT DISTINCT checklist_message_id FROM ws_tasks WHERE checklist_message_id IN (" . implode(',', $ids) . ")")));

    $people = ws_people(array_map(function ($row) { return ($row['sender_kind'] === 'user') ? (int) $row['sender_id'] : 0; }, $rows));
    $endpoint = PATH . SOFTWARE_DIRECTORY . '/workspace_guest.php';
    $out = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $staff = ($row['sender_kind'] === 'user');
        $mine = !$staff && ((int) $row['sender_id'] === $guest_id);
        $sender = $staff ? ($people[(int) $row['sender_id']] ?? null) : ws_guest_sender((int) $row['sender_id']);
        $can_tick = $staff && !isset($listed[$id]);

        $message = array(
            'id'        => $id,
            'mine'      => $mine,
            'staff'     => $staff,
            'name'      => (string) ($sender['name'] ?? ''),
            'avatar'    => (string) ($sender['avatar'] ?? ''),
            'html'      => $staff ? ws_render_body((string) $row['body'], $refs, $checks[$id] ?? array(), $can_tick) : ws_guest_render($row['body']),
            'time'      => date('d.m.Y H:i', (int) $row['created_at']),
            'edited'    => ((int) $row['edited_at'] > 0),
            'parent'    => null,
            'file'      => null,
            'poll'      => null,
            'approval'  => null,
            'task'      => null,
            'reactions' => array_values($reactions[$id] ?? array()),
        );

        if ((int) $row['parent_id'] > 0) {
            $quoted = db_item("SELECT id, sender_kind, sender_id, body, deleted_at, kind FROM ws_messages
                WHERE id = '" . (int) $row['parent_id'] . "' AND channel_id = '" . $channel_id . "'");

            if (is_array($quoted) && ((int) $quoted['deleted_at'] === 0) && in_array($quoted['kind'], array('message', 'decision', 'note'), true)
                && in_array($quoted['sender_kind'], array('user', 'guest'), true)) {
                $quoted_sender = ($quoted['sender_kind'] === 'guest') ? ws_guest_sender((int) $quoted['sender_id']) : current(ws_people(array((int) $quoted['sender_id'])));
                $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(ws_guest_render($quoted['body']))));

                $message['parent'] = array(
                    'id'   => (int) $quoted['id'],
                    'name' => (string) ($quoted_sender['name'] ?? ''),
                    'text' => mb_substr(html_entity_decode($plain, ENT_QUOTES, 'UTF-8'), 0, 120),
                );
            }
        }

        // A file or a picture staff put in the room, from the guest's own
        // address: the channel's folder opens to its members only.
        if ($staff && ((int) $row['file_id'] > 0) && ((string) db_value("SELECT name FROM files WHERE id = '" . (int) $row['file_id'] . "'") !== '')) {
            $message['file'] = array(
                'name' => (string) $row['file_name'],
                'url'  => $endpoint . '?file=' . $id,
                'kind' => ws_file_kind($row['file_name']),
                'ext'  => strtolower((string) pathinfo((string) $row['file_name'], PATHINFO_EXTENSION)),
            );
        }

        if (isset($polls[$id])) {
            $poll = $polls[$id];
            $options = array();

            foreach ($poll['options'] as $option) {
                $options[] = array(
                    'id'      => (int) $option['id'],
                    'label'   => (string) $option['label'],
                    'count'   => (int) $option['count'],
                    'mine'    => !empty($option['mine']),
                    'leading' => !empty($option['leading']),
                    'voters'  => array_map(function ($voter) { return (string) $voter['name']; }, (array) $option['voters']),
                );
            }

            $message['poll'] = array(
                'id'       => (int) $poll['id'],
                'multiple' => !empty($poll['multiple']),
                'closed'   => !empty($poll['closed']),
                'closes'   => (string) $poll['closes'],
                'voters'   => (int) $poll['voters'],
                'voted'    => !empty($poll['voted']),
                'options'  => $options,
            );
        }

        if (isset($approvals[$id])) {
            $approval = $approvals[$id];

            $message['approval'] = array(
                'title'  => (string) $approval['title'],
                'state'  => $approval['open'] ? lang('Approval pending') : ws_guest_approval_outcome($approval['outcome']),
                'open'   => !empty($approval['open']),
                'people' => array_map(function ($person) {
                    return array('name' => (string) $person['name'], 'decision' => (string) $person['decision']);
                }, (array) $approval['people']),
            );
        }

        // A task staff made in the room: what it is and where it stands, to
        // read; the task itself is the staff's.
        if (($row['kind'] === 'task') && ((int) $row['task_id'] > 0) && function_exists('ws_task')) {
            $task = ws_task((int) $row['task_id']);

            if ($task) {
                $message['task'] = array(
                    'title'  => (string) $task['title'],
                    'status' => ws_task_status_label($task['status']),
                    'due'    => ((string) ($task['due_date'] ?? '') !== '') ? ws_task_due_label($task) : '',
                    'open'   => function_exists('ws_task_is_open') ? ws_task_is_open($task['status']) : true,
                );
            }
        }

        $out[] = $message;
    }

    return $out;
}

/**
 * How a settled approval request ended, in the words of the guest's page.
 *
 * @param string $outcome
 * @return string
 */
function ws_guest_approval_outcome($outcome)
{
    $labels = array(
        'approved'  => lang('Approved'),
        'rejected'  => lang('Rejected'),
        'expired'   => lang('Expired'),
        'withdrawn' => lang('Closed without a decision'),
    );

    return $labels[$outcome] ?? '';
}

/**
 * The guest's vote in a poll of their room, replacing the one before; an
 * empty choice takes it back.
 *
 * @param array $context
 * @param int   $poll_id
 * @param int[] $option_ids
 * @return array ok, error
 */
function ws_guest_vote($context, $poll_id, $option_ids)
{
    $poll = function_exists('ws_poll') ? ws_poll((int) $poll_id) : null;

    if (!$poll || ((int) $poll['channel_id'] !== (int) $context['channel']['id']) || !ws_guest_votes_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (((int) $poll['closed_at'] > 0) || (((int) $poll['closes_at'] > 0) && ((int) $poll['closes_at'] <= time()))) {
        return array('ok' => false, 'error' => lang('The poll is closed.'));
    }

    $valid = array_map('intval', (array) db_values("SELECT id FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "'"));
    $chosen = array_values(array_unique(array_intersect(array_map('intval', (array) $option_ids), $valid)));

    if ((count($chosen) > 1) && ((int) $poll['multiple'] !== 1)) {
        $chosen = array($chosen[0]);
    }

    $guest_id = (int) $context['guest']['id'];

    db("DELETE FROM ws_poll_votes WHERE poll_id = '" . (int) $poll['id'] . "' AND user_id = 0 AND guest_id = '" . $guest_id . "'");

    foreach ($chosen as $option_id) {
        db("INSERT IGNORE INTO ws_poll_votes (poll_id, option_id, user_id, guest_id, created_at)
            VALUES ('" . (int) $poll['id'] . "', '" . (int) $option_id . "', 0, '" . $guest_id . "', '" . time() . "')");
    }

    ws_message_touch($poll['message_id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The guest ticks an item of a checklist staff wrote in the room, or clears
 * it. Not one that became tasks: those ticks are the staff's.
 *
 * @param array $context
 * @param int   $message_id
 * @param int   $item
 * @param bool  $checked
 * @return array ok, error
 */
function ws_guest_check($context, $message_id, $item, $checked)
{
    $message = db_item("SELECT * FROM ws_messages
        WHERE id = '" . (int) $message_id . "' AND channel_id = '" . (int) $context['channel']['id'] . "'
        AND deleted_at = 0 AND sender_kind = 'user'");

    if (!is_array($message) || !ws_guest_votes_ready() || !function_exists('ws_checklist_items')) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (function_exists('ws_task_work_ready') && ws_task_work_ready()
        && ((int) db_value("SELECT COUNT(*) FROM ws_tasks WHERE checklist_message_id = '" . (int) $message['id'] . "'") > 0)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $items = ws_checklist_items($message['body']);
    $item = (int) $item;

    if (!isset($items[$item])) {
        return array('ok' => false, 'error' => lang('That item is no longer in the list.'));
    }

    db("INSERT INTO ws_checks (message_id, item, checked, user_id, guest_id, updated_at)
        VALUES ('" . (int) $message['id'] . "', '" . $item . "', '" . ($checked ? 1 : 0) . "', 0, '" . (int) $context['guest']['id'] . "', '" . time() . "')
        ON DUPLICATE KEY UPDATE checked = VALUES(checked), user_id = VALUES(user_id), guest_id = VALUES(guest_id), updated_at = VALUES(updated_at)");

    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Do polls and checklists know a guest's vote and tick (5.112)?
 *
 * @return bool
 */
function ws_guest_votes_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_guests_ready()
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_poll_votes', 'ws_checks') AND COLUMN_NAME = 'guest_id'") === 2);
    }

    return $ready;
}

/**
 * Sends the guest a file staff put in their room, or answers 404.
 *
 * @param array|null $context
 * @param int        $message_id
 */
function ws_guest_file_send($context, $message_id)
{
    $row = $context ? db_item("SELECT m.file_name, f.name AS stored FROM ws_messages m
        INNER JOIN files f ON f.id = m.file_id
        WHERE m.id = '" . (int) $message_id . "' AND m.channel_id = '" . (int) $context['channel']['id'] . "'
        AND m.deleted_at = 0 AND m.sender_kind = 'user' AND m.file_id > 0") : null;

    $path = is_array($row) ? FILE_DIRECTORY_PATH . '/' . basename((string) $row['stored']) : '';

    if (($path === '') || !is_file($path)) {
        http_response_code(404);
        exit;
    }

    // Shown in the page: pictures, sound, video and PDF. Anything else, and
    // anything a browser could run (HTML, SVG), is downloaded.
    $types = array(
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'bmp' => 'image/bmp', 'pdf' => 'application/pdf',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4', 'ogv' => 'video/ogg',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'opus' => 'audio/ogg', 'aac' => 'audio/aac', 'weba' => 'audio/webm',
    );

    $name = (string) $row['file_name'];
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $inline = isset($types[$extension]);
    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

    header('Content-Type: ' . ($inline ? $types[$extension] : 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . filesize($path));

    readfile($path);
    exit;
}

/**
 * Is the guest writing too fast? A second between messages, twenty a
 * minute, three hundred a day.
 *
 * @param array $context
 * @return bool
 */
function ws_guest_rate_limited($context)
{
    $guest_id = (int) $context['guest']['id'];
    $now = time();
    $last = db_item("SELECT
            MAX(created_at) AS last_at,
            SUM(created_at > '" . ($now - 60) . "') AS minute,
            SUM(created_at > '" . ($now - 86400) . "') AS day
        FROM ws_messages WHERE sender_kind = 'guest' AND sender_id = '" . $guest_id . "' AND created_at > '" . ($now - 86400) . "'");

    return is_array($last) && (((int) $last['last_at'] >= $now) || ((int) $last['minute'] >= 20) || ((int) $last['day'] >= 300));
}

/**
 * A message from the guest, into their room. Staff in the room hear about
 * it in their inbox (one notice until they have read it).
 *
 * @param array  $context
 * @param string $text
 * @param int    $parent_id
 * @return array ok, error, message_id
 */
function ws_guest_send($context, $text, $parent_id = 0)
{
    $text = ws_guest_clean_text($text);

    if ($text === '') {
        return array('ok' => false, 'error' => lang('The message is empty.'), 'message_id' => 0);
    }

    if (ws_guest_rate_limited($context)) {
        return array('ok' => false, 'error' => lang('You are sending messages too quickly. Please wait a moment.'), 'message_id' => 0);
    }

    $channel = $context['channel'];
    $guest = $context['guest'];
    $parent_id = (int) $parent_id;

    if (($parent_id > 0) && ((int) db_value("SELECT COUNT(*) FROM ws_messages
        WHERE id = '" . $parent_id . "' AND channel_id = '" . (int) $channel['id'] . "' AND deleted_at = 0 AND kind = 'message'") === 0)) {
        $parent_id = 0;
    }

    $now = time();

    db("INSERT INTO ws_messages (channel_id, parent_id, sender_kind, sender_id, kind, body, created_at)
        VALUES ('" . (int) $channel['id'] . "', '" . $parent_id . "', 'guest', '" . (int) $guest['id'] . "', 'message', '" . e($text) . "', '" . $now . "')");

    $message_id = (int) mysqli_insert_id(db::$con);

    if ($message_id <= 0) {
        return array('ok' => false, 'error' => lang('The message could not be saved.'), 'message_id' => 0);
    }

    db("UPDATE ws_channels SET last_message_id = '" . $message_id . "', last_message_at = '" . $now . "' WHERE id = '" . (int) $channel['id'] . "'");

    foreach ((array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "' AND notify <> 'none'") as $user_id) {
        ws_notify((int) $user_id, 'guest_message', array('channel_id' => (int) $channel['id'], 'actor_id' => 0));
    }

    return array('ok' => true, 'error' => '', 'message_id' => $message_id);
}

/**
 * The guest leaves an emoji on a message of their room, or takes it back.
 *
 * @param array  $context
 * @param int    $message_id
 * @param string $emoji
 * @return array ok, error
 */
function ws_guest_react($context, $message_id, $emoji)
{
    $emoji = (string) $emoji;

    if (!in_array($emoji, explode(',', WS_GUEST_EMOJI), true)) {
        return array('ok' => false, 'error' => lang('That is not an emoji.'));
    }

    $message = db_item("SELECT id FROM ws_messages
        WHERE id = '" . (int) $message_id . "' AND channel_id = '" . (int) $context['channel']['id'] . "'
        AND deleted_at = 0 AND kind = 'message' AND sender_kind IN ('user', 'guest')");

    if (!is_array($message)) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    $where = "message_id = '" . (int) $message['id'] . "' AND sender_kind = 'guest' AND sender_id = '" . (int) $context['guest']['id'] . "' AND emoji = '" . e($emoji) . "'";

    if ((int) db_value("SELECT COUNT(*) FROM ws_reactions WHERE " . $where) > 0) {
        db("DELETE FROM ws_reactions WHERE " . $where);
    } else {
        db("INSERT IGNORE INTO ws_reactions (message_id, sender_kind, sender_id, emoji, created_at)
            VALUES ('" . (int) $message['id'] . "', 'guest', '" . (int) $context['guest']['id'] . "', '" . e($emoji) . "', '" . time() . "')");
    }

    if (function_exists('ws_message_touch')) {
        ws_message_touch($message['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The choices of how long a timed link is valid, for the screens.
 *
 * @return array[] v (seconds), t (label)
 */
function ws_guest_duration_options()
{
    $out = array();

    foreach (pg_short_link_durations() as $seconds => $label) {
        if ($seconds <= WS_GUEST_LONGEST) {
            $out[] = array('v' => (string) $seconds, 't' => $label);
        }
    }

    return $out;
}

/**
 * What the workspace screen needs to offer a conversation with a guest.
 *
 * @param array $viewer
 * @return array
 */
function ws_guests_js_config($viewer)
{
    if (!ws_can_host_guests($viewer)) {
        return array('can_host' => false, 'durations' => array());
    }

    return array('can_host' => true, 'durations' => ws_guest_duration_options());
}

/**
 * A mark that changes whenever anything the guest reads in the room does:
 * a message written, edited, deleted, marked or answered with an emoji. The
 * guest's page asks with the mark it has and is sent the messages only when
 * it no longer holds.
 *
 * @param array $context
 * @return string
 */
function ws_guest_signature($context)
{
    $row = db_item("SELECT MAX(id) AS last_id, COUNT(*) AS total,
            MAX(GREATEST(edited_at, deleted_at, touched_at, marked_at)) AS changed
        FROM ws_messages WHERE channel_id = '" . (int) $context['channel']['id'] . "'");

    return is_array($row) ? ((int) $row['last_id'] . '.' . (int) $row['total'] . '.' . (int) $row['changed']) : '';
}

/**
 * The room as the guest's page draws it: who they are, the staff in it and,
 * when the mark it holds is out of date, the messages.
 *
 * @param array  $context
 * @param string $signature what the page holds
 * @return array
 */
function ws_guest_state($context, $signature = '')
{
    $channel = $context['channel'];
    $now = ws_guest_signature($context);
    $staff = array();

    foreach (ws_people(array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members
        WHERE channel_id = '" . (int) $channel['id'] . "' ORDER BY joined_at"))) as $person) {
        $staff[] = array('name' => (string) $person['name'], 'avatar' => (string) $person['avatar']);
    }

    $shared = !ws_channel_is_guest($channel);

    $state = array(
        'status'    => 'ok',
        'csrf'      => (string) $context['session']['csrf'],
        'guest'     => (string) $context['guest']['name'],
        'topic'     => (string) $channel['topic'],
        // A channel of the team: its name is the page's title. Reading only:
        // the page has no writing box and no buttons that change anything.
        'title'     => $shared ? '#' . (string) $channel['name'] : '',
        'read_only' => ws_guest_reads_only($context['guest']),
        'privacy'   => $shared ? lang('The team of the site reads what you write here.') : lang('Only the people of the site in this conversation read what you write here.'),
        'staff'     => $staff,
        'signature' => $now,
        'same'      => ($signature !== '') && ($signature === $now),
    );

    if (!$state['same']) {
        $state['messages'] = ws_guest_messages($context);
    }

    return $state;
}

/**
 * One request of the guest's page (workspace_guest.php).
 *
 * claim (token): the token taken, a session opened. hello: the session this
 * browser already has, if any. state (signature), send (text, parent_id),
 * react (message_id, emoji), vote (poll_id, option_ids), check (message_id,
 * item, checked): with the session and its csrf.
 *
 * @param string $action
 * @param array  $request
 * @return array
 */
function ws_guest_handle($action, $request)
{
    $ended = array('status' => 'ended', 'message' => lang('This conversation is not open to you any more. If you still need it, ask for a new link.'));

    if ($action === 'claim') {

        // Opening links is counted by address: a run of guesses meets the
        // firewall long before it meets a token.
        if (function_exists('waf_rate_exceeded') && function_exists('waf_client_ip') && waf_rate_exceeded(waf_client_ip(), 'ws_guest_open', 10, 60)) {
            return array('status' => 'error', 'message' => lang('Too many attempts. Please wait a minute and try again.'));
        }

        // A browser that is already in the room goes on in it.
        $context = ws_guest_context();

        if (!$context) {
            $claimed = ws_guest_claim((string) ($request['token'] ?? ''));

            if (!$claimed['ok']) {
                return array('status' => 'refused', 'message' => $claimed['error']);
            }

            $context = ws_guest_context_fresh();
        }

        return $context ? ws_guest_state($context) : $ended;
    }

    $context = ws_guest_context();

    // A browser that was in the room and is not any more is told so; one
    // that never was is asked for its link.
    if ($action === 'hello') {
        return $context ? ws_guest_state($context) : (isset($_COOKIE[WS_GUEST_COOKIE]) ? $ended : array('status' => 'none'));
    }

    if (!$context) {
        return $ended;
    }

    if (!hash_equals((string) $context['session']['csrf'], (string) ($request['csrf'] ?? ''))) {
        return array('status' => 'error', 'message' => lang('The page has expired. Please reload it.'));
    }

    if (in_array($action, array('send', 'react', 'vote', 'check'), true) && ws_guest_reads_only($context['guest'])) {
        return array('status' => 'error', 'message' => lang('This conversation was shared with you to read only.'));
    }

    switch ($action) {

        case 'state':
            return ws_guest_state($context, (string) ($request['signature'] ?? ''));

        case 'send':
            $sent = ws_guest_send($context, (string) ($request['text'] ?? ''), (int) ($request['parent_id'] ?? 0));

            return $sent['ok'] ? ws_guest_state($context) : array('status' => 'error', 'message' => $sent['error']);

        case 'react':
            $reacted = ws_guest_react($context, (int) ($request['message_id'] ?? 0), (string) ($request['emoji'] ?? ''));

            return $reacted['ok'] ? ws_guest_state($context) : array('status' => 'error', 'message' => $reacted['error']);

        case 'vote':
            $voted = ws_guest_vote($context, (int) ($request['poll_id'] ?? 0), (array) ($request['option_ids'] ?? array()));

            return $voted['ok'] ? ws_guest_state($context) : array('status' => 'error', 'message' => $voted['error']);

        case 'check':
            $ticked = ws_guest_check($context, (int) ($request['message_id'] ?? 0), (int) ($request['item'] ?? 0), !empty($request['checked']));

            return $ticked['ok'] ? ws_guest_state($context) : array('status' => 'error', 'message' => $ticked['error']);
    }

    return array('status' => 'error', 'message' => lang('Invalid request.'));
}

/**
 * The guest behind this request, read again: right after a claim the cookie
 * is set for the next request, not in this one.
 *
 * @return array|null
 */
function ws_guest_context_fresh()
{
    if (!empty($GLOBALS['ws_guest_claimed_secret'])) {
        $_COOKIE[WS_GUEST_COOKIE] = $GLOBALS['ws_guest_claimed_secret'];
    }

    return ws_guest_context(true);
}

/**
 * The texts of the guest's page.
 *
 * @return array key => text
 */
function ws_guest_page_strings()
{
    return array(
        'title'        => lang('Conversation'),
        'loading'      => lang('Opening the conversation…'),
        'placeholder'  => lang('Write a message'),
        'send'         => lang('Send'),
        'reply'        => lang('Reply'),
        'react'        => lang('Add a reaction'),
        'cancel'       => lang('Cancel'),
        'bold'         => lang('Bold'),
        'italic'       => lang('Italic'),
        'strike'       => lang('Strikethrough'),
        'you'          => lang('You'),
        'edited'       => lang('edited'),
        'empty'        => lang('No messages yet. Write the first one.'),
        'no_link'      => lang('Open the link you were sent to join the conversation.'),
        'error'        => lang('Something went wrong. Please try again.'),
        'offline'      => lang('The connection was lost. Trying again…'),
        'hint'         => lang('Enter sends, Shift+Enter starts a new line.'),
        'download'     => lang('Download'),
        'task'         => lang('Task'),
        'poll_voters'  => ws_js_template('{var:1} people voted', 1),
        'poll_closed'  => lang('The poll is closed.'),
        'poll_closes'  => ws_js_template('Closes {var:1}', 1),
        'poll_multiple' => lang('You can choose more than one.'),
        'approval'     => lang('Approval request'),
        'privacy'      => lang('Only the people of the site in this conversation read what you write here.'),
        'read_only'    => lang('This conversation was shared with you to read only.'),
        'readonly_badge' => lang('Read only'),
    );
}

/**
 * The texts of the guest room's parts on the channel screen.
 *
 * @return array key => text
 */
function ws_guests_js_strings()
{
    return array(
        'guest_start'        => lang('Talk with a guest'),
        'guest_start_help'   => lang('A room of its own for a conversation with somebody who has no account: a customer, a supplier. Everything written in it is read by the guest. Only staff can be in it.'),
        'guest_name'         => lang('Name of the guest'),
        'guest_topic'        => lang('Topic (optional)'),
        'guest_mode'         => lang('Link'),
        'guest_mode_once'    => lang('One-time: the first browser that opens it holds the room'),
        'guest_mode_timed'   => lang('Timed: valid until a time you choose'),
        'guest_duration'     => lang('Valid for'),
        'guest_staff'        => lang('Staff in the room'),
        'guest_create'       => lang('Start the conversation'),
        'guest_link_help'    => lang('Send this address to the guest yourself. It is shown only now; if it is lost, give a new link.'),
        'guest_link_done'    => lang('Close'),
        'guest_copy'         => lang('Copy'),
        'guest_copied'       => lang('Copied'),
        'guest_banner'       => ws_js_template('A guest is in this room: {var:1}. Everything written here is read by them.', 1),
        'guest_ended'        => ws_js_template('The conversation with {var:1} has ended. A new link opens the room again.', 1),
        'guest_seen'         => ws_js_template('Last here: {var:1}', 1),
        'guest_online'       => lang('Here now'),
        'guest_hidden'       => lang('The guest sees what is written and put in this room as you do - files, pictures, tables, polls - and can answer, vote and tick; they cannot add any of it.'),
        'guest_relink'       => lang('New link'),
        'guest_relink_help'  => lang('A new link closes the one given before and the browsers that came in by it.'),
        'guest_relink_make'  => lang('Make the link'),
        'guest_end'          => lang('End the conversation'),
        'guest_end_confirm'  => lang('End the conversation? The guest\'s link and session are closed and the room is archived. A new link opens it again.'),
        'guest_ended_toast'  => lang('The conversation has ended.'),
        'guest_badge'        => lang('Guest'),
        'share_start'        => lang('Share with a link'),
        'share_help'         => lang('Somebody without an account reads this channel\'s conversation through a link: earlier messages, files and pictures in it, polls and task cards. Nothing else of the workspace opens to them. While it is shared, the assistants are not asked here.'),
        'share_access'       => lang('What they may do'),
        'share_read'         => lang('Read only'),
        'share_read_help'    => lang('They read the conversation as it goes on; they cannot write, react or vote.'),
        'share_write'        => lang('Read and write'),
        'share_write_help'   => lang('They also write, answer, leave an emoji, vote and tick, as a guest of a room does.'),
        'share_create'       => lang('Make the link'),
        'share_bar'          => ws_js_template('This channel is shared outside the team: {var:1}. They read everything written in the conversation.', 1),
        'share_bar_closed'   => lang('This channel was shared outside the team; no link is open now.'),
        'share_read_badge'   => lang('reads'),
        'share_write_badge'  => lang('reads and writes'),
        'share_manage'       => lang('Manage'),
        'share_end'          => lang('Stop sharing'),
        'share_end_confirm'  => ws_js_template('Stop sharing this channel with {var:1}? Their link and session are closed; the channel stays as it is.', 1),
        'share_ended_toast'  => lang('The channel is no longer shared with them.'),
        'share_add'          => lang('Share with somebody else'),
    );
}

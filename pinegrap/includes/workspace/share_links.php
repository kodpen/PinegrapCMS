<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - share links: one item of one message of an agent channel
 * (ws_channels.agent_channel) handed on by an address nobody can guess, read
 * only, with an end.
 *
 * The token is 43 characters from 32 random bytes (pg_short_link_new_token())
 * and is kept only as its SHA-256 (ws_share_links.token_hash), as an approval
 * link of ws_approval_guests is: the address is given once, when the link is
 * made, and a lost one is made again. A link ends after 1, 7 or 30 days, 7
 * unless chosen, never without an end; its maker and whoever manages the
 * channel take it back at any time.
 *
 * A link is also closed while its channel is gone or archived, its message is
 * deleted or lies before the conversation's clear floor, or the item is gone
 * (ws_share_link_state(), one pure rule). A channel holds at most
 * WS_SHARE_LINK_MAX_OPEN open links, and an item one: a second is refused with
 * the end of the open one.
 *
 * Who makes one: a person who may post in the channel, or an application that
 * has an allowed agent row in it. Making a link changes no record, so an
 * agent makes it at once, with no proposal card (workspace/agent-risk-tiers.md).
 * The recurring job deletes the rows that ended WS_SHARE_LINK_KEEP ago
 * (ws_share_links_sweep()).
 *
 * The address (workspace_share.php) asks ws_share_link_answer() what to
 * send: the file, the read-only page of a note, a table or a checklist, or
 * one 404 for every fault. An open is counted once a
 * minute a link at most (ws_share_link_count_open()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The days a link may last, the choices of the dialog and the API. */
define('WS_SHARE_LINK_DAYS', '1,7,30');

/** The days a link lasts when none is chosen. */
define('WS_SHARE_LINK_DEFAULT_DAYS', 7);

/** The most open links one channel holds. */
define('WS_SHARE_LINK_MAX_OPEN', 200);

/** How long a row is kept after its link ended, in seconds. */
define('WS_SHARE_LINK_KEEP', 30 * 86400);

/** Seconds between two opens of one link that are counted. */
define('WS_SHARE_LINK_OPEN_EVERY', 60);

/**
 * Is the table there (2026.11.0, 11.0.273)?
 *
 * @return bool
 */
function ws_share_links_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_share_links', 'token_hash');
    }

    return $ready;
}

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The kinds of item a link can be made to: a message's file, the channel's
 * note whose card it is, a table or a checklist written in its text
 * (share_links_text.php). A note is only one written in the channel, which
 * is the channel's (ws_notes.owner_channel_id). Pure.
 *
 * @return string[]
 */
function ws_share_link_kinds()
{
    return array('file', 'note', 'table', 'list');
}

/**
 * The days a link may last. Pure.
 *
 * @return int[]
 */
function ws_share_link_day_choices()
{
    return array_map('intval', explode(',', WS_SHARE_LINK_DAYS));
}

/**
 * The days asked for, cleaned: nothing given is the default, anything other
 * than a choice is 0 (refused). Pure.
 *
 * @param mixed $days
 * @return int
 */
function ws_share_link_days_clean($days)
{
    if (($days === null) || ($days === '')) {
        return WS_SHARE_LINK_DEFAULT_DAYS;
    }

    if (!is_int($days) && !(is_string($days) && (preg_match('/^[0-9]{1,3}$/', $days) === 1))) {
        return 0;
    }

    return in_array((int) $days, ws_share_link_day_choices(), true) ? (int) $days : 0;
}

/**
 * When a link made now for this many days ends. Pure.
 *
 * @param int $now
 * @param int $days ws_share_link_days_clean()
 * @return int
 */
function ws_share_link_expiry($now, $days)
{
    return (int) $now + max(1, (int) $days) * 86400;
}

/**
 * Could this be a token of a link? The shape of an approval link's token.
 * Pure.
 *
 * @param mixed $token
 * @return bool
 */
function ws_share_link_token_shape($token)
{
    return ws_approval_guest_token_shape($token);
}

/**
 * Is the item's channel closed to a link: gone or archived, or a discussion
 * whose channel is? Pure.
 *
 * @param array|null $channel ws_channels row
 * @param array|null $parent  the channel of a discussion, null otherwise
 * @return bool
 */
function ws_share_link_channel_closed($channel, $parent)
{
    if (!is_array($channel) || ((int) $channel['archived_at'] > 0)) {
        return true;
    }

    return ((string) $channel['kind'] === 'thread') && ((int) ($parent['archived_at'] ?? 1) > 0);
}

/**
 * The state of a link: revoked when taken back, expired after its end,
 * closed while its channel, message or item no longer stands (gone,
 * archived, deleted, before the clear floor), open otherwise. Pure.
 *
 * @param array      $link         ws_share_links row
 * @param int        $now
 * @param array|null $channel      the channel of the message
 * @param array|null $parent       its channel when that is a discussion
 * @param array|null $message      ws_messages row
 * @param bool       $in_past      ws_message_in_past() of the message
 * @param bool       $item_present the item is still in the message
 * @return string open | revoked | expired | closed
 */
function ws_share_link_state($link, $now, $channel, $parent, $message, $in_past, $item_present)
{
    if ((int) ($link['revoked_at'] ?? 0) > 0) {
        return 'revoked';
    }

    if ((int) ($link['expires_at'] ?? 0) <= (int) $now) {
        return 'expired';
    }

    if (ws_share_link_channel_closed($channel, $parent) || !is_array($message) || ((int) $message['deleted_at'] > 0)
        || $in_past || !$item_present) {
        return 'closed';
    }

    return 'open';
}

/**
 * Does the item stand in the message? A file is the one the message carries,
 * still in the file manager's table. A note is the one whose card the
 * message is, and it belongs to the message's channel: a person's note shown
 * in the channel is theirs, never handed on by the channel. A table or a
 * checklist is the n-th of its kind in the message's text. Pure.
 *
 * @param array|null $message  ws_messages row
 * @param string     $kind
 * @param int        $item_id
 * @param mixed      $item_row ws_share_link_item_row(): for a file whether its
 *                             files row exists, for a note the ws_notes row
 *                             the message's card shows, for a table or a
 *                             list its block, or null
 * @return bool
 */
function ws_share_link_item_in($message, $kind, $item_id, $item_row)
{
    if (!is_array($message) || ((int) $item_id <= 0)) {
        return false;
    }

    if ((string) $kind === 'file') {
        return ((int) $message['file_id'] === (int) $item_id) && ($item_row === true);
    }

    if ((string) $kind === 'note') {
        return is_array($item_row) && ((int) $item_row['id'] === (int) $item_id)
            && (ws_note_channel_of($item_row) === (int) $message['channel_id']);
    }

    if (in_array((string) $kind, array('table', 'list'), true)) {
        return is_array($item_row) && ($item_row['kind'] === (string) $kind) && ((int) $item_row['number'] === (int) $item_id);
    }

    return false;
}

/**
 * What a note is called on its link's page, in the conversation's line and
 * in the channel's Sharing: its title, or its first line with tags read as
 * plain names (ws_guest_refs()), so a name never says more than the page.
 *
 * @param array $note ws_notes row
 * @return string
 */
function ws_share_link_note_name($note)
{
    $title = trim((string) ($note['title'] ?? ''));

    if ($title !== '') {
        return $title;
    }

    $body = ws_share_link_note_plain_tags((string) ($note['body'] ?? ''));

    // No tag is left for ws_note_title() to resolve, so no reader's rights
    // decide the name.
    return ws_note_title(array('id' => 0, 'role' => 3), array('title' => '', 'body' => $body));
}

/**
 * A note's text with its tags as plain names: a person as @name, a record
 * by its kind alone, never a link into the panel (ws_guest_refs()).
 *
 * @param string $body
 * @return string
 */
function ws_share_link_note_plain_tags($body)
{
    return ws_note_export_tokens(str_replace("\r\n", "\n", (string) $body), ws_guest_refs(array((string) $body)));
}

/**
 * A shared note as Markdown (&raw=1): its title as the heading, then its
 * text with tags as plain names.
 *
 * @param array $note ws_notes row
 * @return string
 */
function ws_share_link_note_markdown($note)
{
    $title = trim((string) ($note['title'] ?? ''));
    $body = rtrim(ws_share_link_note_plain_tags((string) $note['body']));

    return (($title !== '') ? '# ' . preg_replace('/\s+/u', ' ', $title) . "\n\n" : '') . $body . "\n";
}

/**
 * Who made a link, as a row keeps it: an application when the call was made
 * with its key (app_id > 0), the person otherwise. Pure.
 *
 * @param int $user_id
 * @param int $app_id ws_api_message_writer()['app_id']
 * @return array kind (user | app), id
 */
function ws_share_link_maker($user_id, $app_id = 0)
{
    return ((int) $app_id > 0) ? array('kind' => 'app', 'id' => (int) $app_id) : array('kind' => 'user', 'id' => (int) $user_id);
}

/**
 * Is this the maker of the link? Pure.
 *
 * @param array $link  ws_share_links row
 * @param array $maker ws_share_link_maker()
 * @return bool
 */
function ws_share_link_made_by($link, $maker)
{
    return ((string) $link['created_by_kind'] === (string) $maker['kind']) && ((int) $link['created_by_id'] === (int) $maker['id'])
        && ((int) $maker['id'] > 0);
}

/**
 * The conversation's line when a link is made or taken back: "<who> shared
 * <item> by link until <date>" or "<who> stopped sharing <item>". Pure.
 *
 * @param bool   $made     made (true) or taken back
 * @param string $who      ws_share_link_who(): a person's tag, an agent's @handle
 * @param string $name     the item's name
 * @param int    $until_at the link's end
 * @return string
 */
function ws_share_link_line($made, $who, $name, $until_at)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

    if ($made) {
        return lang(array('string' => '{var:1} shared {var:2} by link until {var:3}',
            'vars' => array((string) $who, $name, date('d.m.Y H:i', (int) $until_at))));
    }

    return lang(array('string' => '{var:1} stopped sharing {var:2}', 'vars' => array((string) $who, $name)));
}

/**
 * The mark of an item a link is open to, as the screen draws it: the link,
 * its end and whether this reader may take it back. Pure.
 *
 * @param array $link       ws_share_links row
 * @param bool  $can_revoke ws_share_link_can_revoke()
 * @return array id, until_at, until, can_revoke
 */
function ws_share_link_item_mark($link, $can_revoke)
{
    return array(
        'id'         => (int) $link['id'],
        'until_at'   => (int) $link['expires_at'],
        'until'      => date('d.m.Y H:i', (int) $link['expires_at']),
        'can_revoke' => (bool) $can_revoke,
    );
}

/**
 * The icon of an item's kind, as the channel's Sharing lists its links. Pure.
 *
 * @param string $kind ws_share_links.kind
 * @return string
 */
function ws_share_link_kind_icon($kind)
{
    $icons = array('file' => 'bi-file-earmark', 'note' => 'bi-journal-text', 'table' => 'bi-table', 'list' => 'bi-list-check');

    return $icons[(string) $kind] ?? 'bi-link-45deg';
}

/**
 * How often a link was opened, in words. Pure but for lang().
 *
 * @param int $opens
 * @return string
 */
function ws_share_link_opened_words($opens)
{
    $opens = (int) $opens;

    if ($opens <= 0) {
        return lang('not opened yet');
    }

    return ($opens === 1) ? lang('opened once') : lang(array('string' => 'opened {var:1} times', 'vars' => $opens));
}

/**
 * One open link as the channel's Sharing lists it (the head's menu, Channel
 * info): the item's kind icon and name, and under it who made it, its end
 * and how often it was opened. Never the address. Pure but for lang().
 *
 * @param array  $link       ws_share_links row
 * @param string $name       the item's name
 * @param string $who        the maker's name, an agent by its @handle
 * @param bool   $can_revoke ws_share_link_can_revoke()
 * @return array id, message_id, kind, icon, name, line, until_at, until, can_revoke
 */
function ws_share_link_row($link, $name, $who, $can_revoke)
{
    $until = date('d.m.Y H:i', (int) $link['expires_at']);

    return array(
        'id'         => (int) $link['id'],
        'message_id' => (int) $link['message_id'],
        'kind'       => (string) $link['kind'],
        'icon'       => ws_share_link_kind_icon($link['kind']),
        'name'       => (string) $name,
        'line'       => implode(' · ', array_filter(array(
            (string) $who,
            lang(array('string' => 'until {var:1}', 'vars' => $until)),
            ws_share_link_opened_words($link['opens'] ?? 0),
        ), 'strlen')),
        'until_at'   => (int) $link['expires_at'],
        'until'      => $until,
        'can_revoke' => (bool) $can_revoke,
    );
}

/**
 * The cut-off of the sweep: a row whose end (its take-back, else its expiry)
 * lies before it is deleted. Pure.
 *
 * @param int $now
 * @return int
 */
function ws_share_links_sweep_before($now)
{
    return (int) $now - WS_SHARE_LINK_KEEP;
}

/**
 * Is an open of the link counted now: the first, or the first a minute after
 * the last one counted? Pure.
 *
 * @param array $link ws_share_links row
 * @param int   $now
 * @return bool
 */
function ws_share_link_open_counts($link, $now)
{
    $opened = (int) ($link['opened_at'] ?? 0);

    return ($opened <= 0) || ($opened <= (int) $now - WS_SHARE_LINK_OPEN_EVERY);
}

/**
 * How the address sends a file by its name: its type, shown in the page
 * (a picture, sound, video, PDF) or downloaded (anything else, and anything
 * a browser could run, as HTML or SVG), the rule the guest page keeps.
 * Pure.
 *
 * @param string $name the name the message shows
 * @return array mime, disposition
 */
function ws_share_link_file_headers($name)
{
    $type = ws_file_type((string) $name);

    return array(
        'mime'        => (string) $type['mime'],
        'disposition' => pg_http_content_disposition((string) $name, !empty($type['inline'])),
    );
}

/**
 * The words of the item's mark, its menu and the dialog
 * (assets/js/workspace.js).
 *
 * @return array key => text
 */
function ws_share_link_js_strings()
{
    return array(
        'sl_share'        => lang('Share by link…'),
        'sl_stop'         => lang('Stop sharing'),
        'sl_stop_ask'     => lang('The address stops working at once.'),
        'sl_share_table'  => lang('Share table by link…'),
        'sl_share_list'   => lang('Share checklist by link…'),
        'sl_stop_table'   => lang('Stop sharing the table'),
        'sl_stop_list'    => lang('Stop sharing the checklist'),
        'sl_until'        => ws_js_template('Shared by link until {var:1}', 1),
        'sl_ends'         => lang('Ends after'),
        'sl_day'          => lang('1 day'),
        'sl_days'         => ws_js_template('{var:1} days', 1),
        'sl_create'       => lang('Create'),
        'sl_shown_once'   => ws_js_template('Shown once. Anyone with this address can read this item until {var:1}.', 1),
        'sl_address'      => lang('Address'),
        'sl_copy'         => lang('Copy'),
        'sl_done'         => lang('Done'),
        // The end's choices and the one chosen first, not words.
        'sl_sharing'      => lang('Sharing'),
        'sl_this_channel' => lang('This channel'),
        'sl_not_outside'  => lang('Not shared outside'),
        'sl_by_link'      => lang('Shared by link'),
        'sl_nothing'      => lang('Nothing is shared outside this channel.'),
        'sl_day_choices'  => WS_SHARE_LINK_DAYS,
        'sl_day_default'  => (string) WS_SHARE_LINK_DEFAULT_DAYS,
    );
}

/* ---------------------------------------------------------------------------
   Rows
   --------------------------------------------------------------------------- */

/**
 * The address a token opens.
 *
 * @param string $token
 * @return string
 */
function ws_share_link_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_share.php?t=' . $token;
}

/**
 * A link by its id.
 *
 * @param int $id
 * @return array|null
 */
function ws_share_link($id)
{
    if (!ws_share_links_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_share_links WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The row a token opens, if any: looked up by the token's SHA-256 alone.
 *
 * @param string $token
 * @return array|null
 */
function ws_share_link_for_token($token)
{
    if (!ws_share_links_ready() || !ws_share_link_token_shape($token)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_share_links WHERE token_hash = '" . hash('sha256', (string) $token) . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The agent channel a message's link belongs to: its own channel, or for a
 * discussion the channel it is about. Null when neither is an agent channel.
 *
 * @param array|null $channel
 * @return array|null
 */
function ws_share_link_agent_channel($channel)
{
    if (!is_array($channel)) {
        return null;
    }

    if (ws_agent_channel_is($channel)) {
        return $channel;
    }

    $parent = function_exists('ws_thread_parent_channel') ? ws_thread_parent_channel($channel) : null;

    return ws_agent_channel_is($parent) ? $parent : null;
}

/**
 * Does the files row of a message's file exist?
 *
 * @param array|null $message
 * @return bool
 */
function ws_share_link_file_row($message)
{
    return is_array($message) && ((int) $message['file_id'] > 0)
        && ((int) db_value("SELECT COUNT(*) FROM files WHERE id = '" . (int) $message['file_id'] . "'") > 0);
}

/**
 * The note a message's card shows, when the note belongs to the message's
 * channel: the ws_notes row, or null.
 *
 * @param array|null $message
 * @param int        $note_id
 * @return array|null
 */
function ws_share_link_note_row($message, $note_id)
{
    if (!is_array($message) || ((int) $note_id <= 0) || !function_exists('ws_note_channel_owner_ready') || !ws_note_channel_owner_ready()) {
        return null;
    }

    $row = db_item("SELECT n.* FROM ws_notes n
        JOIN ws_note_shares s ON s.note_id = n.id AND s.message_id = '" . (int) $message['id'] . "'
        WHERE n.id = '" . (int) $note_id . "' AND n.owner_channel_id = '" . (int) $message['channel_id'] . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * What ws_share_link_item_in() is given for an item: for a file whether its
 * files row exists, for a note its row, for a table or a list its block.
 *
 * @param array|null $message
 * @param string     $kind
 * @param int        $item_id
 * @return mixed
 */
function ws_share_link_item_row($message, $kind, $item_id)
{
    if ((string) $kind === 'file') {
        return ws_share_link_file_row($message);
    }

    if (in_array((string) $kind, array('table', 'list'), true)) {
        return ws_share_link_text_row($message, (string) $kind, (int) $item_id);
    }

    return ((string) $kind === 'note') ? ws_share_link_note_row($message, (int) $item_id) : null;
}

/**
 * The name of an item that stands in its message.
 *
 * @param array  $message
 * @param string $kind
 * @param mixed  $item_row ws_share_link_item_row()
 * @return string
 */
function ws_share_link_item_name($message, $kind, $item_row)
{
    if (((string) $kind === 'note') && is_array($item_row)) {
        return ws_share_link_note_name($item_row);
    }

    if (in_array((string) $kind, array('table', 'list'), true) && is_array($item_row)) {
        return ws_share_link_text_name($item_row);
    }

    return (string) $message['file_name'];
}

/**
 * The state of a stored link, read from its channel, message and item now.
 *
 * @param array $link
 * @param int   $now
 * @return array state, channel, message, item (ws_share_link_item_row()), name
 */
function ws_share_link_read($link, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $message = ws_message((int) $link['message_id']);
    $message = is_array($message) ? $message : null;
    $channel = $message ? ws_channel((int) $message['channel_id']) : null;
    $channel = is_array($channel) ? $channel : null;
    $parent = ($channel && function_exists('ws_thread_parent_channel')) ? ws_thread_parent_channel($channel) : null;
    $in_past = $message && function_exists('ws_message_in_past') && ws_message_in_past($message);
    $row = ws_share_link_item_row($message, (string) $link['kind'], (int) $link['item_id']);
    $present = ws_share_link_item_in($message, (string) $link['kind'], (int) $link['item_id'], $row);

    return array(
        'state'   => ws_share_link_state($link, $now, $channel, $parent, $message, $in_past, $present),
        'channel' => $channel,
        'message' => $message,
        'item'    => $row,
        'name'    => $present ? ws_share_link_item_name($message, (string) $link['kind'], $row) : '',
    );
}

/**
 * Finds the link a token opens with its state, for the address: null for a
 * token of a wrong shape or one no row has.
 *
 * @param string $token
 * @return array|null link, state, channel, message
 */
function ws_share_link_find($token)
{
    $link = ws_share_link_for_token($token);

    if (!$link) {
        return null;
    }

    return array('link' => $link) + ws_share_link_read($link);
}

/**
 * The open link of an item, if any: not taken back, not ended. A table's or
 * a checklist's item_id is its number in the message, so the message
 * tells it apart.
 *
 * @param string $kind
 * @param int    $item_id
 * @param int    $now
 * @param int    $message_id
 * @return array|null
 */
function ws_share_link_open_for_item($kind, $item_id, $now, $message_id = 0)
{
    if (!ws_share_links_ready()) {
        return null;
    }

    $in_message = in_array((string) $kind, array('table', 'list'), true) ? " AND message_id = '" . (int) $message_id . "'" : '';
    $row = db_item("SELECT * FROM ws_share_links WHERE kind = '" . e((string) $kind) . "' AND item_id = '" . (int) $item_id . "'" . $in_message . "
        AND revoked_at = 0 AND expires_at > '" . (int) $now . "' ORDER BY expires_at DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * How many links of a channel are open now.
 *
 * @param int $channel_id
 * @param int $now
 * @return int
 */
function ws_share_links_open_count($channel_id, $now)
{
    if (!ws_share_links_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_share_links WHERE channel_id = '" . (int) $channel_id . "'
        AND revoked_at = 0 AND expires_at > '" . (int) $now . "'");
}

/**
 * The links of a channel, newest first: the open ones, or every row still
 * kept.
 *
 * @param int  $channel_id
 * @param bool $open_only
 * @return array[]
 */
function ws_share_links_of_channel($channel_id, $open_only = true)
{
    if (!ws_share_links_ready()) {
        return array();
    }

    $where = $open_only ? " AND revoked_at = 0 AND expires_at > '" . time() . "'" : '';

    return (array) db_items("SELECT * FROM ws_share_links WHERE channel_id = '" . (int) $channel_id . "'" . $where . "
        ORDER BY id DESC LIMIT " . (WS_SHARE_LINK_MAX_OPEN * 2));
}

/**
 * Does the channel allow this application an agent: an allowed row of an
 * application agent of it?
 *
 * @param int $channel_id
 * @param int $app_id
 * @return bool
 */
function ws_share_link_app_allowed($channel_id, $app_id)
{
    if (((int) $app_id <= 0) || ((int) $channel_id <= 0) || !function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return false;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_channel_agents ca
        JOIN ws_agents a ON a.id = ca.agent_id
        WHERE ca.channel_id = '" . (int) $channel_id . "' AND ca.allowed = 1
            AND a.kind = 'app' AND a.app_id = '" . (int) $app_id . "'") > 0;
}

/**
 * The checks before a link is made, shared by the panel, the API and its dry
 * run. A refusal names the field it is about.
 *
 * @param array      $viewer  the person (the one an application acts for)
 * @param array|null $message ws_messages row
 * @param string     $kind
 * @param int        $item_id
 * @param mixed      $days    as given (ws_share_link_days_clean())
 * @param array      $maker   ws_share_link_maker()
 * @param int|null   $now
 * @return array ok, error, field, status (403 | 422 | 503), channel, days, expires_at, name
 */
function ws_share_link_make_check($viewer, $message, $kind, $item_id, $days, $maker, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $fail = function ($error, $field = '', $status = 422) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'status' => $status, 'channel' => null, 'days' => 0, 'expires_at' => 0, 'name' => '');
    };

    if (!ws_share_links_ready()) {
        return $fail(lang('The database has to be updated first.'), '', 503);
    }

    if (!is_array($message) || ((int) $message['deleted_at'] > 0)) {
        return $fail(lang('The message was not found.'), 'id');
    }

    $channel = ws_channel((int) $message['channel_id']);
    $agent_channel = ws_share_link_agent_channel($channel);

    if (!$agent_channel) {
        return $fail(lang('Only an item of an agent channel can be shared by link.'), 'id');
    }

    if (!ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'), 'id', 403);
    }

    if (($maker['kind'] === 'app') && !ws_share_link_app_allowed((int) $agent_channel['id'], (int) $maker['id'])) {
        return $fail(lang('Only an agent the channel allows can share its items by link.'), 'id', 403);
    }

    if (function_exists('ws_message_in_past') && ws_message_in_past($message)) {
        return $fail(lang('This message is in an earlier version of the conversation, which is kept as it was.'), 'id');
    }

    if (!in_array((string) $kind, ws_share_link_kinds(), true)) {
        return $fail(lang('This kind of item cannot be shared by link yet.'), 'kind');
    }

    $item_row = ws_share_link_item_row($message, (string) $kind, (int) $item_id);

    if (!ws_share_link_item_in($message, (string) $kind, (int) $item_id, $item_row)) {
        return $fail(lang('The item is not in this message.'), 'item_id');
    }

    $days = ws_share_link_days_clean($days);

    if ($days <= 0) {
        return $fail(lang(array('string' => 'A link lasts {var:1} days.', 'vars' => str_replace(',', ', ', WS_SHARE_LINK_DAYS))), 'days');
    }

    $open = ws_share_link_open_for_item((string) $kind, (int) $item_id, $now, (int) $message['id']);

    if ($open) {
        return $fail(lang(array('string' => 'This item is already shared by link until {var:1}.', 'vars' => date('d.m.Y H:i', (int) $open['expires_at']))), 'item_id');
    }

    if (ws_share_links_open_count((int) $agent_channel['id'], $now) >= WS_SHARE_LINK_MAX_OPEN) {
        return $fail(lang(array('string' => 'A channel holds at most {var:1} open links.', 'vars' => WS_SHARE_LINK_MAX_OPEN)), 'item_id');
    }

    return array(
        'ok'         => true,
        'error'      => '',
        'field'      => '',
        'status'     => 200,
        'channel'    => $agent_channel,
        'days'       => $days,
        'expires_at' => ws_share_link_expiry($now, $days),
        'name'       => ws_share_link_item_name($message, (string) $kind, $item_row),
    );
}

/**
 * Makes a link after ws_share_link_make_check(). The token leaves here once,
 * in the address; only its SHA-256 is kept.
 *
 * @param array $message
 * @param array $checked ws_share_link_make_check()
 * @param string $kind
 * @param int   $item_id
 * @param array $maker
 * @param int|null $now
 * @return array ok, error, link (the row), url
 */
function ws_share_link_make($message, $checked, $kind, $item_id, $maker, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $token = pg_short_link_new_token();

    db("INSERT INTO ws_share_links (token_hash, channel_id, message_id, kind, item_id, created_by_kind, created_by_id, created_at, expires_at)
        VALUES ('" . hash('sha256', $token) . "', '" . (int) $checked['channel']['id'] . "', '" . (int) $message['id'] . "',
            '" . e((string) $kind) . "', '" . (int) $item_id . "', '" . (($maker['kind'] === 'app') ? 'app' : 'user') . "',
            '" . (int) $maker['id'] . "', '" . $now . "', '" . (int) $checked['expires_at'] . "')");

    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return array('ok' => false, 'error' => lang('The link could not be made.'), 'link' => null, 'url' => '');
    }

    log_activity(lang(array('string' => 'a workspace item was shared by link until {var:1}: {var:2}', 'vars' => array(date('d.m.Y H:i', (int) $checked['expires_at']), (string) $checked['name']))));

    ws_share_link_note($message, $maker, true, (int) $checked['expires_at'], (int) $checked['channel']['id'], (string) $checked['name']);

    return array('ok' => true, 'error' => '', 'link' => ws_share_link($id), 'url' => ws_share_link_url($token));
}

/**
 * May this person (or the application acting for them) take the link back?
 * Its maker, and whoever manages the channel.
 *
 * @param array      $viewer
 * @param array      $link
 * @param array|null $channel the link's channel
 * @param array      $maker   ws_share_link_maker() of the caller
 * @return bool
 */
function ws_share_link_can_revoke($viewer, $link, $channel, $maker)
{
    if (!is_array($channel) || !ws_can_read_channel($viewer, $channel)) {
        return false;
    }

    if (ws_share_link_made_by($link, $maker)) {
        return true;
    }

    // An application takes back only its own links: managing the channel is
    // the person's right, not their key's.
    return ($maker['kind'] === 'user') && ws_can_manage_channel($viewer, $channel);
}

/**
 * Takes a link back: the address stops working at once. A link already taken
 * back stays as it was.
 *
 * @param array      $link
 * @param int|null   $now
 * @param array|null $by ws_share_link_maker() of who took it back: the
 *                       conversation's line names them; none writes no line
 * @return bool whether this call took it back
 */
function ws_share_link_revoke($link, $now = null, $by = null)
{
    $now = ($now === null) ? time() : (int) $now;

    // The state before it is taken back: only a link still open gets the
    // conversation's line.
    $read = is_array($by) ? ws_share_link_read($link, $now) : null;

    db("UPDATE ws_share_links SET revoked_at = '" . $now . "' WHERE id = '" . (int) $link['id'] . "' AND revoked_at = 0");

    $done = mysqli_affected_rows(db::$con) > 0;

    if ($done) {
        log_activity(lang(array('string' => 'a workspace share link was taken back: {var:1}', 'vars' => '#' . (int) $link['id'])));

        if (is_array($read)) {
            $line = ws_share_link_revoke_line($read['state'], ws_share_link_who($by, (int) $link['channel_id']), (string) $read['name'], (int) $link['expires_at']);

            if ($line !== '') {
                ws_message_system((int) $read['message']['channel_id'], $line);

                if (function_exists('ws_message_touch')) {
                    ws_message_touch((int) $read['message']['id']);
                }
            }
        }
    }

    return $done;
}

/**
 * The conversation's line of a link taken back: only one that was open, as
 * the item's mark and the channel's Sharing showed it. A link that had ended
 * or closed already (expired, its item, message or channel gone) writes
 * none. Pure but for lang().
 *
 * @param string $state    ws_share_link_state() before it was taken back
 * @param string $who      ws_share_link_who()
 * @param string $name     the item's name
 * @param int    $until_at the link's end
 * @return string '' for no line
 */
function ws_share_link_revoke_line($state, $who, $name, $until_at)
{
    return ((string) $state === 'open') ? ws_share_link_line(false, $who, $name, $until_at) : '';
}

/**
 * Who a line names: a person by their tag, an application by the @handle of
 * its agent in the channel (any agent of it, else its name).
 *
 * @param array $maker      ws_share_link_maker()
 * @param int   $channel_id the agent channel
 * @return string
 */
function ws_share_link_who($maker, $channel_id)
{
    if ((string) $maker['kind'] !== 'app') {
        return '<@user:' . (int) $maker['id'] . '>';
    }

    $handle = '';

    if (function_exists('ws_agents_ready') && ws_agents_ready()) {
        $handle = (string) db_value("SELECT a.handle FROM ws_agents a
            LEFT JOIN ws_channel_agents ca ON ca.agent_id = a.id AND ca.channel_id = '" . (int) $channel_id . "'
            WHERE a.kind = 'app' AND a.app_id = '" . (int) $maker['id'] . "'
            ORDER BY (ca.agent_id IS NULL), a.id LIMIT 1");
    }

    if ($handle !== '') {
        return '@' . $handle;
    }

    $app = function_exists('ws_app_sender') ? ws_app_sender((int) $maker['id']) : null;

    return (is_array($app) && ((string) ($app['name'] ?? '') !== '')) ? (string) $app['name'] : '#' . (int) $maker['id'];
}

/**
 * Writes the conversation's line of a link made or taken back, in the
 * message's own channel, and makes open screens read the message again for
 * its mark. A system line: no agent is woken, no bell line written.
 *
 * @param array $message    ws_messages row
 * @param array $maker      ws_share_link_maker() of who did it
 * @param bool  $made
 * @param int    $until_at   the link's end
 * @param int    $channel_id the agent channel
 * @param string $name       the item's name (ws_share_link_item_name())
 */
function ws_share_link_note($message, $maker, $made, $until_at, $channel_id, $name)
{
    ws_message_system((int) $message['channel_id'], ws_share_link_line($made, ws_share_link_who($maker, $channel_id),
        (string) $name, $until_at));

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $message['id']);
    }
}

/**
 * The share marks of a page of messages, read in one query: per message
 * with a file or with the card of a note of the channel's own, the kind of
 * item, the open link (ws_share_link_item_mark()) or null, and whether this
 * reader may make one (can_make). Messages outside an agent channel get
 * nothing.
 *
 * @param array   $viewer
 * @param array[] $rows     ws_messages rows
 * @param array   $can_post channel id => may post
 * @return array message id => kind, open, can_make
 */
function ws_share_links_map($viewer, $rows, $can_post)
{
    if (!ws_share_links_ready()) {
        return array();
    }

    $agent = array();
    $live = array();

    foreach ((array) $rows as $row) {
        $channel_id = (int) $row['channel_id'];

        if ((int) $row['deleted_at'] > 0) {
            continue;
        }

        if (!array_key_exists($channel_id, $agent)) {
            $agent[$channel_id] = ws_share_link_agent_channel(ws_channel($channel_id));
        }

        if ($agent[$channel_id]) {
            $live[(int) $row['id']] = $row;
        }
    }

    if (!$live) {
        return array();
    }

    // The item of each message: its file, or the note of the channel's own
    // whose card it is (a person's note shown here is not the channel's).
    $items = array();

    foreach ($live as $id => $row) {
        if ((int) $row['file_id'] > 0) {
            $items[$id] = array('kind' => 'file', 'item_id' => (int) $row['file_id']);
        }
    }

    if (function_exists('ws_note_channel_owner_ready') && ws_note_channel_owner_ready()) {
        foreach ((array) db_items("SELECT s.message_id, n.id, n.owner_channel_id FROM ws_note_shares s
            JOIN ws_notes n ON n.id = s.note_id
            WHERE s.message_id IN (" . implode(',', array_keys($live)) . ") AND n.owner_channel_id > 0") as $card) {
            $id = (int) $card['message_id'];

            if (!isset($items[$id]) && ((int) $card['owner_channel_id'] === (int) $live[$id]['channel_id'])) {
                $items[$id] = array('kind' => 'note', 'item_id' => (int) $card['id']);
            }
        }
    }

    if (!$items) {
        return array();
    }

    $now = time();
    $open = array();

    foreach ((array) db_items("SELECT * FROM ws_share_links WHERE message_id IN (" . implode(',', array_keys($items)) . ")
        AND kind IN ('file', 'note') AND revoked_at = 0 AND expires_at > '" . $now . "'") as $link) {
        $open[(int) $link['message_id']] = $link;
    }

    $maker = ws_share_link_maker((int) $viewer['id']);
    $out = array();

    foreach ($items as $id => $item) {
        $row = $live[$id];
        $channel = $agent[(int) $row['channel_id']];
        $link = $open[$id] ?? null;

        // A link stays to the item it was made to; a file replaced since
        // has none.
        if ($link && (((string) $link['kind'] !== $item['kind']) || ((int) $link['item_id'] !== $item['item_id']))) {
            $link = null;
        }

        $past = function_exists('ws_message_in_past') && ws_message_in_past($row);

        $out[$id] = array(
            'kind'     => $item['kind'],
            'item_id'  => $item['item_id'],
            'open'     => ($link && !$past) ? ws_share_link_item_mark($link, ws_share_link_can_revoke($viewer, $link, $channel, $maker)) : null,
            'can_make' => !$link && !$past && !empty($can_post[(int) $row['channel_id']]),
        );
    }

    return $out;
}

/**
 * The name of who made a link, as the channel's Sharing shows it: a
 * person's name, an application by the @handle of its agent.
 *
 * @param array $link       ws_share_links row
 * @param int   $channel_id the agent channel
 * @return string
 */
function ws_share_link_maker_name($link, $channel_id)
{
    $maker = array('kind' => (string) $link['created_by_kind'], 'id' => (int) $link['created_by_id']);

    if ($maker['kind'] === 'app') {
        return ws_share_link_who($maker, $channel_id);
    }

    return (string) ws_person_name($maker['id']);
}

/**
 * The open links of an agent channel for its Sharing (the head's menu,
 * Channel info), newest first: only links whose item still stands
 * (ws_share_link_state() open). Null outside an agent channel, where no
 * link can be made; a discussion lists none of its own.
 *
 * @param array $viewer
 * @param array $channel ws_channels row
 * @return array[]|null ws_share_link_row()
 */
function ws_channel_share_links($viewer, $channel)
{
    if (!ws_share_links_ready() || !is_array($channel) || ((string) $channel['kind'] === 'thread') || !ws_agent_channel_is($channel)) {
        return null;
    }

    if ((int) $channel['archived_at'] > 0) {
        return array();
    }

    $links = ws_share_links_of_channel((int) $channel['id'], true);

    if (!$links) {
        return array();
    }

    $message_ids = array_unique(array_map(function ($link) { return (int) $link['message_id']; }, $links));
    $messages = array();

    foreach ((array) db_items("SELECT * FROM ws_messages WHERE id IN (" . implode(',', $message_ids) . ")") as $row) {
        $messages[(int) $row['id']] = $row;
    }

    $file_ids = array_filter(array_map(function ($row) { return (int) $row['file_id']; }, $messages));
    $files = $file_ids ? array_flip(array_map('intval', (array) db_values("SELECT id FROM files WHERE id IN (" . implode(',', array_unique($file_ids)) . ")"))) : array();
    $note_ids = array();

    foreach ($links as $link) {
        if ((string) $link['kind'] === 'note') {
            $note_ids[] = (int) $link['item_id'];
        }
    }

    // The notes the links are to, each with the message whose card it is.
    $notes = array();

    if ($note_ids && function_exists('ws_note_channel_owner_ready') && ws_note_channel_owner_ready()) {
        foreach ((array) db_items("SELECT n.*, s.message_id AS card_message_id FROM ws_notes n
            JOIN ws_note_shares s ON s.note_id = n.id
            WHERE n.id IN (" . implode(',', array_unique($note_ids)) . ") AND s.message_id IN (" . implode(',', $message_ids) . ")") as $note) {
            $notes[(int) $note['id'] . ':' . (int) $note['card_message_id']] = $note;
        }
    }
    $channels = array((int) $channel['id'] => $channel);
    $maker = ws_share_link_maker((int) $viewer['id']);
    $names = array();
    $now = time();
    $out = array();

    foreach ($links as $link) {
        $message = $messages[(int) $link['message_id']] ?? null;

        if (!$message) {
            continue;
        }

        // A link of a message in a discussion of this channel: the
        // discussion is its channel, this one its parent.
        $own_id = (int) $message['channel_id'];

        if (!array_key_exists($own_id, $channels)) {
            $own = ws_channel($own_id);
            $channels[$own_id] = is_array($own) ? $own : null;
        }

        $own = $channels[$own_id];
        $parent = ($own_id === (int) $channel['id']) ? null : $channel;
        $in_past = function_exists('ws_message_in_past') && ws_message_in_past($message);
        if (in_array((string) $link['kind'], array('table', 'list'), true)) {
            $row = ws_share_link_text_row($message, (string) $link['kind'], (int) $link['item_id']);
        } else {
            $row = ((string) $link['kind'] === 'note')
                ? ($notes[(int) $link['item_id'] . ':' . (int) $message['id']] ?? null)
                : isset($files[(int) $message['file_id']]);
        }
        $present = ws_share_link_item_in($message, (string) $link['kind'], (int) $link['item_id'], $row);

        if (ws_share_link_state($link, $now, $own, $parent, $message, $in_past, $present) !== 'open') {
            continue;
        }

        $key = (string) $link['created_by_kind'] . ':' . (int) $link['created_by_id'];

        if (!isset($names[$key])) {
            $names[$key] = ws_share_link_maker_name($link, (int) $channel['id']);
        }

        $out[] = ws_share_link_row($link, ws_share_link_item_name($message, (string) $link['kind'], $row), $names[$key], ws_share_link_can_revoke($viewer, $link, $channel, $maker));
    }

    return $out;
}

/**
 * Deletes the rows whose link ended WS_SHARE_LINK_KEEP ago, and those of a
 * channel that is gone. Run by workspace_recurring_job.php.
 *
 * @param int $limit
 * @return int how many
 */
function ws_share_links_sweep($limit = 500)
{
    if (!ws_share_links_ready()) {
        return 0;
    }

    $before = ws_share_links_sweep_before(time());

    $ids = array_map('intval', (array) db_values("SELECT l.id FROM ws_share_links l
        LEFT JOIN ws_channels c ON c.id = l.channel_id
        WHERE (l.revoked_at > 0 AND l.revoked_at < '" . $before . "') OR l.expires_at < '" . $before . "' OR c.id IS NULL
        LIMIT " . max(1, (int) $limit)));

    if ($ids) {
        db("DELETE FROM ws_share_links WHERE id IN (" . implode(',', $ids) . ")");
    }

    return count($ids);
}


/**
 * Counts an open of a link: opened_at and one more open, once a minute a
 * link at most. The minute is asked in the UPDATE itself, so two opens at
 * once count one.
 *
 * @param array $link
 * @param int|null $now
 * @return bool whether this open was counted
 */
function ws_share_link_count_open($link, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if (!ws_share_link_open_counts($link, $now)) {
        return false;
    }

    db("UPDATE ws_share_links SET opened_at = '" . $now . "', opens = opens + 1
        WHERE id = '" . (int) $link['id'] . "' AND opened_at <= '" . ($now - WS_SHARE_LINK_OPEN_EVERY) . "'");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * What the address answers for a token: the file or the note of an open
 * link, or null for every fault (a bad shape, unknown, ended, taken back,
 * closed, a kind the address does not answer yet, the bytes gone), which is
 * one 404. A token no row has counts against the address, as a wrong guest
 * or approval link does; a link that ended is nobody's guess.
 *
 * @param mixed $token
 * @return array|null kind; a file: link, path, name, mime, disposition; a
 *                    note: link, note, name; a table or a list: link,
 *                    message, block, name
 */
function ws_share_link_answer($token)
{
    if (!ws_enabled() || !ws_share_links_ready()) {
        return null;
    }

    $token = is_string($token) ? $token : '';
    $found = ws_share_link_find($token);

    if (!$found) {
        if ($token !== '') {
            ws_guest_offence();
        }

        return null;
    }

    $link = $found['link'];
    $message = $found['message'];

    if (($found['state'] !== 'open') || !is_array($message)) {
        return null;
    }

    if ((string) $link['kind'] === 'note') {
        return is_array($found['item']) ? array('kind' => 'note', 'link' => $link, 'note' => $found['item'], 'name' => (string) $found['name']) : null;
    }

    if (in_array((string) $link['kind'], array('table', 'list'), true)) {
        return is_array($found['item'])
            ? array('kind' => (string) $link['kind'], 'link' => $link, 'message' => $message, 'block' => $found['item'], 'name' => (string) $found['name'])
            : null;
    }

    if ((string) $link['kind'] !== 'file') {
        return null;
    }

    // MySQL 8 reserves STORED: the stored name is read as stored_name.
    $stored = (string) db_value("SELECT name AS stored_name FROM files WHERE id = '" . (int) $message['file_id'] . "'");
    $name = (string) $message['file_name'];

    if (($stored === '') || pg_file_withheld($stored) || pg_file_withheld($name)) {
        return null;
    }

    // The local file, or a copy of one moved to the object store: the
    // store's address is never shown outside.
    $path = pg_file_reader_path(pg_basename($stored));

    if (($path === '') || !is_file($path)) {
        return null;
    }

    return array('kind' => 'file', 'link' => $link, 'path' => $path, 'name' => $name) + ws_share_link_file_headers($name);
}

/**
 * The page of a shared note: read only, drawn as the channel draws it with
 * tags and record links as plain names (ws_guest_refs()), the site's name
 * above it and the link's end under it. Nothing on it opens the panel.
 *
 * @param array $answer ws_share_link_answer() of a note
 * @return string HTML
 */
function ws_share_link_note_page($answer)
{
    $body = (string) $answer['note']['body'];

    return ws_share_link_page($answer, ws_render_body($body, ws_guest_refs(array($body)), null, false, null, null, true));
}

/**
 * A shared item's read-only page around its drawn HTML: the site's name and
 * the item's name above it, the link's end under it.
 *
 * @param array  $answer ws_share_link_answer()
 * @param string $html   the item as the channel draws it
 * @return string HTML
 */
function ws_share_link_page($answer, $html)
{
    $software = PATH . SOFTWARE_DIRECTORY;
    $version = function ($file) {
        $path = PG_FUNCTIONS_DIR . $file;

        return is_file($path) ? (string) filemtime($path) : '0';
    };
    $site = (string) ORGANIZATION_NAME;
    $name = (string) $answer['name'];
    $until = lang(array('string' => 'Shared by link until {var:1}', 'vars' => date('d.m.Y H:i', (int) $answer['link']['expires_at'])));

    return '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h($name . (($site !== '') ? ' · ' . $site : '')) . '</title>
<link rel="stylesheet" href="' . $software . '/assets/lib/bootstrap-6/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-6/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/css/workspace_guest.css?v=' . $version('/assets/css/workspace_guest.css') . '">
</head>
<body class="pg-guest">
<main class="pg-guest-shared">
    <header class="pg-guest-shared-head">
        <div class="pg-guest-site">' . h($site) . '</div>
        <h1 class="pg-guest-title">' . h($name) . '</h1>
    </header>
    <article class="pg-guest-body pg-guest-shared-body">' . $html . '</article>
    <footer class="pg-guest-shared-foot">
        <i class="bi bi-link-45deg" aria-hidden="true"></i> ' . h($until) . ' · ' . h(lang('Read only')) . '
    </footer>
</main>
</body>
</html>';
}

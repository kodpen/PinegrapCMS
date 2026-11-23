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
 * The kinds of item a link can be made to now. The address answers these
 * only; a note, a table and a list join as their pages are built. Pure.
 *
 * @return string[]
 */
function ws_share_link_kinds()
{
    return array('file');
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
 * still in the file manager's table. Pure.
 *
 * @param array|null $message ws_messages row
 * @param string     $kind
 * @param int        $item_id
 * @param bool       $file_row the files row of the message's file exists
 * @return bool
 */
function ws_share_link_item_in($message, $kind, $item_id, $file_row)
{
    if (!is_array($message) || ((int) $item_id <= 0)) {
        return false;
    }

    if ((string) $kind === 'file') {
        return ((int) $message['file_id'] === (int) $item_id) && $file_row;
    }

    return false;
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
 * The state of a stored link, read from its channel, message and item now.
 *
 * @param array $link
 * @param int   $now
 * @return array state, channel, message
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
    $present = ws_share_link_item_in($message, (string) $link['kind'], (int) $link['item_id'],
        ((string) $link['kind'] === 'file') && ws_share_link_file_row($message));

    return array(
        'state'   => ws_share_link_state($link, $now, $channel, $parent, $message, $in_past, $present),
        'channel' => $channel,
        'message' => $message,
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
 * The open link of an item, if any: not taken back, not ended.
 *
 * @param string $kind
 * @param int    $item_id
 * @param int    $now
 * @return array|null
 */
function ws_share_link_open_for_item($kind, $item_id, $now)
{
    if (!ws_share_links_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_share_links WHERE kind = '" . e((string) $kind) . "' AND item_id = '" . (int) $item_id . "'
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

    $file_row = ((string) $kind === 'file') && ws_share_link_file_row($message);

    if (!ws_share_link_item_in($message, (string) $kind, (int) $item_id, $file_row)) {
        return $fail(lang('The item is not in this message.'), 'item_id');
    }

    $days = ws_share_link_days_clean($days);

    if ($days <= 0) {
        return $fail(lang(array('string' => 'A link lasts {var:1} days.', 'vars' => str_replace(',', ', ', WS_SHARE_LINK_DAYS))), 'days');
    }

    $open = ws_share_link_open_for_item((string) $kind, (int) $item_id, $now);

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
        'name'       => (string) $message['file_name'],
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
 * @param array $link
 * @param int|null $now
 * @return bool whether this call took it back
 */
function ws_share_link_revoke($link, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    db("UPDATE ws_share_links SET revoked_at = '" . $now . "' WHERE id = '" . (int) $link['id'] . "' AND revoked_at = 0");

    $done = mysqli_affected_rows(db::$con) > 0;

    if ($done) {
        log_activity(lang(array('string' => 'a workspace share link was taken back: {var:1}', 'vars' => '#' . (int) $link['id'])));
    }

    return $done;
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

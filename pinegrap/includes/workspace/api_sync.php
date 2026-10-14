<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - one call that keeps an open app up to date (GET /workspace/sync).
 *
 * The panel's own screen asks for news every few seconds (ws_sync in
 * actions.php); an app on a phone or a computer does the same here. One
 * answer carries the sidebar - the channels and the discussions the person is
 * in, each only when something in it changed - the inbox count, and for the
 * channel open on the screen the messages written since the previous call and
 * the ones already sent that changed. Both read the open channel through
 * ws_channel_news().
 *
 * The cursor carries the state the previous answer left the app in, so an
 * answer to a call after which nothing changed is the same answer, byte for
 * byte, and the API's ETag turns it into a 304 with no body. That keeps a
 * poll every few seconds cheap without a connection held open, which on
 * shared hosting and IIS would hold a PHP worker for each open app.
 *
 * A person's own app also gets what the panel's channel screen shows around
 * the messages - the message pinned to the top, the marks beside the tabs and
 * a mark of the board - each time it changed (ws_api_sync_channel_state()),
 * and every caller allowed to read the person's bell gets its count.
 *
 * Pinegrap AI and an MCP server's own agent work only while somebody carries
 * them on. While a request to one waits in the open channel, a person's app
 * does it here as the panel's screen does (ws_api_sync_waiting()): right after
 * the answer where the server can send it first, otherwise in the call, one
 * step at a time; a call that finds the work under way elsewhere returns at
 * once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The most new messages and changed messages of the open channel one answer
 * carries; ws_messages_since() and ws_messages_touched() read no more.
 */
if (!defined('WS_API_SYNC_MAX')) {
    define('WS_API_SYNC_MAX', 200);
}

/**
 * How many messages a channel opened without any held starts with.
 */
if (!defined('WS_API_SYNC_FIRST_PAGE')) {
    define('WS_API_SYNC_FIRST_PAGE', 50);
}

/**
 * The state an answer left the app in, as the cursor it hands back: the
 * moment of the call (changed messages are looked for from there), the
 * channel open then and the newest message of it sent, a mark of the
 * channel list and of the discussion list as they were sent, and a mark of
 * the open channel's state as it was sent. The last is left out while there
 * is none, so the cursor of a call that carries no channel state is written
 * as it was before there was one.
 *
 * @param array $mark t, c, m, ch, th, p
 * @return string
 */
function ws_api_sync_cursor_write($mark)
{
    $packed = array(
        't'  => (int) $mark['t'],
        'c'  => (int) $mark['c'],
        'm'  => (int) $mark['m'],
        'ch' => (string) $mark['ch'],
        'th' => (string) $mark['th'],
    );

    if ((string) ($mark['p'] ?? '') !== '') {
        $packed['p'] = (string) $mark['p'];
    }

    return rtrim(strtr(base64_encode(json_encode($packed)), '+/', '-_'), '=');
}

/**
 * A cursor ws_api_sync_cursor_write() made, read back; null for anything
 * else. Nothing in it opens a door - the channel is checked again, the
 * message id only bounds a read in it - so it is not signed. A cursor
 * without a channel state mark, an app's from before there was one among
 * them, reads with an empty one, so the state is sent once.
 *
 * @param string $cursor
 * @return array|null t, c, m, ch, th, p
 */
function ws_api_sync_cursor_read($cursor)
{
    $cursor = trim((string) $cursor);

    if (($cursor === '') || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) {
        return null;
    }

    $padded = strtr($cursor, '-_', '+/');

    if (strlen($padded) % 4 > 0) {
        $padded .= str_repeat('=', 4 - (strlen($padded) % 4));
    }

    $decoded = base64_decode($padded, true);
    $mark = ($decoded !== false) ? json_decode($decoded, true) : null;

    if (!is_array($mark)) {
        return null;
    }

    foreach (array('t', 'c', 'm') as $key) {
        if (!isset($mark[$key]) || !is_int($mark[$key]) || ($mark[$key] < 0)) {
            return null;
        }
    }

    foreach (array('ch', 'th') as $key) {
        if (!isset($mark[$key]) || !is_string($mark[$key]) || !preg_match('/^[0-9a-f]{8}$/', $mark[$key])) {
            return null;
        }
    }

    if (isset($mark['p']) && (!is_string($mark['p']) || !preg_match('/^[0-9a-f]{8}$/', $mark['p']))) {
        return null;
    }

    return array('t' => $mark['t'], 'c' => $mark['c'], 'm' => $mark['m'], 'ch' => $mark['ch'], 'th' => $mark['th'], 'p' => (string) ($mark['p'] ?? ''));
}

/**
 * A short mark of a list as it is sent: the same list, the same mark.
 *
 * @param array $list
 * @return string eight hex digits
 */
function ws_api_sync_mark($list)
{
    return sprintf('%08x', crc32((string) json_encode($list)));
}

/**
 * One open discussion the person is in, as the sidebar lists it
 * (ws_threads_for()).
 *
 * @param array $row
 * @return array
 */
function ws_api_sync_thread_present($row)
{
    return array(
        'id'              => (int) $row['id'],
        'title'           => (string) $row['title'],
        'channel_id'      => (int) $row['channel_id'],
        'message_id'      => (int) $row['message_id'],
        'unread'          => (int) $row['unread'],
        'last_message_at' => api_time($row['last_message_at']),
    );
}

// What ws_api_sync_thread_present() returns.
function ws_api_sync_thread_schema()
{
    return array(
        'id'              => 'integer',
        'title'           => 'string',
        'channel_id'      => 'integer',
        'message_id'      => 'integer',
        'unread'          => 'integer',
        'last_message_at' => 'string?',
    );
}

/**
 * What the panel's channel screen shows around the messages of the open
 * channel, read where the panel's ws_sync reads it: the message pinned to the
 * top (pins.php), the marks beside the tabs (ws_channel_tab_counts()) and a
 * mark that moves whenever a card of the channel's board would
 * (ws_channel_board_stamp()). A discussion has no tabs and no board of its
 * own, and a guest's room no board, as on the panel.
 *
 * @param array $viewer
 * @param array $channel
 * @return array channel_id, pin, tab_counts, board
 */
function ws_api_sync_channel_state($viewer, $channel)
{
    $pin = null;
    $message = ws_channel_pinned_message($channel);

    if ($message) {
        $presented = ws_api_messages_present($viewer, array($message));

        $pin = array(
            'message'   => $presented[0],
            'pinned_by' => ws_api_person_ref($channel['pinned_by'] ?? 0),
            'pinned_at' => ((int) ($channel['pinned_at'] ?? 0) > 0) ? (int) $channel['pinned_at'] : 0,
            'hidden'    => ws_channel_pin_hidden(ws_channel_membership($channel['id'], $viewer['id']), $message),
        );
    }

    return array(
        'channel_id' => (int) $channel['id'],
        'pin'        => $pin,
        'tab_counts' => ws_channel_is_thread($channel) ? null : ws_channel_tab_counts($channel),
        'board'      => ws_channel_board_available($channel) ? ws_channel_board_stamp($channel) : null,
    );
}

/**
 * The open channel's state as the sync answers with it.
 *
 * @param array $state ws_api_sync_channel_state()
 * @return array
 */
function ws_api_sync_channel_state_present($state)
{
    return array(
        'channel_id' => (int) $state['channel_id'],
        'pin'        => ($state['pin'] !== null) ? ws_api_sync_pin_present($state['pin']) : null,
        'tab_counts' => ($state['tab_counts'] !== null) ? ws_api_sync_tab_counts_present($state['tab_counts']) : null,
        'board'      => ($state['board'] !== null) ? (string) $state['board'] : null,
    );
}

// What ws_api_sync_channel_state_present() returns.
function ws_api_sync_channel_state_schema()
{
    return array(
        'channel_id' => 'integer',
        'pin'        => 'WorkspaceSyncPin?',
        'tab_counts' => 'WorkspaceSyncTabCounts?',
        'board'      => 'string?',
    );
}

function ws_api_sync_pin_present($pin)
{
    return array(
        'message'   => $pin['message'],
        'pinned_by' => $pin['pinned_by'],
        'pinned_at' => ((int) $pin['pinned_at'] > 0) ? api_time($pin['pinned_at']) : null,
        'hidden'    => (bool) $pin['hidden'],
    );
}

// What ws_api_sync_pin_present() returns.
function ws_api_sync_pin_schema()
{
    return array(
        'message'   => 'WorkspaceMessage',
        'pinned_by' => array('user_id' => 'integer', 'name' => 'string'),
        'pinned_at' => 'string?',
        'hidden'    => 'boolean',
    );
}

function ws_api_sync_tab_counts_present($counts)
{
    return array(
        'decisions' => (int) $counts['decisions'],
        'tasks'     => (int) $counts['tasks'],
        'summary'   => (bool) $counts['summary'],
    );
}

// What ws_api_sync_tab_counts_present() returns.
function ws_api_sync_tab_counts_schema()
{
    return array(
        'decisions' => 'integer',
        'tasks'     => 'integer',
        'summary'   => 'boolean',
    );
}

/**
 * The number on the person's bell, for a caller allowed to read it: the
 * Own account permission (account:read), which a person's own sign-in always
 * holds and an application only when the operator gave it. null otherwise,
 * as GET /auth/me leaves its unread out.
 *
 * @param array $owner
 * @param array $scopes the caller's, api_current_scopes()
 * @return int|null
 */
function ws_api_sync_bell($owner, $scopes)
{
    if (!api_has_scope($scopes, 'account:read') || !function_exists('api_notifications_unread_count')) {
        return null;
    }

    return api_notifications_unread_count($owner);
}

function ws_api_sync_present($sync)
{
    return array(
        'cursor'       => (string) $sync['cursor'],
        'channels'     => ($sync['channels'] !== null) ? array_values($sync['channels']) : null,
        'threads'      => ($sync['threads'] !== null) ? array_values($sync['threads']) : null,
        'inbox_unread' => (int) $sync['inbox_unread'],
        'notifications_unread' => ($sync['notifications_unread'] !== null) ? (int) $sync['notifications_unread'] : null,
        'gone'         => (bool) $sync['gone'],
        'messages'     => array_values($sync['messages']),
        'more'         => (bool) $sync['more'],
        'changed'      => array_values($sync['changed']),
        'reload'       => (bool) $sync['reload'],
        'channel_state' => ($sync['channel_state'] !== null) ? ws_api_sync_channel_state_present($sync['channel_state']) : null,
    );
}

// What ws_api_sync_present() returns.
function ws_api_sync_schema()
{
    return array(
        'cursor'       => 'string',
        'channels'     => 'WorkspaceChannel[]?',
        'threads'      => 'WorkspaceSyncThread[]?',
        'inbox_unread' => 'integer',
        'notifications_unread' => 'integer?',
        'gone'         => 'boolean',
        'messages'     => 'WorkspaceMessage[]',
        'more'         => 'boolean',
        'changed'      => 'WorkspaceMessage[]',
        'reload'       => 'boolean',
        'channel_state' => 'WorkspaceSyncChannelState?',
    );
}

/**
 * What waits in a channel for an assistant only a screen carries on: a
 * request to Pinegrap AI or to an MCP server's own agent still queued, sent
 * or running - the sign on which the panel's screen calls ws_ai_kick and
 * ws_mcp_agent_kick (workspace.js, schedule). Claude works on claude.ai and
 * needs nobody.
 *
 * @param int $channel_id
 * @return array claude, agent, ai, as ws_message_ask_assistants() returns them
 */
function ws_api_sync_waiting($channel_id)
{
    $waiting = array('claude' => false, 'agent' => false, 'ai' => false);

    if (!function_exists('ws_ai_schema_ready') || !ws_ai_schema_ready()) {
        return $waiting;
    }

    foreach ((array) db_items("SELECT DISTINCT agent FROM ws_ai_requests
        WHERE agent IN ('ai', 'mcp') AND status IN ('queued', 'sent', 'running') AND channel_id = '" . (int) $channel_id . "'") as $row) {
        $waiting[((string) $row['agent'] === 'mcp') ? 'agent' : 'ai'] = true;
    }

    return $waiting;
}

function ws_api_sync($params)
{
    $viewer = ws_api_viewer();
    $claude = ws_api_is_claude();

    $cursor = array('t' => 0, 'c' => 0, 'm' => 0, 'ch' => '', 'th' => '', 'p' => '');

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = ws_api_sync_cursor_read($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }
    }

    // A person signed in on their own device has the workspace in front of
    // them, as with the panel's screen open: a mention's banner waits, and
    // banners whose wait is over go out. An integration's poll is nobody
    // looking.
    $person = ws_api_device_writes_as_person(function_exists('api_current_device') ? api_current_device() : null);

    if ($person) {
        ws_sync_seen($viewer['id']);
    }

    // The channel open on the screen, while the person may read it.
    $channel_id = (int) ($params['channel_id'] ?? 0);
    $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

    if ($channel && (!ws_can_read_channel($viewer, $channel) || ($claude && !ws_claude_channel_allowed($channel)))) {
        $channel = null;
    }

    // Pinegrap AI and an MCP server's own agent work only while somebody
    // carries them on: the panel's screen does while a request waits in the
    // channel it shows, and so does the person's app. Before anything is
    // read, so a call that does the work here brings what it wrote.
    if ($person && $channel) {
        ws_api_assistants_carry_on(ws_api_sync_waiting($channel_id), true);
    }

    $now = time();

    $sync = array(
        'cursor'       => '',
        'channels'     => null,
        'threads'      => null,
        'inbox_unread' => ws_inbox_unread_count($viewer['id']),
        'notifications_unread' => ws_api_sync_bell(api_current_app()['owner'], api_current_scopes()),
        'gone'         => false,
        'messages'     => array(),
        'more'         => false,
        'changed'      => array(),
        'reload'       => false,
        'channel_state' => null,
    );

    // The sidebar, sent again only when something in it changed.
    $channels = array();

    foreach (ws_channels_for($viewer) as $brief) {
        if ($claude && !ws_claude_channel_allowed(ws_channel($brief['id']))) {
            continue;
        }

        $channels[] = ws_api_channel_present($brief);
    }

    $threads = array();

    foreach (ws_threads_for($viewer) as $row) {
        if ($claude && !ws_claude_channel_allowed(ws_channel($row['channel_id']))) {
            continue;
        }

        $threads[] = ws_api_sync_thread_present($row);
    }

    $mark = array(
        't'  => $cursor['t'],
        'c'  => 0,
        'm'  => 0,
        'ch' => ws_api_sync_mark($channels),
        'th' => ws_api_sync_mark($threads),
        'p'  => '',
    );

    if ($mark['ch'] !== $cursor['ch']) {
        $sync['channels'] = $channels;
    }

    if ($mark['th'] !== $cursor['th']) {
        $sync['threads'] = $threads;
    }

    // The channel open on the screen: what was written since the previous
    // call, and what of the messages already sent changed since then. A
    // channel opened now starts with its newest page.
    if ($channel_id > 0) {
        $mark['c'] = $channel_id;

        if (!$channel) {
            $sync['gone'] = true;
        } else {
            $held = ((int) $cursor['c'] === $channel_id) ? (int) $cursor['m'] : 0;

            $news = ws_channel_news($channel_id, $held, ($held > 0) ? (int) $cursor['t'] : 0, array(
                'first_page' => WS_API_SYNC_FIRST_PAGE,
                'after_id'   => ws_channel_floor($channel_id),
                'limit'      => WS_API_SYNC_MAX,
            ));

            $sync['messages'] = ws_api_messages_present($viewer, $news['new']);
            $sync['more'] = ($held > 0) && (count($news['new']) >= WS_API_SYNC_MAX);
            $sync['changed'] = ws_api_messages_present($viewer, $news['changed']);
            $sync['reload'] = (count($news['changed']) >= WS_API_SYNC_MAX);

            $mark['m'] = !empty($news['new']) ? (int) $news['new'][count($news['new']) - 1]['id'] : $held;

            // What the screen shows around the messages, for a person's own
            // app only: an integration polling for messages has no screen to
            // draw it on, and the reads are not free. Sent again only when
            // it changed; the channel's id is in it, so another channel's
            // state is never taken for this one's.
            if ($person) {
                $state = ws_api_sync_channel_state($viewer, $channel);
                $mark['p'] = ws_api_sync_mark($state);

                if ($mark['p'] !== $cursor['p']) {
                    $sync['channel_state'] = $state;
                }
            }
        }
    }

    // Changed messages are looked for from this call on once anything was
    // sent; after a call that sent nothing the cursor stays as it was, so
    // the answer is the same and its ETag still matches.
    $moved = !empty($sync['messages']) || !empty($sync['changed'])
        || ($sync['channels'] !== null) || ($sync['threads'] !== null)
        || ($mark['c'] !== (int) $cursor['c']) || ($mark['m'] !== (int) $cursor['m'])
        || ($mark['p'] !== $cursor['p']);

    if ($moved) {
        $mark['t'] = $now;
    }

    $sync['cursor'] = ws_api_sync_cursor_write($mark);

    api_ok(ws_api_sync_present($sync));
}

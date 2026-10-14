<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the person's inbox in the external API (GET /workspace/inbox):
 * what they were told - a mention, a task handed to them, an approval asked
 * of them, a meeting about to start - and what each line is about.
 *
 * A workspace line in the bell names its inbox row as its subject
 * (workspace_inbox in GET /notifications), so an app woken by a banner reads
 * that row here and opens the channel, the message, the task, the note or
 * the plan item it is about on its own screen, rather than the panel's
 * address. The sentences are the panel's own (ws_inbox_describe()), and so is
 * the rule of what the person may still see (ws_inbox_visible_rows()).
 *
 * An app that holds lines picks up what changed in them with changed_since,
 * paged with since_id, as it does with a channel's messages: a row changes
 * only by being written, sent again (its created_at moves) or read (its
 * read_at is set), so those two stamps say when, with no column of its own.
 * A row taken away for good leaves a mark in ws_inbox_deleted for a while
 * (ws_inbox_delete()), and comes back among the changes as deleted, the way a
 * deleted message does. So does a row about something the person can no
 * longer see: it is marked read when it goes out of their reach
 * (ws_inbox_reach_lost(), ws_inbox_visible_rows()), and for them it is gone
 * as much as a deleted one. An app asking from further back than the marks
 * are kept is told to read the inbox again (reload).
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
 * The most lines one page of the inbox carries. Each line is described on
 * its own, as on the workspace screen, so a page stays short.
 */
if (!defined('WS_API_INBOX_MAX')) {
    define('WS_API_INBOX_MAX', 100);
}

/**
 * A panel address as an app opens it: the inbox's addresses start at the
 * site's root, and an app has no page to resolve them against.
 *
 * @param string $url
 * @param string $origin scheme and host, https://example.com
 * @return string
 */
function ws_api_inbox_url($url, $origin)
{
    $url = (string) $url;

    if ((strpos($url, '/') === 0) && (strpos($url, '//') !== 0)) {
        return rtrim((string) $origin, '/') . $url;
    }

    return $url;
}

/**
 * The channel and the message a line points at. A scheduled action's notice
 * points at the message the action was written in, and only while the person
 * may read its channel - the row itself holds no channel - as the panel's
 * line does.
 *
 * @param array $viewer
 * @param array $row ws_inbox row
 * @return array channel_id, message_id (0 for none)
 */
function ws_api_inbox_place($viewer, $row)
{
    if ($row['kind'] !== 'scheduled_notice') {
        return array('channel_id' => (int) $row['channel_id'], 'message_id' => (int) $row['message_id']);
    }

    $notice = function_exists('ws_scheduled_notice') ? ws_scheduled_notice((int) $row['id']) : null;
    $readable = $notice && ((int) $notice['channel_id'] > 0) && ((int) $notice['message_id'] > 0)
        && ws_can_read_channel($viewer, ws_channel((int) $notice['channel_id']));

    return $readable
        ? array('channel_id' => (int) $notice['channel_id'], 'message_id' => (int) $notice['message_id'])
        : array('channel_id' => 0, 'message_id' => 0);
}

/**
 * One inbox line as the API shows it.
 *
 * @param array $viewer
 * @param array $row ws_inbox row
 * @return array
 */
function ws_api_inbox_present($viewer, $row)
{
    $described = ws_inbox_describe($viewer, $row);
    $place = ws_api_inbox_place($viewer, $row);

    // A discussion is a channel of its own; the channel it was started in
    // is named beside it, as the sidebar's discussions name theirs.
    $thread = (($place['channel_id'] > 0) && function_exists('ws_thread')) ? ws_thread($place['channel_id']) : null;

    $note_id = (int) ($row['note_id'] ?? 0);
    $event_id = (int) ($row['event_id'] ?? 0);
    $join = (string) ($described['join'] ?? '');

    return array(
        'id'                => (int) $row['id'],
        'kind'              => (string) $row['kind'],
        'title'             => (string) $described['title'],
        'body'              => (string) $described['body'],
        'url'               => ws_api_inbox_url($described['url'], URL_SCHEME . HOSTNAME_SETTING),
        'read'              => ((int) $row['read_at'] > 0),
        'actor_id'          => ((int) $row['actor_id'] > 0) ? (int) $row['actor_id'] : null,
        'channel_id'        => ($place['channel_id'] > 0) ? $place['channel_id'] : null,
        'parent_channel_id' => $thread ? (int) $thread['parent_channel_id'] : null,
        'message_id'        => ($place['message_id'] > 0) ? $place['message_id'] : null,
        'task_id'           => ((int) $row['task_id'] > 0) ? (int) $row['task_id'] : null,
        'note_id'           => ($note_id > 0) ? $note_id : null,
        'plan_item_id'      => ($event_id > 0) ? $event_id : null,
        'meeting_url'       => ($join !== '') ? $join : null,
        'created_at'        => api_time($row['created_at']),
        'deleted'           => false,
    );
}

/**
 * A line taken away (ws_inbox_delete()), as the changed lines carry it: its
 * id, its kind and when it was written, with deleted true and nothing of what
 * it said, as a deleted message comes back with its text gone. It no longer
 * waits, so it is read. A line about something the person can no longer see
 * is carried the same way.
 *
 * @param array $row ws_inbox_deleted row, or a ws_inbox row out of reach
 * @return array
 */
function ws_api_inbox_deleted_present($row)
{
    return array(
        'id'                => (int) $row['id'],
        'kind'              => (string) $row['kind'],
        'title'             => '',
        'body'              => '',
        'url'               => '',
        'read'              => true,
        'actor_id'          => null,
        'channel_id'        => null,
        'parent_channel_id' => null,
        'message_id'        => null,
        'task_id'           => null,
        'note_id'           => null,
        'plan_item_id'      => null,
        'meeting_url'       => null,
        'created_at'        => api_time($row['created_at']),
        'deleted'           => true,
    );
}

// What ws_api_inbox_present() returns.
function ws_api_inbox_schema()
{
    return array(
        'id'                => 'integer',
        'kind'              => 'string',
        'title'             => 'string',
        'body'              => 'string',
        'url'               => 'string',
        'read'              => 'boolean',
        'actor_id'          => 'integer?',
        'channel_id'        => 'integer?',
        'parent_channel_id' => 'integer?',
        'message_id'        => 'integer?',
        'task_id'           => 'integer?',
        'note_id'           => 'integer?',
        'plan_item_id'      => 'integer?',
        'meeting_url'       => 'string?',
        'created_at'        => 'string',
        'deleted'           => 'boolean',
    );
}

/**
 * May the application Claude works through hear of a line about a channel?
 * Only about one Claude may be asked in, as with its channel reads
 * (ws_api_channel_or_404()); a line about no channel, always.
 *
 * @param int $channel_id
 * @return bool
 */
function ws_api_inbox_claude_may_hear($channel_id)
{
    if ((int) $channel_id <= 0) {
        return true;
    }

    $channel = ws_channel((int) $channel_id);

    return $channel && ws_claude_channel_allowed($channel);
}

/**
 * May the application Claude works through read this line? Only about a
 * channel Claude may be asked in.
 *
 * @param array $viewer
 * @param array $row ws_inbox row
 * @return bool
 */
function ws_api_inbox_claude_may_read($viewer, $row)
{
    return ws_api_inbox_claude_may_hear(ws_api_inbox_place($viewer, $row)['channel_id']);
}

/**
 * The rows the owner may see out of a set of theirs, in the order given.
 *
 * @param array   $viewer
 * @param array[] $rows ws_inbox rows of the viewer
 * @return array[] ws_inbox rows
 */
function ws_api_inbox_reachable_rows($viewer, $rows)
{
    $claude = ws_api_is_claude();
    $out = array();

    foreach (ws_inbox_visible_rows($viewer, $rows) as $row) {
        if ($claude && !ws_api_inbox_claude_may_read($viewer, $row)) {
            continue;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * The lines the owner may see out of a set of their rows, as the API shows
 * them.
 *
 * @param array   $viewer
 * @param array[] $rows ws_inbox rows of the viewer
 * @return array[]
 */
function ws_api_inbox_present_rows($viewer, $rows)
{
    $out = array();

    foreach (ws_api_inbox_reachable_rows($viewer, $rows) as $row) {
        $out[] = ws_api_inbox_present($viewer, $row);
    }

    return $out;
}

/**
 * How many lines wait for a person, read now rather than from the badge's
 * cache: an answer after marking gives the count after it.
 *
 * @param int $user_id
 * @return int
 */
function ws_api_inbox_unread($user_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_inbox WHERE user_id = '" . (int) $user_id . "' AND read_at = 0");
}

/**
 * Which of a person's rows changed at or after a moment, after a row: written
 * then, sent again then (a note changed once more moves created_at) or read
 * then - on the workspace screen, in the bell, here or because what the line
 * is about went out of reach. Every writer of ws_inbox moves one of the two;
 * a row deleted is found by ws_api_inbox_deleted_where() instead.
 *
 * @param int $user_id
 * @param int $since    timestamp
 * @param int $after_id only rows after this one
 * @return string
 */
function ws_api_inbox_changed_where($user_id, $since, $after_id)
{
    $since = max(1, (int) $since);

    return "user_id = '" . (int) $user_id . "'"
        . " AND (created_at >= '" . $since . "' OR read_at >= '" . $since . "')"
        . " AND id > '" . max(0, (int) $after_id) . "'";
}

/**
 * The last moment anything in a person's inbox changed as changed_since
 * finds it: the newest created_at of the unread rows (a line written, or sent
 * again - only an unread line is sent again) and the newest read_at of any
 * row (a row read was written before it was read), and the newest line taken
 * away (ws_inbox_deleted). Each reads one end of an index (idx_user of
 * either table, and the unread rows, which are few), so an app's sync can
 * ask every few seconds.
 *
 * @param int $user_id
 * @return int timestamp, 0 for an inbox that never held a line
 */
function ws_api_inbox_changed_at($user_id)
{
    $user_id = (int) $user_id;

    $written = (int) db_value("SELECT COALESCE(MAX(created_at), 0) FROM ws_inbox WHERE user_id = '" . $user_id . "' AND read_at = 0");
    $read = (int) db_value("SELECT COALESCE(MAX(read_at), 0) FROM ws_inbox WHERE user_id = '" . $user_id . "'");

    $deleted = (function_exists('ws_inbox_deleted_ready') && ws_inbox_deleted_ready())
        ? (int) db_value("SELECT COALESCE(MAX(deleted_at), 0) FROM ws_inbox_deleted WHERE user_id = '" . $user_id . "'")
        : 0;

    return max($written, $read, $deleted);
}

/**
 * Which of a person's rows were taken away at or after a moment, after a row,
 * and up to one when the live rows were read only that far (0: no bound).
 *
 * @param int $user_id
 * @param int $since    timestamp
 * @param int $after_id only rows after this one
 * @param int $upto_id
 * @return string
 */
function ws_api_inbox_deleted_where($user_id, $since, $after_id, $upto_id = 0)
{
    return "user_id = '" . (int) $user_id . "'"
        . " AND deleted_at >= '" . max(1, (int) $since) . "'"
        . " AND id > '" . max(0, (int) $after_id) . "'"
        . (((int) $upto_id > 0) ? " AND id <= '" . (int) $upto_id . "'" : '');
}

/**
 * The most marks of lines taken away one read of the changes looks at. A
 * person's inbox loses few lines in WS_INBOX_DELETED_KEEP, so one read finds
 * them all in practice.
 */
if (!defined('WS_API_INBOX_DELETED_READ')) {
    define('WS_API_INBOX_DELETED_READ', 500);
}

/**
 * The lines of the owner's inbox taken away at or after a moment, oldest
 * first, as the changed lines carry them. The application Claude works
 * through hears only of lines about a channel it may be asked in, as with
 * the lines that are still there.
 *
 * @param array $viewer
 * @param int   $since    timestamp
 * @param int   $after_id
 * @param int   $upto_id  0: no bound
 * @return array lines (id => line), upto: the last mark read when there may
 *               be more after it, else 0
 */
function ws_api_inbox_deleted($viewer, $since, $after_id, $upto_id)
{
    if (!function_exists('ws_inbox_deleted_ready') || !ws_inbox_deleted_ready()) {
        return array('lines' => array(), 'upto' => 0);
    }

    $rows = (array) db_items("SELECT * FROM ws_inbox_deleted WHERE " . ws_api_inbox_deleted_where($viewer['id'], $since, $after_id, $upto_id) . "
        ORDER BY id
        LIMIT " . WS_API_INBOX_DELETED_READ);

    $claude = ws_api_is_claude();
    $lines = array();

    foreach ($rows as $row) {
        if ($claude && !ws_api_inbox_claude_may_hear($row['channel_id'])) {
            continue;
        }

        $lines[(int) $row['id']] = ws_api_inbox_deleted_present($row);
    }

    $last = end($rows);

    return array(
        'lines' => $lines,
        'upto'  => (count($rows) >= WS_API_INBOX_DELETED_READ) ? (int) $last['id'] : 0,
    );
}

/**
 * Out of a set of the owner's changed rows, in the order given, what the
 * changed lines carry of each: a row they may see as it is now, and a row
 * about something they can no longer see as taken away. The row is still
 * there, but it is the owner's alone and nothing of it may be shown to them
 * any more, so for them it is gone as much as a deleted line - GET
 * /workspace/inbox/{id} answers 404 for both. The application Claude works
 * through hears of neither outside the channels it may be asked in.
 *
 * @param array   $viewer
 * @param array[] $rows ws_inbox rows of the viewer
 * @return array[] row, gone
 */
function ws_api_inbox_changed_rows($viewer, $rows)
{
    $claude = ws_api_is_claude();
    $seen = array();

    foreach (ws_inbox_visible_rows($viewer, $rows) as $row) {
        $seen[(int) $row['id']] = true;
    }

    $out = array();

    foreach ((array) $rows as $row) {
        $gone = !isset($seen[(int) $row['id']]);

        if ($claude && !($gone ? ws_api_inbox_claude_may_hear($row['channel_id']) : ws_api_inbox_claude_may_read($viewer, $row))) {
            continue;
        }

        $out[] = array('row' => $row, 'gone' => $gone);
    }

    return $out;
}

/**
 * Is a moment too far back for the changed lines to be whole? Lines taken
 * away are remembered for a while (WS_INBOX_DELETED_KEEP); from further back
 * some may have gone unremembered, so the app is told to read the inbox again
 * from the first page, as the sync's reload tells it to read a channel again.
 *
 * @param int $since timestamp the app asked from
 * @param int $now
 * @param int $keep  seconds the marks are kept
 * @return bool
 */
function ws_api_inbox_reload($since, $now, $keep)
{
    return (int) $since < ((int) $now - (int) $keep);
}

/**
 * The lines of the owner's inbox that changed at or after a moment, oldest
 * first, after a line, each as it is now; a line taken away, or about
 * something the owner can no longer see, comes back as deleted. Lines the
 * application Claude works through may not hear of are passed over and the
 * page filled from the rows after them, so as many lines as limit means more
 * may follow - the rule the app pages a channel's changed messages by.
 *
 * @param array $viewer
 * @param int   $since    timestamp
 * @param int   $after_id
 * @param int   $limit
 * @return array[]
 */
function ws_api_inbox_changed($viewer, $since, $after_id, $limit)
{
    $limit = max(1, min(WS_API_INBOX_MAX, (int) $limit));
    $after = max(0, (int) $after_id);
    $chunk = $limit;
    $kept = array();
    $upto = 0;

    // The first read takes as many rows as the page holds. Rows passed over
    // are few - only Claude's, about a channel it may not be asked in - so a
    // read that meets them goes on in larger steps, a bounded number of
    // times, and a moment far back does not walk the whole inbox in one call.
    for ($round = 0; $round < 20; $round++) {
        $rows = (array) db_items("SELECT * FROM ws_inbox WHERE " . ws_api_inbox_changed_where($viewer['id'], $since, $after) . "
            ORDER BY id
            LIMIT " . $chunk);

        $kept = array_merge($kept, ws_api_inbox_changed_rows($viewer, $rows));

        if ((count($kept) >= $limit) || (count($rows) < $chunk)) {
            $upto = 0;
            break;
        }

        $last = end($rows);
        $after = (int) $last['id'];
        $upto = $after;
        $chunk = WS_API_INBOX_MAX;
    }

    $lines = array();

    foreach (array_slice($kept, 0, $limit) as $item) {
        $lines[(int) $item['row']['id']] = $item['gone'] ? ws_api_inbox_deleted_present($item['row']) : ws_api_inbox_present($viewer, $item['row']);
    }

    // The lines taken away go in among them by id, so since_id pages past
    // both. Neither kind goes beyond the last row the other was read up to,
    // when that read stopped short: the next page starts there. A row is
    // either there or taken away; an id a server gave again after taking its
    // row away is the line that is there.
    $gone = ws_api_inbox_deleted($viewer, $since, (int) $after_id, $upto);
    $lines += $gone['lines'];

    ksort($lines);

    if ($gone['upto'] > 0) {
        foreach (array_keys($lines) as $id) {
            if ($id > $gone['upto']) {
                unset($lines[$id]);
            }
        }
    }

    return array_slice(array_values($lines), 0, $limit);
}

function ws_api_inbox_list($params)
{
    $viewer = ws_api_viewer();
    $limit = max(1, min(WS_API_INBOX_MAX, (int) ($params['limit'] ?? 30)));

    if (isset($params['changed_since'])) {
        $since = (int) $params['changed_since'];

        api_ok_list(ws_api_inbox_changed($viewer, $since, (int) ($params['since_id'] ?? 0), $limit), $limit, null, null, array(
            'reload' => ws_api_inbox_reload($since, time(), WS_INBOX_DELETED_KEEP),
        ));
    }

    $where = array("user_id = '" . (int) $viewer['id'] . "'");

    if (!empty($params['unread'])) {
        $where[] = "read_at = 0";
    }

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }

        $where[] = "id < '" . (int) $cursor['i'] . "'";
    }

    $rows = (array) db_items("SELECT * FROM ws_inbox WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT " . ($limit + 1));
    $next = null;

    // The cursor follows the rows read, not the lines shown: a page whose
    // rows are out of the person's reach comes back short, with a cursor.
    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = end($rows);
        $next = api_cursor_encode($last['id'], $last['id']);
    }

    api_ok_list(ws_api_inbox_present_rows($viewer, $rows), $limit, $next, null, array('reload' => false));
}

function ws_api_inbox_get($params)
{
    $viewer = ws_api_viewer();
    $row = db_item("SELECT * FROM ws_inbox WHERE id = '" . (int) $params['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");
    $shown = is_array($row) ? ws_api_inbox_present_rows($viewer, array($row)) : array();

    if (empty($shown)) {
        api_fail_not_found(lang('Inbox item'));
    }

    api_ok($shown[0]);
}

function ws_api_inbox_read($params)
{
    $viewer = ws_api_viewer();

    ws_api_person_only();

    $user_id = (int) $viewer['id'];
    $ids = 'all';

    if (empty($params['all'])) {
        $ids = array();

        foreach ((array) ($params['ids'] ?? array()) as $id) {
            if ((int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }

        if (empty($ids)) {
            api_fail_validation(lang('Send ids, or all set to true.'), 'ids');
        }

        $ids = array_values($ids);
    }

    // Somebody else's ids, and lines already read, are skipped rather than
    // refused, as in the bell.
    $updated = (int) db_value("SELECT COUNT(*) FROM ws_inbox WHERE user_id = '" . $user_id . "' AND read_at = 0"
        . (($ids === 'all') ? '' : " AND id IN (" . implode(',', $ids) . ")"));

    api_dry_run_stop('marked_read', 'workspace_inbox', array('count' => $updated));

    if ($updated > 0) {
        ws_inbox_mark_read($user_id, $ids);
    }

    api_ok(array(
        'updated' => $updated,
        'unread'  => ws_api_inbox_unread($user_id),
    ));
}

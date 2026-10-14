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
    );
}

/**
 * May the application Claude works through read this line? Only about a
 * channel Claude may be asked in, as with its channel reads
 * (ws_api_channel_or_404()).
 *
 * @param array $viewer
 * @param array $row ws_inbox row
 * @return bool
 */
function ws_api_inbox_claude_may_read($viewer, $row)
{
    $channel_id = ws_api_inbox_place($viewer, $row)['channel_id'];

    if ($channel_id <= 0) {
        return true;
    }

    $channel = ws_channel($channel_id);

    return $channel && ws_claude_channel_allowed($channel);
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
    $claude = ws_api_is_claude();
    $out = array();

    foreach (ws_inbox_visible_rows($viewer, $rows) as $row) {
        if ($claude && !ws_api_inbox_claude_may_read($viewer, $row)) {
            continue;
        }

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

function ws_api_inbox_list($params)
{
    $viewer = ws_api_viewer();
    $limit = max(1, min(WS_API_INBOX_MAX, (int) ($params['limit'] ?? 30)));
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

    api_ok_list(ws_api_inbox_present_rows($viewer, $rows), $limit, $next);
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

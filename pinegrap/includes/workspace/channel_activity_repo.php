<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The repository part of a channel's Activity tab (channel_activity.php):
 * today's pull requests opened, merged and closed, checks green and red,
 * and the newest lines, counted from the lines the GitHub application wrote
 * into the channel since local midnight. Only that application's messages
 * count, by its id, so a typed look-alike counts nothing; GitHub itself is
 * not asked. The lines are read back by pg_github_line_read()
 * (includes/fn/github_links.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The newest lines the tab lists.
define('WS_CHANNEL_ACTIVITY_REPO_LINES', 5);

// The channel's newest messages looked through for today's lines, so a
// long channel is never read to its start.
define('WS_CHANNEL_ACTIVITY_REPO_SCAN', 5000);

/**
 * The repository's numbers of today in a channel, for a reader who may see
 * its link (ws_channel_activity_link_name()); null when there is none.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $now
 * @return array|null name, url, pull_requests (opened, merged, closed),
 *                    checks (green, red), lines (pg_github_day_counts())
 */
function ws_channel_activity_repo($viewer, $channel, $now)
{
    $repository = ws_channel_activity_link_name($viewer, $channel);

    if ($repository === '') {
        return null;
    }

    // Read, never made here: ws_github_app_id() would make the application.
    $stored = pg_connect_service_get('github_channel_app');
    $app_id = (int) ($stored['app_id'] ?? 0);
    $rows = array();

    if ($app_id > 0) {
        $rows = db_items("SELECT id, body, created_at FROM (
                SELECT id, sender_kind, sender_id, body, created_at, deleted_at FROM ws_messages
                WHERE channel_id = '" . (int) $channel['id'] . "'
                ORDER BY id DESC LIMIT " . WS_CHANNEL_ACTIVITY_REPO_SCAN . "
            ) recent
            WHERE sender_kind = 'app' AND sender_id = '" . $app_id . "' AND deleted_at = 0
                AND created_at >= '" . (int) strtotime('today', (int) $now) . "'
            ORDER BY id DESC");
    }

    return array(
        'name' => $repository,
        'url'  => 'https://github.com/' . $repository,
    ) + pg_github_day_counts(is_array($rows) ? $rows : array(), $repository, WS_CHANNEL_ACTIVITY_REPO_LINES);
}

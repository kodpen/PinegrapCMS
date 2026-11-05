<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which Workspace channels the channel archive switch may archive
 * or bring back: a discussion (kind thread) is concluded and purged by its
 * own rules (threads.php), so the switch leaves it alone and a concluded
 * discussion is never opened for writing again while its purge waits.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php');

// Channels of the team and guest rooms are archived by the switch; a
// discussion, or no channel, is not.
function test_workspace_channel_archivable()
{
	pg_assert_same(true, ws_channel_archivable(array('id' => 1, 'kind' => 'public')));
	pg_assert_same(true, ws_channel_archivable(array('id' => 2, 'kind' => 'private')));
	pg_assert_same(true, ws_channel_archivable(array('id' => 3, 'kind' => 'guest')));
	pg_assert_same(false, ws_channel_archivable(array('id' => 4, 'kind' => 'thread')));
	pg_assert_same(false, ws_channel_archivable(null));
}

// The panel's archive and the API's archived field both ask it before
// anything is written or rehearsed.
function test_workspace_channel_archive_sources()
{
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	pg_assert_contains('if (!ws_channel_archivable($channel)) {', $channels);
	pg_assert_contains('&& !ws_channel_archivable($channel)) {', $api);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a search narrowed to one channel, that need no database: the
 * condition that takes in the channel's discussions, and the two searches
 * (messages, and the palette's decisions and files) that read through it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_channel_search_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// No channel: every channel, no condition.
function test_workspace_channel_search_sql_every_channel()
{
	pg_assert_same('', ws_channel_search_sql(array()));
	pg_assert_same('', ws_channel_search_sql(array(0, -3)));
	pg_assert_same(array(), ws_channel_search_ids(0));
}

// The channel alone, and with its discussions.
function test_workspace_channel_search_sql_channel_and_discussions()
{
	pg_assert_same(" AND channel_id = '10'", ws_channel_search_sql(array(10)));
	pg_assert_same(" AND channel_id IN ('10', '11', '14')", ws_channel_search_sql(array(10, '11', 14, 11)));
}

// Both searches narrow through the discussions-aware condition, not the
// channel's own id alone.
function test_workspace_channel_search_used_by_both_searches()
{
	foreach (array('ws_message_search', 'ws_palette_message_rows') as $function) {
		$source = workspace_channel_search_test_source($function);

		pg_assert_true(strpos($source, 'ws_channel_search_sql(ws_channel_search_ids($channel_id))') !== false, $function . ' reads the channel with its discussions');
		pg_assert_true(strpos($source, "\" AND channel_id = '\" . (int) \$channel_id") === false, $function . ' does not narrow to the channel alone');
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reach of the external API's proposed changes
 * (includes/workspace/api_resources.php) that need no database: the changes
 * list, apply and set aside reach a discussion's changes through the
 * discussions the owner is a member of in the channels they read, and the
 * assistant requests keep reading the channels alone.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The source of a function, to read which calls it makes.
function ws_api_thread_changes_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// The candidates are the discussions the person is a member of, opened in
// the channels given; nothing is asked without a channel or a person.
function test_ws_api_thread_ids_sql_reads_membership_in_the_given_channels()
{
	$sql = ws_api_thread_ids_sql(7, array(3, '5', 3, 0));

	pg_assert_contains('FROM ws_threads t', $sql, 'only discussions');
	pg_assert_contains("m.channel_id = t.channel_id AND m.user_id = '7'", $sql, 'only those the person is a member of');
	pg_assert_contains('t.parent_channel_id IN (3,5)', $sql, 'only in the channels given, each once');
	pg_assert_same('', ws_api_thread_ids_sql(7, array()), 'no channel, no query');
	pg_assert_same('', ws_api_thread_ids_sql(0, array(3)), 'no person, no query');
}

// Each discussion is checked with the panel's rule and the Claude filter.
function test_ws_api_readable_thread_ids_checks_each_discussion()
{
	$source = ws_api_thread_changes_test_source('ws_api_readable_thread_ids');

	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $source, 'read as the panel reads it');
	pg_assert_contains('ws_claude_channel_allowed($channel)', $source, 'Claude reads only where it may be asked');
	pg_assert_contains('ws_threads_ready()', $source, 'a site without discussions asks nothing');
}

// The changes list, apply and set aside share the reach; the assistant
// requests do not take the discussions.
function test_ws_api_changes_reach_takes_the_discussions()
{
	pg_assert_contains('ws_api_readable_thread_ids($viewer, $channel_ids)', ws_api_thread_changes_test_source('ws_api_changes_reach_sql'), 'the reach adds the discussions');
	pg_assert_contains('ws_api_changes_reach_sql($viewer)', ws_api_thread_changes_test_source('ws_api_change_or_404'), 'apply and set aside find the change through the reach');
	pg_assert_contains('ws_api_change_or_404(', ws_api_thread_changes_test_source('ws_api_changes_apply'), 'apply');
	pg_assert_contains('ws_api_change_or_404(', ws_api_thread_changes_test_source('ws_api_changes_dismiss'), 'set aside');
	pg_assert_false(strpos(ws_api_thread_changes_test_source('ws_api_readable_channel_ids'), 'ws_api_readable_thread_ids') !== false, 'the channel ids the assistant requests read stay channels');
}

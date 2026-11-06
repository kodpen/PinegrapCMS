<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the earlier-version count a Workspace message carries: it counts
 * only the versions the viewer's history lists, so a redacted edit's hidden
 * versions do not show as "3 earlier version(s)" over an empty list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_workspace_file_versions_count_follows_history()
{
	// Message 10: O -> E1 by 4, E1 -> R redacted by 7. Message 11: two plain
	// edits. Message 12: a redaction, then a plain edit after it.
	$rows = array(
		array('id' => 1, 'message_id' => 10, 'edited_by' => 4, 'redacted' => 0),
		array('id' => 2, 'message_id' => 10, 'edited_by' => 7, 'redacted' => 1),
		array('id' => 3, 'message_id' => 11, 'edited_by' => 4, 'redacted' => 0),
		array('id' => 4, 'message_id' => 11, 'edited_by' => 5),
		array('id' => 5, 'message_id' => 12, 'edited_by' => 7, 'redacted' => 1),
		array('id' => 6, 'message_id' => 12, 'edited_by' => 4, 'redacted' => '0'));

	pg_assert_same(array(11 => 2, 12 => 1), ws_file_versions_counts($rows, 9), 'a reader counts only what the history lists');
	pg_assert_same(array(11 => 2, 12 => 1), ws_file_versions_counts($rows, 4), 'so does the plain editor');
	pg_assert_same(array(10 => 2, 11 => 2, 12 => 2), ws_file_versions_counts($rows, 7), 'the person who redacted counts all');
	pg_assert_same(array(), ws_file_versions_counts(array(), 7), 'no rows, no counts');
}

// The count and the history read the same rule.
function test_workspace_file_versions_count_uses_visibility()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/file_edits.php');
	$start = strpos($source, 'function ws_file_versions_counts(');
	$end = strpos($source, "\n}\n", (int) $start);

	pg_assert_true(($start !== false) && (strpos(substr($source, $start, $end - $start), 'ws_file_edits_visible(') !== false), 'the count filters with ws_file_edits_visible()');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php'), "ws_file_versions_map(array_map(") !== false, 'messages ask the map');
	pg_assert_true(preg_match('/ws_file_versions_map\(array_map\(.*?\}, \$rows\), \(int\) \$viewer\[\'id\'\]\)/s', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php')) === 1, 'with the viewer');
}

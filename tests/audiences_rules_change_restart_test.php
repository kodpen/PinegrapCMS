<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a rules change during a recompute that stopped part-way: the
 * save puts the group's cursor back to 0 and removes the pass's state file,
 * so the next tick starts a full pass with the new rules instead of going on
 * from the old cursor with the old pass's ids.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_audience_pass_restart_clears_cursor_and_state()
{
	$source = pg_test_function_source('pg_audience_pass_restart');

	pg_assert_contains("UPDATE contact_groups SET compute_cursor = 0 WHERE id = ?", $source, 'the cursor goes back to the first contact');
	pg_assert_contains('pg_audience_state_write($group_id, null)', $source, 'the old pass\'s ids are dropped');
}

function test_edit_contact_group_restarts_the_pass_on_a_rules_change()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact_group.php');

	pg_assert_contains("if (\$due || (\$posted_rules['kind'] !== \$group_rules['kind'])) {", $screen, 'a rules or kind change restarts the pass');
	pg_assert_contains('pg_audience_pass_restart($id)', $screen, 'through the shared helper');
}

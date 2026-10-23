<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that gives comments their reply column
 * (upgrade_2026_11_0_comment_replies(), 11.0.31): it is called once under its
 * own label, adds comments.parent_id in place with the copy as the fallback,
 * adds the two indexes through the idempotent helper, never writes an ALTER of
 * its own, pauses only after it has run an ALTER in the pass, and the comments
 * table stays on the preflight list of heavy tables for the version. The step
 * itself needs a database and is run twice in a sandbox instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_replies_schema_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of the step function, from its signature to the closing brace at
// the start of a line.
function comment_replies_schema_test_step()
{
	$source = comment_replies_schema_test_source('includes/migrations/2026.11.0.php');

	if (!preg_match('/^function upgrade_2026_11_0_comment_replies\(\) \{\n(.*?)\n\}\n/ms', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_comment_replies_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = comment_replies_schema_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_comment_replies();        // 11.0.31\n"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	pg_assert_true(isset($entry[1]), 'the entry function is found');

	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.31', true)), 'no other step carries 11.0.31');
	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

function test_comment_replies_schema_step_adds_the_column_in_place_with_the_copy_as_fallback()
{
	$step = comment_replies_schema_test_step();

	pg_assert_true($step !== '', 'the step function is found');
	pg_assert_contains("'parent_id' => \"INT UNSIGNED NOT NULL DEFAULT 0\",", $step, 'the column, 0 for a comment that answers none');
	pg_assert_contains("if (!install_add_columns_instant('comments', \$columns)) {", $step, 'in place first');
	pg_assert_contains("install_add_column('comments', \$column, \$definition);", $step, 'copied where the server cannot');
}

function test_comment_replies_schema_step_adds_both_indexes_through_the_helper()
{
	$step = comment_replies_schema_test_step();

	pg_assert_contains("'idx_parent' => \"INDEX idx_parent (parent_id)\",", $step, 'the replies to a comment');
	pg_assert_contains("'idx_thread' => \"INDEX idx_thread (page_id, item_type, item_id, created_timestamp)\",", $step, 'one thread in the order it was written');
	pg_assert_contains("install_add_index('comments', \$index, \$definition);", $step, 'each asks first');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('[^']+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}

function test_comment_replies_schema_step_pauses_only_after_an_alter_of_this_pass()
{
	$step = comment_replies_schema_test_step();

	pg_assert_contains("\$executed = (int) \$install_runner['executed'];", $step, 'counts from the start of the step');
	pg_assert_contains("((int) \$install_runner['executed'] > \$executed)", $step, 'a pass that ran nothing never pauses, so every pass moves on');
	pg_assert_contains('(install_pass_seconds() >= install_pause_budget())', $step, 'only once the pass budget is spent');
	pg_assert_contains("!install_index_exists('comments', \$index)", $step, 'never for an index already there');
	pg_assert_true(strpos($step, 'install_pause(lang(') < strpos($step, "install_add_index('comments'"), 'the pause comes before the next ALTER');
}

function test_comment_replies_schema_comments_stay_on_the_heavy_table_list()
{
	if (!function_exists('install_heavy_tables')) {
		$runner = comment_replies_schema_test_source('includes/migrations/runner.php');

		pg_assert_true(preg_match("/'2026\\.11\\.0' => array\\([^)]*'comments'/", $runner) === 1, 'the preflight warns about comments for 2026.11.0');
		return;
	}

	$heavy = install_heavy_tables();

	pg_assert_true(in_array('comments', isset($heavy['2026.11.0']) ? $heavy['2026.11.0'] : array(), true), 'the preflight warns about comments for 2026.11.0');
}

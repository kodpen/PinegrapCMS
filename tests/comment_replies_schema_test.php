<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that gives comments their reply column
 * (upgrade_2026_11_0_comment_replies(), 11.0.31): it is called once under its
 * own label, adds comments.parent_id in place with the copy as the fallback,
 * adds the two indexes through the idempotent helper, never writes an ALTER of
 * its own, starts none of its three ALTERs once the pass has run a statement
 * and spent its budget (install_pass_spent(), counted from the start of the
 * pass), and the comments table stays on the preflight list of heavy tables
 * for the version. The step itself needs a database and is run twice in a
 * sandbox instead; the runner cannot be loaded without the upgrade's gate, so
 * its helper is checked in its source.
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

// The column copy and both indexes ask the pass, not the step: the copies
// 11.0.14 made of the same table earlier in the pass count too.
function test_comment_replies_schema_step_pauses_before_each_copy_once_the_pass_is_spent()
{
	$step = comment_replies_schema_test_step();

	pg_assert_same(2, substr_count($step, 'if (install_pass_spent()'), 'one guard before the column copy, one before each index');
	pg_assert_true(strpos($step, "\$install_runner['executed']") === false, 'no baseline of its own taken at the start of the step');
	pg_assert_contains("if (install_pass_spent() && !install_index_exists('comments', \$index)) {", $step, 'never for an index already there');

	$instant = strpos($step, "install_add_columns_instant('comments'");
	$first_guard = strpos($step, 'if (install_pass_spent())');
	$copy = strpos($step, "install_add_column('comments'");

	pg_assert_true(($instant !== false) && ($first_guard !== false) && ($copy !== false), 'the column guard is found');
	pg_assert_true(($instant < $first_guard) && ($first_guard < $copy), 'the in-place attempt runs first, the pause comes before the copy');

	$index_guard = strpos($step, 'if (install_pass_spent() &&');
	$index_add = strpos($step, "install_add_index('comments'");

	pg_assert_true(($index_guard !== false) && ($index_add !== false) && ($index_guard < $index_add), 'the pause comes before the next index');
	pg_assert_same(2, substr_count($step, "install_pause(lang(array('string' => 'comments: replies are being added; {var:1} follows in the next pass'"), 'the pause names what follows');
}

// The pass baseline is taken where the pass starts, and the helper needs work
// done in this pass as well as the budget spent, so a pass that has run
// nothing never pauses.
function test_comment_replies_schema_runner_counts_the_pass_from_its_start()
{
	$runner = comment_replies_schema_test_source('includes/migrations/runner.php');

	pg_assert_contains("\t'pass_executed' => 0,\n", $runner, 'a default in the runner state');
	pg_assert_contains("\$install_runner['pass_started'] = microtime(true);\n\n\t\t\t\t\t\t\$install_runner['pass_executed'] = \$install_runner['executed'];\n", $runner, 'taken with the start of every pass');

	pg_assert_true(preg_match('/^function install_pass_spent\(\) \{\n(.*?)\n\}\n/ms', $runner, $match) === 1, 'install_pass_spent() is found');

	$body = isset($match[1]) ? $match[1] : '';

	pg_assert_contains("((int) \$install_runner['executed'] > (int) \$install_runner['pass_executed'])", $body, 'only after a statement of this pass');
	pg_assert_contains('&& (install_pass_seconds() >= install_pause_budget())', $body, 'and only once the budget is spent');
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

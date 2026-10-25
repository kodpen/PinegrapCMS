<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that gives comments an index for a member's
 * own comments (upgrade_2026_11_0_comment_author_index(), 11.0.36): it is
 * called once under its own label, adds idx_author (created_user_id,
 * created_timestamp) through the idempotent helper, never writes an ALTER of
 * its own, does not start the ALTER once the pass has run a statement and
 * spent its budget, and ends with a note for the operator. The step itself
 * needs a database; its source is checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_author_index_schema_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
}

// The body of the step function, from its signature to the closing brace at
// the start of a line.
function comment_author_index_schema_test_step()
{
	if (!preg_match('/^function upgrade_2026_11_0_comment_author_index\(\) \{\n(.*?)\n\}\n/ms', comment_author_index_schema_test_source(), $match)) {
		return '';
	}

	return $match[1];
}

function test_comment_author_index_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = comment_author_index_schema_test_source();

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_comment_author_index();   // 11.0.36\n"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.36', true)), 'no other step carries 11.0.36');
}

function test_comment_author_index_schema_step_adds_the_index_through_the_helper()
{
	$step = comment_author_index_schema_test_step();

	pg_assert_true($step !== '', 'the step function is found');
	pg_assert_contains("install_add_index('comments', 'idx_author', \"INDEX idx_author (created_user_id, created_timestamp)\");", $step, "a member's comments in the order they were written");
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}

// The ALTER asks the pass: on a table still on MyISAM it copies the table,
// and 11.0.14 and 11.0.31 may already have copied it in the same pass.
function test_comment_author_index_schema_step_pauses_once_the_pass_is_spent()
{
	$step = comment_author_index_schema_test_step();

	$guard = strpos($step, "if (install_pass_spent() && !install_index_exists('comments', 'idx_author')) {");
	$add = strpos($step, "install_add_index('comments', 'idx_author'");

	pg_assert_true(($guard !== false) && ($add !== false) && ($guard < $add), 'the pause comes before the index, never for an index already there');
	pg_assert_contains("install_pause(lang('comments: the index for a member\\'s own comments follows in the next pass'));", $step, 'the pause names what follows');
}

function test_comment_author_index_schema_comments_stay_a_heavy_table_of_the_version()
{
	$runner = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/runner.php');

	pg_assert_true(preg_match("/'2026\\.11\\.0' => array\\([^)]*'comments'/", $runner) === 1, 'the preflight warning names comments for 2026.11.0');
}

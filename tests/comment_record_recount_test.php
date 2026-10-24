<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 data step that counts the comments of form records
 * on designed pages again (upgrade_2026_11_0_comment_record_counts(),
 * 11.0.33): it is called once under its own label after 11.0.31, changes no
 * schema, rebuilds each pair through pg_comment_refresh_record_info() in two
 * walks (pairs with record comments, and counter rows whose comments are
 * gone), pauses by time with its place saved first, keeps its place and a
 * done marker in a data/temp file read only while it is fresh, and ends with
 * a note. The step needs a database and is run twice in a sandbox; here it is
 * checked in its source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_record_recount_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
}

// The bodies of the step's functions, by name.
function comment_record_recount_test_functions()
{
	preg_match_all('/^function &?(upgrade_2026_11_0_comment_record_counts[a-z_]*)\([^)]*\) \{\n(.*?)\n\}\n/ms', comment_record_recount_test_source(), $matches, PREG_SET_ORDER);

	$functions = array();

	foreach ($matches as $match) {
		$functions[$match[1]] = $match[2];
	}

	return $functions;
}

function comment_record_recount_test_all()
{
	return implode("\n", comment_record_recount_test_functions());
}

function test_comment_record_recount_is_called_once_after_the_reply_column()
{
	$source = comment_record_recount_test_source();
	$line = "\tupgrade_2026_11_0_comment_record_counts();  // 11.0.33\n";

	pg_assert_same(1, substr_count($source, $line), 'called once');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	$list = isset($entry[1]) ? $entry[1] : '';
	$replies = strpos($list, "\tupgrade_2026_11_0_comment_replies();        // 11.0.31\n");
	$recount = strpos($list, $line);

	pg_assert_true(($replies !== false) && ($recount !== false) && ($replies < $recount), 'inside the entry function, after 11.0.31');

	preg_match_all('#// (11\.0\.\d+)\n#', $list, $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.33', true)), 'no other step carries 11.0.33');
	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

function test_comment_record_recount_is_data_only()
{
	$functions = comment_record_recount_test_functions();
	$all = comment_record_recount_test_all();

	foreach (array('upgrade_2026_11_0_comment_record_counts', 'upgrade_2026_11_0_comment_record_counts_commented', 'upgrade_2026_11_0_comment_record_counts_stale', 'upgrade_2026_11_0_comment_record_counts_one', 'upgrade_2026_11_0_comment_record_counts_progress_file', 'upgrade_2026_11_0_comment_record_counts_progress', 'upgrade_2026_11_0_comment_record_counts_progress_save') as $name) {
		pg_assert_true(isset($functions[$name]), $name . ' is found');
	}

	foreach (array('ALTER', 'install_add_', 'install_create_table', 'install_modify', 'CREATE TABLE', 'DROP') as $schema) {
		pg_assert_false(strpos($all, $schema) !== false, 'no schema statement: ' . $schema);
	}
}

function test_comment_record_recount_rebuilds_designed_record_pairs()
{
	$functions = comment_record_recount_test_functions();
	$all = comment_record_recount_test_all();

	pg_assert_contains("pg_comment_refresh_record_info(\$page_id, 'submitted_form', \$item_id)", $all, 'each pair is rebuilt by the moderation helper');
	pg_assert_same(2, substr_count($all, "(page.page_type <> 'form item view')"), 'both walks leave classic record pages alone');
	pg_assert_contains("(comments.item_type = 'submitted_form')", $all, 'record comments only');
	pg_assert_contains('NOT EXISTS', isset($functions['upgrade_2026_11_0_comment_record_counts_stale']) ? $functions['upgrade_2026_11_0_comment_record_counts_stale'] : '', 'the second walk takes counter rows whose comments are gone');
	pg_assert_contains("function_exists('pg_comment_refresh_record_info')", isset($functions['upgrade_2026_11_0_comment_record_counts']) ? $functions['upgrade_2026_11_0_comment_record_counts'] : '', 'an out-of-date module is reported, not run into');
}

// The time check comes after the work of a pair, and the place is saved
// before the pause, so every pass moves on and the next one goes on from it.
function test_comment_record_recount_pauses_with_its_place_saved()
{
	$functions = comment_record_recount_test_functions();
	$one = isset($functions['upgrade_2026_11_0_comment_record_counts_one']) ? $functions['upgrade_2026_11_0_comment_record_counts_one'] : '';

	$rebuild = strpos($one, 'pg_comment_refresh_record_info(');
	$guard = strpos($one, 'if (install_pass_seconds() >= install_pause_budget()) {');
	$save = ($guard !== false) ? strpos($one, 'upgrade_2026_11_0_comment_record_counts_progress_save();', $guard) : false;
	$pause = ($guard !== false) ? strpos($one, 'install_pause(', $guard) : false;

	pg_assert_true(($rebuild !== false) && ($guard !== false) && ($rebuild < $guard), 'the time is asked after the pair');
	pg_assert_true(($save !== false) && ($pause !== false) && ($save < $pause), 'the place is saved before the pause');
	pg_assert_false(strpos(comment_record_recount_test_all(), 'install_pass_spent()') !== false, 'not the statement count, which the helper\'s queries do not raise');
}

function test_comment_record_recount_keeps_its_place_in_a_fresh_file()
{
	$functions = comment_record_recount_test_functions();
	$all = comment_record_recount_test_all();
	$main = isset($functions['upgrade_2026_11_0_comment_record_counts']) ? $functions['upgrade_2026_11_0_comment_record_counts'] : '';
	$progress = isset($functions['upgrade_2026_11_0_comment_record_counts_progress']) ? $functions['upgrade_2026_11_0_comment_record_counts_progress'] : '';

	pg_assert_contains("/data/temp/upgrade_comment_record_counts.json'", $all, 'the place is kept in data/temp');
	pg_assert_contains('< 600', $progress, 'a file older than ten minutes is not read');
	pg_assert_contains("'done' => false", $progress, 'a done marker');
	pg_assert_contains("if (empty(\$progress['done'])) {", $main, 'a finished recount is not run again');
	pg_assert_true(preg_match("/install_note\\([^;]+\\);\\s*\$/", $main) === 1, 'ends with a note for the operator');
}

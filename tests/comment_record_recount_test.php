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
 * done marker in a data/temp file of its own database, read only while it is
 * fresh, and ends with a note. The step needs a database and is run twice in
 * a sandbox; here it is checked in its source, and the file name in a PHP
 * process of its own.
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

	pg_assert_contains("/data/temp/upgrade_comment_record_counts_' . md5(", $all, 'the place is kept in data/temp, one file per database');
	pg_assert_contains('< 600', $progress, 'a file older than ten minutes is not read');
	pg_assert_contains("'done' => false", $progress, 'a done marker');
	pg_assert_contains("if (empty(\$progress['done'])) {", $main, 'a finished recount is not run again');
	pg_assert_true(preg_match("/install_note\\([^;]+\\);\\s*\$/", $main) === 1, 'ends with a note for the operator');
}

// The progress file of the site whose database is $database on $host, from
// a PHP process of its own: the step file is loaded under the upgrade's gate
// constant, and DB_DATABASE and DB_HOST are a site's configuration, which
// the tests after this one must not find defined. Null when PHP cannot be
// started again.
function comment_record_recount_test_file_of($database, $host)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgrecount');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('INSTALL_OR_UPDATE', true);\n"
		. 'define(\'DB_DATABASE\', ' . var_export($database, true) . ");\n"
		. 'define(\'DB_HOST\', ' . var_export($host, true) . ");\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php', true) . ";\n"
		. "echo upgrade_2026_11_0_comment_record_counts_progress_file();\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? implode("\n", $out) : 'error: ' . implode("\n", $out);
}

// Sites that share one software directory (a custom CONFIG_FILE_PATH) share
// its data/temp, so the place and the done marker of one site's recount must
// never be read by another's upgrade: the file is named after the database,
// as the upgrade lock is (install_lock_name()).
function test_comment_record_recount_file_belongs_to_one_database()
{
	$a = comment_record_recount_test_file_of('site_a', 'localhost');

	if ($a === null) {
		return;
	}

	$a_again = comment_record_recount_test_file_of('site_a', 'localhost');
	$b = comment_record_recount_test_file_of('site_b', 'localhost');
	$a_elsewhere = comment_record_recount_test_file_of('site_a', 'db.example.com');

	pg_assert_same(PG_FUNCTIONS_DIR . '/data/temp/upgrade_comment_record_counts_' . md5('site_a@localhost') . '.json', $a, 'named after the database and its host');
	pg_assert_same($a, $a_again, 'the same site finds its own file again');
	pg_assert_true($a !== $b, 'another database on the server has a file of its own');
	pg_assert_true($a !== $a_elsewhere, 'a database of the same name on another server has a file of its own');
}

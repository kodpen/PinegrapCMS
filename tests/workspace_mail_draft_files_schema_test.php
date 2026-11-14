<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that keeps an e-mail draft's files
 * (upgrade_2026_11_0_ws_mail_draft_files(), 11.0.205): called once under a
 * label of its own, and run twice against an in-memory schema in a PHP
 * process of its own (the real runner helpers, a fake db layer): the first
 * run adds ws_mail_items.draft_files, the second writes nothing; without
 * the mail table it writes nothing at all.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_mail_draft_files_step_is_called_once_under_a_label_of_its_own()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_draft_files();"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match('#\tupgrade_2026_11_0_ws_mail_draft_files\(\);\s*// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $own);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_true(isset($own[1]), 'the call carries a label');
	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

// Runs the step twice in a child process. The fake db layer answers the
// runner's probes from an array, applies ADD to it and logs every write. Null when PHP cannot be started again.
function workspace_mail_draft_files_schema_test_run_twice($schema)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmaildraft');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]]);
		}
		return array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^ALTER TABLE `([^`]+)` (ADD|MODIFY) `([^`]+)` ([A-Z]+(?:\([^)]*\))?)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[3]] = strtolower($m[4]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_mail_draft_files();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs, 'schema' => $GLOBALS['fake_schema']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'fake_schema\'] = ' . var_export($schema, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

function test_workspace_mail_draft_files_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_mail_draft_files_schema_test_run_twice(array('ws_mail_items' => array('id' => 'int', 'body_text' => 'mediumtext')));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(array('ALTER TABLE `ws_mail_items` ADD `draft_files` TEXT NULL', 'ALTER TABLE `ws_mail_items` ADD `draft_app_id` INT UNSIGNED NOT NULL DEFAULT 0'), $first,
		'draft_files, NULL on every row that is not a draft with files; draft_app_id, 0 with no backfill');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_same('text', $result['schema']['ws_mail_items']['draft_files']);
	pg_assert_same('int', $result['schema']['ws_mail_items']['draft_app_id']);
}

function test_workspace_mail_draft_files_step_skips_without_the_mail_table()
{
	$result = workspace_mail_draft_files_schema_test_run_twice(array('ws_channels' => array('id' => 'int')));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_same(array(array(), array()), isset($result['runs']) ? $result['runs'] : null, 'nothing written without ws_mail_items');
}

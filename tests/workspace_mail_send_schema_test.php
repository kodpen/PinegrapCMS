<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that gives Workspace mail its send state,
 * Bcc and per-person read marks (upgrade_2026_11_0_ws_mail_send(),
 * 11.0.185): it is called once under its own label, ws_mail_reads is on
 * get_tables(), and run twice against an in-memory schema in a PHP process
 * of its own (the real runner helpers, a fake db layer) the first run adds
 * the four columns and the table while the second writes nothing; with no
 * ws_mail_items it writes nothing at all.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_mail_send_schema_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_send_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = workspace_mail_send_schema_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_send();"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	pg_assert_true(isset($entry[1]), 'the entry function is found');

	preg_match('#\tupgrade_2026_11_0_ws_mail_send\(\);\s*// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $own);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_true(isset($own[1]), 'the call carries a label');
	pg_assert_same(1, count(array_keys($labels[1], isset($own[1]) ? $own[1] : '', true)), 'no other step carries its label');
	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

function test_workspace_mail_send_schema_reads_table_is_on_get_tables()
{
	pg_assert_same(1, substr_count(workspace_mail_send_schema_test_source('install/index.php'), "\t\t'ws_mail_reads',\n"), 'ws_mail_reads is listed once');
}

// Runs the step twice in a child process, with or without ws_mail_items.
// The fake db layer answers the runner's probes from an array, applies
// CREATE TABLE and ADD to it and logs every write. Null when PHP cannot be
// started again.
function workspace_mail_send_schema_test_run_twice($with_items)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmailsend');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = $with_items ? array('ws_mail_items' => array('id' => 'int', 'cc_list' => 'text')) : array();
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
	if (preg_match('/^CREATE TABLE (\w+) \(/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]] = array();
		preg_match_all('/^\s+([a-z_]+)\s+([A-Z]+(?:\([^)]*\))?)/m', $sql, $columns, PREG_SET_ORDER);
		foreach ($columns as $column) {
			$GLOBALS['fake_schema'][$m[1]][$column[1]] = strtolower($column[2]);
		}
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` ([A-Z]+(?:\([^)]*\))?)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_mail_send();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs, 'schema' => $GLOBALS['fake_schema'], 'reads' => isset($GLOBALS['fake_schema']['ws_mail_reads']) ? $GLOBALS['fake_writes'] : null));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$with_items = ' . var_export((bool) $with_items, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['schema'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

function test_workspace_mail_send_schema_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_mail_send_schema_test_run_twice(true);

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(5, count($first), 'four columns and one table');
	pg_assert_same(4, count(preg_grep('/^ALTER TABLE `ws_mail_items` ADD `(bcc_list|send_state|send_error|outbox_id)` /', $first)), 'the four columns');
	pg_assert_same(1, count(preg_grep('/^CREATE TABLE ws_mail_reads \(/', $first)), 'the read marks');
	pg_assert_same(array(), $second, 'the second run writes nothing');

	$schema = $result['schema'];

	pg_assert_same('text', $schema['ws_mail_items']['bcc_list'], 'Bcc as To and Cc');
	pg_assert_same('varchar(8)', $schema['ws_mail_items']['send_state'], 'a state a later value fits without MODIFY');
	pg_assert_same('varchar(500)', $schema['ws_mail_items']['send_error'], 'as long as the queue keeps its error');
	pg_assert_same('int', $schema['ws_mail_items']['outbox_id']);
	pg_assert_same(array('conversation_id', 'user_id', 'item_id'), array_keys($schema['ws_mail_reads']), 'the three columns, nothing more');

	$create = array_values(preg_grep('/^CREATE TABLE ws_mail_reads \(/', $first));

	pg_assert_contains('PRIMARY KEY (conversation_id, user_id)', $create[0]);
	pg_assert_same(1, substr_count($create[0], 'KEY'), 'no index beyond the primary key');
	pg_assert_contains("ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $create[0]);
	pg_assert_contains("send_state` VARCHAR(8) NOT NULL DEFAULT ''", implode("\n", $first), 'empty by default: mail that came in and earlier answers');
}

function test_workspace_mail_send_schema_step_skips_without_the_items_table()
{
	$result = workspace_mail_send_schema_test_run_twice(false);

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_same(array(array(), array()), isset($result['runs']) ? $result['runs'] : null, 'nothing written');
	pg_assert_false(isset($result['schema']['ws_mail_reads']), 'no read marks without mail');
}

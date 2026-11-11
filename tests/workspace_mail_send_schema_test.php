<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that adds what sending mail from the
 * Workspace needs (upgrade_2026_11_0_ws_mail_send(), 11.0.185): it is called
 * once under its own label, ws_mail_reads is on get_tables(), and run twice
 * against an in-memory schema in a PHP process of its own (the real runner
 * helpers, a fake db layer) the first run adds the four ws_mail_items
 * columns and creates ws_mail_reads while the second changes nothing.
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

	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.185', true)), 'no other step carries 11.0.185');
	pg_assert_same(1, preg_match('#\tupgrade_2026_11_0_ws_mail_send\(\);\s+// 11\.0\.185\n#', isset($entry[1]) ? $entry[1] : ''), 'the call carries 11.0.185');
}

function test_workspace_mail_send_schema_reads_table_is_on_get_tables()
{
	$install = workspace_mail_send_schema_test_source('install/index.php');

	pg_assert_same(1, substr_count($install, "\t\t'ws_mail_reads',\n"), 'ws_mail_reads is listed once');
}

// Runs the step twice in a child process. The fake db layer answers the
// runner's probes from an array, applies CREATE TABLE and ADD to it and logs
// every write. Null when PHP cannot be started again.
function workspace_mail_send_schema_test_run_twice()
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

$GLOBALS['fake_schema'] = array(
	'ws_mail_items' => array('id' => 'int', 'direction' => "enum('in','out')", 'created_at' => 'int'),
);
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

echo json_encode(array('runs' => $runs, 'schema' => $GLOBALS['fake_schema']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
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
	$result = workspace_mail_send_schema_test_run_twice();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(5, count($first), 'four columns added and one table created');
	pg_assert_same(1, count(preg_grep('/^CREATE TABLE ws_mail_reads \(/', $first)), 'the read marks table');
	pg_assert_same(array(), $second, 'the second run writes nothing');

	$schema = $result['schema'];

	pg_assert_same('text', $schema['ws_mail_items']['bcc_list'], 'the Bcc list');
	pg_assert_same("enum('','queued','sent','failed')", $schema['ws_mail_items']['send_state'], 'the send state, empty for mail that came in');
	pg_assert_same('varchar(255)', $schema['ws_mail_items']['send_error'], 'the reason a send failed');
	pg_assert_same('int', $schema['ws_mail_items']['outbox_id'], 'the queue row');
	pg_assert_same(array('conversation_id', 'user_id', 'item_id'), array_keys($schema['ws_mail_reads']), 'the read mark columns');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agency summary lines' schema step
 * (upgrade_2026_11_0_launch_summary_lines(), 11.0.274): it is called once
 * under its own label, the table is on get_tables(), it has exactly its
 * columns and keys, and run twice against an in-memory schema the second run
 * writes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function launch_summary_schema_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The step's body, from its function line to the closing brace.
function launch_summary_schema_test_step()
{
	$source = launch_summary_schema_test_source('includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_2026_11_0_launch_summary_lines\(\) \{(.*?)\n\}\n/s', $source, $m);

	return isset($m[1]) ? $m[1] : '';
}

function test_launch_summary_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = launch_summary_schema_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_launch_summary_lines();"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.274', true)), 'no other step carries 11.0.274');
	pg_assert_same(1, preg_match('/upgrade_2026_11_0_launch_summary_lines\(\);\s+\/\/ 11.0.274\n/', $source), 'the label is beside the call');
}

function test_launch_summary_schema_table_is_on_get_tables()
{
	pg_assert_same(1, substr_count(launch_summary_schema_test_source('install/index.php'), "\t\t'launch_summary_lines',\n"), 'listed once');
}

function test_launch_summary_schema_columns_and_keys()
{
	$step = launch_summary_schema_test_step();

	pg_assert_same(1, preg_match('/CREATE TABLE launch_summary_lines \((.*?)\n\t\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"/s', $step, $m), 'created InnoDB utf8mb4_unicode_ci');

	$columns = array();
	$keys = array();

	foreach (explode("\n", trim(isset($m[1]) ? $m[1] : '')) as $line) {
		$line = rtrim(trim($line), ',');

		if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY) /', $line)) {
			$keys[] = $line;
		} elseif (preg_match('/^([a-z_]+)\s+(.+)$/', $line, $c)) {
			$columns[$c[1]] = preg_replace('/\s+/', ' ', $c[2]);
		}
	}

	pg_assert_same(array(
		'id'          => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'line_text'   => "VARCHAR(500) NOT NULL DEFAULT ''",
		'answer'      => 'TEXT NULL',
		'answered_at' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'sort_order'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'created_by'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'updated_at'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
	), $columns, 'columns');

	pg_assert_same(array('PRIMARY KEY (id)', 'KEY idx_order (sort_order)'), $keys, 'keys');

	pg_assert_true(strpos($step, 'install_note(') !== false, 'the step ends with a note for the operator');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no existing table changes');
}

// Runs the step twice in a child process against a fake db layer that
// answers the runner's probes from an array. Null when PHP cannot be
// started again.
function launch_summary_schema_test_run_twice()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pglaunchsum');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = array('user' => array('user_id' => 'int'));
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
		$GLOBALS['fake_schema'][$m[1]] = array('id' => 'int');
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` (.+)$/s', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = $m[3];
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_launch_summary_lines();
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

function test_launch_summary_schema_step_run_twice_changes_nothing_the_second_time()
{
	$result = launch_summary_schema_test_run_twice();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(1, count($first), 'one write on the first run');
	pg_assert_same(1, count(preg_grep('/^CREATE TABLE launch_summary_lines \(/', $first)), 'the table is created');
	pg_assert_same(array(), $second, 'the second run writes nothing');
}

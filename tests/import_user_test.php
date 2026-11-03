<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for imported authors (includes/import/write_user.php): the username
 * an author asks for, when an account counts as taken over, and the undo,
 * which removes the records before their author (newest first) and keeps an
 * author someone enabled by hand. The undo runs in a fresh PHP with the
 * database stubbed, so its stubs never meet another test's functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('pg_user_is_disabled')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/write_user.php');

function test_import_user_username_base()
{
	pg_assert_same('jane', pg_import_user_username_base(array('login' => 'jane', 'email' => 'x@example.com')), 'login');
	pg_assert_same('jane.doe', pg_import_user_username_base(array('login' => 'jane doe!', 'email' => 'jane.doe@example.com')), 'address part');
	pg_assert_same('omer', pg_import_user_username_base(array('login' => 'ömür', 'email' => 'omer@example.com')), 'not plain');
	pg_assert_same('author', pg_import_user_username_base(array('login' => '', 'email' => '')), 'fallback');
}

function test_import_user_claimed()
{
	$waiting = array('user_disabled_at' => 1700000000, 'user_password' => '');

	pg_assert_false(pg_import_user_claimed($waiting, 0), 'still disabled, no credential');
	pg_assert_true(pg_import_user_claimed(array('user_disabled_at' => 0) + $waiting, 0), 'enabled');
	pg_assert_true(pg_import_user_claimed(array('user_password' => 'hash') + $waiting, 0), 'password set');
	pg_assert_true(pg_import_user_claimed($waiting, 1), 'passkey or provider');
}

// Runs pg_import_undo() on one import holding an author (item 1) and a
// record of that author made after it (item 2). Returns the undo's answer,
// the order the rows were removed in and the report, or array('error' =>
// output); null when PHP cannot be started again.
function pg_test_import_user_undo_run($author_disabled_at)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgimpundo');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		return false;
	}
	throw new ErrorException($message, 0, $severity, $file, $line);
});

define('PG_FUNCTIONS_DIR', $pg_dir);

$GLOBALS['items'] = array(
	array('id' => 1, 'kind' => 'user', 'item_id' => 5),
	array('id' => 2, 'kind' => 'record', 'item_id' => 9),
);
$GLOBALS['removed'] = array();
$GLOBALS['report'] = null;

function lang($text) { return is_array($text) ? $text['string'] : $text; }
function e($value) { return addslashes((string) $value); }
function pg_schema_has($table, $column = null) { return false; }
function pg_basename($path) { return basename((string) $path); }
function pg_auth_token_revoke_user($user_id) {}
function pg_user_is_disabled($row) { return (int) ($row['user_disabled_at'] ?? 0) > 0; }

function db_value($sql) {
	if (strpos($sql, 'GET_LOCK') !== false) { return 1; }
	return count($GLOBALS['items']);
}

function db_item($sql) {
	if (strpos($sql, 'FROM imports') !== false) {
		return array('id' => 3, 'kind' => 'wordpress', 'status' => 'done', 'finished_at' => time(), 'file_name' => 'x.xml', 'mapping' => '', 'counts' => '', 'report' => '');
	}
	return array('user_id' => 5, 'user_username' => 'jane', 'user_password' => '', 'user_disabled_at' => $GLOBALS['author_disabled_at']);
}

function db_items($sql) {
	$rows = $GLOBALS['items'];
	usort($rows, function ($a, $b) { return $b['id'] - $a['id']; });
	return array_slice($rows, 0, 100);
}

function db($sql) {
	if (preg_match("/DELETE FROM import_items WHERE id = '(\d+)'/", $sql, $m)) {
		$GLOBALS['items'] = array_values(array_filter($GLOBALS['items'], function ($row) use ($m) { return $row['id'] !== (int) $m[1]; }));
	} elseif (preg_match("/DELETE FROM user WHERE user_id = '(\d+)'/", $sql, $m)) {
		$GLOBALS['removed'][] = 'user:' . $m[1];
	} elseif (preg_match("/^UPDATE imports SET `report` = '(.*)' WHERE/s", $sql, $m)) {
		$GLOBALS['report'] = json_decode(stripslashes($m[1]), true);
	}
	return true;
}

// The record writer as the framework sees it: its delete path only.
function pg_import_write_record($item, $import) { return array('result' => 'failed'); }
function pg_import_unwrite_record($item_id) { $GLOBALS['removed'][] = 'record:' . $item_id; return true; }

require $pg_dir . '/includes/fn/importers.php';

try {
	$answer = pg_import_undo(3, 5);
} catch (Throwable $error) {
	echo get_class($error) . ': ' . $error->getMessage() . ' at line ' . $error->getLine();
	exit(1);
}

echo json_encode(array('answer' => $answer, 'removed' => $GLOBALS['removed'], 'report' => $GLOBALS['report']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'author_disabled_at\'] = ' . (int) $author_disabled_at . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['answer'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The record goes before its author: newest first, so no record is left
// pointing at a removed account.
function test_import_user_undo_removes_records_before_author()
{
	$run = pg_test_import_user_undo_run(1700000000);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	if (isset($run['error'])) {
		pg_assert_same('', $run['error'], 'the undo ran');
		return;
	}

	pg_assert_same(array('record:9', 'user:5'), $run['removed'], 'order');
	pg_assert_same(2, $run['answer']['removed'], 'removed');
	pg_assert_true($run['answer']['done'], 'done');
}

// An author enabled by hand stays; the report names it.
function test_import_user_undo_keeps_enabled_author()
{
	$run = pg_test_import_user_undo_run(0);

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	if (isset($run['error'])) {
		pg_assert_same('', $run['error'], 'the undo ran');
		return;
	}

	pg_assert_same(array('record:9'), $run['removed'], 'only the record');
	pg_assert_same(1, $run['answer']['removed'], 'removed');
	pg_assert_same('kept', $run['report']['lines'][0]['result'] ?? '', 'report line');
	pg_assert_same('jane', $run['report']['lines'][0]['title'] ?? '', 'named');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pick and pack's schema step (upgrade_2026_11_0_pick_pack(),
 * 11.0.260): it is called once under its own label, the four tables are on
 * get_tables(), each table has exactly its columns and keys, and run twice
 * against an in-memory schema the second run writes nothing. Also the
 * "Packing only" right (user.manage_packing): every role-3 ladder file and
 * the sign-in probe know it, the user screens draw and save it, and an
 * order's deletion removes its wave and pack rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pick_pack_schema_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The step's body, from its function line to the closing brace.
function pick_pack_schema_test_step()
{
	$source = pick_pack_schema_test_source('includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_2026_11_0_pick_pack\(\) \{(.*?)\n\}\n/s', $source, $m);

	return isset($m[1]) ? $m[1] : '';
}

// Column name => definition, and the key lines, of one CREATE TABLE in the step.
function pick_pack_schema_test_table($table)
{
	$step = pick_pack_schema_test_step();

	if (!preg_match('/CREATE TABLE ' . $table . ' \((.*?)\n\t\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"/s', $step, $m)) {
		return null;
	}

	$columns = array();
	$keys = array();

	foreach (explode("\n", trim($m[1])) as $line) {
		$line = rtrim(trim($line), ',');

		if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY) /', $line)) {
			$keys[] = $line;
		} elseif (preg_match('/^`?([a-z_]+)`?\s+(.+)$/', $line, $c)) {
			$columns[$c[1]] = preg_replace('/\s+/', ' ', $c[2]);
		}
	}

	return array('columns' => $columns, 'keys' => $keys);
}

function test_pick_pack_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = pick_pack_schema_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_pick_pack();"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.260', true)), 'no other step carries 11.0.260');
	pg_assert_same(1, preg_match('/upgrade_2026_11_0_pick_pack\(\);\s+\/\/ 11\.0\.260\n/', $source), 'the label is beside the call');
}

function test_pick_pack_schema_tables_are_on_get_tables()
{
	$install = pick_pack_schema_test_source('install/index.php');

	foreach (array('product_bins', 'pick_waves', 'pick_wave_orders', 'order_packs') as $table) {
		pg_assert_same(1, substr_count($install, "\t\t'" . $table . "',\n"), $table . ' is listed once');
	}
}

function test_pick_pack_schema_product_bins_columns_and_keys()
{
	$t = pick_pack_schema_test_table('product_bins');

	pg_assert_true($t !== null, 'product_bins is created InnoDB utf8mb4_unicode_ci');

	pg_assert_same(array(
		'product_id'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'location_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'bin'         => "VARCHAR(40) NOT NULL DEFAULT ''",
		'updated_at'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
	), $t['columns'], 'product_bins columns');

	pg_assert_same(array(
		'PRIMARY KEY (product_id, location_id)',
		'KEY idx_location_bin (location_id, bin)',
	), $t['keys'], 'product_bins keys');
}

function test_pick_pack_schema_pick_waves_columns_and_keys()
{
	$t = pick_pack_schema_test_table('pick_waves');

	pg_assert_true($t !== null, 'pick_waves is created InnoDB utf8mb4_unicode_ci');

	pg_assert_same(array(
		'id'          => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'name'        => "VARCHAR(100) NOT NULL DEFAULT ''",
		'location_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'status'      => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'single_item' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'rule'        => 'TEXT NULL',
		'picked'      => 'TEXT NULL',
		'created_by'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'created_at'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'closed_at'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
	), $t['columns'], 'pick_waves columns');

	pg_assert_same(array(
		'PRIMARY KEY (id)',
		'KEY idx_status_created (status, created_at)',
	), $t['keys'], 'pick_waves keys');
}

function test_pick_pack_schema_pick_wave_orders_columns_and_keys()
{
	$t = pick_pack_schema_test_table('pick_wave_orders');

	pg_assert_true($t !== null, 'pick_wave_orders is created InnoDB utf8mb4_unicode_ci');

	pg_assert_same(array(
		'wave_id'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'ship_to_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'order_id'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'ship_by'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'removed_at' => 'INT UNSIGNED NOT NULL DEFAULT 0',
	), $t['columns'], 'pick_wave_orders columns');

	pg_assert_same(array(
		'PRIMARY KEY (wave_id, ship_to_id)',
		'KEY idx_ship_to (ship_to_id)',
		'KEY idx_order (order_id)',
	), $t['keys'], 'pick_wave_orders keys');
}

function test_pick_pack_schema_order_packs_columns_and_keys()
{
	$t = pick_pack_schema_test_table('order_packs');

	pg_assert_true($t !== null, 'order_packs is created InnoDB utf8mb4_unicode_ci');

	pg_assert_same(array(
		'id'         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'order_id'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'ship_to_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'wave_id'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'status'     => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'packer_id'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'started_at' => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'packed_at'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'short_at'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'units'      => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'scanned'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'refused'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
		'lines'      => 'TEXT NULL',
		'missing'    => 'TEXT NULL',
	), $t['columns'], 'order_packs columns');

	pg_assert_same(array(
		'PRIMARY KEY (id)',
		'UNIQUE KEY uniq_ship_to (ship_to_id)',
		'KEY idx_order (order_id)',
		'KEY idx_status_packed (status, packed_at)',
		'KEY idx_packer_packed (packer_id, packed_at)',
		'KEY idx_wave (wave_id)',
	), $t['keys'], 'order_packs keys');

	// LINES is a reserved word: the column is created quoted.
	pg_assert_true(strpos(pick_pack_schema_test_step(), "\t\t`lines`      TEXT NULL,") !== false, 'lines is quoted');
}

function test_pick_pack_schema_step_adds_the_packing_right()
{
	pg_assert_true(
		strpos(pick_pack_schema_test_step(), "install_add_column('user', 'manage_packing', \"TINYINT UNSIGNED NOT NULL DEFAULT 0\");") !== false,
		'user.manage_packing TINYINT UNSIGNED NOT NULL DEFAULT 0');
}

// Runs the step twice in a child process against a fake db layer that
// answers the runner's probes from an array. Null when PHP cannot be
// started again.
function pick_pack_schema_test_run_twice()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpickpack');

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
	upgrade_2026_11_0_pick_pack();
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

function test_pick_pack_schema_step_run_twice_changes_nothing_the_second_time()
{
	$result = pick_pack_schema_test_run_twice();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(5, count($first), 'four tables created and one column added');
	pg_assert_same(4, count(preg_grep('/^CREATE TABLE (product_bins|pick_waves|pick_wave_orders|order_packs) \(/', $first)), 'the four tables');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_true(isset($result['schema']['user']['manage_packing']), 'the right is on user');
}

// The role-3 ladder: a user whose only right is Packing only may use the panel.
function test_pick_pack_packing_right_is_on_every_role_3_ladder()
{
	foreach (array('welcome.php', 'get_page.php', 'includes/fn/content.php', 'set_password.php') as $file) {
		pg_assert_true(strpos(pick_pack_schema_test_source($file), "\$user['manage_packing']") !== false, $file . ' knows manage_packing');
	}

	$reset = pick_pack_schema_test_source('reset_password.php');

	pg_assert_true(strpos($reset, 'pg_user_has_packing_column()') !== false, 'reset_password.php reads the column behind its probe');
	pg_assert_true(strpos($reset, '|| $manage_packing') !== false, 'reset_password.php names the panel login for it');
}

function test_pick_pack_packing_right_has_its_sign_in_probe()
{
	$auth = pick_pack_schema_test_source('includes/authentication.php');

	pg_assert_true(strpos($auth, "function pg_user_has_packing_column()") !== false, 'the probe exists');
	pg_assert_true(strpos($auth, "pg_auth_column_exists('user', 'manage_packing')") !== false, 'the probe asks by exact name');
	pg_assert_same(1, preg_match('/pg_user_has_packing_column\(\)\s*\?\s*" user\.manage_packing,"\s*:\s*" 0 AS manage_packing,"/', $auth), 'the sign-in row names the column only behind the probe');

	$fn = pick_pack_schema_test_source('includes/fn/auth.php');

	pg_assert_true(strpos($fn, '"manage_packing" => $manage_packing,') !== false, 'initialize_user() carries it');
}

function test_pick_pack_packing_right_is_drawn_and_saved_on_the_user_screens()
{
	$ui = pick_pack_schema_test_source('includes/user_permissions.php');

	pg_assert_same(1, preg_match("/'gate_field'\s+=> 'manage_packing',\s+'gate_value'\s+=> '1',\s+'gate_on'\s+=> \\\$value\('manage_packing', '1'\),/", $ui), 'a row of its own with its gate column');
	pg_assert_true(strpos($ui, "isset(\$panels['commerce_switches']) && function_exists('pg_user_has_packing_column') && pg_user_has_packing_column()") !== false, 'drawn only with the store on and the column there');
	pg_assert_true(strpos($ui, "lang('Packing only')") !== false, 'its plain label');

	pg_assert_true(strpos(pick_pack_schema_test_source('edit_user.php'), "'manage_packing'          => (\$manage_packing == 1) ? '1' : '',") !== false, 'the edit screen draws the stored value');

	$write = pick_pack_schema_test_source('includes/fn/user_write.php');

	pg_assert_true(strpos($write, "pg_user_packing_sql('columns', \$fields)") !== false, 'the add path saves it');
	pg_assert_true(strpos($write, "pg_user_packing_sql('set', \$fields)") !== false, 'the edit path saves it');
	pg_assert_true(strpos(pick_pack_schema_test_source('import_users.php'), "pg_user_packing_sql('columns')") !== false, 'the import path saves it');
}

// The posted value as SQL, and nothing before the column exists.
function test_pick_pack_packing_sql_names_the_column_only_when_it_exists()
{
	$auth = pick_pack_schema_test_source('includes/authentication.php');

	preg_match('/function pg_user_packing_sql\(\$mode, \$post = null\)\n\{(.*?)\n\}\n/s', $auth, $m);

	pg_assert_true(isset($m[1]), 'the helper exists');

	if (!isset($m[1])) {
		return;
	}

	foreach (array(true, false) as $exists) {
		$GLOBALS['pick_pack_schema_test_column'] = $exists;
		$fn = 'pick_pack_schema_test_packing_sql_' . ($exists ? 'on' : 'off');

		if (!function_exists($fn)) {
			eval('function ' . $fn . '($mode, $post = null) {' . str_replace('pg_user_has_packing_column()', '$GLOBALS[\'pick_pack_schema_test_column\']', $m[1]) . '}');
		}

		if ($exists) {
			pg_assert_same('manage_packing,', $fn('columns', array()), 'the column');
			pg_assert_same("'1',", $fn('values', array('manage_packing' => '1')), 'ticked');
			pg_assert_same("manage_packing = '0',", $fn('set', array()), 'not ticked clears it');
		} else {
			pg_assert_same('', $fn('columns', array('manage_packing' => '1')), 'nothing before the upgrade');
			pg_assert_same('', $fn('set', array('manage_packing' => '1')), 'nothing before the upgrade');
		}
	}
}

function test_pick_pack_order_deletion_removes_its_wave_and_pack_rows()
{
	$source = pick_pack_schema_test_source('delete_order.php');

	pg_assert_true(strpos($source, "db(\"DELETE FROM pick_wave_orders WHERE order_id = '\" . e(\$order['id']) . \"'\");") !== false, 'its wave rows');
	pg_assert_true(strpos($source, "db(\"DELETE FROM order_packs WHERE order_id = '\" . e(\$order['id']) . \"'\");") !== false, 'its pack rows');
}

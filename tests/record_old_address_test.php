<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the earlier address names of a record
 * (includes/fn/record_old_address.php): which renames keep the old name,
 * the rows a record keeps, the statements a save writes, the lookup that
 * leaves out a record gone or not on the site, and the wiring of the save,
 * the routes, the list filter, the delete, the erasure and the schema step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/record_old_address.php');

function test_record_old_address_kept()
{
	pg_assert_true(pg_record_old_address_kept('travel', 'journeys', true), 'a complete record renamed');
	pg_assert_false(pg_record_old_address_kept('travel', 'journeys', false), 'a draft was never on the site');
	pg_assert_false(pg_record_old_address_kept('', 'journeys', true), 'no earlier name');
	pg_assert_false(pg_record_old_address_kept('  ', 'journeys', true), 'a blank earlier name');
	pg_assert_false(pg_record_old_address_kept('travel', 'travel', true), 'the name did not change');
	pg_assert_false(pg_record_old_address_kept('travel ', 'travel', true), 'only spaces around it changed');
	pg_assert_true(pg_record_old_address_kept('travel', '', true), 'a name taken away keeps the old one');
}

function test_record_old_address_overflow_drops_the_oldest()
{
	pg_assert_same(array(), pg_record_old_address_overflow(array(3, 1, 2), 20), 'under the limit');
	pg_assert_same(array(2, 1), pg_record_old_address_overflow(array('1', '5', '2', '4', '3'), 3), 'the lowest ids go');
	pg_assert_same(array(), pg_record_old_address_overflow(array(), 20), 'no rows');
	pg_assert_same(20, PG_RECORD_OLD_ADDRESS_MAX, 'twenty a record');
}

// Runs the save and the lookup in a PHP process of its own on a fake
// database that records every statement. $ids are the record's row ids the
// trim reads; $row is what the lookup's query answers. Null when PHP cannot
// be started.
function record_old_address_run($old, $new, $complete, array $ids, $row = null)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgoldaddr');

	if ($file === false) {
		return null;
	}

	$script = '<?php' . "\n"
		. 'define("PG_FUNCTIONS_DIR", ' . var_export(PG_FUNCTIONS_DIR, true) . ');' . "\n"
		. '$GLOBALS["args"] = ' . var_export(array($old, $new, $complete), true) . ';' . "\n"
		. '$GLOBALS["ids"] = ' . var_export($ids, true) . ';' . "\n"
		. '$GLOBALS["row"] = ' . var_export($row, true) . ';' . "\n"
		. <<<'PHP'
$GLOBALS['written'] = array();
$GLOBALS['read'] = array();
function e($value) { return addslashes((string) $value); }
function pg_schema_has($table, $column = null) { return $table === 'form_old_address'; }
function db($sql) { $GLOBALS['written'][] = trim(preg_replace('/\s+/', ' ', $sql)); return true; }
function db_values($sql) { return $GLOBALS['ids']; }
function db_item($sql) { $GLOBALS['read'][] = trim(preg_replace('/\s+/', ' ', $sql)); return $GLOBALS['row']; }

require_once(PG_FUNCTIONS_DIR . '/includes/fn/record_old_address.php');

list($old, $new, $complete) = $GLOBALS['args'];
pg_record_old_address_saved(12, 7, $old, $new, $complete);
$found = pg_record_old_address_record(7, 'travel');

echo json_encode(array('written' => $GLOBALS['written'], 'read' => $GLOBALS['read'], 'found' => $found));
PHP;

	file_put_contents($file, $script);
	$out = array();
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out);
	@unlink($file);

	$decoded = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($decoded), 'the fake run answered: ' . implode("\n", $out));

	return is_array($decoded) ? $decoded : null;
}

// A rename keeps the old name once, gives the new one to this record alone
// and drops what is beyond twenty.
function test_record_old_address_saved_on_rename()
{
	$ids = range(1, 22);
	$run = record_old_address_run('travel', 'journeys', true, $ids);

	if ($run === null) {
		return;
	}

	$w = $run['written'];
	pg_assert_same(4, count($w), 'four statements: ' . implode(' | ', $w));
	pg_assert_same("DELETE FROM form_old_address WHERE form_id = '12' AND address_name = 'travel'", $w[0], 'one row per name of a record');
	pg_assert_true(strpos($w[1], "INSERT INTO form_old_address (form_id, page_id, address_name, created_at) VALUES ('12', '7', 'travel', '") === 0, 'the old name kept: ' . $w[1]);
	pg_assert_same("DELETE FROM form_old_address WHERE page_id = '7' AND address_name = 'journeys'", $w[2], 'the live name is nobody\'s old name');
	pg_assert_same("DELETE FROM form_old_address WHERE id IN (2,1)", $w[3], 'the two oldest beyond twenty go');
}

// A draft, or a record that had no name, keeps nothing; its new name still
// clears that name's old rows on the form page.
function test_record_old_address_saved_keeps_nothing_for_a_draft()
{
	$run = record_old_address_run('travel', 'journeys', false, array(1));

	if ($run === null) {
		return;
	}

	pg_assert_same(array("DELETE FROM form_old_address WHERE page_id = '7' AND address_name = 'journeys'"), $run['written'], 'only the live name is cleared');

	$run = record_old_address_run('', '', true, array());
	pg_assert_same(array(), $run['written'], 'no name before or after: nothing');
}

// The lookup joins the record, so a row whose record is gone, a draft or a
// record with no code is never found.
function test_record_old_address_record_lookup()
{
	$run = record_old_address_run('', '', true, array(), array('id' => 12, 'reference_code' => 'ABC', 'address_name' => 'journeys'));

	if ($run === null) {
		return;
	}

	pg_assert_same('journeys', $run['found']['address_name'], 'the name of today');
	$sql = $run['read'][0];
	pg_assert_true(strpos($sql, 'INNER JOIN forms ON forms.id = form_old_address.form_id AND forms.page_id = form_old_address.page_id') !== false, 'joined to its record');
	pg_assert_true(strpos($sql, 'forms.complete = 1') !== false, 'complete records only');
	pg_assert_true(strpos($sql, "form_old_address.address_name = 'travel'") !== false, 'compared under the collation');
	pg_assert_true(strpos($sql, 'ORDER BY form_old_address.id DESC') !== false, 'the newest holder wins');

	$run = record_old_address_run('', '', true, array(), null);
	pg_assert_same(null, $run['found'], 'no record: unknown');
}

// The save, the routes, the list filter, the delete, the erasure and the
// schema step call it.
function test_record_old_address_wiring()
{
	$core = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
	pg_assert_true(strpos($core, "pg_record_old_address_saved(\$submitted_form_id, \$custom_form_page_id, (string)\$record['address_name'], \$address_name, (int)\$record['complete'] === 1)") !== false, 'the save keeps the old name');

	$widgets = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_true(strpos($widgets, 'pg_sw_route_old_record_address($page_id, $item_view[\'cfg\'], $form_page_id, $segment)') !== false, 'the record page sends an old name on');
	pg_assert_true(strpos($widgets, '$record = pg_record_old_address_record($form_page_id, $segment);') !== false, 'a list page sends an old name on');

	$get_page = file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	pg_assert_true(strpos($get_page, 'pg_record_old_address_record($form_list_view[\'custom_form_page_id\'], $address_name)') !== false, 'the classic list page sends an old name on');

	$collections = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/collections.php');
	pg_assert_true(strpos($collections, 'pg_record_old_address_record($page_id, $value, true)') !== false, 'a list filter finds an old name');

	$delete = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');
	pg_assert_true(strpos($delete, 'pg_record_old_address_delete($form_id);') !== false, 'rows go with their record');

	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_true(strpos($migration, 'upgrade_2026_11_0_form_old_address();        // 11.0.279') !== false, 'the step is called');
	pg_assert_true(strpos($migration, 'KEY idx_lookup (page_id, address_name(191))') !== false, 'the lookup key');

	$install = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_true(strpos($install, "'form_old_address',") !== false, 'get_tables() names the table');
}

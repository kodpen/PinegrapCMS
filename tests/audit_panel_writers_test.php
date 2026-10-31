<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel screens that write products and pages outside their
 * editors: the barcode stock screens, Mass Edit (grid save and its Find &
 * Replace card) and Find And Replace each read the row before the write
 * and record one update line after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function audit_panel_writers_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// Every position of $needle in $source, in order.
function audit_panel_writers_test_positions($source, $needle)
{
	$positions = array();
	$offset = 0;
	while (($found = strpos($source, $needle, $offset)) !== false) {
		$positions[] = $found;
		$offset = $found + 1;
	}
	return $positions;
}

// A scan reads the product before its stock UPDATE and records it after.
function test_audit_barcode_stock_screens_record_the_scan()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$source = audit_panel_writers_test_source($file);
		$read = strpos($source, "pg_audit_fetch('products', (int) \$id)");
		$update = strpos($source, 'UPDATE products');
		$record = strpos($source, "pg_audit_record('update', 'products', (int) \$id, \$audit_before)");

		pg_assert_true(($read !== false) && ($update !== false) && ($record !== false), $file . ': read, update and record');
		pg_assert_true(($read < $update) && ($update < $record), $file . ': read before the update, record after it');
		pg_assert_same(1, count(audit_panel_writers_test_positions($source, "pg_audit_record(")), $file . ': one line per scan');
	}
}

// Mass Edit reads each changed page once, before its first field UPDATE, and
// records it once, after its last one: in the Find & Replace card and in the grid save.
function test_audit_mass_edit_records_one_line_per_page()
{
	$file = 'mass_edit.php';
	$source = audit_panel_writers_test_source($file);

	$reads = audit_panel_writers_test_positions($source, "\$audit_before = pg_audit_fetch('page', ");
	$records = audit_panel_writers_test_positions($source, "pg_audit_record('update', 'page', ");
	$updates = audit_panel_writers_test_positions($source, 'db("UPDATE page SET ');

	pg_assert_same(2, count($records), $file . ': one record in the Find & Replace loop, one in the grid loop');
	pg_assert_same(6, count($updates), $file . ': the Find & Replace update and the five grid field updates');
	pg_assert_same(6, count($reads), $file . ': a lazy read before every field update');

	// Each update is preceded by its own lazy read, after the previous update.
	$previous = 0;
	foreach ($updates as $i => $update) {
		pg_assert_true(($reads[$i] > $previous) && ($reads[$i] < $update), $file . ': read before update ' . ($i + 1));
		$previous = $update;
	}
	pg_assert_true(($updates[0] < $records[0]) && ($records[0] < $updates[1]), $file . ': Find & Replace records after its update, before the grid');
	pg_assert_true($updates[5] < $records[1], $file . ': the grid records after its last field');
	pg_assert_same(6, substr_count($source, 'if ($audit_before === null) {'), $file . ': each read is lazy, once per page');
	pg_assert_same(2, substr_count($source, 'if ($audit_before !== null) {'), $file . ': an untouched page writes no line');
}

// Find And Replace records the products it rewrites, one line per product.
function test_audit_find_and_replace_records_products()
{
	$file = 'find_and_replace.php';
	$source = audit_panel_writers_test_source($file);
	$read = strpos($source, "pg_audit_fetch('products', ");
	$update = strpos($source, 'db("UPDATE {$def[\'table\']} SET $sql_set');
	$record = strpos($source, "pg_audit_record('update', 'products', ");

	pg_assert_true(($read !== false) && ($update !== false) && ($record !== false), $file . ': read, update and record');
	pg_assert_true(($read < $update) && ($update < $record), $file . ': read before the update, record after it');
	pg_assert_same(2, substr_count($source, "\$def['table'] === 'products'"), $file . ': only products are traced');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for bulk product drafts in the Workspace (includes/workspace/bulk.php,
 * action create): the rows checked before a card is kept, the 200-row
 * ceiling, and the slice runner that never writes a row twice on resume.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');

// The rows checked against the catalogue as read from source: ws_change_types()
// asks the database which columns there are.
function pg_test_ws_bulk_rows($type, $rows)
{
	$types = pg_test_ws_change_types();

	return ws_bulk_create_rows($type, $rows, $types[$type] ?? array());
}

function pg_test_ws_bulk_create_code($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' exists');

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) - $start : $end - $start);
}

// Only products are made in bulk, and a card takes at most 200 rows.
function test_ws_bulk_create_types_and_ceiling()
{
	pg_assert_same(array('product'), ws_bulk_create_types(), 'products only');
	pg_assert_same(200, WS_BULK_CREATE_MAX, 'the ceiling');
}

// Rows are checked as a single create is: known fields, values in their
// field's shape, a name on each, no name twice in the list.
function test_ws_bulk_create_rows_checks_each_row()
{
	$checked = pg_test_ws_bulk_rows('product', array(
		array('name' => '  Mavi kupa ', 'price' => 12900, 'short_description' => 'Seramik'),
		array('name' => 'Kırmızı kupa', 'price' => '9900', 'enabled' => '1'),
	));

	pg_assert_true($checked['ok'], 'two good rows');
	pg_assert_same(2, count($checked['rows']), 'both kept');
	pg_assert_same('Mavi kupa', $checked['rows'][0]['name'], 'the name trimmed');
	pg_assert_same(12900, $checked['rows'][0]['price'], 'the price in minor units');
	pg_assert_same(true, $checked['rows'][1]['enabled'], 'on sale ticked');

	$no_name = pg_test_ws_bulk_rows('product', array(array('price' => 100)));
	pg_assert_false($no_name['ok'], 'a row without a name');
	pg_assert_contains('rows[0]', $no_name['error'], 'the row is named');

	$twice = pg_test_ws_bulk_rows('product', array(array('name' => 'Kupa'), array('name' => 'kupa ')));
	pg_assert_false($twice['ok'], 'the same name twice');
	pg_assert_contains('rows[1]', $twice['error'], 'the second row is named');

	$unknown = pg_test_ws_bulk_rows('product', array(array('name' => 'Kupa', 'colour' => 'mavi')));
	pg_assert_false($unknown['ok'], 'an unknown field');

	$bad_price = pg_test_ws_bulk_rows('product', array(array('name' => 'Kupa', 'price' => '12.5')));
	pg_assert_false($bad_price['ok'], 'a price that is not minor units');

	pg_assert_false(pg_test_ws_bulk_rows('product', array())['ok'], 'no rows');
	pg_assert_false(pg_test_ws_bulk_rows('product', 'Kupa')['ok'], 'not a list');
	pg_assert_false(pg_test_ws_bulk_rows('page', array(array('name' => 'x')))['ok'], 'a kind not made in bulk');
}

function test_ws_bulk_create_rows_refuses_more_than_the_ceiling()
{
	$rows = array();

	for ($i = 1; $i <= WS_BULK_CREATE_MAX + 1; $i++) {
		$rows[] = array('name' => 'Ürün ' . $i);
	}

	$checked = pg_test_ws_bulk_rows('product', $rows);

	pg_assert_false($checked['ok'], '201 rows');
	pg_assert_contains('import_products.php', $checked['error'], 'the import is named for more');

	pg_assert_true(pg_test_ws_bulk_rows('product', array_slice($rows, 0, WS_BULK_CREATE_MAX))['ok'], '200 rows');
}

// A row's place is kept before it is written, so a slice that dies after a
// write never writes that row again when it is carried on.
function test_ws_bulk_step_claims_a_create_row_before_writing_it()
{
	$code = pg_test_ws_bulk_create_code('ws_bulk_create_row');
	$claim = strpos($code, 'ws_bulk_save_state(');
	$write = strpos($code, 'ws_change_write(');

	pg_assert_true(($claim !== false) && ($write !== false) && ($claim < $write), 'the state is saved before the write');
	pg_assert_contains('ws_change_create_check(', $code, 'checked again at the write: a name taken since');
	pg_assert_contains("ws_change_check(\$viewer, \$type, 'create'", $code, 'and against the rights of the person');
	pg_assert_contains('ws_bulk_create_row(', pg_test_ws_bulk_create_code('ws_bulk_step'), 'the runner writes rows through it');
}

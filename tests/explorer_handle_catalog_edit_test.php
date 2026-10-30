<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's catalog quick edit and commerce access sub-action
 * handlers (includes/explorer/handle_catalog_edit.php): the handlers live
 * there, behind the gate, pg_explorer_handle() calls each one for its
 * sub-action, and the pure parts (a typed price, a typed stock count and a
 * column's width) behave as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// Each sub-action has its own handler, loaded by the explorer's functions and
// called from pg_explorer_handle().
function test_explorer_catalog_edit_handlers_live_in_their_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');

	foreach (array('catalog_quick_edit', 'catalog_access_get', 'catalog_access_set') as $action) {
		$function = 'pg_explorer_handle_' . $action;
		pg_assert_contains('function ' . $function . '(', $handlers, $function);
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_contains("case 'explorer_" . $action . "':\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $function . ' is called by its case');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, 'gate');
	pg_assert_false(strpos($handlers, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');", $explorer, 'loaded by the explorer');
}

// Every access check stays where it was: the catalog right on all three, and
// the write for roles 0-2 only.
function test_explorer_catalog_access_checks_kept()
{
	$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');

	pg_assert_same(3, substr_count($handlers, 'if (pg_catalog_access($user) == false)') + substr_count($handlers, 'if ((pg_catalog_access($user) == false) || ($user[\'role\'] > 2))'), 'each handler asks for the catalog right');
	pg_assert_contains("if ((pg_catalog_access(\$user) == false) || (\$user['role'] > 2)) {", $handlers, 'write is for roles 0-2');
	pg_assert_contains("WHERE (user_id = '\" . e(\$row_user_id) . \"') AND (user_role = '3')", $handlers, 'only basic users are given rights');
}

// A typed price becomes minor units, rounded rather than truncated; empty,
// non-numeric and negative amounts are refused.
function test_explorer_catalog_quick_price()
{
	pg_assert_same(1250, pg_explorer_catalog_quick_price('12.50'), 'dot');
	pg_assert_same(1250, pg_explorer_catalog_quick_price('12,50'), 'comma');
	pg_assert_same(0, pg_explorer_catalog_quick_price('0'), 'free');
	// 19.99 * 100 is 1998.9999...: rounded, where a cast would give 1998.
	pg_assert_same(1999, pg_explorer_catalog_quick_price('19.99'), 'rounded, not truncated');
	pg_assert_same(null, pg_explorer_catalog_quick_price(''), 'empty');
	pg_assert_same(null, pg_explorer_catalog_quick_price('abc'), 'not a number');
	pg_assert_same(null, pg_explorer_catalog_quick_price('-1'), 'negative');
}

// The quick price box reads a typed amount the way the product screen does:
// grouped amounts in either language are accepted, and a single separator
// with three digits after it is read by the panel language.
function test_explorer_catalog_quick_price_reads_grouped_amounts()
{
	pg_assert_same(125000, pg_explorer_catalog_quick_price('1.250,00'), 'Turkish grouped');
	pg_assert_same(125000, pg_explorer_catalog_quick_price('1,250.00'), 'English grouped');
	pg_assert_same(125000, pg_explorer_catalog_quick_price('1250,00'), 'the prefilled value');
	pg_assert_same(null, pg_explorer_catalog_quick_price('1.250.00x'), 'not an amount');

	$decimal = pg_number_separators()['decimal'];
	$thousands = ($decimal === ',') ? '.' : ',';

	pg_assert_same(125000, pg_explorer_catalog_quick_price('1' . $thousands . '250'), 'three digits after the grouping separator');
	pg_assert_same(125, pg_explorer_catalog_quick_price('1' . $decimal . '250'), 'three digits after the decimal separator');
}

// A typed stock count: empty, non-numeric and not whole are refused, a
// grouped figure is read whole, below zero is zero.
function test_explorer_catalog_quick_quantity()
{
	pg_assert_same(7, pg_explorer_catalog_quick_quantity('7'), 'number');
	pg_assert_same(null, pg_explorer_catalog_quick_quantity('3.7'), 'not whole');
	pg_assert_same(null, pg_explorer_catalog_quick_quantity('2,5'), 'not whole, decimal comma');
	pg_assert_same(1200, pg_explorer_catalog_quick_quantity('1.200,00'), 'Turkish grouped');
	pg_assert_same(1200, pg_explorer_catalog_quick_quantity('1,200.00'), 'English grouped');

	$decimal = pg_number_separators()['decimal'];
	$thousands = ($decimal === ',') ? '.' : ',';

	pg_assert_same(1200, pg_explorer_catalog_quick_quantity('1' . $thousands . '200'), 'grouped the panel\'s way');
	pg_assert_same(null, pg_explorer_catalog_quick_quantity('1' . $decimal . '200'), 'three decimals are not whole');
	pg_assert_same(0, pg_explorer_catalog_quick_quantity('-4'), 'not below zero');
	pg_assert_same(null, pg_explorer_catalog_quick_quantity(''), 'empty');
	pg_assert_same(null, pg_explorer_catalog_quick_quantity('many'), 'not a number');
}

// The width a column type declares, or 0 when it declares none.
function test_explorer_column_length()
{
	pg_assert_same(255, pg_explorer_column_length('varchar(255)'), 'varchar');
	pg_assert_same(100, pg_explorer_column_length('char(100)'), 'char');
	pg_assert_same(0, pg_explorer_column_length('text'), 'no width');
	pg_assert_same(0, pg_explorer_column_length(''), 'nothing');
}

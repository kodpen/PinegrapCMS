<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quantities typed on the ERP stock count and return screens:
 * the stock count's scan quantity follows the counted list's whole-pieces
 * rule (includes/erp/stock_counts.php), and the return screen reads each
 * line's quantity with erp_quantity_in() (add_erp_return.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_counts.php');

// An empty box is one piece, a whole figure is taken, anything else is
// refused instead of being cut to a smaller count.
function test_erp_stock_count_scan_quantity()
{
	pg_assert_same(1, erp_stock_count_scan_quantity(''), 'empty is one piece');
	pg_assert_same(1, erp_stock_count_scan_quantity('  '), 'blank is one piece');
	pg_assert_same(0, erp_stock_count_scan_quantity('0'), 'zero is passed on for set');
	pg_assert_same(12, erp_stock_count_scan_quantity(' 12 '), 'trimmed');
	pg_assert_same(1200, erp_stock_count_scan_quantity('1200'), 'whole');

	foreach (array('1.200', '1,200', '2,5', 'abc', '-3', '12345678') as $refused) {
		pg_assert_same(null, erp_stock_count_scan_quantity($refused), 'refused: ' . $refused);
	}
}

// A figure typed on a count's line: empty takes the product off, a whole
// number up to the limit is taken, a bigger one names the limit, anything
// else is not a count.
function test_erp_stock_count_value_refusal()
{
	pg_assert_same(1000000, erp_stock_count_max(), 'one limit');

	foreach (array('', '0', '7', '1000000') as $taken) {
		pg_assert_same('', erp_stock_count_value_refusal($taken), 'taken: ' . $taken);
	}

	foreach (array('1000001', '9999999', '10999998') as $big) {
		pg_assert_same(erp_stock_count_max_error(), erp_stock_count_value_refusal($big), 'above the limit: ' . $big);
	}

	foreach (array('abc', '12,50', '1.200', '-3', '123456789') as $refused) {
		pg_assert_same(lang('A count is a whole number of pieces.'), erp_stock_count_value_refusal($refused), 'not a count: ' . $refused);
	}
}

// The line corrections are all checked before any is written, the reads add
// up only to the limit, and one read above it names the limit.
function test_erp_stock_count_save_checks_before_writing()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/stock_counts.php');
	$start = strpos($source, 'function erp_stock_count_save(');
	$body = substr($source, $start, strpos($source, "\n}", $start) - $start);

	pg_assert_true(strpos($body, 'db(') > strpos($body, 'erp_stock_count_value_refusal('), 'no write before the checks');
	pg_assert_contains('erp_stock_count_line_label($product_id)', $body, 'the refusal names the product');

	$start = strpos($source, 'function erp_stock_count_add(');
	$body = substr($source, $start, strpos($source, "\n}", $start) - $start);

	pg_assert_contains('return $fail(erp_stock_count_max_error());', $body, 'one read above the limit');
	pg_assert_contains('if (($before + $quantity) > $max) {', $body, 'the sum keeps to the limit');
	pg_assert_contains('LEAST(counted + VALUES(counted), ', $body, 'the stored sum is capped');
}

// The scan form refuses a quantity that is not whole on the code field
// before anything is counted.
function test_erp_stock_count_screen_checks_the_scan_quantity()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_stock_count.php');

	pg_assert_contains("\$quantity = erp_stock_count_scan_quantity((string) (\$_POST['quantity'] ?? ''));", $page, 'read by the helper');
	pg_assert_contains("\$liveform->mark_error('code', h(lang('A count is a whole number of pieces.')));", $page, 'refused on the code field');
	pg_assert_false(strpos($page, "(int) \$quantity") !== false, 'no cast of the typed quantity');
}

// The return screen reads quantities as the ERP does, and a negative figure
// still returns nothing.
function test_erp_return_screen_reads_quantities_with_the_erp_reader()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_return.php');

	pg_assert_contains("(strpos(\$raw, '-') !== false)) ? 0 : erp_quantity_in(\$raw);", $page, 'erp_quantity_in, minus skipped');
	pg_assert_false(strpos($page, "str_replace(',', '.'") !== false, 'no comma-to-point replace');
}

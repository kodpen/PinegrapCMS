<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the counter sale's stock limit on a site with stock locations
 * (includes/local_sale.php): the limit and the cart line's "In stock" count
 * the units at the default location, where completion takes the sale, not
 * the total of every location; a site without locations counts the total.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs local_sale.php with the stock location modules in a PHP process of
// their own, against a fake db layer that answers by matching the SQL.
// Null when PHP cannot be started again.
function local_sale_location_stock_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgsaleloc');

	if ($file === false) {
		return $result = null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);

$GLOBALS['fake_locations'] = array();
$GLOBALS['fake_rows'] = array();

function pg_schema_has($table, $column = null) { return true; }

function db_value($sql)
{
	if (preg_match('/SELECT COUNT\(\*\) FROM stock_locations/', $sql)) {
		return count($GLOBALS['fake_locations']);
	}
	return null;
}
function db_item($sql) { return null; }
function db_values($sql) { return array(); }
function db_items($sql)
{
	if (preg_match('/FROM stock_locations/', $sql)) {
		return $GLOBALS['fake_locations'];
	}
	if (preg_match('/FROM product_stock WHERE product_id = \'7\'/', $sql)) {
		return $GLOBALS['fake_rows'];
	}
	return array();
}
function db($sql) { return true; }

function lang($text)
{
	if (is_array($text)) {
		$string = $text['string'];
		foreach (array_values((array) $text['vars']) as $index => $var) {
			$string = str_replace('{var:' . ($index + 1) . '}', (string) $var, $string);
		}
		return $string;
	}
	return $text;
}

require $pg_dir . '/includes/fn/stock_locations.php';
require $pg_dir . '/includes/fn/stock_sales.php';
require $pg_dir . '/includes/local_sale.php';

$product = array('id' => 7, 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 12);
$line = array('id' => 11, 'product_id' => 7, 'quantity' => 5, 'inventory' => 1, 'inventory_quantity' => 12);

$out = array();

// No locations: the total of 12 counts.
$out['plain'] = array(
	'add_5' => local_sale_stock_error($product, 5),
	'complete_5' => local_sale_stock_error($line, 5, true),
	'stock' => local_sale_product_stock($line),
);

// Warehouse (default) holds 2, Shop holds 10: the till sells from the Warehouse.
$GLOBALS['fake_locations'] = array(
	array('id' => 4, 'name' => 'Warehouse', 'enabled' => 1, 'is_default' => 1),
	array('id' => 6, 'name' => 'Shop', 'enabled' => 1, 'is_default' => 0),
);
$GLOBALS['fake_rows'] = array(array('location_id' => 4, 'quantity' => 2), array('location_id' => 6, 'quantity' => 10));
pg_stock_has_locations(true);

$out['located'] = array(
	'add_5' => local_sale_stock_error($product, 5),
	'add_2' => local_sale_stock_error($product, 2),
	'complete_5' => local_sale_stock_error($line, 5, true),
	'stock' => local_sale_product_stock($line),
);

// The default location holds none, the Shop still holds 10.
$GLOBALS['fake_rows'] = array(array('location_id' => 4, 'quantity' => 0), array('location_id' => 6, 'quantity' => 10));
$out['default_empty'] = local_sale_stock_error($product, 1);

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$decoded = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($decoded) || !isset($decoded['plain'])) {
		return $result = array('error' => implode("\n", $lines));
	}

	return $result = $decoded;
}

function local_sale_location_stock_test_result($key)
{
	$result = local_sale_location_stock_test_run();

	if ($result === null) {
		return null;
	}

	pg_assert_true(!isset($result['error']), 'the script ran: ' . ($result['error'] ?? ''));

	return $result[$key] ?? null;
}

function test_local_sale_location_stock_units()
{
	pg_assert_same(12, local_sale_stock_units(12, null), 'no locations: the total');
	pg_assert_same(2, local_sale_stock_units(12, 2), 'with locations: the units there');
	pg_assert_same(0, local_sale_stock_units(12, -3), 'never below 0');
}

function test_local_sale_location_stock_without_locations_counts_the_total()
{
	$plain = local_sale_location_stock_test_result('plain');

	if ($plain === null) {
		return;
	}

	pg_assert_same('', $plain['add_5'], 'adding 5 of 12 is allowed');
	pg_assert_same('', $plain['complete_5'], 'completing 5 of 12 is allowed');
	pg_assert_same(12, $plain['stock'], 'the line shows the total');
}

// Issue #10815: the cart allowed 12 while completion took 2 from the
// default location, leaving the stock 3 units too high.
function test_local_sale_location_stock_counts_the_default_location()
{
	$located = local_sale_location_stock_test_result('located');

	if ($located === null) {
		return;
	}

	pg_assert_same('Only 2 in stock; no more can be added.', $located['add_5'], 'adding 5 is refused: the default holds 2');
	pg_assert_same('', $located['add_2'], 'adding 2 is allowed');
	pg_assert_same('Only 2 in stock; lower the quantity.', $located['complete_5'], 'completing 5 is refused');
	pg_assert_same(2, $located['stock'], 'the line shows the default location\'s units');
	pg_assert_same('This product is out of stock.', local_sale_location_stock_test_result('default_empty'), 'another location\'s units are not sold');
}

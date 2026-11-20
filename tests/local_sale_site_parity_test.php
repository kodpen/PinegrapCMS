<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a counter sale (includes/fn/local_order.php) charges the tax
 * and the total the site's checkout writes for the same cart with the same
 * settings, with the Commerce tax switched on and switched off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Works one cart out through both doors in a PHP process of its own, where
// ECOMMERCE_TAX can be set (the runner has no shop configuration), the store
// stands in a 20% zone and the db layer answers nothing else. The site door
// is the checkout's: each line's rate (pg_order_line_tax_rate()) and the
// lines' taxes (pg_order_line_taxes()) as update_order_item_taxes() writes
// them, run only while the tax is on (submit_order.php), then the total
// submit_order() adds up. The counter door is pg_local_order_lines() and
// local_sale_totals(), what pg_local_order_complete() writes. Null when PHP
// cannot be started again.
function local_sale_site_parity_test_run($tax_on)
{
	static $results = array();

	$key = $tax_on ? 'on' : 'off';

	if (array_key_exists($key, $results)) {
		return $results[$key];
	}

	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return $results[$key] = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpar');

	if ($file === false) {
		return $results[$key] = null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('ECOMMERCE_TAX', $tax_on);
define('DB_CONNECTED', true);

function db_value($sql) { return (strpos($sql, 'FROM countries WHERE default_selected') !== false) ? 'TR' : null; }
function db_item($sql) { return null; }
function db_items($sql) { return array(); }
function db($sql) { return true; }
function get_tax_rate_for_address($country, $state) { return ($country === 'TR') ? 20.0 : false; }
function lang($text) { return is_array($text) ? $text['string'] : $text; }

require $pg_dir . '/includes/fn/ecommerce.php';
require $pg_dir . '/includes/fn/order_completion.php';
require $pg_dir . '/includes/fn/local_order.php';

// A product with its own 10%, one at the zone's rate whose line lands on a
// half minor unit (3 x 7.95 at 20% = 4.77), one not taxable, and a second
// line of the zone-rated product.
$products = array(
	1 => array('price' => 1999, 'quantity' => 2, 'taxable' => 1, 'tax_rate' => '10.000'),
	2 => array('price' => 795, 'quantity' => 3, 'taxable' => 1, 'tax_rate' => null),
	3 => array('price' => 4500, 'quantity' => 1, 'taxable' => 0, 'tax_rate' => null),
	4 => array('price' => 1, 'quantity' => 7, 'taxable' => 1, 'tax_rate' => null),
);

// The site's checkout, a buyer billed in the store's zone.
$zone = get_tax_rate_for_address('TR', '');
$site_lines = array();
$site_taxes = array_fill_keys(array_keys($products), 0);

foreach ($products as $id => $product) {
	$site_lines[$id] = array(
		'amount' => $product['price'] * $product['quantity'],
		'rate' => pg_order_line_tax_rate(pg_tax_switched_on(), $product['taxable'], $product['tax_rate'], $zone),
		'alone' => false,
	);
}

if (ECOMMERCE_TAX == true) {
	$site_taxes = pg_order_line_taxes($site_lines);
}

$site_subtotal = array_sum(array_column($site_lines, 'amount'));
$site_tax = array_sum($site_taxes);

// The counter.
$rows = array();

foreach ($products as $id => $product) {
	$rows[] = $product + array('id' => $id, 'product_id' => $id);
}

$lines = pg_local_order_lines($rows);
$totals = local_sale_totals($lines);
$counter_taxes = array();

foreach ($lines as $line) {
	$counter_taxes[$line['id']] = $line['tax'];
}

echo json_encode(array(
	'site' => array('taxes' => $site_taxes, 'tax' => $site_tax, 'total' => $site_subtotal + $site_tax),
	'counter' => array('taxes' => $counter_taxes, 'tax' => $totals['tax'], 'total' => $totals['gross']),
	'counter_rates' => array_column($lines, 'rate', 'id'),
));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$tax_on = ' . var_export((bool) $tax_on, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$decoded = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($decoded) || !isset($decoded['site'])) {
		return $results[$key] = array('error' => implode("\n", $lines));
	}

	return $results[$key] = $decoded;
}

// With the tax on, both doors take each line's tax per rate on the sum of
// the lines and charge the same total.
function test_local_sale_site_parity_tax_on()
{
	$run = local_sale_site_parity_test_run(true);

	if ($run === null) {
		return;
	}

	pg_assert_true(!isset($run['error']), 'the parity script ran: ' . ($run['error'] ?? ''));

	if (isset($run['error'])) {
		return;
	}

	pg_assert_same($run['site']['taxes'], $run['counter']['taxes'], 'each line\'s tax');
	pg_assert_same($run['site']['tax'], $run['counter']['tax'], 'the tax');
	pg_assert_same($run['site']['total'], $run['counter']['total'], 'the total');

	// 2 x 19.99 at 10% = 4.00; 3 x 7.95 and 7 x 0.01 at 20% on their sum
	// (23.92) = 4.78; the untaxed line none.
	pg_assert_same(878, $run['site']['tax'], 'the figure itself');
	pg_assert_same(array(1 => 10, 2 => 20, 3 => 0, 4 => 20), array_map('intval', $run['counter_rates']), 'the rates');
}

// With the tax off the site charges the price and no tax: 100.00 for a
// 100.00 product. The counter added the store zone's VAT on top.
function test_local_sale_site_parity_tax_off()
{
	$run = local_sale_site_parity_test_run(false);

	if ($run === null) {
		return;
	}

	pg_assert_true(!isset($run['error']), 'the parity script ran: ' . ($run['error'] ?? ''));

	if (isset($run['error'])) {
		return;
	}

	pg_assert_same($run['site']['taxes'], $run['counter']['taxes'], 'each line\'s tax');
	pg_assert_same(0, $run['counter']['tax'], 'no tax');
	pg_assert_same($run['site']['total'], $run['counter']['total'], 'the total');
	pg_assert_same(2 * 1999 + 3 * 795 + 4500 + 7, $run['counter']['total'], 'the prices alone');
	pg_assert_same(array(0, 0, 0, 0), array_values(array_map('intval', $run['counter_rates'])), 'no rate');
}

// The site door the test copies is the checkout's own: the tax only while it
// is on, each line's rate from the shared rule, the taxes per rate. The
// counter writes a line's rate only while the tax is on, as the checkout
// leaves tax_rate unwritten with it off.
function test_local_sale_site_parity_doors_share_the_rule()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$submit = (string) file_get_contents($root . '/submit_order.php');
	$ecommerce = (string) file_get_contents($root . '/includes/fn/ecommerce.php');
	$sale = (string) file_get_contents($root . '/includes/local_sale.php');
	$order = (string) file_get_contents($root . '/includes/fn/local_order.php');

	pg_assert_contains("if (ECOMMERCE_TAX == true) {\n        update_order_item_taxes();", $submit, 'the checkout taxes only with the tax on');
	pg_assert_contains("'rate' => pg_order_line_tax_rate(pg_tax_switched_on(), \$order_item['taxable'], \$order_item['tax_rate'], \$zone_rate),", $ecommerce, 'the checkout\'s rate');
	pg_assert_contains('$taxes = pg_order_line_taxes($lines);', $ecommerce, 'the checkout\'s taxes');
	pg_assert_contains("return pg_order_line_tax_rate(pg_tax_switched_on(), \$item['taxable'] ?? 0, \$item['tax_rate'] ?? null, \$zone_rate);", $sale, 'the counter\'s rate');
	pg_assert_contains("\$with_rate = pg_tax_switched_on() && pg_schema_has('order_items', 'tax_rate');", $order, 'the rate written only with the tax on');
}

function test_local_sale_site_parity_line_tax_rate()
{
	pg_assert_same(0.0, pg_order_line_tax_rate(false, 1, '10.000', 20.0), 'tax off');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 0, '10.000', 20.0), 'not taxable');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, '10.000', false), 'in no zone');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, null, 0), 'a zone of 0');
	pg_assert_same(10.0, pg_order_line_tax_rate(true, 1, '10.000', 20.0), 'the product\'s rate');
	pg_assert_same(20.0, pg_order_line_tax_rate(true, 1, null, 20.0), 'the zone\'s rate');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, '0.000', 20.0), 'a product rate of 0');
	pg_assert_false(pg_tax_switched_on(), 'the runner has no shop configuration');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the writers that work on a product's total while the site keeps
 * stock per location (pg_stock_total_write(), includes/fn/stock_sales.php):
 * a marketplace order, ERP and Workspace change the stock at a location
 * through pg_stock_change(), so a later location write does not undo them;
 * a decrease takes only what the location holds; tracking switched off
 * drops the rows and switched on builds them from the total. A source pin
 * lists every statement that writes products.inventory_quantity, so a new
 * total-only writer is caught.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_total_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

/**
 * The lines of a PHP source that assign products.inventory_quantity in SQL:
 * PHP variables, WHERE conditions and comments left out.
 *
 * @param string $source
 * @return string[]
 */
function stock_total_test_writes($source)
{
	$found = array();

	if (!preg_match_all('/^.*\binventory_quantity\s*=\s*(?!=).*$/m', $source, $matches)) {
		return $found;
	}

	foreach ($matches[0] as $line) {
		if (preg_match('/\$\w*inventory_quantity\s*=|\[\s*[\'"]inventory_quantity[\'"]\s*\]\s*=|\b(WHERE|AND|OR|IF\(|WHEN)\s+(\(?\s*)?(products\.|p\.)?inventory_quantity\s*=/i', $line)) {
			continue;
		}

		if (preg_match('/^\s*(\*|\/\/)/', $line)) {
			continue;
		}

		$found[] = trim($line);
	}

	return $found;
}

function test_stock_total_issue_case_ends_at_eight()
{
	// 10 units at the default location (7); a marketplace order takes 3,
	// then a barcode scan counts one in through the writer.
	$rows = array(7 => 10);

	$take = pg_stock_plan($rows, 7, pg_stock_total_delta('adjust', -3, $rows[7], array_sum($rows)));
	pg_assert_true($take['ok'], 'the take is planned');
	pg_assert_same(7, $take['total'], 'the total after the order');
	pg_assert_same(7, $take['rows'][7], 'and the location row with it');

	$scan = pg_stock_plan($take['rows'], 7, 1);
	pg_assert_same(8, $scan['total'], 'the next location write ends at 8, not 11');
}

function test_stock_total_decrease_takes_only_what_the_location_holds()
{
	pg_assert_same(-3, pg_stock_total_delta('adjust', -3, 10, 10), 'adjust down');
	pg_assert_same(-2, pg_stock_total_delta('adjust', -5, 2, 9), 'down to 0 at the location, nothing from another');
	pg_assert_same(0, pg_stock_total_delta('adjust', -4, 0, 6), 'an empty location gives nothing');
	pg_assert_same(4, pg_stock_total_delta('adjust', 4, 0, 6), 'an increase lands whole');
	pg_assert_same(3, pg_stock_total_delta('set', 10, 2, 7), 'set up: the difference to the total');
	pg_assert_same(-2, pg_stock_total_delta('set', 4, 2, 7), 'set down: what the location holds');
	pg_assert_same(-2, pg_stock_total_delta('set', -1, 2, 2), 'a set below 0 is 0');

	// Two locations: an order taking 3 at the default (2 there) leaves the
	// shop's 5 alone.
	$rows = array(7 => 2, 3 => 5);
	$plan = pg_stock_plan($rows, 7, pg_stock_total_delta('adjust', -3, $rows[7], array_sum($rows)));
	pg_assert_same(array(7 => 0, 3 => 5), $plan['rows'], 'the other location is not touched');
	pg_assert_same(5, $plan['total']);
}

function test_stock_total_tracking_off_then_on_rebuilds_from_the_total()
{
	$model = stock_total_test_source('includes/fn/stock_locations.php');
	$sync = substr($model, (int) strpos($model, 'function pg_stock_tracking_sync('));
	$sync = substr($sync, 0, (int) strpos($sync, "\n}\n"));

	pg_assert_contains("WHERE (product_stock.product_id IN (\" . \$list . \")) AND (products.inventory <> '1')\");", $sync, 'tracking off: the rows go');
	pg_assert_contains('pg_stock_seed_product((int) $product_id, pg_stock_default_location());', $sync, 'tracking on: the default row from the total');
	pg_assert_contains('if (!$ids || !pg_stock_has_locations()) {', $sync, 'nothing without locations');

	// With no rows left the product holds its total at the default, so the
	// first change after switching on starts from the total, not old rows.
	pg_assert_same(6, pg_stock_available_at(array(), 7, 7, 6), 'the total at the default');
	pg_assert_same(0, pg_stock_available_at(array(), 3, 7, 6), 'nothing elsewhere');

	$callers = array(
		'product_builder.php'                       => 'pg_stock_tracking_sync(array($product_id));',
		'edit_products.php'                         => 'pg_stock_tracking_sync(array((int) $product_id));',
		'includes/explorer/handle_products_bulk.php' => 'pg_stock_tracking_sync(array((int) $product_id));',
		'includes/api/resources/products.php'       => 'pg_stock_tracking_sync(array($id));',
		'import_products.php'                       => 'pg_stock_tracking_sync(array($product_id));',
		'includes/workspace/changes.php'            => "pg_stock_tracking_sync(array((int) \$record['id']));",
	);

	foreach ($callers as $file => $call) {
		pg_assert_contains($call, stock_total_test_source($file), $file . ' keeps the rows in line with tracking');
	}
}

function test_stock_total_writer_answers_null_without_locations()
{
	$sales = stock_total_test_source('includes/fn/stock_sales.php');
	$helper = substr($sales, (int) strpos($sales, 'function pg_stock_total_write('));
	$helper = substr($helper, 0, (int) strpos($helper, "\n}\n"));

	pg_assert_contains("if (!pg_stock_has_locations()) {\n        return null;", $helper, 'no locations: the caller writes as before');
	pg_assert_contains("!== '1') {\n        return null;", $helper, 'an untracked product has no rows: the caller writes as before');
	pg_assert_contains('return pg_stock_change_from_row($product_id, $location_id,', $helper, 'with locations: through the writer');
	pg_assert_contains('pg_stock_total_delta($op, $quantity, $at_location, $total)', $helper);
}

function test_stock_total_writer_has_no_gap_repair()
{
	$model = stock_total_test_source('includes/fn/stock_locations.php');
	$writer = substr($model, (int) strpos($model, 'function pg_stock_change('));
	$writer = substr($writer, 0, (int) strpos($writer, "\n}\n"));

	pg_assert_same(array(), stock_total_test_writes($writer), 'the writer sets the total only by the sum statement');
	pg_assert_contains('db(pg_stock_sum_sql($product_id));', $writer);
}

function test_stock_total_routed_writers_keep_their_old_write_without_locations()
{
	$routed = array(
		'includes/api/outbound/orders.php' => array(
			"pg_stock_total_write(\$product_id, 'adjust', -\$quantity, 0, 'marketplace', \$order_ref);",
			"pg_stock_total_write(\$product_id, 'adjust', \$units, 0, 'cancel', \$order_ref);",
		),
		'includes/erp/stock.php' => array(
			"pg_stock_total_write((int) \$move['product_id'], 'adjust', \$delta, 0, 'erp', 'erp:' . (int) \$move['id']);",
		),
		'includes/erp/stock_counts.php' => array(
			"pg_stock_total_write((int) \$item['product_id'], 'set', \$counted, 0, 'erp', 'erp_count:' . (int) \$count_id,",
		),
		'includes/workspace/changes.php' => array(
			"pg_stock_total_write((int) \$record['id'], 'set', \$quantity, 0, 'workspace', 'workspace');",
		),
	);

	foreach ($routed as $file => $calls) {
		$source = stock_total_test_source($file);

		foreach ($calls as $call) {
			pg_assert_contains($call, $source, $file . ' goes through the location');
		}

		// Every total write in the file sits behind "$written === null".
		foreach (stock_total_test_writes($source) as $line) {
			$end = (int) strpos($source, $line) + strlen($line);
			$guard = strrpos(substr($source, 0, $end), '$written === null');
			pg_assert_true(($guard !== false) && ($end - (int) $guard < 700), $file . ': the total write is the no-locations branch: ' . $line);
		}
	}
}

function test_stock_total_bundle_return_goes_back_to_the_order_location()
{
	$bundles = stock_total_test_source('includes/fn/bundles.php');
	$return = substr($bundles, (int) strpos($bundles, 'function pg_bundle_return_stock('));
	$return = substr($return, 0, (int) strpos($return, "\n}\n"));

	pg_assert_contains('$location_id = pg_stock_order_item_location($order_item_id);', $return, 'the location saved on the order');
	pg_assert_contains("pg_stock_total_write(\$product_id, 'adjust', \$units, \$location_id, 'return',", $return, 'through the writer');

	$writes = stock_total_test_writes($return);
	pg_assert_same(1, count($writes), 'one total write, for a site without locations');

	foreach ($writes as $line) {
		$end = (int) strpos($return, $line) + strlen($line);
		$guard = strrpos(substr($return, 0, $end), 'if ($written === null) {');
		pg_assert_true(($guard !== false) && ($end - (int) $guard < 300), 'the total write is the no-locations branch: ' . $line);
	}

	$sales = stock_total_test_source('includes/fn/stock_sales.php');
	$pick = substr($sales, (int) strpos($sales, 'function pg_stock_order_item_location('));
	$pick = substr($pick, 0, (int) strpos($pick, "\n}\n"));

	pg_assert_contains('SELECT orders.stock_location_id', $pick, 'read from the order');
	pg_assert_contains('return $stored ? (int) pg_stock_resolve_location(pg_stock_locations(), $stored) : 0;', $pick, 'a location that is gone falls back to the default');
}

/**
 * Every file whose SQL writes products.inventory_quantity, with the number
 * of such statements. A writer that changes the total while the site has
 * locations undoes itself at the next location write: a new one goes
 * through pg_stock_total_write() or pg_stock_change(), keeps its own
 * statement for the no-locations branch, and is added here.
 */
function test_stock_total_source_pin_of_every_total_writer()
{
	$reviewed = array(
		// The scan screens: the total only when pg_stock_scan() answers null.
		'barcode_decrease_inventory.php'             => 1,
		'barcode_increase_inventory.php'             => 1,
		// Marketplace take and cancellation: pg_stock_total_write().
		'includes/api/outbound/orders.php'           => 2,
		// The inventory API: pg_stock_change_from_row() with locations.
		'includes/api/resources/inventory.php'       => 1,
		// ERP documents and counts: pg_stock_total_write().
		'includes/erp/stock.php'                     => 1,
		'includes/erp/stock_counts.php'              => 1,
		// The quick stock cell is refused with locations.
		'includes/explorer/handle_catalog_edit.php'  => 1,
		// The bulk edit writes by location when the site has them.
		'includes/explorer/handle_products_bulk.php' => 3,
		// A bundle's cached stock (worked out from its components), its
		// components' take (gated by pg_stock_sale_take()) and their return
		// (gated by pg_stock_total_write()).
		'includes/fn/bundles.php'                    => 3,
		// Completion: the total only when pg_stock_sale_take() answers null.
		'includes/fn/order_completion.php'           => 1,
		// The import's untracked product, which has no rows.
		'includes/fn/stock_location_screens.php'     => 1,
		// The writer itself.
		'includes/fn/stock_locations.php'            => 3,
		// Workspace stock: pg_stock_total_write().
		'includes/workspace/changes.php'             => 1,
	);

	$found = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		$path = str_replace('\\', '/', $file->getPathname());

		if ((substr($path, -4) !== '.php') || (strpos($path, '/vendor/') !== false)) {
			continue;
		}

		$writes = stock_total_test_writes((string) file_get_contents($path));

		if ($writes) {
			$found[substr($path, strlen(str_replace('\\', '/', PG_FUNCTIONS_DIR)) + 1)] = count($writes);
		}
	}

	ksort($found);
	ksort($reviewed);

	pg_assert_same($reviewed, $found, 'a new statement on products.inventory_quantity goes through pg_stock_total_write()');
}

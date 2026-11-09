<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pre-order of a product (2026.11.0, 11.0.151):
 * products.preorder and products.preorder_date, their upgrade step, the
 * date and label rules (includes/fn/preorder.php) and the readers that
 * carry the note.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_preorder_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function product_preorder_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The step is called once with its label and adds both columns with the
// idempotent helper, so a second run changes nothing.
function test_product_preorder_step()
{
	$source = product_preorder_test_source('includes/migrations/2026.11.0.php');
	$entry = product_preorder_test_body($source, 'upgrade_to_2026_11_0');
	$step = product_preorder_test_body($source, 'upgrade_2026_11_0_product_preorder');

	pg_assert_true(preg_match('/upgrade_2026_11_0_product_preorder\(\);\s+\/\/ 11\.0\.151\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.151\b/', $entry), 'the label is used once');
	pg_assert_contains("install_add_column('products', 'preorder', \"TINYINT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("install_add_column('products', 'preorder_date', \"DATE NULL DEFAULT NULL\")", $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'db(') !== false, 'no raw statement');
}

function test_product_preorder_ready_probe()
{
	$body = product_preorder_test_body(product_preorder_test_source('includes/fn/preorder.php'), 'pg_product_preorder_ready');

	pg_assert_contains("pg_schema_has('products', 'preorder')", $body);
	pg_assert_contains("pg_schema_has('products', 'preorder_date')", $body);
	pg_assert_false(pg_product_preorder_ready(), 'no database in the test run');
}

function test_product_preorder_select_sql()
{
	pg_assert_same(', products.preorder, products.preorder_date', pg_preorder_select_sql('products', true));
	pg_assert_same(', p.preorder, p.preorder_date', pg_preorder_select_sql('p', true));
	pg_assert_same(', 0 AS preorder, NULL AS preorder_date', pg_preorder_select_sql('products', false), 'before the upgrade');
	pg_assert_same(', x.preorder, x.preorder_date', pg_preorder_select_sql("x'; --", true), 'only a name');
}

function test_product_preorder_date_parse()
{
	pg_assert_same('', pg_preorder_date_parse(''));
	pg_assert_same('', pg_preorder_date_parse(null));
	pg_assert_same('', pg_preorder_date_parse('0000-00-00'), 'the zero date is none');
	pg_assert_same('2026-12-01', pg_preorder_date_parse(' 2026-12-01 '));
	pg_assert_same('2028-02-29', pg_preorder_date_parse('2028-02-29'), 'a leap day');

	pg_assert_same(false, pg_preorder_date_parse('2027-02-29'), 'no leap day');
	pg_assert_same(false, pg_preorder_date_parse('2026-13-01'));
	pg_assert_same(false, pg_preorder_date_parse('01.12.2026'), 'only ISO');
	pg_assert_same(false, pg_preorder_date_parse('2026-12-01 10:00'));
	pg_assert_same(false, pg_preorder_date_parse(array('2026-12-01')));
	pg_assert_same(false, pg_preorder_date_parse(20261201));
}

function test_product_preorder_problem()
{
	$today = '2026-11-02';

	pg_assert_same('', pg_preorder_problem(false, 'nonsense', $today), 'off: the date is not read');
	pg_assert_same('', pg_preorder_problem(true, '2026-11-03', $today));
	pg_assert_same('A pre-order needs the date the stock is expected.', pg_preorder_problem(true, '', $today));
	pg_assert_same('The pre-order date must be after today.', pg_preorder_problem(true, '2026-11-02', $today), 'today is not after today');
	pg_assert_same('The pre-order date must be after today.', pg_preorder_problem(true, '2026-10-30', $today));
	pg_assert_same('The pre-order date is not a valid date.', pg_preorder_problem(true, '2026-02-30', $today));
}

// Turning pre-order on turns the back order on; off or unreadable stores none.
function test_product_preorder_from_post()
{
	pg_assert_same(array('preorder' => 1, 'preorder_date' => '2026-12-01', 'backorder' => 1),
		pg_preorder_from_post(array('preorder' => '1', 'preorder_date' => '2026-12-01')));
	pg_assert_same(array('preorder' => 0, 'preorder_date' => null), pg_preorder_from_post(array('preorder_date' => '2026-12-01')), 'off');
	pg_assert_same(array('preorder' => 0, 'preorder_date' => null), pg_preorder_from_post(array('preorder' => '1', 'preorder_date' => 'x')), 'never stored as typed');
	pg_assert_same(array('preorder' => 0, 'preorder_date' => null), pg_preorder_from_post(array()));
}

function test_product_preorder_active()
{
	$today = '2026-11-02';
	$row = array('preorder' => '1', 'preorder_date' => '2026-12-01', 'inventory' => '1', 'inventory_quantity' => '0');

	pg_assert_true(pg_preorder_active($row, $today));
	pg_assert_same('2026-12-01', pg_preorder_active_date($row, $today));
	pg_assert_false(pg_preorder_active(array('preorder' => '0') + $row, $today), 'flag off');
	pg_assert_false(pg_preorder_active(array('preorder_date' => '2026-11-02') + $row, $today), 'ends on the date');
	pg_assert_false(pg_preorder_active(array('preorder_date' => null) + $row, $today), 'no date');
	pg_assert_false(pg_preorder_active(array('inventory_quantity' => '3') + $row, $today), 'stock is in');
	pg_assert_true(pg_preorder_active(array('inventory' => '0', 'inventory_quantity' => '3') + $row, $today), 'untracked: the quantity means nothing');
	pg_assert_true(pg_preorder_active(array('inventory_quantity' => '-2') + $row, $today), 'oversold');
	pg_assert_false(pg_preorder_active(null, $today));
	pg_assert_same('', pg_preorder_active_date(array('preorder' => '0') + $row, $today));
}

function test_product_preorder_tokens_and_label()
{
	$today = '2026-11-02';
	$row = array('preorder' => '1', 'preorder_date' => '2026-12-01', 'inventory' => '0');

	$tokens = pg_preorder_tokens($row, $today);
	pg_assert_same('1', $tokens['__preorder']);
	pg_assert_true($tokens['__preorder_date'] !== '', 'the date is printed');
	pg_assert_contains($tokens['__preorder_date'], $tokens['__preorder_label']);
	pg_assert_contains('Pre-order', $tokens['__preorder_label']);

	pg_assert_same(array('__preorder' => '0', '__preorder_date' => '', '__preorder_label' => ''), pg_preorder_tokens(array('preorder' => '0'), $today));
	pg_assert_same('', pg_preorder_label(array('preorder' => '1', 'preorder_date' => '2026-10-01'), $today), 'ended');
}

// The latest running date among the lines bounds the arrival date.
function test_product_preorder_earliest_arrival()
{
	$today = '2026-11-02';
	$rows = array(
		array('preorder' => '1', 'preorder_date' => '2026-12-01'),
		array('preorder' => '1', 'preorder_date' => '2027-01-15'),
		array('preorder' => '1', 'preorder_date' => '2027-03-01', 'inventory' => '1', 'inventory_quantity' => '4'),
		array('preorder' => '0', 'preorder_date' => '2028-01-01'),
	);

	pg_assert_same('2027-01-15', pg_preorder_earliest_arrival($rows, $today));
	pg_assert_same('', pg_preorder_earliest_arrival(array(), $today));
	pg_assert_same('', pg_preorder_earliest_arrival(array(array('preorder' => '1', 'preorder_date' => '2026-11-01')), $today));
}

function test_product_preorder_arrival_problem()
{
	pg_assert_same('', pg_preorder_arrival_problem('2026-12-01', ''), 'no pre-order');
	pg_assert_same('', pg_preorder_arrival_problem('2026-12-01', '2026-12-01'), 'the day itself');
	pg_assert_same('', pg_preorder_arrival_problem('2026-12-05', '2026-12-01'));
	pg_assert_contains('{var:1}', pg_preorder_arrival_problem('2026-11-30', '2026-12-01'));
	pg_assert_same('', pg_preorder_arrival_problem('', '2026-12-01'), 'no date asked');
}

function test_product_preorder_end_sql()
{
	$sql = pg_preorder_end_sql('2026-11-02', 500);

	pg_assert_contains('SET preorder = 0', $sql);
	pg_assert_contains("preorder_date <= '2026-11-02'", $sql);
	pg_assert_contains('inventory = 1 AND inventory_quantity > 0', $sql);
	pg_assert_contains('LIMIT 500', $sql);
	pg_assert_false(strpos($sql, 'backorder') !== false, 'the back order stays');
	pg_assert_contains("'1970-01-01'", pg_preorder_end_sql("x' OR 1", 10), 'only a date');
}

// The product screen saves both behind the probe, refuses a bad pre-order
// before any write and draws the switch beside the back order one.
function test_product_preorder_product_screen()
{
	$source = product_preorder_test_source('product_builder.php');

	pg_assert_contains('$common = array_merge($common, pg_preorder_from_post($_POST));', $source);
	pg_assert_contains('$preorder_problem = pg_preorder_problem(', $source);
	pg_assert_contains("pg_pb_render_preorder_row(\$v)", $source);

	$row = product_preorder_test_body($source, 'pg_pb_render_preorder_row');
	pg_assert_contains('if (!pg_product_preorder_ready())', $row);
	pg_assert_contains('type="date" data-pg-date name="preorder_date"', $row, 'the date is on the date-and-time control');
}

// Every reader takes the columns through the probe-aware select and prints
// the label through the shared rule.
function test_product_preorder_readers()
{
	$readers = array(
		'includes/fn/widgets_catalog.php'      => 'pg_preorder_tokens($p,',
		'includes/fn/widgets_cart.php'         => "\$row_values['__item_preorder_label'] = h(pg_preorder_label(",
		'includes/fn/widgets.php'              => "'__item_preorder_label'    => h(\$item['preorder_label'])",
		'get_order_receipt_in_plain_text.php'  => '$preorder_label = pg_preorder_label($order_item,',
		'includes/api/resources/products.php'  => "'preorder_date'     => api_product_preorder_date(\$row)",
	);

	foreach ($readers as $file => $needle) {
		$source = product_preorder_test_source($file);
		pg_assert_contains($needle, $source, $file);
		pg_assert_contains('pg_preorder_select_sql(', $source, $file . ' reads the columns through the probe');
	}

	pg_assert_contains("pg_preorder_arrival_problem(\$arrival_date, \$preorder_from)", product_preorder_test_source('shipping.php'));
	pg_assert_contains('pg_preorder_end_hourly();', product_preorder_test_source('job.php'));
}

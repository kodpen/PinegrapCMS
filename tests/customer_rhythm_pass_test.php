<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for customer rhythm's nightly pass
 * (includes/fn/customer_rhythm_pass.php): the walk's state, one customer's
 * row and its moves, the write statement, the product summary, the privacy
 * registrations of customer_rhythm and the general job's hook.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _crp_test_now()
{
	return 1790000000;
}

// An order row as pg_customer_value_page_query() reads it.
function _crp_test_order($id, $days_ago, $email, $extra = array())
{
	return array_merge(array(
		'id' => $id,
		'order_date' => _crp_test_now() - ($days_ago * 86400),
		'order_number' => 1000 + $id,
		'billing_email_address' => $email,
		'contact_id' => 0,
		'total' => 5000,
		'currency_code' => 'TRY',
		'status' => 'complete',
		'refunded_amount' => 0,
		'type' => 'online',
		'cancelled_at' => null,
	), $extra);
}

// A walk over some orders, as the pass keeps it.
function _crp_test_walk($orders, $items = array())
{
	$state = pg_customer_rhythm_pass_state_new(_crp_test_now());
	pg_customer_rhythm_pass_add_rows($state, $orders, $items);

	return $state;
}

// The store's figures with a fixed curve and no product rhythm.
function _crp_test_store($curve = null)
{
	return array('median' => 30.0, 'curve' => ($curve === null) ? array('fixed' => true, 'bands' => array()) : $curve, 'products' => array());
}

// A stored row as the write phase reads it back.
function _crp_test_stored($stage, $interval, $last_days_ago, $updated_days_ago, $extra = array())
{
	return array_merge(array(
		'customer_key' => 'eabc',
		'contact_id' => 0,
		'stage' => $stage,
		'stage_before' => 0,
		'stage_at' => _crp_test_now() - (40 * 86400),
		'interval_days' => $interval,
		'last_order_at' => _crp_test_now() - ($last_days_ago * 86400),
		'updated_at' => _crp_test_now() - ($updated_days_ago * 86400),
	), $extra);
}

function _crp_test_row($orders, $stored, $store = null, $lines = array())
{
	return pg_customer_rhythm_pass_row('eabc', $orders, $lines, $stored, ($store === null) ? _crp_test_store() : $store, array(), _crp_test_now());
}

// One order as the walk keeps it.
function _crp_test_packed_order($time, $amount, $id, $contact, $market)
{
	return pack('VVVVC', (int) $time, $amount, $id, $contact, $market);
}

// One line as the walk keeps it.
function _crp_test_packed_line($product, $quantity, $time, $market)
{
	return pack('VVVC', $product, $quantity, (int) $time, $market);
}

// Two orders of one customer, the interval between them in days.
function _crp_test_two_orders($interval, $last_days_ago)
{
	return pg_customer_rhythm_pass_orders(
		_crp_test_packed_order(_crp_test_now() - (($last_days_ago + $interval) * 86400), 5000, 1, 0, 0)
		. _crp_test_packed_order(_crp_test_now() - ($last_days_ago * 86400), 7000, 2, 0, 0)
	);
}

// The codes are those of the schema decision: 0 none to 6 Won back.
function test_customer_rhythm_pass_stage_codes()
{
	pg_assert_same(array('' => 0, 'regular' => 1, 'due' => 2, 'late' => 3, 'slipping' => 4, 'lost' => 5, 'won_back' => 6), pg_customer_rhythm_stage_codes());

	foreach (pg_customer_rhythm_stage_codes() as $name => $code) {
		pg_assert_same($code, pg_customer_rhythm_stage_code($name));
		pg_assert_same($name, pg_customer_rhythm_stage_name($code));
	}

	pg_assert_same('', pg_customer_rhythm_stage_name(9), 'an unknown code is no stage');
}

// The walk keeps only counting orders of a placed customer, marks a
// marketplace order and keeps each order's lines; the place moves on.
function test_customer_rhythm_pass_walk_keeps_counting_orders()
{
	$state = _crp_test_walk(array(
		_crp_test_order(1, 50, 'Ayse@Example.com', array('contact_id' => 7)),
		_crp_test_order(2, 40, 'ayse@example.com', array('cancelled_at' => 123)),
		_crp_test_order(3, 30, 'ayse@example.com', array('refunded_amount' => 5000)),
		_crp_test_order(4, 20, '', array('contact_id' => 0)),
		_crp_test_order(5, 10, 'ayse@example.com', array('type' => 'marketplace', 'total' => 900)),
	), array(1 => array(array(11, 2), array(0, 1)), 5 => array(array(11, 1))));

	$key = pg_customer_value_key(array('billing_email_address' => 'ayse@example.com'));

	pg_assert_same(array($key), array_keys($state['c']), 'one customer, the unplaced order left out');
	pg_assert_same(2, count(pg_customer_rhythm_pass_orders($state['c'][$key])), 'cancelled and fully refunded orders left out');
	pg_assert_same(5, $state['done']);
	pg_assert_same(5, $state['id']);

	$orders = pg_customer_rhythm_pass_orders($state['c'][$key]);
	pg_assert_same(7, $orders[0]['contact_id']);
	pg_assert_same('marketplace', $orders[1]['type']);

	$lines = pg_customer_rhythm_pass_lines($key, $state['l'][$key]);
	pg_assert_same(2, count($lines), 'a line with no product is left out');
	pg_assert_same(array(11, 2, 'online'), array($lines[0]['product_id'], $lines[0]['quantity'], $lines[0]['type']));
	pg_assert_same('marketplace', $lines[1]['type']);
}

// A buyer known only from marketplace orders gets no row.
function test_customer_rhythm_pass_marketplace_buyer_has_no_row()
{
	$orders = pg_customer_rhythm_pass_orders(_crp_test_packed_order(_crp_test_now() - 86400 * 20, 5000, 1, 0, 1) . _crp_test_packed_order(_crp_test_now() - 86400 * 5, 5000, 2, 0, 1));

	pg_assert_same(null, _crp_test_row($orders, null));
}

// A first row is no move: its stage is set with its time, nothing before it.
function test_customer_rhythm_pass_new_row_is_not_a_move()
{
	$made = _crp_test_row(_crp_test_two_orders(10.4, 3), null);
	$row = $made['row'];

	pg_assert_false($made['moved']);
	pg_assert_true($made['new']);
	pg_assert_same(10, $row['interval_days'], 'whole days');
	pg_assert_same(pg_customer_rhythm_stage_code('regular'), $row['stage']);
	pg_assert_same(0, $row['stage_before']);
	pg_assert_same(_crp_test_now(), $row['stage_at']);
	pg_assert_same(_crp_test_now(), $row['updated_at']);
	pg_assert_same(2, $row['orders']);
	pg_assert_same(2, $row['last_order_id']);
	pg_assert_same(6000, $row['avg_order']);

	foreach (array('reminded_at', 'held_back', 'came_back_order_id') as $column) {
		pg_assert_false(array_key_exists($column, $row), $column . ' is left to its own writer');
	}
}

// Time crossing a bound moves the stage forward and keeps the one before.
function test_customer_rhythm_pass_time_moves_the_stage()
{
	$made = _crp_test_row(_crp_test_two_orders(10, 10), _crp_test_stored(1, 10, 10, 1));

	pg_assert_true($made['moved']);
	pg_assert_same(pg_customer_rhythm_stage_code('due'), $made['row']['stage']);
	pg_assert_same(pg_customer_rhythm_stage_code('regular'), $made['row']['stage_before']);
	pg_assert_same(_crp_test_now(), $made['row']['stage_at']);
}

// H7: a rebuild that only changes the store curve moves no one. At 32 days
// of a 10-day interval the fixed curve says Slipping away; a new curve
// below 10% at that band says Lost on yesterday's day too, so the stored
// stage stays.
function test_customer_rhythm_pass_curve_alone_moves_no_one()
{
	$bands = array();

	for ($band = 0; $band <= 7; $band++) {
		$bands[$band] = ($band >= 6) ? 0.05 : 0.5;
	}

	$stored = _crp_test_stored(4, 10, 32, 1, array('stage_before' => 3, 'stage_at' => 123));
	$made = _crp_test_row(_crp_test_two_orders(10, 32), $stored, _crp_test_store(array('fixed' => false, 'bands' => $bands)));

	pg_assert_false($made['moved']);
	pg_assert_same(4, $made['row']['stage'], 'still Slipping away');
	pg_assert_same(3, $made['row']['stage_before']);
	pg_assert_same(123, $made['row']['stage_at']);
}

// A rebuild announces nothing: a first fill and a second pass the same day
// make no move, whatever the stages.
function test_customer_rhythm_pass_rebuild_makes_no_move()
{
	$cases = array(array(10, 3), array(10, 12), array(10, 25), array(10, 40), array(30, 200));

	foreach ($cases as $case) {
		$orders = _crp_test_two_orders($case[0], $case[1]);
		$first = _crp_test_row($orders, null);
		pg_assert_false($first['moved'], 'first fill');

		$again = pg_customer_rhythm_pass_row('eabc', $orders, array(), $first['row'], _crp_test_store(), array(), _crp_test_now() + 3600);
		pg_assert_false($again['moved'], 'second pass');
		pg_assert_same($first['row']['stage'], $again['row']['stage']);
	}
}

// A new order after Late gives Won back, a move from Late.
function test_customer_rhythm_pass_new_order_after_late_is_won_back()
{
	$orders = pg_customer_rhythm_pass_orders(
		_crp_test_packed_order(_crp_test_now() - 86400 * 37, 5000, 1, 0, 0)
		. _crp_test_packed_order(_crp_test_now() - 86400 * 27, 5000, 2, 0, 0)
		. _crp_test_packed_order(_crp_test_now() - 86400 * 2, 5000, 3, 4, 0)
	);
	$made = _crp_test_row($orders, _crp_test_stored(3, 10, 27, 1));

	pg_assert_true($made['moved']);
	pg_assert_same(pg_customer_rhythm_stage_code('won_back'), $made['row']['stage']);
	pg_assert_same(3, $made['row']['stage_before']);
	pg_assert_same(4, $made['row']['contact_id'], 'the latest order\'s contact');
	pg_assert_same(3, $made['row']['last_order_id']);
}

// The next due day is the earliest of the products whose window is open;
// a product that cannot be ordered is left out.
function test_customer_rhythm_pass_next_due_day()
{
	$store = _crp_test_store();
	$store['products'] = array(
		11 => array('days_per_unit' => 10.0, 'pairs' => 12, 'customers' => 6, 'from' => 'product'),
		12 => array('days_per_unit' => 5.0, 'pairs' => 12, 'customers' => 6, 'from' => 'product'),
	);
	$time = _crp_test_now() - 86400 * 9;
	$lines = pg_customer_rhythm_pass_lines('eabc', _crp_test_packed_line(11, 1, $time, 0) . _crp_test_packed_line(12, 1, $time, 0));
	$orders = pg_customer_rhythm_pass_orders(_crp_test_packed_order($time, 5000, 1, 0, 0));

	$made = pg_customer_rhythm_pass_row('eabc', $orders, $lines, null, $store, array(), _crp_test_now());
	pg_assert_same($time + 86400 * 5, $made['row']['next_due_at']);

	$made = pg_customer_rhythm_pass_row('eabc', $orders, $lines, null, $store, array(12 => false), _crp_test_now());
	pg_assert_same($time + 86400 * 10, $made['row']['next_due_at'], 'out of stock left out');
}

// The statement inserts or replaces only the pass's own columns.
function test_customer_rhythm_pass_write_query()
{
	pg_assert_same(array('', array()), pg_customer_rhythm_pass_write_query(array()));

	$made = _crp_test_row(_crp_test_two_orders(10, 3), null);
	list($sql, $params) = pg_customer_rhythm_pass_write_query(array($made['row'], $made['row']));

	pg_assert_same(0, strpos($sql, 'INSERT INTO customer_rhythm (customer_key, '));
	pg_assert_same(2 * count($made['row']), count($params));
	pg_assert_same(2 * count($made['row']), substr_count($sql, '?'));
	pg_assert_false(strpos($sql, 'customer_key = VALUES') !== false, 'the key is not rewritten');
	pg_assert_contains('stage_at = VALUES(stage_at)', $sql);

	foreach (array('reminded_at', 'held_back', 'came_back_order_id') as $column) {
		pg_assert_false(strpos($sql, $column) !== false, $column);
	}
}

// The products the pass works out are those of the rules over all lines.
function test_customer_rhythm_pass_store_matches_the_rules()
{
	$orders = array();
	$items = array();
	$all = array();
	$id = 0;

	for ($customer = 0; $customer < 6; $customer++) {
		for ($n = 0; $n < 3; $n++) {
			$id++;
			$days = 200 - ($n * 20) - $customer;
			$orders[] = _crp_test_order($id, $days, 'c' . $customer . '@example.com');
			$items[$id] = array(array(11, 2), array(12, 1));
		}
	}

	usort($orders, function ($a, $b) {
		return $a['order_date'] - $b['order_date'];
	});

	foreach ($orders as $order) {
		foreach ($items[$order['id']] as $item) {
			$all[] = array('customer' => pg_customer_value_key($order), 'product_id' => $item[0], 'quantity' => $item[1], 'time' => $order['order_date']);
		}
	}

	$store = pg_customer_rhythm_pass_store(_crp_test_walk($orders, $items), array(12 => array(3)), array());

	pg_assert_same(pg_customer_rhythm_products($all, array(12 => array(3)), array(), _crp_test_now()), $store['products']);
	pg_assert_same(10.0, $store['products'][11]['days_per_unit']);
	pg_assert_same(20.0, $store['median']);
	pg_assert_true($store['curve']['fixed']);
}

// The summary holds no person and keeps within its settings text.
function test_customer_rhythm_pass_summary()
{
	$store = _crp_test_store();

	for ($id = 1; $id <= 5000; $id++) {
		$store['products'][$id] = array('days_per_unit' => 12.25, 'pairs' => $id, 'customers' => 5, 'from' => 'product');
	}

	$json = pg_customer_rhythm_summary_encode($store, _crp_test_now(), array('customers' => 3, 'moved' => 0, 'stages' => array(1 => 3)));
	$summary = json_decode($json, true);

	pg_assert_true(strlen($json) <= PG_CUSTOMER_RHYTHM_SUMMARY_BYTES);
	pg_assert_same(5000, $summary['products_all']);
	pg_assert_true(isset($summary['products']['5000']), 'the products with the most pairs are kept');
	pg_assert_false(isset($summary['products']['1']));
	pg_assert_same(array(12.25, 5000, 5, 0), $summary['products']['5000']);
	pg_assert_false(strpos($json, '@') !== false);
}

function test_customer_rhythm_pass_due_and_state()
{
	$now = _crp_test_now();

	pg_assert_true(pg_customer_rhythm_pass_due(array('started' => 0), null, $now), 'no summary yet');
	pg_assert_false(pg_customer_rhythm_pass_due(array('started' => 0), $now - 3600, $now));
	pg_assert_true(pg_customer_rhythm_pass_due(array('started' => 0), $now - 86400, $now));
	pg_assert_true(pg_customer_rhythm_pass_due(array('started' => $now - 60), $now - 3600, $now), 'a pass under way');
	pg_assert_false(pg_customer_rhythm_pass_due(array('started' => 0, 'saved' => 0, 'ended' => $now - 60), $now - 90000, $now), 'a failed write waits');

	$state = pg_customer_rhythm_pass_state_new($now);
	pg_assert_same($state, pg_customer_rhythm_state_decode(serialize($state), $now));
	pg_assert_same(null, pg_customer_rhythm_state_decode(serialize($state), $now + 1), 'another pass');
	pg_assert_same(null, pg_customer_rhythm_state_decode('broken', $now));
	$state['v'] = 99;
	pg_assert_same(null, pg_customer_rhythm_state_decode(serialize($state), $now), 'another format');
}

function test_customer_rhythm_pass_product_available()
{
	pg_assert_true(pg_customer_rhythm_product_available(array('enabled' => '1', 'inventory' => '0', 'inventory_quantity' => 0, 'backorder' => 0)));
	pg_assert_true(pg_customer_rhythm_product_available(array('enabled' => '1', 'inventory' => '1', 'inventory_quantity' => 3, 'backorder' => 0)));
	pg_assert_true(pg_customer_rhythm_product_available(array('enabled' => '1', 'inventory' => '1', 'inventory_quantity' => 0, 'backorder' => 1)));
	pg_assert_false(pg_customer_rhythm_product_available(array('enabled' => '1', 'inventory' => '1', 'inventory_quantity' => 0, 'backorder' => 0)));
	pg_assert_false(pg_customer_rhythm_product_available(array('enabled' => '0', 'inventory' => '0', 'inventory_quantity' => 0, 'backorder' => 0)));
	pg_assert_false(pg_customer_rhythm_product_available(null));
}

// The person's export and erase find their rows by the Customers Report's
// key of each address and by their contact.
function test_customer_rhythm_pass_privacy_finds_the_person()
{
	$keys = pg_privacy_customer_keys(array('Ayse@Example.com ', 'ayse@example.com'), array(7));

	pg_assert_same(array(pg_customer_value_key(array('billing_email_address' => 'ayse@example.com')), 'c7'), $keys);

	$tables = pg_privacy_erase_tables();
	pg_assert_same('delete', $tables['customer_rhythm']['action']);

	$where = pg_privacy_erase_where($tables['customer_rhythm']['find'], array('customer_keys' => $keys, 'contact_ids' => array(7)));
	pg_assert_contains('customer_key', $where);
	pg_assert_contains("'c7'", $where);
	pg_assert_contains('contact_id', $where);

	pg_assert_true(array_key_exists('customer_rhythm', pg_privacy_export_sections()));
	pg_assert_true(array_key_exists('customer_rhythm', pg_privacy_inventory()));
}

// The general job calls the pass once, guarded, on a commerce site; the
// pass itself announces nothing: each written batch goes to
// pg_customer_rhythm_tell(), which announces.
function test_customer_rhythm_pass_job_hook_announces_nothing()
{
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	pg_assert_contains("ECOMMERCE && function_exists('pg_customer_rhythm_job_run')", $job);
	pg_assert_same(1, substr_count($job, 'pg_customer_rhythm_job_run('));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/customer_rhythm_pass.php');
	pg_assert_false(strpos($source, 'pg_announce') !== false, 'no event from the pass');
	pg_assert_false(strpos($source, 'pg_webhook') !== false, 'no webhook from the pass');
	pg_assert_same(1, substr_count($source, '= pg_customer_rhythm_tell('), 'one hand-over per written batch');
}

// A customer erased (or whose orders were deleted) while a pass was under
// way: none of the orders the walk read still carries their key, so the
// write step leaves them out instead of writing their row back.
function test_customer_rhythm_pass_erased_customer_is_not_written_back()
{
	$kept = pg_customer_value_key(array('billing_email_address' => 'ayse@example.com'));
	$erased = pg_customer_value_key(array('billing_email_address' => 'mehmet@example.com'));
	$current = array(
		10 => array('billing_email_address' => 'AYSE@example.com', 'contact_id' => 3),
		// The erasure put a token in place of the address.
		20 => array('billing_email_address' => 'erased-1f2e3d@invalid', 'contact_id' => 0),
		30 => array('billing_email_address' => '', 'contact_id' => 7),
	);

	pg_assert_same(array($erased, 'c9'), pg_customer_rhythm_keys_gone(array($kept => array(10), $erased => array(20), 'c7' => array(30), 'c9' => array(40)), $current), 'gone: address replaced, order deleted');
	pg_assert_same(array(), pg_customer_rhythm_keys_gone(array($kept => array(99, 10)), $current), 'one order still carrying the key keeps the customer');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/customer_rhythm_pass.php');
	pg_assert_contains('pg_customer_rhythm_pass_gone(', $source);
}

// A unit given back through a refunded return was not kept: it is not a
// purchase, so it neither makes a product due nor counts in its rhythm.
function test_customer_rhythm_pass_returned_units_are_not_bought()
{
	$rows = array(
		array(10, 101, 5, 3),
		array(10, 102, 6, 1),
		array(11, 103, 5, 2),
	);

	pg_assert_same(array(10 => array(array(5, 1)), 11 => array(array(5, 2))), pg_customer_rhythm_items_kept($rows, array(101 => 2, 102 => 1)), 'two of three units and the whole line given back');
	pg_assert_same(array(10 => array(array(5, 3), array(6, 1)), 11 => array(array(5, 2))), pg_customer_rhythm_items_kept($rows, array()), 'nothing given back');

	foreach (array('customer_rhythm_pass.php', 'customer_rhythm_report.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
		pg_assert_contains('pg_customer_rhythm_returned_units(', $source, $file);
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the orders list's quick filters (includes/fn/order_list.php):
 * each filter's SQL, run on an in-memory SQLite copy of the tables, keeps
 * the same sample orders as the pure rules of one order (the payment rule
 * and the shipping pill of pg_order_view_states()), and the segmented
 * control that offers them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The sample orders: each order row with its addresses (ship_to_id =>
 * ship_date, delivery_date, lines, parcels as number => status).
 */
function _order_list_quick_samples()
{
	$line = function ($quantity, $shipped, $show = 1) {
		return array('quantity' => $quantity, 'shipped_quantity' => $shipped, 'show_shipped_quantity' => $show);
	};
	$address = function ($ship_date, $delivery_date, array $lines, array $parcels = array()) {
		return array('ship_date' => $ship_date, 'delivery_date' => $delivery_date, 'lines' => $lines, 'parcels' => $parcels);
	};
	$order = function ($status, $method, $paid_at, $total, array $addresses, $transaction = '') {
		return array('status' => $status, 'payment_method' => $method, 'transaction_id' => $transaction, 'paid_at' => $paid_at, 'total' => $total, 'addresses' => $addresses);
	};
	$none = '0000-00-00';

	return array(
		1 => $order('complete', 'Offline Payment', 0, 1000, array(11 => $address($none, $none, array($line(1, 0))))),
		2 => $order('complete', 'Credit/Debit Card', 1, 1000, array(21 => $address($none, $none, array($line(1, 0))))),
		3 => $order('complete', 'Credit/Debit Card', 1, 1000, array(31 => $address('2026-11-20', $none, array($line(1, 0))))),
		4 => $order('exported', 'Credit/Debit Card', 1, 1000, array(41 => $address('2026-11-10', '2026-11-12', array($line(1, 0))))),
		5 => $order('complete', 'Credit/Debit Card', 1, 1000, array(
			51 => $address($none, '2026-11-01', array($line(1, 0))),
			52 => $address($none, $none, array($line(1, 0)), array('T52' => 'in_transit')),
		)),
		6 => $order('complete', 'Credit/Debit Card', 1, 1000, array(
			61 => $address('2026-11-01', $none, array($line(1, 0))),
			62 => $address($none, $none, array($line(1, 0))),
		)),
		7 => $order('complete', 'Credit/Debit Card', 1, 1000, array(71 => $address($none, $none, array($line(1, 0)), array('' => 'delivered')))),
		8 => $order('complete', 'Credit/Debit Card', 1, 1000, array(81 => $address($none, $none, array($line(1, 0)), array('A8' => 'delivered', 'B8' => 'delivered')))),
		9 => $order('complete', 'Credit/Debit Card', 1, 1000, array(91 => $address($none, $none, array($line(2, 2), $line(1, 0, 0))))),
		10 => $order('complete', 'Credit/Debit Card', 1, 1000, array(101 => $address($none, $none, array($line(3, 1))))),
		11 => $order('complete', 'Credit/Debit Card', 1, 1000, array()),
		12 => $order('complete', 'Credit/Debit Card', 1, 1000, array(121 => $address('2026-12-01', '2026-12-03', array($line(1, 0))))),
		13 => $order('complete', 'Offline Payment', 0, 0, array(131 => $address($none, $none, array($line(1, 0))))),
		14 => $order('cancelled', 'Credit/Debit Card', 1, 1000, array(141 => $address($none, '2026-11-02', array($line(1, 0))))),
		15 => $order('complete', 'PayPal Checkout', 0, 1000, array(151 => $address($none, $none, array($line(1, 0))))),
		16 => $order('exported', 'Offline Payment', 0, 1000, array(161 => $address($none, $none, array($line(1, 0))))),
		17 => $order('incomplete', '', 0, 0, array(171 => $address($none, $none, array($line(1, 0))))),
		18 => $order('complete', 'Credit/Debit Card', 1, 1000, array(181 => $address($none, $none, array($line(1, 0)), array('X18' => 'mystery')))),
		19 => $order('cancelled', 'Credit/Debit Card', 1, 1000, array(191 => $address($none, $none, array($line(1, 0))))),
		20 => $order('complete', 'Credit/Debit Card', 0, 1000, array(201 => $address($none, $none, array($line(1, 0)))), 'pi_3PqXyZ'),
		21 => $order('complete', 'Credit/Debit Card', 0, 1000, array(211 => $address($none, $none, array($line(1, 0)))), 'ch_1'),
		22 => $order('complete', 'Credit/Debit Card', 0, 1000, array(221 => $address($none, $none, array($line(1, 0)))), null),
	);
}

/**
 * The codes of the quick filters each sample order falls under, by the
 * pure rules of one order.
 */
function _order_list_quick_by_rule(array $orders, $today, $status_ready)
{
	$kept = array();

	foreach ($orders as $id => $order) {
		$addresses = array();

		foreach ($order['addresses'] as $ship_to_id => $address) {
			$parcels = array();

			foreach ($address['parcels'] as $number => $status) {
				$parcels[] = array('ship_to_id' => $ship_to_id, 'number' => (string) $number, 'status' => $status_ready ? $status : '');
			}

			$lines = array();

			foreach ($address['lines'] as $line) {
				$lines[] = $line + array('order_id' => $id, 'ship_to_id' => $ship_to_id, 'ship_date' => $address['ship_date'], 'delivery_date' => $address['delivery_date']);
			}

			$built = pg_order_list_addresses($lines, $parcels);
			$addresses += $built[$id] ?? array();
		}

		$pills = pg_order_view_states($order, $addresses, $today);
		$awaiting = pg_order_awaiting_payment($order) && ($pills['payment'] !== null);
		$begun = false;

		foreach ($addresses as $address) {
			if (in_array(pg_order_view_address_shipping($address, $today), array('shipped', 'delivered'), true)) {
				$begun = true;
			}
		}

		$codes = array();

		if ($awaiting) {
			$codes[] = 'awaiting_payment';
		}
		if (in_array($order['status'], array('complete', 'exported'), true) && !$awaiting && $addresses && !$begun) {
			$codes[] = 'to_ship';
		}
		if (($pills['shipping'] !== null) && ($pills['shipping'][0] === lang('Shipped'))) {
			$codes[] = 'shipped';
		}
		if (($pills['shipping'] !== null) && ($pills['shipping'][0] === lang('Delivered'))) {
			$codes[] = 'delivered';
		}

		$kept[$id] = $codes;
	}

	return $kept;
}

/**
 * The same, by each filter's SQL on SQLite. Null without pdo_sqlite.
 */
function _order_list_quick_by_sql(array $orders, $today, $status_ready)
{
	if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
		return null;
	}

	$db = new PDO('sqlite::memory:');
	$db->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, status TEXT, payment_method TEXT, transaction_id TEXT, paid_at INT, total INT, order_date INT)');
	$db->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INT, ship_to_id INT, quantity INT, shipped_quantity INT, show_shipped_quantity INT)');
	$db->exec('CREATE TABLE ship_tos (id INTEGER PRIMARY KEY, ship_date TEXT, delivery_date TEXT)');
	$db->exec('CREATE TABLE shipping_tracking_numbers (id INTEGER PRIMARY KEY, order_id INT, ship_to_id INT, number TEXT, status TEXT)');

	foreach ($orders as $id => $order) {
		$db->prepare('INSERT INTO orders VALUES (?, ?, ?, ?, ?, ?, ?)')->execute(array($id, $order['status'], $order['payment_method'], $order['transaction_id'], $order['paid_at'], $order['total'], 1000 + $id));

		foreach ($order['addresses'] as $ship_to_id => $address) {
			$db->prepare('INSERT INTO ship_tos VALUES (?, ?, ?)')->execute(array($ship_to_id, $address['ship_date'], $address['delivery_date']));

			foreach ($address['lines'] as $line) {
				$db->prepare('INSERT INTO order_items (order_id, ship_to_id, quantity, shipped_quantity, show_shipped_quantity) VALUES (?, ?, ?, ?, ?)')
					->execute(array($id, $ship_to_id, $line['quantity'], $line['shipped_quantity'], $line['show_shipped_quantity']));
			}

			foreach ($address['parcels'] as $number => $status) {
				$db->prepare('INSERT INTO shipping_tracking_numbers (order_id, ship_to_id, number, status) VALUES (?, ?, ?, ?)')->execute(array($id, $ship_to_id, (string) $number, $status));
			}
		}

		// A line with no address belongs to no address.
		$db->prepare('INSERT INTO order_items (order_id, ship_to_id, quantity, shipped_quantity, show_shipped_quantity) VALUES (?, 0, 1, 0, 0)')->execute(array($id));
	}

	$kept = array_fill_keys(array_keys($orders), array());

	foreach (array_keys(pg_order_list_quick_filters()) as $code) {
		if ($code === '') {
			continue;
		}

		$st = $db->query('SELECT orders.id FROM orders WHERE ' . pg_order_list_quick_sql($code, $today, true, $status_ready) . ' ORDER BY orders.id');
		pg_assert_true($st !== false, 'the ' . $code . ' filter runs');

		foreach ($st ? $st->fetchAll(PDO::FETCH_COLUMN) : array() as $id) {
			$kept[(int) $id][] = $code;
		}
	}

	$counts = $db->query(pg_order_list_quick_counts_sql('SELECT id FROM orders', $today, true, $status_ready));
	pg_assert_true($counts !== false, 'the counts query runs');
	$row = $counts ? $counts->fetch(PDO::FETCH_ASSOC) : array();
	pg_assert_same(count($orders), (int) ($row['n_all'] ?? -1), 'All counts every order');

	foreach (array('awaiting_payment', 'to_ship', 'shipped', 'delivered') as $code) {
		$n = 0;
		foreach ($kept as $codes) {
			$n += in_array($code, $codes, true) ? 1 : 0;
		}
		pg_assert_same($n, (int) ($row['n_' . $code] ?? -1), 'the count of ' . $code . ' is its filter\'s');
	}

	// The main menu's Orders count is the To ship filter's, over every order,
	// with the order date of the oldest of them.
	$to_ship = array_keys(array_filter($kept, function ($codes) {
		return in_array('to_ship', $codes, true);
	}));
	$menu = $db->query(pg_order_to_ship_menu_sql($today, true, $status_ready));
	pg_assert_true($menu !== false, 'the menu count query runs');
	$menu_row = $menu ? $menu->fetch(PDO::FETCH_ASSOC) : array();
	pg_assert_same(count($to_ship), (int) ($menu_row['n'] ?? -1), 'the menu counts the orders to ship');
	pg_assert_same($to_ship ? 1000 + min($to_ship) : 0, (int) ($menu_row['oldest'] ?? -1), 'the oldest order to ship');

	return $kept;
}

function test_order_list_quick_sql_keeps_what_the_pills_say()
{
	$orders = _order_list_quick_samples();
	$today = '2026-11-22';

	foreach (array(true, false) as $status_ready) {
		$by_sql = _order_list_quick_by_sql($orders, $today, $status_ready);

		if ($by_sql === null) {
			pg_assert_true(true, 'no pdo_sqlite on this runner');
			return;
		}

		pg_assert_same(_order_list_quick_by_rule($orders, $today, $status_ready), $by_sql, 'every sample order, parcel status ' . ($status_ready ? 'known' : 'not yet a column'));
	}

	$by_rule = _order_list_quick_by_rule($orders, $today, true);
	pg_assert_same(array('awaiting_payment'), $by_rule[1], 'an unpaid bank transfer awaits payment');
	pg_assert_same(array('to_ship'), $by_rule[2], 'paid, nothing shipped: to ship');
	pg_assert_same(array('shipped'), $by_rule[5], 'one address delivered, one shipped: shipped');
	pg_assert_same(array(), $by_rule[6], 'partly shipped is neither to ship nor shipped');
	pg_assert_same(array('delivered'), $by_rule[8], 'every parcel delivered');
	pg_assert_same(array('to_ship'), $by_rule[10], 'some quantity shipped, no address shipped: still to ship');
	pg_assert_same(array(), $by_rule[11], 'no address, nothing to ship');
	pg_assert_same(array('to_ship'), $by_rule[12], 'a ship date to come is not shipped');
	pg_assert_same(array('awaiting_payment'), $by_rule[15], 'a payment left pending awaits payment');
	pg_assert_same(array('awaiting_payment'), $by_rule[20], 'a Stripe payment still processing awaits payment');
	pg_assert_same(array('to_ship'), $by_rule[21], 'an older card order with paid_at 0 is not unpaid');
	pg_assert_same(array('to_ship'), $by_rule[22], 'nor one without a transaction id');
	pg_assert_same(array('shipped'), $by_rule[18], 'an unknown parcel status reads as created: shipped');
}

function test_order_list_quick_sql_without_paid_at()
{
	pg_assert_same('(1 = 0)', pg_order_list_quick_sql('awaiting_payment', '2026-11-22', false, true), 'nothing is known to be unpaid before paid_at');
	pg_assert_same('', pg_order_list_quick_sql('', '2026-11-22', true, true), 'All adds nothing');
	pg_assert_same('', pg_order_list_quick_sql('bogus', '2026-11-22', true, true), 'an unknown code adds nothing');
	pg_assert_false(strpos(pg_order_list_quick_sql('delivered', "2026-11-22' OR 1=1 --", true, true), 'OR 1=1') !== false, 'a date that is not one is not written');
	pg_assert_false(strpos(pg_order_list_quick_sql('delivered', '2026-11-22', true, false), 'p.status') !== false, 'no parcel status before its column');
}

function test_order_list_quick_value_and_html()
{
	pg_assert_same('to_ship', pg_order_list_quick_value(' to_ship '));
	pg_assert_same('', pg_order_list_quick_value('nope'), 'an unknown code is All');
	pg_assert_same('', pg_order_list_quick_value(array('to_ship')), 'an array is All');

	$html = pg_order_list_quick_html('shipped', array('n_all' => 40, 'n_awaiting_payment' => 3, 'n_to_ship' => 12, 'n_shipped' => 1234, 'n_delivered' => 9));
	pg_assert_contains('class="pg-seg pg-seg-wrap pg-orders-quick" role="group"', $html, 'the look\'s segmented control');
	pg_assert_contains('<a class="pg-seg-item is-active" aria-current="true" href="view_orders.php?quick=shipped">' . h(lang('Shipped')), $html, 'the chosen filter is marked');
	pg_assert_contains('href="view_orders.php?quick="', $html, 'All clears the filter');
	pg_assert_contains('<span class="pg-seg-count pg-tabular">' . pg_format_number(1234, 0) . '</span>', $html, 'each filter\'s count');
	pg_assert_same(5, substr_count($html, 'pg-seg-item'), 'All and the four filters');
	pg_assert_false(strpos(pg_order_list_quick_html('', array()), 'pg-seg-count') !== false, 'no counts, none drawn');
}

function test_order_list_previous_period_has_the_same_length()
{
	$start = mktime(0, 0, 0, 10, 23, 2026);
	$stop = mktime(23, 59, 59, 11, 22, 2026);
	list($before_start, $before_stop) = pg_order_list_previous_period($start, $stop);

	pg_assert_same('2026-09-22 00:00:00', date('Y-m-d H:i:s', $before_start), 'thirty-one days back');
	pg_assert_same('2026-10-22 23:59:59', date('Y-m-d H:i:s', $before_stop), 'it ends the second before the period');

	list($day_start, $day_stop) = pg_order_list_previous_period(mktime(0, 0, 0, 3, 1, 2026), mktime(23, 59, 59, 3, 1, 2026));
	pg_assert_same('2026-02-28 00:00:00 2026-02-28 23:59:59', date('Y-m-d H:i:s', $day_start) . ' ' . date('Y-m-d H:i:s', $day_stop), 'a day: the day before');
}

function test_order_list_change()
{
	pg_assert_same(null, pg_order_list_change(10, 0), 'nothing before, nothing to compare');
	pg_assert_same(25, pg_order_list_change(125, 100));
	pg_assert_same(-33, pg_order_list_change(2, 3), 'rounded to a whole per cent');
	pg_assert_same(0, pg_order_list_change(7, 7));

	pg_assert_same('', pg_order_list_change_html(5, 0), 'no note without a period before');
	$up = pg_order_list_change_html(125, 100);
	pg_assert_contains('class="pg-figure-note pg-tabular is-good"', $up, 'a rise is good');
	pg_assert_contains('>+25%<', $up);
	pg_assert_contains(h(lang('Against the period before, of the same length')), $up, 'what it is compared with, also for a screen reader');
	$down = pg_order_list_change_html(50, 100);
	pg_assert_contains('is-bad', $down, 'a fall is bad');
	pg_assert_contains("\u{2212}50%", $down, 'a true minus sign');
	pg_assert_false(strpos(pg_order_list_change_html(3, 3), 'is-') !== false, 'no change, no ink');
}

function test_order_list_screen_wires_the_quick_filters()
{
	$screen = file_get_contents(dirname(__DIR__) . '/pinegrap/view_orders.php');
	pg_assert_contains('pg_order_list_quick_value($_SESSION[\'software\'][\'ecommerce\'][\'view_orders\'][\'quick\'] ?? \'\')', $screen, 'the choice kept in the session, as the status select\'s');
	pg_assert_contains('pg_order_list_quick_counts_sql($orders_quick_base_sql,', $screen, 'counted over the list without the quick filter');
	pg_assert_contains('$sql_type .= \' AND \' . $sql_quick;', $screen, 'every query of the list narrowed by it');
	pg_assert_contains('pg_order_list_change_html($filter_totals_minor,', $screen, 'the total\'s change');
	pg_assert_contains('pg_order_list_change_html((int) $number_of_results,', $screen, 'the count\'s change');
}

// The menu's "To ship" entry opens All Orders afresh on the filter; its
// period reaches back to the oldest order to ship only past the default month.
function test_order_to_ship_menu_url()
{
	$now = mktime(12, 0, 0, 11, 28, 2026);
	pg_assert_same('view_orders.php?reset=true&quick=to_ship', pg_order_to_ship_menu_url(0, $now), 'nothing to ship');
	pg_assert_same('view_orders.php?reset=true&quick=to_ship', pg_order_to_ship_menu_url(mktime(9, 0, 0, 11, 20, 2026), $now), 'inside the default month');
	pg_assert_same('view_orders.php?reset=true&quick=to_ship&start_month=03&start_day=05&start_year=2025', pg_order_to_ship_menu_url(mktime(9, 0, 0, 3, 5, 2025), $now), 'older than the default month');
	$start = $now - 2678400;
	pg_assert_same('view_orders.php?reset=true&quick=to_ship', pg_order_to_ship_menu_url($start, $now), 'on the default first day');
}

// The Orders row reads its count from the To ship rule and offers the entry.
function test_order_to_ship_menu_is_wired()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	pg_assert_contains("\$orders_to_ship = pg_order_to_ship_menu_count();", $source, 'the Orders row reads the count');
	pg_assert_contains("pg_order_to_ship_menu_url(\$orders_to_ship['oldest'], time())", $source, 'the entry opens the filtered list');
	pg_assert_contains("'{var:1} order(s) to ship'", $source, 'the hover sentence');
}

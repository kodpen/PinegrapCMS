<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer value summary (includes/fn/customer_values.php):
 * which orders count and for how much, the customer's key, the currency an
 * order is counted in, a rebuild in several runs against one in a single
 * run, and a summary that holds no name, address or e-mail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One order row as the rebuild reads it.
function _cv_test_order($id, $date, $email, $total, $extra = array())
{
	return array_merge(array(
		'id' => $id,
		'order_date' => $date,
		'order_number' => 1000 + $id,
		'billing_email_address' => $email,
		'contact_id' => 0,
		'total' => $total,
		'refunded_amount' => 0,
		'type' => 'online',
		'currency_code' => 'TRY',
		'status' => 'complete',
		'cancelled_at' => null,
	), $extra);
}

// A rebuild over the given rows in one run, finished at $now.
function _cv_test_summary($rows, $now, $base = 'TRY')
{
	$state = pg_customer_value_state_new($now);
	pg_customer_value_add_rows($state, $rows, $base);

	return pg_customer_value_finish($state, $base);
}

// Only a complete or exported order that is not cancelled counts; a wholly
// refunded order does not, a partly refunded one counts for what is left.
function test_customer_values_what_counts()
{
	pg_assert_true(function_exists('pg_customer_value_order_counts'), 'the counting rule exists');

	pg_assert_true(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 5000)));
	pg_assert_true(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 5000, array('status' => 'exported'))));
	pg_assert_false(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 5000, array('status' => 'cancelled'))), 'a cancelled order');
	pg_assert_false(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 5000, array('cancelled_at' => 1700000000))), 'an order with a cancel time');
	pg_assert_false(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 5000, array('status' => 'incomplete'))), 'a cart');
	pg_assert_false(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 5000, array('refunded_amount' => 5000))), 'a wholly refunded order');
	pg_assert_true(pg_customer_value_order_counts(_cv_test_order(1, 100, 'a@x.com', 0)), 'a free order still counts');

	pg_assert_same(3000, pg_customer_value_order_amount(_cv_test_order(1, 100, 'a@x.com', 5000, array('refunded_amount' => 2000))));
	pg_assert_same(0, pg_customer_value_order_amount(_cv_test_order(1, 100, 'a@x.com', 5000, array('refunded_amount' => 9000))));
}

// A cancelled and a refunded order leave the figures.
function test_customer_values_cancelled_and_refunded_left_out()
{
	$now = mktime(12, 0, 0, 6, 15, 2026);
	$day = 86400;
	$summary = _cv_test_summary(array(
		_cv_test_order(1, $now - 40 * $day, 'a@x.com', 10000),
		_cv_test_order(2, $now - 30 * $day, 'a@x.com', 20000, array('status' => 'cancelled')),
		_cv_test_order(3, $now - 20 * $day, 'a@x.com', 30000, array('refunded_amount' => 30000)),
		_cv_test_order(4, $now - 10 * $day, 'b@x.com', 40000, array('refunded_amount' => 15000)),
	), $now);

	pg_assert_same(2, $summary['orders']);
	pg_assert_same(2, $summary['customers']);
	pg_assert_same(array(2, 0, 2, 35000, 0.0), $summary['tiles']['all']);
}

// The base currency is the unit of a web order whatever it was shown in; a
// marketplace order in another unit leaves the figures and is counted apart.
function test_customer_values_currencies_apart()
{
	pg_assert_same('TRY', pg_customer_value_order_unit(_cv_test_order(1, 100, 'a@x.com', 1, array('currency_code' => 'EUR')), 'TRY'));
	pg_assert_same('TRY', pg_customer_value_order_unit(_cv_test_order(1, 100, 'a@x.com', 1, array('type' => 'marketplace')), 'TRY'));
	pg_assert_same('USD', pg_customer_value_order_unit(_cv_test_order(1, 100, 'a@x.com', 1, array('type' => 'marketplace', 'currency_code' => 'usd')), 'TRY'));

	$now = mktime(12, 0, 0, 6, 15, 2026);
	$summary = _cv_test_summary(array(
		_cv_test_order(1, $now - 86400, 'a@x.com', 10000, array('currency_code' => 'EUR')),
		_cv_test_order(2, $now - 3600, 'm@relay.example', 7000, array('type' => 'marketplace', 'currency_code' => 'USD')),
		_cv_test_order(3, $now - 1800, 'n@relay.example', 3000, array('type' => 'marketplace', 'currency_code' => 'USD')),
	), $now);

	pg_assert_same('TRY', $summary['unit']);
	pg_assert_same(1, $summary['orders'], 'only the web order is in the base figures');
	pg_assert_same(10000, $summary['tiles']['all'][3]);
	pg_assert_same(array('USD' => array(2, 10000)), $summary['apart']);
}

// Two guest orders with one e-mail, written differently, are one customer; an
// order with no e-mail goes by its contact; one with neither is not placed.
function test_customer_values_customer_key()
{
	pg_assert_same(pg_customer_value_key(array('billing_email_address' => ' Ayse@Example.com ', 'contact_id' => 4)), pg_customer_value_key(array('billing_email_address' => 'ayse@example.com', 'contact_id' => 9)));
	pg_assert_same('c7', pg_customer_value_key(array('billing_email_address' => '', 'contact_id' => 7)));
	pg_assert_same('', pg_customer_value_key(array('billing_email_address' => '', 'contact_id' => 0)));
	pg_assert_false(strpos(pg_customer_value_key(array('billing_email_address' => 'ayse@example.com', 'contact_id' => 0)), 'ayse') !== false, 'the key holds no e-mail');

	$now = mktime(12, 0, 0, 6, 15, 2026);
	$day = 86400;
	$summary = _cv_test_summary(array(
		_cv_test_order(1, $now - 50 * $day, 'Guest@Example.com', 10000, array('contact_id' => 11)),
		_cv_test_order(2, $now - 5 * $day, 'guest@example.com', 6000, array('contact_id' => 12)),
		_cv_test_order(3, $now - 4 * $day, '', 2000, array('contact_id' => 0)),
	), $now);

	pg_assert_same(1, $summary['customers']);
	pg_assert_same(1, $summary['unplaced']);
	pg_assert_same(array(1, 1, 2, 16000, 45.0), $summary['tiles']['all']);
	pg_assert_same(array(array(1002, 16000, 2)), $summary['top']['all'], 'the top customer is kept as its last order number');
}

// A rebuild cut into runs, its state written and read back between them,
// gives the same summary as one run.
function test_customer_values_runs_match_one_run()
{
	$now = mktime(12, 0, 0, 6, 15, 2026);
	$rows = array();
	$seed = 7;

	for ($i = 1; $i <= 400; $i++) {
		$seed = ($seed * 1103515245 + 12345) % 2147483648;
		$email = 'c' . ($seed % 90) . '@example.com';
		$date = $now - 900 * 86400 + $i * 2 * 86400 + ($seed % 5000);
		$extra = (($seed % 13) === 0) ? array('refunded_amount' => 500) : array();
		$rows[] = _cv_test_order($i, $date, $email, 1000 + ($seed % 9000), $extra);
	}

	$whole = _cv_test_summary($rows, $now);

	$state = pg_customer_value_state_new($now);

	foreach (array_chunk($rows, 37) as $chunk) {
		pg_customer_value_add_rows($state, $chunk, 'TRY');
		$state = pg_customer_value_state_decode(pg_customer_value_state_encode($state));
	}

	pg_assert_same($whole, pg_customer_value_finish($state, 'TRY'));
	pg_assert_same($rows[399]['order_date'], $state['date'], 'the place is the last order read');
	pg_assert_same(400, $state['id']);
	pg_assert_same(400, $state['done']);
	pg_assert_true($whole['customers'] > 50);
	pg_assert_same(400 - 0, $whole['orders']);
}

// The groups' cells, the cohorts and the months come from the same orders.
function test_customer_values_cells_cohorts_months()
{
	$now = mktime(12, 0, 0, 6, 15, 2026);
	$day = 86400;
	$may = mktime(12, 0, 0, 5, 10, 2026);
	$summary = _cv_test_summary(array(
		_cv_test_order(1, mktime(12, 0, 0, 3, 3, 2026), 'a@x.com', 1000),
		_cv_test_order(2, mktime(12, 0, 0, 4, 3, 2026), 'a@x.com', 2000),
		_cv_test_order(3, $may, 'a@x.com', 3000),
		_cv_test_order(4, mktime(12, 0, 0, 3, 20, 2026), 'b@x.com', 4000),
		_cv_test_order(5, $now - 400 * $day, 'c@x.com', 5000),
	), $now);

	// a: 3 orders, last 36 days ago; b: 1 order, 87 days; c: 1 order, 400 days.
	pg_assert_same(array(1, 6000), $summary['cells'][1][1]);
	pg_assert_same(array(1, 4000), $summary['cells'][1][0]);
	pg_assert_same(array(1, 5000), $summary['cells'][4][0]);
	pg_assert_same(array(0, 0), $summary['cells'][0][2]);

	pg_assert_same(array(2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0), $summary['cohorts']['2026-03']);
	pg_assert_same(array(2, 5000, 0, 0), $summary['months']['2026-03']);
	pg_assert_same(array(0, 0, 1, 3000), $summary['months']['2026-05']);
	pg_assert_same(array(0, 0, 0, 0), $summary['months']['2026-06']);

	// The last three whole months are March to May.
	pg_assert_same(array(2, 1, 4, 10000, 34.0), $summary['tiles']['3']);
	pg_assert_same(array(1, 0, 1, 3000, 37.0), $summary['tiles']['1']);
}

// No name, address or e-mail reaches the summary, and it stays small.
function test_customer_values_summary_holds_no_person()
{
	$now = mktime(12, 0, 0, 6, 15, 2026);
	$summary = _cv_test_summary(array(
		_cv_test_order(1, $now - 86400, 'ayse.yilmaz@example.com', 10000, array('billing_first_name' => 'Ayşe', 'billing_last_name' => 'Yılmaz', 'billing_address_1' => 'Atatürk Caddesi 5')),
	), $now);
	$json = pg_customer_value_summary_encode($summary);

	foreach (array('ayse', 'example.com', 'Yılmaz', 'Atatürk', 'Ayşe') as $word) {
		pg_assert_false(stripos($json, $word) !== false, 'the summary holds no ' . $word);
	}

	pg_assert_true(strlen($json) < 65535);
	pg_assert_same($summary, pg_customer_value_summary_decode($json));
}

// The page read keeps its place on the date index and stops at the pass's start.
function test_customer_values_page_query()
{
	list($sql, $params) = pg_customer_value_page_query(1700000000, 1690000000, 42, 500, array());

	pg_assert_contains("status IN ('complete', 'exported')", $sql);
	pg_assert_contains('order_date <= ?', $sql);
	pg_assert_contains('(order_date >= ?) AND ((order_date > ?) OR (id > ?))', $sql);
	pg_assert_contains('ORDER BY order_date, id LIMIT 500', $sql);
	pg_assert_contains('0 AS refunded_amount', $sql, 'a column the site lacks reads as its default');
	pg_assert_same(array(1700000000, 1690000000, 1690000000, 42), $params);
}

// The general job calls the rebuild once, guarded, on a commerce site.
function test_customer_values_job_hook()
{
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	pg_assert_contains("function_exists('pg_customer_value_job_run')", $job);
	pg_assert_same(1, substr_count($job, 'pg_customer_value_job_run('));
	pg_assert_true(function_exists('pg_customer_value_job_run'));
}

// Each tile of the report against a hand-counted set of orders: in March to
// May a orders three times (31 and 37 days apart), b once; c's order is a
// year old and falls in no period but all time.
function test_customer_values_report_tiles()
{
	pg_assert_true(function_exists('pg_customer_value_figures'), 'the tiles exist');

	$now = mktime(12, 0, 0, 6, 15, 2026);
	$summary = _cv_test_summary(array(
		_cv_test_order(1, mktime(12, 0, 0, 3, 3, 2026), 'a@x.com', 1000),
		_cv_test_order(2, mktime(12, 0, 0, 4, 3, 2026), 'a@x.com', 2000),
		_cv_test_order(3, mktime(12, 0, 0, 5, 10, 2026), 'a@x.com', 3000),
		_cv_test_order(4, mktime(12, 0, 0, 3, 20, 2026), 'b@x.com', 4000),
		_cv_test_order(5, $now - 400 * 86400, 'c@x.com', 5000),
	), $now);

	$texts = array_map(function ($figure) {
		return $figure['text'];
	}, pg_customer_value_figures($summary['tiles']['3'], '$'));

	pg_assert_same(pg_format_number(2, 0), $texts[0], 'customers');
	pg_assert_same(lang(array('string' => '{var:1}%', 'vars' => pg_format_number(50, 1))), $texts[1], 'one of two ordered twice');
	pg_assert_same(pg_format_number(2, 2), $texts[2], 'four orders over two customers');
	pg_assert_same(pg_report_view_money(5000, '$'), $texts[3], '100.00 over two customers');
	pg_assert_same(pg_format_number(34, 1), $texts[4], '31 and 37 days apart');

	$all = pg_customer_value_figures($summary['tiles']['all'], '$');
	pg_assert_same(pg_format_number(3, 0), $all[0]['text']);
	pg_assert_same(pg_report_view_money(5000, '$'), $all[3]['text'], '150.00 over three customers');

	$none = pg_customer_value_figures(array(0, 0, 0, 0, 0.0), '$');
	pg_assert_same('–', $none[1]['text'], 'no rate without customers');
	pg_assert_same('–', $none[4]['text']);
}

// The months of a period are the whole months before the summary's month.
function test_customer_values_report_months()
{
	$summary = array('months' => array('2026-03' => array(2, 5000, 0, 0), '2026-04' => array(0, 0, 1, 2000), '2026-05' => array(0, 0, 1, 3000), '2026-06' => array(1, 10, 0, 0)));

	pg_assert_same(array('2026-05'), array_keys(pg_customer_value_period_months($summary, '1')));
	pg_assert_same(array('2026-03', '2026-04', '2026-05'), array_keys(pg_customer_value_period_months($summary, '3')));
	pg_assert_same(array('2026-03', '2026-04', '2026-05', '2026-06'), array_keys(pg_customer_value_period_months($summary, 'all')));

	$chart = pg_customer_value_month_chart(pg_customer_value_period_months($summary, '3'), '$');
	pg_assert_same(array(50.0, 0.0, 0.0), $chart['values']);
	pg_assert_same(array(0.0, 20.0, 30.0), $chart['compare']);
	pg_assert_same(null, pg_customer_value_month_chart(pg_customer_value_period_months($summary, '1'), '$'), 'one month draws no line');

	pg_assert_same('12', pg_customer_value_period_pick('bad', null));
	pg_assert_same('3', pg_customer_value_period_pick('bad', '3'));
	pg_assert_same('all', pg_customer_value_period_pick('all', '3'));
}

// The cohort table: newest first, the share of each cohort that ordered in
// each month after its first, nothing for a month not yet begun.
function test_customer_values_report_cohorts()
{
	$now = mktime(12, 0, 0, 6, 15, 2026);
	$summary = _cv_test_summary(array(
		_cv_test_order(1, mktime(12, 0, 0, 3, 3, 2026), 'a@x.com', 1000),
		_cv_test_order(2, mktime(12, 0, 0, 4, 3, 2026), 'a@x.com', 2000),
		_cv_test_order(3, mktime(12, 0, 0, 3, 20, 2026), 'b@x.com', 4000),
		_cv_test_order(4, mktime(12, 0, 0, 5, 20, 2026), 'c@x.com', 4000),
	), $now);
	$rows = pg_customer_value_cohort_rows($summary, 4);

	pg_assert_same(array('2026-06', '2026-05', '2026-04', '2026-03'), array_column($rows, 'month'));

	$march = $rows[3];
	pg_assert_same(2, $march['size']);
	pg_assert_same(lang(array('string' => '{var:1}%', 'vars' => pg_format_number(50, 1))), $march['shares'][0], 'April: one of two');
	pg_assert_same(lang(array('string' => '{var:1}%', 'vars' => pg_format_number(0, 1))), $march['shares'][2], 'June has begun');
	pg_assert_same(null, $march['shares'][3], 'July has not');

	pg_assert_same('', $rows[2]['shares'][0], 'an empty cohort shares nothing out');
	pg_assert_same(null, $rows[0]['shares'][0]);
}

// The cells' bounds at their edges, and the group each cell belongs to.
function test_customer_values_report_cell_bounds()
{
	pg_assert_same(0, pg_customer_value_cell_age(0));
	pg_assert_same(0, pg_customer_value_cell_age(30));
	pg_assert_same(1, pg_customer_value_cell_age(30.01));
	pg_assert_same(1, pg_customer_value_cell_age(90));
	pg_assert_same(2, pg_customer_value_cell_age(91));
	pg_assert_same(2, pg_customer_value_cell_age(180));
	pg_assert_same(3, pg_customer_value_cell_age(365));
	pg_assert_same(4, pg_customer_value_cell_age(365.5));

	pg_assert_same(0, pg_customer_value_cell_band(1));
	pg_assert_same(1, pg_customer_value_cell_band(2));
	pg_assert_same(1, pg_customer_value_cell_band(3));
	pg_assert_same(2, pg_customer_value_cell_band(4));

	// A customer whose last order is exactly 30 days old is in the first row.
	$now = mktime(12, 0, 0, 6, 15, 2026);
	$summary = _cv_test_summary(array(_cv_test_order(1, $now - 30 * 86400, 'a@x.com', 100), _cv_test_order(2, $now - 31 * 86400, 'b@x.com', 100)), $now);
	pg_assert_same(array(1, 100), $summary['cells'][0][0]);
	pg_assert_same(array(1, 100), $summary['cells'][1][0]);

	$groups = array();

	for ($age = 0; $age <= 4; $age++) {
		for ($band = 0; $band <= 2; $band++) {
			$groups[] = pg_customer_value_cell_group($age, $band);
		}
	}

	pg_assert_same(array('new', 'returning', 'loyal', 'new', 'returning', 'loyal', 'at_risk', 'returning', 'loyal', 'at_risk', 'at_risk', 'at_risk', 'lost', 'lost', 'lost'), $groups, 'all fifteen cells');
	pg_assert_same(array_keys(pg_customer_value_group_names()), array_keys(pg_customer_value_group_bounds()), 'every group states its bounds');
	pg_assert_same(array(), array_diff($groups, array_keys(pg_customer_value_group_names())), 'every cell has a named group');
}

// A reader without the contacts right sees the figures but no name and no
// order to open, whatever names were handed in.
function test_customer_values_report_names_hidden()
{
	$top = array(array(1002, 16000, 2), array(1007, 9000, 1));
	$orders = array(1002 => array('id' => 2, 'name' => 'Ayşe Yılmaz'), 1007 => array('id' => 7, 'name' => 'Can Demir'));

	$hidden = pg_customer_value_top_rows($top, $orders, false);
	pg_assert_same(array('', ''), array_column($hidden, 'name'));
	pg_assert_same(array(0, 0), array_column($hidden, 'order_id'));
	pg_assert_same(array(16000, 9000), array_column($hidden, 'value'));
	pg_assert_same(array(1, 2), array_column($hidden, 'rank'));

	$shown = pg_customer_value_top_rows($top, $orders, true);
	pg_assert_same(array('Ayşe Yılmaz', 'Can Demir'), array_column($shown, 'name'));
	pg_assert_same(array(2, 7), array_column($shown, 'order_id'));

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_customer_report.php');
	pg_assert_contains('validate_contacts_access($user, true)', $screen, 'the screen asks for the contacts right');
	pg_assert_contains('pg_customer_value_order_names(', $screen);
	pg_assert_true(strpos($screen, 'validate_ecommerce_report_access()') !== false, 'commerce reports right');
}

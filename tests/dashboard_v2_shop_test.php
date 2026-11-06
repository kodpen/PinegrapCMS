<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's shop cards (includes/dashboard/v2/cards/
 * shop.php, salesmap.php, orders.php, offers.php, fx.php): the time windows
 * of the summary, the rows each card hands its renderer, and the queries
 * they share with dashboard widgets 1, 3, 8, 15 and 16.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');
require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');

foreach (array('shop', 'salesmap', 'orders', 'offers', 'fx') as $dashboard_v2_shop_card) {
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/' . $dashboard_v2_shop_card . '.php');
}

if (!defined('BASE_CURRENCY_SYMBOL')) {
	define('BASE_CURRENCY_SYMBOL', '$');
}

function test_dashboard_v2_shop_cards_are_built()
{
	$cards = pg_dashboard_v2_cards();

	foreach (array('shop', 'salesmap', 'orders', 'offers', 'fx') as $id) {
		pg_assert_true($cards[$id]['built'], $id);
	}

	pg_assert_same(null, $cards['salesmap']['period'], 'the sales map draws the last 30 days only');
}

function test_dashboard_v2_shop_windows_last_7_days()
{
	$midnight = mktime(0, 0, 0, 3, 10, 2026);
	$windows = pg_dashboard_v2_shop_windows(1, 0, $midnight);

	pg_assert_same(array(mktime(0, 0, 0, 3, 4, 2026), mktime(0, 0, 0, 3, 11, 2026)), $windows['now']);
	pg_assert_same(array(mktime(0, 0, 0, 2, 25, 2026), mktime(0, 0, 0, 3, 4, 2026)), $windows['before'], 'the 7 days before');
	pg_assert_same(array(mktime(0, 0, 0, 3, 4, 2026), mktime(0, 0, 0, 3, 5, 2026)), $windows['d0'], 'six days ago first');
	pg_assert_same(array($midnight, mktime(0, 0, 0, 3, 11, 2026)), $windows['d6'], 'today last');
}

function test_dashboard_v2_shop_windows_today_against_last_year()
{
	$midnight = mktime(0, 0, 0, 3, 10, 2026);
	$windows = pg_dashboard_v2_shop_windows(0, 1, $midnight);

	pg_assert_same(array($midnight, mktime(0, 0, 0, 3, 11, 2026)), $windows['now']);
	pg_assert_same(array(mktime(0, 0, 0, 3, 10, 2025), mktime(0, 0, 0, 3, 11, 2025)), $windows['before']);
}

function test_dashboard_v2_shop_columns_name_every_window()
{
	$sql = pg_dashboard_v2_shop_columns(array('now' => array(10, 20), 'd0' => array(30, 40)));

	pg_assert_contains('orders.order_date >= 10 AND orders.order_date < 20 THEN orders.total ELSE 0 END), 0) AS now_total', $sql);
	pg_assert_contains('AS now_count', $sql);
	pg_assert_contains('AS d0_total', $sql);
}

function test_dashboard_v2_shop_order_rows_say_the_state()
{
	$order = array('id' => 7, 'order_number' => 'A-7', 'status' => 'complete', 'total' => 12345, 'timestamp' => time(), 'name' => 'Ayşe', 'shipped' => false);

	$row = pg_dashboard_v2_order_row($order, true);
	pg_assert_same(array('Ayşe', 'A-7', lang('Not shipped yet'), 'warn', 'view_order.php?id=7'), array($row['text'], $row['number'], $row['note'], $row['tone'], $row['url']));
	pg_assert_contains('123', $row['amount']);

	$order['shipped'] = true;
	pg_assert_same(array(lang('Shipped'), 'good'), array_values(array_intersect_key(pg_dashboard_v2_order_row($order, true), array('note' => 1, 'tone' => 1))));

	$order['status'] = 'cancelled';
	pg_assert_same('bad', pg_dashboard_v2_order_row($order, true)['tone'], 'cancelled wins over shipped');

	$order['status'] = 'complete';
	pg_assert_same('', pg_dashboard_v2_order_row($order, false)['note'], 'no shipping, no shipping state');
}

function test_dashboard_v2_shop_offer_rows()
{
	$lists = array(
		'expiring'  => array(array('id' => 1, 'code' => 'SPRING', 'end_date' => '2026-03-12'), array('id' => 2, 'code' => '', 'end_date' => '2026-03-16')),
		'active'    => array(array('id' => 3, 'code' => 'ALWAYS', 'end_date' => PG_OFFER_OPEN_END_DATE)),
		'expired'   => array(array('id' => 4, 'code' => 'OLD', 'end_date' => '2026-01-01')),
		'sentences' => array(1 => '10% off', 3 => 'Free shipping'),
	);

	$rows = pg_dashboard_v2_offer_rows($lists, '2026-03-10');

	pg_assert_same(array('SPRING', lang('(no code)'), 'ALWAYS'), array_column($rows, 'code'), 'running offers only, ending soonest first');
	pg_assert_same(array('bad', 'warn', 'good'), array_column($rows, 'tone'));
	pg_assert_same(lang('Open-ended'), $rows[2]['days']);
	pg_assert_same('10% off', $rows[0]['note']);
	pg_assert_same('', $rows[1]['note']);
	pg_assert_same(1, count(pg_dashboard_v2_offer_rows($lists, '2026-03-10', 1)), 'the limit holds');
}

function test_dashboard_v2_shop_fx_rows_carry_the_last_rate()
{
	$currencies = array(
		array('id' => 1, 'base' => 1, 'code' => 'TRY', 'name' => 'Turkish Lira', 'exchange_rate' => 1),
		array('id' => 2, 'base' => 0, 'code' => 'usd', 'name' => 'US Dollar', 'exchange_rate' => 0.025),
	);
	$dates = array('2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10');
	$history = array('USD' => array('2026-03-03' => 40.0, '2026-03-06' => 41.0, '2026-03-10' => 42.0));

	$rows = pg_dashboard_v2_fx_rows($currencies, $history, $dates, '₺');

	pg_assert_same(1, count($rows), 'the base currency is not a row');
	pg_assert_same('USD', $rows[0]['code']);
	pg_assert_same(array(40.0, 40.0, 41.0, 41.0, 41.0, 41.0, 42.0), $rows[0]['points'], 'a day without a rate takes the last earlier one');
	pg_assert_same('up', $rows[0]['tone']);
	pg_assert_contains('40', $rows[0]['rate'], 'one unit in the base currency: 1 / 0.025');
	pg_assert_same(lang(array('string' => 'up {var:1}%', 'vars' => pg_format_number(5, 2))), $rows[0]['change']);
}

function test_dashboard_v2_shop_fx_rows_without_history()
{
	$rows = pg_dashboard_v2_fx_rows(array(array('id' => 2, 'base' => 0, 'code' => 'EUR', 'name' => 'Euro', 'exchange_rate' => 0)), array(), array('2026-03-10'), '₺');

	pg_assert_same(array(), $rows[0]['points']);
	pg_assert_same('-', $rows[0]['rate']);
	pg_assert_same('', $rows[0]['change']);
}

function test_dashboard_v2_shop_salesmap_data_shades_the_home_country()
{
	$collect = array(
		'status' => 'ok', 'home' => 'TR', 'home_name' => 'Turkey', 'home_map' => 'tr-regions.svg',
		'periods' => array('d30' => array(
			'total' => 90000, 'count' => 9,
			'countries' => array('TR' => array('code' => 'TR', 'name' => 'Turkey', 'total' => 80000, 'count' => 8)),
			'regions' => array(
				'TR|TR-34' => array('code' => 'TR-34', 'name' => 'İstanbul', 'country' => 'TR', 'total' => 60000, 'count' => 6),
				'TR|TR-06' => array('code' => 'TR-06', 'name' => 'Ankara', 'country' => 'TR', 'total' => 20000, 'count' => 2),
				'DE|BE' => array('code' => '', 'name' => 'Berlin', 'country' => 'DE', 'total' => 10000, 'count' => 1),
			),
		)),
	);

	$data = pg_dashboard_v2_salesmap_data($collect, '/pg/assets/maps/');

	pg_assert_same('/pg/assets/maps/tr-regions.svg', $data['map_url']);
	pg_assert_same(array('İstanbul', 'Ankara'), array_column($data['rows'], 'text'), 'the home country\'s regions only');
	$shades = (array) $data['shades'];
	ksort($shades);
	pg_assert_same(array('TR-06', 'TR-34'), array_keys($shades), 'a step for each region of the map, none for Berlin');
	pg_assert_true($shades['TR-34'] > $shades['TR-06'], 'the bigger region is the darker step');
}

function test_dashboard_v2_shop_salesmap_data_without_a_home_map()
{
	$collect = array(
		'status' => 'ok', 'home' => 'DE', 'home_name' => 'Germany', 'home_map' => '',
		'periods' => array('d30' => array(
			'total' => 10000, 'count' => 1,
			'countries' => array('DE' => array('code' => 'DE', 'name' => 'Germany', 'total' => 10000, 'count' => 1)),
			'regions' => array(),
		)),
	);

	$data = pg_dashboard_v2_salesmap_data($collect, '/m/');

	pg_assert_same('', $data['map_url']);
	pg_assert_same(array('Germany'), array_column($data['rows'], 'text'), 'countries instead');
	pg_assert_same(lang('Countries'), $data['rows_title']);
}

function test_dashboard_v2_shop_salesmap_data_empty()
{
	$data = pg_dashboard_v2_salesmap_data(array('status' => 'empty', 'periods' => array()), '/m/');

	pg_assert_same(lang('There are no sales in this period.'), $data['empty']);
}

function pg_test_dashboard_v2_salesmap_collect()
{
	$region = function ($code, $name, $state, $country, $country_name, $total, $count, $cities) {
		return array('code' => $code, 'name' => $name, 'state' => $state, 'country' => $country, 'country_name' => $country_name,
			'total' => $total, 'count' => $count, 'cities' => $cities);
	};

	return array(
		'status' => 'ok', 'home' => 'TR', 'home_name' => 'Turkey', 'home_map' => 'tr-regions.svg', 'first' => 1700000000,
		'periods' => array('d30' => array(
			'from' => 1790000000, 'total' => 90000, 'count' => 9,
			'countries' => array(
				'TR' => array('code' => 'TR', 'name' => 'Turkey', 'total' => 80000, 'count' => 8),
				'DE' => array('code' => 'DE', 'name' => 'Germany', 'total' => 10000, 'count' => 1),
			),
			'regions' => array(
				'TR|TR-34' => $region('TR-34', 'İstanbul', 'Istanbul', 'TR', 'Turkey', 60000, 6,
					array(array('name' => 'Kadıköy', 'total' => 30000), array('name' => '', 'total' => 20000), array('name' => 'Beşiktaş', 'total' => 10000))),
				'TR|TR-06' => $region('TR-06', 'Ankara', 'Ankara', 'TR', 'Turkey', 20000, 1, array()),
				'DE|berlin' => $region('', 'Berlin', 'Berlin', 'DE', 'Germany', 10000, 1, array()),
			),
		)),
	);
}

// Every province of the map names itself under the pointer and the
// keyboard: the ones that sold first, in sales order, with their figures
// and best cities; the rest, in the map's own order, with "No sales".
function test_dashboard_v2_shop_salesmap_data_names_every_place()
{
	$data = pg_dashboard_v2_salesmap_data(pg_test_dashboard_v2_salesmap_collect(), '/m/');
	$places = $data['places'];

	pg_assert_same(count(pg_sales_map_tr_regions()), count($places), 'every province of the map');
	pg_assert_same(array('TR-34', 'TR-06', 'TR-01'), array_slice(array_column($places, 'id'), 0, 3), 'sold first, by sales');
	pg_assert_same("İstanbul\n" . pg_dashboard_v2_money(60000) . ' · ' . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => '6', 'suffix' => 's')) . "\nKadıköy, Beşiktaş", $places[0]['text']);
	pg_assert_same("Ankara\n" . pg_dashboard_v2_money(20000) . ' · ' . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => '1', 'suffix' => '')), $places[1]['text'], 'no city line without cities');
	pg_assert_same("Adana\n" . lang('No sales'), $places[2]['text']);
	pg_assert_same(array(), array_filter(array_column($places, 'url')), 'nothing opens without the orders screen');
	pg_assert_same(array(), array_filter(array_column($data['rows'], 'url')), 'nor do the rows');
	pg_assert_same(lang(array('string' => 'Sales by region in {var:1}, last 30 days', 'vars' => pg_geo_country_name('TR'))), $data['map_label'], 'the country in the reader\'s language');
}

// For a person who may work the orders, a province that sold and its row
// open the orders it counted: its country and the spelling that sold most,
// over the card's 30 days.
function test_dashboard_v2_shop_salesmap_data_links_the_orders()
{
	$data = pg_dashboard_v2_salesmap_data(pg_test_dashboard_v2_salesmap_collect(), '/m/', 6, true);
	$url = $data['places'][0]['url'];
	parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

	pg_assert_same('view_orders.php', (string) parse_url($url, PHP_URL_PATH));
	pg_assert_same('Turkey', $query['billing_country']);
	pg_assert_same('Istanbul', $query['billing_state']);
	pg_assert_same(date('Y', 1790000000), $query['start_year']);
	pg_assert_same('any', $query['type']);
	pg_assert_same($url, $data['rows'][0]['url'], 'the row opens the same orders');
	pg_assert_same('', $data['places'][2]['url'], 'a province that sold nothing opens nothing');

	$collect = pg_test_dashboard_v2_salesmap_collect();
	$collect['home_map'] = '';
	$data = pg_dashboard_v2_salesmap_data($collect, '/m/', 6, true);
	parse_str((string) parse_url($data['rows'][1]['url'], PHP_URL_QUERY), $query);

	pg_assert_same(array(), $data['places'], 'no map, no places');
	pg_assert_same(pg_geo_country_label('Germany', 'DE'), $data['rows'][1]['text'], 'a country row in the panel language');
	pg_assert_same('Germany', $query['billing_country'], 'a country row opens its country by the stored name');
	pg_assert_false(isset($query['billing_state']));
}

// The cards read the widgets' own queries: one definition of an order, an
// offer list and a currency list.
function test_dashboard_v2_shop_cards_share_the_widget_queries()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains('$orders = pg_dashboard_recent_orders(50);', $read('includes/dashboard/widgets/widget_8.php'));
	pg_assert_contains('pg_dashboard_recent_orders(3)', $read('includes/dashboard/v2/cards/orders.php'));
	pg_assert_contains('$offer_lists = pg_dashboard_offer_lists($today, $deadline);', $read('includes/dashboard/widgets/widget_15.php'));
	pg_assert_contains('pg_dashboard_offer_lists(', $read('includes/dashboard/v2/cards/offers.php'));
	pg_assert_contains('$currencies = pg_dashboard_currencies();', $read('includes/dashboard/widgets/widget_16.php'));
	pg_assert_contains('pg_dashboard_best_sellers(', $read('includes/dashboard/v2/cards/shop.php'));
	pg_assert_contains('pg_sales_map_collect()', $read('includes/dashboard/v2/cards/salesmap.php'));
}

// Card texts are written with textContent, so money carries the symbol's
// character, never its HTML entity.
function test_dashboard_v2_shop_money_is_plain_text()
{
	$money = pg_dashboard_v2_money(1234567);

	pg_assert_false(strpos($money, '&#') !== false, 'no entity');
	pg_assert_same(pg_format_money(12345.67, html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $money);
	pg_assert_same('&#8378;', htmlspecialchars_decode('&#8378;'), 'the entity the store keeps is not decoded by h()-style helpers');
	pg_assert_same('₺', html_entity_decode('&#8378;', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// prepare_amount() returns HTML: the pipeline's orders box read
// "Average &#8378;4.180,80". No card sends it.
function test_dashboard_v2_cards_send_no_html_money()
{
	$files = glob(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/*.php');

	pg_assert_true(count($files) > 10, 'the card files are found');
	foreach ($files as $file) {
		pg_assert_false(strpos((string) file_get_contents($file), 'prepare_amount(') !== false, basename($file) . ' sends plain-text money');
	}
}

function test_dashboard_v2_shop_url_opens_a_screen_the_person_may_open()
{
	// The orders list for whoever may open it.
	pg_assert_same('view_orders.php', pg_dashboard_v2_shop_url(true, true));
	pg_assert_same('view_orders.php', pg_dashboard_v2_shop_url(true, false));
	// A user with commerce reports only: the reports, not a refused list.
	pg_assert_same('view_order_reports.php', pg_dashboard_v2_shop_url(false, true));
	pg_assert_same('', pg_dashboard_v2_shop_url(false, false));
}

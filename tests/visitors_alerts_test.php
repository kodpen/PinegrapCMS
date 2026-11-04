<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the alerts section of the Visitors overview
 * (includes/visitors/alerts.php): each rule as a pure function over figure
 * arrays, its threshold and its minimum.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/alerts.php');

function test_visitors_alerts_fall_needs_more_than_the_share()
{
	pg_assert_same(null, pg_visitors_alerts_fall(70, 100, 0.3), 'exactly 30 % is not more than 30 %');
	pg_assert_same(31, pg_visitors_alerts_fall(69, 100, 0.3));
	pg_assert_same(null, pg_visitors_alerts_fall(120, 100, 0.3), 'a rise');
	pg_assert_same(null, pg_visitors_alerts_fall(0, 0, 0.3), 'nothing before');
	pg_assert_same(null, pg_visitors_alerts_fall(10, 40, 0.3, 50), 'under the minimum');
}

function test_visitors_alerts_visits_down_by_more_than_thirty_percent()
{
	$alert = pg_visitors_alerts_visits(array('visits' => 60), array('visits' => 100));

	pg_assert_same('visits', $alert['rule']);
	pg_assert_same('traffic', $alert['section']);
	pg_assert_same(40, $alert['percent']);
	pg_assert_same(60, $alert['now']);
	pg_assert_same(100, $alert['before']);

	pg_assert_same(null, pg_visitors_alerts_visits(array('visits' => 80), array('visits' => 100)), 'down 20 %');
	pg_assert_same(null, pg_visitors_alerts_visits(array('visits' => 5), array('visits' => 20)), 'too few visits before');
	pg_assert_same(null, pg_visitors_alerts_visits(array('visits' => 5), null), 'not compared');
}

function test_visitors_alerts_landing_page_lost_more_than_half()
{
	$now = array(
		array('value' => 'Products', 'visits' => 20),
		array('value' => 'about', 'visits' => 40),
		array('value' => '/', 'visits' => 10),
	);
	$before = array(
		array('value' => 'products', 'visits' => 100),
		array('value' => 'about', 'visits' => 60),
		array('value' => '', 'visits' => 30),
		array('value' => '/', 'visits' => 30),
		array('value' => 'gone', 'visits' => 55),
		array('value' => 'small', 'visits' => 49),
	);

	$alerts = pg_visitors_alerts_landing($now, $before);

	pg_assert_same(array('products', 'gone', ''), array_column($alerts, 'page'), 'largest loss first, case and the home page folded');
	pg_assert_same(80, $alerts[0]['percent']);
	pg_assert_same(20, $alerts[0]['now']);
	pg_assert_same(0, $alerts[1]['now'], 'a page with no visit now');
	pg_assert_same(60, $alerts[2]['before'], "'' and '/' are one page");
	pg_assert_same('content', $alerts[0]['section']);
	pg_assert_same(array(), pg_visitors_alerts_landing($now, null));
}

function test_visitors_alerts_landing_names_at_most_three_pages()
{
	$before = array();

	foreach (range(1, 5) as $i) {
		$before[] = array('value' => 'page' . $i, 'visits' => 50 + $i);
	}

	$alerts = pg_visitors_alerts_landing(array(), $before);

	pg_assert_same(array('page5', 'page4', 'page3'), array_column($alerts, 'page'));
}

function test_visitors_alerts_conversion_rate_halved()
{
	$before = array('visits' => 1000, 'orders_completed' => 20);

	$alert = pg_visitors_alerts_rate(array('visits' => 1000, 'orders_completed' => 9), $before);

	pg_assert_same('rate', $alert['rule']);
	pg_assert_same('conversions', $alert['section']);
	pg_assert_same(0.9, $alert['now']);
	pg_assert_same(2.0, $alert['before']);
	pg_assert_same(55, $alert['percent']);

	pg_assert_same(null, pg_visitors_alerts_rate(array('visits' => 1000, 'orders_completed' => 10), $before), 'exactly half');
	pg_assert_same(null, pg_visitors_alerts_rate(array('visits' => 1000, 'orders_completed' => 0), array('visits' => 1000, 'orders_completed' => 4)), 'too few orders before');
	pg_assert_same(null, pg_visitors_alerts_rate(array('visits' => 0, 'orders_completed' => 0), $before), 'no visit now: the visits rule speaks');
}

function test_visitors_alerts_google_clicks_down()
{
	$alert = pg_visitors_alerts_clicks(array('clicks' => 50), array('clicks' => 200));

	pg_assert_same('clicks', $alert['rule']);
	pg_assert_same('google', $alert['section']);
	pg_assert_same(75, $alert['percent']);

	pg_assert_same(null, pg_visitors_alerts_clicks(array('clicks' => 150), array('clicks' => 200)));
	pg_assert_same(null, pg_visitors_alerts_clicks(array('clicks' => 0), array('clicks' => 40)), 'too few clicks before');
	pg_assert_same(null, pg_visitors_alerts_clicks(array('clicks' => 0), null));
}

function test_visitors_alerts_google_pages_lost_more_than_half()
{
	$now = array(
		array('name' => 'https://example.com/a', 'clicks' => 5),
		array('name' => 'https://example.com/b', 'clicks' => 30),
	);
	$before = array(
		array('name' => 'https://example.com/a', 'clicks' => 25),
		array('name' => 'https://example.com/b', 'clicks' => 40),
		array('name' => 'https://example.com/c', 'clicks' => 19),
	);

	$alerts = pg_visitors_alerts_google_pages($now, $before, 'pages');

	pg_assert_same(array('https://example.com/a'), array_column($alerts, 'page'));
	pg_assert_same('pages', $alerts[0]['section']);
	pg_assert_same(80, $alerts[0]['percent']);
	pg_assert_same(array(), pg_visitors_alerts_google_pages($now, null));
}

function test_visitors_alerts_quiet_day_after_ten()
{
	$alert = pg_visitors_alerts_quiet(0, 120, 10);

	pg_assert_same('quiet', $alert['rule']);
	pg_assert_same(120, $alert['before']);

	pg_assert_same(null, pg_visitors_alerts_quiet(0, 120, 9), 'before ten');
	pg_assert_same(null, pg_visitors_alerts_quiet(1, 120, 15), 'a visit today');
	pg_assert_same(null, pg_visitors_alerts_quiet(0, 0, 15), 'a quiet site, not broken tracking');
}

function test_visitors_alerts_text_carries_the_figures()
{
	$text = pg_visitors_alerts_text(array('rule' => 'visits', 'now' => 60, 'before' => 100, 'percent' => 40));

	pg_assert_true(strpos($text, '40') !== false && strpos($text, '60') !== false && strpos($text, '100') !== false, $text);
}

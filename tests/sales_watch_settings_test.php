<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sales watch's settings (includes/fn/sales_watch.php): the
 * config_text row read with its defaults, the sensitivity levels, the price
 * drop share, the posted form turned into the stored row, and the pause of
 * the store or of one channel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No row, or a row that is not JSON: every alert on, normal sensitivity,
// nothing taken off sale, half the usual price, nothing paused.
function test_sales_watch_settings_defaults()
{
	foreach (array('', 'not json', '[1,2]', null) as $stored) {

		$settings = pg_sales_watch_settings_normalize($stored);

		pg_assert_true($settings['orders_stopped']);
		pg_assert_true($settings['payment_broken']);
		pg_assert_true($settings['price_error']);
		pg_assert_true($settings['return_spike']);
		pg_assert_same('normal', $settings['level']);
		pg_assert_same(0.01, $settings['sensitivity']);
		pg_assert_false($settings['close_products']);
		pg_assert_same(0.5, $settings['price_drop']);
		pg_assert_same(array(), $settings['paused']);
	}
}

// A stored row: an alert switched off, a level, the price drop share held to
// 10-90%, pauses kept only for known channel keys.
function test_sales_watch_settings_stored()
{
	$settings = pg_sales_watch_settings_normalize(json_encode(array(
		'off'            => array('payment_broken', 'return_spike', 'unknown'),
		'level'          => 'high',
		'close_products' => 1,
		'price_drop'     => 40,
		'paused'         => array('store' => 1800000000, 'mp_4' => '1800003600', 'bad key' => 5, 'site' => 0),
	)));

	pg_assert_true($settings['orders_stopped']);
	pg_assert_false($settings['payment_broken']);
	pg_assert_true($settings['price_error']);
	pg_assert_false($settings['return_spike']);
	pg_assert_same('high', $settings['level']);
	pg_assert_same(0.05, $settings['sensitivity']);
	pg_assert_true($settings['close_products']);
	pg_assert_same(0.4, $settings['price_drop']);
	pg_assert_same(array('store' => 1800000000, 'mp_4' => 1800003600), $settings['paused']);

	$low = pg_sales_watch_settings_normalize(array('level' => 'low', 'price_drop' => 99));
	pg_assert_same(0.001, $low['sensitivity']);
	pg_assert_same(0.9, $low['price_drop']);

	$odd = pg_sales_watch_settings_normalize(array('level' => 'loud', 'price_drop' => 'x'));
	pg_assert_same('normal', $odd['level']);
	pg_assert_same(0.5, $odd['price_drop']);
}

// The stored row read back gives the same settings.
function test_sales_watch_settings_round_trip()
{
	$settings = pg_sales_watch_settings_normalize(array(
		'off'            => array('orders_stopped'),
		'level'          => 'low',
		'close_products' => true,
		'price_drop'     => 30,
		'paused'         => array('site' => 1800000000),
	));

	pg_assert_same($settings, pg_sales_watch_settings_normalize(pg_sales_watch_settings_encode($settings)));
}

// The pause holds until its end; the store's pause holds every channel.
function test_sales_watch_paused()
{
	$settings = pg_sales_watch_settings_normalize(array('paused' => array('mp_4' => 1000)));

	pg_assert_true(pg_sales_watch_paused($settings, 'mp_4', 999));
	pg_assert_false(pg_sales_watch_paused($settings, 'mp_4', 1000));
	pg_assert_false(pg_sales_watch_paused($settings, 'site', 999));

	$store = pg_sales_watch_settings_normalize(array('paused' => array('store' => 1000)));

	pg_assert_true(pg_sales_watch_paused($store, 'site', 999));
	pg_assert_true(pg_sales_watch_paused($store, 'store', 999));
	pg_assert_false(pg_sales_watch_paused($store, 'site', 1001));
}

// A date pauses through its whole day: the end is the next midnight of the
// site's time zone. Empty is no pause; a bad date is refused.
function test_sales_watch_pause_end()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');

	pg_assert_same(mktime(0, 0, 0, 1, 2, 2027), pg_sales_watch_pause_end('2027-01-01'));
	pg_assert_same(0, pg_sales_watch_pause_end(''));
	pg_assert_same(0, pg_sales_watch_pause_end('  '));
	pg_assert_false(pg_sales_watch_pause_end('2027-02-30'));
	pg_assert_false(pg_sales_watch_pause_end('01.01.2027'));

	date_default_timezone_set($zone);
}

// The posted form: unchecked alerts go to the off list, a past pause and an
// unknown channel are dropped, a bad date is named.
function test_sales_watch_settings_from_post()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('UTC');

	$now = mktime(12, 0, 0, 6, 1, 2027);

	$result = pg_sales_watch_settings_from_post(array(
		'alerts'         => array('orders_stopped', 'price_error', 'nonsense'),
		'level'          => 'high',
		'close_products' => '1',
		'price_drop'     => '35',
		'pause'          => array(
			'store' => '',
			'site'  => '2027-06-03',
			'mp_4'  => '2027-05-01',
			'mp_9'  => '2027-06-10',
			'mp_7'  => 'tomorrow',
		),
	), array('site', 'mp_4', 'mp_7'), $now);

	pg_assert_same(array('mp_7'), $result['invalid']);

	$settings = $result['settings'];

	pg_assert_true($settings['orders_stopped']);
	pg_assert_false($settings['payment_broken']);
	pg_assert_true($settings['price_error']);
	pg_assert_false($settings['return_spike']);
	pg_assert_same('high', $settings['level']);
	pg_assert_true($settings['close_products']);
	pg_assert_same(0.35, $settings['price_drop']);
	pg_assert_same(array('site' => mktime(0, 0, 0, 6, 4, 2027)), $settings['paused']);

	$empty = pg_sales_watch_settings_from_post(array(), array('site'), $now);
	pg_assert_false($empty['settings']['orders_stopped']);
	pg_assert_false($empty['settings']['close_products']);
	pg_assert_same('normal', $empty['settings']['level']);
	pg_assert_same(0.5, $empty['settings']['price_drop']);

	date_default_timezone_set($zone);
}

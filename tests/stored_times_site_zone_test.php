<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stored times on Subscriptions and the short link state line follow the
 * site's date order and clock and the person's zone (get_absolute_time()),
 * not a fixed date('d.m.Y') in the server's zone. The Firewall log row and
 * the Paraşüt connection line are checked in their own tests.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array('DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $stored_times_test_name => $stored_times_test_value) {
	if (!defined($stored_times_test_name)) {
		define($stored_times_test_name, $stored_times_test_value);
	}
}

function test_stored_times_short_link_state_lines()
{
	$past = time() - 3600;
	$future = time() + 86400;
	$text = function ($timestamp) {
		return get_absolute_time(array('timestamp' => $timestamp, 'format' => 'plain_text'));
	};

	$expired = pg_short_link_state(array('link_mode' => 'permanent', 'expires_at' => $past));
	pg_assert_same('expired', $expired['status']);
	pg_assert_same(lang(array('string' => 'Expired on {var:1}', 'vars' => $text($past))), $expired['label']);

	$used = pg_short_link_state(array('link_mode' => 'once', 'used_at' => $past));
	pg_assert_same('used', $used['status']);
	pg_assert_same(lang(array('string' => 'Opened on {var:1}; no longer works', 'vars' => $text($past))), $used['label']);

	$open = pg_short_link_state(array('link_mode' => 'permanent', 'expires_at' => $future));
	pg_assert_same('open', $open['status']);
	pg_assert_same(lang(array('string' => 'Valid until {var:1}', 'vars' => $text($future))), $open['label']);
	pg_assert_same(false, strpos($open['label'], '<time'), 'plain text inside the label');
}

function test_stored_times_subscriptions_screen_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_subscriptions.php');

	pg_assert_same(0, preg_match_all("/\\bdate\\('/", $source), 'view_subscriptions.php prints a fixed date() pattern');
	pg_assert_contains("h(get_absolute_time(array('timestamp' => (int) \$time, 'type' => 'date', 'format' => 'plain_text')))", $source);
	pg_assert_contains("prepare_form_data_for_output(\$pending['date'], 'date', false)", $source);
	pg_assert_same(3, substr_count($source, "'format' => 'plain_text')))"), 'the date helper, event and order times');
}

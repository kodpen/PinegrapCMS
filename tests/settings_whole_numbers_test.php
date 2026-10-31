<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings whole-number boxes (image size limits, firewall log and rate
 * limits, login throttle, form submission limits, gift card validity, reward
 * membership length, Mailchimp sync) read a figure grouped with the panel's
 * thousands separator, so "2.400" on a Turkish panel is 2400, never 2.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_whole_numbers_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_whole_numbers_reader_takes_the_thousands_separator()
{
	$s = pg_number_separators();
	pg_assert_same(2400, pg_parse_whole_number('2' . $s['thousands'] . '400', 20000), 'a grouped figure is the whole figure');
	pg_assert_same(20000, pg_parse_whole_number('20' . $s['thousands'] . '000', 5000000));
	pg_assert_same(1095, pg_parse_whole_number('1' . $s['thousands'] . '095', 65535));
	pg_assert_same(250, pg_parse_whole_number('250', 65535));
	pg_assert_same(3, pg_parse_whole_number('2' . $s['decimal'] . '6', 100), 'a fraction is rounded');
}

function test_settings_whole_numbers_reader_bounds()
{
	pg_assert_same(0, pg_parse_whole_number('', 100), 'blank is 0');
	pg_assert_same(0, pg_parse_whole_number(null, 100));
	pg_assert_same(0, pg_parse_whole_number('abc', 100), 'unreadable is 0');
	pg_assert_same(0, pg_parse_whole_number('-5', 100), 'never negative');
	pg_assert_same(65535, pg_parse_whole_number('99999999', 65535), 'held to the upper bound');
	pg_assert_same(4294967295, pg_parse_whole_number('99999999999', 4294967295));
}

function test_settings_whole_numbers_save_modules_use_the_reader()
{
	$features = settings_whole_numbers_test_source('includes/settings/features.save.php');
	foreach (array('image_product_max_dimension', 'image_product_min_dimension', 'image_file_max_dimension', 'image_file_resize_trigger') as $name) {
		pg_assert_contains("pg_parse_whole_number(post_value('" . $name . "'), 20000)", $features, $name);
	}

	$firewall = settings_whole_numbers_test_source('includes/settings/firewall.save.php');
	foreach (array('waf_rate_limit_requests', 'waf_rate_limit_sensitive', 'waf_rate_limit_api', 'waf_auto_ban_threshold', 'waf_auto_ban_minutes', 'waf_log_retention_days', 'waf_log_max_rows', 'waf_inflight_limit', 'waf_auto_ban_max_minutes') as $name) {
		pg_assert_contains("pg_parse_whole_number(post_value('" . $name . "'),", $firewall, $name);
	}

	$security = settings_whole_numbers_test_source('includes/settings/security.save.php');
	foreach (array('login_throttle_attempts', 'login_throttle_minutes', 'login_throttle_lockout', 'login_throttle_captcha_after', 'form_limit_ip_hour', 'form_limit_recipient_day', 'form_limit_domain_hour') as $name) {
		pg_assert_contains("pg_parse_whole_number(post_value('" . $name . "'),", $security, $name);
	}

	$commerce = settings_whole_numbers_test_source('includes/settings/commerce.save.php');
	pg_assert_contains("pg_parse_whole_number(post_value('ecommerce_gift_card_validity_days'), 65535)", $commerce);
	pg_assert_contains("pg_parse_whole_number(post_value('ecommerce_reward_program_membership_days'), 4294967295)", $commerce);

	$contact = settings_whole_numbers_test_source('includes/settings/contact.save.php');
	pg_assert_contains("pg_parse_whole_number(post_value('mailchimp_sync_days'), 65535)", $contact);
	pg_assert_contains("pg_parse_whole_number(post_value('mailchimp_sync_limit'), 4294967295)", $contact);

	foreach (array($features, $firewall, $security) as $source) {
		pg_assert_true(!preg_match("/\\(int\\) post_value\\('(image_|waf_|login_throttle_|form_limit_)/", $source), 'no (int) read of a whole-number box is left');
	}
}

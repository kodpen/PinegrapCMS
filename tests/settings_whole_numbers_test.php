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

function test_settings_whole_numbers_range_check_refuses_what_the_reader_would_clamp()
{
	$s = pg_number_separators();
	pg_assert_same(10, pg_whole_number_in_range('10', 1, 1000));
	pg_assert_same(1, pg_whole_number_in_range(' 1 ', 1, 1000), 'trimmed');
	pg_assert_same(0, pg_whole_number_in_range('0', 0, 50), 'zero where the range allows it');
	pg_assert_same(2400, pg_whole_number_in_range('2' . $s['thousands'] . '400', 1, 60000), 'a grouped figure');
	pg_assert_same(null, pg_whole_number_in_range('', 1, 1000), 'blank');
	pg_assert_same(null, pg_whole_number_in_range(null, 1, 1000));
	pg_assert_same(null, pg_whole_number_in_range('abc', 1, 1000), 'letters');
	pg_assert_same(null, pg_whole_number_in_range('-3', 1, 1000), 'a sign');
	pg_assert_same(null, pg_whole_number_in_range('12' . $s['decimal'] . '5', 1, 1000), 'a fraction');
	pg_assert_same(null, pg_whole_number_in_range('0', 1, 1000), 'below the range');
	pg_assert_same(null, pg_whole_number_in_range('99999', 1, 365), 'above the range');
	pg_assert_same(null, pg_whole_number_in_range(array('5'), 1, 10), 'not a scalar');
}

function test_settings_whole_numbers_limits_are_refused_before_the_write()
{
	$firewall = settings_whole_numbers_test_source('includes/settings/firewall.save.php');
	$country = strpos($firewall, 'Country restriction (2026.4.10)');
	foreach (array('waf_rate_limit_requests', 'waf_rate_limit_sensitive', 'waf_rate_limit_api', 'waf_inflight_limit', 'waf_auto_ban_threshold', 'waf_auto_ban_minutes', 'waf_auto_ban_max_minutes', 'waf_log_retention_days', 'waf_log_max_rows') as $name) {
		$at = strpos($firewall, "'" . $name . "' ");
		pg_assert_true(($at !== false) && ($at < $country), $name . ' checked before the country block writes');
	}

	$security = settings_whole_numbers_test_source('includes/settings/security.save.php');
	$first_write = strpos($security, "post_value('ecommerce_reset_encryption_key') == 1");
	foreach (array('login_throttle_attempts', 'login_throttle_minutes', 'login_throttle_lockout', 'login_throttle_captcha_after', 'form_limit_ip_hour', 'form_limit_recipient_day', 'form_limit_domain_hour', 'membership_expiration_warning_email_page_id', 'membership_expiration_warning_email_days_before_expiration') as $name) {
		$at = strpos($security, "mark_error('" . $name . "'");
		if ($at === false) {
			$at = strpos($security, "'" . $name . "' ");
		}
		pg_assert_true(($at !== false) && ($at < $first_write), $name . ' checked before anything is written');
	}

	// A box where 0 switches a check off is refused when blank or not a
	// figure, never read as 0: that turned a form limit off without a word.
	foreach (array(
		"'login_throttle_captcha_after' => array(lang('Ask a Question After'), 0, 100)",
		"'form_limit_ip_hour'       => array(lang('Per visitor address'), 0, 10000)",
		"'form_limit_recipient_day' => array(lang('Per e-mail address'), 0, 10000)",
		"'form_limit_domain_hour'   => array(lang('Per e-mail domain'), 0, 10000)",
	) as $rule) {
		pg_assert_contains($rule, $security, 'range checked: ' . $rule);
	}
	pg_assert_contains('pg_whole_number_in_range(post_value($zero_off_field), $zero_off_rule[1], $zero_off_rule[2]) === null', $security, 'the zero-off boxes go through the range check');
	$checked = strpos($security, 'foreach ($zero_off_boxes');
	$stop = ($checked === false) ? false : strpos($security, '$liveform->check_form_errors()', $checked);
	pg_assert_true(($stop !== false) && ($stop < strpos($security, 'db("UPDATE config')), 'the save stops on those errors before it writes');
	pg_assert_same(null, pg_whole_number_in_range('', 0, 10000), 'a blank form limit is refused');
	pg_assert_same(null, pg_whole_number_in_range('admin', 0, 10000), 'a saved user name is refused');
	pg_assert_same(0, pg_whole_number_in_range('0', 0, 10000), 'a typed 0 still turns the limit off');

	$seo = settings_whole_numbers_test_source('includes/settings/seo.save.php');
	$indexnow_file = strpos($seo, 'If old key exists and changed');
	foreach (array('merchant_country', 'merchant_return_days', 'merchant_transit_days_min', 'merchant_transit_days_max') as $name) {
		$at = strpos($seo, "mark_error('" . $name . "'");
		pg_assert_true(($at !== false) && ($at < $indexnow_file), $name . ' refused before the IndexNow file is written');
	}
	pg_assert_contains("indexnow_key = '\" . escape(\$new_indexnow_key) . \"'", $seo, 'the trimmed IndexNow key is stored');
}

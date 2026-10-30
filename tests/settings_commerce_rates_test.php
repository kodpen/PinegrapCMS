<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings reads Surcharge (%), Default Commission Rate (%), the reward
 * program goal and the Merchant Center shipping rate with a decimal comma
 * and the panel's thousands separator, so "2,5" is not stored as 2 and a
 * goal of "1.000" on a Turkish panel is a thousand points.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_commerce_rates_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_commerce_rates_surcharge_keeps_a_decimal_comma()
{
	pg_assert_same('2.500', pg_settings_surcharge_percentage('2,5'));
	pg_assert_same('2.500', pg_settings_surcharge_percentage('2.5'));
	pg_assert_same('3.125', pg_settings_surcharge_percentage(' 3,125 '));
	pg_assert_same('0', pg_settings_surcharge_percentage(''), 'blank is no surcharge');
	pg_assert_same('0', pg_settings_surcharge_percentage('abc'), 'unreadable is no surcharge');
	pg_assert_same('100.000', pg_settings_surcharge_percentage('250'), 'held to the percent range like a tax rate');
}

function test_settings_commerce_rates_commission_keeps_a_decimal_comma()
{
	pg_assert_same('7.50', pg_parse_commission_rate('7,5'));
	pg_assert_same('7.50', pg_parse_commission_rate('7.5'));
	pg_assert_same('12.35', pg_parse_commission_rate('12,345'), 'rounded to the column\'s two places');
	pg_assert_same('0.00', pg_parse_commission_rate(''));
	pg_assert_same('150.00', pg_parse_commission_rate('150'), 'no new 100% cap');
	pg_assert_same('999.99', pg_parse_commission_rate('5000'), 'held to decimal(5,2)');
}

function test_settings_commerce_rates_reward_goal_reads_the_thousands_separator()
{
	$s = pg_number_separators();
	pg_assert_same(1000, pg_parse_reward_goal('1' . $s['thousands'] . '000'), 'a grouped thousand is a thousand');
	pg_assert_same(250, pg_parse_reward_goal('250'));
	pg_assert_same(0, pg_parse_reward_goal(''));
	pg_assert_same(0, pg_parse_reward_goal('-5'), 'no negative goal');
	pg_assert_same(4294967295, pg_parse_reward_goal('99999999999'), 'held to int(10) unsigned');
}

function test_settings_commerce_rates_save_uses_the_readers()
{
	$source = settings_commerce_rates_test_source('includes/settings/commerce.save.php');
	pg_assert_contains("ecommerce_surcharge_percentage = '\" . escape(pg_settings_surcharge_percentage(post_value('ecommerce_surcharge_percentage'))) . \"'", $source);
	pg_assert_contains("affiliate_default_commission_rate = '\" . escape(pg_parse_commission_rate(post_value('affiliate_default_commission_rate'))) . \"'", $source);
	pg_assert_contains("ecommerce_reward_program_points = '\" . pg_parse_reward_goal(post_value('ecommerce_reward_program_points')) . \"'", $source);

	$seo = settings_commerce_rates_test_source('includes/settings/seo.save.php');
	pg_assert_contains('pg_parse_amount($merchant_shipping_rate_input)', $seo, 'the shipping rate reads a grouped amount');
}

function test_settings_commerce_rates_percent_boxes_open_a_decimal_keyboard()
{
	$source = settings_commerce_rates_test_source('includes/settings/commerce.php');
	foreach (array('ecommerce_surcharge_percentage', 'affiliate_default_commission_rate') as $name) {
		pg_assert_contains('name="' . $name . '" id="' . $name . '" class="form-ghost" size="', $source, $name);
		pg_assert_true((bool) preg_match('/name="' . $name . '"[^\n]*inputmode="decimal"/', $source), $name . ' opens a decimal keyboard');
	}
	pg_assert_true((bool) preg_match('/name="ecommerce_reward_program_points"[^\n]*inputmode="numeric"/', $source), 'the goal stays a whole number keyboard');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cloudflare Tools names a switch's new value in the panel language: the
 * notice and the Site Log line after a toggle print "on", "under attack" and
 * the like through pg_cloudflare_setting_value_label(), never the raw API
 * value, and the Turkish shards hold each word.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cloudflare_tools.php');
require_once(__DIR__ . '/helpers/language_map.php');

function test_cloudflare_setting_value_labels()
{
	pg_assert_same(lang('on'), pg_cloudflare_setting_value_label('on'), 'on');
	pg_assert_same(lang('off'), pg_cloudflare_setting_value_label('off'), 'off');
	pg_assert_same(lang('under attack'), pg_cloudflare_setting_value_label('under_attack'), 'under_attack');
	pg_assert_same(lang('medium'), pg_cloudflare_setting_value_label('medium'), 'medium');
	pg_assert_same(lang('essentially off'), pg_cloudflare_setting_value_label('essentially_off'), 'essentially_off');
	pg_assert_same(lang('low'), pg_cloudflare_setting_value_label('low'), 'low');
	pg_assert_same(lang('high'), pg_cloudflare_setting_value_label('high'), 'high');
	pg_assert_same('something_new', pg_cloudflare_setting_value_label('something_new'), 'an unknown value is shown as it came');
	pg_assert_same('', pg_cloudflare_setting_value_label(array('x')), 'a non-string value is empty');
}

function test_cloudflare_setting_value_words_are_translated()
{
	$map = pg_test_language_map('tr');

	foreach (array('on' => 'açık', 'off' => 'kapalı', 'under attack' => 'saldırı altında', 'medium' => 'orta') as $english => $turkish) {
		pg_assert_same($turkish, $map[$english] ?? null, $english);
	}

	foreach (array('essentially off', 'low', 'high') as $english) {
		pg_assert_true(isset($map[$english]), $english . ' has a Turkish value');
	}
}

function test_cloudflare_toggles_print_the_value_label()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/cloudflare.php');

	pg_assert_false(strpos($source, "'vars' => \$value)") !== false, 'no notice prints the raw value');
	pg_assert_false(strpos($source, ", \$value))") !== false, 'no Site Log line prints the raw value');
	pg_assert_same(10, substr_count($source, 'pg_cloudflare_setting_value_label($value)'), 'five notices and five Site Log lines');
}

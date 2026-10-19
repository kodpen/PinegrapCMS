<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_settings_keywords() (includes/settings/registry.php): the
 * search terms of a settings section, split from the list lang() returns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function test_settings_keywords_split_trim_lower_and_drop_repeats()
{
	pg_assert_same(array('ssl', 'secure mode', 'waf'), pg_settings_keywords(' SSL, secure mode ,, waf, ssl '), 'terms');
	pg_assert_same(array(), pg_settings_keywords(''), 'empty list');
}

function test_settings_keywords_keep_a_number_a_string()
{
	$keywords = pg_settings_keywords('maintenance, 503, 404');

	pg_assert_same(array('maintenance', '503', '404'), $keywords, 'numbers stay strings');
	pg_assert_same('["maintenance","503","404"]', json_encode($keywords), 'and reach the palette as strings');
}

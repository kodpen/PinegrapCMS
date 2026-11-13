<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what Settings › Firewall does on the spot: a save reads the
 * config row again (waf_config() reload, waf_config_reloaded()) and rewrites
 * the ban mirror from it (pg_waf_save_refresh_mirror()), and the screen's
 * Release buttons lift an automatic ban and come back to the screen
 * (pg_waf_release_return()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// The source of one function, up to the next top-level function.
function waf_settings_save_test_function_source($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// A reload replaces the row the request began with, and the mode follows it.
function test_waf_config_reload_replaces_the_row()
{
	$original = waf_config(null, true);
	$row = array('waf_trusted_proxies' => '', 'waf_enabled' => '1', 'waf_mode' => 'monitor');

	waf_config($row, false, true);
	pg_assert_same('monitor', waf_mode(), 'before the save');

	$row['waf_mode'] = 'block';
	waf_config($row, false, true);
	pg_assert_same('block', waf_mode(), 'the saved row');
	pg_assert_true(waf_config_reloaded(), 'the reload is recorded for WAF_ENABLED');

	// A primed row is kept by a plain call, as before.
	waf_config(array('waf_enabled' => '0'));
	pg_assert_same('block', waf_mode(), 'priming does not replace');

	waf_config(is_array($original) ? $original : array('waf_trusted_proxies' => ''), false, true);
	waf_config_reloaded(false);
}

// The save rewrites the mirror after the lists are written, from the row
// read again.
function test_firewall_save_rewrites_the_mirror()
{
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/firewall.save.php');
	$lists = strrpos($save, 'INSERT INTO banned_ip_addresses');
	$refresh = strpos($save, 'pg_waf_save_refresh_mirror();');

	pg_assert_true($lists !== false && $refresh !== false && $refresh > $lists, 'after the list rows');

	$rescue = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/waf_rescue.php');
	$body = waf_settings_save_test_function_source($rescue, 'pg_waf_save_refresh_mirror');
	$reload = strpos($body, 'waf_config(null, false, true);');
	$mirror = strpos($body, 'waf_ban_shield_refresh(true);');

	pg_assert_true($reload !== false && $mirror !== false && $reload < $mirror, 'reload before the rewrite');
	pg_assert_contains('waf_ip_lists(true);', $body);
}

function test_waf_release_return()
{
	pg_assert_same('settings_firewall.php#pgset-iplists', pg_waf_release_return(array('release_return' => 'settings'), 'view_waf_log.php'));
	pg_assert_same('view_waf_log.php?range=7d', pg_waf_release_return(array(), 'view_waf_log.php?range=7d'));
	pg_assert_same('view_waf_log.php', pg_waf_release_return(array('release_return' => 'https://example.com/'), 'view_waf_log.php'), 'no address of its own');
	pg_assert_same('view_waf_log.php', pg_waf_release_return(array('release_return' => array('settings')), 'view_waf_log.php'), 'an array');
}

// Each Release button on the screen submits the one hidden form, which posts
// to the firewall log's release handler and asks to come back.
function test_firewall_screen_release_buttons()
{
	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/firewall.php');

	pg_assert_contains('form="pgset_waf_release" name="release_ip"', $prep);
	pg_assert_contains('id="pgset_waf_release"', $card);
	pg_assert_contains('action="view_waf_log.php"', $card);
	pg_assert_contains('name="release_return" value="settings"', $card);
	pg_assert_contains('get_token_field()', $card);

	$log = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_waf_log.php');
	pg_assert_contains('pg_waf_release_auto_ban($release_ip)', $log);
}

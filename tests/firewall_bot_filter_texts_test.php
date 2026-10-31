<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The bot filter runs inside waf_run(): it does nothing while the firewall
 * is off and refuses only in Block mode (Monitor mode logs would-block).
 * These tests pin the Settings help texts and the System Status check to
 * that behaviour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

/**
 * @param string $path relative to pinegrap/
 * @return string
 */
function firewall_bot_texts_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_firewall_bot_filter_help_says_it_needs_the_firewall()
{
	$source = firewall_bot_texts_source('includes/settings/firewall.php');

	pg_assert_true(strpos($source, 'Works on its own, whether or not the firewall') === false, 'the Bot Filtering card no longer claims to work with the firewall off');
	pg_assert_true(strpos($source, "lang('Works only while the firewall above is enabled") !== false, 'the Bot Filtering card says it needs the firewall on');
	pg_assert_true(strpos($source, "lang('In Block mode, crawlers that are not recognised") !== false, 'Block Unknown Bots says the 403 comes only in Block mode');
}

function test_system_status_bot_filter_has_a_monitor_branch()
{
	$source = firewall_bot_texts_source('includes/fn/system_status.php');
	$message = 'Unknown bots are only being recorded, not blocked, because the firewall is in monitor mode.';

	pg_assert_true(preg_match("/\\\$waf_current_mode === 'monitor'\\)\\s*\\{(?:\\s*\/\/[^\\n]*)*\\s*\\\$output \\.= \\\$makeIcon\\(\\s*'bi-robot',\\s*'fg-warning',\\s*'Unknown Bot Filtering',\\s*'" . preg_quote($message, '/') . "'\\s*\\);\\s*\\\$score -= \\\$weights\\['bot_filter'\\] \\* 0\\.5;/", $source) === 1, 'Monitor mode with Block Unknown Bots on is a warning with the half-weight deduction');
}

function test_firewall_bot_filter_texts_are_keys()
{
	$map = pg_test_language_map('tr');

	foreach (array(
		'Works only while the firewall above is enabled, and refuses bots only in Block mode; in Monitor mode it records the bots it would refuse. Search engines, social link previews, uptime monitors and payment gateway callbacks are recognised and never blocked.',
		'In Block mode, crawlers that are not recognised and not on your allowed list receive a 403 response, reducing server load and preventing visitor count inflation; in Monitor mode they are only recorded. The REST API is exempt, so your own integrations keep working.',
		'Unknown bots are only being recorded, not blocked, because the firewall is in monitor mode.',
	) as $key) {
		pg_assert_true(isset($map[$key]), 'no Turkish key for "' . $key . '"');
	}
}

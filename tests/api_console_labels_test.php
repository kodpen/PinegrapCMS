<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the API documentation's text (includes/api/console_view.php):
 * every endpoint group has a heading, a parameter range with one end reads as
 * a bound, and Try it closes the missing-credential warning it opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/console_view.php');

// Every route prefix the schema declares has its own heading, so none prints
// as the bare prefix (CART_QUOTES) in the capitals of the group headings.
function test_api_console_every_group_has_a_label()
{
	$labels = api_console_group_labels();

	$missing = array();

	foreach (api_schema() as $route) {
		$parts = explode('.', $route['id']);
		if (!isset($labels[$parts[0]])) {
			$missing[$parts[0]] = true;
		}
	}

	pg_assert_same(array(), array_keys($missing), 'every route prefix has a group label');

	foreach (array('returns', 'cart_quotes', 'audit_log', 'affiliates', 'subscriptions', 'experiments', 'bookings') as $tag) {
		pg_assert_true(isset($labels[$tag]) && strpos($labels[$tag], '_') === false, $tag . ' has a readable label');
	}
}

function test_api_console_range_note()
{
	pg_assert_same('1–100', api_console_range_note(array('min' => 1, 'max' => 100)), 'both ends');
	pg_assert_same('≥ 1', api_console_range_note(array('min' => 1)), 'minimum only');
	pg_assert_same('≤ 50', api_console_range_note(array('max' => 50)), 'maximum only');
	pg_assert_same('≥ 0', api_console_range_note(array('min' => 0)), 'a zero minimum still counts');
	pg_assert_same('', api_console_range_note(array('name' => 'q')), 'no range');
}

function test_api_console_try_closes_the_warning()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/console_view.php');
	$start = strpos($src, 'function toggle(endpoint)');
	$end = strpos($src, 'function send(endpoint, panel, button)');
	$toggle = ($start !== false && $end !== false) ? substr($src, $start, $end - $start) : '';

	pg_assert_true($toggle !== '', 'toggle() is found');
	pg_assert_contains('if (!panel.classList.contains("d-none") && !panel.querySelector(".doc-send")) {', $toggle, 'a shown warning is recognised');
	pg_assert_true(strpos($toggle, 'panel.classList.add("d-none");') < strpos($toggle, 'panel.innerHTML = '), 'it is closed before a warning is drawn again');
	pg_assert_contains("where: ' . json_encode(array('path' => lang('Path'), 'query' => lang('Query'), 'body' => lang('Body'))", $src, 'the place words are translated');
}

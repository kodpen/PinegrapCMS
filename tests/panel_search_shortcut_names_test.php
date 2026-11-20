<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ctrl+K shortcuts of the States and Shipping Zones lists
 * (includes/panel/search.php): named as the menu names them and found by
 * those words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The file only defines the panel actions; its gate is the dispatcher's.
if (!defined('PG_PANEL_ACTIONS')) {
	define('PG_PANEL_ACTIONS', true);
}
require_once(PG_FUNCTIONS_DIR . '/includes/panel/search.php');

// The search box's screen row with the address $url.
function panel_search_shortcut_screen($url)
{
	foreach (pg_panel_search_screens() as $screen) {
		if ($screen['url'] === $url) {
			return $screen;
		}
	}

	return null;
}

function test_panel_search_states_and_zones_shortcut_names()
{
	$zones = panel_search_shortcut_screen('view_zones.php');
	$states = panel_search_shortcut_screen('view_states.php');

	pg_assert_same(array('Shipping Zones', 'bi-map'), array($zones['label'], $zones['icon']), 'zones named as in the menu');
	pg_assert_same(array('States/Provinces', 'bi-geo-alt'), array($states['label'], $states['icon']), 'states named as in the menu');
	pg_assert_true(in_array('nakliye bolge', $zones['keys'], true), 'zones found by their Turkish menu words');
	pg_assert_true(in_array('iller', $states['keys'], true), 'states found by "il" and "iller"');
	pg_assert_true(in_array('province', $states['keys'], true), 'states found by "province"');
}

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

function test_panel_search_states_and_zones_shortcut_names()
{
	$php = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');

	pg_assert_contains("array('label' => lang('Shipping Zones'), 'icon' => 'bi-map', 'url' => \$base_url . '/view_zones.php'", $php, 'zones named as in the menu');
	pg_assert_contains("array('label' => lang('States/Provinces'), 'icon' => 'bi-geo-alt', 'url' => \$base_url . '/view_states.php'", $php, 'states named as in the menu');
	pg_assert_contains("'nakliye bolge'", $php, 'zones found by their Turkish menu words');
	pg_assert_contains("'iller'", $php, 'states found by "il" and "iller"');
	pg_assert_contains("'province'", $php, 'states found by "province"');
}

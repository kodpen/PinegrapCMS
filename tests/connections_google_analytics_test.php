<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Google Analytics on Connections: the Google tag (switch, ID,
 * analytics address) and the GA4 figures are set on Google Analytics' page
 * under the gate of Site Settings › Visitors and privacy they came from; the
 * Visitor Tracking card shows the connection and leads there; the registry
 * row and the old anchors follow the move.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_google_analytics_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_google_analytics_tag_input()
{
	$taken = pg_ga_tag_input(array('google_analytics' => '1', 'google_analytics_web_property_id' => ' G-ABC123 ', 'stats_url' => ' https://analytics.google.com/ '));
	pg_assert_same(array(), $taken['errors'], 'a tag ID and an address are taken');
	pg_assert_same(array('google_analytics' => 1, 'google_analytics_web_property_id' => 'G-ABC123', 'stats_url' => 'https://analytics.google.com/'), $taken['values'], 'as the tag and the menu print them');

	$off = pg_ga_tag_input(array());
	pg_assert_same(array(), $off['errors'], 'nothing typed is no error');
	pg_assert_same(array('google_analytics' => 0, 'google_analytics_web_property_id' => '', 'stats_url' => ''), $off['values'], 'the switch off, both empty');

	$bad = pg_ga_tag_input(array('google_analytics' => '1', 'google_analytics_web_property_id' => 'G-1"><script>', 'stats_url' => 'javascript:alert(1)'));
	pg_assert_same(array('google_analytics_web_property_id', 'stats_url'), array_keys($bad['errors']), 'each refused in its own format');

	$array = pg_ga_tag_input(array('google_analytics_web_property_id' => array('G-1'), 'stats_url' => array('x')));
	pg_assert_same(array(), $array['errors'], 'a posted array is read as empty');
}

function test_connections_google_analytics_page_card_keeps_the_privacy_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google_analytics', array('manager' => true, 'privacy' => true)), 'who may open Visitors and privacy sets the tag');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('google_analytics', array('manager' => true, 'privacy' => false)), 'a manager without it sees no tag');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('search_console', array('privacy' => true)), 'only Google Analytics takes the privacy gate');

	$screen = connections_google_analytics_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('privacy', \$user);", $screen, 'the gate is the Visitors and privacy category');
	pg_assert_contains("in_array(\$action, array('ga_tag_save', 'ga_figures'), true) && connections_privacy_may_manage(\$user)", $screen, 'the write checks the gate');
}

function test_connections_google_analytics_registry_and_anchors_follow_the_move()
{
	$registry = pg_connections_registry();

	pg_assert_same('connections.php', $registry['google_analytics']['place'], 'its place is Connections');
	pg_assert_same('#settings/privacy/pgset-analytics', $registry['google_analytics']['found_at'], 'the Visitor Tracking card still finds it');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');

	$anchors = pg_settings_legacy_anchors();

	foreach (array('google_analytics', 'google_analytics_web_property_id', 'stats_url', 'ga_property') as $field) {
		pg_assert_same(array('privacy', 'pgset-analytics'), $anchors[$field], 'an old link to ' . $field . ' lands on the Visitor Tracking card');
	}
}

function test_connections_google_analytics_card_shows_the_connection()
{
	$card = connections_google_analytics_test_source('includes/settings/privacy.php');
	$save = connections_google_analytics_test_source('includes/settings/privacy.save.php');

	foreach (array('google_analytics', 'google_analytics_web_property_id', 'stats_url', 'ga_property') as $field) {
		pg_assert_false(strpos($card, 'name="' . $field . '"') !== false, 'the card no longer types ' . $field);
		pg_assert_false(strpos($save, $field . ' = ') !== false, 'the card\'s save cannot empty ' . $field);
	}

	pg_assert_contains('id="pgsub-google-analytics"', $card, 'the card shows the connection');
	pg_assert_contains('connections.php?service=google_analytics', $card, 'and leads to its page');
	pg_assert_contains("lang('Manage in Connections')", $card, 'with Manage in Connections');
	pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/includes/settings/privacy_analytics.php'), 'the figures part left the card');
	pg_assert_contains('connections.php?service=google_analytics', connections_google_analytics_test_source('includes/dashboard/v2/cards/ga.php'), 'the dashboard card leads to the page');
}

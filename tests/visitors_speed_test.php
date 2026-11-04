<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Page Speed section of the Visitors overview
 * (includes/visitors/speed.php): its filters, the redirect of the former
 * screen with its period and filters kept, and the search anchor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/speed.php');

function test_visitors_speed_choice_takes_only_an_offered_value()
{
	$orders = pg_visitors_speed_orders();
	pg_assert_same('inp', pg_visitors_speed_choice(array('speed_order' => 'inp'), 'speed_order', $orders, 'lcp'));
	pg_assert_same('lcp', pg_visitors_speed_choice(array('speed_order' => 'nope'), 'speed_order', $orders, 'lcp'), 'unknown value');
	pg_assert_same('lcp', pg_visitors_speed_choice(array('speed_order' => array('inp')), 'speed_order', $orders, 'lcp'), 'not a scalar');
	pg_assert_same('lcp', pg_visitors_speed_choice(array('order' => 'inp'), 'speed_order', $orders, 'lcp'), 'another section\'s parameter is not read');

	$devices = pg_vitals_device_options();
	pg_assert_same('', pg_visitors_speed_choice(array(), 'speed_device', $devices, ''), 'all devices by default');
	pg_assert_same('mobile', pg_visitors_speed_choice(array('speed_device' => 'mobile'), 'speed_device', $devices, ''));
}

function test_visitors_speed_old_link_keeps_period_and_filters()
{
	$rename = array('device' => 'speed_device', 'order' => 'speed_order');

	pg_assert_same(array('p' => '7d', 'speed_device' => 'mobile', 'speed_order' => 'cls'),
		pg_visitor_insight_overview_query(array('p' => '7d', 'device' => 'mobile', 'order' => 'cls'), $rename));
	pg_assert_same(array('p' => '30d'), pg_visitor_insight_overview_query(array(), $rename), 'the old screen\'s 30 days when no period was given');
	pg_assert_same(array('p' => '30d'), pg_visitor_insight_overview_query(array('p' => 'x', 'device' => ''), $rename), 'an unknown period and an empty filter');
	pg_assert_same(array('p' => '90d'), pg_visitor_insight_overview_query(array('p' => '90d', 'device' => array('a')), $rename), 'not a scalar');
}

function test_visitors_speed_screen_redirects_and_anchors()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/visitor_vitals.php');
	pg_assert_contains('validate_visitors_access($user);', $screen);
	pg_assert_contains("array('device' => 'speed_device', 'order' => 'speed_order'))) . '#speed', true, 301);", $screen);

	$search = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_contains("\$base_url . '/visitors.php#speed'", $search);

	$section = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/visitors/speed.php');
	pg_assert_contains("if (!pg_vitals_ready()) {\n        return '';", $section, 'nothing before the table exists');
	pg_assert_contains("'action' => 'visitors.php#speed'", $section, 'a filter comes back to the section');
}

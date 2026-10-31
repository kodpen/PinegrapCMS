<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Screen-wide actions sit in the screen toolbar, never in a card header, and
 * a related-screen link is a slot 2 button, never drawn as primary. Source
 * pins: the screens need a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function screen_wide_actions_source($file)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	pg_assert_true(is_string($source), $file . ' is readable');

	return (string) $source;
}

function test_screen_wide_actions_firewall_clears_are_in_the_toolbar_menu()
{
	$source = screen_wide_actions_source('view_waf_log.php');
	$menu = strpos($source, '$clear_menu = ');
	$toolbar = strpos($source, "'overflow' => \$clear_menu");
	$first_card = strpos($source, '<div class="card-header');

	pg_assert_true($menu !== false && $toolbar !== false, 'the clear menu is the toolbar overflow');
	pg_assert_true($first_card !== false && $menu < $first_card, 'the clear menu is drawn before any card');
	pg_assert_same(1, substr_count($source, 'name="submit_clear"'), 'one Clear Log button');
	pg_assert_same(1, substr_count($source, 'name="submit_csp_clear"'), 'one Clear Reports button');
	pg_assert_true(strpos($source, 'name="submit_clear"') < $first_card && strpos($source, 'name="submit_csp_clear"') < $first_card, 'neither clear sits in a card header');
	pg_assert_same(2, substr_count(substr($source, $menu, $toolbar - $menu), 'get_token_field()'), 'each clear posts its own token');
}

function test_screen_wide_actions_new_department_is_the_toolbar_primary()
{
	$source = screen_wide_actions_source('workspace_settings.php');
	pg_assert_contains('pg_screen_toolbar(', $source, 'workspace settings draws its bar through the helper');
	pg_assert_same(1, substr_count($source, 'data-bs-target="#ws-dept-new"'), 'one New department toggle');
	pg_assert_true(strpos($source, 'data-bs-target="#ws-dept-new"') < strpos($source, 'id="ws-departments"'), 'New department is in the toolbar, above the Departments card');
	pg_assert_false(strpos($source, '<nav id="button_bar"') !== false, 'no hand-written bar');
}

function test_screen_wide_actions_api_settings_primary_comes_first()
{
	$source = screen_wide_actions_source('api_settings.php');
	pg_assert_contains('pg_screen_toolbar(', $source, 'api settings draws its bar through the helper');
	pg_assert_true((bool) preg_match("/'primary'\\s*=>[^\\n]*data-bs-target=\"#api_new\"/", $source), 'New Application is the primary slot');
	pg_assert_contains('id="api_filter"', $source, 'the application filter keeps its id');
	pg_assert_contains('id="api_filter_status"', $source, 'the filter status keeps its id');
}

function test_screen_wide_actions_shipping_report_link_is_secondary()
{
	$source = screen_wide_actions_source('view_shipping_report.php');
	pg_assert_contains('pg_screen_toolbar(', $source, 'shipping report draws its bar through the helper');
	pg_assert_true((bool) preg_match("/'secondary'\\s*=>[^\\n]*view_ship_date_adjustments\\.php/", $source), 'Ship Date Adjustments is a slot 2 link');
	pg_assert_false(strpos($source, '<nav id="button_bar"') !== false, 'no hand-written bar');
	pg_assert_contains('id="update_status"', $source, 'the status form keeps its id');
}

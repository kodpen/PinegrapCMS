<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a list's remembered filter whose record is gone (an ad region,
 * a state, a custom form deleted or out of the user's reach) goes back to
 * '[All]' instead of leaving an empty list under '[All] (N)'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

function test_list_filter_known_keeps_a_listed_id_or_all()
{
	pg_assert_same('7', pg_list_filter_known('7', array('3', '7')), 'a listed id stays');
	pg_assert_same('7', pg_list_filter_known('7', array(3, 7)), 'ids are compared as strings');
	pg_assert_same('[All]', pg_list_filter_known('[All]', array()), '[All] stays');
	pg_assert_same('[All]', pg_list_filter_known('9', array('3', '7')), 'a gone id falls back to [All]');
	pg_assert_same('[All]', pg_list_filter_known('', array('3')), 'an empty value falls back to [All]');
	pg_assert_same('[All]', pg_list_filter_known(array('3'), array('3')), 'a value that is not a string falls back to [All]');
	pg_assert_same('[All]', pg_list_filter_known('07', array('7')), 'no loose number match');
}

function test_submitted_form_list_defaults_drop_a_gone_form()
{
	$forms = array(array('id' => '5', 'name' => 'A'), array('id' => '9', 'name' => 'B'));

	$_SESSION['software']['forms']['view_submitted_forms'] = array('custom_form' => '4');
	pg_submitted_form_list_defaults($forms);
	pg_assert_same('[All]', $_SESSION['software']['forms']['view_submitted_forms']['custom_form'], 'a gone form');

	$_SESSION['software']['forms']['view_submitted_forms'] = array('custom_form' => '9');
	pg_submitted_form_list_defaults($forms);
	pg_assert_same('9', $_SESSION['software']['forms']['view_submitted_forms']['custom_form'], 'a listed form stays');

	$_SESSION['software']['forms']['view_submitted_forms'] = array('advanced_filters' => true, 'custom_form' => '[All]', 'custom_forms' => array('4', '9', '5'));
	pg_submitted_form_list_defaults($forms);
	pg_assert_same(array('9', '5'), $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'], 'gone forms leave the checked ones');
}

function test_list_stale_filter_screens_check_their_options()
{
	$ads = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_ads.php');
	pg_assert_contains("\$_SESSION['software']['ads']['view_ads']['ad_region_id'] = pg_list_filter_known(", $ads, 'All Ads checks the region');
	pg_assert_true(strpos($ads, 'pg_list_filter_known(') > strpos($ads, '$ad_regions[] = $row;'), 'after the regions are read');
	pg_assert_true(strpos($ads, 'pg_list_filter_known(') < strpos($ads, '$output_ad_region_options = \'\';'), 'before the options are printed');

	$addresses = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_verified_shipping_addresses.php');
	pg_assert_contains("\$_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] = pg_list_filter_known(", $addresses, 'Verified Shipping Addresses checks the state');
	pg_assert_true(strpos($addresses, 'pg_list_filter_known(') < strpos($addresses, '$output_state_options = \'\';'), 'before the options are printed');
}

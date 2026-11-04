<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for small root endpoints and their request values: the theme
 * designer's Cancel link (clear_theme_designer_session.php) takes one file id,
 * the catalog menu redirect (browse_catalog_menu.php) passes the back link id
 * encoded, and the shopping cart's upsell offers give a custom layout a
 * translated default button label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function endpoint_request_values_test_source($file)
{
	return file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
}

// ?file_id[]=1 reached unset() with an array key: a fatal error in PHP 8.
function test_clear_theme_designer_session_takes_one_file_id()
{
	$source = endpoint_request_values_test_source('clear_theme_designer_session.php');

	pg_assert_contains("pg_request_single_values(array('file_id', 'send_to'));", $source, 'file_id and send_to are single values');
	pg_assert_true(strpos($source, 'pg_request_single_values') < strpos($source, 'unset($_SESSION'), 'checked before the session is cleared');
}

// A raw id could add query parameters or a fragment to the catalog address.
function test_browse_catalog_menu_encodes_previous_url_id()
{
	$source = endpoint_request_values_test_source('browse_catalog_menu.php');

	pg_assert_contains("'?previous_url_id=' . urlencode(\$_GET['previous_url_id'] ?? '')", $source, 'the back link id is URL-encoded');
}

function test_shopping_cart_upsell_default_label_is_translated()
{
	$source = endpoint_request_values_test_source('get_shopping_cart.php');

	pg_assert_same(0, preg_match("/upsell_action_button_label'\\] = 'More Info';/", $source), 'no hard-coded English default label');
	pg_assert_contains("\$offer['upsell_action_button_label'] = lang('More Info');", $source, 'the custom layout default comes from lang()');
}

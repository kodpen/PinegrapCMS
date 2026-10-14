<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page id the checkout steps are posted with: pg_order_page_known()
 * (includes/fn/ecommerce.php), and the express order, order preview and
 * billing information scripts, which stop with it on an id that names no page
 * of their type, as the shipping address and arrival script already did,
 * instead of going on without that page's settings (receipt e-mail, terms,
 * required custom fields, next page).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An id that cannot name a page is turned away before any query runs (there is
// no connection here, so a query would end the test as an error).
function test_order_page_known_rejects_ids_that_name_no_page()
{
	$ids = array(
		'array' => array('12'),
		'empty' => '',
		'not posted' => null,
		'zero' => '0',
		'zero int' => 0,
		'negative' => '-3',
		'negative int' => -3,
		'letters' => 'abc',
		'digits then letters' => '12abc',
		'space' => ' 12',
		'decimal' => '1.5',
		'float' => 1.5,
		'bool' => true,
	);

	foreach ($ids as $label => $id) {
		foreach (array('express order', 'order preview', 'billing information') as $type) {
			pg_assert_false(pg_order_page_known($id, $type), $type . ': ' . $label);
		}
	}
}

// Each step checks its own page type, before the order is created or changed,
// and stops with its own "could not be found" error. A gateway callback, which
// brings back the id of a submit already checked, is let through.
function test_order_page_known_steps_stop_before_the_order()
{
	$steps = array(
		'express_order' => array("pg_order_page_known(\$_POST['page_id'] ?? '', 'express order')", 'An express order page could not be found for the id that was passed.'),
		'order_preview' => array("pg_order_page_known(\$_POST['page_id'] ?? '', 'order preview')", 'An order preview page could not be found for the id that was passed.'),
		'billing_information' => array("pg_order_page_known(\$_POST['page_id'] ?? '', 'billing information')", 'A billing information page could not be found for the id that was passed.'),
	);

	foreach ($steps as $script => $expected) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $script . '.php');
		$check = strpos($source, $expected[0]);

		pg_assert_true($check !== false, $script . ': checks its page');
		pg_assert_contains("output_error(lang('" . $expected[1] . "')", $source, $script . ': error');
		pg_assert_true(($check !== false) && ($check < strpos($source, 'initialize_order();')), $script . ': before initialize_order()');
	}

	pg_assert_contains('!$is_gateway_return && !pg_order_page_known(', file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php'), 'express order: gateway callback let through');
	pg_assert_contains("'pay_with_iyzico_return'), true)\n    && !pg_order_page_known(", file_get_contents(PG_FUNCTIONS_DIR . '/order_preview.php'), 'order preview: gateway callback let through');

	// The step that already stopped keeps doing so.
	pg_assert_contains("output_error(lang('A shipping address &amp; arrival page could not be found for the id that was passed.')", file_get_contents(PG_FUNCTIONS_DIR . '/shipping_address_and_arrival.php'), 'shipping address and arrival');
}

// The designer's express order widget names the page being rendered when the
// page was reached without its name (the home page at the site root), so its
// form is not turned away with page_id 0.
function test_order_page_known_express_order_widget_names_rendered_page()
{
	pg_assert_contains("if (\$_eo_page_id <= 0) {\n        \$_eo_page_id = (int)pg_rendered_page();", file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php'));
}

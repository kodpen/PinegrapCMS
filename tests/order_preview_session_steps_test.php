<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Order Preview's links back to earlier checkout steps: the
 * step page ids read from the session (pg_op_step_page_id()), the recipient
 * block without a shipping step page, the raw session reads gone from
 * get_order_preview.php, and the widget left out of the SEO and search
 * index renders as it is from the background render.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_preview_step_page_id()
{
	pg_assert_same(0, pg_op_step_page_id(array(), 'billing_information_page_id'), 'no key, no page');
	pg_assert_same(0, pg_op_step_page_id(null, 'billing_information_page_id'), 'no ecommerce session, no page');
	pg_assert_same(12, pg_op_step_page_id(array('billing_information_page_id' => '12'), 'billing_information_page_id'), 'a stored id');
	pg_assert_same(0, pg_op_step_page_id(array('shipping_address_and_arrival_page_id' => ''), 'shipping_address_and_arrival_page_id'), 'an empty id');
	pg_assert_same(0, pg_op_step_page_id(array('billing_information_page_id' => array(3)), 'billing_information_page_id'), 'not an id');
	pg_assert_same(0, pg_op_step_page_id(array('billing_information_page_id' => '-4'), 'billing_information_page_id'), 'a negative id');
}

function test_order_preview_recipient_without_change_link()
{
	$recipient = array(
		'ship_to_heading' => true, 'ship_to_name' => 'Home', 'name' => 'Ada', 'address' => 'Street 1',
		'shipping_method_name' => '', 'items' => array(array(
			'name' => 'SKU1', 'full_description' => '', 'quantity' => 1, 'amount_info' => '10,00',
		)),
	);
	$d = array('recipients' => array($recipient + array('update_url' => '')));
	$html = _pg_op_recipients_html($d);
	pg_assert_true(strpos($html, 'Street 1') !== false, 'the address is drawn');
	pg_assert_true(strpos($html, '<a ') === false, 'no Change link without a shipping step page');

	$d = array('recipients' => array($recipient + array('update_url' => '/shipping?ship_to_id=5')));
	pg_assert_true(strpos(_pg_op_recipients_html($d), 'href="/shipping?ship_to_id=5"') !== false, 'the Change link with one');
}

function test_order_preview_no_raw_step_session_reads()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/get_order_preview.php');
	foreach (array('shipping_address_and_arrival_page_id', 'billing_information_page_id') as $key) {
		pg_assert_true(strpos($src, "\$_SESSION['ecommerce']['" . $key . "']") === false, $key . ' is read through pg_op_step_page_id()');
	}
	$tpl = file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/order_preview.php');
	pg_assert_same(3, substr_count($tpl, "!== ''): ?>"), 'the template draws each Change link only with an address');
}

function test_order_preview_widget_skips_seo_render()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_order_preview.php');
	$start = strpos($src, 'function _render_system_widget_order_preview(');
	$body = substr($src, $start, 1500);
	pg_assert_true(strpos($body, 'pg_seo_rendering()') !== false, 'the SEO render draws no order');
	pg_assert_true(strpos($body, 'UPDATE_SEARCH_INDEX') !== false, 'the search index render draws no order');
}

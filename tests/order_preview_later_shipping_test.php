<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the classic Order Preview's recipient block: a recipient whose
 * lines all start later still shows and counts its shipping, as
 * submit_order.php charges SUM(ship_tos.shipping_cost) over every recipient.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_preview_recipient_block_shown()
{
	pg_assert_same(true, pg_op_recipient_block_shown(true, 5, true, 0), 'lines charged today, shipping on');
	pg_assert_same(true, pg_op_recipient_block_shown(true, 5, false, 10.0), 'only later lines, shipping charged today');
	pg_assert_same(false, pg_op_recipient_block_shown(true, 5, false, 0), 'only later lines, no shipping cost');
	pg_assert_same(true, pg_op_recipient_block_shown(false, 0, true, 0), 'non-shipping lines charged today');
	pg_assert_same(false, pg_op_recipient_block_shown(false, 5, false, 10.0), 'shipping off');
	pg_assert_same(false, pg_op_recipient_block_shown(true, 0, false, 10.0), 'no real recipient');
}

function test_order_preview_classic_layout_uses_recipient_block()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/get_order_preview.php');
	pg_assert_true(strpos($src, 'if (pg_op_recipient_block_shown(ECOMMERCE_SHIPPING, $ship_to_id, $output_products !== \'\', $shipping_cost))') !== false,
		'the classic recipient block opens for shipping charged today');
	pg_assert_true(strpos($src, 'if ($output_products) {') === false, 'no block gated on today-lines alone');
}

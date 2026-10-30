<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order widget's shipping script
 * (_eo_render_widget_js(), includes/fn/widgets_express_order.php): picking
 * a paid shipping method moves the posted total with the shown one, so
 * submit_order()'s "the total has changed" check accepts the first submit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The script carries the drawn totals and the surcharge percentage, and
// writes both hidden totals when the shown total moves.
function test_express_order_shipping_pick_updates_posted_total()
{
	$js = _eo_render_widget_js(7, 'eo_form', true, array(array('id' => 3)), '/', array(
		'shipping_drawn' => 0,
		'shipping'       => 0,
		'total'          => 2700,
	));

	pg_assert_true(strpos($js, 'var TOTAL_SHOWN  = 2700;') !== false, 'the shown total');
	pg_assert_true(strpos($js, 'var SURCHARGE_UNITS = ') !== false, 'the surcharge percentage');
	pg_assert_true(strpos($js, "input[name=\"total\"]") !== false, 'the hidden total is written');
	pg_assert_true(strpos($js, "input[name=\"total_with_surcharge\"]") !== false, 'the hidden total with surcharge is written');
}

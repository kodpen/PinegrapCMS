<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order screen's Restore Order button on a cancelled order. It was built
 * inside the branch that draws the shipping cards, so a cancelled order
 * without a shipping address (a download, a gift card, a counter sale) had
 * no way back to complete on the screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only a cancelled order gets the button, and it posts the restore.
function test_view_order_restore_button_only_for_cancelled()
{
	$button = pg_order_view_restore_button('cancelled');

	pg_assert_contains('name="submit_restore_order" value="restore"', $button, 'restore button');
	pg_assert_contains('data-confirm-content="', $button, 'asks first');

	foreach (array('complete', 'exported', 'incomplete', '') as $status) {
		pg_assert_same('', pg_order_view_restore_button($status), $status . ': no restore button');
	}
}

// The screen builds the button after the shipping branch and its else, so an
// order with no shipping address gets it too.
function test_view_order_restore_button_outside_shipping_branch()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_order.php');

	$call = strpos($source, '$output_restore_button = pg_order_view_restore_button($status);');
	$no_ship_to = strpos($source, '// else a real ship to does not exist');
	$currency = strpos($source, '// if multi currency is enabled, output miscellaneous fieldset with currency information');

	pg_assert_true($call !== false, 'the screen builds the button with the helper');
	pg_assert_true(($no_ship_to !== false) && ($currency !== false), 'markers');
	pg_assert_true(($call > $no_ship_to) && ($call < $currency), 'built after both branches of the shipping cards');
	pg_assert_same(1, substr_count($source, 'name="submit_restore_order"') + substr_count($source, 'pg_order_view_restore_button('), 'one place builds it');
}

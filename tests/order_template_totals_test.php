<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the built-in order receipt and order view layouts (used by a
 * page on a custom layout that was never edited) print a row for every
 * charge the screen adds to the Total they print, so the rows add up.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The totals table of a template: from the Subtotal row to the Total row.
function _test_order_tpl_totals($file)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . $file);
	$start = strpos($src, '$show_subtotal');
	$end = strpos($src, '<?=$total_info?>');
	if ($start === false || $end === false || $end < $start) return '';
	return substr($src, $start, $end - $start);
}

function test_order_templates_print_every_total_component()
{
	// Each figure get_order_receipt.php / get_view_order_screen_content.php
	// passes and folds into $total.
	$parts = array('$discount_info', '$tax_info', '$shipping_info', '$gift_card_discount_info', '$surcharge_info', '$installment_charges_info');
	foreach (array('order_receipt.php', 'view_order.php') as $file) {
		$totals = _test_order_tpl_totals($file);
		pg_assert_true($totals !== '', $file . ': totals table found');
		foreach ($parts as $part) {
			pg_assert_true(strpos($totals, $part) !== false, $file . ' prints ' . $part . ' before the Total');
		}
	}
}

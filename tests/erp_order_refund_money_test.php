<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the money and names of the order screen's card refund by lines
 * (erp_order_refund_dialog(), includes/erp/order_refund.php): the figures
 * the server writes and the ones its script writes follow the panel
 * language's separators, and each line's quantity box is named after the
 * line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_refund.php');

// The dialog of an order without an invoice: two lines, 84.00 on the card.
function erp_order_refund_money_dialog()
{
	if (!defined('BASE_CURRENCY_SYMBOL')) {
		define('BASE_CURRENCY_SYMBOL', '$');
	}

	$items = array();
	foreach (array(array('Blue mug', 2, 5000), array('Red plate', 1, 123450)) as $index => $entry) {
		$items[] = array(
			'id' => 300 + $index,
			'product_id' => 30 + $index,
			'product_name' => $entry[0],
			'short_description' => '',
			'quantity' => $entry[1],
			'price' => $entry[2],
			'tax_total' => 0,
			'tax_rate' => '0.000',
			'product_tax_rate' => '0.000',
		);
	}
	$order = array('order_date' => strtotime('2026-10-21'), 'discount' => 0, 'tax' => 0, 'shipping' => 0,
		'surcharge' => 0, 'installment_charges' => 0, 'gift_card_discount' => 0, 'total' => 133450);
	$built = erp_order_lines($order, $items);
	$source = array('source' => 'order', 'invoice_id' => 0, 'invoice_number' => '',
		'lines' => erp_order_refund_order_lines($built['lines'], $items));

	return erp_order_refund_dialog($source, 7, 0, 123456);
}

// The figures the server writes: the cap and the starting total through
// erp_money_out(), never number_format(..., '.', ','), which wrote 1,234.56
// on a Turkish panel, read there as 1.23456.
function test_erp_order_refund_money_server_figures_follow_the_panel_language()
{
	$html = erp_order_refund_money_dialog();

	pg_assert_contains(': ' . h(erp_money_out(123456)) . '</div>', $html);
	pg_assert_contains('<span id="payment_refund_total">' . h(erp_money_out(0)) . '</span>', $html);

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/order_refund.php');
	pg_assert_true(strpos($source, "number_format(\$refund_uncovered / 100, 2, '.', ',')") === false, 'refunded-earlier figure through erp_money_out()');
	pg_assert_true(strpos($source, "BASE_CURRENCY_SYMBOL . '0.00'") === false, 'starting total through erp_money_out()');
}

// The script is given the panel language's separators and writes its line
// amounts and total with them: 1.234,50 on a Turkish panel.
function test_erp_order_refund_money_script_uses_the_panel_separators()
{
	$html = erp_order_refund_money_dialog();

	pg_assert_contains('var separators = ' . json_encode(erp_number_separators(), JSON_HEX_TAG | JSON_HEX_AMP) . ';', $html);
	pg_assert_true(strpos($html, '.replace(/\B(?=(\d{3})+(?!\d))/g, ",")') === false, 'no comma written by hand');

	pg_assert_true((bool) preg_match('/var money = function \(kurus\) \{.*?\n\s*\};/s', $html, $match), 'money() found');

	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$script = 'var symbol = "₺"; var separators = {"decimal":",","thousands":"."};' . "\n" . $match[0] . "\n"
		. 'console.log(JSON.stringify([money(123450), money(0), money(-1500), money(12345678901)]));';
	$file = tempnam(sys_get_temp_dir(), 'pgref') . '.js';
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	pg_assert_same(array("\u{20BA}1.234,50", "\u{20BA}0,00", "-\u{20BA}15,00", "\u{20BA}123.456.789,01"), json_decode(implode('', $lines), true));
}

// Each line's quantity box carries the line's name: every box was
// "Quantity to refund", and a screen reader could not tell them apart.
function test_erp_order_refund_money_quantity_boxes_are_named_per_line()
{
	$html = erp_order_refund_money_dialog();

	pg_assert_contains('aria-label="' . h(lang(array('string' => 'Quantity to refund: {var:1}', 'vars' => 'Blue mug'))) . '"', $html);
	pg_assert_contains('aria-label="' . h(lang(array('string' => 'Quantity to refund: {var:1}', 'vars' => 'Red plate'))) . '"', $html);
	pg_assert_true(strpos($html, 'aria-label="' . h(lang('Quantity to refund')) . '"') === false, 'no box with the bare name');
}

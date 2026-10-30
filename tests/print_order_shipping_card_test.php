<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The printed order's shipping card (print_order.php): one card for each
 * real recipient, above that recipient's items, rows without a value left
 * out, every tracking number with its carrier; an order without a shipping
 * recipient prints no card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function print_order_shipping_card_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/print_order.php');
}

function test_print_order_shipping_card_rows_with_values()
{
	$html = pg_print_order_shipping_card(array(
		'Ship to Name' => 'Office',
		'Full Name'    => 'Ada &amp; Lovelace',
		'Company'      => '',
		'Phone'        => '   ',
	));

	pg_assert_contains(lang('Shipping Information'), $html, 'the card heading');
	pg_assert_contains('Ship to Name:</span>', $html, 'a row with a value');
	pg_assert_contains('Ada &amp; Lovelace', $html, 'the value as given');
	pg_assert_false(strpos($html, 'Company') !== false, 'an empty row is hidden');
	pg_assert_false(strpos($html, 'Phone') !== false, 'a blank row is hidden');
	pg_assert_same(2, substr_count($html, '<div class="row">'), 'two rows');
}

function test_print_order_shipping_card_label_escaped()
{
	$html = pg_print_order_shipping_card(array('Gift <b>note</b>' => 'Yes'));

	pg_assert_contains('Gift &lt;b&gt;note&lt;/b&gt;:', $html, 'a custom field name is escaped');
}

function test_print_order_shipping_card_nothing_without_values()
{
	pg_assert_same('', pg_print_order_shipping_card(array()), 'no rows, no card');
	pg_assert_same('', pg_print_order_shipping_card(array('Company' => '', 'Phone' => '')), 'only empty rows, no card');
}

function test_print_order_tracking_text_every_parcel_with_carrier()
{
	$text = pg_print_order_tracking_text(array(
		array('number' => '1Z999', 'carrier_name' => 'UPS'),
		array('number' => '<x>', 'carrier_name' => ''),
		array('number' => '', 'carrier_name' => 'Aras Kargo'),
	));

	pg_assert_same('1Z999 (UPS)<br />&lt;x&gt;', $text, 'number and carrier, unknown carrier bare, empty number skipped');
	pg_assert_same('', pg_print_order_tracking_text(array()), 'no parcels');
}

// The card goes above each real recipient's items; the "no shipping"
// recipient (ship_to_id 0) and an order with no recipient print as before.
function test_print_order_prints_card_per_real_recipient()
{
	$source = print_order_shipping_card_source();

	$card = strpos($source, '$output_order_details .= pg_print_order_shipping_card(');
	$items = strpos($source, '<table cellpadding="4" class="order_details table table-bordered w-auto">');
	$real = strpos($source, "if (\$ship_tos[\$key]['ship_to_id'] > 0) {");

	pg_assert_true($card !== false, 'the card is printed');
	pg_assert_true($items !== false && $card < $items, 'above the recipient\'s items');
	pg_assert_true($real !== false && $real < $card, 'only inside the real recipient branch');
	pg_assert_false(strpos($source, '$output_shipping_details') !== false, 'no card built and dropped');
	pg_assert_false(strpos($source, 'pg-link-secondary pg-print-none" href="\' . h($shipping_tracking_url)') !== false, 'tracking numbers are not hidden on paper');
}

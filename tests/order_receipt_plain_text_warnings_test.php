<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warnings the plain-text order receipt (the receipt e-mail's
 * text part, get_order_receipt_in_plain_text()) wrote. The card number was
 * read for every order but set only for a card order; the custom field labels
 * were read from a billing information page an express order does not have
 * (and from a session key it does not set); items that are not shipped (ship
 * to 0) were read from a ship to row and a country row that do not exist and
 * had no shipping line; and every line read two keys its query never selects.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_receipt_plain_text_test_source()
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/get_order_receipt_in_plain_text.php');
}

// The card type and number start empty before only a card order sets them.
function test_order_receipt_plain_text_card_number_default()
{
	$source = order_receipt_plain_text_test_source();
	$card_order = strpos($source, "if (\$payment_method == 'Credit/Debit Card') {");

	pg_assert_true($card_order !== false, 'card order branch');
	pg_assert_true(strpos($source, "\$card_type = '';") !== false && strpos($source, "\$card_type = '';") < $card_order, 'card type starts empty');
	pg_assert_true(strpos($source, "\$card_number = '';") !== false && strpos($source, "\$card_number = '';") < $card_order, 'card number starts empty');
	pg_assert_true($card_order < strpos($source, "if (mb_substr(\$card_number, 0, 1) != '*') {"), 'number protected after the card branch');
}

// An express order (or no shopper session) has no billing information page,
// so neither the session key nor the page's row is read as if it were there.
function test_order_receipt_plain_text_billing_information_page_defaults()
{
	$source = order_receipt_plain_text_test_source();

	pg_assert_true(strpos($source, "(string) (\$_SESSION['ecommerce']['billing_information_page_id'] ?? '')") !== false, 'page id from the session');

	foreach (array('custom_field_1_label', 'custom_field_1_required', 'custom_field_2_label', 'custom_field_2_required') as $column) {
		pg_assert_true(strpos($source, "\$" . $column . " = \$row['" . $column . "'] ?? '';") !== false, $column);
	}
}

// Items that are not shipped sit under ship to 0, which has no ship to row,
// no country row and no shipping line.
function test_order_receipt_plain_text_unshipped_items()
{
	$source = order_receipt_plain_text_test_source();

	pg_assert_same(1, substr_count($source, "\$row = mysqli_fetch_assoc(\$result) ?: array_fill_keys(array_column(mysqli_fetch_fields(\$result), 'name'), null);"), 'ship to row fallback');
	pg_assert_true(strpos($source, '?: array_fill_keys(') < strpos($source, "\$ship_to_name = \$row['ship_to_name'];"), 'fallback before the ship to columns are read');
	pg_assert_true(strpos($source, " \$country = \$row['name'] ?? '';") !== false, 'ship to country');
	pg_assert_true(strpos($source, "\$billing_country = \$row['name'] ?? '';") !== false, 'billing country');

	$started = strpos($source, "\$output_shipping = '';");

	pg_assert_true($started !== false, 'shipping line starts empty');
	pg_assert_true($started > strpos($source, 'foreach ($ship_tos as $ship_to_id) {') && $started < strpos($source, 'if ($output_products) {'), 'started for each recipient');
}

// The order item query selects neither start nor payment_period.
function test_order_receipt_plain_text_unselected_item_keys()
{
	$source = order_receipt_plain_text_test_source();

	pg_assert_same(0, substr_count($source, "\$order_item['start']"), 'start');
	pg_assert_same(0, substr_count($source, "\$order_item['payment_period']"), 'payment_period');
}

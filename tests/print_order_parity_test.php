<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The printed order (print_order.php) says what the order screen says:
 * district and country names, tax number and office, an order put on
 * account, the unpaid state and the cancellation reason, the order codes of
 * a free order, the number of installments, a headed gift card block and
 * the form answers outside the form heading.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function print_order_parity_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/print_order.php');
}

function test_print_order_parity_district_and_country_names()
{
	$source = print_order_parity_source();

	pg_assert_contains('(pg_ship_to_district_ready() ? "ship_tos.district," : "")', $source, 'the district read behind its guard');
	pg_assert_contains("\$address .= \$ship_tos[\$key]['district'] . ', ';", $source, 'the district in the card address');
	pg_assert_contains("h(\$st['district'])", $source, 'the district in the header column');
	pg_assert_contains("\$address .= pg_order_view_country(\$ship_tos[\$key]['country']);", $source, 'the card country by name');
	pg_assert_contains("h(pg_order_view_country(\$st['country']))", $source, 'the header country by name');
	pg_assert_contains('h(pg_order_view_country($billing_country))', $source, 'the billing country by name');
}

function test_print_order_parity_tax_number_and_office()
{
	$source = print_order_parity_source();

	pg_assert_contains('contacts.tax_number AS contact_tax_number', $source, 'the tax number read');
	pg_assert_contains('contacts.tax_office AS contact_tax_office', $source, 'the tax office read');
	pg_assert_contains('VKN / TCKN: \' . h($contact_tax_number)', $source, 'the tax number printed');
	pg_assert_contains("lang('Tax Office') . ': ' . h(\$contact_tax_office)", $source, 'the tax office printed');
}

function test_print_order_parity_on_account_unpaid_and_cancelled()
{
	$source = print_order_parity_source();

	pg_assert_contains("\$order_on_account ? lang('Put on account') : lang(\$payment_method)", $source, 'the payment card names an order put on account');
	pg_assert_contains("\$order_on_account ? lang('Put on account') : lang(h(\$payment_method))", $source, 'the header payment names an order put on account');
	pg_assert_contains("isset(\$row['paid_at']) && pg_order_awaiting_payment(\$row)", $source, 'the unpaid state only with paid_at');
	pg_assert_contains("lang('Awaiting bank transfer')", $source, 'the unpaid state named');
	pg_assert_contains("lang('Cancellation reason')", $source, 'the cancellation reason printed');
	pg_assert_contains("' . lang(ucwords(\$status)) . \$output_status_note . '", $source, 'the note beside the status');
}

function test_print_order_parity_codes_without_payment_method()
{
	$source = print_order_parity_source();

	pg_assert_contains("(\$output_print_payment !== '' ? '", $source, 'the payment block is no longer behind the payment method');
	pg_assert_contains("(\$currency_code != '') ? '<div>' . lang('Currency')", $source, 'the currency only when set');
	pg_assert_false(strpos($source, '$output_currency_code_row') !== false, 'no unused currency row');
}

function test_print_order_parity_installments_gift_cards_and_forms()
{
	$source = print_order_parity_source();

	pg_assert_contains("lang('Number of Installments')", $source, 'the installment count');
	pg_assert_false(strpos($source, '$output_number_of_installment_row') !== false, 'no unused installment row');
	pg_assert_contains("lang('Applied Gift Cards')", $source, 'the gift card heading');
	pg_assert_contains("\$output_applied_gift_card_rows = '';", $source, 'the gift card rows initialised');
	pg_assert_contains("(\$applied_gift_card['givex'] != 0)", $source, 'the Givex row only for Givex cards');
	pg_assert_false(strpos($source, "'[no limit]'") !== false, 'no untranslated no limit');
	pg_assert_contains("lang('No limit')", $source, 'no limit translated');
	pg_assert_false(strpos($source, '</legend>') !== false, 'the form heading closes with its own tag');
}

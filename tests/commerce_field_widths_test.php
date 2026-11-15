<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the field widths of the shipping, tax and currency forms
 * in look A: each field spans as many of the card's 12 columns as its
 * content asks (a price, a rate, a count or a date 3, a code 4, a select 6,
 * a name or a text the whole row); a narrow field alone in its row keeps
 * the row and spans its control only (pg-field-span-N).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/** The class list of the column whose label points at this field. */
function commerce_field_widths_test_column($file, $for)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/forms/' . $file);
	$at = strpos($source, 'for="' . $for . '"');

	if ($at === false) {
		return null;
	}

	if (!preg_match_all('/<div class="(col-12[^"]*)"/', substr($source, 0, $at), $matches)) {
		return null;
	}

	return end($matches[1]);
}

function test_commerce_forms_span_their_content()
{
	$cases = array(
		array('shipping_method_form.php', 'name', 'col-12 my-3'),
		array('shipping_method_form.php', 'code', 'col-12 pg-field-span-4 my-3'),
		array('shipping_method_form.php', 'handle_days', 'col-12 md:col-3 my-3'),
		array('shipping_method_form.php', 'base_transit_days', 'col-12 pg-field-span-3 my-3'),
		array('shipping_method_form.php', 'start_time', 'col-12 md:col-4'),
		array('zone_form.php', '\' . $rate . \'', 'col-12 md:col-3 my-3'),
		array('tax_zone_form.php', 'tax_rate', 'col-12 pg-field-span-3 my-3'),
		array('currency_form.php', 'code', 'col-12 md:col-4 my-3'),
		array('arrival_date_form.php', 'start_date', 'col-12 md:col-3 my-3'),
		array('container_form.php', 'cost', 'col-12 pg-field-span-3 my-3'),
		array('referral_source_form.php', 'sort_order', 'col-12 pg-field-span-3 my-3'),
		array('verified_shipping_address_form.php', 'state_id', 'col-12 md:col-6 my-3'),
	);

	foreach ($cases as $case) {
		list($file, $for, $expected) = $case;
		pg_assert_same($expected, commerce_field_widths_test_column($file, $for), $file . ' ' . $for . ' spans its content');
	}
}

function test_commerce_forms_align_amounts_by_class()
{
	foreach (array('shipping_method_form.php', 'currency_form.php', 'container_form.php') as $file) {
		$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/forms/' . $file);
		pg_assert_true(strpos($source, 'text-align: right') === false, $file . ' aligns amounts with text-end, not an inline style');
		pg_assert_true(strpos($source, 'max-width:200px') === false, $file . ' sizes fields on the grid, not inline');
	}
}

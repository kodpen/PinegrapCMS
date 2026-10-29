<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for four commerce lists: the verified shipping addresses state
 * filter is named by its label and prints countries in the panel language,
 * the tax zones list words the rate with the translated percent template,
 * the shipping methods list's check marks read "Yes", the orders for contact
 * row button names the screen it opens, and every toolbar landmark is
 * translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function commerce_lists_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_commerce_lists_names_state_filter_label()
{
	$source = commerce_lists_names_source('view_verified_shipping_addresses.php');

	pg_assert_contains('<label for="state_id" class="fw-semibold">', $source);
	pg_assert_contains('<select id="state_id" name="state_id"', $source);
}

function test_commerce_lists_names_countries_translated()
{
	$source = commerce_lists_names_source('view_verified_shipping_addresses.php');

	pg_assert_contains("countries.code AS country_code", $source);
	pg_assert_contains("h(pg_geo_country_label(\$state['country_name'], \$state['country_code']))", $source);
	pg_assert_contains("h(pg_geo_country_label(\$address['country_name'], \$address['country_code']))", $source);
	pg_assert_true(strpos($source, "h(\$address['country_name'])") === false, 'raw country name in the Country column');
	pg_assert_true(strpos($source, "h(\$state['country_name'])") === false, 'raw country name in the state filter');
}

function test_commerce_lists_names_tax_rate_percent()
{
	$source = commerce_lists_names_source('view_tax_zones.php');

	pg_assert_contains("format_tax_rate(\$tax_zone['tax_rate'])", $source);
	pg_assert_contains("lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(", $source);
	pg_assert_true(strpos($source, "h(\$tax_zone['tax_rate']) . '%") === false, 'tax rate printed as stored');
}

function test_commerce_lists_names_shipping_marks_read_yes()
{
	$source = commerce_lists_names_source('view_shipping_methods.php');

	pg_assert_true(strpos($source, '<span class="material-icons">task_alt</span>') === false, 'bare task_alt ligature');
	pg_assert_contains("<span class=\"material-icons\" aria-hidden=\"true\">task_alt</span><span class=\"visually-hidden\">' . h(lang('Yes')) . '</span>", $source);
}

function test_commerce_lists_names_orders_for_contact_view_button()
{
	$source = commerce_lists_names_source('view_orders_for_contact.php');

	pg_assert_contains("title=\"' . h(lang('View Order')) . '\" aria-label=\"' . h(lang('View Order')) . '\"", $source);
	pg_assert_true(strpos($source, "title=\"' . lang('Edit') . '\"") === false, 'row button titled Edit');
}

function test_commerce_lists_names_landmarks_translated()
{
	$paths = array(
		'view_verified_shipping_addresses.php',
		'view_tax_zones.php',
		'view_shipping_methods.php',
		'view_orders_for_contact.php',
	);

	foreach ($paths as $path) {
		$source = commerce_lists_names_source($path);
		pg_assert_true(strpos($source, 'aria-label="Button Bar"') === false, $path . ': literal landmark name');
		pg_assert_contains("aria-label=\"' . h(lang('Button Bar')) . '\"", $source, $path);
	}
}

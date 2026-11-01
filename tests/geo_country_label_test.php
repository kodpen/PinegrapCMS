<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a stored country is named and ordered on screen
 * (pg_geo_country_label() and pg_geo_sort_by_label() in includes/fn/geo.php):
 * the stored name through lang() when the shards know it, else the name
 * intl gives for the code, else the stored name; lists ordered by that label
 * with the language's collation.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A name the shards do not translate keeps its stored form, with or without
// a code, and a blank name gives nothing without a code.
function test_geo_country_label_keeps_an_untranslated_name()
{
	pg_assert_same('Nowhere Land', pg_geo_country_label('Nowhere Land', ''));
	pg_assert_same('Nowhere Land', pg_geo_country_label('Nowhere Land', 'ZZ'), 'an unknown code adds nothing');
	pg_assert_same('', pg_geo_country_label('', ''));
	pg_assert_same('', pg_geo_country_label('', 'ZZ'));
}

// A real ISO code names a blank or untranslated row through intl when PHP
// has it: never the bare code, never empty.
function test_geo_country_label_names_a_code()
{
	if (!class_exists('Locale')) {
		return;
	}

	$label = pg_geo_country_label('', 'DE');

	pg_assert_true(($label !== '') && ($label !== 'DE'), 'DE is named: ' . $label);
	pg_assert_same($label, pg_geo_country_label('', 'de'), 'the code is read without case');
}

// Lists are ordered by their labels, letters with diacritics among their
// base letters, not after Z.
function test_geo_sort_by_label_orders_by_the_shown_name()
{
	$rows = array(
		array('code' => 'ZW', 'label' => 'Zimbabwe'),
		array('code' => 'CN', 'label' => 'Çin'),
		array('code' => 'DK', 'label' => 'Danimarka'),
		array('code' => 'BR', 'label' => 'Brezilya'),
		array('code' => 'AL', 'label' => 'arnavutluk'),
	);

	$codes = array();

	foreach (pg_geo_sort_by_label($rows) as $row) {
		$codes[] = $row['code'];
	}

	if (class_exists('Collator')) {
		pg_assert_same(array('AL', 'BR', 'CN', 'DK', 'ZW'), $codes);
	} else {
		pg_assert_same('AL', $codes[0], 'case does not matter');
		pg_assert_same('ZW', $codes[4]);
	}

	pg_assert_same(array(), pg_geo_sort_by_label(array()));
	pg_assert_same(array(array('x' => 1)), pg_geo_sort_by_label(array(array('x' => 1))), 'a row without the label key stays');
}

// Every screen that lists countries names and orders them through the
// helpers; the stored name is still what the row holds.
function test_geo_country_label_used_by_the_country_lists()
{
	foreach (array(
		'includes/fn/widgets_account.php',
		'includes/fn/widgets_express_order.php',
		'get_billing_information.php',
		'get_express_order.php',
		'includes/forms/zone_form.php',
		'includes/forms/tax_zone_form.php',
		'includes/fn/forms.php',
		'view_states.php',
	) as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(strpos($source, 'pg_geo_country_label(') !== false, $file . ' names the country');
		pg_assert_true(strpos($source, 'pg_geo_sort_by_label(') !== false, $file . ' orders by the label');
	}

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_countries.php');
	pg_assert_true(strpos($list, "pg_geo_country_label(\$row['name'], \$row['code'])") !== false, 'the countries list names the country');
}

// The express order's other country selects (the design-tree select, the
// fallback billing form and the recipient shipping select) and the classic
// express order page no longer print or sort the stored name (#3327).
function test_geo_country_label_used_by_the_express_order()
{
	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');

	foreach (array(
		'SELECT code, name FROM countries ORDER BY name ASC',
		"'text' => (string)\$r['name']",
		"h((string)\$c['name'])",
	) as $raw) {
		pg_assert_true(strpos($widget, $raw) === false, 'the express order widget has no ' . $raw);
	}

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_express_order.php');
	pg_assert_true(strpos($page, "\$country_options[\$country['name']]") === false, 'the classic page keys no option by the stored name');
}

// Rows with the same label keep their order, so states already ordered by
// name stay ordered within their country.
function test_geo_sort_by_label_keeps_the_order_of_equal_labels()
{
	$rows = array();

	foreach (array('Z', 'B', 'A', 'Y', 'C', 'X', 'D', 'W', 'E', 'V', 'F', 'U', 'G', 'T', 'H', 'S', 'I', 'R') as $i => $state) {
		$rows[] = array('country' => ($i % 2) ? 'Almanya' : 'Türkiye', 'state' => $state);
	}

	$sorted = pg_geo_sort_by_label($rows, 'country');
	$states = array();

	foreach ($sorted as $row) {
		$states[$row['country']][] = $row['state'];
	}

	pg_assert_same(array('Almanya', 'Türkiye'), array_keys($states), 'countries in label order');
	pg_assert_same(array('B', 'Y', 'X', 'W', 'V', 'U', 'T', 'S', 'R'), $states['Almanya'], 'input order kept within a country');
	pg_assert_same(array('Z', 'A', 'C', 'D', 'E', 'F', 'G', 'H', 'I'), $states['Türkiye'], 'input order kept within the other');
}

function test_country_select_options()
{
	$rows = array(
		array('id' => 7, 'label' => 'Almanya', 'default_selected' => 0),
		array('id' => 3, 'label' => 'Türkiye', 'default_selected' => 1),
	);

	pg_assert_same(
		'<option value="7">Almanya</option><option value="3" selected="selected">Türkiye</option>',
		pg_country_select_options($rows),
		'the label shown, the id as value, the default selected');
	pg_assert_same(
		'<option value="7" selected="selected">Almanya</option><option value="3">Türkiye</option>',
		pg_country_select_options($rows, '7'),
		'the given country selected instead of the default');
}

// The ERP account form's country select and the address book screen name and
// order their countries through the helpers like the other country lists
// (#4697); the option value stays the country code.
function test_geo_country_label_used_by_the_erp_account_and_address_book()
{
	foreach (array(
		'includes/erp/account_form.php',
		'get_update_address_book.php',
	) as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true(strpos($source, 'pg_geo_country_label(') !== false, $file . ' names the country');
		pg_assert_true(strpos($source, 'pg_geo_sort_by_label(') !== false, $file . ' orders by the label');
	}

	$book = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_update_address_book.php');
	pg_assert_true(strpos($book, "\$country_options[\$country['name']]") === false, 'the address book keys no option by the stored name');
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
	pg_assert_true(strpos($form, "h((string) \$country_row['name'])") === false, 'the account form prints no stored name');
}

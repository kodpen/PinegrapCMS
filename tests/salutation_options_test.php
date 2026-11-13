<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the salutation pick list (get_salutation_options(),
 * includes/fn/forms.php): the labels are in the site's language while the
 * stored value stays the English abbreviation, and the express order widget
 * posts the value, not the label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The stored values are the seven abbreviations and the empty pick.
function test_salutation_options_values_unchanged()
{
	$values = array_values(get_salutation_options());
	pg_assert_same(array('', 'Dr.', 'Miss', 'Mr.', 'Mrs.', 'Ms.', 'Prof.', 'Rev.'), $values, 'the stored values');
}

// Every label is translated and no two Turkish labels are the same: a
// repeated key would drop an option from the list.
function test_salutation_options_labels_distinct_in_turkish()
{
	$english = array();
	$turkish = array();

	foreach (get_salutation_options() as $value) {
		if ($value === '') {
			continue;
		}

		$english[] = lang(array('string' => $value, 'language' => 'en'));
		$tr = lang(array('string' => $value, 'language' => 'tr', 'if_known' => true));
		pg_assert_true($tr !== '', 'a Turkish label for ' . $value);
		$turkish[] = $tr;
	}

	pg_assert_same(7, count(array_unique($english)), 'seven English labels');
	pg_assert_same(7, count(array_unique($turkish)), 'seven distinct Turkish labels');
	pg_assert_true(in_array('Bay', $turkish, true), 'Mr. reads Bay in Turkish');
}

// The widget's pick lists take label => value, so the stored abbreviation is
// posted whatever the label reads.
function test_salutation_options_widget_posts_value()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');
	pg_assert_true(strpos($source, '$sal_options as $val => $label') === false, 'no value => label loop left');
	pg_assert_same(2, substr_count($source, '$sal_options as $label => $val'), 'both pick lists read label => value');
}

// A posted salutation is kept only when it is one of the pick list's values:
// the forms draw a pick list, but a post can carry any text, which then
// reached the orders, the addresses and the order exports.
function test_salutation_value_keeps_only_the_options()
{
	foreach (array_values(get_salutation_options()) as $value) {
		pg_assert_same($value, pg_salutation_value($value), 'option ' . $value);
	}

	pg_assert_same('Mr.', pg_salutation_value(' Mr. '), 'trimmed');

	foreach (array('=1+1', '@SUM(1+1)', '+90', 'mr.', 'Mr', 'Sayın', '<b>Mr.</b>', null, 12, array('Mr.')) as $posted) {
		pg_assert_same('', pg_salutation_value($posted), 'refused: ' . json_encode($posted));
	}
}

// Every site form that saves a salutation compares it with the options
// first: the checkout's billing and recipients, the classic billing and
// shipping pages, the address book and the account profile.
function test_salutation_forms_compare_with_the_options()
{
	$forms = array(
		'express_order.php'                => array("pg_liveform_salutation_values(\$liveform, array('billing_salutation'));", "pg_liveform_salutation_values(\$liveform, array(\$pg_prefix . 'salutation'));"),
		'billing_information.php'          => array("pg_liveform_salutation_values(\$liveform, array('billing_salutation'));"),
		'shipping_address_and_arrival.php' => array("pg_liveform_salutation_values(\$liveform, array('salutation'));"),
		'update_address_book.php'          => array("pg_liveform_salutation_values(\$liveform, array('salutation'));"),
		'my_account_profile.php'           => array("pg_liveform_salutation_values(\$liveform, array('salutation'));"),
	);

	foreach ($forms as $file => $calls) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		foreach ($calls as $call) {
			pg_assert_true(strpos($source, $call) !== false, $file . ': ' . $call);
		}
	}
}

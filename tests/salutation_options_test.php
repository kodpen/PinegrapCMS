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

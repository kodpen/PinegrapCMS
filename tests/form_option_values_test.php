<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the option checks of pick list, radio button and check box
 * fields: a site submission keeps only values of the option list
 * (pg_cf_option_values_ok(), custom_form.php), and Edit Submitted Form
 * draws a stored value that is not in the list instead of dropping it
 * (pg_submitted_form_keep_stored_options()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

// Values of the list, and no value at all, pass.
function test_form_option_values_in_list()
{
	$list = array('Bir', 'İki', 'Üç');
	pg_assert_same(array(), pg_cf_values_outside_options('', $list), 'empty value');
	pg_assert_same(array(), pg_cf_values_outside_options(null, $list), 'no value');
	pg_assert_same(array(), pg_cf_values_outside_options('İki', $list), 'one value of the list');
	pg_assert_same(array(), pg_cf_values_outside_options(array('Bir', 'Üç'), $list), 'several values of the list');
	pg_assert_same(array(), pg_cf_values_outside_options(array('', 'Bir'), $list), 'an empty entry is no value');
	pg_assert_same(array(), pg_cf_values_outside_options(' Bir ', array('Bir ')), 'spaces around either side');
	pg_assert_true(pg_cf_option_values_ok('', $list), 'empty is accepted');
	pg_assert_true(pg_cf_option_values_ok(array('Bir', 'İki'), $list), 'list values are accepted');
}

// A value the list does not have is named, once, in the order sent.
function test_form_option_values_outside_list()
{
	$list = array('Kahve', 'Çay');
	pg_assert_same(array('Dört'), pg_cf_values_outside_options('Dört', $list), 'one unknown value');
	pg_assert_same(array('Bira'), pg_cf_values_outside_options(array('Bira', 'Kahve'), $list), 'unknown among known');
	pg_assert_same(array('Bira', 'Su'), pg_cf_values_outside_options(array('Bira', 'Su', 'Bira'), $list), 'each unknown value once');
	pg_assert_same(array('kahve'), pg_cf_values_outside_options('kahve', $list), 'case counts');
	pg_assert_same(array('0'), pg_cf_values_outside_options('0', array('')), 'a zero is a value');
	pg_assert_false(pg_cf_option_values_ok('Dört', $list), 'unknown value refused');
	pg_assert_false(pg_cf_option_values_ok(array('Kahve', array('Çay')), $list), 'a nested array is refused');
	pg_assert_false(pg_cf_option_values_ok('Kahve', array()), 'no options, no value');
}

// Edit Submitted Form: a stored value outside the list becomes an extra
// option marked as not in the list; values of the list add nothing.
function test_form_option_values_keep_stored()
{
	$options = array(
		array('id' => '5', 'label' => 'Kahve', 'value' => 'Kahve', 'default_selected' => '0'),
		array('id' => '6', 'label' => 'Çay', 'value' => 'Çay', 'default_selected' => '1'),
	);

	pg_assert_same($options, pg_submitted_form_keep_stored_options($options, 'Kahve', 12), 'value of the list');
	pg_assert_same($options, pg_submitted_form_keep_stored_options($options, '', 12), 'no value');

	$kept = pg_submitted_form_keep_stored_options($options, array('Bira', 'Kahve'), 12);
	pg_assert_same(3, count($kept), 'one option added');
	pg_assert_same('Bira', $kept[2]['value'], 'the stored value is posted back unchanged');
	pg_assert_same('12_stored0', $kept[2]['id'], 'id unique within the field');
	pg_assert_same(0, $kept[2]['default_selected'], 'not checked by default');
	pg_assert_true(strpos($kept[2]['label'], 'Bira') === 0, 'label starts with the value');
	pg_assert_true($kept[2]['label'] !== 'Bira', 'label says the value is not in the list');
}

// The screens use the checks: the site refuses, the edit screen keeps, and
// the hidden field of a check box group sits before the fieldset, where the
// legend Bootstrap floats cannot push the first option out of the card.
function test_form_option_values_wired()
{
	$site = file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php');
	pg_assert_true(strpos($site, 'pg_cf_option_values_ok(') !== false, 'custom_form.php checks posted options');

	$form = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');
	pg_assert_true(strpos($form, '= pg_submitted_form_keep_stored_options(') !== false, 'edit screen keeps stored values');
	pg_assert_true(strpos($form, "' . \$output_hidden . '\n                        <fieldset>") !== false, 'hidden field before the fieldset');
	pg_assert_true(strpos($form, "</legend>\n                            ' . \$output_hidden") === false, 'hidden field not right after the legend');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for _pg_cf_seed_record_values() (includes/fn/custom_form.php): which
 * values of a design template's sample record are written into its form -
 * the fields the form has, a list of values for a check box group, nothing
 * empty and nothing that is not a scalar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_seed_fields()
{
	return array(
		'name'  => array('id' => 1, 'name' => 'name'),
		'price' => array('id' => 2, 'name' => 'price'),
		'diet'  => array('id' => 3, 'name' => 'diet'),
	);
}

// A scalar becomes a list of one value, keyed by the lowercased name.
function test_custom_form_seed_scalar_values()
{
	$values = _pg_cf_seed_record_values(array('Name' => ' Espresso ', 'price' => 3), _test_cf_seed_fields());
	pg_assert_same(array('name' => array('Espresso'), 'price' => array('3')), $values);
}

// A list fills a field that holds several values, once each, in order.
function test_custom_form_seed_list_values()
{
	$values = _pg_cf_seed_record_values(array('diet' => array('Vegan', '', 'Gluten free', 'Vegan', array('x'))), _test_cf_seed_fields());
	pg_assert_same(array('diet' => array('Vegan', 'Gluten free')), $values);
}

// Unknown names, empty values and empty lists are left out.
function test_custom_form_seed_skips_unknown_and_empty()
{
	$values = _pg_cf_seed_record_values(array('colour' => 'red', 'name' => '  ', 'diet' => array(), 'price' => null), _test_cf_seed_fields());
	pg_assert_same(array(), $values);
	pg_assert_same(array(), _pg_cf_seed_record_values('not a record', _test_cf_seed_fields()));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the per-field "Include in translation" switch of custom forms
 * (form_fields.translate_values): the three-state `_cf.translate` record
 * (_pg_cf_node_record(), _pg_cf_controls()), the column it writes
 * (pg_cf_translate_flag()), the flag a record's values carry to the widgets
 * (pg_sw_index_form_data()) and which values the page translates
 * (pg_tr_record_field_translatable()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A semantic form control with the given attributes and `_cf` record.
function _test_cf_translate_control($tag, $attrs, $cf = null)
{
	$list = array();
	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}
	$props = array('tag' => $tag, '_attrs' => $list);
	if ($cf !== null) {
		$props['_cf'] = $cf;
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

// The control says true, false or nothing.
function test_custom_form_translate_controls_three_state()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_test_cf_translate_control('input', array('type' => 'text', 'name' => 'icon'), array('translate' => false)),
		_test_cf_translate_control('textarea', array('name' => 'body'), array('translate' => true)),
		_test_cf_translate_control('input', array('type' => 'text', 'name' => 'title')),
	));
	$fields = _pg_cf_controls($tree, 0);
	pg_assert_same(false, $fields['icon']['translate'], 'switch off');
	pg_assert_same(true, $fields['body']['translate'], 'switch on');
	pg_assert_same(null, $fields['title']['translate'], 'no key: the row keeps its flag');
}

function test_custom_form_translate_node_record_key()
{
	$off = _pg_cf_node_record(_test_cf_translate_control('input', array(), array('translate' => 0)));
	$on = _pg_cf_node_record(_test_cf_translate_control('input', array(), array('translate' => true)));
	$none = _pg_cf_node_record(_test_cf_translate_control('input', array()));
	pg_assert_same(false, $off['translate']);
	pg_assert_same(true, $on['translate']);
	pg_assert_true(array_key_exists('translate', $none), 'the key is always present');
	pg_assert_same(null, $none['translate']);
}

// Once the switch is used the control decides; before that the stored row
// keeps its flag, and a new field is included.
function test_custom_form_translate_flag()
{
	$off_row = array('type' => 'text box', 'translate_values' => '0');
	pg_assert_same(0, pg_cf_translate_flag(false, 'text box'));
	pg_assert_same(1, pg_cf_translate_flag(true, 'text area', $off_row), 'the switch turns it on');
	pg_assert_same(0, pg_cf_translate_flag(null, 'text box', $off_row), 'kept from the row');
	pg_assert_same(1, pg_cf_translate_flag(null, 'text area', null), 'a new field is included');
	pg_assert_same(1, pg_cf_translate_flag(null, 'text box', array('type' => 'text box')), 'a row read without the column');
	pg_assert_same(1, pg_cf_translate_flag(false, 'pick list'), 'only text fields can be left out');
	pg_assert_same(1, pg_cf_translate_flag(null, 'date', $off_row), 'a field that is no longer a text field');
}

// The flag travels with the value; a row read without the column counts as
// included.
function test_custom_form_translate_index_carries_flag()
{
	$base = array(
		'form_id' => 7, 'data_name' => '', 'data_file_id' => 0, 'field_label' => '',
		'field_type' => 'text box', 'field_wysiwyg' => 0, 'file_name' => null,
	);
	$index = pg_sw_index_form_data(array(
		array_merge($base, array('data' => 'bi-star', 'field_name' => 'icon', 'field_translate' => '0')),
		array_merge($base, array('data' => 'Hello', 'field_name' => 'title', 'field_translate' => '1')),
		array_merge($base, array('data' => 'Body', 'field_name' => 'body')),
	));
	pg_assert_same(0, $index[7]['icon']['translate']);
	pg_assert_same(1, $index[7]['title']['translate']);
	pg_assert_same(1, $index[7]['body']['translate']);
}

function test_custom_form_translate_record_field_translatable()
{
	pg_assert_true(pg_tr_record_field_translatable(array('data' => 'Hello', 'type' => 'text box', 'translate' => 1)));
	pg_assert_true(pg_tr_record_field_translatable(array('data' => 'Hello', 'type' => 'text area')), 'no flag: included');
	pg_assert_false(pg_tr_record_field_translatable(array('data' => 'bi-star', 'type' => 'text box', 'translate' => 0)), 'left out');
	pg_assert_false(pg_tr_record_field_translatable(array('data' => '', 'type' => 'text box')), 'empty');
	pg_assert_false(pg_tr_record_field_translatable(array('data' => '2020-01-01', 'type' => 'date')), 'not a text field');
	pg_assert_false(pg_tr_record_field_translatable('x'), 'not a field');
}

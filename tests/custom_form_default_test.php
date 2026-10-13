<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the default value _pg_cf_controls() (includes/fn/custom_form.php)
 * reads from a form tree: the value of a text control, the text of a text
 * area, and the answer a single pick list marks selected - what an
 * office-use-only field stores for a visitor who never sees it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_default_node($tag, $attrs, $children = array(), $props = array())
{
	$a = array();
	foreach ($attrs as $k => $v) $a[] = array('name' => $k, 'value' => $v);
	return array('type' => 'semantic', 'props' => array_merge(array('tag' => $tag, '_attrs' => $a), $props), 'children' => $children);
}

function _test_cf_default_option($value, $selected = false)
{
	$attrs = array('value' => $value);
	if ($selected) $attrs['selected'] = '';
	return _test_cf_default_node('option', $attrs, array(), array('text' => $value));
}

function _test_cf_default_fields($controls)
{
	return _pg_cf_controls(array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => $controls));
}

// The selected answer of a single pick list is its default.
function test_custom_form_default_select_selected_option()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('select', array('name' => 'status'), array(
			_test_cf_default_option('Requested', true),
			_test_cf_default_option('Confirmed'),
		), array('_cf' => array('office_use_only' => true))),
	));
	pg_assert_same('Requested', $fields['status']['default_value'], 'selected answer');
	pg_assert_true($fields['status']['office_use_only'], 'office use only kept');
	pg_assert_true($fields['status']['options'][0]['default'], 'option still marked default');
}

// No answer selected, a prompt only, or a multiple list: no default.
function test_custom_form_default_select_without_selection()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('select', array('name' => 'topic'), array(
			_test_cf_default_option(''),
			_test_cf_default_option('Billing'),
		)),
		_test_cf_default_node('select', array('name' => 'days', 'multiple' => ''), array(
			_test_cf_default_option('Monday', true),
			_test_cf_default_option('Tuesday', true),
		)),
	));
	pg_assert_same('', $fields['topic']['default_value'], 'nothing selected');
	pg_assert_same('', $fields['days']['default_value'], 'multiple list');
}

// An option without a value attribute answers with its text.
function test_custom_form_default_select_option_text()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('select', array('name' => 'size'), array(
			array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Large', '_attrs' => array(array('name' => 'selected', 'value' => ''))), 'children' => array()),
		)),
	));
	pg_assert_same('Large', $fields['size']['default_value']);
}

// Text controls keep their own rule.
function test_custom_form_default_text_controls()
{
	$fields = _test_cf_default_fields(array(
		_test_cf_default_node('input', array('type' => 'text', 'name' => 'city', 'value' => 'Istanbul')),
		_test_cf_default_node('textarea', array('name' => 'note'), array(), array('text' => 'Hello')),
	));
	pg_assert_same('Istanbul', $fields['city']['default_value']);
	pg_assert_same('Hello', $fields['note']['default_value']);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the inputs of a collection's fields by kind
 * (includes/fn/collections.php): a number as typed and as stored
 * (pg_coll_number_value()), an image's file name (pg_coll_image_name_ok()),
 * a reference against the records of the form it points into
 * (pg_coll_reference_ok()), the attributes a control gets by kind
 * (pg_coll_input_attrs()) and the attributes and choices the site form's
 * controls are drawn with (pg_cf_apply_field_bindings()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_collections_number_value()
{
	pg_assert_same('12', pg_coll_number_value('12'));
	pg_assert_same('12', pg_coll_number_value(' 12 '), 'trimmed');
	pg_assert_same('-3.5', pg_coll_number_value('-3.5'));
	pg_assert_same('3.5', pg_coll_number_value('3,5'), 'a decimal comma is stored as a point');
	pg_assert_same('0.25', pg_coll_number_value('.25'), 'a leading point gets its zero');
	pg_assert_same('7', pg_coll_number_value('+7'), 'a plus sign is dropped');
	pg_assert_same('', pg_coll_number_value(''), 'empty stays empty (the required check is elsewhere)');

	pg_assert_same(null, pg_coll_number_value('12a'));
	pg_assert_same(null, pg_coll_number_value('1,234.5'), 'a thousands separator is not a number');
	pg_assert_same(null, pg_coll_number_value('1e3'), 'no exponent');
	pg_assert_same(null, pg_coll_number_value('-'));
	pg_assert_same(null, pg_coll_number_value('.'));
	pg_assert_same(null, pg_coll_number_value(array('1')), 'not a string');
	pg_assert_same(null, pg_coll_number_value(str_repeat('9', 40)), 'longer than a stored number');
}

function test_collections_image_name()
{
	foreach (array('a.jpg', 'B.JPEG', 'c.png', 'd.gif', 'e.webp', 'photo.final.Png') as $name) {
		pg_assert_true(pg_coll_image_name_ok($name), $name);
	}
	foreach (array('a.svg', 'a.pdf', 'a', 'a.jpg.php', 'png', '', 'a.png ') as $name) {
		pg_assert_false(pg_coll_image_name_ok($name), $name);
	}
	pg_assert_false(pg_coll_image_name_ok(array('a.png')), 'not a string');
}

function test_collections_reference_ok()
{
	$codes = array('abc123' => 'News', 'def456' => 'Sport');

	pg_assert_true(pg_coll_reference_ok('', $codes), 'empty: nothing to check');
	pg_assert_true(pg_coll_reference_ok('abc123', $codes));
	pg_assert_true(pg_coll_reference_ok(' abc123 ', $codes), 'trimmed');
	pg_assert_true(pg_coll_reference_ok('abc123, def456', $codes), 'several in a text box');
	pg_assert_true(pg_coll_reference_ok(array('abc123', 'def456'), $codes), 'several from a check box group');
	pg_assert_true(pg_coll_reference_ok(array('abc123', ''), $codes), 'an empty box of a group is left out');

	pg_assert_false(pg_coll_reference_ok('zzz', $codes), 'not a record of that form');
	pg_assert_false(pg_coll_reference_ok('abc123, zzz', $codes), 'one unknown among known ones');
	pg_assert_false(pg_coll_reference_ok(array('abc123', 'zzz'), $codes));
	pg_assert_false(pg_coll_reference_ok(array(array('abc123')), $codes), 'a nested array');
	pg_assert_false(pg_coll_reference_ok('News', $codes), 'a label is not a code');
	pg_assert_false(pg_coll_reference_ok('abc123', array()), 'a form with no records');
}

function test_collections_input_attrs()
{
	pg_assert_same(array('type' => 'number', 'step' => 'any', 'inputmode' => 'decimal'), pg_coll_input_attrs('number', 'text box'));
	pg_assert_same(array('accept' => 'image/jpeg,image/png,image/gif,image/webp'), pg_coll_input_attrs('image', 'file upload'));
	pg_assert_same(array(), pg_coll_input_attrs('reference', 'text box'), 'a reference is drawn by its choices');
	pg_assert_same(array(), pg_coll_input_attrs('number', 'text area'), 'a kind the type cannot have');
	pg_assert_same(array(), pg_coll_input_attrs('', 'text box'));
}

function _collections_inputs_attr($node, $name)
{
	foreach ((array)$node['props']['_attrs'] as $a) {
		if (is_array($a) && isset($a['name']) && $a['name'] === $name) return $a['value'];
	}
	return null;
}

function _collections_inputs_control($tag, $attrs, $children = array())
{
	$list = array();
	foreach ($attrs as $name => $value) $list[] = array('name' => $name, 'value' => $value);
	return array('type' => 'semantic', 'props' => array('tag' => $tag, '_attrs' => $list), 'children' => $children);
}

function _collections_inputs_row($id, $name, $type, $kind, $options = null)
{
	return array(
		'id' => $id, 'name' => $name, 'label' => ucfirst($name), 'type' => $type, 'required' => 0,
		'multiple' => 0, 'maxlength' => 0, 'size' => 0, 'rows' => 0, 'cols' => 0, 'default_value' => '',
		'use_folder_name_for_default_value' => 0, 'contact_field' => '', 'office_use_only' => 0,
		'wysiwyg' => 0, 'information' => '', 'rss_field' => '', 'validation_regex' => '', 'validation_message' => '',
		'collection_kind' => $kind, 'reference_page_id' => 0, 'reference_options' => $options,
	);
}

function test_collections_site_controls_by_kind()
{
	$price = _collections_inputs_control('input', array('type' => 'text', 'name' => 'price'));
	$photo = _collections_inputs_control('input', array('type' => 'file', 'name' => 'photo'));
	$prompt = array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Choose', '_attrs' => array(array('name' => 'value', 'value' => ''))), 'children' => array());
	$category = _collections_inputs_control('select', array('name' => 'category'), array($prompt));
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => array($price, $photo, $category));

	$map = array(
		'price'    => _collections_inputs_row(901, 'price', 'text box', 'number'),
		'photo'    => _collections_inputs_row(902, 'photo', 'file upload', 'image'),
		'category' => _collections_inputs_row(903, 'category', 'pick list', 'reference', array('abc123' => 'News', 'def456' => 'Sport & <Life>')),
	);
	pg_cf_apply_field_bindings($tree, $map, null, 0, false, array());
	list($price, $photo, $category) = $tree['children'];

	pg_assert_same('number', _collections_inputs_attr($price, 'type'));
	pg_assert_same('any', _collections_inputs_attr($price, 'step'));
	pg_assert_same('decimal', _collections_inputs_attr($price, 'inputmode'));
	pg_assert_same('image/jpeg,image/png,image/gif,image/webp', _collections_inputs_attr($photo, 'accept'));

	pg_assert_same(3, count($category['children']), 'the prompt and one choice per record');
	pg_assert_same('', _collections_inputs_attr($category['children'][0], 'value'), 'the prompt is kept');
	pg_assert_same('abc123', _collections_inputs_attr($category['children'][1], 'value'));
	pg_assert_same('News', $category['children'][1]['props']['text']);
	pg_assert_same('Sport & <Life>', $category['children'][2]['props']['text'], 'text as stored; the renderer escapes it');
}

function test_collections_site_reference_without_records()
{
	$prompt = array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Choose', '_attrs' => array(array('name' => 'value', 'value' => ''))), 'children' => array());
	$drawn = array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => 'Old', '_attrs' => array(array('name' => 'value', 'value' => 'old'))), 'children' => array());
	$tree = _collections_inputs_control('select', array('name' => 'category'), array($prompt, $drawn));
	$map = array('category' => _collections_inputs_row(904, 'category', 'pick list', 'reference', array()));
	pg_cf_apply_field_bindings($tree, $map, null, 0, false, array());

	pg_assert_same(1, count($tree['children']), 'only the prompt: a drawn choice the server would refuse is dropped');
	pg_assert_same('', _collections_inputs_attr($tree['children'][0], 'value'));
}

function test_collections_reference_labels()
{
	pg_assert_same(
		array('b2' => 'Alpha', 'a1' => 'beta'),
		pg_coll_reference_labels(array('a1' => 'beta', 'b2' => 'Alpha')),
		'sorted by label, case left alone'
	);
	pg_assert_same(
		array('x1' => 'News (x1)', 'x2' => 'News (x2)', 'y3' => 'Sport'),
		pg_coll_reference_labels(array('x2' => 'News', 'y3' => 'Sport', 'x1' => 'News')),
		'a shared label gets each code'
	);
	pg_assert_same(array('c9' => 'c9'), pg_coll_reference_labels(array('c9' => 'c9')), 'a code shown as its own label stays as is');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a designed form control that starts from a named page-address
 * parameter (props._cf.url_param, includes/fn/custom_form.php): the value
 * rule (pg_cf_url_prefill_value()), option matching (pg_cf_match_option(),
 * pg_cf_select_option_values()), which fields take a value
 * (pg_cf_url_prefill_values()), its place in the order of sources
 * (pg_cf_field_initial_value()), the render pass without a database
 * (pg_cf_apply_field_bindings(), _render_tree_node()), the `_cf` record
 * (_pg_cf_node_record(), _pg_cf_controls()), where the renderer reads the
 * address (includes/fn/widgets.php) and the editor's panel and twins
 * (assets/js/style_designer.src.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A semantic form control with the given attributes, `_cf` record and id.
function _test_cf_url_control($tag, $attrs, $cf = null, $id = '', $text = null)
{
	$list = array();
	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}
	$props = array('tag' => $tag, '_attrs' => $list);
	if ($id !== '') {
		$props['id'] = $id;
	}
	if ($cf !== null) {
		$props['_cf'] = $cf;
	}
	if ($text !== null) {
		$props['text'] = $text;
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function _test_cf_url_tree($children, $tag = 'div')
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag), 'children' => $children);
}

// A <select> with options given as value => text (value null: no attribute).
function _test_cf_url_select($name, $options, $cf = null, $id = '')
{
	$select = _test_cf_url_control('select', array('name' => $name), $cf, $id);
	foreach ($options as $option) {
		$attrs = ($option[0] === null) ? array() : array('value' => $option[0]);
		if (!empty($option[2])) {
			$attrs['selected'] = '';
		}
		$select['children'][] = _test_cf_url_control('option', $attrs, null, '', $option[1]);
	}
	return $select;
}

// The field row pg_cf_field_map() would hand the render walker.
function _test_cf_url_row($id, $name, $type, $extra = array())
{
	return array_merge(array(
		'id' => $id, 'name' => $name, 'type' => $type, 'wysiwyg' => '0', 'multiple' => '0',
		'office_use_only' => '0', 'contact_field' => '', 'maxlength' => '0',
		'use_folder_name_for_default_value' => '0', 'default_value' => '', 'validation_message' => '',
	), $extra);
}

// Stands in for a liveform: pg_cf_field_initial_value() reads one value.
function _test_cf_url_liveform($values)
{
	return new class($values) {
		public $values;

		public function __construct($values)
		{
			$this->values = $values;
		}

		public function get_field_value($field)
		{
			return isset($this->values[$field]) ? $this->values[$field] : null;
		}
	};
}

// Runs $check with $_GET set to $get, and puts the real $_GET back afterwards.
function _test_cf_url_with_get($get, $check)
{
	$saved = $_GET;
	$_GET = $get;
	try {
		return $check();
	} finally {
		$_GET = $saved;
	}
}

// The node carrying props.id $id, or null.
function _test_cf_url_find($tree, $id)
{
	if (!is_array($tree)) {
		return null;
	}
	if (isset($tree['props']['id']) && $tree['props']['id'] === $id) {
		return $tree;
	}
	foreach ((isset($tree['children']) && is_array($tree['children'])) ? $tree['children'] : array() as $child) {
		$hit = _test_cf_url_find($child, $id);
		if ($hit !== null) {
			return $hit;
		}
	}
	return null;
}

function _test_cf_url_attr($node, $name)
{
	foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
		if (is_array($a) && isset($a['name']) && $a['name'] === $name) {
			return isset($a['value']) ? (string) $a['value'] : '';
		}
	}
	return null;
}

function _test_cf_url_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The text of a function from its declaration to the next one at the same level.
function _test_cf_url_php_function($source, $name)
{
	$from = strpos($source, "\nfunction " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\nfunction ", $from + 1);
	return substr($source, $from, ($to === false) ? strlen($source) - $from : $to - $from);
}

// A function of style_designer.js, from its declaration to its closing brace
// at the same indent.
function _test_cf_url_js_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// -- the value rule ------------------------------------------------------------

function test_cf_url_value_missing_or_empty()
{
	pg_assert_same('', pg_cf_url_prefill_value(array(), 'topic'), 'missing');
	pg_assert_same('', pg_cf_url_prefill_value(array('topic' => ''), 'topic'), 'empty');
	pg_assert_same('', pg_cf_url_prefill_value(array('topic' => "  \t "), 'topic'), 'whitespace only');
	pg_assert_same('', pg_cf_url_prefill_value(array('topic' => array('x')), 'topic'), '?topic[]=x');
	pg_assert_same('', pg_cf_url_prefill_value('topic=x', 'topic'), 'not an array');
	pg_assert_same('', pg_cf_url_prefill_value(array('topic' => 'x'), ''), 'no name');
	pg_assert_same('', pg_cf_url_prefill_value(array('topic' => "\xff"), 'topic'), 'invalid UTF-8');
}

function test_cf_url_value_single_line_refusals()
{
	$refused = array(
		'tab' => "a\tb", 'line feed' => "a\nb", 'U+0085' => "a\u{0085}b", 'U+202E' => "a\u{202E}b",
		'U+200B' => "a\u{200B}b", 'U+FEFF' => "a\u{FEFF}b", 'U+2028' => "a\u{2028}b", 'U+00AD' => "a\u{00AD}b",
	);
	foreach ($refused as $label => $value) {
		pg_assert_same('', pg_cf_url_prefill_value(array('topic' => $value), 'topic'), $label);
	}
	pg_assert_same('5', pg_cf_url_prefill_value(array('topic' => "\n5\n"), 'topic'), 'outer line breaks trimmed');
	pg_assert_same('Gülşen İnce', pg_cf_url_prefill_value(array('topic' => ' Gülşen İnce '), 'topic'));
	pg_assert_same("Jose\u{0301}", pg_cf_url_prefill_value(array('topic' => "Jose\u{0301}"), 'topic'), 'combining accent kept');
}

function test_cf_url_value_multiline()
{
	pg_assert_same("line 1\nline 2", pg_cf_url_prefill_value(array('m' => "line 1\nline 2"), 'm', true));
	pg_assert_same("a\tb", pg_cf_url_prefill_value(array('m' => "a\tb"), 'm', true), 'tab kept');
	pg_assert_same("a\nb\nc", pg_cf_url_prefill_value(array('m' => "a\r\nb\rc"), 'm', true), 'line ends made \n');
	pg_assert_same('', pg_cf_url_prefill_value(array('m' => "a\x00b"), 'm', true), 'NUL');
	pg_assert_same('', pg_cf_url_prefill_value(array('m' => "a\x0Bb"), 'm', true), 'vertical tab');
	pg_assert_same('', pg_cf_url_prefill_value(array('m' => "a\u{202E}b"), 'm', true), 'bidi control');
}

// '^^' would be read as a token by the renderer; a lone '^' is text.
function test_cf_url_value_token_marks()
{
	pg_assert_same('', pg_cf_url_prefill_value(array('topic' => 'x^^__error_message^^y'), 'topic'));
	pg_assert_same('2^10', pg_cf_url_prefill_value(array('topic' => '2^10'), 'topic'));
}

function test_cf_url_value_length_cap()
{
	$long = str_repeat('ş', 1500);
	$value = pg_cf_url_prefill_value(array('topic' => $long), 'topic');
	pg_assert_same(1000, mb_strlen($value, 'UTF-8'), 'cut to 1000 characters');
	pg_assert_same(2000, strlen($value), 'characters, not bytes');
	pg_assert_same(20, mb_strlen(pg_cf_url_prefill_value(array('topic' => $long), 'topic', false, 20), 'UTF-8'));
}

// -- option matching -------------------------------------------------------------

function test_cf_url_match_option()
{
	pg_assert_same('Billing', pg_cf_match_option('Billing', array('', 'Sales', 'Billing')), 'exact');
	pg_assert_same('Bug Report', pg_cf_match_option('bug report', array('Feature', 'Bug Report')), 'the option\'s spelling');
	pg_assert_same('bug', pg_cf_match_option('bug', array('BUG', 'bug')), 'exact beats a case variant');
	pg_assert_same('', pg_cf_match_option('Refund', array('Sales', 'Billing')), 'no match');
	pg_assert_same('', pg_cf_match_option('', array('', 'Sales')), 'an empty value never picks the prompt');
	pg_assert_same('Ürün', pg_cf_match_option('ÜRÜN', array('Ürün')), 'UTF-8 case');
}

function test_cf_url_select_option_values()
{
	$select = _test_cf_url_select('topic', array(
		array('', 'Choose…'), array('sales', 'Sales'), array(null, 'Billing'),
	));
	pg_assert_same(array('sales', 'Billing'), pg_cf_select_option_values($select));
	pg_assert_same(array(), pg_cf_select_option_values(array('props' => array('tag' => 'select'))));
}

// A pick list is matched against what it draws, without a database: the
// bindings rebuild a mapped select from form_field_options, which needs one.
function test_cf_url_select_outcome()
{
	$options = array(array('', 'Choose…'), array('Sales', 'Sales', true), array('Billing', 'Billing'));

	$select = _test_cf_url_select('topic', $options);
	$value = pg_cf_match_option('billing', pg_cf_select_option_values($select));
	pg_assert_same('Billing', $value);
	pg_cf_select_stored_option($select, $value);
	pg_assert_same(null, _test_cf_url_attr($select['children'][1], 'selected'), 'the designer\'s selection gives way');
	pg_assert_same('', _test_cf_url_attr($select['children'][2], 'selected'), 'the matched option is selected');

	$select = _test_cf_url_select('topic', $options);
	pg_cf_select_stored_option($select, '');
	pg_assert_same('', _test_cf_url_attr($select['children'][1], 'selected'), 'no value keeps the designer\'s selection');

	pg_assert_same('', pg_cf_match_option('Refund', pg_cf_select_option_values($select)), 'an unknown value falls through');
}

// -- which fields take a value --------------------------------------------------------

function test_cf_url_prefill_values_names()
{
	$tree = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'text', 'name' => 'subject'), array('url_param' => 'Topic')),
	));
	$controls = _pg_cf_controls($tree, 0);
	$map = array('subject' => _test_cf_url_row(1, 'subject', 'text box'));

	pg_assert_same(array('subject' => 'Billing'), pg_cf_url_prefill_values($controls, $map, array('topic' => 'Billing')), 'a usable name');
	pg_assert_same(array(), pg_cf_url_prefill_values($controls, $map, array()), 'an empty request');
	pg_assert_same(array(), pg_cf_url_prefill_values($controls, array(), array('topic' => 'x')), 'no row yet');

	foreach (array('name', 'email', 'page', 'utm_x', 'pg_x', '1x', 'a-b') as $bad) {
		$tree = _test_cf_url_tree(array(
			_test_cf_url_control('input', array('type' => 'text', 'name' => 'subject'), array('url_param' => $bad)),
		));
		pg_assert_same(array(), pg_cf_url_prefill_values(_pg_cf_controls($tree, 0), $map, array($bad => 'x')), $bad);
	}
}

function test_cf_url_prefill_values_hidden_and_file()
{
	$office = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'text', 'name' => 'status'), array('url_param' => 'status', 'office_use_only' => true)),
	));
	$map = array('status' => _test_cf_url_row(1, 'status', 'text box'));
	pg_assert_same(array(), pg_cf_url_prefill_values(_pg_cf_controls($office, 0), $map, array('status' => 'Closed')), 'office on the control');

	$plain = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'text', 'name' => 'status'), array('url_param' => 'status')),
	));
	$map = array('status' => _test_cf_url_row(1, 'status', 'text box', array('office_use_only' => '1')));
	pg_assert_same(array(), pg_cf_url_prefill_values(_pg_cf_controls($plain, 0), $map, array('status' => 'Closed')), 'office on the row');

	$file = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'file', 'name' => 'attachment'), array('url_param' => 'attachment')),
	));
	$map = array('attachment' => _test_cf_url_row(1, 'attachment', 'file upload'));
	pg_assert_same(array(), pg_cf_url_prefill_values(_pg_cf_controls($file, 0), $map, array('attachment' => 'x')), 'file upload');
}

function test_cf_url_prefill_values_check_boxes()
{
	$map = array('agree' => _test_cf_url_row(1, 'agree', 'check box', array('multiple' => '1')));
	$cases = array(
		'one box' => array(array('value' => 'yes')),
		'one value twice' => array(array('value' => 'yes'), array('value' => 'yes')),
		'no values' => array(array(), array()),
	);
	foreach ($cases as $label => $boxes) {
		$children = array();
		foreach ($boxes as $i => $extra) {
			$children[] = _test_cf_url_control('input', array_merge(array('type' => 'checkbox', 'name' => 'agree'), $extra),
				($i === 0) ? array('url_param' => 'agree') : null);
		}
		$controls = _pg_cf_controls(_test_cf_url_tree($children), 0);
		pg_assert_same(array(), pg_cf_url_prefill_values($controls, $map, array('agree' => 'yes')), $label);
	}

	$group = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'checkbox', 'name' => 'tags', 'value' => 'Design'), array('url_param' => 'tag')),
		_test_cf_url_control('input', array('type' => 'checkbox', 'name' => 'tags', 'value' => 'Code')),
	), 'fieldset');
	$map = array('tags' => _test_cf_url_row(2, 'tags', 'check box', array('multiple' => '1')));
	pg_assert_same(array('tags' => 'Code'), pg_cf_url_prefill_values(_pg_cf_controls($group, 0), $map, array('tag' => 'code')), 'a two-value group');
}

function test_cf_url_prefill_values_options_and_text()
{
	$tree = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'radio', 'name' => 'kind', 'value' => 'Question'), array('url_param' => 'kind')),
		_test_cf_url_control('input', array('type' => 'radio', 'name' => 'kind', 'value' => 'Discussion')),
		_test_cf_url_select('board', array(array('', 'Choose…'), array('General', 'General')), array('url_param' => 'board')),
		_test_cf_url_control('input', array('type' => 'text', 'name' => 'title', 'maxlength' => '5'), array('url_param' => 'title')),
		_test_cf_url_control('textarea', array('name' => 'body'), array('url_param' => 'body')),
	));
	$map = array(
		'kind' => _test_cf_url_row(1, 'kind', 'radio button'),
		'board' => _test_cf_url_row(2, 'board', 'pick list'),
		'title' => _test_cf_url_row(3, 'title', 'text box', array('maxlength' => '5')),
		'body' => _test_cf_url_row(4, 'body', 'text area'),
	);
	$controls = _pg_cf_controls($tree, 0);

	$values = pg_cf_url_prefill_values($controls, $map, array(
		'kind' => 'Announcement', 'board' => 'Elsewhere', 'title' => 'Hello world', 'body' => "one\ntwo",
	));
	pg_assert_false(isset($values['kind']), 'a radio value outside its options');
	pg_assert_same('Elsewhere', isset($values['board']) ? $values['board'] : null, 'a pick list is matched in the bindings');
	pg_assert_same('Hello', isset($values['title']) ? $values['title'] : null, 'the row\'s maxlength');
	pg_assert_same("one\ntwo", isset($values['body']) ? $values['body'] : null, 'a text area keeps its line break');

	$values = pg_cf_url_prefill_values($controls, $map, array('kind' => 'discussion'));
	pg_assert_same(array('kind' => 'Discussion'), $values, 'a radio value in the option\'s spelling');
}

// -- order of sources ------------------------------------------------------------------

function test_cf_url_initial_value_order()
{
	$field = array('id' => 5, 'default_value' => 'Default', 'contact_field' => '', 'use_folder_name_for_default_value' => '0');

	pg_assert_same('From id', _test_cf_url_with_get(array('value_5' => 'From id'), function () use ($field) {
		return pg_cf_field_initial_value($field, null, 0, 'From name');
	}), '?value_<id> first');
	pg_assert_same('From name', _test_cf_url_with_get(array(), function () use ($field) {
		return pg_cf_field_initial_value($field, null, 0, 'From name');
	}), 'the named parameter before the default');
	pg_assert_same('Typed', _test_cf_url_with_get(array('value_5' => 'From id'), function () use ($field) {
		return pg_cf_field_initial_value($field, _test_cf_url_liveform(array(5 => 'Typed')), 0, 'From name');
	}), 'what the visitor typed wins');
	pg_assert_same('Default', _test_cf_url_with_get(array(), function () use ($field) {
		return pg_cf_field_initial_value($field, null, 0, '');
	}), 'no value falls through');
	pg_assert_same('Default', _test_cf_url_with_get(array(), function () use ($field) {
		return pg_cf_field_initial_value($field, null);
	}), 'callers without the argument');
}

// -- the render pass ---------------------------------------------------------------------

// The address parameter is not the field name; every value comes out of the
// renderer's own escaping path.
function test_cf_url_render_pass()
{
	$tree = _test_cf_url_tree(array(
		_test_cf_url_control('input', array('type' => 'text', 'name' => 'subject'), array('url_param' => 'topic'), 'f-subject'),
		_test_cf_url_control('input', array('type' => 'hidden', 'name' => 'post'), array('url_param' => 'post'), 'f-post'),
		_test_cf_url_control('textarea', array('name' => 'details'), array('url_param' => 'details'), 'f-details'),
		_test_cf_url_control('textarea', array('name' => 'body'), array('url_param' => 'body'), 'f-body'),
		array('type' => 'semantic', 'props' => array('tag' => 'fieldset'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'legend', 'text' => 'Kind'), 'children' => array()),
			_test_cf_url_control('input', array('type' => 'radio', 'name' => 'kind', 'value' => 'Question'), array('url_param' => 'kind'), 'f-kind-q'),
			_test_cf_url_control('input', array('type' => 'radio', 'name' => 'kind', 'value' => 'Discussion', 'checked' => ''), null, 'f-kind-d'),
		)),
		_test_cf_url_control('input', array('type' => 'radio', 'name' => 'board', 'value' => 'General', 'checked' => ''), array('url_param' => 'board'), 'f-board-g'),
		_test_cf_url_control('input', array('type' => 'radio', 'name' => 'board', 'value' => 'Help'), null, 'f-board-h'),
	));
	$map = array(
		'subject' => _test_cf_url_row(11, 'subject', 'text box'),
		'post' => _test_cf_url_row(12, 'post', 'text box'),
		'details' => _test_cf_url_row(13, 'details', 'text area'),
		'body' => _test_cf_url_row(14, 'body', 'text area', array('wysiwyg' => '1')),
		'kind' => _test_cf_url_row(15, 'kind', 'radio button'),
		'board' => _test_cf_url_row(16, 'board', 'radio button'),
	);
	$request = array(
		'topic' => 'Billing "Q3"', 'post' => '/topic?r=12', 'details' => "line 1\nline 2",
		'body' => '<script>alert(1)</script><p>ok</p>', 'kind' => 'question', 'board' => 'HELP',
	);

	$values = pg_cf_url_prefill_values(_pg_cf_controls($tree, 0), $map, $request);
	pg_cf_apply_field_bindings($tree, $map, null, 0, false, $values);
	$html = _render_tree_node($tree, 0, 0);

	pg_assert_contains('value="Billing &quot;Q3&quot;"', $html, 'a text box, escaped');
	pg_assert_contains('value="/topic?r=12"', $html, 'a hidden input');
	pg_assert_contains("line 1\nline 2", $html, 'a plain text area keeps its line break');
	pg_assert_false(stripos($html, 'script') !== false, 'a rich text area is filtered');
	pg_assert_contains('&lt;p&gt;ok&lt;/p&gt;', $html, 'a rich text area keeps its markup as text');

	pg_assert_same('', _test_cf_url_attr(_test_cf_url_find($tree, 'f-kind-q'), 'checked'), 'the matching radio in a fieldset');
	pg_assert_same(null, _test_cf_url_attr(_test_cf_url_find($tree, 'f-kind-d'), 'checked'), 'its sibling loses the default');
	pg_assert_same('', _test_cf_url_attr(_test_cf_url_find($tree, 'f-board-h'), 'checked'), 'the matching radio outside a fieldset');
	pg_assert_same(null, _test_cf_url_attr(_test_cf_url_find($tree, 'f-board-g'), 'checked'), 'its sibling loses the default');
}

// -- the `_cf` record ---------------------------------------------------------------------

function test_cf_url_node_record()
{
	$none = _pg_cf_node_record(_test_cf_url_control('input', array()));
	pg_assert_true(array_key_exists('url_param', $none), 'the key is always present');
	pg_assert_same('', $none['url_param']);
	$set = _pg_cf_node_record(_test_cf_url_control('input', array(), array('url_param' => ' topic ')));
	pg_assert_same('topic', $set['url_param'], 'trimmed');
	$array = _pg_cf_node_record(_test_cf_url_control('input', array(), array('url_param' => array('x'))));
	pg_assert_same('', $array['url_param'], 'an array is no name');
}

// Like every `_cf` key, the setting is read from the field's first control.
function test_cf_url_controls_read_the_first_control()
{
	$fieldset = array('type' => 'semantic', 'props' => array('tag' => 'fieldset'), 'children' => array(
		_test_cf_url_control('input', array('type' => 'radio', 'name' => 'kind', 'value' => 'a'), array('url_param' => 'kind')),
		_test_cf_url_control('input', array('type' => 'radio', 'name' => 'kind', 'value' => 'b'), array('url_param' => 'other')),
	));
	$tree = _test_cf_url_tree(array(
		$fieldset,
		_test_cf_url_control('input', array('type' => 'checkbox', 'name' => 'tags', 'value' => 'x')),
		_test_cf_url_control('input', array('type' => 'checkbox', 'name' => 'tags', 'value' => 'y'), array('url_param' => 'tag')),
	));
	$fields = _pg_cf_controls($tree, 0);
	pg_assert_same('kind', $fields['kind']['url_param'], 'a radio group in a fieldset');
	pg_assert_same('', $fields['tags']['url_param'], 'a setting on the second box is not read');
}

// -- where the address is read ---------------------------------------------------------------

function test_cf_url_bindings_match_a_select_against_drawn_options()
{
	$fn = _test_cf_url_php_function(_test_cf_url_source('includes/fn/custom_form.php'), 'pg_cf_apply_field_bindings');
	pg_assert_true($fn !== '', 'pg_cf_apply_field_bindings is found');
	$query = strpos($fn, 'FROM form_field_options');
	$match = strpos($fn, 'pg_cf_match_option($url_value, pg_cf_select_option_values($tree))');
	$initial = strpos($fn, 'pg_cf_field_initial_value($row, $lf, $cf_folder_id, $url_value)');
	pg_assert_true($query !== false && $match !== false && $initial !== false, 'the three steps are found');
	pg_assert_true($query < $match && $match < $initial, 'the options are rebuilt, then matched, then used');
	pg_assert_contains('pg_cf_apply_field_bindings($child, $map, $lf, $cf_folder_id, $staff, $url_values)', $fn);
}

// The renderer passes the request that is empty in an e-mail, the SEO pass,
// the preview and the search indexer, before the bindings rename controls.
function test_cf_url_renderer_reads_the_filter_request()
{
	$fn = _test_cf_url_php_function(_test_cf_url_source('includes/fn/widgets.php'), '_render_system_widget_custom_form');
	pg_assert_true($fn !== '', 'the custom_form renderer is found');
	$read = strpos($fn, 'pg_cf_url_prefill_values(_pg_cf_controls($tree_decoded, $form_page_id), $cf_field_map, pg_sw_filter_request())');
	$bind = strpos($fn, 'pg_cf_apply_field_bindings(');
	pg_assert_true($read !== false && $bind !== false, 'both calls are found');
	pg_assert_true($read < $bind, 'the address is read first');
	pg_assert_contains('$cf_staff, $cf_url_values)', $fn);
}

// -- the editor -------------------------------------------------------------------------------

function test_cf_url_editor_panel()
{
	$js = _test_cf_url_source('assets/js/style_designer.src.js');
	pg_assert_true(_test_cf_url_js_function($js, '_cfFieldLeadOf') !== '', '_cfFieldLeadOf is defined');
	$applies = _test_cf_url_js_function($js, '_cfUrlParamApplies');
	pg_assert_true($applies !== '', '_cfUrlParamApplies is defined');
	pg_assert_contains("_cfNodeAttr(n, 'value')", $applies, 'counts value attributes');
	pg_assert_contains('values.indexOf(v) === -1', $applies, 'each value once');

	$section = _test_cf_url_js_function($js, '_cfFieldSection');
	pg_assert_contains('sd-cf-urlparam', $section);
	pg_assert_contains('_cfFieldLeadOf(', $section);
	pg_assert_contains('_swUrlParamName(', $section);

	$bind = _test_cf_url_js_function($js, '_cfBindFieldSection');
	$from = strpos($bind, "on('sd-cf-urlparam'");
	pg_assert_true($from !== false, 'the handler is found');
	$handler = substr($bind, $from, strpos($bind, "\n        on('", $from + 1) - $from);
	pg_assert_contains('_cfFieldLeadOf(node, w.tree)', $handler);
	pg_assert_contains('rec.url_param = v', $handler);
	pg_assert_contains('delete rec.url_param', $handler);
	pg_assert_false(strpos($js, '_cfRec(lead).url_param') !== false, 'never written on the clicked box');

	pg_assert_contains('url_param: String(rec.url_param', _test_cf_url_js_function($js, '_cfFieldsOf'));
}

// Runs the editor's twins under Node on the trees given; null when Node is
// not installed (the test is skipped). Output that is not JSON fails.
function _test_cf_url_run_js($trees)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = _test_cf_url_source('assets/js/style_designer.src.js');
	$from = strpos($js, "\n    var CF_TYPE_BY_INPUT = {");
	$to = strpos($js, "\n    function _cfTypeLabel(");
	pg_assert_true($from !== false && $to !== false, 'the control helpers are found');
	$script = substr($js, $from, $to - $from);
	foreach (array('_cfNodeText', '_cfControlName', '_cfControls', '_cfFieldLeadOf', '_cfUrlParamApplies', '_cfGroupOf') as $name) {
		$fn = _test_cf_url_js_function($js, $name);
		pg_assert_true($fn !== '', $name . ' is found');
		$script .= $fn;
	}
	$script .= "\nvar trees = " . json_encode($trees) . ";\n"
		. "console.log(JSON.stringify(trees.map(function (t) {\n"
		. "  var list = _cfControls(t), first = list[0].node, last = list[list.length - 1].node;\n"
		. "  var lead = _cfFieldLeadOf(last, t);\n"
		. "  return { applies: _cfUrlParamApplies(lead, _cfGroupOf(lead, t)), leadIsFirst: lead === first };\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgcfurl');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(($printed !== '') && (json_last_error() === JSON_ERROR_NONE), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));
	return $answer;
}

// The panel offers the setting exactly where the server honours it, and
// writes it on the control the server reads.
function test_cf_url_editor_twins()
{
	$box = function ($type, $name, $value = null) {
		$attrs = array('type' => $type, 'name' => $name);
		if ($value !== null) {
			$attrs['value'] = $value;
		}
		return _test_cf_url_control('input', $attrs);
	};
	$cases = array(
		'text' => array(array($box('text', 'f')), 'text box', true),
		'hidden' => array(array($box('hidden', 'f')), 'text box', true),
		'file' => array(array($box('file', 'f')), 'file upload', false),
		'one box' => array(array($box('checkbox', 'f', 'yes')), 'check box', false),
		'one value twice' => array(array($box('checkbox', 'f', 'yes'), $box('checkbox', 'f', 'yes')), 'check box', false),
		'no values' => array(array($box('checkbox', 'f'), $box('checkbox', 'f')), 'check box', false),
		'two values' => array(array($box('checkbox', 'f', 'a'), $box('checkbox', 'f', 'b')), 'check box', true),
		'radios' => array(array($box('radio', 'f', 'a'), $box('radio', 'f', 'b')), 'radio button', true),
		'select' => array(array(_test_cf_url_select('f', array(array('a', 'A')))), 'pick list', true),
	);
	$trees = array();
	foreach ($cases as $label => $case) {
		$children = $case[0];
		$children[0]['props']['_cf'] = array('url_param' => 'pick');
		$trees[] = _test_cf_url_tree($children);
	}
	$answer = _test_cf_url_run_js($trees);

	$i = 0;
	foreach ($cases as $label => $case) {
		$controls = _pg_cf_controls($trees[$i], 0);
		$map = array('f' => _test_cf_url_row(1, 'f', $case[1], array('multiple' => ($case[1] === 'check box') ? '1' : '0')));
		$server = pg_cf_url_prefill_values($controls, $map, array('pick' => ($case[1] === 'pick list') ? 'A' : 'a'));
		if ($case[2]) {
			pg_assert_same(array('f'), array_keys($server), $label . ': the server fills it');
		} else {
			pg_assert_same(array(), $server, $label . ': the server leaves it');
		}
		if (is_array($answer)) {
			pg_assert_same($case[2], isset($answer[$i]['applies']) ? $answer[$i]['applies'] : null, $label . ': the panel agrees');
			pg_assert_same(true, isset($answer[$i]['leadIsFirst']) ? $answer[$i]['leadIsFirst'] : null, $label . ': the first control carries it');
		}
		$i++;
	}
}

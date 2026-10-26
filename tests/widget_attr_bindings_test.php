<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values of a widget's record in an element's attributes
 * (includes/fn/widgets_attrs.php): {{token}} in an Attributes panel entry or
 * a title becomes a plain-character placeholder with a carrier after the
 * element while a widget renders, and the finish pass puts the carrier's
 * value in the placeholder as plain text, escaped once. Outside a widget
 * render the attribute prints as written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _attr_bind_in_scope($kind, $fn, $sid = 41)
{
	$scope = pg_sw_finish_open(array('sid' => $sid, 'kind' => $kind, 'email' => false));
	try {
		return $fn($scope);
	} finally {
		pg_sw_finish_close();
	}
}

// A semantic element of $tag with Attributes panel entries $attrs
// (name => value) and $extra props.
function _attr_bind_el($tag, $attrs, $children = array(), $extra = array())
{
	$list = array();
	foreach ($attrs as $name => $value) $list[] = array('name' => $name, 'value' => $value);
	$props = array('tag' => $tag, 'cssClass' => 'el') + $extra;
	if ($list) $props['_attrs'] = $list;
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

// $tree rendered as a form renderer does inside a widget scope: the record
// template drawn once, filled per record by pg_sw_apply_tokens() (the key
// through $extra, the fields through $custom), swept, then finished.
// $records: id => field name => value. $data_rows: published for lists.
function _attr_bind_finished($tree, $records, $data_rows = null, $kind = 'form_list_view')
{
	return _attr_bind_in_scope($kind, function ($scope) use ($tree, $records, $data_rows) {
		$tpl = trim(_render_tree_node($tree, 0, 0));
		if ($data_rows !== null) pg_sw_list_publish('form_record', $data_rows);
		$html = '';
		foreach ($records as $id => $fields) {
			$custom = array();
			foreach ($fields as $name => $value) $custom[$name] = array('data' => $value, 'type' => 'text box', 'wysiwyg' => 0);
			$html .= pg_sw_hide_empty_fields(pg_sw_sweep_tokens(pg_sw_apply_tokens($tpl, array(), $custom, array('submitted_form_id' => (string)$id))));
		}
		return pg_sw_finish_widget_html($html, $scope);
	});
}

function _attr_bind_clean($html, $label)
{
	pg_assert_same(false, strpos($html, pg_sw_marker_nonce()), $label . ': no nonce left');
	pg_assert_same(false, strpos($html, 'pgav-'), $label . ': no placeholder left');
	pg_assert_same(false, strpos($html, '^^'), $label . ': no token left');
}

function test_widget_attr_bindings_rules()
{
	foreach (array('data-id', 'aria-label', 'title', 'value', 'name', 'data-bs-target', 'alt', 'for', 'xml:lang') as $name) {
		pg_assert_true(pg_sw_attr_name_ok($name), $name . ' takes a value');
	}
	foreach (array('onclick', 'OnMouseOver', 'style', 'class', 'id', 'href', 'src', 'srcset', 'srcdoc', 'action',
		'formaction', 'poster', 'data', 'xlink:href', 'content', 'http-equiv', '', '1x', 'a b', 'x"y') as $name) {
		pg_assert_false(pg_sw_attr_name_ok($name), var_export($name, true) . ' takes none');
	}

	pg_assert_true(pg_sw_attr_token_ok('title'), 'a field');
	pg_assert_true(pg_sw_attr_token_ok('submitted_form_id'), 'the key');
	pg_assert_true(pg_sw_attr_token_ok('__value_param'), 'a list row value');
	foreach (array('', '__description', '__price_formatted', '__comment_message_html', '__breadcrumb_html', '__any_html',
		'__page_link', '__page_link_2', '__link_label_0', '__variant_picker', '__edit_url', '__empty_message') as $token) {
		pg_assert_false(pg_sw_attr_token_ok($token), var_export($token, true) . ' is refused');
	}

	pg_assert_same(array('a', 'b-c', '__d'), pg_sw_attr_tokens('x {{a}} {{b-c}}{{__d}} {{ e }} {{f g}} {{tab:x}}'), 'tokens as written, no spaces');
	pg_assert_same(array(), pg_sw_attr_tokens('plain'), 'none');
}

function test_widget_attr_bindings_outside_a_widget()
{
	$node = _attr_bind_el('span', array('data-x' => '{{title}} & "q"'), array(), array('title' => 'Hi {{title}}'));
	pg_assert_same(false, pg_sw_finish_active(), 'no scope open');
	pg_assert_same(
		'<span class="el" title="Hi {{title}}" data-x="{{title}} &amp; &quot;q&quot;"></span>' . "\n",
		_render_tree_node($node, 0, 0),
		'printed as written'
	);
	pg_assert_same('a &amp; b', pg_sw_attr_value_html('data-x', 'a & b'), 'no token: h()');
	pg_assert_same('', pg_sw_attr_take(0), 'nothing queued');
}

function test_widget_attr_bindings_tree_without_tokens_unchanged()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_attr_bind_el('div', array('data-role' => 'card', 'aria-label' => 'Card'), array(
			array('type' => 'semantic', 'props' => array('tag' => 'span', 'text' => 'x', '_bindings' => array('text' => 'title')), 'children' => array()),
		), array('title' => 'Tip')),
	));
	$outside = _render_tree_node($tree, 0, 0);
	$inside  = _attr_bind_in_scope('form_list_view', function () use ($tree) {
		return _render_tree_node($tree, 0, 0);
	});
	pg_assert_same($outside, $inside, 'byte-identical inside a widget');
}

function test_widget_attr_bindings_record_values()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_attr_bind_el('article', array('data-id' => '{{submitted_form_id}}', 'aria-label' => 'Open {{title}}'), array(
			_attr_bind_el('span', array('data-board' => '{{board}}'), array(), array('text' => 'b')),
		), array('title' => 'About {{title}}')),
	));
	$html = _attr_bind_finished($tree, array(
		7 => array('title' => 'Tom & "Jerry" <b>', 'board' => 'News'),
		9 => array('title' => '  Two   words ', 'board' => ''),
	));
	_attr_bind_clean($html, 'records');
	pg_assert_contains('<article class="el" title="About Tom &amp; &quot;Jerry&quot; &lt;b&gt;" data-id="7" aria-label="Open Tom &amp; &quot;Jerry&quot; &lt;b&gt;">', $html,
		'first record: escaped once, the tag a visitor typed stays text');
	pg_assert_contains('<span class="el" data-board="News">b</span>', $html, 'first record: inner element');
	pg_assert_contains('<article class="el" title="About Two words" data-id="9" aria-label="Open Two words">', $html, 'second record: white space collapsed');
	pg_assert_contains('<span class="el" data-board="">b</span>', $html, 'an empty value');
}

function test_widget_attr_bindings_refused_print_nothing()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_attr_bind_el('button', array('onclick' => 'go({{title}})', 'data-a' => 'x{{__description}}y', 'data-b' => '{{ title }}'), array(), array('text' => 'Go')),
	));
	$html = _attr_bind_finished($tree, array(3 => array('title' => 'T')));
	_attr_bind_clean($html, 'refused');
	pg_assert_contains('onclick="go()"', $html, 'an event handler takes no value');
	pg_assert_contains('data-a="xy"', $html, 'a markup token is refused');
	pg_assert_contains('data-b="{{ title }}"', $html, 'braces with spaces are text');
}

function test_widget_attr_bindings_hide_if_empty_ignores_carriers()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_attr_bind_el('div', array(), array(
			_attr_bind_el('span', array('data-v' => '{{title}}')),
		), array('cssClass' => 'box pg-hide-if-empty')),
	));
	$html = _attr_bind_finished($tree, array(4 => array('title' => 'Visible only in the attribute')));
	_attr_bind_clean($html, 'hide');
	pg_assert_same(false, strpos($html, 'box'), 'a box with no visible content goes');
}

function test_widget_attr_bindings_list_rows()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array('source' => 'form_record.values', 'sourceArg' => 'tags'), 'children' => array(
			_attr_bind_el('span', array('data-tag' => '{{__value_param}}', 'data-rec' => '{{submitted_form_id}}', 'aria-label' => '{{__value_text}} of {{title}}'),
				array(), array('text' => 't')),
		)),
	));
	$rows = array();
	foreach (array(array(5, 'a b'), array(6, 'C&D')) as $i => $v) {
		$rows[] = array('form_id' => '8', 'data' => $v[1], 'data_name' => 'tags', 'data_file_id' => '0', 'field_name' => 'tags',
			'field_label' => 'Tags', 'field_type' => 'check box', 'field_wysiwyg' => '0', 'file_name' => null, 'data_id' => (string)$v[0], 'field_office_use' => '0');
	}
	$html = _attr_bind_finished($tree, array(8 => array('title' => 'Post')), $rows);
	_attr_bind_clean($html, 'list');
	pg_assert_contains('data-tag="a%20b" data-rec="8" aria-label="a b of Post"', $html, 'first row');
	pg_assert_contains('data-tag="C%26D" data-rec="8" aria-label="C&amp;D of Post"', $html, 'second row');
}

function test_widget_attr_bindings_pairs_nested_copies()
{
	$n = pg_sw_marker_nonce();
	$ph = function ($k) use ($n) { return 'pgav-' . $n . '-' . $k . '-'; };
	$cv = function ($k, $v) use ($n) { return '<!--pg-cv:' . $n . ':a' . $k . '-->' . $v . '<!--/pg-cv:' . $n . ':a' . $k . '-->'; };
	$html = '<div data-c="' . $ph(1) . '"><div data-c="' . $ph(1) . '"></div>' . $cv(1, 'inner') . '</div>' . $cv(1, 'outer')
		. '<i data-c="' . $ph(2) . '"></i><b data-c="' . $ph(3) . '"></b>' . $cv(3, '<em>x</em> &amp; y');
	$out = pg_sw_finish_strip_carriers(pg_sw_attr_resolve($html, $n), $n);
	pg_assert_same('<div data-c="outer"><div data-c="inner"></div></div><i data-c=""></i><b data-c="x &amp; y"></b>', $out,
		'each copy reads its own carrier; a placeholder without one reads as empty');
	pg_assert_same('pgav-other-1-', pg_sw_attr_resolve('pgav-other-1-', $n), 'another nonce is text');
}

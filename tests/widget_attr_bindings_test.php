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

// A form list whose record holds a list of the 'tags' values, each a link
// to /board with $params (name => token); the links' page set by hand (no
// database). Returns the finished markup for $records (id => title) and
// the tags $tags (id => list of values).
function _attr_bind_page_links($params, $records, $tags, $wrap_list = null)
{
	$spec = array('page_id' => 12, 'params' => array());
	foreach ($params as $name => $token) $spec['params'][] = array('name' => $name, 'token' => $token);
	$link = array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'tag', 'text' => 't', 'href' => '#',
		'_bindings' => array('href' => '__page_link', 'text' => '__value_text'), '_bindParams' => array('href' => $spec)), 'children' => array());
	$list = array('type' => 'loop_area', 'props' => array('source' => 'form_record.values', 'sourceArg' => 'tags'), 'children' => array($link));
	if ($wrap_list !== null) $list = $wrap_list($list);
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array($list)),
	));

	$links = pg_sw_page_link_bindings($tree);
	foreach ($links as $token => $l) $links[$token]['base'] = '/board';

	$rows = array();
	$id   = 100;
	foreach ($tags as $form_id => $values) {
		foreach ($values as $v) {
			$rows[] = array('form_id' => (string)$form_id, 'data' => $v, 'data_name' => 'tags', 'data_file_id' => '0', 'field_name' => 'tags',
				'field_label' => 'Tags', 'field_type' => 'check box', 'field_wysiwyg' => '0', 'file_name' => null, 'data_id' => (string)(++$id), 'field_office_use' => '0');
		}
	}
	return array($links, $tree, _attr_bind_in_scope('form_list_view', function ($scope) use ($tree, $links, $records, $rows) {
		$tpl = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('form_record', $rows);
		$html = '';
		foreach ($records as $fid => $title) {
			$custom = array('title' => array('data' => $title, 'type' => 'text box', 'wysiwyg' => 0));
			$extra  = array('submitted_form_id' => (string)$fid) + pg_sw_page_link_values($links, array(), $custom, array());
			$html  .= pg_sw_sweep_tokens(pg_sw_apply_tokens($tpl, array(), $custom, $extra));
		}
		return pg_sw_finish_widget_html($html, $scope);
	}));
}

function test_widget_attr_bindings_page_link_row_values()
{
	list($links, $tree, $html) = _attr_bind_page_links(array('tag' => '__value_param', 'author' => 'title', 'label' => '__value_text'),
		array(1 => 'First & co', 2 => 'Second'), array(1 => array('a b', 'C&D'), 2 => array('x=1')));
	pg_assert_same(array(array('name' => 'tag', 'token' => '__value_param', 'row' => true), array('name' => 'author', 'token' => 'title'),
		array('name' => 'label', 'token' => '__value_text', 'row' => true)), $links['__page_link_0']['params'], 'row params marked');
	pg_assert_same(array('__page_link_0'), $tree['children'][0]['children'][0]['props']['_pgRowLinks'], 'the list names its link');
	_attr_bind_clean($html, 'page links');
	pg_assert_same(false, strpos($html, 'pgpl-'), 'no link placeholder left');
	pg_assert_contains('href="/board?tag=a%20b&amp;author=First%20%26%20co&amp;label=a%20b"', $html, 'row 1 of record 1');
	pg_assert_contains('href="/board?tag=C%26D&amp;author=First%20%26%20co&amp;label=C%26D"', $html, 'row 2 of record 1');
	pg_assert_contains('href="/board?tag=x%3D1&amp;author=Second&amp;label=x%3D1"', $html, 'row of record 2');
	pg_assert_same(3, substr_count($html, 'class="tag"'), 'three links');
}

function test_widget_attr_bindings_page_link_without_row()
{
	// A record value only: no placeholder, the address as before.
	list($links, $tree, $html) = _attr_bind_page_links(array('author' => 'title'), array(1 => 'T'), array(1 => array('a')));
	pg_assert_false(pg_sw_page_link_has_row($links['__page_link_0']), 'no row param');
	pg_assert_false(isset($tree['children'][0]['children'][0]['props']['_pgRowLinks']), 'the list names no link');
	pg_assert_contains('href="/board?author=T"', $html, 'record value');

	// A row token outside a list is no row param: it names nothing.
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'a', '_bindings' => array('href' => '__page_link'),
		'_bindParams' => array('href' => array('page_id' => 3, 'params' => array(array('name' => 'tag', 'token' => '__value_param'))))), 'children' => array());
	$links = pg_sw_page_link_bindings($tree);
	pg_assert_false(pg_sw_page_link_has_row($links['__page_link_0']), 'outside a list');

	// Outside a widget render the link is finished at once, row values left out.
	$links = array('__page_link_0' => array('page_id' => 3, 'base' => '/b', 'params' => array(
		array('name' => 'tag', 'token' => '__value_param', 'row' => true), array('name' => 'rec', 'token' => 'submitted_form_id'))));
	pg_assert_same(array('__page_link_0' => '/b?rec=5'), pg_sw_page_link_values($links, array(), array(), array('submitted_form_id' => '5')), 'no scope');
	$links['__page_link_0']['base'] = '';
	pg_assert_same(array('__page_link_0' => '#'), _attr_bind_in_scope('form_list_view', function () use ($links) {
		return pg_sw_page_link_values($links, array(), array(), array());
	}), 'no page: #');
}

function test_widget_attr_bindings_page_link_placeholder()
{
	$n = pg_sw_marker_nonce();
	$ph = _attr_bind_in_scope('form_list_view', function () {
		return pg_sw_page_link_defer('/böard', array(array('tag', '__value_param', ''), array('who', '', 'x "y"')));
	});
	pg_assert_same(1, preg_match('/^pgpl-' . $n . '-[A-Za-z0-9._~]+-$/', $ph), 'plain characters');
	pg_assert_same($ph, h($ph), 'h() leaves it');
	pg_assert_same('/böard?tag=a%2Fb&amp;who=x%20%22y%22', pg_sw_page_link_row_value($ph, $n, array('__value_param' => 'a%2Fb')), 'row value decoded once, encoded once');
	pg_assert_same('/böard?who=x%20%22y%22', pg_sw_page_link_row_value($ph, $n, null), 'no row');
	pg_assert_same('', pg_sw_page_link_row_value($ph, 'other', array()), 'another nonce');
	pg_assert_same('', pg_sw_page_link_row_value('/board?x=1', $n, array()), 'a finished address');
	pg_assert_same('<a href="/böard?who=x%20%22y%22">', pg_sw_page_link_settle('<a href="' . $ph . '">', $n), 'settled without the row');
}

function _attr_bind_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// A `var NAME = [ ... ];` block or a function of style_designer.js, cut out
// at its four-space indent; '' when missing.
function _attr_bind_js_cut($source, $name, $var = false)
{
	$from = strpos($source, $var ? "\n    var " . $name . ' = [' : "\n    function " . $name . '(');
	if ($from === false) return '';
	$to = strpos($source, $var ? "\n    ];\n" : "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// Runs $script after the editor's attribute rules and the page link's row
// tokens; the JSON it prints, or null without Node.
function _attr_bind_run_js($script)
{
	if (!function_exists('exec')) return null;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return null;

	$js  = _attr_bind_js();
	$cut = '';
	foreach (array('SW_COND_DENY', 'SW_ATTR_DENY_NAMES', 'SW_ATTR_MARKUP') as $name) {
		$block = _attr_bind_js_cut($js, $name, true);
		pg_assert_true($block !== '', $name . ' is found');
		$cut .= $block;
	}
	foreach (array('_sdCondTokenAllowed', '_sdAttrBindNameOk', '_sdAttrTokenOk', '_sdListIsSourced', '_sdListsAbove', '_sdPageLinkRowTokens') as $name) {
		$fn = _attr_bind_js_cut($js, $name);
		pg_assert_true($fn !== '', $name . ' is found');
		$cut .= $fn;
	}
	$stubs = "function _sdT(s) { return s; }\n"
		. "var SW_LIST_SOURCES = { 'form_record.values': { rows: [['__value_text', 'Value'], ['__value_param', 'Value for an address']] },"
		. " 'form_record.files': { rows: [['__file_name', 'File Name'], ['__file_url', 'File address']] } };\n"
		. "var PARENTS = new Map();\n"
		. "function _sdAnyParent(n) { var p = PARENTS.get(n); return p ? { parent: p } : null; }\n";

	$file = @tempnam(sys_get_temp_dir(), 'pgattr');
	if ($file === false) return null;
	file_put_contents($file, $stubs . $cut . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));
	return $answer;
}

// The editor's lists are the server's.
function test_widget_attr_bindings_editor_lists_match_the_server()
{
	$js = _attr_bind_js();
	preg_match_all("/'([a-z:-]+)'/", _attr_bind_js_cut($js, 'SW_ATTR_DENY_NAMES', true), $m);
	pg_assert_same(pg_sw_attr_denied_names(), $m[1], 'refused attributes');
	preg_match_all("/'(__[a-z0-9_]+)'/", _attr_bind_js_cut($js, 'SW_ATTR_MARKUP', true), $m);
	pg_assert_same(array_keys(pg_sw_raw_token_keys()), $m[1], 'markup tokens');
	pg_assert_true(!preg_match('/var SW_ATTR[A-Z_]*TOKEN/', $js), 'no SW_ATTR*TOKEN* name (check_bindings reads those as palettes)');
}

// The editor's rules answer as the server's on the same names and tokens,
// and a page link offers the nearest list's row values.
function test_widget_attr_bindings_editor_rules()
{
	$names  = array('data-id', 'aria-label', 'title', 'value', 'xml:lang', 'onclick', 'OnLoad', 'style', 'HREF', 'srcdoc', 'content', '', '1x', 'a b');
	$tokens = array('title', '__value_param', '__description', '__PRICE_FORMATTED', '__comment_stars_html', '__page_link_3', '__edit_url', '');
	$answer = _attr_bind_run_js(
		'var list = { type: "loop_area", props: { source: "form_record.files" } };'
		. 'var inner = { type: "loop_area", props: { source: "form_record.values" } };'
		. 'var a = { type: "semantic", props: {} }, b = { type: "semantic", props: {} };'
		. 'var main = { type: "loop_area", props: {} };'
		. 'PARENTS.set(a, inner); PARENTS.set(inner, list); PARENTS.set(list, main); PARENTS.set(b, main);'
		. 'console.log(JSON.stringify({ names: ' . json_encode($names) . '.map(_sdAttrBindNameOk), tokens: ' . json_encode($tokens) . '.map(_sdAttrTokenOk),'
		. ' nearest: _sdPageLinkRowTokens(a).map(function (r) { return r[0]; }), none: _sdPageLinkRowTokens(b) }));'
	);
	if ($answer === null) return;
	pg_assert_same(array_map('pg_sw_attr_name_ok', $names), $answer['names'], 'attribute names');
	pg_assert_same(array_map('pg_sw_attr_token_ok', $tokens), $answer['tokens'], 'tokens');
	pg_assert_same(array('__value_text', '__value_param'), $answer['nearest'], 'the nearest list');
	pg_assert_same(array(), $answer['none'], 'outside a list');
}

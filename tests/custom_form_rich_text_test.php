<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for rich-text text areas on designed forms
 * (includes/fn/custom_form.php): the three-state `_cf.wysiwyg` record
 * (_pg_cf_node_record(), _pg_cf_controls()) and the column it writes
 * (pg_cf_rich_text_flag()); the render marker, `required` moved to
 * aria-required and the filtered pre-fill (pg_cf_apply_field_bindings());
 * the ids the page loads the editor for (pg_cf_rich_text_ids()); sample
 * values stored as HTML (pg_cf_seed_rich_value()); a rich value printed by a
 * widget (pg_sw_apply_tokens()); the rich-text filter itself
 * (pg_sanitize_rich_text()); and where get_page_content.php and the visual
 * editor wire it up.
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
function _test_cf_rich_control($tag, $attrs, $cf = null, $id = '')
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
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function _test_cf_rich_tree($children)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => $children);
}

// The field row pg_cf_field_map() would hand the render walker.
function _test_cf_rich_row($id, $name, $wysiwyg)
{
	return array(
		'id' => $id, 'name' => $name, 'type' => 'text area', 'wysiwyg' => $wysiwyg ? '1' : '0',
		'multiple' => '0', 'office_use_only' => '0', 'contact_field' => '',
		'use_folder_name_for_default_value' => '0', 'default_value' => '', 'validation_message' => '',
	);
}

// Renders a tree after the binding pass, with ?value_<id> set while it runs.
function _test_cf_rich_render($tree, $map, $get = array())
{
	$saved = $_GET;
	foreach ($get as $key => $value) {
		$_GET[$key] = $value;
	}
	try {
		pg_cf_apply_field_bindings($tree, $map, null, 0, false);
		return _render_tree_node($tree, 0, 0);
	} finally {
		$_GET = $saved;
	}
}

// The control says true, false or nothing; only a text area can be rich.
function test_custom_form_rich_text_controls_three_state()
{
	$tree = _test_cf_rich_tree(array(
		_test_cf_rich_control('textarea', array('name' => 'body'), array('wysiwyg' => true)),
		_test_cf_rich_control('textarea', array('name' => 'notes'), array('wysiwyg' => false)),
		_test_cf_rich_control('textarea', array('name' => 'other')),
		_test_cf_rich_control('input', array('type' => 'text', 'name' => 'title'), array('wysiwyg' => true)),
	));
	$fields = _pg_cf_controls($tree, 0);
	pg_assert_same(true, $fields['body']['wysiwyg'], 'switch on');
	pg_assert_same(false, $fields['notes']['wysiwyg'], 'switch off');
	pg_assert_same(null, $fields['other']['wysiwyg'], 'no key: the row keeps its flag');
	pg_assert_same(false, $fields['title']['wysiwyg'], 'a text box is never rich');
}

function test_custom_form_rich_text_node_record_key()
{
	$on = _pg_cf_node_record(_test_cf_rich_control('textarea', array(), array('wysiwyg' => true)));
	$off = _pg_cf_node_record(_test_cf_rich_control('textarea', array(), array('wysiwyg' => 0)));
	$none = _pg_cf_node_record(_test_cf_rich_control('textarea', array()));
	pg_assert_same(true, $on['wysiwyg']);
	pg_assert_same(false, $off['wysiwyg']);
	pg_assert_true(array_key_exists('wysiwyg', $none), 'the key is always present');
	pg_assert_same(null, $none['wysiwyg']);
}

// Once the switch is used the control decides; before that the stored
// text-area row keeps its flag.
function test_custom_form_rich_text_flag()
{
	$rich_row = array('type' => 'text area', 'wysiwyg' => '1');
	pg_assert_same(1, pg_cf_rich_text_flag(true, 'text area'));
	pg_assert_same(0, pg_cf_rich_text_flag(false, 'text area', $rich_row), 'the switch turns it off');
	pg_assert_same(1, pg_cf_rich_text_flag(null, 'text area', $rich_row), 'kept from the classic screen');
	pg_assert_same(0, pg_cf_rich_text_flag(null, 'text area', null), 'a new field');
	pg_assert_same(0, pg_cf_rich_text_flag(null, 'text area', array('type' => 'text area', 'wysiwyg' => '0')));
	pg_assert_same(0, pg_cf_rich_text_flag(null, 'text area', array('type' => 'text box', 'wysiwyg' => '1')), 'was not a text area');
	pg_assert_same(0, pg_cf_rich_text_flag(true, 'text box'), 'only a text area');
}

// A rich text area is marked, posts under its row id and leaves the
// required check to the server.
function test_custom_form_rich_text_binding_marks_control()
{
	$tree = _test_cf_rich_tree(array(
		_test_cf_rich_control('textarea', array('name' => 'body', 'required' => '', 'rows' => '12'), null, 'nw-body'),
	));
	$html = _test_cf_rich_render($tree, array('body' => _test_cf_rich_row(19414, 'body', true)));
	pg_assert_contains('data-pg-rich-text', $html);
	pg_assert_contains('aria-required="true"', $html);
	pg_assert_contains('name="19414"', $html);
	pg_assert_contains('id="nw-body"', $html);
	pg_assert_false(strpos($html, 'required=""') !== false, 'no browser required check on the hidden control');
}

// A pre-fill link reaches the editor filtered; markup the editor makes stays.
function test_custom_form_rich_text_binding_filters_prefill()
{
	$tree = _test_cf_rich_tree(array(
		_test_cf_rich_control('textarea', array('name' => 'body'), null, 'nw-body'),
	));
	$html = _test_cf_rich_render($tree, array('body' => _test_cf_rich_row(19414, 'body', true)),
		array('value_19414' => '<img src=x onerror=alert(1)><p>ok</p>'));
	pg_assert_false(stripos($html, 'onerror') !== false, 'event handler removed');
	pg_assert_contains('&lt;p&gt;ok&lt;/p&gt;', $html);
}

function test_custom_form_rich_text_binding_plain_unchanged()
{
	$tree = _test_cf_rich_tree(array(
		_test_cf_rich_control('textarea', array('name' => 'body', 'required' => ''), null, 'nw-body'),
	));
	$html = _test_cf_rich_render($tree, array('body' => _test_cf_rich_row(19414, 'body', false)),
		array('value_19414' => '<b>x</b> onerror'));
	pg_assert_false(strpos($html, 'data-pg-rich-text') !== false, 'not marked');
	pg_assert_contains('required=""', $html);
	pg_assert_contains('&lt;b&gt;x&lt;/b&gt; onerror', $html, 'plain text kept as typed');
}

// Only marked text areas with an id a script can carry and no other element
// of the page carries, in order.
function test_custom_form_rich_text_ids()
{
	$html = '<form><textarea id="a0" data-pg-rich-text="">x</textarea>'
		. '<label for="a0">A</label><div data-id="a0"></div>'
		. '<textarea id="plain">y</textarea>'
		. '<textarea data-pg-rich-text id="a&amp;b"></textarea>'
		. '<textarea data-pg-rich-text="" id="x&quot;);alert(1);//"></textarea>'
		. '<textarea data-pg-rich-text="" id="9lives"></textarea>'
		. '<textarea data-id="z" data-pg-rich-text=""></textarea>'
		. '<textarea id="a1" data-pg-rich-text=""></textarea>'
		. '<textarea id="a1" data-pg-rich-text=""></textarea></form>'
		. '<form><textarea class="form-control" id="cf_12" data-pg-rich-text=""></textarea>'
		. '<input id="CF_12"></form>';
	pg_assert_same(array('a0', 'cf_12'), pg_cf_rich_text_ids($html), 'the same rich widget twice: neither');
	// A plain control that shares the id would get the editor, with a
	// starting value that was never filtered.
	pg_assert_same(array(), pg_cf_rich_text_ids('<textarea id="post" name="1">x</textarea>'
		. '<textarea id="post" name="2" data-pg-rich-text=""></textarea>'), 'a plain control first');
	pg_assert_same(array(), pg_cf_rich_text_ids('<textarea id="post" data-pg-rich-text=""></textarea>'
		. "<div ID='post'></div>"), 'single-quoted');
	pg_assert_same(array(), pg_cf_rich_text_ids('<textarea id="post" data-pg-rich-text=""></textarea>'
		. '<span id=post>'), 'unquoted');
	pg_assert_same(array(), pg_cf_rich_text_ids('<textarea id="a"></textarea>'));
	pg_assert_same(array(), pg_cf_rich_text_ids('<div data-pg-rich-text id="d"></div>'), 'only a text area');
}

// Plain sample text becomes paragraphs and line breaks; markup is filtered.
function test_custom_form_rich_text_seed_value()
{
	pg_assert_same("<p>A<br>\nB</p><p>C &lt;b&gt;</p>", pg_cf_seed_rich_value("A\r\nB\n\nC <b>"));
	pg_assert_same('<p>x</p>', pg_cf_seed_rich_value('<p>x</p><script>y</script>'));
	pg_assert_same('', pg_cf_seed_rich_value(''));
	pg_assert_same('', pg_cf_seed_rich_value("  \n "));
}

// A rich value is printed filtered with PHP tags defused; a plain one escaped.
function test_custom_form_rich_text_tokens()
{
	$custom = array(
		'body'  => array('data' => '<p>a</p><img src=x onerror=alert(1)><p title="<?php x ?>">t</p>', 'type' => 'text area', 'wysiwyg' => 1),
		'plain' => array('data' => "One\nTwo <b>x</b>", 'type' => 'text area', 'wysiwyg' => 0),
	);
	$html = pg_sw_apply_tokens('<div>^^body^^</div><div>^^plain^^</div>', array(), $custom);
	pg_assert_contains('<p>a</p>', $html);
	pg_assert_false(stripos($html, 'onerror') !== false, 'event handler removed');
	pg_assert_false(strpos($html, '<?') !== false, 'no PHP open tag');
	pg_assert_contains("One<br />\nTwo &lt;b&gt;x&lt;/b&gt;", $html);
}

// The filter every rich value passes, on input and on output.
function test_custom_form_rich_text_sanitizer_strips()
{
	$out = pg_sanitize_rich_text(
		'<script>a()</script><iframe src="https://e.x"></iframe><svg><g/></svg><form><input></form>'
		. '<p onclick="b()">p</p><a href="jav&#x09;ascript:c()">j</a><img src="data:image/png;base64,AA">'
		. '<span style="background:url(javascript:d())">s</span><?php system(1); ?>');
	foreach (array('<script', '<iframe', '<svg', '<form', '<input', 'onclick', 'javascript', 'data:', 'url(', '<?') as $bad) {
		pg_assert_false(stripos($out, $bad) !== false, $bad);
	}
	pg_assert_contains('<p>p</p>', $out);
}

function test_custom_form_rich_text_sanitizer_keeps()
{
	$in = '<h2>T</h2><p><strong>b</strong> <a href="https://example.com/a">x</a> <a href="/b">y</a>'
		. ' <a href="{path}c">z</a></p><ul><li>i</li></ul>';
	$out = pg_sanitize_rich_text($in);
	pg_assert_contains('<h2>T</h2>', $out);
	pg_assert_contains('<strong>b</strong>', $out);
	pg_assert_contains('href="https://example.com/a"', $out);
	pg_assert_contains('href="/b"', $out);
	pg_assert_contains('href="{path}c"', $out);
	pg_assert_contains('<ul><li>i</li></ul>', $out);
}

// The page brings the editor only for a live render.
function test_custom_form_rich_text_page_injection_gated()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_same(1, preg_match('~if \(([^{]*)\{\s*\$content = pg_cf_rich_text_inject\(\$content\);~', $source, $m), 'injector call');
	$condition = isset($m[1]) ? $m[1] : '';
	pg_assert_contains('!$email', $condition);
	pg_assert_contains('!pg_seo_rendering()', $condition);
	pg_assert_contains('UPDATE_SEARCH_INDEX', $condition);
}

// The editor's switch writes the record, and a form adopted from its rows
// keeps the flag the rows hold (booleans or '1').
function test_custom_form_rich_text_designer_switch()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_same(1, preg_match("~on\\('sd-cf-rich', 'change', function \\(el\\) \\{\\s*_cfRec\\(lead\\)\\.wysiwyg = !!el\\.checked;~", $source), 'switch handler');
	pg_assert_contains("if (kind === 'textarea') rec.wysiwyg = _cfFlagOn(f.wysiwyg);", $source);
	pg_assert_contains("return v === true || String(v) === '1';", $source);
}

// Both places that print a stored rich value raw defuse PHP open tags. libxml
// already escapes `<?` inside attributes, so this guards the next parser.
function test_custom_form_rich_text_raw_prints_defused()
{
	$widgets = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains('$data = pg_defuse_php_tags(pg_sanitize_rich_text($data));', $widgets, 'pg_sw_apply_tokens()');
	$catalog = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_contains('prepare_form_data_for_output(pg_defuse_php_tags(pg_sanitize_rich_text($data)), $type, false)', $catalog, 'order form values');
}

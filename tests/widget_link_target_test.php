<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a link's window kept in its custom attributes: the server's link
 * and button renderers read target (the button also rel) from the node's
 * props and skip the _attrs entry, while the editor's canvas and exporter
 * print the _attrs entry. pg_sw_link_attrs_to_props() moves such an entry
 * into the prop before the order view widget renders, and the order view's
 * starter tree gives its links the prop itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A content link the way the starter tree wrote the invoice and tracking
// links: the window and rel in _attrs, no target prop.
function _test_widget_link_target_link($extra = array())
{
	return array(
		'type' => 'content',
		'props' => array_merge(array(
			'contentType' => 'link',
			'text' => 'Print Invoice',
			'href' => '/order_invoice_print.php?order_id=7',
			'cssClass' => 'btn btn-outline-secondary',
			'_attrs' => array(
				array('name' => 'target', 'value' => '_blank'),
				array('name' => 'rel', 'value' => 'noopener'),
			),
		), $extra),
		'children' => array(),
	);
}

// A Button component drawn as a link, its window and rel in _attrs.
function _test_widget_link_target_button($extra = array())
{
	return array(
		'type' => 'component',
		'props' => array_merge(array(
			'componentType' => 'btn',
			'text' => 'Track',
			'href' => 'https://example.com/track/1',
			'_attrs' => array(
				array('name' => 'target', 'value' => '_blank'),
				array('name' => 'rel', 'value' => 'noopener'),
			),
		), $extra),
		'children' => array(),
	);
}

// The source of one function in a file, up to the next top-level function.
function _test_widget_link_target_function_source($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$next = preg_match('/\nfunction [a-z_]+\(/', $source, $match, PREG_OFFSET_CAPTURE, $from + 10);

	return $next ? substr($source, $from, $match[0][1] - $from) : substr($source, $from);
}

// -- the helper ----------------------------------------------------------------

// The link's _attrs window becomes its prop, and the page's link opens in a
// new window with one target and one rel.
function test_widget_link_target_link_opens_new_window()
{
	$node = _test_widget_link_target_link();
	pg_sw_link_attrs_to_props($node);
	pg_assert_same('_blank', $node['props']['target'], 'the prop');

	$html = _render_tree_node($node, 0);
	pg_assert_contains('target="_blank"', $html, 'the page link');
	pg_assert_same(1, substr_count($html, 'target='), 'one target');
	pg_assert_same(1, substr_count($html, 'rel="noopener"'), 'one rel');
}

// The link renderer itself skips the _attrs window, which is why the widget
// moves it. A renderer that reads it makes the helper unnecessary.
function test_widget_link_target_renderer_skips_attrs_target()
{
	$html = _render_tree_node(_test_widget_link_target_link(), 0);
	pg_assert_same(0, substr_count($html, 'target='), 'no target');
}

// A target prop the designer chose stays; the _attrs entry does not win.
function test_widget_link_target_prop_wins()
{
	$node = _test_widget_link_target_link(array('target' => '_self'));
	pg_sw_link_attrs_to_props($node);
	pg_assert_same('_self', $node['props']['target']);

	$html = _render_tree_node($node, 0);
	pg_assert_contains('target="_self"', $html);
	pg_assert_same(1, substr_count($html, 'target='), 'one target');
}

// The attribute's name is read the way the renderer skips it, in any case.
// An empty value is no window.
function test_widget_link_target_name_case_and_empty_value()
{
	$node = _test_widget_link_target_link(array('_attrs' => array(array('name' => 'TARGET', 'value' => '_blank'))));
	pg_sw_link_attrs_to_props($node);
	pg_assert_same('_blank', $node['props']['target'], 'capitals');

	$node = _test_widget_link_target_link(array('_attrs' => array(array('name' => 'target', 'value' => ''))));
	pg_sw_link_attrs_to_props($node);
	pg_assert_false(isset($node['props']['target']) && $node['props']['target'] !== '', 'empty value');
}

// A link's rel already prints from _attrs; only the window moves.
function test_widget_link_target_link_rel_stays_in_attrs()
{
	$node = _test_widget_link_target_link();
	pg_sw_link_attrs_to_props($node);
	pg_assert_false(isset($node['props']['rel']), 'no rel prop');
}

// A Button component drawn as a link skips both target and rel in _attrs:
// both move, and the page's link carries each once.
function test_widget_link_target_button_link()
{
	$node = _test_widget_link_target_button();
	pg_sw_link_attrs_to_props($node);
	pg_assert_same('_blank', $node['props']['target'], 'target');
	pg_assert_same('noopener', $node['props']['rel'], 'rel');

	$html = _render_tree_node($node, 0);
	pg_assert_same(1, substr_count($html, 'target="_blank"'), 'one target');
	pg_assert_same(1, substr_count($html, 'rel="noopener"'), 'one rel');
}

// A Button component drawn as a <button> opens nothing; it is left alone.
function test_widget_link_target_button_element_untouched()
{
	$node = _test_widget_link_target_button(array('btnElement' => 'button'));
	$before = $node;
	pg_sw_link_attrs_to_props($node);
	pg_assert_same($before, $node);
}

// Other nodes are left alone, the semantic <a> among them: its renderer
// prints the _attrs window itself.
function test_widget_link_target_other_nodes_untouched()
{
	$semantic = array('type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Contract',
		'_attrs' => array(array('name' => 'target', 'value' => '_blank'))), 'children' => array());
	$before = $semantic;
	pg_sw_link_attrs_to_props($semantic);
	pg_assert_same($before, $semantic, 'semantic a');
	pg_assert_contains('target="_blank"', _render_tree_node($semantic, 0), 'semantic a prints it');

	$paragraph = array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'x',
		'_attrs' => array(array('name' => 'target', 'value' => '_blank'))), 'children' => array());
	$before = $paragraph;
	pg_sw_link_attrs_to_props($paragraph);
	pg_assert_same($before, $paragraph, 'paragraph');
}

// Every link of the tree is reached, inside cards and the item loop.
function test_widget_link_target_walks_the_tree()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array(
			_test_widget_link_target_link(),
		)),
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			_test_widget_link_target_button(),
		)),
	));
	pg_sw_link_attrs_to_props($tree);
	pg_assert_same('_blank', $tree['children'][0]['children'][0]['props']['target'], 'in a card');
	pg_assert_same('_blank', $tree['children'][1]['children'][0]['props']['target'], 'in the loop');
}

// Anything that is not a node is left as it is.
function test_widget_link_target_not_a_node()
{
	$value = 'x';
	pg_sw_link_attrs_to_props($value);
	pg_assert_same('x', $value);

	$node = array('type' => 'content', 'props' => array('contentType' => 'link', '_attrs' => 'target'), 'children' => 'none');
	$before = $node;
	pg_sw_link_attrs_to_props($node);
	pg_assert_same($before, $node);
}

// -- the order view widget -----------------------------------------------------

// The widget moves the windows before anything renders: the not-found
// closure takes the tree when it is defined.
function test_widget_link_target_order_view_calls_helper_first()
{
	$section = _test_widget_link_target_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php'), '_render_system_widget_order_view');
	$call = strpos($section, 'pg_sw_link_attrs_to_props($tree_decoded);');
	$closure = strpos($section, '$render_not_found = function');

	pg_assert_true($call !== false, 'the call');
	pg_assert_true($closure !== false, 'the not-found closure');
	pg_assert_true($call !== false && $closure !== false && $call < $closure, 'before the closure');
}

// The starter tree's links carry their window as a prop, so the editor's
// Target box shows it and the server prints it without the helper.
function test_widget_link_target_order_view_starter_tree()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, "case 'order_view': {");
	$to = ($from !== false) ? strpos($source, "case 'express_order': {", $from) : false;
	pg_assert_true($from !== false && $to !== false, 'the starter tree is found');

	$tree = ($from !== false && $to !== false) ? substr($source, $from, $to - $from) : '';
	pg_assert_same(0, substr_count($tree, "name: 'target'"), 'no window in _attrs');

	foreach (array('__order_invoice_pdf_url', '__tracking_link', '__legal_preinfo_url', '__legal_contract_url') as $token) {
		$at = strpos($tree, "href: '" . $token . "'");
		$open = ($at !== false) ? strrpos(substr($tree, 0, $at), "createNode('content', { contentType: 'link'") : false;
		$node = ($open !== false) ? substr($tree, $open, $at - $open) : '';
		pg_assert_contains("target: '_blank'", $node, $token);
	}
}

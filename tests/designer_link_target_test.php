<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a link's window and relationship on the page (_render_tree_node()):
 * the content link and the Button component drawn as a link print `target`
 * and `rel` from their props, and from _attrs when the prop is empty, the way
 * the editor's canvas prints them; a prop wins and the attribute is printed
 * once. The starter trees and the shipped design templates give their links
 * the window as a prop, so the designer's Target option shows it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A content link with the given props over a plain one.
function _test_designer_link_target_link($props = array())
{
	return array(
		'type' => 'content',
		'props' => array_merge(array(
			'contentType' => 'link',
			'text' => 'PDF',
			'href' => '/x',
			'cssClass' => 'btn',
		), $props),
		'children' => array(),
	);
}

// A Button component with the given props over a plain one (drawn as <a>).
function _test_designer_link_target_button($props = array())
{
	return array(
		'type' => 'component',
		'props' => array_merge(array(
			'componentType' => 'btn',
			'text' => 'Go',
			'href' => '/y',
		), $props),
		'children' => array(),
	);
}

// The attributes of the first tag of some markup, name => list of values.
function _test_designer_link_target_attrs($html)
{
	$out = array();

	if (!preg_match('~<[a-z]+\b([^>]*)>~i', $html, $tag)) {
		return $out;
	}

	preg_match_all('~([a-z-]+)="([^"]*)"~i', $tag[1], $m, PREG_SET_ORDER);

	foreach ($m as $a) {
		$out[strtolower($a[1])][] = $a[2];
	}

	return $out;
}

// The source between two markers of a file, '' when either is missing.
function _test_designer_link_target_between($source, $from, $to)
{
	$at = strpos($source, $from);
	$end = ($at !== false) ? strpos($source, $to, $at + strlen($from)) : false;

	return ($at !== false && $end !== false) ? substr($source, $at, $end - $at) : '';
}

// -- the content link ----------------------------------------------------------

// A window kept in _attrs reaches the page: the canvas prints it too.
function test_designer_link_target_link_attrs_target()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_link(array(
		'_attrs' => array(array('name' => 'target', 'value' => '_blank'), array('name' => 'rel', 'value' => 'noopener')),
	)), 0));

	pg_assert_same(array('_blank'), isset($attrs['target']) ? $attrs['target'] : array(), 'target');
	pg_assert_same(array('noopener'), isset($attrs['rel']) ? $attrs['rel'] : array(), 'rel');
}

// The prop the Target option writes wins over an _attrs entry; one target.
function test_designer_link_target_link_prop_wins()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_link(array(
		'target' => '_self',
		'_attrs' => array(array('name' => 'TARGET', 'value' => '_blank')),
	)), 0));

	pg_assert_same(array('_self'), isset($attrs['target']) ? $attrs['target'] : array());
}

// The Relationship option's prop reaches the page; it was not printed at all.
function test_designer_link_target_link_rel_prop()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_link(array(
		'target' => '_blank',
		'rel' => 'nofollow',
	)), 0));

	pg_assert_same(array('_blank'), isset($attrs['target']) ? $attrs['target'] : array(), 'target');
	pg_assert_same(array('nofollow'), isset($attrs['rel']) ? $attrs['rel'] : array(), 'rel');
}

// A rel prop wins over the _attrs rel; one rel.
function test_designer_link_target_link_rel_prop_wins()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_link(array(
		'rel' => 'nofollow',
		'_attrs' => array(array('name' => 'rel', 'value' => 'noopener')),
	)), 0));

	pg_assert_same(array('nofollow'), isset($attrs['rel']) ? $attrs['rel'] : array());
}

// A link with nothing in its props draws exactly as before: the starter
// trees' and templates' rel in _attrs keeps its place, so a page body drawn
// again comes out the same.
function test_designer_link_target_link_unchanged_markup()
{
	$node = _test_designer_link_target_link(array('_attrs' => array(array('name' => 'rel', 'value' => 'noopener'))));
	pg_assert_same('<a href="/x" class="btn" rel="noopener">PDF</a>' . "\n", _render_tree_node($node, 0), 'plain');

	$node['props']['inlineStyle'] = 'color:red';
	pg_assert_same('<a href="/x" class="btn" style="color:red" rel="noopener">PDF</a>' . "\n", _render_tree_node($node, 0), 'styled');
}

// Empty props are no window and no relationship.
function test_designer_link_target_link_empty_props()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_link(array('target' => '', 'rel' => '')), 0));

	pg_assert_false(isset($attrs['target']), 'target');
	pg_assert_false(isset($attrs['rel']), 'rel');
}

// -- the Button component --------------------------------------------------------

// Drawn as a link, the button prints the _attrs window and rel.
function test_designer_link_target_button_attrs()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_button(array(
		'_attrs' => array(array('name' => 'target', 'value' => '_blank'), array('name' => 'rel', 'value' => 'noopener')),
	)), 0));

	pg_assert_same(array('_blank'), isset($attrs['target']) ? $attrs['target'] : array(), 'target');
	pg_assert_same(array('noopener'), isset($attrs['rel']) ? $attrs['rel'] : array(), 'rel');
}

// Its props win; each attribute once.
function test_designer_link_target_button_props_win()
{
	$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_button(array(
		'target' => '_self',
		'rel' => 'nofollow',
		'_attrs' => array(array('name' => 'target', 'value' => '_blank'), array('name' => 'rel', 'value' => 'noopener')),
	)), 0));

	pg_assert_same(array('_self'), isset($attrs['target']) ? $attrs['target'] : array(), 'target');
	pg_assert_same(array('nofollow'), isset($attrs['rel']) ? $attrs['rel'] : array(), 'rel');
}

// A button drawn as <button> or <input> opens nothing: no window, no rel.
function test_designer_link_target_button_element_has_no_window()
{
	foreach (array('button', 'input') as $element) {
		$attrs = _test_designer_link_target_attrs(_render_tree_node(_test_designer_link_target_button(array(
			'btnElement' => $element,
			'_attrs' => array(array('name' => 'target', 'value' => '_blank'), array('name' => 'rel', 'value' => 'noopener')),
		)), 0));

		pg_assert_false(isset($attrs['target']), $element . ' target');
		pg_assert_false(isset($attrs['rel']), $element . ' rel');
	}
}

// -- the semantic <a> --------------------------------------------------------------

// The semantic <a> already printed the _attrs window; it still does, once.
function test_designer_link_target_semantic_a_unchanged()
{
	$node = array('type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Contract', 'href' => '/c',
		'_attrs' => array(array('name' => 'target', 'value' => '_blank'), array('name' => 'rel', 'value' => 'noopener'))), 'children' => array());
	$attrs = _test_designer_link_target_attrs(_render_tree_node($node, 0));

	pg_assert_same(array('_blank'), isset($attrs['target']) ? $attrs['target'] : array(), 'target');
	pg_assert_same(array('noopener'), isset($attrs['rel']) ? $attrs['rel'] : array(), 'rel');
}

// -- the order view widget -----------------------------------------------------------

// The widget leaves the window to the renderer: no pass of its own moves it.
function test_designer_link_target_order_view_has_no_pass_of_its_own()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_same(0, substr_count($source, 'pg_sw_link_attrs_to_props'));
}

// -- starter trees and shipped templates -----------------------------------------------

// The starter trees whose links open in a new window carry it as a prop, so
// the editor's Target option shows it.
function test_designer_link_target_starter_trees()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	$trees = array(
		'order_view' => array("case 'order_view': {", "case 'express_order': {", array('__order_invoice_pdf_url', '__tracking_link', '__legal_preinfo_url', '__legal_contract_url', '__legal_kvkk_url')),
		'account_invoices' => array("case 'account_invoices': {", "\n            case '", array('__invoice_pdf_url')),
	);

	foreach ($trees as $kind => $spec) {
		$tree = _test_designer_link_target_between($source, $spec[0], $spec[1]);
		pg_assert_true($tree !== '', $kind . ' found');
		pg_assert_same(0, substr_count($tree, "name: 'target'"), $kind . ' no window in _attrs');

		foreach ($spec[2] as $token) {
			$at = strpos($tree, "href: '" . $token . "'");
			$open = ($at !== false) ? strrpos(substr($tree, 0, $at), "createNode('content', { contentType: 'link'") : false;
			$node = ($open !== false) ? substr($tree, $open, $at - $open) : '';
			pg_assert_contains("target: '_blank'", $node, $kind . ' ' . $token);
		}
	}
}

// No shipped design template keeps a link's window in _attrs: every link and
// button link of its pages, widgets and shared components has it as a prop.
function test_designer_link_target_shipped_templates()
{
	$files = glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php');
	pg_assert_true(is_array($files) && count($files) > 0, 'templates found');

	$found = array();
	$walk = function ($value, $where) use (&$walk, &$found) {
		if (!is_array($value)) {
			return;
		}

		if (isset($value['type'], $value['props']) && is_array($value['props']) && !empty($value['props']['_attrs']) && is_array($value['props']['_attrs'])) {
			$props = $value['props'];
			$link = ($value['type'] === 'content') && (isset($props['contentType']) ? $props['contentType'] : '') === 'link';
			$button = ($value['type'] === 'component') && (isset($props['componentType']) ? $props['componentType'] : '') === 'btn'
				&& !in_array(isset($props['btnElement']) ? $props['btnElement'] : 'a', array('button', 'input'), true);

			foreach ($props['_attrs'] as $a) {
				if (($link || $button) && is_array($a) && strtolower((string) (isset($a['name']) ? $a['name'] : '')) === 'target') {
					$found[] = $where . ': ' . (isset($props['text']) ? $props['text'] : '');
				}
			}
		}

		foreach ($value as $child) {
			$walk($child, $where);
		}
	};

	foreach ($files ? $files : array() as $file) {
		$walk(_pg_tpl_include($file), basename($file, '.php'));
	}

	pg_assert_same(array(), $found);
}

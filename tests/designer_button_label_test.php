<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where a link or button prints its label beside the elements put
 * into it (_render_tree_node()): after them by default, before them with
 * textPosition 'start', the way the editor's canvas and exporter place it;
 * other elements keep their text first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A semantic element with a label and one span inside it.
function _test_button_label_node($tag, $extra = array())
{
	return array('type' => 'semantic', 'props' => array_merge(array('tag' => $tag, 'cssClass' => '', 'text' => 'Release notes'), $extra), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'span', 'cssClass' => 'badge', 'text' => '2026.4.9'), 'children' => array()),
	));
}

// The label's offset against the span's in the rendered markup.
function _test_button_label_order($html)
{
	$label = strpos($html, 'Release notes');
	$span = strpos($html, '<span class="badge">');
	pg_assert_true($label !== false && $span !== false, 'both pieces are printed');
	return ($label !== false && $span !== false) ? ($label < $span ? 'label-first' : 'span-first') : '';
}

// A link without textPosition: the span comes first, as on the canvas.
function test_designer_button_label_link_default_after_children()
{
	pg_assert_same('span-first', _test_button_label_order(_render_tree_node(_test_button_label_node('a', array('href' => '/changelog')), 0)));
}

// A button without textPosition: the same.
function test_designer_button_label_button_default_after_children()
{
	pg_assert_same('span-first', _test_button_label_order(_render_tree_node(_test_button_label_node('button'), 0)));
}

// textPosition 'start' puts the label in front of the span.
function test_designer_button_label_start_before_children()
{
	pg_assert_same('label-first', _test_button_label_order(_render_tree_node(_test_button_label_node('a', array('textPosition' => 'start')), 0)));
}

// Elements other than a link or a button keep their text first.
function test_designer_button_label_other_tags_unchanged()
{
	pg_assert_same('label-first', _test_button_label_order(_render_tree_node(_test_button_label_node('div'), 0)));
}

// The label is still escaped and the link keeps its address.
function test_designer_button_label_escaped_and_href_kept()
{
	$node = _test_button_label_node('a', array('href' => '/changelog#v2026-4-9', 'text' => 'Notes & fixes'));
	$html = _render_tree_node($node, 0);
	pg_assert_contains('Notes &amp; fixes', $html);
	pg_assert_contains('<a href="/changelog#v2026-4-9">', $html);
}

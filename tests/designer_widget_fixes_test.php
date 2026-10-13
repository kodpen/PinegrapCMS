<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a designed system widget prints its record: a bound heading
 * keeps the link it wraps (pg_binding_text_slot()), an empty field marked
 * pg-hide-if-empty leaves (pg_sw_hide_empty_fields()), a state message takes
 * a tag its list or table may hold (pg_sw_state_message_html()), a stretched
 * link stretches over the box around it rather than its heading
 * (_render_tree_node()), a page with no record keeps only what is meant for
 * that case (pg_sw_prune_record_layout()) and a record page takes its title
 * from the widget's settings (pg_sw_record_title()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- bound text keeps its markup ----------------------------------------------

// A heading whose text is a link, bound to a field: the value goes inside
// the link and the link stays.
function test_designer_widget_fixes_binding_keeps_link()
{
	pg_assert_same('<a href="/blog-detay">^^title^^</a>', pg_binding_text_slot('<a href="/blog-detay">Başlık</a>', '^^title^^'));
}

// Plain text is replaced whole, as before.
function test_designer_widget_fixes_binding_plain_text_replaced()
{
	pg_assert_same('^^title^^', pg_binding_text_slot('Başlık', '^^title^^'));
}

// The spaces around the words stay where they were.
function test_designer_widget_fixes_binding_keeps_spaces()
{
	pg_assert_same('<strong> ^^x^^ </strong>', pg_binding_text_slot('<strong> Eski </strong>', '^^x^^'));
}

// Several runs of words: which one is the value is not known, so the
// value takes the whole text.
function test_designer_widget_fixes_binding_several_runs_take_all()
{
	pg_assert_same('^^x^^', pg_binding_text_slot('<b>Bir</b> iki', '^^x^^'));
}

// Space and &nbsp; between tags are layout, not a second run of words.
function test_designer_widget_fixes_binding_nbsp_is_not_a_run()
{
	pg_assert_same('&nbsp;<a href="/x">^^x^^</a>', pg_binding_text_slot('&nbsp;<a href="/x">Metin</a>', '^^x^^'));
}

// A heading prints its link around the bound value; a component, which
// prints its text escaped, still gets the bare token.
function test_designer_widget_fixes_apply_bindings_rich_text_only()
{
	$props = array('text' => '<a href="/p">T</a>', '_bindings' => array('text' => 'title'));
	$rich  = _apply_bindings($props, true);
	pg_assert_same('<a href="/p">^^title^^</a>', $rich['text']);
	$plain = _apply_bindings($props);
	pg_assert_same('^^title^^', $plain['text']);
}

// -- pg-hide-if-empty ----------------------------------------------------------

// An empty marked element leaves, nested elements of the same tag included.
function test_designer_widget_fixes_hide_empty_leaves()
{
	$html = '<div class="row"><div class="pg-hide-if-empty"><div> </div></div><p>kalır</p></div>';
	pg_assert_same('<div class="row"><p>kalır</p></div>', pg_sw_hide_empty_fields($html));
}

// A marked element with words stays, untouched.
function test_designer_widget_fixes_hide_empty_keeps_content()
{
	$html = '<section class="pg-hide-if-empty mb-2"><span>Metin</span></section>';
	pg_assert_same($html, pg_sw_hide_empty_fields($html));
}

// A "label: value" row goes when its value is empty, though its label is not.
function test_designer_widget_fixes_hide_empty_judges_field_value()
{
	$html = '<li class="pg-hide-if-empty"><strong>Konu:</strong> <span class="pg-field-value"></span></li>';
	pg_assert_same('', pg_sw_hide_empty_fields($html));
	$full = '<li class="pg-hide-if-empty"><strong>Konu:</strong> <span class="pg-field-value">Merhaba</span></li>';
	pg_assert_same($full, pg_sw_hide_empty_fields($full));
}

// A picture counts as content only with an address.
function test_designer_widget_fixes_hide_empty_image()
{
	pg_assert_same('', pg_sw_hide_empty_fields('<img class="pg-hide-if-empty" src="">'));
	pg_assert_same('<img class="pg-hide-if-empty" src="/a.jpg">', pg_sw_hide_empty_fields('<img class="pg-hide-if-empty" src="/a.jpg">'));
}

// An empty semantic element closes on its own line, so :empty matches it.
function test_designer_widget_fixes_empty_element_is_empty()
{
	$html = _render_tree_node(array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'kp-x'), 'children' => array()), 0);
	pg_assert_contains('<div class="kp-x"></div>', $html);
}

// -- state messages ------------------------------------------------------------

// In a list the message is a list item, in a table a row spanning the
// columns, in a grid row a full-width column, elsewhere a block.
function test_designer_widget_fixes_state_message_fits_parent()
{
	pg_assert_same('<li class="pg-sw-empty text-body-secondary py-3">Yok</li>', pg_sw_state_message_html('Yok', 'ul', 'pg-sw-empty'));
	pg_assert_same('<tr class="pg-sw-empty"><td colspan="3" class="text-body-secondary py-3">Yok</td></tr>', pg_sw_state_message_html('Yok', 'tbody', 'pg-sw-empty', 3));
	pg_assert_same('<div class="col-12 pg-sw-empty text-body-secondary py-3">Yok</div>', pg_sw_state_message_html('Yok', 'row', 'pg-sw-empty'));
	pg_assert_contains('<div class="pg-sw-empty', pg_sw_state_message_html('Yok', '', 'pg-sw-empty'));
	pg_assert_contains('&lt;b&gt;', pg_sw_state_message_html('<b>', 'ul', 'pg-sw-empty'));
}

// The columns of a table row template are counted for the colspan.
function test_designer_widget_fixes_row_cells()
{
	pg_assert_same(3, pg_sw_row_cells('<tr><td>a</td><td>b</td><th>c</th></tr>'));
	pg_assert_same(1, pg_sw_row_cells('<li>a</li>'));
}

// The split says what the records print into, and puts the before/after
// markers beside a list so a search box and a pagination stay out of it.
function test_designer_widget_fixes_split_reports_loop_parent()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'UL'), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'li'), 'children' => array()),
			)),
		)),
	));
	$split = _split_widget_tree($tree, true);
	pg_assert_same('ul', $split['loop_parent']);
	pg_assert_same(3, count($split['static_tree']['children']));
	pg_assert_same('<!--pg-loop-before-->', $split['static_tree']['children'][0]['props']['html']);
	pg_assert_same('<!--pg-loop-after-->', $split['static_tree']['children'][2]['props']['html']);
	pg_assert_same(1, count($split['loop_children']));

	$plain = _split_widget_tree($tree);
	pg_assert_same(1, count($plain['static_tree']['children']));

	$top = _split_widget_tree(array('type' => 'root', 'children' => array(array('type' => 'loop_area', 'children' => array()))));
	pg_assert_same('', $top['loop_parent']);
}

// -- stretched links -----------------------------------------------------------

// A card around the heading: the card is the box, the heading gets no
// position-relative of its own.
function test_designer_widget_fixes_stretch_uses_card()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'h2', 'cssClass' => 'h5'), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Oku', 'href' => '/x', 'cssClass' => 'stretched-link'), 'children' => array()),
		)),
	));
	$html = _render_tree_node($tree, 0);
	pg_assert_contains('<h2 class="h5">', $html);
	pg_assert_false(strpos($html, 'position-relative') !== false, 'nothing is given position-relative');
}

// Nothing positioned around it: the bare box it sits in claims it.
function test_designer_widget_fixes_stretch_claims_bare_box()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'kp-msg'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'h2'), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Oku', 'href' => '/x', 'cssClass' => 'stretched-link'), 'children' => array()),
		)),
	));
	$html = _render_tree_node($tree, 0);
	pg_assert_contains('kp-msg position-relative', $html);
	pg_assert_false(strpos($html, '<h2 class="position-relative"') !== false, 'the heading is not the box');
}

// A box whose own style positions it is a box too.
function test_designer_widget_fixes_stretch_inline_position()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'article', 'cssClass' => 'kp-msg',
		'_attrs' => array(array('name' => 'style', 'value' => 'position:relative'))), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'h3', 'cssClass' => 'mb-0'), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Oku', 'href' => '/x', 'cssClass' => 'stretched-link'), 'children' => array()),
		)),
	));
	$html = _render_tree_node($tree, 0);
	pg_assert_false(strpos($html, 'position-relative') !== false, 'no class is added');
}

// -- a page with no record -----------------------------------------------------

// The record's fields go; what is meant for the no-record state stays,
// and so does the element around it.
function test_designer_widget_fixes_prune_keeps_not_found_parts()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'heading', '_bindings' => array('text' => 'konu')), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
				array('type' => 'content', 'props' => array('contentType' => 'paragraph', '_bindings' => array('text' => 'not_found')), 'children' => array()),
				array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Gövde'), 'children' => array()),
			)),
			array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'record_found')), 'children' => array(
				array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Var'), 'children' => array()),
			)),
		)),
	));
	pg_assert_true(pg_sw_prune_record_layout($tree), 'the message is still shown');
	$loop = $tree['children'][0]['children'];
	pg_assert_same(1, count($loop));
	pg_assert_same(1, count($loop[0]['children']));
	pg_assert_same('not_found', $loop[0]['children'][0]['props']['_bindings']['text']);
}

// With nothing for that state, the layout empties and the renderer is told
// to write the message itself.
function test_designer_widget_fixes_prune_without_message()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'heading', '_bindings' => array('text' => 'konu')), 'children' => array()),
		)),
	));
	pg_assert_false(pg_sw_prune_record_layout($tree));
	pg_assert_same(array(), $tree['children'][0]['children']);
}

// The element shown only when there is no record is kept whole.
function test_designer_widget_fixes_prune_keeps_flagged_block()
{
	$block = array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'record_not_found')), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Kayıt yok'), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Listeye dön', 'href' => '/blog'), 'children' => array()),
	));
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array($block)),
	));
	pg_assert_true(pg_sw_prune_record_layout($tree));
	pg_assert_same($block, $tree['children'][0]['children'][0]);
}

// -- the record page's title ----------------------------------------------------

// The chosen field, then the text after it.
function test_designer_widget_fixes_record_title_field_and_suffix()
{
	$cfg = array('title_field' => 'Konu', 'title_suffix' => '| Gelen Kutusu');
	pg_assert_same('Merhaba | Gelen Kutusu', pg_sw_record_title($cfg, array('konu' => "Merhaba\n"), 'RSS'));
}

// No field chosen, or the field left empty: the form's RSS title.
function test_designer_widget_fixes_record_title_falls_back()
{
	pg_assert_same('RSS başlık', pg_sw_record_title(array(), array(), 'RSS başlık'));
	pg_assert_same('RSS | Site', pg_sw_record_title(array('title_field' => 'konu', 'title_suffix' => '| Site'), array('konu' => ''), 'RSS'));
}

// No text at all: the page keeps its own title, the suffix alone is not one.
function test_designer_widget_fixes_record_title_empty()
{
	pg_assert_same('', pg_sw_record_title(array('title_suffix' => '| Site'), array(), ''));
}

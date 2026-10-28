<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checkout documents in the designer's express order widget
 * (includes/fn/widgets_express_order.php): the sentence split behind the
 * box labels, the boxes in the starter tree, their visibility flag, the
 * field binding that names and ticks them, and the prefill list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every node of a tree whose _bindings carry $key => $value, depth first.
function designer_legal_documents_test_find($node, $key, $value, array &$found)
{
	if (!is_array($node)) {
		return;
	}

	if (isset($node['props']['_bindings'][$key]) && ($node['props']['_bindings'][$key] === $value)) {
		$found[] = $node;
	}

	foreach ((array) ($node['children'] ?? array()) as $child) {
		designer_legal_documents_test_find($child, $key, $value, $found);
	}
}

// The value of a node's attribute, or null when it has none.
function designer_legal_documents_test_attr(array $node, $name)
{
	foreach ((array) ($node['props']['_attrs'] ?? array()) as $attr) {
		if (is_array($attr) && (($attr['name'] ?? '') === $name)) {
			return (string) ($attr['value'] ?? '');
		}
	}

	return null;
}

// The link sits where the translated sentence puts its {var:1}.
function test_designer_legal_documents_sentence_split_follows_the_translation()
{
	pg_assert_same(array('I have read and approve', ''), _eo_sentence_around('I have read and approve {var:1}'), 'link last');
	pg_assert_same(array('', 'okudum ve onaylıyorum'), _eo_sentence_around('{var:1} okudum ve onaylıyorum'), 'link first');
	pg_assert_same(array('Ich habe', 'gelesen'), _eo_sentence_around('Ich habe {var:1} gelesen'), 'link inside');
	pg_assert_same(array('No link here', ''), _eo_sentence_around('No link here'), 'no marker');
}

// Both boxes are required, bound to their fields, inside one container that
// shows only while the documents are switched on, and each label links to its
// document through an href binding.
function test_designer_legal_documents_starter_tree_carries_both_boxes()
{
	$tree = _eo_default_designer_tree();

	$blocks = array();
	designer_legal_documents_test_find($tree, 'eo_visible_if', 'has_legal_documents', $blocks);
	pg_assert_same(1, count($blocks), 'one flagged container');

	foreach (array('preinfo', 'contract') as $type) {
		$boxes = array();
		designer_legal_documents_test_find($blocks[0] ?? array(), 'eo_field', 'legal_' . $type, $boxes);
		pg_assert_same(1, count($boxes), $type . ' box');
		pg_assert_same('checkbox', designer_legal_documents_test_attr($boxes[0] ?? array(), 'type'), $type . ' type');
		pg_assert_same('1', designer_legal_documents_test_attr($boxes[0] ?? array(), 'value'), $type . ' value');
		pg_assert_same('', designer_legal_documents_test_attr($boxes[0] ?? array(), 'required'), $type . ' required');

		$links = array();
		designer_legal_documents_test_find($blocks[0] ?? array(), 'href', '__legal_' . $type . '_url', $links);
		pg_assert_same(1, count($links), $type . ' link');
		pg_assert_same('a', $links[0]['props']['tag'] ?? '', $type . ' link tag');
		pg_assert_same('_blank', designer_legal_documents_test_attr($links[0] ?? array(), 'target'), $type . ' link target');
	}
}

// With the documents switched off the container leaves the tree, so no
// required box blocks a checkout the server does not ask them for.
function test_designer_legal_documents_flag_off_drops_the_boxes()
{
	$tree = _eo_default_designer_tree();
	_eo_apply_visibility_bindings($tree, array('has_terms' => true, 'has_legal_documents' => false));

	$boxes = array();
	designer_legal_documents_test_find($tree, 'eo_field', 'legal_preinfo', $boxes);
	designer_legal_documents_test_find($tree, 'eo_field', 'legal_contract', $boxes);
	pg_assert_same(0, count($boxes));
}

// The field binding names the box (so pg_legal_documents_inject() adds no
// second pair) and keeps the visitor's tick after a rejected submit.
function test_designer_legal_documents_field_binding_names_and_ticks_the_box()
{
	// The container alone: the country list elsewhere in the tree reads the
	// database.
	$blocks = array();
	designer_legal_documents_test_find(_eo_default_designer_tree(), 'eo_visible_if', 'has_legal_documents', $blocks);
	$tree = $blocks[0] ?? array();
	$lf = new pg_liveform_snapshot(array('legal_preinfo' => array('value' => '1')));
	_eo_apply_field_bindings($tree, $lf, 0);

	$ticked = array();
	designer_legal_documents_test_find($tree, 'eo_field', 'legal_preinfo', $ticked);
	pg_assert_same('legal_preinfo', designer_legal_documents_test_attr($ticked[0] ?? array(), 'name'), 'name');
	pg_assert_same('legal_preinfo', $ticked[0]['props']['id'] ?? '', 'id');
	pg_assert_same('', designer_legal_documents_test_attr($ticked[0] ?? array(), 'checked'), 'ticked');

	$unticked = array();
	designer_legal_documents_test_find($tree, 'eo_field', 'legal_contract', $unticked);
	pg_assert_same(null, designer_legal_documents_test_attr($unticked[0] ?? array(), 'checked'), 'not ticked');
}

// A tree that writes the boxes by name, without the binding, is prefilled too.
function test_designer_legal_documents_fields_are_prefilled_by_name()
{
	pg_assert_true(_eo_is_known_input_name('legal_preinfo'), 'preinfo');
	pg_assert_true(_eo_is_known_input_name('legal_contract'), 'contract');
}

// The KVKK lines' values for a designer tree: the notice page's address and
// the consent sentence, each escaped once, the sentence's line breaks kept;
// empty while the page did not pass the store's settings.
function test_designer_legal_documents_kvkk_tokens()
{
	$tokens = _eo_legal_kvkk_tokens(array('url' => '/kvkk?a=1&b=2', 'consent' => "I consent <b>\nto marketing"));

	pg_assert_same('/kvkk?a=1&amp;b=2', $tokens['^^__legal_kvkk_notice_url^^'], 'the address');
	pg_assert_same("I consent &lt;b&gt;<br>\nto marketing", $tokens['^^__legal_kvkk_consent_html^^'], 'the sentence');

	$none = _eo_legal_kvkk_tokens(null);
	pg_assert_same('', $none['^^__legal_kvkk_notice_url^^'], 'no address');
	pg_assert_same('', $none['^^__legal_kvkk_consent_html^^'], 'no sentence');
}

// The starter tree's KVKK lines: one pg-legal-kvkk block under has_legal_kvkk
// (the class keeps pg_legal_documents_inject() from adding a second set),
// the notice link under its own flag, the consent box never required and
// labelled by the store's sentence; the editor's starter tree builds the same.
function test_designer_legal_documents_starter_tree_carries_the_kvkk_lines()
{
	$tree = _eo_default_designer_tree();

	$blocks = array();
	designer_legal_documents_test_find($tree, 'eo_visible_if', 'has_legal_kvkk', $blocks);
	pg_assert_same(1, count($blocks), 'one flagged block');
	pg_assert_contains('pg-legal-kvkk', $blocks[0]['props']['cssClass'] ?? '', 'the marker class');

	$links = array();
	designer_legal_documents_test_find($blocks[0] ?? array(), 'href', '__legal_kvkk_notice_url', $links);
	pg_assert_same(1, count($links), 'the notice link');
	pg_assert_same('_blank', designer_legal_documents_test_attr($links[0] ?? array(), 'target'), 'in a new window');

	$boxes = array();
	designer_legal_documents_test_find($blocks[0] ?? array(), 'eo_field', 'legal_kvkk', $boxes);
	pg_assert_same(1, count($boxes), 'the consent box');
	pg_assert_same(null, designer_legal_documents_test_attr($boxes[0] ?? array(), 'required'), 'never required');

	$labels = array();
	designer_legal_documents_test_find($blocks[0] ?? array(), 'text', '__legal_kvkk_consent_html', $labels);
	pg_assert_same(1, count($labels), 'labelled by the store\'s sentence');

	$tree = _eo_default_designer_tree();
	_eo_apply_visibility_bindings($tree, array('has_terms' => true, 'has_legal_documents' => true, 'has_legal_kvkk' => true, 'has_legal_kvkk_notice' => true));
	$boxes = array();
	designer_legal_documents_test_find($tree, 'eo_field', 'legal_kvkk', $boxes);
	pg_assert_same(0, count($boxes), 'no sentence: no box');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	foreach (array("sem('div', 'pg-legal-kvkk mb-2'", "bindings: { href: '__legal_kvkk_notice_url' }", "bindings: { eo_field: 'legal_kvkk' }",
		"bindings: { text: '__legal_kvkk_consent_html' }", "{ bindings: { eo_visible_if: 'has_legal_kvkk_consent' } }", "kvkkBlock(),") as $needle) {
		pg_assert_contains($needle, $js, 'editor twin: ' . $needle);
	}
}

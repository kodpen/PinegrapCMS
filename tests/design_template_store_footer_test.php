<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the store template's footer (includes/design_templates/
 * pinegrap-store.php): the contact column is wide enough on a laptop for the
 * e-mail address not to break inside a word, the phone number stays on one
 * line, the columns still add up to one row, and the template's version moved
 * with the change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The node of $tree whose customName is $name, null when none.
function _design_template_store_footer_node($tree, $name)
{
	if (!is_array($tree)) return null;
	if (isset($tree['props']['customName']) && $tree['props']['customName'] === $name) return $tree;
	foreach (isset($tree['children']) && is_array($tree['children']) ? $tree['children'] : array() as $child) {
		$found = _design_template_store_footer_node($child, $name);
		if ($found !== null) return $found;
	}
	return null;
}

// The link nodes under $tree, in order.
function _design_template_store_footer_links($tree)
{
	$out = array();
	if (!is_array($tree)) return $out;
	if (isset($tree['props']['contentType']) && $tree['props']['contentType'] === 'link') $out[] = $tree;
	foreach (isset($tree['children']) && is_array($tree['children']) ? $tree['children'] : array() as $child) {
		$out = array_merge($out, _design_template_store_footer_links($child));
	}
	return $out;
}

function test_design_template_store_footer_contact_column()
{
	$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-store.php');
	pg_assert_same('1.1.2', $tpl['version']);

	$footer  = $tpl['shared']['footer']['tree'];
	$columns = _design_template_store_footer_node($footer, lang('Footer Columns'));
	pg_assert_true(is_array($columns), 'the footer columns row');

	$about   = _design_template_store_footer_node($footer, lang('About the Store'));
	$contact = _design_template_store_footer_node($footer, lang('Contact Details'));
	pg_assert_same('col-12 col-lg-3', $about['props']['cssClass']);
	pg_assert_same('col-6 col-lg-3', $contact['props']['cssClass']);

	// Every column's large width still adds up to one row of twelve.
	$total = 0;
	foreach ($columns['children'] as $col) {
		pg_assert_true((bool)preg_match('/\bcol-lg-(\d+)\b/', $col['props']['cssClass'], $m), 'a large width on ' . $col['props']['cssClass']);
		$total += (int)$m[1];
	}
	pg_assert_same(12, $total);

	$links = _design_template_store_footer_links($contact);
	pg_assert_same(2, count($links));
	pg_assert_same('mailto:hello@example.com', $links[0]['props']['href']);
	pg_assert_same('tel:+905550000000', $links[1]['props']['href']);
	pg_assert_true(strpos(' ' . $links[1]['props']['cssClass'] . ' ', ' text-nowrap ') !== false, 'the phone stays on one line');
}

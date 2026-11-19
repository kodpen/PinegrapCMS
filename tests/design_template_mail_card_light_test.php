<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the 600 px e-mail card of every design template stays light in
 * any page theme: the card's bg-body class (!important) beats its inline
 * background:#ffffff, so under data-bs-theme="dark" the inline dark text sat
 * on a dark card (1.05-1.15:1). The frame around the card sets
 * data-bs-theme="light".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Calls $fn($node, $ancestors) for every node of the trees in $value.
function _test_tpl_mail_card_walk($value, $fn, $ancestors = array())
{
	if (!is_array($value)) return;
	if (isset($value['type'], $value['props']) && is_array($value['props'])) {
		$fn($value, $ancestors);
		$ancestors[] = $value;
	}
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_mail_card_walk($child, $fn, $ancestors);
	}
}

// The value of attribute $name on $node, or null.
function _test_tpl_mail_card_attr($node, $name)
{
	$attrs = isset($node['props']['_attrs']) && is_array($node['props']['_attrs']) ? $node['props']['_attrs'] : array();
	foreach ($attrs as $attr) {
		if (is_array($attr) && isset($attr['name'], $attr['value']) && $attr['name'] === $name) return (string)$attr['value'];
	}
	return null;
}

// Counts the e-mail cards in $tree and lists those whose nearest
// data-bs-theme ancestor is not "light".
function _test_tpl_mail_card_problems($tree, &$cards)
{
	$bad = array();
	_test_tpl_mail_card_walk($tree, function ($node, $ancestors) use (&$bad, &$cards) {
		$style = (string)_test_tpl_mail_card_attr($node, 'style');
		$class = ' ' . (isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '') . ' ';
		if (strpos($style, 'max-width:600px') !== 0 || strpos($class, ' bg-body ') === false) return;
		$cards++;
		$theme = _test_tpl_mail_card_attr($node, 'data-bs-theme');
		for ($i = count($ancestors) - 1; $theme === null && $i >= 0; $i--) {
			$theme = _test_tpl_mail_card_attr($ancestors[$i], 'data-bs-theme');
		}
		if ($theme !== 'light') $bad[] = 'e-mail card without a light frame: ' . $style;
	});
	return $bad;
}

function test_design_template_mail_cards_stay_light()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates';
	$with_cards = 0;
	foreach (glob($dir . '/*.php') as $file) {
		$id = basename($file, '.php');
		$tpl = _pg_tpl_include($file);
		pg_assert_true(is_array($tpl), $id . ' loaded');
		$cards = 0;
		pg_assert_same(array(), array_values(array_unique(_test_tpl_mail_card_problems($tpl, $cards))), $id . ' keeps its e-mail cards light');
		if ($cards > 0) $with_cards++;
	}
	pg_assert_true($with_cards >= 55, 'the templates with e-mail cards were found (' . $with_cards . ')');
}

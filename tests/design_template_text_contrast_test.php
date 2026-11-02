<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the text and colour classes of the hello-pinegrap and
 * pinegrap-playground design templates: no faded text (opacity-*, also
 * checked in pinegrap-events and pinegrap-courses), no primary-coloured text or outline button on a light grey section, and one
 * name for the playground's site owner (the avatar carries its initials).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The template file's array, as the loader reads it.
function _test_tpl_text_template($id)
{
	return _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
}

// Every string value in $value, depth first.
function _test_tpl_text_strings($value, &$out)
{
	if (is_string($value)) {
		$out[] = $value;
		return;
	}
	if (!is_array($value)) return;
	foreach ($value as $child) _test_tpl_text_strings($child, $out);
}

// Calls $fn($node, $ancestors) for every node of the trees in $value.
function _test_tpl_text_walk($value, $fn, $ancestors = array())
{
	if (!is_array($value)) return;
	$is_node = isset($value['type']) && isset($value['props']) && is_array($value['props']);
	if ($is_node) {
		$fn($value, $ancestors);
		$ancestors[] = $value;
	}
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_text_walk($child, $fn, $ancestors);
	}
}

// The class list of a node, space separated, or ''.
function _test_tpl_text_class($node)
{
	foreach (array('className', 'class', 'cssClass') as $key) {
		if (isset($node['props'][$key]) && is_string($node['props'][$key])) return ' ' . $node['props'][$key] . ' ';
	}
	return '';
}

function test_design_template_text_never_faded()
{
	foreach (array('hello-pinegrap', 'pinegrap-playground', 'pinegrap-events', 'pinegrap-courses') as $id) {
		$strings = array();
		_test_tpl_text_strings(_test_tpl_text_template($id), $strings);
		$faded = array_values(array_filter($strings, function ($s) {
			return (bool)preg_match('/(^|\s)opacity-\d+(\s|$)/', $s);
		}));
		pg_assert_same(array(), $faded, $id . ' fades no text with opacity-*');
	}
}

function test_design_template_text_on_grey_sections_is_readable()
{
	foreach (array('hello-pinegrap', 'pinegrap-playground') as $id) {
		$bad = array();
		_test_tpl_text_walk(_test_tpl_text_template($id), function ($node, $ancestors) use (&$bad) {
			$class = _test_tpl_text_class($node);
			if (!preg_match('/ (text-primary|link-primary|btn-outline-primary|btn-outline-secondary) /', $class)) return;
			if (isset($node['type']) && $node['type'] === 'icon') return;
			if (preg_match('/ bi /', $class) || preg_match('/ bi-/', $class)) return;
			foreach (array_reverse($ancestors) as $a) {
				$ac = _test_tpl_text_class($a);
				if (preg_match('/ (card|accordion-item|list-group-item|bg-body) /', $ac)) return;
				if (strpos($ac, ' bg-body-tertiary ') !== false) {
					$bad[] = trim($class);
					return;
				}
			}
		});
		pg_assert_same(array(), $bad, $id . ' puts no primary text or outline button on bg-body-tertiary');
	}
}

function test_design_template_playground_owner_name()
{
	$strings = array();
	_test_tpl_text_strings(_test_tpl_text_template('pinegrap-playground'), $strings);
	$avatars = array_values(array_filter($strings, function ($s) {
		return strpos($s, 'https://placehold.co/') === 0 && strpos($s, 'a21caf') !== false;
	}));
	pg_assert_true(count($avatars) >= 2, 'the header and the account menu carry the avatar');
	foreach ($avatars as $url) {
		pg_assert_true(substr($url, -strlen('?text=JC')) === '?text=JC', 'the avatar carries the initials of Jane Cooper: ' . $url);
	}
	pg_assert_true(in_array('About me', $strings, true), 'the menu names the about page About me, as its title');
	pg_assert_true(!in_array('Type', $strings, true), 'the font project is tagged Font, not Type');
}

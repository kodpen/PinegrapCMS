<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the design templates colour their pages only with classes that
 * read the design's colour palette and colour mode, as the ready-made blocks
 * do (tests/designer_blocks_test.php). A fixed colour class does not follow
 * them: text-white on a primary surface is unreadable in a palette whose
 * primary is light (amber, silver), btn-light and text-bg-light stay light
 * patches in a dark design, btn-dark and text-bg-dark disappear into its
 * background. A primary surface is written text-bg-primary, a dark band
 * data-bs-theme="dark" with bg-body text-body
 * (docs/memory/designer/designer-block-theme-colours.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The fixed colour classes, the same list the ready-made blocks are held to.
function _test_tpl_colours_fixed()
{
	return array('bg-white', 'bg-light', 'bg-dark', 'bg-black', 'text-white', 'text-white-50', 'text-dark',
		'text-black', 'text-black-50', 'text-muted', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark',
		'link-light', 'link-dark', 'navbar-dark', 'navbar-light', 'bg-primary', 'bg-secondary',
		'text-light', 'text-bg-light', 'text-bg-dark');
}

// The fixed classes a template still uses. The Bike Shop's files have no main
// file yet, so the installer does not offer it and no colour palette is
// chosen for it: its ink buttons, badges and dark bands are replaced when it
// gets one, which is why a listed template may have no main file. The list
// only gets shorter: a class a template no longer uses has to leave it, and
// nothing is added.
function _test_tpl_colours_still_fixed()
{
	return array(
		'pinegrap-bikeshop' => array('bg-dark', 'btn-dark', 'btn-outline-dark', 'link-light', 'text-bg-dark', 'text-white'),
	);
}

// The template a file belongs to: the main file's name, or its folder's.
function _test_tpl_colours_id($file)
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/';
	$rel = substr($file, strlen($dir));
	$slash = strpos($rel, '/');
	return $slash === false ? basename($rel, '.php') : substr($rel, 0, $slash);
}

// The fixed colour classes among the class names of $text.
function _test_tpl_colours_of($text)
{
	return array_values(array_intersect(preg_split('/\s+/', (string)$text, -1, PREG_SPLIT_NO_EMPTY), _test_tpl_colours_fixed()));
}

// template => sorted fixed classes written in the string literals of its
// files, every branch included, also a file no template places yet.
function _test_tpl_colours_in_source()
{
	static $found = null;
	if ($found !== null) return $found;
	$found = array();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates';
	foreach (array_merge(glob($dir . '/*.php'), glob($dir . '/*/*.php')) as $file) {
		$id = _test_tpl_colours_id($file);
		if (!isset($found[$id])) $found[$id] = array();
		foreach (token_get_all((string)file_get_contents($file)) as $token) {
			if (!is_array($token)) continue;
			if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$text = substr($token[1], 1, -1);
			} elseif ($token[0] === T_ENCAPSED_AND_WHITESPACE) {
				$text = $token[1];
			} else {
				continue;
			}
			foreach (_test_tpl_colours_of($text) as $class) $found[$id][$class] = true;
		}
	}
	foreach ($found as $id => $classes) {
		$classes = array_keys($classes);
		sort($classes);
		$found[$id] = $classes;
	}
	return $found;
}

// Adds the fixed classes on the nodes of the trees in $value to $classes. A
// component's light or dark variant (a button, the language switcher) is the
// button class the renderer writes for it.
function _test_tpl_colours_walk($value, array &$classes)
{
	if (!is_array($value)) return;
	if (isset($value['props']['cssClass']) && is_string($value['props']['cssClass'])) {
		foreach (_test_tpl_colours_of($value['props']['cssClass']) as $class) $classes[$class] = true;
	}
	if (isset($value['props']['variant']) && in_array($value['props']['variant'], array('light', 'dark'), true)) {
		$classes[(empty($value['props']['outline']) ? 'btn-' : 'btn-outline-') . $value['props']['variant']] = true;
	}
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_colours_walk($child, $classes);
	}
}

// template => sorted fixed classes on the nodes the template really builds,
// which also reads a class name put together from parts.
function _test_tpl_colours_in_trees()
{
	static $found = null;
	if ($found !== null) return $found;
	$found = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$classes = array();
		_test_tpl_colours_walk(_pg_tpl_include($file), $classes);
		$classes = array_keys($classes);
		sort($classes);
		$found[basename($file, '.php')] = $classes;
	}
	return $found;
}

function test_design_template_theme_colours_reads_class_names()
{
	pg_assert_same(array('text-white', 'bg-primary'), _test_tpl_colours_of("py-5  text-white\tbg-primary text-bg-primary bg-primary-subtle"), 'whole class names only');
	pg_assert_same(array(), _test_tpl_colours_of('btn btn-secondary text-reset bg-body text-body-emphasis border link-body-emphasis'), 'theme classes pass');

	$classes = array();
	_test_tpl_colours_walk(array('pages' => array(array('tree' => array('type' => 'semantic', 'props' => array('cssClass' => 'card text-bg-dark'), 'children' => array(
		array('type' => 'content', 'props' => array('cssClass' => 'btn btn-outline-light')),
	))))), $classes);
	pg_assert_same(array('text-bg-dark', 'btn-outline-light'), array_keys($classes), 'every node of a tree is read');

	$classes = array();
	_test_tpl_colours_walk(array(
		array('type' => 'component', 'props' => array('componentType' => 'language_switcher', 'variant' => 'light', 'outline' => true)),
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'variant' => 'dark')),
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'variant' => 'secondary', 'outline' => true)),
	), $classes);
	pg_assert_same(array('btn-outline-light', 'btn-dark'), array_keys($classes), 'a light or dark variant is its button class');

	pg_assert_same('pinegrap-hotel', _test_tpl_colours_id(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel.php'), 'a main file is its template');
	pg_assert_same('pinegrap-hotel', _test_tpl_colours_id(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/parts.php'), 'a file in the folder is its template');
}

// No template file writes a fixed colour class, but for the listed ones.
function test_design_template_files_use_theme_driven_colours()
{
	$still = _test_tpl_colours_still_fixed();
	$found = _test_tpl_colours_in_source();
	pg_assert_true(count($found) >= 50, 'the template files are read');
	foreach ($found as $id => $classes) {
		$allowed = isset($still[$id]) ? $still[$id] : array();
		pg_assert_same(array(), array_values(array_diff($classes, $allowed)), $id . ': fixed colour classes in its files');
	}
}

// Nor does a node a template builds carry one, however its class was put
// together. Every template that has trees is one the installer offers, so
// the list does not reach here.
function test_design_template_trees_use_theme_driven_colours()
{
	$found = _test_tpl_colours_in_trees();
	pg_assert_true(count($found) >= 50, 'the templates are loaded');
	foreach ($found as $id => $classes) {
		pg_assert_same(array(), $classes, $id . ': fixed colour classes on its nodes');
	}
}

// bg-primary leaves the text colour to chance (white on a badge, the page's
// own elsewhere), so it is on no list: a primary surface is text-bg-primary
// in every template.
function test_design_template_primary_surfaces_are_text_bg_primary()
{
	foreach (_test_tpl_colours_in_source() as $id => $classes) {
		pg_assert_false(in_array('bg-primary', $classes, true), $id . ': bg-primary in its files');
	}
	foreach (_test_tpl_colours_in_trees() as $id => $classes) {
		pg_assert_false(in_array('bg-primary', $classes, true), $id . ': bg-primary on its nodes');
	}
}

// The list holds only what is still there, so it shrinks with every class
// that is replaced and ends when the last one is. A template the installer
// offers is not on it: the step that gives a template its main file replaces
// its fixed classes and takes its entry out.
function test_design_template_still_fixed_list_only_shrinks()
{
	$found = _test_tpl_colours_in_source();
	foreach (_test_tpl_colours_still_fixed() as $id => $allowed) {
		pg_assert_true(isset($found[$id]), $id . ' is a template');
		pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php'), $id . ': a template with a main file is not on the list');
		$sorted = $allowed;
		sort($sorted);
		pg_assert_same($sorted, $allowed, $id . ': the list is sorted');
		$used = isset($found[$id]) ? $found[$id] : array();
		pg_assert_same(array(), array_values(array_diff($allowed, $used)), $id . ': listed classes it no longer uses');
	}
}

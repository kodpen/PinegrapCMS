<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic style opened in the visual designer
 * (includes/fn/classic_style_design.php): which rows may open, the style's
 * code read as one frame component, and the page tree placing it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');

function _test_csd_doc($head, $body, $body_attrs = '')
{
	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Site</title><meta_tags></meta_tags>'
		. $head . '<stylesheet></stylesheet></head><body' . $body_attrs . '>' . $body . '</body></html>';
}

// Every node of a tree, depth first, the root left out.
function _test_csd_nodes($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) $out = array_merge($out, _test_csd_nodes($n['children']));
	}
	return $out;
}

function test_classic_style_refusal()
{
	$ok = array('style_type' => 'custom', 'style_layout' => '', 'style_code' => '<html></html>');
	pg_assert_same('', pg_classic_style_refusal($ok));
	pg_assert_true(pg_classic_style_refusal(array_merge($ok, array('style_layout' => 'visual_designer'))) !== '', 'a visual design');
	pg_assert_true(pg_classic_style_refusal(array_merge($ok, array('style_type' => 'system', 'style_layout' => 'one_column'))) !== '', 'an older system style');
	pg_assert_true(pg_classic_style_refusal(array_merge($ok, array('style_code' => "  \n"))) !== '', 'no code');
	pg_assert_true(pg_classic_style_refusal(null) !== '', 'no row');
}

function test_classic_style_frame_regions_and_root()
{
	$doc = _test_csd_doc('', '<header class="top"><cregion>header</cregion></header><main><pregion></pregion><system></system></main>',
		' class="home" id="top"');
	$f = pg_classic_style_frame($doc, function ($name) {
		return array('regionType' => $name === 'header' ? 'designer' : 'common');
	});

	// Body attributes and the title go to each page's root, not the component.
	pg_assert_same('home', $f['root_props']['cssClass']);
	pg_assert_same('top', $f['root_props']['id']);
	pg_assert_same('Site', $f['root_props']['title']);
	pg_assert_same('root', $f['frame']['type']);
	pg_assert_same(array(), $f['frame']['props']);

	$regions = array();
	foreach (_test_csd_nodes($f['frame']['children']) as $n) {
		pg_assert_true(!empty($n['_id']), 'every node has an id');
		if ($n['type'] === 'region') $regions[] = $n['props']['regionType'] . ':' . $n['props']['regionName'];
	}
	pg_assert_same(array('designer:header', 'page:', 'system:'), $regions);

	// Written back, the frame prints the same placeholders in the same order.
	$code = generate_style_code_from_tree($f['frame'], 'x');
	pg_assert_true(strpos($code, '<cregion>header</cregion>') < strpos($code, '<pregion></pregion>'), 'cregion before pregion');
	pg_assert_true(strpos($code, '<pregion></pregion>') < strpos($code, '<system></system>'), 'pregion before system');
}

function test_classic_style_frame_keeps_theme_framework()
{
	$doc = _test_csd_doc('<link rel="stylesheet" href="{path}bootstrap.css"><script src="{path}jquery.min.js"></script>', '<pregion></pregion>');
	$f = pg_classic_style_frame($doc);
	$css = array_map(function ($a) { return $a['content']; }, $f['css']);
	$js  = array_map(function ($a) { return $a['content']; }, $f['js']);
	pg_assert_same(array('{path}bootstrap.css'), $css);
	pg_assert_same(array('{path}jquery.min.js'), $js);
}

function test_classic_style_page_tree()
{
	$t = pg_classic_style_page_tree(array('cssClass' => 'home', 'title' => 'Site'), 12, 'Main style');
	pg_assert_same('root', $t['type']);
	pg_assert_same('home', $t['props']['cssClass']);
	pg_assert_same(1, count($t['children']));
	$ref = $t['children'][0];
	pg_assert_same('shared_ref', $ref['type']);
	pg_assert_same(12, $ref['props']['sharedId']);
	pg_assert_same('Main style', $ref['props']['sharedName']);
	pg_assert_same(array(), $ref['children']);
}

function test_classic_style_component_category()
{
	pg_assert_same('classic_style:7', pg_classic_style_component_category('7'));
}

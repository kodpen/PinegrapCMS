<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Scroll Scenes block family
 * (assets/js/style_designer_blocks_scene.js): Pinned Story draws a section
 * with the scene interaction, a stage and three layers marked for the
 * script; its group is Scroll Scenes; its rule pins the stage only while
 * the script has written a step, so without it the layers stand one under
 * the other; the stylesheet reader takes the rule as the editor adds it.
 * The drawing runs under Node with the core's family code; on a machine
 * without Node that half is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_scene_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_scene.js');
}

// The core between $from and the first $to after it.
function _test_scene_cut($js, $from, $to)
{
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

function test_designer_block_scene_rule()
{
	$part = _pg_designer_block_css_parse(_test_scene_file());
	pg_assert_same(array('scene-pinned-story' => array('pg-scene')), $part['needs'], 'the block needs its rule');
	$rule = isset($part['rules']['pg-scene']) ? $part['rules']['pg-scene'] : '';
	foreach (explode("\n", $rule) as $line) {
		if (strpos($line, '@media') === 0) {
			pg_assert_contains('prefers-reduced-motion: reduce', $line, 'the media rule only stops the fade');
			continue;
		}
		pg_assert_true(strpos($line, '.pg-scene[data-pg-scene-step] ') === 0, 'only while the script wrote a step: ' . $line);
	}
	pg_assert_contains('position: sticky; top: 0;', $rule, 'the stage is pinned');
	pg_assert_contains('var(--pg-scene-steps, 3) * 100svh', $rule, 'one window per layer');
	pg_assert_contains('[data-pg-scene-layer]:not([data-pg-scene-on]) { opacity: 0; visibility: hidden; }', $rule, 'the marked layer alone shows');
	pg_assert_contains('.pg-scene', pg_designer_block_css(array('scene-pinned-story')), 'the site stylesheet takes the rule');
}

function test_designer_block_scene_draws()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$script = "'use strict';\n"
		. "function _sdT(key, vars) { return vars === undefined ? key : String(key).replace('{var}', vars); }\n"
		. "function createNode(type, props, children) { return { type: type, props: props || {}, children: children || [] }; }\n"
		. _test_scene_cut($core, '    // ── Block families in their own files', '    // ── Block stylesheet')
		. _test_scene_cut($core, '    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. _test_scene_cut($core, '    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. _test_scene_file() . "\n"
		. "var groups = _sdBlockPaletteGroups();\n"
		. "var story = _sdBlockFamilyOf('scene-pinned-story').build('scene-pinned-story');\n"
		. "var stage = story.children[0];\n"
		. "function attr(n, name) { return (n.props._attrs || []).some(function (a) { return a.name === name; }); }\n"
		. "function find(n, test, acc) { acc = acc || []; if (test(n)) acc.push(n); (n.children || []).forEach(function (c) { find(c, test, acc); }); return acc; }\n"
		. "console.log(JSON.stringify({\n"
		. "  group: groups.filter(function (g) { return g.items.some(function (it) { return it.extra.componentType === 'scene-pinned-story'; }); }).map(function (g) { return g.title; }),\n"
		. "  section: [story.type, story.props.tag, story.props.cssClass, story.props._fx],\n"
		. "  stage: [stage.props.cssClass, stage.children.length],\n"
		. "  layers: stage.children.map(function (l) { return attr(l, 'data-pg-scene-layer'); }),\n"
		. "  marked: find(story, function (n) { return attr(n, 'data-pg-scene-layer'); }).length,\n"
		. "  headings: find(story, function (n) { return n.props.contentType === 'heading'; }).map(function (n) { return n.props.tag; }),\n"
		. "  shots: find(story, function (n) { return n.props.contentType === 'image'; }).map(function (n) { return [n.props.cssClass.indexOf('pg-scene-shot') !== -1, n.props.alt !== '']; }),\n"
		. "  needs: _SD_BLOCK_CSS_NEEDS['scene-pinned-story']\n"
		. "}));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgscene');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$got = json_decode((string) end($out), true);

	pg_assert_same(array('Scroll Scenes'), $got['group'], 'in its own palette group');
	pg_assert_same(array('semantic', 'section', 'pg-scene bg-body', array('effect' => 'scene')), $got['section'], 'a section with the scene interaction');
	pg_assert_same(pg_fx_normalize(array('effect' => 'scene')), $got['section'][3], 'kept as written');
	pg_assert_same(array('pg-scene-stage', 3), $got['stage'], 'a stage with three layers');
	pg_assert_same(array(true, true, true), $got['layers'], 'each a direct child of the stage, marked for the script');
	pg_assert_same(3, $got['marked'], 'nothing else marked');
	pg_assert_same(array('h2', 'h2', 'h2'), $got['headings'], 'one heading per layer, read in order without the script');
	pg_assert_same(array(array(true, true), array(true, true), array(true, true)), $got['shots'], 'pictures that fit the window, with alt text');
	pg_assert_same(array('pg-scene'), $got['needs']);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Listen block (assets/js/style_designer_blocks_listen.js):
 * its palette group and entry; the tree the palette drops, drawn under Node
 * with the core's family code and printed by the site's renderer: a hidden
 * root marked data-pg-listen that pg_listen_inject() finds, three real
 * buttons with their marks and labels, pause and stop hidden, no script,
 * handler or style of its own; its rule, without !important, taken by the
 * site stylesheet; and the canvas drawing the hidden root visible.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/listen.php');

function _test_listen_block_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_listen.js');
}

// The core between $from and the first $to after it.
function _test_listen_block_cut($js, $from, $to)
{
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

// The palette's group and the dropped tree, from Node; null without Node.
function _test_listen_block_drawn()
{
	static $got = false;
	if ($got !== false) {
		return $got;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return $got = null;
	}
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$script = "'use strict';\n"
		. "function _sdT(key, vars) { return vars === undefined ? key : String(key).replace('{var}', vars); }\n"
		. "var _n = 0; function createNode(type, props, children) { return { _id: 'n' + (++_n), type: type, props: props || {}, children: children || [] }; }\n"
		. _test_listen_block_cut($core, '    // ── Block families in their own files', '    // ── Block stylesheet')
		. _test_listen_block_cut($core, '    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. _test_listen_block_cut($core, '    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. _test_listen_block_file() . "\n"
		. "var groups = _sdBlockPaletteGroups().filter(function (g) { return g.items.some(function (it) { return it.extra.componentType === 'listen-buttons'; }); });\n"
		. "console.log(JSON.stringify({\n"
		. "  groups: groups.map(function (g) { return [g.title, g.items.map(function (it) { return [it.label, it.extra.componentType, !!it.desc]; })]; }),\n"
		. "  tree: _sdBlockFamilyOf('listen-buttons').build('listen-buttons'),\n"
		. "  needs: _SD_BLOCK_CSS_NEEDS['listen-buttons']\n"
		. "}));\n";
	$file = tempnam(sys_get_temp_dir(), 'pglistenblock');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = ($code === 0 ? json_decode((string) end($out), true) : null);
}

function _test_listen_block_attrs($node)
{
	$out = array();
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) {
		$out[$a['name']] = $a['value'];
	}
	return $out;
}

// (L1) A palette entry in its own group.
function test_designer_block_listen_palette()
{
	$got = _test_listen_block_drawn();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same(array(array('Listen', array(array('Listen', 'listen-buttons', true)))), $got['groups']);
	pg_assert_same(array('pg-listen'), $got['needs'], 'the block needs its rule');
}

// (L1, L2) The tree: a hidden root with the mark, three real buttons.
function test_designer_block_listen_tree()
{
	$got = _test_listen_block_drawn();
	if ($got === null) {
		return;
	}
	$root = $got['tree'];
	$attrs = _test_listen_block_attrs($root);
	pg_assert_same(array('semantic', 'div'), array($root['type'], $root['props']['tag']));
	pg_assert_contains('pg-listen', $root['props']['cssClass']);
	// A display utility (d-flex…) is !important and comes after Bootstrap's
	// [hidden] rule: the hidden root would show without a voice (L8).
	pg_assert_false((bool) preg_match('/(^|\s)d-(\w+-)?(flex|inline|block|grid|table|contents)\b/', $root['props']['cssClass']), 'no display utility on the hidden root');
	pg_assert_same('', $attrs['data-pg-listen'], 'the mark');
	pg_assert_same('', $attrs['hidden'], 'hidden until the script finds a voice');
	pg_assert_same(array('group', 'Read this article aloud'), array($attrs['role'], $attrs['aria-label']));

	pg_assert_same(3, count($root['children']));
	$want = array('play' => false, 'pause' => true, 'stop' => true);
	foreach ($root['children'] as $i => $btn) {
		$name = array_keys($want)[$i];
		$a = _test_listen_block_attrs($btn);
		pg_assert_same('button', $btn['props']['tag'], $name . ' is a real button');
		pg_assert_same('button', $a['type']);
		pg_assert_true(array_key_exists('data-pg-listen-' . $name, $a), $name . ' carries its mark');
		pg_assert_same($want[$name], array_key_exists('hidden', $a), $name . ' hidden while idle');
		pg_assert_same('icon', $btn['children'][0]['props']['contentType']);
		pg_assert_same('true', _test_listen_block_attrs($btn['children'][0])['aria-hidden'], 'the icon is not read');
		pg_assert_true(array_key_exists('data-pg-listen-text', _test_listen_block_attrs($btn['children'][1])), 'the label the script fills');
	}
	pg_assert_same(array('Listen', 'Pause', 'Stop'), array_map(function ($b) { return $b['children'][1]['props']['text']; }, $root['children']));
	// (L1) Listen starts the voice: a play icon, beside pause and stop.
	pg_assert_same(array('bi-play-fill', 'bi-pause-fill', 'bi-stop-fill'), array_map(function ($b) { return $b['children'][0]['props']['iconName']; }, $root['children']));
	pg_assert_same('false', _test_listen_block_attrs($root['children'][1])['aria-pressed'], 'the pause state is told');
}

// (L1, L2) Printed by the site's renderer: the mark the server looks for,
// and no script, handler or style of the block's own.
function test_designer_block_listen_printed()
{
	$got = _test_listen_block_drawn();
	if ($got === null) {
		return;
	}
	$html = _render_tree_node($got['tree']);
	pg_assert_true(pg_listen_on_page('<body>' . $html . '</body>'), 'pg_listen_inject() finds the block');
	pg_assert_true((bool) preg_match('/<div[^>]*\sdata-pg-listen(?=[\s=>])[^>]*\shidden(?=[\s=>])/', $html) || (bool) preg_match('/<div[^>]*\shidden(?=[\s=>])[^>]*\sdata-pg-listen(?=[\s=>])/', $html), 'the root is printed hidden with its mark');
	pg_assert_same(3, preg_match_all('/<button\b[^>]*type="button"/', $html), 'three buttons');
	pg_assert_same(2, preg_match_all('/<button\b[^>]*\shidden(?=[\s=>])/', $html), 'pause and stop hidden');
	pg_assert_same(3, substr_count($html, 'data-pg-listen-text'));
	foreach (array('<script', ' onclick', ' style=') as $never) {
		pg_assert_false(stripos($html, $never) !== false, 'no ' . trim($never));
	}
	pg_assert_contains('<script src="/x/pg_listen.js" defer>', pg_listen_inject('<body>' . $html . '</body>', '/x/pg_listen.js'));
}

// The rule: a finger-sized button, no !important, taken by the site.
function test_designer_block_listen_rule()
{
	$part = _pg_designer_block_css_parse(_test_listen_block_file());
	pg_assert_same(array('listen-buttons' => array('pg-listen')), $part['needs']);
	$rule = isset($part['rules']['pg-listen']) ? $part['rules']['pg-listen'] : '';
	pg_assert_contains('.pg-listen .btn { min-height: 2.75rem;', $rule);
	pg_assert_contains('.pg-listen:not([hidden]) { display: flex;', $rule, 'the row is laid out only once shown');
	pg_assert_false(strpos($rule, '!important') !== false, 'no !important');
	pg_assert_contains('.pg-listen', pg_designer_block_css(array('listen-buttons')), 'the site stylesheet takes the rule');
}

// (L12) On the canvas the hidden root is drawn as it will look, and the
// block's file carries no script of its own.
function test_designer_block_listen_canvas()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = _test_listen_block_cut($core, '    var IFRAME_CSS = [', "\n    ];");
	pg_assert_contains("'.sd-wrap > [data-pg-listen][hidden] { display:flex !important; flex-wrap:wrap; align-items:center; gap:.5rem; }'", $css, 'the canvas shows the hidden root');
	pg_assert_false(strpos($core, "'assets/js/pg_listen.js'") === false, 'the Preview loads the script (L13)');
	foreach (array('speechSynthesis', 'addEventListener', 'onclick') as $never) {
		pg_assert_false(strpos(_test_listen_block_file(), $never) !== false, 'the block file holds no ' . $never);
	}
}

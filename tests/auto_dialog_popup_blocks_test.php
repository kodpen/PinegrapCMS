<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pop-ups blocks (assets/js/style_designer_blocks_popup.js):
 * their palette group and entries; the trees the palette drops, drawn under
 * Node with the core's family code and printed by the site's renderer: each
 * a close button marked data-pg-popup-close and one main action marked
 * data-pg-popup-take, the offer code's code and Copy button, no script,
 * handler or style of their own; their rules; and the site's dialog script
 * (frontend.src.js and the hand-patched frontend.min.js) that copies the
 * code and leaves out its own close button when the content brings one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_popup_blocks_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_popup.js');
}

function _test_popup_blocks_types()
{
	return array('popup-offer', 'popup-newsletter', 'popup-announcement', 'popup-cart');
}

// The core between $from and the first $to after it.
function _test_popup_blocks_cut($js, $from, $to)
{
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

// The palette's group and the dropped trees, from Node; null without Node.
function _test_popup_blocks_drawn()
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
		. _test_popup_blocks_cut($core, '    // ── Block families in their own files', '    // ── Block stylesheet')
		. _test_popup_blocks_cut($core, '    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. _test_popup_blocks_cut($core, '    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. _test_popup_blocks_file() . "\n"
		. "var types = " . json_encode(_test_popup_blocks_types()) . ";\n"
		. "var groups = _sdBlockPaletteGroups().filter(function (g) { return g.items.some(function (it) { return it.extra.componentType === 'popup-offer'; }); });\n"
		. "var trees = {}, needs = {};\n"
		. "types.forEach(function (t) { trees[t] = _sdBlockFamilyOf(t).build(t); needs[t] = _SD_BLOCK_CSS_NEEDS[t] || []; });\n"
		. "console.log(JSON.stringify({\n"
		. "  groups: groups.map(function (g) { return [g.title, g.items.map(function (it) { return [it.label, it.extra.componentType, !!it.desc]; })]; }),\n"
		. "  trees: trees,\n"
		. "  needs: needs\n"
		. "}));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgpopupblock');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = ($code === 0 ? json_decode((string) end($out), true) : null);
}

function _test_popup_blocks_attrs($node)
{
	$out = array();
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) {
		$out[$a['name']] = $a['value'];
	}
	return $out;
}

// Every node of $tree carrying the attribute $name, depth first.
function _test_popup_blocks_marked($tree, $name)
{
	$found = array();
	if (array_key_exists($name, _test_popup_blocks_attrs($tree))) {
		$found[] = $tree;
	}
	foreach (isset($tree['children']) ? $tree['children'] : array() as $child) {
		$found = array_merge($found, _test_popup_blocks_marked($child, $name));
	}
	return $found;
}

// One group, Pop-ups, with the four blocks, each with its description.
function test_auto_dialog_popup_blocks_palette()
{
	$got = _test_popup_blocks_drawn();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same(array(array('Pop-ups', array(
		array('Pop-up: Offer Code', 'popup-offer', true),
		array('Pop-up: Newsletter', 'popup-newsletter', true),
		array('Pop-up: Announcement Bar', 'popup-announcement', true),
		array('Pop-up: Cart Waiting', 'popup-cart', true),
	))), $got['groups']);
	pg_assert_same(array('pg-popup-card', 'pg-popup-code'), $got['needs']['popup-offer']);
	pg_assert_same(array(), $got['needs']['popup-announcement'], 'the bar needs no rule of its own');
}

// Each block: a close button the dialog script reads, one main action.
function test_auto_dialog_popup_blocks_marks()
{
	$got = _test_popup_blocks_drawn();
	if ($got === null) {
		return;
	}
	foreach (_test_popup_blocks_types() as $type) {
		$tree = $got['trees'][$type];
		$closes = _test_popup_blocks_marked($tree, 'data-pg-popup-close');
		pg_assert_true(count($closes) >= 1, $type . ': a close button');
		foreach ($closes as $close) {
			pg_assert_same('button', $close['props']['tag'], $type . ': a close is a real button');
			pg_assert_same('button', _test_popup_blocks_attrs($close)['type'], $type . ': a close sends no form');
		}
		$cross = array_values(array_filter($closes, function ($n) { return strpos($n['props']['cssClass'], 'btn-close') !== false; }));
		pg_assert_same(1, count($cross), $type . ': one cross');
		pg_assert_same('Close', _test_popup_blocks_attrs($cross[0])['aria-label'], $type . ': the cross is named');
		pg_assert_contains('position-relative', $tree['props']['cssClass'], $type . ': the cross sits in the block');
		pg_assert_same(1, count(_test_popup_blocks_marked($tree, 'data-pg-popup-take')), $type . ': one main action');
	}
	$submit = _test_popup_blocks_marked($got['trees']['popup-newsletter'], 'data-pg-popup-take')[0];
	pg_assert_same('submit', _test_popup_blocks_attrs($submit)['type'], 'the newsletter is taken by its Subscribe button');
	foreach (array('popup-announcement', 'popup-cart') as $type) {
		$take = _test_popup_blocks_marked($got['trees'][$type], 'data-pg-popup-take')[0];
		pg_assert_same('a', $take['props']['tag'], $type . ': the main action is a link');
	}
}

// The offer code: the code, and the Copy button that is the main action.
function test_auto_dialog_popup_blocks_offer_code()
{
	$got = _test_popup_blocks_drawn();
	if ($got === null) {
		return;
	}
	$tree = $got['trees']['popup-offer'];
	$codes = _test_popup_blocks_marked($tree, 'data-pg-popup-code');
	pg_assert_same(1, count($codes), 'one code');
	pg_assert_same('WELCOME10', $codes[0]['props']['text']);
	pg_assert_contains('pg-popup-code', $codes[0]['props']['cssClass']);
	$copies = _test_popup_blocks_marked($tree, 'data-pg-popup-copy');
	pg_assert_same(1, count($copies), 'one Copy button');
	$a = _test_popup_blocks_attrs($copies[0]);
	pg_assert_same('Copied', $a['data-pg-popup-copied']);
	pg_assert_true(array_key_exists('data-pg-popup-take', $a), 'Copy is the main action');
	$labels = _test_popup_blocks_marked($copies[0], 'data-pg-popup-copy-label');
	pg_assert_same(1, count($labels), 'the label the script swaps');
	pg_assert_same(array('Copy', 'polite'), array($labels[0]['props']['text'], _test_popup_blocks_attrs($labels[0])['aria-live']));
}

// Printed by the site's renderer: the marks, and nothing of the blocks' own
// script, handler or style.
function test_auto_dialog_popup_blocks_printed()
{
	$got = _test_popup_blocks_drawn();
	if ($got === null) {
		return;
	}
	foreach (_test_popup_blocks_types() as $type) {
		$html = _render_tree_node($got['trees'][$type]);
		pg_assert_true((bool) preg_match('/<button\b[^>]*\sdata-pg-popup-close/', $html), $type . ': the close mark is printed');
		pg_assert_same(1, substr_count($html, 'data-pg-popup-take'), $type . ': the take mark is printed once');
		foreach (array('<script', ' onclick', ' style=') as $never) {
			pg_assert_false(stripos($html, $never) !== false, $type . ': no ' . trim($never));
		}
	}
	$offer = _render_tree_node($got['trees']['popup-offer']);
	pg_assert_true((bool) preg_match('/<span\b[^>]*data-pg-popup-code[^>]*>\s*WELCOME10\s*<\/span>/', $offer), 'the code is printed as text');
	pg_assert_contains('data-pg-popup-copied="Copied"', $offer);
	$newsletter = _render_tree_node($got['trees']['popup-newsletter']);
	pg_assert_true((bool) preg_match('/<label\b[^>]*for="(popnl_[a-z0-9]+_email)"/', $newsletter, $m), 'the email box is labelled');
	pg_assert_contains('id="' . (isset($m[1]) ? $m[1] : '?') . '"', $newsletter);
}

// The rules, read the way the site's stylesheet reads them.
function test_auto_dialog_popup_blocks_rules()
{
	$parsed = _pg_designer_block_css_parse(_test_popup_blocks_file());
	pg_assert_same(array('pg-popup-card', 'pg-popup-code'), array_keys($parsed['rules']));
	pg_assert_contains('--bs-border-style: dashed', $parsed['rules']['pg-popup-code'], 'the code frame is dashed through Bootstrap\'s border');
	pg_assert_same(array('popup-offer', 'popup-newsletter', 'popup-cart'), array_keys($parsed['needs']));
}

// The dialog script: Copy copies the code; content with its own close button
// leaves out the frame's.
function test_auto_dialog_popup_blocks_dialog_script()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	$needles = array(
		"content.querySelector('[data-pg-popup-close]')" => 'asks the content for its own close button',
		"(own_close ? ' pg-dialog-own-close' : '')" => 'marks the dialog',
		"'dialog.pg-dialog-own-close .pg-dialog-bar{display:none}'" => 'and hides its own bar',
		"event.target.closest('[data-pg-popup-copy]')" => 'a press on Copy',
		'software.popup_copy(copy, frame);' => 'copies the code',
		"root.querySelector('[data-pg-popup-code]')" => 'the code inside the pop-up',
		'navigator.clipboard.writeText(text).then(done, old_way);' => 'the Clipboard API first',
		"document.execCommand('copy')" => 'the old way without it',
		"button.getAttribute('data-pg-popup-copied')" => 'the Copied text',
	);
	foreach ($needles as $needle => $why) {
		pg_assert_contains($needle, $src, 'frontend.src.js ' . $why);
	}

	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');
	foreach (array(
		'w=!!(k&&k.querySelector&&k.querySelector("[data-pg-popup-close]"))',
		'(w?" pg-dialog-own-close":"")',
		'dialog.pg-dialog-own-close .pg-dialog-bar{display:none}',
		'var o=e.target.closest("[data-pg-popup-copy]");o&&(e.preventDefault(),software.popup_copy(o,c))',
		'popup_copy:function(t,e){var o=e.querySelector("[data-pg-popup-code]")',
		'navigator.clipboard.writeText(n).then(i,a)',
		'document.execCommand("copy")&&i()',
	) as $needle) {
		pg_assert_contains($needle, $min, 'frontend.min.js: ' . $needle);
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Variant Picker block (variant-picker, style_designer.src.js):
 * its builder and the helpers behind Variant Picker Options are cut out of
 * the designer and run under Node (skipped on a machine without Node). The
 * block draws four choices over four pictures, the first checked, each
 * label naming its radio; every pg- class it writes is in its rule; a choice
 * is added up to six and removed down to two, renumbered, unchecked and with
 * fresh ids, the first checked again when the checked one goes; the look
 * switches between colour dots and text pills; a nested picker is not
 * counted; and the same helpers work on the Phone Launch Site's colour band.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The core between $from and the first $to after it, asserted found.
function _test_vp_cut($from, $to)
{
	static $js = null;
	if ($js === null) $js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

// The Phone Launch Site home page's colour band (the first pg-variant section).
function _test_vp_launch_band()
{
	$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch.php');
	$find = function ($node) use (&$find) {
		if (!is_array($node)) return null;
		if (isset($node['props']['cssClass']) && preg_match('/(?<![\w-])pg-variant(?![\w-])/', $node['props']['cssClass'])) return $node;
		foreach (isset($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
			$hit = $find($child);
			if ($hit) return $hit;
		}
		return null;
	};
	foreach ($tpl['pages'] as $p) {
		if ($p['key'] === 'home') return $find($p['tree']);
	}
	return null;
}

function _test_vp_run()
{
	static $got = false;
	if ($got !== false) return $got;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return $got = null;

	$band = _test_vp_launch_band();
	pg_assert_true(is_array($band), 'the launch home page has a Variant Picker band');
	$script = "'use strict';\n"
		. "function _sdT(key, vars) { return vars === undefined ? key : String(key).replace('{var}', vars); }\n"
		. "var _ids = 0;\n"
		. "function gid() { return 'sd_' + (++_ids); }\n"
		. "function cloneTree(n) { return JSON.parse(JSON.stringify(n)); }\n"
		// createNode as the designer's: an id attribute becomes props.id.
		. "function createNode(type, props, children) { var p = props || {};\n"
		. "  if (p._attrs) p._attrs = p._attrs.filter(function (a) { if (a.name === 'id') { p.id = a.value; return false; } return true; });\n"
		. "  return { _id: gid(), type: type, props: p, children: children || [] }; }\n"
		. _test_vp_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. _test_vp_cut('    var _SD_BLOCK_CSS = {', "\n    };") . "\n    };\n"
		. _test_vp_cut('    var _SD_BLOCK_CSS_NEEDS = {', "\n    };") . "\n    };\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. "function walk(n, f) { f(n); (n.children || []).forEach(function (c) { walk(c, f); }); }\n"
		. "function attr(n, name) { var a = ((n && n.props._attrs) || []).filter(function (x) { return x.name === name; })[0]; return a ? a.value : null; }\n"
		. "function state(root) {\n"
		. "  var p = _sdVariantParts(root), ids = [], classes = [];\n"
		. "  walk(root, function (x) { ids.push(x._id); classes.push(x.props.cssClass || ''); });\n"
		. "  return {\n"
		. "    picks: p.choices.map(function (c) {\n"
		. "      var r = _sdVariantRadio(c.node), label = _sdVariantFind(c.node, function (x) { return x.props.tag === 'label'; });\n"
		. "      var text = _sdVariantFind(label, function (x) { return x !== label && typeof x.props.text === 'string'; });\n"
		. "      return { cls: r.props.cssClass, id: r.props.id || attr(r, 'id'), name: attr(r, 'name'), checked: attr(r, 'checked') !== null,\n"
		. "        forId: attr(label, 'for'), text: text ? text.props.text : null, custom: c.node.props.customName || c.node.props._label || null };\n"
		. "    }),\n"
		. "    shots: p.shots.map(function (s) { var img = _sdVariantFind(s.node, function (x) { return typeof x.props.alt === 'string'; });\n"
		. "      return { cls: s.node.props.cssClass, alt: img ? img.props.alt : null, kids: (s.node.children || []).length }; }),\n"
		. "    ids: ids, classes: classes.join(' ')\n"
		. "  };\n"
		. "}\n"
		. "var calls = 0; function before() { calls++; }\n"
		. "var out = {};\n"
		. "var vp = _sdVariantPicker();\n"
		. "out.tag = vp.props.tag; out.cls = vp.props.cssClass; out.look = _sdVariantLook(vp); out.built = state(vp);\n"
		. "out.rule = _SD_BLOCK_CSS['pg-variant']; out.needs = _SD_BLOCK_CSS_NEEDS['variant-picker'];\n"
		. "out.dots = []; walk(vp, function (x) { if (/(^|\s)pg-variant-dot(\s|$)/.test(x.props.cssClass || '')) out.dots.push([attr(x, 'aria-hidden'), attr(x, 'style')]); });\n"
		. "out.add = [_sdVariantAddChoice(vp, before), _sdVariantAddChoice(vp, before), _sdVariantAddChoice(vp, before)];\n"
		. "out.addCalls = calls; out.six = state(vp);\n"
		. "calls = 0; out.remove = [];\n"
		. "for (var i = 0; i < 5; i++) { out.remove.push(_sdVariantRemoveChoice(vp, before)); if (i === 0) out.five = state(vp); }\n"
		. "out.removeCalls = calls; out.two = state(vp);\n"
		// The checked choice is the last one removed: the first takes the check.
		. "var vp2 = _sdVariantPicker(), p2 = _sdVariantParts(vp2);\n"
		. "var r1 = _sdVariantRadio(p2.choices[0].node); r1.props._attrs = r1.props._attrs.filter(function (a) { return a.name !== 'checked'; });\n"
		. "_sdVariantRadio(p2.choices[3].node).props._attrs.push({ name: 'checked', value: 'checked' });\n"
		. "out.removeChecked = _sdVariantRemoveChoice(vp2); out.afterChecked = state(vp2);\n"
		. "calls = 0; var vp3 = _sdVariantPicker();\n"
		. "out.looks = [_sdVariantSetLook(vp3, 'pills', before), vp3.props.cssClass, _sdVariantLook(vp3), _sdVariantSetLook(vp3, 'pills', before),\n"
		. "  _sdVariantSetLook(vp3, 'grid', before), _sdVariantSetLook(vp3, 'dots', before), vp3.props.cssClass];\n"
		. "out.lookCalls = calls;\n"
		. "var vp4 = _sdVariantPicker(); vp4.children[0].children.push(_sdVariantPicker());\n"
		. "out.nested = _sdVariantParts(vp4).choices.length;\n"
		. "var band = " . json_encode($band, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n"
		. "out.bandLook = _sdVariantLook(band); out.bandBefore = state(band);\n"
		. "out.bandAdd = _sdVariantAddChoice(band); out.band = state(band);\n"
		. "out.bandRemove = [_sdVariantRemoveChoice(band), _sdVariantRemoveChoice(band), _sdVariantRemoveChoice(band), _sdVariantRemoveChoice(band)];\n"
		. "out.bandTwo = state(band);\n"
		. "console.log(JSON.stringify(out));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgvariant');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function _test_vp_column($picks, $field)
{
	$col = array();
	foreach ($picks as $p) $col[] = $p[$field];
	return $col;
}

function test_designer_block_variant_picker_draws()
{
	$got = _test_vp_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$b = $got['built'];
	pg_assert_same('section', $got['tag'], 'a section');
	pg_assert_same('py-5 text-center pg-variant pg-variant-dots', $got['cls'], 'colour dots by default');
	pg_assert_same('dots', $got['look'], 'read as the dots look');
	pg_assert_same(array('btn-check pg-variant-pick pg-variant-pick-1', 'btn-check pg-variant-pick pg-variant-pick-2', 'btn-check pg-variant-pick pg-variant-pick-3', 'btn-check pg-variant-pick pg-variant-pick-4'),
		_test_vp_column($b['picks'], 'cls'), 'four numbered radios');
	pg_assert_same(array(true, false, false, false), _test_vp_column($b['picks'], 'checked'), 'the first is checked');
	pg_assert_same(_test_vp_column($b['picks'], 'id'), _test_vp_column($b['picks'], 'forId'), 'each label names its radio');
	pg_assert_same(4, count(array_unique(_test_vp_column($b['picks'], 'id'))), 'four radio ids');
	pg_assert_same(1, count(array_unique(_test_vp_column($b['picks'], 'name'))), 'one radio group');
	pg_assert_same(array('Graphite', 'Sand', 'Mist Blue', 'Rose'), _test_vp_column($b['picks'], 'text'), 'each choice is named');
	pg_assert_same(4, count($b['shots']), 'a picture per choice');
	foreach ($b['shots'] as $i => $s) {
		pg_assert_contains('pg-variant-shot pg-variant-shot-' . ($i + 1), $s['cls'], 'picture ' . ($i + 1) . ' is numbered');
	}
	pg_assert_same('Shown in Graphite', $b['shots'][0]['alt'], 'the picture says its choice');
	pg_assert_same(4, count($got['dots']), 'a dot per choice');
	foreach ($got['dots'] as $d) {
		pg_assert_same('true', $d[0], 'the dot is hidden from screen readers: the name says the colour');
		pg_assert_true((bool)preg_match('/^background-color: #[0-9a-f]{6};$/', (string)$d[1]), 'the dot\'s colour is in the Background control\'s store');
	}
	pg_assert_same(array('pg-variant'), $got['needs'], 'the block needs its rule');
	preg_match_all('/(?<![\w-])pg-[a-z0-9-]*[a-z0-9](?![\w-])/', $b['classes'], $m);
	foreach (array_unique($m[0]) as $class) {
		pg_assert_true((bool)preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $got['rule']), "$class is in the pg-variant rule");
	}
	pg_assert_false((bool)preg_match('/(?<![\w-])(text|bg)-(white|black|dark|light)(?![\w-])/', $b['classes']), 'no fixed colour class: the block follows light and dark');
	for ($n = 1; $n <= 6; $n++) {
		pg_assert_contains('.pg-variant:has(.pg-variant-pick-' . $n . ':checked) .pg-variant-shot-' . $n, $got['rule'], "choice $n shows picture $n");
	}
	// The canvas wraps an image as .sd-wrap[data-content-type="image"] > img
	// { display: block } (two classes and an element): the hiding rule outweighs it.
	pg_assert_contains('.pg-variant.pg-variant .pg-variant-shot { display: none;', $got['rule'], 'the hiding rule outweighs the canvas image rule');
	pg_assert_contains('@supports not selector(:has(a)) { .pg-variant.pg-variant .pg-variant-shot-1 { display: block; } }', $got['rule'], 'the fallback outweighs the hiding rule');
	pg_assert_contains('{ .pg-variant.pg-variant .pg-variant-shot { animation: none; } }', $got['rule'], 'reduced motion outweighs the hiding rule');
}

function test_designer_block_variant_picker_add_and_remove()
{
	$got = _test_vp_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same(array(true, true, false), $got['add'], 'choices are added up to six');
	pg_assert_same(2, $got['addCalls'], 'the undo snapshot is taken only when a choice is added');
	$six = $got['six'];
	pg_assert_same(6, count($six['picks']), 'six choices');
	pg_assert_same(6, count($six['shots']), 'six pictures');
	pg_assert_same(count($six['ids']), count(array_unique($six['ids'])), 'every node keeps an id of its own');
	$five = $six['picks'][4];
	pg_assert_same('btn-check pg-variant-pick pg-variant-pick-5', $five['cls'], 'the copy is renumbered');
	pg_assert_false($five['checked'], 'the copy is not checked');
	pg_assert_same($five['id'], $five['forId'], 'its label names its radio');
	pg_assert_same($six['picks'][0]['name'] . '-5', $five['id'], 'its id is the group and its number');
	pg_assert_same($six['picks'][0]['name'], $five['name'], 'it joins the group');
	pg_assert_same(array('Choice 5', 'Choice 5'), array($five['text'], $five['custom']), 'its name and layer name are its number');
	pg_assert_contains('pg-variant-shot-6', $six['shots'][5]['cls'], 'the sixth picture is numbered');
	pg_assert_false(strpos($six['shots'][5]['cls'], 'pg-variant-shot-4') !== false, 'the copied number is gone');
	pg_assert_same('Shown in Choice 6', $six['shots'][5]['alt'], 'the copied picture says its choice');
	pg_assert_same(array(true, false, false, false, false, false), _test_vp_column($six['picks'], 'checked'), 'still one checked radio, the first');

	pg_assert_same(5, count($got['five']['picks']), 'the last choice goes');
	pg_assert_same(5, count($got['five']['shots']), 'with its picture');
	pg_assert_same(array(true, true, true, true, false), $got['remove'], 'choices are removed down to two');
	pg_assert_same(4, $got['removeCalls'], 'the undo snapshot is taken only when a choice is removed');
	pg_assert_same(2, count($got['two']['picks']), 'two choices stay');
	pg_assert_same(2, count($got['two']['shots']), 'two pictures stay');
	pg_assert_true($got['removeChecked'], 'a checked last choice is removed');
	pg_assert_same(array(true, false, false), _test_vp_column($got['afterChecked']['picks'], 'checked'), 'the first takes the check');
	pg_assert_same(4, $got['nested'], 'a picker nested inside another is not counted as its choices');
}

function test_designer_block_variant_picker_look()
{
	$got = _test_vp_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same(array(true, 'py-5 text-center pg-variant pg-variant-pills', 'pills', false, false, true, 'py-5 text-center pg-variant pg-variant-dots'),
		$got['looks'], 'dots and pills switch; the same look again and an unknown one change nothing');
	pg_assert_same(2, $got['lookCalls'], 'the undo snapshot is taken only on a change');
}

function test_designer_block_variant_picker_launch_band()
{
	$got = _test_vp_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('dots', $got['bandLook'], 'the launch band shows colour dots');
	pg_assert_same(4, count($got['bandBefore']['picks']), 'four finishes');
	pg_assert_true($got['bandAdd'], 'a finish is added to the launch band');
	$five = $got['band']['picks'][4];
	pg_assert_same('btn-check pg-variant-pick pg-variant-pick-5', $five['cls'], 'renumbered');
	pg_assert_same(array('lx-colour', 'lx-colour-5', 'lx-colour-5', false), array($five['name'], $five['id'], $five['forId'], $five['checked']), 'in the template\'s group, named by its label, unchecked');
	pg_assert_same(array('Choice 5', 'Choice 5'), array($five['text'], $five['custom']), 'the template\'s name span and label are renamed');
	pg_assert_contains('pg-variant-shot pg-variant-shot-5', $got['band']['shots'][4]['cls'], 'the figure is copied and renumbered');
	pg_assert_same('Shown in Choice 5', $got['band']['shots'][4]['alt'], 'the picture inside the figure says its choice');
	pg_assert_same($got['bandBefore']['shots'][3]['kids'], $got['band']['shots'][4]['kids'], 'the copy keeps the caption');
	pg_assert_same(array(true, true, true, false), $got['bandRemove'], 'finishes are removed down to two');
	pg_assert_same(2, count($got['bandTwo']['shots']), 'with their pictures');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the background layer model (assets/js/pg_bg_layers.js): a
 * background read as a list of colour, gradient and picture layers, the
 * list written back, palette colours, stops, the order, a background no
 * list reads. Run under Node; skipped on a machine without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as L; returns what it prints as JSON, null
// without Node.
function designer_bg_layers_run($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgbgl');

	if ($file === false) {
		return null;
	}

	file_put_contents($file,
		'var L = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_bg_layers.js') . ");\n" . $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		pg_assert_false(true, implode("\n", $out));

		return null;
	}

	return json_decode(implode("\n", $out), true);
}

function test_designer_bg_layers_read_and_write()
{
	$r = designer_bg_layers_run(
		'var three = L.read({ "background-image": "linear-gradient(180deg, rgba(20,38,31,0), rgba(20,38,31,.7)), url(\"/files/hero.jpg\"), linear-gradient(var(--bs-primary), var(--bs-primary))",'
		. ' "background-size": "auto, cover, auto", "background-position": "0% 0%, center, 0% 0%" });'
		. 'var old = L.read({ "background-image": "linear-gradient(135deg, #667eea, 30%, #764ba2)", "background-color": "#ffffff" });'
		. 'var pic = L.read({ "background-image": "url(a.png)", "background-repeat": "no-repeat" });'
		. 'var sh = L.read({ "background": "url(\'b.jpg\')" });'
		. 'console.log(JSON.stringify({ three: three, back: L.write(three.layers), old: old, oldBack: L.write(old.layers), pic: pic.layers[0], sh: sh,'
		. ' none: L.read({ "background-image": "none" }), empty: L.write([]),'
		. ' hand: [L.read({ "background-image": "repeating-linear-gradient(red, blue 10px)" }), L.read({ "background": "#fff url(a.png) no-repeat" }),'
		. ' L.read({ "background-image": "radial-gradient(closest-side, red, blue)" }), L.read({ "background-image": "linear-gradient(red 10% 20%, blue)" })] }));'
	);

	if ($r === null) {
		return;
	}

	$l = $r['three']['layers'];
	pg_assert_same(3, count($l), 'three layers, the first in front');
	pg_assert_same('gradient', $l[0]['kind']);
	pg_assert_same(array('v' => '#14261f', 'a' => 70), $l[0]['stops'][1]['c'], 'an rgba stop reads as a colour and its opacity');
	pg_assert_same('picture', $l[1]['kind']);
	pg_assert_same('/files/hero.jpg', $l[1]['url']);
	pg_assert_same('cover', $l[1]['size']);
	pg_assert_same('center center', $l[1]['position'], 'a place reads as one of the nine');
	pg_assert_same('', $l[0]['position'], 'a value equal to CSS\'s own is not kept');
	pg_assert_same(array('kind' => 'color', 'c' => array('p' => 'primary', 'a' => 100)), $l[2], 'a gradient of one colour twice is a colour layer');
	pg_assert_false($r['three']['legacy']);

	pg_assert_same('linear-gradient(180deg, rgba(20, 38, 31, 0) 0%, rgba(20, 38, 31, 0.7) 100%), url("/files/hero.jpg"), linear-gradient(var(--bs-primary), var(--bs-primary))', $r['back']['background-image']);
	pg_assert_same('auto, cover, auto', $r['back']['background-size'], 'the whole list once one layer needs it');
	pg_assert_same('0% 0%, center center, 0% 0%', $r['back']['background-position']);
	pg_assert_same('', $r['back']['background-repeat'], 'a property no layer needs is removed');
	pg_assert_same('', $r['back']['background-color'], 'the old custom colour goes once the list is written');

	pg_assert_true($r['old']['legacy'], 'a background made before');
	pg_assert_same(30, $r['old']['layers'][0]['stops'][1]['hint'], 'the middle of a two-colour gradient is kept');
	pg_assert_same(135, $r['old']['layers'][0]['angle']);
	pg_assert_same(array('kind' => 'color', 'c' => array('v' => '#ffffff', 'a' => 100), 'legacy' => true), $r['old']['layers'][1], 'the custom colour is the layer under');
	pg_assert_same('linear-gradient(135deg, #667eea 0%, 30%, #764ba2 100%), linear-gradient(#ffffff, #ffffff)', $r['oldBack']['background-image']);

	pg_assert_same('no-repeat', $r['pic']['repeat']);
	pg_assert_same('b.jpg', $r['sh']['layers'][0]['url'], 'a shorthand of one picture is read');
	pg_assert_same(array(), $r['none']['layers']);
	pg_assert_same('', $r['empty']['background-image']);
	pg_assert_same(array(null, null, null, null), $r['hand'], 'a background no list reads is the hand\'s');
}

function test_designer_bg_layers_gradients_and_colours()
{
	$r = designer_bg_layers_run(
		'var g = { type: "conic", from: 90, at: "left top", stops: [{ c: { p: "primary", a: 45 }, pos: 0 }, { c: { v: "#ffffff", a: 50 }, pos: 55 }, { c: { v: "red" }, pos: 100 }] };'
		. 'var rad = L.parseImage("radial-gradient(circle at 72% 30%, #f3e3c2 0, rgba(243,227,194,0) 42%)");'
		. 'var to = L.parseImage("linear-gradient(to top right, red, green, blue)");'
		. 'console.log(JSON.stringify({ conic: L.gradient(g), radial: L.gradient({ type: "radial", shape: "circle", at: "center center", stops: g.stops }),'
		. ' rad: rad, to: to, colors: [L.color({ p: "body-bg" }), L.color({ p: "dark", a: 20 }), L.color({ v: "#1f6f54", a: 45 }), L.color({ v: "hsl(0 0% 0%)", a: 50 })],'
		. ' parsed: [L.parseColor("rgba(var(--bs-dark-rgb), 0.2)"), L.parseColor("var(--bs-success)"), L.parseColor("var(--bs-indigo)"), L.parseColor("color-mix(in srgb, hsl(0 0% 0%) 50%, transparent)")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('conic-gradient(from 90deg at left top, rgba(var(--bs-primary-rgb), 0.45) 0%, rgba(255, 255, 255, 0.5) 55%, red 100%)', $r['conic'], 'three stops, a palette stop as the theme\'s variable');
	pg_assert_same('radial-gradient(circle, rgba(var(--bs-primary-rgb), 0.45) 0%, rgba(255, 255, 255, 0.5) 55%, red 100%)', $r['radial']);
	pg_assert_same('circle', $r['rad']['shape']);
	pg_assert_same('72% 30%', $r['rad']['at'], 'a centre not one of the nine is kept as written');
	pg_assert_same(array('v' => '#f3e3c2', 'a' => 100), $r['rad']['stops'][0]['c'], 'a stop at 0 without a unit');
	pg_assert_same(45, $r['to']['angle'], 'a side reads as its angle');
	pg_assert_same(50, $r['to']['stops'][1]['pos'], 'a stop without a place is spread');
	pg_assert_same(array('var(--bs-body-bg)', 'rgba(var(--bs-dark-rgb), 0.2)', 'rgba(31, 111, 84, 0.45)', 'color-mix(in srgb, hsl(0 0% 0%) 50%, transparent)'), $r['colors']);
	pg_assert_same(array('p' => 'dark', 'a' => 20), $r['parsed'][0]);
	pg_assert_same(array('p' => 'success', 'a' => 100), $r['parsed'][1]);
	pg_assert_same(array('v' => 'var(--bs-indigo)', 'a' => 100), $r['parsed'][2], 'a variable outside the palette is a custom colour');
	pg_assert_same(array('v' => 'hsl(0 0% 0%)', 'a' => 50), $r['parsed'][3]);
}

function test_designer_bg_layers_order_and_stops()
{
	$r = designer_bg_layers_run(
		'var stops = [{ c: { v: "#000000", a: 100 }, pos: 0 }, { c: { v: "#ffffff", a: 0 }, pos: 20 }, { c: { p: "primary", a: 100 }, pos: 100 }];'
		. 'var rgb = function (c) { return c.p ? null : L.hexRgb(c.v).concat([c.a]); };'
		. 'console.log(JSON.stringify({ move: [L.move(["a", "b", "c"], 0, 2), L.move(["a", "b", "c"], 2, 0), L.move(["a", "b"], 0, 5)],'
		. ' widest: L.widest(stops), mid: L.mixAt(stops, 10, rgb), near: L.mixAt(stops, 70, rgb), edge: L.mixAt(stops, 0, rgb),'
		. ' places: [L.place("center"), L.place("top"), L.place("100% 0%"), L.place("bottom left"), L.place("10px 20px")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('b', 'c', 'a'), array('c', 'a', 'b'), array('a', 'b')), $r['move']);
	pg_assert_same(60, $r['widest'], 'Enter adds a stop in the widest gap');
	pg_assert_same(array('v' => '#808080', 'a' => 50), $r['mid'], 'a stop pressed on the strip takes the colour found there');
	pg_assert_same(array('p' => 'primary', 'v' => null, 'a' => 100), array('p' => $r['near']['p'], 'v' => isset($r['near']['v']) ? $r['near']['v'] : null, 'a' => $r['near']['a']), 'without both colours, the nearer stop\'s');
	pg_assert_same('#000000', $r['edge']['v']);
	pg_assert_same(array('center center', 'center top', 'right top', 'left bottom', '10px 20px'), $r['places']);
}

// A picture with no position of its own sits where CSS puts it, the top
// left corner, and the nine places show that corner; choosing it and
// reading the style back shows the same corner, not the middle.
function test_designer_bg_layers_position_shown_at_css_default()
{
	$r = designer_bg_layers_run(
		'var back = L.read(L.write([{ kind: "picture", url: "a.jpg", size: "cover", position: "left top" }])).layers[0];'
		. 'console.log(JSON.stringify({ none: L.shownPlace(""), back: back.position, shown: L.shownPlace(back.position),'
		. ' set: L.shownPlace("right bottom"), pct: L.shownPlace("50% 100%"), own: L.shownPlace("30% 20%") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('left top', $r['none'], 'no position is CSS\'s top left');
	pg_assert_same('', $r['back'], 'the default is not written');
	pg_assert_same('left top', $r['shown'], 'the chosen top left shows after the write');
	pg_assert_same('right bottom', $r['set']);
	pg_assert_same('center bottom', $r['pct']);
	pg_assert_same('30% 20%', $r['own'], 'a place none of the nine is kept as it is');
}

// The Repeat list: a layer with no repeat of its own repeats, as CSS
// draws it, and "No repeat" ('') writes no-repeat on a picture and on a
// gradient alike.
function test_designer_bg_layers_repeat_row()
{
	$r = designer_bg_layers_run(
		'var pic = L.read({ "background-image": "url(a.jpg)", "background-size": "contain" }).layers[0];'
		. 'var g = { kind: "gradient", type: "linear", angle: 90, stops: [{ c: { v: "#000000" }, pos: 0 }, { c: { v: "#ffffff" }, pos: 100 }] };'
		. 'g.repeat = L.repeatOf("");'
		. 'console.log(JSON.stringify({ pic: L.shownRepeat(pic), none: L.shownRepeat({ kind: "picture", repeat: "no-repeat" }),'
		. ' x: L.shownRepeat({ kind: "gradient", repeat: "repeat-x" }), grad: L.shownRepeat(g), of: [L.repeatOf(""), L.repeatOf("repeat-y")],'
		. ' written: L.write([g])["background-repeat"] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('repeat', $r['pic'], 'a picture with no repeat set repeats');
	pg_assert_same('', $r['none'], 'no-repeat is the "No repeat" line');
	pg_assert_same('repeat-x', $r['x']);
	pg_assert_same('', $r['grad'], '"No repeat" stays chosen on a gradient');
	pg_assert_same(array('no-repeat', 'repeat-y'), $r['of']);
	pg_assert_same('no-repeat', $r['written'], 'a gradient\'s "No repeat" is written');
}

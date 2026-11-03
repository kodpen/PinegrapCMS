<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's icons and emoji (the Sticker tool's glyph
 * layers): the emoji set and its labels, the icon search, the recent emoji,
 * the characters a glyph draws and its size (run under Node), the icon font
 * and map the config points at, and the part's place in the bundle.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_glyph_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieglyph');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Eight categories, each labelled in the glyph part, every emoji once.
function test_image_editor_glyph_emoji_set()
{
	$r = image_editor_glyph_node(
		'var all = []; M.EMOJI.order.forEach(function (k) { all = all.concat(M.EMOJI.categories[k]); });'
		. 'console.log(JSON.stringify({ order: M.EMOJI.order, sizes: M.EMOJI.order.map(function (k) { return M.EMOJI.categories[k].length; }),'
		. ' total: all.length, unique: all.filter(function (e, i) { return all.indexOf(e) === i; }).length,'
		. ' short: all.every(function (e) { return typeof e === "string" && e.length > 0 && e.length <= 16; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('smileys', 'people', 'nature', 'food', 'travel', 'activities', 'objects', 'symbols'), $r['order']);
	pg_assert_same($r['total'], $r['unique'], 'every emoji once');
	pg_assert_true($r['short'] === true, 'every emoji is a short string');

	foreach ($r['sizes'] as $size) {
		pg_assert_true($size >= 24, 'a category holds at least 24 emoji');
	}

	$strings = pg_image_editor_strings_glyph();

	foreach ($r['order'] as $key) {
		pg_assert_true(isset($strings['emojiCat_' . $key]), 'category ' . $key . ' is labelled');
	}

	pg_assert_true(isset($strings['layer_glyph']), 'the panel title of a selected glyph');
}

// Names starting with the query come first, spaces read as dashes, any
// case; the limit cuts the list and found counts every match.
function test_image_editor_glyph_icon_search()
{
	$r = image_editor_glyph_node(
		'var map = { "bell-alarm": 2, "alarm": 1, "alarm-fill": 3, "x": 4, "broken": "" };'
		. 'console.log(JSON.stringify({'
		. ' alarm: M.iconSearch(map, "ALARM", 0),'
		. ' dash: M.iconSearch(map, "alarm fill", 10).icons.map(function (i) { return i.name; }),'
		. ' cut: M.iconSearch(map, "", 2),'
		. ' none: M.iconSearch(map, "zzz", 5).found,'
		. ' nomap: M.iconSearch(null, "a", 5).found }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('name' => 'alarm', 'code' => 1), array('name' => 'alarm-fill', 'code' => 3), array('name' => 'bell-alarm', 'code' => 2)), $r['alarm']['icons'], 'prefix matches first, by name');
	pg_assert_same(3, $r['alarm']['found']);
	pg_assert_same(array('alarm-fill'), $r['dash'], 'a space reads as a dash');
	pg_assert_same(2, count($r['cut']['icons']), 'the limit');
	pg_assert_same(4, $r['cut']['found'], 'found counts every icon with a code point');
	pg_assert_same(0, $r['none']);
	pg_assert_same(0, $r['nomap']);
}

function test_image_editor_glyph_recent_text_and_size()
{
	$r = image_editor_glyph_node(
		'console.log(JSON.stringify({'
		. ' recent: M.emojiRecent(["😀", "🎉", 5, ""], "🎉"),'
		. ' eight: M.emojiRecent(["1", "2", "3", "4", "5", "6", "7", "8"], "9"),'
		. ' bad: M.emojiRecent("x", null),'
		. ' bi: M.glyphText({ set: "bi", code: 61697 }),'
		. ' biBad: M.glyphText({ set: "bi", code: -1 }),'
		. ' emoji: M.glyphText({ set: "emoji", code: "🔥" }),'
		. ' other: M.glyphText({ set: "font", code: "a" }),'
		. ' size: M.glyphSize({ w: 120, h: 80 }),'
		. ' upright: M.mapLayer({ type: "glyph", x: 0, y: 0, w: 10, h: 20, rot: 0 }, function (x, y) { return [y, x]; }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('🎉', '😀'), $r['recent'], 'newest first, each once, junk dropped');
	pg_assert_same(array('9', '1', '2', '3', '4', '5', '6', '7'), $r['eight'], 'at most eight');
	pg_assert_same(array(), $r['bad']);
	pg_assert_same(json_decode('""'), $r['bi'], 'an icon draws its code point');
	pg_assert_same('', $r['biBad']);
	pg_assert_same('🔥', $r['emoji']);
	pg_assert_same('', $r['other']);
	pg_assert_same(80, $r['size'], 'the box\'s shorter side');
	pg_assert_same(array(10, 20), array($r['upright']['w'], $r['upright']['h']), 'a glyph stays upright under a flip: its size is kept');
}

// The config points at the font and map the panel ships, and the map gives
// code points in the font's private use range.
function test_image_editor_glyph_icon_files()
{
	$dir = PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/';

	pg_assert_true(is_file($dir . 'fonts/bootstrap-icons.woff2'), 'the icon font ships');

	$map = json_decode((string) file_get_contents($dir . 'bootstrap-icons.json'), true);

	pg_assert_true(is_array($map) && (count($map) > 1000), 'the icon map ships');
	pg_assert_true(isset($map['star-fill']) && ($map['star-fill'] >= 0xf000) && ($map['star-fill'] <= 0xf8ff), 'a code point in the private use range');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

	pg_assert_contains("'icons'   => pg_image_editor_icon_urls()", $source, 'the config carries the icons');
	pg_assert_contains("fonts/bootstrap-icons.woff2?v=", $source);
	pg_assert_contains("bootstrap-icons.json?v=", $source);
}

// The glyph part is bundled last and hooks the Sticker panel.
function test_image_editor_glyph_bundled()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	pg_assert_same('assets/js/pg_image_editor_glyph.js', end($files), 'after the sticker part, whose panel it wraps');
	pg_assert_true(in_array('glyph', pg_image_editor_parts(), true), 'a part with its strings');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_glyph.js');

	pg_assert_contains('var stickerPanel = Editor.panels.sticker;', $js);
	pg_assert_contains('Editor.layerTypes.glyph = {', $js);
}

// Change: a placed sticker, icon or emoji takes another one in its place.
// Turn, opacity, motion and what the person changed stay; the pick brings its
// own look and proportions, fitted in the old box about the same centre.
function test_image_editor_glyph_swap()
{
	$r = image_editor_glyph_node(
		'var motion = { in: { kind: "fade", ms: 400 } };'
		. 'var PILL = { type: "sticker", kind: "pill", label: "YENI", fill: "#1e88e5", ink: "#ffffff", w: 18, h: 7 };'
		. 'var HEART = { type: "sticker", kind: "heart", label: "", fill: "#e53935", ink: "#ffffff", w: 10, h: 9 };'
		. 'var STAR = { type: "sticker", kind: "star", label: "", fill: "#fdd835", ink: "#1a1c1e", w: 10, h: 10 };'
		. 'var ICON = { type: "glyph", set: "bi", code: 0xf586 }, SMILE = { type: "glyph", set: "emoji", code: "\u{1F600}" };'
		. 'var emoji = { id: "e1", type: "glyph", set: "emoji", code: "\u{1F600}", x: 100, y: 100, w: 60, h: 60, rot: 15, opacity: 0.8, motion: motion, locked: false };'
		. 'var burst = { id: "b1", type: "sticker", kind: "burst", label: "%20", color: "#e53935", ink: "#ffffff", x: 0, y: 0, w: 50, h: 50, rot: 0, opacity: 1 };'
		. 'var typed = Object.assign({}, burst, { label: "-50%", color: "#8e24aa" });'
		. 'var icon = M.swapGlyph(emoji, ICON), back = M.swapGlyph(icon, SMILE);'
		. 'var redIcon = Object.assign({}, icon, { color: "#e53935" });'
		. 'var pillFromEmoji = M.swapGlyph(emoji, PILL);'
		. 'console.log(JSON.stringify({ emoji: emoji, icon: icon, back: back, star: M.swapGlyph(emoji, STAR),'
		. ' pill: M.swapGlyph(burst, PILL, "%20"), typed: M.swapGlyph(typed, PILL, "%20"),'
		. ' heart: M.swapGlyph(redIcon, HEART), plainHeart: M.swapGlyph(icon, HEART), emojiPill: pillFromEmoji, emojiAgain: M.swapGlyph(pillFromEmoji, SMILE),'
		. ' burstIcon: M.swapGlyph(burst, ICON, "%20"), typedIcon: M.swapGlyph(typed, ICON, "%20"),'
		. ' bad: [M.swapGlyph({ type: "text" }, ICON), M.swapGlyph(burst, { type: "glyph", set: "bi", code: 0 }), M.swapGlyph(burst, { type: "sticker" }), M.swapGlyph(burst, null)],'
		. ' same: emoji.code === "\u{1F600}" && burst.kind === "burst" }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['same'], 'the layer given is not changed');

	// Same proportions: the box and every other field stay.
	foreach (array('icon', 'back', 'star') as $name) {
		foreach (array('id', 'x', 'y', 'w', 'h', 'rot', 'opacity', 'motion', 'locked') as $field) {
			pg_assert_same($r['emoji'][$field], $r[$name][$field], $name . ' keeps ' . $field);
		}
	}

	pg_assert_same(array('glyph', 'bi', 0xf586, '#212529'), array($r['icon']['type'], $r['icon']['set'], $r['icon']['code'], $r['icon']['color']), 'an emoji becomes a dark icon');
	pg_assert_same('emoji', $r['back']['set'], 'and back to an emoji');
	pg_assert_same(array('sticker', 'star', '#fdd835'), array($r['star']['type'], $r['star']['kind'], $r['star']['color']), 'an emoji takes the star\'s fill');
	pg_assert_false(isset($r['star']['set']) || isset($r['star']['code']), 'the glyph fields go');

	// A sticker for a sticker brings its own look unless the person changed it.
	pg_assert_same(array('pill', 'YENI', '#1e88e5', '#ffffff'), array($r['pill']['kind'], $r['pill']['label'], $r['pill']['color'], $r['pill']['ink']), 'burst to pill takes the pill\'s label, fill and ink');
	pg_assert_same(array('-50%', '#8e24aa'), array($r['typed']['label'], $r['typed']['color']), 'a typed label and a chosen colour stay');
	pg_assert_same('#e53935', $r['heart']['color'], 'an icon coloured red stays red as a heart');
	pg_assert_same('#e53935', $r['plainHeart']['color'], 'a default icon takes the heart\'s fill');
	pg_assert_same('#212529', $r['burstIcon']['color'], 'a burst in its own colour becomes a dark icon');
	pg_assert_same('#8e24aa', $r['typedIcon']['color'], 'a chosen colour stays on the icon');
	pg_assert_false(isset($r['burstIcon']['kind']) || isset($r['burstIcon']['label']) || isset($r['burstIcon']['ink']), 'the sticker fields go');

	// Proportions: square to pill, pill to emoji, about the same centre.
	$p = $r['emojiPill'];
	pg_assert_true(abs($p['w'] - 60) < 1e-6 && abs($p['w'] / $p['h'] - 18 / 7) < 1e-6, 'the pill fits the square\'s width in its own proportions');
	pg_assert_true(abs($p['x'] + $p['w'] / 2 - 130) < 1e-6 && abs($p['y'] + $p['h'] / 2 - 130) < 1e-6, 'about the same centre');
	$e = $r['emojiAgain'];
	pg_assert_true(abs($e['w'] - $p['h']) < 1e-6 && abs($e['h'] - $p['h']) < 1e-6, 'the pill back to an emoji is a square of its height');
	pg_assert_true(abs($e['x'] + $e['w'] / 2 - 130) < 1e-6 && abs($e['y'] + $e['h'] / 2 - 130) < 1e-6, 'about the same centre');
	pg_assert_same(15, $e['rot'], 'the turn stays');

	pg_assert_same(array(null, null, null, null), $r['bad'], 'only a sticker or glyph, only a real pick');
}

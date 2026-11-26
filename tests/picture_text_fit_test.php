<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a translated picture text's room and growth: the editor's
 * M.languageRoom(), M.languagePlace(), M.languageFitted() and their PHP
 * twins in includes/fn/picture_server_draw.php, run on the same fixtures
 * (tests/fixtures/picture_language/room.json) with a stub layout of half
 * the size a letter and 1.25 the size a line. The measure
 * (fixtures/picture_language/measure.json) holds that no text ends smaller
 * than the old rule (the source's box) made it, and fewer layouts end
 * "Look by hand".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (is_file(PG_FUNCTIONS_DIR . '/includes/fn/picture_server_draw.php')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/picture_server_draw.php');
}

// The stub layout's numbers may differ by a rounding between the twins.
const PICTURE_FIT_TOLERANCE = 0.011;

// The measure's translations, as many times the source's width.
const PICTURE_FIT_RATIOS = array(1.0, 1.3, 1.6, 2.0, 2.5);

/**
 * A fixture of tests/fixtures/picture_language, decoded as arrays.
 *
 * @param string $name
 * @return array
 */
function picture_fit_fixture($name)
{
	$list = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/picture_language/' . $name . '.json'), true);

	return is_array($list) ? $list : array();
}

/**
 * The stub layout: half the size a letter of the longest line, 1.25 the
 * size a line, rounded to two places.
 *
 * @param array $l
 * @return array w, h
 */
function picture_fit_layout($l)
{
	$lines = explode("\n", (string) $l['text']);
	$size = (float) $l['size'];

	return array('w' => round(max(array_map('strlen', $lines)) * $size / 2, 2), 'h' => round(count($lines) * $size * 1.25, 2));
}

/**
 * Runs $script under Node with the editor's model as M, the fixture folder
 * as F and the stub layout as layout, and answers its JSON; null without
 * Node.
 *
 * @param string $script
 * @return array|null
 */
function picture_fit_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpicfit');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var F = ' . json_encode(dirname(__FILE__) . '/fixtures/picture_language/') . ";\n"
		. 'var layout = function (l) { var lines = String(l.text).split("\n"), size = Number(l.size);'
		. ' return { w: Math.round(Math.max.apply(null, lines.map(function (s) { return s.length; })) * size / 2 * 100) / 100,'
		. ' h: Math.round(lines.length * size * 1.25 * 100) / 100 }; };' . "\n"
		. 'var board = function (doc) { return doc.artboard ? { w: doc.artboard.width, h: doc.artboard.height } : { w: 0, h: 0 }; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

/**
 * The picture's w, h of a document, 0 without an artboard.
 *
 * @param array $doc
 * @return array
 */
function picture_fit_board($doc)
{
	return isset($doc['artboard']) ? array('w' => $doc['artboard']['width'], 'h' => $doc['artboard']['height']) : array('w' => 0, 'h' => 0);
}

/**
 * Whether two boxes (or nulls) agree within the tolerance.
 *
 * @param array|null $want
 * @param array|null $got
 * @return bool
 */
function picture_fit_same($want, $got)
{
	if (($want === null) || ($got === null)) {
		return $want === $got;
	}

	foreach ($want as $key => $value) {
		if (!isset($got[$key]) || (abs((float) $got[$key] - (float) $value) > PICTURE_FIT_TOLERANCE)) {
			return false;
		}
	}

	return true;
}

/**
 * The measure's translations of a layout at a ratio: each line of each
 * text as many letters as the ratio gives.
 *
 * @param array $doc
 * @param float $ratio
 * @return array pg_picture_tr_language_texts()
 */
function picture_fit_translations($doc, $ratio)
{
	$texts = array();

	foreach ($doc['layers'] as $l) {
		if ($l['type'] !== 'text') {
			continue;
		}

		$lines = array_map(function ($line) use ($ratio) {
			return str_repeat('a', (int) round(strlen($line) * $ratio));
		}, explode("\n", $l['text']));

		// Letters of their own: at 1.0 too the words differ and are fitted.
		$texts[] = array('id' => $l['id'], 'field' => 'text', 'text' => implode("\n", $lines));
	}

	return $texts;
}

// The room and the fitted texts of every fixture, in PHP and in the script.
function test_picture_fit_room_twins()
{
	$fixtures = picture_fit_fixture('room');

	pg_assert_true(count($fixtures) >= 15, 'fixtures read');

	if (!function_exists('pg_picture_tr_language_room')) {
		pg_assert_true(false, 'pg_picture_tr_language_room() is missing');

		return;
	}

	foreach ($fixtures as $case) {
		$put = pg_picture_tr_language_doc($case['doc'], $case['texts']);

		foreach ($case['rooms'] as $id => $room) {
			$got = pg_picture_tr_language_room($put['doc'], (string) $id, picture_fit_board($case['doc']));
			pg_assert_true(picture_fit_same($room, $got), 'PHP room ' . $id . ': ' . $case['about'] . ' ' . json_encode($got));
		}

		$fitted = pg_picture_tr_language_fitted($put, 'picture_fit_layout');

		foreach ($fitted['doc']['layers'] as $i => $l) {
			if (isset($case['layers'][$l['id']])) {
				pg_assert_true(picture_fit_same($case['layers'][$l['id']], $l), 'PHP text ' . $l['id'] . ': ' . $case['about'] . ' ' . json_encode($l));
			} elseif ($l['type'] !== 'sticker') {
				pg_assert_same($case['doc']['layers'][$i], $l, 'PHP layer kept: ' . $case['about']);
			} else {
				unset($l['label'], $case['doc']['layers'][$i]['label']);
				pg_assert_same($case['doc']['layers'][$i], $l, 'PHP sticker kept but for its label: ' . $case['about']);
			}
		}

		pg_assert_same($case['fit'], $fitted['fit'], 'PHP mark: ' . $case['about']);
	}

	$r = picture_fit_node('var list = require(F + "room.json");'
		. 'console.log(JSON.stringify(list.map(function (c) {'
		. ' var put = M.languageDoc(c.doc, c.texts), rooms = {}, layers = {};'
		. ' Object.keys(put.source).forEach(function (id) { rooms[id] = M.languageRoom(put.doc, id, board(c.doc)); });'
		. ' var f = M.languageFitted(put, layout, board(c.doc));'
		. ' f.doc.layers.forEach(function (l) { if (put.source[l.id]) { layers[l.id] = { x: l.x, y: l.y, w: l.w, h: l.h, size: l.size }; } });'
		. ' return { rooms: rooms, layers: layers, fit: f.fit };'
		. '})));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	foreach ($fixtures as $i => $case) {
		foreach ($case['rooms'] as $id => $room) {
			pg_assert_true(picture_fit_same($room, $r[$i]['rooms'][$id]), 'script room ' . $id . ': ' . $case['about']);
		}

		foreach ($case['layers'] as $id => $layer) {
			pg_assert_true(picture_fit_same($layer, $r[$i]['layers'][$id]), 'script text ' . $id . ': ' . $case['about'] . ' ' . json_encode($r[$i]['layers'][$id]));
		}

		pg_assert_same($case['fit'], $r[$i]['fit'], 'script mark: ' . $case['about']);
	}
}

// A text that fits its source's box is put as before; past it, it slides
// inside its room toward the free side and keeps its middle upright.
function test_picture_fit_place()
{
	if (!function_exists('pg_picture_tr_language_place')) {
		pg_assert_true(false, 'pg_picture_tr_language_place() is missing');

		return;
	}

	$src = array('x' => 100, 'y' => 50, 'w' => 100, 'h' => 40, 'align' => 'right');
	$room = array('x' => 20, 'y' => 20, 'w' => 300, 'h' => 200);

	pg_assert_same(array('x' => 140.0, 'y' => 50.0), pg_picture_tr_language_place($src, $room, array('w' => 100, 'h' => 40), array('w' => 60, 'h' => 40)), 'inside its box: its right side kept');
	pg_assert_same(array('x' => 20.0, 'y' => 50.0), pg_picture_tr_language_place($src, $room, array('w' => 100, 'h' => 40), array('w' => 250, 'h' => 40)), 'past it: slid to the room\'s left side');
	pg_assert_same(array('x' => 0.0, 'y' => 50.0), pg_picture_tr_language_place($src, $room, array('w' => 100, 'h' => 40), array('w' => 340, 'h' => 40)), 'wider than the room: its middle');
	pg_assert_same(array('x' => 100.0, 'y' => 30.0), pg_picture_tr_language_place($src, null, array('w' => 100, 'h' => 40), array('w' => 100, 'h' => 80)), 'no room: the old rule');

	$r = picture_fit_node('console.log(JSON.stringify(['
		. 'M.languagePlace({ x: 100, y: 50, w: 100, h: 40, align: "right" }, { x: 20, y: 20, w: 300, h: 200 }, { w: 100, h: 40 }, { w: 250, h: 40 }),'
		. 'M.languagePlace({ x: 100, y: 50, w: 100, h: 40, align: "right" }, null, { w: 100, h: 40 }, { w: 100, h: 80 })]));');

	if ($r !== null) {
		pg_assert_same(array(array('x' => 20, 'y' => 50), array('x' => 100, 'y' => 30)), $r, 'the script puts it alike');
	}
}

// The measure: every layout at each ratio, fitted by the old rule (the
// source's box) and by the room. No text ends smaller than before, no
// layout is marked that was not, fewer are, and both twins agree.
function test_picture_fit_measure()
{
	$layouts = picture_fit_fixture('measure');

	pg_assert_true(count($layouts) >= 20, 'at least twenty layouts');

	if (!function_exists('pg_picture_tr_language_room')) {
		pg_assert_true(false, 'pg_picture_tr_language_room() is missing');

		return;
	}

	$before = 0;
	$after = 0;
	$smaller = array();
	$marked = array();
	$php = array();

	foreach ($layouts as $layout) {
		$doc = $layout['doc'];
		$side = min($doc['artboard']['width'], $doc['artboard']['height']);

		foreach (PICTURE_FIT_RATIOS as $ratio) {
			$put = pg_picture_tr_language_doc($doc, picture_fit_translations($doc, $ratio));
			$fitted = pg_picture_tr_language_fitted($put, 'picture_fit_layout');
			$old = false;
			$sizes = array();

			foreach ($fitted['doc']['layers'] as $l) {
				if (!isset($put['source'][$l['id']])) {
					continue;
				}

				$src = $put['source'][$l['id']];
				$a = picture_fit_layout($src);
				$wide = picture_fit_layout(array('text' => $l['text'], 'size' => $src['size']));
				$fit = pg_picture_tr_language_fit($a['w'], $a['h'], $wide['w'], $wide['h'], $src['size'], $side);
				$was = pg_picture_tr_js_round($src['size'] * $fit['scale'], 2);
				$old = $old || $fit['mark'];
				$sizes[] = (float) $l['size'];

				if ((float) $l['size'] < $was - 1e-9) {
					$smaller[] = $layout['about'] . ' x' . $ratio . ' ' . $l['id'];
				}
			}

			$before += $old ? 1 : 0;
			$after += $fitted['fit'];

			if ($fitted['fit'] && !$old) {
				$marked[] = $layout['about'] . ' x' . $ratio;
			}

			$php[] = array('fit' => $fitted['fit'], 'sizes' => $sizes);
		}
	}

	pg_assert_same(array(), $smaller, 'no text ends smaller than the old rule made it');
	pg_assert_same(array(), $marked, 'no layout is marked that the old rule left');
	pg_assert_true($after < $before, 'fewer layouts end "Look by hand": ' . $before . ' before, ' . $after . ' after');

	$r = picture_fit_node('var list = require(F + "measure.json"), out = [];'
		. 'list.forEach(function (c) { ' . json_encode(PICTURE_FIT_RATIOS) . '.forEach(function (k) {'
		. ' var texts = c.doc.layers.filter(function (l) { return l.type === "text"; }).map(function (l) {'
		. '  return { id: l.id, field: "text", text: l.text.split("\n").map(function (s) { return new Array(Math.round(s.length * k) + 1).join("a"); }).join("\n") }; });'
		. ' var put = M.languageDoc(c.doc, texts), f = M.languageFitted(put, layout, board(c.doc));'
		. ' out.push({ fit: f.fit, sizes: f.doc.layers.filter(function (l) { return put.source[l.id]; }).map(function (l) { return l.size; }) });'
		. '}); });'
		. 'console.log(JSON.stringify(out));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(count($php), count($r), 'the script measured every layout');

	foreach ($php as $i => $want) {
		pg_assert_same($want['fit'], $r[$i]['fit'], 'the twins mark alike: ' . $i);

		foreach ($want['sizes'] as $k => $size) {
			pg_assert_true(abs($size - (float) $r[$i]['sizes'][$k]) <= PICTURE_FIT_TOLERANCE, 'the twins size alike: ' . $i . '.' . $k);
		}
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a translated picture text corrected by hand for one language:
 * the correction kept on a text layer (corrections, by language code), read
 * by M.languageCorrection() and pg_image_edit_correction(), refused by the
 * save's document check when it is malformed, taken by M.languageDoc() /
 * M.languageFitted() and their PHP twins in place of the fit, on the shared
 * fixtures (tests/fixtures/picture_language/correct.json), and counted for
 * the Languages list.
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
const PICTURE_CORRECT_TOLERANCE = 0.011;

/**
 * The correction fixtures, decoded as arrays.
 *
 * @return array
 */
function picture_correct_fixture()
{
	$list = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/picture_language/correct.json'), true);

	return is_array($list) ? $list : array();
}

/**
 * The stub layout: half the size a letter of the longest line, 1.25 the
 * size a line, rounded to two places.
 *
 * @param array $l
 * @return array w, h
 */
function picture_correct_layout($l)
{
	$lines = explode("\n", (string) $l['text']);
	$size = (float) $l['size'];

	return array('w' => round(max(array_map('strlen', $lines)) * $size / 2, 2), 'h' => round(count($lines) * $size * 1.25, 2));
}

/**
 * Runs $script under Node with the editor's model as M, the fixture folder
 * as F, the stub layout as layout and board(doc) as the picture's size;
 * answers its JSON, null without Node.
 *
 * @param string $script
 * @return array|null
 */
function picture_correct_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpiccor');

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
 * Whether a fitted layer matches the fixture's numbers (within the
 * tolerance) and its lines (exactly).
 *
 * @param array      $want
 * @param array|null $got
 * @return bool
 */
function picture_correct_same($want, $got)
{
	if (!is_array($got)) {
		return false;
	}

	foreach ($want as $key => $value) {
		if (!isset($got[$key])) {
			return false;
		}

		if (is_string($value) ? ($got[$key] !== $value) : (abs((float) $got[$key] - (float) $value) > PICTURE_CORRECT_TOLERANCE)) {
			return false;
		}
	}

	return true;
}

/**
 * A layer of a document by its id.
 *
 * @param array  $doc
 * @param string $id
 * @return array|null
 */
function picture_correct_layer($doc, $id)
{
	foreach ($doc['layers'] as $l) {
		if ((string) $l['id'] === (string) $id) {
			return $l;
		}
	}

	return null;
}

// A correction is read only when each of its numbers is a JSON number in
// range; the twins read alike.
function test_picture_correct_read()
{
	if (!function_exists('pg_image_edit_correction')) {
		pg_assert_true(false, 'pg_image_edit_correction() is missing');

		return;
	}

	$cases = array(
		array(array('x' => 10, 'y' => -5.5, 'w' => 200, 'size' => 24), array('x' => 10.0, 'y' => -5.5, 'w' => 200.0, 'size' => 24.0)),
		array(array('x' => 10, 'y' => 5, 'w' => 200, 'size' => 24, 'extra' => 1), array('x' => 10.0, 'y' => 5.0, 'w' => 200.0, 'size' => 24.0)),
		array(array('x' => '10', 'y' => 5, 'w' => 200, 'size' => 24), null),
		array(array('x' => 10, 'y' => 5, 'w' => 0, 'size' => 24), null),
		array(array('x' => 10, 'y' => 5, 'w' => 200, 'size' => 0.1), null),
		array(array('x' => 10, 'y' => 5, 'w' => 200), null),
		array(array('x' => 1e9, 'y' => 5, 'w' => 200, 'size' => 24), null),
		array('box', null),
		array(null, null),
	);

	foreach ($cases as $i => $case) {
		pg_assert_same($case[1], pg_image_edit_correction($case[0]), 'PHP read ' . $i);
	}

	$r = picture_correct_node('var list = ' . json_encode(array_column($cases, 0)) . ';'
		. 'console.log(JSON.stringify(list.map(function (c) { return M.languageCorrection({ type: "text", corrections: { de: c } }, "de"); })));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	foreach ($cases as $i => $case) {
		pg_assert_same($case[1], ($r[$i] === null) ? null : array_map('floatval', $r[$i]), 'script read ' . $i);
	}
}

// The save's document check refuses a malformed correction, in the layers
// and in a closed scene's layers, and keeps a well formed one.
function test_picture_correct_document_check()
{
	if (!function_exists('pg_image_edit_validate')) {
		pg_assert_true(false, 'pg_image_edit_validate() is missing');

		return;
	}

	$doc = function ($corrections, $scene = false) {
		$layer = array('id' => 'a', 'type' => 'text', 'text' => 'Sale', 'size' => 40, 'x' => 0, 'y' => 0, 'w' => 80, 'h' => 50, 'corrections' => $corrections);
		$d = array('v' => 1, 'artboard' => array('width' => 400, 'height' => 200), 'layers' => array());

		if ($scene) {
			$d['scenes'] = array(array('layers' => array($layer)));
		} else {
			$d['layers'][] = $layer;
		}

		return $d;
	};
	$good = array('de' => array('x' => 10, 'y' => 5, 'w' => 200, 'size' => 24), 'pt-BR' => array('x' => 0, 'y' => 0, 'w' => 1, 'size' => 0.5));

	pg_assert_same('', pg_image_edit_validate($doc($good))['error'], 'well formed corrections kept');
	pg_assert_same('', pg_image_edit_validate($doc(array()))['error'], 'an empty list kept');
	pg_assert_same('corrections', pg_image_edit_validate($doc(array('de' => array('x' => '10', 'y' => 5, 'w' => 200, 'size' => 24))))['error'], 'a number as a string refused');
	pg_assert_same('corrections', pg_image_edit_validate($doc(array('de' => array('x' => 10, 'y' => 5, 'w' => 200))))['error'], 'a missing size refused');
	pg_assert_same('corrections', pg_image_edit_validate($doc(array('<b>' => $good['de'])))['error'], 'a key that is no language code refused');
	pg_assert_same('corrections', pg_image_edit_validate($doc(array($good['de'])))['error'], 'a list in place of codes refused');
	pg_assert_same('corrections', pg_image_edit_validate($doc('de'))['error'], 'a string refused');
	pg_assert_same('corrections', pg_image_edit_validate($doc(array('de' => array('x' => 10, 'y' => 5, 'w' => 0, 'size' => 24)), true))['error'], 'a closed scene\'s malformed correction refused');

	$many = array();

	for ($i = 0; $i < 101; $i++) {
		$many['l' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = $good['de'];
	}

	pg_assert_same('corrections', pg_image_edit_validate($doc($many))['error'], 'more languages than the cap refused');
}

// A corrected text is drawn at its size, wrapped to its width, from its
// place, in both twins; one that is not read is fitted as before.
function test_picture_correct_fitted_twins()
{
	$fixtures = picture_correct_fixture();

	pg_assert_true(count($fixtures) >= 10, 'fixtures read');

	if (!function_exists('pg_picture_tr_language_fitted')) {
		pg_assert_true(false, 'pg_picture_tr_language_fitted() is missing');

		return;
	}

	$php = array();

	foreach ($fixtures as $case) {
		$put = pg_picture_tr_language_doc($case['doc'], $case['texts'], $case['code']);
		$fitted = pg_picture_tr_language_fitted($put, 'picture_correct_layout');
		$plain = pg_picture_tr_language_fitted(pg_picture_tr_language_doc($case['doc'], $case['texts']), 'picture_correct_layout');
		$php[] = $fitted;

		if (!empty($case['same_as_fit'])) {
			pg_assert_same($plain, $fitted, 'PHP fitted as without a correction: ' . $case['about']);
			continue;
		}

		foreach ($case['layers'] as $id => $want) {
			$got = picture_correct_layer($fitted['doc'], $id);
			pg_assert_true(picture_correct_same($want, $got), 'PHP text ' . $id . ': ' . $case['about'] . ' ' . json_encode($got));
		}

		if (!empty($case['others_as_fit'])) {
			foreach ($fitted['doc']['layers'] as $l) {
				if (!isset($case['layers'][$l['id']])) {
					pg_assert_same(picture_correct_layer($plain['doc'], $l['id']), $l, 'PHP other text fitted as before: ' . $case['about']);
				}
			}
		}

		pg_assert_same($case['fit'], $fitted['fit'], 'PHP mark: ' . $case['about']);
		pg_assert_same(1, pg_picture_tr_corrected($case['doc'], $case['code']), 'PHP counted: ' . $case['about']);
	}

	pg_assert_same(0, pg_picture_tr_corrected($fixtures[0]['doc'], 'fr'), 'PHP: no correction in another language');
	pg_assert_same(0, pg_picture_tr_corrected(array('layers' => 'x'), 'de'), 'PHP: no layers, none counted');

	$r = picture_correct_node('var list = require(F + "correct.json");'
		. 'console.log(JSON.stringify(list.map(function (c) {'
		. ' var f = M.languageFitted(M.languageDoc(c.doc, c.texts, c.code), layout, board(c.doc));'
		. ' return { doc: f.doc, fit: f.fit, count: M.languageCorrected(c.doc, c.code) };'
		. '})));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	foreach ($fixtures as $i => $case) {
		pg_assert_same($php[$i]['fit'], $r[$i]['fit'], 'script mark: ' . $case['about']);

		foreach ($php[$i]['doc']['layers'] as $l) {
			$keys = array('x', 'y', 'w', 'h', 'size', 'text');
			$want = array_intersect_key($l, array_flip($keys));
			pg_assert_true(picture_correct_same($want, picture_correct_layer($r[$i]['doc'], $l['id'])), 'script text ' . $l['id'] . ': ' . $case['about']);
		}

		if (empty($case['same_as_fit'])) {
			pg_assert_same(1, $r[$i]['count'], 'script counted: ' . $case['about']);
		}
	}
}

// The editor sets a language's correction on a text layer and takes it off;
// the last one off takes the field with it.
function test_picture_correct_set()
{
	$r = picture_correct_node('var l = { id: "a", type: "text", text: "Sale" }, out = [];'
		. 'M.languageCorrect(l, "de", { x: 10.004, y: 5, w: 200, size: 24 }); out.push(JSON.parse(JSON.stringify(l.corrections)));'
		. 'M.languageCorrect(l, "fr", { x: 1, y: 2, w: 3, size: 4 }); out.push(Object.keys(l.corrections));'
		. 'M.languageCorrect(l, "de", null); out.push(Object.keys(l.corrections));'
		. 'M.languageCorrect(l, "fr", null); out.push(l.hasOwnProperty("corrections"));'
		. 'out.push(M.languageCorrect(l, "de", { x: 1, y: 2, w: 0, size: 4 })); out.push(l.hasOwnProperty("corrections"));'
		. 'out.push(M.languageCorrect({ type: "shape" }, "de", { x: 1, y: 2, w: 3, size: 4 }));'
		. 'console.log(JSON.stringify(out));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(array('de' => array('x' => 10, 'y' => 5, 'w' => 200, 'size' => 24)), $r[0], 'set, rounded to two places');
	pg_assert_same(array('de', 'fr'), $r[1], 'a second language beside it');
	pg_assert_same(array('fr'), $r[2], 'one taken off');
	pg_assert_same(false, $r[3], 'the last taken off takes the field');
	pg_assert_same(false, $r[4], 'a malformed box not set');
	pg_assert_same(false, $r[5], 'nothing left behind');
	pg_assert_same(false, $r[6], 'only a text layer is corrected');
}

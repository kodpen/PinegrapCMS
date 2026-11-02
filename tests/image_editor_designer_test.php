<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor in the visual designer: the crop ratios a
 * caller's ratio adds to the crop panel (PgImageEditorModel.cropRatios()),
 * the ratio the designer reads from an Image element (_sdImageCropRatio(),
 * _sdRatioOf()) and the cache key a saved file gets on the canvas
 * (_sdStampAddress(), _sdStampSrcset()), cut out of
 * assets/js/style_designer.src.js. Run under Node, skipped without it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The function or declaration starting at $start in $source, up to its
// closing brace; '' when it is not there.
function image_editor_designer_test_cut($source, $start)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$open = strpos($source, '{', $from);
	$depth = 0;

	for ($i = $open; $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

// Runs $script after the model (as M) and the designer's helpers; prints
// its JSON. null without Node, ['error' => …] when the script fails.
function image_editor_designer_test_node($script)
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

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$helpers = '';

	foreach (array('function _sdRatioOf(', 'function _sdImageCropRatio(', 'function _sdImagePath(', 'function _sdStampAddress(', 'function _sdStampSrcset(') as $start) {
		$found = image_editor_designer_test_cut($source, $start);
		pg_assert_true($found !== '', $start . ' is in the designer');
		$helpers .= $found . "\n";
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgiedesigner');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. $helpers . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A caller's ratio marks the list's own entry, or is added after the
// picture's own; nonsense adds nothing.
function test_image_editor_designer_crop_ratios()
{
	$r = image_editor_designer_test_node(
		'function marked(l) { return l.filter(function (r) { return r[2]; }).map(function (r) { return r[0]; }); }' . "\n"
		. 'console.log(JSON.stringify({'
		. ' none: M.cropRatios(null).map(function (r) { return r[0]; }),'
		. ' same: marked(M.cropRatios({ value: 16 / 9, label: "16:9" })), sameLen: M.cropRatios({ value: 1.7778 }).length,'
		. ' added: M.cropRatios({ value: 21 / 9, label: "7:3" }).slice(0, 3).map(function (r) { return [r[0], r[2]]; }),'
		. ' unnamed: marked(M.cropRatios({ value: 2.5 })),'
		. ' bad: [M.givenRatio({ value: 0 }), M.givenRatio({ value: "x" }), M.givenRatio({ value: 50 }), M.givenRatio(null), M.cropRatios({ value: -1 }).length] }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'no Node; skipped');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : '');
	pg_assert_same(array('free', 'original', '1:1', '4:3', '3:2', '16:9', '4:5', '9:16'), $r['none'], 'no ratio: the usual list');
	pg_assert_same(array('16:9'), $r['same'], 'a ratio on the list marks its entry');
	pg_assert_same(8, $r['sameLen'], 'a near value is not added twice');
	pg_assert_same(array(array('free', false), array('original', false), array('7:3', true)), $r['added'], 'another ratio comes after the original');
	pg_assert_same(array('2.5:1'), $r['unnamed'], 'a ratio without a label is named as a decimal');
	pg_assert_same(array(0, 0, 0, 0, 8), $r['bad'], 'nonsense is no ratio');
}

// The ratio of an Image element: its ratio box, else whole-number sizes.
function test_image_editor_designer_image_ratio()
{
	$cases = array(
		'preset'         => array('aspectRatio' => '16x9'),
		'preset square'  => array('aspectRatio' => '1x1', 'width' => '300', 'height' => '100'),
		'custom percent' => array('aspectRatio' => 'custom', 'aspectRatioEnabled' => true, 'aspectRatioCustom' => '56.25%'),
		'custom calc'    => array('aspectRatio' => 'custom', 'aspectRatioEnabled' => true, 'aspectRatioCustom' => 'calc(3 / 4 * 100%)'),
		'custom off'     => array('aspectRatio' => 'custom', 'aspectRatioEnabled' => false, 'aspectRatioCustom' => '50%', 'width' => '200', 'height' => '100'),
		'custom unread'  => array('aspectRatio' => 'custom', 'aspectRatioEnabled' => true, 'aspectRatioCustom' => 'var(--x)'),
		'sizes'          => array('width' => '1200', 'height' => '630'),
		'odd sizes'      => array('width' => '1001', 'height' => '500'),
		'a unit'         => array('width' => '50%', 'height' => '200'),
		'nothing'        => array(),
	);

	$r = image_editor_designer_test_node(
		'var cases = ' . json_encode($cases) . ', out = {};' . "\n"
		. 'Object.keys(cases).forEach(function (k) { var v = _sdImageCropRatio(cases[k]); out[k] = v ? [v.label, Math.round(v.value * 1000) / 1000] : null; });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		pg_assert_true(true, 'no Node; skipped');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : '');
	pg_assert_same(array('16:9', 1.778), $r['preset'], 'a preset ratio box');
	pg_assert_same(array('1:1', 1), $r['preset square'], 'the ratio box wins over the sizes');
	pg_assert_same(array('16:9', 1.778), $r['custom percent'], 'a custom percentage is height over width');
	pg_assert_same(array('4:3', 1.333), $r['custom calc'], 'a custom calc()');
	pg_assert_same(null, $r['custom off'], 'a custom ratio switched off is no box and no ratio');
	pg_assert_same(null, $r['custom unread'], 'a custom value it cannot read gives none');
	pg_assert_same(array('40:21', 1.905), $r['sizes'], 'whole-number sizes, reduced');
	pg_assert_same(array('2:1', 2.002), $r['odd sizes'], 'large sizes get the nearest small fraction');
	pg_assert_same(null, $r['a unit'], 'a size with a unit gives none');
	pg_assert_same(null, $r['nothing'], 'no ratio box and no sizes');
}

// The cache key goes on addresses of the files saved in the editor only,
// replacing an older key, and on each matching srcset candidate.
function test_image_editor_designer_cache_key()
{
	$r = image_editor_designer_test_node(
		'var base = "https://site.test/pages/home", stamps = { "/files/a photo.jpg": "9", "/files/b.png": "7" };' . "\n"
		. 'console.log(JSON.stringify({'
		. ' plain: _sdStampAddress("/files/a%20photo.jpg", base, stamps),'
		. ' absolute: _sdStampAddress("https://site.test/files/b.png", base, stamps),'
		. ' older: _sdStampAddress("/files/b.png?w=640&_pgv=3", base, stamps),'
		. ' same: _sdStampAddress("/files/b.png?_pgv=7", base, stamps),'
		. ' other: _sdStampAddress("/files/c.png", base, stamps),'
		. ' elsewhere: _sdStampAddress("https://cdn.test/files/b.png", base, stamps),'
		. ' data: _sdStampAddress("data:image/png;base64,AA", base, stamps),'
		. ' srcset: _sdStampSrcset("/files/b.png?w=640 640w, /files/c.png 1x, /files/b.png 1280w", base, stamps),'
		. ' untouched: _sdStampSrcset("/files/c.png 1x", base, stamps) }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'no Node; skipped');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : '');
	pg_assert_same('/files/a%20photo.jpg?_pgv=9', $r['plain'], 'an encoded path matches its file');
	pg_assert_same('https://site.test/files/b.png?_pgv=7', $r['absolute'], 'a full address of this site');
	pg_assert_same('/files/b.png?w=640&_pgv=7', $r['older'], 'an older key is replaced, other parameters kept');
	pg_assert_same(null, $r['same'], 'an address with the key already is left');
	pg_assert_same(null, $r['other'], 'a file not saved in the editor is left');
	pg_assert_same(null, $r['elsewhere'], 'another site is left');
	pg_assert_same(null, $r['data'], 'a data address is left');
	pg_assert_same('/files/b.png?w=640&_pgv=7 640w, /files/c.png 1x, /files/b.png?_pgv=7 1280w', $r['srcset'], 'each matching srcset candidate');
	pg_assert_same(null, $r['untouched'], 'a srcset with no saved file is left');
}

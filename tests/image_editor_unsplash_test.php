<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for From Unsplash in the Artifact Canvas's Import: the picture layer
 * a picked photo becomes (its place, the returned address, the credit), the
 * credit read back from a layer, the addresses loaded for a save's canvas,
 * and the part's wiring (run under Node).
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
function image_editor_unsplash_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieunsplash');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A photo becomes a picture layer in the middle of the view, at most 60 % of
// it, keeping the returned address and the credit; a photo smaller than
// that keeps its size.
function test_image_editor_unsplash_layer()
{
	$r = image_editor_unsplash_node(
		'var info = { src: "https://images.unsplash.com/photo-1?w=1080", alt: "A cup", name: "Ada Lens", profile: "https://unsplash.com/@ada?utm_source=pinegrap&utm_medium=referral", page: "https://unsplash.com/photos/abc?utm_source=pinegrap&utm_medium=referral" };'
		. 'console.log(JSON.stringify({ big: M.unsplashLayer(info, 1080, 1620, { x: 0, y: 0, w: 1000, h: 1000 }),'
		. ' small: M.unsplashLayer(info, 100, 50, { x: 10, y: 20, w: 1000, h: 500 }),'
		. ' none: M.unsplashLayer(null, 100, 50, { x: 0, y: 0, w: 10, h: 10 }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), (string) ($r['error'] ?? ''));
	pg_assert_same(array(
		'type' => 'image', 'src' => 'https://images.unsplash.com/photo-1?w=1080', 'x' => 300, 'y' => 200, 'w' => 400, 'h' => 600, 'rot' => 0, 'opacity' => 1,
		'credit' => array('source' => 'unsplash', 'name' => 'Ada Lens', 'profile' => 'https://unsplash.com/@ada?utm_source=pinegrap&utm_medium=referral', 'page' => 'https://unsplash.com/photos/abc?utm_source=pinegrap&utm_medium=referral'),
		'alt' => 'A cup',
	), $r['big']);
	pg_assert_same(array(460, 245, 100, 50), array($r['small']['x'], $r['small']['y'], $r['small']['w'], $r['small']['h']));
	pg_assert_same(null, $r['none']);
}

// The credit read back from a layer: an Unsplash one with its links on
// unsplash.com, else null (another source, a missing name, a link elsewhere).
function test_image_editor_unsplash_credit()
{
	$r = image_editor_unsplash_node(
		'var ok = { source: "unsplash", name: "Ada", profile: "https://unsplash.com/@ada?x=1", page: "https://unsplash.com/photos/abc" };'
		. 'console.log(JSON.stringify({ ok: M.pictureCredit({ type: "image", credit: ok }),'
		. ' noName: M.pictureCredit({ type: "image", credit: { source: "unsplash", name: "", profile: ok.profile, page: ok.page } }),'
		. ' elsewhere: M.pictureCredit({ type: "image", credit: { source: "unsplash", name: "Ada", profile: "javascript:alert(1)", page: ok.page } }),'
		. ' other: M.pictureCredit({ type: "image", credit: { source: "x", name: "Ada", profile: ok.profile, page: ok.page } }),'
		. ' bare: M.pictureCredit({ type: "image" }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), (string) ($r['error'] ?? ''));
	pg_assert_same(array('source' => 'unsplash', 'name' => 'Ada', 'profile' => 'https://unsplash.com/@ada?x=1', 'page' => 'https://unsplash.com/photos/abc'), $r['ok']);
	pg_assert_same(null, $r['noName']);
	pg_assert_same(null, $r['elsewhere']);
	pg_assert_same(null, $r['other']);
	pg_assert_same(null, $r['bare']);
}

// Only Unsplash's picture addresses are loaded asking for CORS, so a save's
// canvas can read their pixels; the site's own files and data: addresses are
// loaded as before.
function test_image_editor_unsplash_cors()
{
	$r = image_editor_unsplash_node(
		'console.log(JSON.stringify(["https://images.unsplash.com/photo-1?w=1080", "https://images.unsplash.com.evil.test/a", "http://images.unsplash.com/a",'
		. ' "/files/a.png", "data:image/png;base64,AA", "https://example.com/a.png"].map(M.corsPicture)));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(true, false, false, false, false, false), $r);
}

// The part is listed, labelled and bundled after the frames; the config
// offers the dialog only with the site's key.
function test_image_editor_unsplash_part()
{
	pg_assert_true(in_array('unsplash', pg_image_editor_parts(), true), 'the part is listed');

	$strings = pg_image_editor_strings_unsplash();

	foreach (array('unsplashImport', 'unsplashCredit', 'unsplashError') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is labelled');
	}

	pg_assert_true(strpos($strings['unsplashCredit'], '{var:1}') !== false, 'the credit names the photographer');

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_unsplash.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_frames.js', $files, true), 'after the frames');

	pg_assert_same(null, pg_image_editor_unsplash_config(''));

	$config = pg_image_editor_unsplash_config('k1');

	pg_assert_same('k1', $config['key']);
	pg_assert_true(!empty($config['scripts']), 'the dialog script is named');
	pg_assert_true(isset($config['texts']['Search']), 'the dialog is labelled');
	pg_assert_true(array_key_exists('unsplash', pg_image_editor_config()), 'the editor reads it');
}

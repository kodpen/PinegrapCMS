<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Free photos (Openverse) in the Artifact Canvas's Import: the
 * search address, the answer read into records (CC0 and public domain
 * only), the picture layer a picked photo becomes with its credit, the
 * credit read back from a layer, and the part's wiring (run under Node).
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
function image_editor_openverse_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieopenverse');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The search asks only for CC0 and public domain photos, twenty a page; an
// empty search asks nothing.
function test_image_editor_openverse_list_url()
{
	$r = image_editor_openverse_node(
		'console.log(JSON.stringify([M.openverseListUrl(" red cup ", 1), M.openverseListUrl("a&b", 3), M.openverseListUrl("  ", 1), M.openverseListUrl("x", 0)]));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), (string) ($r['error'] ?? ''));
	pg_assert_same(array(
		'https://api.openverse.org/v1/images/?q=red%20cup&license=cc0,pdm&category=photograph&page_size=20&page=1',
		'https://api.openverse.org/v1/images/?q=a%26b&license=cc0,pdm&category=photograph&page_size=20&page=3',
		'',
		'https://api.openverse.org/v1/images/?q=x&license=cc0,pdm&category=photograph&page_size=20&page=1',
	), $r);
}

// An answer becomes records: the picture addresses are built from the id on
// Openverse's own host, links are kept only over https, and a record under
// another licence, or without a proper id, is left out.
function test_image_editor_openverse_list()
{
	$r = image_editor_openverse_node(
		'var id = "4bc43a04-ef46-4544-a0c1-63c63f56e276";'
		. 'var ok = { id: id, title: "Red cup", creator: "Ada Lens", creator_url: "https://www.flickr.com/photos/ada", license: "CC0", license_version: "1.0",'
		. ' license_url: "https://creativecommons.org/publicdomain/zero/1.0/", foreign_landing_url: "https://www.flickr.com/photos/ada/1", thumbnail: "https://evil.test/t.jpg", width: 4000, height: "3000" };'
		. 'var pdm = { id: "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee", title: "", creator: "", creator_url: "javascript:alert(1)", license: "pdm", license_url: "http://creativecommons.org/publicdomain/mark/1.0/", foreign_landing_url: "" };'
		. 'var by = Object.assign({}, ok, { id: "11111111-2222-3333-4444-555555555555", license: "by" });'
		. 'var badId = Object.assign({}, ok, { id: "../x" });'
		. 'console.log(JSON.stringify({ list: M.openverseList({ result_count: 45, page_count: 3, page: 1, results: [ok, pdm, by, badId, null] }),'
		. ' last: M.openverseList({ page_count: 2, page: 2, results: [] }), junk: M.openverseList(null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), (string) ($r['error'] ?? ''));
	pg_assert_same(true, $r['list']['more']);
	pg_assert_same(2, count($r['list']['photos']));
	pg_assert_same(array(
		'id' => '4bc43a04-ef46-4544-a0c1-63c63f56e276', 'title' => 'Red cup', 'creator' => 'Ada Lens', 'creatorUrl' => 'https://www.flickr.com/photos/ada',
		'license' => 'cc0', 'licenseUrl' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'page' => 'https://www.flickr.com/photos/ada/1',
		'thumb' => 'https://api.openverse.org/v1/images/4bc43a04-ef46-4544-a0c1-63c63f56e276/thumb/',
		'full' => 'https://api.openverse.org/v1/images/4bc43a04-ef46-4544-a0c1-63c63f56e276/thumb/?full_size=true',
		'width' => 4000, 'height' => 3000,
	), $r['list']['photos'][0]);
	pg_assert_same('pdm', $r['list']['photos'][1]['license']);
	pg_assert_same('', $r['list']['photos'][1]['creatorUrl']);
	pg_assert_same('', $r['list']['photos'][1]['licenseUrl']);
	pg_assert_same(array('photos' => array(), 'more' => false), $r['last']);
	pg_assert_same(array('photos' => array(), 'more' => false), $r['junk']);
}

// A picked photo becomes a picture layer in the middle of the view, at most
// 60 % of it, with its own pixels (a data: address, so a save keeps it as a
// file) and the record's credit.
function test_image_editor_openverse_layer()
{
	$r = image_editor_openverse_node(
		'var info = { id: "4bc43a04-ef46-4544-a0c1-63c63f56e276", title: "Red cup", creator: "Ada Lens", creatorUrl: "https://www.flickr.com/photos/ada", license: "cc0",'
		. ' licenseUrl: "https://creativecommons.org/publicdomain/zero/1.0/", page: "https://www.flickr.com/photos/ada/1" };'
		. 'console.log(JSON.stringify({ big: M.openverseLayer(info, "data:image/jpeg;base64,AA", 1000, 1500, { x: 0, y: 0, w: 1000, h: 1000 }),'
		. ' remote: M.openverseLayer(info, "https://example.com/a.jpg", 10, 10, { x: 0, y: 0, w: 10, h: 10 }),'
		. ' none: M.openverseLayer(null, "data:image/png;base64,AA", 10, 10, { x: 0, y: 0, w: 10, h: 10 }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), (string) ($r['error'] ?? ''));
	pg_assert_same(array(
		'type' => 'image', 'src' => 'data:image/jpeg;base64,AA', 'fileName' => 'Red cup', 'x' => 300, 'y' => 200, 'w' => 400, 'h' => 600, 'rot' => 0, 'opacity' => 1,
		'credit' => array('source' => 'openverse', 'title' => 'Red cup', 'creator' => 'Ada Lens', 'creatorUrl' => 'https://www.flickr.com/photos/ada', 'license' => 'cc0',
			'licenseUrl' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'page' => 'https://www.flickr.com/photos/ada/1'),
		'alt' => 'Red cup',
	), $r['big']);
	pg_assert_same(null, $r['remote']);
	pg_assert_same(null, $r['none']);
}

// The credit read back from a layer: an Openverse one under CC0 or the
// public domain mark, its links kept only over https; else null.
function test_image_editor_openverse_credit()
{
	$r = image_editor_openverse_node(
		'var ok = { source: "openverse", title: "Cup", creator: "Ada", creatorUrl: "https://a.test/ada", license: "pdm", licenseUrl: "https://creativecommons.org/publicdomain/mark/1.0/", page: "https://a.test/1" };'
		. 'console.log(JSON.stringify({ ok: M.openverseCredit({ type: "image", credit: ok }),'
		. ' bad: M.openverseCredit({ type: "image", credit: Object.assign({}, ok, { creatorUrl: "javascript:alert(1)", page: "data:x" }) }),'
		. ' by: M.openverseCredit({ type: "image", credit: Object.assign({}, ok, { license: "by" }) }),'
		. ' unsplash: M.openverseCredit({ type: "image", credit: { source: "unsplash", name: "Ada" } }),'
		. ' bare: M.openverseCredit({ type: "image" }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), (string) ($r['error'] ?? ''));
	pg_assert_same(array('source' => 'openverse', 'title' => 'Cup', 'creator' => 'Ada', 'creatorUrl' => 'https://a.test/ada', 'license' => 'pdm',
		'licenseUrl' => 'https://creativecommons.org/publicdomain/mark/1.0/', 'page' => 'https://a.test/1'), $r['ok']);
	pg_assert_same('', $r['bad']['creatorUrl']);
	pg_assert_same('', $r['bad']['page']);
	pg_assert_same(null, $r['by']);
	pg_assert_same(null, $r['unsplash']);
	pg_assert_same(null, $r['bare']);
}

// The part is listed, labelled and bundled after Unsplash's; the panel's
// built-in content policy lets the browser ask Openverse.
function test_image_editor_openverse_part()
{
	pg_assert_true(in_array('openverse', pg_image_editor_parts(), true), 'the part is listed');

	$strings = pg_image_editor_strings_openverse();

	foreach (array('openverseImport', 'openverseFind', 'openverseNote', 'openverseNone', 'openverseError', 'openverseBusy', 'openverseMore', 'openverseCredit',
		'openverseCc0', 'openversePdm', 'openversePage', 'openverseUnknown', 'openversePlaceError') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' is labelled');
	}

	pg_assert_true(strpos($strings['openverseCredit'], '{var:1}') !== false, 'the credit names the creator');

	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];
	$at = array_search('assets/js/pg_image_editor_openverse.js', $files, true);

	pg_assert_true($at !== false, 'bundled');
	pg_assert_true($at > array_search('assets/js/pg_image_editor_unsplash.js', $files, true), 'after Unsplash');

	$waf = file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');

	pg_assert_true((bool) preg_match('/connect-src [^;"]*https:\/\/api\.openverse\.org/', $waf), 'connect-src names Openverse');
}

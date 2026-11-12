<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rich text editor's bridge to the picker (assets/js/pg_ckeditor_picker.js)
 * and the Unsplash dialog (assets/js/pg_unsplash.js): the pick's address, the
 * name the dialog opens on, the dialog definition walk, the Unsplash
 * addresses and answers; and the bridge's wiring in includes/fn/editor.php.
 * The JavaScript runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $body (JavaScript with B = pgCkeditorPicker.pure, U = pgUnsplash.pure)
// and returns what it prints as JSON; null when Node cannot run.
function editor_bridge_js_test_run($body)
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

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_unsplash.js') . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ckeditor_picker.js') . "\n"
		. "var B = window.pgCkeditorPicker.pure, U = window.pgUnsplash.pure;\n"
		. 'process.stdout.write(JSON.stringify((function () {' . $body . '})()));';

	$file = tempnam(sys_get_temp_dir(), 'pgbridge') . '.js';
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The address of a pick: encoded as encode_url_path() encodes, a short link
// by its name.
function test_editor_bridge_pick_address()
{
	$out = editor_bridge_js_test_run('return [
		B.pickAddress({value: 4, text: "about us"}, "/"),
		B.pickAddress({value: "yaz", text: "yaz", extra: true}, "/site/"),
		B.pickAddress({value: 9, text: "Fiyat (2026)!.pdf"}, "/"),
		B.pickAddress({value: 9, text: "a#b?c.png"}, "/"),
		B.pickAddress(null, "/"),
		B.pickAddress({value: 1, text: ""}, "/"),
		B.encodePath("x/y z")
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same('/about%20us', $out[0]);
	pg_assert_same('/site/yaz', $out[1], 'a short link by its name');
	pg_assert_same('/Fiyat%20%282026%29%21.pdf', $out[2], 'as rawurlencode()');
	pg_assert_same('/a%23b%3Fc.png', $out[3], 'a # or ? does not end the path');
	pg_assert_same('', $out[4]);
	pg_assert_same('', $out[5]);
	pg_assert_same('x/y%20z', $out[6], 'slashes kept');
}

// The dialog opens on the name of the site's own address in the field.
function test_editor_bridge_name_from_address()
{
	$out = editor_bridge_js_test_run('return [
		B.nameFromAddress("/about%20us", "/"),
		B.nameFromAddress("/site/logo.png?v=3", "/site/"),
		B.nameFromAddress("https://example.com/x", "/"),
		B.nameFromAddress("/shop/blue-mug", "/"),
		B.nameFromAddress("/%E0%A4%A", "/"),
		B.nameFromAddress("", "/")
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('about us', 'logo.png', '', '', '', ''), $out);
}

// The Browse Server button is found inside the dialog's boxes, with its list.
function test_editor_bridge_find_element()
{
	$out = editor_bridge_js_test_run('var elements = [{id: "linkType"}, {type: "vbox", id: "urlOptions", children: [{type: "hbox", children: [{id: "protocol"}, {id: "url"}]}, {type: "button", id: "browse"}]}];
		var found = B.findElement(elements, "browse");
		return [found.index, found.list === elements[1].children, B.findElement(elements, "url").index, B.findElement(elements, "nope")];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(1, true, 1, null), $out);
}

// The Unsplash addresses and answers: popular without a query, a search
// with one; more pages while a full page or a further page exists.
function test_editor_bridge_unsplash()
{
	$out = editor_bridge_js_test_run('var photos = []; for (var i = 0; i < 20; i++) { photos.push({id: i}); }
		return {
			popular: U.listUrl("k1", "  ", 0),
			search: U.listUrl("k1", "kahve & çay", 2),
			list: U.readAnswer(photos, 1, "57"),
			short: U.readAnswer([{id: 1}], 3, null).more,
			found: U.readAnswer({results: [{id: 1}], total: 41, total_pages: 3}, 2, null),
			last: U.readAnswer({results: [], total: 41, total_pages: 3}, 3, null).more,
			broken: U.readAnswer(null, 1, null),
			referral: [U.referral("https://unsplash.com/@a", "x"), U.referral("", "https://unsplash.com")]
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same('https://api.unsplash.com/photos?order_by=popular&per_page=20&page=1&client_id=k1', $out['popular']);
	pg_assert_same('https://api.unsplash.com/search/photos?query=kahve%20%26%20%C3%A7ay&per_page=20&page=2&client_id=k1', $out['search']);
	pg_assert_same(57, $out['list']['total']);
	pg_assert_true($out['list']['more'], 'a full page asks for more');
	pg_assert_same(false, $out['short']);
	pg_assert_same(array('photos' => array(array('id' => 1)), 'total' => 41, 'more' => true), $out['found']);
	pg_assert_same(false, $out['last']);
	pg_assert_same(array('photos' => array(), 'total' => 0, 'more' => false), $out['broken']);
	pg_assert_same(array('https://unsplash.com/@a?utm_source=pinegrap&utm_medium=referral', 'https://unsplash.com?utm_source=pinegrap&utm_medium=referral'), $out['referral']);
}

// The bridge is printed behind the same gate as Browse and Upload, after
// CKEditor, and served in its bundle.
function test_editor_bridge_wired()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/editor.php');
	pg_assert_true(strpos($src, '$output_picker_bridge = $files_allowed ? pg_ckeditor_picker_script() : \'\';') !== false, 'behind the files gate');
	pg_assert_true(strpos($src, "pg_js_bundle_tags('assets/js/pg_editor_bridge.min.js')") !== false, 'the bundle');
	pg_assert_true(strpos($src, 'return pg_picker_script()') !== false, 'with the picker');

	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_unsplash.js', 'assets/js/pg_ckeditor_picker.js'), $bundles['assets/js/pg_editor_bridge.min.js']);

	foreach (pg_ckeditor_picker_texts() as $english => $text) {
		pg_assert_true(is_string($text) && $text !== '', $english);
	}
}

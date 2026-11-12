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
	pg_assert_true(strpos($src, '$output_picker_bridge = $files_allowed ? pg_editor_bridge_script() : \'\';') !== false, 'behind the files gate');
	pg_assert_true(strpos($src, "pg_js_bundle_tags('assets/js/pg_editor_bridge.min.js')") !== false, 'the bundle');
	pg_assert_true(strpos($src, 'return pg_picker_script()') !== false, 'with the picker');

	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_unsplash.js', 'assets/js/pg_ckeditor_picker.js'), $bundles['assets/js/pg_editor_bridge.min.js']);

	foreach (pg_editor_bridge_texts() as $english => $text) {
		pg_assert_true(is_string($text) && $text !== '', $english);
	}
}

// The visual designer chooses through the same bridge: the screen prints it,
// the link and picture fields call pgEditorPicker, no window and no fake
// CKEDITOR is left; widget page selects get Browse; the page type pane draws
// picker fields.
function test_editor_bridge_designer_wired()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, 'pg_editor_bridge_script()') !== false, 'the designer prints the bridge');

	$designer = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_same(false, strpos($designer, 'editor_select_page_or_file.php'), 'no link window');
	pg_assert_same(false, strpos($designer, 'CKEDITOR.tools'), 'no fake CKEDITOR');
	pg_assert_same(false, strpos($designer, 'software_image_picker'), 'no image window');
	pg_assert_true(strpos($designer, 'window.pgEditorPicker.image(') !== false, 'pictures through the bridge');
	pg_assert_true(strpos($designer, "picker.link(inp.value)") !== false, 'links through the bridge');
	pg_assert_true(strpos($designer, ' data-sw-page-none>') !== false, 'page selects are marked');
	pg_assert_true(strpos($designer, '_swPageBrowseDecorate(panel);') !== false, 'Browse is added as the panel is drawn');

	$pane = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');
	pg_assert_true(strpos($pane, '$properties, $user, $page_id, true);') !== false, 'the page type pane draws picker fields');

	$bridge = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ckeditor_picker.js');
	pg_assert_true(strpos($bridge, 'root.pgEditorPicker = {') !== false, 'the bridge offers its pickers');
}

// The link window is gone: no file, no setting naming it, and Cleanup lists
// it for sites that still carry it from before.
function test_editor_bridge_link_window_deleted()
{
	pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/editor_select_page_or_file.php'), 'the file is deleted');
	pg_assert_same(false, strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/editor.php'), 'editor_select_page_or_file'), 'no setting names it');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/clean_up.php'), "    'editor_select_page_or_file.php',\n") !== false, 'Cleanup lists it');
}

// A screen that keeps files gets the picked pictures as names and ids, in
// the order picked; an item without a name is left out.
function test_editor_bridge_picture_items()
{
	$out = editor_bridge_js_test_run('return [
		B.pictureItems({value: "a.png", text: "a.png", id: 7}),
		B.pictureItems([{value: "b c.jpg", text: "b c.jpg", id: "12"}, {value: "", text: ""}, {value: "d.webp", id: 3}]),
		B.pictureItems(null),
		B.pictureItems([])
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('name' => 'a.png', 'id' => 7)), $out[0]);
	pg_assert_same(array(array('name' => 'b c.jpg', 'id' => 12), array('name' => 'd.webp', 'id' => 3)), $out[1]);
	pg_assert_same(null, $out[2]);
	pg_assert_same(null, $out[3]);
}

// The image window is gone: the product builder's pictures and a contact's
// photo open the picker through software_image_picker(), the screens print
// the bridge, no setting names the window and Cleanup lists it.
function test_editor_bridge_image_window_deleted()
{
	pg_assert_false(is_file(PG_FUNCTIONS_DIR . '/editor_select_image.php'), 'the file is deleted');
	pg_assert_same(false, strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/editor.php'), 'editor_select_image'), 'no setting names it');
	pg_assert_same(false, strpos(file_get_contents(PG_FUNCTIONS_DIR . '/add_file.php'), 'editor_select_image'), 'no upload returns to it');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/clean_up.php'), "    'editor_select_image.php',\n") !== false, 'Cleanup lists it');

	$backend = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	pg_assert_same(false, strpos($backend, 'window.open(windowUrl'), 'no window');
	pg_assert_true(strpos($backend, 'window.pgEditorPicker.pictures({ multiple: !single })') !== false, 'software_image_picker() opens the picker');

	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php'), 'pg_editor_bridge_script()') !== false, 'the product builder prints the bridge');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php'), 'return pg_editor_bridge_script()') !== false, 'the contact picture card prints the bridge');
}

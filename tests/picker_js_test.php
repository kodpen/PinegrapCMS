<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker dialog's pure helpers (assets/js/pg_picker.js, pgPicker.pure):
 * the folded match and its highlight, the sort with folders first, the
 * cursor over a grid and a list, the search kept to one folder, type-ahead
 * and the small labels. Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $body (JavaScript with P = pgPicker.pure) and returns what it prints
// as JSON; null when Node cannot run.
function picker_js_test_run($body)
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

	$script = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_picker.js') . "\n"
		. "var P = window.pgPicker.pure;\n"
		. 'process.stdout.write(JSON.stringify((function () {' . $body . '})()));';

	$file = tempnam(sys_get_temp_dir(), 'pgpicker') . '.js';
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_picker_js_fold_and_highlight()
{
	$out = picker_js_test_run('return {
		fold: [P.foldText("İstanbul"), P.foldText("IŞIK"), P.foldText("ışık"), P.foldText(null), P.foldText("Çağrı Ünlü"), P.foldText("Straße")],
		grown: P.highlightParts("Große-Tasse.png", "grosse"),
		parts: P.highlightParts("seramik-KUPA-mavi.jpg", "kupa"),
		turkish: P.highlightParts("Işık.png", "isik"),
		none: P.highlightParts("logo.png", "x"),
		empty: P.highlightParts("logo.png", "  ")
	};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('istanbul', 'isik', 'isik', '', 'cagri unlu', 'strasse'), $out['fold'], 'as fold_keyword_for_matching(): case, İ/I/ı/i and accents alike');
	pg_assert_same(array(array('text' => 'Große', 'match' => true), array('text' => '-Tasse.png', 'match' => false)), $out['grown'], 'a letter that folds to two still slices the original');
	pg_assert_same(array(array('text' => 'seramik-', 'match' => false), array('text' => 'KUPA', 'match' => true), array('text' => '-mavi.jpg', 'match' => false)), $out['parts']);
	pg_assert_same(array(array('text' => 'Işık', 'match' => true), array('text' => '.png', 'match' => false)), $out['turkish'], 'the original letters are kept');
	pg_assert_same(array(array('text' => 'logo.png', 'match' => false)), $out['none']);
	pg_assert_same(array(array('text' => 'logo.png', 'match' => false)), $out['empty']);
}

function test_picker_js_sort()
{
	$out = picker_js_test_run('var c = P.makeCollator("tr");
		var items = [
			{text: "Zeytin.jpg", size: 10, modified: 3, type: "jpg"},
			{text: "çanta.png", size: 30, modified: 1, type: "png"},
			{text: "Armut.jpg", size: 20, modified: 2, type: "jpg"},
			{text: "Kupalar", isFolder: true},
			{text: "resim10.jpg", size: 1, modified: 9, type: "jpg"},
			{text: "resim9.jpg", size: 1, modified: 9, type: "jpg"}
		];
		function names(list) { return list.map(function (i) { return i.text; }); }
		return {
			name: names(P.sortItems(items, "name", "asc", c)),
			desc: names(P.sortItems(items, "name", "desc", c)),
			size: names(P.sortItems(items, "size", "desc", c)),
			modified: names(P.sortItems(items, "modified", "desc", c)),
			type: names(P.sortItems(items, "type", "asc", c)),
			best: names(P.sortItems(items, "best", "asc", c)),
			dirs: [P.defaultDir("name"), P.defaultDir("size"), P.defaultDir("modified"), P.defaultDir("type")]
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('Kupalar', 'Armut.jpg', 'çanta.png', 'resim9.jpg', 'resim10.jpg', 'Zeytin.jpg'), $out['name'], 'folders first, Ç before Z, numbers in order');
	pg_assert_same(array('Kupalar', 'Zeytin.jpg', 'resim10.jpg', 'resim9.jpg', 'çanta.png', 'Armut.jpg'), $out['desc']);
	pg_assert_same(array('Kupalar', 'çanta.png', 'Armut.jpg', 'Zeytin.jpg', 'resim9.jpg', 'resim10.jpg'), $out['size'], 'a tie goes by name, A to Z');
	pg_assert_same('Kupalar', $out['modified'][0]);
	pg_assert_same(array('Kupalar', 'Zeytin.jpg', 'çanta.png', 'Armut.jpg', 'resim10.jpg', 'resim9.jpg'), $out['best'], 'best keeps the server order after the folders');
	pg_assert_same('çanta.png', end($out['type']));
	pg_assert_same(array('asc', 'desc', 'desc', 'asc'), $out['dirs']);
}

function test_picker_js_cursor_steps()
{
	$out = picker_js_test_run('return [
		P.stepIndex(-1, 10, 4, "ArrowRight"), P.stepIndex(0, 10, 4, "ArrowRight"), P.stepIndex(0, 10, 4, "ArrowLeft"),
		P.stepIndex(1, 10, 4, "ArrowDown"), P.stepIndex(8, 10, 4, "ArrowDown"), P.stepIndex(5, 10, 4, "ArrowUp"),
		P.stepIndex(2, 10, 4, "ArrowUp"), P.stepIndex(5, 10, 4, "Home"), P.stepIndex(5, 10, 4, "End"),
		P.stepIndex(3, 10, 1, "ArrowRight"), P.stepIndex(3, 10, 1, "ArrowDown"), P.stepIndex(0, 0, 4, "ArrowDown"),
		P.stepIndex(0, 40, 1, "PageDown"), P.stepIndex(4, 10, 4, "x")
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(0, 1, 0, 5, 9, 1, 0, 0, 9, 3, 4, -1, 12, 4), $out);
}

function test_picker_js_search_helpers()
{
	$out = picker_js_test_run('var results = [
			{text: "a.jpg", path: [{id: 1, name: "Files"}, {id: 4, name: "Pictures"}]},
			{text: "b.jpg", path: [{id: 1, name: "Files"}, {id: 4, name: "Pictures"}, {id: 9, name: "Products"}]},
			{text: "c.jpg", path: [{id: 1, name: "Files"}, {id: 5, name: "Documents"}]}
		];
		var items = [{text: "Armut"}, {text: "İade.pdf"}, {text: "ilk.png"}, {text: "Bal"}];
		return {
			under: P.underFolder(results, 4).map(function (i) { return i.text; }),
			deep: P.underFolder(results, "9").map(function (i) { return i.text; }),
			ahead: [P.typeAheadIndex(items, "i", 0), P.typeAheadIndex(items, "i", 2), P.typeAheadIndex(items, "ia", 0), P.typeAheadIndex(items, "q", 0), P.typeAheadIndex(items, "", 0)],
			path: P.pathText(results[1].path),
			types: P.typesLabel(["png", "svg"]),
			fill: P.fill("{n} results for “{q}”", {n: 8, q: "kupa"}),
			keep: P.fill("{x} stays", {}),
			icons: [P.fileIcon("PDF"), P.fileIcon("jpeg"), P.fileIcon("docx"), P.fileIcon("bin")]
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('a.jpg', 'b.jpg'), $out['under'], 'the open folder and the folders under it');
	pg_assert_same(array('b.jpg'), $out['deep']);
	pg_assert_same(array(1, 2, 1, -1, -1), $out['ahead'], 'İ and i alike, wrapping from the cursor');
	pg_assert_same('Files / Pictures / Products', $out['path']);
	pg_assert_same('PNG, SVG', $out['types']);
	pg_assert_same('8 results for “kupa”', $out['fill']);
	pg_assert_same('{x} stays', $out['keep']);
	pg_assert_same(array('bi-file-earmark-pdf', 'bi-file-earmark-image', 'bi-file-earmark-word', 'bi-file-earmark'), $out['icons']);
}

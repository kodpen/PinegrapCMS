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
			labels: names(P.sortItems([
				{text: "A", type: "standard", type_label: "Standart"},
				{text: "B", type: "custom form", type_label: "Özel Form"},
				{text: "C", type: "login", type_label: "Giriş Yap"}
			], "type", "asc", c)),
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
	pg_assert_same(array('C', 'B', 'A'), $out['labels'], 'pages sort by the type label shown, not the inner key');
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
			path: [P.pathText(results[1].path), P.pathText([{id: 1, name: "Files"}]), P.pathText(null)],
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
	pg_assert_same(array('Pictures / Products', 'Files', ''), $out['path'], 'the top folder is left out below it');
	pg_assert_same('PNG, SVG', $out['types']);
	pg_assert_same('8 results for “kupa”', $out['fill']);
	pg_assert_same('{x} stays', $out['keep']);
	pg_assert_same(array('bi-file-earmark-pdf', 'bi-file-earmark-image', 'bi-file-earmark-word', 'bi-file-earmark'), $out['icons']);
}

function test_picker_js_upload_helpers()
{
	$out = picker_js_test_run('return {
		ext: [P.extensionOf("Logo.PNG"), P.extensionOf("a.tar.gz"), P.extensionOf("noext"), P.extensionOf("dir.v2/file"), P.extensionOf(null)],
		refuse: [
			P.uploadRefusal({name: "a.png", size: 10}, ["png", "svg"], 100),
			P.uploadRefusal({name: "a.JPG", size: 10}, ["png", "svg"], 100),
			P.uploadRefusal({name: "a.png", size: 200}, ["png"], 100),
			P.uploadRefusal({name: "a.bin", size: 200}, [], 0)
		],
		sizes: [P.formatSize(0, "en"), P.formatSize(2048, "en"), P.formatSize(1572864, "en"), P.formatSize(1572864, "tr")]
	};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('png', 'gz', '', '', ''), $out['ext']);
	pg_assert_same(array('', 'type', 'size', ''), $out['refuse'], 'the type first, then the size; no limit takes any');
	pg_assert_same(array('0 B', '2 KB', '1.5 MB', '1,5 MB'), $out['sizes']);
}

function test_picker_js_recent_and_picks()
{
	$out = picker_js_test_run('var a = {value: "a.png", text: "a.png", type: "png", folder: 4, thumb: "t", size: 9};
		var b = {value: "b.png", text: "b.png", folder: 4}, c = {value: 7, text: "c"}, f = {value: 3, isFolder: true};
		var list = P.addRecent([], a, 12);
		list = P.addRecent(list, b, 12);
		list = P.addRecent(list, a, 12);
		var capped = [1, 2, 3, 4].reduce(function (l, n) { return P.addRecent(l, {value: n}, 3); }, []);
		var picks = P.togglePick([], a);
		picks = P.togglePick(picks, b);
		var removed = P.togglePick(picks, {value: "a.png"});
		return {
			recent: list,
			capped: capped.map(function (e) { return e.value; }),
			bad: P.addRecent("x", c, 12).length,
			picks: picks.map(function (p) { return p.value; }),
			removed: removed.map(function (p) { return p.value; }),
			range: P.rangePick([b], [a, f, b, c], 3, 0).map(function (p) { return p.value; }),
			numeric: P.togglePick([c], {value: "7"}).length
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('value' => 'a.png', 'text' => 'a.png', 'type' => 'png', 'folder' => 4), array('value' => 'b.png', 'text' => 'b.png', 'folder' => 4)), $out['recent'], 'newest first, each value once, only what finds it again');
	pg_assert_same(array(4, 3, 2), $out['capped']);
	pg_assert_same(1, $out['bad'], 'a broken stored list starts again');
	pg_assert_same(array('a.png', 'b.png'), $out['picks'], 'in the order picked');
	pg_assert_same(array('b.png'), $out['removed']);
	pg_assert_same(array('b.png', 'a.png', 7), $out['range'], 'folders and files already picked are passed over');
	pg_assert_same(0, $out['numeric'], 'a value matches as text');
}

function test_picker_js_pages()
{
	$out = picker_js_test_run('var order = ["standard", "form", "catalog", "checkout"];
		var items = [
			{isFolder: true, text: "Blog"},
			{value: 1, text: "Hakkımızda", type_group: "standard"},
			{value: 2, text: "İletişim", type_group: "form"},
			{value: 3, text: "Kış İndirimi", type_group: "standard", mark: "archived"},
			{value: 4, text: "Eski", type_group: "checkout", mark: "binned"}
		];
		var extra = P.extraItems([
			{value: "tab:a1", text: "İade koşulları", type: "standard", note: "Not saved yet"},
			{value: "", text: "no value"},
			{value: "tab:b2"},
			{value: 9, text: "Sepet", type_group: "checkout"}
		]);
		function values(list) { return list.map(function (i) { return i.value; }); }
		return {
			pickable: items.map(P.pickable),
			chips: P.groupChips(items, order, ""),
			kept: P.groupChips(items, order, "catalog"),
			only: values(P.inGroup(items, "standard")).concat([P.inGroup(items, "").length]),
			range: values(P.rangePick([], items, 0, 4)),
			extra: extra,
			found: values(P.extraMatches(extra, "IADE")).concat(values(P.extraMatches(extra, "ep"))).concat(values(P.extraMatches(extra, " ")))
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(false, true, true, false, false), $out['pickable'], 'neither an archived nor a binned page is chosen, nor a folder');
	pg_assert_same(array('standard', 'form', 'checkout'), $out['chips'], 'a chip for each group in view, in the set order');
	pg_assert_same(array('standard', 'form', 'catalog', 'checkout'), $out['kept'], 'the chosen chip stays though nothing in view has it');
	pg_assert_same(array(null, 1, 3, 5), $out['only'], 'folders stay beside the chosen type');
	pg_assert_same(array(1, 2), $out['range'], 'a range passes the marked pages over');
	pg_assert_same(array(
		array('value' => 'tab:a1', 'text' => 'İade koşulları', 'type' => 'standard', 'type_group' => 'standard', 'note' => 'Not saved yet', 'extra' => true),
		array('value' => '9', 'text' => 'Sepet', 'type' => '', 'type_group' => 'checkout', 'note' => '', 'extra' => true),
	), $out['extra'], 'an entry needs a value and a name; the value is kept as text');
	pg_assert_same(array('tab:a1', '9'), $out['found'], 'folded as the server matches; nothing for an empty text');
}

// The field's line under a name and its count line match the server's
// (pg_picker_field_meta()).
function test_picker_js_field_helpers()
{
	$out = picker_js_test_run('return {
			image: P.fieldMeta({path: [{name: "Files"}, {name: "Logos"}, {name: "2026"}], width: 512, height: 256, size_label: "48 KB"}),
			root: P.fieldMeta({path: [{name: "Files"}], size_label: "1 MB"}),
			page: P.fieldMeta({address: "/about", path: [{name: "Pages"}, {name: "Company"}]}),
			none: P.fieldMeta(null),
			keys: [P.resultsKey(1, false), P.resultsKey(5, false), P.resultsKey(8, true)]
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same('Logos / 2026 · 512 × 256 · 48 KB', $out['image'], 'the top folder left out below it');
	pg_assert_same('Files · 1 MB', $out['root'], 'a file in the top folder names it');
	pg_assert_same('/about · Company', $out['page']);
	pg_assert_same('', $out['none']);
	pg_assert_same(array('1 result · in every folder', '{n} results · in every folder', '{n}+ results · in every folder'), $out['keys']);
}

// The dialog opens on the list of everything: a typed text on its results,
// a caller's own entry among them; All asks the server without a folder,
// sorted and paged by the cursor, with a sort of its own.
function test_picker_js_all_opening_and_sort()
{
	$out = picker_js_test_run('return {
			open: [P.openingMode("", false), P.openingMode("  ", false), P.openingMode("logo", false), P.openingMode("", true), P.openingMode("x", true)],
			first: P.allParams("image", "modified", "desc", "", 100),
			next: P.allParams("page", "name", "asc", "abc_-1", 50),
			sorts: [P.allSort(null, null, false), P.allSort("size", null, false), P.allSort("size", "asc", true), P.allSort("best", "asc", false), P.allSort("name", "sideways", false), P.allSort("type", "desc", true)],
			views: [P.viewFor(null, true, false), P.viewFor(null, false, false), P.viewFor("list", true, false), P.viewFor("grid", false, false), P.viewFor("grid", false, true), P.viewFor("x", true, false)]
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('all', 'all', 'search', 'extras', 'search'), $out['open'], 'All unless a text is typed or the value is a caller entry');
	pg_assert_same(array('kind' => 'image', 'request' => 'list', 'sort' => 'modified', 'order' => 'desc', 'per_page' => 100), $out['first'], 'no folder: the list of everything, no cursor on the first page');
	pg_assert_same(array('kind' => 'page', 'request' => 'list', 'sort' => 'name', 'order' => 'asc', 'per_page' => 50, 'cursor' => 'abc_-1'), $out['next']);
	pg_assert_same(array(
		array('sort' => 'modified', 'dir' => 'desc'),
		array('sort' => 'size', 'dir' => 'desc'),
		array('sort' => 'modified', 'dir' => 'desc'),
		array('sort' => 'modified', 'dir' => 'desc'),
		array('sort' => 'name', 'dir' => 'asc'),
		array('sort' => 'type', 'dir' => 'desc'),
	), $out['sorts'], 'newest first by default; a page has no size; best match is a search sort');
	pg_assert_same(array('grid', 'list', 'list', 'grid', 'list', 'grid'), $out['views'], 'pictures as tiles, files and pages as a list, the person\'s choice kept');
}

// Rows of the next page join without repeats, read near the end; each row
// carries its folder line; Upload on All tries the value's folder, the last
// one uploaded to, then the top folders.
function test_picker_js_all_rows_line_and_upload_target()
{
	$out = picker_js_test_run('return {
			rows: P.appendRows([{value: 1}, {value: 2}], [{value: 2}, {value: 3}, {value: 3}]).map(function (r) { return r.value; }),
			fresh: P.appendRows(null, [{value: "a.jpg"}]).length,
			near: [P.nearEnd(0, 500, 5000), P.nearEnd(4000, 500, 5000), P.nearEnd(3500, 500, 5000), P.nearEnd(0, 100, 250)],
			lines: [
				P.metaLine({path: [{name: "Files"}, {name: "Ürünler"}, {name: "2026"}], width: 10, height: 5, size_label: "1 KB"}, "all", false),
				P.metaLine({path: [{name: "Files"}, {name: "Ürünler"}], width: 10, height: 5, size_label: "1 KB"}, "folder", false),
				P.metaLine({path: [{name: "Pages"}, {name: "Kurumsal"}], address: "/hakkimizda", type_label: "Standard"}, "all", true),
				P.metaLine({address: "/hakkimizda", type_label: "Standard"}, "folder", true),
				P.metaLine({isFolder: true}, "all", false),
				P.metaLine({extra: true, note: "Not saved yet", path: []}, "all", true)
			],
			targets: [P.uploadTargets(7, 9, [3, 7, 4]), P.uploadTargets(0, null, [3]), P.uploadTargets("12", "12", []), P.uploadTargets(0, 0, [])]
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(1, 2, 3), $out['rows'], 'a row the next page repeats is drawn once');
	pg_assert_same(1, $out['fresh']);
	pg_assert_same(array(false, true, true, true), $out['near'], 'the next page is read within two screens of the end');
	pg_assert_same(array('Ürünler / 2026', '10 × 5 · 1 KB', 'Kurumsal', '/hakkimizda · Standard', null, 'Not saved yet'), $out['lines'], 'All draws the folder line, as a search result');
	pg_assert_same(array(array(7, 9, 3, 4), array(3), array(12), array()), $out['targets']);
}

// The dialog opens on All: its row heads the tree, before Recently used;
// All keeps its own sort keys; the page tells which kinds are pictures.
function test_picker_js_all_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_picker.js');

	$all = strpos($js, 'this.treeBox.appendChild(this.allRow);');
	$recent = strpos($js, 'this.treeBox.appendChild(this.recentRow);');
	pg_assert_true($all !== false && $recent !== false && $all < $recent, 'the All row is the tree\'s first');
	pg_assert_true(strpos($js, "remember('all.sort', this.allSortKey);") !== false, 'All keeps its sort apart from a folder\'s');
	pg_assert_true(strpos($js, "allSort(remember('all.sort'), remember('all.dir'), this.pages)") !== false);
	pg_assert_true(strpos($js, "self.showExtras() : self.showAll();") !== false, 'the dialog opens on All, a stored value too');
	pg_assert_true(strpos($js, 'request(allParams(') !== false, 'All is read from picker.php without a folder');
	pg_assert_true(strpos($js, "t('All folders')") !== false, 'the phone chip names All');

	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/picker.php');
	pg_assert_true(strpos($php, "'picture_kinds' => \$picture_kinds") !== false);

	foreach (array('All folders', 'Show in its folder', 'Upload into', 'There is nothing to choose yet') as $text) {
		pg_assert_true(array_key_exists($text, pg_picker_texts()), $text . ' is handed to the dialog');
	}
}

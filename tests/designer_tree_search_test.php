<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the designer Overview's tree search and page map: the treesearch
 * part (assets/js/style_designer_treesearch.js) folding Turkish letters
 * (_tsFold()), indexing the page with each element's path (_tsIndex(),
 * _tsPathCut()) and finding names, texts, classes and ids (_tsMatch()); the
 * core's page map placing the sections by their size (_sdTreeMapRanges())
 * and a place on it (_sdTreeMapPos(), _sdTreeMapAt()). Runs the functions
 * under Node; skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One four-space function of a designer script, from its declaration to its
// closing brace.
function designer_tree_search_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the named functions of `$file` and returns its
// JSON output. Null without Node or when a function is missing.
function designer_tree_search_run($file, $names, $script)
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

	$js = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
	$src = 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;"); }' . "\n";

	foreach ($names as $name) {
		$fn = designer_tree_search_fn($js, $name);

		if (!pg_assert_true($fn !== '', $file . ' has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgtreesearch');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// The search's functions under Node, with a page of sections, a menu of
// links named alike and elements found by text, class and id.
function designer_tree_search_page_script()
{
	return 'function N(name, kids, f) { var n = { name: name, children: kids || [], f: f || {} }; return n; }' . "\n"
		. 'var orders = N("Link", [], { text: "My orders" });' . "\n"
		. 'var page = N("body", [' . "\n"
		. '  N("Store Account Bar", [N("Account area", [N("Signed in", [N("Account Menu", [N("Menu Item", [orders]), N("Menu Item", [N("Link", [], { text: "My addresses" })])])])])]),' . "\n"
		. '  N("Main Content", [N("Hero", [N("Heading", [], { text: "Işık ve renk" }), N("Button", [], { cls: "btn btn-primary hero-cta" })], { id: "hero-main" })]),' . "\n"
		. '  N("Footer", [N("Link List", []), N("Column", [], { label: "Footer links" }), N("İkon", [])])' . "\n"
		. ']);' . "\n"
		. 'var entries = _tsIndex(page, function (n) { return n.children; }, function (n) { return { name: n.name, label: n.f.label, text: n.f.text, cls: n.f.cls, id: n.f.id }; });' . "\n"
		. 'function find(q) { var r = _tsMatch(entries, q, 100); return { total: r.total, hits: r.hits.map(function (h) { var e = entries[h.i]; return [e.label || e.name, h.field, h.at, h.len, e.path]; }) }; }' . "\n";
}

// Turkish letters fold both ways, one letter for one, so a place found in
// the folded text is the place in the text.
function test_designer_tree_search_folds_turkish_letters_in_place()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold'),
		'var words = ["İkon", "ikon", "IŞIK", "ışık", "Bağlantı", "Çıkış yap", "Öğe", "Ünlü", "Kâğıt", "naïve", "😀x"];' . "\n"
		. 'console.log(JSON.stringify(words.map(function (w) { var f = _tsFold(w); return [f, f.length === w.length]; })));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('ikon', true), array('ikon', true), array('isik', true), array('isik', true), array('baglanti', true),
		array('cikis yap', true), array('oge', true), array('unlu', true), array('kagit', true), array('naive', true),
	), array_slice($res, 0, 10), 'İ, I, ı and i are one letter; ş ğ ç ö ü â lose their marks; the length stays');
	pg_assert_same(true, $res[10][1], 'a letter outside the plane keeps its length too');
}

// Only a mark comes off a letter: a Korean syllable is not cut to its
// first consonant, so "한국" no longer finds "하고".
function test_designer_tree_search_folds_only_marks()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold'),
		'console.log(JSON.stringify([_tsFold("한국"), _tsFold("하고"), _tsFold("é"), _tsFold("e\u0301")]));');

	if ($res === null) {
		return;
	}

	pg_assert_same('한국', $res[0], 'a Hangul syllable stays whole');
	pg_assert_true($res[0] !== $res[1], 'two words with the same first consonants stay apart');
	pg_assert_same('e', $res[2], 'a letter with a mark folds to the plain letter');
	pg_assert_same("e\u{301}", $res[3], 'a letter and a separate mark stay one for one');
}

// The name, the designer label, the text, the class and the id are found;
// a name that starts with the words comes first, then one that holds them,
// then an id, a class and a text.
function test_designer_tree_search_finds_name_text_class_and_id()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold', '_tsPlain', '_tsPathCut', '_tsIndex', '_tsMatch'),
		designer_tree_search_page_script()
		. 'console.log(JSON.stringify({ link: find("link"), ikon: find("ikon"), isik: find("ISIK"), cls: find("hero-cta"), id: find("HERO-MAIN"), text: find("my orders"), none: find("zzz"), blank: find("   ") }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(4, $res['link']['total'], 'four elements hold "link"');
	pg_assert_same(array('Link', 'name', 0), array_slice($res['link']['hits'][0], 0, 3), 'a name that starts with it first');
	pg_assert_same(array('Link List', 'name', 0), array_slice($res['link']['hits'][2], 0, 3), 'in the page\'s order inside a group');
	pg_assert_same(array('Footer links', 'label', 7), array_slice($res['link']['hits'][3], 0, 3), 'a designer label that holds it after the names that start with it');
	pg_assert_same(array(array('İkon', 'name', 0, 4)), array_map(function ($h) { return array_slice($h, 0, 4); }, $res['ikon']['hits']), '"ikon" finds "İkon"');
	pg_assert_same(array('Heading', 'text', 0, 4), array_slice($res['isik']['hits'][0], 0, 4), '"ISIK" finds the text "Işık ve renk"');
	pg_assert_same(array('Button', 'cls', 16, 8), array_slice($res['cls']['hits'][0], 0, 4), 'a class, with its place in the class list');
	pg_assert_same(array('Hero', 'id', 0, 9), array_slice($res['id']['hits'][0], 0, 4), 'an id, upper case typed');
	pg_assert_same(array('Link', 'text'), array_slice($res['text']['hits'][0], 0, 2), 'two words of a text');
	pg_assert_same(0, $res['none']['total'], 'nothing for words no element holds');
	pg_assert_same(0, $res['blank']['total'], 'blank words search nothing');
}

// A name or designer label more than one element shows gets the row hint
// after it in its result, so three "App option" results read apart; a name
// shown once gets none.
function test_designer_tree_search_hints_names_shown_twice()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold', '_tsMark', '_tsSnippet', '_tsItem'),
		'var _sdT = function (s) { return s; };' . "\n"
		. 'function E(name, label) { return { node: { n: name + label }, name: name, label: label, path: [], fold: {} }; }' . "\n"
		. 'var state = { active: 0, entries: [E("Div", "App option"), E("Div", "App option"), E("Fieldset", "App options")], shown: { "app option": 2, "app options": 1 } };' . "\n"
		. 'var host = { icon: function () { return ""; }, hint: function (n) { return n.n === "DivApp option" ? "Notes" : "Other"; } };' . "\n"
		. 'var hit = function (i) { return { i: i, field: "label", at: 0, len: 3 }; };' . "\n"
		. 'console.log(JSON.stringify({ twice: _tsItem(host, hit(0), 0), once: _tsItem(host, hit(2), 1) }));');

	if ($res === null) {
		return;
	}

	pg_assert_true(strpos($res['twice'], '<span class="sd-ov-res-extra">· Notes</span>') !== false, 'a label shown twice gets its hint');
	pg_assert_true(strpos($res['twice'], '<mark>App</mark> option') !== false, 'with the words marked in the label');
	pg_assert_false(strpos($res['once'], 'sd-ov-res-extra') !== false, 'a label shown once gets none');
}

// The part may arrive after the box changed: an answer draws only while the
// box still holds its words, so an emptied box keeps no count and an older
// search never draws over a newer one.
function test_designer_tree_search_drops_a_late_answer()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold', '_tsPlain', '_tsPathCut', '_tsIndex', '_tsMatch', '_tsSearch'),
		designer_tree_search_page_script()
		. 'var MAX_SHOWN = 100, state = { entries: [], hits: [], active: 0, shown: {} }, drawn = [];' . "\n"
		. 'function _tsDraw(host, total) { drawn.push(host.q + ":" + total); }' . "\n"
		. 'var box = "";' . "\n"
		. 'function host(q) { return { q: q, live: function () { return q === box; }, root: page, kids: function (n) { return n.children; }, fields: function (n) { return { name: n.name, label: n.f.label, text: n.f.text, cls: n.f.cls, id: n.f.id }; } }; }' . "\n"
		. 'var emptied = host("link"); _tsSearch(emptied, "link"); var afterEmpty = drawn.slice();' . "\n"
		. 'box = "link"; var older = host("link"); box = "links"; _tsSearch(host("links"), "links"); _tsSearch(older, "link");' . "\n"
		. 'box = "link"; _tsSearch(host("link"), "link");' . "\n"
		. '_tsSearch({ root: page, kids: function (n) { return n.children; }, fields: function () { return { name: "Link" }; }, q: "nolive" }, "link");' . "\n"
		. 'console.log(JSON.stringify({ afterEmpty: afterEmpty, drawn: drawn }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(), $res['afterEmpty'], 'the box was emptied before the answer: nothing is drawn');
	pg_assert_same(array('links:1', 'link:4'), array_slice($res['drawn'], 0, 2), 'the older "link" answer after "links" drew is dropped; "link" draws once the box holds it again');
	pg_assert_same(3, count($res['drawn']), 'a host with no live check always draws');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($core, "_sdPartCall('treesearch', 'search', [_sdTreeSearchHost(q), q])") !== false, 'the core hands the search its words');
	pg_assert_true(strpos($core, 'live: function () { return q === undefined || q === _sdTreeSearchQ; }') !== false, 'and the host checks them against the box');
}

// A result's path: its section down to its parent; longer than four steps,
// the section, a gap and the last two.
function test_designer_tree_search_gives_each_result_its_path()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold', '_tsPlain', '_tsPathCut', '_tsIndex', '_tsMatch'),
		designer_tree_search_page_script()
		. 'console.log(JSON.stringify({ orders: find("my orders").hits[0][4], hero: find("hero-main").hits[0][4], section: find("main content").hits[0][4], cut: _tsPathCut(["a", "b", "c", "d"]), long: _tsPathCut(["a", "b", "c", "d", "e"]) }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Store Account Bar', '…', 'Account Menu', 'Menu Item'), $res['orders'], 'a deep link: its section, a gap and its last two levels');
	pg_assert_same(array('Main Content'), $res['hero'], 'one level down: its section');
	pg_assert_same(array(), $res['section'], 'a section has no path');
	pg_assert_same(array('a', 'b', 'c', 'd'), $res['cut'], 'four steps stay');
	pg_assert_same(array('a', '…', 'd', 'e'), $res['long'], 'five steps are cut');
}

// The page map: each section's block by its size, none under the floor
// while all have room for it, filling the map; a place inside its block;
// a height back to its place.
function test_designer_tree_search_map_ranges_follow_the_sections_sizes()
{
	$res = designer_tree_search_run('style_designer.src.js', array('_sdTreeMapRanges', '_sdTreeMapPos', '_sdTreeMapAt'),
		'function round(r) { return r.map(function (x) { return [Math.round(x.top * 100) / 100, Math.round(x.height * 100) / 100]; }); }' . "\n"
		. 'var sizes = [44, 59, 313, 22, 68], starts = [0, 44, 103, 416, 438];' . "\n"
		. 'var r = _sdTreeMapRanges(sizes, 3);' . "\n"
		. 'var sum = r.reduce(function (s, x) { return s + x.height; }, 0);' . "\n"
		. 'var many = []; for (var i = 0; i < 50; i++) many.push(i === 0 ? 1000 : 1);' . "\n"
		. 'var m = _sdTreeMapRanges(many, 3);' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '  r: round(r), sum: Math.round(sum * 1000) / 1000,' . "\n"
		. '  tiny: round(_sdTreeMapRanges([1, 1000], 3)), empty: _sdTreeMapRanges([], 3), zero: round(_sdTreeMapRanges([0, 0], 3)),' . "\n"
		. '  many: [Math.round(m[1].height * 1000) / 1000, Math.round(m.reduce(function (s, x) { return s + x.height; }, 0))],' . "\n"
		. '  pos: [_sdTreeMapPos(r, starts, sizes, 0), Math.round(_sdTreeMapPos(r, starts, sizes, 103 + 156) * 100) / 100, _sdTreeMapPos(r, starts, sizes, 506), _sdTreeMapPos(r, starts, sizes, -1)],' . "\n"
		. '  at: [_sdTreeMapAt(r, starts, sizes, 0), _sdTreeMapAt(r, starts, sizes, 99.99), _sdTreeMapAt(r, starts, sizes, 140), _sdTreeMapAt(r, starts, sizes, r[2].top + r[2].height / 2), _sdTreeMapAt([], [], [], 5)]' . "\n"
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(100.0, (float) $res['sum'], 'the blocks fill the map');
	pg_assert_same(array(array(0, 10.39), array(10.39, 12.91), array(23.3, 55.58), array(78.88, 6.7), array(85.58, 14.42)), $res['r'], 'each block by its size, over a 3 % floor');
	pg_assert_same(array(array(0, 3.09), array(3.09, 96.91)), $res['tiny'], 'a one-element section keeps its floor');
	pg_assert_same(array(), $res['empty'], 'no sections, no blocks');
	pg_assert_same(array(array(0, 50), array(50, 50)), $res['zero'], 'sections of no size share the map');
	pg_assert_same(array(2.0, 100), array(round($res['many'][0], 3), $res['many'][1]), 'fifty sections: the floor falls to what each can have');
	pg_assert_same(0, $res['pos'][0], 'the first place is the map\'s top');
	pg_assert_same(51.0, (float) $res['pos'][1], 'the middle of the largest section is the middle of its block');
	pg_assert_same(100, $res['pos'][2], 'past the page\'s end is the map\'s foot');
	pg_assert_same(0, $res['pos'][3], 'before the page is the map\'s top');
	pg_assert_same(array('section' => 0, 'idx' => 0), $res['at'][0], 'the top is the first section\'s first place');
	pg_assert_same(array('section' => 4, 'idx' => 505), $res['at'][1], 'the foot is the last place');
	pg_assert_same(array('section' => 4, 'idx' => 505), $res['at'][2], 'below the map is the last place');
	pg_assert_same(array('section' => 2, 'idx' => 259), $res['at'][3], 'the middle of a block is the middle of its section');
	pg_assert_same(null, $res['at'][4], 'no map, no place');
}

// The search is a part: built from its own file, handed to the editor and
// run from the box; the row filter it replaces has left the core.
function test_designer_tree_search_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_treesearch.min.js', $parts['treesearch'] ?? '', 'the tree search is a part');
	pg_assert_same(array('assets/js/style_designer_treesearch.js'), pg_js_bundles()['assets/js/style_designer_treesearch.min.js'] ?? array(), 'built from its file');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_treesearch.js');
	pg_assert_true(strpos($part, "StyleDesigner.definePart('treesearch', function (K) {") !== false, 'the part registers');

	foreach (array('_tsFold', '_tsIndex', '_tsMatch') as $name) {
		pg_assert_false(strpos($core, 'function ' . $name . '(') !== false, $name . ' is not in the core');
	}

	pg_assert_false(strpos($core, 'function filterOverviewTree(') !== false, 'the old row filter is gone');
	pg_assert_true(strpos($core, "_sdPartCall('treesearch', 'search'") !== false, 'the core runs the part to search');
	pg_assert_true(strpos($core, "_sdPartCall('treesearch', 'key'") !== false, 'and to move through the results');
	pg_assert_same('always', pg_designer_part_files()['treesearch']['when'], 'the screen hands the part over');
}

// A text shows its words as the page does: the host takes the tags out but
// leaves the entities, so the part decodes them. "&" finds "Tom &amp;
// Jerry" and the result shows "Tom & Jerry"; "amp" finds nothing.
function test_designer_tree_search_reads_a_text_as_the_page_shows_it()
{
	$res = designer_tree_search_run('style_designer_treesearch.js', array('_tsFold', '_tsPlain', '_tsPathCut', '_tsIndex', '_tsMatch'),
		'function N(name, kids, f) { return { name: name, children: kids || [], f: f || {} }; }' . "\n"
		. 'var page = N("body", [N("Heading", [], { text: "Tom &amp; Jerry &lt;3" }), N("Text", [], { text: "&#252;r&#xFC;n &copy; &unknown;" })]);' . "\n"
		. 'var entries = _tsIndex(page, function (n) { return n.children; }, function (n) { return { name: n.name, text: n.f.text }; });' . "\n"
		. 'function find(q) { var r = _tsMatch(entries, q, 100); return r.hits.map(function (h) { return [entries[h.i].name, h.field, h.at]; }); }' . "\n"
		. 'console.log(JSON.stringify({ texts: entries.map(function (e) { return e.text; }), amp: find("&"), word: find("amp"), lt: find("<3"), urun: find("ürün") }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Tom & Jerry <3', 'ürün © &unknown;'), $res['texts'], 'the entities decoded, an unknown one kept as written');
	pg_assert_same(array(array('Heading', 'text', 4), array('Text', 'text', 7)), $res['amp'], '"&" finds the decoded sign at its place, and the unknown entity kept as written');
	pg_assert_same(array(), $res['word'], 'the entity\'s name is no word');
	pg_assert_same(array(array('Heading', 'text', 12)), $res['lt'], '"<3" finds &lt;3');
	pg_assert_same(array(array('Text', 'text', 0)), $res['urun'], 'a numeric entity is its letter');
}

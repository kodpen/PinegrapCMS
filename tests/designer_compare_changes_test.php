<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The change reader of Compare's Look tab
 * (assets/js/pg_compare_changes.js, window.PgCompareChanges): two designer
 * trees in, what changed out (added, removed, moved, words, picture, link,
 * look, other), each with its sentence and its place. Runs in Node; skipped
 * where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PgCompareChanges loaded as C and the
// helpers T(), N() and P() (a tree, a node, a page section); its `out`
// comes back decoded. Null when Node cannot run.
function compare_changes_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_compare_changes.js') . "\n"
		. "var C = window.PgCompareChanges, out = null;\n"
		. "function N(id, type, props, children) { return { _id: id, type: type, props: props || {}, children: children || [] }; }\n"
		. "function T(children) { return N('r', 'root', {}, children); }\n"
		. "function H(id, text) { return N(id, 'content', { contentType: 'heading', tag: 'h2', text: text, cssClass: '' }); }\n"
		. "function S(id, label, children) { return N(id, 'semantic', { tag: 'section', _label: label, cssClass: 'py-5' }, children); }\n"
		. "function clone(v) { return JSON.parse(JSON.stringify(v)); }\n"
		. "function brief(r) { return r.changes.map(function (c) { return c.kind + ':' + c.node + ':' + c.text; }); }\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgcc');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Equal trees, a page never published and an unreadable side.
function test_compare_changes_states()
{
	$res = compare_changes_test_node('var a = T([S("s1", "Prices", [H("h1", "Our prices")])]);'
		. ' out = [C.read(a, clone(a)), C.read(null, a), C.read(JSON.stringify(a), JSON.stringify(a)), C.read(a, "{nope")];');

	if ($res === null) {
		return;
	}

	pg_assert_same('same', $res[0]['state'], 'equal trees: nothing has changed');
	pg_assert_same(array(), $res[0]['changes'], 'equal trees: no change');
	pg_assert_same('new', $res[1]['state'], 'no live tree: the page was never on the site');
	pg_assert_same(array(), $res[1]['changes'], 'no live tree: no change listed');
	pg_assert_same('same', $res[2]['state'], 'trees are read from JSON text too');
	pg_assert_same('unreadable', $res[3]['state'], 'a side that is no tree says so');
}

// An added or removed subtree is one change at its top, with its size.
function test_compare_changes_added_and_removed()
{
	$res = compare_changes_test_node('var a = T([S("s1", "Hero", [H("h1", "Hi")]), S("s2", "Team", [H("h2", "Us"), H("h3", "Them")])]);'
		. ' var b = T([S("s1", "Hero", [H("h1", "Hi")]), S("s3", "Prices", [H("h4", "Cheap")])]);'
		. ' var r = C.read(a, b); out = { brief: brief(r), changes: r.changes, state: r.state };');

	if ($res === null) {
		return;
	}

	pg_assert_same('changed', $res['state'], 'the state');
	pg_assert_same(array('added:s3:Section added: Prices', 'removed:s2:Section removed: Team'), $res['brief'], 'one change per subtree, added first');
	pg_assert_same(2, $res['changes'][0]['count'], 'the added section counts its elements');
	pg_assert_same(3, $res['changes'][1]['count'], 'the removed section counts its elements');
	pg_assert_same('after', $res['changes'][0]['side'], 'an added element is found on the draft');
	pg_assert_same('before', $res['changes'][1]['side'], 'a removed element is found on the live page');
	pg_assert_same('s3', $res['changes'][0]['section'], 'its section is itself');
	pg_assert_same('r', $res['changes'][0]['parent'], 'its parent');
}

// Words, picture and link changes name the element and keep old and new.
function test_compare_changes_words_picture_link()
{
	$res = compare_changes_test_node('var img = N("i1", "content", { contentType: "image", src: "/a.jpg", alt: "Cup" });'
		. ' var lnk = N("l1", "content", { contentType: "link", text: "Menu", href: "{{page:menu}}", target: "" });'
		. ' var p = N("p1", "content", { contentType: "paragraph", text: "<b>Old</b>&nbsp;words" });'
		. ' var a = T([S("s1", "Hero", [H("h1", "Hello"), img, lnk, p])]);'
		. ' var b = clone(a); var s = b.children[0].children;'
		. ' s[0].props.text = "Hello there"; s[1].props.src = "/b.jpg"; s[1].props.alt = "Mug";'
		. ' s[2].props.href = "{{page:prices}}"; s[2].props.target = "_blank"; s[3].props.text = "Old <i>words</i>";'
		. ' var r = C.read(a, b); out = { brief: brief(r), changes: r.changes };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		'picture:i1:Picture replaced',
		'words:h1:Heading text changed',
		'words:i1:Picture description changed',
		'link:l1:Link changed',
		'look:p1:Text look changed',
	), $res['brief'], 'one change per kind, most visible first; same words in new markup are a look change');

	$words = $res['changes'][1];
	pg_assert_same('Hello', $words['before'], 'the old words');
	pg_assert_same('Hello there', $words['after'], 'the new words');
	pg_assert_same('both', $words['side'], 'a kept element is found on both');
	pg_assert_same('s1', $words['section'], 'its section');
	pg_assert_same('/a.jpg', $res['changes'][0]['before'], 'the old picture');
	pg_assert_same('/b.jpg', $res['changes'][0]['after'], 'the new picture');
	pg_assert_same('{{page:menu}}', $res['changes'][3]['before'], 'the old link');
	pg_assert_same('{{page:prices}}', $res['changes'][3]['after'], 'the new link');
}

// Moved up / down among the same siblings, moved to another parent; a
// sibling only pushed along by the move is not moved.
function test_compare_changes_moved()
{
	$res = compare_changes_test_node('var a = T([S("s1", "A"), S("s2", "B"), S("s3", "C"), S("s4", "D", [H("h1", "x")]), S("s5", "E")]);'
		. ' var b = T([S("s3", "C"), S("s1", "A"), S("s2", "B"), S("s4", "D"), S("s5", "E", [H("h1", "x")])]);'
		. ' var c = T([S("s1", "A"), S("s3", "C"), S("s4", "D", [H("h1", "x")]), S("s5", "E"), S("s2", "B")]);'
		. ' out = [brief(C.read(a, b)), brief(C.read(a, c))];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('moved:s3:Section moved up', 'moved:h1:Heading moved into Section: E'), $res[0], 'one moved up, one moved into another parent');
	pg_assert_same(array('moved:s2:Section moved down'), $res[1], 'only the moved one is moved');
}

// Look and other changes; editor-only props change nothing on the site.
function test_compare_changes_look_other_and_editor_props()
{
	$res = compare_changes_test_node('var a = T([S("s1", "Hero", [H("h1", "Hi"), N("w1", "component", { componentType: "cart", _bindings: { a: 1 } })])]);'
		. ' var b = clone(a); var s = b.children[0];'
		. ' s.props.cssClass = "py-3 bg-dark"; s.props._label = "Top"; s.props._notes = "check";'
		. ' s.children[0].props.tag = "h3"; s.children[1].props._bindings = { a: 2 }; s.children[1].props.newThing = "x";'
		. ' var c = clone(a); c.children[0].props._locked = true; c.children[0].props.cssClass = "py-5"; c.children[0].props.extra = "";'
		. ' out = [brief(C.read(a, b)), C.read(a, c).state];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		'look:s1:Section look changed',
		'look:h1:Heading look changed',
		'other:w1:Component changed',
	), $res[0], 'class and tag are look; an unknown prop is another change, once per element');
	pg_assert_same('same', $res[1], 'editor-only props and empty values change nothing');
}

// Grouped rows: look and other changes are one row each with their count.
function test_compare_changes_rows()
{
	$res = compare_changes_test_node('var a = T([S("s1", "A", [H("h1", "x"), H("h2", "y")]), N("w1", "component", { k: 1 }), N("w2", "component", { k: 1 })]);'
		. ' var b = clone(a); b.children[0].props.cssClass = "py-1"; b.children[0].children[0].props.cssClass = "m-0";'
		. ' b.children[0].children[1].props.text = "z"; b.children[1].props.k = 2; b.children[2].props.k = 2;'
		. ' var r = C.read(a, b); out = C.rows(r.changes).map(function (w) { return [w.kind, w.text, w.nodes]; });');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('words', 'Heading text changed', array('h2')),
		array('look', 'Look changed (2 elements)', array('s1', 'h1')),
		array('other', 'Other changes (2)', array('w1', 'w2')),
	), $res, 'the rows');
}

// Each change carries its place (the section around it, none for a
// section itself) and its element's short words; a row carries the place
// its changes share.
function test_compare_changes_place_and_words()
{
	$res = compare_changes_test_node('var hd = N("hd", "semantic", { tag: "header" }, [N("l1", "content", { contentType: "link", text: "A" }), N("l2", "content", { contentType: "link", text: "B" })]);'
		. ' var a = T([hd, S("s1", "Welcome", [H("h1", "Breathe. Move. Rest."), N("t1", "content", { contentType: "text", text: "<b>3</b> places left in a very long sentence that goes on" }), N("t2", "content", { contentType: "text", text: "Full" })])]);'
		. ' var b = clone(a); b.children[0].children.reverse(); b.children[1].children[0].props.text = "Breathe. Move. Rest well.";'
		. ' b.children[1].children[1].props.cssClass = "x"; b.children[1].children[2].props.cssClass = "x"; b.children.push(S("s2", "Prices"));'
		. ' var r = C.read(a, b); out = { c: r.changes.map(function (c) { return [c.node, c.place, c.words]; }),'
		. ' rows: C.rows(r.changes).map(function (w) { return [w.text, w.place]; }),'
		. ' tr: C.read(a, b, { place_named: "{what} - {name}", kind_section: "Bölüm" }).changes[1].place };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('s2', '', 'Prices'),
		array('h1', 'Section: Welcome', 'Breathe. Move. Rest well.'),
		array('l1', 'Header', 'A'),
		array('t1', 'Section: Welcome', '3 places left in a very long sentence t…'),
		array('t2', 'Section: Welcome', 'Full'),
	), $res['c'], 'place and words of each change');
	pg_assert_same(array(
		array('Section added: Prices', ''),
		array('Heading text changed', 'Section: Welcome'),
		array('Link moved down', 'Header'),
		array('Look changed (2 elements)', 'Section: Welcome'),
	), $res['rows'], 'a row keeps the place its changes share');
	pg_assert_same('Bölüm - Welcome', $res['tr'], 'the place is translated');
}

// Translated sentences are handed in and filled.
function test_compare_changes_texts()
{
	$res = compare_changes_test_node('var a = T([S("s1", "Hero", [H("h1", "Hi")])]);'
		. ' var b = T([S("s1", "Hero", [H("h1", "Selam")]), S("s2", "Fiyatlar")]);'
		. ' var texts = { added_named: "{what} eklendi: {name}", words: "{what} metni değişti", kind_section: "Bölüm", kind_heading: "Başlık" };'
		. ' out = brief(C.read(a, b, texts));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('added:s2:Bölüm eklendi: Fiyatlar', 'words:h1:Başlık metni değişti'), $res, 'handed-in texts are used');
}

// Nodes without an id and twice the same id are still matched and counted.
function test_compare_changes_ids_missing_or_doubled()
{
	$res = compare_changes_test_node('var a = T([{ type: "content", props: { contentType: "heading", text: "A" }, children: [] }, H("d", "one"), H("d", "two")]);'
		. ' var b = clone(a); b.children[0].props.text = "B"; b.children[2].props.text = "three";'
		. ' out = brief(C.read(a, b));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('words:#0:Heading text changed', 'words:d:Heading text changed'), $res, 'matched by place without an id, by order for a doubled id');
}

// The big fixture page compared with itself after edits: every edit is
// found, no change is lost.
function test_compare_changes_big_fixture()
{
	$res = compare_changes_test_node('var fs = require("fs"); var a = JSON.parse(fs.readFileSync(' . json_encode(dirname(__FILE__) . '/fixtures/designer/big_page.json') . ', "utf8"));'
		. ' var b = clone(a); var main = b.children[0]; var moved = main.children.splice(1, 1)[0]; main.children.push(moved);'
		. ' main.children[0].props.cssClass += " text-center"; main.children.splice(2, 1);'
		. ' var t0 = Date.now(); var r = C.read(a, b); var ms = Date.now() - t0;'
		. ' out = { kinds: r.changes.map(function (c) { return c.kind; }), ms: ms, same: C.read(a, clone(a)).state };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('removed', 'moved', 'look'), $res['kinds'], 'a removed section, a moved section, a look change');
	pg_assert_same('same', $res['same'], 'the fixture equals its copy');
	pg_assert_true($res['ms'] < 500, 'the 1,539-node page is read quickly (' . $res['ms'] . ' ms)');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the pure functions behind the designer's Overview rows
 * (assets/js/style_designer.src.js): the indent and depth badge of a row
 * (_sdTreeRowIndent()), which top-level section is open when a page is
 * opened (_sdTreeSectionStates()), the count on a section row
 * (_sdTreeNodeCount()) and the steps of the selection's path
 * (_sdTreePathSteps()). Runs the functions under Node; skipped where there
 * is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_overview_rows_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the named functions and returns its JSON output.
// Null without Node or when a function is missing.
function designer_overview_rows_run($names, $script)
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

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$src = '';

	foreach ($names as $name) {
		$fn = designer_overview_rows_fn($js, $name);

		if (!pg_assert_true($fn !== '', 'style_designer.js has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pgovrows');
	file_put_contents($file, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// A row 28 px with a 12 px indent: at most six indents, then a depth badge,
// and the label keeps 150 px, fourteen levels deep included.
function test_designer_overview_row_indent_and_depth_badge()
{
	$res = designer_overview_rows_run(array('_sdTreeRowIndent'),
		'var w = [0, 260, 293, 400];' . "\n"
		. 'var out = {};' . "\n"
		. 'w.forEach(function (width) { out[width] = []; for (var d = 1; d <= 14; d++) out[width].push(_sdTreeRowIndent(d, width)); });' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	// Without a width (nothing measured yet): the levels alone decide.
	pg_assert_same(0, $res[0][0]['indent'], 'a section row has no indent');
	pg_assert_same(0, $res[0][0]['badge'], 'a section row has no depth badge');
	pg_assert_same(12, $res[0][1]['indent'], 'one level is 12 px');
	pg_assert_same(72, $res[0][6]['indent'], 'six indents at the seventh level');
	pg_assert_same(0, $res[0][6]['badge'], 'no badge while the indents show every level');
	pg_assert_same(72, $res[0][13]['indent'], 'never more than six indents');
	pg_assert_same(14, $res[0][13]['badge'], 'deeper rows carry their level in a badge');

	foreach (array(260, 293, 400) as $width) {
		foreach ($res[$width] as $i => $row) {
			pg_assert_true($row['label'] >= 150, 'the label keeps 150 px at level ' . ($i + 1) . ' in ' . $width . ' px (got ' . $row['label'] . ')');
			pg_assert_true($row['indent'] <= 72, 'at most six indents in ' . $width . ' px');

			if ($row['indent'] < $i * 12) {
				pg_assert_same($i + 1, $row['badge'], 'a row drawn shallower than it is says its level in ' . $width . ' px');
			}
		}
	}

	// A narrow tree gives up indents before the label's 150 px.
	pg_assert_true($res[260][13]['indent'] < 72, 'a 260 px tree draws fewer indents at level 14');
	pg_assert_same(72, $res[400][13]['indent'], 'a 400 px tree draws all six');
}

// On opening, only the section that holds the selection is open.
function test_designer_overview_only_selection_section_open()
{
	$res = designer_overview_rows_run(array('_sdTreeSectionStates'),
		'var root = { _id: "r", children: [] };' . "\n"
		. 'var parents = new Map();' . "\n"
		. 'function add(p, id) { var n = { _id: id, children: [] }; p.children.push(n); parents.set(n, p); return n; }' . "\n"
		. 'var a = add(root, "a"), b = add(root, "b"), c = add(root, "c");' . "\n"
		. 'var b1 = add(b, "b1"), b2 = add(b1, "b2"); add(a, "a1");' . "\n"
		. 'var other = { _id: "x", children: [] };' . "\n"
		. 'var parentOf = function (n) { return parents.get(n) || null; };' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '  deep: _sdTreeSectionStates(root, b2, parentOf),' . "\n"
		. '  top: _sdTreeSectionStates(root, c, parentOf),' . "\n"
		. '  none: _sdTreeSectionStates(root, null, parentOf),' . "\n"
		. '  body: _sdTreeSectionStates(root, root, parentOf),' . "\n"
		. '  stray: _sdTreeSectionStates(root, other, parentOf)' . "\n"
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(false, true, false), $res['deep'], 'the section above a deep selection is the open one');
	pg_assert_same(array(false, false, true), $res['top'], 'a selected section is open');
	pg_assert_same(array(false, false, false), $res['none'], 'no selection: every section closed');
	pg_assert_same(array(false, false, false), $res['body'], 'the body selected: every section closed');
	pg_assert_same(array(false, false, false), $res['stray'], 'a node of another tree opens nothing');
}

// A section row counts every element it holds.
function test_designer_overview_section_count()
{
	$res = designer_overview_rows_run(array('_sdTreeNodeCount'),
		'var t = { children: [ { children: [ {}, { children: [ {}, {} ] } ] }, { children: [] } ] };' . "\n"
		. 'console.log(JSON.stringify([_sdTreeNodeCount(t), _sdTreeNodeCount(t.children[0]), _sdTreeNodeCount(t.children[1]), _sdTreeNodeCount(null)]));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(6, 4, 0, 0), $res, 'the count is every descendant, the row itself not counted');
}

// The selection's path: from its section down to it, the body left out, a
// long path cut in the middle.
function test_designer_overview_path_steps()
{
	$res = designer_overview_rows_run(array('_sdTreePathSteps'),
		'var root = { _id: "root", children: [] };' . "\n"
		. 'var parents = new Map(); var chain = [root];' . "\n"
		. 'for (var i = 1; i <= 9; i++) { var n = { _id: "n" + i, children: [] }; chain[i - 1].children.push(n); parents.set(n, chain[i - 1]); chain.push(n); }' . "\n"
		. 'var parentOf = function (n) { return parents.get(n) || null; };' . "\n"
		. 'var ids = function (steps) { return steps.map(function (s) { return s ? s._id : null; }); };' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '  three: ids(_sdTreePathSteps(root, chain[3], parentOf, 5)),' . "\n"
		. '  one: ids(_sdTreePathSteps(root, chain[1], parentOf, 5)),' . "\n"
		. '  long: ids(_sdTreePathSteps(root, chain[9], parentOf, 5)),' . "\n"
		. '  body: ids(_sdTreePathSteps(root, root, parentOf, 5)),' . "\n"
		. '  none: ids(_sdTreePathSteps(root, null, parentOf, 5))' . "\n"
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('n1', 'n2', 'n3'), $res['three'], 'every step from the section down to the selection');
	pg_assert_same(array('n1'), $res['one'], 'a selected section is a path of one step');
	pg_assert_same(array('n1', null, 'n7', 'n8', 'n9'), $res['long'], 'a long path keeps its section and its last three steps, a gap between');
	pg_assert_same(array(), $res['body'], 'the body has no path');
	pg_assert_same(array(), $res['none'], 'no selection, no path');
}

// A chain of wrappers with one child each is one line; a chain opened, or
// holding the selection, is drawn open.
function test_designer_overview_chain_folds()
{
	$res = designer_overview_rows_run(array('_sdTreeIsWrapper', '_sdTreeChain', '_sdTreeChainLabel'),
		'function w(id, kids, extra) { return { _id: id, type: "semantic", props: Object.assign({ tag: "div" }, extra || {}), children: kids || [] }; }' . "\n"
		. 'var menu = { _id: "menu", type: "semantic", props: { tag: "ul" }, children: [w("li1"), w("li2"), w("li3")] };' . "\n"
		. 'var w4 = w("w4", [menu]), w3 = w("w3", [w4]), w2 = w("w2", [w3], { tag: "nav" }), w1 = w("w1", [w2]);' . "\n"
		. 'var ids = function (c) { return { hidden: c.hidden.map(function (n) { return n._id; }), next: c.next ? c.next._id : null }; };' . "\n"
		. 'var never = function () { return false; };' . "\n"
		. 'var lone = w("lone", [menu]);' . "\n"
		. 'var shut = w("s1", [w("s2", [w("s3", [menu])], { _x: 1 })]); shut.children[0]._expanded = false;' . "\n"
		. 'var text = w("t1", [w("t2", [{ _id: "p", type: "content", props: { contentType: "paragraph", text: "Hi" }, children: [{ _id: "x", type: "semantic", props: { tag: "div" }, children: [] }] }])]);' . "\n"
		. 'var marked = w("m1", [w("m2", [menu], { _editable: true })]);' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '  four: ids(_sdTreeChain(w1, never)),' . "\n"
		. '  opened: ids(_sdTreeChain(w1, function (n) { return n === w1; })),' . "\n"
		. '  selected: ids(_sdTreeChain(w1, function (n) { return n === w3; })),' . "\n"
		. '  lone: ids(_sdTreeChain(lone, never)),' . "\n"
		. '  shut: ids(_sdTreeChain(shut, never)),' . "\n"
		. '  content: ids(_sdTreeChain(text, never)),' . "\n"
		. '  marked: ids(_sdTreeChain(marked, never)),' . "\n"
		. '  label4: _sdTreeChainLabel(["Account area", "Wrap", "Inner", "Signed in"]),' . "\n"
		. '  label2: _sdTreeChainLabel(["Account area", "Signed in"])' . "\n"
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('hidden' => array('w1', 'w2', 'w3', 'w4'), 'next' => 'menu'), $res['four'], 'four wrappers with one child each fold into one line, the list after them drawn');
	pg_assert_same(array(), $res['opened']['hidden'], 'an opened chain is drawn open');
	pg_assert_same(array(), $res['selected']['hidden'], 'a chain holding the selection is drawn open');
	pg_assert_same(array(), $res['lone']['hidden'], 'one wrapper alone is no chain');
	pg_assert_same(array(), $res['shut']['hidden'], 'a closed wrapper ends the chain before it is two long');
	pg_assert_same(array('t1', 't2'), $res['content']['hidden'], 'a content element is never hidden');
	pg_assert_same(array(), $res['marked']['hidden'], 'an editable area is never hidden');
	pg_assert_same('Account area › … › Signed in', $res['label4'], 'a long chain names its first and last level');
	pg_assert_same('Account area › Signed in', $res['label2'], 'a chain of two names both');
}

// A name that repeats gets a hint: the text, the picture's file name, the
// link's target; the hint is cut, never the name.
function test_designer_overview_row_hints()
{
	$res = designer_overview_rows_run(array('_sdTreeRowHint'),
		'function link(t, href) { return { type: "content", props: { contentType: "link", text: t, href: href || "#" }, children: [] }; }' . "\n"
		. 'var links = []; for (var i = 1; i <= 61; i++) links.push(link("Item " + i));' . "\n"
		. 'var li = { type: "semantic", props: { tag: "li" }, children: [{ type: "semantic", props: { tag: "div" }, children: [link("My orders")] }] };' . "\n"
		. 'var img = { type: "content", props: { contentType: "image", src: "https://cdn.example.com/files/photo-12.jpg?v=3" }, children: [] };' . "\n"
		. 'var bare = link("", "/account/addresses");' . "\n"
		. 'var html = { type: "content", props: { contentType: "heading", text: "<b>Big</b>  sale" }, children: [] };' . "\n"
		. 'var empty = { type: "semantic", props: { tag: "div" }, children: [] };' . "\n"
		. 'var long = link("A very long link text that goes on and on past the limit");' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '  links: links.map(function (l) { return _sdTreeRowHint(l, 40); }),' . "\n"
		. '  li: _sdTreeRowHint(li, 40), img: _sdTreeRowHint(img, 40), bare: _sdTreeRowHint(bare, 40),' . "\n"
		. '  html: _sdTreeRowHint(html, 40), empty: _sdTreeRowHint(empty, 40), long: _sdTreeRowHint(long, 20)' . "\n"
		. '}));');

	if ($res === null) {
		return;
	}

	pg_assert_same(61, count(array_unique($res['links'])), '61 links named alike each get their own hint');
	pg_assert_same('Item 7', $res['links'][6], 'a link hints its text');
	pg_assert_same('My orders', $res['li'], 'a wrapper hints the first text inside it');
	pg_assert_same('photo-12.jpg', $res['img'], 'a picture hints its file name');
	pg_assert_same('/account/addresses', $res['bare'], 'a link without text hints its target');
	pg_assert_same('Big sale', $res['html'], 'markup and doubled spaces are left out');
	pg_assert_same('', $res['empty'], 'nothing to hint, no hint');
	pg_assert_true(mb_strlen($res['long']) <= 20 && mb_substr($res['long'], -1) === '…', 'a long hint is cut to its limit: ' . $res['long']);
}

// The hint gives way before the label: the label never shrinks while a hint
// is there to cut.
function test_designer_overview_hint_cut_before_name()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_true((bool) preg_match('/\.sd-tree-hint\s*\{[^}]*text-overflow:\s*ellipsis/', $css), 'the hint is cut with an ellipsis');
	pg_assert_true((bool) preg_match('/\.sd-tree-list li > \.sd-tree-lbl ~ :not\(\.sd-tree-sect-count\)\s*\{[^}]*flex-shrink:\s*100/', $css), 'what follows the label shrinks first');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the matcher of the visual designer's "Find and Replace"
 * (assets/js/pg_find_replace.js, window.PGFindReplace): the pattern and its
 * refusals, a plain replacement taken as typed and a regular expression's
 * groups, a whole word in any script, a page tree's words without the
 * inside of a placed shared component, the list per page with the pages
 * left out, and the hook that opens the part. Runs the module under Node;
 * skipped where there is none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGFindReplace loaded as F and two small
// pages as P1 and P2; its `out` comes back decoded. Null without Node.
function find_replace_test_node($code)
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

	$pages = <<<'JS'
var F = window.PGFindReplace, out = null;
function n(id, type, props, kids) { return { _id: id, type: type, props: props || {}, children: kids || [] }; }
var P1 = n('r', 'root', {}, [
    n('h', 'content', { contentType: 'heading', text: 'Çerez politikası', cssClass: 'cerez' }),
    n('p', 'content', { contentType: 'paragraph', text: 'Çerez ve çerezler; çerez.' }),
    n('b', 'component', { btnText: 'Price $40', href: '/cerez' }),
    n('s', 'shared_ref', { sharedId: 3, title: 'çerez' }, [n('in', 'content', { text: 'çerez' })])
]);
var P2 = n('r', 'root', {}, [n('t', 'content', { text: 'Nothing here' })]);
JS;

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_find_replace.js') . "\n"
		. $pages . "\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgfr');

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

function test_find_replace_pattern_refusals()
{
	$res = find_replace_test_node(
		'out = { empty: F.pattern({ search: "" }).error, bad: F.pattern({ search: "(", regex: true }).error,'
		. ' star: F.pattern({ search: "x*", regex: true }).error, ok: F.pattern({ search: "(" }).error,'
		. ' none: F.pattern({ search: "" }).re === null };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same('', $res['empty'], 'no search is no error');
	pg_assert_true($res['none'], 'and no pattern');
	pg_assert_same('invalid', $res['bad'], 'a regular expression that does not compile');
	pg_assert_same('empty', $res['star'], 'one that matches empty text would write between every two letters');
	pg_assert_same('', $res['ok'], 'a plain search takes any character');
}

function test_find_replace_counts_and_replaces_as_typed()
{
	$res = find_replace_test_node(
		'var ci = F.pattern({ search: "çerez" }).re, cs = F.pattern({ search: "çerez", caseSensitive: true }).re,'
		. ' w = F.pattern({ search: "çerez", wholeWord: true }).re, d = F.pattern({ search: "$40" }).re,'
		. ' g = F.pattern({ search: "(\\\\w+) (\\\\d+)", regex: true }).re,'
		. ' l = F.pattern({ search: "^a", regex: true, lines: true }).re;'
		. 'out = { ci: F.count("Çerez ve çerezler; çerez.", ci), cs: F.count("Çerez ve çerezler; çerez.", cs),'
		. ' word: F.count("Çerez ve çerezler; çerez.", w),'
		. ' lit: F.replace("Price $40", d, "$&$1 now", false),'
		. ' grp: F.replace("item 12, item 7", g, "$2x$1", true),'
		. ' lines: F.count("a\\nb\\na", l) };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(3, $res['ci'], 'any case: the capital Ç too');
	pg_assert_same(2, $res['cs'], 'case sensitive');
	pg_assert_same(2, $res['word'], 'a whole word in Turkish letters, not inside "çerezler"');
	pg_assert_same(array('text' => 'Price $&$1 now', 'count' => 1), $res['lit'], 'a plain replacement goes in as typed');
	pg_assert_same(array('text' => '12xitem, 7xitem', 'count' => 2), $res['grp'], 'a regular expression reads its groups');
	pg_assert_same(2, $res['lines'], 'the code files read ^ per line');
}

function test_find_replace_tree_words_only()
{
	$res = find_replace_test_node(
		'var re = F.pattern({ search: "çerez" }).re; var before = F.treeCount(P1, re);'
		. ' var n = F.treeReplace(P1, re, "tanımlama", false);'
		. 'out = { before: before, n: n, h: P1.children[0].props, b: P1.children[2].props, s: P1.children[3], after: F.treeCount(P1, re) };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(4, $res['before'], 'the heading and the paragraph words, never classes, links or the placed component');
	pg_assert_same(4, $res['n'], 'as many replaced');
	pg_assert_same('tanımlama politikası', $res['h']['text'], 'the heading changed');
	pg_assert_same('cerez', $res['h']['cssClass'], 'its class did not');
	pg_assert_same('/cerez', $res['b']['href'], 'a link address is not a word');
	pg_assert_same('çerez', $res['s']['props']['title'], 'a placed shared component is its own row');
	pg_assert_same('çerez', $res['s']['children'][0]['props']['text'], 'and its inside is never written');
	pg_assert_same(0, $res['after'], 'nothing is left to find');
}

function test_find_replace_plan_per_page_with_the_left_out()
{
	$res = find_replace_test_node(
		'var re = F.pattern({ search: "e" }).re;'
		. 'var rows = F.plan([{ key: "a", name: "Held", tree: P1 }, { key: "b", name: "Mine", tree: P2 },'
		. ' { key: "c", name: "Locked", tree: P2, locked: true }, { key: "d", name: "Empty", tree: { type: "root", children: [] } }],'
		. ' re, function (p) { return p.key === "a" ? "Ayşe" : ""; });'
		. 'out = { rows: rows, totals: F.totals(rows) };'
	);

	if ($res === null) {
		return;
	}

	$keys = array_map(function ($r) { return $r['key']; }, $res['rows']);
	pg_assert_same(array('b', 'a', 'c'), $keys, 'the pages that change first, the ones left out after, a page without a match not listed');
	pg_assert_same(array('held', 'Ayşe'), array($res['rows'][1]['skip'], $res['rows'][1]['who']), 'a page someone else has open is left out and named');
	pg_assert_same('rights', $res['rows'][2]['skip'], 'a page without content rights for its folder is left out');
	pg_assert_same(array('count' => 2, 'pages' => 1, 'left' => 2), $res['totals'], 'only the pages that change are counted');
}

// A content element's text is markup: a word inside a tag or an entity is
// never found nor replaced, "&" finds the "&amp;" a text shows as "&", and
// a replacement typed with < or & goes in as text. A plain prop (a
// button's words) is read as it is.
function test_find_replace_keeps_the_markup_of_a_text()
{
	$res = find_replace_test_node(
		'function t(text) { return n("r", "root", {}, [n("p", "content", { contentType: "paragraph", text: text }), n("b", "component", { btnText: "A & B" })]); }'
		. 'var html = "<a href=\"/about\" class=\"lead\">About us</a> &amp; our&nbsp;<b>team</b>";'
		. 'var T1 = t(html), about = F.pattern({ search: "about" }).re, amp = F.pattern({ search: "&" }).re;'
		. 'var n1 = F.treeReplace(T1, about, "Hakkımızda", false);'
		. 'var T2 = t(html), n2 = F.treeCount(T2, amp), r2 = F.treeReplace(T2, amp, "and", false);'
		. 'var T3 = t(html), r3 = F.treeReplace(T3, F.pattern({ search: "team" }).re, "<team> & co", false);'
		. 'var T4 = t(html), r4 = F.treeReplace(T4, F.pattern({ search: "(\\\\w+) us", regex: true }).re, "$1 Us", true);'
		. 'var T5 = t(html), r5 = F.treeReplace(T5, F.pattern({ search: "our team" }).re, "the team", false);'
		. 'out = { n1: n1, t1: T1.children[0].props.text, n2: n2, r2: r2, t2: T2.children[0].props.text, b2: T2.children[1].props.btnText,'
		. ' t3: T3.children[0].props.text, t4: T4.children[0].props.text, t5: T5.children[0].props.text,'
		. ' none: [F.treeCount(t(html), F.pattern({ search: "amp" }).re), F.treeCount(t(html), F.pattern({ search: "nbsp" }).re), F.treeCount(t(html), F.pattern({ search: "class" }).re), F.treeCount(t(html), F.pattern({ search: "href" }).re)] };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(1, $res['n1'], 'the link\'s address and class are no words');
	pg_assert_same('<a href="/about" class="lead">Hakkımızda us</a> &amp; our&nbsp;<b>team</b>', $res['t1'], 'only the words changed, the tag kept');
	pg_assert_same(2, $res['n2'], '"&" finds the text\'s &amp; and the button\'s &');
	pg_assert_same('<a href="/about" class="lead">About us</a> and our&nbsp;<b>team</b>', $res['t2'], 'the entity replaced whole');
	pg_assert_same('A and B', $res['b2'], 'a plain prop is read as it is');
	pg_assert_same('<a href="/about" class="lead">About us</a> &amp; our&nbsp;<b>&lt;team&gt; &amp; co</b>', $res['t3'], 'a replacement with < and & goes in as text');
	pg_assert_same('<a href="/about" class="lead">About Us</a> &amp; our&nbsp;<b>team</b>', $res['t4'], 'a regular expression\'s groups inside a text');
	pg_assert_same(array(0, 0, 0, 0), $res['none'], 'nothing found inside an entity or a tag');
	pg_assert_same('<a href="/about" class="lead">About us</a> &amp; our&nbsp;<b>team</b>', $res['t5'], 'words across a tag are not one match');
}

// A content-level operator changes only the text of an element outside a
// marked area, and everything inside one (the save keeps no more): a
// button's words outside one are neither counted nor replaced.
function test_find_replace_content_level_words_only()
{
	$res = find_replace_test_node(
		'var T = n("r", "root", {}, [n("p", "content", { text: "çerez" }), n("b", "component", { btnText: "çerez" }),'
		. ' n("a", "semantic", { tag: "div", _editable: true }, [n("ib", "component", { btnText: "çerez", title: "çerez" })])]);'
		. 'var re = F.pattern({ search: "çerez" }).re, may = function (node, prop, inArea) { return inArea || prop === "text"; };'
		. 'var all = F.treeCount(T, re), c = F.treeCount(T, re, may), rows = F.plan([{ key: "a", name: "A", tree: T }], re, null, may);'
		. 'var r = F.treeReplace(T, re, "x", false, may);'
		. 'out = { all: all, c: c, plan: rows[0].count, r: r, p: T.children[0].props.text, b: T.children[1].props.btnText, ib: T.children[2].children[0].props };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(4, $res['all'], 'without a filter every word');
	pg_assert_same(3, $res['c'], 'the button outside the marked area is left');
	pg_assert_same(3, $res['plan'], 'the list per page counts the same');
	pg_assert_same(3, $res['r'], 'as many replaced');
	pg_assert_same(array('x', 'çerez'), array($res['p'], $res['b']), 'the text changed, the button outside did not');
	pg_assert_same(array('x', 'x'), array($res['ib']['btnText'], $res['ib']['title']), 'inside the marked area every word');

	$part = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_findreplace.js');
	pg_assert_true(strpos($part, "K._sdAccess() !== 'content'") !== false, 'the part filters at the content level');
}

function test_find_replace_opens_the_part()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($src, "_sdPartCall('findreplace', 'open', [])") !== false, 'the window is the part findreplace');
	pg_assert_true(strpos($src, 'function _frReplaceAll') === false, 'the one-page replace left the core');

	$part = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_findreplace.js');
	pg_assert_true(strpos($part, "p.undoStack.push(") !== false, 'another page takes its undo step in its own history');
	pg_assert_true(strpos($part, 'setValue(out.text, true)') !== false, 'a code file keeps the change in its undo history');
}

// A custom HTML's script and style are code, not words of the page: a
// replace of "Contact" changes the paragraph, never the script's string or
// the style's selector beside it.
function test_find_replace_leaves_script_and_style_code()
{
	$res = find_replace_test_node(
		'var html = "<p>Contact</p><script>if (x === \"Contact\") {}</script><STYLE media=\"all\">.Contact{}</STYLE><p>Contact</p>";'
		. 'var T = n("r", "root", {}, [n("c", "custom_html", { html: html })]), re = F.pattern({ search: "Contact", caseSensitive: true }).re;'
		. 'var c = F.treeCount(T, re), r = F.treeReplace(T, re, "Kontakt", false);'
		. 'out = { c: c, r: r, html: T.children[0].props.html };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(2, $res['c'], 'only the two paragraphs are counted');
	pg_assert_same(2, $res['r'], 'and replaced');
	pg_assert_same('<p>Kontakt</p><script>if (x === "Contact") {}</script><STYLE media="all">.Contact{}</STYLE><p>Kontakt</p>', $res['html'], 'the script and the style stay byte for byte');
}

// "Whole word" holds with "Regex" too: "cat" no longer changes
// "category", and a pattern the Unicode mode refuses still compiles.
function test_find_replace_whole_word_with_a_regular_expression()
{
	$res = find_replace_test_node(
		'var w = F.pattern({ search: "cat|dog", regex: true, wholeWord: true }).re,'
		. ' t = F.pattern({ search: "çerez", regex: true, wholeWord: true }).re,'
		. ' loose = F.pattern({ search: "a\\\\-b", regex: true, wholeWord: true });'
		. 'out = { n: F.count("cat category dog hotdog", w), r: F.replace("cat category", w, "x", true).text,'
		. ' t: F.count("Çerez ve çerezler; çerez.", t), loose: loose.error, ln: F.count("a-b xa-b", loose.re) };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(2, $res['n'], 'only the whole words');
	pg_assert_same('x category', $res['r'], 'the longer word kept');
	pg_assert_same(2, $res['t'], 'a whole word in Turkish letters, not inside "çerezler"');
	pg_assert_same('', $res['loose'], 'an escape the Unicode mode refuses still compiles');
	pg_assert_same(1, $res['ln'], 'and keeps to whole words');
}

// Any case reads the Turkish dotted and dotless i as the other searches
// do: "istanbul" finds "İstanbul", "ılık" finds "ILIK"; case sensitive
// keeps them apart.
function test_find_replace_turkish_i_in_any_case()
{
	$res = find_replace_test_node(
		'var a = F.pattern({ search: "istanbul" }).re, b = F.pattern({ search: "ılık" }).re,'
		. ' c = F.pattern({ search: "İade", wholeWord: true }).re, s = F.pattern({ search: "istanbul", caseSensitive: true }).re;'
		. 'out = { a: F.count("İstanbul, ISTANBUL, istanbul", a), b: F.count("ILIK ılık", b), c: F.count("iade İADE iadeler", c),'
		. ' s: F.count("İstanbul istanbul", s), r: F.replace("İstanbul", a, "Ankara", false).text };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(3, $res['a'], '"istanbul" finds the capital İ too');
	pg_assert_same(2, $res['b'], '"ılık" finds the capitals');
	pg_assert_same(2, $res['c'], 'a whole word typed with İ');
	pg_assert_same(1, $res['s'], 'case sensitive keeps them apart');
	pg_assert_same('Ankara', $res['r'], 'and replaces it');
}

// A typed space finds a no-break space, as the tree search and the
// browser's find read it: "our team" finds "our&nbsp;team", and the
// replacement's own spaces go in as typed.
function test_find_replace_space_finds_a_no_break_space()
{
	$res = find_replace_test_node(
		'var T = n("r", "root", {}, [n("p", "content", { contentType: "paragraph", text: "our&nbsp;team" }), n("b", "component", { btnText: "our team" })]);'
		. 'var re = F.pattern({ search: "our team" }).re, c = F.treeCount(T, re), r = F.treeReplace(T, re, "the crew", false);'
		. 'out = { c: c, r: r, p: T.children[0].props.text, b: T.children[1].props.btnText, rx: F.count("a b", F.pattern({ search: "a b", regex: true }).re) };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(2, $res['c'], 'the entity and the sign both found');
	pg_assert_same(2, $res['r'], 'and replaced');
	pg_assert_same('the crew', $res['p'], 'the text');
	pg_assert_same('the crew', $res['b'], 'the button');
	pg_assert_same(0, $res['rx'], 'a regular expression reads a space as typed');
}

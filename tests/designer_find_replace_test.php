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

function test_find_replace_opens_the_part()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($src, "_sdPartCall('findreplace', 'open', [])") !== false, 'the window is the part findreplace');
	pg_assert_true(strpos($src, 'function _frReplaceAll') === false, 'the one-page replace left the core');

	$part = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_findreplace.js');
	pg_assert_true(strpos($part, "p.undoStack.push(") !== false, 'another page takes its undo step in its own history');
	pg_assert_true(strpos($part, 'setValue(out.text, true)') !== false, 'a code file keeps the change in its undo history');
}

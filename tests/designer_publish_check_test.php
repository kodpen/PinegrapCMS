<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Publish check part (assets/js/style_designer_publishcheck.js):
 * it is a part of the designer loaded on first use, a page's checks are read
 * into one summary, the answer is kept until the page changes, a page not
 * saved yet is not sent to the server's render, and a check that fails
 * blocks nothing. The part runs under Node with a stand-in kit; on a machine
 * without Node the runs are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decoded JSON lines $script prints after the part ran on a stand-in
// kit; null without Node. `api` is the part's entry points, `calls` the
// server calls made, `reply` what seo_check answers.
function designer_publish_check_test_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js/';
	$js = 'var window = this; var document = {};'
		. 'var factory = null; var StyleDesigner = { definePart: function (n, f) { factory = f; } };'
		. file_get_contents($dir . 'pg_publish_findings.js') . "\n"
		. file_get_contents($dir . 'pg_publish_content.js') . "\n"
		. 'window.PgPublishFindings = PgPublishFindings; window.PgPublishContent = PgPublishContent;'
		. file_get_contents($dir . 'style_designer_publishcheck.js') . "\n"
		. 'var calls = []; var bodies = []; var reply = { status: "success", checks: [{ code: "title_present", status: "fail", label: "The page has no title" }], findings: [], a11y: [], concepts: [] };'
		. 'var pages = {'
		. ' a: { key: "a", page_id: 4, page_name: "Home", tree: { _id: "r", type: "root", props: {}, children: ['
		. '   { _id: "h1", type: "semantic", props: { tag: "h1", text: "Lorem ipsum dolor" }, children: [] },'
		. '   { _id: "e", type: "semantic", props: { tag: "h2", text: "" }, children: [] },'
		. '   { _id: "img", type: "component", props: { componentType: "image", src: "/a.png", width: "", height: "" }, children: [] }] } },'
		. ' b: { key: "b", page_id: 0, page_name: "New", tree: { _id: "r2", type: "root", props: {}, children: [] } } };'
		. 'var K = { _sdT: function (s) { return s; }, esc: function (s) { return s; }, sdToast: function () {},'
		. ' COMPONENTS: { hero: { defaultProps: { title: "Build something amazing today" } } },'
		. ' _sdBlockFamilies: function () { return []; },'
		. ' _pgTabsPersistActive: function () {}, _pgPageByKey: function (k) { return pages[k] || null; },'
		. ' _pgTreeOf: function (p) { return p.tree; }, createDefaultTree: function () { return { type: "root", props: {}, children: [] }; },'
		. ' validateTree: function (t) { return t.children.some(function (c) { return c._id === "e"; }) ? [{ rule: "heading_empty", label: "The heading is empty", node: { _id: "e" } }] : []; },'
		. ' getNodeLabel: function (n) { return n.props.tag; }, edits: [],'
		. ' _sdPageEdit: function (k, fn) { K.edits.push(k); fn(pages[k].tree); return true; },'
		. ' _sdProtoTabs: function () { return []; }, sharedCache: {},'
		. ' _sdSeoCheckBody: function (p) { return { page_id: p.page_id }; },'
		. ' _pgApiPost: function (what, body) { calls.push(what); bodies.push(body); return what === "seo_check" ? Promise.resolve(reply) : Promise.reject(new Error("x")); } };'
		. 'var api = factory(K);'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. 'function run(keys) { return new Promise(function (r) { var got = {}; api.check(keys, function (k, s) { got[k] = s; if (Object.keys(got).length === keys.length) r(got); }); }); }'
		. "\n" . $script;

	$file = tempnam(sys_get_temp_dir(), 'sdpc') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the part runs: ' . implode("\n", $lines));

	return array_map(function ($line) { return json_decode($line, true); }, $lines);
}

// The part is a bundle of its own with the finding shape and the content
// check, handed only where the Publish sheet is, and the core hands the
// sheet the check through its bridge.
function test_designer_publish_check_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_publishcheck.min.js', $parts['publishcheck']);
	pg_assert_same(array('assets/js/pg_publish_findings.js', 'assets/js/pg_publish_content.js', 'assets/js/style_designer_publishcheck.js'),
		pg_js_bundles()['assets/js/style_designer_publishcheck.min.js']);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_same('publishcheck', pg_designer_part_files()['publishcheck']['when'], 'handed where the sheet is');
	pg_assert_contains("'publishcheck' => \$access === PG_DESIGNER_ACCESS_FULL && pg_page_draft_ready() && !\$sd_component,", $screen, 'the condition on the screen is the sheet condition');
	pg_assert_false(strpos($screen, 'style_designer_publishcheck') !== false, 'no script tag');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdPartCall('publishcheck', 'check', [keys, done])", $js, 'the sheet checks through the part');
	pg_assert_contains("_pgApiPost('seo_check', _sdSeoCheckBody(p, tree))", $js, 'the audit panel sends the same body');
}

// A saved page: the canvas audit, the server's render and the content
// check in one summary; asked again unchanged, the kept answer and no new
// call; changed, checked again. A page not saved yet is not sent to the
// render; the failed links read blocks nothing.
function test_designer_publish_check_runs_and_keeps()
{
	$got = designer_publish_check_test_run('(async function () {'
		. 'var first = await run(["a", "b"]);'
		. 'out([first.a, first.b, calls.slice()]);'
		. 'await run(["a"]); out(calls.length);'
		. 'pages.a.page_title = "Home of ACME"; reply = { status: "success", checks: [], findings: [], a11y: [], concepts: [] };'
		. 'var again = await run(["a"]); out([again.a, calls.length]);'
		. 'var gone = await run(["zz"]); out(gone.zz);'
		. '})();');

	if ($got === null) {
		return;
	}

	pg_assert_same('block', $got[0][0]['state'], 'the missing title blocks');
	pg_assert_same(1, $got[0][0]['block']);
	pg_assert_same(2, $got[0][0]['look'], 'lorem ipsum and an empty heading left: two to look at');
	pg_assert_same(2, $got[0][0]['groups']['content']);
	pg_assert_same(1, $got[0][0]['failed'], 'the failed links read is counted apart');
	pg_assert_same('ready', $got[0][1]['state'], 'a new page is ready but for its failed links read');
	pg_assert_same(array('seo_check'), array_values(array_filter($got[0][2], function ($c) { return $c === 'seo_check'; })), 'one render, for the saved page only');
	pg_assert_same(count($got[0][2]), $got[1], 'unchanged: no new call');
	pg_assert_same('look', $got[2][0]['state'], 'checked again once the page changed');
	pg_assert_true($got[2][1] > $got[1]);
	pg_assert_same(array('state' => 'failed'), $got[3], 'a page that is gone');
}

// The Publish check asks seo_check for the weight; its findings go to the
// Weight group. "Fix" changes the page's tree as one edit and the count
// drops at once; "Undo" puts it back; "Ignore" keeps the reason on the tree's
// root and takes the finding out of the count. Neither makes the check run
// again.
function test_designer_publish_check_fix_undo_and_ignore()
{
	$got = designer_publish_check_test_run('(async function () {'
		. 'reply.weight = { bytes: { markup: 1, styles: 2, scripts: 3, images: 4, total: 10 }, budget: { markup: 9, styles: 9, scripts: 9, images: 9, total: 9 },'
		. ' grades: { state: "amber", measures: {} }, unused: [], sizes: { img: [200, 80] },'
		. ' findings: [{ id: "weight:img_no_size:img:0", source: "weight", code: "img_no_size", group: "weight", level: "look", text: "No size", node: "img", count: 1 }] };'
		. 'var first = await run(["a"]); out([first.a, bodies[0].weight]);'
		. 'var ids = {}; api.state("a").shown.forEach(function (f) { ids[f.code] = f.id; });'
		. 'out(api.fix("a", ids.heading_empty)); var tr = pages.a.tree;'
		. 'out([tr.children.map(function (c) { return c._id; }), api.state("a").sum.look, api.state("a").fixed.length, K.edits.length]);'
		. 'out(api.undo("a", ids.heading_empty)); out([tr.children.map(function (c) { return c._id; }), api.state("a").sum.look]);'
		. 'out(api.fix("a", ids.img_no_size)); out([tr.children[2].props.width, tr.children[2].props.height]);'
		. 'out([api.ignore("a", ids.sample_text, "Kept on purpose"), tr.props._pcIgnored, api.state("a").sum.look, api.state("a").ignored.length]);'
		. 'out(api.ignore("a", ids.title_present, "no"));'
		. 'var n = calls.length; await run(["a"]); out(calls.length === n);'
		. 'out([api.unignore("a", ids.sample_text), tr.props._pcIgnored, api.state("a").sum.look]);'
		. '})();');

	if ($got === null) {
		return;
	}

	pg_assert_same(1, $got[0][0]['groups']['weight'], 'the weight is measured');
	pg_assert_same(3, $got[0][0]['look'], 'sample words, empty heading, picture without size');
	pg_assert_same(1, $got[0][1], 'seo_check is asked for the weight');
	pg_assert_same(true, $got[1]);
	pg_assert_same(array(array('h1', 'img'), 2, 1, 1), $got[2], 'the empty heading is gone, one edit');
	pg_assert_same(true, $got[3]);
	pg_assert_same(array(array('h1', 'e', 'img'), 3), $got[4], 'undone: back in its place');
	pg_assert_same(true, $got[5]);
	pg_assert_same(array('200', '80'), $got[6], 'the picture got its own width and height');
	pg_assert_same(true, $got[7][0]);
	pg_assert_same(array('content:sample_text:h1:0' => 'Kept on purpose'), $got[7][1], 'the reason is kept on the page');
	pg_assert_same(1, $got[7][2]);
	pg_assert_same(1, $got[7][3]);
	pg_assert_same(false, $got[8], 'a blocking finding is not ignored');
	pg_assert_same(true, $got[9], 'no new check after a fix or an ignore');
	pg_assert_same(array(true, null, 2), $got[10], 'stopped: the root keeps no empty list');
}

// The core hands the part a page edit (one undo step on any page) and the
// sheet a way to redraw a row's result; Show me opens the page's settings
// for a finding of the page.
function test_designer_publish_check_editor_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('function _sdPageEdit(key, fn)', $js);
	pg_assert_contains('_sdPageEdit: _sdPageEdit,', $js);
	pg_assert_contains('_pgTabsOpenPageSettings: _pgTabsOpenPageSettings,', $js);

	$sheet = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_publish.js');
	pg_assert_contains('update: update', $sheet);
}

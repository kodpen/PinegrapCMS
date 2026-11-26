<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pure rules of the designer's History "Activity" tab
 * (assets/js/pg_designer_activity.js, window.PgDesignerActivity): the day
 * heads, the marks, which row opens Compare, the count after the hour, the
 * people and pages of the choosers, the choosers as the panel action takes
 * them and when the next fifty are asked for. Runs in Node; skipped where
 * Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PgDesignerActivity loaded as A; its
// `out` comes back decoded. Null when Node cannot run.
function designer_activity_js_test_node($code)
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

	$file = PG_FUNCTIONS_DIR . '/assets/js/pg_designer_activity.js';
	pg_assert_true(is_file($file), 'pg_designer_activity.js exists');

	if (!is_file($file)) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents($file) . "\n"
		. "var A = window.PgDesignerActivity, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$tmp = @tempnam(sys_get_temp_dir(), 'pgda');

	if ($tmp === false) {
		return null;
	}

	file_put_contents($tmp, $script);
	$out = array();
	@exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $status);
	@unlink($tmp);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Today, yesterday, a date this year and one of another year, in local time.
function test_designer_activity_js_day_heads()
{
	$res = designer_activity_js_test_node('var now = new Date(2026, 10, 25, 14, 0).getTime();'
		. ' function at(y, m, d, h) { return new Date(y, m, d, h).getTime() / 1000; }'
		. ' out = [A.day(at(2026, 10, 25, 0), now).code, A.day(at(2026, 10, 24, 23), now).code,'
		. ' A.day(at(2026, 10, 23, 9), now).code, A.day(at(2025, 11, 31, 9), now).code];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('today', 'yesterday', 'date', 'year'), $res);
}

// Rows of one day stay under one head, in the reader's order.
function test_designer_activity_js_groups()
{
	$res = designer_activity_js_test_node('var now = new Date(2026, 10, 25, 14, 0).getTime();'
		. ' function at(d, h) { return new Date(2026, 10, d, h).getTime() / 1000; }'
		. ' var g = A.groups([{ key: "a", at: at(25, 12) }, { key: "b", at: at(25, 9) }, { key: "c", at: at(24, 18) }, { key: "d", at: at(20, 9) }], now);'
		. ' out = g.map(function (x) { return [x.day.code, x.events.map(function (e) { return e.key; })]; });');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(array('today', array('a', 'b')), array('yesterday', array('c')), array('date', array('d'))), $res);
}

// Every kind's mark is grey and named by the drawing; an unknown action
// keeps a plain dot.
function test_designer_activity_js_marks()
{
	$res = designer_activity_js_test_node('out = ["publish", "restore", "import", "ai", "applied", "bundle", "page_recycle", "page_restore", "page_delete",'
		. ' "page_publish", "page_unpublish", "link_create", "link_revoke", "approved", "changes_requested", "comment", "style_save", "style_copy",'
		. ' "component_save", "file_update", "nothing"].map(function (a) { return A.mark({ action: a }); });');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('cloud-arrow-up', 'arrow-counterclockwise', 'box-arrow-in-down', 'stars', 'pencil-square', 'box-seam', 'trash', 'reply',
		'x-circle', 'eye', 'eye-slash', 'link-45deg', 'slash-circle', 'check-circle', 'arrow-repeat', 'chat-left-text', 'palette', 'copy',
		'puzzle', 'file-earmark-code', 'dot'), $res);
}

// A page's version with one before it opens Compare; its first version and
// any other row open nothing.
function test_designer_activity_js_row_opens_compare()
{
	$res = designer_activity_js_test_node('out = [A.opens({ kind: "version", page_id: 4, revision_no: 12 }), A.opens({ kind: "version", page_id: 4, revision_no: 1 }),'
		. ' A.opens({ kind: "share", page_id: 4, revision_no: 0 }), A.opens({ kind: "version", page_id: 0, revision_no: 3 }),'
		. ' A.isVersion({ kind: "version", page_id: 4, revision_no: 1 })];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(true, false, false, false, true), $res);
}

// The count after the hour, as Compare's list would show it.
function test_designer_activity_js_count()
{
	$res = designer_activity_js_test_node('out = [A.count({ state: "changed" }, 12), A.count({ state: "changed" }, 1), A.count({ state: "same" }, 0),'
		. ' A.count({ state: "new" }, 0), A.count({ state: "unreadable" }, 0), A.count(null, 0)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('code' => 'many', 'n' => 12), $res[0]);
	pg_assert_same(array('code' => 'one', 'n' => 1), $res[1]);
	pg_assert_same(array('code' => 'same'), $res[2], 'settings only');
	pg_assert_same(array('code' => 'first'), $res[3], 'nothing before it');
	pg_assert_same(array('code' => ''), $res[4], 'a count that cannot be made leaves its place empty');
	pg_assert_same(array('code' => ''), $res[5]);
}

// The people of the rows read so far, by name, the person asking left out;
// one found before stays.
function test_designer_activity_js_people()
{
	$res = designer_activity_js_test_node('out = A.people([{ user_id: 3, who: "Mert" }, { user_id: 7, who: "Me" }, { user_id: 2, who: "Ayse" },'
		. ' { user_id: 3, who: "Mert" }, { user_id: 0, who: "The customer" }], 7, [{ id: 9, name: "Deniz" }]);');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(array('id' => 2, 'name' => 'Ayse'), array('id' => 9, 'name' => 'Deniz'), array('id' => 3, 'name' => 'Mert')), $res);
}

// The Page chooser: the open page first, then by name; the search field
// filters; a tab not saved yet is not a page.
function test_designer_activity_js_pages()
{
	$res = designer_activity_js_test_node('var list = [{ page_id: 3, page_name: "Prices" }, { page_id: 1, page_name: "About" }, { page_id: 5, page_name: "Home" }, { page_id: 0, page_name: "New" }];'
		. ' out = [A.pages(list, 5, "").map(function (p) { return p.name; }), A.pages(list, 5, "ri").map(function (p) { return p.name; }), A.pages(list, 5, "")[0].open];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Home', 'About', 'Prices'), $res[0]);
	pg_assert_same(array('Prices'), $res[1]);
	pg_assert_true($res[2], 'the open page is marked');
}

// The choosers as the panel action takes them; junk widens.
function test_designer_activity_js_params()
{
	$res = designer_activity_js_test_node('out = [A.params({}), A.params({ person: "me", page: 4, kind: "version" }), A.params({ person: "12", page: "x", kind: "nope" }),'
		. ' A.params({ person: "1 OR 1", page: -2, kind: "share" }), A.narrowed({}), A.narrowed({ kind: "file" }), A.narrowed({ person: "customers" })];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('person' => '', 'page' => '', 'kind' => ''), $res[0]);
	pg_assert_same(array('person' => 'me', 'page' => '4', 'kind' => 'version'), $res[1]);
	pg_assert_same(array('person' => '12', 'page' => '', 'kind' => ''), $res[2]);
	pg_assert_same(array('person' => '', 'page' => '', 'kind' => 'share'), $res[3]);
	pg_assert_same(array(false, true, true), array_slice($res, 4));
}

// The next fifty are asked for near the end of what is read.
function test_designer_activity_js_near_the_end()
{
	$res = designer_activity_js_test_node('out = [A.near(0, 400, 2000), A.near(1400, 400, 2000), A.near(0, 400, 500)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(false, true, true), $res);
}

// A version whose version before is gone (before_no 0) opens nothing; the
// left side of Compare is the version before the reader named.
function test_designer_activity_js_row_opens_only_with_a_version_before()
{
	$res = designer_activity_js_test_node('out = [A.opens({ kind: "version", page_id: 4, revision_no: 31, before_no: 0 }),'
		. ' A.opens({ kind: "version", page_id: 4, revision_no: 31, before_no: 30 }), A.before({ kind: "version", page_id: 4, revision_no: 31, before_no: 30 }),'
		. ' A.before({ kind: "version", page_id: 4, revision_no: 12 }), A.before({ kind: "version", page_id: 4, revision_no: 31, before_no: 0 })];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(false, true, 30, 11, 0), $res);
}

// The empty states only when nothing older remains: a page that came back
// empty with more after it is read on, never "Nothing has happened".
function test_designer_activity_js_blank()
{
	$res = designer_activity_js_test_node('out = [A.blank(0, false, false), A.blank(0, false, true), A.blank(0, true, false), A.blank(0, true, true), A.blank(3, false, false)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('none', 'narrowed', '', '', ''), $res);
}

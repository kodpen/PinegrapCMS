<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's Publish sheet (assets/js/
 * pg_designer_publish.js): which pages come checked when it opens, and what
 * "Publish selected" does with the checked ones — the drafts go on the
 * site, the unchecked changed pages on the site are held out of the save.
 * The script runs under Node; on a machine without Node the runs are
 * skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decoded JSON `$expr` prints after the sheet script is loaded; null
// without Node.
function designer_publish_sheet_test_eval($expr)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsdpub');

	if ($file === false) {
		return null;
	}

	$rows = 'var rows = ['
		. '{ key: "a", name: "Home", state: "live", changed: false, home: true },'
		. '{ key: "b", name: "About", state: "live", changed: true },'
		. '{ key: "c", name: "New", state: "draft", changed: true },'
		. '{ key: "d", name: "Spring", state: "scheduled", changed: false },'
		. '{ key: "e", name: "Variant", state: "held", changed: true },'
		. '{ key: "f", name: "Team", state: "live", changed: true, locked: true },'
		. '{ key: "g", name: "Old draft", state: "draft", changed: false }'
		. '];';

	file_put_contents($file, file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_publish.js')
		. "\n" . $rows . "\nconsole.log(JSON.stringify(" . $expr . '));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Drafts and changed pages on the site come checked; a page waiting for its
// publish time, an experiment's variant and a locked page do not.
function test_designer_publish_sheet_defaults()
{
	$got = designer_publish_sheet_test_eval('PgDesignerPublish.defaults(rows)');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('b', 'c', 'g'), $got, 'the changed live page and the two drafts');
}

// The defaults publish both drafts and hold nothing back.
function test_designer_publish_sheet_plan_defaults()
{
	$got = designer_publish_sheet_test_eval('PgDesignerPublish.plan(rows, PgDesignerPublish.defaults(rows))');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('publish' => array('c', 'g'), 'hold' => array(), 'save' => true, 'reasons' => array(), 'checks' => array(), 'missing' => false, 'waiting' => false), $got, 'the default selection');
}

// An unchecked changed page on the site is held out of the save; a checked
// scheduled page is published now; the variant and the locked page are
// never touched, checked or not.
function test_designer_publish_sheet_plan_choices()
{
	$got = designer_publish_sheet_test_eval('['
		. 'PgDesignerPublish.plan(rows, ["d", "e", "f"]),'
		. 'PgDesignerPublish.plan(rows, []),'
		. 'PgDesignerPublish.plan(rows, ["a"])'
		. ']');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('publish' => array('d'), 'hold' => array('b'), 'save' => true, 'reasons' => array(), 'checks' => array(), 'missing' => false, 'waiting' => false), $got[0], 'a scheduled page checked, the changed page unchecked');
	pg_assert_same(array('publish' => array(), 'hold' => array('b'), 'save' => false, 'reasons' => array(), 'checks' => array(), 'missing' => false, 'waiting' => false), $got[1], 'nothing checked: nothing to do');
	pg_assert_same(array('publish' => array(), 'hold' => array('b'), 'save' => true, 'reasons' => array(), 'checks' => array(), 'missing' => false, 'waiting' => false), $got[2], 'an unchanged live page checked saves the design');
}

// A draft waiting for approval: held, unless an administrator checked it
// with "Publish anyway" and a reason; without a reason the button stays off.
function test_designer_publish_sheet_plan_publish_anyway()
{
	$got = designer_publish_sheet_test_eval('(function () {'
		. 'var held = [{ key: "w", name: "Spring", state: "held", changed: false, override: true },'
		. '{ key: "x", name: "Variant", state: "held", changed: false }];'
		. 'return ['
		. 'PgDesignerPublish.defaults(held),'
		. 'PgDesignerPublish.plan(held, ["w", "x"]),'
		. 'PgDesignerPublish.plan(held, ["w", "x"], { w: "  " , x: "no" }),'
		. 'PgDesignerPublish.plan(held, ["w"], { w: " Client said yes " }),'
		. 'PgDesignerPublish.plan(held, [], { w: "Client said yes" })'
		. '];})()');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(), $got[0], 'held rows never come checked');
	pg_assert_same(array(), $got[1]['publish'], 'without Publish anyway nothing goes');
	pg_assert_true($got[2]['missing'], 'a reason is needed');
	pg_assert_false($got[2]['save'], 'nothing to do without the reason');
	pg_assert_same(array(), $got[2]['publish'], 'a row the person may not override stays held');
	pg_assert_same(array('w'), $got[3]['publish']);
	pg_assert_same(array('w' => 'Client said yes'), $got[3]['reasons']);
	pg_assert_true($got[3]['save'], 'published with the reason');
	pg_assert_same(array(), $got[4]['publish'], 'unchecked, it stays held');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('rec.approval_override = p._approvalOverride;', $js, 'the reason goes with the page');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'anyway'          => lang('Publish anyway'),", $screen, 'the button text');
}

// The editor's side: Publish opens the sheet, a held page is sent as
// unchanged and keeps its changes after the save, Ctrl+S still saves.
function test_designer_publish_sheet_editor_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains('publish: _pgPublishClick,', $js, 'StyleDesigner.publish');
	pg_assert_contains("if (_pgActiveIsDraft()) { saveAjax(opts); return; }", $js, 'a draft tab\'s main button saves');
	pg_assert_contains("pubPage.addEventListener('click', function () { _pgPublishSheet({ button: pubPage }); });", $js, 'the draft\'s Publish opens the sheet');
	pg_assert_contains("if ((_pgSaveHold[p.key] && p.page_id > 0 && !p.pickedPending) || _pgPageUnchanged(p, t, vr)) rec.unchanged = 1;", $js, 'a held page goes as unchanged');
	pg_assert_true(substr_count($js, 'if (_pgSaveHold[p.key]) return;') === 2, 'a held page keeps its name and baseline after the save');
	pg_assert_contains("if (b && !b.disabled) saveAjax({ button: b });", $js, 'Ctrl+S saves without the sheet');
	pg_assert_false(strpos($js, 'data-sd-status') !== false, 'the publishing menu is gone from the editor');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains('StyleDesigner.publish({ button: _saveBtn });', $screen, 'the main button goes through publish');
	pg_assert_contains('id="sdPublishSheet"', $screen, 'the sheet markup');
	pg_assert_false(strpos($screen, 'data-sd-status') !== false, 'the publishing menu is gone from the toolbar');
	foreach (array('page-draft', 'all-draft', 'publish', 'all') as $act) {
		pg_assert_contains('data-sdp-act="' . $act . '"', $screen, 'the sheet action ' . $act);
	}
}

// After the save the sheet lists where each page ended up, from the
// server's reply: on the site, a draft, or not saved (held back by the
// sheet, or a page another user holds, which comes back without a state).
// A page the reply does not name is left out.
function test_designer_publish_sheet_outcome()
{
	$got = designer_publish_sheet_test_eval('PgDesignerPublish.outcome(rows, ['
		. '{ key: "a", draft: 0 }, { key: "b", draft: 0 }, { key: "c", draft: 0 },'
		. '{ key: "d", draft: 1 }, { key: "e", draft: 1 }, { key: "f", page_id: 9 },'
		. '{ key: "zz", draft: 0 }'
		. '], ["b"])');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(
		'live'  => array('Home', 'New'),
		'draft' => array('Spring', 'Variant'),
		'kept'  => array('About', 'Team'),
	), $got, 'the pages grouped by where the save left them');

	$none = designer_publish_sheet_test_eval('PgDesignerPublish.outcome(rows, null, null)');
	pg_assert_same(array('live' => array(), 'draft' => array(), 'kept' => array()), $none, 'no reply: nothing to list');
}

// The sheet stays open during the save: its button shows progress, the
// editor reports the reply back instead of a toast, and a save that did
// not run hands the selection back.
function test_designer_publish_sheet_save_feedback_wiring()
{
	$sheet = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_publish.js');
	pg_assert_contains('bridge.publish(p.publish, p.hold, report, p.reasons, p.checks)', $sheet, 'the sheet passes its report to the save');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('if (keys.length) _pgSetDraft(keys, false, hold, report);', $js, 'the drafts go on the site with the report');
	pg_assert_contains('else saveAjax({ hold: hold, reportSaved: report, onError: function () { report(null); } });', $js, 'a plain save reports too');
	pg_assert_contains("if (!(typeof opts.reportSaved === 'function' && opts.reportSaved(res.data)))", $js, 'the toast only when the sheet did not show the result');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains('data-sdp-act="done"', $screen, 'the Done button');
	pg_assert_contains('data-sdp-result', $screen, 'the result list');
	foreach (array('busy', 'result-live', 'result-draft', 'result-kept') as $k) {
		pg_assert_contains("'" . $k . "'", $screen, 'the text ' . $k);
	}
}

// The Publish check's results in the plan: a ticked page still being
// checked keeps Publish selected off; a blocking page is not selectable and
// goes only with "Publish anyway" and its reason, which travels apart from
// a held draft's approval reason; a blocking page on the site left out is
// held out of the save, a blocking draft left out stays a draft.
function test_designer_publish_sheet_plan_check_results()
{
	$got = designer_publish_sheet_test_eval('(function () {'
		. 'var ck = { blocked: { b: true, c: true }, pending: { d: true } };'
		. 'return ['
		. 'PgDesignerPublish.plan(rows, ["d"], {}, ck),'
		. 'PgDesignerPublish.plan(rows, ["b", "c"], {}, ck),'
		. 'PgDesignerPublish.plan(rows, ["b", "c"], { b: "Fixed tomorrow", c: " " }, ck),'
		. 'PgDesignerPublish.plan(rows, ["b", "c"], { b: " Fixed tomorrow ", c: "Agreed" }, ck),'
		. 'PgDesignerPublish.plan(rows, [], {}, ck),'
		. 'PgDesignerPublish.defaults(rows, ck)'
		. '];})()');

	if ($got === null) {
		return;
	}

	pg_assert_true($got[0]['waiting'], 'a page being checked keeps the button off');
	pg_assert_same(array(), $got[1]['publish'], 'a blocking page is not selectable without Publish anyway');
	pg_assert_same(array('b'), $got[1]['hold'], 'a blocking changed page on the site is held out');
	pg_assert_false($got[1]['save'], 'nothing to save but the drafts left out');
	pg_assert_true($got[2]['missing'], 'a blocking page published anyway needs its reason');
	pg_assert_same(array('b' => 'Fixed tomorrow', 'c' => 'Agreed'), $got[3]['checks'], 'the reasons, trimmed');
	pg_assert_same(array(), $got[3]['reasons'], 'apart from the approval reasons');
	pg_assert_same(array('c'), $got[3]['publish'], 'the blocking draft goes on the site');
	pg_assert_same(array(), $got[3]['hold']);
	pg_assert_true($got[3]['save']);
	pg_assert_false($got[3]['missing']);
	pg_assert_false($got[3]['waiting']);
	pg_assert_same(array('b'), $got[4]['hold'], 'left out, the blocking page stays as the site has it');
	pg_assert_same(array('g'), $got[5], 'a blocking page never comes checked');
}

// What a row's result says: busy, Ready, n to look at, n blocking (with the
// looks inside), or not checked; a failed check alone changes nothing.
function test_designer_publish_sheet_result_label()
{
	$got = designer_publish_sheet_test_eval('['
		. 'PgDesignerPublish.result(null, true),'
		. 'PgDesignerPublish.result({ state: "ready", block: 0, look: 0, failed: 1 }),'
		. 'PgDesignerPublish.result({ state: "look", block: 0, look: 3 }),'
		. 'PgDesignerPublish.result({ state: "block", block: 2, look: 4 }),'
		. 'PgDesignerPublish.result({ state: "failed" }),'
		. 'PgDesignerPublish.result(null, false)'
		. ']');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('kind' => 'run', 'text' => 'checking', 'n' => 0), $got[0]);
	pg_assert_same(array('kind' => 'ok', 'text' => 'result-ready', 'n' => 0), $got[1]);
	pg_assert_same(array('kind' => 'warn', 'text' => 'result-look', 'n' => 3), $got[2]);
	pg_assert_same(array('kind' => 'bad', 'text' => 'result-block', 'n' => 2), $got[3]);
	pg_assert_same(array('kind' => 'none', 'text' => 'result-failed', 'n' => 0), $got[4]);
	pg_assert_same(null, $got[5], 'a page not ticked has no result');
}

// The reason a blocking page is published anyway: plain text on one line,
// at most 500 characters, '' without one; the save logs it.
function test_designer_publish_sheet_check_reason()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/designer.php';
	pg_assert_same('', pg_designer_publish_check_reason(array()));
	pg_assert_same('', pg_designer_publish_check_reason(array('publish_check_reason' => array('x'))));
	pg_assert_same('Fixed <b>tomorrow</b> & more', pg_designer_publish_check_reason(array('publish_check_reason' => "  Fixed\n &lt;b&gt;tomorrow&lt;/b&gt; <i>&amp;</i>   more ")));
	pg_assert_same(500, mb_strlen(pg_designer_publish_check_reason(array('publish_check_reason' => str_repeat('ş', 600))), 'UTF-8'));

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("'page ({var:1}) was published with a blocking Publish check finding: {var:2}'", $save, 'the Site Log line');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('rec.publish_check_reason = p._publishCheckReason;', $js, 'the reason goes with the page');
}

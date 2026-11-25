<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Edit content here" in the designer's Preview: the part recordedit, the
 * core hooks that send the marks and ask before a choice drops changes,
 * and the marks the Preview's own markup carries (_sdRecEditMarkHtml(),
 * run in Node; skipped where Node cannot run).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function record_edit_preview_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The function `name` of the editor's source, cut out whole.
function record_edit_preview_cut($src, $name)
{
	$at = strpos($src, 'function ' . $name . '(');
	pg_assert_true($at !== false, $name . ' is in the source');
	$end = strpos($src, "\n    }\n", (int) $at);

	return substr($src, (int) $at, (int) $end - (int) $at + 6);
}

// A part loaded on first use, after the pure list of spots it reads.
function test_record_edit_preview_is_a_part()
{
	$files = pg_designer_part_files();
	pg_assert_true(isset($files['recordedit']), 'the part is registered');
	pg_assert_same(array('assets/js/pg_record_edit_spots.js', 'assets/js/style_designer_recordedit.js'), $files['recordedit']['sources']);
	pg_assert_same('always', $files['recordedit']['when'], 'the core decides when to load it');

	$part = record_edit_preview_source('assets/js/style_designer_recordedit.js');
	pg_assert_contains("StyleDesigner.definePart('recordedit',", $part);
	pg_assert_contains('return { holds: holds, html: html, reset: reset, sync: sync };', $part);
	pg_assert_contains("api('record_edit_save', { item_id: R.id, changes: changes })", $part, 'saved through the item\'s own writer');
	pg_assert_false(strpos($part, 'saveState(') !== false, 'never part of the design\'s undo');
}

// The core: the switch's marks only while it is on and only in the
// Preview's markup; a choice of the bar waits while changes are not saved;
// the bar draws the switch and hands each document over.
function test_record_edit_preview_core_hooks()
{
	$core = record_edit_preview_source('assets/js/style_designer.src.js');
	pg_assert_contains('if (_sdRecEdit.emit && !sink && h) h = _sdRecEditMarkHtml(n, h);', $core);
	pg_assert_contains('try { html = generateHTML(styleName, addBc); } finally { _sdPreviewWidgetMarkers = false; _sdRecEdit.emit = false; }', $core, 'switched off again');
	pg_assert_contains('if (_sdRecEdit.marks) payload.marks = true;', record_edit_preview_cut($core, '_sdPvAsApply'));
	pg_assert_contains("if (!quiet && _sdPartLoaded('recordedit') && _sdPartApis.recordedit.holds(c)) return;", record_edit_preview_cut($core, '_sdPvAsChoose'));
	pg_assert_contains('_sdRecEdit.marks = false;', record_edit_preview_cut($core, 'openPreview'), 'off each time the Preview opens');

	$bar = record_edit_preview_source('assets/js/style_designer_previewas.js');
	pg_assert_contains("if (!S.narrow) h += K._sdRecEditHtml('bar');", $bar);
	pg_assert_contains("h += K._sdRecEditHtml('row');", $bar, 'the narrow sheet\'s row');
	pg_assert_contains('K._sdRecEditSync(d, S.win, S.setups);', $bar);
}

// The first tag of an element gets its node; a reference, a tag marked
// already and markup that starts with no tag are left alone.
function test_record_edit_preview_marks_the_first_tag()
{
	if (!function_exists('exec')) {
		return;
	}
	$v = array();
	$status = 1;
	@exec('node --version 2>&1', $v, $status);
	if ($status !== 0) {
		return;
	}

	$fn = record_edit_preview_cut(record_edit_preview_source('assets/js/style_designer.src.js'), '_sdRecEditMarkHtml');
	$script = "function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/\"/g, '&quot;').replace(/</g, '&lt;'); }\n" . $fn . "\n"
		. 'console.log(JSON.stringify(['
		. '_sdRecEditMarkHtml({ _id: "a1", type: "content" }, "  <h2 class=\"x\">T</h2>"),'
		. '_sdRecEditMarkHtml({ _id: "b\"2", type: "semantic" }, "<img src=\"p.jpg\">"),'
		. '_sdRecEditMarkHtml({ _id: "c3", type: "shared_ref" }, "<div>x</div>"),'
		. '_sdRecEditMarkHtml({ _id: "d4", type: "content" }, "<p data-pg-node=\"z\">x</p>"),'
		. '_sdRecEditMarkHtml({ _id: "e5", type: "content" }, "text <b>x</b>"),'
		. '_sdRecEditMarkHtml({ type: "content" }, "<p>x</p>")'
		. ']));';

	$file = @tempnam(sys_get_temp_dir(), 'pgrem');
	if ($file === false) {
		return;
	}
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	pg_assert_same(array(
		'  <h2 data-pg-node="a1" class="x">T</h2>',
		'<img data-pg-node="b&quot;2" src="p.jpg">',
		'<div>x</div>',
		'<p data-pg-node="z">x</p>',
		'text <b>x</b>',
		'<p>x</p>',
	), json_decode(implode("\n", $out), true));
}

// The canvas row: in a bound element's menu (view mode too) and in the
// command palette; the Preview opened with the switch on and that frame
// focused, or the record's screen; one question per widget and record.
function test_record_edit_preview_canvas_row()
{
	$core = record_edit_preview_source('assets/js/style_designer.src.js');
	$menu = record_edit_preview_cut($core, 'showCanvasCtxMenu');
	pg_assert_contains("var recItem = multi ? null : _sdRecEditItem(node, 'menu');", $menu);
	pg_assert_contains('}, recItem].filter(Boolean));', $menu, 'view mode keeps the row: it changes the record, not the page');
	pg_assert_contains("_sdRecEditItem(selectedNode, 'palette')", $core, 'the command palette row');
	pg_assert_contains("id: 'act_record_edit'", record_edit_preview_cut($core, '_sdRecEditItem'));

	$go = record_edit_preview_cut($core, '_sdRecEditGo');
	pg_assert_contains("openPreview();\n            _sdRecEdit.want = { node: String(node._id) };", $go, 'the want is set after openPreview() clears it');
	pg_assert_contains("var tab = window.open('', '_blank');", $go, 'a tab opened inside the click while the answer is on its way');
	pg_assert_contains('_sdRecEdit.want = null;', record_edit_preview_cut($core, 'openPreview'), 'a plain Preview forgets a stale want');
	pg_assert_contains("_pgApiPost('preview_record_open',", record_edit_preview_cut($core, '_sdRecOpenAsk'));
	pg_assert_contains("var key = w.sid + ':' + (parseInt(_sdPvAsChoice().records[w.sid], 10) || 0);", record_edit_preview_cut($core, '_sdRecEditRow'));
	pg_assert_contains('_sdRecEditWant: _sdRecEditWant,', $core, 'handed to the part');

	$part = record_edit_preview_source('assets/js/style_designer_recordedit.js');
	pg_assert_contains('if (R.read && !R.read.waits) setOn(true);', $part, 'the switch turns on unless the change would wait');
	pg_assert_contains('focusWanted();', record_edit_preview_cut($part, 'sync'));
}

// A product or an event the Preview draws gets the link "Open the record"
// in the bar, once the server says it may be opened; the part loads for it.
function test_record_edit_preview_link_for_other_kinds()
{
	$core = record_edit_preview_source('assets/js/style_designer.src.js');
	pg_assert_contains('return !!s.source && parseInt(s.record, 10) > 0;', record_edit_preview_cut($core, '_sdRecEditSync'));

	$part = record_edit_preview_source('assets/js/style_designer_recordedit.js');
	$sync = record_edit_preview_cut($part, 'sync');
	pg_assert_contains("else if ((s.source === 'product' || s.source === 'event') && !other) other = { sid: parseInt(k, 10), id: rec };", $sync);
	pg_assert_contains('otherRecord(id ? null : other);', $sync, 'a collection item drawn keeps the switch');
	$other = record_edit_preview_cut($part, 'otherRecord');
	pg_assert_contains('K._sdRecOpenAsk(o.sid, o.id)', $other);
	pg_assert_contains("a.status !== 'ok'", $other, 'no link for whoever may not open it');
	pg_assert_contains('if (!R.id) return (R.other && R.other.screen) ? link(R.other.screen, where) : \'\';', record_edit_preview_cut($part, 'html'));
}

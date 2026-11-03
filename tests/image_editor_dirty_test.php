<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's unsaved flag and the focus after the
 * unsaved-changes question (assets/js/pg_image_editor.js and
 * pg_image_editor_adjust.js, run under Node on an editor without a page):
 * a document brought back to its opening state has nothing to save, an
 * adjustment back at 0 leaves the document, and closing the question gives
 * the focus back to the control that had it, else to the stage.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M, the editor's prototype as Editor and a
// bare editor from fake() (no page: the drawing calls do nothing); answers
// its JSON, null without Node.
function image_editor_dirty_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiedirty');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'require(' . json_encode($js . 'pg_image_editor.js') . '); require(' . json_encode($js . 'pg_image_editor_adjust.js') . ');' . "\n"
		. 'var Editor = window.PgImageEditor.parts.Editor;' . "\n"
		. 'function fake() {' . "\n"
		. ' var e = Object.create(Editor.prototype), none = function () {};' . "\n"
		. ' e.doc = M.newDocument({ fileId: 1 }); e.opening = M.clone(e.doc); e.history = new M.History(); e.dirty = false;' . "\n"
		. ' e.undoBtn = {}; e.redoBtn = {}; e.render = none; e.renderView = none; e.renderMeta = none; e.keepSelection = none; e.setReady = none;' . "\n"
		. ' return e;' . "\n"
		. '}' . "\n"
		. 'function node(name, inside) { var n = { name: name, inside: inside !== false, focus: function () { focused = name; } }; return n; }' . "\n"
		. 'var focused = null;' . "\n";

	file_put_contents($file, $head . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A change that brings the document back to its opening state (a filter
// picked and put back, a slider moved and back at 0) leaves nothing to save.
function test_image_editor_dirty_change_back_to_opening()
{
	$r = image_editor_dirty_node(
		'var a = fake(); a.change(function (d) { d.filter = "bw"; }); var filterOn = a.dirty;' . "\n"
		. 'a.change(function (d) { d.filter = "none"; }); var filterBack = a.dirty;' . "\n"
		. 'var b = fake(); b.setAdjust("brightness", 1, "adjust-brightness"); var sliderOn = b.dirty;' . "\n"
		. 'b.setAdjust("brightness", 0, "adjust-brightness"); var sliderBack = b.dirty, adjust = b.doc.adjust;' . "\n"
		. 'var c = fake(); c.setAdjust("contrast", 5, null); c.setAdjust("contrast", 0, null); c.undo(); var undone = [c.doc.adjust, c.dirty];' . "\n"
		. 'console.log(JSON.stringify({ filterOn: filterOn, filterBack: filterBack, sliderOn: sliderOn, sliderBack: sliderBack, adjust: adjust, undone: undone }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['filterOn'], 'a filter changes the picture');
	pg_assert_false($r['filterBack'], 'the filter put back to Original leaves nothing to save');
	pg_assert_true($r['sliderOn'], 'a slider off 0 changes the picture');
	pg_assert_false($r['sliderBack'], 'a slider back at 0 leaves nothing to save');
	pg_assert_same(array(), $r['adjust'], 'an adjustment at 0 is not kept in the document');
	pg_assert_same(array(array('contrast' => 5), true), $r['undone'], 'undo brings the adjustment and the unsaved flag back');
}

// Closing the question gives the focus back to the control that had it when
// it opened; one gone from the editor, or none, hands it to the stage.
function test_image_editor_dirty_question_gives_focus_back()
{
	$r = image_editor_dirty_node(
		'function ask(back) {' . "\n"
		. ' var e = fake(), removed = false; focused = null;' . "\n"
		. ' e.stage = node("stage"); e.dialog = { contains: function (n) { return !!n && n.inside; } };' . "\n"
		. ' e.confirmBox = { parentNode: { removeChild: function () { removed = true; } } }; e.confirmReturn = back;' . "\n"
		. ' e.closeConfirm(); return [focused, removed, e.confirmBox, e.confirmReturn || null];' . "\n"
		. '}' . "\n"
		. 'var off = node("off"); off.disabled = true;' . "\n"
		. 'console.log(JSON.stringify({ back: ask(node("close")), gone: ask(node("old", false)), disabled: ask(off), none: ask(null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('close', true, null, null), $r['back'], 'the control that had the focus takes it back');
	pg_assert_same('stage', $r['gone'][0], 'a control gone from the editor hands the focus to the stage');
	pg_assert_same('stage', $r['disabled'][0], 'a disabled control hands the focus to the stage');
	pg_assert_same('stage', $r['none'][0], 'no earlier focus hands it to the stage');
}

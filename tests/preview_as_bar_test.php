<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Preview as" bar of the designer's Preview: a widget row's
 * own record, what the answer says each widget offers, the part and the
 * core hooks that send the choice and hand the answer to the bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function preview_as_bar_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// A widget row carries the record chosen for it alone: a whole positive
// number, anything else reads as none.
function test_preview_as_bar_row_record_is_a_whole_number()
{
	$rows = pg_designer_preview_override_rows(array(
		array('id' => 1, 'record' => 42),
		array('id' => 2, 'record' => '17'),
		array('id' => 3, 'record' => '4 OR 1=1'),
		array('id' => 4, 'record' => -3),
		array('id' => 5, 'record' => array(9)),
		array('id' => 6, 'record' => '1234567890123456789'),
		array('id' => 7),
	));

	pg_assert_same(42, $rows[1]['record']);
	pg_assert_same(17, $rows[2]['record'], 'a number sent as text');
	pg_assert_same(0, $rows[3]['record'], 'not a number');
	pg_assert_same(0, $rows[4]['record'], 'below one');
	pg_assert_same(0, $rows[5]['record'], 'not a scalar');
	pg_assert_same(0, $rows[6]['record'], 'too long');
	pg_assert_same(0, $rows[7]['record'], 'none sent');
}

// The row's record goes over the page's, and the answer says per widget
// what the bar offers: its record source and its cases.
function test_preview_as_bar_answer_says_what_each_widget_offers()
{
	$src = preview_as_bar_test_source('includes/fn/designer.php');
	$fn  = substr($src, (int) strpos($src, 'function pg_designer_preview_widgets('), 7000);
	pg_assert_contains('$own  = pg_preview_as_row_choice($choice, $ov);', $fn, 'the row\'s record over the page\'s');
	pg_assert_contains("'source' => pg_preview_as_record_source(\$type),", $fn);
	pg_assert_contains("'cases'  => pg_preview_as_kind_cases(\$type),", $fn);
}

// The bar is a part loaded on first use, never a script of the screen.
function test_preview_as_bar_is_a_part()
{
	$files = pg_designer_part_files();
	pg_assert_true(isset($files['previewas']), 'the part is registered');
	pg_assert_same(array('assets/js/style_designer_previewas.js'), $files['previewas']['sources']);
	pg_assert_same('always', $files['previewas']['when'], 'the core decides when to load it');

	$part = preview_as_bar_test_source('assets/js/style_designer_previewas.js');
	pg_assert_contains("StyleDesigner.definePart('previewas',", $part);
	pg_assert_contains('return { answer: answer, attach: attach, check: check, failed: failed, inject: inject, note: note, redraw: render };', $part);
}

// The core sends the kept choice with the Preview's widgets and hands each
// answer to the bar; a page that offers no choice never loads it.
function test_preview_as_bar_core_hooks()
{
	$core = preview_as_bar_test_source('assets/js/style_designer.src.js');
	$open = substr($core, (int) strpos($core, 'function openPreview() {'), 2400);
	pg_assert_contains('_sdPvAsApply(_sdPreviewWidgetPayload(raw))', $open, 'the explicit Preview sends the choice');
	pg_assert_contains('_sdPvAsAnswered(ok ? res.setups : null);', $open);

	$live = substr($core, (int) strpos($core, 'function _sdLivePreviewRefresh() {'), 2400);
	pg_assert_contains('_sdPvAsApply(_sdPreviewWidgetPayload(raw))', $live, 'the live preview sends it too');
	pg_assert_contains('_sdPvAsFailed()', $live, 'a failed draw of a choice keeps the last page');

	$show = substr($core, (int) strpos($core, 'function _sdShowPreview(html, w) {'), 1200);
	pg_assert_contains('new Blob([_sdPvAsInject(html)]', $show, 'the bar is in the markup');
	pg_assert_contains('_sdPreviewSent = html;', $show, 'the unchanged page is still not reloaded');

	$ans = substr($core, (int) strpos($core, 'function _sdPvAsAnswered(setups) {'), 1200);
	pg_assert_contains("if (!offers && !_sdPartLoaded('previewas')) return;", $ans, 'no choice, no part');

	$apply = substr($core, (int) strpos($core, 'function _sdPvAsApply(payload, given) {'), 900);
	pg_assert_contains('access_rules: false', $apply, 'the editor knows no access rule yet');
}

// With "Edit content here" on, the widgets are drawn with their node marks,
// so the Preview finds the elements to frame; never on a stored page.
function test_preview_as_bar_widgets_carry_marks_only_when_asked()
{
	$src  = preview_as_bar_test_source('includes/panel/designer.php');
	$case = substr($src, (int) strpos($src, "case 'preview_widgets':"), 2200);
	pg_assert_contains("\$pv_marks = !empty(\$request['marks']);", $case);
	pg_assert_contains('if ($pv_marks) pg_designer_audit_render(true);', $case);
	pg_assert_contains('if ($pv_marks) pg_designer_audit_render(false);', $case, 'switched off again');
}

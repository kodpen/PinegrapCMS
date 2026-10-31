<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the way back inside a Workspace view: a child view drawn in
 * the page (a flow, the template gallery opened from a flow, a scheduled
 * action's canvas) goes back by the page's back arrow,
 * which the shared viewBack() of workspace.js draws at the head of the
 * breadcrumb strip, not by a button of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_view_back_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
}

function test_workspace_view_back_kit_draws_the_arrow()
{
	$source = workspace_view_back_source('workspace.js');

	pg_assert_contains('function viewBack(label, onBack, refocusKey)', $source, 'shared back step');
	pg_assert_contains('viewBack: viewBack,', $source, 'offered to the other Workspace scripts');
	pg_assert_contains("'pg-backbtn no-popover'", $source, 'drawn as output_header() draws the arrow');
	pg_assert_contains("'pg-breadcrumb pg-print-none'", $source, 'the strip is made where the screen has none');
}

function test_workspace_view_back_flow_views_use_the_arrow()
{
	$source = workspace_view_back_source('workspace_flow.js');

	foreach (array('fl_tpl_back', 'sa_canvas_back', 'fl_list_back') as $key) {
		pg_assert_contains("K.viewBack(t('" . $key . "')", $source, $key . ' runs through the arrow');
	}
	pg_assert_false(strpos($source, "theme-secondary', t('fl_tpl_back')") !== false, 'no gallery back button');
	pg_assert_false(strpos($source, "theme-secondary', t('sa_canvas_back')") !== false, 'no canvas back button');
	pg_assert_false(strpos($source, 'function flowsButton') !== false, 'no "Flows ›" button');
	pg_assert_contains('K.viewBack(null)', $source, 'the arrow goes when the view closes');
}

function test_workspace_view_back_kept_where_the_arrow_cannot_reach()
{
	$source = workspace_view_back_source('workspace.js');

	// The OneNote sections are a step inside the modal Import dialog: the
	// page behind it, and so its arrow, cannot be reached while it is open.
	pg_assert_contains("theme-secondary mb-3', t('notes_onenote_back'), 'bi-arrow-left')", $source, 'OneNote back stays in the dialog');
	// The phone switch between the note list and a note: on a wide screen
	// both are shown, so it is no step back.
	pg_assert_contains("lg:d-none', '', 'bi-arrow-left', t('notes_back')", $source, 'phone list switch kept');
}

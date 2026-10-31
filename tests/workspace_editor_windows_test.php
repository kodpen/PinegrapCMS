<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the writing box's windows (assets/js/workspace_editor.js,
 * workspace.js, workspace_replies.js): a table, checklist, code or
 * calculation window, the pull drawer and the saved replies hand their
 * result to the box only once their dialog or drawer has closed, since a
 * Bootstrap 6 one keeps the page inert until then; a new card leaves the
 * caret on the line after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_editor_windows_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
}

function test_workspace_editor_windows_hand_over_after_closing()
{
	$editor = workspace_editor_windows_test_source('workspace_editor.js');

	pg_assert_contains('pgUI.dialog.hide(node).then(function () { callback(); });', $editor, 'finish() waits for the dialog to close');
	pg_assert_true(substr_count($editor, 'win.finish(function () {') >= 4, 'the table, checklist, code and calculation windows hand over through finish()');
	pg_assert_same(0, preg_match('/win\.hide\(\);\s*(?:\n\s*)*(?:if \(|done\()/', $editor), 'no window hands over right after hide()');
}

function test_workspace_editor_drawers_write_after_closing()
{
	$screen = workspace_editor_windows_test_source('workspace.js');
	$replies = workspace_editor_windows_test_source('workspace_replies.js');

	pg_assert_contains('pgUI.drawer.hide(node).then(function () { then(); });', $screen, 'hideOffcanvas() runs what follows once the drawer has closed');
	pg_assert_contains('hideOffcanvas(node, function () { onPick(pulled); });', $screen, 'a pulled block goes in after the drawer closed');
	pg_assert_contains('h.hideOffcanvas(node, function () {', $replies, 'a saved reply goes in after the drawer closed');
}

function test_workspace_editor_caret_after_a_new_card()
{
	$editor = workspace_editor_windows_test_source('workspace_editor.js');

	pg_assert_contains("fresh.setAttribute('data-ws-new', '1');", $editor, 'the new card is marked');
	pg_assert_contains("var placed = this.node.querySelector('[data-ws-new]');", $editor, 'and found after insertHTML');
	pg_assert_contains("placed.removeAttribute('data-ws-new');", $editor, 'the mark does not stay');
	pg_assert_contains('select(stop.parentNode, indexIn(stop) + 1, stop.parentNode, indexIn(stop) + 1);', $editor, 'the caret goes after the card\'s line break');
}

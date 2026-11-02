<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager on narrow screens and a crowded toolbar: the
 * details sheet that opens the preview panel below 1024 px (item menu and
 * Quick look), no keyboard hint on a touch screen, the breadcrumb folding
 * its leading crumbs under "..." instead of showing a word cut in half, and
 * the Files view's toolbar staying on one row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_narrow_screens_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

function test_explorer_details_sheet_wiring()
{
	$source = explorer_narrow_screens_source();

	pg_assert_contains('#explorer_app.details-sheet-open #explorer_preview_pane { display: block !important; position: fixed;', $source);
	pg_assert_contains('id="explorer_details_close"', $source);
	pg_assert_contains('id="explorer_details_backdrop"', $source);
	pg_assert_contains('id="quicklook_details"', $source);
	pg_assert_contains("html += menuItem('bi-info-circle', L.details, 'details', multi);", $source);
	pg_assert_contains("case 'details': if (item) { openDetailsSheet(item); } break;", $source);
	pg_assert_contains("window.matchMedia('(max-width: 1023.98px)')", $source, 'the menu item follows the width that hides the panel');
	pg_assert_contains("if ((event.key === 'Escape') && detailsSheetIsOpen()) {", $source);
}

function test_explorer_quick_look_hint_hidden_on_touch()
{
	pg_assert_contains("@media (hover: none) and (pointer: coarse) {\n    #explorer_quicklook .ql-hint { display: none; }", explorer_narrow_screens_source());
}

function test_explorer_breadcrumb_folds_leading_crumbs()
{
	$source = explorer_narrow_screens_source();

	$render = substr($source, strpos($source, "\n    function renderBreadcrumb("), 200);
	pg_assert_true(strpos($render, 'fitBreadcrumb();') < strpos($render, 'showBreadcrumbEnd();'), 'the crumbs are folded before the bar scrolls to its end');
	pg_assert_contains("holder.className = 'breadcrumb-item pg-menu-wrap crumb-overflow';", $source);
	pg_assert_contains("crumb.classList.add('crumb-folded', 'd-none');", $source);
	pg_assert_contains('if (target) { target.click(); }', $source, 'a menu entry runs the hidden crumb\'s own action');
	pg_assert_contains("'more' => lang('More'),", $source);
}

function test_explorer_files_toolbar_one_row()
{
	$source = explorer_narrow_screens_source();

	pg_assert_contains('#explorer_toolbar:has(#file_scope_wrap:not(.d-none)) #explorer_search_wrap { width: auto; flex: 0 1 200px; min-width: 7rem; }', $source);
	pg_assert_contains('#file_scope_label { display: inline-block; max-width: 9rem;', $source);
}

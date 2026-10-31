<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the Word and Excel previews from the keyboard
 * (assets/js/pg_office_view.js): an editable workbook and document have a
 * Tab stop, and a cell or paragraph reached with Tab is selected; a sheet
 * or document only read scrolls through a focusable box, and the document
 * box is named. A unit test cannot press keys in the browser, so these pin
 * the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_view_keyboard_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
}

// The body of one function, from its declaration to the next function
// declared at the same indent.
function office_view_keyboard_function($name, $indent)
{
	$source = office_view_keyboard_source();
	$start = strpos($source, "\n" . $indent . 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n" . $indent . 'function ', $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_office_view_editable_grid_has_a_tab_stop_that_selects()
{
	$wire = office_view_keyboard_function('wireGrid', '        ');
	$show = office_view_keyboard_function('show', '        ');

	pg_assert_true($wire !== '' && $show !== '', 'wireGrid() and show() are found');
	pg_assert_contains("table.addEventListener('focusin'", $wire, 'a cell reached with Tab is selected');
	pg_assert_contains('td !== shown.cell', $wire, 'focus the grid gave itself does not select again');
	pg_assert_contains("shown.table.querySelector('tbody td')", $show, 'the first cell of the sheet shown is found');
	pg_assert_contains('first.tabIndex = 0;', $show, 'the first cell is the grid\'s Tab stop');
}

function test_office_view_read_only_sheet_pane_scrolls_from_the_keyboard()
{
	$show = office_view_keyboard_function('show', '        ');

	pg_assert_contains('pane.tabIndex = 0;', $show, 'a sheet only read is a Tab stop');
	pg_assert_contains("pane.removeAttribute('tabindex');", $show, 'an editable grid is entered through its cells');
}

function test_office_view_document_has_a_name_and_a_tab_stop_that_selects()
{
	$render = office_view_keyboard_function('renderDocument', '    ');

	pg_assert_true($render !== '', 'renderDocument() is found');
	pg_assert_contains("doc.setAttribute('aria-label', strings.doc || '');", $render, 'the document box is named');
	pg_assert_contains("if (!editable) {\n            doc.tabIndex = 0;", $render, 'a document only read scrolls from the keyboard');
	pg_assert_contains("doc.addEventListener('focusin'", $render, 'a paragraph reached with Tab is picked');
	pg_assert_contains('node !== shown.node', $render, 'a paragraph already picked is not picked again');

	$strings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/office.php');
	pg_assert_contains("'doc'               => lang('Document preview'),", $strings, 'the document name is translated');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for simple formulas written by a workbook save
 * (includes/fn/office_edit.php): a typed "=" text that is a simple formula,
 * which stored formulas count as simple, a formula cell changed, and the
 * values worked out again on a save: a chain, a circle, a mixed sheet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_office_edit_formula_typed()
{
	pg_assert_same('SUM(A1:A3)', pg_office_xlsx_typed_formula('=sum(a1:a3)', '', 'en'), 'stored as Excel stores it');
	pg_assert_same('SUM(A1,B2)', pg_office_xlsx_typed_formula('=TOPLA(A1;B2)', '', 'tr'), 'a Turkish name and a semicolon');
	pg_assert_same(null, pg_office_xlsx_typed_formula('=TOPLA(A1;B2)', '', 'en'), 'a Turkish name outside Turkish is not one');
	pg_assert_same('A1*2', pg_office_xlsx_typed_formula('=A1*2', 'date', 'en'), 'a date cell takes a formula');
	pg_assert_same(null, pg_office_xlsx_typed_formula('=A1*2', 'text', 'en'), 'a text cell keeps the text');
	pg_assert_same(null, pg_office_xlsx_typed_formula('A1*2', '', 'en'), 'no leading =');
	pg_assert_same(null, pg_office_xlsx_typed_formula('=VLOOKUP(A1,B1:C9,2)', '', 'en'), 'another function is not simple');
}

function test_office_edit_formula_simple()
{
	pg_assert_true(pg_office_xlsx_simple_formula('SUM(B1:C1)', '', false, false) !== null, 'a plain formula');
	pg_assert_true(pg_office_xlsx_simple_formula('B1*2', 'normal', false, false) !== null, 'written as normal');
	pg_assert_same(null, pg_office_xlsx_simple_formula('B1*2', 'shared', true, false), 'a shared formula');
	pg_assert_same(null, pg_office_xlsx_simple_formula('B1*2', 'array', true, false), 'an array formula');
	pg_assert_same(null, pg_office_xlsx_simple_formula('B1*2', '', false, true), 'a linked value');
	pg_assert_same(null, pg_office_xlsx_simple_formula('TOPLA(B1)', '', false, false), 'a stored formula is read in English');
	pg_assert_same(null, pg_office_xlsx_simple_formula('Sheet2!A1', '', false, false), 'another sheet');
}

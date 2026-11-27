<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for simple spreadsheet formulas (includes/fn/office_formula.php):
 * what is read as a simple formula and what is not, the cells a formula
 * reads, its text as Excel stores it, the function names per language, and
 * its value, with the result Excel gives for the same formula on the same
 * cells as the expected value. The formulas of workbooks saved by a
 * spreadsheet program are worked out again and compared with the result the
 * program stored.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A formula's value from its text, or 'not simple'.
 */
function office_formula_test_value($text, $cells, $language = 'en')
{
	$tree = pg_office_formula_parse($text, $language);

	return ($tree === null) ? 'not simple' : pg_office_formula_value($tree, $cells);
}

/**
 * The cells of a sheet part with the value each holds and the formula
 * cells' stored text and result: [cells, formulas]. Shared strings are read
 * as their text; t="b" as a logical value; t="e" as an error value.
 */
function office_formula_test_sheet($path)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$strings = array();
	$shared = $zip->getFromName('xl/sharedStrings.xml');

	if ($shared !== false) {
		preg_match_all('#<si>(.*?)</si>#s', $shared, $items);

		foreach ($items[1] as $item) {
			preg_match_all('#<t[^>]*>([^<]*)</t>#', $item, $texts);
			$strings[] = html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
		}
	}

	$sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
	$zip->close();
	$cells = array();
	$formulas = array();
	preg_match_all('#<c r="([A-Z]+[0-9]+)"([^>]*?)(?:/>|>(.*?)</c>)#s', $sheet, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		$place = pg_office_xlsx_cell($match[1]);
		$type = preg_match('/\bt="([a-z]+)"/', $match[2], $t) ? $t[1] : 'n';
		$body = isset($match[3]) ? $match[3] : '';

		if (!preg_match('#<v>([^<]*)</v>#', $body, $v)) {
			continue;
		}

		$raw = html_entity_decode($v[1], ENT_QUOTES | ENT_XML1, 'UTF-8');

		if ($type === 's') {
			$value = $strings[(int) $raw];
		} elseif ($type === 'b') {
			$value = ($raw === '1');
		} elseif ($type === 'e') {
			$value = array('error' => $raw);
		} elseif ($type === 'str') {
			$value = $raw;
		} else {
			$value = (float) $raw;
		}

		$cells[$place[0]][$place[1]] = $value;

		if (preg_match('#<f[^>]*>([^<]*)</f>#', $body, $f)) {
			$formulas[$match[1]] = array(html_entity_decode($f[1], ENT_QUOTES | ENT_XML1, 'UTF-8'), $value);
		}
	}

	return array($cells, $formulas);
}

function test_office_formula_reads_the_simple_formulas()
{
	$simple = array(
		'=1+2', '1+2', '=A1', '=$A$1', '=A$1*$B2', '=a1+b2', '=SUM(A1:B9)', '=sum(a1:b9)', '=SUM(A1,B2,3)', '=SUM(A1;B2;3)',
		'=AVERAGE(A1:A3)', '=MIN(A1:A3,0)', '=MAX(A1:A3)', '=COUNT(A1:C3)', '=(A1+B1)*2', '=-A1', '=--A1', '=+A1+B1', '=2*-3',
		'= A1 + B1 ', '=SUM((A1:B2))', '=SUM(A1:B2)/COUNT(A1:B2)', '=.5*A1', '=1.5E3', '=SUM(SUM(A1:A2),MAX(B1,B2))', '=XFD1048576',
	);

	foreach ($simple as $text) {
		pg_assert_true(pg_office_formula_parse($text) !== null, $text . ' is a simple formula');
	}
}

function test_office_formula_leaves_other_formulas_out()
{
	$other = array(
		'', '=', '=A1:B2', '=A1:B2+1', '=Sheet2!A1', "='My sheet'!A1", '=A:A', '=SUM(A:A)', '=SUM(1:1)', '=Total', '=TRUE',
		'=IF(A1>0,1,2)', '=A1>0', '="a"&"b"', '=A1^2', '=A1%', '=SUM()', '=SUM(A1,)', '=SUM(,A1)', '=SUM(A1 B1)', '=A1 B1',
		'=(A1', '=A1)', '=1+', '=*2', '={1,2}', '=XFE1', '=A1048577', '=A0', '=_xlfn.SUM(A1)', '=#REF!+1', '=1E400', '=2A',
		'=SUMA(A1)', '=TOPLA(A1)', '=SUM(A1:B)', '=A1:', '=SUM(A1):B2',
	);

	foreach ($other as $text) {
		pg_assert_same(null, pg_office_formula_parse($text), var_export($text, true) . ' is not a simple formula');
	}

	pg_assert_same(null, pg_office_formula_parse('=' . str_repeat('1+', 4096) . '1'), 'a text over 8192 characters');
	pg_assert_true(pg_office_formula_parse('=' . str_repeat('(', 64) . '1' . str_repeat(')', 64)) !== null, '64 levels of brackets');
	pg_assert_same(null, pg_office_formula_parse('=' . str_repeat('(', 65) . '1' . str_repeat(')', 65)), '65 levels of brackets');
	pg_assert_true(pg_office_formula_parse('=SUM(' . implode(',', array_fill(0, 255, '1')) . ')') !== null, '255 arguments');
	pg_assert_same(null, pg_office_formula_parse('=SUM(' . implode(',', array_fill(0, 256, '1')) . ')'), '256 arguments');
}

function test_office_formula_names_per_language()
{
	pg_assert_same(array('SUM' => 'TOPLA', 'AVERAGE' => 'ORTALAMA', 'MIN' => 'MİN', 'MAX' => 'MAK', 'COUNT' => 'SAY'), pg_office_formula_names('tr'), 'Turkish names');
	pg_assert_same('SUM', pg_office_formula_names('de')['SUM'], 'a language without names of its own has the English ones');

	foreach (array('TOPLA' => 'SUM', 'topla' => 'SUM', 'ORTALAMA' => 'AVERAGE', 'MİN' => 'MIN', 'min' => 'MIN', 'mın' => 'MIN', 'MAK' => 'MAX', 'SAY' => 'COUNT', 'Sum' => 'SUM') as $typed => $english) {
		pg_assert_same($english, pg_office_formula_function($typed, 'tr'), $typed . ' in Turkish');
	}

	pg_assert_same(null, pg_office_formula_function('TOPLA', 'en'), 'a Turkish name is not understood in English');
	pg_assert_same(null, pg_office_formula_function('MAKS', 'tr'), 'not a name');

	$cells = array(1 => array(0 => 2.0, 1 => 3.0));
	pg_assert_same(5.0, office_formula_test_value('=TOPLA(A1;B1)', $cells, 'tr'), 'TOPLA with semicolons');
	pg_assert_same(3.0, office_formula_test_value('=mak(A1,B1)', $cells, 'tr'), 'mak typed in lower case');
	pg_assert_same('SUM(A1,B1)', pg_office_formula_text(pg_office_formula_parse('=topla(a1;b1)', 'tr')), 'stored in English with commas');
	pg_assert_same('TOPLA(A1,B1)', pg_office_formula_text(pg_office_formula_parse('=SUM(A1,B1)'), 'tr'), 'shown with the Turkish name');
}

function test_office_formula_text_as_excel_stores_it()
{
	$cases = array(
		'=a1+$b$2' => 'A1+$B$2',
		'= sum( a1 : b9 ; 3 )' => 'SUM(A1:B9,3)',
		'=(A1+B1)*2' => '(A1+B1)*2',
		'=.5+1.50+1E3+2.5e-3' => '0.5+1.5+1000+0.0025',
		'=-A1' => '-A1',
		'=1E20' => '1E+20',
		'=0.1234567890123456789' => '0.123456789012346',
		'=Max(aa10:ab11)' => 'MAX(AA10:AB11)',
	);

	foreach ($cases as $typed => $stored) {
		pg_assert_same($stored, pg_office_formula_text(pg_office_formula_parse($typed)), $typed);
	}
}

function test_office_formula_cells_it_reads()
{
	pg_assert_same(array(array(1, 0, 1, 0), array(2, 1, 9, 3)), pg_office_formula_cells(pg_office_formula_parse('=A1+SUM(B2:D9)')), 'a cell and a range');
	pg_assert_same(array(array(1, 0, 3, 1)), pg_office_formula_cells(pg_office_formula_parse('=SUM(B3:A1,$A$1:$B$3)')), 'a range typed bottom right first, and the same range again, once');
	pg_assert_same(array(array(1, 0, 1, 0)), pg_office_formula_cells(pg_office_formula_parse('=A1+$A$1')), 'one cell once');
	pg_assert_same(array(), pg_office_formula_cells(pg_office_formula_parse('=1+2')), 'no cell');

	$tree = pg_office_formula_parse('=SUM(B2:C4)*A1');
	pg_assert_true(pg_office_formula_reads($tree, 3, 2), 'C3 is read');
	pg_assert_true(pg_office_formula_reads($tree, 1, 0), 'A1 is read');
	pg_assert_false(pg_office_formula_reads($tree, 5, 2), 'C5 is not read');
	pg_assert_false(pg_office_formula_reads($tree, 2, 0), 'A2 is not read');
}

function test_office_formula_arithmetic_as_excel()
{
	$cells = array(
		1 => array(0 => 10.0, 1 => 4, 2 => 'abc', 3 => '5', 4 => true, 5 => false),
		2 => array(0 => ' 2.5 ', 1 => '50%', 2 => '', 3 => array('error' => '#N/A')),
	);

	$cases = array(
		'=1+2*3' => 7.0,
		'=(1+2)*3' => 9.0,
		'=10/4' => 2.5,
		'=10-4-3' => 3.0,
		'=24/4/3' => 2.0,
		'=-A1*2' => -20.0,
		'=2*-3' => -6.0,
		'=1--1' => 2.0,
		'=A1+B1' => 14.0,
		'=A1+Z99' => 10.0,
		'=A1*Z99' => 0.0,
		'=D1+1' => 6.0,
		'=A2*2' => 5.0,
		'=B2*10' => 5.0,
		'=E1+1' => 2.0,
		'=F1+1' => 1.0,
		'=C2+1' => 1.0,
		'=C1+1' => array('error' => '#VALUE!'),
		'=-C1' => array('error' => '#VALUE!'),
		'=A1/0' => array('error' => '#DIV/0!'),
		'=A1/Z99' => array('error' => '#DIV/0!'),
		'=0/0' => array('error' => '#DIV/0!'),
		'=1/0+C1' => array('error' => '#DIV/0!'),
		'=C1+1/0' => array('error' => '#VALUE!'),
		'=D2+1' => array('error' => '#N/A'),
		'=1E300*1E300' => array('error' => '#NUM!'),
		'=-0' => 0.0,
	);

	foreach ($cases as $text => $expected) {
		pg_assert_same($expected, office_formula_test_value($text, $cells), $text);
	}
}

function test_office_formula_one_reference_gives_the_cell_as_it_is()
{
	$cells = array(1 => array(0 => 'abc', 1 => true, 2 => 7, 3 => array('error' => '#DIV/0!'), 4 => '5'));

	pg_assert_same('abc', office_formula_test_value('=A1', $cells), 'a text');
	pg_assert_same(true, office_formula_test_value('=B1', $cells), 'a logical value');
	pg_assert_same(7.0, office_formula_test_value('=C1', $cells), 'a number');
	pg_assert_same(array('error' => '#DIV/0!'), office_formula_test_value('=D1', $cells), 'an error');
	pg_assert_same('5', office_formula_test_value('=E1', $cells), 'a text that reads as a number stays a text');
	pg_assert_same(0.0, office_formula_test_value('=Z9', $cells), 'an empty cell is 0');
	pg_assert_same('abc', office_formula_test_value('=(A1)', $cells), 'in brackets');
	pg_assert_same(-1.0, office_formula_test_value('=-B1', $cells), 'with a minus it is arithmetic');
}

function test_office_formula_functions_as_excel()
{
	// A1:A5 = 1, "x", TRUE, empty, 4; B1:B3 = "3", 6, empty; C1 = #DIV/0!.
	$cells = array(
		1 => array(0 => 1.0, 1 => '3', 2 => array('error' => '#DIV/0!')),
		2 => array(0 => 'x', 1 => 6.0),
		3 => array(0 => true),
		5 => array(0 => 4.0),
	);

	$cases = array(
		'=SUM(A1:A5)' => 5.0,
		'=AVERAGE(A1:A5)' => 2.5,
		'=MIN(A1:A5)' => 1.0,
		'=MAX(A1:A5)' => 4.0,
		'=COUNT(A1:A5)' => 2.0,
		'=SUM(A1:B5)' => 11.0,
		'=COUNT(A1:B5)' => 3.0,
		'=SUM(A1,B1,B2)' => 7.0,
		'=SUM(A1:A5,10,2*3)' => 21.0,
		'=AVERAGE(A1:A5,6)' => 11.0 / 3,
		'=SUM(B1+1)' => 4.0,
		'=SUM(A2+1)' => array('error' => '#VALUE!'),
		'=COUNT(A2+1,A1)' => 1.0,
		'=COUNT(1,2,3)' => 3.0,
		'=SUM(D1:D9)' => 0.0,
		'=MIN(D1:D9)' => 0.0,
		'=MAX(A2:A3)' => 0.0,
		'=COUNT(D1:D9)' => 0.0,
		'=AVERAGE(D1:D9)' => array('error' => '#DIV/0!'),
		'=AVERAGE(A2)' => array('error' => '#DIV/0!'),
		'=SUM(A1:C1)' => array('error' => '#DIV/0!'),
		'=MAX(A1:C5)' => array('error' => '#DIV/0!'),
		'=COUNT(A1:C5)' => 3.0,
		'=SUM(A5:A1)' => 5.0,
		'=SUM((A1:A5))' => 5.0,
		'=MAX(-5,-2,-9)' => -2.0,
		'=MIN(A1:A5,-1)' => -1.0,
		'=SUM(A1:A5)/COUNT(A1:A5)' => 2.5,
		'=SUM(A1:XFD1048576)' => array('error' => '#DIV/0!'),
		'=SUM(A1:B1048576)' => 11.0,
	);

	foreach ($cases as $text => $expected) {
		pg_assert_same($expected, office_formula_test_value($text, $cells), $text);
	}
}

function test_office_formula_number_text()
{
	$cases = array(
		array(0.0, '0'), array(5.0, '5'), array(-13753.14, '-13753.14'), array(0.1 + 0.2, '0.3'), array(1e15, '1E+15'),
		array(123456789012345.0, '123456789012345'), array(1.5e-7, '1.5E-07'), array(2.0 / 3, '0.666666666666667'),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[1], pg_office_formula_number_text($case[0]), (string) $case[1]);
	}
}

function test_office_formula_saved_workbooks_give_the_stored_result()
{
	$root = dirname(__FILE__) . '/fixtures/';
	$seen = 0;

	foreach (array('erp_bank/statement.xlsx', 'sheet_read/book.xlsx') as $file) {
		list($cells, $formulas) = office_formula_test_sheet($root . $file);

		foreach ($formulas as $place => $formula) {
			$tree = pg_office_formula_parse($formula[0]);

			if ($tree === null) {
				continue;
			}

			$value = pg_office_formula_value($tree, $cells);

			if (is_float($value)) {
				// The program stores 15 significant digits.
				$value = (float) sprintf('%.15G', $value);
			}

			pg_assert_same($formula[1], $value, $file . ' ' . $place . ' =' . $formula[0]);
			$seen++;
		}
	}

	pg_assert_same(3, $seen, 'SUM(D6:D9), B2*2 and 1/0 are worked out again');
	pg_assert_same(null, pg_office_formula_parse('"A"&"B"'), 'a joined text is not simple');
	pg_assert_same(null, pg_office_formula_parse('TRUE()'), 'TRUE() is not simple');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The browser's twin of the simple formula reader and calculator
 * (assets/js/pg_office_formula.js) runs the same cases as
 * includes/fn/office_formula.php and pg_office_xlsx_formula_results(): what
 * is a simple formula, its text in English and Turkish, the cells it reads,
 * its value, a sheet worked out in dependency order with a circle.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs a Node script after the twin's source with the cases as `CASES`; its
// JSON output, or null when Node cannot run.
function office_formula_twin_test_node($cases, $script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgform');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_formula.js')
		. "\nvar F = window.pgOfficeFormula;\nvar CASES = " . json_encode($cases) . ";\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	$result = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($result), 'the Node script answers JSON: ' . implode("\n", array_slice($out, 0, 8)));

	return $result;
}

// A value as both sides compare it: numbers as floats, maps sorted by key.
function office_formula_twin_test_plain($value)
{
	if (is_int($value) || is_float($value)) {
		return (float) $value;
	}

	if (is_array($value)) {
		$out = array();

		foreach ($value as $key => $item) {
			$out[$key] = office_formula_twin_test_plain($item);
		}

		if (array_keys($out) !== range(0, count($out) - 1)) {
			ksort($out);
		}

		return $out;
	}

	return $value;
}

// The formula texts of tests/office_formula_test.php, simple or not.
function office_formula_twin_test_texts()
{
	return array(
		'=1+2', '1+2', '=A1', '=$A$1', '=A$1*$B2', '=a1+b2', '=SUM(A1:B9)', '=sum(a1:b9)', '=SUM(A1,B2,3)', '=SUM(A1;B2;3)',
		'=AVERAGE(A1:A3)', '=MIN(A1:A3,0)', '=MAX(A1:A3)', '=COUNT(A1:C3)', '=(A1+B1)*2', '=-A1', '=--A1', '=+A1+B1', '=2*-3',
		'= A1 + B1 ', '=SUM((A1:B2))', '=SUM(A1:B2)/COUNT(A1:B2)', '=.5*A1', '=1.5E3', '=SUM(SUM(A1:A2),MAX(B1,B2))', '=XFD1048576',
		'', '=', '=A1:B2', '=A1:B2+1', '=Sheet2!A1', "='My sheet'!A1", '=A:A', '=SUM(A:A)', '=SUM(1:1)', '=Total', '=TRUE',
		'=IF(A1>0,1,2)', '=A1>0', '="a"&"b"', '=A1^2', '=A1%', '=SUM()', '=SUM(A1,)', '=SUM(,A1)', '=SUM(A1 B1)', '=A1 B1',
		'=(A1', '=A1)', '=1+', '=*2', '={1,2}', '=XFE1', '=A1048577', '=A0', '=_xlfn.SUM(A1)', '=#REF!+1', '=1E400', '=2A',
		'=SUMA(A1)', '=TOPLA(A1)', '=SUM(A1:B)', '=A1:', '=SUM(A1):B2', '=topla(a1;b1)', "=M\xC4\xB0N(A1:A3)", "=m\xC4\xB1n(A1,2)",
		'=mak(A1,B1)', '=SAY(A1:B5)', '=ORTALAMA(A1:A5;6)', '=MAKS(A1)', '=.5+1.50+1E3+2.5e-3', '=1E20', '=0.1234567890123456789',
		'=Max(aa10:ab11)', '=SUM(B3:A1,$A$1:$B$3)', '=A1+$A$1', "=A1\xC3\xA9", "=\xC3\xA9", '=A1/0', '=1/0+C1', '=C1+1/0', '=-0',
		'=' . str_repeat('1+', 4096) . '1', '=' . str_repeat('(', 64) . '1' . str_repeat(')', 64), '=' . str_repeat('(', 65) . '1' . str_repeat(')', 65),
		'=SUM(' . implode(',', array_fill(0, 255, '1')) . ')', '=SUM(' . implode(',', array_fill(0, 256, '1')) . ')',
	);
}

// The cells of tests/office_formula_test.php, each with the texts worked
// out on it.
function office_formula_twin_test_sheets()
{
	return array(
		array(
			'cells' => array(
				1 => array(0 => 10.0, 1 => 4, 2 => 'abc', 3 => '5', 4 => true, 5 => false),
				2 => array(0 => ' 2.5 ', 1 => '50%', 2 => '', 3 => array('error' => '#N/A')),
			),
			'texts' => array(
				'=1+2*3', '=(1+2)*3', '=10/4', '=10-4-3', '=24/4/3', '=-A1*2', '=2*-3', '=1--1', '=A1+B1', '=A1+Z99', '=A1*Z99', '=D1+1',
				'=A2*2', '=B2*10', '=E1+1', '=F1+1', '=C2+1', '=C1+1', '=-C1', '=A1/0', '=A1/Z99', '=0/0', '=1/0+C1', '=C1+1/0', '=D2+1',
				'=1E300*1E300', '=-0', '=A1', '=C1', '=E1', '=D2', '=D1', '=0.1+0.2', '=2/3', '=1E-7*1.5',
			),
		),
		array(
			'cells' => array(
				1 => array(0 => 1.0, 1 => '3', 2 => array('error' => '#DIV/0!')),
				2 => array(0 => 'x', 1 => 6.0),
				3 => array(0 => true),
				5 => array(0 => 4.0),
			),
			'texts' => array(
				'=SUM(A1:A5)', '=AVERAGE(A1:A5)', '=MIN(A1:A5)', '=MAX(A1:A5)', '=COUNT(A1:A5)', '=SUM(A1:B5)', '=COUNT(A1:B5)',
				'=SUM(A1,B1,B2)', '=SUM(A1:A5,10,2*3)', '=AVERAGE(A1:A5,6)', '=SUM(B1+1)', '=SUM(A2+1)', '=COUNT(A2+1,A1)', '=COUNT(1,2,3)',
				'=SUM(D1:D9)', '=MIN(D1:D9)', '=MAX(A2:A3)', '=COUNT(D1:D9)', '=AVERAGE(D1:D9)', '=AVERAGE(A2)', '=SUM(A1:C1)',
				'=MAX(A1:C5)', '=COUNT(A1:C5)', '=SUM(A5:A1)', '=SUM((A1:A5))', '=MAX(-5,-2,-9)', '=MIN(A1:A5,-1)',
				'=SUM(A1:A5)/COUNT(A1:A5)', '=SUM(A1:XFD1048576)', '=SUM(A1:B1048576)', '=B1', '=(A2)', '=A3', '=-A3',
			),
		),
	);
}

function test_office_formula_twin_reads_as_php()
{
	$texts = office_formula_twin_test_texts();
	$result = office_formula_twin_test_node($texts, <<<'JS'
var out = CASES.map(function (text) {
    var row = {};
    ['en', 'tr'].forEach(function (language) {
        var tree = F.parse(text, language);
        row[language] = tree === null ? null : { tree: tree, text: F.text(tree), shown: F.text(tree, 'tr'), cells: F.cells(tree) };
    });
    return row;
});
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	foreach ($texts as $place => $text) {
		foreach (array('en', 'tr') as $language) {
			$tree = pg_office_formula_parse($text, $language);
			$php = ($tree === null) ? null : array(
				'tree' => $tree,
				'text' => pg_office_formula_text($tree),
				'shown' => pg_office_formula_text($tree, 'tr'),
				'cells' => pg_office_formula_cells($tree),
			);

			pg_assert_same(office_formula_twin_test_plain($php), office_formula_twin_test_plain($result[$place][$language]), substr($text, 0, 60) . ' read in ' . $language);
		}
	}
}

function test_office_formula_twin_values_as_php()
{
	$sheets = office_formula_twin_test_sheets();
	$result = office_formula_twin_test_node($sheets, <<<'JS'
var out = CASES.map(function (sheet) {
    return sheet.texts.map(function (text) {
        return F.value(F.parse(text, 'en'), sheet.cells);
    });
});
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	foreach ($sheets as $place => $sheet) {
		foreach ($sheet['texts'] as $spot => $text) {
			$php = pg_office_formula_value(pg_office_formula_parse($text), $sheet['cells']);

			pg_assert_same(office_formula_twin_test_plain($php), office_formula_twin_test_plain($result[$place][$spot]), $text);
		}
	}
}

function test_office_formula_twin_number_text_as_php()
{
	$numbers = array(0.0, 5.0, -13753.14, 0.1 + 0.2, 1e15, 123456789012345.0, 1.5e-7, 2.0 / 3, 1e-5, 0.0001234, 99999999999999.9,
		-2.5e300, 1.0 / 7 * 1e20, 0.000123456789012345678, 123456.789e10);
	$result = office_formula_twin_test_node($numbers, "console.log(JSON.stringify(CASES.map(F.numberText)));\n");

	if ($result === null) {
		return;
	}

	foreach ($numbers as $place => $number) {
		pg_assert_same(pg_office_formula_number_text($number), $result[$place], 'number text of ' . var_export($number, true));
	}
}

function test_office_formula_twin_sheet_as_the_save()
{
	// A1 = 2, A2 = "x"; B1..B3 a chain, C1/C2 a circle, D1 reads the circle,
	// E1 reads an unknown cell (F1), E2 reads E1, E3 counts past the circle,
	// E4 reads itself, G1 a text by reference.
	$formulas = array(
		'B1' => array(1, 1, '=A1*2'),
		'B2' => array(2, 1, '=B1+1'),
		'B3' => array(3, 1, '=SUM(B1:B2,A1:A2)'),
		'C1' => array(1, 2, '=C2+1'),
		'C2' => array(2, 2, '=C1+1'),
		'D1' => array(1, 3, '=C1*2'),
		'E1' => array(1, 4, '=F1+1'),
		'E2' => array(2, 4, '=E1+1'),
		'E3' => array(3, 4, '=COUNT(C1:C2,B1:B3)'),
		'E4' => array(4, 4, '=E4+1'),
		'G1' => array(1, 6, '=A2'),
		'G2' => array(2, 6, '=A2+1'),
	);
	$values = array(1 => array(0 => 2.0), 2 => array(0 => 'x'));
	$unknown = array(1 => array(5 => true));

	$cases = array('formulas' => $formulas, 'values' => $values, 'unknown' => $unknown);
	$result = office_formula_twin_test_node($cases, <<<'JS'
var formulas = {};
Object.keys(CASES.formulas).forEach(function (ref) {
    var item = CASES.formulas[ref];
    formulas[ref] = [item[0], item[1], F.parse(item[2], 'en')];
});
console.log(JSON.stringify(F.results(formulas, CASES.values, CASES.unknown)));
JS
	);

	if ($result === null) {
		return;
	}

	$php_formulas = array();

	foreach ($formulas as $ref => $item) {
		$php_formulas[$ref] = array($item[0], $item[1], pg_office_formula_parse($item[2]));
	}

	$php = pg_office_xlsx_formula_results($php_formulas, $values, $unknown);

	pg_assert_same(office_formula_twin_test_plain($php), office_formula_twin_test_plain($result), 'the same results as the save');
	pg_assert_same(11.0, (float) $result['B3'], 'a chain of three: 4, 5 and their sum with A1');
	pg_assert_same(array('error' => '#REF!'), $result['C1'], 'a circle is #REF!');
	pg_assert_same(null, $result['E2'], 'a formula reading an unknown result has none');
	pg_assert_same('x', $result['G1'], 'a text by reference');
}

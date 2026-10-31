<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the figures inside a workspace sum (includes/workspace/calc.php):
 * a number in an expression is read the way the site writes it, as a lone
 * figure is, on a Turkish and on an English site; a leading % is part of
 * the number; a comma between digits on an English site separates the
 * arguments of a function; an "=" line shows an error for a mark it cannot
 * read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');

// Runs $test with the site writing decimals with a comma (Turkish) or a dot,
// then puts the site's own way back.
function ws_calc_numbers_test_in($comma, $test)
{
	$own = ws_calc_decimal_comma();
	ws_calc_decimal_comma($comma);

	try {
		$test();
	} finally {
		ws_calc_decimal_comma($own);
	}
}

function ws_calc_numbers_test_formula($formula)
{
	$result = ws_calc_evaluate($formula, function () {
		return 'ref';
	});

	return $result['ok'] ? round($result['value'], 10) : $result['error'];
}

function ws_calc_numbers_test_block($line)
{
	$lines = ws_calc_block(array($line));

	return ($lines[0]['error'] !== '') ? $lines[0]['error'] : round($lines[0]['value'], 10);
}

function ws_calc_numbers_test_inline($line)
{
	$inline = ws_calc_inline($line);

	if ($inline === null) {
		return null;
	}

	return ($inline['error'] !== '') ? $inline['error'] : round($inline['value'], 10);
}

function test_workspace_calc_numbers_turkish_expression()
{
	ws_calc_numbers_test_in(true, function () {
		pg_assert_same(15750.0, ws_calc_numbers_test_formula('15.000 + 750'), 'a dot groups thousands');
		pg_assert_same(2501.0, ws_calc_numbers_test_formula('1.250,50 * 2'), 'thousands and decimals');
		pg_assert_same(3.0, ws_calc_numbers_test_formula('1,5 * 2'), 'a decimal comma');
		pg_assert_same(3.0, ws_calc_numbers_test_formula('1.5 * 2'), 'a dot not before three digits is a decimal');
		pg_assert_same(1000000.0, ws_calc_numbers_test_formula('1.000.000'), 'two groups');
		pg_assert_same(0.25, ws_calc_numbers_test_formula('.25'), 'a figure from its decimals');
		pg_assert_same('syntax', ws_calc_numbers_test_formula('1.2.3 + 1'), 'a figure the site cannot read');
	});
}

function test_workspace_calc_numbers_english_expression()
{
	ws_calc_numbers_test_in(false, function () {
		pg_assert_same(16.5, ws_calc_numbers_test_formula('15.000 + 1.5'), 'a dot is a decimal');
		pg_assert_same(15750.0, ws_calc_numbers_test_formula('15,000 + 750'), 'a comma groups thousands outside a function');
		pg_assert_same(2501.0, ws_calc_numbers_test_formula('1,250.50 * 2'), 'thousands and decimals');
		pg_assert_same('syntax', ws_calc_numbers_test_formula('1,5 * 2'), 'a comma is no decimal');
	});
}

function test_workspace_calc_numbers_english_comma_separates_arguments()
{
	ws_calc_numbers_test_in(false, function () {
		pg_assert_same(20.0, ws_calc_numbers_test_formula('MAX(10,20)'), 'MAX');
		pg_assert_same(6.0, ws_calc_numbers_test_formula('SUM(1,2,3)'), 'SUM');
		pg_assert_same(5.0, ws_calc_numbers_test_formula('IF(1>0,5,6)'), 'IF');
		pg_assert_same(1.25, ws_calc_numbers_test_formula('ROUND(1.2468,2)'), 'ROUND with a decimal dot');
		pg_assert_same(1.0, ws_calc_numbers_test_formula('SUM(1,000)'), 'inside a function a comma never groups');
		pg_assert_same(30.0, ws_calc_numbers_test_formula('SUM(10; 20)'), 'a semicolon as before');
	});
}

function test_workspace_calc_numbers_turkish_argument_separators()
{
	ws_calc_numbers_test_in(true, function () {
		pg_assert_same(20.0, ws_calc_numbers_test_formula('MAX(10;20)'), 'a semicolon');
		pg_assert_same(20.0, ws_calc_numbers_test_formula('MAX(10, 20)'), 'a comma with a space');
		pg_assert_same(5.0, ws_calc_numbers_test_formula('IF(1>0; 5; 6)'), 'IF with semicolons');
		pg_assert_same(2501.0, ws_calc_numbers_test_formula('SUM(1.250,50; 1.250,50)'), 'Turkish figures as arguments');
		pg_assert_same(10.2, ws_calc_numbers_test_formula('MAX(10,20)'), 'a comma between digits is a decimal');
		pg_assert_same(4.2, ws_calc_numbers_test_formula('SUM(1,2,3)'), 'one decimal comma per figure');
	});
}

function test_workspace_calc_numbers_leading_and_trailing_percent()
{
	ws_calc_numbers_test_in(true, function () {
		pg_assert_same(200.0, ws_calc_numbers_test_formula('1.000 * %20'), 'leading, Turkish');
		pg_assert_same(200.0, ws_calc_numbers_test_formula('1.000 * 20%'), 'trailing, Turkish');
		pg_assert_same(0.175, ws_calc_numbers_test_formula('%17,5'), 'a leading share with decimals');
		pg_assert_same(1200.0, ws_calc_numbers_test_formula('1.000 * (1 + %20)'), 'after a bracket');
		pg_assert_same(200.0, ws_calc_numbers_test_block('Yüzde = 1.000 * %20'), 'a hesap block line');
		pg_assert_same(200.0, ws_calc_numbers_test_block('Yüzde2 = 1.000 * 20%'), 'a hesap block line, trailing');
	});

	ws_calc_numbers_test_in(false, function () {
		pg_assert_same(200.0, ws_calc_numbers_test_formula('1000 * %20'), 'leading, English');
		pg_assert_same(200.0, ws_calc_numbers_test_formula('1,000 * 20%'), 'trailing, English');
		pg_assert_same(5.0, ws_calc_numbers_test_formula('ROUND(%5 * 100,0)'), 'inside a function');
		pg_assert_same('syntax', ws_calc_numbers_test_formula('5 %20'), 'after a figure % is the percent sign of that figure');
	});
}

function test_workspace_calc_numbers_inline_lines()
{
	ws_calc_numbers_test_in(true, function () {
		pg_assert_same(15750.0, ws_calc_numbers_test_inline('Kira 15.000 + aidat 750 ='), 'Turkish thousands');
		pg_assert_same(200.0, ws_calc_numbers_test_inline('Tutar 1.000 * %20 ='), 'a leading % is kept');
		pg_assert_same(200.0, ws_calc_numbers_test_inline('Tutar 1.000 * 20% ='), 'a trailing % as before');
		pg_assert_same(1000.125, ws_calc_numbers_test_inline('1.000 + 0,125 ='), 'decimals survive the source');
		pg_assert_same('1000 + 0,125', ws_calc_inline('1.000 + 0,125 =')['source'], 'the source written the site\'s way');
		pg_assert_same('syntax', ws_calc_numbers_test_inline('Tutar 1.000 * % 20 ='), 'a lone % is an error, not dropped');
		pg_assert_same('syntax', ws_calc_numbers_test_inline('Saat 1.2.3 + 4 ='), 'a figure that cannot be read');
		pg_assert_same(null, ws_calc_numbers_test_inline('Kira 15.000 ='), 'one figure is no sum');
	});

	ws_calc_numbers_test_in(false, function () {
		pg_assert_same(15750.0, ws_calc_numbers_test_inline('Rent 15,000 + dues 750 ='), 'English thousands');
		pg_assert_same(200.0, ws_calc_numbers_test_inline('Total 1,000 * %20 ='), 'a leading % in English');
		pg_assert_same(1000.125, ws_calc_numbers_test_inline('1,000 + 0.125 ='), 'English decimals');
	});
}

// A formula column of a workflow table goes through ws_calc_bind() and
// ws_calc_evaluate() with the same figures.
function test_workspace_calc_numbers_formula_column()
{
	$known = array('PRICE' => array('text' => 'Price', 'slot' => 0));
	$resolve = function ($token) {
		return ($token[0] === 'var') ? 1000.0 : 'ref';
	};

	ws_calc_numbers_test_in(true, function () use ($known, $resolve) {
		list($expression) = ws_calc_bind('Price * %18 + 1.000', $known);
		$result = ws_calc_evaluate($expression, $resolve);
		pg_assert_same(1180.0, round($result['value'], 10), 'Turkish');
	});

	ws_calc_numbers_test_in(false, function () use ($known, $resolve) {
		list($expression) = ws_calc_bind('ROUND(Price * 0.18,0) + 1,000', $known);
		$result = ws_calc_evaluate($expression, $resolve);
		pg_assert_same(1180.0, round($result['value'], 10), 'English');
	});
}

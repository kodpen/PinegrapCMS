<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace's sums (includes/workspace/calc.php): formulas
 * with comparisons and the newer functions, the groups, hidden lines and
 * settings of a hesap block, the names a block passes to the blocks and
 * tables after it, and a reader's figures worked out again.
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

// The values of a block by label, for short assertions.
function ws_calc_test_values($lines, $seed = array(), &$scope = null)
{
	$values = array();

	foreach (ws_calc_block($lines, $seed, array(), $scope) as $line) {
		if (in_array($line['kind'], array('value', 'total'), true) && ($line['label'] !== '')) {
			$values[$line['label']] = ($line['error'] !== '') ? $line['error'] : $line['value'];
		}
	}

	return $values;
}

function ws_calc_test_formula($formula)
{
	$result = ws_calc_evaluate($formula, function () {
		return 'ref';
	});

	return $result['ok'] ? $result['value'] : $result['error'];
}

function test_workspace_calc_group_sums_its_lines()
{
	$values = ws_calc_test_values(array(
		'Toplamlar:',
		'sayı1 = 200',
		'sayı2 = sayı1 + 100',
		'sayı3 = 200',
		'sayı4 = 200',
		'',
		'Kalan = 1000 - Toplamlar',
	));

	pg_assert_same(900.0, $values['Toplamlar'], 'group total');
	pg_assert_same(300.0, $values['sayı2'], 'a line inside the group uses another');
	pg_assert_same(100.0, $values['Kalan'], 'the group name after the group');
}

function test_workspace_calc_group_ends_at_the_next_heading()
{
	$values = ws_calc_test_values(array(
		'Gelir:',
		'Maaş = 40000',
		'Ek = 5000',
		'Gider:',
		'Kira = 15000',
		'',
		'Net = Gelir - Gider',
	));

	pg_assert_same(45000.0, $values['Gelir'], 'first group');
	pg_assert_same(15000.0, $values['Gider'], 'second group');
	pg_assert_same(30000.0, $values['Net'], 'both used');
}

function test_workspace_calc_group_takes_another_function()
{
	$values = ws_calc_test_values(array('Puanlar: ORTALAMA', 'a = 80', 'b = 90', 'c = 100', '', 'Enler: MAX', 'x = 3', 'y = 7'));

	pg_assert_same(90.0, $values['Puanlar'], 'average');
	pg_assert_same(7.0, $values['Enler'], 'max');

	$lines = ws_calc_block(array('Puanlar: ORTALAMA', 'a = 1'));
	pg_assert_same('group', $lines[0]['kind'], 'heading line');
	pg_assert_same('AVERAGE', $lines[0]['func'], 'function read in Turkish');
	pg_assert_same('total', $lines[2]['kind'], 'subtotal at the end');
}

function test_workspace_calc_heading_with_unknown_function_is_not_a_group()
{
	$lines = ws_calc_block(array('Toplam: BİLMEM'));

	pg_assert_same('value', $lines[0]['kind'], 'read as a line');
	pg_assert_same('ref', $lines[0]['error'], 'and it fails as one');
}

function test_workspace_calc_group_error_is_its_total()
{
	$values = ws_calc_test_values(array('G:', 'a = 5', 'b = 1 / 0', '', 'c = G + 1'));

	pg_assert_same('div0', $values['G'], 'a member error');
	pg_assert_same('ref', $values['c'], 'a line using it cannot be worked out');
}

function test_workspace_calc_hidden_lines_count_but_are_not_shown()
{
	$lines = ws_calc_block(array('_kur = 34,20', 'Yazılım = 49 * kur', 'Diğer = 2 * _kur'));

	pg_assert_true($lines[0]['hidden'], 'hidden line');
	pg_assert_same('kur', $lines[0]['label'], 'name without the mark');
	pg_assert_false($lines[0]['input'], 'a hidden figure is not an input');
	pg_assert_same(1675.8, round($lines[1]['value'], 2), 'used by its name');
	pg_assert_same(68.4, round($lines[2]['value'], 2), 'used with the mark');

	$html = ws_calc_block_html(array('_kur = 34,20', 'Yazılım = 49 * kur'));
	pg_assert_false(strpos($html, '<th scope="row">kur</th>') !== false, 'hidden row not drawn');
	pg_assert_contains('Yazılım', $html, 'visible row drawn');
}

function test_workspace_calc_hidden_line_inside_group_still_counts()
{
	$values = ws_calc_test_values(array('T:', 'a = 200', '_b = 300', '', 'x = T'));

	pg_assert_same(500.0, $values['T'], 'hidden member counts');
}

function test_workspace_calc_hidden_group_hides_its_lines()
{
	$lines = ws_calc_block(array('_Ara:', 'a = 1', 'b = 2', '', 'Son = Ara * 10'));

	pg_assert_true($lines[0]['hidden'], 'heading hidden');
	pg_assert_true($lines[1]['hidden'], 'member hidden');
	pg_assert_true($lines[3]['hidden'], 'subtotal hidden');
	pg_assert_same(30.0, $lines[5]['value'], 'still known by name');
}

function test_workspace_calc_blank_lines_are_gaps()
{
	$html = ws_calc_block_html(array('', 'a = 1', '', '', 'b = 2', ''));

	pg_assert_same(1, substr_count($html, 'ws-calc-gap'), 'one gap between two rows, none at the ends');
}

function test_workspace_calc_block_settings()
{
	pg_assert_same(array('decimals' => 2, 'unit' => '₺'), ws_calc_block_options('ondalık=2 birim=₺'), 'Turkish keys');
	pg_assert_same(array('decimals' => 0, 'unit' => 'kg'), ws_calc_block_options('decimals = 0; unit = kg'), 'English keys, spaces');
	pg_assert_same(array('decimals' => null, 'unit' => ''), ws_calc_block_options('nonsense'), 'nothing known');
	pg_assert_same(array('decimals' => null, 'unit' => 'x'), ws_calc_block_options('birim=<x>'), 'markup stripped');
}

function test_workspace_calc_format_with_places_and_unit()
{
	$comma = ws_calc_decimal_comma();

	pg_assert_same($comma ? '1.234,50' : '1,234.50', ws_calc_format(1234.5), 'default');
	pg_assert_same($comma ? '1.234,500' : '1,234.500', ws_calc_format(1234.5, 3), 'three places');
	pg_assert_same('1' . ($comma ? '.' : ',') . "235\xc2\xa0₺", ws_calc_format(1234.5, 0, '₺'), 'unit after');
	pg_assert_same('-$5' . ($comma ? ',' : '.') . '00', ws_calc_format(-5, 2, '$'), 'dollar before, sign first');
	pg_assert_same('0', ws_calc_format(-0.0001, 0), 'no minus nought');
}

function test_workspace_calc_fence_with_settings()
{
	pg_assert_same('hesap', ws_code_fence_open('```hesap ondalık=2 birim=₺'), 'hesap with settings');
	pg_assert_same('ondalık=2 birim=₺', ws_code_fence_info('```hesap ondalık=2 birim=₺'), 'its settings');
	pg_assert_same(null, ws_code_fence_open('```php some words'), 'other languages take no words');
	pg_assert_same('php', ws_code_fence_open('```php'), 'plain language');
	pg_assert_same('', ws_code_fence_open('```'), 'bare fence');
	pg_assert_same('', ws_code_fence_info('```hesap'), 'no settings');
}

function test_workspace_calc_comparisons_and_if()
{
	pg_assert_same(1.0, ws_calc_test_formula('3 > 2'), 'greater');
	pg_assert_same(0.0, ws_calc_test_formula('3 <= 2'), 'not less or equal');
	pg_assert_same(1.0, ws_calc_test_formula('0.1 + 0.2 = 0.3'), 'equal within a hair');
	pg_assert_same(1.0, ws_calc_test_formula('2 <> 3'), 'not equal');
	pg_assert_same(10.0, ws_calc_test_formula('IF(5 > 3; 10; 20)'), 'IF true');
	pg_assert_same(20.0, ws_calc_test_formula('EĞER(5 < 3; 10; 20)'), 'EĞER false');
	pg_assert_same(0.0, ws_calc_test_formula('IF(0; 1)'), 'missing else is nought');
	pg_assert_same(0.0, ws_calc_test_formula('IF(0 = 0; 0; 5 / 0)'), 'the branch not taken may fail');
	pg_assert_same(7.0, ws_calc_test_formula('EĞERHATA(1 / 0; 7)'), 'IFERROR fallback');
	pg_assert_same('div0', ws_calc_test_formula('1 + 1 / 0 * 3'), 'error with the rest still read');
	pg_assert_same(1.0, ws_calc_test_formula('VE(1; 2 > 1)'), 'AND');
	pg_assert_same(0.0, ws_calc_test_formula('YADA(0; 0)'), 'OR');
}

function test_workspace_calc_newer_functions()
{
	pg_assert_same(2.68, ws_calc_test_formula('ROUNDUP(2.671; 2)'), 'round up');
	pg_assert_same(-2.67, ws_calc_test_formula('AŞAĞIYUVARLA(-2.679; 2)'), 'round down towards nought');
	pg_assert_same(3.0, ws_calc_test_formula('KAREKÖK(9)'), 'square root');
	pg_assert_same('value', ws_calc_test_formula('SQRT(-1)'), 'no root of a negative');
	pg_assert_same(8.0, ws_calc_test_formula('KUVVET(2; 3)'), 'power');
	pg_assert_same(1.0, ws_calc_test_formula('MOD(10; 3)'), 'remainder');
	pg_assert_same(2.0, ws_calc_test_formula('MOD(-10; 3)'), 'remainder takes the divisor sign');
	pg_assert_same(24.0, ws_calc_test_formula('ÇARPIM(2; 3; 4)'), 'product');
	pg_assert_same(2.5, ws_calc_test_formula('ORTANCA(1; 2; 3; 4)'), 'median');
	pg_assert_same(888.49, round(ws_calc_test_formula('TAKSİT(1%; 12; 10000)'), 2), 'installment');
	pg_assert_same(-888.49, round(ws_calc_test_formula('PMT(1%; 12; 10000)'), 2), 'PMT as money going out');
	pg_assert_same(1000.0, ws_calc_test_formula('TAKSİT(0; 10; 10000)'), 'no interest');
	pg_assert_same('syntax', ws_calc_test_formula('ROUND(2;)'), 'argument left out');
	pg_assert_same('name', ws_calc_test_formula('BILINMEZ(1)'), 'unknown function');
}

function test_workspace_calc_assignment_skips_comparisons()
{
	pg_assert_same(5, ws_calc_assignment_at('Fark = EĞER(a >= b; 1; 0)'), 'first plain =');
	pg_assert_same(false, ws_calc_assignment_at('a >= b'), 'only a comparison');
	pg_assert_same(false, ws_calc_assignment_at('a == b'), 'double equals');

	$values = ws_calc_test_values(array('a = 5', 'b = 3', 'Büyük = EĞER(a >= b; a; b)'));
	pg_assert_same(5.0, $values['Büyük'], 'IF with >= in a line');
}

function test_workspace_calc_names_pass_to_later_blocks_and_tables()
{
	$lines = array(
		'```hesap',
		'Kur = 34,20',
		'```',
		'| Ürün | USD | TL |',
		'| --- | --- | --- |',
		'| A | 10 | =B2*Kur |',
		'```hesap',
		'Toplam = 2 * Kur',
		'```',
	);

	$calc = ws_calc_body_scope($lines);

	pg_assert_same(array(), $calc['seeds'][0], 'the first block inherits nothing');
	pg_assert_same(34.2, $calc['seeds'][6]['kur']['value'], 'the second inherits the rate');
	pg_assert_same(68.4, round($calc['all']['toplam']['value'], 2), 'every name in all');

	$grid = ws_calc_grid(array(array('Ürün', 'USD', 'TL'), array('A', '10', '=B2*Kur')), $calc['all']);
	pg_assert_same(342.0, round($grid[1][2]['value'], 2), 'a table uses a block name');
	pg_assert_true($grid[1][2]['named'], 'and says so');

	$grid = ws_calc_grid(array(array('A', 'B'), array('1', '=A2+1')), $calc['all']);
	pg_assert_false($grid[1][1]['named'], 'a table without names');
}

function test_workspace_calc_names_do_not_take_cells_or_functions()
{
	$scope = array('a' => array('text' => 'A', 'value' => 100.0), 'topla' => array('text' => 'TOPLA', 'value' => 5.0));
	$grid = ws_calc_grid(array(array('x', 'y'), array('2', '=A2+A'), array('3', '=TOPLA(A2:A3)+TOPLA')), $scope);

	pg_assert_same(102.0, $grid[1][1]['value'], 'A2 is the cell, A the name');
	pg_assert_same(10.0, $grid[2][1]['value'], 'TOPLA( is the function, TOPLA the name');
}

function test_workspace_calc_live_works_again_with_other_figures()
{
	$out = ws_calc_live(array(
		array('lines' => array('Kur = 40'), 'info' => ''),
		array('grid' => array(array('USD', 'TL'), array('10', '=A2*Kur'))),
		array('lines' => array('İki = Kur * 2'), 'info' => 'ondalık=1'),
		'nonsense',
	));

	pg_assert_same(4, count($out), 'one answer per unit');
	pg_assert_contains('data-ws-calc-input="0"', $out[0]['html'], 'the figure stays an input');
	pg_assert_same(array(array('r' => 1, 'c' => 1, 'display' => '400', 'error' => '')), $out[1]['cells'], 'the table follows');
	pg_assert_contains(ws_calc_decimal_comma() ? '80,0' : '80.0', $out[2]['html'], 'the later block follows, with its settings');
	pg_assert_same(array(), $out[3], 'a unit that is neither');
}

function test_workspace_calc_plain_text_shows_groups_not_hidden()
{
	$plain = ws_calc_plain("```hesap\n_k = 2\nG:\na = 3 * k\nb = 4\n\nSon = G + 1\n```");

	pg_assert_false(strpos($plain, 'k = 2') !== false, 'hidden line left out');
	pg_assert_contains('G:', $plain, 'group heading');
	pg_assert_contains('G = 10', $plain, 'subtotal');
	pg_assert_contains('Son = 11 (G + 1)', $plain, 'line after the group');
}

function test_workspace_calc_older_blocks_read_as_before()
{
	$values = ws_calc_test_values(array('Kira = 15000', 'Aidat = 750', 'Toplam = Kira + Aidat', '# not', '200 + 300'));

	pg_assert_same(15750.0, $values['Toplam'], 'names');

	$lines = ws_calc_block(array('200 + 300'));
	pg_assert_same(500.0, $lines[0]['value'], 'a bare expression');
	pg_assert_same('', $lines[0]['label'], 'with no name');
}

function test_workspace_calc_a_written_share_shows_as_one()
{
	$lines = ws_calc_block(array('Oran = 3.5%', 'KDV = 20%', 'Tutar = 100 * KDV'));

	pg_assert_same(ws_calc_decimal_comma() ? '%3,5' : '3.5%', $lines[0]['display'], 'decimals kept');
	pg_assert_same(ws_calc_decimal_comma() ? '%20' : '20%', $lines[1]['display'], 'a whole share');
	pg_assert_same(0.2, $lines[1]['value'], 'the value stays the share');
	pg_assert_same('20', $lines[2]['display'], 'a line using it is a figure');
}

function test_workspace_calc_inline_reads_a_highlighted_figure()
{
	$plain = ws_calc_inline('Kira 15.000 + aidat 750 =');
	$marked = ws_calc_inline('Kira ==15.000== + aidat 750 =');

	pg_assert_same(15750.0, (float) ws_calc_inline('Kira 15000 + aidat 750 =')['value'], 'the plain line');
	pg_assert_same((float) $plain['value'], (float) $marked['value'], 'a highlighted figure adds up the same');
	pg_assert_same(5.0, (float) ws_calc_inline('==2== + ==3== =')['value'], 'two highlighted figures');
}

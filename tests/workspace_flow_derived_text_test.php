<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the text rules of a flow's derived value box
 * (includes/workspace/flow_values.php): replace, take a number or an e-mail
 * address out of a text, capitals or small letters (Turkish i on a Turkish
 * site), tidy the spaces, cut to N characters, split and take part N. An
 * input the rule cannot read gives an empty value, never an error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

function ws_flow_derived_text_test_value($rule, $a, $b = '', $c = '')
{
	$config = ws_flow_derived_normalize(array('rule' => $rule, 'a' => $a, 'b' => $b, 'c' => $c));

	return ws_flow_derived_value($config, $config['a'], $config['b'], array(), $config['c']);
}

// The rules are kept with their types, and those that read b or c say so.
function test_workspace_flow_derived_text_rules_listed()
{
	$rules = ws_flow_derived_rules();

	foreach (array('replace', 'extract_email', 'upper', 'lower', 'trim', 'cut', 'split') as $rule) {
		pg_assert_same('text', $rules[$rule] ?? null, $rule . ' gives a text');
	}

	pg_assert_same('number', $rules['extract_number'] ?? null, 'a number taken out is a number');

	foreach (array('replace', 'cut', 'split') as $rule) {
		pg_assert_true(in_array($rule, ws_flow_derived_two_inputs(), true), $rule . ' reads b');
	}

	pg_assert_same(array('replace', 'split'), ws_flow_derived_three_inputs(), 'the rules that read c');
	pg_assert_same('Ada', ws_flow_derived_normalize(array('c' => ' Ada '))['c'], 'c kept as one line');
	pg_assert_same('', ws_flow_derived_normalize(array('c' => array('x')))['c'], 'c only a text');

	$lists = ws_flow_derived_lists();
	$split = array_values(array_filter($lists['rules'], function ($rule) {
		return $rule['key'] === 'split';
	}))[0];

	pg_assert_true($split['two'] && $split['three'], 'the canvas knows split reads b and c');
}

function test_workspace_flow_derived_text_replace()
{
	pg_assert_same('Order 1001 shipped', ws_flow_derived_text_test_value('replace', 'Order #1001 shipped', '#', ''), 'an empty c takes the text out');
	pg_assert_same('a-b-c', ws_flow_derived_text_test_value('replace', 'a/b/c', '/', '-'), 'every place');
	pg_assert_same('Cat cat', ws_flow_derived_text_test_value('replace', 'Cat dog', 'dog', 'cat'), 'as written, case kept');
	pg_assert_same('a.b', ws_flow_derived_text_test_value('replace', 'a.b', '.*', 'x'), 'never a pattern');
	pg_assert_same('', ws_flow_derived_text_test_value('replace', 'abc', '', 'x'), 'nothing to find');
}

function test_workspace_flow_derived_text_extract()
{
	$comma = ws_calc_decimal_comma();

	ws_calc_decimal_comma(true);
	pg_assert_same('1250.5', ws_flow_derived_text_test_value('extract_number', 'Toplam: 1.250,50 TL'), 'as a Turkish site writes it');
	pg_assert_same('-12', ws_flow_derived_text_test_value('extract_number', 'Change -12 units'), 'a minus');
	pg_assert_same('123', ws_flow_derived_text_test_value('extract_number', 'Code ABC-123'), 'a dash in a code is no minus');
	pg_assert_same('3', ws_flow_derived_text_test_value('extract_number', 'Room 3, floor 2'), 'the first number');
	ws_calc_decimal_comma(false);
	pg_assert_same('1250.5', ws_flow_derived_text_test_value('extract_number', 'Total $1,250.50 due'), 'as an English site writes it');
	pg_assert_same('', ws_flow_derived_text_test_value('extract_number', 'no figure'), 'none');
	ws_calc_decimal_comma($comma);

	pg_assert_same('ada.l+shop@example.co.uk', ws_flow_derived_text_test_value('extract_email', 'Write to <ada.l+shop@example.co.uk>.'), 'the address alone');
	pg_assert_same('a@b.io', ws_flow_derived_text_test_value('extract_email', 'From a@b.io, cc c@d.io'), 'the first one');
	pg_assert_same('', ws_flow_derived_text_test_value('extract_email', 'ada at example dot com'), 'none');
	pg_assert_same('', ws_flow_derived_text_test_value('extract_email', 'user@localhost'), 'no domain');
}

function test_workspace_flow_derived_text_case_and_spaces()
{
	pg_assert_same('ISTANBUL', ws_flow_derived_case('istanbul', true, false), 'capitals');
	pg_assert_same('İSTANBUL ILIK', ws_flow_derived_case('istanbul ılık', true, true), 'Turkish capitals');
	pg_assert_same('ılık istanbul', ws_flow_derived_case('ILIK İSTANBUL', false, true), 'Turkish small letters');
	pg_assert_same('ilik istanbul', ws_flow_derived_case('ILIK ISTANBUL', false, false), 'small letters');
	pg_assert_same('ÇAĞ', ws_flow_derived_text_test_value('upper', 'çağ'), 'rule upper');
	pg_assert_same('ab', ws_flow_derived_text_test_value('lower', 'AB'), 'rule lower');
	pg_assert_same('a b c', ws_flow_derived_value(array('rule' => 'trim'), "  a \t b\n\nc ", ''), 'spaces tidied');
}

function test_workspace_flow_derived_text_cut_and_split()
{
	pg_assert_same('Şeker', ws_flow_derived_text_test_value('cut', 'Şekerleme', '5'), 'by characters');
	pg_assert_same('abc', ws_flow_derived_text_test_value('cut', 'abc', '10'), 'shorter stays');
	pg_assert_same('', ws_flow_derived_text_test_value('cut', 'abc', 'ten'), 'no whole number');
	pg_assert_same('', ws_flow_derived_text_test_value('cut', 'abc', '0'), 'not one character');

	pg_assert_same('Ada', ws_flow_derived_text_test_value('split', 'Ada Lovelace'), 'by spaces, the first part');
	pg_assert_same('Lovelace', ws_flow_derived_text_test_value('split', 'Ada  Lovelace', '', '2'), 'spaces between count once');
	pg_assert_same('c', ws_flow_derived_text_test_value('split', 'a; b ;c', ';', '-1'), 'the last, trimmed');
	pg_assert_same('b', ws_flow_derived_text_test_value('split', 'a;b;c', ';', '-2'), 'from the end');
	pg_assert_same('', ws_flow_derived_text_test_value('split', 'a;b', ';', '3'), 'no such part');
	pg_assert_same('', ws_flow_derived_text_test_value('split', 'a;b', ';', 'two'), 'no whole number');
}

// The check names what is missing; the reader and the fill take c too.
function test_workspace_flow_derived_text_check_and_run()
{
	$problems = function ($rule, $b, $c = '') {
		return ws_flow_derived_problems(ws_flow_derived_normalize(array('rule' => $rule, 'a' => 'x', 'b' => $b, 'c' => $c)), 'Box');
	};

	pg_assert_same(1, count($problems('replace', '')), 'replace needs what it finds');
	pg_assert_same(array(), $problems('replace', 'x'), 'an empty replacement is fine');
	pg_assert_same(1, count($problems('cut', 'ten')), 'cut needs a whole number');
	pg_assert_same(1, count($problems('cut', '')), 'cut needs its length');
	pg_assert_same(array(), $problems('cut', '{{node.a_1.n}}'), 'a value is read at run time');
	pg_assert_same(array(), $problems('split', ''), 'split by spaces');
	pg_assert_same(1, count($problems('split', ',', '0')), 'part 0 is none');
	pg_assert_same(array(), $problems('split', ',', '-1'), 'the last part');

	pg_assert_same(array('const_1'), ws_flow_derived_reads(ws_flow_derived_normalize(array('rule' => 'replace', 'a' => 'x', 'b' => 'y', 'c' => '{{node.const_1.with}}'))), 'c read');

	$graph = array('nodes' => array(
		array('id' => 'const_1', 'kind' => 'constants', 'config' => array('rows' => array(array('key' => 'with', 'type' => 'text', 'value' => 'Lovelace'), array('key' => 'part', 'type' => 'text', 'value' => '2')))),
		array('id' => 'value_1', 'kind' => 'value', 'config' => array('rule' => 'replace', 'a' => 'Ada Byron', 'b' => 'Byron', 'c' => '{{node.const_1.with}}')),
		array('id' => 'value_2', 'kind' => 'value', 'config' => array('rule' => 'split', 'a' => '{{node.value_1.value}}', 'b' => '', 'c' => '{{node.const_1.part}}')),
	), 'edges' => array());
	$values = ws_flow_pure_values($graph);

	pg_assert_same('Ada Lovelace', $values['value_1']['value'], 'c filled from a value');
	pg_assert_same('Lovelace', $values['value_2']['value'], 'a part number from a value');
}

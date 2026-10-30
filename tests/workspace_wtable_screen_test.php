<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the workflow tables screen turns between fields and cells
 * (includes/workspace/wtable_screen.php): money typed and shown, status
 * choices keeping their keys, column options from the form, record links,
 * amounts and numbers read and shown the way the site writes them, and
 * every store code worded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');

// Runs $test with the site writing decimals with a comma (Turkish) or a dot,
// then puts the site's own way back.
function ws_wtable_screen_test_in($comma, $test)
{
	$own = ws_calc_decimal_comma();
	ws_calc_decimal_comma($comma);

	try {
		$test();
	} finally {
		ws_calc_decimal_comma($own);
	}
}

function test_workspace_wtable_screen_money_text()
{
	pg_assert_same('12.50', ws_wtable_screen_money_text(1250, 2));
	pg_assert_same('0.05', ws_wtable_screen_money_text(5, 2), 'leading noughts');
	pg_assert_same('-0.05', ws_wtable_screen_money_text(-5, 2), 'the sign before the noughts');
	pg_assert_same('7', ws_wtable_screen_money_text(7, 0));
	pg_assert_same('1.234', ws_wtable_screen_money_text(1234, 3));
}

function test_workspace_wtable_screen_money_from_form_rounds()
{
	ws_wtable_screen_test_in(false, function () {
		$column = array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'EUR'));

		pg_assert_same(1250, ws_wtable_screen_cell_from_form($column, '12.50'));
		pg_assert_same(1250, ws_wtable_screen_cell_from_form($column, '12,5'), 'a lone comma before one digit is a decimal');
		pg_assert_same(29, ws_wtable_screen_cell_from_form($column, '0.29'), 'rounded, not cut to 28');
		pg_assert_same(1001, ws_wtable_screen_cell_from_form($column, '10.005'), 'a third decimal rounds');
		pg_assert_same(null, ws_wtable_screen_cell_from_form($column, '  '), 'empty empties the cell');
		pg_assert_same('twelve', ws_wtable_screen_cell_from_form($column, 'twelve'), 'left for the store to refuse');

		$check = ws_wtable_cell_check($column, ws_wtable_screen_cell_from_form($column, 'twelve'));
		pg_assert_same('not_minor_units', $check['error']);
		pg_assert_same('12.50', ws_wtable_screen_cell_to_form($column, 1250), 'shown as typed');
		pg_assert_same('12.50 EUR', ws_wtable_screen_cell_text($column, 1250));
	});
}

function test_workspace_wtable_screen_amounts_read_the_site_way()
{
	$column = array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'TRY'));

	ws_wtable_screen_test_in(true, function () use ($column) {
		pg_assert_same(125000, ws_wtable_screen_cell_from_form($column, '1.250'), 'Turkish: a dot before three digits groups thousands');
		pg_assert_same(125050, ws_wtable_screen_cell_from_form($column, '1.250,50'));
		pg_assert_same(125050, ws_wtable_screen_cell_from_form($column, '1 250,50'), 'a space groups too');
		pg_assert_same(1250, ws_wtable_screen_cell_from_form($column, '12,50'));
		pg_assert_same(1250, ws_wtable_screen_cell_from_form($column, '12.50'), 'a dot before two digits is still a decimal');
		pg_assert_same(-125050, ws_wtable_screen_cell_from_form($column, '-1.250,50'));
		pg_assert_same(125050, ws_wtable_screen_cell_from_form($column, '₺1.250,50'), 'a currency mark is no part of the figure');
		pg_assert_same('5%', ws_wtable_screen_cell_from_form($column, '5%'), 'a share is no amount');
		pg_assert_same('1.2.3', ws_wtable_screen_cell_from_form($column, '1.2.3'));
		pg_assert_same('1.250,50 TRY', ws_wtable_screen_cell_text($column, 125050));
		pg_assert_same('-0,05 TRY', ws_wtable_screen_cell_text($column, -5));
		pg_assert_same('1250,50', ws_wtable_screen_cell_to_form($column, 125050), 'the field takes no grouping');
		pg_assert_same(125050, ws_wtable_screen_cell_from_form($column, ws_wtable_screen_cell_to_form($column, 125050)), 'the field reads back as it was');
	});

	ws_wtable_screen_test_in(false, function () use ($column) {
		pg_assert_same(125050, ws_wtable_screen_cell_from_form($column, '1,250.50'));
		pg_assert_same(125, ws_wtable_screen_cell_from_form($column, '1.25'));
		pg_assert_same(125000, ws_wtable_screen_cell_from_form($column, '1,250'), 'English: a comma before three digits groups thousands');
		pg_assert_same('1,250.50 TRY', ws_wtable_screen_cell_text($column, 125050));
		pg_assert_same('1250.50', ws_wtable_screen_cell_to_form($column, 125050));
	});

	$three = array('type' => 'money', 'options' => array('decimals' => 3));

	ws_wtable_screen_test_in(true, function () use ($three) {
		pg_assert_same('1,234', ws_wtable_screen_cell_to_form($three, 1234));
		pg_assert_same(1234, ws_wtable_screen_cell_from_form($three, ws_wtable_screen_cell_to_form($three, 1234)), 'three decimals read back, not as thousands');
	});
}

function test_workspace_wtable_screen_amounts_stored_as_before()
{
	$column = array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'TRY'));

	foreach (array(true, false) as $comma) {
		ws_wtable_screen_test_in($comma, function () use ($column) {
			$check = ws_wtable_cell_check($column, ws_wtable_screen_cell_from_form($column, ws_calc_decimal_comma() ? '1.250,50' : '1,250.50'));
			pg_assert_true($check['ok'], 'the store takes it');
			pg_assert_same(125050, $check['value'], 'minor units, as before');
		});
	}

	ws_wtable_screen_test_in(true, function () use ($column) {
		$check = ws_wtable_cell_check($column, ws_wtable_screen_cell_from_form($column, '99.999.999.999.999,00'));
		pg_assert_same('out_of_range', $check['error'], 'too large is still the store\'s answer');
	});
}

function test_workspace_wtable_screen_numbers_read_and_shown_the_site_way()
{
	$number = array('type' => 'number', 'options' => array('decimals' => 2));

	ws_wtable_screen_test_in(true, function () use ($number) {
		pg_assert_same(1250.0, ws_wtable_screen_cell_from_form($number, '1.250'));
		pg_assert_same(1250.5, ws_wtable_screen_cell_from_form($number, '1.250,50'));
		pg_assert_same(1.5, ws_wtable_screen_cell_from_form($number, '1,5'));
		pg_assert_same('abc', ws_wtable_screen_cell_from_form($number, 'abc'), 'left for the store to refuse');
		pg_assert_same('1.234,5', ws_wtable_screen_cell_text($number, 1234.5));
		pg_assert_same('1.234', ws_wtable_screen_cell_text($number, 1234));
		pg_assert_same('1234,5', ws_wtable_screen_cell_to_form($number, 1234.5));
		pg_assert_same('2,5', ws_wtable_screen_cell_text(array('type' => 'formula'), 2.5), 'a formula result too');
		pg_assert_same('1.234.567,125', ws_wtable_screen_cell_text(array('type' => 'formula'), 1234567.125));

		$check = ws_wtable_cell_check($number, ws_wtable_screen_cell_from_form($number, '1.250,50'));
		pg_assert_same(1250.5, $check['value'], 'stored in machine form');
	});

	ws_wtable_screen_test_in(false, function () use ($number) {
		pg_assert_same(1250.5, ws_wtable_screen_cell_from_form($number, '1,250.50'));
		pg_assert_same(1.25, ws_wtable_screen_cell_from_form($number, '1.25'));
		pg_assert_same('1,234.5', ws_wtable_screen_cell_text($number, 1234.5));
		pg_assert_same('1234.5', ws_wtable_screen_cell_to_form($number, 1234.5));
		pg_assert_same('-0.5', ws_wtable_screen_cell_text(array('type' => 'formula'), -0.5));
	});
}

function test_workspace_wtable_screen_amount_hint_written_the_site_way()
{
	ws_wtable_screen_test_in(true, function () {
		pg_assert_true(strpos(ws_wtable_screen_error_text('not_minor_units'), '12,50') !== false, 'Turkish sample');
	});

	ws_wtable_screen_test_in(false, function () {
		pg_assert_true(strpos(ws_wtable_screen_error_text('not_minor_units'), '12.50') !== false, 'English sample');
	});
}

function test_workspace_wtable_screen_cells_from_form()
{
	pg_assert_same(true, ws_wtable_screen_cell_from_form(array('type' => 'checkbox'), '1'));
	pg_assert_same(false, ws_wtable_screen_cell_from_form(array('type' => 'checkbox'), '0'), 'the hidden nought of an unticked box');
	pg_assert_same('2026-10-29 09:30', ws_wtable_screen_cell_from_form(array('type' => 'date', 'options' => array('time' => true)), '2026-10-29T09:30'));
	pg_assert_same(12, ws_wtable_screen_cell_from_form(array('type' => 'person'), '12'));
	pg_assert_same(array('kind' => 'contact', 'id' => 12), ws_wtable_screen_cell_from_form(array('type' => 'link'), 'Contact:12'));
	pg_assert_same(array('kind' => 'product', 'id' => 7), ws_wtable_screen_cell_from_form(array('type' => 'link'), 'product #7'));
	pg_assert_same('contact', ws_wtable_screen_cell_from_form(array('type' => 'link'), 'contact'), 'no id: refused by the store');
	pg_assert_same('contact:12', ws_wtable_screen_cell_to_form(array('type' => 'link'), array('kind' => 'contact', 'id' => 12)));
	pg_assert_same('', ws_wtable_screen_cell_from_form(array('type' => 'text'), array('x')), 'an array is no text');
}

function test_workspace_wtable_screen_cell_text()
{
	$status = array('type' => 'status', 'options' => array('choices' => array(array('key' => 's1', 'label' => 'Open', 'color' => ''))));

	pg_assert_same('Open', ws_wtable_screen_cell_text($status, 's1'));
	pg_assert_same('', ws_wtable_screen_cell_text($status, 'gone'), 'a choice removed shows nothing');
	pg_assert_same('Ada', ws_wtable_screen_cell_text(array('type' => 'person'), 3, array(3 => array('name' => 'Ada'))));
	pg_assert_same('#4', ws_wtable_screen_cell_text(array('type' => 'person'), 4), 'a person not read');
	pg_assert_same('✓', ws_wtable_screen_cell_text(array('type' => 'checkbox'), true));
	pg_assert_same('', ws_wtable_screen_cell_text(array('type' => 'checkbox'), false));
	pg_assert_same('contact #12', ws_wtable_screen_cell_text(array('type' => 'link'), array('kind' => 'contact', 'id' => 12)));
}

function test_workspace_wtable_screen_status_choices_keep_keys()
{
	$old = array(array('key' => 's1', 'label' => 'Open', 'color' => '#112233'), array('key' => 's2', 'label' => 'Done', 'color' => ''));
	$choices = ws_wtable_screen_status_choices("Done\n\nWaiting\nOpen\nDone", $old);

	pg_assert_same(array(
		array('key' => 's2', 'label' => 'Done', 'color' => ''),
		array('key' => 's3', 'label' => 'Waiting', 'color' => ''),
		array('key' => 's1', 'label' => 'Open', 'color' => '#112233'),
	), $choices, 'kept keys and colours, a new key for a new label, empty and repeated lines dropped');

	$check = ws_wtable_column_options_check('status', array('choices' => $choices));
	pg_assert_true($check['ok'], 'the store takes them');
}

function test_workspace_wtable_screen_options_from_form()
{
	pg_assert_same(array('decimals' => '2', 'currency' => 'usd'), ws_wtable_screen_options_from_form('money', array('decimals' => '2', 'currency' => ' usd ')));
	pg_assert_same(array(), ws_wtable_screen_options_from_form('number', array('decimals' => '')), 'the store\'s default');
	pg_assert_same(array('time' => true), ws_wtable_screen_options_from_form('date', array('time' => '1')));
	pg_assert_same(array('kinds' => array('contact', 'product')), ws_wtable_screen_options_from_form('link', array('kinds' => 'Contact, product,')));
	pg_assert_same(array('expression' => 'Price * Quantity'), ws_wtable_screen_options_from_form('formula', array('expression' => 'Price * Quantity')));
	pg_assert_same(array(), ws_wtable_screen_options_from_form('text', array('expression' => 'x')));
	pg_assert_same(array(), ws_wtable_screen_options_from_form('checkbox', 'not an array'));
}

function test_workspace_wtable_screen_error_text_words_every_code()
{
	$fallback = ws_wtable_screen_error_text('no_such_code');

	foreach (array('not_found', 'forbidden', 'name_empty', 'too_many_rows', 'changed_meanwhile', 'cells_invalid', 'channel_level',
		'target_not_member', 'not_minor_units', 'not_link', 'unknown_column', 'type_fixed', 'formula_empty') as $code) {
		pg_assert_true(ws_wtable_screen_error_text($code) !== $fallback, $code . ' has its own sentence');
	}
}

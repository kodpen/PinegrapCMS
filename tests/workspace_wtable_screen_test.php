<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the workflow tables screen turns between fields and cells
 * (includes/workspace/wtable_screen.php): money typed and shown, status
 * choices keeping their keys, column options from the form, record links,
 * and every store code worded.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');

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
	$column = array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'EUR'));

	pg_assert_same(1250, ws_wtable_screen_cell_from_form($column, '12.50'));
	pg_assert_same(1250, ws_wtable_screen_cell_from_form($column, '12,5'), 'a decimal comma');
	pg_assert_same(29, ws_wtable_screen_cell_from_form($column, '0.29'), 'rounded, not cut to 28');
	pg_assert_same(1001, ws_wtable_screen_cell_from_form($column, '10.005'), 'a third decimal rounds');
	pg_assert_same(null, ws_wtable_screen_cell_from_form($column, '  '), 'empty empties the cell');
	pg_assert_same('twelve', ws_wtable_screen_cell_from_form($column, 'twelve'), 'left for the store to refuse');

	$check = ws_wtable_cell_check($column, ws_wtable_screen_cell_from_form($column, 'twelve'));
	pg_assert_same('not_minor_units', $check['error']);
	pg_assert_same('12.50', ws_wtable_screen_cell_to_form($column, 1250), 'shown as typed');
	pg_assert_same('12.50 EUR', ws_wtable_screen_cell_text($column, 1250));
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
	pg_assert_same('2.5', ws_wtable_screen_cell_text(array('type' => 'formula'), 2.5));
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

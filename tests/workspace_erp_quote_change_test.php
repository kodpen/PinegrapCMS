<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the sales quote (erp_quote) as a kind of Workspace change: its entry
 * in the change catalogue, the document lines it takes (value kind items),
 * what a new one needs, how its lines are shown on the card and how its
 * fields are shaped for erp_quote_save().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The lines field of a sales quote, as the catalogue gives it.
function workspace_erp_quote_change_test_field()
{
	return pg_test_ws_change_types()['erp_quote']['create']['lines'];
}

// A sales quote is made, never changed or deleted, from the Workspace, with
// the ERP right; the account and the lines come only with a new one.
function test_workspace_erp_quote_change_in_the_catalogue()
{
	$type = pg_test_ws_change_types()['erp_quote'] ?? null;

	pg_assert_true(is_array($type), 'ws_change_types() has the sales quote');
	pg_assert_same('erp', $type['right'], 'asked with the ERP right');
	pg_assert_same('erp_quote', $type['tag'], 'shown with the sales quote tag');
	pg_assert_same('erp_quotes', $type['table'], 'kept in erp_quotes, never in erp_invoices');
	pg_assert_same(array('create'), $type['actions'], 'only made');
	pg_assert_same(array('ids', 'erp_account'), array_slice($type['create']['account_id'], 0, 2), 'account_id names a current account');
	pg_assert_same('items', $type['create']['lines'][0], 'lines are document lines');
	pg_assert_same(array('issue_date', 'valid_until', 'currency', 'notes'), array_keys($type['fields']), 'the header fields');
	pg_assert_true(in_array('erp_quote', ws_change_erp_types(), true), 'refused to the ERP\'s read-only right');
}

// Document lines are read into one shape; a price is whole minor units.
function test_workspace_erp_quote_change_items_value()
{
	$field = workspace_erp_quote_change_test_field();

	pg_assert_same(array(
		array('product_id' => 12, 'description' => 'Red mug', 'quantity' => 2.5, 'unit' => 'C62', 'unit_price' => 1999, 'discount_rate' => 10.0, 'tax_rate' => 20.0),
		array('product_id' => 0, 'description' => 'Set up', 'quantity' => 1.0, 'unit' => '', 'unit_price' => 0, 'discount_rate' => 0.0, 'tax_rate' => 0.0),
		array('product_id' => 7, 'description' => '', 'quantity' => 1.0, 'unit' => '', 'unit_price' => 500, 'discount_rate' => 0.0, 'tax_rate' => null),
	), ws_change_value($field, array(
		array('product_id' => '12', 'description' => "  Red \n mug ", 'quantity' => '2.5', 'unit' => 'c62', 'unit_price' => '1999', 'discount_rate' => 10, 'tax_rate' => '20'),
		array('description' => 'Set up', 'unit_price' => 0, 'tax_rate' => 0),
		array('product_id' => 7, 'unit_price' => 500),
	)), 'lines in their shape');

	$refused = array(
		'not a list'           => 'two lines',
		'no line'              => array(),
		'an object'            => array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 20),
		'a line not an object' => array('Mug'),
		'an unknown field'     => array(array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 20, 'price' => 100)),
		'no price'             => array(array('description' => 'Mug', 'tax_rate' => 20)),
		'a decimal price'      => array(array('description' => 'Mug', 'unit_price' => '19.99', 'tax_rate' => 20)),
		'a negative price'     => array(array('description' => 'Mug', 'unit_price' => -1, 'tax_rate' => 20)),
		'a price as bool'      => array(array('description' => 'Mug', 'unit_price' => true, 'tax_rate' => 20)),
		'no product, no words' => array(array('unit_price' => 100, 'tax_rate' => 20)),
		'no product, no VAT'   => array(array('description' => 'Mug', 'unit_price' => 100)),
		'no quantity'          => array(array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 20, 'quantity' => 0)),
		'a VAT over 100'       => array(array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 120)),
		'a discount below 0'   => array(array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 20, 'discount_rate' => -5)),
		'a long unit'          => array(array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 20, 'unit' => 'ABCDEFGHIJK')),
		'a long description'   => array(array('description' => str_repeat('a', 256), 'unit_price' => 100, 'tax_rate' => 20)),
		'a product id of 1.5'  => array(array('product_id' => 1.5, 'unit_price' => 100)),
	);

	foreach ($refused as $what => $value) {
		pg_assert_same(null, ws_change_value($field, $value), 'refused: ' . $what);
	}

	$many = array_fill(0, $field[1][0] + 1, array('description' => 'Mug', 'unit_price' => 100, 'tax_rate' => 20));
	pg_assert_same(null, ws_change_value($field, $many), 'refused: more lines than the field takes');
	pg_assert_same(lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => 'lines')), ws_change_value_refusal($field, 'x', 'lines'), 'the refusal names the field');
}

// A new sales quote needs one account and its lines; it cannot run out
// before its own date.
function test_workspace_erp_quote_change_create_refusal()
{
	$lines = array(array('product_id' => 0, 'description' => 'Mug', 'quantity' => 1.0, 'unit' => '', 'unit_price' => 100, 'discount_rate' => 0.0, 'tax_rate' => 20.0));

	pg_assert_same('', ws_change_erp_quote_refusal(array('account_id' => array(4), 'lines' => $lines), '2026-10-25'), 'an account and a line');
	pg_assert_same(lang('A new sales quote needs account_id: the current account it is for.'), ws_change_erp_quote_refusal(array('lines' => $lines), '2026-10-25'), 'no account');
	pg_assert_same(lang('account_id is one current account.'), ws_change_erp_quote_refusal(array('account_id' => array(4, 5), 'lines' => $lines), '2026-10-25'), 'two accounts');
	pg_assert_same(lang('A new sales quote needs lines.'), ws_change_erp_quote_refusal(array('account_id' => array(4)), '2026-10-25'), 'no lines');
	pg_assert_same(lang('The quote cannot run out before its date.'), ws_change_erp_quote_refusal(array('account_id' => array(4), 'lines' => $lines, 'issue_date' => '2026-11-01', 'valid_until' => '2026-10-31'), '2026-10-25'), 'runs out before its date');
	pg_assert_same(lang('The quote cannot run out before its date.'), ws_change_erp_quote_refusal(array('account_id' => array(4), 'lines' => $lines, 'valid_until' => '2026-10-24'), '2026-10-25'), 'runs out before today, its date when none is given');
	pg_assert_same('', ws_change_erp_quote_refusal(array('account_id' => array(4), 'lines' => $lines, 'valid_until' => '2026-10-25'), '2026-10-25'), 'runs out on its own date');
}

// The card and the decision read the lines as words: quantity, what, price
// in the quote's own figures (its currency is a field of its own), VAT.
function test_workspace_erp_quote_change_items_text()
{
	$lines = array(
		array('product_id' => 12, 'description' => 'Red mug', 'quantity' => 2.5, 'unit' => 'C62', 'unit_price' => 1999, 'discount_rate' => 10.0, 'tax_rate' => 20.0),
		array('product_id' => 7, 'description' => '', 'quantity' => 1.0, 'unit' => '', 'unit_price' => 500, 'discount_rate' => 0.0, 'tax_rate' => null),
	);

	pg_assert_same('2.5 × Red mug @ 19.99 −10% · ' . lang('VAT') . ' 20% · 1 × ' . lang('Product') . ' #7 @ 5.00', ws_change_items_text($lines, 300), 'every line');
	pg_assert_same('2.5 × Red mug @ 19.99…', ws_change_items_text($lines, 22), 'cut to the limit');
	pg_assert_same(lang('(empty)'), ws_change_items_text(array(), 300), 'no line');
}

// What erp_quote_save() is given: a sales document on the account, the
// quote's date and the date it runs to, and the lines in the editor's shape.
function test_workspace_erp_quote_change_save_data()
{
	$data = ws_change_erp_quote_data(array(
		'account_id'  => array(4),
		'valid_until' => '2026-11-30',
		'notes'       => 'Prices hold for a month.',
		'lines'       => array(array('product_id' => 12, 'description' => 'Red mug', 'quantity' => 2.0, 'unit' => 'C62', 'unit_price' => 1999, 'discount_rate' => 10.0, 'tax_rate' => 20.0)),
	), 9, '2026-10-25');

	pg_assert_same('sales', $data['direction'], 'a sales document');
	pg_assert_same(4, $data['account_id'], 'the account');
	pg_assert_same('2026-10-25', $data['issue_date'], 'dated today when no date is given');
	pg_assert_same('2026-11-30', $data['due_date'], 'the date it runs to is the due date the save reads');
	pg_assert_same('', $data['currency'], 'the currency is left to the writer');
	pg_assert_same('Prices hold for a month.', $data['notes'], 'the notes');
	pg_assert_same(9, $data['created_by'], 'made by the person who applies it');
	pg_assert_same(array(array(
		'product_id' => 12, 'description' => 'Red mug', 'quantity' => 2.0, 'unit_code' => 'C62', 'unit_price' => 1999,
		'offer_id' => 0, 'offer_discount_rate' => 0.0, 'discount_rate' => 10.0, 'tax_rate' => 20.0, 'tax2_rate' => 0.0,
		'withholding_code' => '', 'withholding_rate' => 0.0, 'source_line' => '',
	)), $data['lines'], 'the lines in the shape erp_manual_lines_build() reads');

	$data = ws_change_erp_quote_data(array('account_id' => array(4), 'issue_date' => '2026-11-02', 'currency' => 'EUR', 'lines' => array()), 9, '2026-10-25');
	pg_assert_same('2026-11-02', $data['issue_date'], 'the date given');
	pg_assert_same('', $data['due_date'], 'no date it runs to: the save counts ERP_QUOTE_VALID_DAYS');
	pg_assert_same('EUR', $data['currency'], 'the currency given');
}

// The writer saves through erp_quote_save(), the quote screens' and the
// API's writer, and never into erp_invoices.
function test_workspace_erp_quote_change_writer()
{
	$code = pg_test_function_source('ws_change_write_erp_quote');

	pg_assert_contains('erp_quote_save(', $code, 'saved by erp_quote_save()');
	pg_assert_contains('ws_change_erp_quote_data(', $code, 'from the shaped fields');
	pg_assert_false(strpos($code, 'erp_invoices') !== false, 'never written into erp_invoices');
}

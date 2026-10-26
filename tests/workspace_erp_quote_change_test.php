<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sales quote a Workspace change adds (kind erp_quote in
 * includes/workspace/changes.php): the lines of an ERP document as a change
 * carries them (ws_change_erp_lines(), pure), how the card writes a line,
 * the shape a stored quote's lines are read back in, and, from source, that
 * the quote is checked and written by the ERP's own functions.
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

// The limit the catalogue gives the field: at most 100 lines, a description
// of at most 255 characters.
function workspace_erp_quote_change_test_limit()
{
	return array(100, 255);
}

// The source of one function of includes/workspace/changes.php.
function workspace_erp_quote_change_test_source($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
	$start = strpos($source, 'function ' . $name . '(');

	pg_assert_true($start !== false, $name . ' is in changes.php');

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

// A product line may leave its description and its VAT rate to the product;
// a typed line keeps what it was given, figures sent as text included, and
// every line comes back with every field.
function test_workspace_erp_quote_lines_are_read_in_one_shape()
{
	$lines = ws_change_erp_lines(array(
		array('product_id' => 12, 'quantity' => 3, 'unit_price' => 15000),
		array('description' => '  Kurulum   hizmeti ', 'quantity' => '1.5', 'unit' => 'hur', 'unit_price' => '250000', 'discount_rate' => 10, 'tax_rate' => '20',
			'withholding_code' => '615', 'withholding_rate' => 50),
	), workspace_erp_quote_change_test_limit());

	pg_assert_true(is_array($lines), 'the lines are taken');
	pg_assert_same(ws_change_erp_line_fields(), array_keys($lines[0]), 'every field, in order');

	pg_assert_same(12, $lines[0]['product_id'], 'the product');
	pg_assert_same('', $lines[0]['description'], 'its description is left to the product');
	pg_assert_same(null, $lines[0]['tax_rate'], 'and its VAT rate');
	pg_assert_same(3.0, $lines[0]['quantity'], 'a quantity is a number');
	pg_assert_same(15000, $lines[0]['unit_price'], 'the price in minor units');
	pg_assert_same(0.0, $lines[0]['discount_rate'], 'no discount');

	pg_assert_same(0, $lines[1]['product_id'], 'a typed line');
	pg_assert_same('Kurulum hizmeti', $lines[1]['description'], 'its words, spaces folded');
	pg_assert_same(1.5, $lines[1]['quantity'], 'a quantity sent as text');
	pg_assert_same('HUR', $lines[1]['unit'], 'the unit in capitals');
	pg_assert_same(250000, $lines[1]['unit_price'], 'a price sent as text');
	pg_assert_same(20.0, $lines[1]['tax_rate'], 'a rate sent as text');
	pg_assert_same(10.0, $lines[1]['discount_rate'], 'its discount');
	pg_assert_same('615', $lines[1]['withholding_code'], 'its withholding code');

	$one = ws_change_erp_lines(array(array('description' => 'Danışmanlık', 'unit_price' => 100, 'tax_rate' => 0)), workspace_erp_quote_change_test_limit());

	pg_assert_same(1.0, $one[0]['quantity'], 'one when no quantity is given');
	pg_assert_same(0.0, $one[0]['tax_rate'], 'a zero rate is a rate');
}

// What is refused names the line and the part of it, in the API's words.
function test_workspace_erp_quote_lines_refusals_name_the_line()
{
	$limit = workspace_erp_quote_change_test_limit();
	$refused = function ($value) use ($limit) {
		$out = ws_change_erp_lines($value, $limit);

		return is_string($out) ? $out : '(taken)';
	};

	pg_assert_contains('lines', $refused('one line'), 'text is not a list');
	pg_assert_contains('lines', $refused(array('a' => array('unit_price' => 1))), 'an object is not a list');
	pg_assert_same(lang('Enter at least one line.'), $refused(array()), 'no line');
	pg_assert_contains('100', $refused(array_fill(0, 101, array('description' => 'x', 'unit_price' => 1, 'tax_rate' => 0))), 'more lines than the card takes');

	pg_assert_contains('lines[0]', $refused(array('x')), 'a line that is not an object');
	pg_assert_contains('lines[0]', $refused(array(array(1, 2))), 'a list is not an object');
	pg_assert_contains('price', $refused(array(array('price' => 1))), 'a field a line does not take is named');

	$typed = array('description' => 'Hizmet', 'tax_rate' => 20);

	pg_assert_contains('lines[1].unit_price', $refused(array($typed + array('unit_price' => 1), $typed)), 'the price is required');
	pg_assert_contains('lines[0].unit_price', $refused(array($typed + array('unit_price' => '19.99'))), 'a price with a decimal point');
	pg_assert_contains('lines[0].unit_price', $refused(array($typed + array('unit_price' => '19,99'))), 'a price with a decimal comma');
	pg_assert_contains('lines[0].unit_price', $refused(array($typed + array('unit_price' => '1e5'))), 'a price written with an exponent');
	pg_assert_contains('lines[0].unit_price', $refused(array($typed + array('unit_price' => -5))), 'a price below zero');
	pg_assert_contains('lines[0].unit_price', $refused(array($typed + array('unit_price' => true))), 'a price that is a switch');
	pg_assert_contains('lines[0].tax_rate', $refused(array(array('description' => 'Hizmet', 'unit_price' => 1, 'tax_rate' => 120))), 'a rate over 100');
	pg_assert_contains('lines[0].quantity', $refused(array($typed + array('unit_price' => 1, 'quantity' => 'iki'))), 'a quantity that is no number');
	pg_assert_contains('lines[0].discount_rate', $refused(array($typed + array('unit_price' => 1, 'discount_rate' => '10%'))), 'a rate with its sign');
	pg_assert_contains('lines[0].description', $refused(array(array('description' => str_repeat('a', 256), 'unit_price' => 1, 'tax_rate' => 0))), 'a description longer than its column');
	pg_assert_contains('lines[0].description', $refused(array(array('description' => array('x'), 'unit_price' => 1, 'tax_rate' => 0))), 'a description that is no text');
	pg_assert_contains('lines[0].product_id', $refused(array(array('product_id' => 'abc', 'unit_price' => 1))), 'a product that is no id');
	pg_assert_contains('lines[0].product_id', $refused(array(array('product_id' => 0.5, 'unit_price' => 1))), 'a product id with decimals');

	// Without a product, the line names itself.
	pg_assert_contains('lines[0].description', $refused(array(array('unit_price' => 1, 'tax_rate' => 20))), 'a typed line needs its description');
	pg_assert_contains('lines[0].tax_rate', $refused(array(array('description' => 'Hizmet', 'unit_price' => 1))), 'and its VAT rate');
}

// The value door takes the lines or nothing, and the refusal sentence is the
// validator's, naming the field as it was sent.
function test_workspace_erp_quote_lines_through_the_value_door()
{
	$field = array('erp_lines', workspace_erp_quote_change_test_limit(), 'Lines', 'line_data');
	$good = array(array('description' => 'Hizmet', 'unit_price' => 1000, 'tax_rate' => 20));
	$bad = array(array('description' => 'Hizmet', 'unit_price' => '10.00', 'tax_rate' => 20));

	pg_assert_true(is_array(ws_change_value($field, $good)), 'taken');
	pg_assert_same(null, ws_change_value($field, $bad), 'refused');
	pg_assert_same('', ws_change_value_refusal($field, $good, 'lines'), 'no refusal for good lines');
	pg_assert_contains('lines[0].unit_price', ws_change_value_refusal($field, $bad, 'lines'), 'the refusal names the line');
}

// A quote's stored lines (erp_quotes.line_data, built by the ERP) are read
// back in the shape a change carries them.
function test_workspace_erp_quote_lines_read_back_from_a_quote()
{
	$built = array(array(
		'product_id' => 4, 'description' => 'Masa', 'quantity' => 2, 'unit_code' => 'C62', 'unit_price' => 120000,
		'offer_id' => 0, 'offer_discount_rate' => 0, 'discount_rate' => 5, 'discount_amount' => 12000,
		'tax_rate' => 20, 'tax_total' => 45600, 'tax2_rate' => 0, 'withholding_code' => '', 'withholding_rate' => 0, 'line_total' => 240000,
	));

	$lines = ws_change_erp_lines_from_built($built);

	pg_assert_same(ws_change_erp_line_fields(), array_keys($lines[0]), 'the fields a change takes, and only those');
	pg_assert_same('C62', $lines[0]['unit'], 'unit_code is the unit');
	pg_assert_same(5.0, $lines[0]['discount_rate'], 'the typed discount');
	pg_assert_same(array(), ws_change_erp_lines_from_built(null), 'no lines stored');
	pg_assert_same(array(), ws_change_erp_lines_from_built(array('x')), 'a line that is not one is left out');

	// What a record holds: the account as the list the field takes.
	pg_assert_same(array(7), ws_change_current_value(array('ids', 'erp_account', 'Current account', 'account_id'), array('account_id' => '7')), 'the account');
	pg_assert_same(array(), ws_change_current_value(array('ids', 'erp_account', 'Current account', 'account_id'), array('account_id' => '0')), 'no account');
	pg_assert_same('Masa', ws_change_current_value(array('erp_lines', array(100, 255), 'Lines', 'line_data'), array('line_data' => json_encode($built)))[0]['description'], 'the lines');
}

// A line in words: quantity and description, the unit price, the VAT and
// only the extras the line has; the piece unit (C62) is not written.
function test_workspace_erp_quote_line_text()
{
	$line = array('product_id' => 0, 'description' => 'Masa', 'quantity' => 2.0, 'unit' => 'C62', 'unit_price' => 120000,
		'discount_rate' => 0.0, 'tax_rate' => 20.0, 'tax2_rate' => 0.0, 'withholding_code' => '', 'withholding_rate' => 0.0);

	$text = ws_change_erp_line_text($line);

	pg_assert_contains('2 × Masa', $text, 'quantity and description');
	pg_assert_contains(lang('Unit price'), $text, 'the unit price');
	pg_assert_contains(lang('VAT'), $text, 'the VAT');
	pg_assert_false(strpos($text, 'C62') !== false, 'the piece unit is not written');
	pg_assert_false(strpos($text, lang('Discount')) !== false, 'no discount, none written');

	$extras = ws_change_erp_line_text(array('unit' => 'KGM', 'discount_rate' => 10, 'withholding_code' => '615') + $line);

	pg_assert_contains('2 KGM × Masa', $extras, 'another unit is written');
	pg_assert_contains(lang('Discount'), $extras, 'the discount');
	pg_assert_contains(lang('Withholding') . ' 615', $extras, 'the withholding code');
}

// The catalogue: a create-only kind of the ERP right, tagged as a sales
// quote, its lines a field of their own kind on the quote's line_data.
function test_workspace_erp_quote_kind_in_the_catalogue()
{
	$types = pg_test_ws_change_types();

	pg_assert_true(isset($types['erp_quote']), 'erp_quote is a kind of change');
	pg_assert_same(array('create'), $types['erp_quote']['actions'], 'only ever added');
	pg_assert_same('erp', $types['erp_quote']['right'], 'with the ERP right');
	pg_assert_same('erp_quote', $types['erp_quote']['tag'], 'tagged as a sales quote');
	pg_assert_same('erp_quotes', $types['erp_quote']['table'], 'in its own table, never erp_invoices');
	pg_assert_same(array('erp_lines', array(100, 255)), array_slice($types['erp_quote']['fields']['lines'], 0, 2), 'the lines field');
	pg_assert_same('line_data', $types['erp_quote']['fields']['lines'][3], 'read from line_data');
	pg_assert_same(array('ids', 'erp_account'), array_slice($types['erp_quote']['fields']['account_id'], 0, 2), 'one current account');
	pg_assert_contains('erp_quote', ws_change_erp_types(), 'the read-only accountant does not add one');
	pg_assert_contains('erp_quote', workspace_erp_quote_change_test_skipped(), 'no scheduled action adds one');
}

// The kinds a scheduled action leaves alone, read from scheduled.php.
function workspace_erp_quote_change_test_skipped()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

	pg_assert_true((bool) preg_match('/function ws_scheduled_change_skipped\(\)\s*\{\s*return array\(([^)]*)\);/', $source, $match), 'ws_scheduled_change_skipped() is one list');

	preg_match_all("/'([a-z_]+)'/", $match[1] ?? '', $kinds);

	return $kinds[1];
}

// Checked and written by the ERP: the refusal asks the builders the save
// asks, the writer is the quote editor's save and writes no row itself, and
// both the proposal and the apply ask the refusal (ws_change_check()).
function test_workspace_erp_quote_is_checked_and_written_by_the_erp()
{
	$refusal = workspace_erp_quote_change_test_source('ws_change_erp_quote_refusal');

	pg_assert_contains('erp_manual_header_build(', $refusal, 'the header as the save builds it');
	pg_assert_contains('erp_manual_lines_build(', $refusal, 'the lines as the save builds them');
	pg_assert_contains("\$account['status'] !== 'active'", $refusal, 'an active account');
	pg_assert_contains("\$account['kind'] === 'supplier'", $refusal, 'not a supplier alone');
	pg_assert_contains('recycled', $refusal, 'no product from the Recycle Bin');

	$writer = workspace_erp_quote_change_test_source('ws_change_write_erp_quote');

	pg_assert_contains('erp_quote_save(', $writer, 'the quote editor\'s save');
	pg_assert_false(stripos($writer, 'INSERT') !== false, 'no row written by hand');
	pg_assert_false(strpos($writer, 'erp_invoice') !== false, 'never an invoice');

	pg_assert_contains("case 'erp_quote':\n            return ws_change_erp_quote_refusal(\$values);", workspace_erp_quote_change_test_source('ws_change_check'), 'asked at proposal and at apply');
	pg_assert_contains('ws_change_erp_quote_fill(', workspace_erp_quote_change_test_source('ws_changes_input'), 'laid out when proposed');
}

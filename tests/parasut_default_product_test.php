<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Paraşüt product an invoice line is written against
 * (includes/erp/edoc/parasut.php) and the currency of the product card a
 * Pinegrap product gets (includes/fn/parasut.php). The default
 * product/service takes only lines without a product of their own; a line
 * whose product card Paraşüt did not open is refused in Paraşüt's words. A
 * card's sales price goes in the store's base currency, in Paraşüt's code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

/**
 * The source of one function, from its declaration to the next one.
 *
 * @param string $file      Path under the software directory
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_default_product_test_source($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

/**
 * A products row as parasut_sync_product() reads it.
 *
 * @param array $over
 * @return array
 */
function parasut_default_product_test_product($over = array())
{
	return array_merge(array(
		'name' => 'MUG-1',
		'short_description' => 'Mug',
		'price' => '1050',
		'inventory' => '1',
		'inventory_quantity' => '3',
		'tax_rate' => '20',
	), $over);
}

// A line of a catalogue product whose card Paraşüt did not open (a 422, a
// timeout, a 5xx, a second 429) is refused naming the line, in Paraşüt's
// words, before the invoice is written: not put on the default
// product/service, with one set or none.
function test_parasut_default_product_a_card_not_opened_is_refused()
{
	$line = array('product_id' => 543, 'description' => 'Deri Kalem Kılıfı');
	$refused = array('success' => false, 'parasut_product_id' => null, 'track_stock' => true, 'error' => 'Ürün adı boş olamaz.');

	foreach (array('9001', '') as $default) {
		$product = erp_edoc_parasut_line_product_pick($line, $refused, $default);

		pg_assert_same('', $product['id'], 'no product with default "' . $default . '"');
		pg_assert_contains('"Deri Kalem Kılıfı"', $product['error']);
		pg_assert_contains('Ürün adı boş olamaz', $product['error']);
		pg_assert_false(strpos($product['error'], 'boş olamaz..') !== false, 'one period');
		pg_assert_false($product['error'] === erp_edoc_parasut_no_product_text($line), 'not the default product advice');
		pg_assert_false(strpos($product['error'], 'Enter a default product/service id') !== false, 'no default advice');
	}

	$timeout = array('success' => false, 'parasut_product_id' => null, 'track_stock' => false, 'error' => 'Operation timed out after 30001 milliseconds with 0 bytes received');
	$product = erp_edoc_parasut_line_product_pick($line, $timeout, '9001');

	pg_assert_same('', $product['id']);
	pg_assert_contains('Operation timed out after 30001 milliseconds', $product['error']);

	// A create that came back without an id is no card either.
	$product = erp_edoc_parasut_line_product_pick($line, array('success' => true, 'parasut_product_id' => '', 'error' => ''), '9001');

	pg_assert_same('', $product['id']);
	pg_assert_contains('"Deri Kalem Kılıfı"', $product['error']);
}

// A products read that failed is the database's error, not Paraşüt's: the
// line is refused in words of its own, with the database's words, and
// never sent to delete a card on Paraşüt's product list, which no request
// opened. Not put on the default either, with one set or none.
function test_parasut_default_product_a_failed_read_is_a_database_error()
{
	$line = array('product_id' => 543, 'description' => 'Deri Kalem Kılıfı');
	$said = lang(array('string' => 'Product {var:1} could not be read from the database: {var:2}', 'vars' => array(543, "Unknown column 'parasut_product_id' in 'field list'")));
	$unread = array('success' => false, 'parasut_product_id' => null, 'track_stock' => false, 'error' => $said, 'read_failed' => true);

	foreach (array('9001', '') as $default) {
		$product = erp_edoc_parasut_line_product_pick($line, $unread, $default);

		pg_assert_same(array('id' => '', 'error' => lang(array(
			'string' => 'Line "{var:1}" could not be written to Paraşüt because of a database error: {var:2}. The invoice was not written; send again once the database error is put right.',
			'vars' => array('Deri Kalem Kılıfı', $said),
		))), $product, 'default "' . $default . '"');
		pg_assert_false($product['error'] === erp_edoc_parasut_line_product_pick($line, array_diff_key($unread, array('read_failed' => true)), $default)['error'], 'not the card-not-opened text');
	}

	// A line with no description is named by its product.
	$product = erp_edoc_parasut_line_product_pick(array('product_id' => 543, 'description' => ' '), $unread, '9001');

	pg_assert_contains('"#543"', $product['error']);
}

// A line without a product of its own goes on the default product/service:
// no product (shipping, a charge, free text, a rounding line), or a product
// whose catalogue row is gone. With no default set it is refused with the
// advice to enter one.
function test_parasut_default_product_takes_only_lines_without_their_own()
{
	$free = array('product_id' => 0, 'description' => 'Kargo');
	$deleted = array('product_id' => 543, 'description' => 'Deri Kalem Kılıfı');
	$gone = array('success' => false, 'parasut_product_id' => null, 'track_stock' => false, 'error' => 'Product not found: 543', 'missing' => true);

	pg_assert_same(array('id' => '9001', 'error' => ''), erp_edoc_parasut_line_product_pick($free, null, '9001'));
	pg_assert_same(array('id' => '9001', 'error' => ''), erp_edoc_parasut_line_product_pick($free, null, ' 9001 '), 'the setting trimmed');
	pg_assert_same(array('id' => '', 'error' => erp_edoc_parasut_no_product_text($free)), erp_edoc_parasut_line_product_pick($free, null, ''));

	pg_assert_same(array('id' => '9001', 'error' => ''), erp_edoc_parasut_line_product_pick($deleted, $gone, '9001'));
	pg_assert_same(array('id' => '', 'error' => erp_edoc_parasut_no_product_text($deleted)), erp_edoc_parasut_line_product_pick($deleted, $gone, ''));
}

// The product's own card wins over the default product/service.
function test_parasut_default_product_own_card_first()
{
	$line = array('product_id' => 543, 'description' => 'Deri Kalem Kılıfı');
	$opened = array('success' => true, 'parasut_product_id' => '1079305293', 'track_stock' => true, 'error' => '');

	pg_assert_same(array('id' => '1079305293', 'error' => ''), erp_edoc_parasut_line_product_pick($line, $opened, '9001'));
	pg_assert_same(array('id' => '1079305293', 'error' => ''), erp_edoc_parasut_line_product_pick($line, $opened, ''));
}

// parasut_sync_product() says when the catalogue row is gone, and only then;
// the line product hands its answer to the choice and keeps the token's
// error, so no request answer is replaced by the default.
function test_parasut_default_product_wiring()
{
	$sync = parasut_default_product_test_source('includes/fn/parasut.php', 'parasut_sync_product');

	pg_assert_contains("'error' => lang(array('string' => 'Product not found: {var:1}', 'vars' => array(\$product_id))), 'missing' => true];", $sync);
	pg_assert_same(1, substr_count($sync, "'missing' => true"), 'only the missing row');

	// A read that fails (a column the upgrade has not added yet, say) tells
	// nothing about the row: it is answered before mysqli_fetch_assoc(),
	// which PHP 8 refuses false with a TypeError, and never as a missing row,
	// so its line is refused rather than put on the default.
	$failed = strpos($sync, 'if ($result === false) {');

	pg_assert_true($failed !== false, 'a failed read is answered');
	pg_assert_true((int) $failed < (int) strpos($sync, 'mysqli_fetch_assoc($result)'), 'before the row is fetched');

	$branch = substr($sync, (int) $failed, (int) strpos($sync, "\n    }", (int) $failed) - (int) $failed);

	pg_assert_contains("'Product {var:1} could not be read from the database: {var:2}'", $branch);
	pg_assert_contains("'success' => false", $branch);
	pg_assert_contains("'read_failed' => true", $branch, 'said to be the database\'s');
	pg_assert_false(strpos($branch, "'missing' =>") !== false, 'not a missing row');
	pg_assert_same(1, substr_count($sync, "'read_failed' => true"), 'only the failed read');

	$line = parasut_default_product_test_source('includes/erp/edoc/parasut.php', 'erp_edoc_parasut_line_product');

	pg_assert_contains("return array('id' => '', 'error' => (string) \$token['error']);", $line);
	pg_assert_contains("\$sync = parasut_sync_product(\$product_id, (string) \$token['token'], \$taken_out);", $line);
	pg_assert_contains('return erp_edoc_parasut_line_product_pick($line, $sync, ', $line);
	pg_assert_false(strpos($line, 'return array(\'id\' => $default') !== false, 'no default returned past the choice');
}

// Paraşüt's code for the store's base currency: TRL for the lira, USD, EUR
// and GBP as they are, nothing for a currency it does not take (its
// definition lists only those four).
function test_parasut_default_product_currency_codes()
{
	pg_assert_same('TRL', parasut_currency_code('TRY'));
	pg_assert_same('TRL', parasut_currency_code('try'));
	pg_assert_same('TRL', parasut_currency_code('TL'));
	pg_assert_same('TRL', parasut_currency_code('TRL'));
	pg_assert_same('TRL', parasut_currency_code(''));
	pg_assert_same('EUR', parasut_currency_code('EUR'));
	pg_assert_same('USD', parasut_currency_code(' usd '));
	pg_assert_same('GBP', parasut_currency_code('GBP'));
	pg_assert_same('', parasut_currency_code('JPY'));
	pg_assert_same('', parasut_currency_code('CHF'));

	// The invoice keeps its own rule: the lira as TRL, the rest passed on.
	pg_assert_same('TRL', erp_edoc_parasut_currency('tl'));
	pg_assert_same('EUR', erp_edoc_parasut_currency('eur'));
	pg_assert_same('JPY', erp_edoc_parasut_currency('JPY'));
}

// The card's sales price is labelled with the store's base currency, the
// currency products.price is held in.
function test_parasut_default_product_card_price_in_the_base_currency()
{
	$product = parasut_default_product_test_product();

	$euro = parasut_product_attributes($product, 0, 'EUR');
	pg_assert_same(10.5, $euro['list_price']);
	pg_assert_same('EUR', $euro['currency']);

	pg_assert_same('TRL', parasut_product_attributes($product, 0, 'TRY')['currency']);
	pg_assert_same('USD', parasut_product_attributes($product, 2, 'usd')['currency']);
	pg_assert_same('GBP', parasut_product_attributes($product, 0, 'GBP')['currency']);

	// Under the test runner the base currency is not defined, which reads as
	// the lira (erp_edoc_parasut_currency('') does the same).
	if (!defined('BASE_CURRENCY_CODE')) {
		pg_assert_same('TRL', parasut_product_attributes($product)['currency']);
	}
}

// A base currency Paraşüt does not take: the card opens without a sales
// price rather than with one in the wrong currency; the invoice lines carry
// their own prices.
function test_parasut_default_product_card_without_a_currency_parasut_takes()
{
	$attributes = parasut_product_attributes(parasut_default_product_test_product(), 2, 'JPY');

	pg_assert_same(array(
		'name' => 'Mug',
		'vat_rate' => 20.0,
		'inventory_tracking' => true,
		'initial_stock_count' => 5,
	), $attributes);
}

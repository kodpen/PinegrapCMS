<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/supplier_feed_screen.php, the pure parts of the
 * Supplier feeds screens: the form's values and the saved secret, the
 * request built from the sign-in way, the host shown, the sample of the
 * feed's fields, the mapping form, the state pill and the test pull's
 * classification. Also the record path candidates of
 * includes/fn/supplier_feed.php the mapping's select offers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_screen.php');

function supplier_feed_screen_post($values = array())
{
	return array_merge(array(
		'name'           => 'Acme',
		'address'        => 'https://feeds.example.com/list.xml',
		'format'         => '',
		'interval_hours' => '3',
		'auth'           => 'none',
	), $values);
}

function test_supplier_feed_record_paths_lists_candidates()
{
	$dir = dirname(__FILE__) . '/fixtures/supplier_feed/';

	$xml = pg_supplier_feed_record_paths($dir . 'nested.xml', 'xml');
	pg_assert_same('Root/Urunler/Urun', $xml[0], 'xml: the record path first');
	pg_assert_contains('Root/Urunler/Urun/Resimler', $xml, 'xml: a deeper repeated element is offered too');

	$json = pg_supplier_feed_record_paths($dir . 'wrapped.json', 'json');
	pg_assert_same('data/products', $json[0], 'json: the list of objects first');
	pg_assert_same(array(''), pg_supplier_feed_record_paths($dir . 'bom_semicolon.csv', 'csv'), 'csv: the one empty path');
	pg_assert_same(array(), pg_supplier_feed_record_paths($dir . 'missing.xml', 'xml'), 'a missing file: none');
}

function test_supplier_feed_screen_input_values()
{
	$input = pg_supplier_feed_input(supplier_feed_screen_post(array('format' => 'yaml', 'interval_hours' => '5', 'currency' => 'usd', 'vat_included' => '1')), array());

	pg_assert_same(array(), $input['errors'], 'a good feed has no error');
	pg_assert_same('', $input['feed']['format'], 'an unknown format is automatic');
	pg_assert_same(1, $input['feed']['interval_hours'], 'an interval not offered is every hour');
	pg_assert_same('USD', $input['feed']['currency'], 'the currency in capitals');
	pg_assert_same(1, $input['feed']['vat_included'], 'prices include VAT');
	pg_assert_same('feeds.example.com', $input['feed']['host'], 'only the host is kept in the clear');
	pg_assert_same(array('address' => 'https://feeds.example.com/list.xml', 'auth' => 'none'), $input['secret'], 'the secret holds the address');

	$input = pg_supplier_feed_input(supplier_feed_screen_post(array('name' => ' ', 'address' => 'ftp://x.example.com/a')), array());
	pg_assert_same(2, count($input['errors']), 'no name and an ftp address: two errors');
}

function test_supplier_feed_screen_input_keeps_saved_secrets()
{
	$stored = array('address' => 'https://feeds.example.com/list.xml?token=1', 'auth' => 'basic', 'user' => 'shop', 'password' => 'secret');

	$input = pg_supplier_feed_input(supplier_feed_screen_post(array('address' => '', 'auth' => 'basic', 'user' => '', 'password' => '')), $stored);
	pg_assert_same(array(), $input['errors'], 'empty boxes keep what was saved');
	pg_assert_same($stored, $input['secret'], 'the saved address, user and password stay');

	$input = pg_supplier_feed_input(supplier_feed_screen_post(array('address' => '', 'auth' => 'header', 'header_name' => 'X-Api-Key', 'key' => 'k1')), $stored);
	pg_assert_same(array('address' => $stored['address'], 'auth' => 'header', 'header_name' => 'X-Api-Key', 'key' => 'k1'), $input['secret'], 'another way drops the old way\'s fields');

	$input = pg_supplier_feed_input(supplier_feed_screen_post(array('auth' => 'header', 'header_name' => 'Bad Name', 'key' => '')), array());
	pg_assert_same(2, count($input['errors']), 'a header name with a space and no key');

	$input = pg_supplier_feed_input(supplier_feed_screen_post(array('address' => '', 'auth' => 'none')), array());
	pg_assert_same(1, count($input['errors']), 'a new feed needs its address');
}

function test_supplier_feed_screen_input_http_rule()
{
	$http = array('address' => 'http://feeds.example.com/list.csv');

	$input = pg_supplier_feed_input(supplier_feed_screen_post($http), array());
	pg_assert_same(1, count($input['errors']), 'http without the tick is refused');

	$input = pg_supplier_feed_input(supplier_feed_screen_post($http + array('allow_http' => '1', 'auth' => 'basic', 'user' => 'a', 'password' => 'b')), array());
	pg_assert_same(1, count($input['errors']), 'http takes no user and password');

	$input = pg_supplier_feed_input(supplier_feed_screen_post($http + array('allow_http' => '1', 'auth' => 'query', 'key_param' => 'apikey', 'key' => 'k')), array());
	pg_assert_same(array(), $input['errors'], 'http with a key in the address');
	pg_assert_same(1, $input['feed']['allow_http'], 'the tick is kept');
}

function test_supplier_feed_screen_request()
{
	$request = pg_supplier_feed_request(array('address' => 'https://a.example.com/f.xml', 'auth' => 'basic', 'user' => 'shop', 'password' => 'p:w'));
	pg_assert_same('https://a.example.com/f.xml', $request['url'], 'basic: the address as it is');
	pg_assert_same(array('Authorization: Basic ' . base64_encode('shop:p:w')), $request['headers'], 'basic: the header');

	$request = pg_supplier_feed_request(array('address' => 'https://a.example.com/f.xml', 'auth' => 'header', 'header_name' => 'X-Key', 'key' => 'abc'));
	pg_assert_same(array('X-Key: abc'), $request['headers'], 'key header');

	$request = pg_supplier_feed_request(array('address' => 'https://a.example.com/f.xml?lang=tr#top', 'auth' => 'query', 'key_param' => 'api key', 'key' => 'a&b'));
	pg_assert_same('https://a.example.com/f.xml?lang=tr&api%20key=a%26b', $request['url'], 'key in the address, encoded, the fragment dropped');
	pg_assert_same(array(), $request['headers'], 'no header');
}

function test_supplier_feed_screen_host()
{
	pg_assert_same('feeds.example.com', pg_supplier_feed_host('https://user:pw@Feeds.Example.com:8443/a?b=c'), 'the host alone, lower case');
	pg_assert_same('', pg_supplier_feed_host('not an address'), 'nothing for text');
}

function test_supplier_feed_screen_sample()
{
	$sample = pg_supplier_feed_sample(array(
		array('code' => 'A1', 'name' => '', 'desc' => str_repeat('ş', 100)),
		array('code' => 'A2', 'name' => 'Kettle', 'price' => '10,50'),
	));

	pg_assert_same(array('code', 'name', 'desc', 'price'), array_keys($sample), 'keys in the order met');
	pg_assert_same('A1', $sample['code'], 'the first value');
	pg_assert_same('Kettle', $sample['name'], 'the first value that is not empty');
	pg_assert_same(81, mb_strlen($sample['desc'], 'UTF-8'), 'a long value cut to 80 letters and an ellipsis');
}

function test_supplier_feed_screen_mapping_input()
{
	$keys = array('StokKodu' => 'A-100', 'AlisFiyati' => '10', 'Stok' => '3', 'Barkod' => '869');

	$mapping = pg_supplier_feed_mapping_input(array('supplier_key' => 'StokKodu', 'buy_price' => 'AlisFiyati', 'stock' => 'Stok', 'barcode' => 'Nope'), $keys);
	pg_assert_same(array(), $mapping['errors'], 'the required three are chosen');
	pg_assert_same('', $mapping['fields']['barcode'], 'a key the feed has not is dropped');
	pg_assert_same('StokKodu', $mapping['fields']['supplier_key'], 'kept');

	$mapping = pg_supplier_feed_mapping_input(array('supplier_key' => 'StokKodu'), $keys);
	pg_assert_same(2, count($mapping['errors']), 'buy price and stock are required');
}

function test_supplier_feed_screen_state()
{
	pg_assert_same('gray', pg_supplier_feed_state(array('status' => 0, 'pause_reason' => ''), null)['tone'], 'never pulled');
	pg_assert_same('teal', pg_supplier_feed_state(array('status' => 1, 'pause_reason' => ''), array('status' => 1, 'detail' => ''))['tone'], 'the last pull was done');

	$failed = pg_supplier_feed_state(array('status' => 1, 'pause_reason' => ''), array('status' => 3, 'detail' => 'The supplier answered 500 and the list could not be read at all today'));
	pg_assert_same('rose', $failed['tone'], 'a failed pull');
	pg_assert_same('The supplier answered 500 and the list could…', $failed['note'], 'the reason\'s first words');

	pg_assert_same('amber', pg_supplier_feed_state(array('status' => 2, 'pause_reason' => 'Half dropped'), array('status' => 2, 'detail' => ''))['tone'], 'paused by a brake');
}

function test_supplier_feed_screen_test_diff()
{
	$fields = array('supplier_key' => 'code', 'barcode' => 'ean', 'stock' => 'qty', 'name' => 'title');
	$products = array(
		1 => array('id' => 1, 'name' => 'A-1', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 5),
		2 => array('id' => 2, 'name' => 'B-2', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 4),
		3 => array('id' => 3, 'name' => 'C-3', 'enabled' => 0, 'inventory' => 0, 'inventory_quantity' => 0),
		4 => array('id' => 4, 'name' => 'D-4', 'enabled' => 1, 'inventory' => 0, 'inventory_quantity' => 0),
		5 => array('id' => 5, 'name' => 'E-5', 'enabled' => 0, 'inventory' => 0, 'inventory_quantity' => 0),
		6 => array('id' => 6, 'name' => 'F-6', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 2),
	);
	$records = array(
		array('code' => 'a-1', 'qty' => '5', 'title' => 'Same'),
		array('code' => 'X', 'ean' => '869', 'qty' => '7', 'title' => 'By barcode'),
		array('code' => 'C-3', 'qty' => '2', 'title' => 'Closed by the feed'),
		array('code' => 'D-4', 'qty' => '0', 'title' => 'Out'),
		array('code' => 'E-5', 'qty' => '9', 'title' => 'A person\'s off'),
		array('code' => 'NEW', 'qty' => '1', 'title' => 'New one'),
		array('code' => '', 'qty' => '1', 'title' => 'No code'),
	);
	$by_code = array('a-1' => 1, 'c-3' => 3, 'd-4' => 4, 'e-5' => 5);
	$by_barcode = array('869' => 2);
	$items = array(
		array('product_id' => 3, 'closed_by_feed' => 1),
		array('product_id' => 6, 'closed_by_feed' => 0),
	);

	$diff = pg_supplier_feed_test_diff($records, $fields, $by_code, $by_barcode, $products, $items);

	pg_assert_same(1, $diff['added']['count'], 'one new record');
	pg_assert_same('NEW', $diff['added']['rows'][0]['code'], 'the new record\'s code');
	pg_assert_same(1, $diff['changed']['count'], 'the stock read by barcode changes');
	pg_assert_same('B-2', $diff['changed']['rows'][0]['code'], 'shown under the product\'s code');
	pg_assert_same(2, $diff['closed']['count'], 'stock 0 and a followed product missing from the pull');
	pg_assert_same(1, $diff['reopened']['count'], 'only what the feed closed reopens');
	pg_assert_same(7, $diff['records'], 'every record counted');
	pg_assert_same(1, $diff['skipped'], 'a record with no code is skipped');
}

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

function test_supplier_feed_screen_rules_input()
{
	$input = pg_supplier_feed_rules_input(array('rule_margin' => '25,5', 'rule_fixed' => '12,50', 'rule_rounding' => '90', 'rule_floor' => '%10'));
	pg_assert_same(array(), $input['errors'], 'a good rule has no error');
	pg_assert_same(array('margin' => 25.5, 'fixed' => 1250, 'rounding' => '90', 'floor_margin' => 10), $input['rules'], 'margin, fixed in minor units, rounding, floor');

	$input = pg_supplier_feed_rules_input(array('rule_margin' => '', 'rule_rounding' => 'odd'));
	pg_assert_false(isset($input['rules']['margin']), 'an empty margin saves no margin, so prices do not follow');
	pg_assert_same('none', $input['rules']['rounding'], 'an unknown rounding is none');

	$input = pg_supplier_feed_rules_input(array('rule_margin' => 'x', 'rule_fixed' => '-3', 'rule_floor' => '2000'));
	pg_assert_same(3, count($input['errors']), 'a bad margin, a negative fixed amount and a floor over 1000');

	$input = pg_supplier_feed_rules_input(array(
		'rule_margin'     => '20',
		'rule_kind'       => array('category', 'brand', 'category', 'brand', 'category', 'category'),
		'rule_name'       => array('Mutfak', 'Kodpen', '', '', 'MUTFAK', 'Bahçe'),
		'rule_row_margin' => array('30', '', '', '15', '35', ''),
		'rule_row_fixed'  => array('', '5', '', '', '', ''),
	));
	pg_assert_same(array(
		array('category' => 'Mutfak', 'margin' => 30),
		array('brand' => 'Kodpen', 'fixed' => 500),
	), $input['rules']['specific'], 'rows keep only what they set; an empty row is dropped');
	pg_assert_same(3, count($input['errors']), 'a row with no name, the same category twice, a row with neither margin nor fixed');
}

function test_supplier_feed_screen_rules_decode()
{
	$rules = pg_supplier_feed_rules_decode(json_encode(array('margin' => 20, 'fixed' => 150, 'rounding' => '99', 'floor_margin' => 5, 'specific' => array(array('brand' => 'Kodpen', 'margin' => 25)))));
	pg_assert_same(20, $rules['margin'], 'margin');
	pg_assert_same(150, $rules['fixed'], 'fixed');
	pg_assert_same('99', $rules['rounding'], 'rounding');
	pg_assert_same(5, $rules['floor_margin'], 'floor');
	pg_assert_same(array(array('kind' => 'brand', 'name' => 'Kodpen', 'margin' => 25, 'fixed' => null)), $rules['specific'], 'a brand row');

	$none = pg_supplier_feed_rules_decode(null);
	pg_assert_same('', $none['margin'], 'no rule: an empty margin');
	pg_assert_same('none', $none['rounding'], 'no rule: no rounding');
	pg_assert_same(array(), $none['specific'], 'no rule: no rows');
}

function test_supplier_feed_screen_test_diff_prices()
{
	$fields = array('supplier_key' => 'code', 'stock' => 'qty', 'buy_price' => 'cost', 'category' => 'cat');
	$products = array(
		1 => array('id' => 1, 'name' => 'A-1', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 5, 'price' => 11000, 'tax_rate' => '20'),
		2 => array('id' => 2, 'name' => 'B-2', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 5, 'price' => 100, 'tax_rate' => ''),
		3 => array('id' => 3, 'name' => 'C-3', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 5, 'price' => 100, 'tax_rate' => ''),
		4 => array('id' => 4, 'name' => 'D-4', 'enabled' => 1, 'inventory' => 1, 'inventory_quantity' => 5, 'price' => 100, 'tax_rate' => ''),
	);
	$records = array(
		array('code' => 'A-1', 'qty' => '5', 'cost' => '120,00', 'cat' => ''),
		array('code' => 'B-2', 'qty' => '5', 'cost' => '100,00', 'cat' => 'Mutfak'),
		array('code' => 'C-3', 'qty' => '5', 'cost' => '100,00', 'cat' => ''),
		array('code' => 'D-4', 'qty' => '5', 'cost' => '2000,00', 'cat' => ''),
		array('code' => 'NEW', 'qty' => '1', 'cost' => '50,00', 'cat' => ''),
	);
	$by_code = array('a-1' => 1, 'b-2' => 2, 'c-3' => 3, 'd-4' => 4);
	$items = array(
		array('product_id' => 1, 'closed_by_feed' => 0, 'follow_price' => 1, 'buy_price' => 10000),
		array('product_id' => 2, 'closed_by_feed' => 0, 'follow_price' => 1, 'buy_price' => 10000),
		array('product_id' => 3, 'closed_by_feed' => 0, 'follow_price' => 0, 'buy_price' => 10000),
		array('product_id' => 4, 'closed_by_feed' => 0, 'follow_price' => 1, 'buy_price' => 10000),
	);
	$pricing = array(
		'rules'        => array('margin' => 0, 'rounding' => 'none', 'floor_margin' => 10, 'specific' => array(array('category' => 'Mutfak', 'margin' => 50))),
		'rate'         => 1,
		'vat_included' => true,
	);

	$diff = pg_supplier_feed_test_diff($records, $fields, $by_code, array(), $products, $items, $pricing);

	pg_assert_same(1, $diff['changed']['count'], 'only the product whose selling price moves and follows the feed (A-1 at 120,00 with VAT stays 110,00; C-3 does not follow; D-4 is held)');
	$row = $diff['changed']['rows'][0];
	pg_assert_same('B-2', $row['code'], 'the category rule gives 150,00 against 1,00');
	pg_assert_same(10000, $row['buy'], 'the buy price on the row');
	pg_assert_same(15000, $row['price'], 'the selling price the category rule gives');
	pg_assert_false($row['floor'], 'over the floor: no pill');

	pg_assert_same(11000, $diff['added']['rows'][0]['price'] * 2, 'a new record: buy 50,00 lifted to the floor 55,00');
	pg_assert_true($diff['added']['rows'][0]['floor'], 'the floor lifted it');

	$none = pg_supplier_feed_test_diff($records, $fields, $by_code, array(), $products, $items);
	pg_assert_same(0, $none['changed']['count'], 'no pricing: stock alone, and it did not move');
	pg_assert_same(null, $none['added']['rows'][0]['buy'], 'no pricing: no buy price on the row');
}

function test_supplier_feed_screen_rule_card_counts()
{
	$html = pg_supplier_feed_rule_fields_html(pg_supplier_feed_rules_decode(json_encode(array('margin' => 20, 'rounding' => '90', 'specific' => array(array('category' => 'Mutfak', 'margin' => 30))))), 'USD');
	$card = preg_replace('/<template.*<\/template>/s', '', $html);

	pg_assert_same(3, substr_count($card, 'class="pg-input" id="sf_rule_'), 'margin, fixed amount and minimum margin');
	pg_assert_same(4, substr_count($card, 'data-pg-sf-round='), 'rounding as four segments');
	pg_assert_same(1, substr_count($card, 'data-pg-sf-round="90" aria-pressed="true"'), 'the saved rounding is pressed');
	pg_assert_contains('Buy price x (1 + margin) + fixed amount; never under buy price plus the minimum margin.', $card, 'the formula line');
	pg_assert_contains('Rules by category or brand', $card, 'the rules heading');
	pg_assert_same(1, substr_count($card, 'data-pg-sf-rule-row'), 'one saved rule row');
	pg_assert_same(4, substr_count($card, 'class="pg-input" name="rule_'), 'a row: kind, name, margin, fixed amount');
	pg_assert_same(1, substr_count($card, 'data-pg-sf-rule-remove'), 'a row\'s remove');
	pg_assert_same(1, substr_count($card, 'data-pg-sf-rule-add'), 'Add a rule');
	pg_assert_same(1, substr_count($html, 'data-pg-sf-rule-template'), 'the row template for Add a rule');
}

function test_supplier_feed_screen_test_drawer()
{
	$row = array('code' => 'A-1', 'name' => 'Kettle', 'note' => 'Stock 5', 'buy' => 5000, 'price' => 5500, 'floor' => true);
	$plain = array('code' => 'B-2', 'name' => 'Pot', 'note' => 'Stock 1', 'buy' => 10000, 'price' => 15000, 'floor' => false);
	$diff = array('records' => 2, 'skipped' => 0,
		'added' => array('count' => 2, 'rows' => array($row, $plain)),
		'changed' => array('count' => 0, 'rows' => array()),
		'closed' => array('count' => 0, 'rows' => array()),
		'reopened' => array('count' => 0, 'rows' => array()),
	);
	$html = pg_supplier_feed_test_drawer_html(array('diff' => $diff, 'cut' => false), '<button name="pull_now" class="pg-button pg-button-fill">Pull now</button>', 'USD', 'TRY');

	pg_assert_same(4, substr_count($html, 'class="pg-card pg-figure"'), 'four figures');
	pg_assert_same(2, substr_count($html, 'data-pg-sf-test-row'), 'a row a record');
	pg_assert_same(2, substr_count($html, 'data-pg-sf-prices'), 'each row shows its prices');
	pg_assert_same(1, substr_count($html, 'data-pg-sf-floor'), 'the Floor pill only where the floor lifted the price');
	pg_assert_contains('Nothing was written.', $html, 'one line says nothing was written');
	pg_assert_contains('TRY', $html, 'the selling price in the store\'s currency');
	pg_assert_contains('name="pull_now"', $html, 'Pull now at the foot');
	pg_assert_same(2, substr_count($html, 'data-bs-dismiss="drawer"'), 'Close at the foot and the cross');
	pg_assert_contains('<dialog class="drawer drawer-end" id="sf_test_pull"', $html, 'a drawer');
}

function test_supplier_feed_screen_held_and_runs()
{
	pg_assert_same('', pg_supplier_feed_held_html(array(), 'USD'), 'no held price: no card');

	$html = pg_supplier_feed_held_html(array(
		array('id' => 7, 'supplier_key' => 'A-1', 'buy_price' => 1000, 'held_price' => 300, 'product_name' => 'Kettle'),
		array('id' => 8, 'supplier_key' => 'B-2', 'buy_price' => 1000, 'held_price' => 20000, 'product_name' => ''),
	), 'USD');
	pg_assert_same(2, substr_count($html, 'data-pg-sf-held-row'), 'a row a held price');
	pg_assert_same(2, substr_count($html, 'name="held_accept"'), 'Accept on each row');
	pg_assert_same(2, substr_count($html, 'name="held_reject"'), 'Reject on each row');
	pg_assert_contains('value="7"', $html, 'the item\'s id');
	pg_assert_contains('B-2', $html, 'an unmatched item shows its code');

	$runs = pg_supplier_feed_runs_drawer_html(array(
		array('kind' => 1, 'status' => 1, 'started_at' => 1763700000, 'records' => 10, 'changed' => 2, 'closed' => 1, 'reopened' => 0, 'held' => 1, 'failed' => 1, 'detail' => json_encode(array('reason' => '', 'failed' => array(array('line' => 'X', 'reason' => 'No buy price'))))),
		array('kind' => 0, 'status' => 2, 'started_at' => 1763690000, 'records' => 10, 'changed' => 0, 'closed' => 0, 'reopened' => 0, 'held' => 0, 'failed' => 0, 'detail' => json_encode(array('reason' => 'Half dropped', 'failed' => array()))),
	));
	pg_assert_same(2, substr_count($runs, 'data-pg-sf-run'), 'a row a run');
	pg_assert_contains('Half dropped', $runs, 'a brake\'s reason');
	pg_assert_contains('No buy price', $runs, 'a failed line');
	pg_assert_contains('id="sf_runs"', $runs, 'the pull history drawer');
}

function test_supplier_feed_screen_pull_button_and_product_card()
{
	$confirmed = array('confirmed' => 1);
	pg_assert_same('', pg_supplier_feed_pull_button(array('id' => 3, 'status' => 1, 'pause_reason' => ''), array('confirmed' => 0), 'pg-button'), 'no Pull now before the fields are saved');
	pg_assert_same('', pg_supplier_feed_pull_button(array('id' => 3, 'status' => 0, 'pause_reason' => ''), $confirmed, 'pg-button'), 'no Pull now on a feed that is off');

	$on = pg_supplier_feed_pull_button(array('id' => 3, 'status' => 1, 'pause_reason' => ''), $confirmed, 'pg-button');
	pg_assert_contains('name="pull_now" value="3"', $on, 'Pull now posts the feed');
	pg_assert_false(strpos($on, 'data-confirm-content') !== false, 'an on feed pulls at once');

	$paused = pg_supplier_feed_pull_button(array('id' => 3, 'status' => 2, 'pause_reason' => 'Half dropped'), $confirmed, 'pg-button');
	pg_assert_contains('data-confirm-content=', $paused, 'a paused feed asks first');
	pg_assert_contains('Half dropped.', $paused, 'naming why it was paused');

	pg_assert_same('', pg_supplier_feed_product_card_html(array()), 'no feed carries it: no card');

	$card = pg_supplier_feed_product_card_html(array(
		array('feed_id' => 2, 'name' => 'Acme', 'buy_price' => 1000, 'currency' => 'USD', 'seen_at' => 0, 'follow_price' => 1, 'active' => 1),
	));
	pg_assert_contains('name="supplier_feed_follow_price" value="1" checked', $card, 'Price from the feed on');
	pg_assert_contains('name="supplier_feed_follow_was" value="1"', $card, 'what it was, so only a move is written');
	pg_assert_contains('edit_supplier_feed.php?id=2', $card, 'the feed is named with its link');

	$off = pg_supplier_feed_product_card_html(array(
		array('feed_id' => 2, 'name' => 'Acme', 'buy_price' => 1000, 'currency' => 'USD', 'seen_at' => 0, 'follow_price' => 0, 'active' => 1),
	));
	pg_assert_false(strpos($off, ' checked') !== false, 'Price from the feed off');
}

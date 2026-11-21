<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/supplier_feed.php: the format told from the first
 * bytes, the record path found in an XML or JSON feed, the XML, CSV and JSON
 * readers with their resumable cursor (fixtures in
 * tests/fixtures/supplier_feed/: nested XML, a CSV with a BOM and ";", a JSON
 * list and a wrapped JSON list), the field proposal, amounts with Turkish
 * decimal commas, and the price rule (margin, fixed amount, rate, rounding
 * endings, the floor, category and brand rules). Also the migration step and
 * get_tables().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function supplier_feed_test_fixture($name)
{
	return dirname(__FILE__) . '/fixtures/supplier_feed/' . $name;
}

// Reads a whole fixture in steps of $limit records, following the cursor.
function supplier_feed_test_read_all($name, $format, $record_path, $limit)
{
	$records = array();
	$cursor = array();
	$calls = 0;

	do {
		$result = pg_supplier_feed_read(supplier_feed_test_fixture($name), $format, $record_path, $cursor, $limit);
		pg_assert_same('', $result['error'], $name . ': no error');
		$records = array_merge($records, $result['records']);
		$cursor = $result['cursor'];
		$calls++;
	} while (($cursor !== null) && ($calls < 20));

	return array('records' => $records, 'calls' => $calls);
}

function test_supplier_feed_format_detect()
{
	pg_assert_same('xml', pg_supplier_feed_format_detect("\xEF\xBB\xBF  \n<?xml version=\"1.0\"?><a/>"), 'xml after a BOM and spaces');
	pg_assert_same('xml', pg_supplier_feed_format_detect('<Urunler>'), 'xml without a declaration');
	pg_assert_same('json', pg_supplier_feed_format_detect("\r\n[{\"a\":1}]"), 'json list');
	pg_assert_same('json', pg_supplier_feed_format_detect('{"data":[]}'), 'json object');
	pg_assert_same('csv', pg_supplier_feed_format_detect("\xEF\xBB\xBFStok Kodu;Barkod\r\n"), 'csv');
	pg_assert_same('', pg_supplier_feed_format_detect("  \n"), 'nothing');
	pg_assert_same('xml', pg_supplier_feed_format_detect_file(supplier_feed_test_fixture('nested.xml')), 'xml file');
	pg_assert_same('csv', pg_supplier_feed_format_detect_file(supplier_feed_test_fixture('bom_semicolon.csv')), 'csv file');
	pg_assert_same('json', pg_supplier_feed_format_detect_file(supplier_feed_test_fixture('wrapped.json')), 'json file');
}

function test_supplier_feed_record_path()
{
	pg_assert_same('Root/Urunler/Urun', pg_supplier_feed_record_path(supplier_feed_test_fixture('nested.xml'), 'xml'), 'xml: the shallowest repeated element with children');
	pg_assert_same('', pg_supplier_feed_record_path(supplier_feed_test_fixture('list.json'), 'json'), 'json: the top list');
	pg_assert_same('data/products', pg_supplier_feed_record_path(supplier_feed_test_fixture('wrapped.json'), 'json'), 'json: the list of objects, not of strings');
	pg_assert_same('', pg_supplier_feed_record_path(supplier_feed_test_fixture('bom_semicolon.csv'), 'csv'), 'csv has none');
}

function test_supplier_feed_read_xml()
{
	$all = supplier_feed_test_read_all('nested.xml', 'xml', 'Root/Urunler/Urun', 2);
	$records = $all['records'];

	pg_assert_same(3, count($records), 'three records');
	pg_assert_same(2, $all['calls'], 'two reads of two');
	pg_assert_same('7', $records[0]['@id'], 'attribute');
	pg_assert_same('A-100', $records[0]['StokKodu'], 'leaf');
	pg_assert_same('Çaydanlık & Demlik', $records[0]['UrunAdi'], 'entity decoded');
	pg_assert_same('Mutfak', $records[0]['Kategori/Ad'], 'nested');
	pg_assert_same('<p>Paslanmaz çelik</p>', $records[0]['Aciklama'], 'CDATA');
	pg_assert_same('https://example.com/a1.jpg', $records[0]['Resimler/Resim'], 'first of a repeated element');
	pg_assert_same('https://example.com/a2.jpg', $records[0]['Resimler/Resim[2]'], 'second of a repeated element');
	pg_assert_same('B-200', $records[2]['StokKodu'], 'resumed at the third');
	pg_assert_same('', $records[2]['Barkod'], 'empty element');

	$first = pg_supplier_feed_read(supplier_feed_test_fixture('nested.xml'), 'xml', 'Root/Urunler/Urun', array(), 1);
	pg_assert_same(array('index' => 1), $first['cursor'], 'cursor counts records');

	$missing = pg_supplier_feed_read(supplier_feed_test_fixture('nested.xml'), 'xml', 'Root/Products/Product', array(), 10);
	pg_assert_same(array(), $missing['records'], 'a wrong path finds nothing');
	pg_assert_same(null, $missing['cursor'], 'and ends');

	$broken = pg_supplier_feed_test_broken_xml();
	$result = pg_supplier_feed_read($broken, 'xml', 'r/i', array(), 10);
	@unlink($broken);
	pg_assert_same('parse', $result['error'], 'a cut file is an error, not an end');
}

function pg_supplier_feed_test_broken_xml()
{
	$file = tempnam(sys_get_temp_dir(), 'sf');
	file_put_contents($file, '<r><i><a>1</a></i><i><a>2</a></i><i><a>3');

	return $file;
}

function test_supplier_feed_read_csv()
{
	$all = supplier_feed_test_read_all('bom_semicolon.csv', 'csv', '', 1);
	$records = $all['records'];

	pg_assert_same(3, count($records), 'three records, the blank row left out');
	pg_assert_same(array('Stok Kodu', 'Barkod', 'Ürün Adı', 'Alış Fiyatı', 'Stok Adedi'), array_keys($records[0]), 'headings without the BOM');
	pg_assert_same('Kupa; 300 ml', $records[0]['Ürün Adı'], 'quoted separator');
	pg_assert_same('12,90', $records[0]['Alış Fiyatı'], 'value as written');
	pg_assert_same('C-2', $records[1]['Stok Kodu'], 'resumed after a blank row');
	pg_assert_same("Sat\xC4\xB1r\r\nkesik", $records[2]['Ürün Adı'], 'quoted line break');

	$win = tempnam(sys_get_temp_dir(), 'sf');
	file_put_contents($win, "Kod;Ad\nK1;\xDE\xFDk\n");
	$result = pg_supplier_feed_read($win, 'csv', '', array(), 10);
	@unlink($win);
	pg_assert_same('Şık', $result['records'][0]['Ad'], 'Windows-1254 turned into UTF-8');
}

function test_supplier_feed_read_json()
{
	$all = supplier_feed_test_read_all('list.json', 'json', '', 2);
	$records = $all['records'];

	pg_assert_same(3, count($records), 'three records');
	pg_assert_same('J1', $records[0]['sku'], 'string');
	pg_assert_same('5', $records[0]['quantity'], 'number as text');
	pg_assert_same('7.5', $records[1]['price'], 'float as text');
	pg_assert_same('Işık', $records[0]['brand/name'], 'nested object');
	pg_assert_same('https://example.com/j1b.jpg', $records[0]['images[2]'], 'list');
	pg_assert_same('', $records[2]['barcode'], 'null');
	pg_assert_same('1', $records[2]['active'], 'true');

	$wrapped = supplier_feed_test_read_all('wrapped.json', 'json', 'data/products', 5);
	pg_assert_same(2, count($wrapped['records']), 'wrapped list');
	pg_assert_same('Mavi', $wrapped['records'][0]['variants/color[2]'], 'list of objects');

	$wrong = pg_supplier_feed_read(supplier_feed_test_fixture('wrapped.json'), 'json', 'data/none', array(), 5);
	pg_assert_same('no_records', $wrong['error'], 'a wrong path');
}

function test_supplier_feed_propose_fields()
{
	$xml = pg_supplier_feed_read(supplier_feed_test_fixture('nested.xml'), 'xml', 'Root/Urunler/Urun', array(), 200);
	$fields = pg_supplier_feed_propose_fields($xml['records']);

	pg_assert_same('StokKodu', $fields['supplier_key'], 'xml supplier key');
	pg_assert_same('Barkod', $fields['barcode'], 'xml barcode');
	pg_assert_same('UrunAdi', $fields['name'], 'xml name');
	pg_assert_same('AlisFiyati', $fields['buy_price'], 'xml buy price');
	pg_assert_same('Stok', $fields['stock'], 'xml stock');
	pg_assert_same('ParaBirimi', $fields['currency'], 'xml currency');
	pg_assert_same('Marka', $fields['brand'], 'xml brand');
	pg_assert_same('ModelKodu', $fields['model_code'], 'xml model code');
	pg_assert_same('Kategori/Ad', $fields['category'], 'xml category');
	pg_assert_same('Aciklama', $fields['description'], 'xml description');
	pg_assert_same('Resimler/Resim', $fields['image'], 'xml image');

	$csv = pg_supplier_feed_read(supplier_feed_test_fixture('bom_semicolon.csv'), 'csv', '', array(), 200);
	$fields = pg_supplier_feed_propose_fields($csv['records']);

	pg_assert_same('Stok Kodu', $fields['supplier_key'], 'csv supplier key');
	pg_assert_same('Ürün Adı', $fields['name'], 'csv name');
	pg_assert_same('Alış Fiyatı', $fields['buy_price'], 'csv buy price');
	pg_assert_same('Stok Adedi', $fields['stock'], 'csv stock, not the supplier key');
	pg_assert_same('', $fields['brand'], 'csv no brand');

	$json = pg_supplier_feed_read(supplier_feed_test_fixture('list.json'), 'json', '', array(), 200);
	$fields = pg_supplier_feed_propose_fields($json['records']);

	pg_assert_same('sku', $fields['supplier_key'], 'json sku');
	pg_assert_same('title', $fields['name'], 'json title');
	pg_assert_same('price', $fields['buy_price'], 'json price');
	pg_assert_same('quantity', $fields['stock'], 'json quantity');
	pg_assert_same('brand/name', $fields['brand'], 'json nested brand');
	pg_assert_same('images', $fields['image'], 'json images');

	$wrapped = pg_supplier_feed_read(supplier_feed_test_fixture('wrapped.json'), 'json', 'data/products', array(), 200);
	$fields = pg_supplier_feed_propose_fields($wrapped['records']);

	pg_assert_same('code', $fields['supplier_key'], 'code');
	pg_assert_same('ean', $fields['barcode'], 'ean');
	pg_assert_same('cost', $fields['buy_price'], 'cost');
}

function test_supplier_feed_parse_amount()
{
	pg_assert_same(123450, pg_supplier_feed_parse_amount('1.234,50'), 'Turkish thousands and comma');
	pg_assert_same(1290, pg_supplier_feed_parse_amount('12,90'), 'decimal comma');
	pg_assert_same(750, pg_supplier_feed_parse_amount('₺ 7,5'), 'symbol and one decimal');
	pg_assert_same(123456, pg_supplier_feed_parse_amount('1,234.56'), 'English thousands');
	pg_assert_same(1999, pg_supplier_feed_parse_amount('19.99'), 'decimal point');
	pg_assert_same(45000, pg_supplier_feed_parse_amount('450'), 'whole');
	pg_assert_same(123400, pg_supplier_feed_parse_amount('1.234'), 'a lone dot before three digits is a thousands dot');
	pg_assert_same(13, pg_supplier_feed_parse_amount('0.125'), 'rounded, not cut');
	pg_assert_same(123456780, pg_supplier_feed_parse_amount('1.234.567,8 TL'), 'two thousands dots');
	pg_assert_same(1050, pg_supplier_feed_parse_amount('10.5', 2), 'two decimals');
	pg_assert_same(11, pg_supplier_feed_parse_amount('10,5', 0), 'no decimals: rounded');
	pg_assert_same(null, pg_supplier_feed_parse_amount(''), 'empty');
	pg_assert_same(null, pg_supplier_feed_parse_amount('yok'), 'no number');
	pg_assert_same(null, pg_supplier_feed_parse_amount('-5'), 'negative');
}

function test_supplier_feed_parse_stock()
{
	pg_assert_same(12, pg_supplier_feed_parse_stock('12'), 'number');
	pg_assert_same(10, pg_supplier_feed_parse_stock('10+'), 'plus');
	pg_assert_same(5, pg_supplier_feed_parse_stock('5,00'), 'decimal zeros');
	pg_assert_same(1000, pg_supplier_feed_parse_stock('1.000'), 'thousands dot');
	pg_assert_same(0, pg_supplier_feed_parse_stock('-3'), 'never below 0');
	pg_assert_same(0, pg_supplier_feed_parse_stock(''), 'empty');
	pg_assert_same(0, pg_supplier_feed_parse_stock('yok'), 'text');
}

function test_supplier_feed_net_of_vat()
{
	pg_assert_same(10000, pg_supplier_feed_net_of_vat(12000, 20), '20 %');
	pg_assert_same(10000, pg_supplier_feed_net_of_vat(11800, 18), '18 %');
	pg_assert_same(833, pg_supplier_feed_net_of_vat(1000, 20), 'rounded');
	pg_assert_same(1000, pg_supplier_feed_net_of_vat(1000, 0), 'no VAT');
}

function test_supplier_feed_price()
{
	$rule = array('margin' => 20, 'fixed' => 500, 'rounding' => 'none', 'floor_margin' => 0);

	pg_assert_same(12500, pg_supplier_feed_price(10000, $rule, 1), 'margin and fixed amount');
	pg_assert_same(443750, pg_supplier_feed_price(10000, $rule, 35.5), 'at the rate');
	pg_assert_same(0, pg_supplier_feed_price(0, $rule, 1), 'no buy price, no price');
	pg_assert_same(1234, pg_supplier_feed_price(1028, array('margin' => 20), 1), '1233.6 rounded, not cut');

	$whole = array('margin' => 0, 'rounding' => 'whole');
	pg_assert_same(12500, pg_supplier_feed_price(12549, $whole, 1), 'whole down');
	pg_assert_same(12600, pg_supplier_feed_price(12550, $whole, 1), 'whole up');

	$ends90 = array('margin' => 0, 'rounding' => '90');
	pg_assert_same(12590, pg_supplier_feed_price(12549, $ends90, 1), ',90 nearest above');
	pg_assert_same(12490, pg_supplier_feed_price(12520, $ends90, 1), ',90 nearest below');
	pg_assert_same(90, pg_supplier_feed_price(30, $ends90, 1), ',90 never 0 or below');

	$ends99 = array('margin' => 0, 'rounding' => '99');
	pg_assert_same(12599, pg_supplier_feed_price(12549, $ends99, 1), ',99 a tie goes up');
	pg_assert_same(12499, pg_supplier_feed_price(12540, $ends99, 1), ',99 nearest below');

	pg_assert_same(13804, pg_supplier_feed_price(12549, array('margin' => 10, 'rounding' => '90'), 1, 0, 0), ',90 in a currency without decimals is whole');
	pg_assert_same(355, pg_supplier_feed_price(1000, array('margin' => 0), 35.5, 0, 2), 'feed and home decimals differ');

	$floor = array('margin' => 0, 'rounding' => 'none', 'floor_margin' => 10);
	pg_assert_same(11000, pg_supplier_feed_price(10000, $floor, 1), 'floor');
	pg_assert_same(390500, pg_supplier_feed_price(10000, $floor, 35.5), 'floor at the rate');

	$floor90 = array('margin' => 4, 'rounding' => '90', 'floor_margin' => 10);
	pg_assert_same(11090, pg_supplier_feed_price(10000, $floor90, 1), 'under the floor: the next ending above it');

	$floor_whole = array('margin' => 10.4, 'rounding' => 'whole', 'floor_margin' => 10.4);
	pg_assert_same(11100, pg_supplier_feed_price(10000, $floor_whole, 1), 'rounding down under the floor goes up instead');
}

function test_supplier_feed_price_parts_floor()
{
	$floor90 = array('margin' => 4, 'rounding' => '90', 'floor_margin' => 10);
	pg_assert_same(array('price' => 11090, 'floor' => true), pg_supplier_feed_price_parts(10000, $floor90, 1), 'the floor lifted it');
	pg_assert_same(array('price' => 12990, 'floor' => false), pg_supplier_feed_price_parts(10000, array('margin' => 30, 'rounding' => '90', 'floor_margin' => 10), 1), 'over the floor: not lifted');
	pg_assert_same(array('price' => 11000, 'floor' => false), pg_supplier_feed_price_parts(10000, array('margin' => 10, 'floor_margin' => 10), 1), 'at the floor: not lifted');
	pg_assert_same(array('price' => 0, 'floor' => false), pg_supplier_feed_price_parts(0, $floor90, 1), 'no buy price');
}

function test_supplier_feed_rule_pick()
{
	$rules = array(
		'margin' => 20,
		'fixed' => 0,
		'rounding' => '90',
		'floor_margin' => 5,
		'specific' => array(
			array('category' => 'Mutfak', 'margin' => 30),
			array('brand' => 'Kodpen', 'margin' => 25, 'rounding' => 'whole'),
			array('category' => 'Mutfak', 'brand' => 'Kodpen', 'margin' => 40),
		),
	);

	$both = pg_supplier_feed_rule_pick($rules, 'Mutfak', 'Kodpen');
	pg_assert_same(40, $both['margin'], 'category and brand first');
	pg_assert_same('90', $both['rounding'], 'rounding from the feed');
	pg_assert_same(5, $both['floor_margin'], 'floor from the feed');

	pg_assert_same(30, pg_supplier_feed_rule_pick($rules, 'mutfak', 'Başka')['margin'], 'category, case left aside');
	$brand = pg_supplier_feed_rule_pick($rules, 'Bahçe', 'KODPEN');
	pg_assert_same(25, $brand['margin'], 'brand');
	pg_assert_same('whole', $brand['rounding'], 'its own rounding');
	pg_assert_same(20, pg_supplier_feed_rule_pick($rules, 'Bahçe', 'Başka')['margin'], 'the feed\'s own');
	pg_assert_same(0, pg_supplier_feed_rule_pick(array(), '', '')['margin'], 'no rules: margin 0');
	pg_assert_same('none', pg_supplier_feed_rule_pick(array(), '', '')['rounding'], 'no rules: no rounding');
}

function test_supplier_feed_tables()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_contains('upgrade_2026_11_0_supplier_feeds();', $migration, 'step called');

	foreach (array('supplier_feeds', 'supplier_feed_items', 'supplier_feed_runs') as $table) {
		pg_assert_contains("install_create_table('" . $table . "'", $migration, $table . ' created');
		pg_assert_contains("'" . $table . "',", $install, $table . ' in get_tables()');
	}
}

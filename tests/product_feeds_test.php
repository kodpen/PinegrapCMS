<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/product_feeds.php: prices, GTINs,
 * text, availability, where a product sits in the catalog, the item a product
 * row becomes, the OpenAI and Google rows of an item and the JSONL, CSV and
 * TSV writers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A product row as pg_product_feed_products() reads it (values are strings,
// as they come from the database).
function product_feeds_test_row($overrides = array())
{
	return array_merge(array(
		'id'                      => '12',
		'name'                    => 'CHR-01',
		'title'                   => '',
		'short_description'       => 'Ofis Sandalyesi',
		'full_description'        => '<p>Y&uuml;ksek <strong>s&#305;rtl&#305;</strong> ofis sandalyesi.</p><p>Tekerlekli.</p>',
		'meta_description'        => 'Ofis sandalyesi',
		'price'                   => '149900',
		'image_name'              => 'sandalye.jpg',
		'address_name'            => 'ofis-sandalyesi-gri',
		'selection_type'          => 'quantity',
		'recurring'               => '0',
		'inventory'               => '1',
		'inventory_quantity'      => '4',
		'backorder'               => '0',
		'gtin'                    => '4006381333931',
		'brand'                   => 'Kodpen',
		'mpn'                     => 'CHR-01-G',
		'google_product_category' => '2045',
	), $overrides);
}

function product_feeds_test_site($overrides = array())
{
	return array_merge(array(
		'base_url'      => 'https://shop.example/',
		'detail_page'   => 'ürün',
		'currency'      => 'try',
		'default_brand' => '',
	), $overrides);
}

function product_feeds_test_item($row_overrides = array(), $extra = array(), $site_overrides = array())
{
	return pg_product_feed_item(
		product_feeds_test_row($row_overrides),
		array_merge(array(
			'category_path' => 'Mağaza > Ofis > Sandalyeler',
			'group_id'      => 0,
			'gallery'       => array(),
			'choices'       => array(),
			'sale_price'    => null,
		), $extra),
		product_feeds_test_site($site_overrides)
	);
}

function product_feeds_test_seller()
{
	return array(
		'name'           => 'Kodpen Mağaza',
		'url'            => 'https://shop.example/',
		'privacy_policy' => 'https://shop.example/gizlilik',
		'terms'          => '',
	);
}

// Prices are minor units with two decimals and the upper-case currency code.
function test_product_feeds_money()
{
	pg_assert_same('79.99 TRY', pg_product_feed_money(7999, 'try'));
	pg_assert_same('0.05 TRY', pg_product_feed_money(5, 'TRY'), 'below one lira');
	pg_assert_same('1000.00 USD', pg_product_feed_money('100000', 'USD'), 'string from the database');
	pg_assert_same('20.00 TRY', pg_product_feed_money(1999.6, 'TRY'), 'an offer price with a fraction of a kuruş is rounded');
}

// Only a GTIN with a valid check digit goes out; spaces and dashes are dropped.
function test_product_feeds_gtin()
{
	pg_assert_same('4006381333931', pg_product_feed_gtin('4006381333931'), 'EAN-13');
	pg_assert_same('036000291452', pg_product_feed_gtin('036000291452'), 'UPC-A');
	pg_assert_same('96385074', pg_product_feed_gtin('96385074'), 'EAN-8');
	pg_assert_same('10012345678902', pg_product_feed_gtin('10012345678902'), 'GTIN-14');
	pg_assert_same('4006381333931', pg_product_feed_gtin(' 400-638 1333931 '), 'typed with separators');
	pg_assert_same('', pg_product_feed_gtin('4006381333932'), 'wrong check digit');
	pg_assert_same('', pg_product_feed_gtin('4006381333'), 'ten digits');
	pg_assert_same('', pg_product_feed_gtin('ABC1234567890'), 'letters');
	pg_assert_same('', pg_product_feed_gtin(''), 'empty');
}

// Rich text becomes one line of plain text with entities decoded.
function test_product_feeds_plain_text()
{
	pg_assert_same(
		'Yüksek sırtlı ofis sandalyesi. Tekerlekli.',
		pg_product_feed_plain_text('<p>Y&uuml;ksek <strong>s&#305;rtl&#305;</strong> ofis sandalyesi.</p><p>Tekerlekli.</p>')
	);
	pg_assert_same('a b c', pg_product_feed_plain_text("a&nbsp;&nbsp;b<br>\n\t c"), 'non-breaking spaces and line breaks');
	pg_assert_same('Önce sonra', pg_product_feed_plain_text('Önce<script>alert(1)</script> <style>p{}</style>sonra'), 'scripts and styles dropped');
	pg_assert_same('', pg_product_feed_plain_text(''), 'empty');
}

// A text field is not HTML: a "<" stays.
function test_product_feeds_text_keeps_angle_brackets()
{
	pg_assert_same('Vida <10 mm', pg_product_feed_text('Vida &lt;10 mm'));
	pg_assert_same('Vida <10 mm & somun', pg_product_feed_text("Vida <10 mm\n & somun"));
}

// Long text is cut at a word boundary, within the limit.
function test_product_feeds_truncate()
{
	$text = str_repeat('kelime ', 40);
	$cut = pg_product_feed_truncate(trim($text), 150);

	pg_assert_true(mb_strlen($cut, 'UTF-8') <= 150, 'within the limit');
	pg_assert_same('kelime', mb_substr($cut, -6, null, 'UTF-8'), 'ends on a whole word');
	pg_assert_same('kısa', pg_product_feed_truncate('kısa', 150), 'short text unchanged');

	// No space near the end: a hard cut at the limit.
	pg_assert_same(str_repeat('ş', 10), pg_product_feed_truncate(str_repeat('ş', 30), 10));
}

// Only a tracked product can run out; one that takes back orders keeps selling.
function test_product_feeds_availability()
{
	pg_assert_same('in_stock', pg_product_feed_availability(array('inventory' => '0', 'inventory_quantity' => '0', 'backorder' => '0')), 'not tracked');
	pg_assert_same('in_stock', pg_product_feed_availability(array('inventory' => '1', 'inventory_quantity' => '3', 'backorder' => '0')), 'in stock');
	pg_assert_same('out_of_stock', pg_product_feed_availability(array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '0')), 'sold out');
	pg_assert_same('backorder', pg_product_feed_availability(array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '1')), 'back order');
}

function product_feeds_test_groups()
{
	return array(
		array('id' => '1', 'parent_id' => '0', 'name' => 'Mağaza', 'display_type' => 'browse'),
		array('id' => '2', 'parent_id' => '1', 'name' => 'Ofis', 'display_type' => 'browse'),
		array('id' => '3', 'parent_id' => '2', 'name' => 'Sandalyeler', 'display_type' => 'select'),
		array('id' => '5', 'parent_id' => '1', 'name' => 'Ev &amp; Ya&#351;am', 'display_type' => 'browse'),
		// Its parent (9) is disabled, so it is not in the rows: unreachable.
		array('id' => '4', 'parent_id' => '9', 'name' => 'Arşiv', 'display_type' => 'browse'),
	);
}

// The index holds the groups a catalog shows, with their trail.
function test_product_feeds_group_index()
{
	$index = pg_product_feed_group_index(product_feeds_test_groups(), array(1));

	pg_assert_same(array(1, 2, 3, 5), array_keys($index), 'reachable groups in walk order');
	pg_assert_same('Mağaza > Ofis > Sandalyeler', $index[3]['path']);
	pg_assert_same('Mağaza > Ev & Yaşam', $index[5]['path'], 'names decoded');
	pg_assert_true($index[3]['select'], 'variant chooser');
	pg_assert_false($index[2]['select']);
	pg_assert_false($index[3]['excluded']);

	$excluded = pg_product_feed_group_index(product_feeds_test_groups(), array(1), array('2'));

	pg_assert_true($excluded[2]['excluded'], 'the excluded group');
	pg_assert_true($excluded[3]['excluded'], 'and everything under it');
	pg_assert_false($excluded[5]['excluded'], 'a sibling stays');

	pg_assert_same(array(), pg_product_feed_group_index(product_feeds_test_groups(), array()), 'no catalog, no groups');
	pg_assert_same(array(), pg_product_feed_group_index(product_feeds_test_groups(), array(9)), 'a disabled root');
}

// Only the groups under the catalogs' groups are on the shelves.
function test_product_feeds_group_index_catalog_roots()
{
	$index = pg_product_feed_group_index(product_feeds_test_groups(), array(2));

	pg_assert_same(array(2, 3), array_keys($index), 'a catalog over one branch');
	pg_assert_same('Ofis > Sandalyeler', $index[3]['path'], 'the trail starts at the catalog\'s group');

	// A catalog over the whole shop and one over a branch of it: the branch
	// is reached from the shop and keeps the longer trail, whatever the ids.
	$nested = pg_product_feed_group_index(array(
		array('id' => '7', 'parent_id' => '0', 'name' => 'Mağaza', 'display_type' => 'browse'),
		array('id' => '3', 'parent_id' => '7', 'name' => 'Ofis', 'display_type' => 'browse'),
		array('id' => '8', 'parent_id' => '3', 'name' => 'Masalar', 'display_type' => 'browse'),
	), array(3, 7));

	pg_assert_same('Mağaza > Ofis > Masalar', $nested[8]['path']);

	// A cycle does not loop; a root inside a cycle is its own ancestor and
	// is not walked.
	$cycle = pg_product_feed_group_index(array(
		array('id' => '1', 'parent_id' => '0', 'name' => 'A', 'display_type' => 'browse'),
		array('id' => '2', 'parent_id' => '3', 'name' => 'B', 'display_type' => 'browse'),
		array('id' => '3', 'parent_id' => '2', 'name' => 'C', 'display_type' => 'browse'),
	), array(1, 2));

	pg_assert_same(array(1), array_keys($cycle));
}

// A product is placed by its deepest reachable group; one excluded group
// keeps it out.
function test_product_feeds_placement()
{
	$index = pg_product_feed_group_index(product_feeds_test_groups(), array(1));

	pg_assert_same(
		array('skip' => '', 'category_path' => 'Mağaza > Ofis > Sandalyeler', 'group_id' => 3),
		pg_product_feed_placement(array(2, 3), $index)
	);
	pg_assert_same(
		array('skip' => '', 'category_path' => 'Mağaza > Ofis', 'group_id' => 0),
		pg_product_feed_placement(array(2, 4), $index),
		'the unreachable group is ignored'
	);
	pg_assert_same('not_in_catalog', pg_product_feed_placement(array(4), $index)['skip'], 'only in an unreachable group');
	pg_assert_same('not_in_catalog', pg_product_feed_placement(array(), $index)['skip'], 'in no group');

	$excluded = pg_product_feed_group_index(product_feeds_test_groups(), array(1), array(3));

	pg_assert_same('excluded_group', pg_product_feed_placement(array(5, 3), $excluded)['skip']);
}

// Rows the feeds never offer.
function test_product_feeds_row_skip()
{
	pg_assert_same('', pg_product_feed_row_skip(product_feeds_test_row()));
	pg_assert_same('no_address', pg_product_feed_row_skip(product_feeds_test_row(array('address_name' => ' '))));
	pg_assert_same('donation', pg_product_feed_row_skip(product_feeds_test_row(array('selection_type' => 'donation'))));
	pg_assert_same('recurring', pg_product_feed_row_skip(product_feeds_test_row(array('recurring' => '1'))));
	pg_assert_same('no_price', pg_product_feed_row_skip(product_feeds_test_row(array('price' => '0'))));
}

// The item of an ordinary product.
function test_product_feeds_item()
{
	$item = product_feeds_test_item(array(), array('gallery' => array('sandalye-2.png', 'sandalye.jpg')));

	pg_assert_same('12', $item['id']);
	pg_assert_same('Ofis Sandalyesi', $item['title'], 'the RSS title: short description when there is no title');
	pg_assert_same('Yüksek sırtlı ofis sandalyesi. Tekerlekli.', $item['description'], 'the full description as text');
	pg_assert_same('https://shop.example/%C3%BCr%C3%BCn/ofis-sandalyesi-gri', $item['url'], 'under the detail page');
	pg_assert_same(array('https://shop.example/sandalye.jpg', 'https://shop.example/sandalye-2.png'), $item['image_urls'], 'main image first, no repeats');
	pg_assert_same(149900, $item['price']);
	pg_assert_same(null, $item['sale_price']);
	pg_assert_same('TRY', $item['currency']);
	pg_assert_same('in_stock', $item['availability']);
	pg_assert_same('Kodpen', $item['brand']);
	pg_assert_same('4006381333931', $item['gtin']);
	pg_assert_same('', $item['group_id'], 'not in a variant chooser');
	pg_assert_same(array(), $item['choices']);
}

// Title order and description fallbacks.
function test_product_feeds_item_text_fallbacks()
{
	$item = product_feeds_test_item(array('title' => 'Gri Ofis Sandalyesi | Kodpen', 'full_description' => '<p> </p>'));

	pg_assert_same('Gri Ofis Sandalyesi | Kodpen', $item['title'], 'the title column wins');
	pg_assert_same('Ofis sandalyesi', $item['description'], 'meta description when the full one is empty');

	$bare = product_feeds_test_item(array('full_description' => '', 'meta_description' => '', 'short_description' => ''));

	pg_assert_same('CHR-01', $bare['title'], 'the name last');
	pg_assert_same('CHR-01', $bare['description'], 'the title when there is no description');
}

// An offer price counts only when it undercuts the price.
function test_product_feeds_item_sale_price()
{
	pg_assert_same(119920, product_feeds_test_item(array(), array('sale_price' => 119920.4))['sale_price']);
	pg_assert_same(null, product_feeds_test_item(array(), array('sale_price' => 149900))['sale_price'], 'the same price');
	pg_assert_same(null, product_feeds_test_item(array(), array('sale_price' => 0))['sale_price'], 'free is not a sale price');
}

// The shop's brand stands in for a product without one; an invalid GTIN is left out.
function test_product_feeds_item_brand_and_gtin()
{
	$item = product_feeds_test_item(array('brand' => '', 'gtin' => '1234'), array(), array('default_brand' => 'Kodpen Atölye'));

	pg_assert_same('Kodpen Atölye', $item['brand']);
	pg_assert_same('', $item['gtin']);
}

// A variant names its family and its choices.
function test_product_feeds_item_variant()
{
	$item = product_feeds_test_item(array(), array('group_id' => 3, 'choices' => array('Renk:' => 'Gri', 'Beden' => ' ', '42' => 'Evet')));

	pg_assert_same('G3', $item['group_id'], 'never equal to an item id');
	pg_assert_same(array('Renk' => 'Gri', '42' => 'Evet'), $item['choices'], 'the label\'s colon and empty options dropped');

	$single = product_feeds_test_item(array(), array('group_id' => 0, 'choices' => array('Renk' => 'Gri')));

	pg_assert_same(array(), $single['choices'], 'choices only for a variant');
}

// The OpenAI row starts with the nine required fields, in order.
function test_product_feeds_openai_row()
{
	$reason = null;
	$row = pg_product_feed_openai_row(
		product_feeds_test_item(array(), array('gallery' => array('sandalye-2.png', 'arka.webp'), 'sale_price' => 119900)),
		product_feeds_test_seller(),
		$reason
	);

	pg_assert_same('', $reason);
	pg_assert_same(
		array('item_id', 'title', 'description', 'url', 'brand', 'seller_name', 'image_url', 'availability', 'price'),
		array_slice(array_keys($row), 0, 9)
	);
	pg_assert_same('12', $row['item_id']);
	pg_assert_same('Kodpen Mağaza', $row['seller_name']);
	pg_assert_same('1499.00 TRY', $row['price']);
	pg_assert_same('1199.00 TRY', $row['sale_price']);
	pg_assert_same(array('https://shop.example/sandalye-2.png'), $row['additional_image_urls'], 'WebP is not a JPEG or PNG');
	pg_assert_same('Mağaza > Ofis > Sandalyeler', $row['product_category']);
	pg_assert_same(true, $row['is_eligible_search']);
	pg_assert_same(false, $row['is_eligible_checkout'], 'no checkout without the storefront API');
	pg_assert_same('https://shop.example/gizlilik', $row['seller_privacy_policy']);
	pg_assert_false(isset($row['seller_tos']), 'an empty link is left out');
	pg_assert_false(isset($row['group_id']), 'not a variant');
}

// A variant row carries its family and choices.
function test_product_feeds_openai_row_variant()
{
	$row = pg_product_feed_openai_row(
		product_feeds_test_item(array(), array('group_id' => 3, 'choices' => array('Renk' => 'Gri'))),
		product_feeds_test_seller()
	);

	pg_assert_same('G3', $row['group_id']);
	pg_assert_same(true, $row['listing_has_variations']);
	pg_assert_same('{"Renk":"Gri"}', json_encode($row['variant_dict'], JSON_UNESCAPED_UNICODE));
}

// Rows the OpenAI feed would reject are not written, with the reason.
function test_product_feeds_openai_row_rejects()
{
	$reason = null;

	pg_assert_same(null, pg_product_feed_openai_row(product_feeds_test_item(array('brand' => '')), product_feeds_test_seller(), $reason));
	pg_assert_same('no_brand', $reason);

	pg_assert_same(null, pg_product_feed_openai_row(product_feeds_test_item(array('image_name' => 'sandalye.webp')), product_feeds_test_seller(), $reason));
	pg_assert_same('no_image', $reason, 'only JPEG and PNG');

	pg_assert_same(null, pg_product_feed_openai_row(product_feeds_test_item(array('image_name' => '')), product_feeds_test_seller(), $reason));
	pg_assert_same('no_image', $reason, 'no image');
}

// The Google row mirrors the RSS g: fields.
function test_product_feeds_google_row()
{
	$reason = null;
	$row = pg_product_feed_google_row(
		product_feeds_test_item(
			array('image_name' => 'sandalye.webp', 'gtin' => '', 'mpn' => '', 'inventory_quantity' => '0', 'backorder' => '1'),
			array('group_id' => 3)
		),
		$reason
	);

	pg_assert_same('', $reason);
	pg_assert_same('https://shop.example/sandalye.webp', $row['image_link'], 'Merchant Center takes WebP');
	pg_assert_same('in_stock', $row['availability'], 'back order is in stock, as in the RSS feed');
	pg_assert_same('no', $row['identifier_exists'], 'neither GTIN nor MPN');
	pg_assert_same('G3', $row['item_group_id']);
	pg_assert_same('Mağaza > Ofis > Sandalyeler', $row['product_type']);
	pg_assert_same('2045', $row['google_product_category']);
	pg_assert_same('1499.00 TRY', $row['price']);

	$sold_out = pg_product_feed_google_row(product_feeds_test_item(array('inventory_quantity' => '0')));

	pg_assert_same('out_of_stock', $sold_out['availability']);
	pg_assert_false(isset($sold_out['identifier_exists']), 'a GTIN is there');

	pg_assert_same(null, pg_product_feed_google_row(product_feeds_test_item(array('image_name' => 'logo.svg')), $reason));
	pg_assert_same('no_image', $reason);
}

// JSON Lines: one object per line, letters and slashes as they are, a
// variant_dict with a numeric label still an object.
function test_product_feeds_jsonl()
{
	$rows = array(
		array('item_id' => '1', 'title' => 'Çay bardağı', 'url' => 'https://shop.example/a', 'is_eligible_search' => true),
		array('item_id' => '2', 'variant_dict' => (object) array('42' => 'Evet')),
	);
	$body = pg_product_feed_jsonl($rows);
	$lines = explode("\n", rtrim($body, "\n"));

	pg_assert_same(2, count($lines));
	pg_assert_same('{"item_id":"1","title":"Çay bardağı","url":"https://shop.example/a","is_eligible_search":true}', $lines[0]);
	pg_assert_same('{"item_id":"2","variant_dict":{"42":"Evet"}}', $lines[1]);
	pg_assert_same("\n", substr($body, -1), 'ends with a line break');
	pg_assert_same('', pg_product_feed_jsonl(array()), 'no rows, no lines');
}

// CSV quoting, lists, booleans and objects.
function test_product_feeds_csv()
{
	$rows = array(
		array(
			'item_id'               => '1',
			'title'                 => 'Masa, "ahşap"',
			'additional_image_urls' => array('https://shop.example/a.jpg', 'https://shop.example/b,c.jpg'),
			'variant_dict'          => (object) array('Renk' => 'Gri', 'Beden' => 'L'),
			'is_eligible_checkout'  => false,
		),
		array('item_id' => '2', 'title' => ' boşluk'),
	);
	$body = pg_product_feed_csv($rows, pg_product_feed_used_columns('openai_csv', $rows));
	$lines = explode("\n", rtrim($body, "\n"));

	pg_assert_same('item_id,title,additional_image_urls,variant_dict,is_eligible_checkout', $lines[0], 'used columns in the format order');
	pg_assert_same(
		'1,"Masa, ""ahşap""","https://shop.example/a.jpg,https://shop.example/b%2Cc.jpg","{""Renk"":""Gri"",""Beden"":""L""}",false',
		$lines[1]
	);
	pg_assert_same('2," boşluk",,,', $lines[2], 'empty cells for missing fields; an edge space is quoted');

	// What a CSV reader makes of it.
	$parsed = str_getcsv($lines[1], ',', '"', '');

	pg_assert_same('Masa, "ahşap"', $parsed[1]);
	pg_assert_same(array('Renk' => 'Gri', 'Beden' => 'L'), json_decode($parsed[3], true));
}

// TSV: no tabs or line breaks inside a value.
function test_product_feeds_tsv()
{
	$rows = array(array('id' => '1', 'title' => "Masa\tahşap\r\nyeni", 'additional_image_link' => array('https://shop.example/a.jpg', 'https://shop.example/b.jpg')));
	$body = pg_product_feed_tsv($rows, pg_product_feed_used_columns('google_tsv', $rows));

	pg_assert_same("id\ttitle\tadditional_image_link\n1\tMasa ahşap yeni\thttps://shop.example/a.jpg,https://shop.example/b.jpg\n", $body);
}

// Rendering a format: rows written, rejects counted, required checks.
function test_product_feeds_render()
{
	$items = array(
		product_feeds_test_item(),
		product_feeds_test_item(array('id' => '13', 'brand' => '')),
		product_feeds_test_item(array('id' => '14', 'image_name' => '')),
	);
	$options = array('seller_name' => 'Kodpen Mağaza', 'privacy_policy_url' => 'https://shop.example/gizlilik');

	$jsonl = pg_product_feed_render('openai_jsonl', $items, $options);

	pg_assert_true($jsonl['ok']);
	pg_assert_same(1, $jsonl['count']);
	pg_assert_same(array('no_brand' => 1, 'no_image' => 1), $jsonl['skipped']);
	pg_assert_same('12', json_decode(trim($jsonl['body']), true)['item_id']);

	$tsv = pg_product_feed_render('google_tsv', $items, array());

	pg_assert_true($tsv['ok'], 'Google needs no seller name: the account names the merchant');
	pg_assert_same(2, $tsv['count'], 'Google takes a product without a brand');
	pg_assert_same(array('no_image' => 1), $tsv['skipped']);
	pg_assert_contains("id\ttitle\tdescription\tlink\timage_link\tavailability\tprice\tbrand", $tsv['body']);

	$no_seller = pg_product_feed_render('openai_csv', $items, array());

	pg_assert_false($no_seller['ok']);
	pg_assert_same('no_seller_name', $no_seller['error']);

	pg_assert_same('unknown_format', pg_product_feed_render('xml', $items, $options)['error']);
}

// An empty catalog is still a readable file.
function test_product_feeds_render_empty()
{
	$csv = pg_product_feed_render('openai_csv', array(), array('seller_name' => 'Kodpen'));

	pg_assert_true($csv['ok']);
	pg_assert_same(0, $csv['count']);
	pg_assert_same("item_id,title,description,url,brand,seller_name,image_url,availability,price\n", $csv['body']);

	pg_assert_same("id\ttitle\tdescription\tlink\timage_link\tavailability\tprice\n", pg_product_feed_render('google_tsv', array())['body']);
	pg_assert_same('', pg_product_feed_render('openai_jsonl', array(), array('seller_name' => 'Kodpen'))['body']);
}

// Every format has a file extension and a content type.
function test_product_feeds_formats()
{
	foreach (pg_product_feed_formats() as $format => $definition) {
		pg_assert_true(($definition['extension'] !== '') && ($definition['content_type'] !== ''), $format);
		pg_assert_true(count(pg_product_feed_columns($format)) > 0, $format . ' columns');
	}
}

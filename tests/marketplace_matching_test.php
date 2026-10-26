<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the suggestions that match a marketplace's products to the shop's
 * (includes/api/outbound/matching.php): how titles and codes are read, which
 * product a remote row is offered, when a suggestion counts as sure, and that
 * no shop product is offered twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/matching.php');

function matching_test_products()
{
	return array(
		array('id' => 1, 'code' => 'KRN12K', 'title' => '12V Korna Kırmızı', 'barcodes' => array('8690000000012'), 'price' => 25000),
		array('id' => 2, 'code' => 'KRN12M', 'title' => '12V Korna Mavi', 'barcodes' => array(), 'price' => 25000),
		array('id' => 3, 'code' => 'AMP-H4', 'title' => 'H4 Ampul 55W', 'barcodes' => array(), 'price' => 9000),
		array('id' => 4, 'code' => 'AMP-H7', 'title' => 'H7 Ampul 55W', 'barcodes' => array(), 'price' => 9500),
	);
}

function matching_test_remote($key, $title, $code = '', $barcode = '', $price = 0)
{
	return array('key' => $key, 'code' => $code, 'barcode' => $barcode, 'title' => $title, 'price' => $price);
}

/* ------------------------------------------------------------ reading text */

function test_marketplace_matching_tokens_read_turkish_letters_units_and_suffixes()
{
	$tokens = mp_match_tokens("12 Volt Korna, YÜKSEK Sesli! İnce ışık 12V'li");

	pg_assert_true(in_array('12v', $tokens, true), 'a number and its unit are one word');
	pg_assert_true(in_array('korna', $tokens, true));
	pg_assert_true(in_array('yuksek', $tokens, true), 'Turkish capitals are read as their small letters');
	pg_assert_true(in_array('ince', $tokens, true), 'dotted capital I is i');
	pg_assert_true(in_array('isik', $tokens, true), 'dotless i is i');
	pg_assert_false(in_array('li', $tokens, true), 'the suffix after an apostrophe is not a word');
	pg_assert_false(in_array('volt', $tokens, true));
	pg_assert_same(count($tokens), count(array_unique($tokens)), 'each word once');
}

function test_marketplace_matching_tokens_keep_decimals_apart_from_whole_numbers()
{
	$tokens = mp_match_tokens('1,5 mm kablo 15 mm');

	pg_assert_true(in_array('1d5mm', $tokens, true));
	pg_assert_true(in_array('15mm', $tokens, true));
}

function test_marketplace_matching_code_key_ignores_case_and_separators()
{
	pg_assert_same('KRN12A', mp_match_code_key(' krn-12/a '));
	pg_assert_same('ISIK1', mp_match_code_key('ışık 1'));
	pg_assert_same('', mp_match_code_key(' - '));
}

/* ------------------------------------------------------------- suggestions */

function test_marketplace_matching_a_shared_barcode_is_a_sure_match()
{
	$suggested = mp_match_suggest(
		array(matching_test_remote('r1', 'Araba Kornası', 'N11-XYZ', '8690000000012')),
		matching_test_products());

	pg_assert_same(1, $suggested['r1']['product_id']);
	pg_assert_same('barcode', $suggested['r1']['reason']);
	pg_assert_same(100, $suggested['r1']['score']);
	pg_assert_true($suggested['r1']['sure']);
}

function test_marketplace_matching_the_same_code_written_differently_is_a_sure_match()
{
	$suggested = mp_match_suggest(array(matching_test_remote('r1', 'Ampul', 'amp h7')), matching_test_products());

	pg_assert_same(4, $suggested['r1']['product_id']);
	pg_assert_same('code', $suggested['r1']['reason']);
	pg_assert_true($suggested['r1']['sure']);
}

function test_marketplace_matching_a_code_inside_the_other_code_is_offered()
{
	$suggested = mp_match_suggest(array(
		matching_test_remote('r1', 'Ürün', 'ELK-KRN12M'),
		matching_test_remote('r2', 'Kırmızı korna KRN12K stok kodlu', 'X1'),
	), matching_test_products());

	pg_assert_same(2, $suggested['r1']['product_id'], 'the store prefixed its own code on the marketplace');
	pg_assert_same('code_part', $suggested['r1']['reason']);
	pg_assert_same(1, $suggested['r2']['product_id'], 'the shop code is a word of the remote title');
	pg_assert_same('code_part', $suggested['r2']['reason']);
}

function test_marketplace_matching_a_title_finds_the_product_with_the_same_words()
{
	$suggested = mp_match_suggest(
		array(matching_test_remote('r1', 'Motosiklet 12 Volt Korna Yüksek Ses Kırmızı', 'N11-1')),
		matching_test_products());

	pg_assert_same(1, $suggested['r1']['product_id'], 'red, not blue');
	pg_assert_same('title', $suggested['r1']['reason']);
	pg_assert_true($suggested['r1']['score'] >= 70, 'score ' . $suggested['r1']['score']);
	pg_assert_true($suggested['r1']['sure'], 'the blue one is far behind');
}

function test_marketplace_matching_numbers_tell_similar_titles_apart()
{
	$suggested = mp_match_suggest(array(matching_test_remote('r1', 'H7 Ampul 55W Beyaz', 'N11-2')), matching_test_products());

	pg_assert_same(4, $suggested['r1']['product_id']);
}

function test_marketplace_matching_offers_nothing_for_an_unrelated_title()
{
	$suggested = mp_match_suggest(array(matching_test_remote('r1', 'Bluetooth Kulaklık Siyah', 'N11-3')), matching_test_products());

	pg_assert_false(isset($suggested['r1']));
}

function test_marketplace_matching_offers_each_shop_product_once()
{
	$suggested = mp_match_suggest(array(
		matching_test_remote('r1', '12V Korna Kırmızı', 'N11-4'),
		matching_test_remote('r2', '12 Volt Korna Kırmızı Renk', 'N11-5'),
	), matching_test_products());

	$ids = array();

	foreach ($suggested as $row) {
		$ids[] = $row['product_id'];
	}

	pg_assert_same(count($ids), count(array_unique($ids)), 'one product, one remote row');
	pg_assert_same(1, $suggested['r1']['product_id'], 'the closer title wins the product');
}

function test_marketplace_matching_skips_products_that_are_already_matched()
{
	$suggested = mp_match_suggest(
		array(matching_test_remote('r1', '12V Korna Kırmızı', 'N11-6')),
		matching_test_products(),
		array(1));

	pg_assert_true(!isset($suggested['r1']) || $suggested['r1']['product_id'] !== 1);
	pg_assert_true(!isset($suggested['r1']) || !$suggested['r1']['sure'], 'a weaker product is never sure');
}

function test_marketplace_matching_a_barcode_two_products_share_is_not_sure()
{
	$products = matching_test_products();
	$products[1]['barcodes'] = array('8690000000012');

	$suggested = mp_match_suggest(array(matching_test_remote('r1', 'Korna', '', '8690000000012')), $products);

	pg_assert_same('barcode', $suggested['r1']['reason']);
	pg_assert_false($suggested['r1']['sure']);
}

function test_marketplace_matching_sure_count_counts_only_sure_rows()
{
	$suggested = array(
		'a' => array('product_id' => 1, 'score' => 100, 'reason' => 'barcode', 'sure' => true),
		'b' => array('product_id' => 2, 'score' => 60, 'reason' => 'title', 'sure' => false),
	);

	pg_assert_same(1, mp_match_sure_count($suggested));
}

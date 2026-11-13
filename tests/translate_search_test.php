<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site search on a page served in another language
 * (includes/translate/search.php): the typed text is cut into words, a
 * quoted phrase kept whole, each with its i/ı readings; a page is found when
 * every word is in its translated title, description and body, wherever
 * they stand, with ı and i read as one letter; the whole phrase ranks first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/search.php');

// The words' texts only.
function _translate_search_texts($query)
{
	return array_map(function ($w) { return $w['text']; }, pg_tr_search_words($query));
}

// Spaces cut words; a quoted phrase stays one; signs alone are dropped.
function test_translate_search_words_cut_and_phrase()
{
	pg_assert_same(array('blue', 'shoes'), _translate_search_texts('  blue   shoes '), 'words');
	pg_assert_same(array('summer sale', 'shoes'), _translate_search_texts('"summer  sale" shoes'), 'phrase');
	pg_assert_same(array('shoes'), _translate_search_texts('+shoes* - "" *'), 'operators');
	pg_assert_same(array(), _translate_search_texts('   '), 'empty');
}

// A word typed twice, or in another case or i, counts once.
function test_translate_search_words_once()
{
	pg_assert_same(array('Kahvaltı', 'menu'), _translate_search_texts('Kahvaltı menu KAHVALTI kahvalti'));
}

// At most eight words are compared.
function test_translate_search_words_at_most_eight()
{
	pg_assert_same(8, count(pg_tr_search_words('a1 a2 a3 a4 a5 a6 a7 a8 a9 a10')));
}

// Each word carries its i/ı readings for the database.
function test_translate_search_words_readings()
{
	$words = pg_tr_search_words('KAHVALTI blue');
	pg_assert_same(array('kahvaltı', 'kahvalti'), $words[0]['readings'], 'ı and i');
	pg_assert_same(array('blue'), $words[1]['readings'], 'no i letter');
}

// The condition reads any reading of any word, escaped.
function test_translate_search_like_sql()
{
	pg_assert_same('', pg_tr_search_like_sql('t.text', array()), 'no words');
	$sql = pg_tr_search_like_sql('t.text', pg_tr_search_words("kahvalti 50%"));
	pg_assert_contains("t.text LIKE '%kahvalti%'", $sql, 'typed');
	pg_assert_contains("t.text LIKE '%kahvaltı%'", $sql, 'reading');
	pg_assert_contains("t.text LIKE '%50\\\\%%'", $sql, 'percent escaped');
	pg_assert_contains(' OR ', $sql, 'any word');
}

// "blue shoes" finds a page saying "shoes in blue"; one missing word drops the page.
function test_translate_search_rank_every_word_anywhere()
{
	$texts = array(
		1 => array('seo' => '', 'body' => 'Shoes in blue'),
		2 => array('seo' => 'Blue', 'body' => 'Hats'),
		3 => array('seo' => 'Blue hats', 'body' => 'and our shoes'),
	);
	pg_assert_same(array(3, 1), pg_tr_search_rank($texts, pg_tr_search_words('blue shoes')));
}

// ı and i, I and İ are one letter.
function test_translate_search_rank_folds_turkish_i()
{
	$texts = array(
		4 => array('seo' => '', 'body' => 'Kahvaltı tabağı'),
		5 => array('seo' => 'İADE KOŞULLARI', 'body' => ''),
	);
	pg_assert_same(array(4), pg_tr_search_rank($texts, pg_tr_search_words('KAHVALTI tabagi')), 'KAHVALTI');
	pg_assert_same(array(4), pg_tr_search_rank($texts, pg_tr_search_words('kahvalti')), 'kahvalti');
	pg_assert_same(array(5), pg_tr_search_rank($texts, pg_tr_search_words('iade kosullari')), 'İADE');
}

// The whole phrase first, then hits, a title or description hit three times.
function test_translate_search_rank_phrase_then_hits()
{
	$texts = array(
		1 => array('seo' => 'shoes shoes', 'body' => 'blue'),
		2 => array('seo' => '', 'body' => 'blue shoes'),
		3 => array('seo' => 'shoes', 'body' => 'blue'),
		4 => array('seo' => '', 'body' => 'shoes blue'),
	);
	pg_assert_same(array(2, 1, 3, 4), pg_tr_search_rank($texts, pg_tr_search_words('blue shoes')));
}

// A quoted phrase must stand whole.
function test_translate_search_rank_quoted_phrase()
{
	$texts = array(
		1 => array('seo' => '', 'body' => 'sale for summer'),
		2 => array('seo' => '', 'body' => 'the summer sale'),
	);
	pg_assert_same(array(2), pg_tr_search_rank($texts, pg_tr_search_words('"summer sale"')));
}

// The excerpt centres on the phrase, else the first word the text holds.
function test_translate_search_excerpt_needle()
{
	$words = pg_tr_search_words('blue shoes');
	pg_assert_same('blue shoes', pg_tr_search_excerpt_needle('Our Blue Shoes', $words, 'blue shoes'), 'phrase');
	pg_assert_same('shoes', pg_tr_search_excerpt_needle('shoes in navy', $words, 'blue shoes'), 'word');
	pg_assert_same('kahvaltı', pg_tr_search_excerpt_needle('kahvaltı', pg_tr_search_words('KAHVALTI'), 'KAHVALTI'), 'reading');
}

// The page query keeps its fences and limits.
function test_translate_search_pages_query_fences()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/search.php');
	foreach (array("page.page_search = '1'", "folder.folder_archived = '0'", 'pg_designer_not_binned_sql', 'LIMIT 2000', "u.owner_type IN ('page', 'page_seo')", '$limit = 100') as $needle) {
		pg_assert_contains($needle, $src, $needle);
	}
}

// The PHP fold drops the marks the collation drops.
function test_translate_search_fold()
{
	pg_assert_same('iade kosullari', pg_tr_search_fold('İADE KOŞULLARI'), 'Turkish capitals');
	pg_assert_same('kahvalti tabagi', pg_tr_search_fold('Kahvaltı Tabağı'), 'ı and ğ');
	pg_assert_same('cafe', pg_tr_search_fold('Café'), 'accent');
}

// A product's name line, title and keywords weigh like a page's title.
function test_translate_search_catalog_part()
{
	foreach (array('short_description', 'title', 'keywords', 'keywords#1') as $field) {
		pg_assert_same('seo', pg_tr_search_catalog_part($field), $field);
	}
	foreach (array('full_description', 'full_description#2', 'details@3#0', 'meta_description') as $field) {
		pg_assert_same('body', pg_tr_search_catalog_part($field), $field);
	}
}

// Products and product groups are told apart and ranked by every word.
function test_translate_search_catalog_texts_and_rank()
{
	$rows = array(
		array('owner_type' => 'product', 'owner_id' => '7', 'field' => 'short_description', 'text' => 'Blue <b>shoes</b>'),
		array('owner_type' => 'product_group', 'owner_id' => '7', 'field' => 'full_description', 'text' => 'Shoes for summer'),
		array('owner_type' => 'product_group', 'owner_id' => '7', 'field' => 'details#1', 'text' => 'in blue &amp; red'),
		array('owner_type' => 'product', 'owner_id' => '9', 'field' => 'details', 'text' => 'Blue hats'),
	);
	$texts = pg_tr_search_catalog_texts($rows);
	pg_assert_same(array('seo' => 'Blue shoes', 'body' => ''), $texts['product:7'], 'product');
	pg_assert_same(array('seo' => '', 'body' => "Shoes for summer\nin blue & red"), $texts['product_group:7'], 'group');
	pg_assert_same(array('product:7', 'product_group:7'), pg_tr_search_rank($texts, pg_tr_search_words('blue shoes')), 'rank');
}

// In the source language nothing is asked and nothing is added: the source
// search's answer is unchanged.
function test_translate_search_catalog_source_language_unchanged()
{
	pg_assert_same(array(), pg_tr_search_catalog('shoes'), 'no translated keys');
	$added = array('product_groups' => array(3), 'products' => array(5));
	$allowed = array('product_groups' => array(3), 'products' => array(5, 6));
	pg_assert_same(array('items' => array(), 'added_items' => $added), get_catalog_search_results_translated('shoes', $allowed, $added), 'no items');
	pg_assert_same('masa lambası', _catalog_search_sort_key(null, 'product:5', 'Masa Lambası'), 'source sorts by name');
	pg_assert_same(1, _catalog_search_sort_key(array('product:5' => 1), 'product:5', 'Masa'), 'translated sorts by rank');
}

// The catalog query reads the product and product group uses.
function test_translate_search_catalog_query()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/search.php');
	pg_assert_contains("u.owner_type IN ('product', 'product_group')", $src, 'owner types');
	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content.php');
	$at = strpos($content, 'get_catalog_search_results_translated($search_query');
	pg_assert_true($at !== false && $at < strpos($content, "get_catalog_search_results_for_field('keywords'"), 'translated items come first');
}

// A record is read from its use's field.
function test_translate_search_record_id()
{
	pg_assert_same(12, pg_tr_search_record_id('r12:4'), 'plain');
	pg_assert_same(12, pg_tr_search_record_id('r12:4@1#0'), 'rich text');
	pg_assert_same(0, pg_tr_search_record_id('label'), 'no record');
}

// Records are grouped by id and found by every word.
function test_translate_search_record_texts_and_rank()
{
	$rows = array(
		array('field' => 'r3:1', 'text' => 'Kahvaltı menüsü'),
		array('field' => 'r3:2#1', 'text' => '<p>Sabah &amp; öğle</p>'),
		array('field' => 'r5:1', 'text' => 'Akşam menüsü'),
		array('field' => 'label', 'text' => 'menu'),
	);
	$texts = pg_tr_search_record_texts($rows);
	pg_assert_same(array(3, 5), array_keys($texts), 'records');
	pg_assert_same("Kahvaltı menüsü\nSabah & öğle", $texts[3]['body'], 'body');
	pg_assert_same(array(3), pg_tr_search_rank($texts, pg_tr_search_words('KAHVALTI ogle')), 'every word, folded');
}

// An item is a record's only at the address the index writes for it.
function test_translate_search_record_item_matches()
{
	$record = array('reference_code' => 'AB-12', 'address_name' => 'my-post');
	pg_assert_true(pg_tr_search_record_item_matches('blog?r=AB-12', 'blog', $record), 'reference code');
	pg_assert_true(pg_tr_search_record_item_matches('blog/my-post', 'blog', $record), 'address name');
	pg_assert_false(pg_tr_search_record_item_matches('news/my-post', 'blog', $record), 'another page');
	pg_assert_false(pg_tr_search_record_item_matches('blog/my-post-2', 'blog', $record), 'another record');
	pg_assert_false(pg_tr_search_record_item_matches('blog?r=AB-123', 'blog', $record), 'another code');
}

// In the source language no record is asked for; the advanced search passes
// its own fences.
function test_translate_search_records_source_language_and_fences()
{
	pg_assert_same(array(), pg_tr_search_records('menu', '', ''), 'source language');
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains('pg_tr_search_records($query, $sql_folders, $sql_live)', $src, 'fences passed');
	$search = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/search.php');
	pg_assert_contains("u.owner_type = 'form_records'", $search, 'record uses');
	pg_assert_contains('WHERE $fence (', $search, 'folder fence');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the listings view of marketplace_products.php
 * (includes/api/outbound/listing_articles.php): the order the attribute form
 * asks in, where an article stands, the filters, and the category search.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/listing_articles.php');

function marketplace_listing_articles_test_definitions()
{
	return array(
		array('attribute_id' => '1', 'name' => 'Material', 'mandatory' => '0', 'is_variant' => '0'),
		array('attribute_id' => '2', 'name' => 'Brand', 'mandatory' => '1', 'is_variant' => '0'),
		array('attribute_id' => '3', 'name' => 'Colour', 'mandatory' => '1', 'is_variant' => '1'),
		array('attribute_id' => '4', 'name' => 'Voltage', 'mandatory' => '1', 'is_variant' => '0'),
	);
}

function test_marketplace_listing_articles_required_first_optional_last()
{
	$sections = mp_articles_sections(marketplace_listing_articles_test_definitions());

	pg_assert_same(array('Brand', 'Voltage'), array_column($sections['required'], 'name'));
	pg_assert_same(array('Colour'), array_column($sections['variant'], 'name'), 'a variant attribute is answered by the products, never required here');
	pg_assert_same(array('Material'), array_column($sections['optional'], 'name'));
}

function test_marketplace_listing_articles_missing_answers()
{
	$definitions = marketplace_listing_articles_test_definitions();

	pg_assert_same(array('Brand', 'Voltage'), mp_articles_missing($definitions, array()));
	pg_assert_same(array('Voltage'), mp_articles_missing($definitions, array('2' => 'Osram', '4' => '  ')), 'blank is not an answer');
	pg_assert_same(array(), mp_articles_missing($definitions, array('2' => 'Osram', '4' => '12')));
}

function test_marketplace_listing_articles_state()
{
	$definitions = marketplace_listing_articles_test_definitions();

	$article = array('last_error' => '', 'remote_category_id' => '', 'attributes_json' => '');

	pg_assert_same('none', mp_articles_state($article, null));

	$article['remote_category_id'] = '1000';

	pg_assert_same('unread', mp_articles_state($article, null), 'attributes not in the cache yet');
	pg_assert_same('missing', mp_articles_state($article, $definitions));
	pg_assert_same('ready', mp_articles_state(array_merge($article, array('attributes_json' => '{"2":"Osram","4":"12"}')), $definitions));
	pg_assert_same('ready', mp_articles_state($article, array()), 'a category that asks for nothing');
	pg_assert_same('error', mp_articles_state(array_merge($article, array('last_error' => 'Refused')), $definitions));
}

function test_marketplace_listing_articles_filters_and_page()
{
	$articles = array(
		array('name' => 'LAMP-A', 'title' => 'Desk lamp', 'state' => 'none'),
		array('name' => 'BULB-H4', 'title' => 'H4 bulb', 'state' => 'missing'),
		array('name' => 'BULB-H7', 'title' => 'H7 bulb', 'state' => 'unread'),
		array('name' => 'CABLE', 'title' => 'Cable', 'state' => 'ready'),
		array('name' => 'FUSE', 'title' => 'Fuse', 'state' => 'error'),
	);

	pg_assert_same(array('all' => 5, 'none' => 1, 'missing' => 2, 'ready' => 1, 'errors' => 1), mp_articles_counts($articles));

	$page = mp_articles_page($articles, 'missing', '', 1, 10);

	pg_assert_same(2, $page['total']);
	pg_assert_same(array('BULB-H4', 'BULB-H7'), array_column($page['rows'], 'name'));

	$page = mp_articles_page($articles, 'all', 'h7', 1, 10);

	pg_assert_same(array('BULB-H7'), array_column($page['rows'], 'name'), 'search ignores case');

	$page = mp_articles_page($articles, "x' OR 1", '', 2, 2);

	pg_assert_same(5, $page['total'], 'an unknown filter holds everything');
	pg_assert_same(array('BULB-H7', 'CABLE'), array_column($page['rows'], 'name'));
}

function test_marketplace_listing_articles_category_words_and_label()
{
	pg_assert_same(array('Oto', 'Ampul'), mp_articles_category_words('  Oto > Ampul '));
	pg_assert_same(array(), mp_articles_category_words(' > '));
	pg_assert_same(6, count(mp_articles_category_words('a b c d e f g h')));

	pg_assert_same(array('Auto > Lighting', 'Bulbs'), mp_articles_category_label('Auto > Lighting > Bulbs'));
	pg_assert_same(array('', 'Bulbs'), mp_articles_category_label('Bulbs'));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the RSS categories of a form feed (includes/fn/widgets.php,
 * pg_sw_feed_categories()): a reference, product or page field prints the
 * label of each code it holds, any other field its text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _collections_feeds_labels()
{
	return function ($code) {
		$labels = array('news' => 'News', 'tech' => 'Technology', '12' => 'Blue shirt');
		return isset($labels[$code]) ? $labels[$code] : null;
	};
}

function test_collections_feed_categories_plain_text()
{
	pg_assert_same(array('News', 'Sport'), pg_sw_feed_categories(array('News||Sport', ' News '), '', _collections_feeds_labels()), 'split at ||, trimmed, once each');
	pg_assert_same(array('news, tech'), pg_sw_feed_categories(array('news, tech'), 'number', _collections_feeds_labels()), 'another kind prints its text');
	pg_assert_same(array(), pg_sw_feed_categories(array('', '  '), '', _collections_feeds_labels()));
}

function test_collections_feed_categories_reference_labels()
{
	pg_assert_same(array('News', 'Technology'), pg_sw_feed_categories(array('news, tech'), 'reference', _collections_feeds_labels()), 'a text box holding several codes');
	pg_assert_same(array('News', 'Technology'), pg_sw_feed_categories(array('news', 'tech', 'news'), 'reference', _collections_feeds_labels()), 'one row per code, each once');
	pg_assert_same(array('News', 'Technology'), pg_sw_feed_categories(array('news||tech'), 'reference', _collections_feeds_labels()), 'rows the legacy feed joined');
	pg_assert_same(array('gone'), pg_sw_feed_categories(array('gone'), 'reference', _collections_feeds_labels()), 'a record gone prints its code');
	pg_assert_same(array(), pg_sw_feed_categories(array('<b>'), 'reference', _collections_feeds_labels()), 'not a code: left out');
}

function test_collections_feed_categories_product_and_page()
{
	pg_assert_same(array('Blue shirt'), pg_sw_feed_categories(array('12'), 'product', _collections_feeds_labels()));
	pg_assert_same(array('7'), pg_sw_feed_categories(array('7'), 'page', _collections_feeds_labels()));
}

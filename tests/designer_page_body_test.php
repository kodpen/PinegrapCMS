<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for page bodies written one after another in the same request
 * (generate_style_code_from_tree()): each body carries the assets it needs
 * once (_pg_once_state()), as a designer save of several pages and the
 * Rebuild Page HTML action write them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_page_body_test_carousel_tree($carousels)
{
	$children = array();

	for ($i = 0; $i < $carousels; $i++) {
		$children[] = array('type' => 'content', 'props' => array('contentType' => 'carousel', 'slides' => array('/a.jpg', '/b.jpg')));
	}

	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

function test_each_page_body_carries_its_carousel_assets()
{
	$first = generate_style_code_from_tree(designer_page_body_test_carousel_tree(2), 'first');
	$second = generate_style_code_from_tree(designer_page_body_test_carousel_tree(1), 'second');

	pg_assert_same(1, substr_count($first, 'fancybox.css'), 'once in a body with two carousels');
	pg_assert_same(1, substr_count($first, 'pg_carousel_layout.css'), 'the layout styles once too');
	pg_assert_same(1, substr_count($second, 'fancybox.css'), 'and again in the next body of the same request');

	pg_assert_same('', _pg_fancybox_assets_once(), 'written for the rest of the request after a body');

	$plain = generate_style_code_from_tree(array('type' => 'root', 'props' => array(), 'children' => array()), 'plain');
	pg_assert_false(strpos($plain, 'fancybox') !== false, 'a body without a carousel has none');
}

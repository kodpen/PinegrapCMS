<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the edges of the SEO link graph (pg_seo_link_graph_edges(),
 * includes/fn/link_resolve.php): a page's link to itself, such as the
 * navigation item of the page being viewed, is neither an incoming link nor a
 * step of the click path, so a page only it links to stays an orphan.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A page's own navigation item: no incoming link, no click-path step.
function test_seo_link_graph_self_link_is_not_incoming()
{
	$edges = pg_seo_link_graph_edges(array(
		array('from_type' => 'page', 'from_id' => '7', 'to_id' => '7'),
	));

	pg_assert_same(array(), $edges['incoming'], 'a self link is not an incoming link');
	pg_assert_same(array(), $edges['outgoing'], 'a self link is not a click-path step');
}

// Other pages and products count once per link; a product sharing the page's
// id is another record, not a self link.
function test_seo_link_graph_counts_other_records()
{
	$edges = pg_seo_link_graph_edges(array(
		array('from_type' => 'page', 'from_id' => '1', 'to_id' => '7'),
		array('from_type' => 'page', 'from_id' => '1', 'to_id' => '7'),
		array('from_type' => 'page', 'from_id' => '7', 'to_id' => '7'),
		array('from_type' => 'product', 'from_id' => '7', 'to_id' => '7'),
		array('from_type' => 'page', 'from_id' => '7', 'to_id' => '2'),
	));

	pg_assert_same(array(7 => 3, 2 => 1), $edges['incoming'], 'incoming links of other records counted');
	pg_assert_same(array(1 => array(7 => true), 7 => array(2 => true)), $edges['outgoing'], 'page to page steps');
}

function test_seo_link_graph_build_uses_edges_helper()
{
	pg_assert_contains('pg_seo_link_graph_edges(', (string) file_get_contents(dirname(__DIR__) . '/pinegrap/seo_links.php'), 'the graph build reads its edges from the pure helper');
}

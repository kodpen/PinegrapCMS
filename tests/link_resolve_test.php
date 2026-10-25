<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure link resolver (includes/fn/link_resolve.php): one href
 * resolved to what router.php would find, from name maps handed in. The SEO
 * link graph (seo_links.php, pg_seo_resolve_link()) and the prototype viewer
 * (prototype.php) both stand on it, so every branch the SEO resolver had is
 * pinned here with the answer it gave before the split.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function link_resolve_test_env($path = '/')
{
	return array('host' => 'example.com', 'path' => $path, 'software_directory' => 'pinegrap');
}

function link_resolve_test_maps()
{
	return array(
		'pages' => array(
			'home' => array('id' => 1, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'about' => array('id' => 2, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'shop' => array('id' => 3, 'type' => 'catalog', 'layout_type' => 'custom', 'folder' => 1),
			'blog' => array('id' => 4, 'type' => 'form list view', 'layout_type' => 'custom', 'folder' => 1),
			'designed' => array('id' => 5, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'events' => array('id' => 6, 'type' => 'calendar view', 'layout_type' => 'custom', 'folder' => 1),
			'about us' => array('id' => 7, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'old-blog' => array('id' => 8, 'type' => 'form list view', 'layout_type' => 'system', 'folder' => 1),
		),
		'files' => array('logo.png' => 10),
		'short_links' => array('promo' => true),
		'products' => array('red-shirt' => 20),
		'product_groups' => array('shirts' => 30),
		'home_page_id' => 1,
	);
}

// type and id only: the shape pg_seo_resolve_link() returns.
function link_resolve_test_type_id($href, $env = null, $form_names = array('hello-world' => true))
{
	$result = pg_link_resolve($href, link_resolve_test_maps(), ($env === null) ? link_resolve_test_env() : $env, $form_names);

	return array($result['type'], $result['id']);
}

function test_link_resolve_empty_href_is_unknown()
{
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id(''));
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id('   '));
}

function test_link_resolve_schemes_and_fragments_are_external()
{
	foreach (array('mailto:a@example.com', 'TEL:+90123', 'sms:123', 'javascript:void(0)', 'data:text/plain,x', 'ftp://files.example.com/a', '#top', '#') as $href) {
		pg_assert_same(array('external', 0), link_resolve_test_type_id($href), $href);
	}
}

function test_link_resolve_classes_name_what_external_means()
{
	$expect = array(
		'mailto:a@example.com' => 'mailto',
		'TEL:+90123' => 'tel',
		'sms:123' => 'sms',
		'javascript:void(0)' => 'script',
		'data:text/plain,x' => 'data',
		'ftp://files.example.com/a' => 'ftp',
		'#top' => 'anchor',
		'#' => 'anchor',
		'https://other.example.org/about' => 'host',
		'?page=2' => 'self',
		'/robots.txt' => 'robots',
		'/pinegrap/edit_page.php' => 'backend',
		'' => 'empty',
		'/missing' => 'unknown',
		'/about' => 'page',
		'/logo.png' => 'file',
	);

	foreach ($expect as $href => $class) {
		$result = pg_link_resolve($href, link_resolve_test_maps(), link_resolve_test_env(), array());
		pg_assert_same($class, $result['class'], $href);
	}
}

function test_link_resolve_other_host_is_external_own_host_is_resolved()
{
	pg_assert_same(array('external', 0), link_resolve_test_type_id('https://other.example.org/about'));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('https://EXAMPLE.com/about'));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('https://example.com:8080/about'));
}

function test_link_resolve_without_own_host_every_host_is_external()
{
	$env = link_resolve_test_env();
	$env['host'] = '';

	pg_assert_same(array('external', 0), link_resolve_test_type_id('https://example.com/about', $env));
}

function test_link_resolve_bare_query_and_host_without_path_are_external()
{
	pg_assert_same(array('external', 0), link_resolve_test_type_id('?a=1'));
	pg_assert_same(array('external', 0), link_resolve_test_type_id('https://example.com'));
}

function test_link_resolve_unparsable_href_is_unknown()
{
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id('http:///example.com'));
}

function test_link_resolve_relative_and_absolute_page_names()
{
	pg_assert_same(array('page', 2), link_resolve_test_type_id('about'));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('/about'));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('/about?x=1#y'));
	pg_assert_same(array('page', 7), link_resolve_test_type_id('/about%20us'));
}

function test_link_resolve_site_root_and_index_are_the_home_page()
{
	pg_assert_same(array('page', 1), link_resolve_test_type_id('/'));
	pg_assert_same(array('page', 1), link_resolve_test_type_id('/index.php'));
}

function test_link_resolve_robots_sitemap_and_backend_are_external()
{
	pg_assert_same(array('external', 0), link_resolve_test_type_id('/robots.txt'));
	pg_assert_same(array('external', 0), link_resolve_test_type_id('/Sitemap.XML'));
	pg_assert_same(array('external', 0), link_resolve_test_type_id('/pinegrap/edit_page.php'));
}

function test_link_resolve_files_short_links_and_unknown_names()
{
	pg_assert_same(array('file', 10), link_resolve_test_type_id('/logo.png'));
	pg_assert_same(array('short_link', 0), link_resolve_test_type_id('/promo'));
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id('/missing'));
}

function test_link_resolve_pretty_addresses_follow_the_page_type()
{
	pg_assert_same(array('product', 20), link_resolve_test_type_id('/shop/red-shirt'));
	pg_assert_same(array('product_group', 30), link_resolve_test_type_id('/shop/shirts'));
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id('/shop/nothing'));
	pg_assert_same(array('page', 5), link_resolve_test_type_id('/designed/whatever'));
	pg_assert_same(array('page', 4), link_resolve_test_type_id('/blog/hello-world'));
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id('/blog/nope'));
	pg_assert_same(array('page', 6), link_resolve_test_type_id('/events/2026'));
	pg_assert_same(array('unknown', 0), link_resolve_test_type_id('/nope/x'));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('/about/'));
}

function test_link_resolve_system_layout_list_is_read_as_a_catalog_page_first()
{
	// layout_type 'system' sends the address down the catalog branch before
	// the form list view branch is reached, as router-mirroring code did.
	pg_assert_same(array('page', 8), link_resolve_test_type_id('/old-blog/nope'));
}

function test_link_resolve_outside_the_installation_path_is_external()
{
	pg_assert_same(array('external', 0), link_resolve_test_type_id('/other/about', link_resolve_test_env('/site/')));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('/site/about', link_resolve_test_env('/site/')));
	pg_assert_same(array('page', 2), link_resolve_test_type_id('about', link_resolve_test_env('/site/')));
}

function test_link_resolve_reads_form_names_only_for_a_form_list_address()
{
	$calls = 0;
	$loader = function () use (&$calls) {
		$calls++;
		return array('hello-world' => true);
	};

	pg_link_resolve('/about', link_resolve_test_maps(), link_resolve_test_env(), $loader);
	pg_link_resolve('mailto:a@example.com', link_resolve_test_maps(), link_resolve_test_env(), $loader);
	pg_assert_same(0, $calls);

	$result = pg_link_resolve('/blog/hello-world', link_resolve_test_maps(), link_resolve_test_env(), $loader);
	pg_assert_same('page', $result['type']);
	pg_assert_same(1, $calls);
}

function test_link_resolve_parse_hands_back_the_item_name()
{
	$parsed = pg_link_parse('https://example.com/shop/red-shirt?x=1', link_resolve_test_env());
	pg_assert_same(null, $parsed['result']);
	pg_assert_same('shop/red-shirt', $parsed['item']);

	$parsed = pg_link_parse('mailto:a@example.com', link_resolve_test_env());
	pg_assert_same('external', $parsed['result']['type']);
	pg_assert_same('mailto', $parsed['result']['class']);
}

function test_link_resolve_lookup_names_cover_page_prefix_and_slug()
{
	$names = pg_link_lookup_names(array('about', 'shop/red-shirt', 'shop/red-shirt', 'blog/hello-world/'));

	pg_assert_same(array('about', 'shop/red-shirt', 'shop', 'blog/hello-world/', 'blog'), $names['items']);
	pg_assert_same(array('red-shirt', 'hello-world'), $names['slugs']);
}

function test_link_resolve_seo_wrapper_delegates_to_the_pure_resolver()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/seo_links.php');

	pg_assert_contains('pg_link_parse(', $source);
	pg_assert_contains('pg_link_resolve_item(', $source);
	pg_assert_contains("'pg_seo_form_address_names'", $source);
	// The maps now live with the resolver; a second definition would be a
	// fatal "cannot redeclare" in every request that loads both.
	pg_assert_false(strpos($source, 'function pg_seo_link_maps') !== false);
	pg_assert_false(strpos($source, 'function pg_seo_form_address_names') !== false);
}

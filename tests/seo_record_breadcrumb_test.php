<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_record_list_page_pick() in includes/fn/seo.php: which page a
 * record page's breadcrumbs name as its list, and the BreadcrumbList that
 * get_page_content() builds from it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _seo_record_breadcrumb_page($id, $name, $title, $extra = array())
{
	return $extra + array(
		'page_id' => $id, 'page_name' => $name, 'page_title' => $title, 'page_home' => 'no',
		'sitemap' => 1, 'noindex' => 0, 'public' => true, 'archived' => false,
	);
}

// The list page is named by its title, else its name.
function test_seo_record_breadcrumb_names_the_list()
{
	pg_assert_same(array('name' => 'Blog', 'page_name' => 'blog', 'home' => false),
		pg_record_list_page_pick(array(_seo_record_breadcrumb_page(5, 'blog', 'Blog')), 9));
	pg_assert_same('archive', pg_record_list_page_pick(array(_seo_record_breadcrumb_page(5, 'archive', '  ')), 9)['name']);
	pg_assert_same(null, pg_record_list_page_pick(array(), 9));
}

// Private, archived, noindex pages and the record page itself are never
// the list step.
function test_seo_record_breadcrumb_skips_unlisted_pages()
{
	pg_assert_same(null, pg_record_list_page_pick(array(
		_seo_record_breadcrumb_page(1, 'members', 'Members', array('public' => false)),
		_seo_record_breadcrumb_page(2, 'old', 'Old', array('archived' => true)),
		_seo_record_breadcrumb_page(3, 'tags', 'Tags', array('noindex' => 1)),
		_seo_record_breadcrumb_page(9, 'blog-detail', 'Post'),
		_seo_record_breadcrumb_page(4, '', 'Nameless'),
	), 9));
}

// Several lists: not the home page, then one in the site map, then the oldest.
function test_seo_record_breadcrumb_order()
{
	$home = _seo_record_breadcrumb_page(1, 'home', 'Home', array('page_home' => 'yes'));
	$off_map = _seo_record_breadcrumb_page(2, 'latest', 'Latest', array('sitemap' => 0));
	$blog = _seo_record_breadcrumb_page(7, 'blog', 'Blog');
	$news = _seo_record_breadcrumb_page(4, 'news', 'News');

	pg_assert_same('news', pg_record_list_page_pick(array($home, $off_map, $blog, $news), 9)['page_name']);
	pg_assert_same('latest', pg_record_list_page_pick(array($home, $off_map), 9)['page_name']);
	pg_assert_same(true, pg_record_list_page_pick(array($home), 9)['home']);
}

// get_page_content() takes the step from the list page, not from the first
// part of the post's address.
function test_seo_record_breadcrumb_page_content()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_contains('$pg_list_page = pg_record_list_page($page_id);', $source);
	pg_assert_contains("if (\$pg_list_page && !\$pg_list_page['home']) {", $source);
	pg_assert_false(strpos($source, '$pg_list_page_name = mb_substr(PRETTY_URL_PATH') !== false);

	$seo = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');
	$start = strpos($seo, 'function pg_record_list_page(');
	$body = substr($seo, $start, strpos($seo, "\n}\n", $start) - $start);
	pg_assert_contains("(form_item_view_page_id = ?) AND (collection = 'a')", $body);
	pg_assert_contains("(int) (\$cfg['detail_page_id'] ?? 0) === \$record_page_id", $body);
	pg_assert_contains('pg_sw_form_list_types()', $body);
}

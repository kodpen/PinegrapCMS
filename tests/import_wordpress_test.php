<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WordPress export reader (includes/import/wordpress.php) on
 * tests/fixtures/import/wordpress.xml: what each item becomes, comment
 * threads, drafts, skipped internals, the cursor across requests, and the
 * pure helpers for dates, shortcodes and paragraphs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/wordpress.php');

function pg_test_import_wp_file()
{
	return dirname(__FILE__) . '/fixtures/import/wordpress.xml';
}

// Every item of the fixture, in file order.
function pg_test_import_wp_items($cursor = null)
{
	$cursor = ($cursor !== null) ? $cursor : pg_import_wordpress_open(pg_test_import_wp_file());
	$items = array();

	while (($item = pg_import_wordpress_next($cursor)) !== null) {
		$items[$item['source_key']] = $item;
	}

	return $items;
}

function test_import_wp_detected_as_wordpress()
{
	pg_assert_same('wordpress', pg_import_detect_kind((string) file_get_contents(pg_test_import_wp_file(), false, null, 0, 8192)));
}

// The authors are user items before every post, keyed by site and login.
function test_import_wp_author_items()
{
	$items = pg_import_wp_author_items(pg_import_wp_site(pg_test_import_wp_file()));

	pg_assert_same(2, count($items), 'count');
	pg_assert_same('user', $items[1]['kind'], 'kind');
	pg_assert_same('old.example.com:author:omer', $items[1]['source_key'], 'key');
	pg_assert_same('omer@example.com', $items[1]['data']['email'], 'email');
	pg_assert_same(array('title' => 'Ömer Yılmaz', 'detail' => 'omer@example.com'), pg_import_wordpress_plan($items[1]), 'plan');
}

// The site head: host as key prefix, the namespace version, authors by login.
function test_import_wp_site_head()
{
	$site = pg_import_wp_site(pg_test_import_wp_file());

	pg_assert_same('old.example.com', $site['key'], 'key');
	pg_assert_same('http://wordpress.org/export/1.2/', $site['ns'], 'ns');
	pg_assert_same(array('login' => 'omer', 'name' => 'Ömer Yılmaz', 'email' => 'omer@example.com'), $site['authors']['omer'], 'author');
}

// Counts per kind: two authors, three records (two posts and the custom post
// type), one page, two files, three comments; menu item, bin, pingback left
// out.
function test_import_wp_counts_per_kind()
{
	$counts = array();

	foreach (pg_test_import_wp_items() as $item) {
		$counts[$item['kind']] = ($counts[$item['kind']] ?? 0) + 1;
	}

	ksort($counts);

	pg_assert_same(array('comment' => 3, 'file' => 2, 'page' => 1, 'record' => 3, 'user' => 2), $counts);
}

// The post with a featured image and its comment thread.
function test_import_wp_post_with_picture_and_comments()
{
	$items = pg_test_import_wp_items();
	$post = $items['old.example.com:post:10'];

	pg_assert_same('record', $post['kind'], 'kind');
	pg_assert_same('A week by the sea', $post['title'], 'title');
	pg_assert_same('published', $post['data']['status'], 'status');
	pg_assert_same(1748856600, $post['data']['time'], 'gmt date');
	pg_assert_same('Seven days of sand.', $post['data']['excerpt'], 'excerpt');
	pg_assert_same(array('Travel'), $post['data']['categories'], 'categories');
	pg_assert_same(array('Sea'), $post['data']['tags'], 'tags');
	pg_assert_same('old.example.com:post:21', $post['data']['picture'], 'picture');
	pg_assert_same('Jane Doe', $post['data']['author']['name'], 'author');
	pg_assert_same('https://old.example.com/2025/06/a-week-by-the-sea/', $post['data']['link'], 'old address');

	// Caption kept as its content, gallery gone.
	pg_assert_contains('beach.jpg', $post['data']['body'], 'caption image');
	pg_assert_contains('The beach at dawn', $post['data']['body'], 'caption text');
	pg_assert_false(strpos($post['data']['body'], '[gallery') !== false, 'gallery removed');
	pg_assert_false(strpos($post['data']['body'], '[caption') !== false, 'caption tag removed');

	$reply = $items['old.example.com:comment:101'];

	pg_assert_same('comment', $reply['kind'], 'comment kind');
	pg_assert_same('old.example.com:post:10', $reply['data']['post'], 'comment post');
	pg_assert_same('old.example.com:comment:100', $reply['data']['parent'], 'thread');
	pg_assert_true($reply['data']['published'], 'approved');
	pg_assert_false($items['old.example.com:comment:102']['data']['published'], 'held comment kept unpublished');
	pg_assert_false(isset($items['old.example.com:comment:103']), 'pingback left out');
}

// A draft: draft status, its local date in the old site's zone, an unknown
// shortcode kept as text, the embed as a link.
function test_import_wp_draft()
{
	$post = pg_test_import_wp_items()['old.example.com:post:11'];

	pg_assert_same('draft', $post['data']['status'], 'status');
	pg_assert_same(1751364000, $post['data']['time'], 'local date read as UTC without a zone');
	pg_assert_contains('[contact-form-7 id="5"]', $post['data']['body'], 'unknown shortcode kept');
	pg_assert_contains('<a href="https://video.example.com/1">', $post['data']['body'], 'embed as link');
}

function test_import_wp_page_file_and_custom_type()
{
	$items = pg_test_import_wp_items();

	pg_assert_same('page', $items['old.example.com:post:12']['kind'], 'page');
	pg_assert_same('https://old.example.com/wp-content/uploads/2025/06/harbour.png', $items['old.example.com:post:22']['data']['url'], 'file url');
	pg_assert_same(10, $items['old.example.com:post:22']['data']['parent'], 'file parent');
	pg_assert_same('portfolio', $items['old.example.com:post:30']['data']['post_type'], 'custom type');
	pg_assert_false(isset($items['old.example.com:post:40']), 'menu item left out');
	pg_assert_false(isset($items['old.example.com:post:41']), 'bin left out');
}

// A cursor saved after the first item and read back in a later request
// (JSON, the file opened again) gives the rest, nothing twice.
function test_import_wp_cursor_resumes()
{
	$cursor = pg_import_wordpress_open(pg_test_import_wp_file());
	$author = pg_import_wordpress_next($cursor);
	pg_import_wordpress_next($cursor);
	$first = pg_import_wordpress_next($cursor);
	pg_import_wordpress_next($cursor);

	$saved = json_decode(json_encode($cursor), true);

	// Another file in between drops the reader this request held open.
	$other = pg_import_wordpress_open(pg_test_import_wp_file());
	$other['index'] = 3;
	pg_import_wordpress_next($other);

	$rest = pg_test_import_wp_items($saved);
	$all = pg_test_import_wp_items();

	pg_assert_same('old.example.com:author:jane', $author['source_key'], 'authors first');
	pg_assert_same('old.example.com:post:10', $first['source_key'], 'first');
	pg_assert_same(array_slice(array_keys($all), 4), array_keys($rest), 'rest');
}

function test_import_wp_time()
{
	pg_assert_same(1748856600, pg_import_wp_time('2025-06-02 09:30:00', '2025-06-02 12:30:00', 'Europe/Istanbul'), 'gmt wins');
	pg_assert_same(1748856600, pg_import_wp_time('0000-00-00 00:00:00', '2025-06-02 12:30:00', 'Europe/Istanbul'), 'local in zone');
	pg_assert_same(0, pg_import_wp_time('', 'yesterday', ''), 'no date');
	pg_assert_same(1748856600, pg_import_wp_time('', '2025-06-02 09:30:00', 'No/Such_Zone'), 'unknown zone as UTC');
}

function test_import_wp_status()
{
	pg_assert_same('published', pg_import_wp_status('publish'));
	pg_assert_same('draft', pg_import_wp_status('pending'));
	pg_assert_same('draft', pg_import_wp_status('future'));
	pg_assert_same('', pg_import_wp_status('trash'));
	pg_assert_same('', pg_import_wp_status('auto-draft'));
}

// Classic editor text becomes paragraphs; block editor HTML stays.
function test_import_wp_autop()
{
	pg_assert_same("<p>One<br>\nline</p>\n<p>Two</p>\n<h2>Head</h2>", pg_import_wp_autop("One\nline\n\nTwo\n\n<h2>Head</h2>"), 'classic');
	pg_assert_same('<p>Already</p>', pg_import_wp_autop('<p>Already</p>'), 'block editor');
	pg_assert_same('', pg_import_wp_autop("  \n "), 'empty');
}

function test_import_wp_embed_only_web_addresses()
{
	pg_assert_same('', pg_import_wp_shortcodes('[embed]javascript:alert(1)[/embed]'));
}

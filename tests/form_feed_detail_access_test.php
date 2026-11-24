<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a form feed whose items open a page that is not public
 * (includes/fn/widgets.php, get_page.php): such an item is a teaser with
 * title, link, guid, pubDate and category, and no description or media.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_feed_teaser_by_access()
{
	pg_assert_same(false, pg_sw_feed_teaser_access('public'), 'a public page gets the full item');
	pg_assert_same(false, pg_sw_feed_teaser_access(''), 'no detail page: the item opens the public list page');
	foreach (array('membership', 'private', 'registration', 'guest') as $type) {
		pg_assert_same(true, pg_sw_feed_teaser_access($type), $type . ' folder gives a teaser');
	}
}

function test_form_feed_item_full()
{
	$item = array(
		'title'       => 'A & B',
		'link'        => 'https://example.com/post?r=1&x=2',
		'guid'        => '<guid>https://example.com/post?r=1</guid>',
		'description' => 'Body <b>text</b>',
		'pubDate'     => 'Tue, 24 Nov 2026 10:00:00 +0000',
		'categories'  => '<category>News</category>',
		'enclosure'   => '<enclosure url="https://example.com/a.png" length="0" type="image/png"/>',
	);
	$xml = pg_sw_feed_item_xml($item, false);
	pg_assert_contains('<title>A &amp; B</title>', $xml, 'title escaped');
	pg_assert_contains('<link>https://example.com/post?r=1&amp;x=2</link>', $xml, 'link escaped');
	pg_assert_contains('<description>Body &lt;b&gt;text&lt;/b&gt;</description>', $xml, 'description escaped');
	pg_assert_contains('<pubDate>Tue, 24 Nov 2026 10:00:00 +0000</pubDate>', $xml, 'pubDate');
	pg_assert_contains('<category>News</category>', $xml, 'category');
	pg_assert_contains('<enclosure url=', $xml, 'media');
}

function test_form_feed_item_teaser_leaves_out_body_and_media()
{
	$item = array(
		'title'       => 'Members post',
		'link'        => 'https://example.com/post?r=1',
		'guid'        => '<guid>https://example.com/post?r=1</guid>',
		'description' => 'Secret body',
		'pubDate'     => 'Tue, 24 Nov 2026 10:00:00 +0000',
		'categories'  => '<category>News</category>',
		'enclosure'   => '<enclosure url="https://example.com/a.png" length="0" type="image/png"/>',
	);
	$xml = pg_sw_feed_item_xml($item, true);
	pg_assert_contains('<title>Members post</title>', $xml, 'title kept');
	pg_assert_contains('<link>https://example.com/post?r=1</link>', $xml, 'link kept');
	pg_assert_contains('<guid>https://example.com/post?r=1</guid>', $xml, 'guid kept');
	pg_assert_contains('<pubDate>', $xml, 'pubDate kept');
	pg_assert_contains('<category>News</category>', $xml, 'category kept');
	pg_assert_same(false, strpos($xml, 'Secret body') !== false, 'no body');
	pg_assert_same(false, strpos($xml, '<description') !== false, 'no description element');
	pg_assert_same(false, strpos($xml, '<enclosure') !== false, 'no media');
}

function test_form_feed_designed_feed_reads_detail_access()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets.php');
	pg_assert_contains("\$teaser = pg_sw_feed_teaser_access(pg_sw_feed_page_access(\$pick['detail_page_id']));", $source, 'the designed feed checks the detail page');
	pg_assert_contains('pg_sw_feed_item_xml(array(', $source, 'items built by the shared builder');
}

function test_form_feed_classic_feed_reads_detail_access()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_page.php');
	pg_assert_contains("\$rss_teaser = (\$form_item_view_page_id != '') && pg_sw_feed_teaser_access(pg_sw_feed_page_access(\$form_item_view_page_id));", $source, 'the classic feed checks the detail page');
	pg_assert_same(2, substr_count($source, "(\$rss_teaser ? '' : '<description>' . \$output_rss_description . '</description>')"), 'both classic item builders leave the description out');
	pg_assert_contains("if (\$rss_teaser)\n\t\t\t\t\t\t{\n\t\t\t\t\t\t\t\$output_rss_media = '';", $source, 'the classic teaser leaves the media out');
}

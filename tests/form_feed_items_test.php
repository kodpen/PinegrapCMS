<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the per-record parts of a form feed item (includes/fn/widgets.php):
 * pg_sw_feed_guid_xml() gives every record its own guid, and
 * pg_sw_feed_url_enclosure_xml() writes an enclosure for an address typed
 * into a media field without making up its size or type.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_feed_guid_detail_page_is_permalink()
{
	pg_assert_same(
		'<guid>https://example.com/news-item?r=AB12</guid>',
		pg_sw_feed_guid_xml('https://example.com/news-item?r=AB12', 'https://example.com/menu', 'AB12'),
		'a record address stays the guid'
	);
	pg_assert_same(
		'<guid>https://example.com/a?r=1&amp;x=2</guid>',
		pg_sw_feed_guid_xml('https://example.com/a?r=1&x=2', 'https://example.com/menu', '1'),
		'escaped once'
	);
}

function test_form_feed_guid_without_detail_page_is_per_record()
{
	$a = pg_sw_feed_guid_xml('', 'https://example.com/menu', 'AB12');
	$b = pg_sw_feed_guid_xml('', 'https://example.com/menu', 'CD34');
	pg_assert_same('<guid isPermaLink="false">https://example.com/menu#AB12</guid>', $a, 'page address and the record code');
	pg_assert_true($a !== $b, 'two records, two guids');
	pg_assert_same(
		'<guid isPermaLink="false">https://example.com/menu#%3Cb%3E</guid>',
		pg_sw_feed_guid_xml('', 'https://example.com/menu', '<b>'),
		'the code is encoded'
	);
}

function test_form_feed_url_enclosure_type_from_extension()
{
	pg_assert_same(
		'<enclosure url="https://cdn.example.com/a/photo.JPG" length="0" type="image/jpeg"/>',
		pg_sw_feed_url_enclosure_xml(' https://cdn.example.com/a/photo.JPG '),
		'unknown size is 0, type from the extension'
	);
	pg_assert_same(
		'<enclosure url="https://example.com/p.png?w=1&amp;h=2" length="0" type="image/png"/>',
		pg_sw_feed_url_enclosure_xml('https://example.com/p.png?w=1&h=2'),
		'the query is not the extension'
	);
	pg_assert_same(
		'<enclosure url="https://example.com/files/talk.mp3" length="0" type="audio/mpeg"/>',
		pg_sw_feed_url_enclosure_xml('/files/talk.mp3', 'https://example.com'),
		'a site path is made absolute'
	);
	pg_assert_same('', pg_sw_feed_url_enclosure_xml('photo.jpg', 'https://example.com'), 'a relative name is not an address');
}

function test_form_feed_url_enclosure_unknown_type_left_out()
{
	pg_assert_same('', pg_sw_feed_url_enclosure_xml('https://picsum.photos/seed/x/600/400'), 'no extension');
	pg_assert_same('', pg_sw_feed_url_enclosure_xml('https://example.com/file.xyz'), 'an extension of no known type');
	pg_assert_same('', pg_sw_feed_url_enclosure_xml('   '), 'empty');
	pg_assert_same('', pg_sw_feed_url_enclosure_xml('javascript:alert(1).png'), 'not a web address');
}

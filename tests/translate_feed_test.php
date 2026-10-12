<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_rss_localize() in includes/translate/render.php: a page's
 * feed served in a language names that language and, in a target language,
 * leads to the pages in it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/render.php');

// A feed the way get_page.php writes one: CRLF, a channel head, two items.
function translate_feed_sample()
{
	return "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\r\n"
		. "<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\r\n"
		. "    <channel>\r\n"
		. "        <title>Shop</title>\r\n"
		. "        <link>https://example.com/shop</link>\r\n"
		. "        <description>Picked one by one.</description>\r\n"
		. "        <item>\r\n"
		. "            <title>Gift Box</title>\r\n"
		. "            <link>https://example.com/item/Gift_Box</link>\r\n"
		. "            <guid isPermaLink=\"true\">https://example.com/item/Gift_Box</guid>\r\n"
		. "            <enclosure url=\"https://example.com/gift.jpg\" length=\"1\" type=\"image/jpeg\"/>\r\n"
		. "        </item><item>\r\n"
		. "            <title>Note</title>\r\n"
		. "            <link>https://example.com/notes?r=AB12</link>\r\n"
		. "            <guid isPermaLink=\"false\">AB12</guid>\r\n"
		. "        </item>\r\n"
		. "    </channel>\r\n"
		. "</rss>";
}

// Stands in for pg_tr_rewrite_url(): puts /tr in front of the path.
function translate_feed_rewrite($value)
{
	return str_replace('https://example.com/', 'https://example.com/tr/', $value);
}

// The channel names its language once, after its description, on a line of
// its own with the description's indentation and the feed's line ending.
function test_translate_feed_adds_language_to_channel()
{
	$out = pg_tr_rss_localize(translate_feed_sample(), 'tr');

	pg_assert_contains("<description>Picked one by one.</description>\r\n        <language>tr</language>\r\n", $out, 'after the description');
	pg_assert_same(1, substr_count($out, '<language>'), 'once, not in the items');
}

// A feed that already names a language keeps it.
function test_translate_feed_keeps_existing_language()
{
	$feed = str_replace('<title>Shop</title>', '<title>Shop</title><language>en-us</language>', translate_feed_sample());
	$out = pg_tr_rss_localize($feed, 'tr');

	pg_assert_same($feed, $out, 'unchanged');
}

// A channel without a description gets the element right after <channel>.
function test_translate_feed_language_without_description()
{
	$feed = str_replace("        <description>Picked one by one.</description>\r\n", '', translate_feed_sample());
	$out = pg_tr_rss_localize($feed, 'de');

	pg_assert_contains('<channel><language>de</language>', $out, 'after the opening tag');
}

// Without a rewrite (the source language) the addresses stay as they are.
function test_translate_feed_source_keeps_addresses()
{
	$out = pg_tr_rss_localize(translate_feed_sample(), 'en');

	pg_assert_contains('<link>https://example.com/shop</link>', $out, 'channel link');
	pg_assert_contains('<guid isPermaLink="true">https://example.com/item/Gift_Box</guid>', $out, 'guid');
}

// In a target language the channel and item links and a permalink guid go
// through the rewrite; a guid that is not an address and an enclosure do not.
function test_translate_feed_rewrites_addresses()
{
	$out = pg_tr_rss_localize(translate_feed_sample(), 'tr', 'translate_feed_rewrite');

	pg_assert_contains('<link>https://example.com/tr/shop</link>', $out, 'channel link');
	pg_assert_contains('<link>https://example.com/tr/item/Gift_Box</link>', $out, 'item link');
	pg_assert_contains('<guid isPermaLink="true">https://example.com/tr/item/Gift_Box</guid>', $out, 'permalink guid');
	pg_assert_contains('<link>https://example.com/tr/notes?r=AB12</link>', $out, 'item link with a query');
	pg_assert_contains('<guid isPermaLink="false">AB12</guid>', $out, 'plain guid untouched');
	pg_assert_contains('<enclosure url="https://example.com/gift.jpg"', $out, 'enclosure untouched');
}

// Anything that is not an RSS document passes through unchanged.
function test_translate_feed_ignores_other_content()
{
	$html = '<!DOCTYPE html><html><head><link rel="canonical" href="https://example.com/"></head><body><link>x</link></body></html>';

	pg_assert_same($html, pg_tr_rss_localize($html, 'tr', 'translate_feed_rewrite'), 'html page');
	pg_assert_same('', pg_tr_rss_localize('', 'tr'), 'empty');
}

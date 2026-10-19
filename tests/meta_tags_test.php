<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the custom head meta tags in includes/fn/meta_tags.php: what a
 * stored list keeps, how it is encoded, which attribute a name is written
 * in, how the page's list meets the design's, the HTML, and which of the
 * generated tags a custom tag replaces.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The block get_page_content.php builds before the custom tags join it.
function meta_tags_test_generated_block()
{
	return '<meta name="google-site-verification" content="abc">' . "\n" . '        ' .
		'<meta property="og:description" name="description" content="Fresh coffee">' . "\n" .
		'        <meta property="og:title" content="Home">' . "\n" .
		'        <meta property="og:type" content="website">' . "\n" .
		'        <meta property="og:image" content="https://example.com/a.jpg">' . "\n" .
		'        <meta property="og:image:width" content="1200">' . "\n" .
		'        <meta property="og:image:height" content="630">' . "\n" .
		'        <meta property="og:image:alt" content="Home">' . "\n" .
		'        <meta name="twitter:card" content="summary_large_image">' . "\n" .
		'        ';
}

// Rows without a usable name or content are dropped; the rest are trimmed.
function test_meta_tags_normalize_keeps_only_writable_rows()
{
	$list = pg_meta_tags_normalize(array(
		array('name' => ' theme-color ', 'content' => ' #0d6efd '),
		array('name' => 'og:image', 'content' => 'https://example.com/b.jpg'),
		array('name' => '', 'content' => 'no name'),
		array('name' => 'no-content', 'content' => '   '),
		array('name' => '1starts-with-digit', 'content' => 'x'),
		array('name' => 'has space', 'content' => 'x'),
		array('name' => 'quote"name', 'content' => 'x'),
		array('name' => array('x'), 'content' => 'x'),
		array('content' => 'missing name'),
		'not a row',
	));

	pg_assert_same(array(
		array('name' => 'theme-color', 'content' => '#0d6efd'),
		array('name' => 'og:image', 'content' => 'https://example.com/b.jpg'),
	), $list);
}

// The names other settings write cannot be taken, in any case.
function test_meta_tags_normalize_drops_reserved_names()
{
	$list = pg_meta_tags_normalize(array(
		array('name' => 'Viewport', 'content' => 'width=device-width'),
		array('name' => 'description', 'content' => 'x'),
		array('name' => 'ROBOTS', 'content' => 'noindex'),
		array('name' => 'charset', 'content' => 'utf-8'),
		array('name' => 'keywords', 'content' => 'coffee'),
	));

	pg_assert_same(array(array('name' => 'keywords', 'content' => 'coffee')), $list);
}

// The stored JSON and the posted JSON are read; anything else is no list.
function test_meta_tags_normalize_reads_json_and_rejects_garbage()
{
	pg_assert_same(array(array('name' => 'fb:app_id', 'content' => '123')),
		pg_meta_tags_normalize('[{"name":"fb:app_id","content":"123"}]'));
	pg_assert_same(array(), pg_meta_tags_normalize(''));
	pg_assert_same(array(), pg_meta_tags_normalize('not json'));
	pg_assert_same(array(), pg_meta_tags_normalize('{"name":"x","content":"y"}'), 'an object is not a list of rows');
	pg_assert_same(array(), pg_meta_tags_normalize(null));
	pg_assert_same(array(), pg_meta_tags_normalize(42));
}

// Content is one line, cut to the limit; the number of rows is capped.
function test_meta_tags_normalize_limits_content_and_rows()
{
	$limits = pg_meta_tags_limits();

	$list = pg_meta_tags_normalize(array(array('name' => 'x-note', 'content' => "first\nsecond\t third")));
	pg_assert_same('first second third', $list[0]['content'], 'line breaks and tabs become spaces');

	$list = pg_meta_tags_normalize(array(array('name' => 'x-long', 'content' => str_repeat('ş', $limits['content'] + 50))));
	pg_assert_same($limits['content'], mb_strlen($list[0]['content'], 'UTF-8'), 'content cut to the limit in characters');

	$rows = array();
	for ($i = 0; $i < $limits['tags'] + 5; $i++) {
		$rows[] = array('name' => 'og:image', 'content' => 'https://example.com/' . $i . '.jpg');
	}
	$list = pg_meta_tags_normalize($rows);
	pg_assert_same($limits['tags'], count($list), 'rows capped');
	pg_assert_same('https://example.com/0.jpg', $list[0]['content'], 'repeated names are kept, first rows first');

	pg_assert_same(array(), pg_meta_tags_normalize(array(array('name' => 'x-bad', 'content' => "\xC3\x28"))), 'invalid UTF-8 is dropped');
}

// None is '', a list is compact JSON, and rows go from the end over the limit.
function test_meta_tags_encode()
{
	pg_assert_same('', pg_meta_tags_encode(array()));
	pg_assert_same('[{"name":"og:url","content":"https://example.com/ş"}]',
		pg_meta_tags_encode(array(array('name' => 'og:url', 'content' => 'https://example.com/ş'))));

	$limits = pg_meta_tags_limits();
	$rows   = array();
	for ($i = 0; $i < 3; $i++) {
		$rows[] = array('name' => 'x-' . $i, 'content' => str_repeat('a', (int)($limits['bytes'] / 2)));
	}
	$json = pg_meta_tags_encode($rows);
	pg_assert_true(strlen($json) <= $limits['bytes'], 'within the byte limit');
	pg_assert_same(1, count(json_decode($json, true)), 'trailing rows dropped');
}

// Open Graph namespaces are written as property, everything else as name.
function test_meta_tags_attribute()
{
	pg_assert_same('property', pg_meta_tags_attribute('og:image'));
	pg_assert_same('property', pg_meta_tags_attribute('Article:tag'));
	pg_assert_same('property', pg_meta_tags_attribute('fb:app_id'));
	pg_assert_same('name', pg_meta_tags_attribute('twitter:card'));
	pg_assert_same('name', pg_meta_tags_attribute('theme-color'));
	pg_assert_same('name', pg_meta_tags_attribute('ogx:thing'));
}

// A page tag replaces every design tag of its name; the design's come first.
function test_meta_tags_merge_page_wins_by_name()
{
	$design = array(
		array('name' => 'theme-color', 'content' => '#000'),
		array('name' => 'og:image', 'content' => 'https://example.com/design-1.jpg'),
		array('name' => 'og:image', 'content' => 'https://example.com/design-2.jpg'),
	);
	$page = array(
		array('name' => 'OG:IMAGE', 'content' => 'https://example.com/page.jpg'),
		array('name' => 'author', 'content' => 'Jane Cooper'),
	);

	pg_assert_same(array(
		array('name' => 'theme-color', 'content' => '#000'),
		array('name' => 'OG:IMAGE', 'content' => 'https://example.com/page.jpg'),
		array('name' => 'author', 'content' => 'Jane Cooper'),
	), pg_meta_tags_merge($design, $page));

	pg_assert_same($design, pg_meta_tags_merge($design, array()));
	pg_assert_same($page, pg_meta_tags_merge(null, $page));
}

// Values are escaped and each tag ends the line with the head's indent.
function test_meta_tags_html_escapes()
{
	$html = pg_meta_tags_html(array(
		array('name' => 'og:title', 'content' => 'Tom & "Jerry" <3'),
		array('name' => 'theme-color', 'content' => '#fff'),
	));

	pg_assert_same(
		'<meta property="og:title" content="Tom &amp; &quot;Jerry&quot; &lt;3">' . "\n" . '        ' .
		'<meta name="theme-color" content="#fff">' . "\n" . '        ',
		$html);
	pg_assert_same('', pg_meta_tags_html(array()));
}

// A custom og:image takes the generated picture and its sizes with it.
function test_meta_tags_drop_overridden_image_and_its_properties()
{
	$out = pg_meta_tags_drop_overridden(meta_tags_test_generated_block(), array(
		array('name' => 'og:image', 'content' => 'https://example.com/b.jpg'),
	));

	pg_assert_false(strpos($out, 'og:image') !== false, 'no generated og:image tag is left');
	pg_assert_contains('<meta property="og:title" content="Home">', $out);
	pg_assert_contains('<meta property="og:type" content="website">', $out);
	pg_assert_contains('<meta name="twitter:card" content="summary_large_image">', $out);
}

// A custom og:description leaves the description tag as a plain description.
function test_meta_tags_drop_overridden_splits_the_description()
{
	$out = pg_meta_tags_drop_overridden(meta_tags_test_generated_block(), array(
		array('name' => 'og:description', 'content' => 'Shared text'),
	));

	pg_assert_contains('<meta name="description" content="Fresh coffee">', $out);
	pg_assert_false(strpos($out, 'og:description') !== false, 'og:description left the description tag');
}

// Names are matched whole and in any case; verification codes add up.
function test_meta_tags_drop_overridden_matching()
{
	$block = meta_tags_test_generated_block();

	$out = pg_meta_tags_drop_overridden($block, array(array('name' => 'Twitter:Card', 'content' => 'summary')));
	pg_assert_false(strpos($out, 'twitter:card') !== false, 'twitter:card replaced');

	$out = pg_meta_tags_drop_overridden($block, array(array('name' => 'og:t', 'content' => 'x')));
	pg_assert_same($block, $out, 'og:t is not a prefix of og:title or og:type');

	$out = pg_meta_tags_drop_overridden($block, array(array('name' => 'google-site-verification', 'content' => 'other')));
	pg_assert_same($block, $out, 'the site verification tag stays beside another');

	pg_assert_same($block, pg_meta_tags_drop_overridden($block, array()), 'nothing to replace');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lines of Compare on the Waiting Content Changes screen
 * (includes/fn/content_proposals_compare.php): a change only in a text's
 * markup is shown, a change in its words keeps the plain line, and every
 * value is escaped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals_compare.php');

// The "On the site" and "Proposed" halves of a line, in that order.
function _content_proposals_compare_test_sides($line)
{
	$parts = explode('<div class="col-12 md:col-5">', $line);

	return array(isset($parts[1]) ? $parts[1] : '', isset($parts[2]) ? $parts[2] : '');
}

function test_content_proposals_compare_link_address_shown()
{
	$was = 'Order from <a href="https://shop.example.com/">our shop</a>.';
	$now = 'Order from <a href="https://shop-example.net/pay">our shop</a>.';

	pg_assert_true(pg_content_proposals_compare_markup_only($was, $now), 'same words, other link');

	list($site, $proposed) = _content_proposals_compare_test_sides(pg_content_proposals_compare_line('Text', $was, $now));

	pg_assert_true($site !== $proposed, 'the two sides print differently');
	pg_assert_contains('https://shop.example.com/', $site, 'the site side names its address');
	pg_assert_contains('https://shop-example.net/pay', $proposed, 'the proposed side names its address');
	pg_assert_contains('&lt;a href=&quot;https://shop-example.net/pay&quot;&gt;', $proposed, 'the HTML is escaped');
	pg_assert_false(strpos($proposed, '<a href') !== false, 'no raw link reaches the page');
}

function test_content_proposals_compare_bold_only_shown()
{
	$line = pg_content_proposals_compare_line('Text', 'Opening hours', '<strong>Opening</strong> hours');
	list($site, $proposed) = _content_proposals_compare_test_sides($line);

	pg_assert_contains('&lt;strong&gt;Opening&lt;/strong&gt; hours', $proposed);
	pg_assert_contains('Opening hours', $site);
	pg_assert_contains('<code', $proposed, 'shown as code');
}

function test_content_proposals_compare_words_differ_keep_plain_line()
{
	$was = 'Order from <a href="https://shop.example.com/">our shop</a>.';
	$now = 'Buy in <a href="https://shop.example.com/">our store</a>.';

	pg_assert_false(pg_content_proposals_compare_markup_only($was, $now));

	$line = pg_content_proposals_compare_line('Text', $was, $now);
	list($site, $proposed) = _content_proposals_compare_test_sides($line);

	pg_assert_false(strpos($line, '<code') !== false, 'no code block');
	pg_assert_contains('<span class="text-break">Order from our shop.</span>', $site);
	pg_assert_contains('<span class="text-break">Buy in our store.</span>', $proposed);
}

function test_content_proposals_compare_same_value_plain()
{
	pg_assert_false(pg_content_proposals_compare_markup_only('<b>x</b>', ' <b>x</b> '), 'only outer spaces');
	pg_assert_false(pg_content_proposals_compare_markup_only(array('a' => 1), array('a' => 2)), 'not text');
	pg_assert_false(pg_content_proposals_compare_markup_only(null, ''), 'both empty');
}

function test_content_proposals_compare_script_escaped()
{
	$was = 'Hello';
	$now = 'Hello<script>alert(1)</script>';

	// The words differ (strip_tags keeps the script's text): the plain line.
	$line = pg_content_proposals_compare_line('Text', $was, $now);
	pg_assert_false(stripos($line, '<script') !== false, 'plain line: no raw script');

	// The same words around a script: the code block, escaped.
	$line = pg_content_proposals_compare_line('Text', 'Hello', 'Hello<script></script>');
	pg_assert_contains('&lt;script&gt;&lt;/script&gt;', $line);
	pg_assert_false(stripos($line, '<script') !== false, 'code block: no raw script');

	$line = pg_content_proposals_compare_line('<b>Label</b>', '<img src=x onerror=alert(1)>', '<img src=y onerror=alert(1)>');
	pg_assert_false(stripos($line, '<img') !== false, 'no raw picture');
	pg_assert_contains('&lt;b&gt;Label&lt;/b&gt;', $line, 'the label is escaped');
}

function test_content_proposals_compare_picture_line_unchanged()
{
	$line = pg_content_proposals_compare_line('Picture', '/files/a.jpg', '/files/b.jpg', true);

	pg_assert_contains('<img src="/files/a.jpg"', $line);
	pg_assert_false(strpos($line, '<code') !== false);
}

function test_content_proposals_compare_markup_not_cut()
{
	$words = str_repeat('word ', 200);
	$line = pg_content_proposals_compare_line('Text', $words . '<a href="/a">end</a>', $words . '<a href="/zz-long-tail">end</a>');

	pg_assert_contains('/zz-long-tail', $line, 'a change past the words width is still shown');
}

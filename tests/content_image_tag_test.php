<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image tag rewrite after an image is saved under the same or a
 * new name (pg_update_image_in_content() in includes/fn/content.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_image_tag_size($name)
{
	return array(320, 240);
}

// A dollar amount in the tag's other attributes is kept as written.
function test_content_image_tag_dollar_kept()
{
	$content = '<p>Sale</p><img src="{path}deal.jpg" alt="$50 off, now $5" width="100" height="80" /><p>end</p>';
	$out = pg_update_image_in_content($content, 'deal.jpg', 'deal.jpg', 'pg_test_image_tag_size');

	pg_assert_same('<p>Sale</p><img src="{path}deal.jpg" alt="$50 off, now $5" width="320" height="240" /><p>end</p>', $out, 'same name');
}

// A backslash in the tag no longer breaks the match.
function test_content_image_tag_backslash()
{
	$content = '<img src="{path}a.png" title="C:\\pics" style="width: 10px; height: 20px;">';
	$out = pg_update_image_in_content($content, 'a.png', 'a.png', 'pg_test_image_tag_size');

	pg_assert_same('<img src="{path}a.png" title="C:\\pics" style="width: 320px; height: 240px;">', $out, 'backslash');
}

// A new name rewrites src, alt and title; another image is left alone.
function test_content_image_tag_renamed()
{
	$content = '<img src="{path}old.png" alt="old.png" title="old.png" width="1" height="1"><img src="{path}other.png" width="5">';
	$out = pg_update_image_in_content($content, 'old.png', 'new.png', 'pg_test_image_tag_size');

	pg_assert_same('<img src="{path}new.png" alt="new.png" title="new.png" width="320" height="240"><img src="{path}other.png" width="5">', $out, 'renamed');
}

// A size that cannot be read keeps the tag's own dimensions.
function test_content_image_tag_size_unknown()
{
	$content = '<img src="{path}gone.png" width="100" height="80">';
	$out = pg_update_image_in_content($content, 'gone.png', 'gone.png', function ($name) {
		return null;
	});

	pg_assert_same($content, $out, 'unchanged');
}

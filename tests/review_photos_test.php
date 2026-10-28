<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for photo reviews (includes/fn/reviews.php): which file names count
 * as a review photo, which switch lets a thread take a file, the content
 * check of an upload, and where the forms, add_comment.php, the settings and
 * the migration step ask them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function review_photos_test_file($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_review_photos_file_names()
{
	foreach (array('a.jpg', 'a.JPEG', 'shirt.photo.png', 'x.gif', 'y.webp') as $name) {
		pg_assert_true(pg_comment_file_is_image($name), $name);
	}
	foreach (array('a.pdf', 'jpg', 'a.jpg.php', 'a.svg', '', null, array('a.jpg')) as $name) {
		pg_assert_false(pg_comment_file_is_image($name), json_encode($name));
	}
}

// A review thread asks the site setting alone, any other the page's switch.
function test_review_photos_attachments_allowed()
{
	pg_assert_true(pg_comment_attachments_allowed(false, true, true), 'a review, setting on, page switch off');
	pg_assert_false(pg_comment_attachments_allowed(true, true, false), 'a review, setting off, page switch on');
	pg_assert_true(pg_comment_attachments_allowed(true, false, false), 'another thread keeps the page switch');
	pg_assert_false(pg_comment_attachments_allowed(false, false, true), 'the setting opens no other thread');
}

// The content must be an image of an allowed type, whatever the name says.
function test_review_photos_content_check()
{
	$dir = sys_get_temp_dir();
	$png = tempnam($dir, 'pgrp');
	file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
	$text = tempnam($dir, 'pgrp');
	file_put_contents($text, '<?php echo 1;');

	pg_assert_true(pg_review_photo_ok('photo.png', $png), 'a PNG named .png');
	pg_assert_true(pg_review_photo_ok('photo.jpg', $png), 'an image under another image ending');
	pg_assert_false(pg_review_photo_ok('photo.pdf', $png), 'an image under a name that is no image');
	pg_assert_false(pg_review_photo_ok('photo.png', $text), 'a renamed file that is no image');
	pg_assert_false(pg_review_photo_ok('photo.png', ''), 'no upload');
	pg_assert_false(pg_review_photo_ok('photo.png', $dir . '/pg-no-such-file'), 'a missing file');

	@unlink($png);
	@unlink($text);
}

function test_review_photos_wiring()
{
	$add = review_photos_test_file('add_comment.php');
	$decide = strpos($add, '$comments_allow_file_attachments = pg_comment_attachments_allowed(');
	$check = strpos($add, '!pg_review_photo_ok($_FILES[\'file\'][\'name\']');
	$redirect = strpos($add, '// if an error exists, then send user back to previous screen');
	$store = strpos($add, 'if (($comments_allow_file_attachments == 1) && isset($_FILES[\'file\'])');
	pg_assert_true(($decide !== false) && ($check !== false) && ($decide < $check) && ($check < $redirect) && ($redirect < $store), 'add_comment.php decides, checks the image, then stores');
	pg_assert_true(strpos($add, 'pg_comment_thread_verify(') < $decide, 'after the thread is known');

	pg_assert_contains("\$settings['attachments'] = pg_comment_attachments_allowed(\$settings['attachments'], pg_review_gate_applies(\$post_type, \$post_id), pg_review_photos_on());", review_photos_test_file('includes/fn/widgets_comments.php'), 'the comments widget');

	$page = review_photos_test_file('get_page_content.php');
	pg_assert_contains('$comments_file_field_on = pg_comment_attachments_allowed(', $page, 'the classic form');
	pg_assert_contains("if (!empty(\$comments_read['review']) && (\$output_file_attachment !== '') && pg_comment_file_is_image(", $page, 'the classic list shows a review photo');

	pg_assert_contains('pg_comment_file_is_image($file_name)', review_photos_test_file('edit_comment.php'), 'moderation');
	pg_assert_contains("pg_comment_file_is_image(\$comment['file_name'] ?? '')", review_photos_test_file('view_comments.php'), 'the moderation list');

	pg_assert_contains("define('REVIEW_PHOTOS', (int) (\$row['review_photos'] ?? 0) == 1);", review_photos_test_file('init.php'), 'the constant, off before the upgrade');
	pg_assert_contains("pg_schema_has('config', 'review_photos')", review_photos_test_file('includes/settings/commerce.save.php'), 'saved once the column is there');
	pg_assert_contains("\$review_photos_ready ? '", review_photos_test_file('includes/settings/commerce.php'), 'shown once the column is there');

	$migration = review_photos_test_file('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_review_photos();          // 11.0.57', $migration, 'the step is called');
	pg_assert_contains("install_add_column('config', 'review_photos', \"TINYINT(1) UNSIGNED NOT NULL DEFAULT 0\");", $migration, 'off by default, idempotent');
}

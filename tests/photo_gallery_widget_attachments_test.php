<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the files the Photo Gallery widget counts as photos
 * (includes/fn/widgets_photo_gallery.php): a file uploaded with a submitted
 * form or a comment (files.attachment = 1, signatures included) is never a
 * photo, even when its upload folder is the gallery or one of its albums.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The photo condition leaves out design files and attachments.
function test_photo_gallery_photo_sql_leaves_attachments_out()
{
	$sql = pg_gal_photo_sql(12);
	pg_assert_contains("folder = '12'", $sql, 'the folder');
	pg_assert_contains('type IN (' . pg_gal_image_types_sql() . ')', $sql, 'image types');
	pg_assert_contains("design = '0'", $sql, 'no design files');
	pg_assert_contains("attachment = '0'", $sql, 'no form or comment attachments');
	pg_assert_contains("folder = '0'", pg_gal_photo_sql('x'), 'an id is cast');
}

// Every files query of the widget reads through the photo condition: the
// album count, the album's first photo and the photos of the folder shown.
function test_photo_gallery_widget_reads_photos_through_the_condition()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_photo_gallery.php');
	$from = strpos($source, "\nfunction _pg_gal_read(");
	$to = strpos($source, "\n}\n", (int) $from);
	pg_assert_true($from !== false && $to !== false, '_pg_gal_read found');
	$body = substr($source, (int) $from, (int) $to - (int) $from);
	$queries = preg_match_all('/FROM files WHERE/', $body);
	$through = substr_count($body, 'FROM files WHERE " . pg_gal_photo_sql(');
	pg_assert_same(3, $queries, 'three files queries');
	pg_assert_same($queries, $through, 'each through pg_gal_photo_sql()');
}

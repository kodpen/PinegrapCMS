<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's versions: which saves the retention keeps
 * (the original plus the last 10), the stored file names, which documents a
 * version row may hold, which version a document was drawn on, and the
 * drawer's model section run under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Rows of versions 1..$count of one file, oldest first.
function image_editor_versions_rows($count)
{
	$rows = array();

	for ($n = 1; $n <= $count; $n++) {
		$rows[] = array('version_id' => 100 + $n, 'version_no' => $n);
	}

	return $rows;
}

// The original and the last 10 saves stay; the rest go, oldest first.
function test_image_editor_versions_retention_keeps_the_original_and_ten()
{
	pg_assert_same(array(), pg_file_versions_pruned(image_editor_versions_rows(11)), 'eleven rows: nothing goes');
	pg_assert_same(array(102), pg_file_versions_pruned(image_editor_versions_rows(12)), 'twelve rows: version 2 goes');
	pg_assert_same(array(102, 103, 104), pg_file_versions_pruned(image_editor_versions_rows(14)), 'fourteen rows: versions 2-4 go');
	pg_assert_same(array(), pg_file_versions_pruned(array()), 'no rows');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a translated picture whose source is in the object store: the
 * daily prune and the Pictures group's read do not take it for a picture
 * changed outside the editor, and its Languages list and draws read its
 * bytes through the cached copy.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function in a file under the software directory.
function picture_translation_stored_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// Bytes on the disk are compared with the version; a file whose bytes are
// only in the object store is judged by its size, never fetched.
function test_picture_translation_stored_holds_version()
{
	$newest = array('size' => '1200', 'doc_json' => '{"v":1}');
	$yes = function () {
		return true;
	};
	$no = function () {
		return false;
	};

	pg_assert_same(true, pg_picture_tr_holds_version($newest, '/files/a.png', null, $yes), 'bytes on the disk that match');
	pg_assert_same(false, pg_picture_tr_holds_version($newest, '/files/a.png', null, $no), 'bytes on the disk that differ');
	pg_assert_same(true, pg_picture_tr_holds_version($newest, '', 1200, $no), 'in the object store only, the size of the version');
	pg_assert_same(false, pg_picture_tr_holds_version($newest, '', 900, $yes), 'in the object store only, another size');
	pg_assert_same(false, pg_picture_tr_holds_version($newest, '', null, $yes), 'no bytes anywhere');
	pg_assert_same(false, pg_picture_tr_holds_version(array('size' => '1200', 'doc_json' => ''), '/files/a.png', null, $yes), 'a base row holds no document');
	pg_assert_same(false, pg_picture_tr_holds_version(null, '/files/a.png', null, $yes), 'no version');
}

// The prune's check reads the bytes this disk has without fetching; a
// draw and the Languages list read a stored source through its copy.
function test_picture_translation_stored_paths()
{
	$live = picture_translation_stored_body('includes/fn/picture_translation.php', 'pg_picture_tr_live');
	$doc = picture_translation_stored_body('includes/fn/picture_translation.php', 'pg_picture_tr_live_doc');

	pg_assert_contains('pg_file_present_path(', $live, 'the prune looks for a cached copy');
	pg_assert_contains('pg_picture_tr_holds_version(', $live, 'the prune judges through the pure rule');
	pg_assert_true(strpos($live, 'pg_file_local_copy(') === false, 'the prune fetches nothing');
	pg_assert_contains('pg_file_local_copy(', $doc, 'a draw reads a stored source through its copy');
}

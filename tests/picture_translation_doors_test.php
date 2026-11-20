<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the file doors of a language's picture: it follows its source
 * on a move (and stays put while the source is in the Recycle Bin), it goes
 * with the source on a delete for good, a source in a binned folder is
 * never swapped on a page, Disk Usage counts it as any files row, and the
 * daily sweep removes a picture whose source is gone and puts back what a
 * door missed.
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
function picture_translation_doors_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// A picture out of its source's folder is moved there; one in place, one
// whose source is binned and a row with no picture are left.
function test_picture_translation_doors_follow_moves()
{
	$rows = array(
		array('picture_id' => '10', 'picture_folder' => '3', 'source_folder' => '7', 'binned' => false),
		array('picture_id' => '11', 'picture_folder' => '7', 'source_folder' => '7', 'binned' => false),
		array('picture_id' => '12', 'picture_folder' => '3', 'source_folder' => '99', 'binned' => true),
		array('picture_id' => '0', 'picture_folder' => '0', 'source_folder' => '7', 'binned' => false),
	);

	pg_assert_same(array(10 => 7), pg_picture_tr_follow_moves($rows), 'only the picture out of place, source not binned');
	pg_assert_same(array(), pg_picture_tr_follow_moves(null), 'no rows, no moves');
}

// Every door that moves a file to another folder calls the follow after
// its write.
function test_picture_translation_doors_move_doors_follow()
{
	$doors = array(
		array('includes/explorer/handle_items.php', 'pg_explorer_handle_move', "UPDATE files SET"),
		array('includes/explorer/handle_file_window.php', null, "UPDATE files SET"),
		array('includes/explorer/handle_recycle.php', null, "UPDATE files SET folder = '\" . e(\$target_id)"),
		array('edit_file.php', null, "UPDATE files SET"),
		array('edit_files.php', null, "\"UPDATE files\n"),
	);

	foreach ($doors as $door) {
		$source = $door[1]
			? picture_translation_doors_body($door[0], $door[1])
			: (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $door[0]);
		$write = strpos($source, $door[2]);
		$follow = strpos($source, 'pg_picture_tr_follow(');

		pg_assert_true($write !== false, $door[0] . ': the move is found');
		pg_assert_true(($follow !== false) && ($follow > $write), $door[0] . ': the pictures follow after the move');
	}
}

// The follow reads only pictures out of place, asks the bin per source and
// marks the page cache when it moved one.
function test_picture_translation_doors_follow_reads()
{
	$body = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_follow');

	pg_assert_contains('p.folder <> s.folder', $body, 'only pictures out of place');
	pg_assert_contains('pg_folder_binned(', $body, 'a binned source keeps its picture where it is');
	pg_assert_contains('pg_picture_tr_follow_moves(', $body, 'the pure rule decides');
	pg_assert_contains('pg_page_cache_mark_dirty()', $body, 'pages drawn again after a move');
}

// A delete for good takes the rows and each language's picture: the files
// row, its versions and its bytes.
function test_picture_translation_doors_delete()
{
	$delete = picture_translation_doors_body('includes/fn/image_editor.php', 'pg_file_versions_delete_file');
	pg_assert_contains('pg_picture_tr_forget(', $delete, 'a deleted file forgets its languages');

	$forget = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_forget');
	pg_assert_contains('DELETE FROM file_translation', $forget, 'its rows');
	pg_assert_contains('pg_picture_tr_picture_delete(', $forget, 'each picture');

	$picture = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_picture_delete');
	pg_assert_contains('DELETE FROM files', $picture, 'the picture row');
	pg_assert_contains('pg_file_versions_delete_file(', $picture, 'its versions');
	pg_assert_contains('@unlink(', $picture, 'its bytes');

	$record = picture_translation_doors_body('includes/explorer/recycle_bin.php', 'pg_delete_file_record');
	pg_assert_contains('pg_file_versions_delete_file(', $record, 'the bin and a folder deleted for good');
}

// A file in a binned folder keeps its name, so its address still matches:
// it is neither swapped nor given a waiting row.
function test_picture_translation_doors_binned_not_swapped()
{
	$query = function ($sql) {
		if (strpos($sql, 'SELECT') === 0) {
			return array(
				array('id' => '1', 'name' => 'banner.jpg', 'folder' => '5', 'state' => '1', 'picture' => 'banner-en.jpg', 'has_uses' => '1'),
				array('id' => '2', 'name' => 'hero.jpg', 'folder' => '9', 'state' => '1', 'picture' => 'hero-en.jpg', 'has_uses' => '1'),
				array('id' => '3', 'name' => 'new.jpg', 'folder' => '9', 'state' => null, 'picture' => null, 'has_uses' => '1'),
			);
		}

		pg_assert_true(false, 'no waiting row for a binned picture');
		return true;
	};
	$binned = function ($folder) {
		return $folder === 9;
	};
	$hosts = array('example.com' => true);

	$out = pg_picture_tr_page_apply('<img src="/banner.jpg"><img src="/hero.jpg"><img src="/new.jpg">', 'en', '/', $hosts, false, $query, $binned);
	pg_assert_same('<img src="/banner-en.jpg"><img src="/hero.jpg"><img src="/new.jpg">', $out, 'the binned source shows itself');

	pg_assert_contains('f.folder', pg_picture_tr_page_sql(array('banner.jpg'), 'en'), 'the query gives the source folder');
	pg_assert_contains("'pg_folder_binned'", picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_page_pass'), 'the page pass asks the bin');
}

// Disk Usage counts every files row (a language's picture is one, its size
// written by the draw) and every version.
function test_picture_translation_doors_disk_usage()
{
	pg_assert_contains('pg_file_storage_usage()', picture_translation_doors_body('includes/fn/image_editor.php', 'pg_files_disk_usage'), 'the files rows');
	pg_assert_contains('FROM files GROUP BY storage', picture_translation_doors_body('includes/fn/file_storage.php', 'pg_file_storage_usage'), 'no row left out');

	$draw = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_keep_write');
	pg_assert_contains('pg_image_editor_record(', $draw, 'the draw writes the row with its size');
	pg_assert_contains('size', picture_translation_doors_body('includes/fn/image_editor.php', 'pg_image_editor_record'), 'the size kept');
}

// The daily sweep drops a picture whose source is gone, then puts back a
// picture a door left behind and a row whose picture is gone.
function test_picture_translation_doors_sweep()
{
	pg_assert_contains('pg_picture_tr_prune(', picture_translation_doors_body('includes/fn/image_editor.php', 'pg_file_versions_sweep'), 'the daily sweep');

	$prune = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_prune');
	pg_assert_contains('pg_picture_tr_forget(', $prune, 'a source gone takes its pictures');
	pg_assert_contains('pg_picture_tr_follow(null)', $prune, 'pictures out of place');
	pg_assert_contains('pg_picture_tr_lost()', $prune, 'rows whose picture is gone');
	pg_assert_same('deleted', pg_picture_tr_prune_reason(false, false, false), 'a source gone');

	$lost = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_lost');
	pg_assert_contains('p.id IS NULL', $lost, 'only a picture that is gone');
	pg_assert_contains('picture_file_id = 0, ft.state = 0', $lost, 'waits to be drawn again');
}

// A folder copy leaves the languages' pictures out.
function test_picture_translation_doors_folder_copy()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$at = strpos($source, "WHERE folder = '\" . e(\$source_id) . \"'\"");

	pg_assert_true($at !== false, 'the copy reads the folder files');
	pg_assert_contains("pg_picture_tr_hidden_sql('id')", substr($source, $at, 120), 'without the languages\' pictures');
}

// The prune goes on each day after the picture the last run stopped at, so
// a site with more pictures than one budget checks every one in turn.
function test_picture_translation_doors_prune_order()
{
	pg_assert_same(array(3, 9, 12), pg_picture_tr_prune_order(array(12, 3, 9), 0), 'from the start, in id order');
	pg_assert_same(array(12, 3, 9), pg_picture_tr_prune_order(array(3, 9, 12), 9), 'after the last one checked, then round');
	pg_assert_same(array(3, 9, 12), pg_picture_tr_prune_order(array(3, 9, 12), 12), 'the last one checked was the end');
	pg_assert_same(array(), pg_picture_tr_prune_order(array(), 5), 'nothing to check');

	pg_assert_same(0, pg_picture_tr_prune_cursor(array(3, 9, 12), 12, true), 'a whole pass starts again from the start');
	pg_assert_same(9, pg_picture_tr_prune_cursor(array(3, 9), 3, false), 'stopped: after the last one checked');
	pg_assert_same(5, pg_picture_tr_prune_cursor(array(), 5, false), 'stopped before any: where it was');
}

// The catch-ups are two queries and run before the budget is spent on the
// pictures; pages are marked once a picture they may name is gone, and the
// mark is checked whatever the budget left.
function test_picture_translation_doors_prune_catch_ups()
{
	$prune = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_prune');
	$follow = strpos($prune, 'pg_picture_tr_follow(null)');
	$loop = strpos($prune, 'foreach (pg_picture_tr_prune_order(');

	pg_assert_true(($follow !== false) && ($loop !== false) && ($follow < $loop), 'the follow before the pictures');
	pg_assert_true(strpos($prune, 'pg_picture_tr_lost()') < $loop, 'the lost rows before the pictures');
	pg_assert_contains('pg_page_cache_mark_dirty()', $prune, 'pages naming a picture now gone are drawn again');
	pg_assert_false((bool) preg_match('~if \(microtime\(true\) <= \$deadline\) \{\s*pg_picture_tr_mark_check\(\)~', $prune), 'the mark is checked after any budget');

	$lost = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_lost');
	pg_assert_contains('mysqli_affected_rows(', $lost, 'how many rows lost their picture');
}

// A source in the Recycle Bin loses its rows and pictures at the prune but
// keeps its texts: restored, its next visit in a language writes a waiting
// row again (the page pass asks for texts) and it is drawn anew. A source
// deleted or changed outside the editor loses its texts too.
function test_picture_translation_doors_binned_keeps_texts()
{
	$forget = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_forget');
	pg_assert_contains('function pg_picture_tr_forget($file_id, $keep_texts = false)', $forget, 'the texts kept on request');
	pg_assert_contains('!$keep_texts', $forget, 'the uses deleted only when not kept');

	$prune = picture_translation_doors_body('includes/fn/picture_translation.php', 'pg_picture_tr_prune');
	pg_assert_contains("pg_picture_tr_forget(\$file_id, \$reason === 'binned')", $prune, 'the prune keeps a binned source\'s texts');

	$extract = picture_translation_doors_body('includes/translate/extract.php', 'pg_tr_extract_pictures');
	pg_assert_contains("pg_picture_tr_forget(\$file_id, \$reason === 'binned')", $extract, 'so does the Pictures group\'s read');

	pg_assert_same(array('map' => array(), 'waiting' => array(7)), pg_picture_tr_page_plan(array(array('id' => '7', 'name' => 'a.png', 'state' => null, 'picture' => null, 'has_uses' => '1')), false), 'restored with its texts, a visit writes its waiting row');
}

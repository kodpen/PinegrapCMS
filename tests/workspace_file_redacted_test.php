<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a redacted picture replaced in a Workspace message: the earlier
 * version it replaced is listed only for the person who made the edit, and
 * the editor's render says when it burnt a redaction in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// A redacted edit hides its own earlier version and every older one from
// all but its editor: each of them still shows what the redaction covers.
// Rows come newest first, as ws_file_history() reads them.
function test_workspace_file_redacted_history_rows()
{
	$ids = function ($rows) { return array_map(function ($row) { return (int) $row['id']; }, $rows); };
	// O posted; a plain edit O -> E1 by 4; a redacted edit E1 -> R by 7.
	$rows = array(
		array('id' => 2, 'edited_by' => 7, 'redacted' => 1),
		array('id' => 1, 'edited_by' => 4, 'redacted' => 0));

	pg_assert_same(array(), $ids(ws_file_edits_visible($rows, 9)), 'a reader sees no earlier version');
	pg_assert_same(array(), $ids(ws_file_edits_visible($rows, 4)), 'nor does the plain editor');
	pg_assert_same(array(), $ids(ws_file_edits_visible($rows, 0)), 'nor no viewer');
	pg_assert_same(array(2, 1), $ids(ws_file_edits_visible($rows, 7)), 'the person who redacted sees both');

	// A plain edit R -> E2 after the redaction is listed for everyone.
	array_unshift($rows, array('id' => 3, 'edited_by' => 4, 'redacted' => 0));
	pg_assert_same(array(3), $ids(ws_file_edits_visible($rows, 9)), 'a later plain edit is listed for a reader');
	pg_assert_same(array(3, 2, 1), $ids(ws_file_edits_visible($rows, 7)), 'and all of them for the person who redacted');

	// Rows without a redaction, or from before the column, are listed as before.
	$plain = array(array('id' => 5, 'edited_by' => 4), array('id' => 4, 'edited_by' => 4, 'redacted' => '0'));
	pg_assert_same(array(5, 4), $ids(ws_file_edits_visible($plain, 9)), 'plain rows are listed for everyone');
}

// The render answers whether it burnt a redaction in.
function test_workspace_file_redacted_render_says_so()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$folder = sys_get_temp_dir() . '/pgwsredact' . uniqid();
	mkdir($folder);
	$path = $folder . '/picture.png';
	$image = imagecreatetruecolor(120, 80);
	imagepng($image, $path);
	pg_image_destroy($image);

	$mask = imagecreatetruecolor(20, 20);
	ob_start();
	imagepng($mask);
	pg_image_destroy($mask);
	$mask = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
	$doc = array(
		'v' => 1,
		'source' => array('fileId' => 3, 'width' => 120, 'height' => 80),
		'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
		'adjust' => array(),
		'filter' => 'none',
		'layers' => array());

	try {
		$plain = pg_image_editor_render($path, array('doc' => $doc));
		pg_assert_same('', $plain['error'], 'a plain render');
		pg_assert_false($plain['redacted'], 'burnt nothing in');

		$redacted = pg_image_editor_render($path, array('doc' => $doc, 'redact' => array(array('mode' => 'solid', 'strength' => 50, 'x' => 0, 'y' => 0, 'mask' => $mask))));
		pg_assert_same('', $redacted['error'], 'a redacted render');
		pg_assert_true($redacted['redacted'], 'says it burnt a redaction in');
	} finally {
		foreach (glob($folder . '/*') as $file) {
			@unlink($file);
		}

		@rmdir($folder);
	}
}

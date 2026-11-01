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

// A redacted edit's earlier version is the editor's alone; any other edit is
// listed for everyone who reads the channel.
function test_workspace_file_redacted_history_rows()
{
	$plain = array('edited_by' => 4, 'redacted' => 0);
	$redacted = array('edited_by' => 4, 'redacted' => 1);
	$before = array('edited_by' => 4);

	pg_assert_true(ws_file_edit_visible($plain, 9), 'a plain edit is listed for another reader');
	pg_assert_true(ws_file_edit_visible($before, 9), 'a row from before the column is listed');
	pg_assert_true(ws_file_edit_visible($redacted, 4), 'a redacted edit is listed for its editor');
	pg_assert_false(ws_file_edit_visible($redacted, 9), 'and for nobody else');
	pg_assert_false(ws_file_edit_visible(array('edited_by' => '4', 'redacted' => '1'), 0), 'nor for no viewer');
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

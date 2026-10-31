<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Edit File and Image Editor links of All Files, Design Files,
 * Edit File and Edit Design File: send_to and file_name are URL-encoded, so a
 * list address with '&' (a view change gives ?filter=default&filter=my_photos)
 * or a legacy file name with '&', '+' or '#' reaches the target whole.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_list_links_encoding_source($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

function test_file_lists_encode_send_to_and_file_name()
{
	$cases = array(
		'view_files.php' => array(
			"/image_editor_edit.php?file_name=' . h(urlencode(\$file['name'])) . '&send_to=' . h(escape_javascript(urlencode(REQUEST_URL))) . '",
			"/edit_file.php?id=' . \$file['id'] . '&send_to=' . h(escape_javascript(urlencode(REQUEST_URL))) . '",
		),
		'view_design_files.php' => array(
			"/image_editor_edit.php?file_name=' . h(urlencode(\$file['name'])) . '&send_to=' . h(escape_javascript(urlencode(REQUEST_URL))) . '",
			"/edit_design_file.php?id=' . \$file['id'] . '&send_to=' . h(escape_javascript(urlencode(REQUEST_URL))) . '",
		),
	);

	foreach ($cases as $file => $needles) {
		$source = file_list_links_encoding_source($file);

		foreach ($needles as $needle) {
			pg_assert_true(strpos($source, $needle) !== false, $file . ' encodes ' . $needle);
		}

		pg_assert_true(strpos($source, "send_to=' . h(escape_javascript(REQUEST_URL))") === false, $file . ' has no unencoded send_to');
		pg_assert_true(strpos($source, "file_name=' . h(\$file['name'])") === false, $file . ' has no unencoded file_name');
	}
}

function test_edit_screens_encode_the_image_editor_link()
{
	foreach (array('edit_file.php', 'edit_design_file.php') as $file) {
		$source = file_list_links_encoding_source($file);

		pg_assert_true(strpos($source, "/image_editor_edit.php?file_name=' . h(urlencode(\$file_name)) . '&send_to=' . h(urlencode(REQUEST_URL)) . '") !== false, $file . ' encodes the Image Editor link');
		pg_assert_true(strpos($source, "send_to=' . h(escape_javascript(REQUEST_URL))") === false, $file . ' has no unencoded send_to');
	}
}

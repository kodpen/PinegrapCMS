<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A text file that is not UTF-8 in the File Manager's Edit File window
 * (#8988): the window opens with no text and a note, saves no text over the
 * file, and a panel answer json_encode() refuses is no longer an empty 200
 * body (pg_panel_answer_json(), api.php's respond()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// UTF-8 with or without a byte order mark is shown; Windows-1254 is not.
function test_explorer_text_is_utf8()
{
	pg_assert_true(pg_explorer_text_is_utf8(''), 'empty');
	pg_assert_true(pg_explorer_text_is_utf8("plain ascii\n"), 'ascii');
	pg_assert_true(pg_explorer_text_is_utf8("T\xC3\xBCrk\xC3\xA7e \xC5\x9F\xC4\x9F\xC4\xB1"), 'Turkish in UTF-8');
	pg_assert_true(pg_explorer_text_is_utf8("\xEF\xBB\xBFwith a BOM"), 'byte order mark');
	pg_assert_false(pg_explorer_text_is_utf8("T\xFCrk\xE7e \xFE\xF0\xFD"), 'Windows-1254');
	pg_assert_false(pg_explorer_text_is_utf8("abc\xC3"), 'cut in the middle of a letter');
}

// The handler reads the text through the check and flags the file; the
// window shows the note and sends no text back.
function test_explorer_file_window_flags_text_that_is_not_utf8()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_window.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains("\$payload['content_not_utf8'] = false;", $handler, 'default');
	pg_assert_contains("if (pg_explorer_text_is_utf8(\$content)) {\n                \$payload['content'] = \$content;\n            } else {\n                \$payload['content_not_utf8'] = true;\n            }", $handler, 'flagged, no text');

	pg_assert_contains("} else if (file.content_not_utf8) {\n                note.textContent = L.file_not_utf8;", $screen, 'the window says so');
	pg_assert_contains('if (file.editable && file.on_disk && !file.content_too_large && !file.content_not_utf8) {', $screen, 'no text is saved over the file');
	pg_assert_contains("'file_not_utf8' => lang(", $screen);
}

// A refused value is written with U+FFFD; one JSON cannot hold at all is an
// error answer; a plain one is written exactly as encode_json() writes it.
function test_panel_answer_json_never_empty()
{
	$plain = array('status' => 'success', 'path' => 'a/b', 'name' => "T\xC3\xBCrk\xC3\xA7e");

	pg_assert_same(encode_json($plain), pg_panel_answer_json($plain), 'unchanged when it can be written');

	$broken = pg_panel_answer_json(array('status' => 'success', 'file' => array('content' => "T\xFCrk\xE7e")));
	$decoded = json_decode($broken, true);

	pg_assert_true(is_array($decoded), 'valid JSON');
	pg_assert_same('success', $decoded['status']);
	pg_assert_same("T\xEF\xBF\xBDrk\xEF\xBF\xBDe", $decoded['file']['content'], 'broken bytes as U+FFFD');

	$impossible = json_decode(pg_panel_answer_json(array('status' => 'success', 'value' => INF)), true);

	pg_assert_true(is_array($impossible), 'an error, not an empty body');
	pg_assert_same('error', $impossible['status']);
	pg_assert_true($impossible['message'] !== '', 'with a message');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api.php');
	pg_assert_contains("function respond(\$response)\n{\n    echo pg_panel_answer_json(\$response);\n    exit;\n}", $api, 'respond() writes through it');
}

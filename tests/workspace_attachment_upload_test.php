<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Workspace channel and note attachments sent as a form part
 * (includes/workspace/files.php, actions.php, assets/js/workspace.js): the
 * one check a part goes through on every door, the base64 body still taken,
 * and the screen's two doors on the shared XMLHttpRequest upload with its
 * progress and Cancel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

// The part of a source file from $start up to $end (or the end of the file).
function ws_attachment_upload_test_between($source, $start, $end)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$to = strpos($source, $end, $from + strlen($start));

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// A part over the server's limit, one that did not arrive whole, one that is
// not an upload of this request and one over the channel's limit are refused
// with the API's sentences; one at the limit passes.
function test_ws_upload_part_error()
{
	$max = 5 * 1048576;
	$too_large = lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => 5));
	$unreadable = lang('The file could not be read.');

	pg_assert_same($too_large, ws_upload_part_error(UPLOAD_ERR_INI_SIZE, false, 0, $max), 'over upload_max_filesize');
	pg_assert_same($too_large, ws_upload_part_error(UPLOAD_ERR_FORM_SIZE, false, 0, $max), 'over MAX_FILE_SIZE');
	pg_assert_same($unreadable, ws_upload_part_error(UPLOAD_ERR_PARTIAL, false, 0, $max), 'partial');
	pg_assert_same($unreadable, ws_upload_part_error(UPLOAD_ERR_NO_FILE, false, 0, $max), 'no file');
	pg_assert_same($unreadable, ws_upload_part_error(UPLOAD_ERR_OK, false, 10, $max), 'not an upload');
	pg_assert_same($too_large, ws_upload_part_error(UPLOAD_ERR_OK, true, $max + 1, $max), 'over the channel limit');
	pg_assert_same('', ws_upload_part_error(UPLOAD_ERR_OK, true, $max, $max), 'at the limit');
}

// A form part is never held to more than a base64 body is.
function test_ws_upload_form_max_bytes()
{
	pg_assert_true(ws_upload_form_max_bytes() > 0, 'a limit');
	pg_assert_true(ws_upload_form_max_bytes() <= ws_upload_max_bytes(), 'not above the channel limit');
}

// Without a part the base64 body of older screens and scripts is read as
// before: the same bytes and name go on to the same store.
function test_ws_upload_request_file_base64()
{
	$saved = $_FILES;
	$_FILES = array();

	$file = ws_upload_request_file(array('name' => 'note.txt', 'data' => 'data:text/plain;base64,' . base64_encode('hello')));

	pg_assert_true($file['ok'], 'taken');
	pg_assert_same('note.txt', $file['name'], 'name');
	pg_assert_same('hello', $file['binary'], 'bytes');
	pg_assert_same(ws_upload_decode('data:text/plain;base64,' . base64_encode('hello')), $file['binary'], 'as ws_upload_decode() reads it');

	$file = ws_upload_request_file(array('name' => 'note.txt', 'data' => '%%%'));

	pg_assert_true($file['ok'], 'left to the store to refuse');
	pg_assert_same(false, $file['binary'], 'undecodable');

	$_FILES = $saved;
}

// A part PHP refused, or a temporary file that is not this request's upload,
// is refused before anything is stored.
function test_ws_upload_request_file_part_refused()
{
	$saved = $_FILES;

	$_FILES = array('file' => array('name' => 'big.mp4', 'type' => 'video/mp4', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0));
	$file = ws_upload_request_file(array('name' => 'big.mp4'));

	pg_assert_false($file['ok'], 'over the server limit');
	pg_assert_contains('MB', $file['error'], 'says the limit');

	$_FILES = array('file' => array('name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK, 'size' => 10));
	$file = ws_upload_request_file(array('name' => 'a.txt'));

	pg_assert_false($file['ok'], 'not an upload of this request');
	pg_assert_same(lang('The file could not be read.'), $file['error'], 'unreadable');

	$_FILES = $saved;
}

// The panel's two doors and the external API read a part through one check,
// and both doors store the bytes with the function base64 reaches too.
function test_ws_upload_receivers_share_one_check()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$attach = ws_attachment_upload_test_between($actions, "case 'ws_attach':", "case 'ws_library_browse':");
	$note = ws_attachment_upload_test_between($actions, "case 'ws_note_file':", "case 'ws_note_claude':");

	pg_assert_contains('ws_upload_request_file($request)', $attach, 'ws_attach reads the request through the shared reader');
	pg_assert_contains('ws_message_send_file_bytes(', $attach, 'ws_attach stores the bytes');
	pg_assert_contains('ws_upload_request_file($request)', $note, 'ws_note_file reads the request through the shared reader');
	pg_assert_contains('ws_note_file_store_bytes(', $note, 'ws_note_file stores the bytes');

	$files = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
	$reader = ws_attachment_upload_test_between($files, 'function ws_upload_request_file(', "\n}\n");

	pg_assert_contains('ws_upload_part_error(', $reader, 'the reader checks a part');
	pg_assert_contains('ws_upload_form_max_bytes()', $reader, 'with the form limit');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');
	$input = ws_attachment_upload_test_between($api, 'function ws_api_upload_input(', "\n}\n");

	pg_assert_contains('ws_upload_part_error(', $input, 'the API checks a part the same way');
	pg_assert_contains('ws_upload_form_max_bytes()', $input, 'with the same limit');

	// The screen checks sizes against the limit of the way it sends them.
	pg_assert_contains("'upload'      => array('max' => ws_upload_form_max_bytes()", $actions, 'boot limit');

	// api.php reads a multipart body's fields as the request.
	$entry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api.php');

	pg_assert_contains('multipart/form-data', $entry, 'api.php knows a form body');
}

// Both doors of the screen send through the shared upload, which reports
// progress and can be aborted; no file is read into base64 on them.
function test_ws_attachment_doors_use_the_shared_upload()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$helper = ws_attachment_upload_test_between($js, '    function apiUpload(', "\n    }\n");

	pg_assert_contains('new XMLHttpRequest()', $helper, 'XMLHttpRequest');
	pg_assert_contains("xhr.upload.addEventListener('progress'", $helper, 'progress');
	pg_assert_contains("addEventListener('abort'", $helper, 'abort');
	pg_assert_contains('new FormData()', $helper, 'a form');
	pg_assert_contains('CFG.api_url', $helper, 'to the panel API');

	$card = ws_attachment_upload_test_between($js, '    function uploadCard(', "\n    }\n");

	pg_assert_contains("t('upload_cancel')", $card, 'Cancel upload');
	pg_assert_contains('progressBar(', $card, 'the shared bar');
	pg_assert_contains("t('upload_sent'", $card, 'bytes sent');

	$channel = ws_attachment_upload_test_between($js, '        uploadFiles: function (files, options) {', '        bindDrop: function () {');
	$note = ws_attachment_upload_test_between($js, '        function attach(files) {', '        function recordVoice() {');

	foreach (array('channel' => $channel, 'note' => $note) as $door => $source) {
		pg_assert_true($source !== '', $door . ' door found');
		pg_assert_contains('apiUpload(', $source, $door . ' uses the shared upload');
		pg_assert_contains('uploadCard(', $source, $door . ' shows the progress card');
		pg_assert_contains('.xhr.abort()', $source, $door . ' can be cancelled');
		pg_assert_false(strpos($source, 'readAsDataURL') !== false, $door . ' reads no base64');
	}

	pg_assert_contains("apiUpload('ws_attach'", $channel, 'channel action');
	pg_assert_contains("apiUpload('ws_note_file'", $note, 'note action');
}

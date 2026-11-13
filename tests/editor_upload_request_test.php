<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rich text editor's Upload tab (editor_add_file.php, #9119): the token
 * is read from the query string CKEditor posts to, without a warning for the
 * body field it never sends; folder_id is read only when the upload address
 * has one; every answer goes through one escaped helper.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The token in the query string or the body passes; none, another one or a
// session without one does not.
function test_editor_upload_token_ok()
{
	pg_assert_true(pg_editor_upload_token_ok('abc123', array(), array('token' => 'abc123')), 'in the query string, as CKEditor sends it');
	pg_assert_true(pg_editor_upload_token_ok('abc123', array('token' => 'abc123'), array()), 'in the body');
	pg_assert_true(pg_editor_upload_token_ok('abc123', array('token' => 'other'), array('token' => 'abc123')), 'a wrong body field and the right query string');

	pg_assert_false(pg_editor_upload_token_ok('abc123', array(), array()), 'no token at all');
	pg_assert_false(pg_editor_upload_token_ok('abc123', array(), array('token' => 'abc12')), 'another token');
	pg_assert_false(pg_editor_upload_token_ok('abc123', array(), array('token' => array('abc123'))), 'a list instead of a token');
	pg_assert_false(pg_editor_upload_token_ok('', array('token' => ''), array('token' => '')), 'a session without a token');
	pg_assert_false(pg_editor_upload_token_ok(null, array(), array('token' => '')), 'no session token');
	pg_assert_false(pg_editor_upload_token_ok('abc123', null, null), 'no request arrays');
}

// A positive whole number, else 0 (left out, blank, a list, a sign, text).
function test_editor_upload_folder_request()
{
	pg_assert_same(12, pg_editor_upload_folder_request(array('folder_id' => '12')), 'a folder');
	pg_assert_same(7, pg_editor_upload_folder_request(array('folder_id' => ' 7 ')), 'spaces around it');

	foreach (array(array(), array('folder_id' => ''), array('folder_id' => '0'), array('folder_id' => '-3'), array('folder_id' => '3abc'), array('folder_id' => '1.5'), array('folder_id' => array('4')), array('folder_id' => '007')) as $get) {
		pg_assert_same(0, pg_editor_upload_folder_request($get), json_encode($get));
	}

	pg_assert_same(0, pg_editor_upload_folder_request(null), 'no query string');
}

// The script reads the request only through the helpers, so a screen without
// a folder and CKEditor's body without a token raise no warning, and every
// answer escapes the function number, the address and the message.
function test_editor_add_file_reads_the_request_through_the_helpers()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/editor_add_file.php');

	pg_assert_contains("pg_editor_upload_token_ok(\$_SESSION['software']['token'] ?? '', \$_POST, \$_GET)", $src, 'the token check');
	pg_assert_contains('$folder_id = pg_editor_upload_folder_request($_GET);', $src, 'the folder');
	pg_assert_contains('if (($folder_id === 0) || (check_edit_access($folder_id) == false)) {', $src, 'the access check gets the whole number');

	pg_assert_false(strpos($src, "\$_POST['token']") !== false, 'no raw body token');
	pg_assert_false(strpos($src, "(\$_GET['folder_id'])") !== false, 'no raw folder id');
	pg_assert_same(1, substr_count($src, 'CKEDITOR.tools.callFunction('), 'one place writes the answer');

	$helper = substr($src, (int) strpos($src, 'function editor_add_file_answer('), 600);

	pg_assert_contains("isset(\$_GET['CKEditorFuncNum']) && is_scalar(\$_GET['CKEditorFuncNum'])", $helper, 'the function number without a warning');
	pg_assert_contains('escape_javascript($function_number)', $helper);
	pg_assert_contains('escape_javascript($url)', $helper);
	pg_assert_contains('escape_javascript($message)', $helper);
	pg_assert_contains('exit();', $helper, 'the answer ends the script');
}

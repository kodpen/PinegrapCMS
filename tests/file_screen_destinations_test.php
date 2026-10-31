<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where Edit File and Upload File (and Upload Design Files) go
 * after Save & Return, Delete, rotate, convert, duplicate, the back arrow and
 * a finished upload: the send_to they were opened with (a filtered list, the
 * dashboard), never a bare list or a raw request value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a panel screen.
function file_screen_destinations_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_edit_file_save_and_return_and_delete_follow_send_to()
{
	$source = file_screen_destinations_source('edit_file.php');

	pg_assert_same(false, strpos($source, "PATH . SOFTWARE_DIRECTORY . '/view_files.php');"), 'no redirect goes to the bare Files list');
	pg_assert_same(2, substr_count($source, "header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(pg_send_to_url(''), PATH . SOFTWARE_DIRECTORY . '/view_files.php'));"), 'Save & Return and Delete go to send_to, else the Files list');
}

function test_edit_file_reloads_keep_send_to()
{
	$source = file_screen_destinations_source('edit_file.php');

	pg_assert_same(0, preg_match("~/edit_file\\.php\\?id=' \\. (urlencode\\(\\\$_POST\\['id'\\]\\)|urlencode\\(\\\$new_id\\)|\\\$new_file_id)\\);~", $source), 'every reload of Edit File carries send_to');
	pg_assert_same(6, substr_count($source, "'&send_to=' . urlencode(pg_send_to_url(''))"), 'Save, rotate (twice), convert (twice) and duplicate keep send_to');
	pg_assert_true(strpos($source, "'&send_to=' . urlencode(\$_POST['send_to'])") === false, 'Save reads send_to through pg_send_to_url()');

	$start = strpos($source, 'id="edit_file_rotate"');
	pg_assert_true($start !== false, 'the rotate form exists');
	$form = substr($source, $start, strpos($source, '</form>', $start) - $start);
	pg_assert_true(strpos($form, 'name="send_to"') !== false, 'the rotate form posts send_to');
}

function test_edit_file_files_crumb_stays_on_the_files_list()
{
	$source = file_screen_destinations_source('edit_file.php');

	pg_assert_true(strpos($source, "array('label' => lang('Files'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_files.php'))") !== false, 'the Files crumb keeps a filtered Files list only');
}

function test_upload_screens_back_arrow_and_success_follow_send_to()
{
	$cases = array(
		'add_file.php' => array('view_files.php', 'All My Files'),
		'add_design_file.php' => array('view_design_files.php', 'All Design Files'),
	);

	foreach ($cases as $file => $case) {
		list($list, $label) = $case;
		$source = file_screen_destinations_source($file);

		pg_assert_true(strpos($source, "'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('" . $list . "'))") !== false, $file . ': the back arrow goes to send_to');
		pg_assert_true(strpos($source, "array('label' => lang('" . $label . "'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/" . $list . "'))") !== false, $file . ': the list crumb keeps a filtered list only');
		pg_assert_true(strpos($source, '$("#send_to").val()') === false, $file . ': the finished upload does not follow the raw request value');
		pg_assert_true(strpos($source, "window.location = ' . json_encode(pg_send_to_url('" . $list . "'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';") !== false, $file . ': the finished upload goes to the server-checked send_to');
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where Edit File and Upload File (and Upload Design Files) go
 * after Save & Return, Delete, rotate, convert, duplicate, the back arrow and
 * a finished upload: the send_to they were opened with (a File Manager view,
 * a filtered list, the dashboard), never a raw request value; without one, the
 * File Manager's Files view.
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

	pg_assert_same(false, strpos($source, 'view_files.php'), 'nothing goes to the retired Files list');
	pg_assert_same(2, substr_count($source, "header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(pg_send_to_url(''), PATH . SOFTWARE_DIRECTORY . '/view_folders.php?view=files'));"), 'Save & Return and Delete go to send_to, else the File Manager\'s Files view');
	pg_assert_same(2, substr_count($source, "new liveform('view_folders');"), 'their notices are left for the File Manager');
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

function test_edit_file_crumb_stays_on_the_file_manager()
{
	$source = file_screen_destinations_source('edit_file.php');

	pg_assert_true(strpos($source, "array('label' => lang('File Manager'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php'))") !== false, 'the crumb keeps the File Manager view it came from only');
}

function test_upload_screens_back_arrow_and_success_follow_send_to()
{
	// The screen, where it goes without send_to, its list crumb (label, list).
	$cases = array(
		'add_file.php' => array('view_folders.php?view=files', 'File Manager', 'view_folders.php'),
		'add_design_file.php' => array('view_design_files.php', 'All Design Files', 'view_design_files.php'),
	);

	foreach ($cases as $file => $case) {
		list($back, $label, $list) = $case;
		$source = file_screen_destinations_source($file);

		pg_assert_true(strpos($source, "'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('" . $back . "'))") !== false, $file . ': the back arrow goes to send_to');
		pg_assert_true(strpos($source, "array('label' => lang('" . $label . "'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/" . $list . "'))") !== false, $file . ': the list crumb keeps a filtered list only');
		pg_assert_true(strpos($source, '$("#send_to").val()') === false, $file . ': the finished upload does not follow the raw request value');
		pg_assert_true(strpos($source, "window.location = ' . json_encode(pg_send_to_url('" . $back . "'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';") !== false, $file . ': the finished upload goes to the server-checked send_to');
	}
}

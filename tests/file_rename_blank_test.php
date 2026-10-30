<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the blank-name refusal of the file renames: Edit File, Edit
 * Design File and the File Manager's file window refuse a name that is empty
 * or only spaces before prepare_file_name() turns it into 'file'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_rename_blank_source($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

// Uploads and imports rely on the default, so the refusal belongs in the
// rename screens, not in prepare_file_name().
function test_prepare_file_name_keeps_its_default_for_a_blank_name()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');

	pg_assert_same('file', prepare_file_name('   '), 'a name of spaces becomes file');
	pg_assert_same('file', prepare_file_name(''), 'an empty name becomes file');
}

function test_edit_file_refuses_a_blank_name_after_delete()
{
	$source = file_rename_blank_source('edit_file.php');
	$check = "if (trim((string) \$_POST['name']) === '') {\n        output_error(lang('Please enter a name.') . ' <a href=\"javascript:history.go(-1)\">' . lang('Go back') . '</a>.');\n    }";

	$delete = strpos($source, "    // DELETE\n    if (!empty(\$_POST['delete'])) {");
	$blank = strpos($source, $check);
	$rename = strpos($source, 'check_name_availability(');

	pg_assert_true($delete !== false, 'the delete branch exists');
	pg_assert_true($blank !== false, 'a blank name is refused');
	pg_assert_true(($blank > $delete) && ($blank < $rename), 'the refusal sits after Delete and before the rename');
}

function test_edit_design_file_refuses_a_blank_name_in_the_save_branch()
{
	$source = file_rename_blank_source('edit_design_file.php');
	$check = "if (trim((string) (\$_POST['name'] ?? '')) === '') {\n            output_error(lang('Please enter a name.') . ' <a href=\"javascript:history.go(-1)\">' . lang('Go back') . '</a>.');\n        }";

	$else = strpos($source, "        \$notice = lang('The design file was deleted successfully.');\n    }\n    else\n    {");
	$blank = strpos($source, $check);
	$rename = strpos($source, 'check_name_availability(');

	pg_assert_true($else !== false, 'the save branch follows the delete branch');
	pg_assert_true($blank !== false, 'a blank name is refused');
	pg_assert_true(($blank > $else) && ($blank < $rename), 'the refusal sits in the save branch, before the availability check');
}

function test_file_window_refuses_a_blank_name_before_preparing_it()
{
	$source = file_rename_blank_source('includes/explorer/handle_file_window.php');

	$blank = strpos($source, "if (\$requested_name === '') {\n        respond(array('status' => 'error', 'message' => lang('Please enter a name.')));\n    }");
	$blocked = strpos($source, 'pg_upload_name_blocked($requested_name)');
	$prepare = strpos($source, '$name = prepare_file_name($requested_name);');

	pg_assert_true($blank !== false, 'a blank name is refused');
	pg_assert_true(($blank < $blocked) && ($blank < $prepare), 'the refusal runs before the blocked-name check and prepare_file_name()');
	pg_assert_false(strpos($source, "if (\$name == '') {") !== false, 'the unreachable check after prepare_file_name() is gone');
}

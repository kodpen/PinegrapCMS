<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the submitted form, import, Form List View and Form Item View
 * screens print form names, field labels and pick list choice labels as
 * text: liveform prints a select's option labels as they are, so the label
 * is escaped where it is put into the options array.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_form_screen_source($file)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_form_screen_pick_list_choice_labels_escaped()
{
	$source = pg_test_form_screen_source('includes/forms/submitted_form_form.php');

	pg_assert_same(0, preg_match('/\$pick_list_options\[\$option\[\'label\'\]\]/', $source), 'pick list choice label used raw as option label');
	pg_assert_same(1, preg_match('/\$pick_list_options\[h\(\$option\[\'label\'\]\)\]/', $source), 'pick list choice label through h()');
	// The option value stays raw: liveform compares it with the stored value.
	pg_assert_same(1, preg_match('/\'value\' => \$option\[\'value\'\],/', $source), 'pick list option value stays raw');
}

function test_form_screen_custom_form_select_names_escaped()
{
	foreach (array('add_submitted_form.php', 'import_submitted_forms.php') as $file) {
		$source = pg_test_form_screen_source($file);

		pg_assert_same(0, preg_match('/\$custom_form_options\[\$name\]/', $source), $file . ': form name used raw as option label');
		pg_assert_same(1, preg_match('/\$custom_form_options\[h\(\$name\)\]/', $source), $file . ': form name through h()');
	}
}

function test_form_screen_view_information_names_escaped()
{
	foreach (array('edit_form_list_view.php', 'edit_form_item_view.php') as $file) {
		$source = pg_test_form_screen_source($file);

		pg_assert_same(0, preg_match('/\': \' \. \$custom_form_name;/', $source), $file . ': form name printed raw');
		pg_assert_same(1, preg_match('/\': \' \. h\(\$custom_form_name\);/', $source), $file . ': form name through h()');
	}
}

function test_form_screen_list_view_field_labels_escaped()
{
	$source = pg_test_form_screen_source('edit_form_list_view.php');

	pg_assert_same(0, preg_match('/>\' \. \$output_label \. \'</', $source), 'field label printed raw');
	pg_assert_same(2, preg_match_all('/>\' \. h\(\$output_label\) \. \'</', $source), 'field label through h() in the option and the browse field label');
}

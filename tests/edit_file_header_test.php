<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Edit File header: the image size written as the File
 * Manager writes it, one spelling of the rotate buttons, the file's folder
 * in the breadcrumb, the last change with its time and the editor's name,
 * the address as text, the optimized state and "Where is it used?".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_image_dimensions_label()
{
	pg_assert_same('600 × 600 px', pg_image_dimensions_label(600, 600));
	pg_assert_same('1920 × 1080 px', pg_image_dimensions_label('1920', '1080'));
	pg_assert_same('13 × 7 px', pg_image_dimensions_label('12.6', 7));
	pg_assert_same('', pg_image_dimensions_label('', 600), 'unknown width');
	pg_assert_same('', pg_image_dimensions_label(0, 600), 'zero width');
	pg_assert_same('', pg_image_dimensions_label(600, 'auto'));
}

function test_user_display_name()
{
	pg_assert_same('Erdal Güral', pg_user_display_name('Erdal', 'Güral', 'admin'));
	pg_assert_same('Erdal', pg_user_display_name(' Erdal ', '', 'admin'));
	pg_assert_same('admin', pg_user_display_name('', '', 'admin'), 'no contact name: the username');
	pg_assert_same('admin', pg_user_display_name(null, null, 'admin'));
	pg_assert_same('', pg_user_display_name(null, null, null));
}

function test_edit_file_header_wiring()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_file.php');

	pg_assert_contains("pg_image_dimensions_label(\$image_width, \$image_height)", $source);
	pg_assert_false(strpos($source, "'px - '") !== false, 'no "600px - 600px" left');
	pg_assert_false(strpos($source, "lang('Rotate right')") !== false, 'one spelling of the rotate buttons');
	pg_assert_contains("lang('Rotate Right')", $source);
	pg_assert_contains("view_folders.php?folder=' . (int) \$file_folder", $source);
	pg_assert_contains('LEFT JOIN contacts ON contacts.id = user.user_contact', $source);
	pg_assert_contains("pg_user_display_name(\$row['user_first_name'], \$row['user_last_name'], \$row['user_username'])", $source);
	pg_assert_contains("lang('Not optimised')", $source);
	pg_assert_contains('id="file_address"', $source);
	pg_assert_contains('type: "explorer_file_usage"', $source);
	// The facts, the preview and "Where is it used?" sit in the sticky status
	// column of the shared design file layout (ui/edit-screen-wide-layout.md).
	pg_assert_contains('pg_design_file_form_layout(', $source);
	pg_assert_contains("pg_design_file_form_summary_card('', \$summary_rows)", $source);
	pg_assert_contains(". \$output_where_used,", $source);
	pg_assert_false(strpos($source, 'output_no_file_editor_classes') !== false, 'no column classes switched by the editor');
}

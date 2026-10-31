<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the screens that show a files row whose file is gone from
 * the disk (a site restored without data/files, a file removed by hand).
 * Edit File and Edit Design File say so with the theme editors' note and drop
 * the preview, the image tools and the Open in New Tab and Download links;
 * Edit Comment names the attachment as not on the disk; the quote's signature
 * card shows its "drawing is missing" text instead of a broken image.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_missing_on_disk_source($screen)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
}

function test_file_missing_on_disk_file_screens_check_the_disk_once()
{
	foreach (array('edit_file', 'edit_design_file') as $screen) {
		$source = file_missing_on_disk_source($screen);
		pg_assert_true(strpos($source, "\$on_disk = is_file(FILE_DIRECTORY_PATH . '/' . \$file_name);") !== false, $screen . ' checks the disk');
		pg_assert_true(strpos($source, "lang('The file is recorded here but is not on the disk.')") !== false, $screen . ' shows the note');
		// Open in New Tab and Download are built only for a file on the disk.
		pg_assert_true(strpos($source, "\$output_file_links = '';") !== false, $screen . ' defaults the links to empty');
		pg_assert_true(strpos($source, "' . \$output_file_links . '") !== false, $screen . ' prints the links through the variable');
		pg_assert_same(1, substr_count($source, "lang('Open in New Tab')"), $screen . ' has one Open in New Tab link');
		pg_assert_true(strpos($source, "if (\$on_disk && (") !== false, $screen . ' offers the image tools only for a file on the disk');
	}
}

function test_file_missing_on_disk_edit_file_reads_only_a_file_on_the_disk()
{
	$source = file_missing_on_disk_source('edit_file');
	pg_assert_true(strpos($source, "if (\$on_disk && in_array(mb_strtolower(pathinfo(\$file_name, PATHINFO_EXTENSION)), \$viewable_formats)) {") !== false, 'the editor content is read only from the disk');
	pg_assert_true(strpos($source, "if (\$on_disk && \$file['type'] === 'svg') {") !== false, 'the SVG convert card needs the file');
	pg_assert_true(strpos($source, "if (!\$on_disk) {") !== false, 'the preview is replaced by the note');
}

function test_file_missing_on_disk_edit_design_file_has_no_unchecked_image_size()
{
	$source = file_missing_on_disk_source('edit_design_file');
	pg_assert_true(strpos($source, "\$image_size = @getimagesize(") !== false, 'still measures the image');
	pg_assert_true(strpos($source, "if (\$image_size !== false) {") !== false, 'reads the size only when getimagesize() gave one');
}

function test_file_missing_on_disk_comment_attachment_says_not_on_the_disk()
{
	$source = file_missing_on_disk_source('edit_comment');
	pg_assert_true(strpos($source, "is_file(FILE_DIRECTORY_PATH . '/' . \$file_name)") !== false, 'checks the attachment on the disk');
	pg_assert_true(strpos($source, "lang('not on the disk')") !== false, 'says when it is not');
}

function test_file_missing_on_disk_quote_signature_needs_the_drawing()
{
	$source = file_missing_on_disk_source('edit_erp_quote');
	pg_assert_true(strpos($source, "is_file(FILE_DIRECTORY_PATH . '/' . (string) \$signature['file_name'])") !== false, 'draws the signature only from the disk');
}

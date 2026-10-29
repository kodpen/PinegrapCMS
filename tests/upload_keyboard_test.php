<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for keyboard use of the upload screens (add_file.php,
 * add_design_file.php): a real Choose files button beside the Dropzone drop
 * area, and a queued file's remove link that shows while it has focus.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function upload_keyboard_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_upload_keyboard_choose_files_button()
{
	foreach (array('add_file.php', 'add_design_file.php') as $file) {
		$src = upload_keyboard_source($file);

		pg_assert_true(
			(bool) preg_match('/<button type="button" id="pg_upload_choose"[^>]*>/', $src),
			$file . ': no Choose files button'
		);
		pg_assert_contains('clickable: [".dropzone_previews", "#pg_upload_choose"]', $src, $file . ': button not passed to Dropzone');

		// Outside the drop area: .dz-message hides once files are queued, and
		// a button inside a clickable element would open the dialog twice.
		$area_start = strpos($src, '<div class="dropzone_previews');
		$area_end   = strpos($src, '<div class="fallback">', (int) $area_start);
		$button     = strpos($src, 'id="pg_upload_choose"');
		pg_assert_true($area_start !== false && $area_end !== false && $button !== false, $file . ': drop area or button not found');
		pg_assert_false($button > $area_start && $button < $area_end, $file . ': button sits inside the drop area');
	}
}

function test_upload_keyboard_remove_link_visible_on_focus()
{
	$css = upload_keyboard_source('assets/css/backend.src.css');

	pg_assert_contains('.dz-preview:focus-within .dz-remove', $css);
	pg_assert_contains('.dz-remove:focus-visible', $css);
}

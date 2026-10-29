<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the names of the file previews: every <iframe> and
 * <embed> of the File Manager and the Edit File screen carries a title,
 * the side pane's image link is named, and Edit File's .ico preview is
 * decorative like its image preview.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_preview_names_source($file)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
}

// The opening tags of $tag in $source.
function file_preview_names_tags($source, $tag)
{
	preg_match_all('/<' . $tag . '\b[^>]*>/i', $source, $matches);

	return $matches[0];
}

function test_file_preview_frames_carry_a_title()
{
	foreach (array('view_folders.php', 'edit_file.php') as $file) {
		$source = file_preview_names_source($file);

		foreach (array('iframe', 'embed') as $tag) {
			foreach (file_preview_names_tags($source, $tag) as $open) {
				pg_assert_true(strpos($open, 'title=') !== false, $file . ': ' . $open . ' has no title');
			}
		}
	}
}

function test_file_preview_frames_are_found()
{
	pg_assert_true(count(file_preview_names_tags(file_preview_names_source('view_folders.php'), 'iframe')) >= 7, 'the File Manager preview frames are found');
	pg_assert_true(count(file_preview_names_tags(file_preview_names_source('edit_file.php'), 'embed')) >= 1, 'the Edit File PDF preview is found');
}

function test_file_preview_pane_image_link_is_named()
{
	$source = file_preview_names_source('view_folders.php');

	pg_assert_true(strpos($source, '<a href="\' + esc(imageUrl(item)) + \'" target="_blank" draggable="false" aria-label="\' + esc(L.open_in_new_tab + \': \' + item.name) + \'">') !== false, 'the preview pane image link is named');
}

function test_edit_file_icon_preview_is_decorative()
{
	$source = file_preview_names_source('edit_file.php');

	pg_assert_true(strpos($source, 'alt="icon preview"') === false, 'no English literal alt on the icon preview');
}

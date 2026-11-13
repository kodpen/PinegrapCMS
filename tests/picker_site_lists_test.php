<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Design Canvas's From the site lists and the Workspace's "From the file
 * manager" on the one picker: which kind each opens, where the editor keeps
 * its own lists (a site page in Edit mode), how the picker is loaded inside
 * the editor, and the mark of a file a channel refuses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function picker_site_lists_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The text of a function from its head up to the next prototype member.
function picker_site_lists_test_member($source, $head)
{
	$start = strpos($source, $head);

	if ($start === false) {
		return '';
	}

	$end = strpos($source, 'Editor.prototype.', $start + strlen($head));

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_picker_site_lists_canvas_kinds()
{
	$artboard = picker_site_lists_test_source('assets/js/pg_image_editor_artboard.js');
	$clip = picker_site_lists_test_source('assets/js/pg_image_editor_clip.js');
	$sound = picker_site_lists_test_source('assets/js/pg_image_editor_sound.js');

	$import = picker_site_lists_test_member($artboard, 'Editor.prototype.openImportFiles = function');
	pg_assert_contains("this.sitePick(pick ? 'canvas_photo' : 'canvas_picture'", $import, 'Import and a picture handed on');
	pg_assert_true(strpos($import, 'this.sitePickerOk()') < strpos($import, 'this.confirmBox'), 'the picker first, the own list without it');

	$video = picker_site_lists_test_member($clip, 'Editor.prototype.openClipFiles = function');
	pg_assert_contains("this.sitePick('canvas_video'", $video, 'Video from the site and Replace');
	pg_assert_true(strpos($video, 'this.sitePickerOk()') < strpos($video, 'this.confirmBox'));

	$add = picker_site_lists_test_member($sound, 'Editor.prototype.openSoundFiles = function');
	pg_assert_contains("this.sitePick('canvas_sound'", $add, 'Add sound');
	pg_assert_contains('self.addSound(f)', $add);
	pg_assert_true(strpos($add, 'this.sitePickerOk()') < strpos($add, 'this.confirmBox'));

	// The picked file goes the way a press in the own list sent it.
	pg_assert_contains('self.importPicked(f, pick)', $artboard);
	pg_assert_contains('self.clipPicked(f, pick)', $clip);
}

function test_picker_site_lists_canvas_loads_the_picker()
{
	$artboard = picker_site_lists_test_source('assets/js/pg_image_editor_artboard.js');
	$ok = picker_site_lists_test_member($artboard, 'Editor.prototype.sitePickerOk = function');

	pg_assert_contains('ui.version() === 6', $ok, 'only where Bootstrap 6 dialogs are: a panel screen');
	pg_assert_contains('X.config().picker', $ok, 'and the editor carries the picker');
	pg_assert_contains("getElementById('pg_picker_config')", $artboard, 'the settings written where the screen did not print them');
	pg_assert_contains("id: 'pg_picker_config'", $artboard);
	pg_assert_contains('window.pgPicker && window.pgPicker.open', $artboard, 'a screen that printed the picker keeps its own');

	foreach (array('id', 'name', 'url', 'width', 'height', 'svg') as $field) {
		pg_assert_contains($field . ':', picker_site_lists_test_member($artboard, 'Editor.pickedFile = function'), 'a pick carries ' . $field);
	}

	$config = pg_image_editor_config();
	pg_assert_true(is_array($config['picker']), 'the editor carries the picker');
	pg_assert_same(pg_js_bundle_urls('assets/js/pg_picker.min.js'), $config['picker']['scripts']);
	pg_assert_true(isset($config['picker']['config']['endpoint'], $config['picker']['config']['texts']), 'with its settings');
	pg_assert_contains('json_encode(pg_picker_config()', picker_site_lists_test_source('includes/fn/picker.php'), 'one set of settings for both');
}

function test_picker_site_lists_workspace_paperclip()
{
	$workspace = picker_site_lists_test_source('assets/js/workspace.js');
	$screen = picker_site_lists_test_source('includes/workspace/screen.php');
	$start = strpos($workspace, 'attachFromLibrary: function');
	$attach = substr($workspace, $start, strpos($workspace, 'uploadFiles: function', $start) - $start);

	pg_assert_contains("window.pgPicker.open({ kind: 'channel_file'", $attach, 'the one picker under the paperclip');
	pg_assert_true(strpos($attach, "api('ws_library_attach'") > strpos($attach, 'pgPicker.open('), 'the copy is posted once a file is picked');
	pg_assert_false(strpos($workspace, 'function libraryPicker(') !== false, 'the drawer is gone');
	pg_assert_false(strpos($workspace, "'ws_library_browse'") !== false);
	pg_assert_contains('$lightbox .= pg_picker_script();', $screen, 'a channel prints the picker');
	pg_assert_false(strpos(picker_site_lists_test_source('includes/panel/actions.php'), "'ws_library_browse'") !== false, 'the action is gone from the panel door');
}

function test_picker_site_lists_refused_mark()
{
	$picker = picker_site_lists_test_source('assets/js/pg_picker.js');

	pg_assert_contains('return item.mark_text || markLabel(item.mark);', $picker, 'the server\'s reason shown as the mark');
	pg_assert_contains("t('Marked files cannot be chosen.')", $picker, 'the note for files');
	pg_assert_contains('.pg-picker-mark.is-refused', picker_site_lists_test_source('assets/css/backend.src.css'));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the rich text editor (CKEditor 4) carries its field's caption:
 * the editing area, the wrapper and the Source view are named by it, a click
 * on the caption focuses the editor, and Source view says how Tab leaves it
 * and still reaches the toolbar from the keyboard.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_rich_text_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Every panel and site field goes through pgCkeditorReplace(), not the
// shared config straight into CKEDITOR.replace() (title: false, so the
// editor was voiced as "Rich Text Editor, <element id>").
function test_rich_text_names_every_field_goes_through_the_helper()
{
	$editor = pg_test_rich_text_names_source('includes/fn/editor.php');

	pg_assert_contains('pgCkeditorReplace("\' . $editor_id . \'", software_ckeditor_config);', $editor, 'each field through the helper');
	pg_assert_false(strpos($editor, 'CKEDITOR.replace("\' . $editor_id . \'"') !== false, 'no field with the shared, unnamed config');
}

// The name follows pgCeTextareaName(): aria-label, aria-labelledby, then the
// first label without its hidden parts. It sets title (frame title, body
// aria-label) and the encoded applicationTitle (voice label, put into the
// markup as is); a label click focuses the editor.
function test_rich_text_names_helper_names_the_editor()
{
	$plugin = pg_test_rich_text_names_source('assets/js/pg_ckeditor_source.js');

	pg_assert_contains('window.pgCkeditorReplace = function (id, config) {', $plugin, 'the helper');
	pg_assert_contains("var name = textarea.getAttribute('aria-label') || '';", $plugin, 'aria-label first');
	pg_assert_contains("var ids = textarea.getAttribute('aria-labelledby');", $plugin, 'then aria-labelledby');
	pg_assert_contains('var hidden = copy.querySelectorAll(\'[aria-hidden="true"]\');', $plugin, 'then the label without its hidden parts');
	pg_assert_contains('own = CKEDITOR.tools.extend({}, config);', $plugin, 'a copy per field, the shared config untouched');
	pg_assert_contains('own.title = name;', $plugin, 'the editing area is named');
	pg_assert_contains('own.applicationTitle = CKEDITOR.tools.htmlEncode(name);', $plugin, 'the wrapper is named, encoded');
	pg_assert_contains('var editor = CKEDITOR.replace(id, own);', $plugin, 'replaced with the named copy');
	pg_assert_contains("labels[i].addEventListener('click', function (event) {", $plugin, 'the caption listens');
	pg_assert_contains('editor.focus();', $plugin, 'and focuses the editor');
}

// Source view: the code editor takes the name from the source textarea, is
// described by a line that says how Tab leaves, passes Alt+F10 and Alt+0 on
// to CKEditor, and editor.focus() lands in it.
function test_rich_text_names_source_view()
{
	$plugin = pg_test_rich_text_names_source('assets/js/pg_ckeditor_source.js');

	pg_assert_contains("textarea.setAttribute('aria-label', editor.title);", $plugin, 'the source textarea is named for the code editor');
	pg_assert_contains("hint.id = 'cke_' + editor.name + '_keys_hint';", $plugin, 'the hint line');
	pg_assert_contains("String(labels.keysHint).replace('{var}', /mac|iphone|ipad|ipod/i.test(platform) ? 'Shift+Alt+M' : 'Ctrl+M');", $plugin, 'the key by platform');
	pg_assert_contains('container.parentNode.insertBefore(hint, container.nextSibling);', $plugin, 'under the editor');
	pg_assert_contains('}, hint ? { describedBy: hint.id } : null);', $plugin, 'the code editor is described by it');
	pg_assert_contains("var command = (event.key === 'F10') ? 'toolbarFocus'", $plugin, 'Alt+F10 reaches the toolbar');
	pg_assert_contains(": ((event.code === 'Digit0') || (event.key === '0')) ? 'a11yHelp'", $plugin, 'Alt+0 opens the help');
	pg_assert_contains('forwardKeys(editor, instance.container || instance.host);', $plugin, 'from the code editor');
	pg_assert_contains('editable.focus = function () {', $plugin, 'editor.focus() lands in the code editor');
	pg_assert_contains("editor.on('beforeModeUnload', leaveSource);", $plugin, 'the line goes with Source view');
	pg_assert_contains("editor.on('destroy', leaveSource);", $plugin, 'and with the editor');

	pg_assert_contains("'keysHint'    => lang('Tab indents. {var} makes Tab move to the next field.'),", pg_test_rich_text_names_source('includes/fn/editor.php'), 'the text reaches the page');
}

// Fields whose caption is not a label for the textarea.
function test_rich_text_names_captions_reach_the_textarea()
{
	$field = pg_test_rich_text_names_source('includes/forms/field_form.php');

	pg_assert_contains('fw-bold" id="information_heading">', $field, 'the Information heading has an id');
	pg_assert_contains('<textarea id="information" name="information" aria-labelledby="information_heading">', $field, 'and names the editor');

	$page = pg_test_rich_text_names_source('includes/forms/page_form.php');

	pg_assert_contains('id="custom_form_confirmation_message" aria-labelledby="custom_form_confirmation_type_legend custom_form_confirmation_message_label"', $page, 'confirmation message: legend and label');
	pg_assert_contains('id="custom_form_return_message" aria-labelledby="custom_form_return_type_legend custom_form_return_message_label"', $page, 'return message: legend and label');

	foreach (array('custom_form_confirmation_type_legend', 'custom_form_confirmation_message_label', 'custom_form_return_type_legend', 'custom_form_return_message_label') as $id) {
		pg_assert_contains('id="' . $id . '"', $page, $id . ' exists');
	}
}

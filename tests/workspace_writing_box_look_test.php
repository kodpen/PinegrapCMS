<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the writing box's buttons in the panel look: the editor
 * and the scripts hanging on it (highlights, saved replies, voice,
 * translation) draw .pg-button / .pg-icon-button / .pg-seg-item, no old
 * Bootstrap button but the editor's context menu rows, and the row of
 * tools keeps the accent tint for a pressed mark.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_writing_box_look_source($file)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/' . $file);
}

function test_workspace_writing_box_look_no_old_buttons()
{
	foreach (array('workspace_highlights.js', 'workspace_replies.js', 'workspace_voice.js', 'workspace_translate.js') as $file) {
		pg_assert_same(0, preg_match_all('/(?<![\w-])btn btn-/', workspace_writing_box_look_source($file)), $file . ' has no old button');
	}

	$editor = workspace_writing_box_look_source('workspace_editor.js');
	preg_match_all('/^.*(?<![\w-])btn btn-.*$/m', $editor, $lines);

	pg_assert_same(1, count($lines[0]), 'one old button line is left in the editor');
	pg_assert_contains('ws-ctx-item', $lines[0][0]);
}

function test_workspace_writing_box_look_toolbar_and_seg()
{
	$editor = workspace_writing_box_look_source('workspace_editor.js');

	pg_assert_contains("var node = button('pg-icon-button pg-icon-button-sm', spec.text || '', spec.icon,", $editor);
	pg_assert_contains("' is-active' : ''), '', entry[1], entry[2])", $editor);

	$highlights = workspace_writing_box_look_source('workspace_highlights.js');

	pg_assert_contains("h.el('div', 'pg-seg pg-seg-wrap')", $highlights);
	pg_assert_contains("h.button('pg-seg-item', h.t(option[1]))", $highlights);

	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$at = strpos($css, '.ws-rich-tools .pg-icon-button[aria-pressed="true"] {');

	pg_assert_true($at !== false, 'pressed tool rule found');

	$rule = substr($css, (int) $at, (int) strpos($css, '}', (int) $at) - (int) $at);

	pg_assert_contains('color-mix(in srgb, var(--ws-accent) 15%, transparent)', $rule);
	pg_assert_contains('color: var(--ws-accent);', $rule);
}

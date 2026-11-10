<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Keyboard and screen-reader wiring of the My Notes groups, tabs and lock
 * (assets/js/workspace_note_groups.js, workspace_note_tabs.js,
 * workspace_note_lock.js): a move made from a menu gives the focus back to
 * the moved row or folder, each folder's menu button and lock mark carry a
 * name, a cut tab title is read whole, the tab reorder keys are announced,
 * and a refused PIN is tied to its fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_notes_a11y_test_src($name)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $name);
}

function test_ws_notes_a11y_groups_focus_and_names()
{
	$src = workspace_notes_a11y_test_src('workspace_note_groups.js');

	pg_assert_contains("item.dataset.noteId = String(note.id);", $src);
	pg_assert_contains("toggle.dataset.groupId = String(group.id);", $src);
	pg_assert_contains("return reloadThenFocus(ctx, noteSelector(note.id));", $src);
	pg_assert_contains("refocus(groupSelector(movingId));", $src);
	pg_assert_contains("body.setAttribute('role', 'group');", $src);
	pg_assert_contains("lock.setAttribute('role', 'img');", $src);
	pg_assert_contains("more.setAttribute('aria-label', kit.t('more') + ': ' + group.name);", $src);
}

function test_ws_notes_a11y_tabs()
{
	$src = workspace_notes_a11y_test_src('workspace_note_tabs.js');

	pg_assert_contains("row.setAttribute('role', 'tablist');", $src);
	pg_assert_contains("tab.appendChild(kit.el('span', 'visually-hidden', name));", $src);
	pg_assert_contains("'Control+Shift+ArrowLeft Control+Shift+ArrowRight'", $src);
}

function test_ws_notes_a11y_lock()
{
	$src = workspace_notes_a11y_test_src('workspace_note_lock.js');

	pg_assert_contains("field.input.setAttribute('aria-describedby', note.id);", $src);
	pg_assert_contains("input.setAttribute('aria-invalid', text ? 'true' : 'false');", $src);
	pg_assert_contains("(state && state.set_up) ? 'menu' : 'dialog'", $src);
}

// The pin and lock marks of a note row and a folder's lock mark are read by
// their label: icon() hides an icon from screen readers, so each mark drops
// that and stands as a named image.
function test_ws_notes_a11y_marks_named()
{
	$main = workspace_notes_a11y_test_src('workspace.js');

	pg_assert_contains("pinMark.removeAttribute('aria-hidden');", $main, 'pin mark not hidden');
	pg_assert_contains("pinMark.setAttribute('role', 'img');", $main);
	pg_assert_contains("pinMark.setAttribute('aria-label', t('notes_pinned'));", $main);

	pg_assert_contains("node.removeAttribute('aria-hidden');", workspace_notes_a11y_test_src('workspace_note_lock.js'), 'note lock mark not hidden');
	pg_assert_contains("lock.removeAttribute('aria-hidden');", workspace_notes_a11y_test_src('workspace_note_groups.js'), 'folder lock mark not hidden');
}

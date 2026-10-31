<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Workspace from the keyboard: messages carry one roving
 * Tab stop and a name, a menu raised from the keyboard opens at the message,
 * the context menu gives focus back to what opened it, closes on Tab, knows
 * Home and End and says which choice is on, its button openers announce the
 * menu, and the floating boxes (Insert table) give focus back too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_context_menu_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// The text of a function of workspace.js, from its head to the next
// function at the same indent.
function workspace_context_menu_function($source, $head)
{
	$start = strpos($source, $head);

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    function ", $start + strlen($head));

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

// A message is an article named by its sender and time row, in one roving
// Tab stop over the channel pane and the discussion pane.
function test_workspace_messages_take_focus()
{
	$source = workspace_context_menu_source();
	$keys = workspace_context_menu_function($source, 'function messageKeys(pane) {');

	pg_assert_contains("node.setAttribute('role', 'article');", $source);
	pg_assert_contains("node.setAttribute('aria-labelledby', meta.id);", $source);
	pg_assert_contains('node.tabIndex = -1;', $source);
	pg_assert_same(2, substr_count($source, 'messageKeys(pane);'), 'the channel pane and the discussion pane');
	pg_assert_contains("event.target.classList.contains('ws-msg')", $keys, 'keys act only on the message itself');

	foreach (array("'ArrowUp'", "'ArrowDown'", "'Home'", "'End'") as $key) {
		pg_assert_contains($key, $keys);
	}

	pg_assert_contains('new MutationObserver(keepStop)', $keys, 'a pane always keeps one Tab stop');
}

// ContextMenu or Shift+F10 raises the native contextmenu: the menu opens
// at the node, not at the pointer.
function test_workspace_keyboard_contextmenu_opens_at_the_node()
{
	$source = workspace_context_menu_source();
	$context = workspace_context_menu_function($source, 'function onContext(node, build) {');

	pg_assert_contains("event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')", $source);
	pg_assert_contains('(Date.now() - ctxKeyAt) < 1000', $context);
	pg_assert_contains('node.getBoundingClientRect()', $context);
}

// The menu remembers its opener and gives focus back to it on close.
function test_workspace_ctx_menu_gives_focus_back()
{
	$source = workspace_context_menu_source();
	$close = workspace_context_menu_function($source, 'function closeCtx() {');

	pg_assert_contains('giveFocusBack(opener);', $close);
	pg_assert_contains("opener.setAttribute('aria-expanded', 'false');", $close);
	pg_assert_same(2, substr_count($source, 'takeOpener();'), 'ctxMenu and floatBox remember their opener');
	pg_assert_contains("if (document.activeElement === document.body || !document.activeElement) {\n                    giveFocusBack(opener);", $source, 'after an item that left focus on the page');
}

// Tab closes the menu; Home and End reach the first and the last entry.
function test_workspace_ctx_menu_keys()
{
	$source = workspace_context_menu_source();

	pg_assert_contains("if (event.key === 'Tab' && isMenu) {", $source);
	pg_assert_contains("event.key === 'Home' || event.key === 'End'", $source);
}

// The checked choice is a radio entry that says so; the header names the menu.
function test_workspace_ctx_menu_says_which_choice_is_on()
{
	$menu = workspace_context_menu_function(workspace_context_menu_source(), 'function ctxMenu(x, y, items, above) {');

	pg_assert_contains("entry.setAttribute('role', ('checked' in item) ? 'menuitemradio' : 'menuitem');", $menu);
	pg_assert_contains("entry.setAttribute('aria-checked', item.checked ? 'true' : 'false');", $menu);
	pg_assert_contains("header.setAttribute('role', 'none');", $menu);
	pg_assert_contains("menu.setAttribute('aria-label', item.header);", $menu);
}

// Button openers announce their menu.
function test_workspace_menu_openers_announce_the_menu()
{
	$source = workspace_context_menu_source();

	pg_assert_contains("node.setAttribute('aria-haspopup', 'menu');", workspace_context_menu_function($source, 'function menuButton(node) {'));
	pg_assert_true(substr_count($source, 'menuButton(') >= 10, 'every button opener of a context menu');
}

// Insert table opens with focus on its first cell.
function test_workspace_table_picker_takes_focus()
{
	$source = workspace_context_menu_source();

	pg_assert_contains("floatBox(x, y, content, 'ws-table-picker', true);\n            cells[0].focus({ preventScroll: true });", $source);
}

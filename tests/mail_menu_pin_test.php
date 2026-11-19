<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Incoming e-mail pinned to a person's own main menu (Pin to main
 * menu on the tab, includes/workspace/mail_menu.php): the column and its
 * migration step, the toolbar button, the panel action and its gate, the
 * menu row in its slot, its place and the screen it lights, its icon and the
 * script that adds or takes the row away without a reload. The reads and
 * writes need a database and are checked in their source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

// A product file with Windows line ends read as Unix ones.
function mail_menu_pin_test_file($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

// The source of a PHP function, line ends made Unix ones.
function mail_menu_pin_test_function($name)
{
	return str_replace("\r\n", "\n", pg_test_function_source($name));
}

// Whether a lang() source has its Turkish in a shard.
function mail_menu_pin_test_translated($key)
{
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$shard = json_decode((string) file_get_contents($file), true);

		if (is_array($shard) && isset($shard[$key]) && ($shard[$key] !== '')) {
			return true;
		}
	}

	return false;
}

function test_mail_menu_pin_column_is_added_like_notes_pinned()
{
	$step = mail_menu_pin_test_file('includes/migrations/2026.11.0.php');
	$notes = mail_menu_pin_test_file('includes/migrations/2026.4.8.php');

	pg_assert_contains("upgrade_2026_11_0_ws_mail_menu_pin();         // 11.0.239", $step, 'a sub-step of the open version');
	pg_assert_contains("install_add_column('ws_profiles', 'notes_pinned', \"TINYINT(1) NOT NULL DEFAULT 0\");", $notes, 'the column it mirrors');

	// The migration file loads only during an upgrade: its step is read as text.
	$start = strpos($step, "\nfunction upgrade_2026_11_0_ws_mail_menu_pin() {");
	$end = ($start !== false) ? strpos($step, "\n}\n", $start) : false;
	$body = (($start !== false) && ($end !== false)) ? substr($step, $start, $end - $start) : '';

	pg_assert_same(1, substr_count($step, 'function upgrade_2026_11_0_ws_mail_menu_pin()'), 'one step');

	pg_assert_contains("install_add_column('ws_profiles', 'mail_pinned', \"TINYINT(1) NOT NULL DEFAULT 0\");", $body, 'the same type and default as notes_pinned, added once');
	pg_assert_contains("if (!install_table_exists('ws_profiles')) {", $body, 'skipped where the Workspace\'s tables are not there');
	pg_assert_false(strpos($body, 'ALTER TABLE') !== false, 'never a raw ALTER');
}

function test_mail_menu_pin_reads_and_writes_the_person_row()
{
	pg_assert_contains("pg_schema_has('ws_profiles', 'mail_pinned')", mail_menu_pin_test_function('ws_mail_menu_ready'), 'probed by its exact name');

	$pinned = mail_menu_pin_test_function('ws_mail_menu_pinned');

	pg_assert_contains('!ws_mail_menu_ready()', $pinned, 'nothing before the column');
	pg_assert_contains("SELECT mail_pinned FROM ws_profiles WHERE user_id = '\" . (int) \$user_id . \"'\"", $pinned, 'the person\'s own row');

	$pin = mail_menu_pin_test_function('ws_mail_menu_pin');

	pg_assert_contains('INSERT INTO ws_profiles (user_id, mail_pinned, updated_at)', $pin, 'a person with no profile gets one');
	pg_assert_contains('ON DUPLICATE KEY UPDATE mail_pinned = VALUES(mail_pinned)', $pin, 'kept as notes_pinned is');

	pg_assert_false(ws_mail_menu_pinned(0), 'nobody has no pin');
	pg_assert_false(ws_mail_menu_pinned(-3), 'nor a bad id');
}

function test_mail_menu_pin_button()
{
	pg_assert_same('', ws_mail_menu_pin_html(null), 'not offered before the column is there');

	$off = ws_mail_menu_pin_html(false);

	pg_assert_contains('<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-menu-pin="0">', $off, 'look A\'s quiet button, not pinned');
	pg_assert_contains('<i class="bi bi-pin-angle" aria-hidden="true"></i>', $off, 'the pin');
	pg_assert_contains(h(lang('Pin to main menu')), $off, 'says what a click does');

	$on = ws_mail_menu_pin_html(true);

	pg_assert_contains('data-ws-mail-menu-pin="1"', $on, 'pinned');
	pg_assert_contains('<i class="bi bi-pin-angle-fill" aria-hidden="true"></i>', $on, 'the pin filled');
	pg_assert_contains(h(lang('Remove from main menu')), $on, 'says what a click does');

	foreach (array($off, $on) as $html) {
		pg_assert_false(strpos($html, 'btn btn-') !== false, 'no old button');
		pg_assert_false(strpos($html, 'aria-pressed') !== false, 'the label changes, so no pressed state beside it');
	}

	foreach (array('Pin to main menu', 'Remove from main menu', 'Incoming e-mail is in the main menu now.', 'Incoming e-mail is no longer in the main menu; Communication still has it.') as $key) {
		pg_assert_true(mail_menu_pin_test_translated($key), $key . ' is translated');
	}

	$strings = ws_mail_menu_js_strings();

	foreach (array('menu_pin', 'menu_unpin', 'menu_pinned', 'menu_unpinned', 'menu_label') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' for the script');
	}
}

function test_mail_menu_pin_button_on_the_tab()
{
	$filters = mail_menu_pin_test_function('ws_mail_list_filters_html');

	pg_assert_contains('$blocked = false, $pin = null)', $filters, 'the state comes from the screen');
	pg_assert_contains('ws_mail_menu_pin_html($pin)', $filters, 'drawn on the toolbar');

	$page = mail_menu_pin_test_file('messages_mail.php');

	pg_assert_contains("ws_mail_menu_ready() ? ws_mail_menu_pinned((int) \$viewer['id']) : null", $page, 'the person\'s own pin');
	pg_assert_contains("'strings' => ws_mail_screen_js_strings() + ws_mail_menu_js_strings()", $page, 'the button\'s words for the script');
}

function test_mail_menu_pin_action()
{
	pg_assert_true(in_array('ws_mail_menu_pin', ws_mail_actions_without_workspace(), true), 'answers with the Workspace off while mail is on');
	pg_assert_contains("'ws_mail_menu_pin'", mail_menu_pin_test_file('includes/panel/actions.php'), 'a panel action');

	$handler = mail_menu_pin_test_function('ws_handle_action');
	$case = strpos($handler, "case 'ws_mail_menu_pin':");

	pg_assert_true($case !== false, 'handled');

	$body = substr($handler, (int) $case, 1400);

	pg_assert_contains('ws_mail_list_visible($viewer)', $body, 'only whoever may open Incoming e-mail pins it');
	pg_assert_contains("ws_mail_menu_pin(\$viewer, !empty(\$request['pinned']))", $body, 'the person\'s own choice');
	pg_assert_contains("pg_menu_icon('mail', 'campaigns-color')", $body, 'the row\'s drawing for the script');
	pg_assert_contains('pg_menu_count_badge(', $body, 'and its unread count');

	$js = mail_menu_pin_test_file('assets/js/workspace_mail.js');

	pg_assert_contains("h.api('ws_mail_menu_pin', { pinned: wanted ? 1 : 0 })", $js, 'the script asks for it');
	pg_assert_contains('[data-ws-mail-menu-pin]', $js, 'from the toolbar button');
	pg_assert_contains("row.id = 'menu_item_27';", $js, 'adds the row without a reload');
}

function test_mail_menu_pin_row_in_the_menu()
{
	$categories = pg_menu_categories();

	pg_assert_same(array(0, 26, 23, 24, 27), $categories['']['slots'], 'in the untitled group, under the Workspace and My Notes');
	pg_assert_same(array(0, 27), pg_menu_layout(array(27, 0, 9), $categories)[0]['slots'], 'with the Workspace off it follows the Dashboard');

	$menu = mail_menu_pin_test_function('output_menu');

	pg_assert_contains("if (in_array('mail', \$messages_tabs, true) && function_exists('ws_mail_menu_pinned') && ws_mail_menu_pinned((int) \$user['id'])) {", $menu, 'only for whoever may open the tab and pinned it');
	pg_assert_contains("\$menu_items[27]['id'] = 27;", $menu, 'slot 27, the next free one');
	pg_assert_contains("\$menu_items[27]['href'] = 'messages_mail.php';", $menu, 'the tab');
	pg_assert_contains("\$menu_items[27]['menu_icon'] = 'mail';", $menu, 'its own drawing');
	pg_assert_contains("\$menu_items[27]['color_class'] = 'campaigns-color';", $menu, 'Communication\'s colour');
	pg_assert_contains("\$menu_items[27]['title'] = 'Incoming e-mail';", $menu, 'the tab\'s name');
	pg_assert_contains("\$menu_items[27]['badge'] = \$menu_items[9]['badge'];", $menu, 'the unread count, counted once');
	pg_assert_false(strpos($menu, "\$menu_items[27]['context']") !== false, 'one screen, no panel');
	pg_assert_contains("\$menu_items[9]['id'] = 9;", $menu, 'Communication\'s row stays');

	pg_assert_contains("case 'messages_mail.php':\n            // Pinned to the menu, Incoming e-mail lights its own row.\n            \$active_menu = isset(\$menu_items[27]) ? 27 : 9;", $menu, 'its own row, Communication\'s without it');
	pg_assert_same(1, substr_count($menu, "case 'messages_mail.php':"), 'one case');
}

function test_mail_menu_pin_row_icon()
{
	$svg = pg_menu_icon('mail', 'campaigns-color');

	pg_assert_contains('class="pg-mi pg-mi-mail me-3 campaigns-color"', $svg, 'its classes');
	pg_assert_contains('class="pg-mi-a pg-mi-sheet"', $svg, 'the letter in the second colour');

	$css = mail_menu_pin_test_file('assets/css/backend.src.css');

	pg_assert_contains('#menu .pg-mi-mail { --pg-mi-a: var(--pg-mi-sky); }', $css, 'the second colour');
	pg_assert_contains('#menu .list-group-item:is(:hover, :focus-visible) .pg-mi-mail .pg-mi-sheet {', $css, 'the letter comes up on hover');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents, Claude's row: the set-up on
 * claude.ai is a drawer of numbered steps (Bootstrap's .stepper) opened by
 * one quiet button, which reads "Finish the set-up" while Claude is not
 * ready; the opened row keeps the connection's fields, one line of help on
 * the switch, and Try the connection.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function settings_claude_setup_drawer_test_function($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is defined in ' . $file);
	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, $end - $start);
}

function test_settings_claude_setup_drawer_button()
{
	$ready = ws_claude_setup_button(array());
	pg_assert_contains(h(lang('Set up on claude.ai')), $ready, 'ready: the plain name');
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#pgset-ws-claude-setup" aria-controls="pgset-ws-claude-setup"', $ready, 'it opens the drawer');
	pg_assert_contains('pg-button pg-button-quiet', $ready, 'a quiet button: Save is the one filled button');
	pg_assert_false(strpos($ready, 'pg-button-fill') !== false, 'never filled');

	$missing = ws_claude_setup_button(array('The routine\'s address is missing.'));
	pg_assert_contains(h(lang('Finish the set-up')), $missing, 'not ready: finish it');
	pg_assert_false(strpos($missing, h(lang('Set up on claude.ai'))) !== false, 'one name at a time');
}

function test_settings_claude_setup_drawer_steps()
{
	$body = ws_claude_setup_drawer_html('<li class="stepper-item active">x</li>');
	pg_assert_contains('<ol class="stepper pg-stepper-cards ws-claude-setup-steps">', $body, 'numbered steps, Bootstrap\'s stepper');
	pg_assert_contains('<li class="stepper-item active">x</li>', $body, 'the steps inside');
	pg_assert_contains(h(lang('Each run counts against the routine owner\'s daily allowance on claude.ai.')), $body, 'the cost moved here from the switch');

	$guide = settings_claude_setup_drawer_test_function('includes/workspace/claude_setup.php', 'ws_claude_setup_guide');
	pg_assert_contains('<li class="stepper-item active ws-claude-step">', $guide, 'each step a stepper item; the number is the counter');

	$drawer = settings_claude_setup_drawer_test_function('includes/workspace/claude.php', 'ws_settings_claude_setup_drawer');
	pg_assert_contains("pg_settings_drawer('pgset-ws-claude-setup', h(lang('Set up on claude.ai'))", $drawer, 'the drawer and its title');
}

function test_settings_claude_setup_drawer_row()
{
	$card = settings_claude_setup_drawer_test_function('includes/workspace/claude.php', 'ws_claude_settings_card');
	pg_assert_false(strpos($card, 'ws-claude-guide') !== false, 'the guide left the row');
	pg_assert_false(strpos($card, 'ws_claude_setup_guide(') !== false, 'the row draws no step');
	pg_assert_contains('ws_claude_setup_button($missing)', $card, 'the one button that opens it');
	pg_assert_contains("lang('When someone writes @Claude in a channel, your routine on claude.ai answers.')", $card, 'one line of help on the switch');
	pg_assert_false(strpos($card, 'A discussion Claude follows') !== false, 'the rest is in the drawer');

	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/workspace_assistants.php');
	pg_assert_contains('$pg_settings_modals[] = ws_settings_claude_setup_drawer(!empty($pg_settings_in_dialog));', $pane, 'the pane draws the drawer');
}

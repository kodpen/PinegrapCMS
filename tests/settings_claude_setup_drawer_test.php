<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents, Claude's row: the set-up on
 * claude.ai is a drawer opened by one quiet button, which reads "Finish the
 * set-up" while Claude is not ready. As the architect's drawing has it: an
 * icon tile, the title and the line "Five steps; every value has Copy beside
 * it."; five numbered steps (Bootstrap's .stepper), each a title and one
 * sentence with the values to copy beside a Copy button; a note on which
 * name Claude answers under; Close in the foot.
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
	$body = ws_claude_setup_drawer_html('<li class="stepper-item active">x</li>', array('The routine\'s address is missing.'), ws_claude_how_it_works(array('Orders')));
	pg_assert_contains('<ol class="stepper pg-stepper-cards ws-claude-setup-steps">', $body, 'numbered steps, Bootstrap\'s stepper');
	pg_assert_contains('<li class="stepper-item active">x</li>', $body, 'the steps inside');
	pg_assert_contains(h(lang('Each run counts against the routine owner\'s daily allowance on claude.ai.')), $body, 'the cost moved here from the switch');
	pg_assert_contains('<h4 class="pg-group-label mt-5" id="ws-claude-how-title">' . h(lang('How does this work?')) . '</h4>', $body, 'the row\'s How does this work? scrolls here');
	pg_assert_contains('<ul class="pg-text-2 mb-5 ws-claude-missing"><li>' . h('The routine\'s address is missing.') . '</li></ul>', $body, 'the whole list of what is missing stands at the top');
	pg_assert_true(strpos($body, 'ws-claude-missing') < strpos($body, 'ws-claude-setup-steps'), 'before the steps');
	pg_assert_false(strpos(ws_claude_setup_drawer_html('x'), 'ws-claude-missing') !== false, 'nothing missing, no list');

	// The note after the steps, before How does this work?
	$note = strpos($body, '<div class="ws-claude-note" role="note">');
	pg_assert_true($note !== false && $note > strpos($body, 'ws-claude-setup-steps') && $note < strpos($body, 'ws-claude-how-title'), 'the note between the steps and How does this work?');
	pg_assert_contains(h(lang('This connection answers as @claude in the channel. For another agent that answers under its own name, use the Workspace agents row.')), $body, 'which name Claude answers under');

	// The five steps, a title and one sentence each.
	$guide = ws_claude_setup_guide('example.com', "line one\nline two", 'pk_1234');
	pg_assert_same(5, substr_count($guide, '<li class="stepper-item active ws-claude-step">'), 'five steps');
	pg_assert_true((bool) preg_match('/<li class="stepper-item active ws-claude-step">\s*<div class="ws-claude-step-body">/', $guide), 'one child an item, or the grid puts the text into the number\'s column');
	pg_assert_same(5, substr_count($guide, '<div class="ws-claude-step-title">'), 'each with its title');
	pg_assert_same(5, substr_count($guide, '<p class="pg-text-2 fg-2 mb-0">'), 'and one sentence');
	pg_assert_false(strpos($guide, '<svg') !== false || strpos($guide, '<img') !== false, 'no pictures');

	$titles = array(lang('Create the routine'), lang('Set up its environment'), lang('Add the API trigger'), lang('Paste the routine address and the token here'), lang('Try'));
	$last = -1;

	foreach ($titles as $title) {
		$at = strpos($guide, '<div class="ws-claude-step-title">' . h($title) . '</div>');
		pg_assert_true($at !== false && $at > $last, $title . ' in its place');
		$last = $at;
	}

	// The values to copy: the prompt in a code box, the domain and the key as
	// value rows, each with its Copy.
	pg_assert_contains('<div class="ws-claude-code"><textarea class="font-monospace" id="ws-claude-prompt"', $guide, 'the prompt in a code box');
	pg_assert_contains("line one\nline two</textarea>", $guide, 'the prompt as it is');
	pg_assert_contains('id="ws-claude-host" value="example.com" readonly>', $guide, 'the domain');
	pg_assert_contains('id="ws-claude-setup-key" value="pk_1234" readonly>', $guide, 'the key');
	pg_assert_same(3, substr_count($guide, 'data-ws-copy-field="'), 'three Copy buttons');
	pg_assert_false(strpos(ws_claude_setup_guide('example.com', 'p', ''), 'ws-claude-setup-key') !== false, 'no key, no row for it');
	pg_assert_contains('href="https://claude.ai/code/routines" target="_blank" rel="noopener"', $guide, 'claude.ai\'s routines open from the first step');
	pg_assert_contains('aria-controls="ws-claude-setup-credentials">' . h(lang('Where do the key and secret go?')), $guide, 'where the key and secret go, folded');
	pg_assert_contains('id="ws-claude-setup-credentials" hidden', $guide, 'shut');

	$drawer = settings_claude_setup_drawer_test_function('includes/workspace/claude.php', 'ws_settings_claude_setup_drawer');
	pg_assert_contains("pg_settings_drawer('pgset-ws-claude-setup', h(lang('Set up on claude.ai'))", $drawer, 'the drawer and its title');
	pg_assert_contains("h(lang('Five steps; every value has Copy beside it.'))", $drawer, 'the line under the title');
	pg_assert_contains('pg-icon-tile pg-icon-tile-lg pg-tone-violet', $drawer, 'Claude\'s tile');
	pg_assert_contains("h(lang('Close'))", $drawer, 'Close in the foot');
	pg_assert_contains("(\$app && \$admin) ? (string) \$app['api_key'] : ''", $drawer, 'only an administrator sees the key');

	$html = pg_settings_drawer('d', 'T', 'B', true, 'F', 'L', '', '<span class="tile"></span>');
	pg_assert_true(strpos($html, '<span class="tile"></span>') < strpos($html, 'pg-sm-drawer-title'), 'the tile before the title');
}

function test_settings_claude_setup_drawer_row()
{
	$card = settings_claude_setup_drawer_test_function('includes/workspace/claude.php', 'ws_claude_settings_card')
		. settings_claude_setup_drawer_test_function('includes/workspace/claude.php', 'ws_claude_row_html');
	pg_assert_false(strpos($card, 'ws-claude-guide') !== false, 'the guide left the row');
	pg_assert_false(strpos($card, 'ws_claude_setup_guide(') !== false, 'the row draws no step');
	pg_assert_contains('ws_claude_setup_button($missing)', $card, 'the one button that opens it');
	pg_assert_contains("lang('When @claude is written in a channel, your routine on claude.ai answers.')", $card, 'one sentence beside the switch');
	pg_assert_false(strpos($card, 'A discussion Claude follows') !== false, 'the rest is in the drawer');

	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/workspace_assistants.php');
	pg_assert_contains('$pg_settings_modals[] = ws_settings_claude_setup_drawer(!empty($pg_settings_in_dialog), (int) $ws_viewer[\'role\'] === 0);', $pane, 'the pane draws the drawer');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents, the help lines: each of Pinegrap
 * AI, Workspace agents, MCP servers, Voice and Canvas templates says one line
 * and keeps the rest behind "How does this work?" in a drawer of numbered
 * steps; the cards no longer carry the long paragraphs, and Add an agent
 * offers to start from an application.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_help.php');

function settings_assistants_help_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_assistants_help_parts()
{
	pg_assert_same(array('ws-ai', 'ws-agents', 'ws-mcp', 'ws-voice', 'ws-canvas-templates'), ws_assistants_help_keys(), 'the five parts with a help line');
	pg_assert_same('', ws_assistants_help_line('ws-claude'), 'Claude\'s row is its own step');
	pg_assert_same(array(), ws_assistants_guide_steps('ws-bulk'), 'no guide for an unknown part');
}

function test_settings_assistants_help_one_line()
{
	foreach (ws_assistants_help_keys() as $key) {
		$line = ws_assistants_help_line($key);
		pg_assert_true($line !== '', $key . ': a line');
		pg_assert_true(strpos($line, "\n") === false, $key . ': one line');
		pg_assert_true(mb_strlen($line) <= 130, $key . ': short, ' . mb_strlen($line));
		pg_assert_same(1, preg_match_all('/[.?!]["”]?(\s|$)/u', $line), $key . ': one sentence');
	}
}

function test_settings_assistants_help_guides()
{
	foreach (ws_assistants_help_keys() as $key) {
		$steps = ws_assistants_guide_steps($key);
		pg_assert_true(count($steps) >= 2, $key . ': numbered steps');

		foreach ($steps as $n => $step) {
			pg_assert_true(($step['title'] ?? '') !== '' && ($step['text'] ?? '') !== '', $key . ' step ' . $n . ': a title and a text');
		}

		pg_assert_true(ws_assistants_guide_title($key) !== '', $key . ': a drawer title');
	}
}

function test_settings_assistants_help_markup()
{
	$html = ws_assistants_help_html('ws-mcp');
	pg_assert_contains('data-bs-target="#pgset-ws-mcp-guide"', $html, 'opens its drawer');
	pg_assert_contains(h(lang('How does this work?')), $html, 'the button\'s words');
	pg_assert_contains(h(ws_assistants_help_line('ws-mcp')), $html, 'the line');

	$drawer = ws_assistants_guide_drawer('ws-ai', true);
	pg_assert_contains('id="pgset-ws-ai-guide"', $drawer, 'the drawer\'s id');
	pg_assert_contains('<ol class="ws-claude-steps', $drawer, 'numbered steps');
	pg_assert_contains('href="api_settings.php"', $drawer, 'a step leads to Application Access');
	pg_assert_same('', ws_assistants_guide_drawer('ws-claude', true), 'no drawer for a part without a guide');
}


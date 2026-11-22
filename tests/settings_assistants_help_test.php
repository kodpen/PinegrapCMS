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

	$drawer = ws_settings_assistants_guide_drawer('ws-ai', true);
	pg_assert_contains('id="pgset-ws-ai-guide"', $drawer, 'the drawer\'s id');
	pg_assert_contains('<ol class="stepper pg-stepper-cards ws-claude-setup-steps mb-0">', $drawer, 'numbered steps, the stepper of Claude\'s set-up drawer');
	pg_assert_contains('<li class="stepper-item active ws-claude-step">', $drawer, 'each step a coloured stepper item');
	pg_assert_contains('href="api_settings.php"', $drawer, 'a step leads to Application Access');
	pg_assert_same('', ws_settings_assistants_guide_drawer('ws-claude', true), 'no drawer for a part without a guide');
}

function test_settings_assistants_help_cards()
{
	$cards = array(
		'ws-ai'               => 'includes/workspace/ai.php',
		'ws-agents'           => 'includes/workspace/agents_settings.php',
		'ws-mcp'              => 'includes/workspace/mcp.php',
		'ws-voice'            => 'includes/workspace/voice.php',
		'ws-canvas-templates' => 'includes/workspace/canvas_templates_card.php',
	);

	foreach ($cards as $key => $file) {
		$source = settings_assistants_help_test_source($file);
		pg_assert_true(strpos($source, "ws_assistants_help_html('" . $key . "')") !== false || strpos($source, "ws_assistants_help_line('" . $key . "')") !== false, $key . ': the card draws its line');
	}

	$gone = array(
		'includes/workspace/ai.php'                    => array("lang('How to connect it')", "lang('Pinegrap AI is part of Pinegrap Premium and works with the subscription key of the site; it has no key of its own.')"),
		'includes/workspace/agents_settings.php'       => array("lang('Agents act as members of the channels they are added to, each through its own application."),
		'includes/workspace/mcp.php'                   => array("<summary>' . h(lang('How it works'))", "Add a ready-made connector with one click, or any other by its address.')) . '</p>"),
		'includes/workspace/canvas_templates_card.php' => array("lang('Canvases marked with Save as template in the image editor."),
	);

	foreach ($gone as $file => $texts) {
		foreach ($texts as $text) {
			pg_assert_true(strpos(settings_assistants_help_test_source($file), $text) === false, $file . ': the long text moved to the drawer: ' . $text);
		}
	}

	pg_assert_contains('ws_settings_assistants_guide_drawer(', settings_assistants_help_test_source('includes/settings/workspace_assistants.php'), 'the pane draws the guides');
	pg_assert_contains("lang('Start from an application')", settings_assistants_help_test_source('includes/workspace/agents_settings.php'), 'Add an agent offers an application');
}

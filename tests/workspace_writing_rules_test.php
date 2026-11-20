<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a Workspace message is written (includes/workspace/writing_rules.php):
 * every example the lines give draws through the renderer as the lines say,
 * the lines keep their length, the agent instructions carry them (so their
 * version changes) and the guide's workspace topic lists them as its
 * writing section within the topic's size.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/writing_rules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');

// Each example drawn as a message: the mark the lines promise is there.
function test_workspace_writing_rules_examples_draw()
{
	$expect = array(
		'bold'  => '<strong>Approved</strong>',
		'code'  => '<code>render.php</code>',
		'list'  => '<ul class="ws-list">',
		'table' => '<table class="table table-sm ws-table',
		'quote' => '<blockquote class="ws-quote">',
		'link'  => '<a href="https://github.com/o/r/pull/12"',
	);
	$examples = ws_writing_rules_examples();

	pg_assert_same(array_keys($expect), array_keys($examples), 'every example has its check');

	foreach ($examples as $mark => $text) {
		pg_assert_contains($expect[$mark], ws_render_body($text, array()), 'drawn: ' . $mark);
	}
}

// The lines quote their examples and name only drawn marks; a heading and a
// bare pull request number stay text, as the lines say.
function test_workspace_writing_rules_lines_say_what_is_drawn()
{
	$lines = implode("\n", ws_writing_rules_lines());
	$e = ws_writing_rules_examples();

	foreach (array($e['bold'], $e['code'], $e['link'], '| Item | Qty |', '|---|---|', '"- "', '"1. "', '"> "', '<#message:ID>', 'No headings') as $needle) {
		pg_assert_contains($needle, $lines, 'the lines say ' . $needle);
	}

	pg_assert_false(strpos(ws_render_body('# Title', array()), '<h') !== false, 'a heading stays text in a message');
	pg_assert_false(strpos(ws_render_body('See #12', array()), '<a ') !== false, 'a bare number is no link');
	pg_assert_contains('<ol class="ws-list">', ws_render_body("Steps:\n1. one\n2. two\n\nDone.", array()), 'a numbered list with an empty line after it');
}

function test_workspace_writing_rules_length()
{
	$length = strlen(implode("\n", ws_writing_rules_lines()));

	pg_assert_true($length <= WS_WRITING_RULES_MAX, 'the lines hold ' . $length . ' characters, at most ' . WS_WRITING_RULES_MAX);
	foreach (array('the short form' => ws_writing_rules_short(), 'the pointer' => WS_WRITING_RULES_POINTER) as $name => $sentence) {
		pg_assert_same('.', substr($sentence, -1), $name . ' ends with a full stop');
		pg_assert_same(0, preg_match('/[.!?]\s/', $sentence), $name . ' is one sentence');
	}

	pg_assert_contains('section=writing', WS_WRITING_RULES_POINTER, 'the pointer names the section');
}

// The instructions carry the lines before the rules; their version is not
// the version of the text without them.
function test_workspace_writing_rules_in_agent_instructions()
{
	$agent = array('id' => 7, 'kind' => 'app', 'handle' => 'mimar', 'name' => 'Architect', 'role' => 'architect', 'door_kind' => 'poll');
	$channels = array(array('id' => 25, 'name' => 'dev', 'wake_rule' => 'mention', 'wake_prefix' => '', 'agents' => array()));
	$text = ws_agent_instructions_text($agent, $channels, 'https://shop.example/pinegrap/integration.php');
	$block = implode("\n", ws_agent_instructions_writing_lines());

	pg_assert_contains(implode("\n- ", ws_writing_rules_lines()), $block, 'every line, one item each');
	pg_assert_contains($block, $text, 'the lines are in the instructions');
	pg_assert_true(strpos($text, $block) < strpos($text, "\nRules:"), 'before the assistant rules');

	$before = str_replace("\n" . $block . "\n", '', $text);

	pg_assert_true($before !== $text, 'the block comes out whole');
	pg_assert_false(ws_agent_instructions_version($before) === ws_agent_instructions_version($text), 'the version changes');
}

function test_workspace_writing_rules_guide_section()
{
	$registry = pg_agent_guide_registry();
	$text = (string) pg_agent_guide_topic_text($registry, 'workspace', array('workspace', 'store', 'erp'));
	$sizes = pg_agent_guide_sizes();

	pg_assert_contains('(#writing)', $text, 'the writing section is listed');

	foreach (ws_writing_rules_lines() as $line) {
		pg_assert_contains('- ' . $line, $text, 'the guide carries: ' . $line);
	}

	pg_assert_true(strlen($text) <= $sizes['topic'], 'the topic holds ' . strlen($text) . ' bytes');
	pg_assert_true(is_string(pg_agent_guide_topic_text($registry, 'workspace', array('workspace'), 'writing')), 'the section reads alone');
}

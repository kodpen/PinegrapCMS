<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a voice message that asks an MCP agent (VA6): the microphone
 * answers an agent's message, the agent reads the recording's transcript as
 * the other assistants do, the ask goes after the transcript to the agent
 * whose request the reply continues, and a discussion follows it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_ask.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');

function ws_voice_agents_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a method of the channel screen, up to the next method.
function ws_voice_agents_test_method($source, $name)
{
	$from = strpos($source, "\n        " . $name . ': function');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n        },\n", $from + 1);

	return ($to === false) ? '' : trim(substr($source, $from, $to + 11 - $from));
}

// The microphone answers an MCP agent's message as it answers Claude's and
// Pinegrap AI's: in the reply bar and on the agent's own sound. Run under
// Node; skipped without it.
function test_ws_voice_mic_answers_an_agent()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$method = ws_voice_agents_test_method(ws_voice_agents_test_source('assets/js/workspace.js'), 'voiceAnswers');
	pg_assert_true($method !== '', 'voiceAnswers() is there');

	$file = tempnam(sys_get_temp_dir(), 'pgagent') . '.js';
	$probe = 'var screen = {' . "\n" . $method . "\n" . '};' . "\n"
		. 'var out = {};' . "\n"
		. 'out.agent = screen.voiceAnswers({ id: 1, sender: { mcp: "crm", app: true } });' . "\n"
		. 'out.claude = screen.voiceAnswers({ id: 2, sender: { claude: true, app: true } });' . "\n"
		. 'out.person = screen.voiceAnswers({ id: 3, sender: { id: 4 } });' . "\n"
		. 'out.none = screen.voiceAnswers(null);' . "\n"
		. 'console.log(JSON.stringify(out));';

	file_put_contents($file, $probe);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $lines));
	$result = json_decode(implode("\n", $lines), true);

	pg_assert_true($result['agent'], 'an MCP agent\'s message');
	pg_assert_true($result['claude'], 'Claude\'s message');
	pg_assert_true($result['person'], 'a person\'s message');
	pg_assert_false($result['none'], 'no message');
}

// The agent reads the transcript: what it is asked and the message the ask
// answers go through ws_mcp_agent_heard(), which joins the recording's
// transcript (or the reason nobody heard it) as the other assistants read
// it; a message without a recording reads as before.
function test_ws_voice_agent_reads_the_transcript()
{
	pg_assert_same('Ship it [voice message, machine transcript: to the customer]', ws_mcp_agent_heard_text('Ship it', 'to the customer', 'voice-1.weba', 0, ''), 'written out');
	pg_assert_same('[voice message, not transcribed: ' . ws_voice_unheard_words('off') . ']', ws_mcp_agent_heard_text('', '', 'voice-1.ogg', 0, 'off'), 'not written out, with the reason');
	pg_assert_same('Ship it', ws_mcp_agent_heard_text('Ship it', '', 'report.pdf', 0, 'off'), 'another file');
	pg_assert_same('Ship it', ws_mcp_agent_heard_text('Ship it', '', '', 0, ''), 'no file');
	pg_assert_same('Ship it [voice message, machine transcript: to the…]', ws_mcp_agent_heard_text('Ship it', 'to the customer', 'voice-1.weba', 6, ''), 'the transcript is cut like the words');

	$agent = ws_voice_agents_test_source('includes/workspace/mcp_agent.php');
	pg_assert_contains("\$parent_text = ws_mcp_agent_heard(\$asker, \$parent, WS_MCP_AGENT_CONTEXT_LIMIT);", $agent, 'the message a reply answers');
	pg_assert_contains("ws_mcp_agent_unaddressed(ws_mcp_agent_heard(\$asker, \$message, WS_MCP_AGENT_TEXT_LIMIT), \$server['alias'])", $agent, 'the request');
	pg_assert_contains('return ws_mcp_agent_heard_text($text, $transcript, (string) ($row[\'file_name\'] ?? \'\'), $limit, $reason);', $agent, 'through the pure join');
}

// A recording that answers an agent's message asks that agent once it is
// written out: ws_voice_ask() asks as a message does, and the agent is found
// by the request its parent answered, with or without the alias beside the
// microphone. The screen carries the agent's request on as after any
// message, and starts it at once when the recording asked at once.
function test_ws_voice_agent_asked_after_the_transcript()
{
	$voice = ws_voice_agents_test_source('includes/workspace/voice.php');
	pg_assert_contains("\$asked = ws_message_ask_assistants(\$viewer, \$channel, (int) \$message['id'], (string) \$message['body']);", $voice, 'ws_voice_ask() asks as a message does');

	$messages = ws_voice_agents_test_source('includes/workspace/messages.php');
	pg_assert_contains("\$agent = function_exists('ws_mcp_agent_after_send') ? ws_mcp_agent_after_send(\$viewer, \$channel, \$message_id, \$body) : null;", $messages, 'the agent is among the assistants asked');

	$agent = ws_voice_agents_test_source('includes/workspace/mcp_agent.php');
	pg_assert_contains("\$server = (\$followed > 0) ? ws_mcp_server(\$followed) : ws_mcp_ask_server(\$body, \$message_id);", $agent, 'the agent of the message');

	$ask = ws_voice_agents_test_source('includes/workspace/mcp_ask.php');
	pg_assert_contains("WHERE reply_message_id = '\" . (int) \$message['parent_id'] . \"' AND agent IN ('ai', 'mcp') AND mcp_server_id > 0", $ask, 'found by the request the parent answered');
	pg_assert_contains("if (((int) \$message_id <= 0) || ws_ai_asked(\$body)) {\n        return null;\n    }", $ask, 'tags alone beside the microphone still find it');

	$js = ws_voice_agents_test_source('assets/js/workspace.js');
	pg_assert_contains("if (result.agent) {\n                    self.kickAgent(true);", $js, 'asked at once: the screen starts it');
	pg_assert_contains("return message.claude && message.claude.agent === 'mcp' && /^(queued|sent|running)$/.test(message.claude.status);", $js, 'asked after the transcript: the screen carries it on');
	pg_assert_contains("'agent' => !empty(\$asked['agent'])", ws_voice_agents_test_source('includes/workspace/actions.php'), 'ws_attach answers agent');
}

// In a discussion, a recording answering an agent's message asks the agent
// and the discussion follows it from then on, as a written reply does.
function test_ws_voice_agent_in_a_discussion()
{
	$threads = ws_voice_agents_test_source('includes/workspace/threads.php');
	pg_assert_contains("\$server = function_exists('ws_mcp_ask_server') ? ws_mcp_ask_server(\$body, \$message_id) : null;", $threads, 'the agent a reply answers');
	pg_assert_contains("\$out[] = 'mcp:' . (int) \$server['id'];", $threads, 'followed as the agent');

	$messages = ws_voice_agents_test_source('includes/workspace/messages.php');
	pg_assert_contains("\$named = \$follows ? ws_thread_agents_asked(\$body, \$message_id, \$channel) : array();", $messages, 'ws_voice_ask() reaches it through ws_message_ask_assistants()');
	pg_assert_contains("\$agent = (\$kind === 'agent') ? true : \$agent;", $messages, 'a discussion that follows the agent asks it');
}

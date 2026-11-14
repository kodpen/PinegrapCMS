<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for spoken answers (includes/workspace/voice.php, workspace.js) that
 * need no database: the words of an answer as they are spoken, the language
 * they are spoken in, which message counts as a recording, where the three
 * answer routes ask for the sound, and the screen reading an answer to the
 * viewer's own recording aloud whatever the switch says.
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

// The words a voice reads: no tags (the mention of the asker, a record), no
// code blocks, no Markdown marks; lines kept, white space within them folded.
function test_ws_voice_answer_text()
{
	pg_assert_same('Merhaba, yarın görüşelim.', ws_voice_answer_text('<@user:4> Merhaba,   yarın görüşelim.'), 'the mention and the spaces');
	pg_assert_same('The order is shipped.', ws_voice_answer_text('The order <#order:12> is shipped.'), 'a record tag');
	pg_assert_same("First line\nSecond line", ws_voice_answer_text("First line\n\n\n\nSecond line\n"), 'lines kept, empty ones folded');
	pg_assert_same('Run it, then look.', ws_voice_answer_text("Run it, then look.\n```sh\nphp tools/test.php\n```"), 'a code block is not read');
	pg_assert_same('Bold and plain and code.', ws_voice_answer_text('**Bold** and _plain_ and `code`.'), 'marks taken off');
	pg_assert_same("Title\nPoint one\nPoint two", ws_voice_answer_text("## Title\n- Point one\n* Point two"), 'headings and bullets');
	pg_assert_same('', ws_voice_answer_text("<@user:4>\n```\nx\n```"), 'nothing left to say');
}

// Turkish letters mean Turkish, whatever the site speaks; otherwise the
// site's own language; a code that is no code is English.
function test_ws_voice_answer_locale_rule()
{
	pg_assert_same('tr', ws_voice_answer_locale_rule('Yarın görüşelim.', 'en'), 'Turkish letters');
	pg_assert_same('en', ws_voice_answer_locale_rule('See you tomorrow.', 'en'), 'the site\'s');
	pg_assert_same('de', ws_voice_answer_locale_rule('Bis morgen.', 'de_DE'), 'a locale cut to its code');
	pg_assert_same('en', ws_voice_answer_locale_rule('Hello.', ''), 'no code');
	pg_assert_same('en', ws_voice_answer_locale_rule('Hello.', '12'), 'not a code');
}

// Only a recording still there asks for a spoken answer: a sound somebody
// attached, a deleted message or words alone do not.
function test_ws_voice_answer_to_recording()
{
	pg_assert_true(ws_voice_answer_to_recording(array('file_id' => 5, 'file_name' => 'voice-20261102-101500.weba', 'deleted_at' => 0)), 'a recording');
	pg_assert_true(ws_voice_answer_to_recording(array('file_id' => 5, 'file_name' => 'voice-20261102-101500.mp3', 'deleted_at' => 0)), 'a spoken message');
	pg_assert_false(ws_voice_answer_to_recording(array('file_id' => 5, 'file_name' => 'song.mp3', 'deleted_at' => 0)), 'an attached sound');
	pg_assert_false(ws_voice_answer_to_recording(array('file_id' => 5, 'file_name' => 'voice-20261102-101500.weba', 'deleted_at' => 1762000000)), 'deleted');
	pg_assert_false(ws_voice_answer_to_recording(array('file_id' => 0, 'file_name' => '', 'deleted_at' => 0)), 'words alone');
	pg_assert_false(ws_voice_answer_to_recording(null), 'no message');
}

// Every route that writes an assistant's answer asks for its sound on the
// same message; the screen reads an answer to the viewer's recording aloud
// and plays the sound the site made.
function test_ws_voice_answers_are_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains("ws_voice_answer_spoken(\$channel, (int) \$sent['message_id'], (int) \$row['message_id'], \$text, (int) \$row['requested_by'])", $read('includes/workspace/ai.php'), 'Pinegrap AI');
	pg_assert_contains("ws_voice_answer_spoken(\$channel, (int) \$sent['message_id'], (int) \$row['message_id'], \$text, (int) \$row['requested_by'])", $read('includes/workspace/api_claude.php'), 'Claude\'s answer route');
	pg_assert_contains("ws_voice_answer_spoken(\$channel, \$reply_id, (int) \$message['id'], ws_mcp_agent_neutral(\$answer, WS_MESSAGE_MAX), (int) \$asker['id'])", $read('includes/workspace/mcp_agent.php'), 'an MCP agent');
	$voice = $read('includes/workspace/voice.php');
	$from = strpos($voice, 'function ws_voice_answer_spoken(');
	$spoken = substr($voice, $from, strpos($voice, "\n}\n", $from) - $from);

	pg_assert_contains("!ws_voice_channel_allowed(\$channel)", $spoken, 'a channel that keeps its recordings sends no answer out either');
	pg_assert_contains("asked = !!(parent && parent.mine) && this.answersRecording(parent)", $read('assets/js/workspace.js'), 'the screen knows an answer to a recording');
	pg_assert_contains("(!asked && !voice.speakOn(CFG.api_url, CFG.me))", $read('assets/js/workspace.js'), 'the switch only decides other answers');
	pg_assert_contains("window.PGWsVoice.isVoice(file.name)", $read('assets/js/workspace.js'), 'a recording by name');
}

// speakAnswer() as the screen runs it, under Node with stubs: an answer to
// the viewer's recording is read with the switch off; one with a sound is
// played instead of read; an answer to words follows the switch.
function test_ws_voice_answers_read_aloud()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$from = strpos($screen, 'speakAnswer: function (message, node) {');
	$to = strpos($screen, "\n        },", $from);
	$recording_from = strpos($screen, 'answersRecording: function (message) {');
	$recording_to = strpos($screen, "\n        },", $recording_from);

	pg_assert_true(($from !== false) && ($to !== false) && ($recording_from !== false) && ($recording_to !== false), 'the methods are there');

	preg_match('/^    function speaksAnswer\(message, parent, channel\) \{.*?^    \}$/ms', $screen, $decides);
	pg_assert_true(!empty($decides), 'speaksAnswer() is there');

	$methods = ($decides[0] ?? '') . "\n"
		. 'var screen = { channel: { agents: [] }, findMessage: function (id) { return parents[id]; }, '
		. substr($screen, $from, $to - $from) . "\n        }, "
		. substr($screen, $recording_from, $recording_to - $recording_from) . "\n        } };";
	$file = tempnam(sys_get_temp_dir(), 'pgspeak') . '.js';
	$probe = 'var CFG = { api_url: "s", me: 3, locale: "tr" }; var on = false; var spoken = []; var played = 0;' . "\n"
		. 'global.document = { hidden: false, documentElement: { lang: "tr" } };' . "\n"
		. 'global.window = { PGWsVoice: { speakSupported: function () { return true; }, speakOn: function () { return on; }, speak: function (text) { spoken.push(text); }, isVoice: function (name) { return /^voice-/.test(name); } } };' . "\n"
		. 'var parents = { 1: { id: 1, mine: true, file: { kind: "audio", name: "voice-20261102-101500.weba" } }, 2: { id: 2, mine: true, file: null }, 3: { id: 3, mine: false, file: { kind: "audio", name: "voice-20261102-101500.weba" } } };' . "\n"
		. $methods . "\n"
		. 'function node(withAudio, text) { var audio = withAudio ? { play: function () { played++; return { catch: function () {} }; } } : null;'
		. ' var body = { cloneNode: function () { return { querySelectorAll: function () { return { forEach: function () {} }; }, textContent: text }; } };'
		. ' return { querySelector: function (sel) { return (sel === "audio") ? audio : ((sel === ".ws-msg-body") ? body : null); } }; }' . "\n"
		. 'function answer(parent, file) { return { sender_kind: "app", sender: { claude: true }, parent_id: parent, file: file || null }; }' . "\n"
		. 'var out = {};' . "\n"
		. 'screen.speakAnswer(answer(1), node(false, "Spoken.")); out.recording_off = spoken.length;' . "\n"
		. 'screen.speakAnswer(answer(2), node(false, "Text.")); out.words_off = spoken.length;' . "\n"
		. 'screen.speakAnswer(answer(1, { kind: "audio", name: "voice-20261102-101501.mp3" }), node(true, "Sound.")); out.sound_played = played; out.sound_spoken = spoken.length;' . "\n"
		. 'screen.speakAnswer(answer(3), node(false, "Theirs.")); out.others = spoken.length;' . "\n"
		. 'on = true; screen.speakAnswer(answer(2), node(false, "Text on.")); out.words_on = spoken.length;' . "\n"
		. 'document.hidden = true; screen.speakAnswer(answer(1), node(false, "Hidden.")); out.hidden = spoken.length;' . "\n"
		. 'console.log(JSON.stringify(out));';

	file_put_contents($file, $probe);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $lines));
	$result = json_decode(implode("\n", $lines), true);

	pg_assert_same(1, $result['recording_off'], 'an answer to my recording is read with the switch off');
	pg_assert_same(1, $result['words_off'], 'an answer to my words is not, with the switch off');
	pg_assert_same(1, $result['sound_played'], 'the sound the site made is played');
	pg_assert_same(1, $result['sound_spoken'], 'and not read as well');
	pg_assert_same(1, $result['others'], 'never somebody else\'s answer');
	pg_assert_same(2, $result['words_on'], 'an answer to my words is read with the switch on');
	pg_assert_same(2, $result['hidden'], 'nothing while the tab is out of sight');
}

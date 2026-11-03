<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for why nobody heard a voice message (includes/workspace/voice.php)
 * that need no database: the reason an assistant did not hear a recording
 * (off, skipped, failed, pending), the words it reads and the line that
 * tells it where the setting is, and the recorder's hint that the
 * assistants will not hear what is being recorded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');

function ws_voice_unheard_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The reason by the row's status and the site's and the channel's state
// now: nothing once written out, the site's switch first, then the
// service's failure, a recording still waiting, and a channel that keeps
// its recordings (or a run that left one out) last.
function test_ws_voice_unheard_rule()
{
	pg_assert_same('', ws_voice_unheard_rule('done', true, true), 'written out');
	pg_assert_same('', ws_voice_unheard_rule('done', false, false), 'written out before the switch went off');
	pg_assert_same('off', ws_voice_unheard_rule('', false, true), 'transcripts are off');
	pg_assert_same('off', ws_voice_unheard_rule('failed', false, true), 'the switch first');
	pg_assert_same('off', ws_voice_unheard_rule('queued', false, false), 'a queued recording while off');
	pg_assert_same('failed', ws_voice_unheard_rule('failed', true, true), 'the service failed');
	pg_assert_same('failed', ws_voice_unheard_rule('failed', true, false), 'failed before the channel closed');
	pg_assert_same('pending', ws_voice_unheard_rule('queued', true, true), 'still waiting');
	pg_assert_same('pending', ws_voice_unheard_rule('running', true, true), 'being written out');
	pg_assert_same('skipped', ws_voice_unheard_rule('', true, false), 'the channel keeps its recordings');
	pg_assert_same('skipped', ws_voice_unheard_rule('skipped', true, true), 'the run left it out');
	pg_assert_same('skipped', ws_voice_unheard_rule('', true, true), 'posted before transcripts were allowed');
}

// What the assistant reads inside the message's note, and the line after
// the request that says what to answer and where the setting is.
function test_ws_voice_unheard_words_and_line()
{
	foreach (array('off', 'skipped', 'failed', 'pending') as $reason) {
		pg_assert_true(ws_voice_unheard_words($reason) !== '', $reason . ' has words');
		pg_assert_contains('could not hear', ws_voice_unheard_line($reason), $reason . ' says it could not hear');
		pg_assert_contains('not guess', ws_voice_unheard_line($reason), $reason . ' never guesses');
		pg_assert_false(strpos(ws_voice_unheard_line($reason), '<<<') !== false, $reason . ' is no data block');
	}

	pg_assert_same('', ws_voice_unheard_words(''), 'heard: no words');
	pg_assert_same('', ws_voice_unheard_line(''), 'heard: no line');
	pg_assert_same('', ws_voice_unheard_words('other'), 'an unknown reason has no words');

	pg_assert_contains('switched off', ws_voice_unheard_words('off'));
	pg_assert_contains('Workspace Settings', ws_voice_unheard_line('off'), 'off names the settings card');
	pg_assert_contains('Voice message transcripts', ws_voice_unheard_line('off'), 'off names the card');
	pg_assert_contains('channel', ws_voice_unheard_words('skipped'));
	pg_assert_contains('channel menu', ws_voice_unheard_line('skipped'), 'skipped names the channel switch');
	pg_assert_contains('Voice messages are transcribed here', ws_voice_unheard_line('skipped'), 'skipped names the menu entry');
	pg_assert_contains('service', ws_voice_unheard_words('failed'));
	pg_assert_contains('still', ws_voice_unheard_words('pending'));
}

// The note the assistant reads carries the reason once it is known; without
// one it reads as before.
function test_ws_voice_heard_with_reason()
{
	pg_assert_same('@ai [voice message, not transcribed: ' . ws_voice_unheard_words('off') . ']', ws_voice_heard('@ai', '', 'voice-1.weba', 0, 'off'), 'off');
	pg_assert_same('[voice message, not transcribed: ' . ws_voice_unheard_words('failed') . ']', ws_voice_heard('', '', 'voice-1.ogg', 0, 'failed'), 'failed, no words');
	pg_assert_same('@ai [voice message, not transcribed]', ws_voice_heard('@ai', '', 'voice-1.weba', 0, ''), 'no reason');
	pg_assert_same('@ai [voice message, machine transcript: Ship it]', ws_voice_heard('@ai', 'Ship it', 'voice-1.weba', 0, 'off'), 'a transcript wins over a stale reason');
	pg_assert_same('@ai look', ws_voice_heard('@ai look', '', 'report.pdf', 0, 'off'), 'another file has no reason');
}

// The recorder's hint: off or skipped for the channel screen, nothing where
// the recording will be written out.
function test_ws_voice_channel_unheard_rule()
{
	pg_assert_same('off', ws_voice_channel_unheard_rule(false, true), 'off');
	pg_assert_same('off', ws_voice_channel_unheard_rule(false, false), 'off before closed');
	pg_assert_same('skipped', ws_voice_channel_unheard_rule(true, false), 'the channel keeps its recordings');
	pg_assert_same('', ws_voice_channel_unheard_rule(true, true), 'will be written out');
}

// The readers carry the reason: Pinegrap AI's and the MCP ask's briefing
// add the line after the request, Claude's instructions name it, the
// channel screen sends the recorder's reason and the recorder shows it.
function test_ws_voice_unheard_is_wired()
{
	$ai = ws_voice_unheard_test_source('includes/workspace/ai.php');
	pg_assert_contains('ws_voice_unheard_reason(', $ai, 'Pinegrap AI reads the reason');
	pg_assert_contains("\$parts[] = pg_ai_data_block('request', \$request_text);\n\n        if (\$unheard !== '') {\n            \$parts[] = ws_voice_unheard_line(\$unheard);", $ai, 'the line follows the request block');

	$mcp = ws_voice_unheard_test_source('includes/workspace/mcp_ask.php');
	pg_assert_contains("\$parts[] = \$request_text;\n\n    if (\$unheard !== '') {\n        \$parts[] = ws_voice_unheard_line(\$unheard);", $mcp, 'the MCP ask adds the line');

	$claude = ws_voice_unheard_test_source('includes/workspace/claude.php');
	pg_assert_contains('A request that was a voice message that was not', $claude, 'Claude\'s instructions name the case');

	pg_assert_contains("\$brief['voice_unheard'] = function_exists('ws_voice_channel_unheard') ? ws_voice_channel_unheard(\$channel) : '';", ws_voice_unheard_test_source('includes/workspace/channels.php'), 'the channel screen gets the reason');

	$js = ws_voice_unheard_test_source('assets/js/workspace.js');
	pg_assert_contains('unheard: self.voiceUnheard(),', $js, 'the recorder gets the hint');
	pg_assert_contains("CFG.urls.settings + '#ws-voice'", $js, 'role 0 gets the settings link');
	pg_assert_contains('parseInt(CFG.me_role, 10) === 0', $js, 'only role 0');

	$voice = ws_voice_unheard_test_source('assets/js/workspace_voice.js');
	pg_assert_contains('unheardHint(options.unheard, h)', $voice, 'the recorder draws the hint');
	pg_assert_contains('ws-voice-rec-hint', ws_voice_unheard_test_source('assets/css/backend.src.css'), 'the hint has its style');

	$tr = pg_test_language_map();

	foreach (array('voice_unheard_off', 'voice_unheard_skipped', 'voice_unheard_settings', 'voice_unheard_menu') as $key) {
		pg_assert_contains("'" . $key . "'", $js, $key . ' is asked for');
	}
}

// The hint is drawn as a line of the recording row: the words, the link in
// a new tab so the recording goes on, and nothing without a reason. Run
// under Node with stub elements; skipped without Node.
function test_ws_voice_unheard_hint_drawn()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$script = ws_voice_unheard_test_source('assets/js/workspace_voice.js');
	$file = tempnam(sys_get_temp_dir(), 'pghint') . '.js';
	$probe = 'global.window = { localStorage: null };' . "\n"
		. $script . "\n"
		. 'function el(tag, cls, text) { return { tag: tag, cls: cls, text: text || "", children: [], attrs: {}, appendChild: function (c) { this.children.push(c); return c; }, setAttribute: function (k, v) { this.attrs[k] = v; } }; }' . "\n"
		. 'var h = { el: el, t: function (k) { return k; } };' . "\n"
		. 'var v = window.PGWsVoice; var out = {};' . "\n"
		. 'out.none = v.unheardHint(null, h);' . "\n"
		. 'var plain = v.unheardHint({ text: "Nobody hears", link: null, note: "" }, h); out.plainText = plain.children[0].text; out.plainCount = plain.children.length; out.cls = plain.cls;' . "\n"
		. 'var linked = v.unheardHint({ text: "Nobody hears", link: { url: "settings#ws-voice", text: "Settings" }, note: "" }, h); var a = linked.children[1]; out.href = a.attrs.href; out.target = a.attrs.target; out.rel = a.attrs.rel; out.label = a.text; out.tag = a.tag;' . "\n"
		. 'var noted = v.unheardHint({ text: "Nobody hears", link: null, note: "Switch it on" }, h); out.note = noted.children[1].text;' . "\n"
		. 'console.log(JSON.stringify(out));';

	file_put_contents($file, $probe);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $lines));
	$result = json_decode(implode("\n", $lines), true);

	pg_assert_true($result['none'] === null, 'no reason, no hint');
	pg_assert_same('ws-voice-rec-hint', $result['cls']);
	pg_assert_same('Nobody hears', $result['plainText']);
	pg_assert_same(1, $result['plainCount'], 'words alone');
	pg_assert_same('a', $result['tag'], 'the link is an anchor');
	pg_assert_same('settings#ws-voice', $result['href']);
	pg_assert_same('_blank', $result['target'], 'opens in a new tab so the recording goes on');
	pg_assert_same('noopener', $result['rel']);
	pg_assert_same('Settings', $result['label']);
	pg_assert_same('Switch it on', $result['note'], 'the manager\'s note follows the words');
}

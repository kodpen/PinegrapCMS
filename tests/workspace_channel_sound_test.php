<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace channel sound: which of a person's new inbox lines
 * sound (soundFor()), one tone a look (soundBatch(), soundHeard()), the
 * per-person switch kept in the browser (soundOn(), soundSet()) run under
 * node, and the wiring of the screen's look (ws_sync, ws_sound_lines()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The sound's pieces of workspace.js, or null when one is not found.
function workspace_channel_sound_test_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$parts = array();

	foreach (array('SOUND_KINDS', 'SOUND_KEY', 'soundChosen') as $name) {
		if (!preg_match('/^    var ' . $name . ' = [^\n]*;$/m', $source, $match)) {
			return null;
		}

		$parts[] = $match[0];
	}

	foreach (array('soundFor(line, view)', 'soundBatch(lines, view, play)', 'soundKey(site, viewer)', 'soundOn(site, viewer)', 'soundSet(site, viewer, on)') as $name) {
		if (!preg_match('/^    function ' . preg_quote($name, '/') . ' \{.*?^    \}$/ms', $source, $match)) {
			return null;
		}

		$parts[] = $match[0];
	}

	if (!preg_match('/^        soundHeard: function \(sound\) \{.*?^        \},$/ms', $source, $match)) {
		return null;
	}

	$parts[] = 'var soundHeard = ' . substr(trim($match[0]), strlen('soundHeard: '), -1) . ';';

	return implode("\n", $parts) . "\n";
}

// Runs the script after the sound's pieces under node and returns what it
// printed as JSON; false when node is missing, null when a piece is missing.
function workspace_channel_sound_test_run($script)
{
	$source = workspace_channel_sound_test_source();

	if ($source === null) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwssnd');

	if ($file === false) {
		return false;
	}

	// A browser's storage, which can be made to refuse.
	$stub = "var store = {}; var refuse = false;\n"
		. "var window = { localStorage: {\n"
		. "  getItem: function (k) { if (refuse) { throw new Error('no'); } return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; },\n"
		. "  setItem: function (k, v) { if (refuse) { throw new Error('no'); } store[k] = String(v); },\n"
		. "  removeItem: function (k) { if (refuse) { throw new Error('no'); } delete store[k]; }\n"
		. "} };\n";

	file_put_contents($file, $stub . $source . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_channel_sound_which_lines_sound()
{
	$got = workspace_channel_sound_test_run(<<<'JS'
var front = { on: true, me: 5, visible: true, focused: true, channelId: 10, threadId: 30 };
function view(change) { var v = {}; Object.keys(front).forEach(function (k) { v[k] = front[k]; }); Object.keys(change || {}).forEach(function (k) { v[k] = change[k]; }); return v; }
function line(kind, channel, actor) { return { id: 1, kind: kind, channel_id: channel, actor_id: actor || 0 }; }
console.log(JSON.stringify({
	mention_elsewhere: soundFor(line('mention', 20, 3), front),
	mention_in_front: soundFor(line('mention', 10, 3), front),
	mention_window_away: soundFor(line('mention', 10, 3), view({ focused: false })),
	mention_tab_hidden: soundFor(line('mention', 10, 3), view({ visible: false })),
	mention_other_tab: soundFor(line('mention', 10, 3), view({ channelId: 0 })),
	agent_answer_elsewhere: soundFor(line('agent_answer', 20, 0), front),
	agent_answer_in_front: soundFor(line('agent_answer', 10, 0), front),
	question_elsewhere: soundFor(line('question_asked', 20, 0), front),
	thread_open_beside: soundFor(line('thread', 30, 3), front),
	thread_closed: soundFor(line('thread', 31, 3), front),
	guest_elsewhere: soundFor(line('guest_message', 20, 0), front),
	customer_wrote_again: soundFor(line('mail_customer', 20, 0), front),
	mail_given: soundFor(line('mail_assigned', 20, 3), front),
	own_doing: soundFor(line('mention', 20, 5), front),
	switched_off: soundFor(line('mention', 20, 3), view({ on: false })),
	task_given: soundFor(line('assigned', 20, 3), front),
	flow_finished: soundFor(line('flow_finished', 0, 0), front),
	note_updated: soundFor(line('note_updated', 0, 3), front),
	no_line: soundFor(null, front)
}));
JS
	);

	pg_assert_true($got !== null, 'workspace.js defines the sound\'s pieces');

	if ($got === false) {
		return;
	}

	pg_assert_true($got['mention_elsewhere'], 'a mention in another channel sounds');
	pg_assert_false($got['mention_in_front'], 'a mention in the conversation in front of the person is silent');
	pg_assert_true($got['mention_window_away'], 'a mention in the open channel sounds while the window is not focused');
	pg_assert_true($got['mention_tab_hidden'], 'a mention in the open channel sounds while the tab is hidden');
	pg_assert_true($got['mention_other_tab'], 'a mention in the open channel sounds while another of its tabs is shown');
	pg_assert_true($got['agent_answer_elsewhere'], 'an agent\'s answer elsewhere sounds');
	pg_assert_false($got['agent_answer_in_front'], 'an agent\'s answer in front of the person is silent');
	pg_assert_true($got['question_elsewhere'], 'a question asked of the person sounds');
	pg_assert_false($got['thread_open_beside'], 'a discussion open beside the channel is in front');
	pg_assert_true($got['thread_closed'], 'a discussion started on their message sounds');
	pg_assert_true($got['guest_elsewhere'], 'a guest\'s message sounds');
	pg_assert_true($got['customer_wrote_again'], 'a customer writing again on the person\'s mail conversation sounds');
	pg_assert_false($got['mail_given'], 'a mail conversation given is no answer, like a task given');
	pg_assert_false($got['own_doing'], 'the person\'s own doing never sounds');
	pg_assert_false($got['switched_off'], 'nothing sounds with the switch off');
	pg_assert_false($got['task_given'], 'a task given is no conversation line');
	pg_assert_false($got['flow_finished'], 'a flow run is no conversation line');
	pg_assert_false($got['note_updated'], 'a note change is no conversation line');
	pg_assert_false($got['no_line'], 'no line, no sound');
}

function test_workspace_channel_sound_once_a_look()
{
	$got = workspace_channel_sound_test_run(<<<'JS'
var played = 0;
function play() { played++; }
var away = { on: true, me: 5, visible: true, focused: false, channelId: 10, threadId: 0 };
var three = [
	{ id: 4, kind: 'mention', channel_id: 10, actor_id: 3 },
	{ id: 5, kind: 'agent_answer', channel_id: 20, actor_id: 0 },
	{ id: 6, kind: 'question_asked', channel_id: 21, actor_id: 0 }
];
var batch = { returned: soundBatch(three, away, play), played: played };
played = 0;
var none = { returned: soundBatch([{ id: 7, kind: 'assigned', channel_id: 20, actor_id: 3 }], away, play), played: played };
played = 0;
var empty = { returned: soundBatch([], away, play), played: played };

// The screen's look: the first only sets the mark, so does the one after
// the tab comes back; a look with lines for the person plays once.
var CFG = { api_url: 'https://example.test/api.php', me: 5 };
var document = { hidden: false, hasFocus: function () { return false; } };
var soundPlay = play;
var screen = { soundMark: null, soundQuiet: false, channel: { id: 10 }, tab: 'messages', era: null, openThreadId: function () { return 0; }, soundHeard: soundHeard };
var looks = [];
played = 0;
screen.soundHeard({ mark: 3, lines: [] });
looks.push({ mark: screen.soundMark, played: played });
screen.soundHeard({ mark: 6, lines: three });
looks.push({ mark: screen.soundMark, played: played });
screen.soundQuiet = true;
screen.soundHeard({ mark: 8, lines: [{ id: 8, kind: 'mention', channel_id: 20, actor_id: 3 }] });
looks.push({ mark: screen.soundMark, played: played, quiet: screen.soundQuiet });
screen.soundHeard({ mark: 9, lines: [{ id: 9, kind: 'mention', channel_id: 20, actor_id: 3 }] });
looks.push({ mark: screen.soundMark, played: played });
screen.soundHeard({ mark: 2, lines: [] });
looks.push({ mark: screen.soundMark, played: played });
var first = { soundMark: null, soundQuiet: false, channel: null, tab: 'messages', era: null, openThreadId: function () { return 0; }, soundHeard: soundHeard };
played = 0;
first.soundHeard({ mark: 12, lines: [{ id: 12, kind: 'mention', channel_id: 20, actor_id: 3 }] });
console.log(JSON.stringify({ batch: batch, none: none, empty: empty, looks: looks, first: { mark: first.soundMark, played: played } }));
JS
	);

	pg_assert_true(is_array($got), 'the look runs under node');

	if (!is_array($got)) {
		return;
	}

	pg_assert_same(array('returned' => true, 'played' => 1), $got['batch'], 'three lines for the person play the tone once');
	pg_assert_same(array('returned' => false, 'played' => 0), $got['none'], 'no line for the person plays nothing');
	pg_assert_same(array('returned' => false, 'played' => 0), $got['empty'], 'an empty look plays nothing');
	pg_assert_same(array('mark' => 3, 'played' => 0), $got['looks'][0], 'the first look only sets the mark');
	pg_assert_same(array('mark' => 6, 'played' => 1), $got['looks'][1], 'the next look with lines for the person plays once');
	pg_assert_same(array('mark' => 8, 'played' => 1, 'quiet' => false), $got['looks'][2], 'the look after the tab comes back is quiet and ends the quiet');
	pg_assert_same(array('mark' => 9, 'played' => 2), $got['looks'][3], 'the look after that sounds again');
	pg_assert_same(array('mark' => 9, 'played' => 2), $got['looks'][4], 'the mark never goes back');
	pg_assert_same(array('mark' => 12, 'played' => 0), $got['first'], 'lines in the first look stay silent');
}

function test_workspace_channel_sound_switch()
{
	$got = workspace_channel_sound_test_run(<<<'JS'
var site = 'https://example.test/api.php';
var out = { fresh: soundOn(site, 5) };
soundSet(site, 5, false);
out.off = soundOn(site, 5);
out.stored = store['ws-sound:' + site + ':5'] || null;
out.other_person = soundOn(site, 6);
soundChosen = {};
out.after_reload = soundOn(site, 5);
soundSet(site, 5, true);
out.on_again = soundOn(site, 5);
out.removed = !Object.prototype.hasOwnProperty.call(store, 'ws-sound:' + site + ':5');
soundChosen = {};
refuse = true;
out.refused = soundOn(site, 5);
soundSet(site, 5, false);
out.refused_off = soundOn(site, 5);
console.log(JSON.stringify(out));
JS
	);

	pg_assert_true(is_array($got), 'the switch runs under node');

	if (!is_array($got)) {
		return;
	}

	pg_assert_true($got['fresh'], 'the sound is on by default');
	pg_assert_false($got['off'], 'switched off, it is off');
	pg_assert_same('off', $got['stored'], 'off is kept in the browser for that person');
	pg_assert_true($got['other_person'], 'another person on the same browser keeps their own');
	pg_assert_false($got['after_reload'], 'the choice survives a reload');
	pg_assert_true($got['on_again'], 'switched on again, it is on');
	pg_assert_true($got['removed'], 'on is the default, so nothing is kept');
	pg_assert_true($got['refused'], 'storage refused: on');
	pg_assert_false($got['refused_off'], 'storage refused: the choice lasts the page');
}

function test_workspace_channel_sound_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

	pg_assert_contains("sound: (!self.isThread && soundOn(CFG.api_url, CFG.me)) ? 1 : 0,", $js, 'the look asks for the lines only from the channel screen with the switch on');
	pg_assert_contains("sound_after: ((self.soundMark === null) || (self.soundMark === undefined)) ? -1 : self.soundMark", $js, 'the first look asks for the mark only');
	pg_assert_contains("                    if (data.sound) {\n                        self.soundHeard(data.sound);", $js, 'the look hands its lines to the sound');
	pg_assert_contains("                    self.soundQuiet = true;\n                    self.sync();", $js, 'the look after the tab comes back is quiet');
	pg_assert_contains("item(t('sound_on'), 'bi-music-note-beamed'", $js, 'the switch is in the channel menu');

	pg_assert_true((bool) preg_match("/if \\(empty\\(\\\$request\\['panel'\\]\\)\\) \\{.*?if \\(!empty\\(\\\$request\\['sound'\\]\\)\\) \\{\n\\s*\\\$out\\['sound'\\] = ws_sound_lines\\(\\\$viewer\\['id'\\], \\(int\\) \\(\\\$request\\['sound_after'\\] \\?\\? -1\\)\\);/s", $actions),
		'ws_sync gives the lines only to the channel screen, not a discussion beside it, and only when asked');
	pg_assert_contains("WHERE user_id = '\" . \$user_id . \"' AND read_at = 0 AND id > \" . \$after . \"", $notify, 'only the person\'s unread lines newer than the mark');
	pg_assert_contains("ORDER BY id LIMIT 50", $notify, 'a look gives at most fifty lines');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /approval (/onay), /update (/güncelleme) and /checkin: what each
 * reads, that a complete line writes through its button's function with
 * that function's refusals, that an incomplete one opens the button's form
 * filled, that /update and /checkin are not offered in a discussion, and
 * the states /update reads in Turkish and English
 * (includes/workspace/commands.php, command_registry.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/status.php');

function workspace_command_auc_test_channel($kind = 'public')
{
	return array('id' => 7, 'kind' => $kind, 'name' => 'general', 'archived_at' => 0, 'parent_id' => ($kind === 'thread') ? 6 : 0);
}

function workspace_command_auc_test_team()
{
	return array(
		array('id' => 5, 'username' => 'ayse'),
		array('id' => 8, 'username' => 'Mehmet'),
	);
}

// The three have their entries, names in Turkish and English, a live line
// and their group; /update and /checkin run in the channel itself.
function test_workspace_command_approval_update_checkin_entries()
{
	$registry = ws_command_registry();
	$table = ws_command_table();

	foreach (array('onay' => 'approval', 'approval' => 'approval', 'güncelleme' => 'update', 'guncelleme' => 'update', 'update' => 'update', 'checkin' => 'checkin', 'check-in' => 'checkin') as $name => $key) {
		pg_assert_same($key, $table[$name] ?? '', '/' . $name);
	}

	foreach (array('approval', 'update', 'checkin') as $key) {
		pg_assert_same('channel', $registry[$key]['group'], $key . ' is the channel\'s');
		pg_assert_true($registry[$key]['live'] !== '', $key . ' has a live line');
	}

	pg_assert_same('any', $registry['approval']['where'], '/approval runs in a discussion too, as its button');
	pg_assert_same('channel', $registry['update']['where']);
	pg_assert_same('channel', $registry['checkin']['where']);
	pg_assert_same('healths', $registry['update']['args'][0]['suggest'], 'the states are offered');
}

// /update and /checkin are not offered in a discussion and are refused
// there before their runner; /approval is.
function test_workspace_command_update_checkin_not_in_a_discussion()
{
	$thread = workspace_command_auc_test_channel('thread');
	$keys = array_column(ws_commands(null, false, $thread), 'key');

	pg_assert_true(!in_array('update', $keys, true), '/update left out of a discussion\'s list');
	pg_assert_true(!in_array('checkin', $keys, true), '/checkin left out of a discussion\'s list');
	pg_assert_true(in_array('approval', $keys, true), '/approval offered in a discussion');
	pg_assert_true(!ws_command_offered(null, 'update', $thread));
	pg_assert_true(ws_command_offered(null, 'update', workspace_command_auc_test_channel()), 'offered in the channel');

	foreach (array('/güncelleme yolunda Bitti', '/checkin Ne durumdayız?') as $body) {
		$refused = ws_command_run(array('id' => 3), $thread, $body);

		pg_assert_same('error', $refused['type'], $body . ' refused in a discussion');
		pg_assert_same(lang('This command runs in the channel itself, not in one of its discussions.'), $refused['error']);
	}
}

// The states in Turkish and English, with or without the Turkish letters,
// and as two English words.
function test_workspace_command_update_reads_the_states()
{
	pg_assert_same(array('health' => 'on_track', 'text' => 'Her şey planda'), ws_command_update_data('yolunda Her şey planda'));
	pg_assert_same(array('health' => 'at_risk', 'text' => 'Tedarikçi gecikti'), ws_command_update_data('Riskte Tedarikçi gecikti'));
	pg_assert_same(array('health' => 'off_track', 'text' => ''), ws_command_update_data('gecikti'));
	pg_assert_same(array('health' => 'at_risk', 'text' => 'The supplier is late'), ws_command_update_data('at-risk The supplier is late'));
	pg_assert_same(array('health' => 'on_track', 'text' => 'All good'), ws_command_update_data('on track All good'), 'two words');
	pg_assert_same(array('health' => 'off_track', 'text' => "Line\nmore"), ws_command_update_data("off track Line\nmore"));
	pg_assert_same(array('health' => '', 'text' => 'Nothing said about the state'), ws_command_update_data('Nothing said about the state'), 'no state: the text stays');

	$tr = array_column(ws_command_suggestions('tr')['healths'], 'value');
	$en = array_column(ws_command_suggestions('en')['healths'], 'value');

	pg_assert_same(array('yolunda', 'riskte', 'gecikti'), $tr, 'offered on a Turkish screen');
	pg_assert_same(array('on-track', 'at-risk', 'off-track'), $en, 'offered on any other');

	foreach (array_merge($tr, $en) as $word) {
		pg_assert_true(ws_command_health_word($word) !== '', $word . ' is read back');
	}

	pg_assert_same(array_keys(ws_status_healths()), array_map('ws_command_health_word', $en), 'every state, in the form\'s order');
	pg_assert_same(array('', '', ''), array_column(ws_command_suggestions('tr')['healths'], 'meta'), 'the word is not said twice');
	pg_assert_true(in_array('health', ws_command_argument_kinds(), true));
}

// What /approval reads: the people tagged or typed, the title and the
// details under it.
function test_workspace_command_approval_reads_title_and_people()
{
	$team = workspace_command_auc_test_team();
	$data = ws_command_approval_data('Final logo <@user:5> @mehmet', $team);

	pg_assert_same('Final logo', $data['title']);
	pg_assert_same(array(5, 8), $data['people'], 'the tag and the typed username');
	pg_assert_same('', $data['text']);

	$data = ws_command_approval_data("@ayse Budget for May\nThe figures are in #sheet", $team);

	pg_assert_same('Budget for May', $data['title'], 'the person anywhere on the line');
	pg_assert_same('The figures are in #sheet', $data['text'], 'the lines under it are the details');
	pg_assert_same(array(5), $data['people']);

	$data = ws_command_approval_data('Final logo @nobody', $team);

	pg_assert_same(array(), $data['people'], 'a username nobody has asks nobody');
	pg_assert_same(array('nobody'), $data['typed_unknown']);
	pg_assert_same('Final logo @nobody', $data['title'], 'and stays in the title');
}

// Nobody named or no title: the Ask for approval form opens filled, nothing
// is written; the API shows it back.
function test_workspace_command_incomplete_lines_open_the_forms_filled()
{
	$viewer = array('id' => 3, 'member' => true);
	$channel = workspace_command_auc_test_channel();

	$answer = ws_command_run_approval($viewer, $channel, 'Final logo');

	pg_assert_same('approval_form', $answer['type'], 'nobody named: the form');
	pg_assert_same(array('title' => 'Final logo', 'text' => '', 'approvers' => array()), $answer['fill']);

	$answer = ws_command_run_approval($viewer, $channel, '<@user:5>');

	pg_assert_same('approval_form', $answer['type'], 'no title: the form');
	pg_assert_same(array(5), $answer['fill']['approvers'], 'with the person chosen');

	$answer = ws_command_run_update($viewer, $channel, 'The supplier is late');

	pg_assert_same('status_form', $answer['type'], 'no state: the Status update form');
	pg_assert_same(array('health' => '', 'text' => 'The supplier is late'), $answer['fill'], 'the text in place of the draft');

	$answer = ws_command_run_checkin($viewer, $channel, '   ');

	pg_assert_same('checkin_form', $answer['type'], 'no question: the check-in form');
	pg_assert_same(ws_checkin_default_question(), $answer['fill']['question'], 'with the usual question');

	foreach (array('approval_form', 'status_form', 'checkin_form') as $type) {
		pg_assert_true(in_array($type, ws_api_command_preview_types(), true), $type . ' is shown back over the API');
	}

	pg_assert_same('preview', ws_api_command_kind('/onay Final logo', array()));
	pg_assert_same('run', ws_api_command_kind('/onay Final logo <@user:5>', array()));
	pg_assert_same('preview', ws_api_command_kind('/güncelleme Tedarikçi gecikti', array()));
	pg_assert_same('run', ws_api_command_kind('/güncelleme riskte Tedarikçi gecikti', array()));
	pg_assert_same('preview', ws_api_command_kind('/checkin', array()));
	pg_assert_same('run', ws_api_command_kind('/checkin Ne durumdayız?', array()));
}

// A complete line writes through its button's function, which checks it
// (rights, a closed channel, a discussion, the tables) and whose refusal is
// the answer; the live line warns with the same checks. Running them needs
// a database, so the calls are read from the source.
function test_workspace_command_complete_lines_write_through_the_buttons_function()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$body = function ($name) use ($source) {
		$start = strpos($source, 'function ' . $name . '(');

		return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
	};

	$approval = $body('ws_command_run_approval');

	pg_assert_contains('ws_approval_create($viewer, $channel,', $approval, '/approval writes as the form does');
	pg_assert_contains('\'approvers\' => $data[\'people\']', $approval);
	pg_assert_contains("'rule'      => 'any'", $approval, 'any one of them decides, the form\'s first choice');
	pg_assert_contains('array(\'type\' => \'error\', \'error\' => $result[\'error\'])', $approval, 'with the function\'s refusal');
	pg_assert_contains('ws_status_post($viewer, $channel, $data[\'health\'], $data[\'text\'])', $body('ws_command_run_update'), '/update writes as the form does');
	pg_assert_contains('array(\'type\' => \'error\', \'error\' => $result[\'error\'])', $body('ws_command_run_update'));
	pg_assert_contains('ws_checkin_start($viewer, $channel, $question)', $body('ws_command_run_checkin'), '/checkin asks as the form does');
	pg_assert_contains('array(\'type\' => \'error\', \'error\' => $result[\'error\'])', $body('ws_command_run_checkin'));
	pg_assert_contains('ws_approval_input($viewer, $channel,', $body('ws_command_live_approval'), 'the live line warns with the form\'s checks');
	pg_assert_contains('ws_status_post_check($viewer, $channel,', $body('ws_command_live_update'));
	pg_assert_contains('ws_checkin_start_check($viewer, $channel,', $body('ws_command_live_checkin'));
}

// The live lines when the form would open, and with nothing typed.
function test_workspace_command_approval_update_checkin_live_lines()
{
	$channel = workspace_command_auc_test_channel();

	$form = ws_command_live_update(null, $channel, 'Tedarikçi gecikti');

	pg_assert_same(lang(array('string' => 'Will open Status update: {var:1}', 'vars' => '“Tedarikçi gecikti”')), $form['line']);
	pg_assert_same(array(lang('Choose how the work stands.')), $form['warnings']);

	$form = ws_command_live_approval(null, $channel, 'Final logo');

	pg_assert_same(lang(array('string' => 'Will open Ask for approval: {var:1}', 'vars' => '“Final logo”')), $form['line']);
	pg_assert_same(array(lang('Choose who is to approve it.')), $form['warnings']);

	foreach (array('ws_command_live_approval', 'ws_command_live_update', 'ws_command_live_checkin') as $function) {
		pg_assert_same(array('line' => '', 'warnings' => array()), $function(null, $channel, ''), $function . ': nothing typed');
	}
}

// The forms of the box's Insert menu take what was typed.
function test_workspace_command_forms_take_the_fill()
{
	$read = function ($file) {
		return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
	};

	$composer = $read('workspace.js');
	$approvals = $read('workspace_approvals.js');
	$status = $read('workspace_status.js');

	pg_assert_contains("approval_form: [window.PGWsApprovals, 'form']", $composer);
	pg_assert_contains("status_form: [window.PGWsStatus, 'form']", $composer);
	pg_assert_contains("checkin_form: [window.PGWsStatus, 'checkinForm']", $composer);
	pg_assert_contains("if (source === 'healths')", $composer, 'the popup offers the states');
	pg_assert_contains('function form(view, h, fill, done)', $approvals);
	pg_assert_contains('h.peoplePicker((fill && fill.approvers) || [], members)', $approvals);
	pg_assert_contains('function form(view, h, fill, done)', $status);
	pg_assert_contains('function checkinForm(view, h, fill, done)', $status);
	pg_assert_contains("(fill && fill.question) || config.question || ''", $status);
	pg_assert_contains("t((fill && fill.text) ? 'st_text_help_own' : 'st_text_help')", $status, 'typed text in place of the draft: no word of the figures');
	pg_assert_same(lang('@ and # tags work here.'), ws_status_js_strings()['st_text_help_own'], 'only the tags are explained');
}

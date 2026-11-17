<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /notify (/bildirim), /invite (/davet) and /leave (/ayrıl): the
 * three notification words in both languages and the answer without one,
 * /invite's answer with somebody already in, /leave writing nothing before
 * its card's confirm, and each refused or offered as the channel menu and
 * "Add people" refuse or offer it (includes/workspace/commands.php,
 * command_registry.php).
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

function workspace_command_nil_test_channel($kind = 'public')
{
	return array('id' => 7, 'kind' => $kind, 'name' => 'general', 'archived_at' => 0, 'parent_id' => ($kind === 'thread') ? 6 : 0);
}

function workspace_command_nil_test_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$start = strpos($source, 'function ' . $name . '(');

	return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

// The three have their entries, names in Turkish and English, a live line
// and their group; /notify and /leave run in the channel itself.
function test_workspace_command_notify_invite_leave_entries()
{
	$registry = ws_command_registry();
	$table = ws_command_table();

	foreach (array('bildirim' => 'notify', 'notify' => 'notify', 'davet' => 'invite', 'invite' => 'invite', 'ayrıl' => 'leave', 'ayril' => 'leave', 'leave' => 'leave') as $name => $key) {
		pg_assert_same($key, $table[$name] ?? '', '/' . $name);
	}

	foreach (array('notify', 'invite', 'leave') as $key) {
		pg_assert_same('channel', $registry[$key]['group'], $key . ' is the channel\'s');
		pg_assert_true($registry[$key]['live'] !== '', $key . ' has a live line');
	}

	pg_assert_same('channel', $registry['notify']['where'], 'a discussion has no such menu item');
	pg_assert_same('channel', $registry['leave']['where'], 'a discussion is left with its own Leave');
	pg_assert_same('any', $registry['invite']['where'], 'a discussion takes people in too');
	pg_assert_same('preview', $registry['leave']['api'], 'over the API it only shows its card');
	pg_assert_true(in_array('leave_card', ws_api_command_preview_types(), true));
}

// The three words in both languages, with or without the Turkish letters;
// the popup offers them in the screen's language.
function test_workspace_command_notify_reads_three_words_in_both_languages()
{
	foreach (array('hepsi' => 'all', 'Tümü' => 'all', 'all' => 'all', 'anma' => 'mentions', 'mentions' => 'mentions', 'hiç' => 'none', 'hic' => 'none', 'HİÇ' => 'none', 'never' => 'none', 'none' => 'none') as $word => $notify) {
		pg_assert_same($notify, ws_command_notify_word($word), $word);
	}

	pg_assert_same('', ws_command_notify_word('loud'), 'a word that is none of them');
	pg_assert_same(array('hepsi', 'anma', 'hiç'), array_column(ws_command_notify_values(true), 'value'), 'offered on a Turkish screen');
	pg_assert_same(array('all', 'mentions', 'never'), array_column(ws_command_notify_values(false), 'value'), 'offered on any other');
	pg_assert_same(array_column(ws_command_notify_values(true), 'value'), array_column(ws_command_suggestions('tr')['notify'], 'value'));
	pg_assert_same(lang('Only when I am mentioned'), ws_command_notify_values(false)[1]['meta'], 'with the menu\'s own words');

	foreach (array_merge(ws_command_notify_values(true), ws_command_notify_values(false)) as $item) {
		pg_assert_true(ws_command_notify_word($item['value']) !== '', $item['value'] . ' is read back');
	}

	pg_assert_same(array('all', 'mentions', 'none'), array_keys(ws_command_notify_labels()), 'the values ws_channel_set_notify() takes');
	pg_assert_true(in_array('notify', ws_command_argument_kinds(), true));
}

// Without a word: the choice in force and the three words, to the writer
// alone; with one, ws_channel_set_notify() sets it, with its refusal.
function test_workspace_command_notify_answers_without_and_with_a_word()
{
	$now = ws_command_notify_html('mentions', false);

	pg_assert_contains(h(lang(array('string' => 'Now: {var:1}', 'vars' => lang('Only when I am mentioned')))), $now, 'the choice in force');

	foreach (ws_command_notify_values(ws_command_turkish()) as $item) {
		pg_assert_contains('<span class="text-nowrap"><code>' . h($item['value']) . '</code> ' . h($item['meta']) . '</span>', $now, 'names ' . $item['value'] . ' with its meaning, never broken apart');
	}

	pg_assert_contains(h(lang(array('string' => 'Now set: {var:1}', 'vars' => lang('Never')))), ws_command_notify_html('none', true), 'just set');
	pg_assert_contains(h(lang('You are not in this channel.')), ws_command_notify_html('', false));

	$runner = workspace_command_nil_test_body('ws_command_run_notify');

	pg_assert_contains('ws_channel_set_notify($viewer, $channel, $notify)', $runner, 'writes as the menu does');
	pg_assert_contains("array('type' => 'error', 'error' => \$result['error'])", $runner, 'with its refusal');
	pg_assert_same(2, substr_count($runner, "'type' => 'ephemeral'"), 'both answers to the writer alone');

	pg_assert_same(array('line' => '', 'warnings' => array()), ws_command_live_notify(null, workspace_command_nil_test_channel(), ''), 'nothing typed');
	pg_assert_same(lang(array('string' => 'Will set your notifications here: {var:1}', 'vars' => lang('Never'))), ws_command_live_notify(null, workspace_command_nil_test_channel(), 'hiç')['line']);
	pg_assert_same(array(ws_command_notify_unknown()), ws_command_live_notify(null, workspace_command_nil_test_channel(), 'loud')['warnings']);
}

// /invite with somebody already in: told apart from the people added and
// the people "Add people" would not take.
function test_workspace_command_invite_with_somebody_already_in()
{
	pg_assert_same(array('added' => array(8), 'already' => array(5), 'refused' => array(9)), ws_command_invite_split(array(5, 8, 9), array(5), array(8)));
	pg_assert_same(array('added' => array(), 'already' => array(5), 'refused' => array()), ws_command_invite_split(array(5), array(5), array()), 'everybody in already');

	$runner = workspace_command_nil_test_body('ws_command_run_invite');

	pg_assert_contains('ws_channel_add_members($viewer, $channel,', $runner, 'adds as Add people does');
	pg_assert_contains("lang('Name the people to add with @.')", $runner, 'nobody named');
	pg_assert_contains("'type' => 'ephemeral'", $runner, 'the answer to the writer alone');
	pg_assert_same('<div class="ws-ephemeral-title">' . h(lang('Add people')) . '</div>', ws_command_invite_html(array(), array(), array()));
}

// Each right as the menu and Add people have it: /notify and /leave for a
// member, /invite for anybody in a public channel and a member elsewhere;
// the gates read the channel only when there is one.
function test_workspace_command_notify_invite_leave_rights()
{
	$thread = workspace_command_nil_test_channel('thread');
	$keys = array_column(ws_commands(null, false, $thread), 'key');

	pg_assert_true(!in_array('notify', $keys, true), '/notify left out of a discussion');
	pg_assert_true(!in_array('leave', $keys, true), '/leave left out of a discussion');
	pg_assert_true(in_array('invite', $keys, true), '/invite offered in a discussion');

	pg_assert_true(ws_command_gate_member(null, 'leave', workspace_command_nil_test_channel()), 'listed without a person');
	pg_assert_true(ws_command_gate_member(array('id' => 3), 'leave', null), 'listed without a channel');
	pg_assert_true(ws_command_gate_invite(array('id' => 3), 'invite', workspace_command_nil_test_channel('public')), 'anybody in a public channel');
	pg_assert_contains('ws_channel_membership($channel[\'id\'], $viewer[\'id\'])', workspace_command_nil_test_body('ws_command_gate_invite'), 'a member elsewhere');
	pg_assert_contains('ws_command_gate_invite($viewer, \'invite\', $channel)', workspace_command_nil_test_body('ws_command_run_invite'), 'refused when run anyway');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$add = substr($actions, strpos($actions, "case 'ws_channel_members_add':"), 700);

	pg_assert_contains("(\$channel['kind'] !== 'public') && !ws_channel_membership(\$channel['id'], \$viewer['id'])", $add, 'the rule /invite keeps is Add people\'s');
}

// /leave writes nothing before the card's confirm: the card first, the
// leave only from its button, refused to somebody not in the channel.
function test_workspace_command_leave_writes_nothing_before_the_confirm()
{
	pg_assert_same(array('type' => 'leave_card', 'private' => false), ws_command_leave_step(true, false, 'public'), 'the card first');
	pg_assert_same(array('type' => 'leave_card', 'private' => true), ws_command_leave_step(true, false, 'private'), 'the private channel\'s question');
	pg_assert_same(array('type' => 'leave'), ws_command_leave_step(true, true, 'public'), 'its confirm leaves');
	pg_assert_same(array('type' => 'error', 'error' => lang('You are not in this channel.')), ws_command_leave_step(false, true, 'public'), 'not in the channel');

	$runner = workspace_command_nil_test_body('ws_command_run_leave');

	pg_assert_true(strpos($runner, "if (\$step['type'] !== 'leave') {") < strpos($runner, 'ws_channel_leave($viewer, $channel)'), 'ws_channel_leave() only after the confirm');

	$composer = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));

	pg_assert_contains("result.command.type === 'leave_card'", $composer);
	pg_assert_contains("self.send({ confirm: 1, body: body });", $composer, 'the card\'s button confirms');
	pg_assert_contains("button('pg-button pg-button-danger pg-button-sm', t('leave_channel')", $composer, 'the button in the design language');
	pg_assert_contains("if (source === 'notify')", $composer, 'the popup offers the choices');
	pg_assert_same(lang(array('string' => 'Will ask before you leave #{var:1}', 'vars' => 'general')), ws_command_live_leave(null, workspace_command_nil_test_channel(), '')['line']);
}

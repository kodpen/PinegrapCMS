<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An application made an agent from Application Access
 * (includes/workspace/agent_app_setup.php): who may, each refusal naming its
 * part, the tab's fields over the agent's own, nothing written twice on a
 * second save, and the channel rows only where the list offered them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_agent_app_setup_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_app_setup.php');
	$start = strpos($source, 'function ' . $name . '(');
	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_workspace_agent_app_setup_rights()
{
	$app = array('kind' => 'server', 'has_secret' => true, 'locked' => false);

	pg_assert_same('', ws_agent_app_setup_refusal(true, 0, $app), 'an administrator on an integration');
	pg_assert_same('workspace_off', ws_agent_app_setup_refusal(false, 0, $app), 'no Workspace, no agent');
	pg_assert_same('admin_only', ws_agent_app_setup_refusal(true, 1, $app), 'a designer may not add an agent');
	pg_assert_same('admin_only', ws_agent_app_setup_refusal(true, 2, $app), 'nor a manager');
	pg_assert_same('not_found', ws_agent_app_setup_refusal(true, 0, null), 'no application');
	pg_assert_same('kind', ws_agent_app_setup_refusal(true, 0, array('kind' => 'oauth') + $app), 'a sign-in acts as the person');
	pg_assert_same('kind', ws_agent_app_setup_refusal(true, 0, array('kind' => 'device') + $app), 'team devices act as the person');
	pg_assert_same('no_secret', ws_agent_app_setup_refusal(true, 0, array('has_secret' => false) + $app), 'a key without a secret cannot sign in');
	pg_assert_same('locked', ws_agent_app_setup_refusal(true, 0, array('locked' => true) + $app), 'an owner above the editor');

	foreach (array('workspace_off', 'admin_only', 'not_found', 'kind', 'no_secret', 'locked') as $code) {
		pg_assert_true(ws_agent_app_setup_refusal_text($code) !== lang('Invalid request.'), 'a sentence for ' . $code);
	}
}

function test_workspace_agent_app_setup_check_names_its_part()
{
	$check = workspace_agent_app_setup_test_function('ws_agent_app_setup_check');

	pg_assert_contains("'part' => 'rights'", $check, 'a refusal of who may is the rights part');
	pg_assert_contains("'part' => 'agent'", $check, 'a refused field is the agent part');
	pg_assert_contains("'field' => (string) \$checked['field']", $check, 'with the field the Agents card names');
	pg_assert_contains('ws_agents_settings_check(', $check, 'the Agents card\'s own check');

	$write = workspace_agent_app_setup_test_function('ws_agent_app_setup_write');

	pg_assert_contains('ws_agents_settings_write(', $write, 'the Agents card\'s own write');
	pg_assert_contains('ws_channel_agent_add_problem(', $write, 'who may bring the agent into the channel');
	pg_assert_contains('ws_channel_agent_set_one(', $write, 'the single add of Agents in this channel');
	pg_assert_contains("'name' => \$offered[\$channel_id]", $write, 'a refused channel is named');

	$messages = ws_agent_app_setup_messages(array('agent_written' => false, 'added' => array(5), 'removed' => array(), 'refused' => array(
		array('channel_id' => 6, 'name' => 'sales<b>', 'error' => 'Only a manager of a private channel can add an agent to it.'),
	)), 'console');
	pg_assert_same(1, count($messages['errors']), 'one channel refused');
	pg_assert_contains('#sales&lt;b&gt;', $messages['errors'][0], 'the channel named, escaped');
	pg_assert_contains('Agents in this channel', $messages['errors'][0], 'and where to finish it');
	pg_assert_same(1, count($messages['notices']), 'what was added');
}

function test_workspace_agent_app_setup_posted()
{
	$posted = ws_agent_app_setup_posted(array(
		'agent_handle' => ' console ', 'agent_name' => 'Console', 'agent_role' => 'worker', 'agent_door_kind' => 'other',
		'agent_door_url' => 'https://x', 'agent_channels' => array('4', '2', '4', 'x', array('1')), 'agent_channels_sent' => '1',
	));

	pg_assert_same('console', $posted['handle'], 'trimmed');
	pg_assert_same('poll', $posted['door_kind'], 'an unknown door is a poll door');
	pg_assert_same(array(2, 4), $posted['channels'], 'channel ids, once each, in order');
	pg_assert_true($posted['channels_sent'], 'the list was drawn');

	$bare = ws_agent_app_setup_posted(array('agent_handle' => array('x')));
	pg_assert_same('', $bare['handle'], 'a list is no value');
	pg_assert_same(array(), $bare['channels'], 'no channels');
	pg_assert_false($bare['channels_sent'], 'no list, no channel changes');
}

function test_workspace_agent_app_setup_fields_keep_the_agents_own()
{
	$posted = array('handle' => 'console', 'name' => 'Console', 'role' => 'worker', 'door_kind' => 'poll', 'door_url' => 'https://ignored', 'channels' => array(), 'channels_sent' => false);

	$new = ws_agent_app_setup_fields(9, null, $posted);
	pg_assert_same('app', $new['kind'], 'an application agent');
	pg_assert_same('9', $new['app_id'], 'through this application');
	pg_assert_same('active', $new['status'], 'a new agent is active');
	pg_assert_same('', $new['door_url'], 'a poll door has no address');
	pg_assert_same('', $new['door_secret_clear'], 'the door secret is never cleared');
	pg_assert_same(array_keys(ws_agents_settings_input(array())), array_keys($new), 'the Agents card\'s fields');

	$current = array('duty' => 'Keep the board.', 'status' => 'paused', 'wake_every' => 30, 'wake_note' => 'Look');
	$kept = ws_agent_app_setup_fields(9, $current, array('door_kind' => 'push', 'door_url' => 'https://hook') + $posted);
	pg_assert_same('Keep the board.', $kept['duty'], 'the duty stays');
	pg_assert_same('paused', $kept['status'], 'the status stays');
	pg_assert_same('30', $kept['wake_every'], 'the schedule stays');
	pg_assert_same('Look', $kept['wake_note'], 'the wake note stays');
	pg_assert_same('https://hook', $kept['door_url'], 'a push door takes its address');
}

function test_workspace_agent_app_setup_second_save_writes_nothing()
{
	$current = array('id' => 3, 'kind' => 'app', 'handle' => 'console', 'name' => 'Console', 'role' => 'worker', 'duty' => '', 'app_id' => 9,
		'status' => 'active', 'door_kind' => 'poll', 'door_url' => '', 'wake_every' => 0, 'wake_note' => '');
	$values = array('kind' => 'app', 'handle' => 'console', 'name' => 'Console', 'role' => 'worker', 'duty' => '', 'app_id' => 9,
		'provider' => '', 'model' => '', 'status' => 'active', 'door_kind' => 'poll', 'door_url' => '', 'wake_every' => 0, 'wake_note' => '');

	pg_assert_true(ws_agent_app_setup_same($values, $current), 'the same values write nothing');
	pg_assert_false(ws_agent_app_setup_same(array('name' => 'Console 2') + $values, $current), 'a new name is written');
	pg_assert_false(ws_agent_app_setup_same($values, null), 'a new agent is written');

	$write = workspace_agent_app_setup_test_function('ws_agent_app_setup_write');
	pg_assert_contains("if (!\$ok['same'])", $write, 'the agent is written only when it changed');

	$diff = ws_agent_app_setup_channel_diff(array(2, 4), array(2, 4), array(2, 4, 6));
	pg_assert_same(array('add' => array(), 'remove' => array()), $diff, 'the same ticks change no row');
}

function test_workspace_agent_app_setup_channel_diff()
{
	$diff = ws_agent_app_setup_channel_diff(array(1, 2, 7), array(2, 3, 5), array(1, 2, 3, 4));

	pg_assert_same(array(1), $diff['add'], 'a ticked channel the agent is not in; 7 was never offered');
	pg_assert_same(array(3), $diff['remove'], 'an unticked one it is in; 5 is not the editor\'s to take out');
	pg_assert_same(array('add' => array(), 'remove' => array(2)), ws_agent_app_setup_channel_diff(array(), array(2), array(2)), 'unticking every row');
}

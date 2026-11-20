<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The agents of Site Settings' "Assistants and agents" in the panel's look:
 * each row by the recipe of "Agents in this channel" with Edit at its end,
 * the state pill's words and tones, the summary line, the drawer's facts
 * (state, waiting requests, the reported model and usage windows) and its
 * buttons in the drawer's foot, with the field names unchanged.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/language_map.php');

function settings_workspace_agents_look_test_agent($fields = array())
{
	return ws_agent_shape($fields + array(
		'id' => 7, 'kind' => 'app', 'handle' => 'koordinator', 'name' => 'Koordinatör', 'role' => 'coordinator', 'status' => 'active',
		'door_kind' => 'poll', 'app_id' => 4,
	));
}

function test_settings_workspace_agents_look_state_words()
{
	$now = 1800000000;
	$agent = settings_workspace_agents_look_test_agent(array('seen_at' => $now - 120));

	$state = ws_agents_settings_state($agent, array(), $now);
	pg_assert_same('on', $state['state'], 'seen two minutes ago');
	pg_assert_same('pg-tone-teal', $state['tone']);
	pg_assert_same(ws_agent_seen_words($agent, $now)['label'], $state['words'], 'the pill says when, as in the channel');

	$counts = array('agent:7' => array('queued' => 3, 'working' => 0, 'answered' => 2, 'failed' => 1));
	$state = ws_agents_settings_state($agent, $counts, $now);
	pg_assert_same('waiting', $state['state']);
	pg_assert_same(str_replace('{1}', '3', lang('{1} requests are waiting')), $state['words']);
	pg_assert_same(3, $state['counts']['queued'], 'this agent\'s counts');

	pg_assert_same(lang('Working on a request'), ws_agents_settings_state($agent, array('agent:7' => array('working' => 1)), $now)['words']);
	pg_assert_same(lang('A request is waiting'), ws_agents_settings_state($agent, array('agent:7' => array('queued' => 1)), $now)['words']);
	pg_assert_same(lang('Never seen'), ws_agents_settings_state(settings_workspace_agents_look_test_agent(), array(), $now)['words']);

	$paused = ws_agents_settings_state(settings_workspace_agents_look_test_agent(array('status' => 'paused')), $counts, $now);
	pg_assert_same(array('paused', lang('Paused'), 'pg-tone-gray'), array($paused['state'], $paused['words'], $paused['tone']), 'paused goes first');
	pg_assert_same(lang('Off'), ws_agents_settings_state(settings_workspace_agents_look_test_agent(array('status' => 'off')), array(), $now)['words'], 'an agent turned off says so');
}

function test_settings_workspace_agents_look_summary()
{
	$poll = settings_workspace_agents_look_test_agent(array('wake_every' => 30));
	pg_assert_same(lang('Poll door') . ' · ' . lang(array('string' => 'every {var:1} min', 'vars' => 30)), ws_agents_settings_summary($poll));

	$model = settings_workspace_agents_look_test_agent(array('kind' => 'model', 'provider' => 'openai', 'model' => 'gpt-x'));
	pg_assert_same(ws_model_provider_name('openai') . ' · gpt-x', ws_agents_settings_summary($model), 'a model agent names its model');
}

function test_settings_workspace_agents_look_row()
{
	$now = 1800000000;
	$row = ws_agents_settings_row_html(settings_workspace_agents_look_test_agent(array('name' => 'Koor<b>')), array(), $now, '<div class="small fg-danger">x</div>');

	pg_assert_contains('Koor&lt;b&gt;', $row, 'the name escaped');
	pg_assert_contains('@koordinator', $row);
	pg_assert_contains('pg-pill pg-tone-violet', $row, 'the role pill');
	pg_assert_contains('pg-pill ws-agset-state', $row, 'the state pill');
	pg_assert_contains('data-bs-target="#pgset-ws-agent-7"', $row, 'Edit opens its drawer');

	// Edit is the row's last part: the warnings stand inside the text column.
	pg_assert_true(strpos($row, 'fg-danger') < strpos($row, 'ws-agset-edit'), 'warnings before Edit');
	pg_assert_true(strpos($row, 'ws-agset-edit') > strpos($row, '</div>', strpos($row, 'ws-agset-main')), 'Edit after the text column');
	pg_assert_same('</button></div>', preg_replace('~\s+~', '', substr($row, strrpos($row, '</button>'))), 'nothing after Edit');
}

function test_settings_workspace_agents_look_facts()
{
	$now = 1800000000;
	$agent = settings_workspace_agents_look_test_agent(array(
		'seen_at' => $now - 60, 'report_at' => $now - 60, 'report_model' => 'model-<x>',
		'report_usage' => json_encode(array(array('name' => 'weekly', 'percent' => 92, 'resets_at' => $now + 3600))),
	));
	$facts = ws_agents_settings_facts_html($agent, array('agent:7' => array('queued' => 2)), $now);

	pg_assert_contains(h(lang('Waiting for it')), $facts, 'the waiting requests');
	pg_assert_contains(h(lang(array('string' => '{var:1} requests', 'vars' => 2))), $facts);
	pg_assert_contains('model-&lt;x&gt;', $facts, 'the reported model, escaped');
	pg_assert_contains('ws-agent-card-window is-high', $facts, 'a window at 90% is marked');
	pg_assert_contains('aria-valuenow="92"', $facts, 'its bar');
	pg_assert_contains(h(lang(array('string' => 'As the agent reported it {var:1}; the site cannot check it.', 'vars' => ws_seen_ago_words($now - 60, $now)))), $facts, 'said to be the agent\'s own report');

	$quiet = ws_agents_settings_facts_html(settings_workspace_agents_look_test_agent(), array(), $now);
	pg_assert_false(strpos($quiet, 'ws-agent-card-window') !== false, 'no report, no windows');
}

function test_settings_workspace_agents_look_drawer_foot()
{
	$foot = ws_agents_settings_buttons(settings_workspace_agents_look_test_agent(array('kind' => 'model')), true, 'pg_settings_modal_form');

	foreach (array('ws_agent_save', 'ws_agent_test', 'ws_agent_delete') as $name) {
		pg_assert_contains('name="' . $name . '" value="7" form="pg_settings_modal_form"', $foot, $name . ' keeps its name and value');
	}

	pg_assert_false(strpos(ws_agents_settings_buttons(settings_workspace_agents_look_test_agent(), true, 'f'), 'ws_agent_test') !== false, 'Try only for a model agent');
	pg_assert_false(strpos(ws_agents_settings_buttons(settings_workspace_agents_look_test_agent(array('kind' => 'claude')), true, 'f'), 'ws_agent_delete') !== false, 'Claude is not deleted here');
	pg_assert_same('', ws_agents_settings_buttons(settings_workspace_agents_look_test_agent(), false, 'f'), 'nothing for a viewer who cannot change agents');

	$drawer = pg_settings_drawer('d1', 'T', '<p>body</p>', true, $foot);
	pg_assert_true(strpos($drawer, 'pg-sm-drawer-foot') > strpos($drawer, 'pg-sm-drawer-body'), 'the foot under the scrolling body');
	pg_assert_false(strpos(pg_settings_drawer('d1', 'T', '<p>body</p>', true), 'pg-sm-drawer-foot') !== false, 'no foot without buttons');
	pg_assert_contains('drawer-footer', pg_settings_drawer('d1', 'T', '', false, $foot), 'the page path\'s drawer too');
}

function test_settings_workspace_agents_look_texts_translated()
{
	$turkish = pg_test_language_map('tr');

	foreach (array('Who it is', 'How it is reached', 'Now', 'Requests') as $english) {
		pg_assert_true(isset($turkish[$english]), $english . ' has its Turkish');
	}
}

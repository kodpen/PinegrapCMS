<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's role (includes/workspace/agents.php): the fixed list
 * and its Turkish labels, an unknown key read as no role, the role in the
 * save check and in the rows the panel and the API give.
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
require_once(__DIR__ . '/helpers/language_map.php');

// The list is fixed, in this order, and each label has its Turkish.
function test_workspace_agent_role_list()
{
	pg_assert_same(
		array('architect', 'general_coordinator', 'coordinator', 'merger', 'worker', 'tester', 'bug_hunter', 'marketing', 'design', 'data_analysis', 'other'),
		array_keys(ws_agent_role_list())
	);
	pg_assert_same(array_keys(ws_agent_role_list()), array_keys(ws_agent_roles()), 'the translated list has the same keys');

	$turkish = pg_test_language_map('tr');
	$expected = array(
		'architect' => 'Mimar', 'general_coordinator' => 'Genel koordinatör', 'coordinator' => 'Koordinatör', 'merger' => 'Birleştirici',
		'worker' => 'İşçi', 'tester' => 'Test', 'bug_hunter' => 'Hata avcısı', 'marketing' => 'Pazarlama', 'design' => 'Tasarım',
		'data_analysis' => 'Veri analizi', 'other' => 'Diğer',
	);

	foreach (ws_agent_role_list() as $key => $english) {
		pg_assert_same($expected[$key], $turkish[$english] ?? null, $key . ' in Turkish');
	}

	foreach (ws_agent_role_list() as $key => $english) {
		pg_assert_true(strlen($key) <= 32, $key . ' fits the column');
	}
}

// A key that is not on the list, a removed one or a stray value, reads as
// no role.
function test_workspace_agent_role_unknown_key()
{
	pg_assert_same('worker', ws_agent_role_key('worker'));
	pg_assert_same('', ws_agent_role_key('captain'), 'an unknown key');
	pg_assert_same('', ws_agent_role_key('Worker'), 'keys are exact');
	pg_assert_same('', ws_agent_role_key(null));
	pg_assert_same('', ws_agent_role_key(array('worker')));
	pg_assert_same('', ws_agent_role_label('captain'));
	pg_assert_same('', ws_agent_role_label(''));
	pg_assert_true(ws_agent_role_label('architect') !== '', 'a known key has a label');

	pg_assert_same('', ws_agent_shape(array('id' => 1, 'role' => 'captain'))['role'], 'the row reads an unknown key as no role');
	pg_assert_same('merger', ws_agent_shape(array('id' => 1, 'role' => 'merger'))['role']);
	pg_assert_same('', ws_agent_shape(array('id' => 1))['role'], 'a site before the upgrade has no role');

	$row = array('agent' => ws_agent_shape(array('id' => 3, 'kind' => 'app', 'handle' => 'a', 'role' => 'captain')), 'wake_rule' => 'mention', 'wake_prefix' => '', 'tier' => 1, 'loop_guard' => 1, 'added_by' => 0, 'added_at' => 0);
	pg_assert_same('', ws_channel_member_agent($row, time())['role'], 'the member list shows no role');

	$row['agent']['role'] = 'design';
	pg_assert_same(ws_agent_role_label('design'), ws_channel_member_agent($row, time())['role'], 'the member list shows the label');
	pg_assert_same('design', ws_api_channel_agent_present(7, $row)['role'], 'the API gives the key');
	pg_assert_same(array_keys(ws_api_channel_agent_schema()), array_keys(ws_api_channel_agent_present(7, $row)), 'the API row matches its schema');
}

// The save check takes a role of the list or none, and refuses any other.
function test_workspace_agent_role_save_check()
{
	$app = array('kind' => 'app', 'handle' => 'worker-a', 'name' => 'Worker A', 'app_id' => 4, 'door_kind' => 'poll');

	$none = ws_agent_save_check($app, null, array(), array(), array(4));
	pg_assert_true($none['ok'], $none['error']);
	pg_assert_same('', $none['values']['role'], 'no role is allowed');

	$worker = ws_agent_save_check(array('role' => 'worker') + $app, null, array(), array(), array(4));
	pg_assert_true($worker['ok'], $worker['error']);
	pg_assert_same('worker', $worker['values']['role']);

	$unknown = ws_agent_save_check(array('role' => 'captain') + $app, null, array(), array(), array(4));
	pg_assert_false($unknown['ok'], 'a key not on the list is refused');
	pg_assert_same('role', $unknown['field']);

	$model = array('kind' => 'model', 'handle' => 'chatgpt', 'name' => 'ChatGPT', 'provider' => 'openai', 'model' => 'gpt-4.1-mini', 'role' => 'data_analysis');
	$checked = ws_agent_save_check($model, null, array(), array(), array());
	pg_assert_true($checked['ok'], $checked['error']);
	pg_assert_same('data_analysis', $checked['values']['role'], 'a model assistant takes a role too');

	$claude = array('id' => 1, 'kind' => 'claude', 'handle' => 'claude', 'status' => 'active', 'door_url' => 'https://x.test/');
	$checked = ws_agent_save_check(array('name' => 'Claude', 'role' => 'architect'), $claude, array(), array(), array());
	pg_assert_true($checked['ok'], $checked['error']);
	pg_assert_same('architect', $checked['values']['role'], 'Claude\'s row takes a role too');

	pg_assert_same('role', ws_agents_settings_input(array('role' => 'worker')) ? 'role' : '', 'the form reads the field');
	pg_assert_same('worker', ws_agents_settings_input(array('role' => 'worker'))['role']);
}

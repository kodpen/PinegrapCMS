<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Workspace agents card in Assistants and agents of the Site Settings
 * dialog: it came over from Workspace Settings. Pins the section, its list
 * and the old anchor's new place, the two screens' field names reading as
 * one request, the form showing only the chosen kind's fields with the rarely
 * needed ones under Advanced, the drawers' named buttons, and the save
 * module checking the agent before anything is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function settings_workspace_agents_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The source of one function, up to the next top-level function.
function settings_workspace_agents_test_function($file, $name)
{
	$source = settings_workspace_agents_test_source($file);
	$from = strpos($source, 'function ' . $name . '(');
	$to = ($from === false) ? false : strpos($source, "\nfunction ", $from + 10);

	return ($from === false) ? '' : (($to === false) ? substr($source, $from) : substr($source, $from, $to - $from));
}

function test_settings_workspace_agents_section_list_and_anchor()
{
	$source = settings_workspace_agents_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_assistants' => array(");
	$to = strpos($source, "        'workspace_channels' => array(", (int) $from);
	$entry = substr($source, (int) $from, (int) $to - (int) $from);

	pg_assert_contains("'pgset-ws-agents'            => lang('Workspace agents'),", $entry, 'the card is a section');
	pg_assert_contains("'ws_agent' => 2,", $entry, 'an agent\'s fields posted by its id');
	pg_assert_same(array('workspace_assistants', 'pgset-ws-agents'), pg_settings_legacy_anchors()['ws-agents'] ?? null, 'the old anchor lands on the card');

	$card = settings_workspace_agents_test_source('includes/settings/workspace_assistants.php');
	pg_assert_false(strpos($card, "array('ws-agents'") !== false, 'no link to the old card');
	pg_assert_contains('ws_settings_agent_drawer($ws_agent,', $card, 'each agent\'s drawer');
	pg_assert_contains('ws_settings_agent_drawer(null,', $card, 'Add an agent\'s drawer');
}

function test_settings_workspace_agents_names_read_alike()
{
	$fields = array(
		'kind' => 'model', 'handle' => 'koordinator', 'name' => 'Koordinatör', 'app_id' => '4', 'provider' => 'azure', 'model' => 'gpt-x',
		'status' => 'paused', 'door_kind' => 'poll', 'door_url' => '', 'base' => 'https://x.openai.azure.com/openai/v1', 'wake_every' => '30',
		'wake_note' => 'Look at the board', 'key' => 'sk-1', 'key_clear' => '', 'door_secret' => '', 'door_secret_clear' => '1', 'token_limit' => '5000',
	);
	$old = array('ws_action' => 'agent_save', 'agent_id' => '3', 'token' => 'csrf');

	foreach ($fields as $field => $value) {
		$old['agent_' . $field] = $value;
	}

	pg_assert_same($fields, ws_agents_settings_input($fields), 'the dialog\'s fields');
	pg_assert_same($fields, ws_agents_settings_old_input($old), 'the old screen\'s fields read alike');

	$odd = ws_agents_settings_input(array('handle' => array('x'), 'name' => 7));
	pg_assert_same('', $odd['handle'], 'a list is no value');
	pg_assert_same('7', $odd['name'], 'a scalar is kept as text');
	pg_assert_same('', ws_agents_settings_input('x')['kind'], 'no array, no values');
}

function test_settings_workspace_agents_form_shows_the_kind_fields()
{
	$form = settings_workspace_agents_test_function('includes/workspace/agents_settings.php', 'ws_agents_settings_form');

	// The kind first; an application's and a model's fields hang on it.
	pg_assert_true(strpos($form, "\$field('kind', lang('Kind')") < strpos($form, "\$field('handle',"), 'Kind first');
	pg_assert_contains("\$when_kind('app')", $form, 'an application\'s fields');
	pg_assert_contains("\$when_kind('model')", $form, 'a model\'s fields');
	pg_assert_contains("'door_kind=push', \$push)", $form, 'the push address and token only for a push door');
	pg_assert_contains("'provider=' . implode('|', \$own_address)", $form, 'the address only for a provider without one');

	// Advanced: status, wake interval and note, the monthly token limit.
	$advanced = substr($form, (int) strpos($form, "\$advanced = \$field('status'"));
	pg_assert_contains("\$field('wake_every',", $advanced, 'the wake interval');
	pg_assert_contains("\$field('wake_note',", $advanced, 'the wake note');
	pg_assert_contains("\$advanced .= \$field('token_limit',", $form, 'the token limit');
	pg_assert_contains("h(lang('Advanced'))", $form, 'a closed Advanced part');
	pg_assert_false(strpos($form, ' required') !== false, 'nothing required: the pane\'s Save would be blocked');

	// The dialog's form: no form of its own, fields by id, named buttons.
	pg_assert_contains("return \$dialog ? 'ws_agent[' . \$id . '][' . \$field . ']' : 'agent_' . \$field;", $form, 'the names');
	pg_assert_contains("if (\$dialog) {\n        return '<div class=\"ws-agent-form\">' . \$body . '</div>';", $form, 'no form in the dialog');
	pg_assert_contains("'<button type=\"submit\" name=\"ws_' . \$action . '\" value=\"' . \$id . '\" form=\"' . h(\$form) . '\"", $form, 'a named button of the settings form');

	$activity = settings_workspace_agents_test_function('includes/workspace/agent_activity.php', 'ws_agent_activity_block');
	pg_assert_contains("'<button type=\"submit\" name=\"ws_' . \$action . '\" value=\"' . (int) \$row['id'] . '\" form=\"' . h(\$form) . '\"", $activity, 'a request row\'s button carries the request');
}

function test_settings_workspace_agents_save_module()
{
	$save = settings_workspace_agents_test_source('includes/settings/workspace_assistants.save.php');

	pg_assert_contains("post_value('ws_agent_save')", $save, 'the drawer\'s save');
	pg_assert_contains("\$liveform->mark_error('ws_agent[' . \$ws_agent_id . '][' .", $save, 'a refusal is marked on the drawer\'s field');
	pg_assert_true(strpos($save, '$ws_agent = ws_agents_settings_check(') < strpos($save, '$ws_refused = ws_claude_settings_save('), 'the agent is checked before anything is written');
	pg_assert_true(strpos($save, '    ws_bulk_settings_save($ws_bulk);') < strpos($save, '    ws_agents_settings_write($ws_viewer, $ws_agent);'), 'and written with the rest');

	foreach (array('agent_delete', 'agent_test', 'agent_request_cancel', 'agent_request_retry', 'agent_request_knock') as $action) {
		pg_assert_contains("'" . $action . "'", $save, $action . ' read back');
	}

	pg_assert_contains('ws_agent_activity_post($ws_viewer, $ws_action, $liveform, (int) $ws_value, true);', $save, 'a request row after the save');

	// The old screen's case checks and writes through the same functions.
	$post = settings_workspace_agents_test_function('includes/workspace/agents_settings.php', 'ws_agents_settings_post');
	pg_assert_contains('ws_agents_settings_check($viewer, $id, ws_agents_settings_old_input($_POST));', $post, 'the old screen\'s check');
	pg_assert_contains('ws_agents_settings_write($viewer, $checked);', $post, 'the old screen\'s write');
	pg_assert_false(strpos($post, 'ws_agent_save(') !== false, 'no write of its own');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Claude in the channels and the assistants' bulk changes in Assistants and
 * agents of the Site Settings dialog: they came over from Workspace Settings.
 * Pins the sections and the old anchors' new places, the two screens' field
 * names reading as one request, a pane Save that changes nothing writing
 * nothing, the card buttons run after the save, the window with a new secret
 * as a drawer read once, and the gate.
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

function settings_workspace_assistants_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The source of one function, up to the next top-level function.
function settings_workspace_assistants_test_function($file, $name)
{
	$source = settings_workspace_assistants_test_source($file);
	$from = strpos($source, 'function ' . $name . '(');
	$to = ($from === false) ? false : strpos($source, "\nfunction ", $from + 10);

	return ($from === false) ? '' : (($to === false) ? substr($source, $from) : substr($source, $from, $to - $from));
}

function test_settings_workspace_assistants_sections_and_anchors()
{
	$source = settings_workspace_assistants_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_assistants' => array(");
	$to = strpos($source, "        'workspace_channels' => array(", (int) $from);
	$entry = substr($source, (int) $from, (int) $to - (int) $from);

	pg_assert_contains("'gate'  => 'workspace',", $entry, 'gated by the Workspace settings right');
	pg_assert_contains("'pgset-ws-claude'            => lang('Claude in the channels'),", $entry, 'Claude is a section');
	pg_assert_contains("'pgset-ws-bulk'              => lang('Bulk changes by the assistants'),", $entry, 'Bulk changes is a section');
	pg_assert_true(strpos($entry, "'pgset-ws-bulk'") < strpos($entry, "'pgset-ws-assistants-screen'"), 'the link card comes last');
	pg_assert_same(array('workspace_assistants', 'pgset-ws-claude'), pg_settings_legacy_anchors()['ws-claude'] ?? null, 'the old Claude anchor');
	pg_assert_same(array('workspace_assistants', 'pgset-ws-bulk'), pg_settings_legacy_anchors()['ws-bulk'] ?? null, 'the old Bulk changes anchor');

	$card = settings_workspace_assistants_test_source('includes/settings/workspace_assistants.php');
	pg_assert_false(strpos($card, "array('ws-claude'") !== false, 'no link to the old Claude card');
	pg_assert_false(strpos($card, "array('ws-bulk'") !== false, 'no link to the old Bulk changes card');
	pg_assert_contains("array('ws-mcp'", $card, 'the MCP servers are still linked');

	$category = array('group' => 'workspace', 'gate' => 'workspace');
	pg_assert_false(pg_settings_category_open($category, array('manager' => true, 'workspace' => false)), 'a manager without the right');
	pg_assert_true(pg_settings_category_open($category, array('manager' => false, 'workspace' => true)), 'a holder of the right alone');
}

function test_settings_workspace_assistants_claude_names_read_alike()
{
	$old = array(
		'claude_enabled' => '1', 'claude_app_id' => '7', 'claude_routine_url' => ' https://api.anthropic.com/v1/claude_code/routines/trig_1/fire ',
		'claude_token' => 'tok', 'claude_token_clear' => '1', 'claude_summary_hour' => '9', 'claude_summary_channel' => '4',
	);
	$new = array();

	foreach ($old as $name => $value) {
		$new['ws_' . $name] = $value;
	}

	$read = ws_claude_settings_input($old);

	pg_assert_same($read, ws_claude_settings_input($new, 'ws_'), 'both screens read alike');
	pg_assert_same(array(
		'enabled' => true, 'app_id' => 7, 'routine_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_1/fire',
		'token' => 'tok', 'token_clear' => true, 'summary_hour' => '9', 'summary_channel' => 4,
	), $read, 'the values');

	$empty = ws_claude_settings_input(array('ws_claude_routine_url' => '', 'ws_claude_enabled' => array('1')), 'ws_');
	pg_assert_false($empty['enabled'], 'a list is no tick');
	pg_assert_same(null, $empty['summary_hour'], 'a summary not posted is left alone');
	pg_assert_false($empty['token_clear'], 'no clear');
}

function test_settings_workspace_assistants_claude_unchanged_writes_nothing()
{
	$config = array('enabled' => true, 'app_id' => 7, 'routine_url' => 'https://x/fire', 'token_set' => true, 'hold_until' => 0, 'error' => 'late');
	$summary = array('hour' => 9, 'channel_id' => 4, 'message_id' => 0);
	$input = array('enabled' => true, 'app_id' => 7, 'routine_url' => 'https://x/fire', 'token' => '', 'token_clear' => false, 'summary_hour' => '9', 'summary_channel' => 4);

	pg_assert_false(ws_claude_settings_changed($input, $config, $summary), 'the same values');
	pg_assert_true(ws_claude_settings_changed(array('token' => 'new') + $input, $config, $summary), 'a new token');
	pg_assert_true(ws_claude_settings_changed(array('token_clear' => true) + $input, $config, $summary), 'the token removed');
	pg_assert_true(ws_claude_settings_changed(array('enabled' => false) + $input, $config, $summary), 'switched off');
	pg_assert_true(ws_claude_settings_changed(array('app_id' => 8) + $input, $config, $summary), 'another application');
	pg_assert_true(ws_claude_settings_changed(array('routine_url' => 'https://y/fire') + $input, $config, $summary), 'another address');
	pg_assert_true(ws_claude_settings_changed(array('summary_hour' => '') + $input, $config, $summary), 'the summary off');
	pg_assert_true(ws_claude_settings_changed(array('summary_channel' => 5) + $input, $config, $summary), 'another summary channel');
	pg_assert_false(ws_claude_settings_changed(array('summary_hour' => null, 'summary_channel' => 5) + $input, $config, $summary), 'no summary posted');
	pg_assert_false(ws_claude_settings_changed(array('summary_channel' => 5) + $input, $config, null), 'before the summary upgrade');

	$off = array('hour' => WS_CLAUDE_SUMMARY_OFF, 'channel_id' => 4, 'message_id' => 0);
	pg_assert_false(ws_claude_settings_changed(array('summary_hour' => '', 'summary_channel' => 0) + $input, $config, $off), 'off stays off, whatever the channel');
}

function test_settings_workspace_assistants_save_module()
{
	$save = settings_workspace_assistants_test_source('includes/settings/workspace_assistants.save.php');

	pg_assert_contains("\$ws_admin = ((int) \$ws_viewer['role'] === 0);", $save, 'an administrator\'s cards');
	pg_assert_contains("ws_claude_settings_input(\$_POST, 'ws_')", $save, 'the dialog\'s names');
	pg_assert_contains('ws_claude_settings_changed(', $save, 'an unchanged connection is not written');
	pg_assert_contains("\$liveform->mark_error('ws_claude_' . \$ws_field, \$ws_sentence);", $save, 'a refusal is marked on its field');
	pg_assert_contains("post_value('ws_bulk_card') !== null", $save, 'the bulk switch only when its card came');
	pg_assert_contains("ws_claude_settings_post(\$ws_viewer, \$ws_action, \$liveform, \$user, true);", $save, 'the buttons run after the save');
	pg_assert_true(strpos($save, 'ws_claude_settings_save(') < strpos($save, 'ws_claude_settings_post('), 'the save before the button');

	foreach (array('claude_test', 'claude_key_create', 'claude_key_reset') as $action) {
		pg_assert_contains("'" . $action . "'", $save, $action . ' read back');
	}

	// The old screen's case calls the same save, not a copy.
	$post = settings_workspace_assistants_test_function('includes/workspace/claude.php', 'ws_claude_settings_post');
	pg_assert_contains('ws_claude_settings_save(ws_claude_settings_input($_POST));', $post, 'the old screen\'s save');
	pg_assert_false(strpos($post, 'UPDATE config') !== false, 'no write of its own');

	// A key refused on the old screen is an error there, as before.
	$key = settings_workspace_assistants_test_function('includes/workspace/claude.php', 'ws_claude_key_post');
	pg_assert_contains("\$refuse = function (\$sentence) use (\$liveform) {\n            \$liveform->add_error(\$sentence);", $key, 'the old screen\'s refusal');

	$bulk = settings_workspace_assistants_test_function('includes/workspace/bulk.php', 'ws_bulk_settings_post');
	pg_assert_contains("ws_bulk_settings_save(!empty(\$_POST['bulk_delete']));", $bulk, 'the old bulk save');
}

function test_settings_workspace_assistants_cards_have_no_form_in_the_dialog()
{
	$card = settings_workspace_assistants_test_function('includes/workspace/claude.php', 'ws_claude_settings_card');

	// Every <form> of the card is drawn on Workspace Settings only.
	pg_assert_contains("\$dialog ? '<div class=\"ws-claude-connection\">' : '<form method=\"post\"", $card, 'the connection');
	pg_assert_contains("if (\$form !== null) {\n            return '<div' . ((\$wrap !== '') ? ' class=\"' . h(\$wrap) . '\"' : '') . '><button type=\"submit\" name=\"ws_' . h(\$action) . '\" value=\"1\" form=\"' . h(\$form) . '\"", $card, 'a named submit button of the settings form');
	pg_assert_same(2, substr_count($card, '<form method="post"'), 'the old screen\'s forms: the connection and a button\'s');
	pg_assert_contains("\$sheet = \$dialog ? '' :", $card, 'no window of its own in the dialog');

	foreach (array('enabled', 'app_id', 'routine_url', 'token', 'token_clear', 'summary_hour', 'summary_channel') as $field) {
		pg_assert_contains("name=\"' . \$field_name('" . $field . "') . '\"", $card, $field . ' named for the screen');
	}

	$bulk = settings_workspace_assistants_test_function('includes/workspace/bulk.php', 'ws_bulk_settings_card');
	pg_assert_contains("name=\"' . (\$dialog ? 'ws_bulk_delete' : 'bulk_delete') . '\"", $bulk, 'the switch');
	pg_assert_contains('<input type="hidden" name="ws_bulk_card" value="1">', $bulk, 'the marker');
	pg_assert_true(strpos($bulk, 'if ($dialog) {') < strpos($bulk, '<form method="post"'), 'the form is the old screen\'s');
}

function test_settings_workspace_assistants_secret_drawer()
{
	$category = settings_workspace_assistants_test_source('includes/settings/workspace_assistants.php');

	pg_assert_contains("(\$ws_credentials['agent'] ?? '') === 'claude'", $category, 'Claude\'s only; Pinegrap AI\'s stay');
	pg_assert_contains("((int) \$ws_viewer['role'] === 0)", $category, 'an administrator\'s');
	pg_assert_contains("!isset(\$_GET['search'])", $category, 'never by the search index');
	pg_assert_contains("unset(\$_SESSION['software']['ws_assistant_credentials']);", $category, 'read once');
	pg_assert_contains("header('Cache-Control: no-store');", $category, 'not kept by the browser');
	pg_assert_contains("pg_settings_drawer('pgset-ws-claude-secret',", $category, 'a drawer');

	// The drawer's body reads the site's address and prompt, so its source
	// is read rather than drawn.
	$html = settings_workspace_assistants_test_function('includes/workspace/claude.php', 'ws_claude_credentials_drawer_html')
		. settings_workspace_assistants_test_function('includes/workspace/claude.php', 'ws_claude_credentials_html');
	pg_assert_contains('ws_claude_credentials_html($credentials, \'pgset-ws-claude-secret\')', $html, 'the same fields as the old window');
	pg_assert_contains('id="pgset-ws-claude-secret-done" disabled', $html, 'Close waits for the tick');
	pg_assert_contains("body.innerHTML = \\'\\';", $html, 'closing takes the secret out');
	pg_assert_false(strpos($html, 'name=') !== false, 'nothing of it is posted with the form');
}

function test_settings_workspace_assistants_pinegrap_ai()
{
	$source = settings_workspace_assistants_test_source('includes/settings/registry.php');
	pg_assert_contains("'pgset-ws-ai'                => lang('Pinegrap AI in the channels'),", $source, 'Pinegrap AI is a section');
	pg_assert_same(array('workspace_assistants', 'pgset-ws-ai'), pg_settings_legacy_anchors()['ws-ai'] ?? null, 'the old anchor');
	pg_assert_false(strpos(settings_workspace_assistants_test_source('includes/settings/workspace_assistants.php'), "array('ws-ai'") !== false, 'no link to the old card');

	// Both screens' names read alike.
	pg_assert_same(ws_ai_settings_input(array('ai_enabled' => '1', 'ai_app_id' => '9')), ws_ai_settings_input(array('ws_ai_enabled' => '1', 'ws_ai_app_id' => '9'), 'ws_'), 'both screens');
	pg_assert_same(array('enabled' => false, 'app_id' => 0), ws_ai_settings_input(array('ws_ai_enabled' => array('1'), 'ws_ai_app_id' => array(3)), 'ws_'), 'lists are no values');

	// A pane Save that changes nothing writes nothing.
	$config = array('enabled' => true, 'app_id' => 9);
	pg_assert_false(ws_ai_settings_changed(array('enabled' => true, 'app_id' => 9), $config), 'the same');
	pg_assert_true(ws_ai_settings_changed(array('enabled' => false, 'app_id' => 9), $config), 'switched off');
	pg_assert_true(ws_ai_settings_changed(array('enabled' => true, 'app_id' => 0), $config), 'another application');

	$save = settings_workspace_assistants_test_source('includes/settings/workspace_assistants.save.php');
	pg_assert_contains("\$liveform->mark_error('ws_ai_' . \$ws_field, \$ws_sentence);", $save, 'a refusal marked on its field');
	pg_assert_contains("(\$ws_claude !== null) ? (int) \$ws_claude['app_id']", $save, 'never Claude\'s application, as posted in the same save');
	pg_assert_true(strpos($save, 'ws_ai_settings_check($ws_ai,') < strpos($save, '$ws_refused = ws_claude_settings_save('), 'checked before anything is written');
	pg_assert_contains('ws_ai_settings_post($ws_viewer, $ws_action, $liveform, $user, true);', $save, 'the buttons after the save');

	// The old screen's case checks and writes through the same functions.
	$post = settings_workspace_assistants_test_function('includes/workspace/ai.php', 'ws_ai_settings_post');
	pg_assert_contains('ws_ai_settings_check($input, (int) ws_claude_config()[\'app_id\']);', $post, 'the old screen\'s check');
	pg_assert_contains('ws_ai_settings_write($input, $liveform, $refuse);', $post, 'the old screen\'s write');

	$card = settings_workspace_assistants_test_function('includes/workspace/ai.php', 'ws_ai_settings_card');
	pg_assert_contains("\$dialog ? '<div class=\"ws-ai-connection\">' : '<form method=\"post\"", $card, 'no form in the dialog');
	pg_assert_contains("name=\"ws_' . h(\$action) . '\" value=\"1\" form=\"' . h(\$form) . '\"", $card, 'named buttons of the settings form');
}

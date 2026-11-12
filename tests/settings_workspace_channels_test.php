<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Channel templates, Voice message transcripts and GitHub repositories in
 * Channels, mail and voice of the Site Settings dialog: they came over from
 * Workspace Settings. Pins the sections, the list and the old anchors' new
 * places, the two screens' voice fields reading as one, the voice check
 * naming its fields, an unchanged voice card not written, the GitHub
 * drawer's names read back as the old form's, and the buttons the save
 * module runs after the save.
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

function settings_workspace_channels_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_workspace_channels_sections_and_anchors()
{
	$source = settings_workspace_channels_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_channels' => array(");
	$entry = substr($source, (int) $from, 2500);

	pg_assert_contains("'pgset-ws-templates'       => lang('Channel templates'),", $entry, 'templates are a section');
	pg_assert_contains("'pgset-ws-voice'           => lang('Voice message transcripts'),", $entry, 'voice is a section');
	pg_assert_contains("'pgset-ws-github'          => lang('GitHub repositories'),", $entry, 'GitHub is a section');
	pg_assert_contains("'ws_github' => 2,", $entry, 'the events are ws_github[events][]');

	foreach (array('templates', 'voice', 'github') as $card) {
		pg_assert_same(array('workspace_channels', 'pgset-ws-' . $card), pg_settings_legacy_anchors()['ws-' . $card] ?? null, 'the old #ws-' . $card . ' lands on the card');
	}

	$cards = settings_workspace_channels_test_source('includes/settings/workspace_channels.php');
	pg_assert_contains("array('ws-mail'", $cards, 'the mailboxes are still linked');

	foreach (array('ws-templates', 'ws-voice', 'ws-github') as $old) {
		pg_assert_false(strpos($cards, "array('" . $old . "'") !== false, 'no link to the old ' . $old);
	}
}

function test_settings_workspace_channels_voice_names_read_alike()
{
	$old = array('voice_enabled' => '1', 'voice_url' => ' https://api.openai.com/v1/audio/transcriptions ', 'voice_model' => 'whisper-1', 'voice_key' => '', 'voice_speech_voice' => 'nova');
	$new = array();

	foreach ($old as $field => $value) {
		$new['ws_' . $field] = $value;
	}

	$input = ws_voice_settings_input($old);

	pg_assert_same($input, ws_voice_settings_input($new, 'ws_'), 'both screens read one card');
	pg_assert_true($input['enabled'], 'the switch');
	pg_assert_same('https://api.openai.com/v1', $input['url'], 'the address is cleaned');
	pg_assert_false($input['speech_enabled'], 'an unticked switch is off');
	pg_assert_same('', ws_voice_settings_input(array('voice_model' => array('x')))['model'], 'a list is no text');
}

function test_settings_workspace_channels_voice_check_names_fields()
{
	$input = ws_voice_settings_input(array(
		'voice_url' => 'http://insecure.test', 'voice_model' => 'bad model', 'voice_key' => 'short',
		'voice_speech_url' => 'ftp://x', 'voice_speech_model' => 'ok-model', 'voice_speech_voice' => 'a b', 'voice_speech_key' => 'abcdefgh123',
	));
	$refused = ws_voice_settings_check($input);

	pg_assert_same(array('url', 'model', 'key', 'speech_url', 'speech_voice'), array_keys($refused), 'each refused field, in the card\'s order');
	pg_assert_same(array(), ws_voice_settings_check(ws_voice_settings_input(array())), 'an empty card is no refusal');
}

function test_settings_workspace_channels_voice_unchanged_is_not_written()
{
	$transcript = array('enabled' => true, 'url' => 'https://api.openai.com/v1', 'model' => 'whisper-1');
	$speech = array('enabled' => false, 'url' => '', 'model' => '', 'voice' => 'nova');
	$same = ws_voice_settings_input(array('voice_enabled' => '1', 'voice_url' => 'https://api.openai.com/v1', 'voice_model' => 'whisper-1', 'voice_speech_voice' => 'nova'));

	pg_assert_false(ws_voice_settings_changed($same, $transcript, $speech), 'the pane\'s Save does not clear the errors and the wait');

	$typed = $same;
	$typed['key'] = 'sk-abcdefgh';
	pg_assert_true(ws_voice_settings_changed($typed, $transcript, $speech), 'a typed key is a change');

	$cleared = $same;
	$cleared['speech_key_clear'] = true;
	pg_assert_true(ws_voice_settings_changed($cleared, $transcript, $speech), 'a ticked remove box is a change');

	$off = $same;
	$off['enabled'] = false;
	pg_assert_true(ws_voice_settings_changed($off, $transcript, $speech), 'the switch');
}

function test_settings_workspace_channels_github_drawer_reads_as_the_old_form()
{
	$post = array('ws_github' => array('connection_id' => '4', 'channel_id' => '9', 'repository' => 'kodpen/site', 'events' => array('ci', 'label'), 'labels' => 'ready', 'other' => 'x'));

	pg_assert_same(array('github_connection_id' => '4', 'github_channel_id' => '9', 'github_repository' => 'kodpen/site', 'github_events' => array('ci', 'label'), 'github_labels' => 'ready'),
		ws_github_links_settings_dialog_input($post), 'the old names, nothing else');
	pg_assert_same(array(), ws_github_links_settings_dialog_input(array('ws_github' => 'x')), 'a hand-made string is no form');

	$source = settings_workspace_channels_test_source('includes/workspace/github_links.php');
	pg_assert_contains("name=\"ws_github_link_save\" value=\"1\" form=\"' . h(\$form) . '\"", $source, 'Link is a named button');
	pg_assert_contains("name=\"ws_github_link_remove\" value=\"' . (int) \$link['id'] . '\"", $source, 'Remove is a named button');
}

function test_settings_workspace_channels_save_runs_the_buttons_after_the_save()
{
	$save = settings_workspace_channels_test_source('includes/settings/workspace_channels.save.php');

	pg_assert_true(strpos($save, "mark_error('ws_voice_'") < strpos($save, 'ws_voice_settings_write('), 'the voice card is checked before it is written');
	pg_assert_true(strpos($save, 'ws_voice_settings_write(') < strpos($save, "'ws_template_archive'"), 'the buttons run after the save');
	pg_assert_contains("ws_templates_settings_post(\$ws_viewer, \$liveform, (int) \$ws_value, \$ws_archive, \$ws_warn)", $save, 'a template\'s refusal is a warning');
	pg_assert_contains("ws_github_links_settings_post(\$ws_viewer, 'github_link_save', \$liveform, ws_github_links_settings_dialog_input(\$_POST), \$ws_warn)", $save, 'the drawer\'s fields');

	$templates = settings_workspace_channels_test_source('includes/workspace/templates.php');
	pg_assert_contains("name=\"' . (\$present['archived'] ? 'ws_template_restore' : 'ws_template_archive') . '\"", $templates, 'archive and bring back are named buttons');
}

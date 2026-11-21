<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents, Pinegrap AI's and Voice's opened
 * rows (ws_ai_row_html(), ws_voice_row_html()): one column, no help line
 * longer than 110 characters, no list of requests in the pane, the parts in
 * their order, the fold "Application", the meta lines, and the posted names
 * and button values the save module reads. The drawers: Pinegrap AI's "The
 * latest requests", and "Still missing" at the top of a part's guide.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_help.php');

/** Pinegrap AI's row values: ready, an application chosen. */
function settings_ai_voice_rows_test_ai($over = array())
{
	return $over + array(
		'dialog'   => true,
		'form'     => 'pg-settings-form',
		'self_url' => '',
		'admin'    => true,
		'config'   => array('enabled' => true, 'app_id' => 7, 'model' => 'pinegrap-1', 'license_set' => true),
		'missing'  => array(),
		'apps'     => array(array('id' => 7, 'name' => 'Pinegrap AI', 'status' => 'active')),
		'app'      => array('id' => 7, 'name' => 'Pinegrap AI', 'status' => 'active', 'scopes' => array('workspace:read', 'workspace:write', 'tasks:write', 'orders:read')),
		'scopes'   => array('workspace:read', 'workspace:write', 'tasks:write'),
		'notices'  => array(),
		'license'  => array('label' => 'Valid until 01.01.2027', 'tone' => 'pg-tone-teal'),
		'tokens'   => 'Tokens, last 7 days: 1,200; last 30 days: 4,800',
		'last'     => 'Last request 14:05 · Answered',
	);
}

/** Voice's row values: OpenAI, a key of its own, speech switched on. */
function settings_ai_voice_rows_test_voice($over = array())
{
	return $over + array(
		'dialog'      => true,
		'self_url'    => '',
		'admin'       => true,
		'config'      => array('enabled' => true, 'url' => '', 'model' => '', 'key_set' => true, 'service' => 'openai', 'own_key' => true, 'provider' => 'OpenAI'),
		'speech'      => array('enabled' => true, 'url' => '', 'base' => 'https://api.openai.com/v1', 'own_key' => true, 'model' => '', 'voice' => 'alloy', 'provider' => 'OpenAI'),
		'missing'     => array(),
		'notices'     => array(),
		'connection'  => '<div class="ws-test-connection"><input type="checkbox" name="ws_voice_key_clear" value="1" id="ws_voice_key_use"></div>',
	);
}

/** Every help line of a row, as text. */
function settings_ai_voice_rows_test_help($html)
{
	preg_match_all('/<(?:div|span)[^>]*class="[^"]*(?:pg-field-help|ws-grow)[^"]*"[^>]*>(.*?)<\/(?:div|span)>/s', $html, $found);
	$out = array();

	foreach ($found[1] as $text) {
		$text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));

		if ($text !== '') {
			$out[] = $text;
		}
	}

	return $out;
}

/** The posted names, with the value of a named button. */
function settings_ai_voice_rows_test_names($html, $buttons)
{
	preg_match_all('/name="([^"]+)"(?: value="([^"]*)")?/', $html, $found, PREG_SET_ORDER);
	$out = array();

	foreach ($found as $match) {
		$out[] = $match[1] . (in_array($match[1], $buttons, true) ? '=' . $match[2] : '');
	}

	$out = array_values(array_unique($out));
	sort($out);

	return $out;
}

function test_settings_ai_voice_rows_one_column_no_requests()
{
	$rows = array(
		'ai'    => ws_ai_row_html(settings_ai_voice_rows_test_ai()),
		'voice' => ws_voice_row_html(settings_ai_voice_rows_test_voice()),
	);

	foreach ($rows as $name => $html) {
		pg_assert_false((bool) preg_match('/\b(?:sm|md|lg|xl):col-[57]\b/', $html), $name . ': no second column');
		pg_assert_false(strpos($html, 'ws-claude-status') !== false, $name . ': the right column is gone');
		pg_assert_false(strpos($html, 'ws-claude-recent') !== false, $name . ': no list of requests in the pane');
		pg_assert_false(strpos($html, h(lang('Still missing'))) !== false, $name . ': what is missing stands in the guide drawer');
		pg_assert_false(strpos($html, 'pg-button-fill') !== false, $name . ': the dialog\'s Save is the one filled button');
		pg_assert_false(strpos($html, 'btn ') !== false, $name . ': look A buttons only');
	}
}

function test_settings_ai_voice_rows_help_lines_are_short()
{
	$rows = array(
		ws_ai_row_html(settings_ai_voice_rows_test_ai()),
		ws_ai_row_html(settings_ai_voice_rows_test_ai(array('app' => null, 'config' => array('enabled' => false, 'app_id' => 0, 'model' => '', 'license_set' => false), 'missing' => array('x')))),
		ws_ai_row_html(settings_ai_voice_rows_test_ai(array('admin' => false))),
		ws_voice_row_html(settings_ai_voice_rows_test_voice()),
		ws_voice_row_html(settings_ai_voice_rows_test_voice(array('config' => array('enabled' => false, 'url' => 'https://api.groq.com/openai/v1', 'model' => 'whisper', 'key_set' => false, 'service' => 'custom', 'own_key' => false, 'provider' => 'Groq')))),
		ws_voice_row_html(settings_ai_voice_rows_test_voice(array('admin' => false))),
	);
	$seen = 0;

	foreach ($rows as $html) {
		foreach (settings_ai_voice_rows_test_help($html) as $text) {
			$seen++;
			pg_assert_true(mb_strlen($text) <= 110, 'at most 110 characters: ' . $text);
		}
	}

	pg_assert_true($seen >= 15, 'the help lines were found');
}

function test_settings_ai_voice_rows_ai_parts_in_order()
{
	$html = ws_ai_row_html(settings_ai_voice_rows_test_ai(array('notices' => array(array('tone' => 'danger', 'text' => 'The model did not answer.')))));
	$order = array('alert theme-danger', 'id="ws_ai_enabled"', 'data-bs-target="#pgset-ws-ai-guide"', 'ws-ai-facts', 'name="ws_ai_test"', 'data-bs-target="#pgset-ws-ai-requests"', 'Last request 14:05 · Answered', 'aria-controls="ws-ai-fold"', 'name="ws_ai_app_id"');
	$last = -1;

	foreach ($order as $needle) {
		$at = strpos($html, $needle);
		pg_assert_true($at !== false && $at > $last, $needle . ' in its place');
		$last = $at;
	}

	pg_assert_same(1, substr_count($html, 'class="pg-switch"'), 'one switch');
	pg_assert_same(1, substr_count($html, '<select'), 'one select, in the fold');
	pg_assert_contains(h(ws_assistants_help_line('ws-ai')), $html, 'the switch\'s one line');
	pg_assert_false(strpos($html, h(lang('What is written in a channel where Pinegrap AI is asked is sent to the model at ai.pinegrap.com. Public channels are open to it, private ones only when their manager allows it.'))) !== false, 'what is sent stands in the guide');

	// The facts as one meta line: the licence pill, the model, the tokens.
	preg_match('/<div class="[^"]*ws-ai-facts[^"]*">(.*?)<\/div>/s', $html, $facts);
	pg_assert_contains('<span class="pg-pill pg-tone-teal">Valid until 01.01.2027</span>', $facts[1], 'the licence pill');
	pg_assert_contains('<code>pinegrap-1</code>', $facts[1], 'the model');
	pg_assert_contains('Tokens, last 7 days: 1,200; last 30 days: 4,800', $facts[1], 'the tokens');

	pg_assert_contains(h(lang('Nobody has asked Pinegrap AI yet.')), ws_ai_row_html(settings_ai_voice_rows_test_ai(array('last' => lang('Nobody has asked Pinegrap AI yet.')))), 'none yet');
	pg_assert_false(strpos(ws_ai_row_html(settings_ai_voice_rows_test_ai(array('config' => array('enabled' => true, 'app_id' => 7, 'model' => '', 'license_set' => false)))), 'ws_ai_test') !== false, 'no test without a licence key');
}

function test_settings_ai_voice_rows_ai_fold()
{
	$html = ws_ai_row_html(settings_ai_voice_rows_test_ai());
	$fold = substr($html, strpos($html, 'ws-ai-fold"'));

	pg_assert_contains('class="pg-gather-toggle mt-0" data-ws-claude-fold aria-expanded="false" aria-controls="ws-ai-fold">' . h(lang('Application')), $html, 'shut while an application is chosen');
	pg_assert_contains('id="ws-ai-fold" hidden', $fold, 'its body hidden');
	pg_assert_contains('<strong>Pinegrap AI</strong> <span class="pg-text-meta">#7</span>', $fold, 'its name and number');
	pg_assert_contains(h(lang('It needs no secret: Pinegrap AI runs inside this site.')), $fold, 'one line');
	pg_assert_contains('<label class="pg-field-label" for="ws_ai_app_id">' . h(lang('Use another application')) . '</label>', $fold, 'Use another application with its select');
	pg_assert_contains('orders:read', $fold, 'what it may read');

	$none = ws_ai_row_html(settings_ai_voice_rows_test_ai(array('app' => null, 'config' => array('enabled' => true, 'app_id' => 0, 'model' => '', 'license_set' => true), 'missing' => array('x'))));
	$fold = substr($none, strpos($none, 'ws-ai-fold"'));
	pg_assert_contains('aria-expanded="true" aria-controls="ws-ai-fold"', $none, 'open with no application');
	pg_assert_false(strpos($fold, 'id="ws-ai-fold" hidden') !== false, 'its body shown');
	pg_assert_contains('name="ws_ai_key_create" value="1"', $fold, 'Create Pinegrap AI\'s key');
	pg_assert_contains(h(lang('Makes the application Pinegrap AI writes through; it writes only in the Workspace and the Tasks.')), $fold, 'and one line');

	$lacking = settings_ai_voice_rows_test_ai();
	$lacking['app']['scopes'] = array('workspace:read');
	pg_assert_contains('aria-expanded="true" aria-controls="ws-ai-fold"', ws_ai_row_html($lacking), 'open while a permission is missing');
}

function test_settings_ai_voice_rows_ai_posted_names_unchanged()
{
	$buttons = array('ws_ai_test', 'ws_ai_key_create');

	pg_assert_same(array('ws_ai_app_id', 'ws_ai_enabled', 'ws_ai_test=1'), settings_ai_voice_rows_test_names(ws_ai_row_html(settings_ai_voice_rows_test_ai()), $buttons), 'with an application');
	pg_assert_same(array('ws_ai_app_id', 'ws_ai_enabled', 'ws_ai_key_create=1', 'ws_ai_test=1'), settings_ai_voice_rows_test_names(ws_ai_row_html(settings_ai_voice_rows_test_ai(array('app' => null, 'missing' => array('x')))), $buttons), 'with none');

	$other = ws_ai_row_html(settings_ai_voice_rows_test_ai(array('admin' => false)));
	pg_assert_same(array('ws_ai_app_id', 'ws_ai_enabled'), settings_ai_voice_rows_test_names($other, $buttons), 'somebody else: no buttons');
	pg_assert_contains('id="ws_ai_enabled" aria-describedby="ws_ai_enabled-help" checked disabled', $other, 'read only');
	pg_assert_contains(h(lang('Only an administrator can connect Pinegrap AI.')), $other, 'and says why');
}

function test_settings_ai_voice_rows_voice_parts()
{
	$html = ws_voice_row_html(settings_ai_voice_rows_test_voice(array('notices' => array(array('tone' => 'danger', 'text' => 'The service refused the key.')))));
	$order = array('alert theme-danger', 'id="ws_voice_enabled"', 'data-bs-target="#pgset-ws-voice-guide"', 'id="ws_voice_service"', 'id="ws_voice_model"', 'ws-test-connection', 'id="ws_voice_url"', 'id="ws_voice_key"', h(lang('Recordings are sent to')), h(lang('Spoken answers')), 'id="ws_voice_speech_enabled"', 'id="ws_voice_speech_url"', 'id="ws_voice_speech_model"', 'id="ws_voice_speech_voice"', h(lang('Spoken answers are made by')));
	$last = -1;

	foreach ($order as $needle) {
		$at = strpos($html, $needle);
		pg_assert_true($at !== false && $at > $last, $needle . ' in its place');
		$last = $at;
	}

	// Provider and Model side by side; Model and Voice side by side.
	pg_assert_true((bool) preg_match('/<div class="sm:col-6">\s*<label class="pg-field-label" for="ws_voice_service">.*?<div class="sm:col-6">\s*<label class="pg-field-label" for="ws_voice_model">/s', $html), 'Provider and Model in one row');
	pg_assert_true((bool) preg_match('/<div class="sm:col-6">\s*<label class="pg-field-label" for="ws_voice_speech_model">.*?<div class="sm:col-6">\s*<label class="pg-field-label" for="ws_voice_speech_voice">/s', $html), 'Model and Voice in one row');

	// The custom address only for the custom provider.
	pg_assert_contains('<div class="d-none" data-ws-voice-when="custom">', $html, 'hidden while OpenAI is chosen');
	$custom = ws_voice_row_html(settings_ai_voice_rows_test_voice(array('config' => array('enabled' => true, 'url' => 'https://api.groq.com/openai/v1', 'model' => '', 'key_set' => true, 'service' => 'custom', 'own_key' => true, 'provider' => 'Groq'))));
	pg_assert_contains('<div class="" data-ws-voice-when="custom">', $custom, 'shown for the custom provider');

	// The key full width, its one line and Remove the stored key on that line.
	pg_assert_true((bool) preg_match('/id="ws_voice_key-help">' . preg_quote(h(lang('Stored encrypted and never shown again.')), '/') . '<\/span><label[^>]*><input class="pg-checkbox" type="checkbox" name="ws_voice_key_clear"/', $html), 'the key\'s line with Remove the stored key');

	// The meta lines under their parts, never a column.
	pg_assert_contains('<div class="mt-4 pg-text-meta d-flex flex-wrap align-items-center gap-2"><span>' . h(lang('Recordings are sent to')) . '</span> <span>OpenAI</span><span aria-hidden="true">·</span><code>' . h(ws_voice_default_model()) . '</code></div>', $html, 'Recordings are sent to, one meta line');
	pg_assert_contains('<code>alloy</code>', $html, 'Spoken answers are made by, with the voice');

	// The speech switch's other sentences stand in the guide.
	pg_assert_contains(h(lang('The answer\'s text is sent to the speech service; the sound is kept beside the answer.')), $html, 'one line');
	$steps = ws_assistants_guide_steps('ws-voice');
	pg_assert_same(lang('Spoken answers'), $steps[count($steps) - 1]['title'], 'the guide says the rest');
}

function test_settings_ai_voice_rows_voice_posted_names_unchanged()
{
	$names = settings_ai_voice_rows_test_names(ws_voice_row_html(settings_ai_voice_rows_test_voice()), array());

	pg_assert_same(array('ws_voice_enabled', 'ws_voice_key', 'ws_voice_key_clear', 'ws_voice_model', 'ws_voice_service', 'ws_voice_speech_enabled', 'ws_voice_speech_key', 'ws_voice_speech_key_clear', 'ws_voice_speech_model', 'ws_voice_speech_url', 'ws_voice_speech_voice', 'ws_voice_url'), $names, 'the names the save module reads');

	$other = ws_voice_row_html(settings_ai_voice_rows_test_voice(array('admin' => false)));
	pg_assert_false(strpos($other, 'name="ws_voice_key"') !== false || strpos($other, 'name="ws_voice_speech_key"') !== false, 'somebody else: no keys');
	pg_assert_contains(h(lang('Only an administrator can connect a transcription service.')), $other, 'and says why');

	// Off the dialog the old screen's names, without ws_.
	pg_assert_contains('name="voice_enabled"', ws_voice_row_html(settings_ai_voice_rows_test_voice(array('dialog' => false, 'token_field' => ''))), 'the old screen\'s names');
}

function test_settings_ai_voice_rows_drawers()
{
	$body = ws_ai_requests_drawer_html(array('id' => 1, 'role' => 0), array());
	pg_assert_contains('<ul class="list-unstyled pg-card-inner ws-claude-recent mb-0">', $body, 'the requests as rows');
	pg_assert_contains(h(lang('Nobody has asked Pinegrap AI yet.')), $body, 'none yet');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');
	pg_assert_contains("pg_settings_drawer('pgset-ws-ai-requests', h(lang('The latest requests'))", $source, 'the drawer and its title');
	pg_assert_contains('$pg_settings_modals[] = ws_settings_ai_requests_drawer($ws_viewer, !empty($pg_settings_in_dialog));', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/workspace_assistants.php'), 'the pane draws it');

	// "Still missing" tops the guide drawer.
	$guide = ws_settings_assistants_guide_drawer('ws-ai', true, array('Pinegrap AI is switched off.', 'No application is chosen for Pinegrap AI.'));
	$top = strpos($guide, h(lang('Still missing')));
	pg_assert_true($top !== false && $top < strpos($guide, '<ol class="stepper'), 'above the steps');
	pg_assert_same(2, substr_count($guide, '<li>'), 'the whole list');
	pg_assert_false(strpos(ws_settings_assistants_guide_drawer('ws-voice', true), h(lang('Still missing'))) !== false, 'nothing missing, no list');
}

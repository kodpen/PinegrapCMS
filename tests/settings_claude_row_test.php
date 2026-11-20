<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents, Claude's opened row
 * (ws_claude_row_html()): one column; outside the fold one switch, two text
 * fields, one checkbox, two selects and three buttons; no help line longer
 * than 110 characters; the fold "Application and key" holds the key, the
 * secret's hint, Reset secret and the application select; the posted names
 * are the ones the save module reads. The drawer "The latest requests"
 * holds the version and the requests.
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

/**
 * The row's values as ws_claude_settings_card() hands them over: ready, a
 * token stored, an application chosen.
 */
function settings_claude_row_test_values($over = array())
{
	return $over + array(
		'dialog'   => true,
		'form'     => 'pg-settings-form',
		'self_url' => '',
		'admin'    => true,
		'config'   => array('enabled' => true, 'app_id' => 4, 'routine_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_1/fire', 'token_set' => true),
		'missing'  => array(),
		'summary'  => array('hour' => 9, 'channel_id' => 2, 'channels' => array(2 => 'general')),
		'apps'     => array(array('id' => 4, 'name' => 'Claude', 'status' => 'active')),
		'app'      => array('name' => 'Claude', 'api_key' => 'pk_1234', 'api_secret_hint' => 'a1b2', 'kind' => 'server', 'scopes' => array('workspace:read', 'workspace:write', 'tasks:write', 'content:read')),
		'scopes'   => array('workspace:read', 'workspace:write', 'tasks:write'),
		'notices'  => array(),
		'last'     => 'Last request 14:05 · Claude answered',
		'fresh'    => false,
	);
}

/** The part of the row before the fold, and the fold. */
function settings_claude_row_test_split($html)
{
	$at = strpos($html, 'ws-claude-fold"');
	pg_assert_true($at !== false, 'the fold is drawn');

	return array(substr($html, 0, $at), substr($html, $at));
}

function test_settings_claude_row_counts_outside_the_fold()
{
	$html = ws_claude_row_html(settings_claude_row_test_values());
	list($outside) = settings_claude_row_test_split($html);

	pg_assert_false((bool) preg_match('/\b(?:sm|md|lg|xl):col-[57]\b/', $html), 'no second column');
	pg_assert_false(strpos($html, 'ws-claude-status') !== false, 'the right column is gone');
	pg_assert_same(1, substr_count($outside, 'class="pg-switch"'), 'one switch');
	pg_assert_same(2, preg_match_all('/<input[^>]+type="(?:url|password|text)"/', $outside), 'two text fields');
	pg_assert_same(1, substr_count($outside, 'class="pg-checkbox"'), 'one checkbox');
	pg_assert_same(2, substr_count($outside, '<select'), 'two selects');
	pg_assert_same(3, substr_count($outside, '<button'), 'three buttons');
	pg_assert_false(strpos($outside, 'data-ws-copy-field') !== false, 'no copy field');
	pg_assert_false(strpos($html, 'ws-claude-recent') !== false, 'no list of requests');
	pg_assert_false(strpos($html, 'ws-claude-key-prompt') !== false, 'the prompt is only in the set-up drawer');
	pg_assert_false(strpos($html, h(lang('Still missing'))) !== false, 'what is missing stands in the set-up drawer');
	pg_assert_false(strpos($html, 'pg-button-fill') !== false, 'the dialog\'s Save is the one filled button');

	// The buttons in their order, the last request at their right.
	$try = strpos($outside, 'name="ws_claude_test"');
	$setup = strpos($outside, 'data-bs-target="#pgset-ws-claude-setup"');
	$requests = strpos($outside, 'data-bs-target="#pgset-ws-claude-requests"');
	pg_assert_true($try !== false && $try < $setup && $setup < $requests, 'Try the connection, Set up on claude.ai, The latest requests');
	pg_assert_true($requests < strpos($outside, 'Last request 14:05 · Claude answered'), 'the grey line last');

	// The order of the parts.
	$order = array('ws_claude_enabled"', 'ws_claude_routine_url"', 'ws_claude_token"', 'ws_claude_summary_hour"', 'ws_claude_test"');
	$last = -1;

	foreach ($order as $needle) {
		$at = strpos($outside, $needle);
		pg_assert_true($at !== false && $at > $last, $needle . ' in its place');
		$last = $at;
	}
}

function test_settings_claude_row_help_lines_are_short()
{
	$rows = array(
		ws_claude_row_html(settings_claude_row_test_values()),
		ws_claude_row_html(settings_claude_row_test_values(array('app' => null, 'config' => array('enabled' => false, 'app_id' => 0, 'routine_url' => '', 'token_set' => false), 'missing' => array('x')))),
		ws_claude_row_html(settings_claude_row_test_values(array('notices' => array(
			array('tone' => 'warning', 'text' => lang('This site is not opened over HTTPS, so the routine cannot reach it.'), 'prompt' => false),
			array('tone' => 'warning', 'text' => lang('The routine still runs the long prompt pasted before.'), 'prompt' => true),
		)))),
	);
	$seen = 0;

	foreach ($rows as $html) {
		preg_match_all('/<(?:div|span)[^>]*class="[^"]*(?:pg-field-help|ws-grow)[^"]*"[^>]*>(.*?)<\/(?:div|span)>/s', $html, $found);

		foreach ($found[1] as $text) {
			$text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));

			if ($text === '') {
				continue;
			}

			$seen++;
			pg_assert_true(mb_strlen($text) <= 110, 'at most 110 characters: ' . $text);
		}
	}

	pg_assert_true($seen >= 6, 'the help lines were found');

	$prompt = $rows[2];
	pg_assert_contains(h(lang('Show the new prompt')), $prompt, 'an old prompt leads to the new one');
	pg_assert_true(substr_count($prompt, 'data-bs-target="#pgset-ws-claude-setup"') === 2, 'in the set-up drawer');
}

function test_settings_claude_row_fold()
{
	$html = ws_claude_row_html(settings_claude_row_test_values());
	list($outside, $fold) = settings_claude_row_test_split($html);

	pg_assert_contains('class="pg-gather-toggle mt-0" data-ws-claude-fold aria-expanded="false" aria-controls="ws-claude-fold">' . h(lang('Application and key')), $fold, 'shut while an application is chosen');
	pg_assert_contains('id="ws-claude-fold" hidden', $fold, 'its body hidden');
	pg_assert_contains('data-ws-copy-field="ws-claude-key"', $fold, 'the key with Copy');
	pg_assert_contains('••••a1b2', $fold, 'the secret\'s last four');
	pg_assert_contains('name="ws_claude_key_reset" value="1"', $fold, 'Reset secret');
	pg_assert_contains('onclick="return confirm(', $fold, 'its warning in the confirm');
	pg_assert_contains('name="ws_claude_app_id"', $fold, 'the application select');
	pg_assert_contains('href="api_settings.php"', $fold, 'the name leads to Application Access');
	pg_assert_contains('content:read', $fold, 'what it may read');
	pg_assert_false(strpos($outside, 'ws_claude_app_id') !== false, 'the select is in the fold only');

	// No application: the fold is open and holds Create Claude's key.
	$none = ws_claude_row_html(settings_claude_row_test_values(array('app' => null, 'config' => array('enabled' => true, 'app_id' => 0, 'routine_url' => '', 'token_set' => false), 'missing' => array('x'))));
	list(, $fold) = settings_claude_row_test_split($none);
	pg_assert_contains('aria-expanded="true"', $fold, 'open with no application');
	pg_assert_false(strpos($fold, 'id="ws-claude-fold" hidden') !== false, 'its body shown');
	pg_assert_contains('name="ws_claude_key_create" value="1"', $fold, 'Create Claude\'s key');

	// An application without a permission the routine needs opens it too.
	$lacking = settings_claude_row_test_values();
	$lacking['app']['scopes'] = array('workspace:read');
	pg_assert_contains('aria-expanded="true"', settings_claude_row_test_split(ws_claude_row_html($lacking))[1], 'open while a permission is missing');
}

function test_settings_claude_row_posted_names_unchanged()
{
	$names = function ($html) {
		preg_match_all('/name="([^"]+)"( value="([^"]*)")?/', $html, $found, PREG_SET_ORDER);
		$out = array();

		foreach ($found as $match) {
			$out[] = $match[1] . (isset($match[3]) && strpos($match[1], 'ws_claude_key_') === 0 || $match[1] === 'ws_claude_test' ? '=' . $match[3] : '');
		}

		sort($out);

		return $out;
	};

	pg_assert_same(array('ws_claude_app_id', 'ws_claude_enabled', 'ws_claude_key_reset=1', 'ws_claude_routine_url', 'ws_claude_summary_channel', 'ws_claude_summary_hour', 'ws_claude_test=1', 'ws_claude_token', 'ws_claude_token_clear'), $names(ws_claude_row_html(settings_claude_row_test_values())), 'with an application');

	$none = ws_claude_row_html(settings_claude_row_test_values(array('app' => null, 'missing' => array('x'))));
	pg_assert_same(array('ws_claude_app_id', 'ws_claude_enabled', 'ws_claude_key_create=1', 'ws_claude_routine_url', 'ws_claude_summary_channel', 'ws_claude_summary_hour', 'ws_claude_token', 'ws_claude_token_clear'), $names($none), 'with none');

	// Somebody else who may open the settings: the fields read only, no key.
	$other = ws_claude_row_html(settings_claude_row_test_values(array('admin' => false)));
	pg_assert_false(strpos($other, 'ws_claude_key_') !== false, 'no key buttons');
	pg_assert_false(strpos($other, 'data-ws-copy-field') !== false, 'no key');
	pg_assert_contains('id="ws_claude_enabled" aria-describedby="ws_claude_enabled-help" checked disabled', $other, 'read only');
}

function test_settings_claude_row_requests_drawer()
{
	$body = ws_claude_requests_drawer_html('2026.11.0-3', '<li class="pg-card-inner-row ws-claude-recent-row">r</li>');
	pg_assert_contains('<span class="font-monospace">2026.11.0-3</span>', $body, 'the version as one line');
	pg_assert_contains(h(lang('It changes with every update that changes them. A run takes a request only with the version it read at its start.')), $body, 'with its help');
	pg_assert_true(strpos($body, '2026.11.0-3') < strpos($body, 'ws-claude-recent-row'), 'then the requests');
	pg_assert_contains(h(lang('Nobody has asked Claude yet.')), ws_claude_requests_drawer_html('1', ''), 'none yet');

	$drawer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');
	pg_assert_contains("pg_settings_drawer('pgset-ws-claude-requests', h(lang('The latest requests'))", $drawer, 'the drawer and its title');
	pg_assert_contains('$pg_settings_modals[] = ws_settings_claude_requests_drawer($ws_viewer, !empty($pg_settings_in_dialog));', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/workspace_assistants.php'), 'the pane draws it');
}

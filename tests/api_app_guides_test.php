<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Application Access's connection guides (api_app_guides()) and
 * the edit drawer's parts (includes/api/app_drawer_view.php): every guide
 * has its fields and says who it acts as, the compact MCP address is named
 * when there is one, which guide suits which application, step 3's guide
 * for each starting point, and the drawer's head, tabs and Overview as the
 * drawing shows them.
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

if (!function_exists('lang')) {
	function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
}

if (!function_exists('h')) {
	function h($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/app_guides.php');

function api_app_guides_test_at($over = array())
{
	return array_merge(array(
		'site'        => 'https://example.com',
		'api'         => 'https://example.com/pinegrap/integration.php',
		'docs'        => 'api_docs.php',
		'mcp'         => 'https://example.com/pinegrap/mcp.php',
		'mcp_compact' => 'https://example.com/pinegrap/mcp_compact.php',
		'claude_link' => 'https://claude.ai/settings/connectors?add=1',
		'start_text'  => 'You are an agent in the Workspace of a Pinegrap site.'
	), $over);
}

function test_api_app_guides_shape()
{
	$guides = api_app_guides(api_app_guides_test_at());

	pg_assert_same(array('key_secret', 'token', 'mcp_signin', 'devices', 'agent'), array_keys($guides), 'the guides in reading order');

	foreach ($guides as $id => $guide) {
		pg_assert_same($id, $guide['id'], $id . ' carries its id');

		foreach (array('icon', 'tone', 'title', 'line') as $field) {
			pg_assert_true(is_string($guide[$field]) && ($guide[$field] !== ''), $id . ' has ' . $field);
		}

		pg_assert_true(in_array($guide['acts'], array('application', 'person'), true), $id . ' says who it acts as');
		pg_assert_true(!empty($guide['kinds']), $id . ' names the kinds it suits');
		pg_assert_true(count($guide['steps']) >= 2, $id . ' has numbered steps');

		foreach ($guide['steps'] as $step) {
			pg_assert_true($step['title'] !== '', $id . ': every step has a title');
			pg_assert_true(($step['value'] === '') || ($step['value_id'] !== ''), $id . ': a value has an id for its Copy');
			pg_assert_true(($step['link'] === '') || ($step['link_label'] !== ''), $id . ': a link has a label');
		}
	}

	pg_assert_same('person', $guides['mcp_signin']['acts'], 'MCP with sign-in acts as the person');
	pg_assert_same('application', $guides['key_secret']['acts'], 'a key acts as the application');
	pg_assert_true(!isset($guides['console']), 'the Claude Console guide is not written yet');
}

function test_api_app_guides_mcp_addresses()
{
	$guides = api_app_guides(api_app_guides_test_at());
	$values = array_column($guides['mcp_signin']['steps'], 'value', 'value_id');

	pg_assert_same('https://example.com/pinegrap/mcp.php', $values['mcp'], 'the MCP address');
	pg_assert_same('https://example.com/pinegrap/mcp_compact.php', $values['mcp_compact'], 'the compact address, for AI assistants');
	pg_assert_true(count($guides['mcp_signin']['clients']) > 3, 'each client\'s own steps');
	pg_assert_contains('claude mcp add --transport http pinegrap https://example.com/pinegrap/mcp.php', $guides['mcp_signin']['clients']['claude_code']['snippet']);

	$links = array_column($guides['mcp_signin']['steps'], 'link');
	pg_assert_true(in_array('https://claude.ai/settings/connectors?add=1', $links, true), 'Connect to Claude');

	$without = api_app_guides(api_app_guides_test_at(array('mcp_compact' => '', 'claude_link' => '')));
	$ids = array_column($without['mcp_signin']['steps'], 'value_id');
	pg_assert_true(!in_array('mcp_compact', $ids, true), 'no compact step without the address');
	pg_assert_same('', implode('', array_column($without['mcp_signin']['steps'], 'link')), 'off https there is no Claude link');
}

function test_api_app_guides_key_and_token_values()
{
	$guides = api_app_guides(api_app_guides_test_at());
	$key = array_column($guides['key_secret']['steps'], 'value', 'value_id');
	$token = array_column($guides['token']['steps'], 'value', 'value_id');
	$agent = array_column($guides['agent']['steps'], 'value', 'value_id');

	pg_assert_same('https://example.com', $key['site'], 'the site address comes first');
	pg_assert_same('curl -u APPLICATION_KEY:SECRET_KEY "https://example.com/pinegrap/integration.php/meta"', $key['curl'], 'one line to try it');
	pg_assert_same('Authorization: Bearer TOKEN', $token['token_header'], 'the token goes in the Bearer header');
	pg_assert_same("PINEGRAP_KEY\nPINEGRAP_SECRET", $agent['agent_env'], 'the two environment variables');
	pg_assert_same('You are an agent in the Workspace of a Pinegrap site.', $agent['agent_start'], 'the start text, English as written');

	$no_ws = api_app_guides(api_app_guides_test_at(array('start_text' => '')));
	pg_assert_true(!in_array('agent_start', array_column($no_ws['agent']['steps'], 'value_id'), true), 'no start text without the Workspace');
}

function test_api_app_guides_fit()
{
	$guides = api_app_guides(api_app_guides_test_at());

	pg_assert_same('', api_app_guide_fit($guides['key_secret'], 'server', true), 'a key with a secret');
	pg_assert_same('no_secret', api_app_guide_fit($guides['key_secret'], 'server', false), 'a key made without a secret');
	pg_assert_same('kind', api_app_guide_fit($guides['key_secret'], 'oauth', false), 'an OAuth application has no key to send');
	pg_assert_same('', api_app_guide_fit($guides['mcp_signin'], 'oauth', false), 'sign-in suits OAuth');
	pg_assert_same('kind', api_app_guide_fit($guides['mcp_signin'], 'server', true), 'sign-in does not suit a key');
	pg_assert_same('', api_app_guide_fit($guides['devices'], 'device', false), 'devices suit team devices');
	pg_assert_same('kind', api_app_guide_fit($guides['token'], 'device', false), 'a device has no token');
}

function test_api_app_guides_for_starting_points()
{
	$guides = api_app_guides(api_app_guides_test_at());

	pg_assert_same('mcp_signin', api_app_guide_for($guides, 'mcp_signin', 'oauth')['id'], 'the card names its guide');
	pg_assert_same('devices', api_app_guide_for($guides, '', 'device')['id'], 'without a card the kind decides');
	pg_assert_same('key_secret', api_app_guide_for($guides, 'missing', 'server')['id'], 'an unknown guide falls back to the kind');
}

function api_app_guides_test_view()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/app_drawer_view.php');
}

function test_api_app_drawer_head_and_tabs()
{
	$view = api_app_guides_test_view();

	pg_assert_contains('<dialog class="drawer drawer-end" id="api_drawer" aria-labelledby="d_title" style="width:min(760px,100%)">', $view, '760 px wide, from the end');
	pg_assert_contains('<h5 class="drawer-title mb-0" id="d_title"></h5>', $view);
	pg_assert_contains('id="d_tile"', $view, 'the head has the kind\'s icon tile');
	pg_assert_contains('<span class="pg-pill pg-tone-teal" id="d_state"></span>', $view, 'and the state pill');
	pg_assert_contains('<div class="drawer-body tab-content flex-grow-1 overflow-auto">', $view);
	pg_assert_contains('<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">', $view);

	$tabs = substr($view, strpos($view, 'role="tablist"'), 3000);
	$order = array('#d_general', '#d_connect', '#d_perms', '#d_hooks', '#d_devices', '#d_log');
	$last = 0;
	foreach ($order as $target) {
		$at = strpos($tabs, 'data-bs-target="' . $target . '"');
		pg_assert_true(($at !== false) && ($at > $last), $target . ' in the drawing\'s order');
		$last = (int) $at;
	}
	pg_assert_true(strpos($tabs, 'd_security') === false, 'no Security tab: its fields fold under Advanced');
	pg_assert_same(6, substr_count($tabs, 'class="pg-seg-item'), 'six tabs as pg-seg items');
	pg_assert_same(6, substr_count($tabs, 'role="tab"'), 'each with role=tab');
	pg_assert_true(strpos($view, 'nav-tabs') === false, 'no Bootstrap tab strip');
}

function test_api_app_drawer_overview_parts()
{
	$view = api_app_guides_test_view();
	$overview = substr($view, strpos($view, 'function api_app_drawer_overview_html('));
	$overview = substr($overview, 0, strpos($overview, "\n}\n"));

	pg_assert_same(4, substr_count($overview, "\$fact('"), 'four facts');
	pg_assert_contains('<div class="pg-figures pg-figures-sm mb-5">', $overview, 'the facts are the figure cards\' compact form');
	pg_assert_contains('<div class="pg-card pg-figure"><div class="pg-figure-label">', $overview);
	pg_assert_contains('id="d_status"', $overview, 'the Active switch');
	pg_assert_contains('class="pg-switch"', $overview);
	pg_assert_contains("'key'  => 'd-advanced'", $overview, 'Advanced is a folded row');
	pg_assert_contains('data-go-tab="#d_perms"', $overview, 'the summary leads to Permissions');
	pg_assert_true(strpos($overview, 'id="d_danger_label"') > strpos($overview, "'key'  => 'd-advanced'"), 'Danger stands at the foot');
	pg_assert_contains('id="d_rotate"', $overview, 'New secret under Danger');
	pg_assert_contains('class="pg-button pg-button-danger pg-button-sm" id="d_delete_ask"', $overview, 'Delete under Danger');
	pg_assert_same(0, substr_count($overview, 'pg-button-fill'), 'the one filled button is the foot\'s Save');
}

function test_api_app_drawer_footer_ends_with_the_fill()
{
	$view = api_app_guides_test_view();
	$foot = substr($view, strpos($view, '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">'));
	$foot = substr($foot, 0, strpos($foot, '</div>'));

	pg_assert_contains('href="connections.php"', $foot, 'the foot links Connections');
	pg_assert_same(1, substr_count($foot, 'pg-button-fill'), 'one filled button');
	pg_assert_true(strrpos($foot, '<button') === strpos($foot, '<button type="submit" class="pg-button pg-button-fill" id="d_save">'), 'Save stands last');
}

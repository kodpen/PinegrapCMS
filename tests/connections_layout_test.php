<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Connections screen (connections.php): one list of groups
 * with the attention block on top and the state filter, each provider's
 * panel keeping its accounts and application, and the application fields as
 * form-field boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function connections_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
}

function test_connections_is_one_list_of_groups()
{
	$source = connections_layout_source();

	pg_assert_contains("'heading'             => lang('Connections')", $source, 'the screen is called Connections');
	pg_assert_contains('pg_connections_screen_order($shown', $source, 'the groups are read in the screen\'s order');
	pg_assert_contains('pg_connections_screen_may_see($service[\'place\']', $source, 'a row is shown only to a user who may open its place');
	pg_assert_contains('class="pg-card connections-attention', $source, 'the attention block is on top');
	pg_assert_true(strpos($source, 'connections-attention') < strpos($source, '\' . $groups_html . \''), 'the attention block before the groups');
	pg_assert_contains('class="pg-seg pg-list-seg"', $source, 'the state filter is the segmented control');
	pg_assert_true(strpos($source, 'pg_connect_api(') === false && strpos($source, 'pg_connect_check($account);') < strpos($source, 'Reads'), 'no outside call while the list is drawn: only the Check write calls the provider');
}

function test_connections_provider_drawer_keeps_what_the_screen_did()
{
	$source = connections_layout_source();

	pg_assert_contains('<dialog class="drawer drawer-end connections-drawer" id="connections-\' . h($provider_key) . \'"', $source, 'each provider has its drawer, under the id old links jump to');
	pg_assert_contains('data-drawer="connections-\' . h($provider_key) . \'"', $source, 'the provider row opens its drawer');

	foreach (array('data-action="check"', 'data-action="disconnect"', 'connections-connect', 'name="connections_action" value="save_app"', 'data-action="remove_app"') as $part) {
		pg_assert_contains($part, $source, $part . ' stays on the screen');
	}
}

function test_connections_service_page_repeats_no_field()
{
	$source = connections_layout_source();
	$page = substr($source, (int) strpos($source, 'function connections_service_page('), 9000);

	pg_assert_true(strpos($source, 'id="connections-detail"') === false, 'a service opens its page, not a drawer');
	pg_assert_contains('href="\' . h(pg_connections_screen_page_url($id)) . \'"', $source, 'a service row leads to its page');
	pg_assert_contains("array('label' => lang('Connections'), 'url' =>", $source, 'the page\'s breadcrumb leads back to the list');
	pg_assert_contains("h(lang('Status'))", $page, 'the Status card');
	pg_assert_contains("h(lang('Where it is used'))", $page, 'the Where it is used card');
	pg_assert_true(strpos($page, "lang('Status')") < strpos($page, "lang('Where it is used')"), 'Status comes first');
	pg_assert_true(strpos($page, '<input') === false && strpos($page, '<form') === false, 'no field of a setting is repeated on it');
	pg_assert_contains('pg_connections_screen_last_error(', $page, 'the last error on file');
	pg_assert_contains("lang('Open its settings')", $page, 'one button opens the setting\'s one place');
	pg_assert_contains('name="connections_return"', $source, 'a write from the page comes back to it');
}

function test_connections_row_offers_one_action()
{
	$source = connections_layout_source();

	pg_assert_contains('(($row[\'kind\'] !== \'settings\') && (pg_connections_screen_action($row[\'state\']) === \'connect\')) ? lang(\'Connect\') : lang(\'Manage\')', $source, 'Connect or Manage on every row, Manage on the E-mail sending row');
}

function test_connections_other_side_leads_to_application_access()
{
	$source = connections_layout_source();

	pg_assert_contains('id="connections-other-side"', $source, 'the other side\'s block');
	pg_assert_contains('href="api_settings.php"', $source, 'it leads to Application Access');
	pg_assert_contains('pg_connections_screen_other_side()', $source, 'with its counts');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $source, 'in the side column');
}

function test_connections_application_fields_are_look_a_fields()
{
	$source = connections_layout_source();
	$start = (int) strpos($source, 'function connections_app_form(');
	$drawers = substr($source, $start, (int) strpos($source, '/* The attention block:') - $start);

	foreach (array('redirect', 'client_id', 'client_secret') as $id) {
		pg_assert_true(preg_match('~<label class="pg-field-label" for="\' . \$field_id\(\'' . $id . '\'\) \. \'">~', $drawers) === 1, $id . ': a look A label');
	}

	pg_assert_contains('class="pg-switch" role="switch" name="enabled"', $drawers, 'the look\'s switch');
	pg_assert_contains('class="pg-button pg-button-quiet pg-button-sm connections-copy"', $drawers, 'Copy is the look\'s small quiet button');
	pg_assert_true(preg_match('~</button>\s*</div>\s*<div class="pg-field-help connections-copy-status"~', $drawers) === 1, 'the copy status follows the row the script reads');
	pg_assert_true(preg_match('~class="(btn|form-control|form-label|form-text|nav-|switch")~', $drawers) === 0, 'no old button, field or tab markup in the drawers');
	pg_assert_true(strpos($drawers, 'font-monospace') === false, 'the redirect address in the text face');
}

function test_connections_github_page_holds_its_connection_and_its_links()
{
	if (!defined('PG_SETTINGS_MENU')) {
		define('PG_SETTINGS_MENU', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

	$source = connections_layout_source();

	pg_assert_same(array('status', 'details', 'uses', 'repositories'), pg_connections_screen_page_cards('github', array('manager' => true, 'repositories' => true)), 'Status, Connection details, Where it is used, then Repository links');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('github', array('manager' => true, 'repositories' => false)), 'the links keep the Workspace settings right');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('github', array('manager' => false, 'repositories' => false)), 'the application keeps the screen\'s manager gate');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('stripe', array('manager' => true, 'repositories' => true)), 'a service whose settings are elsewhere has no card of its own yet');
	pg_assert_same('github', pg_connections_screen_page_providers()['github'] ?? null, 'GitHub\'s application is set up on its page');

	pg_assert_contains("connections_app_form(\$page_provider, \$providers[\$page_provider], \$provider_apps[\$page_provider]", $source, 'the page draws the provider\'s application');
	pg_assert_contains('name="submit_save" class="pg-button pg-button-sm pg-button-fill"', $source, 'Save is the one filled button');
	pg_assert_contains('name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"', $source, 'Save and try is quiet');
	pg_assert_contains("h(lang('Connection details'))", $source, 'the card is titled by what it holds');
	pg_assert_contains("lang('The GitHub MCP connector of the Workspace\\'s assistants is a connection of its own, with its own token.')", $source, 'the MCP connector is named under Where it is used');
	pg_assert_contains('ws_github_links_settings_post($ws_viewer, $ws_action, $liveform)', $source, 'the links are written on the page');
	pg_assert_contains('ws_github_links_can_manage($ws_viewer)', $source, 'under the Workspace settings right');

	$links = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/github_links.php');
	pg_assert_contains("h(lang('Repository links'))", $links, 'the card on the page');
	pg_assert_contains('id="pgset-ws-github"', $links, 'the Site Settings card keeps its address');
	pg_assert_contains("h(lang('Manage in Connections'))", $links, 'and leads to the page');
	pg_assert_same(array('workspace_channels', 'pgset-ws-github'), pg_settings_legacy_anchors()['pgset-ws-github'] ?? null, 'its own anchor lands on it');
	pg_assert_same('connections.php', pg_connections_registry()['github']['place'], 'the registry names the new place');
}

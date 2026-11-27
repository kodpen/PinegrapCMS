<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a ready-made MCP connector's page on Connections: the page's
 * connector, "Connection details" for the Workspace's administrator (Add
 * while none is added, the form filled after Add asked for a token, the
 * server's address and sign-in once added), the registry rows that follow
 * the move, the sign-in's return to the page, and the lines of Site
 * Settings' MCP servers row that lead there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_catalogue.php');

function connections_mcp_page_test_kept($over = array())
{
	return $over + array('for' => '', 'values' => array(), 'problem' => '', 'catalogue' => '', 'state' => '', 'text' => '');
}

function test_connections_mcp_page_connector_of_a_page()
{
	pg_assert_same('linear', pg_connections_screen_mcp_connector('mcp_linear'), 'a connector\'s page');
	pg_assert_same('cloudflare_docs', pg_connections_screen_mcp_connector('mcp_cloudflare_docs'), 'the key its row covers');
	pg_assert_same('', pg_connections_screen_mcp_connector('claude'), 'another service');
	pg_assert_same('', pg_connections_screen_mcp_connector('mcp_nothing'), 'no such row');
	pg_assert_same('', pg_connections_screen_mcp_connector(array('mcp_linear')), 'a list is no page');

	$catalogue = ws_mcp_catalogue();
	$rows = 0;

	foreach (pg_connections_registry() as $id => $row) {
		if ($row['group'] !== 'mcp') {
			continue;
		}

		$rows++;
		pg_assert_same('connections.php', $row['place'], $id . ' set up on Connections');
		pg_assert_same('#settings/workspace_assistants/pgset-ws-mcp', $row['found_at'], $id . ' still found at the MCP servers row');
		$key = pg_connections_screen_mcp_connector($id);
		pg_assert_true(isset($catalogue[$key]), $id . ' covers a catalogue entry');
		pg_assert_same('mcp_' . $key, $id, $id . ' is mcp_<key>, as ws_mcp_connections_url() builds it');
	}

	pg_assert_same(count($catalogue), $rows, 'one page for every ready-made connector');
}

function test_connections_mcp_page_cards()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('mcp_notion', array('manager' => true, 'mcp' => true)), 'the details for the Workspace\'s administrator');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('mcp_notion', array('manager' => true, 'mcp' => false)), 'not without the row\'s gate');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('mcp_notion', array('manager' => true, 'claude' => true)), 'Claude\'s right is not enough');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('slack', array('manager' => true, 'mcp' => true)), 'the right opens no other page');
}

function test_connections_mcp_page_card_before_add()
{
	$html = ws_mcp_connection_card_html('deepwiki', null, connections_mcp_page_test_kept(), 'connections.php?service=mcp_deepwiki');

	pg_assert_contains('id="connections-page-details">' . h(lang('Connection details')) . '</h2>', $html, 'the card\'s title');
	pg_assert_contains(h(lang('Not added yet.')), $html, 'not added');
	pg_assert_contains('<form method="post" action="connections.php?service=mcp_deepwiki">', $html, 'posts to its page');
	pg_assert_contains('<input type="hidden" name="ws_action" value="mcp_catalogue_add">', $html, 'Add');
	pg_assert_contains('<input type="hidden" name="mcp_catalogue" value="deepwiki">', $html, 'this connector');
	pg_assert_contains('name="token"', $html, 'the CSRF field');
	pg_assert_false(strpos($html, 'mcp_url') !== false, 'no form before Add');
	pg_assert_same('', ws_mcp_connection_card_html('nothing', null, connections_mcp_page_test_kept(), 'connections.php'), 'an unknown connector draws nothing');
}

// The server's form reads the site's address (pg_connect_redirect_uri()),
// which the runner leaves undefined, so the branches drawing it are read
// from the source.
function test_connections_mcp_page_card_branches()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_catalogue.php');
	$at = strpos($source, 'function ws_mcp_connection_card_html(');
	pg_assert_true($at !== false, 'the card');
	$card = substr($source, $at, strpos($source, "\n}\n", $at) - $at);

	pg_assert_contains("ws_mcp_settings_form(\$self_url, \$server, true, \$typed, \$typed ? \$kept['problem'] : '')", $card, 'an added server\'s address and sign-in, what a refused save kept drawn back');
	pg_assert_contains("elseif ((\$kept['for'] === 'new') && (\$kept['catalogue'] === \$key))", $card, 'the kept form only on its own connector\'s page');
	pg_assert_contains("ws_mcp_settings_form(\$self_url, null, true, \$kept['values'], \$kept['problem'], \$key)", $card, 'filled from the connector after Add asked for a token');
	pg_assert_contains("'mcp_connect', \$id", $card, 'Connect for a sign-in');
	pg_assert_contains("'mcp_disconnect', \$id", $card, 'Disconnect while connected');
	pg_assert_contains('ws_mcp_settings_script(false)', $card, 'the sign-in kind\'s fields switch');

	$form = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
	pg_assert_contains('<form method="post" action="\' . h($self_url) . \'" class="ws-dept-form" data-ws-mcp-form>', $form, 'the form posts to the page it is drawn on');
	pg_assert_contains('<input type="hidden" name="ws_action" value="mcp_save">', $form, 'as mcp_save');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
	pg_assert_contains("in_array(\$ws_action, array('mcp_save', 'mcp_catalogue_add', 'mcp_connect', 'mcp_disconnect'), true)", $screen, 'the four writes of the page');
	pg_assert_contains("pg_connections_screen_mcp_connector(\$_GET['service'])", $screen, 'the page named by the form\'s address');
	pg_assert_contains("(\$mcp_page !== '') && connections_mcp_may_manage(\$user)", $screen, 'under the row\'s gate');
	pg_assert_contains("'hold' => false", $screen, 'a sign-in leaves at once');
}

function test_connections_mcp_page_return_keeps_the_query()
{
	ws_mcp_settings_place(array('return' => 'connections.php?service=mcp_linear', 'anchor' => '#connections-details'));
	pg_assert_same('connections.php?service=mcp_linear&mcp_connected=3#connections-details', ws_mcp_settings_return_url(3), 'the page\'s service kept');
	pg_assert_same('connections.php?service=mcp_linear&mcp_connected=3#connections-details', pg_connect_return_path(ws_mcp_settings_return_url(3)), 'a return connect.php takes');

	ws_mcp_settings_place(array('return' => 'settings_workspace_assistants.php', 'anchor' => '#pgset-ws-mcp'));
	pg_assert_same('settings_workspace_assistants.php?mcp_connected=3#pgset-ws-mcp', ws_mcp_settings_return_url(3), 'Site Settings as before');
}

function test_connections_mcp_page_settings_lines_lead_there()
{
	pg_assert_same('connections.php?service=mcp_sentry&from=workspace_assistants%2Fpgset-ws-mcp', ws_mcp_connections_url('sentry'), 'a connector\'s page, back to MCP servers');
	pg_assert_same('connections.php#connections-group-mcp', ws_mcp_connections_url(''), 'the list\'s MCP connectors');

	$line = ws_mcp_connections_line_html('sentry', 'x');
	pg_assert_contains('href="connections.php?service=mcp_sentry&amp;from=workspace_assistants%2Fpgset-ws-mcp">' . h(lang('Manage in Connections')), $line, 'Manage in Connections, in the same tab');
	pg_assert_contains(h(lang('Open in Connections')), ws_mcp_connections_line_html('', 'x'), 'Open in Connections for the list');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
	pg_assert_contains("if (\$form !== null) {\n        \$catalogue = function_exists('ws_mcp_connections_line_html')\n            ? ws_mcp_connections_line_html('',", $source, 'Add a server leads to the connectors in Site Settings');
	pg_assert_same(2, substr_count($source, "ws_mcp_connections_line_html('', lang('The ready-made connectors are added, and their address and sign-in kept, on their pages in Connections.'))"), 'the card and Add a server say where the connectors are');

	pg_assert_contains('ws_mcp_catalogue_entry_for_url((string) $server[\'url\'])', $source, 'the drawer tells a connector\'s server apart');
	pg_assert_contains('lang(\'Its address and sign-in are on its page in Connections.\')', $source, 'and leads to its page');
}

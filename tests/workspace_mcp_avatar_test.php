<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one picture and icon an MCP server that is asked by its alias
 * is drawn with (includes/workspace/mcp_catalogue.php): the catalogue entry a
 * server's address is, the icon it gives, the letters and colour of the
 * avatar, and what a deleted server is drawn as.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_catalogue.php');

function test_workspace_mcp_avatar_catalogue_entry_by_url()
{
	$catalogue = ws_mcp_catalogue();

	pg_assert_same('deepwiki', ws_mcp_catalogue_entry_for_url($catalogue['deepwiki']['url']), 'the entry\'s own address');
	pg_assert_same('deepwiki', ws_mcp_catalogue_entry_for_url('HTTPS://MCP.DeepWiki.com:443/mcp/'), 'compared as ws_mcp_url_key() compares addresses');
	pg_assert_same('', ws_mcp_catalogue_entry_for_url('https://mcp.example.com/mcp'), 'an address of no entry');
	pg_assert_same('', ws_mcp_catalogue_entry_for_url(''), 'no address');
	pg_assert_same('', ws_mcp_catalogue_entry_for_url(array('x')), 'a list is no address');

	foreach ($catalogue as $key => $entry) {
		pg_assert_same($key, ws_mcp_catalogue_entry_for_url($entry['url']), $key . ': found by its address');
	}
}

function test_workspace_mcp_avatar_icon_from_catalogue_or_plug()
{
	$catalogue = ws_mcp_catalogue();
	$from = ws_mcp_agent_identity(array('id' => 4, 'alias' => 'wiki', 'url' => $catalogue['deepwiki']['url']));

	pg_assert_same($catalogue['deepwiki']['icon'], $from['icon'], 'a catalogue server carries the entry\'s icon');
	pg_assert_same('deepwiki', $from['catalogue'], 'and names the entry');

	$own = ws_mcp_agent_identity(array('id' => 5, 'alias' => 'tools', 'url' => 'https://mcp.example.com/mcp'));

	pg_assert_same('bi-plug', $own['icon'], 'a server added by its address has the plug');
	pg_assert_same('', $own['catalogue'], 'and no entry');

	$bad = ws_mcp_agent_identity(array('id' => 6, 'alias' => 'x', 'url' => 'https://mcp.deepwiki.com/mcp'), 0, array('deepwiki' => array('url' => 'https://mcp.deepwiki.com/mcp', 'icon' => 'onclick=x')));

	pg_assert_same('bi-plug', $bad['icon'], 'an icon that is no bi- class is not drawn');
}

function test_workspace_mcp_avatar_letters_and_seed()
{
	$identity = ws_mcp_agent_identity(array('id' => 7, 'alias' => 'deepwiki', 'url' => ''));

	pg_assert_same(pg_initials('deepwiki', ''), $identity['initials'], 'the letters of the alias');
	pg_assert_same('mcp7', $identity['seed'], 'the colour seeded by the server id');
	pg_assert_same(pg_initials_avatar_url($identity['initials'], 'contact', 'mcp7'), $identity['avatar'], 'the letters avatar');
	pg_assert_true(strpos($identity['avatar'], 'ws-ai.svg') === false, 'never Pinegrap AI\'s picture');

	$other = ws_mcp_agent_identity(array('id' => 8, 'alias' => 'deepwiki', 'url' => ''));

	pg_assert_true($identity['avatar'] !== $other['avatar'] || $identity['seed'] !== $other['seed'], 'two servers are seeded apart');
}

function test_workspace_mcp_avatar_same_from_every_entry_point()
{
	$server = array('id' => 9, 'alias' => 'linear', 'url' => 'https://mcp.linear.app/mcp', 'name' => 'Linear');

	// The answer view and the assistants list read the stored row, the
	// inbox line may hold only id, alias and url: one picture for all.
	$full = ws_mcp_agent_identity($server);
	$slim = ws_mcp_agent_identity(array('id' => '9', 'alias' => 'linear', 'url' => 'https://mcp.linear.app/mcp/'));

	pg_assert_same($full, $slim, 'the same identity whatever the row carries');
}

function test_workspace_mcp_avatar_deleted_server()
{
	$gone = ws_mcp_agent_identity(null, 12);

	pg_assert_same('bi-plug', $gone['icon'], 'a deleted server has the plug');
	pg_assert_same('mcp12', $gone['seed'], 'its colour still seeded by its id');
	pg_assert_same(pg_initials('mcp', ''), $gone['initials'], 'drawn with the letters of mcp');
	pg_assert_same(pg_initials_avatar_url($gone['initials'], 'contact', 'mcp12'), $gone['avatar'], 'the letters avatar');
}

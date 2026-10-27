<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one picture and icon an MCP server that is asked by its alias
 * is drawn with (includes/workspace/mcp_catalogue.php): the catalogue entry a
 * server's address is, the icon it gives, the letters and colour of the
 * avatar, what a deleted server is drawn as, and what the channel's @ picker
 * and a task's delegate tag are given to draw it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_ask.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/delegation.php');

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

function test_workspace_mcp_avatar_picker_entry_carries_the_identity()
{
	$catalogue = ws_mcp_catalogue();
	$server = array('id' => 14, 'alias' => 'wiki', 'name' => 'DeepWiki', 'url' => $catalogue['deepwiki']['url'], 'agent_tool' => '');
	$identity = ws_mcp_agent_identity($server);
	$entry = ws_mcp_boot_entry($server);

	pg_assert_same('wiki', $entry['alias'], 'the alias the picker writes');
	pg_assert_same('DeepWiki', $entry['name'], 'the server name for the meta line');
	pg_assert_same(false, $entry['agent'], 'answered by Pinegrap AI');
	pg_assert_same($identity['avatar'], $entry['avatar'], 'the avatar it has everywhere');
	pg_assert_same($catalogue['deepwiki']['icon'], $entry['icon'], 'the catalogue entry\'s icon');
	pg_assert_same($identity['initials'], $entry['initials'], 'the letters the Avatar draws');
	pg_assert_same($identity['tone'], $entry['tone'], 'the tone the letters are drawn on');
	pg_assert_same(array('alias', 'name', 'agent', 'avatar', 'initials', 'tone', 'icon'), array_keys($entry), 'nothing more reaches the page');

	$agent = ws_mcp_boot_entry(array('id' => 15, 'alias' => 'tools', 'name' => 'Tools', 'url' => 'https://mcp.example.com/mcp', 'agent_tool' => 'ask'));

	pg_assert_same(true, $agent['agent'], 'a server that answers on its own');
	pg_assert_same('bi-plug', $agent['icon'], 'the plug when no catalogue entry');
}

function test_workspace_mcp_avatar_delegate_icon()
{
	// An alias's icon is ws_mcp_agent_identity_for_id()'s, which reads the
	// server row (tested above through ws_mcp_agent_identity()); the keys
	// that are no alias need no database.
	pg_assert_same('', ws_assistant_mcp_icon('ai'), 'Pinegrap AI keeps the tag\'s own icon');
	pg_assert_same('', ws_assistant_mcp_icon('claude'), 'Claude keeps the tag\'s own icon');
	pg_assert_same('', ws_assistant_mcp_icon('mcp:x'), 'no server id');
	pg_assert_same('', ws_assistant_mcp_icon(''), 'no delegate');
}

// delegateIcon() of workspace_task_delegate.js run under Node on the tasks
// given; null when Node is not installed.
function workspace_mcp_avatar_test_delegate_icons($tasks)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_task_delegate.js');
	$start = strpos($source, "\n    function delegateIcon(");
	pg_assert_true($start !== false, 'delegateIcon() is found');
	$end = strpos($source, "\n    }\n", (int) $start);

	$script = "'use strict';\n" . substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n"
		. 'console.log(JSON.stringify(' . json_encode($tasks) . ".map(delegateIcon)));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsicon');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);

	return json_decode($printed, true);
}

function test_workspace_mcp_avatar_delegate_tag_icon()
{
	$icons = workspace_mcp_avatar_test_delegate_icons(array(
		array('delegate' => 'ai', 'delegate_icon' => ''),
		array('delegate' => 'claude', 'delegate_icon' => ''),
		array('delegate' => 'mcp:3', 'delegate_icon' => 'bi-book'),
		array('delegate' => 'mcp:3', 'delegate_icon' => ''),
		array('delegate' => 'mcp:3'),
		array('delegate' => 'mcp:3', 'delegate_icon' => 'bi-x" onclick="y'),
	));

	if ($icons === null) {
		return;
	}

	pg_assert_same(array('bi-cpu', 'bi-stars', 'bi-book', 'bi-plug', 'bi-plug', 'bi-plug'), $icons, 'Pinegrap AI, Claude, an alias\'s own icon, the plug otherwise');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts of a server's tools pane on the Workspace "MCP
 * servers" card (ws_mcp_settings_tools() in includes/workspace/mcp.php).
 * Designers and managers see the pane without the refresh button, the
 * switches or the Save button, so their texts name what an administrator
 * does instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_mcp_settings_texts_test_tools_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
	$start = strpos($source, 'function ws_mcp_settings_tools(');
	$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 1);

	return ($start === false) ? '' : (($end === false) ? substr($source, $start) : substr($source, $start, $end - $start));
}

function test_workspace_mcp_settings_texts_by_role()
{
	$tools = workspace_mcp_settings_texts_test_tools_source();

	pg_assert_true($tools !== '', 'ws_mcp_settings_tools is found');

	// Tools never read: the refresh button is an administrator's only.
	pg_assert_contains("\$admin\n                ? lang('The tools have not been read yet. Ask the server for them with the Ask the server for its tools again button.')\n                : lang('The tools have not been read yet. An administrator can ask the server for them.')", $tools, 'never read, by role');

	// No tool on: switches and Save are an administrator's only.
	pg_assert_contains("? lang(array('string' => 'No tool of @{var:1} is on yet, so it is not offered after @ in the channels and cannot be asked. Turn on the ones that may be used and save.', 'vars' => \$server['alias']))", $tools, 'no tool on, administrator');
	pg_assert_contains(": lang(array('string' => 'No tool of @{var:1} is on yet, so it is not offered after @ in the channels and cannot be asked. An administrator can turn on the ones that may be used.', 'vars' => \$server['alias']))", $tools, 'no tool on, designer and manager');
	pg_assert_contains("\$none_on = \$admin\n        ? lang(array('string' => 'No tool of @{var:1}", $tools, 'the no-tool-on sentence follows the role');
}

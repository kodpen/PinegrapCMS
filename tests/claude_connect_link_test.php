<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_oauth_claude_connect_link() (includes/oauth/rules.php): the
 * install link that opens Claude's "Add custom connector" dialog filled in
 * with the site's name and its MCP server address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_OAUTH_ENTRY')) {
	define('PG_OAUTH_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/oauth/rules.php');

function claude_connect_link_prefix()
{
	return 'https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=';
}

function test_claude_connect_link_plain_site()
{
	pg_assert_same(
		claude_connect_link_prefix() . 'Shop&connectorUrl=https%3A%2F%2Fshop.example.com%2Fpinegrap%2Fmcp.php',
		pg_oauth_claude_connect_link('https://shop.example.com/pinegrap/mcp.php', 'Shop'),
		'name and address encoded'
	);
}

function test_claude_connect_link_keeps_path_and_port()
{
	pg_assert_same(
		claude_connect_link_prefix() . 'Shop&connectorUrl=https%3A%2F%2Fexample.com%3A8443%2Fsub%2Fdir%2Fpinegrap%2Fmcp.php',
		pg_oauth_claude_connect_link('https://example.com:8443/sub/dir/pinegrap/mcp.php', 'Shop'),
		'a site in a folder on its own port'
	);
}

// The address is given exactly as the site names it in its answers, since the
// client compares the two: an uppercase host stays, an IDN host is encoded
// byte by byte.
function test_claude_connect_link_uppercase_and_idn_host()
{
	pg_assert_same(
		claude_connect_link_prefix() . 'Shop&connectorUrl=https%3A%2F%2FShop.Example.COM%2Fmcp.php',
		pg_oauth_claude_connect_link('https://Shop.Example.COM/mcp.php', 'Shop'),
		'uppercase host kept'
	);

	pg_assert_same(
		claude_connect_link_prefix() . '%C3%87i%C3%A7ek&connectorUrl=https%3A%2F%2F%C3%A7i%C3%A7ek.com.tr%2Fmcp.php',
		pg_oauth_claude_connect_link('https://çiçek.com.tr/mcp.php', 'Çiçek'),
		'IDN host and name encoded'
	);
}

function test_claude_connect_link_encodes_name_characters()
{
	$link = pg_oauth_claude_connect_link('https://example.com/mcp.php', 'Tom & Jerry\'s "Shop" #1');

	pg_assert_same(
		claude_connect_link_prefix() . 'Tom%20%26%20Jerry%27s%20%22Shop%22%20%231&connectorUrl=https%3A%2F%2Fexample.com%2Fmcp.php',
		$link,
		'&, #, quotes and spaces encoded'
	);
}

function test_claude_connect_link_drops_query_and_fragment()
{
	pg_assert_same(
		claude_connect_link_prefix() . 'Shop&connectorUrl=https%3A%2F%2Fexample.com%2Fmcp.php',
		pg_oauth_claude_connect_link('https://example.com/mcp.php?x=1&connectorName=Evil#frag', 'Shop'),
		'the address\'s own query and fragment kept out'
	);
}

function test_claude_connect_link_name_falls_back()
{
	pg_assert_same(
		claude_connect_link_prefix() . 'Pinegrap&connectorUrl=https%3A%2F%2Fexample.com%2Fmcp.php',
		pg_oauth_claude_connect_link('https://example.com/mcp.php', "  \n "),
		'an empty name becomes Pinegrap'
	);
}

function test_claude_connect_link_refuses_http_and_junk()
{
	pg_assert_same('', pg_oauth_claude_connect_link('http://example.com/mcp.php', 'Shop'), 'plain http');
	pg_assert_same('', pg_oauth_claude_connect_link('HTTP://example.com/mcp.php', 'Shop'), 'plain http in capitals');
	pg_assert_same('', pg_oauth_claude_connect_link('https:///mcp.php', 'Shop'), 'no host');
	pg_assert_same('', pg_oauth_claude_connect_link('', 'Shop'), 'empty');
	pg_assert_same('', pg_oauth_claude_connect_link(null, 'Shop'), 'not a string');
	pg_assert_same('', pg_oauth_claude_connect_link('https://user:pw@example.com/mcp.php', 'Shop'), 'credentials in the address');
}

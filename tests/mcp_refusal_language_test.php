<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the MCP server's own refusal sentences (includes/mcp/tools.php,
 * includes/mcp/protocol.php) are plain English on a site in Turkish, while
 * the API door's same sentence stays translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Neither MCP file passes a sentence of its own through lang().
function test_mcp_refusal_language_no_lang_call_in_mcp_files()
{
	foreach (array('includes/mcp/tools.php', 'includes/mcp/protocol.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match_all('/\blang\s*\(/', $source), $file . ' calls lang()');
	}

	$protocol = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');

	foreach (array(
		"api_fail(503, 'not_installed', 'The API is not installed on this site yet. Run the software upgrade to finish it.')",
		"api_fail(503, 'api_disabled', 'The API is switched off for this site.')",
		"api_fail(403, 'https_required', 'This API requires a secure (https) connection.')",
		"api_fail(403, 'address_blocked', 'We are currently unable to fulfill your request.')",
	) as $line) {
		pg_assert_contains($line, $protocol, 'the gate refuses in English');
	}
}

// Runs the compact list's refusals in a PHP process of its own whose site
// speaks Turkish (the runner's lang() speaks English). Null when PHP cannot
// be started again.
function mcp_refusal_language_turkish_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmcplang');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('SOFTWARE_LANGUAGE', 'tr');
define('PG_MCP_ENTRY', true);
define('PG_API_PANEL', true);
define('PG_INIT_LOADED', true);
require $pg_dir . '/functions.php';
require_once($pg_dir . '/includes/fn/agent_guide.php');
require_once($pg_dir . '/includes/oauth/server.php');
require_once($pg_dir . '/includes/api/openapi.php');
require_once($pg_dir . '/includes/api/dry_run.php');
require_once($pg_dir . '/includes/api/scopes.php');
require_once($pg_dir . '/includes/api/schema.php');
require_once($pg_dir . '/includes/mcp/protocol.php');
require_once($pg_dir . '/includes/mcp/tools.php');

$routes = array();

foreach (api_schema() as $route) {
	if (isset($route['id'])) {
		$routes[str_replace('.', '_', $route['id'])] = $route;
	}
}

$message = function ($answer) {
	return isset($answer['refuse'][2]) ? $answer['refuse'][2] : null;
};

echo json_encode(array(
	'api_door' => lang('The API is switched off for this site.'),
	'find_none' => $message(pg_mcp_compact_target($routes, 'tools_find', array())),
	'find_area' => $message(pg_mcp_compact_target($routes, 'tools_find', array('area' => 'nowhere'))),
	'nameless' => $message(pg_mcp_compact_target($routes, 'tools_read', array())),
	'no_tool' => $message(pg_mcp_compact_target(array(), 'tools_read', array('name' => 'products_list'))),
));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['api_door'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// On a site in Turkish the API door's sentence is Turkish, and the compact
// list's own refusals are the English sentences, word for word.
function test_mcp_refusal_language_english_on_a_turkish_site()
{
	$result = mcp_refusal_language_turkish_run();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), isset($result['error']) ? $result['error'] : '');

	pg_assert_true($result['api_door'] !== 'The API is switched off for this site.', 'the API door still speaks the site\'s language');

	pg_assert_same('Give words, an area or both.', $result['find_none']);
	pg_assert_same('area must be one of the areas tools_find lists.', $result['find_area']);
	pg_assert_same('name is required: the tool\'s name, as tools_find answers it.', $result['nameless']);
	pg_assert_same('There is no tool of that name. Find one with tools_find.', $result['no_tool']);
}

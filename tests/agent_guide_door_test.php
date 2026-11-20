<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's door to the guide for AI agents: the address the
 * panel head's rel="help" link and the account menu's "AI agent guide" row
 * print (pg_agent_guide_url(), includes/fn/output.php), and the router line
 * that sends llms.txt in the software folder to agent_guide.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PATH')) {
	define('PATH', '/');
}

// SOFTWARE_DIRECTORY is left to the tests that define it for their own
// expectations (pinegrap or software): defining it here would decide theirs.
function test_agent_guide_url_list_and_topic()
{
	if (!defined('SOFTWARE_DIRECTORY')) {
		pg_assert_true(function_exists('pg_agent_guide_url'));
		return;
	}

	$base = PATH . SOFTWARE_DIRECTORY . '/agent_guide.php';

	pg_assert_same($base, pg_agent_guide_url());
	pg_assert_same($base . '?topic=designer', pg_agent_guide_url('designer'));
	pg_assert_same($base . '?topic=a%26b', pg_agent_guide_url('a&b'), 'the topic is encoded');
}

function test_agent_guide_door_files()
{
	$root = dirname(__DIR__) . '/pinegrap';

	$router = (string)file_get_contents($root . '/router.php');
	pg_assert_true(strpos($router, "mb_strtolower(SOFTWARE_DIRECTORY) . '/llms.txt'") !== false, 'llms.txt in the software folder is routed');
	pg_assert_true(strpos($router, "require(dirname(__FILE__) . '/agent_guide.php');") !== false, 'to agent_guide.php');

	$door = (string)file_get_contents($root . '/agent_guide.php');
	pg_assert_true(strpos($door, '$user = validate_user();') !== false, 'the door asks for a panel session');
	pg_assert_true(strpos($door, "Content-Type: text/markdown") !== false, 'it answers Markdown');

	$output = (string)file_get_contents($root . '/includes/fn/output.php');
	pg_assert_true(strpos($output, '<link rel="help" type="text/markdown"') !== false, 'the panel head links the guide');

	$designer = (string)file_get_contents($root . '/includes/designer_screen.php');
	pg_assert_true(strpos($designer, "'agent_guide'  => 'designer'") !== false, 'the Visual Page Editor links the designer topic');
}

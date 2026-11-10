<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the word the Workspace calls an agent by where it has no name or
 * handle to show: the @ picker and the tag of an agent with no name, the
 * line under a message whose agent is gone and the agent routes' not-found
 * text. They ask for "The agent" ("Ajan" in Turkish), never for a key of the
 * real-estate template's estate agents ("Danışman").
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/language_map.php');

// The two words in Turkish: the Workspace's agent is "Ajan", and "Agent"
// stays the real-estate template's estate agent, which still asks for it.
function test_workspace_agent_word_is_ajan_in_turkish()
{
	$turkish = pg_test_language_map('tr');

	pg_assert_same('Ajan', $turkish['The agent'] ?? null, 'the Workspace\'s agent');
	pg_assert_same('Danışman', $turkish['Agent'] ?? null, 'the template\'s estate agent keeps its wording');
	pg_assert_contains("lang('Agent')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-realestate/requests.php'), 'and the template still asks for it');
}

// An agent with no name is described by the Workspace's word in the @ picker
// and where its tag is drawn.
function test_workspace_agent_word_for_an_agent_with_no_name()
{
	$entries = ws_agent_picker_entries(array(array('handle' => 'ci', 'name' => '', 'app_id' => 3)));

	pg_assert_same('@ci', $entries[0]['label'] ?? null);
	pg_assert_same(lang('The agent'), $entries[0]['meta'] ?? null, 'the @ picker');

	$tag = ws_app_tag(3, null, 9, 5, array(), array(3 => array('handle' => 'ci', 'name' => ' ')));

	pg_assert_same('@ci', $tag['label']);
	pg_assert_same(lang('The agent'), $tag['meta'], 'its tag');
}

// The line under a message names an agent whose row is gone by the
// Workspace's word. A request whose value names no registry agent takes the
// same way out and reads no table, so it can be asked here.
function test_workspace_agent_word_for_an_agent_that_is_gone()
{
	$row = array('id' => 5, 'agent' => 'app:0', 'error' => '');

	pg_assert_same(
		lang(array('string' => '{var:1} answered', 'vars' => lang('The agent'))),
		ws_agent_request_state(array('status' => 'answered') + $row)['label'],
		'answered');
	pg_assert_same(
		lang(array('string' => 'Waiting for {var:1}', 'vars' => lang('The agent'))),
		ws_agent_request_state(array('status' => 'queued') + $row)['label'],
		'waiting');
}

// The agent routes say what was not found with it: an agent that is not
// there, and a row that cannot be read back after it was written.
function test_workspace_agent_word_in_the_routes_not_found_text()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_agents.php');

	pg_assert_same(2, substr_count($source, "api_fail_not_found(lang('The agent'))"));
}

// No Workspace file asks for a key of the template's estate agents, in
// lang()'s plain form, its array form or a text handed to the scripts.
// flows.php is not scanned: the Agent field of its Ask an agent box
// (fl_agent) still asks for the template's key.
function test_workspace_agent_word_is_never_the_estate_agents_key()
{
	$found = array();

	foreach ((array) glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $path) {
		if (basename($path) === 'flows.php') {
			continue;
		}

		if (preg_match_all("/(?:\\b(?:lang|ws_js_template)\\(\\s*|'string'\\s*=>\\s*)'(Agent|Agents|Agent Card|Our Agents)'/", (string) file_get_contents($path), $matches)) {
			$found[] = basename($path) . ': ' . implode(', ', array_unique($matches[1]));
		}
	}

	pg_assert_same(array(), $found);
}

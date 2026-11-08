<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP guidance a client gets before it writes a record on a
 * form (includes/mcp/tools.php, resources.php, prompts.php): the extra lines
 * on the form write tools, the pinegrap://forms/{id}/fields resource over
 * GET /forms/{id}/fields, and the blog_post prompt - when it is offered, the
 * arguments it takes and the text it is built from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/prompts.php');

// The core routes a caller holding these scopes may use, by tool name.
function mcp_blog_post_test_routes($scopes)
{
	return pg_mcp_routes_for(api_schema(), $scopes);
}

function mcp_blog_post_test_route($route_id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $route_id) {
			return $route;
		}
	}

	return null;
}

function test_mcp_blog_post_form_tools_say_to_read_the_fields()
{
	foreach (array('forms.submission_create', 'forms.submission_update') as $route_id) {
		$guidance = pg_mcp_tool_guidance($route_id);

		pg_assert_contains('forms_fields', $guidance, $route_id . ': names the fields tool');
		pg_assert_contains('pinegrap://forms/{id}/fields', $guidance, $route_id . ': and the resource');
		pg_assert_contains('html_allowed', $guidance, $route_id . ': what a rich-text field keeps');
		pg_assert_contains('max_length', $guidance, $route_id . ': and how long');
		pg_assert_contains('dry_run', $guidance, $route_id . ': a rehearsal first');

		$tool = pg_mcp_tool_from_route(mcp_blog_post_test_route($route_id));

		pg_assert_contains($guidance, $tool['description'], $route_id . ': on the tool description');
		pg_assert_contains(mcp_blog_post_test_route($route_id)['description'], $tool['description'], $route_id . ': after the route\'s own');
	}

	pg_assert_contains('forms_fields', pg_mcp_tool_guidance('forms.get'));

	// The tools named are real tools.
	pg_assert_same('forms_fields', pg_mcp_tool_name('forms.fields'));
	pg_assert_true(mcp_blog_post_test_route('forms.fields') !== null, 'forms.fields exists');

	pg_assert_same('', pg_mcp_tool_guidance('products.list'), 'no guidance elsewhere');
	pg_assert_same('', pg_mcp_tool_guidance(''));
}

function test_mcp_blog_post_fields_resource()
{
	pg_assert_same(array('kind' => 'form_fields', 'arguments' => array('id' => '12')), pg_mcp_resource_parse('pinegrap://forms/12/fields'));
	pg_assert_same(array('kind' => 'form_fields', 'arguments' => array('id' => '12')), pg_mcp_resource_parse('PINEGRAP://FORMS/12/fields'), 'scheme and host in any case');
	pg_assert_same(null, pg_mcp_resource_parse('pinegrap://forms/12/Fields'), 'the path as written');
	pg_assert_same(null, pg_mcp_resource_parse('pinegrap://forms/0/fields'));
	pg_assert_same(null, pg_mcp_resource_parse('pinegrap://forms/12'));
	pg_assert_same('pinegrap://forms/7/fields', pg_mcp_resource_uri('form_fields', array('id' => '7')));

	$readers = mcp_blog_post_test_routes(array('forms:read'));

	pg_assert_true(pg_mcp_resource_route('form_fields', $readers) !== null, 'offered with forms:read');
	pg_assert_contains('pinegrap://forms/{id}/fields', array_column(pg_mcp_resource_templates_list($readers), 'uriTemplate'));
	pg_assert_same(null, pg_mcp_resource_route('form_fields', mcp_blog_post_test_routes(array('products:read'))), 'not without it');

	$target = pg_mcp_resource_target(array('uri' => 'pinegrap://forms/12/fields'), $readers, true);

	pg_assert_same('forms.fields', $target['route']['id'], 'read through GET /forms/{id}/fields');
	pg_assert_same(array('id' => '12'), $target['arguments']);
}

function test_mcp_blog_post_is_offered_with_what_it_uses()
{
	$writer = mcp_blog_post_test_routes(array('forms:read', 'forms:write'));

	pg_assert_true(pg_mcp_prompt_is_offered('blog_post', $writer), 'forms read and write');
	pg_assert_contains('blog_post', array_column(pg_mcp_prompts_list($writer), 'name'));
	pg_assert_false(pg_mcp_prompt_is_offered('blog_post', mcp_blog_post_test_routes(array('forms:read'))), 'read only: nothing to send with');
	pg_assert_false(pg_mcp_prompt_is_offered('blog_post', mcp_blog_post_test_routes(array())), 'nothing at all');

	$schema = array();

	foreach (api_schema() as $route) {
		$schema[] = ($route['id'] === 'forms.submission_create') ? array_merge($route, array('mcp' => false)) : $route;
	}

	pg_assert_false(pg_mcp_prompt_is_offered('blog_post', pg_mcp_routes_for($schema, array('forms:read', 'forms:write'))), 'not when the create route is no tool');
}

function test_mcp_blog_post_arguments()
{
	pg_assert_same(array('values' => array('form_id' => '12'), 'error' => ''), pg_mcp_prompt_arguments('blog_post', array('form_id' => '12')));
	pg_assert_same(array('values' => array('form_id' => '12', 'topic' => 'Spring opening hours'), 'error' => ''), pg_mcp_prompt_arguments('blog_post', array('form_id' => 12, 'topic' => '  Spring opening hours ')));
	pg_assert_same('Spring opening hours', pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'topic' => "Spring\nopening\thours"))['values']['topic'], 'one line');
	pg_assert_same(array('values' => array('form_id' => '12'), 'error' => ''), pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'topic' => '')), 'an empty topic is left out');

	pg_assert_same('Missing required argument: form_id', pg_mcp_prompt_arguments('blog_post', array('topic' => 'x'))['error']);
	pg_assert_same('Invalid argument: form_id must be a positive whole number.', pg_mcp_prompt_arguments('blog_post', array('form_id' => 'abc'))['error']);

	$limit = 'Invalid argument: topic must be text of 1 to ' . pg_mcp_prompt_text_max() . ' characters.';

	pg_assert_same($limit, pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'topic' => str_repeat('a', pg_mcp_prompt_text_max() + 1)))['error'], 'too long is refused, not cut');
	pg_assert_same('', pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'topic' => str_repeat('ş', pg_mcp_prompt_text_max())))['error'], 'counted in characters');
	pg_assert_same($limit, pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'topic' => array('x')))['error'], 'a list is no text');
	pg_assert_same($limit, pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'topic' => " \n "))['error'], 'only blanks');
	pg_assert_same('Unknown argument: title', pg_mcp_prompt_arguments('blog_post', array('form_id' => '12', 'title' => 'x'))['error']);
}

function test_mcp_blog_post_text()
{
	$routes = mcp_blog_post_test_routes(array('forms:read', 'forms:write', 'files:write'));
	$result = pg_mcp_prompt_result('blog_post', array('form_id' => '12', 'topic' => 'Spring opening hours'), 'Acme Store', $routes, null);
	$text = $result['messages'][0]['content']['text'];

	pg_assert_same(1, count($result['messages']), 'text only when the fields are not embedded');
	pg_assert_contains('form 12 on Acme Store', $text);
	pg_assert_contains('Spring opening hours', $text, 'the topic');
	pg_assert_contains('pinegrap://forms/12/fields', $text, 'names the fields resource');
	pg_assert_contains('forms_fields (id 12)', $text, 'and the tool that reads the same');
	pg_assert_contains('forms_get (id 12)', $text, 'the form itself');

	foreach (array('is_collection', 'record_url_pattern', 'framework', 'rich_text', 'html_allowed', 'max_length', 'class attribute', 'forms_submission_create', 'dry_run true', 'files_create', '<img src', 'its url') as $needle) {
		pg_assert_contains($needle, $text, $needle);
	}

	foreach (pg_ai_prompt_rules('assistant', '- ') as $line) {
		pg_assert_contains($line, $text, 'the rules every prompt carries');
	}

	$no_files = pg_mcp_prompt_result('blog_post', array('form_id' => '12'), 'Acme Store', mcp_blog_post_test_routes(array('forms:read', 'forms:write')), null)['messages'][0]['content']['text'];

	pg_assert_false(strpos($no_files, 'files_create') !== false, 'no upload tool named without files:write');
	pg_assert_contains('cannot upload files', $no_files);
	pg_assert_false(strpos($no_files, ' about: ') !== false, 'no topic line without a topic');

	$item = array('uri' => 'pinegrap://forms/12/fields', 'mimeType' => 'application/json', 'text' => '{"data":[]}');
	$embedded = pg_mcp_prompt_result('blog_post', array('form_id' => '12'), 'Acme Store', $routes, $item);

	pg_assert_same(2, count($embedded['messages']), 'the fields as a second message');
	pg_assert_same(array('type' => 'resource', 'resource' => $item), $embedded['messages'][1]['content']);
	pg_assert_contains('attached below as the resource pinegrap://forms/12/fields', $embedded['messages'][0]['content']['text']);
	pg_assert_same(pg_mcp_prompt_kinds()['blog_post']['description'], $embedded['description']);
}

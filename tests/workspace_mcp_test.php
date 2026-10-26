<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of other services' MCP servers in the Workspace
 * (includes/workspace/mcp.php, mcp_ask.php): which tools may be turned on
 * with one connection for the whole site, which ask first on a server people
 * sign in to, which are on, the names the model knows them by, aliases, the
 * credential headers, the arguments a waiting call shows, the form a
 * tool that reads is run with (mcp_form.php), and the tool that takes the
 * messages of a server that is an agent itself, what it is sent for a
 * workspace task, how often its tasks are asked about, who may stop a
 * request to it, the conversation it keeps and its answer without the head
 * line (mcp_agent.php). Also the tools turned on by a server's first read and
 * by declaring the unsaid ones as reading only, the fields the settings form
 * keeps after a failed check, and when a request to an alias that could not
 * reach Pinegrap AI posts the form-run link (ai.php). Also the ready-made
 * connectors, the alias an address suggests, which catalogue entry a server
 * already is, and how the probe of an address decides the sign-in
 * (mcp_catalogue.php).
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');

// A tool as the client keeps it.
function workspace_mcp_test_tool($name, $annotations = array())
{
	return pg_mcp_client_tool_normalise(array('name' => $name, 'description' => 'About ' . $name, 'annotations' => $annotations), '{"type":"object","properties":{}}');
}

// A server row with these tools and rules.
function workspace_mcp_test_server($tools, $rules, $auth_kind = 'none')
{
	return array(
		'id'         => 1,
		'alias'      => 'docs',
		'status'     => 'active',
		'auth_kind'  => $auth_kind,
		'tools'      => json_encode(array_values($tools)),
		'tool_rules' => json_encode((object) $rules),
	);
}

function test_workspace_mcp_only_reading_tools_can_be_turned_on()
{
	pg_assert_same('read', ws_mcp_tool_can_run(workspace_mcp_test_tool('a', array('readOnlyHint' => true))));
	pg_assert_same('declare', ws_mcp_tool_can_run(workspace_mcp_test_tool('b')));
	pg_assert_same('', ws_mcp_tool_can_run(workspace_mcp_test_tool('c', array('readOnlyHint' => false))));
	pg_assert_same('', ws_mcp_tool_can_run(workspace_mcp_test_tool('d', array('destructiveHint' => true))));

	// A hint that only means something for a tool that writes says it writes.
	pg_assert_same('', ws_mcp_tool_can_run(workspace_mcp_test_tool('e', array('idempotentHint' => true))));

	// A hint that is not a boolean counts as not given.
	pg_assert_same('declare', ws_mcp_tool_can_run(workspace_mcp_test_tool('f', array('readOnlyHint' => 'yes'))));
}

function test_workspace_mcp_rules_count_only_for_tools_that_may_run()
{
	$server = workspace_mcp_test_server(array(
		workspace_mcp_test_tool('reads', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('unsaid'),
		workspace_mcp_test_tool('declared'),
		workspace_mcp_test_tool('writes', array('readOnlyHint' => false)),
	), array(
		'reads'    => 'auto',
		'unsaid'   => 'auto',
		'declared' => 'auto_declared',
		'writes'   => 'auto_declared',
		'gone'     => 'auto',
		'odd'      => 'always',
	));

	pg_assert_same(array('reads', 'declared'), array_keys(ws_mcp_tools_on($server)));
}

function test_workspace_mcp_tools_that_write_ask_first_only_where_people_sign_in()
{
	$tools = array(
		workspace_mcp_test_tool('reads', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('unsaid'),
		workspace_mcp_test_tool('writes', array('readOnlyHint' => false)),
		workspace_mcp_test_tool('deletes', array('destructiveHint' => true)),
	);
	$rules = array('reads' => 'auto', 'unsaid' => 'approve', 'writes' => 'approve', 'deletes' => 'approve');

	pg_assert_same(array('reads'), array_keys(ws_mcp_tools_on(workspace_mcp_test_server($tools, $rules, 'bearer'))), 'one connection for everybody: approve counts for nothing');

	$on = ws_mcp_tools_on(workspace_mcp_test_server($tools, $rules, 'oauth'));

	pg_assert_same(array('reads', 'unsaid', 'writes', 'deletes'), array_keys($on), 'a server people sign in to');
	pg_assert_true(empty($on['reads']['approve']), 'a tool that reads runs at once');
	pg_assert_true(!empty($on['writes']['approve']) && !empty($on['deletes']['approve']) && !empty($on['unsaid']['approve']), 'the others wait for approval');

	// The model is offered them, under their own names.
	pg_assert_same(array('deletes', 'reads', 'unsaid', 'writes'), array_keys(ws_mcp_ask_functions(workspace_mcp_test_server($tools, $rules, 'oauth'))));
}

function test_workspace_mcp_rule_from_the_settings_form()
{
	$oauth = workspace_mcp_test_server(array(), array(), 'oauth');
	$shared = workspace_mcp_test_server(array(), array(), 'basic');
	$reads = workspace_mcp_test_tool('a', array('readOnlyHint' => true));
	$unsaid = workspace_mcp_test_tool('b');
	$writes = workspace_mcp_test_tool('c', array('readOnlyHint' => false));

	pg_assert_same('', ws_mcp_tool_rule_for($oauth, $reads, ''), 'off');
	pg_assert_same('', ws_mcp_tool_rule_for($oauth, $writes, '0'), 'off');
	pg_assert_same('auto', ws_mcp_tool_rule_for($shared, $reads, '1'));
	pg_assert_same('auto', ws_mcp_tool_rule_for($oauth, $reads, 'approve'), 'a tool that reads never asks');
	pg_assert_same('auto_declared', ws_mcp_tool_rule_for($shared, $unsaid, '1'));
	pg_assert_same('auto_declared', ws_mcp_tool_rule_for($shared, $unsaid, 'approve'), 'one connection for everybody cannot ask');
	pg_assert_same('auto_declared', ws_mcp_tool_rule_for($oauth, $unsaid, 'auto_declared'));
	pg_assert_same('approve', ws_mcp_tool_rule_for($oauth, $unsaid, 'approve'));
	pg_assert_same('', ws_mcp_tool_rule_for($shared, $writes, 'approve'), 'a tool that writes stays off with one connection');
	pg_assert_same('approve', ws_mcp_tool_rule_for($oauth, $writes, '1'), 'a tool that writes always asks');
	pg_assert_same('approve', ws_mcp_tool_rule_for($oauth, $writes, 'auto_declared'), 'it cannot be declared to read');
}

function test_workspace_mcp_arguments_as_the_card_prints_them()
{
	pg_assert_same('octo/repo', ws_mcp_argument_show(' octo/repo '));
	pg_assert_same('42', ws_mcp_argument_show(42));
	pg_assert_same(lang('Yes'), ws_mcp_argument_show(true));
	pg_assert_same(lang('(empty)'), ws_mcp_argument_show(null));
	pg_assert_same(lang('(empty)'), ws_mcp_argument_show(''));
	pg_assert_same('{"labels":["bug"]}', ws_mcp_argument_show(array('labels' => array('bug'))));
	pg_assert_same(300, mb_strlen(ws_mcp_argument_show(str_repeat('ğ', 400))), 'cut to 300 characters');
}

function test_workspace_mcp_function_names_suit_the_chat_api()
{
	$tools = array();

	foreach (array('repos/search.issues', 'repos_search_issues', 'answer', 'fail', str_repeat('x', 100)) as $name) {
		$tools[] = workspace_mcp_test_tool($name, array('readOnlyHint' => true));
	}

	$rules = array_fill_keys(array_column($tools, 'name'), 'auto');
	$functions = ws_mcp_ask_functions(workspace_mcp_test_server($tools, $rules));

	foreach (array_keys($functions) as $function) {
		pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/', $function), $function . ' is a valid function name');
		pg_assert_true(!in_array($function, array('answer', 'fail'), true), $function . ' is not answer or fail');
	}

	pg_assert_same(5, count($functions));
	pg_assert_same('answer', $functions['answer_2']['name']);
	pg_assert_same('fail', $functions['fail_2']['name']);
	pg_assert_same('repos/search.issues', $functions['repos_search_issues']['name']);
	pg_assert_same('repos_search_issues', $functions['repos_search_issues_2']['name']);
}

function test_workspace_mcp_model_is_offered_a_limited_number_of_tools()
{
	$tools = array();

	for ($i = 0; $i < WS_MCP_ASK_MAX_TOOLS + 5; $i++) {
		$tools[] = workspace_mcp_test_tool(sprintf('tool_%03d', $i), array('readOnlyHint' => true));
	}

	$functions = ws_mcp_ask_functions(workspace_mcp_test_server($tools, array_fill_keys(array_column($tools, 'name'), 'auto')));

	pg_assert_same(WS_MCP_ASK_MAX_TOOLS, count($functions));
	pg_assert_same('tool_000', array_keys($functions)[0]);
}

function test_workspace_mcp_alias_shapes()
{
	pg_assert_same('github', ws_mcp_alias_clean(' @GitHub '));
	pg_assert_same('', ws_mcp_alias_shape_problem('github'));
	pg_assert_same('', ws_mcp_alias_shape_problem('my-docs_2'));
	pg_assert_true(ws_mcp_alias_shape_problem('g') !== '', 'one letter is too short');
	pg_assert_true(ws_mcp_alias_shape_problem('2docs') !== '', 'a digit cannot start it');
	pg_assert_true(ws_mcp_alias_shape_problem('döküman') !== '', 'no accented letters');
	pg_assert_true(ws_mcp_alias_shape_problem('ai') !== '', 'ai is the assistant');
	pg_assert_true(ws_mcp_alias_shape_problem('herkes') !== '', 'herkes is everybody');
}

function test_workspace_mcp_alias_named_in_a_text()
{
	$servers = array(
		array('id' => 3, 'alias' => 'github'),
		array('id' => 5, 'alias' => 'docs'),
	);

	pg_assert_same(3, ws_mcp_alias_named('@github which pull requests are open?', $servers)['id']);
	pg_assert_same(5, ws_mcp_alias_named("Look it up\n@Docs please", $servers)['id'], 'any case, on a line of its own');
	pg_assert_same(5, ws_mcp_alias_named('(@docs) and @github', $servers)['id'], 'the first one written');
	pg_assert_same(3, ws_mcp_alias_named('@ai and @github, both', $servers)['id'], 'an alias next to Pinegrap AI');
	pg_assert_same(null, ws_mcp_alias_named('erdal@github.com wrote', $servers), 'an e-mail address is not an alias');
	pg_assert_same(null, ws_mcp_alias_named('@github-enterprise is another one', $servers), 'a longer word is another alias');
	pg_assert_same(null, ws_mcp_alias_named('@gitlab', $servers), 'only the servers there are');
	pg_assert_same(null, ws_mcp_alias_named('@github', array()));
	pg_assert_same(null, ws_mcp_alias_named(null, $servers));
}

function test_workspace_mcp_credential_headers()
{
	pg_assert_same(array('Authorization: Bearer abc'), ws_mcp_auth_headers('bearer', array('token' => 'abc')));
	pg_assert_same(array('X-Api-Key: k1'), ws_mcp_auth_headers('header', array('name' => 'X-Api-Key', 'value' => 'k1')));
	pg_assert_same(array('Authorization: Basic ' . base64_encode('u:p')), ws_mcp_auth_headers('basic', array('user' => 'u', 'password' => 'p')));
	pg_assert_same(array(), ws_mcp_auth_headers('bearer', array()));
	pg_assert_same(array(), ws_mcp_auth_headers('none', array('token' => 'abc')));
}

function test_workspace_mcp_header_names()
{
	pg_assert_same('', ws_mcp_header_name_problem('X-Api-Key'));
	pg_assert_same('', ws_mcp_header_name_problem('Authorization'));
	pg_assert_true(ws_mcp_header_name_problem('Content-Type') !== '', 'the connection sets Content-Type');
	pg_assert_true(ws_mcp_header_name_problem('Mcp-Session-Id') !== '', 'the connection sets Mcp-*');
	pg_assert_true(ws_mcp_header_name_problem("X-Key\r\nHost") !== '', 'no line breaks');
}

function test_workspace_mcp_oauth_provider_definition()
{
	pg_assert_true(isset(ws_mcp_auth_kinds()['oauth']), 'OAuth is a way to sign in');

	$server = array('id' => 7, 'alias' => 'github', 'auth_kind' => 'oauth');

	pg_assert_same('mcp_7', ws_mcp_connect_key($server));
	pg_assert_same(null, ws_mcp_connect_definition($server, array()), 'nothing found yet');

	$provider = ws_mcp_connect_definition($server, array(
		'authorize_url' => 'https://github.com/login/oauth/authorize',
		'token_url'     => 'https://github.com/login/oauth/access_token',
		'issuer'        => 'https://github.com/login/oauth',
		'iss_param'     => false,
		'resource'      => 'https://api.githubcopilot.com/mcp',
		'scopes'        => array('repo', 'repo', 'read:org'),
		'client_auth'   => 'basic',
	));

	pg_assert_same('@github', $provider['label']);
	pg_assert_same('@github', $provider['account_label'], 'the connection is named after the alias');
	pg_assert_false($provider['identity'], 'the service names no account');
	pg_assert_true($provider['refresh_optional'], 'a refresh token is not required');
	pg_assert_same(WS_MCP_TOKEN_TTL, $provider['default_ttl'], 'a token without an end lasts');
	pg_assert_false($provider['nonce'], 'no nonce');
	pg_assert_same(array('resource' => 'https://api.githubcopilot.com/mcp'), $provider['authorize_params'], 'RFC 8707 on the authorization');
	pg_assert_same(array('resource' => 'https://api.githubcopilot.com/mcp'), $provider['token_params'], 'and on every token request');
	pg_assert_same(array('repo', 'read:org'), $provider['identity_scopes'], 'the scopes the server asks for');
	pg_assert_same(array('https://github.com/login/oauth'), $provider['issuers']);
	pg_assert_false($provider['iss_required']);
	pg_assert_same('basic', $provider['client_auth']);
	pg_assert_same('', $provider['revoke_url'], 'no revocation endpoint');

	$bare = ws_mcp_connect_definition($server, array('authorize_url' => 'https://a/authorize', 'token_url' => 'https://a/token'));

	pg_assert_same(array(), $bare['issuers'], 'no issuer known');
	pg_assert_same(array(), $bare['authorize_params'], 'no resource known');
	pg_assert_same('post', $bare['client_auth'], 'the secret in the body by default');

	pg_assert_same(null, ws_mcp_connect_provider('mcp_x'), 'not a server id');
	pg_assert_same(null, ws_mcp_connect_provider('mcp_0'), 'no server 0');
}

function test_workspace_mcp_oauth_registration_order()
{
	$found = array('cimd' => true, 'registration_url' => 'https://auth.example.com/register');

	pg_assert_same('manual', ws_mcp_oauth_registration($found, ' Iv1.abc ', true), 'the administrator\'s application first');
	pg_assert_same('cimd', ws_mcp_oauth_registration($found, '', true), 'then the metadata document');
	pg_assert_same('dcr', ws_mcp_oauth_registration($found, '', false), 'a site not on https cannot be fetched');
	pg_assert_same('dcr', ws_mcp_oauth_registration(array('cimd' => false, 'registration_url' => 'https://a/register'), '', true), 'then a registration');
	pg_assert_same('', ws_mcp_oauth_registration(array('cimd' => false, 'registration_url' => ''), '', true), 'no way');
}

function test_workspace_mcp_oauth_error_text()
{
	$oauth = array('alias' => 'docs', 'auth_kind' => 'oauth');

	pg_assert_contains('@docs', ws_mcp_error_text($oauth, 401, 'refused'), 'a refused token asks for a new connection');
	pg_assert_same('refused', ws_mcp_error_text($oauth, 500, 'refused'), 'another failure is said as it is');
	pg_assert_same('refused', ws_mcp_error_text(array('alias' => 'docs', 'auth_kind' => 'bearer'), 401, 'refused'), 'a bearer token is the administrator\'s to fix');

	pg_assert_contains('@docs', ws_mcp_error_text($oauth, 401, 'refused', true), 'a person\'s own account');
	pg_assert_true(ws_mcp_error_text($oauth, 401, 'refused', true) !== ws_mcp_error_text($oauth, 401, 'refused'), 'a person connects their own account again, not an administrator');

	$conn = array('headers' => array('x'), 'account_id' => 5);

	pg_assert_false(ws_mcp_reauthorize($conn, array('auth_kind' => 'bearer', 'id' => 1, 'alias' => 'a'), 401), 'only an OAuth server renews');
	pg_assert_false(ws_mcp_reauthorize($conn, $oauth + array('id' => 1), 500), 'only after a 401');

	$anonymous = array('headers' => array('x'));

	pg_assert_false(ws_mcp_reauthorize($anonymous, $oauth + array('id' => 1), 401), 'only a connection opened with an account');
	pg_assert_same(array('x'), $conn['headers'], 'the connection is left as it was');
}

function test_workspace_mcp_form_runs_only_tools_that_run_by_themselves()
{
	$server = workspace_mcp_test_server(array(
		workspace_mcp_test_tool('search', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('get', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('create', array('readOnlyHint' => false)),
		workspace_mcp_test_tool('off', array('readOnlyHint' => true)),
	), array('search' => 'auto', 'get' => 'auto', 'create' => 'approve'), 'oauth');

	pg_assert_same(array('get', 'search'), array_keys(ws_mcp_form_tools($server)), 'a tool that asks first is never run from a form; in name order');
	pg_assert_true(isset(ws_mcp_tools_on($server)['create']), 'the tool that asks first is on all the same');
	pg_assert_false(isset(ws_mcp_form_tools($server)['off']), 'a tool that is not turned on is not offered');
}

function test_workspace_mcp_form_query()
{
	pg_assert_same('', ws_mcp_form_query(), 'the screen itself');
	pg_assert_same('?server=3#ws-mcp-3', ws_mcp_form_query(3));
	pg_assert_same('?server=3&tool=list_issues&channel=9#ws-mcp-form', ws_mcp_form_query(3, 'list_issues', 9));
	pg_assert_same('?server=3&tool=a%2Fb#ws-mcp-form', ws_mcp_form_query(3, 'a/b'), 'the tool name is encoded');
}

function test_workspace_mcp_form_fields_from_the_schema()
{
	$fields = ws_mcp_form_fields(json_encode(array(
		'type'       => 'object',
		'properties' => array(
			'q'        => array('type' => 'string', 'title' => 'Search words', 'description' => "What to look\nfor"),
			'state'    => array('type' => 'string', 'enum' => array('open', 'closed'), 'default' => 'open'),
			'limit'    => array('type' => array('integer', 'null'), 'default' => 20),
			'ratio'    => array('type' => 'number'),
			'archived' => array('type' => 'boolean', 'default' => false),
			'labels'   => array('type' => 'array', 'items' => array('type' => 'string')),
			'ids'      => array('type' => 'array', 'items' => array('type' => 'integer')),
			'filter'   => array('type' => 'object'),
			'pairs'    => array('type' => 'array', 'items' => array('type' => 'object')),
			'anything' => array(),
			'either'   => array('anyOf' => array(array('type' => 'null'), array('type' => 'integer'))),
			'bad name' => array('type' => 'string'),
		),
		'required'   => array('q', 'ids'),
	)));

	$by = array();

	foreach ($fields as $field) {
		$by[$field['name']] = $field;
	}

	pg_assert_false(isset($by['bad name']), 'a property whose name is not a plain word is left out');
	pg_assert_same(11, count($fields));

	pg_assert_same('Search words', $by['q']['label'], 'the title is the label');
	pg_assert_same('state', $by['state']['label'], 'else the name');
	pg_assert_same('What to look for', $by['q']['description'], 'one line');
	pg_assert_true($by['q']['required']);
	pg_assert_false($by['state']['required']);

	pg_assert_same('string', $by['state']['type']);
	pg_assert_same(array('open', 'closed'), $by['state']['enum']);
	pg_assert_same('open', $by['state']['default']);
	pg_assert_same('integer', $by['limit']['type'], 'null is not the type');
	pg_assert_same('20', $by['limit']['default']);
	pg_assert_same('number', $by['ratio']['type']);
	pg_assert_same('boolean', $by['archived']['type']);
	pg_assert_same('0', $by['archived']['default']);
	pg_assert_same('list', $by['labels']['type']);
	pg_assert_same('string', $by['labels']['item_type']);
	pg_assert_same('integer', $by['ids']['item_type']);
	pg_assert_same('json', $by['filter']['type'], 'an object is typed as JSON');
	pg_assert_same('json', $by['pairs']['type'], 'and a list of objects');
	pg_assert_same('string', $by['anything']['type'], 'no type: text');
	pg_assert_same('integer', $by['either']['type'], 'from anyOf');

	pg_assert_same(array(), ws_mcp_form_fields('{"type":"object","properties":{}}'), 'a tool that takes nothing');
	pg_assert_same(array(), ws_mcp_form_fields('not json'));
}

function test_workspace_mcp_form_arguments_take_their_types()
{
	$fields = ws_mcp_form_fields(json_encode(array(
		'type'       => 'object',
		'properties' => array(
			'q'        => array('type' => 'string'),
			'state'    => array('type' => 'string', 'enum' => array('open', 'closed')),
			'size'     => array('type' => 'integer', 'enum' => array(10, 20)),
			'limit'    => array('type' => 'integer'),
			'ratio'    => array('type' => 'number'),
			'archived' => array('type' => 'boolean'),
			'labels'   => array('type' => 'array', 'items' => array('type' => 'string')),
			'ids'      => array('type' => 'array', 'items' => array('type' => 'integer')),
			'filter'   => array('type' => 'object'),
			'empty'    => array('type' => 'string'),
		),
		'required'   => array('q'),
	)));

	$out = ws_mcp_form_arguments($fields, array(
		'q'        => '  bug ',
		'state'    => 'closed',
		'size'     => '20',
		'limit'    => '5',
		'ratio'    => '1,5',
		'archived' => '0',
		'labels'   => "a\n\n b \r\nc",
		'ids'      => "1\n2",
		'filter'   => '{}',
		'empty'    => '   ',
		'unknown'  => 'x',
	));

	pg_assert_same(array(), $out['errors']);
	pg_assert_same('bug', $out['arguments']['q']);
	pg_assert_same('closed', $out['arguments']['state']);
	pg_assert_same(20, $out['arguments']['size'], 'a number from a list is a number');
	pg_assert_same(5, $out['arguments']['limit']);
	pg_assert_same(1.5, $out['arguments']['ratio'], 'a comma is a decimal point');
	pg_assert_same(false, $out['arguments']['archived']);
	pg_assert_same(array('a', 'b', 'c'), $out['arguments']['labels'], 'one per line, empty lines left out');
	pg_assert_same(array(1, 2), $out['arguments']['ids']);
	pg_assert_same('{}', json_encode($out['arguments']['filter']), 'an empty object stays an object');
	pg_assert_false(array_key_exists('empty', $out['arguments']), 'an empty field is not sent');
	pg_assert_false(array_key_exists('unknown', $out['arguments']), 'only the schema\'s fields');
	pg_assert_same('{"q":"bug","state":"closed","size":20,"limit":5,"ratio":1.5,"archived":false,"labels":["a","b","c"],"ids":[1,2],"filter":{}}', json_encode((object) $out['arguments']));

	$bad = ws_mcp_form_arguments($fields, array(
		'state'  => 'merged',
		'size'   => '15',
		'limit'  => '2.5',
		'ratio'  => 'many',
		'ids'    => "1\nx",
		'filter' => '{nope',
		'q'      => array('not', 'text'),
	));

	pg_assert_same(array('q', 'state', 'size', 'limit', 'ratio', 'ids', 'filter'), array_keys($bad['errors']), 'each field says what is wrong');
	pg_assert_same(array(), $bad['arguments'], 'nothing is sent from a field that is wrong');

	pg_assert_same(array('arguments' => array(), 'errors' => array()), ws_mcp_form_arguments(array(), array('x' => '1')), 'a tool that takes nothing');
}

function test_workspace_mcp_form_number()
{
	pg_assert_same(7, ws_mcp_form_number(' 7 ', true));
	pg_assert_same(-2, ws_mcp_form_number('-2', true));
	pg_assert_same(null, ws_mcp_form_number('7.5', true));
	pg_assert_same(7.5, ws_mcp_form_number('7,5', false));
	pg_assert_same(7, ws_mcp_form_number('7', false), 'a whole number stays whole');
	pg_assert_same(null, ws_mcp_form_number('1e5', false), 'only plain numbers');
	pg_assert_same(null, ws_mcp_form_number('', false));
}

function test_workspace_mcp_form_message_quotes_the_answer_safely()
{
	$body = ws_mcp_form_message_body('github', 'list_issues', 'Ayşe', "Issue <@12> and <#3>\n```evil```");

	pg_assert_contains('@github', $body);
	pg_assert_contains('`list_issues`', $body);
	pg_assert_contains('Ayşe', $body);
	pg_assert_contains("\n```\nIssue ‹@12> and ‹#3>\n'''evil'''\n```", $body, 'quoted as a block that cannot be closed early, nobody mentioned');

	pg_assert_true(strpos(ws_mcp_form_message_body('github', 'a`b', 'Ayşe', ''), '`ab`') !== false, 'a backtick in the name does not break the code');
	pg_assert_false(strpos(ws_mcp_form_message_body('github', 'a', 'Ayşe', ''), '```') !== false, 'no block for an empty answer');
	pg_assert_true(ws_mcp_form_message_body('github', 'a', 'Ayşe', 'x', true) !== ws_mcp_form_message_body('github', 'a', 'Ayşe', 'x'), 'an error is said');

	$long = ws_mcp_form_message_body('github', 'a', 'Ayşe', str_repeat('x', 10000));

	pg_assert_true(mb_strlen($long) <= WS_MESSAGE_MAX, 'it fits in a message');
	pg_assert_contains("…\n```", $long, 'cut with a mark');
}

// A tool with this input schema, as the client keeps it.
function workspace_mcp_test_schema_tool($name, $schema, $annotations = array())
{
	return pg_mcp_client_tool_normalise(array('name' => $name, 'annotations' => $annotations), json_encode($schema));
}

function test_workspace_mcp_agent_argument_takes_the_message()
{
	$text = array('type' => 'string');

	pg_assert_same('message', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('a', array('type' => 'object', 'properties' => array('message' => $text, 'lang' => $text), 'required' => array('message')))), 'the one required text');
	pg_assert_same('q', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('b', array('type' => 'object', 'properties' => array('q' => $text), 'required' => array('q')))), 'whatever its name');
	pg_assert_same('prompt', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('c', array('type' => 'object', 'properties' => array('style' => $text, 'prompt' => $text)))), 'nothing required: the name that says it takes a message');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('d', array('type' => 'object', 'properties' => array('count' => array('type' => 'integer'), 'cursor' => $text)))), 'nothing required and no such name: a cursor or a filter does not take messages');
	pg_assert_same('text', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('e', array('type' => 'object', 'properties' => array('text' => array('type' => array('string', 'null'))), 'required' => array('text')))), 'a text that may be null');

	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('f', array('type' => 'object', 'properties' => array('room' => $text, 'when' => $text), 'required' => array('room', 'when')))), 'two things required');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('g', array('type' => 'object', 'properties' => array('id' => array('type' => 'integer')), 'required' => array('id')))), 'the required one is not a text');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('h', array('type' => 'object', 'properties' => new stdClass()))), 'no argument at all');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('i', array('type' => 'object', 'properties' => array('n' => array('type' => array('string', 'integer')))))), 'a text or a number is not a text');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('j', array('type' => 'object', 'properties' => array('status' => array('type' => 'string', 'enum' => array('open', 'closed'))), 'required' => array('status')))), 'a fixed list of values is not a text');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('k', array('type' => 'object', 'properties' => array('query' => array('type' => 'string', 'format' => 'date'))))), 'nor is a date');
}

function test_workspace_mcp_agent_tool_must_be_turned_on()
{
	$schema = array('type' => 'object', 'properties' => array('message' => array('type' => 'string')), 'required' => array('message'));
	$tools = array(
		workspace_mcp_test_schema_tool('ask', $schema),
		workspace_mcp_test_schema_tool('lookup', $schema, array('readOnlyHint' => true)),
		workspace_mcp_test_schema_tool('send', $schema, array('readOnlyHint' => false)),
		workspace_mcp_test_schema_tool('ping', array('type' => 'object', 'properties' => new stdClass()), array('readOnlyHint' => true)),
	);

	$server = workspace_mcp_test_server($tools, array('ask' => 'auto_declared', 'lookup' => 'auto'));

	pg_assert_same(array('ask' => 'message', 'lookup' => 'message'), ws_mcp_agent_candidates($server), 'a tool that writes, or takes no text, cannot take messages');

	pg_assert_false(ws_mcp_agent_mode($server), 'no tool chosen: Pinegrap AI answers');
	pg_assert_same(null, ws_mcp_agent_tool($server));

	$server['agent_tool'] = 'ask';

	pg_assert_true(ws_mcp_agent_mode($server));
	pg_assert_same(array('name' => 'ask', 'argument' => 'message', 'approve' => false, 'conversation' => ''), ws_mcp_agent_tool($server));

	// Turned off, or no longer listed: the server stays an agent, but the
	// tool cannot be used.
	$off = workspace_mcp_test_server($tools, array('lookup' => 'auto'));
	$off['agent_tool'] = 'ask';

	pg_assert_true(ws_mcp_agent_mode($off));
	pg_assert_same(null, ws_mcp_agent_tool($off), 'a tool turned off takes no messages');

	$gone = workspace_mcp_test_server($tools, array('ask' => 'auto_declared'));
	$gone['agent_tool'] = 'vanished';

	pg_assert_same(null, ws_mcp_agent_tool($gone), 'a tool the server no longer lists takes no messages');

	// On a server people sign in to, a tool that writes can take the
	// messages too: each one waits for the approval of the person who asked.
	$oauth = workspace_mcp_test_server($tools, array('ask' => 'approve', 'send' => 'approve', 'lookup' => 'auto'), 'oauth');

	pg_assert_same(array('ask' => 'message', 'lookup' => 'message', 'send' => 'message'), ws_mcp_agent_candidates($oauth), 'a tool that writes may take the messages there');

	$oauth['agent_tool'] = 'send';
	pg_assert_same(array('name' => 'send', 'argument' => 'message', 'approve' => true, 'conversation' => ''), ws_mcp_agent_tool($oauth), 'each message asks first');

	$oauth['agent_tool'] = 'ask';
	pg_assert_same(array('name' => 'ask', 'argument' => 'message', 'approve' => true, 'conversation' => ''), ws_mcp_agent_tool($oauth), 'so does one the administrator turned on to ask first');

	$oauth['agent_tool'] = 'lookup';
	pg_assert_same(array('name' => 'lookup', 'argument' => 'message', 'approve' => false, 'conversation' => ''), ws_mcp_agent_tool($oauth), 'one that reads only answers at once');

	// The same rules on a server that no longer signs in with OAuth count
	// for nothing: nobody's own account could run them.
	$plain = workspace_mcp_test_server($tools, array('ask' => 'approve', 'send' => 'approve'), 'bearer');
	$plain['agent_tool'] = 'send';

	pg_assert_same(array('ask' => 'message', 'lookup' => 'message'), ws_mcp_agent_candidates($plain), 'a tool that writes cannot take messages without OAuth');
	pg_assert_same(null, ws_mcp_agent_tool($plain));
}

function test_workspace_mcp_agent_conversation_goes_back_in_its_argument()
{
	$text = array('type' => 'string');

	pg_assert_same('conversation_id', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('a', array('type' => 'object', 'properties' => array('message' => $text, 'conversation_id' => $text), 'required' => array('message')))), 'an optional text named for it');
	pg_assert_same('contextId', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('b', array('type' => 'object', 'properties' => array('prompt' => $text, 'contextId' => array('type' => array('string', 'null')))))), 'whatever the usual name, and it may be null');
	pg_assert_same('conversation_id', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('c', array('type' => 'object', 'properties' => array('message' => $text, 'session_id' => $text, 'conversation_id' => $text), 'required' => array('message')))), 'the likeliest name first');
	pg_assert_same('', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('d', array('type' => 'object', 'properties' => array('message' => $text), 'required' => array('message')))), 'a tool without one');
	pg_assert_same('', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('e', array('type' => 'object', 'properties' => array('message' => $text, 'thread_id' => array('type' => 'integer')), 'required' => array('message')))), 'a number is not taken');
	pg_assert_same('', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('f', array('type' => 'object', 'properties' => array('message' => $text, 'thread_id' => array('type' => 'string', 'enum' => array('a', 'b'))), 'required' => array('message')))), 'nor a fixed list');
	pg_assert_same('', ws_mcp_agent_conversation_argument(workspace_mcp_test_schema_tool('g', array('type' => 'object', 'properties' => array('conversation_id' => $text), 'required' => array('conversation_id')))), 'not the argument the message goes into');

	$server = workspace_mcp_test_server(array(workspace_mcp_test_schema_tool('ask', array('type' => 'object', 'properties' => array('message' => $text, 'conversation_id' => $text), 'required' => array('message')))), array('ask' => 'auto_declared'));
	$server['agent_tool'] = 'ask';

	pg_assert_same(array('name' => 'ask', 'argument' => 'message', 'approve' => false, 'conversation' => 'conversation_id'), ws_mcp_agent_tool($server));
	pg_assert_same('conversation_id', ws_mcp_agent_conversation_argument_of($server, 'ask'), 'the tool a call went to');
	pg_assert_same('', ws_mcp_agent_conversation_argument_of($server, 'other'), 'a tool that no longer takes the messages');
}

function test_workspace_mcp_agent_conversation_is_read_from_the_structured_answer()
{
	$result = function ($structured) {
		return array('content' => array(array('type' => 'text', 'text' => 'Done.')), 'structuredContent' => $structured);
	};

	pg_assert_same('c-41', ws_mcp_agent_conversation_of($result(array('conversation_id' => 'c-41', 'answer' => 'Done.')), 'conversation_id'));
	pg_assert_same('ctx 9', ws_mcp_agent_conversation_of($result(array('contextId' => ' ctx 9 ')), 'conversation_id'), 'under another usual name, trimmed');
	pg_assert_same('77', ws_mcp_agent_conversation_of($result(array('thread_id' => 77)), 'thread_id'), 'a number is an id too');
	pg_assert_same('', ws_mcp_agent_conversation_of($result(array('conversation_id' => 'c-41')), ''), 'a tool that takes none is given none back');
	pg_assert_same('', ws_mcp_agent_conversation_of(array('content' => array()), 'conversation_id'), 'no structured answer');
	pg_assert_same('', ws_mcp_agent_conversation_of($result(array('conversation_id' => array('id' => 'x'))), 'conversation_id'), 'an object is not an id');
	pg_assert_same('', ws_mcp_agent_conversation_of($result(array('conversation_id' => str_repeat('x', 256))), 'conversation_id'), 'too long to keep');
	pg_assert_same('', ws_mcp_agent_conversation_of($result(array('conversation_id' => "a\nb")), 'conversation_id'), 'a control character');
	pg_assert_same('', ws_mcp_agent_conversation_of($result(array('conversation_id' => true)), 'conversation_id'), 'a flag is not an id');
}

function test_workspace_mcp_agent_conversation_is_carried_on_by_the_same_account()
{
	$earlier = array('requested_by' => 7, 'mcp_conversation' => 'c-41');
	$site = array('auth_kind' => 'bearer');
	$oauth = array('auth_kind' => 'oauth');

	pg_assert_same('c-41', ws_mcp_agent_conversation_carried($site, $earlier, 7), 'the one who started it');
	pg_assert_same('c-41', ws_mcp_agent_conversation_carried($site, $earlier, 8), 'anybody, on the site\'s one credential');
	pg_assert_same('c-41', ws_mcp_agent_conversation_carried($oauth, $earlier, 7), 'the one who started it, with their own account');
	pg_assert_same('', ws_mcp_agent_conversation_carried($oauth, $earlier, 8), 'somebody else\'s account starts a new one');
	pg_assert_same('', ws_mcp_agent_conversation_carried($site, array('requested_by' => 7, 'mcp_conversation' => ''), 7), 'the agent gave none');
	pg_assert_same('', ws_mcp_agent_conversation_carried($site, array('requested_by' => 7), 7), 'before the schema step');
	pg_assert_same('', ws_mcp_agent_conversation_carried($site, null, 7), 'nothing it follows');
}

function test_workspace_mcp_agent_answer_is_read_without_its_first_line()
{
	$body = ws_mcp_agent_answer_body('helpdesk', 'ask', "Order 1045 left today.\nTracking: 1Z99.");

	pg_assert_same(array('tool' => 'ask', 'text' => "Order 1045 left today.\nTracking: 1Z99."), ws_mcp_agent_answer_split($body));
	pg_assert_same(array('tool' => '', 'text' => 'One line only'), ws_mcp_agent_answer_split('One line only'), 'not an answer an agent posted');
}

function test_workspace_mcp_agent_approved_call_goes_with_the_askers_account()
{
	pg_assert_true(ws_mcp_agent_call_approved(array('approved_by' => 7)), 'run on the approval of the person who asked');
	pg_assert_false(ws_mcp_agent_call_approved(array('approved_by' => 0)), 'a call made at once');
	pg_assert_false(ws_mcp_agent_call_approved(array()), 'a call without the column');
	pg_assert_false(ws_mcp_agent_call_approved(null), 'no call at all');
}

function test_workspace_mcp_agent_message_with_the_one_it_answers()
{
	pg_assert_same('What about tomorrow?', ws_mcp_agent_compose(' What about tomorrow? '), 'a message alone goes as it is');
	pg_assert_same("> Ayşe: It rains today.\n> Take an umbrella.\n\nWhat about tomorrow?", ws_mcp_agent_compose('What about tomorrow?', 'Ayşe', "It rains today.\nTake an umbrella."), 'a reply quotes the message it answers');
	pg_assert_same("> Nothing said.\n\nAnd?", ws_mcp_agent_compose('And?', '', 'Nothing said.'), 'no name when none is known');
	pg_assert_same('And?', ws_mcp_agent_compose('And?', 'Ayşe', '  '), 'an empty message answered adds nothing');
}

function test_workspace_mcp_agent_message_without_the_alias_it_starts_with()
{
	pg_assert_same('Is order 1045 shipped?', ws_mcp_agent_unaddressed('@helpdesk Is order 1045 shipped?', 'helpdesk'));
	pg_assert_same('is it shipped?', ws_mcp_agent_unaddressed(' @HelpDesk, is it shipped?', 'helpdesk'), 'any case, with the comma after it');
	pg_assert_same('@helpdesk2 hello', ws_mcp_agent_unaddressed('@helpdesk2 hello', 'helpdesk'), 'a longer alias is another one');
	pg_assert_same('Ask @helpdesk later', ws_mcp_agent_unaddressed('Ask @helpdesk later', 'helpdesk'), 'only at the start');
	pg_assert_same('@helpdesk', ws_mcp_agent_unaddressed('@helpdesk', 'helpdesk'), 'the alias alone is kept');
	pg_assert_same('', ws_mcp_agent_unaddressed('@helpdesk:', 'helpdesk', false), 'or not, when nothing else is asked');
	pg_assert_same('check it', ws_mcp_agent_unaddressed('@helpdesk check it', 'helpdesk', false));
}

function test_workspace_mcp_agent_reads_a_workspace_task()
{
	pg_assert_same(
		"T-12: Invoice run\n\nDescription:\nAll orders of May.\n\nInstruction:\nStart with the late ones.",
		ws_mcp_agent_task_compose('T-12: Invoice run', ' All orders of May. ', '', '@helpdesk Start with the late ones.', 'helpdesk'),
		'the task, then the instruction without the alias it starts with'
	);
	pg_assert_same('T-12: Invoice run', ws_mcp_agent_task_compose('T-12: Invoice run', '', '', '@HelpDesk', 'helpdesk'), 'a note that only writes the alias: the task itself is the request');
	pg_assert_same('T-12: Invoice run', ws_mcp_agent_task_compose('T-12: Invoice run', '  ', '', '  ', 'helpdesk'), 'handed over without an instruction');
	pg_assert_same(
		"T-12: Invoice run\n\nYour last answer on this task:\n> 40 orders found.\n> 3 are late.\n\nInstruction:\nAnd the others?",
		ws_mcp_agent_task_compose('T-12: Invoice run', '', "40 orders found.\n3 are late.", 'And the others?', 'helpdesk'),
		'its own last answer on the task is quoted, as a reply quotes the answer it replies to'
	);
	pg_assert_same(
		"T-12: Invoice run\n\nInstruction:\nAsk @helpdesk later",
		ws_mcp_agent_task_compose('T-12: Invoice run', '', '', 'Ask @helpdesk later', 'helpdesk'),
		'an alias written later in the note stays'
	);
}

function test_workspace_mcp_agent_answer_on_a_task_mentions_nobody()
{
	pg_assert_same('Ask ‹@user:12> about ‹#order:3>.', ws_mcp_agent_neutral(" Ask <@user:12> about <#order:3>. ", 100));
	pg_assert_same('a < b', ws_mcp_agent_neutral('a < b', 100), 'only a tag\'s bracket changes');
	pg_assert_same('abcd…', ws_mcp_agent_neutral('abcdefghij', 5), 'cut with a mark');
	pg_assert_same("> Ayşe: one\n> two", ws_mcp_agent_quote("one\ntwo", 'Ayşe'));
}

function test_workspace_mcp_agent_answer_mentions_nobody()
{
	$body = ws_mcp_agent_answer_body('helpdesk', 'a`sk', "Ask <@user:12> in <#channel:3>.\n**Done.**");

	pg_assert_contains('@helpdesk', $body);
	pg_assert_contains('`ask`', $body, 'a backtick in the name does not break the code');
	pg_assert_contains("Ask ‹@user:12> in ‹#channel:3>.\n**Done.**", $body, 'the answer stays Markdown, its tags mention nobody');

	$long = ws_mcp_agent_answer_body('helpdesk', 'ask', str_repeat('x', 10000));

	pg_assert_true(mb_strlen($long) <= WS_MESSAGE_MAX, 'it fits in a message');
	pg_assert_contains('…', $long, 'cut with a mark');
}

function test_workspace_mcp_agent_task_is_asked_about_as_the_server_asks()
{
	pg_assert_same(WS_MCP_AGENT_POLL_DEFAULT, ws_mcp_agent_poll_delay(0), 'the server did not say');
	pg_assert_same(WS_MCP_AGENT_POLL_MIN, ws_mcp_agent_poll_delay(200), 'not more often than the least wait');
	pg_assert_same(3, ws_mcp_agent_poll_delay(3000));
	pg_assert_same(4, ws_mcp_agent_poll_delay(3001), 'rounded up, never sooner than asked');
	pg_assert_same(WS_MCP_AGENT_POLL_MAX, ws_mcp_agent_poll_delay(86400000), 'not less often than the longest wait');
}

function test_workspace_mcp_agent_request_is_stopped_by_who_asked_or_an_administrator()
{
	$row = array('requested_by' => 7, 'status' => 'running');

	pg_assert_true(ws_mcp_agent_can_cancel(array('id' => 7, 'role' => 3), $row), 'the one who asked');
	pg_assert_true(ws_mcp_agent_can_cancel(array('id' => 1, 'role' => 0), $row), 'an administrator');
	pg_assert_false(ws_mcp_agent_can_cancel(array('id' => 8, 'role' => 3), $row), 'somebody else');
	pg_assert_false(ws_mcp_agent_can_cancel(null, $row), 'nobody');
	pg_assert_true(ws_mcp_agent_can_cancel(array('id' => 7, 'role' => 3), array('requested_by' => 7, 'status' => 'queued')), 'while it waits');
	pg_assert_false(ws_mcp_agent_can_cancel(array('id' => 7, 'role' => 3), array('requested_by' => 7, 'status' => 'answered')), 'not once it ended');
}

function test_workspace_mcp_first_read_turns_on_the_tools_that_read()
{
	$tools = array(
		workspace_mcp_test_tool('reads', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('unsaid'),
		workspace_mcp_test_tool('writes', array('readOnlyHint' => false)),
		workspace_mcp_test_tool('deletes', array('destructiveHint' => true)),
	);

	pg_assert_true(ws_mcp_first_read(workspace_mcp_test_server(array(), array())), 'no tool known and no rule: a first read');
	pg_assert_false(ws_mcp_first_read(workspace_mcp_test_server($tools, array())), 'tools read before, all of them off: the setup is the administrator\'s');
	pg_assert_false(ws_mcp_first_read(workspace_mcp_test_server(array(), array('reads' => 'auto'))), 'a rule is kept');

	pg_assert_same(array('reads' => 'auto'), ws_mcp_rules_after_read(array(), $tools, true), 'on the first read only the tool the server marks as reading only is on');
	pg_assert_same(array(), ws_mcp_rules_after_read(array(), $tools, false), 'a server read before is left as it is');
	pg_assert_same(array('unsaid' => 'auto_declared'), ws_mcp_rules_after_read(array('unsaid' => 'auto_declared', 'gone' => 'auto'), $tools, false), 'the rules of the tools it still lists stay; the others go');

	// What a first read leaves on is what the screens offer.
	$server = workspace_mcp_test_server($tools, ws_mcp_rules_after_read(array(), $tools, true), 'oauth');
	pg_assert_same(array('reads'), array_keys(ws_mcp_tools_on($server)), 'a tool that writes stays off on a server people sign in to too');
}

function test_workspace_mcp_declare_all_turns_on_the_unsaid_tools()
{
	$tools = array(
		workspace_mcp_test_tool('reads', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('unsaid'),
		workspace_mcp_test_tool('other'),
		workspace_mcp_test_tool('writes', array('readOnlyHint' => false)),
	);
	$shared = workspace_mcp_test_server($tools, array());
	$oauth = workspace_mcp_test_server($tools, array(), 'oauth');

	pg_assert_same(
		array('reads' => 'auto', 'unsaid' => 'auto_declared', 'other' => 'auto_declared'),
		ws_mcp_rules_declare_all($shared, ws_mcp_tools($shared), array('reads' => 'auto')),
		'every tool the server says nothing about reads only; the one that writes stays off'
	);
	pg_assert_same(array(), ws_mcp_rules_declare_all($shared, array(), array()), 'no tool, nothing on');

	$declared = ws_mcp_rules_declare_all($oauth, ws_mcp_tools($oauth), array('unsaid' => 'approve', 'writes' => 'approve'));
	pg_assert_same('approve', $declared['unsaid'], 'a tool set to ask first keeps asking');
	pg_assert_same('auto_declared', $declared['other'], 'an unsaid tool that was off reads only');
	pg_assert_same('approve', $declared['writes'], 'a tool that writes keeps its rule');
	pg_assert_false(isset($declared['reads']), 'a tool the server says reads only is left to its switch');
}

// The tools form and the API meet one check: the form names every tool (a
// missing one off), the API only the ones it changes; a tool that writes,
// asked on where nobody can approve its calls, is refused, an unknown name
// reported.
function test_workspace_mcp_tools_check_shared_by_form_and_api()
{
	$tools = array(
		workspace_mcp_test_tool('reads', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('unsaid'),
		workspace_mcp_test_tool('writes', array('readOnlyHint' => false)),
	);
	$shared = workspace_mcp_test_server($tools, array('reads' => 'auto'));
	$oauth = workspace_mcp_test_server($tools, array(), 'oauth');

	$form = ws_mcp_tools_check($shared, array('unsaid' => '1', 'writes' => '1', 'gone' => '1'), false);
	pg_assert_same(array('unsaid' => 'auto_declared'), $form['rules'], 'the form starts from nothing: reads is off');
	pg_assert_same(array('gone'), $form['unknown']);
	pg_assert_same(array('writes'), $form['refused'], 'a tool that writes cannot be on without OAuth');

	$api = ws_mcp_tools_check($shared, array('unsaid' => '1'), false, ws_mcp_tool_rules($shared));
	pg_assert_same(array('reads' => 'auto', 'unsaid' => 'auto_declared'), $api['rules'], 'the API keeps the rules it does not name');

	$off = ws_mcp_tools_check($shared, array('reads' => ''), false, ws_mcp_tool_rules($shared));
	pg_assert_same(array(), $off['rules'], 'off takes the rule away');
	pg_assert_same(array(), $off['refused'], 'off is never refused');

	$approve = ws_mcp_tools_check($oauth, array('writes' => '1', 'unsaid' => 'approve'), true);
	pg_assert_same('approve', $approve['rules']['writes'], 'on a server people sign in to a writing tool asks first');
	pg_assert_same('approve', $approve['rules']['unsaid']);
	pg_assert_false(isset($approve['rules']['reads']), 'declare_all leaves a reading tool to its switch');

	$declared = ws_mcp_tools_check($shared, array(), true);
	pg_assert_same(array('unsaid' => 'auto_declared'), $declared['rules'], 'declare_all turns on the unsaid one');
}

// The badge on the card and the state the API gives come from one place.
function test_workspace_mcp_server_state()
{
	$tools = array(workspace_mcp_test_tool('reads', array('readOnlyHint' => true)));
	$server = workspace_mcp_test_server($tools, array('reads' => 'auto')) + array('last_error' => '', 'tools_at' => 1800000000);

	$ready = ws_mcp_server_state($server);
	pg_assert_same('ready', $ready['state']);
	pg_assert_same('', $ready['reason']);
	pg_assert_same(array('turned_on', 'tools_read', 'tool_on'), array_column($ready['checklist'], 'key'), 'no sign-in step without OAuth');
	pg_assert_same(array(true, true, true), array_column($ready['checklist'], 'done'));

	pg_assert_same('no_tool_on', ws_mcp_server_state(array('tool_rules' => '{}') + $server)['state']);
	pg_assert_same('unreachable', ws_mcp_server_state(array('last_error' => 'Down.') + $server)['state']);
	pg_assert_same('Down.', ws_mcp_server_state(array('last_error' => 'Down.') + $server)['reason']);
	pg_assert_same('off', ws_mcp_server_state(array('status' => 'disabled', 'last_error' => 'Down.') + $server)['state'], 'off comes first');
}

// The address check the probe and the API share sends nothing.
function test_workspace_mcp_probe_check()
{
	pg_assert_same('', ws_mcp_probe_check('https://mcp.example.com/mcp', true));
	pg_assert_true(ws_mcp_probe_check('http://mcp.example.com/mcp', true) !== '', 'http is refused');
	pg_assert_true(ws_mcp_probe_check('', true) !== '', 'an empty address is refused');
}

function test_workspace_mcp_settings_form_keeps_no_secret()
{
	$kept = ws_mcp_settings_keep_values(array(
		'mcp_alias'         => ' @Docs ',
		'mcp_name'          => array('x'),
		'mcp_url'           => 'https://mcp.example.com/mcp',
		'mcp_auth'          => 'header',
		'mcp_token'         => 'token-secret',
		'mcp_header_name'   => 'X-Api-Key',
		'mcp_header_value'  => 'header-secret',
		'mcp_user'          => 'robot',
		'mcp_password'      => 'password-secret',
		'mcp_client_id'     => 'client-1',
		'mcp_client_secret' => 'client-secret',
	));

	pg_assert_same(array('mcp_alias', 'mcp_name', 'mcp_url', 'mcp_auth', 'mcp_header_name', 'mcp_user', 'mcp_client_id'), array_keys($kept));
	pg_assert_same('@Docs', $kept['mcp_alias'], 'as typed, trimmed');
	pg_assert_same('', $kept['mcp_name'], 'a list is not a value');
	pg_assert_same('X-Api-Key', $kept['mcp_header_name']);

	foreach (array('token-secret', 'header-secret', 'password-secret', 'client-secret') as $secret) {
		pg_assert_false(in_array($secret, $kept, true), 'never kept: ' . $secret);
	}

	pg_assert_same(500, mb_strlen(ws_mcp_settings_keep_values(array('mcp_url' => str_repeat('é', 900)))['mcp_url']), 'a long value is cut');
}

function test_workspace_mcp_form_link_at_the_first_hold_only()
{
	$row = array('mcp_server_id' => 3, 'channel_id' => 5, 'note_id' => 0, 'task_id' => 0, 'ai_steps' => 0, 'ai_attempts' => 0);

	pg_assert_true(ws_ai_form_hint_due($row, true), 'the first call of a request to an alias could not reach Pinegrap AI');
	pg_assert_false(ws_ai_form_hint_due($row, false), 'Pinegrap AI answered, with a problem of another kind');
	pg_assert_false(ws_ai_form_hint_due(array('ai_attempts' => 1) + $row, true), 'a retry says nothing again');
	pg_assert_false(ws_ai_form_hint_due(array('ai_steps' => 2) + $row, true), 'a request that got on is not told');
	pg_assert_false(ws_ai_form_hint_due(array('mcp_server_id' => 0) + $row, true), 'no alias was asked');
	pg_assert_false(ws_ai_form_hint_due(array('note_id' => 4) + $row, true), 'asked in a note');
	pg_assert_false(ws_ai_form_hint_due(array('task_id' => 2) + $row, true), 'a run on a task');
	pg_assert_false(ws_ai_form_hint_due(array('channel_id' => 0) + $row, true), 'no channel to tell');
}

function test_workspace_mcp_catalogue_entries_are_valid()
{
	$catalogue = ws_mcp_catalogue();
	$aliases = array();
	$addresses = array();

	pg_assert_true((count($catalogue) >= 10) && (count($catalogue) <= 14), 'about a dozen connectors: ' . count($catalogue));

	foreach ($catalogue as $key => $entry) {
		pg_assert_true((bool) preg_match('/^[a-z][a-z0-9_]{1,31}$/', (string) $key), $key . ': a key of letters, digits and _');
		pg_assert_same(array('name', 'description', 'url', 'auth', 'token_url', 'icon', 'alias', 'tools'), array_keys($entry), $key . ': every field, in order');
		pg_assert_true(trim($entry['name']) !== '', $key . ': a name');
		pg_assert_true(trim($entry['description']) !== '', $key . ': a description');
		pg_assert_true(pg_mcp_client_url_is_valid($entry['url']), $key . ': an https address');
		pg_assert_true(in_array($entry['auth'], array('none', 'oauth', 'bearer'), true), $key . ': a sign-in one click can make');
		pg_assert_true(isset(ws_mcp_auth_kinds()[$entry['auth']]), $key . ': a sign-in the form knows');
		pg_assert_same('read_only', $entry['tools'], $key . ': the first read turns on the tools that read');

		if ($entry['auth'] === 'bearer') {
			pg_assert_true(pg_mcp_client_url_is_valid($entry['token_url']), $key . ': the page where a token is made');
		} else {
			pg_assert_same('', $entry['token_url'], $key . ': no token page without a token');
		}

		pg_assert_same('', ws_mcp_alias_shape_problem($entry['alias']), $key . ': an alias that may be taken');
		pg_assert_false(in_array($entry['alias'], ws_mcp_reserved_aliases(), true), $key . ': not a reserved alias');
		pg_assert_false(isset($aliases[$entry['alias']]), $key . ': its alias is its own');
		pg_assert_false(isset($addresses[ws_mcp_url_key($entry['url'])]), $key . ': its address is its own');

		$aliases[$entry['alias']] = true;
		$addresses[ws_mcp_url_key($entry['url'])] = true;

		pg_assert_same($entry, ws_mcp_catalogue_entry($key), $key . ': found by its key');
	}

	pg_assert_same(null, ws_mcp_catalogue_entry('nothing'), 'an unknown key is nothing');
	pg_assert_same(null, ws_mcp_catalogue_entry(array('github')), 'a list is no key');
}

function test_workspace_mcp_catalogue_icons_are_bootstrap_icons()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.css');

	foreach (ws_mcp_catalogue() as $key => $entry) {
		pg_assert_true((bool) preg_match('/^bi-[a-z0-9-]+$/', $entry['icon']), $key . ': an icon class');
		pg_assert_true(strpos($css, '.' . $entry['icon'] . '::before') !== false, $key . ': ' . $entry['icon'] . ' is in the icon font');
	}
}

function test_workspace_mcp_alias_from_host()
{
	pg_assert_same('linear', ws_mcp_alias_from_host('mcp.linear.app'));
	pg_assert_same('notion', ws_mcp_alias_from_host('MCP.Notion.com'), 'in lower case');
	pg_assert_same('cloudflare', ws_mcp_alias_from_host('docs.mcp.cloudflare.com'));
	pg_assert_same('microsoft', ws_mcp_alias_from_host('learn.microsoft.com'));
	pg_assert_same('huggingface', ws_mcp_alias_from_host('huggingface.co'));
	pg_assert_same('githubcopilot', ws_mcp_alias_from_host('api.githubcopilot.com'));
	pg_assert_same('example', ws_mcp_alias_from_host('shop.example.com.tr'), 'a country\'s second level is skipped');
	pg_assert_same('example', ws_mcp_alias_from_host('tools.example.co.uk'));
	pg_assert_same('localhost', ws_mcp_alias_from_host('localhost'));
	pg_assert_same('lives', ws_mcp_alias_from_host('9lives.dev'), 'an alias starts with a letter');
	pg_assert_same('claude-mcp', ws_mcp_alias_from_host('claude.ai'), 'a reserved word is not suggested');
	pg_assert_same('x-mcp', ws_mcp_alias_from_host('x.com'), 'too short alone');
	pg_assert_same('a-very-long-name-of-a-service-th', ws_mcp_alias_from_host('a-very-long-name-of-a-service-that-goes-on.example'), 'at most 32 characters');
	pg_assert_same('server', ws_mcp_alias_from_host('192.168.1.10'), 'an IP address has no name');
	pg_assert_same('server', ws_mcp_alias_from_host(''), 'nothing');

	foreach (array('mcp.linear.app', 'claude.ai', 'x.com', '9lives.dev', '192.168.1.10', '---.com') as $host) {
		pg_assert_same('', ws_mcp_alias_shape_problem(ws_mcp_alias_from_host($host)), $host . ': the suggestion is a valid alias');
	}
}

function test_workspace_mcp_catalogue_entry_already_added()
{
	pg_assert_same(ws_mcp_url_key('https://mcp.linear.app/mcp'), ws_mcp_url_key('https://MCP.Linear.app/mcp/'), 'case of the host and a trailing slash do not count');
	pg_assert_same(ws_mcp_url_key('https://mcp.vercel.com'), ws_mcp_url_key('https://mcp.vercel.com:443/'), 'the default port does not count');
	pg_assert_false(ws_mcp_url_key('https://mcp.linear.app/mcp') === ws_mcp_url_key('https://mcp.linear.app/sse'), 'another path is another server');
	pg_assert_false(ws_mcp_url_key('https://example.com/mcp?x=1') === ws_mcp_url_key('https://example.com/mcp'), 'the query counts');

	$servers = array(
		array('id' => 4, 'alias' => 'issues', 'url' => 'https://mcp.linear.app/mcp/'),
		array('id' => 9, 'alias' => 'gh', 'url' => 'https://api.githubcopilot.com/mcp'),
		array('id' => 11, 'alias' => 'other', 'url' => 'https://mcp.example.com/mcp'),
	);
	$added = ws_mcp_catalogue_added($servers);

	pg_assert_same(4, (int) $added['linear']['id'], 'Linear is added, under another alias');
	pg_assert_same(9, (int) $added['github']['id'], 'GitHub is added, without its trailing slash');
	pg_assert_false(isset($added['notion']), 'Notion is not');
	pg_assert_same(2, count($added), 'a server outside the catalogue is no entry');
	pg_assert_same(array(), ws_mcp_catalogue_added(array()), 'no server, no entry');
}

function test_workspace_mcp_probe_kind()
{
	$found = array('ok' => true, 'registration_url' => 'https://mcp.example.test/register', 'cimd' => false);
	$cimd = array('ok' => true, 'registration_url' => '', 'cimd' => true);
	$manual = array('ok' => true, 'registration_url' => '', 'cimd' => false);

	pg_assert_same('none', ws_mcp_probe_kind(true, 200, null, true), 'it answered without a credential');
	pg_assert_same('oauth', ws_mcp_probe_kind(false, 401, $found, false), 'a sign-in the site registers for itself');
	pg_assert_same('oauth', ws_mcp_probe_kind(false, 401, $cimd, true), 'a client ID metadata document, on an https site');
	pg_assert_same('bearer', ws_mcp_probe_kind(false, 401, $cimd, false), 'not on an http site');
	pg_assert_same('bearer', ws_mcp_probe_kind(false, 401, $manual, true), 'a sign-in that needs an application of its own: a token first');
	pg_assert_same('bearer', ws_mcp_probe_kind(false, 401, array('ok' => false, 'error' => 'x'), true), 'no sign-in found');
	pg_assert_same('bearer', ws_mcp_probe_kind(false, 403, null, true), 'refused');
	pg_assert_same('', ws_mcp_probe_kind(false, 404, null, true), 'no MCP server there');
	pg_assert_same('', ws_mcp_probe_kind(false, 0, null, true), 'not reached');
}

// A stand-in for the network: answers each request by its address and its
// JSON-RPC method, the way an open server, a server people sign in to, one
// whose sign-in needs an application of its own, one that takes a token and
// an address with no server answer.
function workspace_mcp_test_fetch($kind)
{
	return function ($method, $url, $body, $headers) use ($kind) {
		$request = ($body !== null) ? json_decode((string) $body, true) : null;
		$rpc = is_array($request) ? (string) ($request['method'] ?? '') : '';
		$id = (is_array($request) && array_key_exists('id', $request)) ? $request['id'] : null;
		$json = function ($status, $data, $extra = array()) {
			return array('status' => $status, 'body' => (string) json_encode($data), 'headers' => $extra + array('content-type' => 'application/json'), 'error' => '');
		};
		$metadata = 'https://mcp.example.test/.well-known/oauth-protected-resource/mcp';

		if ($kind === 'open') {
			if ($rpc === 'server/discover') {
				return $json(400, array('jsonrpc' => '2.0', 'id' => 'server-error', 'error' => array('code' => -32600, 'message' => 'Unsupported protocol version')));
			}

			if ($rpc === 'initialize') {
				return $json(200, array('jsonrpc' => '2.0', 'id' => $id, 'result' => array('protocolVersion' => '2025-11-25', 'capabilities' => new stdClass(), 'serverInfo' => array('name' => 'docs', 'title' => 'Example Docs', 'version' => '1.0'))));
			}

			if ($rpc === 'notifications/initialized') {
				return array('status' => 202, 'body' => '', 'headers' => array(), 'error' => '');
			}

			if ($rpc === 'tools/list') {
				return $json(200, array('jsonrpc' => '2.0', 'id' => $id, 'result' => array('tools' => array(
					array('name' => 'search', 'description' => 'Search', 'inputSchema' => array('type' => 'object'), 'annotations' => array('readOnlyHint' => true)),
					array('name' => 'fetch', 'description' => 'Fetch', 'inputSchema' => array('type' => 'object')),
				))));
			}
		}

		if (in_array($kind, array('oauth', 'manual', 'token'), true)) {
			if ($method === 'POST') {
				return $json(401, array('error' => 'invalid_token'), array('www-authenticate' => 'Bearer resource_metadata="' . $metadata . '"'));
			}

			if (($kind !== 'token') && ($url === $metadata)) {
				return $json(200, array('resource' => 'https://mcp.example.test/mcp', 'authorization_servers' => array('https://mcp.example.test')));
			}

			if (($kind !== 'token') && ($url === 'https://mcp.example.test/.well-known/oauth-authorization-server')) {
				return $json(200, array(
					'issuer'                           => 'https://mcp.example.test',
					'authorization_endpoint'           => 'https://mcp.example.test/authorize',
					'token_endpoint'                   => 'https://mcp.example.test/token',
					'code_challenge_methods_supported' => array('S256'),
				) + (($kind === 'oauth') ? array('registration_endpoint' => 'https://mcp.example.test/register') : array()));
			}
		}

		return array('status' => 404, 'body' => 'Not found', 'headers' => array('content-type' => 'text/plain'), 'error' => '');
	};
}

function test_workspace_mcp_probe_reads_a_server()
{
	$url = 'https://mcp.example.test/mcp';

	$open = ws_mcp_probe($url, workspace_mcp_test_fetch('open'), true);
	pg_assert_true($open['ok'], 'an open server is understood');
	pg_assert_same('none', $open['kind'], 'no credential');
	pg_assert_same(2, $open['tools'], 'its tools are counted');
	pg_assert_same(1, $open['reads'], 'and the ones it marks as reading only');
	pg_assert_same('Example Docs', $open['name'], 'the name it gives itself');
	pg_assert_same('example', $open['alias'], 'an alias from its host');

	$oauth = ws_mcp_probe($url, workspace_mcp_test_fetch('oauth'), true);
	pg_assert_same('oauth', $oauth['kind'], 'a sign-in the site registers for itself');
	pg_assert_false($oauth['oauth_manual'], 'no application of its own is needed');
	pg_assert_same('Example', $oauth['name'], 'without an answer, the name its host suggests');

	$manual = ws_mcp_probe($url, workspace_mcp_test_fetch('manual'), true);
	pg_assert_same('bearer', $manual['kind'], 'a token first');
	pg_assert_true($manual['oauth_manual'], 'a sign-in with an application of its own is under More settings');

	$token = ws_mcp_probe($url, workspace_mcp_test_fetch('token'), true);
	pg_assert_same('bearer', $token['kind'], 'no sign-in found: a token');
	pg_assert_false($token['oauth_manual']);

	$missing = ws_mcp_probe($url, workspace_mcp_test_fetch('missing'), true);
	pg_assert_false($missing['ok'], 'no MCP server there');
	pg_assert_same('', $missing['kind']);
	pg_assert_same(lang('There is no MCP server at that address.'), $missing['error'], 'the client\'s own words');

	$asked = 0;
	$bad = ws_mcp_probe('http://mcp.example.test/mcp', function () use (&$asked) {
		$asked++;
		return array('status' => 200, 'body' => '', 'headers' => array(), 'error' => '');
	}, true);
	pg_assert_false($bad['ok'], 'an http address is refused');
	pg_assert_same(0, $asked, 'before anything is sent');
}

function test_workspace_mcp_probe_text()
{
	$base = array('ok' => true, 'kind' => 'none', 'tools' => 3, 'reads' => 2, 'name' => 'Docs', 'alias' => 'docs', 'error' => '', 'oauth_manual' => false, 'host' => 'mcp.example.test');

	pg_assert_contains('3', ws_mcp_probe_text($base), 'tools counted');
	pg_assert_contains('mcp.example.test', ws_mcp_probe_text($base), 'the host named');
	pg_assert_false(ws_mcp_probe_text(array('reads' => 0) + $base) === ws_mcp_probe_text($base), 'tools that say nothing get their own words');
	pg_assert_false(ws_mcp_probe_text(array('tools' => 0, 'reads' => 0) + $base) === ws_mcp_probe_text(array('reads' => 0) + $base), 'no tools');
	pg_assert_false(ws_mcp_probe_text(array('kind' => 'oauth') + $base) === ws_mcp_probe_text(array('kind' => 'bearer') + $base), 'a sign-in and a token differ');
	pg_assert_false(ws_mcp_probe_text(array('kind' => 'bearer', 'oauth_manual' => true) + $base) === ws_mcp_probe_text(array('kind' => 'bearer') + $base), 'a sign-in under More settings is named');
	pg_assert_contains('Not here', ws_mcp_probe_text(array('ok' => false, 'kind' => '', 'error' => 'Not here') + $base), 'the reason');

	pg_assert_same('success', ws_mcp_probe_state(array('kind' => 'none') + $base));
	pg_assert_same('info', ws_mcp_probe_state(array('kind' => 'oauth') + $base));
	pg_assert_same('info', ws_mcp_probe_state(array('kind' => 'bearer') + $base));
	pg_assert_same('danger', ws_mcp_probe_state(array('ok' => false, 'kind' => '') + $base));
}

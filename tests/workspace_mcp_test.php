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
 * messages of a server that is an agent itself (mcp_agent.php).
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_ask.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');

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
	pg_assert_same('style', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('d', array('type' => 'object', 'properties' => array('count' => array('type' => 'integer'), 'style' => $text)))), 'else the first text');
	pg_assert_same('text', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('e', array('type' => 'object', 'properties' => array('text' => array('type' => array('string', 'null'))), 'required' => array('text')))), 'a text that may be null');

	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('f', array('type' => 'object', 'properties' => array('room' => $text, 'when' => $text), 'required' => array('room', 'when')))), 'two things required');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('g', array('type' => 'object', 'properties' => array('id' => array('type' => 'integer')), 'required' => array('id')))), 'the required one is not a text');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('h', array('type' => 'object', 'properties' => new stdClass()))), 'no argument at all');
	pg_assert_same('', ws_mcp_agent_argument(workspace_mcp_test_schema_tool('i', array('type' => 'object', 'properties' => array('n' => array('type' => array('string', 'integer')))))), 'a text or a number is not a text');
}

function test_workspace_mcp_agent_tool_must_read_only()
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
	pg_assert_same(array('name' => 'ask', 'argument' => 'message'), ws_mcp_agent_tool($server));

	// Turned off, or no longer listed: the server stays an agent, but the
	// tool cannot be used.
	$off = workspace_mcp_test_server($tools, array('lookup' => 'auto'));
	$off['agent_tool'] = 'ask';

	pg_assert_true(ws_mcp_agent_mode($off));
	pg_assert_same(null, ws_mcp_agent_tool($off), 'a tool turned off takes no messages');

	$gone = workspace_mcp_test_server($tools, array('ask' => 'auto_declared'));
	$gone['agent_tool'] = 'vanished';

	pg_assert_same(null, ws_mcp_agent_tool($gone), 'a tool the server no longer lists takes no messages');

	// On a server people sign in to, a tool that waits for an approval
	// before each call does not take messages yet.
	$oauth = workspace_mcp_test_server($tools, array('ask' => 'approve', 'send' => 'approve'), 'oauth');
	$oauth['agent_tool'] = 'ask';

	pg_assert_same(null, ws_mcp_agent_tool($oauth));
}

function test_workspace_mcp_agent_message_with_the_one_it_answers()
{
	pg_assert_same('What about tomorrow?', ws_mcp_agent_compose(' What about tomorrow? '), 'a message alone goes as it is');
	pg_assert_same("> Ayşe: It rains today.\n> Take an umbrella.\n\nWhat about tomorrow?", ws_mcp_agent_compose('What about tomorrow?', 'Ayşe', "It rains today.\nTake an umbrella."), 'a reply quotes the message it answers');
	pg_assert_same("> Nothing said.\n\nAnd?", ws_mcp_agent_compose('And?', '', 'Nothing said.'), 'no name when none is known');
	pg_assert_same('And?', ws_mcp_agent_compose('And?', 'Ayşe', '  '), 'an empty message answered adds nothing');
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

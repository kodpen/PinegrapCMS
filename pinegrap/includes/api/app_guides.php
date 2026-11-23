<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The connection guides of Application Access: one guide per way an outside
 * client connects (key and secret, one token, Claude Console, MCP with
 * sign-in, team devices, an agent in a channel), each opening with who the connection acts
 * as, then numbered steps built from the site's own addresses. Drawn on the
 * edit drawer's Connect tab and in step 3 of New application's sheet
 * (includes/api/app_drawer_view.php). Pure: the addresses come in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_PANEL')) {
	exit;
}

// The guides in reading order, keyed by id. $at holds the site's addresses:
//   site        the site's address (a client finds the API from it)
//   api         the Site API's base (integration.php)
//   docs        the API document's screen
//   mcp         the MCP server's address that answers on every server
//   mcp_compact the compact list's address ('' when there is none)
//   claude_link Claude's "Add custom connector" link ('' off https)
//   start_text  an application agent's start text ('' without Workspace)
//
// Each guide: id, icon, tone, title, line (one line of what it is for),
// acts ('application' or 'person'), kinds (the application kinds it suits),
// secret (whether it needs a key made with a secret), intro (a line above
// the steps, '' for none), steps (each: title, text, value, value_id, link,
// link_label), clients (the MCP clients' own steps, only the sign-in guide),
// notes (lines under the steps).
function api_app_guides($at) {

	$at = array_merge(array(
		'site' => '', 'api' => '', 'docs' => 'api_docs.php', 'mcp' => '', 'mcp_compact' => '',
		'claude_link' => '', 'start_text' => ''
	), (array)$at);

	$guides = array();

	$guides['key_secret'] = array(
		'icon'  => 'bi-key',
		'tone'  => 'pg-tone-blue',
		'title' => lang('Key and secret'),
		'line'  => lang('For a server or a script: the site address, HTTP Basic, one line to try it, the API document.'),
		'acts'  => 'application',
		'kinds' => array('server'),
		'secret' => true,
		'steps' => array(
			api_app_guide_step(lang('Give the client the site address'),
				lang('The client finds the API from this address (/.well-known/pinegrap.json).'), $at['site'], 'site'),
			api_app_guide_step(lang('Sign in with HTTP Basic'),
				lang('The user name is the key, the password is the secret. This line tries it: only the secret is left to fill in.'),
				'curl -u APPLICATION_KEY:SECRET_KEY "' . $at['api'] . '/meta"', 'curl'),
			api_app_guide_step(lang('Read the API document'),
				lang('Every address, field and answer of the API, with a place to try them.'), '', '', $at['docs'], lang('API Documentation'))
		),
		'clients' => array(),
		'notes' => array(lang('Keep the secret like a password. Do not put it in a URL, an email or a shared file - server logs record those too.'))
	);

	$guides['token'] = array(
		'icon'  => 'bi-ticket-perforated',
		'tone'  => 'pg-tone-blue',
		'title' => lang('One token'),
		'line'  => lang('For a client that asks for an address and a single token: Authorization: Bearer.'),
		'acts'  => 'application',
		'kinds' => array('server'),
		'secret' => true,
		'steps' => array(
			api_app_guide_step(lang('Give the client the address'),
				lang('The API\'s address; a client that speaks MCP takes the MCP address below instead.'), $at['api'], 'token_api'),
			api_app_guide_step(lang('Or the MCP address'),
				lang('The token is taken here as it is on the API.'), $at['mcp'], 'token_mcp'),
			api_app_guide_step(lang('Send the token in this header'),
				lang('The token is the key and the secret in one. It is shown once, beside them, when the application is made or a new secret is issued.'),
				'Authorization: Bearer TOKEN', 'token_header')
		),
		'clients' => array(),
		'notes' => array(lang('The token is never read from the address: a client that puts it there is refused.'))
	);

	$guides['console'] = api_app_console_guide($at);

	$mcp_steps = array(
		api_app_guide_step(lang('Add the MCP address in the application'),
			lang('Exactly as shown: the application compares it with the address the site names in its answers, so another spelling of the host, with or without www, does not connect.'), $at['mcp'], 'mcp')
	);

	if ($at['mcp_compact'] !== '') {

		$mcp_steps[] = api_app_guide_step(lang('Or the compact address, for AI assistants'),
			lang('The same server with its tools behind five, which find and run the rest. An assistant that holds few tools at a time works better with it.'), $at['mcp_compact'], 'mcp_compact');

	}

	$mcp_steps[] = ($at['claude_link'] !== '')
		? api_app_guide_step(lang('Or connect Claude in one step'),
			lang('Opens Claude in a new tab with the Add custom connector dialog filled in with this site\'s address. Sign in to Claude, press Add, then Connect.'), '', '', $at['claude_link'], lang('Connect to Claude'))
		: api_app_guide_step(lang('Claude'), lang('Claude connects only to an https address. Switch the site to https to connect it to Claude.'));

	$mcp_steps[] = api_app_guide_step(lang('Sign in and agree'),
		lang('The application opens this site\'s sign-in page. The person signs in with their own panel account, sees what the application asks for and agrees or refuses; the application never sees the password.'));

	$guides['mcp_signin'] = array(
		'icon'  => 'bi-stars',
		'tone'  => 'pg-tone-violet',
		'title' => lang('MCP with sign-in'),
		'line'  => lang('Claude, ChatGPT and clients that ask for an address. A person signs in on the site and agrees.'),
		'acts'  => 'person',
		'kinds' => array('oauth'),
		'secret' => false,
		'steps' => $mcp_steps,
		'clients' => api_app_mcp_clients($at['mcp']),
		'notes' => array(
			lang('This acts as the person who signs in. For an agent that answers as itself in a channel, use Agent in a channel.'),
			lang('A person gets at most what their own panel account may do, and never more than the permissions chosen here.'),
			lang('Narrowing the permissions takes effect at once. After widening them, connect again in the application so the person can agree to the new ones.')
		)
	);

	$guides['devices'] = array(
		'icon'  => 'bi-phone',
		'tone'  => 'pg-tone-rose',
		'title' => lang('Mobile app and team devices'),
		'line'  => lang('Where a device is paired, what the staff member signs in with.'),
		'acts'  => 'person',
		'kinds' => array('device'),
		'secret' => false,
		'steps' => array(
			api_app_guide_step(lang('Point the app at the sign-in address'),
				lang('The app sends the key as client_key, and can leave it out while this is the only team devices application.'), 'POST ' . $at['api'] . '/auth/login', 'login'),
			api_app_guide_step(lang('Staff sign in with their own account'),
				lang('Each person gets at most what their own account may do, and never more than the permissions chosen here.')),
			api_app_guide_step(lang('See the devices'),
				lang('Signed-in devices appear on the Devices tab, where each can be signed out.'))
		),
		'clients' => array(),
		'notes' => array()
	);

	$agent_steps = array(
		api_app_guide_step(lang('Put the key and the secret in the agent\'s environment'),
			lang('Two environment variables: the key in PINEGRAP_KEY, the secret in PINEGRAP_SECRET. The agent never prints them.'), "PINEGRAP_KEY\nPINEGRAP_SECRET", 'agent_env')
	);

	$agent_steps[] = ($at['start_text'] !== '')
		? api_app_guide_step(lang('Make the start text the agent\'s first instruction'),
			lang('The agent reads its instructions from the site; this text only tells it where to look.'), $at['start_text'], 'agent_start')
		: api_app_guide_step(lang('Make the start text the agent\'s first instruction'),
			lang('The start text comes with the Workspace: switch it on to give an agent a channel.'));

	$agent_steps[] = api_app_guide_step(lang('Let it wait for requests'),
		lang('The agent waits on its request queue and answers as itself when its @handle is called in a channel.'));

	$guides['agent'] = array(
		'icon'  => 'bi-robot',
		'tone'  => 'pg-tone-teal',
		'title' => lang('Agent in a channel'),
		'line'  => lang('Two environment variables, the start text, how the agent waits.'),
		'acts'  => 'application',
		'kinds' => array('server'),
		'secret' => true,
		'steps' => $agent_steps,
		'clients' => array(),
		'notes' => array()
	);

	foreach ($guides as $id => $guide) {
		$guides[$id] = array_merge(array('id' => $id, 'intro' => ''), $guide);
	}

	return $guides;

}

// The Claude Console guide: an agent of Claude Managed Agents that works its
// channel requests over MCP as the application. The application's one token
// is a vault's bearer credential for the MCP address, the site's host is
// allowed in the agent's environment, the start text is its system prompt and
// a scheduled run wakes it: the site keeps no Anthropic key and calls no
// Anthropic address. Sign-in (OAuth) is not offered here, as it acts as the
// person who signs in. Written to Anthropic's pages as read on 2026-11-20.
function api_app_console_guide($at) {

	$host = (string)parse_url((string)$at['site'], PHP_URL_HOST);

	$steps = array(
		api_app_guide_step(lang('Make this application an agent'),
			lang('On its AI agent tab, with the channels it answers in. The agent\'s own tools are offered over MCP only to an active agent\'s own key.')),
		api_app_guide_step(lang('Add the MCP server to the agent'),
			lang('In the agent\'s definition, an MCP server named pinegrap at this address. The Console asks for no token at this point.'), $at['mcp'], 'console_mcp'),
		api_app_guide_step(lang('Put the token in a vault'),
			lang('A credential of the static bearer kind for the same MCP server address, its token the application\'s one token. Name the vault when the session or the scheduled run is made.')),
		($host !== '')
			? api_app_guide_step(lang('Allow the site\'s host'),
				lang('In the agent\'s environment, under networking: the Console starts with no host allowed. Add this one.'), $host, 'console_host')
			: api_app_guide_step(lang('Allow the site\'s host'),
				lang('In the agent\'s environment, under networking: the Console starts with no host allowed. Add the host of the site address.'))
	);

	$steps[] = ($at['start_text'] !== '')
		? api_app_guide_step(lang('Make the start text the agent\'s system prompt'),
			lang('The agent reads its instructions from the site; this text only tells it where to look.'), $at['start_text'], 'console_start')
		: api_app_guide_step(lang('Make the start text the agent\'s system prompt'),
			lang('The start text comes with the Workspace: switch it on to give an agent a channel.'));

	$steps[] = api_app_guide_step(lang('Wake it on a schedule'),
		lang('Make a scheduled run of the agent. Each run reads the request list, takes what waits, answers and ends.'));

	return array(
		'icon'  => 'bi-cloud',
		'tone'  => 'pg-tone-amber',
		'title' => lang('Claude Console'),
		'line'  => lang('An agent in the Claude Console that answers in a channel as the application: the token in a vault, the MCP address, a scheduled run.'),
		'acts'  => 'application',
		'kinds' => array('server'),
		'secret' => true,
		'intro' => lang('These steps follow Anthropic\'s pages on Claude Managed Agents as read on 20 November 2026. The product is in beta: its screens may differ.'),
		'steps' => $steps,
		'clients' => array(),
		'notes' => array(
			lang('A call to the agent waits in its request queue until the next scheduled run.'),
			lang('Sign-in is not offered here: it acts as the person who signs in, never as the agent.')
		)
	);

}

// One numbered step: a title, one line, and either a value to copy (with the
// id its box takes) or a link with its label.
function api_app_guide_step($title, $text, $value = '', $value_id = '', $link = '', $link_label = '') {

	return array(
		'title'      => (string)$title,
		'text'       => (string)$text,
		'value'      => (string)$value,
		'value_id'   => (string)$value_id,
		'link'       => (string)$link,
		'link_label' => (string)$link_label
	);

}

// Why a guide does not suit an application, as a code, or '' when it does:
// 'kind' (another kind of application) or 'no_secret' (a key made without a
// secret, which has no way in by key).
function api_app_guide_fit($guide, $kind, $has_secret) {

	if (!in_array((string)$kind, (array)$guide['kinds'], true)) {
		return 'kind';
	}

	if (!empty($guide['secret']) && !$has_secret) {
		return 'no_secret';
	}

	return '';

}

// The guide a starting point of New application names (api_app_templates()
// 'guide'), or the first guide that suits its kind.
function api_app_guide_for($guides, $guide_id, $kind) {

	if (isset($guides[$guide_id])) {
		return $guides[$guide_id];
	}

	foreach ($guides as $guide) {

		if (api_app_guide_fit($guide, $kind, true) === '') {
			return $guide;
		}

	}

	return reset($guides);

}

// Each MCP client is set up its own way: a menu, a command or a configuration
// file. The steps and the text to paste with this site's address in it.
// Product names are names and are not translated; the configuration keys are
// what each client reads and are written exactly.
function api_app_mcp_clients($mcp) {

	$json_address = json_encode((string)$mcp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	return array(
		'claude' => array(
			'name'    => lang('Claude (web, desktop and mobile)'),
			'steps'   => lang('Settings > Connectors > Add custom connector. Paste the address, then press Connect.'),
			'snippet' => ''
		),
		'claude_code' => array(
			'name'    => 'Claude Code',
			'steps'   => lang('Run this in a terminal, then type /mcp in Claude Code and choose the server to sign in.'),
			'snippet' => 'claude mcp add --transport http pinegrap ' . $mcp
		),
		'chatgpt' => array(
			'name'    => 'ChatGPT',
			'steps'   => lang('Settings > Apps & Connectors > Advanced settings: switch developer mode on. Then press Create, paste the address as the MCP server URL and choose OAuth as the authentication.'),
			'snippet' => ''
		),
		'vscode' => array(
			'name'    => 'VS Code (GitHub Copilot)',
			'steps'   => lang('Command Palette > MCP: Add Server > HTTP, then paste the address; or put this in .vscode/mcp.json. VS Code asks you to sign in the first time the server starts.'),
			'snippet' => "{\n  \"servers\": {\n    \"pinegrap\": {\n      \"type\": \"http\",\n      \"url\": " . $json_address . "\n    }\n  }\n}"
		),
		'cursor' => array(
			'name'    => 'Cursor',
			'steps'   => lang('Add a new MCP server in Cursor\'s settings, or put this in ~/.cursor/mcp.json. Then press Connect next to the server.'),
			'snippet' => "{\n  \"mcpServers\": {\n    \"pinegrap\": {\n      \"url\": " . $json_address . "\n    }\n  }\n}"
		),
		'windsurf' => array(
			'name'    => 'Windsurf',
			'steps'   => lang('Put this in ~/.codeium/windsurf/mcp_config.json, refresh the MCP servers and sign in when asked.'),
			'snippet' => "{\n  \"mcpServers\": {\n    \"pinegrap\": {\n      \"serverUrl\": " . $json_address . "\n    }\n  }\n}"
		),
		'gemini' => array(
			'name'    => 'Gemini CLI',
			'steps'   => lang('Put this in ~/.gemini/settings.json, then run /mcp auth pinegrap in Gemini CLI.'),
			'snippet' => "{\n  \"mcpServers\": {\n    \"pinegrap\": {\n      \"httpUrl\": " . $json_address . "\n    }\n  }\n}"
		),
		'codex' => array(
			'name'    => 'Codex',
			'steps'   => lang('Run these in a terminal. The second opens the sign-in in your browser.'),
			'snippet' => 'codex mcp add pinegrap --url ' . $mcp . "\n" . 'codex mcp login pinegrap'
		),
		'other' => array(
			'name'    => lang('Other applications'),
			'steps'   => lang('An application that asks for a server address works with the address above. One that can only start a program on the computer reaches the server through the mcp-remote bridge, which needs Node.js and opens the sign-in in the browser: give it this as the command.'),
			'snippet' => 'npx -y mcp-remote ' . $mcp
		)
	);

}

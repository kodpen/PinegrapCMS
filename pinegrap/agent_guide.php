<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The guide for AI agents (includes/fn/agent_guide.php) through the panel's
// own session, for an agent that drives a browser rather than the API or MCP.
// It answers Markdown: the topic list, or a topic with ?topic= (and one of its
// sections with &section=). Without a session it answers as any panel page:
// validate_user() sends the browser to the sign-in page.
//
// llms.txt in the software folder is routed here by router.php with
// PG_AGENT_GUIDE_LIST defined, and always answers the topic list.
//
// The topics listed are the ones a connection owned by the signed-in user
// could reach: the routes whose scope api_owner_scopes() lets them hand on.
// The panel topic reads the same user's screens.

include(dirname(__FILE__) . '/init.php');

$user = validate_user();

define('PG_API_PANEL', true);

require_once(dirname(__FILE__) . '/includes/api/scopes.php');
require_once(dirname(__FILE__) . '/includes/api/schema.php');
require_once(dirname(__FILE__) . '/includes/fn/agent_guide.php');

$agent_guide_refuse = function ($status, $message) {
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: private, no-store');
	print $message . "\n";
	exit();
};

$agent_guide_param = function ($name) {
	return (isset($_GET[$name]) && is_string($_GET[$name])) ? trim($_GET[$name]) : '';
};

$agent_guide_topic = defined('PG_AGENT_GUIDE_LIST') ? '' : $agent_guide_param('topic');
$agent_guide_section = defined('PG_AGENT_GUIDE_LIST') ? '' : $agent_guide_param('section');

if ((($agent_guide_topic !== '') && !preg_match('/^[a-z][a-z_]{0,31}$/', $agent_guide_topic))
	|| (($agent_guide_section !== '') && !preg_match('/^[a-z_]{1,64}$/', $agent_guide_section))
) {
	$agent_guide_refuse(400, lang('The topic or section name is not valid.'));
}

if (($agent_guide_section !== '') && ($agent_guide_topic === '')) {
	$agent_guide_refuse(400, lang('A section is read within a topic: send topic as well.'));
}

$agent_guide_owner_scopes = api_owner_scopes($user);

$agent_guide_route_ids = array();

foreach (api_schema() as $agent_guide_route) {
	if (isset($agent_guide_route['id'], $agent_guide_route['scope'])
		&& api_has_scope($agent_guide_owner_scopes, (string)$agent_guide_route['scope'])
	) {
		$agent_guide_route_ids[] = (string)$agent_guide_route['id'];
	}
}

$agent_guide_registry = pg_agent_guide_registry();
$agent_guide_modules = pg_agent_guide_modules_on();

$agent_guide_answer = pg_agent_guide_answer(
	$agent_guide_registry,
	pg_agent_guide_listed($agent_guide_registry, $agent_guide_route_ids, $agent_guide_modules),
	$agent_guide_modules,
	$agent_guide_topic,
	$agent_guide_section
);

if ($agent_guide_answer['status'] === 404) {
	$agent_guide_refuse(404, ($agent_guide_answer['what'] === 'section') ? lang('Guide section') : lang('Guide topic'));
}

$agent_guide_text = $agent_guide_answer['body']['text'];

// The list names topic ids; say where a browser reads one.
if ($agent_guide_topic === '') {
	$agent_guide_text .= "\nRead a topic at " . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . "/agent_guide.php?topic=<id>\n";
}

// The text differs per user, so no shared cache may keep it; the digest lets
// a reader that kept it ask whether it changed.
header('Content-Type: text/markdown; charset=utf-8');
header('Cache-Control: private, no-cache');
header('ETag: "' . $agent_guide_answer['body']['digest'] . '"');
header('X-Robots-Tag: noindex');

$agent_guide_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) : '';

if ($agent_guide_match === '"' . $agent_guide_answer['body']['digest'] . '"') {
	http_response_code(304);
	exit();
}

print $agent_guide_text;

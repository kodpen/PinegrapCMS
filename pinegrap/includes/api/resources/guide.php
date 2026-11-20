<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GET /guide (guide.read): the guide an AI agent reads before it works on the
 * site (includes/fn/agent_guide.php) - the topic list, one topic, or one
 * section of a topic. Any valid credentials read it: it holds no record of
 * the site, only how the software is used, so it has no scope of its own.
 * Like every GET, the answer carries an ETag and an unchanged one sent in
 * If-None-Match is answered 304 with no body.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');

// The ids of the routes the caller's scopes cover, which decide the topics
// listed beside start.
function api_guide_route_ids() {

	$scopes = api_current_scopes();

	$ids = array();

	foreach (api_schema() as $route) {

		if (isset($route['id'], $route['scope']) && api_has_scope($scopes, (string)$route['scope'])) {

			$ids[] = (string)$route['id'];

		}

	}

	return $ids;

}

function api_guide_read($params) {

	$registry = pg_agent_guide_registry();

	$modules = pg_agent_guide_modules_on();

	$topic = isset($params['topic']) ? (string)$params['topic'] : '';

	$section = isset($params['section']) ? (string)$params['section'] : '';

	if (($section !== '') && ($topic === '')) {

		api_fail_validation(lang('A section is read within a topic: send topic as well.'), 'topic');

	}

	$answer = pg_agent_guide_answer($registry, pg_agent_guide_listed($registry, api_guide_route_ids(), $modules), $modules, $topic, $section);

	if ($answer['status'] === 404) {

		api_fail_not_found(($answer['what'] === 'section') ? lang('Guide section') : lang('Guide topic'));

	}

	api_ok($answer['body']);

}

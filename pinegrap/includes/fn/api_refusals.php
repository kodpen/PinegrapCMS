<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Refused calls: the API requests answered 400 or above in the last days,
 * added up by route (ids folded out of the path), error code and
 * application, each code with the guide section its hint names
 * (includes/fn/agent_guide_hints.php). Read by the "Refused calls" card of
 * Application Access (api_settings.php) and by GET /reports/api-refusals.
 * It reads api_request_log only: nothing new is recorded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// The longest window, in days.
function pg_api_refusals_days_max() {

	return 30;

}

// The raw groups the one read hands back at most. A client walking through
// ids writes one path per id, so the cap keeps a scan of thousands of ids from
// turning into thousands of rows; the busiest groups come first.
function pg_api_refusals_read_max() {

	return 2000;

}

// The one read: refused requests since $since, grouped by method, raw path,
// code and application. The range on timestamp walks idx_timestamp; the
// application's name comes along by its primary key.
function pg_api_refusals_sql($since) {

	return "SELECT api_request_log.method, api_request_log.path, api_request_log.error_code,
			api_request_log.app_id, MAX(api_request_log.status_code) AS status_code,
			COUNT(*) AS refusals, MAX(api_request_log.timestamp) AS last_at,
			MAX(api_apps.name) AS app_name
		FROM api_request_log
		LEFT JOIN api_apps ON api_apps.id = api_request_log.app_id
		WHERE api_request_log.timestamp >= " . (int)$since . "
			AND api_request_log.status_code >= 400
		GROUP BY api_request_log.method, api_request_log.path, api_request_log.error_code, api_request_log.app_id
		ORDER BY refusals DESC
		LIMIT " . pg_api_refusals_read_max();

}

// Method => list of array(regex, route id, path template) for the routes
// given, each placeholder narrowed as the router narrows it: an int
// parameter to digits, anything else to one segment.
function pg_api_refusals_patterns($routes) {

	$patterns = array();

	foreach ((array)$routes as $route) {

		if (empty($route['path']) || empty($route['method'])) {

			continue;

		}

		$types = array();

		foreach ((isset($route['params']) ? (array)$route['params'] : array()) as $param) {

			if (isset($param['in'], $param['name']) && $param['in'] === 'path') {

				$types[$param['name']] = isset($param['type']) ? $param['type'] : 'string';

			}

		}

		$body = preg_replace_callback('#\\\{([a-z_]+)\\\}#i', function ($found) use ($types) {

			return (isset($types[$found[1]]) && $types[$found[1]] === 'int') ? '[0-9]+' : '[^/]+';

		}, preg_quote($route['path'], '#'));

		$methods = array($route['method']);

		if (!empty($route['also_accepts'])) {

			$methods = array_merge($methods, (array)$route['also_accepts']);

		}

		foreach ($methods as $method) {

			$patterns[strtoupper($method)][] = array('#^' . $body . '$#i', isset($route['id']) ? (string)$route['id'] : '', $route['path']);

		}

	}

	return $patterns;

}

// The route a logged request reached and its path with the ids folded out:
// array(route id or '', path). A path no route matches (a wrong address, a
// wrong method) keeps its words and loses its numbers, so /nope/12 and
// /nope/13 are one row. A tool called over MCP is logged as /mcp followed by
// its route's path (pg_mcp_route_run()): it folds to that route, the /mcp
// kept in front so MCP and REST calls stay separate rows.
function pg_api_refusals_fold_path($method, $path, $patterns) {

	$method = strtoupper((string)$method);

	$path = (string)$path;

	$mcp = (strpos($path, '/mcp/') === 0);

	$route_path = $mcp ? substr($path, 4) : $path;

	if (isset($patterns[$method])) {

		foreach ($patterns[$method] as $pattern) {

			if (preg_match($pattern[0], $route_path)) {

				return array($pattern[1], ($mcp ? '/mcp' : '') . $pattern[2]);

			}

		}

	}

	return array('', preg_replace('#/[0-9]+(?=/|$)#', '/{id}', (string)$path));

}

// The read's rows folded into the report's rows, busiest first: route, method,
// path, error_code, status (the highest one seen), app_id, app_name,
// refusals, last_at and guide (topic#section, '' for a code without a hint).
// $guides is code => topic#section.
function pg_api_refusals_fold($rows, $routes, $guides) {

	$patterns = pg_api_refusals_patterns($routes);

	$folded = array();

	foreach ((array)$rows as $row) {

		$method = strtoupper((string)$row['method']);

		list($route_id, $path) = pg_api_refusals_fold_path($method, (string)$row['path'], $patterns);

		$code = (string)$row['error_code'];

		$app_id = (int)$row['app_id'];

		$key = $method . ' ' . $path . "\n" . $code . "\n" . $app_id;

		if (!isset($folded[$key])) {

			$folded[$key] = array(
				'route'      => $route_id,
				'method'     => $method,
				'path'       => $path,
				'error_code' => $code,
				'status'     => 0,
				'app_id'     => $app_id,
				'app_name'   => isset($row['app_name']) ? (string)$row['app_name'] : '',
				'refusals'   => 0,
				'last_at'    => 0,
				'guide'      => isset($guides[$code]) ? (string)$guides[$code] : ''
			);

		}

		$folded[$key]['status'] = max($folded[$key]['status'], (int)$row['status_code']);

		$folded[$key]['refusals'] += (int)$row['refusals'];

		$folded[$key]['last_at'] = max($folded[$key]['last_at'], (int)$row['last_at']);

	}

	$folded = array_values($folded);

	usort($folded, function ($a, $b) {

		if ($a['refusals'] !== $b['refusals']) {

			return ($a['refusals'] > $b['refusals']) ? -1 : 1;

		}

		if ($a['last_at'] !== $b['last_at']) {

			return ($a['last_at'] > $b['last_at']) ? -1 : 1;

		}

		return strcmp($a['method'] . $a['path'] . $a['error_code'], $b['method'] . $b['path'] . $b['error_code']);

	});

	return $folded;

}

// Error code => guide section, from the hints registry.
function pg_api_refusals_guides() {

	if (!function_exists('pg_agent_guide_hints')) {

		require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_hints.php');

	}

	$guides = array();

	foreach (pg_agent_guide_hints() as $code => $entry) {

		$guides[$code] = $entry['guide'];

	}

	return $guides;

}

// The topic a guide section (topic#section) is read in, when it is one of
// the $listed topics the viewer may read (pg_agent_guide_listed()), or '':
// the Refused calls card links only a topic agent_guide.php answers for them.
function pg_api_refusals_guide_topic($guide, $listed) {

	$topic = strtok((string)$guide, '#');

	return (($topic !== false) && ($topic !== '') && in_array($topic, (array)$listed, true)) ? $topic : '';

}

// The report for the last $days days against $routes; a log that cannot be
// read reports nothing refused (db_items() answers an empty list).
function pg_api_refusals_report($routes, $days = 30, $now = null) {

	$days = max(1, min(pg_api_refusals_days_max(), (int)$days));

	$now = ($now === null) ? time() : (int)$now;

	$rows = db_items(pg_api_refusals_sql($now - $days * 86400));

	return pg_api_refusals_fold($rows, $routes, pg_api_refusals_guides());

}

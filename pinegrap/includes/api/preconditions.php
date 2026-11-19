<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// If-Match on an update: a client that read a record sends its ETag back with
// the change, and the change is refused with 412 precondition_failed when the
// record no longer answers with that tag - someone else changed it since. A
// client that sends no If-Match is answered as before: the later write stands.
//
// The tag compared is the one the GET route of the same path answers this
// caller in full (no fields=), so an update is only conditional where such a
// route exists; elsewhere the header is ignored and the API document leaves
// it out of that operation. The record is read right before the handler runs,
// with no lock: a write that lands between that read and the update is not
// caught.
//
// The pure helpers come first; api_if_match_check() is what the router calls.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Whether a route changes an existing record: it is reached by PATCH or PUT,
// as its own method or beside POST ('also_accepts').
function api_route_is_update($route) {

	$methods = array(isset($route['method']) ? (string)$route['method'] : '');

	if (isset($route['also_accepts'])) {

		$methods = array_merge($methods, (array)$route['also_accepts']);

	}

	return (bool)array_intersect($methods, array('PATCH', 'PUT'));

}

// The GET route of the same path as an update, whose answer the If-Match tag
// is compared with; null when the route is no update or no GET reads its path.
function api_update_get_twin($route, $routes) {

	if (!api_route_is_update($route)) {

		return null;

	}

	foreach ((array)$routes as $candidate) {

		if (isset($candidate['method'], $candidate['path'])
			&& ($candidate['method'] === 'GET')
			&& ($candidate['path'] === $route['path'])) {

			return $candidate;

		}

	}

	return null;

}

// Whether an If-Match header holds for the record's current tag. $current is
// null when there is no record: then nothing matches, * included (RFC 9110
// 13.1.1). * matches any record that exists. Otherwise the header is a comma
// separated list, and one tag equal to the current one is enough; W/ is
// ignored on both sides, as If-None-Match does (api_etag_matches()), since
// every answer's tag is weak and a proxy may add or drop the marker.
function api_if_match_holds($header, $current) {

	if ($current === null || $current === '') {

		return false;

	}

	$header = trim((string)$header);

	if ($header === '*') {

		return true;

	}

	$bare = preg_replace('#^W/#', '', trim((string)$current));

	foreach (explode(',', $header) as $candidate) {

		$candidate = trim($candidate);

		if ($candidate !== '' && preg_replace('#^W/#', '', $candidate) === $bare) {

			return true;

		}

	}

	return false;

}

// What the router does with an update's If-Match: null to write, or the
// refusal array(status, code, message). $read_tag($get_route) gives the
// current tag of the record, null when there is none; it is called only when
// the header was sent and the path has a GET route.
function api_if_match_refusal($route, $routes, $header, $read_tag) {

	if (trim((string)$header) === '') {

		return null;

	}

	$get_route = api_update_get_twin($route, $routes);

	if ($get_route === null) {

		return null;

	}

	if (api_if_match_holds($header, call_user_func($read_tag, $get_route))) {

		return null;

	}

	return array(412, 'precondition_failed', lang('The record has changed since the ETag sent in If-Match was read. Read it again and repeat the change.'));

}

// The tag a GET route answers this caller for the record at $path_values, or
// null when it answers anything but 200. The GET handler runs in this request
// with the GET's own method, an empty input and its declared defaults, and its
// answer is caught before api_send() sends or logs it (api_send_capture()).
function api_current_etag($get_route, $path_values) {

	$saved_method = api_request_method();

	$saved_input = api_request_input();

	$saved_route = api_current_route();

	$get_route['path_values'] = (array)$path_values;

	api_request_method('GET');

	api_request_input(array());

	api_current_route($get_route);

	api_send_capture(true);

	$tag = null;

	try {

		$checked = api_validate_collect($get_route, array(), array());

		if (empty($checked['errors'])) {

			call_user_func($get_route['handler'], $checked['values']);

		}

	} catch (ApiAnswerCaptured $answer) {

		if ($answer->status === 200) {

			$json = api_json_encode($answer->body);

			$tag = ($json === null) ? null : api_etag_of_json($json);

		}

	} finally {

		api_send_capture(false);

		api_request_method($saved_method);

		api_request_input($saved_input);

		if ($saved_route !== null) {

			api_current_route($saved_route);

		}

	}

	return $tag;

}

// Refuses the update with 412 when its If-Match does not hold; $routes is
// api_all_routes() unless given. Called by the router right before the
// handler, after validation and the idempotency replay, so a repeat of a
// change that went through gets the first answer.
function api_if_match_check($route, $routes = null) {

	$refusal = api_if_match_refusal($route, ($routes === null) ? api_all_routes() : $routes, api_header('If-Match'), function ($get_route) use ($route) {

		return api_current_etag($get_route, isset($route['path_values']) ? $route['path_values'] : array());

	});

	if ($refusal !== null) {

		api_fail($refusal[0], $refusal[1], $refusal[2]);

	}

}

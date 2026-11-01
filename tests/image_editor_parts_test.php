<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's parts (includes/fn/image_editor.php): each
 * tool keeps its labels in its own function, beside its own script in the
 * bundle, no key in two parts; a request reaches a tool's own API action
 * and nothing else.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A tool action for the test below; the editor's API reaches it by name.
function pg_image_editor_action_parts_probe($request, $user)
{
	return array(array('status' => 'success', 'value' => isset($request['value']) ? $request['value'] : null, 'user' => $user['id']), 200);
}

// Every part has its labels function and its script in the bundle, every
// tool script has a part, and a key is defined by one part only.
function test_image_editor_parts_keep_their_own_labels()
{
	$bundles = pg_js_bundles();
	$scripts = $bundles['assets/js/pg_image_editor.min.js'];
	$owner = array();

	foreach (pg_image_editor_parts() as $part) {
		$function = 'pg_image_editor_strings_' . $part;
		$script = 'assets/js/pg_image_editor' . (($part === 'core') ? '' : '_' . $part) . '.js';

		pg_assert_true(function_exists($function), $function . ' exists');
		pg_assert_true(in_array($script, $scripts, true), $script . ' is in the bundle');

		if (!function_exists($function)) {
			continue;
		}

		foreach (array_keys(call_user_func($function)) as $key) {
			pg_assert_false(isset($owner[$key]), $key . ' is defined by ' . (isset($owner[$key]) ? $owner[$key] : '') . ' and ' . $part);
			$owner[$key] = $part;
		}
	}

	foreach ($scripts as $script) {
		if (preg_match('#^assets/js/pg_image_editor_([a-z]+)\.js$#', $script, $m) && ($m[1] !== 'model')) {
			pg_assert_true(in_array($m[1], pg_image_editor_parts(), true), $script . ' has a part');
		}
	}

	pg_assert_same(count($owner), count(pg_image_editor_strings()), 'the labels are every part\'s, merged');
}

// An action that is not open or save goes to the tool's function of that
// name; a name in another form, or with no function, is refused.
function test_image_editor_api_reaches_a_tool_action()
{
	$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
	$had_session = isset($_SESSION);
	$session = $had_session ? $_SESSION : null;

	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SESSION['software']['token'] = 'parts-token';

	$call = function ($action) {
		return pg_image_editor_api(json_encode(array('token' => 'parts-token', 'action' => $action, 'value' => 7)), array('id' => 3));
	};

	list($payload, $status) = $call('parts_probe');
	list($upper, $upper_status) = $call('Parts_Probe');
	list($missing) = $call('parts_missing');
	list($path) = $call('../parts_probe');

	if ($method === null) {
		unset($_SERVER['REQUEST_METHOD']);
	} else {
		$_SERVER['REQUEST_METHOD'] = $method;
	}

	if ($had_session) {
		$_SESSION = $session;
	} else {
		unset($_SESSION);
	}

	pg_assert_same(200, $status, 'the action answers');
	pg_assert_same(array('status' => 'success', 'value' => 7, 'user' => 3), $payload, 'with the request and the user');
	pg_assert_same('error', $upper['status'], 'a name in capitals is refused');
	pg_assert_same('error', $missing['status'], 'an action no tool has is refused');
	pg_assert_same('error', $path['status'], 'a name with other characters is refused');
}

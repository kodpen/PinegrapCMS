<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's return routes across orders (GET /returns,
 * GET /returns/{id}, POST /returns/{id}; includes/api/resources/returns.php):
 * the route rows, the action check every move and its rehearsal go through,
 * and that the handler writes only through the screen's own functions.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/returns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_returns_test_row($status)
{
	return array('id' => '31', 'order_id' => '9007', 'ship_to_id' => '9008', 'status' => $status);
}

function api_returns_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function api_returns_test_param($route, $name)
{
	foreach ($route['params'] as $param) {
		if ($param['name'] === $name) {
			return $param;
		}
	}

	return null;
}

// The source of one top-level function, up to the next one.
function api_returns_test_body($relative, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_api_returns_routes_and_scopes()
{
	$expected = array(
		'returns.list'   => array('GET', '/returns', 'orders:read'),
		'returns.get'    => array('GET', '/returns/{id}', 'orders:read'),
		'returns.update' => array('POST', '/returns/{id}', 'orders:write'),
	);

	foreach ($expected as $id => $row) {
		$route = api_returns_test_route($id);

		pg_assert_true($route !== null, $id . ' is declared');
		pg_assert_same($row[0], $route['method'], $id . ' method');
		pg_assert_same($row[1], $route['path'], $id . ' path');
		pg_assert_same($row[2], $route['scope'], $id . ' scope');
		pg_assert_true(function_exists($route['handler']), $id . ': ' . $route['handler'] . '() exists');
	}

	$update = api_returns_test_route('returns.update');

	pg_assert_true(!empty($update['dry_run']), 'the move takes X-Dry-Run');
	pg_assert_same('OrderReturn', $update['returns']);
	pg_assert_same(array_keys(api_return_actions()), api_returns_test_param($update, 'action')['values'], 'the action enum is the action list');
	pg_assert_same(pg_return_statuses(), api_returns_test_param($update, 'status_was')['values']);

	$list = api_returns_test_route('returns.list');

	pg_assert_same(array_merge(array('open'), pg_return_statuses()), api_returns_test_param($list, 'status')['values']);
}

// Each action's fields are declared on the route, and a text field fits its
// column (order_returns: note TEXT, carrier 32, carrier_code 64,
// tracking_number 100, refund_reference 255).
function test_api_returns_action_fields_are_declared_within_their_columns()
{
	$update = api_returns_test_route('returns.update');
	$limits = array('note' => PG_RETURN_NOTE_MAX, 'carrier' => 32, 'carrier_code' => 64, 'tracking_number' => 100, 'reference' => 255);

	foreach (api_return_action_fields() as $action => $fields) {
		foreach ($fields as $field) {
			pg_assert_true(api_returns_test_param($update, $field) !== null, $action . ': ' . $field . ' is declared');
		}
	}

	foreach ($limits as $field => $max) {
		pg_assert_true(api_returns_test_param($update, $field)['max_length'] <= $max, $field . ' fits its column');
	}
}

function test_api_returns_action_check_moves_from_the_screens_statuses()
{
	$cases = array(
		array('requested', 'approve', true),
		array('requested', 'receive', false),
		array('approved', 'ship', true),
		array('approved', 'receive', true),
		array('shipped', 'receive', true),
		array('received', 'close', true),
		array('requested', 'refund', false),
		array('closed', 'close', false),
		array('rejected', 'approve', false),
	);

	foreach ($cases as $case) {
		$checked = api_return_action_check(api_returns_test_row($case[0]), array('action' => $case[1]), array());

		pg_assert_same($case[2], $checked['ok'], $case[0] . ' -> ' . $case[1]);

		if (!$case[2]) {
			pg_assert_same(409, $checked['http']);
			pg_assert_same('return_not_movable', $checked['code']);
		}
	}

	$checked = api_return_action_check(api_returns_test_row('approved'), array('action' => 'receive'), array());

	pg_assert_same('received', $checked['to']);
	pg_assert_same('approved', $checked['options']['status_was'], 'the status read is compared again under the lock');
}

function test_api_returns_action_check_refuses_a_stale_status_and_a_foreign_field()
{
	$checked = api_return_action_check(api_returns_test_row('received'), array('action' => 'close', 'status_was' => 'refunded'), array());

	pg_assert_false($checked['ok']);
	pg_assert_same('return_changed', $checked['code']);
	pg_assert_same('status_was', $checked['field']);

	$checked = api_return_action_check(api_returns_test_row('approved'), array('action' => 'receive', 'tracking_number' => 'YK1'), array());

	pg_assert_false($checked['ok'], 'a field the action does not read is refused, not dropped');
	pg_assert_same(422, $checked['http']);
	pg_assert_same('tracking_number', $checked['field']);
}

function test_api_returns_reject_needs_a_note()
{
	$checked = api_return_action_check(api_returns_test_row('requested'), array('action' => 'reject', 'note' => '  '), array());

	pg_assert_false($checked['ok']);
	pg_assert_same('note', $checked['field']);

	$checked = api_return_action_check(api_returns_test_row('requested'), array('action' => 'reject', 'note' => 'Kullanılmış ürün.'), array());

	pg_assert_true($checked['ok']);
	pg_assert_same('rejected', $checked['to']);
	pg_assert_same('Kullanılmış ürün.', $checked['options']['note']);
}

function test_api_returns_approve_takes_the_screens_suggestion()
{
	$context = array(
		'suggested' => array('carrier' => 'yurtici', 'carrier_code' => 'Y-100'),
		'codes'     => array('yurtici' => 'Y-100', 'Aras Ekspres' => 'A-200'),
	);

	$checked = api_return_action_check(api_returns_test_row('requested'), array('action' => 'approve'), $context);

	pg_assert_same('yurtici', $checked['options']['carrier']);
	pg_assert_same('Y-100', $checked['options']['carrier_code']);
	pg_assert_same('', $checked['options']['note']);

	$checked = api_return_action_check(api_returns_test_row('requested'), array('action' => 'approve', 'carrier' => 'Aras Ekspres'), $context);

	pg_assert_same('A-200', $checked['options']['carrier_code'], 'a carrier sent alone takes its own latest code');

	$checked = api_return_action_check(api_returns_test_row('requested'), array('action' => 'approve', 'carrier' => 'Sürat', 'carrier_code' => ''), $context);

	pg_assert_same('', $checked['options']['carrier_code'], 'an empty code is kept empty');

	$checked = api_return_action_check(api_returns_test_row('requested'), array('action' => 'approve', 'carrier_code' => 'X-9'), $context);

	pg_assert_same('yurtici', $checked['options']['carrier']);
	pg_assert_same('X-9', $checked['options']['carrier_code']);
}

function test_api_returns_ship_keeps_the_approvals_carrier_when_none_is_sent()
{
	$checked = api_return_action_check(api_returns_test_row('approved'), array('action' => 'ship', 'tracking_number' => 'YK55', 'carrier' => ''), array());

	pg_assert_true($checked['ok']);
	pg_assert_same('YK55', $checked['options']['tracking_number']);
	pg_assert_false(array_key_exists('carrier', $checked['options']));
}

function test_api_returns_refund_checks()
{
	$plan = array('driver' => 'iyzico', 'remaining' => 5000);
	$row = api_returns_test_row('received');

	$checked = api_return_action_check($row, array('action' => 'refund', 'method' => 'driver'), array('plan' => $plan));
	pg_assert_same('amount', $checked['field'], 'amount is required');

	$checked = api_return_action_check($row, array('action' => 'refund', 'amount' => 100), array('plan' => $plan));
	pg_assert_same('method', $checked['field'], 'method is required');

	$checked = api_return_action_check($row, array('action' => 'refund', 'amount' => 0, 'method' => 'driver'), array('plan' => $plan));
	pg_assert_same('amount', $checked['field'], 'a driver refund moves money');

	$checked = api_return_action_check($row, array('action' => 'refund', 'amount' => 5001, 'method' => 'manual'), array('plan' => $plan));
	pg_assert_same('amount', $checked['field'], 'never more than is left');

	$checked = api_return_action_check($row, array('action' => 'refund', 'amount' => 100, 'method' => 'driver'), array('plan' => array('driver' => '', 'remaining' => 5000)));
	pg_assert_same(409, $checked['http']);
	pg_assert_same('refund_driver_unavailable', $checked['code']);

	$checked = api_return_action_check($row, array('action' => 'refund', 'amount' => 0, 'method' => 'manual', 'reference' => 'EFT-1'), array('plan' => $plan));
	pg_assert_true($checked['ok'], 'a manual 0 marks it refunded');
	pg_assert_same(array('amount' => 0, 'method' => 'manual', 'reference' => 'EFT-1'), $checked['options']);

	$checked = api_return_action_check($row, array('action' => 'refund', 'amount' => 5000, 'method' => 'driver', 'reference' => 'X'), array('plan' => $plan));
	pg_assert_same('', $checked['options']['reference'], 'a driver refund keeps the service\'s own id');
	pg_assert_false(array_key_exists('status_was', $checked['options']), 'the refund re-reads under its lock itself');
}

// The handler writes only through the screen's functions, after the check
// and the rehearsal stop, and names the application owner in the log.
function test_api_returns_update_writes_through_the_screens_functions()
{
	$body = api_returns_test_body('includes/api/resources/returns.php', 'api_returns_update');

	$check = strpos($body, 'api_return_action_check(');
	$stop = strpos($body, 'api_dry_run_stop(');
	$refund = strpos($body, 'pg_return_refund(');
	$move = strpos($body, 'pg_return_staff_move(');

	pg_assert_true(($check !== false) && ($stop > $check) && ($refund > $stop) && ($move > $stop), 'check, then the rehearsal stop, then the write');
	pg_assert_false(strpos($body, 'UPDATE order_returns'), 'no SQL of its own');
	pg_assert_contains("'log_user'", $body);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_return.php');

	pg_assert_contains('pg_return_staff_move(', $screen);
	pg_assert_contains('pg_return_refund(', $screen);
}

// The write functions say why they refused, so the API answers by the code
// and never by the sentence, and carry the caller's name into the log.
function test_api_returns_write_functions_answer_a_code()
{
	$move = api_returns_test_body('includes/fn/returns.php', 'pg_return_staff_move');

	foreach (array("'note_required'", "'busy'", "'changed'") as $code) {
		pg_assert_contains($code, $move);
	}

	pg_assert_contains('$log_user', $move);
	pg_assert_contains("unset(\$options['log_user'])", $move);

	$refund = api_returns_test_body('includes/fn/returns.php', 'pg_return_refund');

	foreach (array("'busy'", "'not_received'", "'no_driver'", "'amount_exceeds'", "'refused'", "'unclear'", "'no_customer'", "'credit'") as $code) {
		pg_assert_contains($code, $refund);
	}

	pg_assert_same(5, substr_count($move, ', $log_user);'), 'each log line of a move');
	pg_assert_same(5, substr_count($refund, ', $log_user);'), 'each log line of a refund');
}

function test_api_returns_write_refusals_map_to_codes()
{
	$body = api_returns_test_body('includes/api/resources/returns.php', 'api_return_write_refuse');

	foreach (array("'return_in_progress'", "'refund_refused'", "'refund_driver_unavailable'", "'return_changed'", "'service_unavailable'") as $code) {
		pg_assert_contains($code, $body);
	}

	$description = api_returns_test_route('returns.update')['description'];

	foreach (array('return_not_movable', 'return_changed', 'return_in_progress', 'refund_driver_unavailable', 'refund_refused') as $code) {
		pg_assert_contains('409 ' . $code, $description, 'every 409 is described on its route');
	}
}

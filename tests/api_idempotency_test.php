<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a write carrying an Idempotency-Key does with the row on file
 * for its key (api_idempotency_decision(), includes/api/idempotency.php),
 * including a retry that arrives while the first attempt is still running.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/idempotency.php');

// Nothing on file: the write runs, after reserving the key.
function test_api_idempotency_runs_with_nothing_on_file()
{
	pg_assert_same('run', api_idempotency_decision(null, 'h1', 1000));
}

// A finished request with the same fingerprint is answered from its record.
function test_api_idempotency_replays_a_finished_request()
{
	$row = array('request_hash' => 'h1', 'status_code' => '200', 'created_timestamp' => '900');

	pg_assert_same('replay', api_idempotency_decision($row, 'h1', 1000));
}

// The same key with another body is refused, finished or not.
function test_api_idempotency_refuses_a_key_reused_for_another_request()
{
	pg_assert_same('reused', api_idempotency_decision(array('request_hash' => 'h1', 'status_code' => '201', 'created_timestamp' => '900'), 'h2', 1000), 'finished');
	pg_assert_same('reused', api_idempotency_decision(array('request_hash' => 'h1', 'status_code' => '0', 'created_timestamp' => '999'), 'h2', 1000), 'still running');
}

// A retry that arrives while the first attempt holds the key is not run: that
// is the overlapping retry that used to apply the write twice.
function test_api_idempotency_holds_a_retry_while_the_first_attempt_runs()
{
	$row = array('request_hash' => 'h1', 'status_code' => '0', 'created_timestamp' => '990');

	pg_assert_same('in_progress', api_idempotency_decision($row, 'h1', 1000));
	pg_assert_same('in_progress', api_idempotency_decision($row, 'h1', 990 + api_idempotency_pending_ttl()), 'up to the last second');
}

// A reservation whose request never answered is taken over once it is older
// than the window, so a request killed hard does not lock its key for a day.
function test_api_idempotency_takes_over_a_stale_reservation()
{
	$row = array('request_hash' => 'h1', 'status_code' => '0', 'created_timestamp' => '990');

	pg_assert_same('stale', api_idempotency_decision($row, 'h1', 991 + api_idempotency_pending_ttl()));
}

// A body JSON can write keeps the text its fingerprint has always been taken
// over, so a key recorded before still matches its retry.
function test_api_idempotency_input_text_is_json_for_a_readable_body()
{
	$input = array('name' => 'Çay', 'price' => 1250, 'tags' => array('a', 'b'));

	pg_assert_same(json_encode($input), api_idempotency_input_text($input));
}

// Bytes that are not UTF-8 (a form field from a client encoding Windows-1254)
// made json_encode() fail, and every such body read as the same request: the
// same key with another body was answered from the first one's record.
function test_api_idempotency_input_text_tells_apart_bodies_json_cannot_write()
{
	$first = api_idempotency_input_text(array('name' => "x\xFDy"));
	$second = api_idempotency_input_text(array('name' => "x\xFEy"));

	pg_assert_true(is_string($first) && ($first !== ''), 'a text to hash');
	pg_assert_true($first !== $second, 'two bodies, two fingerprints');
	pg_assert_same($first, api_idempotency_input_text(array('name' => "x\xFDy")), 'the same body, the same fingerprint');
	pg_assert_true(api_idempotency_input_text(array('n' => INF)) !== api_idempotency_input_text(array('n' => -INF)), 'INF and -INF too');
}

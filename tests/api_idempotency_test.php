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

// A field a route marks 'secret' (a password) enters the fingerprint as an
// HMAC keyed with the site's key, never as sent: the fingerprint is kept for a
// day beside the key, where a plain SHA-256 over a short password is a fast
// offline guess.
function test_api_idempotency_fingerprint_keys_a_secret_field()
{
	$route = array('params' => array(
		array('name' => 'audience', 'in' => 'body', 'type' => 'enum'),
		array('name' => 'password', 'in' => 'body', 'type' => 'string', 'secret' => true),
	));

	$input = api_idempotency_secret_input($route, array('audience' => 'anyone', 'password' => 'hunter2x'), 'site-key');

	pg_assert_same('anyone', $input['audience'], 'other fields as they came');
	pg_assert_true($input['password'] !== 'hunter2x', 'not the password itself');
	pg_assert_false(strpos(api_idempotency_input_text($input), 'hunter2x') !== false, 'nowhere in the hashed text');
	pg_assert_false(strpos(api_idempotency_input_text($input), hash('sha256', 'hunter2x')) !== false, 'nor its plain hash');
	pg_assert_same($input, api_idempotency_secret_input($route, array('audience' => 'anyone', 'password' => 'hunter2x'), 'site-key'), 'the same request, the same fingerprint');
	pg_assert_true($input !== api_idempotency_secret_input($route, array('audience' => 'anyone', 'password' => 'hunter3x'), 'site-key'), 'another password, another fingerprint');
	pg_assert_true($input !== api_idempotency_secret_input($route, array('audience' => 'anyone', 'password' => 'hunter2x'), 'other-key'), 'keyed');
	pg_assert_same(array('audience' => 'team'), api_idempotency_secret_input($route, array('audience' => 'team'), 'site-key'), 'a secret not sent is not added');
	pg_assert_same(array('n' => 1), api_idempotency_secret_input(array(), array('n' => 1), 'site-key'), 'a route without params');
}

// The fields a route names in 'idempotency_redact' are recorded as null: a
// secret answered once (a share link's address) does not sit readable in
// api_idempotency for the day the row lives.
function test_api_idempotency_record_leaves_out_a_routes_redacted_fields()
{
	$answer = array('url' => 'https://example.com/pinegrap/prototype.php?link=secret', 'link' => array('id' => 4, 'url' => 'kept'));

	pg_assert_same(array('url' => null, 'link' => array('id' => 4, 'url' => 'kept')), api_idempotency_redacted($answer, array('url')), 'top-level fields only');
	pg_assert_same($answer, api_idempotency_redacted($answer, array()), 'nothing named');
	pg_assert_same($answer, api_idempotency_redacted($answer, array('token_url')), 'a name not in the answer is not added');
	pg_assert_same('text', api_idempotency_redacted('text', array('url')), 'not an object');

	// The route's list travels with the key to the line that records the
	// answer.
	$context = api_idempotency_context(3, 'k1', 'h1', array('url'));
	pg_assert_same(array('url'), $context['redact']);
	$context = api_idempotency_context(3, 'k1', 'h1');
	pg_assert_same(array(), $context['redact'], 'none unless the route names some');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/idempotency.php');
	pg_assert_contains("api_idempotency_redacted(\$response, \$context['redact'])", $source);
	pg_assert_contains("api_idempotency_secret_input(\$route, \$params,", $source);

	$bootstrap = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/bootstrap.php');
	pg_assert_contains("api_idempotency_context((int)\$app['id'], \$key, \$hash, isset(\$route['idempotency_redact']) ? (array)\$route['idempotency_redact'] : array());", $bootstrap);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the renewal of a Paraşüt access token Paraşüt answers 401 for
 * while the cache still holds it (includes/fn/parasut.php): the order
 * screen's buttons, the inbox and the VKN lookup forget the cached token,
 * renew it once and send the request again, as the ERP driver does
 * (erp_edoc_parasut_request()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The source of one function of includes/fn/parasut.php, from its
 * declaration to the next one.
 *
 * @param string $function
 * @return string
 */
function parasut_token_renewal_test_source($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

// The cache still holds the token sent: it is the one Paraşüt retired, so
// it is forgotten before the renewal. Another token in the cache (renewed
// meanwhile by another request, or forgotten by one) is renewed into
// without forgetting what that request wrote. Without a token sent or a
// cache there is nothing to renew.
function test_parasut_unauthorized_step()
{
	pg_assert_true(function_exists('parasut_unauthorized_step'), 'parasut_unauthorized_step() exists');

	pg_assert_same('forget', parasut_unauthorized_step('old', array('access_token' => 'old', 'expires_at' => time() + 3600)));
	pg_assert_same('renew', parasut_unauthorized_step('old', array('access_token' => 'new', 'expires_at' => time() + 3600)));
	pg_assert_same('renew', parasut_unauthorized_step('old', array('refresh_token' => 'r', 'expires_at' => 0)));
	pg_assert_same('none', parasut_unauthorized_step('old', array()));
	pg_assert_same('none', parasut_unauthorized_step('', array('access_token' => '', 'expires_at' => 0)));
	pg_assert_same('none', parasut_unauthorized_step(null, array('access_token' => 'old')));
}

// A 401 is sent once more with the renewed token; a second 401 (a company
// number the user cannot reach) is returned, never renewed again.
function test_parasut_request_renewing_sends_once_more_after_401()
{
	pg_assert_true(function_exists('parasut_request_renewing'), 'parasut_request_renewing() exists');

	$sent = array();
	$renewed = array();
	$answers = array(array('http_code' => 401, 'body' => null), array('http_code' => 200, 'body' => array('data' => array())));
	$send = function ($token) use (&$sent, &$answers) {
		$sent[] = $token;

		return array_shift($answers);
	};
	$renew = function ($token) use (&$renewed) {
		$renewed[] = $token;

		return 'fresh';
	};

	$result = parasut_request_renewing($send, 'old', $renew);
	pg_assert_same(200, $result['http_code']);
	pg_assert_same(array('old', 'fresh'), $sent);
	pg_assert_same(array('old'), $renewed);

	$sent = array();
	$renewed = array();
	$answers = array(array('http_code' => 401, 'body' => 'a'), array('http_code' => 401, 'body' => 'b'));
	$result = parasut_request_renewing($send, 'old', $renew);
	pg_assert_same(401, $result['http_code']);
	pg_assert_same('b', $result['body'], 'the second answer is returned');
	pg_assert_same(array('old', 'fresh'), $sent, 'one renewal per request');
	pg_assert_same(array('old'), $renewed);
}

// No renewal for an answer other than 401, a request without a token (the
// token endpoint), a caller that opts out, or a renewal that fails or
// hands back the same token.
function test_parasut_request_renewing_leaves_other_answers()
{
	$count = 0;
	$send = function ($token) use (&$count) {
		$count++;

		return array('http_code' => ($token === 'old' || $token === null) ? 401 : 200);
	};
	$never = function ($token) {
		pg_assert_true(false, 'no renewal');

		return 'fresh';
	};

	$ok = function ($token) use (&$count) {
		$count++;

		return array('http_code' => 404);
	};
	pg_assert_same(404, parasut_request_renewing($ok, 'old', $never)['http_code']);
	pg_assert_same(1, $count);

	$count = 0;
	pg_assert_same(401, parasut_request_renewing($send, null, $never)['http_code']);
	pg_assert_same(1, $count);

	$count = 0;
	pg_assert_same(401, parasut_request_renewing($send, 'old', null)['http_code']);
	pg_assert_same(1, $count);

	foreach (array('', 'old') as $failed) {
		$count = 0;
		$renew = function ($token) use ($failed) {
			return $failed;
		};
		pg_assert_same(401, parasut_request_renewing($send, 'old', $renew)['http_code']);
		pg_assert_same(1, $count, 'nothing sent again without a new token');
	}
}

// The callers keep the token they read at the start of a press; after a
// renewal their next requests go with the new one instead of renewing
// again (each renewal retires the token before it).
function test_parasut_token_in_use_follows_a_renewal()
{
	pg_assert_true(function_exists('parasut_token_in_use'), 'parasut_token_in_use() exists');

	pg_assert_same('t-unrenewed', parasut_token_in_use('t-unrenewed'));
	parasut_token_in_use('t-one', 't-two');
	pg_assert_same('t-two', parasut_token_in_use('t-one'));
	parasut_token_in_use('t-two', 't-three');
	pg_assert_same('t-three', parasut_token_in_use('t-one'), 'a chain of renewals ends on the last token');
	pg_assert_same('t-three', parasut_token_in_use('t-two'));
	pg_assert_same(null, parasut_token_in_use(null));
}

// _parasut_request() goes through the renewal, and the renewal forgets the
// retired token before it asks for a new one. The connect check of a code
// just exchanged opts out: its token is not the cached one.
function test_parasut_request_wires_the_renewal()
{
	$request = parasut_token_renewal_test_source('_parasut_request');
	pg_assert_contains('function _parasut_request($method, $url, $body = null, $token = null, $retry_429 = true, $renew_401 = true)', $request);
	pg_assert_contains('parasut_request_renewing(', $request);
	pg_assert_contains('parasut_token_in_use($token)', $request);

	$after = parasut_token_renewal_test_source('_parasut_token_after_401');
	$forget = strpos($after, 'parasut_token_forget();');
	$renew = strpos($after, 'parasut_get_token();');
	pg_assert_true(($forget !== false) && ($renew !== false) && ($forget < $renew), 'the retired token is forgotten before the renewal');
	pg_assert_contains('parasut_token_in_use($token, ', $after);

	$me = parasut_token_renewal_test_source('parasut_me');
	pg_assert_contains("_parasut_request('GET', _parasut_base() . '/v4/me?include=companies', null, \$token, true, false);", $me);
}

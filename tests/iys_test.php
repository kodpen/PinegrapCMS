<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the İYS client (includes/iys/client.php): the account check, the
 * requests, the consent a consent log row becomes, İYS dates, and how its
 * answers are read. İYS is not called and no database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

function iys_test_app($changes = array())
{
	return array_merge(array(
		'user'       => 'api-user',
		'password'   => 'p:ss',
		'iys_code'   => '123456',
		'brand_code' => '654321',
		'env'        => 'live',
		'live'       => false,
		'email'      => true,
		'enabled'    => true,
		'push_id'    => 0,
		'pull_after' => '',
	), $changes);
}

function iys_test_response($status, $data, $error = '')
{
	return array('status' => $status, 'body' => is_string($data) ? $data : (string) json_encode($data), 'error' => $error);
}

// The codes are digits; a switched-off account or a missing password calls nothing.
function test_iys_app_complete()
{
	pg_assert_true(pg_iys_app_complete(iys_test_app()));
	pg_assert_false(pg_iys_app_complete(null), 'none');
	pg_assert_false(pg_iys_app_complete(iys_test_app(array('enabled' => false))), 'off');
	pg_assert_false(pg_iys_app_complete(iys_test_app(array('password' => ''))), 'no password');
	pg_assert_false(pg_iys_app_complete(iys_test_app(array('brand_code' => ''))), 'no brand');
	pg_assert_false(pg_iys_app_complete(iys_test_app(array('iys_code' => '12/../3'))), 'not digits');
}

// The sandbox only for env test; the codes go into the path.
function test_iys_requests()
{
	pg_assert_same('https://api.iys.org.tr/oauth2/token', pg_iys_token_request(iys_test_app())['url']);
	pg_assert_same('https://api.sandbox.iys.org.tr/oauth2/token', pg_iys_token_request(iys_test_app(array('env' => 'test')))['url']);
	pg_assert_same(array('username' => 'api-user', 'password' => 'p:ss', 'grant_type' => 'password'), json_decode(pg_iys_token_request(iys_test_app())['body'], true));

	$brands = pg_iys_brands_request(iys_test_app(), 'tok');
	pg_assert_same('https://api.iys.org.tr/sps/123456/brands', $brands['url']);
	pg_assert_true(in_array('Authorization: Bearer tok', $brands['headers'], true));
	pg_assert_false(isset($brands['body']), 'a GET');

	$consent = pg_iys_consent_request(iys_test_app(), 'tok', array('type' => 'EPOSTA'));
	pg_assert_same('https://api.iys.org.tr/sps/123456/brands/654321/consents', $consent['url']);
	pg_assert_true(in_array('Content-Type: application/json', $consent['headers'], true));

	pg_assert_same('https://api.iys.org.tr/sps/123456/brands/654321/consents/changes?limit=999', pg_iys_changes_request(iys_test_app(), 'tok', '', 5000)['url']);
	pg_assert_same('https://api.iys.org.tr/sps/123456/brands/654321/consents/changes?limit=10&after=abc%3D', pg_iys_changes_request(iys_test_app(), 'tok', 'abc=', 10)['url']);
}

// Dates are Turkish local time both ways.
function test_iys_dates()
{
	pg_assert_same('2026-10-26 13:00:00', pg_iys_date(1793008800));
	pg_assert_same(1793008800, pg_iys_timestamp('2026-10-26 13:00:00'));
	pg_assert_same(1793008800, pg_iys_timestamp('2026-10-26 13:00:00.123'));
	pg_assert_same(0, pg_iys_timestamp('yesterday'));
	pg_assert_same(0, pg_iys_timestamp(null));
}

// A granted row is ONAY, a refused one RET; a pending row or no address is not sent.
function test_iys_consent_body()
{
	pg_assert_same(array(
		'consentDate'   => '2026-10-26 13:00:00',
		'source'        => 'HS_WEB',
		'recipient'     => 'ayse@example.com',
		'recipientType' => 'BIREYSEL',
		'status'        => 'ONAY',
		'type'          => 'EPOSTA',
	), pg_iys_consent_body(' ayse@example.com ', 'granted', 1793008800));
	pg_assert_same('RET', pg_iys_consent_body('ayse@example.com', 'refused', 1793008800)['status']);
	pg_assert_same(null, pg_iys_consent_body('ayse@example.com', 'pending', 1793008800), 'pending');
	pg_assert_same(null, pg_iys_consent_body('', 'granted', 1793008800), 'no address');
	pg_assert_same(null, pg_iys_consent_body('not an address', 'granted', 1793008800), 'bad address');
	pg_assert_same(null, pg_iys_consent_body('ayse@example.com', 'granted', 0), 'no date');
}

// A refusal of one row lets the next go; credentials, limits, outages and no answer stop the run.
function test_iys_answer_outcomes()
{
	pg_assert_same('ok', pg_iys_answer(iys_test_response(200, array('transactionId' => 'x')))['outcome']);

	$rejected = pg_iys_answer(iys_test_response(422, array('errors' => array(array('code' => 'H174', 'message' => "Bu izin <b>zaten</b>\nkayıtlı")))));
	pg_assert_same('rejected', $rejected['outcome']);
	pg_assert_same('H174: Bu izin zaten kayıtlı', $rejected['error']);
	pg_assert_same('rejected', pg_iys_answer(iys_test_response(400, ''))['outcome']);
	pg_assert_same('HTTP 400', pg_iys_answer(iys_test_response(400, ''))['error']);

	foreach (array(401, 403, 429, 500, 503, 302) as $status) {
		pg_assert_same('stop', pg_iys_answer(iys_test_response($status, array('message' => 'no')))['outcome'], (string) $status);
	}

	$none = pg_iys_answer(iys_test_response(0, '', 'Could not resolve host'));
	pg_assert_same('stop', $none['outcome']);
	pg_assert_same('Could not resolve host', $none['error']);
}

function test_iys_token_and_brands_parse()
{
	pg_assert_same('abc', pg_iys_token_parse(pg_iys_answer(iys_test_response(200, array('access_token' => 'abc', 'expires_in' => 7200)))));
	pg_assert_same('', pg_iys_token_parse(pg_iys_answer(iys_test_response(200, array('token' => 'abc')))));
	pg_assert_same('', pg_iys_token_parse(pg_iys_answer(iys_test_response(401, array('access_token' => 'abc')))));

	$list = array(array('brandCode' => 654321, 'name' => 'Mağaza'), array('name' => 'no code'));
	pg_assert_same(array('654321' => 'Mağaza'), pg_iys_brands_parse(pg_iys_answer(iys_test_response(200, $list))));
	pg_assert_same(array('654321' => 'Mağaza'), pg_iys_brands_parse(pg_iys_answer(iys_test_response(200, array('list' => $list)))));
}

// Only e-mail changes with a known status are read; the cursor comes along.
function test_iys_changes_parse()
{
	$read = pg_iys_changes_parse(pg_iys_answer(iys_test_response(200, array(
		'after' => 'cursor-2',
		'list'  => array(
			array('type' => 'EPOSTA', 'status' => 'RET', 'recipient' => 'ayse@example.com', 'consentDate' => '2026-10-26 13:00:00'),
			array('type' => 'EPOSTA', 'status' => 'ONAY', 'recipient' => 'can@example.com'),
			array('type' => 'MESAJ', 'status' => 'RET', 'recipient' => '+905321234567'),
			array('type' => 'EPOSTA', 'status' => 'BEKLIYOR', 'recipient' => 'x@example.com'),
			array('type' => 'EPOSTA', 'status' => 'RET', 'recipient' => array('x')),
			'junk',
		),
	))));

	pg_assert_same('cursor-2', $read['after']);
	pg_assert_same(array(
		array('email' => 'ayse@example.com', 'status' => 'refused', 'at' => 1793008800),
		array('email' => 'can@example.com', 'status' => 'approved', 'at' => 0),
	), $read['changes']);

	pg_assert_same(array('changes' => array(), 'after' => ''), pg_iys_changes_parse(pg_iys_answer(iys_test_response(500, ''))));
}

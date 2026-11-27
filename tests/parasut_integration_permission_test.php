<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Paraşüt user without the application integration permission:
 * GET /v4/me answers 200 for such a user and every company-scoped call 403
 * "User has no 3rd party integration permission for company: <id>". Connect
 * makes one company-scoped read and warns of it, and an error text with that
 * 403 says which Paraşüt setting to change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// Paraşüt's answer to a company-scoped call of a user without the permission.
function parasut_integration_permission_test_refusal()
{
	return array(
		'http_code' => 403,
		'body' => array('errors' => array(array(
			'title' => 'Forbidden',
			'detail' => 'User has no 3rd party integration permission for company: 123',
		))),
		'raw' => '{}',
	);
}

function test_parasut_integration_permission_refused_reads_the_403_text()
{
	pg_assert_true(parasut_integration_permission_refused(parasut_integration_permission_test_refusal()));

	pg_assert_true(parasut_integration_permission_refused(array(
		'http_code' => 403,
		'body' => null,
		'raw' => 'User has no 3rd party integration permission for company: 123',
	)), 'a body that is not JSON');

	pg_assert_false(parasut_integration_permission_refused(array(
		'http_code' => 403,
		'body' => array('errors' => array(array('title' => 'Forbidden', 'detail' => 'Bu işlem için yetkiniz yok'))),
	)), 'another 403');

	$unauthorized = parasut_integration_permission_test_refusal();
	$unauthorized['http_code'] = 401;
	pg_assert_false(parasut_integration_permission_refused($unauthorized), 'a 401');

	pg_assert_false(parasut_integration_permission_refused(array('http_code' => 200, 'body' => array('data' => array()))));
	pg_assert_false(parasut_integration_permission_refused(null));
}

// Paraşüt's own text comes first, the setting to change after it; another
// refusal reads as before.
function test_parasut_integration_permission_hint_in_the_error_texts()
{
	$hint = parasut_integration_permission_hint();
	$said = 'Forbidden: User has no 3rd party integration permission for company: 123';

	pg_assert_contains('Ayarlar › Kullanıcılar', $hint);
	pg_assert_contains('Uygulama Entegrasyonları', $hint);
	pg_assert_contains('Yetki Var', $hint);

	pg_assert_same($said . ' ' . $hint, _parasut_extract_error(parasut_integration_permission_test_refusal()));
	pg_assert_same($said . ' ' . $hint, erp_edoc_parasut_error_text(parasut_integration_permission_test_refusal()));

	$other = array('http_code' => 403, 'body' => array('errors' => array(array('title' => 'Forbidden', 'detail' => 'Bu işlem için yetkiniz yok'))));
	pg_assert_same('Forbidden: Bu işlem için yetkiniz yok', _parasut_extract_error($other));
	pg_assert_same('Forbidden: Bu işlem için yetkiniz yok', erp_edoc_parasut_error_text($other));

	pg_assert_same('HTTP 500', _parasut_extract_error(array('http_code' => 500, 'body' => null)));
}

// One read, the company's contacts one card long; a warning only for the
// permission's 403.
function test_parasut_integration_permission_warning_sends_one_read()
{
	$calls = array();
	$answer = parasut_integration_permission_test_refusal();
	$get = function ($path) use (&$calls, &$answer) {
		$calls[] = $path;

		return $answer;
	};

	$warning = parasut_integration_permission_warning('123', $get);
	pg_assert_same(array('/v4/123/contacts?page[size]=1'), $calls);
	pg_assert_contains('123', $warning);
	pg_assert_contains(parasut_integration_permission_hint(), $warning);

	foreach (array(
		'allowed' => array('http_code' => 200, 'body' => array('data' => array())),
		'another 403' => array('http_code' => 403, 'body' => array('errors' => array(array('detail' => 'Bu işlem için yetkiniz yok')))),
		'a 401' => array('http_code' => 401, 'body' => array('error' => 'invalid_token')),
		'no answer' => array('curl_error' => 'timeout'),
	) as $label => $answer) {
		$calls = array();
		pg_assert_same('', parasut_integration_permission_warning('123', $get), $label);
		pg_assert_same(1, count($calls), $label . ': one read');
	}

	$calls = array();
	pg_assert_same('', parasut_integration_permission_warning('', $get), 'no company');
	pg_assert_same(array(), $calls, 'no company: nothing sent');
}

// The connect report carries the warning; without one it is as before.
function test_parasut_integration_permission_warning_in_the_connect_report()
{
	$plain = parasut_connect_report(array('email' => 'a@example.com', 'company_id' => '123', 'company_name' => 'Demo', 'replaced_company_id' => ''));
	pg_assert_same(array(), $plain['warnings']);

	$refused = parasut_connect_report(array('email' => 'a@example.com', 'company_id' => '123', 'company_name' => 'Demo', 'replaced_company_id' => '', 'permission_warning' => 'W'));
	pg_assert_same(array('W'), $refused['warnings']);
	pg_assert_same($plain['notices'], $refused['notices']);
}

// Both ways in read once, after the company is saved, without the 429 wait
// or a renewal: Paraşüt may switch off a client that collects many 401/403
// answers.
function test_parasut_integration_permission_read_at_connect()
{
	$check = pg_test_function_source('parasut_integration_permission_check');
	pg_assert_contains("_parasut_request('GET', _parasut_v4_base() . \$path, null, (string) \$token, false, false)", $check);

	$after = pg_test_function_source('parasut_devhub_after_connect');
	$saved = strpos($after, 'UPDATE config SET parasut_company_id');
	$read = strpos($after, "'permission_warning' => parasut_integration_permission_check(\$company_id, (string) \$token['token'])");
	pg_assert_true(($saved !== false) && ($read !== false) && ($saved < $read), 'the portal connection reads after the company is saved');
	pg_assert_same(1, substr_count($after, 'parasut_integration_permission_check('));

	$code = pg_test_function_source('parasut_connect_with_code');
	$saved = strpos($code, '_parasut_token_cache_write(');
	$read = strpos($code, "'permission_warning' => parasut_integration_permission_check(\$company_id, (string) \$data['access_token'])");
	pg_assert_true(($saved !== false) && ($read !== false) && ($saved < $read), 'a pasted code reads after the token is saved');
	pg_assert_same(1, substr_count($code, 'parasut_integration_permission_check('));
}

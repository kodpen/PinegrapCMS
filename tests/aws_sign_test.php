<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for AWS Signature Version 4 (includes/fn/aws_sign.php) against
 * cases of the AWS SigV4 test suite (tests/fixtures/aws_sigv4/, from
 * awslabs/aws-c-auth, Apache License 2.0): the canonical request, the
 * string to sign and the signature of each, signed by headers and by query.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/aws_sign.php');

// One case: its context, and request.txt read into method, path, query,
// headers and body.
function aws_sign_test_case($name)
{
	$directory = dirname(__FILE__) . '/fixtures/aws_sigv4/' . $name;
	$context = json_decode(file_get_contents($directory . '/context.json'), true);
	$raw = str_replace("\r\n", "\n", file_get_contents($directory . '/request.txt'));
	$parts = explode("\n\n", $raw, 2);
	$lines = explode("\n", $parts[0]);
	$first = explode(' ', array_shift($lines));
	array_pop($first);
	$method = array_shift($first);
	$target = implode(' ', $first);
	$question = strpos($target, '?');
	$headers = array();

	foreach ($lines as $line) {
		if ($line === '') {
			continue;
		}

		$colon = strpos($line, ':');
		$headers[] = array(substr($line, 0, $colon), substr($line, $colon + 1));
	}

	$read = function ($file) use ($directory) {
		return is_file($directory . '/' . $file) ? str_replace("\r\n", "\n", file_get_contents($directory . '/' . $file)) : null;
	};

	return array(
		'context' => $context,
		'method'  => $method,
		'path'    => ($question === false) ? $target : substr($target, 0, $question),
		'query'   => ($question === false) ? '' : substr($target, $question + 1),
		'headers' => $headers,
		'body'    => isset($parts[1]) ? $parts[1] : '',
		'amz'     => '20150830T123600Z',
		'read'    => $read,
	);
}

function aws_sign_test_names()
{
	$names = array();

	foreach (glob(dirname(__FILE__) . '/fixtures/aws_sigv4/*', GLOB_ONLYDIR) as $directory) {
		$names[] = basename($directory);
	}

	return $names;
}

function test_aws_sign_header_signing_matches_the_suite()
{
	$names = aws_sign_test_names();
	pg_assert_true(count($names) >= 10);

	foreach ($names as $name) {
		$case = aws_sign_test_case($name);
		$read = $case['read'];
		$headers = $case['headers'];
		$headers[] = array('X-Amz-Date', $case['amz']);
		$hash = hash('sha256', $case['body']);

		if ($case['context']['sign_body']) {
			$headers[] = array('X-Amz-Content-Sha256', $hash);
		}

		$canonical = pg_aws_canonical_request($case['method'], $case['path'], $case['query'], $headers, $hash, $case['context']['normalize']);
		pg_assert_same(rtrim($read('header-canonical-request.txt'), "\n"), $canonical, $name . ' canonical request');

		$scope = pg_aws_scope($case['amz'], $case['context']['region'], $case['context']['service']);
		$to_sign = pg_aws_string_to_sign($case['amz'], $scope, $canonical);
		pg_assert_same(rtrim($read('header-string-to-sign.txt'), "\n"), $to_sign, $name . ' string to sign');

		$key = pg_aws_signing_key($case['context']['credentials']['secret_access_key'], '20150830', $case['context']['region'], $case['context']['service']);
		preg_match('/Signature=([0-9a-f]{64})/', $read('header-signed-request.txt'), $expected);
		pg_assert_same($expected[1], hash_hmac('sha256', $to_sign, $key), $name . ' signature');
	}
}

function test_aws_sign_query_signing_matches_the_suite()
{
	foreach (aws_sign_test_names() as $name) {
		$case = aws_sign_test_case($name);
		$read = $case['read'];

		if ($read('query-signed-request.txt') === null) {
			continue;
		}

		$extra = array();

		foreach ($case['headers'] as $header) {
			if (strtolower($header[0]) !== 'host') {
				$extra[] = $header;
			}
		}

		$url = 'https://example.amazonaws.com' . str_replace('%2F', '/', rawurlencode($case['path'])) . (($case['query'] !== '') ? '?' . $case['query'] : '');
		$key = array('id' => 'AKIDEXAMPLE', 'secret' => $case['context']['credentials']['secret_access_key']);
		$signed = pg_aws_presign($key, 'us-east-1', 'service', $case['method'], $url, 3600, $extra, hash('sha256', $case['body']), $case['amz'], $case['context']['normalize']);

		preg_match('/X-Amz-Signature=([0-9a-f]{64})/', $read('query-signed-request.txt'), $expected);
		preg_match('/X-Amz-Signature=([0-9a-f]{64})/', $signed, $got);
		pg_assert_same($expected[1], isset($got[1]) ? $got[1] : '', $name . ' presigned');
	}
}

function test_aws_sign_authorization_headers()
{
	$case = aws_sign_test_case('post-x-www-form-urlencoded');
	$read = $case['read'];
	$lines = pg_aws_authorization(
		array('id' => 'AKIDEXAMPLE', 'secret' => 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY'),
		'us-east-1', 'service', 'POST', 'https://example.amazonaws.com/',
		array(array('Content-Type', 'application/x-www-form-urlencoded'), array('Content-Length', '13')),
		'Param1=value1', '20150830T123600Z');

	preg_match('/Authorization:(.*)/', $read('header-signed-request.txt'), $expected);
	pg_assert_same('Authorization: ' . trim($expected[1]), end($lines));
	pg_assert_same('X-Amz-Date: 20150830T123600Z', $lines[0]);
	pg_assert_same('X-Amz-Content-Sha256: 9095672bbd1f56dfc5b65f3e153adc8731a4a654192329106275f4c7b24d0b6e', $lines[1]);

	$vanilla = aws_sign_test_case('get-vanilla');
	$lines = pg_aws_authorization(array('id' => 'AKIDEXAMPLE', 'secret' => 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY'),
		'us-east-1', 'service', 'GET', 'https://example.amazonaws.com/', array(), '', '20150830T123600Z', array('sign_body' => false));
	preg_match('/Authorization:(.*)/', call_user_func($vanilla['read'], 'header-signed-request.txt'), $expected);
	pg_assert_same(array('X-Amz-Date: 20150830T123600Z', 'Authorization: ' . trim($expected[1])), $lines);
}

// The worked example of the AWS documentation (IAM, signing key).
function test_aws_sign_signing_key_example()
{
	pg_assert_same('c4afb1cc5771d871763a393e44b703571b55cc28424d1a5e86da6ed3c154a4b9',
		bin2hex(pg_aws_signing_key('wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '20150830', 'us-east-1', 'iam')));
}

function test_aws_sign_path_without_normalizing()
{
	pg_assert_same('/a/./b//c%20d', pg_aws_canonical_path('/a/./b//c d', false));
	pg_assert_same('/a/b/c%20d', pg_aws_canonical_path('/a/./b//c d'));
	pg_assert_same('/', pg_aws_canonical_path(''));
	pg_assert_same('/a/', pg_aws_canonical_path('/a/b/..'));
}

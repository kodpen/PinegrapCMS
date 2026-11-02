<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/aws_sign.php against the AWS SigV4 signing test
 * suite (tests/fixtures/aws_sigv4/<vector>/) and the Amazon S3 examples of
 * the SigV4 documentation (s3_examples.json): the canonical request, the
 * string to sign and the signature, signed in the headers and presigned.
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

function object_storage_sign_test_dir()
{
	return dirname(__FILE__) . '/fixtures/aws_sigv4';
}

/**
 * A vector's request.txt read into method, path, query pairs, header pairs
 * and body. The request line ends with " HTTP/1.1"; the path may hold a
 * space.
 */
function object_storage_sign_test_request($text)
{
	$text = str_replace("\r\n", "\n", $text);
	$parts = explode("\n\n", $text, 2);
	$lines = explode("\n", $parts[0]);
	$first = preg_replace('/ HTTP\/1\.1$/', '', array_shift($lines));
	list($method, $target) = explode(' ', $first, 2);

	$path = $target;
	$query = array();

	if (strpos($target, '?') !== false) {
		list($path, $raw) = explode('?', $target, 2);
		foreach (explode('&', $raw) as $piece) {
			$pair = explode('=', $piece, 2);
			$query[] = array($pair[0], isset($pair[1]) ? $pair[1] : '');
		}
	}

	$headers = array();

	foreach ($lines as $line) {
		if ($line === '') {
			continue;
		}
		$pair = explode(':', $line, 2);
		$headers[] = array($pair[0], $pair[1]);
	}

	return array(
		'method'  => $method,
		'path'    => $path,
		'query'   => $query,
		'headers' => $headers,
		'body'    => isset($parts[1]) ? rtrim($parts[1], "\n") : '',
	);
}

function object_storage_sign_test_vectors()
{
	$vectors = array();

	foreach (glob(object_storage_sign_test_dir() . '/*', GLOB_ONLYDIR) as $dir) {
		$vectors[basename($dir)] = $dir;
	}

	ksort($vectors);

	return $vectors;
}

function object_storage_sign_test_read($dir, $file)
{
	return rtrim(str_replace("\r\n", "\n", file_get_contents($dir . '/' . $file)), "\n");
}

function test_object_storage_sign_vectors_present()
{
	pg_assert_true(count(object_storage_sign_test_vectors()) >= 15, 'the AWS vectors are in tests/fixtures/aws_sigv4');
}

function test_object_storage_sign_header_vectors()
{
	foreach (object_storage_sign_test_vectors() as $name => $dir) {
		$context = json_decode(file_get_contents($dir . '/context.json'), true);
		$request = object_storage_sign_test_request(file_get_contents($dir . '/request.txt'));
		$time = strtotime($context['timestamp']);
		$payload_hash = hash('sha256', $request['body']);

		if (!empty($context['sign_body'])) {
			$request['headers'][] = array('x-amz-content-sha256', $payload_hash);
		}

		$signed = pg_aws_authorization(array(
			'method'       => $request['method'],
			'path'         => $request['path'],
			'query'        => $request['query'],
			'headers'      => $request['headers'],
			'payload_hash' => $payload_hash,
		), array(
			'key'    => $context['credentials']['access_key_id'],
			'secret' => $context['credentials']['secret_access_key'],
		), $context['region'], $context['service'], $time);

		pg_assert_same(object_storage_sign_test_read($dir, 'header-canonical-request.txt'), $signed['canonical_request'], $name . ': canonical request');
		pg_assert_same(object_storage_sign_test_read($dir, 'header-string-to-sign.txt'), $signed['string_to_sign'], $name . ': string to sign');
		pg_assert_same(object_storage_sign_test_read($dir, 'header-signature.txt'), $signed['signature'], $name . ': signature');
	}
}

function test_object_storage_sign_query_vectors()
{
	foreach (object_storage_sign_test_vectors() as $name => $dir) {
		if (!is_file($dir . '/query-signature.txt')) {
			continue;
		}

		$context = json_decode(file_get_contents($dir . '/context.json'), true);
		$request = object_storage_sign_test_request(file_get_contents($dir . '/request.txt'));

		$signed = pg_aws_presign(array(
			'method'       => $request['method'],
			'path'         => $request['path'],
			'query'        => $request['query'],
			'headers'      => $request['headers'],
			'payload_hash' => hash('sha256', $request['body']),
		), array(
			'key'    => $context['credentials']['access_key_id'],
			'secret' => $context['credentials']['secret_access_key'],
		), $context['region'], $context['service'], strtotime($context['timestamp']), $context['expiration_in_seconds']);

		pg_assert_same(object_storage_sign_test_read($dir, 'query-canonical-request.txt'), $signed['canonical_request'], $name . ': presigned canonical request');
		pg_assert_same(object_storage_sign_test_read($dir, 'query-signature.txt'), $signed['signature'], $name . ': presigned signature');
		pg_assert_true(substr($signed['query'], -80) === 'X-Amz-Signature=' . $signed['signature'], $name . ': signature last');
	}
}

function test_object_storage_sign_s3_examples()
{
	$examples = json_decode(file_get_contents(object_storage_sign_test_dir() . '/s3_examples.json'), true);
	$time = strtotime($examples['timestamp']);

	foreach ($examples['header'] as $example) {
		$signed = pg_aws_authorization($example, $examples['credentials'], $examples['region'], $examples['service'], $time);
		pg_assert_same($example['signature'], $signed['signature'], $example['name']);
	}

	foreach ($examples['presign'] as $example) {
		$signed = pg_aws_presign($example, $examples['credentials'], $examples['region'], $examples['service'], $time, $example['expires']);
		pg_assert_same($example['signature'], $signed['signature'], $example['name']);
		pg_assert_true(strpos($signed['query'], 'X-Amz-Credential=AKIAIOSFODNN7EXAMPLE%2F20130524%2Fus-east-1%2Fs3%2Faws4_request') !== false, 'credential encoded in the query');
	}
}

function test_object_storage_sign_uri_encode()
{
	pg_assert_same('a~b-c_d.e', pg_aws_uri_encode('a~b-c_d.e', false));
	pg_assert_same('a%20b%2Fc', pg_aws_uri_encode('a b/c', false));
	pg_assert_same('/a%20b/c%2B', pg_aws_canonical_path('/a b/c+'));
	pg_assert_same('/', pg_aws_canonical_path(''));
	pg_assert_same('%C3%A7', pg_aws_uri_encode('ç', false));
}

function test_object_storage_sign_query_sorted_by_name_then_value()
{
	pg_assert_same('a=1&a=2&b=', pg_aws_canonical_query(array(array('b', ''), array('a', '2'), array('a', '1'))));
	pg_assert_same('list-type=2&prefix=a%2Fb', pg_aws_canonical_query(array('prefix' => 'a/b', 'list-type' => '2')));
}

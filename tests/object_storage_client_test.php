<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/object_storage.php: settings
 * normalised and checked, object addresses in both styles, presigned
 * addresses, the answers of the service read (errors, headers, lists) and
 * the parts of a multipart upload. Also the upload option of
 * pg_curl_run_options(). The presigned addresses need the SigV4 signer
 * (includes/fn/aws_sign.php) and are skipped without it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/object_storage.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');

function object_storage_client_test_config($values = array())
{
	return pg_os_normalize($values + array(
		'endpoint' => 'https://storage.example.com',
		'region'   => 'eu-1',
		'bucket'   => 'media',
		'key_id'   => 'AKID',
		'secret'   => 'SECRET',
	)) + array('enabled' => true);
}

function test_object_storage_client_normalize()
{
	$config = pg_os_normalize(array(
		'endpoint'   => ' storage.example.com:9000/ ',
		'region'     => '',
		'bucket'     => ' media ',
		'prefix'     => '/site//files/',
		'path_style' => '1',
		'key_id'     => ' AKID ',
		'secret'     => 'S',
	));

	pg_assert_same('https://storage.example.com:9000', $config['endpoint']);
	pg_assert_same('us-east-1', $config['region'], 'empty region defaults');
	pg_assert_same('media', $config['bucket']);
	pg_assert_same('site/files', $config['prefix']);
	pg_assert_same(true, $config['path_style']);
	pg_assert_same('AKID', $config['key_id']);
	pg_assert_same('http://minio.lan', pg_os_normalize(array('endpoint' => 'http://minio.lan/'))['endpoint'], 'http kept');
}

function test_object_storage_client_config_errors()
{
	pg_assert_same(array(), pg_os_config_errors(object_storage_client_test_config()));

	$errors = pg_os_config_errors(pg_os_normalize(array()));
	foreach (array('object_storage_endpoint', 'object_storage_bucket', 'object_storage_key_id', 'object_storage_secret') as $field) {
		pg_assert_true(isset($errors[$field]), $field . ' required');
	}

	$bad = pg_os_config_errors(object_storage_client_test_config(array('endpoint' => 'ftp://x.example.com')));
	pg_assert_true(isset($bad['object_storage_endpoint']), 'ftp refused');

	$bad = pg_os_config_errors(object_storage_client_test_config(array('endpoint' => 'https://u:p@x.example.com')));
	pg_assert_true(isset($bad['object_storage_endpoint']), 'credentials in the address refused');

	$bad = pg_os_config_errors(object_storage_client_test_config(array('bucket' => 'My_Bucket')));
	pg_assert_true(isset($bad['object_storage_bucket']), 'not a host name without path style');
	pg_assert_same(array(), pg_os_config_errors(object_storage_client_test_config(array('bucket' => 'My_Bucket', 'path_style' => true))));

	$bad = pg_os_config_errors(object_storage_client_test_config(array('prefix' => 'a/../b')));
	pg_assert_true(isset($bad['object_storage_prefix']), '.. refused');

	$bad = pg_os_config_errors(object_storage_client_test_config(array('region' => 'eu west')));
	pg_assert_true(isset($bad['object_storage_region']), 'space in region refused');
}

function test_object_storage_client_ready()
{
	pg_assert_true(pg_os_ready(object_storage_client_test_config()));
	pg_assert_false(pg_os_ready(array('enabled' => false) + object_storage_client_test_config()), 'switched off');
	pg_assert_false(pg_os_ready(object_storage_client_test_config(array('secret' => ''))), 'no secret');
}

function test_object_storage_client_target_virtual_hosted()
{
	$config = object_storage_client_test_config(array('prefix' => 'site one'));
	$target = pg_os_target($config, pg_os_object_key($config, '/files/a b+c.pdf'));

	pg_assert_same('media.storage.example.com', $target['host']);
	pg_assert_same('/site one/files/a b+c.pdf', $target['path']);
	pg_assert_same('https://media.storage.example.com/site%20one/files/a%20b%2Bc.pdf', $target['url']);
}

function test_object_storage_client_target_path_style()
{
	$config = object_storage_client_test_config(array('endpoint' => 'http://10.0.0.5:9000/s3', 'path_style' => true));
	$target = pg_os_target($config, 'x/y.txt', array('partNumber' => '2', 'uploadId' => 'a/b'));

	pg_assert_same('10.0.0.5:9000', $target['host']);
	pg_assert_same('/s3/media/x/y.txt', $target['path']);
	pg_assert_same('http', $target['scheme']);
	pg_assert_same('http://10.0.0.5:9000/s3/media/x/y.txt?partNumber=2&uploadId=a%2Fb', $target['url']);

	$bucket = pg_os_target($config, '', array('list-type' => '2'));
	pg_assert_same('/s3/media', $bucket['path'], 'the bucket itself');

	pg_assert_same(array(), pg_os_target(object_storage_client_test_config(array('endpoint' => 'ftp://x')), 'k'));
}

function test_object_storage_client_presign_matches_the_s3_example()
{
	if (!function_exists('pg_aws_presign')) {
		return;
	}

	$config = pg_os_normalize(array(
		'endpoint' => 'https://s3.amazonaws.com',
		'region'   => 'us-east-1',
		'bucket'   => 'examplebucket',
		'key_id'   => 'AKIAIOSFODNN7EXAMPLE',
		'secret'   => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
	)) + array('enabled' => true);

	$url = pg_os_presign($config, 'test.txt', 86400, array(), '20130524T000000Z');

	pg_assert_same('https://examplebucket.s3.amazonaws.com/test.txt?X-Amz-Algorithm=AWS4-HMAC-SHA256'
		. '&X-Amz-Credential=AKIAIOSFODNN7EXAMPLE%2F20130524%2Fus-east-1%2Fs3%2Faws4_request'
		. '&X-Amz-Date=20130524T000000Z&X-Amz-Expires=86400&X-Amz-SignedHeaders=host'
		. '&X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404', $url);
}

function test_object_storage_client_presign_overrides_are_signed()
{
	if (!function_exists('pg_aws_presign')) {
		return;
	}

	$config = object_storage_client_test_config();
	$url = pg_os_presign($config, 'a.pdf', 300, array('content_type' => 'application/pdf', 'disposition' => 'attachment; filename="r.pdf"'), '20231114T221320Z');

	pg_assert_true(strpos($url, 'response-content-disposition=attachment%3B%20filename%3D%22r.pdf%22') !== false, 'disposition encoded');
	pg_assert_true(strpos($url, 'response-content-type=application%2Fpdf') !== false, 'type encoded');
	pg_assert_true(strpos($url, 'X-Amz-Expires=300') !== false);
	pg_assert_same('', pg_os_presign(array('enabled' => false) + $config, 'a.pdf'), 'nothing while switched off');
}

function test_object_storage_client_error_from_service()
{
	$error = pg_os_error(array('status' => 403, 'errno' => 0, 'error' => '',
		'body' => '<?xml version="1.0"?><Error><Code>SignatureDoesNotMatch</Code><Message>The request signature &amp; key</Message></Error>'));

	pg_assert_same(false, $error['ok']);
	pg_assert_same('SignatureDoesNotMatch', $error['code']);
	pg_assert_same(403, $error['status']);
	pg_assert_true(strpos($error['message'], 'The request signature & key') !== false, 'message decoded');

	pg_assert_same('not_found', pg_os_error(array('status' => 404, 'errno' => 0, 'error' => '', 'body' => ''))['code']);
	pg_assert_same('http_502', pg_os_error(array('status' => 502, 'errno' => 0, 'error' => '', 'body' => 'Bad gateway'))['code']);
	pg_assert_same('network', pg_os_error(array('status' => 0, 'errno' => 6, 'error' => 'Could not resolve host', 'body' => ''))['code']);
}

function test_object_storage_client_get_error_names_cap_and_disk()
{
	pg_assert_same('too_large', pg_os_get_error(array('status' => 0, 'errno' => 23, 'error' => '', 'body' => '', 'capped' => true, 'write_failed' => false))['code']);
	pg_assert_same('write_failed', pg_os_get_error(array('status' => 0, 'errno' => 23, 'error' => '', 'body' => '', 'capped' => false, 'write_failed' => true))['code']);
}

function test_object_storage_client_head_parse_takes_the_last_block()
{
	$head = pg_os_head_parse("HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\nETag: \"abc123\"\r\nContent-Length: 42\r\nContent-Type: image/png\r\nLast-Modified: Wed, 01 Jan 2025 00:00:00 GMT\r\n\r\n");

	pg_assert_same(array('size' => 42, 'etag' => 'abc123', 'content_type' => 'image/png', 'modified' => 1735689600), $head);
	pg_assert_same(-1, pg_os_head_parse('')['size']);
}

function test_object_storage_client_list_parse()
{
	$xml = '<ListBucketResult><IsTruncated>true</IsTruncated><NextContinuationToken>tok/1=</NextContinuationToken>'
		. '<Contents><Key>site/a &amp; b.zip</Key><LastModified>2025-01-01T00:00:00.000Z</LastModified><ETag>&quot;e1&quot;</ETag><Size>10</Size></Contents>'
		. '<Contents><Key>site/c.zip</Key><LastModified>2025-01-02T00:00:00.000Z</LastModified><ETag>"e2"</ETag><Size>20</Size></Contents>'
		. '</ListBucketResult>';

	$page = pg_os_list_parse($xml, 'site/');

	pg_assert_same(true, $page['truncated']);
	pg_assert_same('tok/1=', $page['next']);
	pg_assert_same(array('key' => 'a & b.zip', 'size' => 10, 'modified' => 1735689600, 'etag' => 'e1'), $page['items'][0]);
	pg_assert_same('c.zip', $page['items'][1]['key']);

	$empty = pg_os_list_parse('<ListBucketResult><IsTruncated>false</IsTruncated></ListBucketResult>', '');
	pg_assert_same(array('items' => array(), 'truncated' => false, 'next' => ''), $empty);
}

function test_object_storage_client_part_ranges()
{
	pg_assert_same(array(1 => array(0, 16), 2 => array(16, 16), 3 => array(32, 5)), pg_os_part_ranges(37, 16));
	pg_assert_same(array(1 => array(0, 16)), pg_os_part_ranges(16, 16));
	pg_assert_same(array(), pg_os_part_ranges(0, 16));
	pg_assert_true(pg_os_part_size() >= 5 * 1024 * 1024, 'S3 minimum part size');
	pg_assert_true(pg_os_multipart_threshold() >= pg_os_part_size());
}

function test_object_storage_client_complete_xml()
{
	pg_assert_same('<CompleteMultipartUpload><Part><PartNumber>1</PartNumber><ETag>"a"</ETag></Part><Part><PartNumber>2</PartNumber><ETag>"b&amp;"</ETag></Part></CompleteMultipartUpload>',
		pg_os_multipart_complete_xml(array(2 => '"b&"', 1 => 'a')));
}

function test_object_storage_client_object_key()
{
	pg_assert_same('k/x.txt', pg_os_object_key(object_storage_client_test_config(), '/k/x.txt'));
	pg_assert_same('p/k.txt', pg_os_object_key(object_storage_client_test_config(array('prefix' => 'p')), 'k.txt'));
}

function test_object_storage_client_curl_upload_option()
{
	$handle = fopen('php://memory', 'rb');
	$options = pg_curl_run_options('PUT', 'https://example.com/k', array('upload' => array('handle' => $handle, 'size' => 12)), array('user_agent' => 'Pinegrap/test', 'proxy' => '', 'resolve' => ''));

	pg_assert_same(true, $options['CURLOPT_UPLOAD']);
	pg_assert_same($handle, $options['CURLOPT_INFILE']);
	pg_assert_same(12, $options['CURLOPT_INFILESIZE']);
	pg_assert_same('PUT', $options['CURLOPT_CUSTOMREQUEST']);
	pg_assert_false(isset($options['CURLOPT_POSTFIELDS']));

	$options = pg_curl_run_options('PUT', 'https://example.com/k', array('body' => 'x', 'upload' => array('handle' => $handle, 'size' => 12)), array('user_agent' => 'Pinegrap/test', 'proxy' => '', 'resolve' => ''));
	pg_assert_false(isset($options['CURLOPT_UPLOAD']), 'a body wins');

	fclose($handle);
}

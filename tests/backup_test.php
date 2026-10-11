<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the automatic backup helpers in includes/fn/backup.php: which
 * names count as automatic backups, which ones a retention deletes, and the
 * Signature Version 4 pieces of the S3 upload.
 *
 * The SigV4 vectors are the IAM example from the AWS documentation ("Create a
 * signed AWS API request"): the example secret key with date 20150830,
 * region us-east-1, service iam.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The archive and the folder form of an automatic backup are both recognised.
function test_backup_is_auto_name_accepts_both_forms()
{
	pg_assert_true(pg_backup_is_auto_name('auto_backup_2026-10-41.zip'), 'zip');
	pg_assert_true(pg_backup_is_auto_name('auto_backup_2026-10-41'), 'folder');
	pg_assert_true(pg_backup_is_auto_name(pg_backup_auto_name(mktime(12, 0, 0, 10, 8, 2026))), 'generated name');
}

// Nothing else matches: install dumps, hand-named backups, near misses.
function test_backup_is_auto_name_refuses_everything_else()
{
	foreach (array('english_default', 'turkish_default', 'auto_backup_x', 'my_backup', 'auto_backup_2026-10-41.zip.bak', 'auto_backup_2026-10-41.ZIP', 'pre_upgrade_2026.4.8_1760000000', '.htaccess', '../auto_backup_2026-10-41', 'auto_backup_2026-10-41/', '') as $name) {
		pg_assert_false(pg_backup_is_auto_name($name), $name);
	}

	pg_assert_false(pg_backup_is_auto_name(null), 'null');
}

// Keep 2 of five weeks: the three oldest go, dumps and hand-named backups never.
function test_backup_prune_list_keeps_newest_weeks_only()
{
	$names = array(
		'.', '..', '.htaccess',
		'english_default', 'turkish_default', 'my_backup', 'site_2026-01-01@10-00',
		'auto_backup_2026-09-36.zip',
		'auto_backup_2026-10-41.zip',
		'auto_backup_2026-08-33',
		'auto_backup_2026-10-40.zip',
		'auto_backup_2026-09-38',
	);

	pg_assert_same(
		array('auto_backup_2026-09-38', 'auto_backup_2026-09-36.zip', 'auto_backup_2026-08-33'),
		pg_backup_prune_list($names, 2)
	);
}

// 0 (and anything below 1) means keep everything.
function test_backup_prune_list_zero_keeps_all()
{
	$names = array('auto_backup_2026-09-36.zip', 'auto_backup_2026-10-41.zip', 'auto_backup_2026-08-33');

	pg_assert_same(array(), pg_backup_prune_list($names, 0), 'zero');
	pg_assert_same(array(), pg_backup_prune_list($names, -3), 'negative');
}

// A week stored both ways counts once; its folder is superseded by the archive.
function test_backup_prune_list_archive_supersedes_folder_of_same_week()
{
	$names = array('auto_backup_2026-10-41', 'auto_backup_2026-10-41.zip', 'auto_backup_2026-10-40.zip');

	pg_assert_same(array('auto_backup_2026-10-41'), pg_backup_prune_list($names, 2), 'both weeks kept');
	pg_assert_same(array('auto_backup_2026-10-41', 'auto_backup_2026-10-40.zip'), pg_backup_prune_list($names, 1), 'one week kept');
}

// ISO weeks across New Year: 1 January 2027 is week 53, older than week 1.
function test_backup_prune_list_orders_new_year_weeks_by_time()
{
	$names = array(
		'auto_backup_2027-01-02.zip',  // 11-17 January 2027
		'auto_backup_2027-01-01.zip',  // 4-10 January 2027
		'auto_backup_2027-01-53.zip',  // 1-3 January 2027
		'auto_backup_2026-12-53.zip',  // 28-31 December 2026
	);

	pg_assert_same('auto_backup_2027-01-53', pg_backup_auto_name(mktime(12, 0, 0, 1, 2, 2027)), 'week 53 in January');
	pg_assert_same(
		array('auto_backup_2027-01-53.zip', 'auto_backup_2026-12-53.zip'),
		pg_backup_prune_list($names, 2)
	);

	// 29 December 2025 already belongs to ISO week 1 of 2026.
	pg_assert_same('auto_backup_2025-12-01', pg_backup_auto_name(mktime(12, 0, 0, 12, 29, 2025)), 'week 1 in December');
	pg_assert_same(
		array('auto_backup_2025-12-51.zip'),
		pg_backup_prune_list(array('auto_backup_2025-12-51.zip', 'auto_backup_2025-12-01.zip', 'auto_backup_2025-12-52.zip'), 2)
	);
}

// SigV4 signing key, AWS IAM example.
function test_backup_s3_signing_key_matches_aws_vector()
{
	pg_assert_same(
		'c4afb1cc5771d871763a393e44b703571b55cc28424d1a5e86da6ed3c154a4b9',
		bin2hex(pg_backup_s3_signing_key('wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '20150830', 'us-east-1', 'iam'))
	);
}

// SigV4 signature of the example string to sign.
function test_backup_s3_signature_matches_aws_vector()
{
	$string_to_sign = pg_backup_s3_string_to_sign(
		'20150830T123600Z',
		'20150830/us-east-1/iam/aws4_request',
		'f536975d06c0309214f805bb90ccff089219ecd68b2577efef23edd43b7e1a59'
	);

	pg_assert_same(
		"AWS4-HMAC-SHA256\n20150830T123600Z\n20150830/us-east-1/iam/aws4_request\nf536975d06c0309214f805bb90ccff089219ecd68b2577efef23edd43b7e1a59",
		$string_to_sign,
		'string to sign'
	);

	pg_assert_same(
		'5d672d79c15b13162d9279b0855cfba6789a8edb4c82c400e06b5924a6f2b5d7',
		pg_backup_s3_signature('wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '20150830', 'us-east-1', 'iam', $string_to_sign)
	);
}

// SigV4 canonical request of the example: headers lower-cased and sorted.
function test_backup_s3_canonical_request_matches_aws_vector()
{
	$canonical = pg_backup_s3_canonical_request(
		'GET',
		'/',
		'Action=ListUsers&Version=2010-05-08',
		array(
			'X-Amz-Date'   => '20150830T123600Z',
			'Host'         => 'iam.amazonaws.com',
			'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
		),
		'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
	);

	pg_assert_same('content-type;host;x-amz-date', $canonical['signed_headers'], 'signed headers');
	pg_assert_same('f536975d06c0309214f805bb90ccff089219ecd68b2577efef23edd43b7e1a59', hash('sha256', $canonical['request']), 'hash');

	// The same query given as pairs is encoded and sorted into the same string.
	$from_pairs = pg_backup_s3_canonical_request(
		'GET',
		'/',
		array('Version' => '2010-05-08', 'Action' => 'ListUsers'),
		array('Host' => 'iam.amazonaws.com', 'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8', 'X-Amz-Date' => '20150830T123600Z'),
		'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
	);

	pg_assert_same($canonical['request'], $from_pairs['request'], 'query from pairs');
}

// Object addresses: Amazon default, path style, virtual host, key encoding.
function test_backup_s3_target_builds_urls()
{
	$config = pg_backup_remote_normalize('s3', array(
		'region'     => 'eu-central-1',
		'bucket'     => 'site-backups',
		'prefix'     => '/weekly/',
		'access_key' => 'AK',
		'secret_key' => 'SK',
	));

	$amazon = pg_backup_s3_target($config, 'auto_backup_2026-10-41.zip');

	pg_assert_same('https://site-backups.s3.eu-central-1.amazonaws.com/weekly/auto_backup_2026-10-41.zip', $amazon['url'], 'virtual host');
	pg_assert_same('site-backups.s3.eu-central-1.amazonaws.com', $amazon['host'], 'virtual host header');
	pg_assert_same('eu-central-1', $amazon['region'], 'region');

	$config['endpoint'] = 'http://127.0.0.1:9000';
	$config['path_style'] = true;

	$minio = pg_backup_s3_target($config, 'a b+c.zip');

	pg_assert_same('http://127.0.0.1:9000/site-backups/weekly/a%20b%2Bc.zip', $minio['url'], 'path style');
	pg_assert_same('127.0.0.1:9000', $minio['host'], 'host keeps the port');
	pg_assert_same('/site-backups/weekly/a%20b%2Bc.zip', $minio['uri'], 'canonical uri');

	$config['endpoint'] = 'ftp://example.com';
	pg_assert_same(array(), pg_backup_s3_target($config, 'x.zip'), 'only http and https');
}

// The reason in an S3 error document is what the operator is shown.
function test_backup_s3_error_message_reads_xml()
{
	$body = '<?xml version="1.0" encoding="UTF-8"?><Error><Code>SignatureDoesNotMatch</Code><Message>The request signature we calculated does not match the signature you provided.</Message></Error>';

	pg_assert_contains('SignatureDoesNotMatch: The request signature we calculated does not match', pg_backup_s3_error_message(403, $body));
	pg_assert_contains('403', pg_backup_s3_error_message(403, $body));
}

// Settings are typed and defaulted whatever arrives.
function test_backup_remote_normalize_defaults()
{
	$ftp = pg_backup_remote_normalize('ftp', array('host' => ' ftp.example.com ', 'port' => '99999', 'tls' => '1'));

	pg_assert_same('ftp.example.com', $ftp['host'], 'host trimmed');
	pg_assert_same(21, $ftp['port'], 'bad port falls back to 21');
	pg_assert_true($ftp['tls'], 'tls');
	pg_assert_same('', $ftp['password'], 'password');

	$s3 = pg_backup_remote_normalize('s3', 'not an array');

	pg_assert_same('', $s3['bucket'], 'bucket');
	pg_assert_false($s3['path_style'], 'path style off');
}

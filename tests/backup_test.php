<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the automatic backup helpers in includes/fn/backup.php: which
 * names count as automatic backups, which ones a retention deletes, the
 * Signature Version 4 pieces of the S3 upload, and which tables the database
 * dump carries without their rows.
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

// The backup destination's own fields become the shared client's settings;
// an empty endpoint stays Amazon S3 in the region, as it always was.
function test_backup_s3_client_config_maps_the_card_fields()
{
	$config = pg_backup_s3_client_config(pg_backup_remote_normalize('s3', array(
		'region'     => 'eu-central-1',
		'bucket'     => 'site-backups',
		'prefix'     => '/weekly/',
		'access_key' => 'AK',
		'secret_key' => 'SK',
	)));

	pg_assert_same('https://s3.eu-central-1.amazonaws.com', $config['endpoint']);
	pg_assert_same('eu-central-1', $config['region']);
	pg_assert_same('weekly', $config['prefix']);
	pg_assert_same('AK', $config['key_id']);
	pg_assert_same('SK', $config['secret']);
	pg_assert_true(pg_os_ready($config), 'ready to send');

	// The key and address an archive is sent under, unchanged from the old
	// single PUT: the folder, then the archive's name.
	$target = pg_os_target($config, pg_os_object_key($config, 'auto_backup_2026-10-41.zip'));
	pg_assert_same('https://site-backups.s3.eu-central-1.amazonaws.com/weekly/auto_backup_2026-10-41.zip', $target['url'], 'virtual host');

	$minio = pg_backup_s3_client_config(pg_backup_remote_normalize('s3', array(
		'endpoint'   => 'http://127.0.0.1:9000',
		'bucket'     => 'site-backups',
		'prefix'     => 'weekly',
		'access_key' => 'AK',
		'secret_key' => 'SK',
		'path_style' => '1',
	)));

	pg_assert_same('us-east-1', $minio['region'], 'empty region');
	$target = pg_os_target($minio, pg_os_object_key($minio, 'a b+c.zip'));
	pg_assert_same('http://127.0.0.1:9000/site-backups/weekly/a%20b%2Bc.zip', $target['url'], 'path style');

	$ftp = pg_backup_s3_client_config(pg_backup_remote_normalize('s3', array('endpoint' => 'ftp://example.com', 'bucket' => 'b', 'access_key' => 'A', 'secret_key' => 'S')));
	pg_assert_same(array(), pg_os_target($ftp, ''), 'only http and https');
}

// Only the names directly in the backup folder take part in the retention.
function test_backup_s3_names_and_prune_choice()
{
	$items = array(
		array('key' => 'auto_backup_2026-10-41.zip'),
		array('key' => 'auto_backup_2026-10-42.zip'),
		array('key' => 'auto_backup_2026-11-44.zip'),
		array('key' => 'auto_backup_2026-11-45.zip'),
		array('key' => 'other/auto_backup_2026-01-01.zip'),
		array('key' => 'manual.zip'),
		array('key' => 'auto_backup_2026-11-45.zip'),
	);

	$names = pg_backup_s3_names($items);

	pg_assert_same(array('auto_backup_2026-10-41.zip', 'auto_backup_2026-10-42.zip', 'auto_backup_2026-11-44.zip', 'auto_backup_2026-11-45.zip', 'manual.zip'), $names);
	pg_assert_same(array('auto_backup_2026-10-42.zip', 'auto_backup_2026-10-41.zip'), pg_backup_prune_list($names, 2));
	pg_assert_same(array(), pg_backup_prune_list($names, 0), 'keep everything');
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

// Visit records, the page and form item view counters, the request logs and
// the downloaded Search Console figures go without rows; the summaries the
// dashboard and the visitor report read keep theirs, and so does every log of
// what someone did and every SEO or search table built from the content.
function test_backup_rowless_tables_scope()
{
	pg_assert_same(
		array('visitors', 'visitor_texts', 'visitor_content_hourly', 'submitted_form_views', 'submitted_form_view_stats', 'waf_log', 'perf_log', 'api_request_log', 'seo_search_daily'),
		array_keys(pg_backup_rowless_tables())
	);

	$settings = pg_backup_dump_settings();

	$kept_tables = array(
		'visitor_stats_daily', 'visitor_stats_hourly', 'submitted_form_info', 'visitor_reports', 'orders', 'config',
		'forms', 'form_data', 'contacts', 'ws_messages', 'perf_stats', 'log', 'cron_runs',
		'erp_audit_log', 'erp_edoc_log', 'audit_log', 'consent_log', 'mail_outbox', 'api_webhook_queue',
		'seo_issue', 'seo_link', 'search_items', 'search_results_pages', 'short_links', 'connect_accounts',
	);

	foreach ($kept_tables as $kept) {
		pg_assert_false(in_array($kept, $settings['no-data'], true), $kept . ' keeps its rows');
		pg_assert_false(in_array($kept, $settings['exclude-tables'], true), $kept . ' is dumped');
	}
}

// The move's working copies are left out altogether, visitors itself is not.
function test_backup_dump_settings_skip_visitor_move_tables()
{
	$settings = pg_backup_dump_settings();

	pg_assert_same(array('visitors_innodb', 'visitors_myisam'), $settings['exclude-tables']);
	pg_assert_false(in_array('visitors', $settings['exclude-tables'], true), 'visitors keeps its structure');
}

// Mysqldump refuses a setting it does not know; these are accepted.
function test_backup_dump_settings_accepted_by_mysqldump()
{
	include_once(PG_FUNCTIONS_DIR . '/mysqldump.php');

	$dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=127.0.0.1;dbname=pinegrap_test', 'user', 'pass', pg_backup_dump_settings());

	pg_assert_true($dump instanceof \Ifsnop\Mysqldump\Mysqldump);
}

// The note is SQL comments only, names every table and comes first.
function test_backup_dump_note_is_comments_naming_each_table()
{
	$note = pg_backup_dump_note();
	$lines = explode(PHP_EOL, rtrim($note, PHP_EOL));

	foreach ($lines as $line) {
		pg_assert_same('--', substr($line, 0, 2), $line);
	}

	foreach (array_keys(pg_backup_rowless_tables()) as $table) {
		pg_assert_contains('--   ' . $table . ' ', $note, $table);
	}

	pg_assert_same('-- Pinegrap database backup', $lines[0]);
}

// The estimate before a backup leaves out every table the dump carries
// without its rows or skips, read from the same list, and counts the rest.
function test_backup_estimate_bytes_leaves_out_rowless_and_skipped_tables()
{
	$table_bytes = array('orders' => 3000000, 'config' => 16384, 'log' => '500000', 'visitor_stats_daily' => 65536);
	$kept = 3000000 + 16384 + 500000 + 65536;

	foreach (array_keys(pg_backup_rowless_tables()) as $table) {
		$table_bytes[$table] = 900000000;
	}

	foreach (pg_backup_skipped_tables() as $table) {
		$table_bytes[$table] = 700000000;
	}

	pg_assert_same((float) $kept, pg_backup_estimate_bytes($table_bytes));
	pg_assert_same(0.0, pg_backup_estimate_bytes(array()), 'no tables');
	pg_assert_same(0.0, pg_backup_estimate_bytes(array('visitors' => 5000000)), 'only left-out tables');
	pg_assert_same(16384.0, pg_backup_estimate_bytes(array('visitors_stats' => 16384)), 'a name close to a left-out one counts');
}

// The dump reads the tables in the site connection's character set. Read as
// utf8 (three bytes), every emoji or other four-byte character stored in a
// utf8mb4 column comes out of the server as "?" and the backup loses it.
function test_backup_dump_charset_follows_the_site_connection()
{
	pg_assert_same('utf8mb4', pg_backup_dump_charset('utf8mb4'));
	pg_assert_same('utf8', pg_backup_dump_charset('utf8'));
	pg_assert_same('utf8', pg_backup_dump_charset('utf8mb3'));
	pg_assert_same('utf8mb4', pg_backup_dump_charset(''));
	pg_assert_same('utf8mb4', pg_backup_dump_charset(false));

	$settings = pg_backup_dump_settings('utf8mb4');

	pg_assert_same('utf8mb4', $settings['default-character-set']);
}

// Only an archive directly in the bucket's folder, under a name safe for
// data/backups, can be brought back.
function test_backup_s3_archive_name_ok()
{
	foreach (array('auto_backup_2026-11-45.zip', 'site.backup-1.zip', 'A_b.zip') as $name) {
		pg_assert_true(pg_backup_s3_archive_name_ok($name), $name);
	}

	foreach (array('.hidden.zip', 'a/b.zip', '../x.zip', 'x.tar', 'a b.zip', 'x.zip/', '', 'x.ZIP.php', str_repeat('a', 200) . '.zip') as $name) {
		pg_assert_false(pg_backup_s3_archive_name_ok($name), $name);
	}
}

// The bucket's archives, newest first; other keys and repeats left out.
function test_backup_s3_archive_rows()
{
	$rows = pg_backup_s3_archive_rows(array(
		array('key' => 'auto_backup_2026-10-41.zip', 'size' => 10, 'modified' => 100, 'etag' => 'a'),
		array('key' => 'notes.txt', 'size' => 1, 'modified' => 500, 'etag' => 'b'),
		array('key' => 'other/auto_backup_2026-10-42.zip', 'size' => 1, 'modified' => 600, 'etag' => 'c'),
		array('key' => 'manual.zip', 'size' => 30, 'modified' => 300, 'etag' => 'd'),
		array('key' => 'auto_backup_2026-11-44.zip', 'size' => 20, 'modified' => 300, 'etag' => 'e'),
		array('key' => 'manual.zip', 'size' => 99, 'modified' => 999, 'etag' => 'f'),
	));

	pg_assert_same(array('manual.zip', 'auto_backup_2026-11-44.zip', 'auto_backup_2026-10-41.zip'), array_column($rows, 'name'));
	pg_assert_same(array('name' => 'manual.zip', 'size' => 30, 'modified' => 300, 'etag' => 'd'), $rows[0]);
}

// An empty FTP password box keeps the stored password only for the server it
// was given for: a new host or port, or a connection that drops TLS, would
// otherwise send it to a server the person who typed it never named.
function test_backup_ftp_password_kept_only_for_the_same_server()
{
	$stored = pg_backup_remote_normalize('ftp', array('host' => 'ftp.example.com', 'port' => 21, 'user' => 'site', 'password' => 's3cret', 'tls' => true));

	$posted = function ($changes) use ($stored) {
		return pg_backup_remote_normalize('ftp', array_merge($stored, array('password' => ''), $changes));
	};

	pg_assert_same('s3cret', pg_backup_ftp_password_kept($posted(array()), $stored), 'same server');
	pg_assert_same('s3cret', pg_backup_ftp_password_kept($posted(array('host' => 'FTP.Example.com')), $stored), 'host case');
	pg_assert_same('s3cret', pg_backup_ftp_password_kept($posted(array('user' => 'other', 'path' => '/b')), $stored), 'user and path');
	pg_assert_same('', pg_backup_ftp_password_kept($posted(array('host' => 'ftp.attacker.example')), $stored), 'another host');
	pg_assert_same('', pg_backup_ftp_password_kept($posted(array('port' => 2121)), $stored), 'another port');
	pg_assert_same('', pg_backup_ftp_password_kept($posted(array('tls' => false)), $stored), 'TLS dropped');
	pg_assert_same('typed', pg_backup_ftp_password_kept($posted(array('host' => 'ftp.other.example', 'password' => 'typed')), $stored), 'a typed password');

	$plain = array_merge($stored, array('tls' => false));
	pg_assert_same('s3cret', pg_backup_ftp_password_kept($posted(array('tls' => true)), $plain), 'TLS turned on');
}

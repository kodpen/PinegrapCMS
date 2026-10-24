<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the step-by-step restore in includes/fn/backup_restore.php: how a
 * dump statement is classified, which table name is pointed at the restore's
 * own tables, what a step hands to the next one (session variables, the
 * LOCK TABLES that is still held), and what a restore takes out of a backup
 * archive.
 *
 * The statements are the shapes mysqldump.php (the Backup Manager), the
 * mysqldump command line and phpMyAdmin write.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The statements of a Backup Manager dump, in the order it writes them.
function test_backup_restore_classify_mysqldump_php_statements()
{
	$cases = array(
		"/*!40101 SET NAMES utf8 */;" => array('set', '', ''),
		"/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;" => array('set', '', ''),
		"CREATE TABLE `currencies` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n) ENGINE=MyISAM;" => array('table', 'create', 'currencies'),
		"LOCK TABLES `currencies` WRITE;" => array('lock', 'lock', 'currencies'),
		"/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;" => array('table', 'alter', 'currencies'),
		"SET autocommit=0;" => array('set', '', ''),
		"INSERT INTO `currencies` VALUES (1,'US Dollar','USD','$');" => array('table', 'insert', 'currencies'),
		"UNLOCK TABLES;" => array('unlock', '', ''),
		"COMMIT;" => array('transaction', '', ''),
	);

	foreach ($cases as $sql => $expected) {
		$classified = pg_restore_classify($sql);
		pg_assert_same($expected[0], $classified['kind'], 'kind: ' . $sql);
		pg_assert_same($expected[1], $classified['verb'], 'verb: ' . $sql);
		pg_assert_same($expected[2], $classified['table'], 'table: ' . $sql);
	}
}

// The command line and phpMyAdmin forms, unquoted names among them.
function test_backup_restore_classify_other_dump_tools()
{
	$cases = array(
		"DROP TABLE IF EXISTS `page`;" => array('table', 'drop', 'page'),
		"CREATE TABLE IF NOT EXISTS `page` (`id` int);" => array('table', 'create', 'page'),
		"INSERT IGNORE INTO page VALUES (1);" => array('table', 'insert', 'page'),
		"REPLACE INTO `page` (`id`) VALUES (1);" => array('table', 'replace', 'page'),
		"ALTER TABLE `page`\n  ADD PRIMARY KEY (`id`);" => array('table', 'alter', 'page'),
		"TRUNCATE TABLE `page`;" => array('table', 'truncate', 'page'),
		"START TRANSACTION;" => array('transaction', '', ''),
		"SET time_zone = \"+00:00\";" => array('set', '', ''),
		"/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;" => array('set', '', ''),
		"/*M!100100 SET character_set_client = utf8mb4 */;" => array('set', '', ''),
		"/*M!999999\\- enable the sandbox mode */ \n/*!40101 SET NAMES utf8mb4 */;" => array('set', '', ''),
		"/*!40000 ALTER TABLE `we``ird` ENABLE KEYS */;" => array('table', 'alter', 'we`ird'),
		"SELECT 1;" => array('plain', '', ''),
	);

	foreach ($cases as $sql => $expected) {
		$classified = pg_restore_classify($sql);
		pg_assert_same($expected[0], $classified['kind'], 'kind: ' . $sql);
		pg_assert_same($expected[1], $classified['verb'], 'verb: ' . $sql);
		pg_assert_same($expected[2], $classified['table'], 'table: ' . $sql);
	}
}

// Nothing that would point somewhere else than the connected database, or need
// a server privilege, is run; neither are the restore's own tables in a dump.
function test_backup_restore_classify_skips()
{
	foreach (array(
		"USE `other_site`;",
		"CREATE DATABASE /*!32312 IF NOT EXISTS*/ `other_site`;",
		"SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'abc:1-5';",
		"SET @@SESSION.SQL_LOG_BIN= 0;",
		"SET GLOBAL max_allowed_packet = 1;",
		"INSERT INTO `pgr_new_page` VALUES (1);",
		"CREATE TABLE `pgr_old_page` (`id` int);",
		"/*!40000 */;",
		"-- just a comment\n;",
	) as $sql) {
		pg_assert_same('skip', pg_restore_classify($sql)['kind'], $sql);
	}
}

// A statement that could change the site's own tables while the backup is
// still loading is refused.
function test_backup_restore_classify_refuses_statements_that_reach_the_site()
{
	foreach (array(
		"UPDATE config SET hostname = 'x';",
		"DELETE FROM `user`;",
		"/*!50001 CREATE ALGORITHM=UNDEFINED VIEW `v` AS SELECT 1 */;",
		"CREATE TRIGGER t BEFORE INSERT ON page FOR EACH ROW SET @a = 1;",
		"INSERT INTO `site`.`page` VALUES (1);",
		"DROP TABLE IF EXISTS `page`, `user`;",
		"LOCK TABLES `page` WRITE, `user` WRITE;",
		"RENAME TABLE `page` TO `x`;",
	) as $sql) {
		pg_assert_same('other', pg_restore_classify($sql)['kind'], $sql);
	}
}

// Only the name at the head of the statement changes, not a value that
// happens to look like one.
function test_backup_restore_rename_statement_touches_only_the_table_name()
{
	$prefix = pg_restore_prefix_new();

	$sql = "INSERT INTO `visitors` VALUES (1,'INSERT INTO `visitors` VALUES');";
	pg_assert_same("INSERT INTO `pgr_new_visitors` VALUES (1,'INSERT INTO `visitors` VALUES');", pg_restore_rename_statement($sql, pg_restore_classify($sql), $prefix));

	$sql = "/*!40000 ALTER TABLE `visitors` DISABLE KEYS */;";
	pg_assert_same("/*!40000 ALTER TABLE `pgr_new_visitors` DISABLE KEYS */;", pg_restore_rename_statement($sql, pg_restore_classify($sql), $prefix));

	$sql = "LOCK TABLES visitors WRITE;";
	pg_assert_same("LOCK TABLES `pgr_new_visitors` WRITE;", pg_restore_rename_statement($sql, pg_restore_classify($sql), $prefix));

	$sql = "  CREATE TABLE `we``ird` (`id` int);";
	pg_assert_same("  CREATE TABLE `pgr_new_we``ird` (`id` int);", pg_restore_rename_statement($sql, pg_restore_classify($sql), $prefix));

	$sql = "SET autocommit=0;";
	pg_assert_same($sql, pg_restore_rename_statement($sql, pg_restore_classify($sql), $prefix));
}

// The next step replays the session variables in the order they were last
// set, each once, and takes the lock again only while it is held.
function test_backup_restore_track_session_for_the_next_step()
{
	$state = array('sets' => array(), 'lock' => '');

	$state = pg_restore_track($state, 'set', "/*!40101 SET @saved_cs_client = @@character_set_client */;");
	$state = pg_restore_track($state, 'set', "/*!40101 SET character_set_client = utf8 */;");
	$state = pg_restore_track($state, 'set', "/*!40101 SET character_set_client = @saved_cs_client */;");
	$state = pg_restore_track($state, 'set', "/*!40101 SET @saved_cs_client = @@character_set_client */;");
	$state = pg_restore_track($state, 'set', "/*!40101 SET character_set_client = utf8 */;");

	pg_assert_same(array(
		"/*!40101 SET character_set_client = @saved_cs_client */;",
		"/*!40101 SET @saved_cs_client = @@character_set_client */;",
		"/*!40101 SET character_set_client = utf8 */;",
	), $state['sets']);

	$state = pg_restore_track($state, 'lock', "LOCK TABLES `pgr_new_page` WRITE;\n");
	pg_assert_same("LOCK TABLES `pgr_new_page` WRITE;", $state['lock']);

	$state = pg_restore_track($state, 'table', "INSERT INTO `pgr_new_page` VALUES (1);");
	pg_assert_same("LOCK TABLES `pgr_new_page` WRITE;", $state['lock'], 'an insert keeps the lock');

	$state = pg_restore_track($state, 'unlock', "UNLOCK TABLES;");
	pg_assert_same('', $state['lock']);
}

// The list of session variables stays bounded whatever the dump does.
function test_backup_restore_track_caps_the_list()
{
	$state = array('sets' => array(), 'lock' => '');

	for ($index = 0; $index < 100; $index++) {
		$state = pg_restore_track($state, 'set', 'SET @v' . $index . ' = 1;');
	}

	pg_assert_same(64, count($state['sets']));
	pg_assert_same('SET @v99 = 1;', $state['sets'][63]);
}

// The restore's names stay within MySQL's 64 characters for every table the
// software creates.
function test_backup_restore_prefix_fits_identifier_limit()
{
	pg_assert_true(strlen(pg_restore_prefix_new()) === strlen(pg_restore_prefix_old()), 'same length');
	pg_assert_true(strlen(pg_restore_prefix_new() . str_repeat('x', 45)) <= 64, 'longest table name of the software');
}

// A posted restore continues only the recorded one, for the same folder and
// database.
function test_backup_restore_matches_needs_the_same_restore()
{
	$state = array('id' => 'aaaaaaaaaaaaaaaa', 'folder' => 'site_backup', 'database' => 'site', 'host' => 'localhost', 'phase' => 'swapped', 'size' => 1, 'mtime' => 1);

	pg_assert_true(pg_restore_matches($state, 'aaaaaaaaaaaaaaaa', 'site_backup', 'site', 'localhost'), 'same');
	pg_assert_false(pg_restore_matches($state, 'bbbbbbbbbbbbbbbb', 'site_backup', 'site', 'localhost'), 'other id');
	pg_assert_false(pg_restore_matches($state, '', 'site_backup', 'site', 'localhost'), 'no id');
	pg_assert_false(pg_restore_matches($state, 'aaaaaaaaaaaaaaaa', 'other_backup', 'site', 'localhost'), 'other folder');
	pg_assert_false(pg_restore_matches($state, 'aaaaaaaaaaaaaaaa', 'site_backup', 'other', 'localhost'), 'other database');
	pg_assert_false(pg_restore_matches(null, 'aaaaaaaaaaaaaaaa', 'site_backup', 'site', 'localhost'), 'nothing recorded');

	// While loading, the file has to be the one the restore started with.
	$state['phase'] = 'load';
	pg_assert_false(pg_restore_matches($state, 'aaaaaaaaaaaaaaaa', 'site_backup', 'site', 'localhost'), 'file is not there');
}

function test_backup_restore_percent()
{
	pg_assert_same(0, pg_restore_percent(array('offset' => 0, 'size' => 0)));
	pg_assert_same(43, pg_restore_percent(array('offset' => 437, 'size' => 1000)));
	pg_assert_same(100, pg_restore_percent(array('offset' => 1000, 'size' => 1000)));
}

// A backup is an archive when its name ends in .zip, in any case.
function test_backup_restore_is_archive()
{
	pg_assert_true(pg_restore_is_archive('auto_backup_2026-10-41.zip'));
	pg_assert_true(pg_restore_is_archive('Site.ZIP'));
	pg_assert_false(pg_restore_is_archive('site_backup'));
	pg_assert_false(pg_restore_is_archive('zip'));
	pg_assert_false(pg_restore_is_archive(array('site.zip')));
}

// An archive is read from the folder it is extracted into; a folder backup from
// its own folder. The file that has to stay the same is the archive itself.
function test_backup_restore_paths_of_an_archive()
{
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/temp/restore_aaaaaaaaaaaaaaaa', pg_restore_content_path(array('id' => 'aaaaaaaaaaaaaaaa', 'folder' => 'site.zip', 'archive' => true)));
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/backups/site_backup', pg_restore_content_path(array('id' => 'aaaaaaaaaaaaaaaa', 'folder' => 'site_backup')));
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/backups/site.zip', pg_restore_source_path('site.zip'));
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/backups/site_backup/sql.sql', pg_restore_source_path('site_backup'));

	// While it is extracted, the archive has to be the one the restore started with.
	$state = array('id' => 'aaaaaaaaaaaaaaaa', 'folder' => 'no_such_backup.zip', 'database' => 'site', 'host' => 'localhost', 'phase' => 'extract', 'size' => 1, 'mtime' => 1, 'source_size' => 1, 'source_mtime' => 1);
	pg_assert_false(pg_restore_matches($state, 'aaaaaaaaaaaaaaaa', 'no_such_backup.zip', 'site', 'localhost'), 'archive is not there');
}

// What a restore takes out of an archive: sql.sql at the top or in one folder,
// and the files directly under files/ and layouts/ next to it. Nothing that
// could land outside the working folder, and nothing else.
function test_backup_restore_archive_read()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_restore_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	$make = function ($name, array $entries) use ($dir) {
		$zip = new ZipArchive();
		$zip->open($dir . '/' . $name, ZipArchive::CREATE);
		foreach ($entries as $entry => $contents) {
			if (substr($entry, -1) === '/') {
				$zip->addEmptyDir(rtrim($entry, '/'));
			} else {
				$zip->addFromString($entry, $contents);
			}
		}
		$zip->close();
		return $dir . '/' . $name;
	};

	// The automatic backup's archive.
	$dump = "CREATE TABLE `config` (`id` int);\n";
	$read = pg_restore_archive_read($make('auto.zip', array('sql.sql' => $dump, 'files/logo.png' => 'png', 'files/.htaccess' => 'deny from all', 'layouts/1.php' => '<?php')));
	pg_assert_true($read['ok'], 'automatic backup');
	pg_assert_same(array('sql.sql', 'files/logo.png', 'files/.htaccess', 'layouts/1.php'), array_column($read['entries'], 'name'));
	pg_assert_same(array('files', 'layouts'), $read['folders']);
	pg_assert_same((float) (strlen($dump) + 3 + 13 + 5), $read['size']);
	pg_assert_same(hash('crc32b', $dump), sprintf('%08x', $read['entries'][0]['crc'] & 0xFFFFFFFF), 'checksum as the archive has it');

	// A backup folder zipped by hand, with things the restore leaves alone.
	$read = pg_restore_archive_read($make('hand.zip', array(
		'site/' => '',
		'site/sql.sql' => $dump,
		'site/files/a.jpg' => 'a',
		'site/files/sub/b.jpg' => 'b',
		'site/files/../../config.php' => 'outside',
		'site/layouts/a:b' => 'a stream on Windows',
		'site/notes.txt' => 'n',
		'other/files/c.jpg' => 'c',
	)));
	pg_assert_true($read['ok'], 'folder in the archive');
	pg_assert_same(array('sql.sql', 'files/a.jpg'), array_column($read['entries'], 'name'));
	pg_assert_same(array('files', 'layouts'), $read['folders']);

	// No dump, no backup; and a file that is not an archive.
	$read = pg_restore_archive_read($make('nodump.zip', array('files/a.jpg' => 'a')));
	pg_assert_false($read['ok'], 'no sql.sql');
	pg_assert_true($read['error'] !== '', 'says why');

	file_put_contents($dir . '/broken.zip', 'not an archive');
	pg_assert_false(pg_restore_archive_read($dir . '/broken.zip')['ok'], 'not an archive');
	pg_assert_false(pg_restore_archive_read($dir . '/missing.zip')['ok'], 'not there');

	foreach (array('auto.zip', 'hand.zip', 'nodump.zip', 'broken.zip') as $name) {
		@unlink($dir . '/' . $name);
	}

	rmdir($dir);
}

// Copies one file of an archive with pg_restore_archive_copy() in a PHP
// process of its own. Returns what the copy returned (error) and how far that
// process's peak memory rose above what it had in use before the copy (peak,
// in bytes; null when the process failed, error then holding what it said).
// Its own process, because the peak of this one cannot be cleared before
// PHP 8.2 (memory_reset_peak_usage()): it still holds the dump the test
// wrote, twice over with ZipArchive::addFromString()'s copy of it, and
// whatever earlier tests used. Null when PHP cannot be started again.
function backup_restore_test_copy_alone($archive, $index, $path)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgrestore');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. "\$zip = new ZipArchive();\n"
		. '$zip->open(' . var_export($archive, true) . ");\n"
		. "\$before = memory_get_usage();\n"
		. '$error = pg_restore_archive_copy($zip, ' . (int) $index . ', ' . var_export($path, true) . ");\n"
		. "echo json_encode(array('error' => \$error, 'peak' => memory_get_peak_usage() - \$before));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['error'], $result['peak'])) {
		return array('error' => implode("\n", $out), 'peak' => null);
	}

	return $result;
}

// The installation screen's upload extracts a file a megabyte at a time, so a
// dump larger than the memory limit still comes out, whole and checked; a file
// that does not match its checksum is not left behind.
function test_backup_restore_archive_copy()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_restore_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	$dump = str_repeat("INSERT INTO `log` VALUES (1,'" . str_repeat('x', 200) . "');\n", 100000);
	$size = strlen($dump);
	$crc = hash('crc32b', $dump);

	$zip = new ZipArchive();
	$zip->open($dir . '/big.zip', ZipArchive::CREATE);
	$zip->addFromString('site/sql.sql', $dump);
	$zip->close();
	unset($dump);

	$copied = backup_restore_test_copy_alone($dir . '/big.zip', 0, $dir . '/sql.sql');

	if ($copied !== null) {
		pg_assert_same('', $copied['error'], 'copied');
		pg_assert_true(($copied['peak'] !== null) && ($copied['peak'] < 8 * 1048576), 'a ' . round($size / 1048576) . ' MB dump is copied within a few megabytes: ' . round((float) $copied['peak'] / 1048576, 1) . ' MB');
		pg_assert_same($size, filesize($dir . '/sql.sql'));
		pg_assert_same($crc, hash_file('crc32b', $dir . '/sql.sql'));
	}

	$zip = new ZipArchive();
	$zip->open($dir . '/big.zip');
	pg_assert_true(pg_restore_archive_copy($zip, 5, $dir . '/none.sql') !== '', 'no such file in the archive');
	pg_assert_false(file_exists($dir . '/none.sql'));
	$zip->close();

	// A stored (uncompressed) file with one byte changed after it was written.
	$zip = new ZipArchive();
	$zip->open($dir . '/damaged.zip', ZipArchive::CREATE);
	$zip->addFromString('sql.sql', 'CREATE TABLE `config` (`id` int);');
	$zip->setCompressionIndex(0, ZipArchive::CM_STORE);
	$zip->close();
	file_put_contents($dir . '/damaged.zip', str_replace('`config`', '`cOnfig`', file_get_contents($dir . '/damaged.zip')));

	$zip = new ZipArchive();
	$zip->open($dir . '/damaged.zip');
	pg_assert_contains('sql.sql', pg_restore_archive_copy($zip, 0, $dir . '/damaged.sql'), 'damaged');
	pg_assert_false(file_exists($dir . '/damaged.sql'), 'a damaged file is deleted');
	$zip->close();

	foreach (array('big.zip', 'sql.sql', 'damaged.zip') as $name) {
		@unlink($dir . '/' . $name);
	}

	rmdir($dir);
}

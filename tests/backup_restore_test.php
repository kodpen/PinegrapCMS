<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the step-by-step restore in includes/fn/backup_restore.php: how a
 * dump statement is classified, which table name is pointed at the restore's
 * own tables, and what a step hands to the next one (session variables, the
 * LOCK TABLES that is still held).
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

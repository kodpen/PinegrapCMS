<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The upgrade runner.
//
// install/index.php used to hold the version list, every upgrade function and the loop
// that ran them, in one 370 KB file. Three things went wrong with that on real sites, all
// of them seen on the same day:
//
//  - the version was written to config.version only after the step had been reported to
//    the browser, so a refresh in the wrong second left the schema changed and the number
//    unchanged;
//  - most steps were plain "ALTER TABLE ... ADD", so running them a second time died on
//    "Duplicate column" and db() answered every failure with exit();
//  - nothing stopped two requests from running the same steps at once.
//
// Together those turned one interrupted upgrade into a site that could never be upgraded
// again. This file replaces the loop. The version list lives in versions.php, every version
// that touches the database has a file of its own next to it (2026.2.5.php holds
// upgrade_to_2026_2_5()), the LiveSite-era steps live in legacy.php, and the loop below
// takes a lock, writes the version the moment a step returns, turns query failures into
// exceptions it can report, and treats "already there" as a note rather than a crash.
//
// It lives under includes/ rather than install/ because the install directory is sometimes
// removed from a server after installation; the schema history has to survive that.
//
// Loaded by install/index.php after functions.php. Every function here starts with
// install_ so the names cannot collide with the software's own.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// ─── Disabled functions ──────────────────────────────────────────────────────
//
// Shared hosts strip functions from PHP with disable_functions, and the list is
// the host's, not ours: disk_free_space, ini_get_all, set_time_limit,
// ignore_user_abort, getmypid and even error_log turn up on it. Since PHP 8 a
// disabled function is an undefined function - function_exists() says false and
// a call throws an Error that @ does not silence. A site's first upgrade died on
// exactly that (disk_free_space in the pre-flight card), so nothing in this file
// calls one of those without asking first. Everything they would have given is
// optional: the answer without them is "unknown", never "stop".

function install_log($message) {

	if (function_exists('error_log')) {

		error_log('[pinegrap upgrade] ' . $message);

	}

}

// ─── Exceptions ──────────────────────────────────────────────────────────────

// Anything that stops a version from being applied.
class InstallUpgradeException extends Exception {
}

// A query that failed and could not be waved through as "already applied".
class InstallQueryException extends InstallUpgradeException {

	public $statement = '';

	public $errno = 0;

}

// Another upgrade holds the lock.
class InstallLockedException extends InstallUpgradeException {
}

// ─── State ───────────────────────────────────────────────────────────────────

// Everything the runner knows while a run is going. 'active' is what db() looks at: while
// it is true a failed query is handed to install_query_failed() instead of output_error().
$install_runner = array(
	'active' => false,
	'version' => '',
	'notes' => array(),
	'executed' => 0,
	'skipped' => 0,
	'lock_name' => '',
	'lock_handle' => null,
	'lock_file' => '',
	'guard' => false,
	'started' => 0,
);

// ─── Version list and migration files ────────────────────────────────────────

// The directory this file is in; every migration file is next to it.
function install_migrations_path() {

	return dirname(__FILE__);

}

// The version history, in the shape install/index.php has always used:
// array(array('number' => '2017.2'), array('number' => '2017.2.1'), ...)
function install_get_versions() {

	static $versions = null;

	if ($versions !== null) {

		return $versions;

	}

	$numbers = include install_migrations_path() . '/versions.php';

	$versions = array();

	foreach ((array) $numbers as $number) {

		$versions[] = array('number' => (string) $number);

	}

	return $versions;

}

// The key of a version number in the list, or false when it is not part of this package.
function install_version_key($number, $versions) {

	foreach ($versions as $key => $version) {

		if ($version['number'] == $number) {

			return $key;

		}

	}

	return false;

}

// The name of the function that applies a version.
function install_upgrade_function($number) {

	return 'upgrade_to_' . str_replace('.', '_', $number);

}

// The file that holds a version's step, or '' when the version has none (a code-only
// release, or a legacy version whose function lives in legacy.php).
function install_migration_file($number) {

	if (preg_match('/^\d{4}(\.\d+)*$/', $number) != 1) {

		return '';

	}

	$path = install_migrations_path() . '/' . $number . '.php';

	if (!is_file($path)) {

		return '';

	}

	return $path;

}

// Loads the file of a version. A syntax error in the file is a ParseError in PHP 7+, which
// is caught here and reported with the file and line, so a broken migration stops its own
// version and nothing else.
function install_load_migration($number) {

	$path = install_migration_file($number);

	if ($path == '') {

		return false;

	}

	try {

		include_once $path;

	} catch (Throwable $e) {

		throw new InstallUpgradeException(
			basename($path) . ' ' . lang(array('string' => 'line {var:1}', 'vars' => $e->getLine())) . ': ' . $e->getMessage());

	}

	return true;

}

// The LiveSite-era functions, loaded once per run.
function install_include_legacy() {

	include_once install_migrations_path() . '/legacy.php';

}

// The ENGINE constant that the legacy steps put into their CREATE TABLE statements. Engine
// support arrived in MySQL 4.1; nobody runs anything older, but the constant is kept so
// the old statements keep working unchanged.
function install_define_engine() {

	if (defined('ENGINE')) {

		return;

	}

	$mysql_version = (string) db_value("SELECT VERSION()");

	$parts = explode('.', $mysql_version);

	$major = (int) $parts[0];

	$minor = isset($parts[1]) ? (int) $parts[1] : 0;

	if ((($major == 4) && ($minor >= 1)) || ($major >= 5)) {

		define('ENGINE', ' ENGINE=MyISAM');

	} else {

		define('ENGINE', '');

	}

}

// ─── Notes ───────────────────────────────────────────────────────────────────

// A line about what a step did or did not do, kept per version and shown on the screen
// under the step. "config.waf_enabled already exists" is what tells an operator that the
// run they are looking at is a second run.
function install_note($text) {

	global $install_runner;

	$version = ($install_runner['version'] != '') ? $install_runner['version'] : '-';

	if (!isset($install_runner['notes'][$version])) {

		$install_runner['notes'][$version] = array();

	}

	$install_runner['notes'][$version][] = $text;

	install_progress_write();

}

function install_notes($version = '') {

	global $install_runner;

	if ($version == '') {

		return $install_runner['notes'];

	}

	return isset($install_runner['notes'][$version]) ? $install_runner['notes'][$version] : array();

}

// ─── Schema probes ───────────────────────────────────────────────────────────
//
// Every probe asks by exact name. SHOW TABLES LIKE and SHOW COLUMNS LIKE are not used for
// that, because in a LIKE pattern the underscore is a wildcard: LIKE 'waf_log' would also
// match a table called wafXlog. WHERE Field = and information_schema compare the name.

function install_quote_name($name) {

	return '`' . str_replace('`', '``', $name) . '`';

}

function install_table_exists($table) {

	$count = db_value(
		"SELECT COUNT(*)
		FROM information_schema.TABLES
		WHERE
			(TABLE_SCHEMA = DATABASE())
			AND (TABLE_NAME = '" . e($table) . "')");

	return ((int) $count > 0);

}

function install_column_exists($table, $column) {

	if (!install_table_exists($table)) {

		return false;

	}

	$row = db_item("SHOW COLUMNS FROM " . install_quote_name($table) . " WHERE Field = '" . e($column) . "'");

	return (is_array($row) && (count($row) > 0));

}

// The definition of a column as SHOW COLUMNS reports it (Type, Null, Default...), or false.
function install_column_info($table, $column) {

	if (!install_table_exists($table)) {

		return false;

	}

	$row = db_item("SHOW COLUMNS FROM " . install_quote_name($table) . " WHERE Field = '" . e($column) . "'");

	return (is_array($row) && (count($row) > 0)) ? $row : false;

}

function install_index_exists($table, $index) {

	if (!install_table_exists($table)) {

		return false;

	}

	$row = db_item("SHOW INDEX FROM " . install_quote_name($table) . " WHERE Key_name = '" . e($index) . "'");

	return (is_array($row) && (count($row) > 0));

}

function install_table_engine($table) {

	$status = db_item("SHOW TABLE STATUS WHERE Name = '" . e($table) . "'");

	if (is_array($status) && isset($status['Engine'])) {

		return strtolower((string) $status['Engine']);

	}

	return '';

}

// ─── Schema steps ────────────────────────────────────────────────────────────
//
// Every step asks first and does the work only when it is not already done. Each returns
// true when it ran a statement and false when there was nothing to do, and leaves a note
// either way, so the screen can say "12 statements, 3 were already in place".

function install_ran($text) {

	global $install_runner;

	$install_runner['executed']++;

	install_note($text);

	return true;

}

function install_skipped($text) {

	global $install_runner;

	$install_runner['skipped']++;

	install_note($text);

	return false;

}

// ALTER TABLE t ADD c <definition>. The definition may end in AFTER x / FIRST.
function install_add_column($table, $column, $definition) {

	if (install_column_exists($table, $column)) {

		return install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => $table . '.' . $column)));

	}

	db("ALTER TABLE " . install_quote_name($table) . " ADD " . install_quote_name($column) . " " . $definition);

	return install_ran(lang(array('string' => '{var:1} added', 'vars' => $table . '.' . $column)));

}

// ALTER TABLE t ADD <definition>, where the definition names the index in full:
// "INDEX idx_type (type)", "UNIQUE KEY uniq_entry (a, b)", "FULLTEXT ft (body)".
function install_add_index($table, $index, $definition) {

	if (stripos($definition, $index) === false) {

		throw new InstallUpgradeException('install_add_index: the definition does not name the index ' . $index);

	}

	if (install_index_exists($table, $index)) {

		return install_skipped(lang(array('string' => 'index {var:1} already exists', 'vars' => $table . '.' . $index)));

	}

	db("ALTER TABLE " . install_quote_name($table) . " ADD " . $definition);

	return install_ran(lang(array('string' => 'index {var:1} added', 'vars' => $table . '.' . $index)));

}

// The full CREATE TABLE statement, run only when the table is missing. IF NOT EXISTS in the
// statement is fine but not needed.
function install_create_table($table, $statement) {

	if (install_table_exists($table)) {

		return install_skipped(lang(array('string' => 'table {var:1} already exists', 'vars' => $table)));

	}

	db($statement);

	return install_ran(lang(array('string' => 'table {var:1} created', 'vars' => $table)));

}

function install_drop_column($table, $column) {

	if (!install_column_exists($table, $column)) {

		return install_skipped(lang(array('string' => '{var:1} is already gone', 'vars' => $table . '.' . $column)));

	}

	db("ALTER TABLE " . install_quote_name($table) . " DROP " . install_quote_name($column));

	return install_ran(lang(array('string' => '{var:1} dropped', 'vars' => $table . '.' . $column)));

}

function install_drop_index($table, $index) {

	if (!install_index_exists($table, $index)) {

		return install_skipped(lang(array('string' => 'index {var:1} is already gone', 'vars' => $table . '.' . $index)));

	}

	db("ALTER TABLE " . install_quote_name($table) . " DROP INDEX " . install_quote_name($index));

	return install_ran(lang(array('string' => 'index {var:1} dropped', 'vars' => $table . '.' . $index)));

}

function install_drop_table($table) {

	if (!install_table_exists($table)) {

		return install_skipped(lang(array('string' => 'table {var:1} is already gone', 'vars' => $table)));

	}

	db("DROP TABLE " . install_quote_name($table));

	return install_ran(lang(array('string' => 'table {var:1} dropped', 'vars' => $table)));

}

// ALTER TABLE t MODIFY c <definition>. MODIFY is safe to repeat, so the only question is
// whether the column is there. Normally a missing column is an error, because a step that
// widens a column it expects to find has found a broken schema; a rename that already
// happened is the exception, and then $required is false.
function install_modify_column($table, $column, $definition, $required = true) {

	if (!install_column_exists($table, $column)) {

		if ($required) {

			throw new InstallUpgradeException(lang(array('string' => '{var:1} does not exist, so it cannot be changed', 'vars' => $table . '.' . $column)));

		}

		return install_skipped(lang(array('string' => '{var:1} does not exist, skipped', 'vars' => $table . '.' . $column)));

	}

	db("ALTER TABLE " . install_quote_name($table) . " MODIFY " . install_quote_name($column) . " " . $definition);

	return install_ran(lang(array('string' => '{var:1} changed', 'vars' => $table . '.' . $column)));

}

// ALTER TABLE t CHANGE old new <definition>. Done when the old name is there; when only
// the new name is there the rename already happened.
function install_rename_column($table, $old, $new, $definition) {

	if (!install_column_exists($table, $old)) {

		if (install_column_exists($table, $new)) {

			return install_skipped(lang(array('string' => '{var:1} was already renamed to {var:2}', 'vars' => array($table . '.' . $old, $new))));

		}

		throw new InstallUpgradeException(lang(array('string' => '{var:1} does not exist, so it cannot be changed', 'vars' => $table . '.' . $old)));

	}

	db("ALTER TABLE " . install_quote_name($table) . " CHANGE " . install_quote_name($old) . " " . install_quote_name($new) . " " . $definition);

	return install_ran(lang(array('string' => '{var:1} renamed to {var:2}', 'vars' => array($table . '.' . $old, $new))));

}

function install_rename_table($old, $new) {

	if (!install_table_exists($old)) {

		if (install_table_exists($new)) {

			return install_skipped(lang(array('string' => 'table {var:1} was already renamed to {var:2}', 'vars' => array($old, $new))));

		}

		throw new InstallUpgradeException(lang(array('string' => 'table {var:1} does not exist, so it cannot be renamed', 'vars' => $old)));

	}

	db("RENAME TABLE " . install_quote_name($old) . " TO " . install_quote_name($new));

	return install_ran(lang(array('string' => 'table {var:1} renamed to {var:2}', 'vars' => array($old, $new))));

}

// ALTER TABLE t ENGINE=x, only when the table is on another engine. The conversion cannot
// be resumed and is expensive to start over, so it is never repeated by accident.
function install_set_engine($table, $engine) {

	$current = install_table_engine($table);

	if ($current == '') {

		return install_skipped(lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => $table)));

	}

	if ($current == strtolower($engine)) {

		return install_skipped(lang(array('string' => 'table {var:1} is already {var:2}', 'vars' => array($table, $engine))));

	}

	db("ALTER TABLE " . install_quote_name($table) . " ENGINE=" . $engine);

	return install_ran(lang(array('string' => 'table {var:1} moved to {var:2}', 'vars' => array($table, $engine))));

}

// ─── The safety net under db() ───────────────────────────────────────────────
//
// functions.php calls this from db(), db_value(), db_values(), db_item() and db_items()
// when a query fails and the runner is active. It answers one question: can the failure be
// waved through as "already applied"? Only for schema statements, and only for the errors
// that mean the work was done before:
//
//   1050  table already exists          CREATE TABLE
//   1060  duplicate column name         ALTER ... ADD
//   1061  duplicate key name            ALTER ... ADD INDEX / KEY / UNIQUE
//   1091  can't DROP, does not exist    ALTER ... DROP COLUMN / INDEX
//   1146  table doesn't exist           DROP TABLE only
//
// Everything else - 1062 duplicate entry under a new UNIQUE key, 1054 unknown column, a
// syntax error, a lost connection - is a real failure and becomes an exception that the
// loop reports with the statement. Returns true to skip; never returns false while active.
function install_query_failed($query, $errno, $error) {

	global $install_runner;

	if (empty($install_runner['active'])) {

		return false;

	}

	$errno = (int) $errno;

	$head = preg_replace('/\s+/', ' ', trim(mb_substr($query, 0, 160)));

	$is_ddl = (preg_match('/^\s*(ALTER|CREATE|DROP)\b/i', $query) == 1);

	$is_drop_table = (preg_match('/^\s*DROP\s+TABLE\b/i', $query) == 1);

	$tolerated = array(1050, 1060, 1061, 1091);

	if ($is_ddl && (in_array($errno, $tolerated) || ($is_drop_table && ($errno == 1146)))) {

		$install_runner['skipped']++;

		install_note(lang(array('string' => 'already applied (MySQL {var:1}): {var:2}', 'vars' => array($errno, $head))));

		install_log($install_runner['version'] . ' skipped, MySQL ' . $errno . ' ' . $error . ' :: ' . $head);

		return true;

	}

	$exception = new InstallQueryException(lang(array('string' => 'MySQL error {var:1}: {var:2}', 'vars' => array($errno, $error))));

	$exception->statement = $head;

	$exception->errno = $errno;

	throw $exception;

}

// ─── Lock ────────────────────────────────────────────────────────────────────
//
// GET_LOCK is a session lock: it goes away with the connection, so a request that the web
// server killed cannot leave a stale lock behind. Where GET_LOCK is not available the
// fallback is flock() on a file under data/temp, which the operating system releases when
// the process ends.

function install_lock_marker_file() {

	return dirname(__FILE__) . '/../../data/temp/upgrade_running.json';

}

function install_acquire_lock() {

	global $install_runner;

	$name = 'pinegrap_upgrade_' . md5((defined('DB_DATABASE') ? DB_DATABASE : '') . '@' . (defined('DB_HOST') ? DB_HOST : ''));

	// The runner is already active here, so a server without GET_LOCK answers with an
	// exception rather than a dead page, and the file lock takes over.
	$got = null;

	try {

		$got = db_value("SELECT GET_LOCK('" . $name . "', 0)");

	} catch (InstallQueryException $e) {

		$got = null;

	}

	if ($got === '1' || $got === 1) {

		$install_runner['lock_name'] = $name;

	} elseif ($got === '0' || $got === 0) {

		throw new InstallLockedException(install_lock_message());

	} else {

		// no GET_LOCK on this server, so fall back to a file lock
		$directory = dirname(install_lock_marker_file());

		if (!is_dir($directory)) {

			@mkdir($directory, 0755, true);

		}

		$file = $directory . '/upgrade.lock';

		$handle = @fopen($file, 'c');

		if (($handle === false) || (!flock($handle, LOCK_EX | LOCK_NB))) {

			if ($handle !== false) {

				fclose($handle);

			}

			throw new InstallLockedException(install_lock_message());

		}

		$install_runner['lock_handle'] = $handle;

		$install_runner['lock_file'] = $file;

	}

	$install_runner['started'] = time();

	@file_put_contents(install_lock_marker_file(), json_encode(array('started' => time(), 'pid' => (function_exists('getmypid') ? getmypid() : 0))), LOCK_EX);

}

function install_release_lock() {

	global $install_runner;

	if ($install_runner['lock_name'] != '') {

		$name = $install_runner['lock_name'];

		$install_runner['lock_name'] = '';

		try {

			db_value("SELECT RELEASE_LOCK('" . $name . "')");

		} catch (Throwable $e) {

			// the connection is gone, and with it the lock

		}

	}

	if ($install_runner['lock_handle']) {

		@flock($install_runner['lock_handle'], LOCK_UN);

		@fclose($install_runner['lock_handle']);

		$install_runner['lock_handle'] = null;

	}

	@unlink(install_lock_marker_file());

}

// Whether another connection holds the upgrade lock right now. Asked by the screen before
// it offers the start button, so an upgrade that a cron is running in the background is
// reported instead of being run into. The file lock cannot be asked without taking it, so
// on a server without GET_LOCK the marker file is the only hint.
function install_lock_is_held() {

	global $install_runner;

	$name = 'pinegrap_upgrade_' . md5((defined('DB_DATABASE') ? DB_DATABASE : '') . '@' . (defined('DB_HOST') ? DB_HOST : ''));

	$free = null;

	// outside a run a failed query is fatal, so the safety net is switched on for this one
	$was_active = !empty($install_runner['active']);

	$install_runner['active'] = true;

	try {

		$free = db_value("SELECT IS_FREE_LOCK('" . $name . "')");

	} catch (Throwable $e) {

		$free = null;

	} finally {

		$install_runner['active'] = $was_active;

	}

	if (($free === '0') || ($free === 0)) {

		return true;

	}

	if (($free === '1') || ($free === 1)) {

		// the lock is free, so a marker that is still here was left by a killed process
		@unlink(install_lock_marker_file());

		return false;

	}

	// no GET_LOCK on this server: go by the marker, and do not trust it for longer than a day
	$marker = @json_decode((string) @file_get_contents(install_lock_marker_file()), true);

	if (is_array($marker) && !empty($marker['started']) && (((int) $marker['started'] + 86400) > time())) {

		return true;

	}

	return false;

}

// What to tell the person who ran into the lock. The marker says when the other run
// started; it can be stale when a process was killed, but then the lock itself is free and
// nobody reads this.
function install_lock_message() {

	$since = '';

	$marker = @json_decode((string) @file_get_contents(install_lock_marker_file()), true);

	if (is_array($marker) && !empty($marker['started'])) {

		$since = date('H:i:s', (int) $marker['started']);

	}

	if ($since != '') {

		return lang(array('string' => 'Another upgrade has been running since {var:1}. Wait for it to finish, then refresh this screen.', 'vars' => $since));

	}

	return lang('Another upgrade is running right now. Wait for it to finish, then refresh this screen.');

}

// ─── The last failed run ─────────────────────────────────────────────────────
//
// When a step fails, or the request dies under it, what happened is written to a small file
// next to the lock marker. The screen reads it the next time it is opened, so an
// administrator who comes back after a timeout sees where the last run stopped and why,
// and the button says "continue" rather than "start". The file goes away as soon as that
// version is applied.

function install_last_marker_file() {

	return dirname(__FILE__) . '/../../data/temp/upgrade_last.json';

}

function install_last_read() {

	$marker = @json_decode((string) @file_get_contents(install_last_marker_file()), true);

	if ((!is_array($marker)) || (empty($marker['version']))) {

		return null;

	}

	return array(
		'version' => (string) $marker['version'],
		'message' => isset($marker['message']) ? (string) $marker['message'] : '',
		'statement' => isset($marker['statement']) ? (string) $marker['statement'] : '',
		'time' => isset($marker['time']) ? (int) $marker['time'] : 0
	);

}

function install_last_write($version, $message, $statement = '') {

	$directory = dirname(install_last_marker_file());

	if (!is_dir($directory)) {

		@mkdir($directory, 0755, true);

	}

	@file_put_contents(install_last_marker_file(), json_encode(array(
		'version' => (string) $version,
		'message' => (string) $message,
		'statement' => (string) $statement,
		'time' => time()
	)), LOCK_EX);

}

function install_last_clear() {

	@unlink(install_last_marker_file());

}

// ─── Progress reporting ──────────────────────────────────────────────────────
//
// The install screen keeps a progress file that the browser polls; those functions live in
// install/index.php. The runner talks to them through these three hooks, and works without
// them (from the command line, or from a job) by writing to the error log instead.

function install_progress_write() {

	if (function_exists('write_install_progress_file')) {

		write_install_progress_file(false);

	}

}

function install_progress_running($number) {

	global $install_progress_running;

	$install_progress_running = $number;

	install_progress_write();

}

function install_progress_failed($number, $message, $statement = '') {

	global $install_progress_error, $install_progress_running;

	$install_progress_error = array('version' => $number, 'message' => $message, 'statement' => $statement);

	$install_progress_running = '';

	install_last_write($number, $message, $statement);

	install_log('failed at ' . $number . ': ' . $message . (($statement != '') ? ' :: ' . $statement : ''));

	if (function_exists('add_install_step')) {

		add_install_step(
			lang(array('string' => 'Version {var:1} failed', 'vars' => $number)),
			$message . (($statement != '') ? ' — ' . $statement : ''),
			'error');

	} else {

		install_progress_write();

	}

}

// A fatal error (memory, a timeout, a parse error outside the migration files) does not
// pass through the loop, so the shutdown function is what turns it into a reported failure
// instead of a screen that never moves again.
function install_register_shutdown_guard() {

	global $install_runner;

	if ($install_runner['guard']) {

		return;

	}

	$install_runner['guard'] = true;

	// Without it a fatal error is not reported as a failed step; the lock still
	// goes with the connection, and the next run picks up from the last version.
	if (function_exists('register_shutdown_function')) {

		register_shutdown_function('install_shutdown_guard');

	}

}

function install_shutdown_guard() {

	global $install_runner;

	if (empty($install_runner['active'])) {

		return;

	}

	$last = error_get_last();

	$message = lang('The upgrade stopped before it finished.');

	if (is_array($last) && in_array($last['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {

		$message = $last['message'] . ' (' . basename($last['file']) . ':' . $last['line'] . ')';

	}

	$install_runner['active'] = false;

	install_progress_failed($install_runner['version'], $message);

	install_release_lock();

}

// ─── The loop ────────────────────────────────────────────────────────────────

function install_run_begin() {

	global $install_runner;

	$install_runner['active'] = true;

}

function install_run_end() {

	global $install_runner;

	$install_runner['active'] = false;

	$install_runner['version'] = '';

}

// What a failed step is reported as: the message, and for a query failure the statement.
function install_describe_error($e) {

	$description = array('message' => $e->getMessage(), 'statement' => '');

	if ($e instanceof InstallQueryException) {

		$description['statement'] = $e->statement;

	} elseif (!($e instanceof InstallUpgradeException)) {

		// a PHP error inside a step: say where
		$description['message'] .= ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';

	}

	return $description;

}

// Applies every version after $from_key, in order.
//
// $options:
//   'stream'  bool  report every applied version with add_install_step()
//   'one'     bool  apply only the next version and return (the screen drives the loop)
//
// Returns array(
//   'ok'        => bool      false when a version failed
//   'applied'   => array     the numbers that were written to config.version
//   'steps'     => array     one entry per applied version: number, touched (it had an
//                            upgrade function), detail, executed, skipped, seconds, notes
//   'failed'    => string    the version that failed, or ''
//   'error'     => string    what went wrong
//   'statement' => string    the statement that failed, when it was a query
//   'done'      => bool      the database is now at the last version of the package
//   'next'      => string    the next version to apply, when not done
//   'locked'    => bool      another run held the lock; nothing was done
//   'last'      => array     what the previous run left behind (see install_last_read()),
//                            when it stopped at the version this run started with
// )
//
// The version is written to config.version the moment a step returns and before anything
// is sent to the browser. Every step is expected to be safe to run twice, so after a
// failure the right thing to do is simply to run the upgrade again.
function install_run_upgrades($versions, $from_key, $options = array()) {

	global $install_runner;

	$stream = !empty($options['stream']);

	$one = !empty($options['one']);

	$result = array(
		'ok' => true,
		'applied' => array(),
		'steps' => array(),
		'failed' => '',
		'error' => '',
		'statement' => '',
		'done' => false,
		'next' => '',
		'locked' => false,
		'last' => null,
	);

	// what the previous run said before it stopped, if it stopped at the version that is next
	$last = install_last_read();

	if (($last !== null) && (isset($versions[$from_key + 1])) && ($last['version'] == $versions[$from_key + 1]['number'])) {

		$result['last'] = $last;

	}

	// From here on a failed query is an exception, not a dead page.
	install_run_begin();

	try {

		install_acquire_lock();

	} catch (InstallLockedException $e) {

		install_run_end();

		$result['ok'] = false;

		$result['locked'] = true;

		$result['error'] = $e->getMessage();

		return $result;

	}

	// A closed browser tab must not stop a step halfway through; the lock says so on screen.
	if (function_exists('ignore_user_abort')) {

		@ignore_user_abort(true);

	}

	if (function_exists('set_time_limit')) {

		@set_time_limit(0);

	}

	install_register_shutdown_guard();

	install_define_engine();

	install_include_legacy();

	$key_5_5_0 = install_version_key('5.5.0', $versions);

	$last_key = count($versions) - 1;

	$current_key = $from_key;

	try {

		foreach ($versions as $key => $version) {

			if ($key <= $from_key) {

				continue;

			}

			$number = $version['number'];

			$install_runner['version'] = $number;

			$install_runner['executed'] = 0;

			$install_runner['skipped'] = 0;

			install_progress_running($number);

			$started = microtime(true);

			$has_function = false;

			try {

				install_load_migration($number);

				$function = install_upgrade_function($number);

				if (function_exists($function)) {

					$has_function = true;

					$function();

				}

			} catch (Throwable $e) {

				$description = install_describe_error($e);

				install_progress_failed($number, $description['message'], $description['statement']);

				$result['ok'] = false;

				$result['failed'] = $number;

				$result['error'] = $description['message'];

				$result['statement'] = $description['statement'];

				break;

			}

			// The version is written here, before any output, so a browser that goes away
			// while the step is reported cannot leave the schema ahead of the number.
			if (($key_5_5_0 === false) || ($key >= $key_5_5_0)) {

				db("UPDATE config SET version = '" . e($number) . "'");

			}

			$current_key = $key;

			$result['applied'][] = $number;

			// a failure the previous run left at this version is history now
			if (($last !== null) && ($last['version'] == $number)) {

				install_last_clear();

			}

			$detail = '';

			if ($has_function) {

				if (function_exists('get_install_schema_note')) {

					$detail = get_install_schema_note($number);

				}

				if ($detail == '') {

					$detail = lang('the database is updated');

				}

				$counts = $install_runner['executed'] + $install_runner['skipped'];

				if ($counts > 0) {

					$detail .= ' · ' . lang(array('string' => '{var:1} statements', 'vars' => $counts));

					if ($install_runner['skipped'] > 0) {

						$detail .= ', ' . lang(array('string' => '{var:1} were already in place', 'vars' => $install_runner['skipped']));

					}

				}

			}

			$result['steps'][] = array(
				'number' => $number,
				'touched' => $has_function,
				'detail' => $detail,
				'executed' => $install_runner['executed'],
				'skipped' => $install_runner['skipped'],
				'seconds' => round(microtime(true) - $started, 1),
				'notes' => install_notes($number)
			);

			if ($has_function && $stream && function_exists('add_install_step')) {

				add_install_step(lang(array('string' => 'Version {var:1}', 'vars' => $number)), $detail);

			}

			if ($one) {

				break;

			}

		}

	} finally {

		install_run_end();

		install_release_lock();

	}

	install_progress_running('');

	if ($result['ok'] && ($current_key >= $last_key)) {

		$result['done'] = true;

		install_last_clear();

		// the site no longer has to say that an update is waiting, and the next check
		// starts from a clean slate so the messages for the new version arrive
		db("UPDATE config SET software_update_available = '0'");

		db("UPDATE config SET last_software_update_check_timestamp = ''");

	} elseif (isset($versions[$current_key + 1])) {

		$result['next'] = $versions[$current_key + 1]['number'];

	}

	return $result;

}

// ─── Before the upgrade starts ───────────────────────────────────────────────
//
// The screen shows these checks above the start button. None of them stops the upgrade;
// they say what is likely to go wrong on this server before it does, which is when the
// administrator can still do something about it: take a backup, wait for the other run,
// ask the host for a privilege.

// The versions whose steps rewrite a table that grows with the traffic of the site. Adding
// a column or an index to a table with millions of rows, or moving it to another engine,
// is the one kind of step that can run longer than a web server allows a request to take.
function install_heavy_tables() {

	return array(
		'2026.1.2' => array('visitors'),
		'2026.2.5' => array('visitors'),
		'2026.3.4' => array('visitors'),
		'2026.3.6' => array('visitors'),
		'2026.3.8' => array('visitors'),
		// 2026.4.4 rewrites `user` eight times (a column, the engine conversion,
		// the password column, three more columns, a unique index and a whole
		// table UPDATE), `products` four times and `product_groups` three. None
		// of them grows with page views the way `visitors` does, but they grow
		// with the membership and the catalogue, and a shop with a large one
		// pays for every pass.
		//
		// `orders` joins them for the external API's incremental sync index on
		// order_date. It is the table that grows fastest on a working shop, and
		// adding an index to it is a full pass on every server that does not
		// support an in-place ALTER.
		// `page` and `style` carry the LONGTEXT page trees and generated HTML, so
		// a copy-rebuild ALTER on them is slow on a big site; `notifications` is
		// read whole by the notification_reads backfill.
		'2026.4.4' => array('user', 'products', 'product_groups', 'orders', 'page', 'style', 'notifications'),
	);

}

function install_size_label($bytes) {

	$bytes = (float) $bytes;

	if ($bytes >= 1073741824) {

		return number_format($bytes / 1073741824, 1) . ' GB';

	}

	if ($bytes >= 1048576) {

		return number_format($bytes / 1048576, 0) . ' MB';

	}

	return number_format($bytes / 1024, 0) . ' KB';

}

function install_count_label($count) {

	$count = (float) $count;

	if ($count >= 1000000) {

		return number_format($count / 1000000, 1) . 'M';

	}

	if ($count >= 1000) {

		return number_format($count / 1000, 0) . 'k';

	}

	return number_format($count);

}

// Whether the user the site connects with may change the schema. SHOW GRANTS answers for
// the current user; ALL PRIVILEGES on everything or on this database is the usual case.
// Returns array('state' => ok|warning|unknown, 'missing' => array()).
function install_check_grants() {

	$rows = array();

	try {

		$rows = db_values("SHOW GRANTS");

	} catch (Throwable $e) {

		return array('state' => 'unknown', 'missing' => array());

	}

	if (!is_array($rows) || (count($rows) == 0)) {

		return array('state' => 'unknown', 'missing' => array());

	}

	$required = array('SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX');

	$found = array();

	$through_role = false;

	$database = defined('DB_DATABASE') ? DB_DATABASE : '';

	foreach ($rows as $row) {

		$row = (string) $row;

		// GRANT `role`@`%` TO `user`@`host`: the privileges live in the role
		if (preg_match('/^GRANT\s+`[^`]*`@`[^`]*`(\s*,\s*`[^`]*`@`[^`]*`)*\s+TO\s/i', $row)) {

			$through_role = true;

			continue;

		}

		if (!preg_match('/^GRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s/i', $row, $match)) {

			continue;

		}

		$privileges = strtoupper($match[1]);

		$scope = trim($match[2]);

		// the scope is *.*, `db`.* or `db`.`table`; underscores in the name come back escaped
		$scope_database = '';

		if ($scope == '*.*') {

			$scope_database = '*';

		} elseif (preg_match('/^`?([^`]+)`?\.\*$/', $scope, $scope_match)) {

			$scope_database = str_replace('\\', '', $scope_match[1]);

		}

		if (($scope_database != '*') && (strcasecmp($scope_database, $database) != 0)) {

			continue;

		}

		if (strpos($privileges, 'ALL PRIVILEGES') !== false) {

			return array('state' => 'ok', 'missing' => array());

		}

		foreach (preg_split('/\s*,\s*/', $privileges) as $privilege) {

			$privilege = trim(preg_replace('/\s*\(.*$/', '', $privilege));

			$found[$privilege] = true;

		}

	}

	$missing = array();

	foreach ($required as $privilege) {

		if (!isset($found[$privilege])) {

			$missing[] = $privilege;

		}

	}

	if (count($missing) == 0) {

		return array('state' => 'ok', 'missing' => array());

	}

	if ($through_role) {

		return array('state' => 'unknown', 'missing' => $missing);

	}

	return array('state' => 'warning', 'missing' => $missing);

}

// Size and row count of a table as information_schema estimates them; array() when the
// table is not there.
function install_table_size($table) {

	try {

		$row = db_item("SELECT TABLE_ROWS, DATA_LENGTH + INDEX_LENGTH AS bytes, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . e($table) . "'");

	} catch (Throwable $e) {

		return array();

	}

	if (!is_array($row) || (count($row) == 0)) {

		return array();

	}

	return array('rows' => (int) $row['TABLE_ROWS'], 'bytes' => (float) $row['bytes'], 'engine' => (string) $row['ENGINE']);

}

// Everything the screen shows before the start button, for an upgrade from $from_key.
//
// Returns array(
//   'checks'           => array of array('label', 'value', 'state' => ok|warning|error|info, 'detail')
//   'locked'           => bool    another run holds the lock right now
//   'last'             => array   what the previous run left behind at a pending version, or null
//   'backup_available' => bool    a database backup can be written from this screen
//   'database_bytes'   => float   the size of the database, for the backup
// )
function install_preflight($versions, $from_key) {

	global $install_runner;

	// outside a run a failed query is fatal; here every probe may fail quietly
	$was_active = !empty($install_runner['active']);

	$install_runner['active'] = true;

	try {

		$result = install_preflight_checks($versions, $from_key);

	} catch (Throwable $e) {

		// The card is advice, not a gate. Whatever stopped it - a function the
		// host removed, an information_schema the user may not read, a PHP error
		// in a probe - is reported as one line, and the upgrade is still offered.
		install_log('pre-flight could not run: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');

		$result = array(
			'checks' => array(array(
				'label' => lang('Before you start'),
				'value' => lang('could not be checked'),
				'state' => 'info',
				'detail' => $e->getMessage()
			)),
			'locked' => false,
			'last' => null,
			'backup_available' => false,
			'database_bytes' => 0
		);

	} finally {

		$install_runner['active'] = $was_active;

	}

	return $result;

}

function install_preflight_checks($versions, $from_key) {

	$checks = array();

	$result = array('checks' => array(), 'locked' => false, 'last' => null, 'backup_available' => false, 'database_bytes' => 0);

	// the pending versions, and the tables they rewrite
	$pending = array();

	$heavy = install_heavy_tables();

	$tables = array();

	foreach ($versions as $key => $version) {

		if ($key <= $from_key) {

			continue;

		}

		$pending[] = $version['number'];

		if (isset($heavy[$version['number']])) {

			foreach ($heavy[$version['number']] as $table) {

				if (!isset($tables[$table])) {

					$tables[$table] = array();

				}

				$tables[$table][] = $version['number'];

			}

		}

	}

	// MySQL
	$server = '';

	try {

		$server = (string) db_value("SELECT VERSION()");

	} catch (Throwable $e) {

		$server = '';

	}

	$checks[] = array('label' => lang('MySQL'), 'value' => (($server != '') ? $server : '-'), 'state' => 'ok', 'detail' => '');

	// privileges
	$grants = install_check_grants();

	if ($grants['state'] == 'ok') {

		$checks[] = array('label' => lang('Database privileges'), 'value' => lang('sufficient'), 'state' => 'ok', 'detail' => '');

	} elseif ($grants['state'] == 'warning') {

		$checks[] = array(
			'label' => lang('Database privileges'),
			'value' => lang('missing') . ': ' . implode(', ', $grants['missing']),
			'state' => 'warning',
			'detail' => lang('The database user does not seem to have every privilege the schema steps need. Ask the host to grant them, or the upgrade may stop at the first ALTER TABLE.')
		);

	} else {

		$checks[] = array('label' => lang('Database privileges'), 'value' => lang('could not be checked'), 'state' => 'info', 'detail' => '');

	}

	// Large tables. A version can name more than one (2026.4.4 rewrites `user`,
	// `products` and `product_groups`), so every table over the threshold is
	// reported rather than whichever happened to be last in the loop; when none
	// is over it, the biggest one is shown so the line still says something.
	$heavy_detail = '';

	$heavy_value = lang('none');

	$heavy_state = 'ok';

	$heavy_over = array();

	$heavy_reasons = array();

	$heavy_largest = '';

	$heavy_largest_bytes = -1;

	foreach ($tables as $table => $numbers) {

		$size = install_table_size($table);

		if (count($size) == 0) {

			continue;

		}

		$label = $table . ': ' . lang(array('string' => '{var:1} rows', 'vars' => install_count_label($size['rows']))) . ', ' . install_size_label($size['bytes']);

		if (($size['rows'] >= 250000) || ($size['bytes'] >= 268435456)) {

			$heavy_state = 'warning';

			$heavy_over[] = $label;

			$heavy_reasons[] = lang(array(
				'string' => '{var:1} is rewritten by version {var:2}.',
				'vars' => array($table, implode(', ', $numbers))
			));

		}

		if ($size['bytes'] > $heavy_largest_bytes) {

			$heavy_largest_bytes = $size['bytes'];

			$heavy_largest = $label;

		}

	}

	if (count($heavy_over) > 0) {

		$heavy_value = implode(' · ', $heavy_over);

		$heavy_detail = implode(' ', $heavy_reasons) . ' '
			. lang('On a table this size a single step can take minutes, and it is the one step a web server timeout can cut short. Every version runs in its own request, so if that happens the screen offers to continue from the same version.');

	} elseif ($heavy_largest != '') {

		$heavy_value = $heavy_largest;

	}

	$checks[] = array('label' => lang('Large tables'), 'value' => $heavy_value, 'state' => $heavy_state, 'detail' => $heavy_detail);

	// server limits: the values in php.ini, not what this script raised them to
	$settings = function_exists('ini_get_all') ? @ini_get_all('core', true) : false;

	$time_limit = (is_array($settings) && isset($settings['max_execution_time']['global_value'])) ? (string) $settings['max_execution_time']['global_value'] : (string) ini_get('max_execution_time');

	$memory_limit = (is_array($settings) && isset($settings['memory_limit']['global_value'])) ? (string) $settings['memory_limit']['global_value'] : (string) ini_get('memory_limit');

	$checks[] = array(
		'label' => lang('Server limits'),
		'value' => 'max_execution_time ' . $time_limit . ' · memory_limit ' . $memory_limit,
		'state' => 'info',
		'detail' => lang('Each version runs in its own request, so a request limit of the web server (FastCGI, proxy) bounds one version rather than the whole upgrade.')
	);

	// another run
	$result['locked'] = install_lock_is_held();

	if ($result['locked']) {

		$checks[] = array('label' => lang('Another upgrade'), 'value' => lang('running'), 'state' => 'warning', 'detail' => install_lock_message());

	}

	// the last run
	$last = install_last_read();

	if ($last !== null) {

		$last_key = install_version_key($last['version'], $versions);

		if (($last_key === false) || ($last_key <= $from_key)) {

			// it stopped at a version that has been applied since
			install_last_clear();

		} else {

			$result['last'] = $last;

			$checks[] = array(
				'label' => lang('Last run'),
				'value' => lang(array('string' => 'stopped at {var:1}', 'vars' => $last['version'])) . (($last['time'] > 0) ? ' · ' . date('d.m.Y H:i', $last['time']) : ''),
				'state' => 'warning',
				'detail' => $last['message'] . (($last['statement'] != '') ? ' — ' . $last['statement'] : '')
			);

		}

	}

	// backup
	$database_bytes = 0;

	try {

		$database_bytes = (float) db_value("SELECT SUM(DATA_LENGTH + INDEX_LENGTH) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");

	} catch (Throwable $e) {

		$database_bytes = 0;

	}

	$result['database_bytes'] = $database_bytes;

	$backups_path = dirname(__FILE__) . '/../../data/backups';

	// disk_free_space is on many hosts' disable_functions list; without it the
	// line simply does not say how much room there is.
	$free = false;

	if (function_exists('disk_free_space')) {

		$free = @disk_free_space(is_dir($backups_path) ? $backups_path : dirname($backups_path));

	}

	$backup_value = install_size_label($database_bytes);

	if ($free !== false) {

		$backup_value .= ' · ' . lang(array('string' => '{var:1} free', 'vars' => install_size_label($free)));

	}

	if (!extension_loaded('pdo_mysql')) {

		$checks[] = array(
			'label' => lang('Database backup'),
			'value' => $backup_value,
			'state' => 'warning',
			'detail' => lang('The pdo_mysql extension is not loaded, so a backup cannot be written from this screen. Take one from the Backups area of the File Manager before you start.')
		);

	} elseif (($free !== false) && ($free < ($database_bytes * 2))) {

		$result['backup_available'] = true;

		$checks[] = array(
			'label' => lang('Database backup'),
			'value' => $backup_value,
			'state' => 'warning',
			'detail' => lang('There may not be enough free space for a copy of the database. Make room first, or take the backup somewhere else.')
		);

	} else {

		$result['backup_available'] = true;

		$checks[] = array('label' => lang('Database backup'), 'value' => $backup_value, 'state' => 'ok', 'detail' => '');

	}

	$result['checks'] = $checks;

	return $result;

}


// Writes a dump of the database into data/backups/pre_upgrade_<version>_<time>/sql.sql,
// the same file the Backups area writes, so the folder can be restored from the install
// screen like any other backup. Returns array('ok', 'folder', 'bytes', 'seconds', 'error').
function install_backup_database($database_version) {

	$result = array('ok' => false, 'folder' => '', 'bytes' => 0, 'seconds' => 0, 'error' => '');

	if (!extension_loaded('pdo_mysql')) {

		$result['error'] = lang('The pdo_mysql extension is not loaded, so a backup cannot be written from this screen. Take one from the Backups area of the File Manager before you start.');

		return $result;

	}

	$mysqldump = dirname(__FILE__) . '/../../mysqldump.php';

	if (!file_exists($mysqldump)) {

		$result['error'] = lang('mysqldump.php is missing from the software directory.');

		return $result;

	}

	include_once $mysqldump;

	$folder = 'pre_upgrade_' . preg_replace('/[^0-9A-Za-z.]+/', '_', (string) $database_version) . '_' . date('Ymd_His');

	$path = dirname(__FILE__) . '/../../data/backups/' . $folder;

	if ((!is_dir($path)) && (!@mkdir($path, 0755, true))) {

		$result['error'] = lang(array('string' => 'The folder {var:1} could not be created under data/backups.', 'vars' => $folder));

		return $result;

	}

	@file_put_contents($path . '/.htaccess', 'deny from all');

	$started = microtime(true);

	if (function_exists('set_time_limit')) {

		@set_time_limit(0);

	}

	try {

		$dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USERNAME, DB_PASSWORD);

		$dump->start($path . '/sql.sql');

	} catch (\Throwable $e) {

		$result['error'] = $e->getMessage();

		@unlink($path . '/sql.sql');

		@unlink($path . '/.htaccess');

		@rmdir($path);

		return $result;

	}

	$result['ok'] = true;

	$result['folder'] = $folder;

	$result['bytes'] = (float) @filesize($path . '/sql.sql');

	$result['seconds'] = round(microtime(true) - $started, 1);

	return $result;

}

// ─── Automated upgrade (cron) ────────────────────────────────────────────────

// Whether the secret in the request matches the one in data/config.php. The constant is
// opt-in: an installation that never defined it has no key path at all. A short constant
// counts as undefined, so a typo cannot open the door with a one-character key.
function install_secret_matches($secret) {

	if (!defined('AUTOMATED_UPGRADE_SECRET')) {

		return false;

	}

	$expected = (string) AUTOMATED_UPGRADE_SECRET;

	if (strlen($expected) < 16) {

		install_log('AUTOMATED_UPGRADE_SECRET is shorter than 16 characters and is ignored');

		return false;

	}

	$secret = (string) $secret;

	if ($secret == '') {

		return false;

	}

	return hash_equals($expected, $secret);

}

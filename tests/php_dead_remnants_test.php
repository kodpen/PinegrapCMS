<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The PHP 5 remnants stay out of the product: no call into the mysql
 * extension (removed from PHP in 7.0) and no DB_LEGACY setting that opened
 * a second connection through it; no magic quotes strip
 * (get_magic_quotes_gpc() always answered false from PHP 5.4, is deprecated
 * in 7.4 and removed in 8.0) outside the installer's own copy.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// What the product's PHP files still hold, as "file:line name" per kind:
// 'mysql' (a call of a mysql_* function), 'db_legacy' (the DB_LEGACY name)
// and 'magic_quotes' (the magic quotes functions or ini settings). The
// vendored libraries and data/ are left out as tools/phpcs.xml leaves them
// out; the config template data/config(default).php is read.
function php_dead_remnants_test_found()
{
	static $found = null;

	if ($found !== null) {
		return $found;
	}

	$root = str_replace('\\', '/', PG_FUNCTIONS_DIR);
	$excluded = array();

	preg_match_all('#<exclude-pattern>([^<]+)</exclude-pattern>#', (string) file_get_contents(dirname($root) . '/tools/phpcs.xml'), $patterns);

	foreach ($patterns[1] as $pattern) {
		$excluded[] = '#' . str_replace('*', '.*', $pattern) . '#';
	}

	$paths = array($root . '/data/config(default).php');
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', $file->getPathname());

		if (substr($path, -4) !== '.php') {
			continue;
		}

		foreach ($excluded as $pattern) {
			if (preg_match($pattern, $path)) {
				continue 2;
			}
		}

		$paths[] = $path;
	}

	$found = array('mysql' => array(), 'db_legacy' => array(), 'magic_quotes' => array());

	foreach ($paths as $path) {
		$relative = substr($path, strlen($root) + 1);
		$tokens = token_get_all((string) file_get_contents($path));
		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i])) {
				continue;
			}

			$text = $tokens[$i][1];
			$where = $relative . ':' . $tokens[$i][2] . ' ';

			if ($tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
				$value = substr($text, 1, -1);

				if ($value === 'DB_LEGACY') {
					$found['db_legacy'][] = $where . $value;
				} elseif (stripos($value, 'magic_quotes') !== false) {
					$found['magic_quotes'][] = $where . $value;
				}

				continue;
			}

			if ($tokens[$i][0] !== T_STRING) {
				continue;
			}

			if ($text === 'DB_LEGACY') {
				$found['db_legacy'][] = $where . $text;
				continue;
			}

			if (stripos($text, 'magic_quotes') !== false) {
				$found['magic_quotes'][] = $where . $text;
				continue;
			}

			if (stripos($text, 'mysql_') !== 0) {
				continue;
			}

			// A call: the name followed by "(", and not a declaration, a
			// method or a static method of the same name.
			$before = $i - 1;

			while (($before >= 0) && is_array($tokens[$before]) && ($tokens[$before][0] === T_WHITESPACE)) {
				$before--;
			}

			if (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true)) {
				continue;
			}

			$after = $i + 1;

			while (($after < $count) && is_array($tokens[$after]) && ($tokens[$after][0] === T_WHITESPACE)) {
				$after++;
			}

			if (($after < $count) && ($tokens[$after] === '(')) {
				$found['mysql'][] = $where . $text;
			}
		}
	}

	return $found;
}

function test_php_dead_remnants_no_mysql_extension_call()
{
	$found = php_dead_remnants_test_found();

	pg_assert_same(array(), $found['mysql']);
}

function test_php_dead_remnants_no_db_legacy_setting()
{
	$found = php_dead_remnants_test_found();

	pg_assert_same(array(), $found['db_legacy']);
}

function test_php_dead_remnants_magic_quotes_only_in_installer()
{
	$files = array();

	foreach (php_dead_remnants_test_found()['magic_quotes'] as $finding) {
		$files[substr($finding, 0, strpos($finding, ':'))] = true;
	}

	// The installer does not load init.php and keeps its own copy of the
	// strip; it is the one place left.
	pg_assert_same(array('install/index.php'), array_keys($files));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Pinegrap's own code (the product, the root files, tools/ and
 * tests/) calls nothing PHP 8.1 to 8.5 deprecates: the resource free calls
 * that do nothing since PHP 8.0, the functions, constants and cast names
 * deprecated for removal in PHP 9, the shell backtick operator and the CSV
 * functions without their escape argument. Each one prints "Deprecated"
 * into the error log when its line runs. A call kept on purpose behind a
 * version check carries a phpcs:ignore on the same line and is let through.
 * Embedded third-party libraries keep their own code and are not read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Functions deprecated from PHP 8.1 to 8.5, by lower-case name.
 *
 * @return array<string,string> name => the version that deprecates it
 */
function deprecated_calls_test_functions()
{
	return array(
		'curl_close' => '8.5',
		'curl_share_close' => '8.5',
		'finfo_close' => '8.5',
		'imagedestroy' => '8.5',
		'xml_parser_free' => '8.5',
		'mysqli_execute' => '8.5',
		'openssl_x509_free' => '8.4',
		'openssl_pkey_free' => '8.4',
		'mysqli_ping' => '8.4',
		'mysqli_kill' => '8.4',
		'mysqli_refresh' => '8.4',
		'lcg_value' => '8.4',
		'xml_set_object' => '8.4',
		'assert_options' => '8.3',
		'utf8_encode' => '8.2',
		'utf8_decode' => '8.2',
		'date_sunrise' => '8.1',
		'date_sunset' => '8.1',
		'strftime' => '8.1',
		'gmstrftime' => '8.1',
		'mhash' => '8.1',
		'mhash_count' => '8.1',
		'mhash_get_block_size' => '8.1',
		'mhash_get_hash_name' => '8.1',
		'mhash_keygen_s2k' => '8.1',
		'odbc_result_all' => '8.1',
		'openssl_free_key' => '8.0',
	);
}

/**
 * Constants deprecated from PHP 8.1 to 8.5. PDO::MYSQL_ATTR_* (8.5) is
 * matched by its prefix in deprecated_calls_test_scan().
 *
 * @return string[]
 */
function deprecated_calls_test_constants()
{
	return array('FILTER_SANITIZE_STRING', 'FILTER_SANITIZE_STRIPPED', 'FILE_BINARY', 'FILE_TEXT', 'E_STRICT', 'CURLOPT_BINARYTRANSFER', 'MT_RAND_PHP');
}

/**
 * The CSV functions whose escape argument must be given from PHP 8.4, with
 * its position (1-based).
 *
 * @return array<string,int>
 */
function deprecated_calls_test_csv_functions()
{
	return array('fputcsv' => 5, 'fgetcsv' => 5, 'str_getcsv' => 4);
}

/**
 * The deprecated uses in one piece of PHP source.
 *
 * @param string $source PHP source
 * @return string[] "line: what" for each use found
 */
function deprecated_calls_test_scan($source)
{
	$functions = deprecated_calls_test_functions();
	$constants = deprecated_calls_test_constants();
	$csv = deprecated_calls_test_csv_functions();
	$lines = explode("\n", $source);
	$tokens = token_get_all($source);
	$count = count($tokens);
	$found = array();
	$line = 1;

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (is_array($token)) {
			$line = $token[2];
		}

		$what = '';

		if ($token === '`') {
			$what = 'the backtick operator (8.5)';
		} elseif (is_array($token) && in_array($token[0], array(T_BOOL_CAST, T_INT_CAST, T_DOUBLE_CAST, T_STRING_CAST), true)) {
			$cast = strtolower((string) preg_replace('/\s+/', '', $token[1]));

			if (in_array($cast, array('(boolean)', '(integer)', '(double)', '(binary)'), true)) {
				$what = 'the cast ' . $cast . ' (8.5)';
			}
		} elseif (is_array($token) && (($token[0] === T_STRING) || deprecated_calls_test_is_global_name($token))) {
			$name = ltrim($token[1], '\\');
			$previous = deprecated_calls_test_neighbour($tokens, $i, -1);
			$next = deprecated_calls_test_neighbour($tokens, $i, 1);
			$after_object = in_array($previous, array('->', '?->', '::', 'function', 'const', 'new'), true);

			// PHP 7 splits "\name" and "Space\name" into separator and name:
			// the first is the global function, the second is not.
			if ($previous === '\\') {
				$before = $i - 1;

				while (is_array($tokens[$before]) && ($tokens[$before][0] === T_WHITESPACE)) {
					$before--;
				}

				$owner = deprecated_calls_test_neighbour($tokens, $before, -1);
				$after_object = (bool) preg_match('/^[A-Za-z_\\\\]/', $owner) && ($owner !== 'new') && ($owner !== 'return') && ($owner !== 'echo');
			}

			if (($previous === '::') && (strpos($name, 'MYSQL_ATTR_') === 0) && (strtolower(deprecated_calls_test_neighbour($tokens, $i - 1, -1, true)) === 'pdo')) {
				$what = 'PDO::' . $name . ' (8.5)';
			} elseif (($previous === '->') && ($next === '(') && ($name === 'setAccessible')) {
				$what = '->setAccessible() (8.5)';
			} elseif (!$after_object && ($next !== '(') && in_array($name, $constants, true)) {
				$what = $name . ' (constant)';
			} elseif (!$after_object && ($next === '(')) {
				$lower = strtolower($name);

				if (isset($functions[$lower])) {
					$what = $lower . '() (' . $functions[$lower] . ')';
				} elseif (isset($csv[$lower]) && (deprecated_calls_test_arguments($tokens, $i) < $csv[$lower])) {
					$what = $lower . '() without its escape argument (8.4)';
				} elseif (in_array($lower, array('get_class', 'get_parent_class'), true) && (deprecated_calls_test_arguments($tokens, $i) === 0)) {
					$what = $lower . '() without an argument (8.3)';
				}
			}
		}

		if ($what === '') {
			continue;
		}

		// A call kept on purpose behind a version check says so on its line.
		if (isset($lines[$line - 1]) && (strpos($lines[$line - 1], 'phpcs:ignore') !== false)) {
			continue;
		}

		$found[] = $line . ': ' . $what;
	}

	return $found;
}

/**
 * Whether a token is a fully qualified name of one part ("\name"), which
 * PHP 8 gives as one token.
 *
 * @param array<int,mixed> $token
 * @return bool
 */
function deprecated_calls_test_is_global_name($token)
{
	return defined('T_NAME_FULLY_QUALIFIED') && ($token[0] === constant('T_NAME_FULLY_QUALIFIED')) && (substr_count($token[1], '\\') === 1);
}

/**
 * The text of the nearest token before ($step -1) or after ($step 1) token
 * $i, whitespace and comments skipped.
 *
 * @param array<int,mixed> $tokens
 * @param int $i
 * @param int $step
 * @param bool $name_only with a namespaced name, only its last part
 * @return string
 */
function deprecated_calls_test_neighbour($tokens, $i, $step, $name_only = false)
{
	for ($j = $i + $step; isset($tokens[$j]); $j += $step) {
		$token = $tokens[$j];

		if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}

		$text = is_array($token) ? $token[1] : $token;

		if ($name_only) {
			$parts = explode('\\', $text);
			$text = (string) end($parts);
		}

		return $text;
	}

	return '';
}

/**
 * The number of arguments of the call whose name is token $i.
 *
 * @param array<int,mixed> $tokens
 * @param int $i
 * @return int
 */
function deprecated_calls_test_arguments($tokens, $i)
{
	$depth = 0;
	$arguments = 0;
	$empty = true;

	for ($j = $i + 1; isset($tokens[$j]); $j++) {
		$token = $tokens[$j];
		$text = is_array($token) ? $token[1] : $token;

		if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}

		if (in_array($text, array('(', '[', '{'), true) || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$depth++;

			if ($depth === 1) {
				continue;
			}
		} elseif (in_array($text, array(')', ']', '}'), true)) {
			$depth--;

			if ($depth === 0) {
				break;
			}
		} elseif (($text === ',') && ($depth === 1)) {
			$arguments++;
			$empty = true;
			continue;
		} elseif (is_array($token) && ($token[0] === T_ELLIPSIS) && ($depth === 1)) {
			// An unpacked array may hold any number of arguments.
			return PHP_INT_MAX;
		}

		$empty = false;
	}

	// A trailing comma adds no argument.
	return $empty ? $arguments : $arguments + 1;
}

/**
 * The PHP files of Pinegrap's own code, embedded libraries left out.
 *
 * @return string[] absolute paths
 */
function deprecated_calls_test_files()
{
	$root = str_replace('\\', '/', dirname(PG_FUNCTIONS_DIR));
	$skip = array(
		'/pinegrap/includes/phpmailer/',
		'/pinegrap/includes/stripe/',
		'/pinegrap/includes/iyzipay-php/',
		'/pinegrap/includes/phpexcel/',
		'/pinegrap/includes/boxpacker/',
		'/pinegrap/includes/dompdf/',
		'/pinegrap/includes/qrcode/',
		'/pinegrap/assets/lib/',
		'/pinegrap/data/',
		'/pinegrap/pclzip.lib.php',
		'/pinegrap/excel_reader2.php',
		'/pinegrap/mysqldump.php',
	);
	$files = glob($root . '/*.php') ?: array();

	foreach (array('pinegrap', 'tools', 'tests') as $folder) {
		if (!is_dir($root . '/' . $folder)) {
			continue;
		}

		$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS));

		foreach ($directory as $file) {
			if (substr($file->getFilename(), -4) !== '.php') {
				continue;
			}

			$path = substr(str_replace('\\', '/', $file->getPathname()), strlen($root));

			foreach ($skip as $prefix) {
				if (strpos($path, $prefix) === 0) {
					continue 2;
				}
			}

			$files[] = $file->getPathname();
		}
	}

	return $files;
}

function test_deprecated_calls_scan_finds_each_family()
{
	$source = "<?php\ncurl_close(\$ch);\n"
		. "\$x = (integer) \$y;\n"
		. "\$out = `ls`;\n"
		. "fputcsv(\$h, \$row, ',', '\"');\n"
		. "\$f = FILTER_SANITIZE_STRING;\n"
		. "\$a = PDO::MYSQL_ATTR_INIT_COMMAND;\n"
		. "\$r->setAccessible(true);\n"
		. "echo get_class();\n"
		. "\\utf8_encode(\$s);\n";

	pg_assert_same(array(
		'2: curl_close() (8.5)',
		'3: the cast (integer) (8.5)',
		'4: the backtick operator (8.5)',
		'4: the backtick operator (8.5)',
		'5: fputcsv() without its escape argument (8.4)',
		'6: FILTER_SANITIZE_STRING (constant)',
		'7: PDO::MYSQL_ATTR_INIT_COMMAND (8.5)',
		'8: ->setAccessible() (8.5)',
		'9: get_class() without an argument (8.3)',
		'10: utf8_encode() (8.2)',
	), deprecated_calls_test_scan($source));
}

function test_deprecated_calls_scan_lets_through_what_is_not_deprecated()
{
	$source = "<?php\n"
		. "// curl_close(\$ch) in a comment\n"
		. "\$s = 'curl_close(\$ch)';\n"
		. "\$client->curl_close();\n"
		. "Http::curl_close();\n"
		. "function strftime_like() {}\n"
		. "\$x = (int) \$y + (bool) \$z + (float) \$w;\n"
		. "fputcsv(\$h, \$row, ',', '\"', '\\\\');\n"
		. "str_getcsv(\$line, ';', '\"', '\\\\');\n"
		. "fgetcsv(...\$args);\n"
		. "echo get_class(\$e);\n"
		. "if (PHP_VERSION_ID < 80000) { curl_close(\$ch); } // phpcs:ignore -- guarded\n"
		. "\$c = \\Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY;\n"
		. "\$e = 2048; // E_STRICT\n";

	pg_assert_same(array(), deprecated_calls_test_scan($source));
}

function test_own_code_calls_nothing_deprecated()
{
	$root = str_replace('\\', '/', dirname(PG_FUNCTIONS_DIR));
	$found = array();
	$files = deprecated_calls_test_files();

	foreach ($files as $file) {
		foreach (deprecated_calls_test_scan((string) file_get_contents($file)) as $use) {
			$found[] = ltrim(substr(str_replace('\\', '/', $file), strlen($root)), '/') . ':' . $use;
		}
	}

	pg_assert_true(count($files) > 500, 'own code files read: ' . count($files));
	pg_assert_same(array(), $found, 'deprecated calls in own code');
}

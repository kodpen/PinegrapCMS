<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every fgetcsv(), fputcsv() and str_getcsv() call in the product passes its
 * escape character. Leaving it out is deprecated from PHP 8.4, because its
 * default is to change; the calls pass '\\', the value they always had, so
 * nothing they read or write changes on any version.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The calls under pinegrap/ that leave the escape argument out, as
// "file:line function", vendored libraries (tools/phpcs.xml) left out.
function csv_escape_argument_test_missing()
{
	$root = PG_FUNCTIONS_DIR;
	$excluded = array();

	preg_match_all('#<exclude-pattern>([^<]+)</exclude-pattern>#', (string) file_get_contents(dirname($root) . '/tools/phpcs.xml'), $patterns);

	foreach ($patterns[1] as $pattern) {
		$excluded[] = '#' . str_replace('*', '.*', $pattern) . '#';
	}

	// The argument's position: fgetcsv(stream, length, separator, enclosure,
	// escape), fputcsv(stream, fields, separator, enclosure, escape),
	// str_getcsv(string, separator, enclosure, escape).
	$position = array('fgetcsv' => 5, 'fputcsv' => 5, 'str_getcsv' => 4);
	$missing = array();
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

		$tokens = token_get_all((string) file_get_contents($path));
		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_STRING) || !isset($position[strtolower($tokens[$i][1])])) {
				continue;
			}

			// A declaration or a method of the same name is not the function.
			$before = $i - 1;

			while (($before >= 0) && is_array($tokens[$before]) && ($tokens[$before][0] === T_WHITESPACE)) {
				$before--;
			}

			if (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true)) {
				continue;
			}

			$open = $i + 1;

			while (($open < $count) && is_array($tokens[$open]) && ($tokens[$open][0] === T_WHITESPACE)) {
				$open++;
			}

			if (($open >= $count) || ($tokens[$open] !== '(')) {
				continue;
			}

			$depth = 0;
			$arguments = 1;

			for ($j = $open; $j < $count; $j++) {
				$token = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];

				if (in_array($token, array('(', '[', '{', '${'), true)) {
					$depth++;
				} elseif (in_array($token, array(')', ']', '}'), true)) {
					$depth--;

					if ($depth === 0) {
						break;
					}
				} elseif (($depth === 1) && ($token === ',')) {
					$arguments++;
				}
			}

			if ($arguments < $position[strtolower($tokens[$i][1])]) {
				$missing[] = substr($path, strlen($root) + 1) . ':' . $tokens[$i][2] . ' ' . $tokens[$i][1];
			}
		}
	}

	return $missing;
}

function test_csv_escape_argument_every_call_passes_it()
{
	pg_assert_same(array(), csv_escape_argument_test_missing());
}

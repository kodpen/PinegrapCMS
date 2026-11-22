<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Calls that hand a second argument to a helper that takes one. PHP drops
 * the extra argument without a word: db_item(), db_value() and db_values()
 * run their SQL as given, so values meant for its placeholders never reach
 * it and the query fails (values go through db_q()); lang() fills {var:N}
 * only from array('string' => ..., 'vars' => ...), so the placeholder is
 * printed as it is.
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
 * file:line of every call of one of $names with more than one argument.
 *
 * @param string $source
 * @param array  $names
 * @return int[] line numbers
 */
function single_argument_helpers_calls_with_more($source, array $names)
{
	$tokens = token_get_all($source);
	$count = count($tokens);
	$found = array();

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_STRING) || !in_array(strtolower($tokens[$i][1]), $names, true)) {
			continue;
		}

		// A definition, a method or a static call is another function.
		$before = $i - 1;
		while (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$before--;
		}
		if (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW), true)) {
			continue;
		}

		$next = $i + 1;
		while (($next < $count) && is_array($tokens[$next]) && ($tokens[$next][0] === T_WHITESPACE)) {
			$next++;
		}
		if (($next >= $count) || ($tokens[$next] !== '(')) {
			continue;
		}

		$depth = 0;
		$commas = 0;
		for ($j = $next; $j < $count; $j++) {
			$token = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
			if (in_array($token, array('(', '[', '{'), true) || (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
			} elseif (in_array($token, array(')', ']', '}'), true)) {
				$depth--;
				if ($depth === 0) {
					break;
				}
			} elseif (($token === ',') && ($depth === 1)) {
				$commas++;
			}
		}

		if ($commas > 0) {
			$found[] = (int) $tokens[$i][2];
		}
	}

	return $found;
}

function test_single_argument_helpers_reader_counts_arguments()
{
	$source = "<?php\nlang('a');\nlang('{var:1} min', \$n);\nfunction db_item(\$query) {}\n\$x->db_value('a', 1);\ndb_values(f(1, 2));\ndb_item('?', array(1));\n";

	pg_assert_same(array(3, 7), single_argument_helpers_calls_with_more($source, array('lang', 'db_item', 'db_value', 'db_values')));
}

function test_single_argument_helpers_get_one_argument()
{
	$root = PG_FUNCTIONS_DIR;
	$names = array('lang', 'db_item', 'db_value', 'db_values');
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$found = array();
	$checked = 0;

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}
		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
		if ((strpos($relative, 'data/') === 0) || (strpos($relative, 'includes/local/') === 0) || (strpos($relative, '/lib/') !== false) || (strpos($relative, 'assets/') === 0)) {
			continue;
		}
		$source = (string) file_get_contents($file->getPathname());
		if (!preg_match('/\b(lang|db_item|db_values?)\s*\(/', $source)) {
			continue;
		}
		$checked++;
		foreach (single_argument_helpers_calls_with_more($source, $names) as $line) {
			$found[] = $relative . ':' . $line;
		}
	}

	pg_assert_same(true, $checked > 100, 'the product files were read');
	pg_assert_same(array(), $found, 'a second argument is dropped: pass values through db_q() or lang(array(\'string\' => ..., \'vars\' => ...))');
}

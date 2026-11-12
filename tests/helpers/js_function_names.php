<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Finds a function name declared twice in one scope of a script. JavaScript
 * lets the second declaration replace the first without a word, and
 * `node --check` parses such a file happily: the first function's callers
 * then run the second. A small scanner, not a parser: it steps over
 * comments, strings, template literals and regular expressions, keeps one
 * list of names per pair of braces, and counts `function name(` only where
 * a statement starts (an expression's name belongs to that expression).
 *
 * Not a test file (tools/test.php runs tests/*_test.php only): a test
 * requires it with require_once(__DIR__ . '/helpers/js_function_names.php').
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
 * The function names a script declares twice in one scope.
 *
 * @param string $source the script
 * @return array[] each name, line (of the first) and again (the line of the
 *                 later one), in the order the later ones come
 */
function pg_test_js_duplicate_functions($source)
{
	$source = (string) $source;
	$length = strlen($source);
	$word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$';
	$space = " \t\r\n\f\v";
	// After these a slash starts a regular expression, not a division.
	$before_regex = array('return', 'typeof', 'case', 'do', 'else', 'in', 'of', 'new', 'delete', 'void', 'throw', 'yield', 'await', 'instanceof');
	// After these a statement starts, so `function name(` declares a name.
	$statement = array('', '{', '}', ';', 'async', 'export', 'default');
	$scopes = array(array());
	$kinds = array('code');
	$last = '';
	$newline = false;
	$found = array();
	$i = 0;

	while ($i < $length) {
		$skip = strspn($source, $space, $i);

		if ($skip > 0) {
			$newline = $newline || (strpos(substr($source, $i, $skip), "\n") !== false);
			$i += $skip;
			continue;
		}

		$char = $source[$i];
		$next = ($i + 1 < $length) ? $source[$i + 1] : '';

		if ($char === '/' && $next === '/') {
			$end = strpos($source, "\n", $i);
			$i = ($end === false) ? $length : $end;
			continue;
		}

		if ($char === '/' && $next === '*') {
			$end = strpos($source, '*/', $i + 2);
			$newline = $newline || (strpos(substr($source, $i, ($end === false) ? $length - $i : $end - $i), "\n") !== false);
			$i = ($end === false) ? $length : $end + 2;
			continue;
		}

		if ($char === '"' || $char === "'") {
			$i = pg_test_js_skip_match('/\G' . $char . '(?:[^' . $char . '\\\\\n]|\\\\.)*' . $char . '?/s', $source, $i);
			$last = 'string';
			$newline = false;
			continue;
		}

		if ($char === '`') {
			if (pg_test_js_template_text($source, $i, $length)) {
				$scopes[] = array();
				$kinds[] = 'template';
			}

			$last = 'string';
			$newline = false;
			continue;
		}

		if ($char === '/') {
			$regex = in_array($last, $before_regex, true) || ($last === '') || (strlen($last) === 1 && strpos('(,=:[!&|?{};+-*%<>~^', $last) !== false);

			if ($regex) {
				$i = pg_test_js_skip_match('/\G\/(?:[^\/\\\\\[\n]|\\\\.|\[(?:[^\]\\\\\n]|\\\\.)*\])+\/[a-z]*/', $source, $i);
				$last = 'regex';
				$newline = false;
				continue;
			}
		}

		$run = strspn($source, $word, $i);

		if ($run > 0) {
			$token = substr($source, $i, $run);
			$declares = ($token === 'function')
				&& (in_array($last, $statement, true) || ($newline && preg_match('/^[\w$)\]]+$/', $last) && !in_array($last, $before_regex, true)));
			$i += $run;

			if ($declares && preg_match('/\G\s*\*?\s*([A-Za-z_$][\w$]*)\s*\(/', $source, $match, 0, $i)) {
				$name = $match[1];
				$scope = count($scopes) - 1;

				// Offsets are kept; lines are counted only for a name found twice.
				if (isset($scopes[$scope][$name])) {
					$found[] = array(
						'name'  => $name,
						'line'  => substr_count($source, "\n", 0, $scopes[$scope][$name]) + 1,
						'again' => substr_count($source, "\n", 0, $i) + 1,
					);
				} else {
					$scopes[$scope][$name] = $i;
				}
			}

			$last = $token;
			$newline = false;
			continue;
		}

		if ($char === '{') {
			$scopes[] = array();
			$kinds[] = 'code';
		} elseif ($char === '}' && count($scopes) > 1) {
			array_pop($scopes);

			// The end of a template's ${…}: the template's text goes on.
			if (array_pop($kinds) === 'template') {
				if (pg_test_js_template_text($source, $i, $length)) {
					$scopes[] = array();
					$kinds[] = 'template';
				}

				$last = 'string';
				$newline = false;
				continue;
			}
		}

		$last = $char;
		$newline = false;
		$i++;
	}

	return $found;
}

/**
 * The end of what a pattern matches at a position, or the next character
 * when it matches nothing there.
 *
 * @param string $pattern anchored with \G
 * @param string $source
 * @param int    $at
 * @return int
 */
function pg_test_js_skip_match($pattern, $source, $at)
{
	return preg_match($pattern, $source, $match, 0, $at) ? $at + strlen($match[0]) : $at + 1;
}

/**
 * Steps over a template literal's text from its opening backtick, or from
 * the brace that closes one of its ${…}.
 *
 * @param string $source
 * @param int    $i      moved past the closing backtick, or past the ${
 * @param int    $length
 * @return bool true when a ${…} opens (its code follows)
 */
function pg_test_js_template_text($source, &$i, $length)
{
	$i++;

	while ($i < $length) {
		$char = $source[$i];

		if ($char === '\\') {
			$i += 2;
		} elseif ($char === '`') {
			$i++;

			return false;
		} elseif ($char === '$' && ($i + 1 < $length) && $source[$i + 1] === '{') {
			$i += 2;

			return true;
		} else {
			$i++;
		}
	}

	return false;
}

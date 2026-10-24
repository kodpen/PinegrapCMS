<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reading product code as data, for tests that cannot call it: a function
 * that runs a query or a schema probe (a *_ready() function, a column
 * check) cannot run without a database, but the array literal it is built
 * from can still be read from its source. The Workspace's change catalogue,
 * ws_change_types(), is the first: it drops kinds and fields after the
 * probes, so a test reads it from the literal, with every kind and field
 * the code knows, whatever a given site runs.
 *
 * Not a test file (tools/test.php runs tests/*_test.php only): a test
 * requires it with require_once(__DIR__ . '/helpers/source_reader.php').
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
 * A value of an array literal that is not a literal itself (a call such as
 * lang('Name'), a constant, a condition): kept as the code that computes it,
 * so a test can tell "a limit of 255" from "a limit some code works out".
 */
class PgTestSourceExpression
{
	/** @var string the code, with its runs of whitespace made one space */
	public $code;

	public function __construct($code)
	{
		$this->code = $code;
	}
}

/**
 * The source of a loaded function, from its "function" line to its closing
 * brace (the doc comment above it left out).
 *
 * @param string $function
 * @return string
 */
function pg_test_function_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

/**
 * The tokens of a piece of PHP code without its comments. Whitespace is
 * kept, so an expression can be given back as it was written;
 * pg_test_source_skip() steps over it.
 *
 * @param string $code
 * @return array
 */
function pg_test_source_tokens($code)
{
	$out = array();

	foreach (token_get_all('<?php ' . $code) as $token) {
		if (is_array($token) && in_array($token[0], array(T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}

		$out[] = $token;
	}

	return $out;
}

/**
 * Moves $i past whitespace.
 *
 * @param array $tokens
 * @param int   $i
 */
function pg_test_source_skip(array $tokens, &$i)
{
	while (isset($tokens[$i]) && is_array($tokens[$i]) && ($tokens[$i][0] === T_WHITESPACE)) {
		$i++;
	}
}

/**
 * The text of a token.
 *
 * @param mixed $token
 * @return string
 */
function pg_test_source_text($token)
{
	return is_array($token) ? (string) $token[1] : (string) $token;
}

/**
 * Does an array literal start at $i: array( or [ ?
 *
 * @param array $tokens
 * @param int   $i
 * @return bool
 */
function pg_test_source_array_starts(array $tokens, $i)
{
	if (($tokens[$i] ?? null) === '[') {
		return true;
	}

	if (!is_array($tokens[$i] ?? null) || ($tokens[$i][0] !== T_ARRAY)) {
		return false;
	}

	$i++;
	pg_test_source_skip($tokens, $i);

	return ($tokens[$i] ?? null) === '(';
}

/**
 * The value of a single-quoted or a plain double-quoted string token.
 *
 * @param string $text the token, quotes included
 * @return string
 */
function pg_test_source_string($text)
{
	$inner = substr($text, 1, -1);

	if ($text[0] === "'") {
		return strtr($inner, array('\\\\' => '\\', "\\'" => "'"));
	}

	return stripcslashes(str_replace('\\$', '$', $inner));
}

/**
 * One value of an array literal, starting at $i, which ends past it: a
 * string, a number, true, false, null, a nested array literal, or else a
 * PgTestSourceExpression holding the code (a literal followed by an
 * operator is an expression too).
 *
 * @param array $tokens pg_test_source_tokens()
 * @param int   $i
 * @return mixed
 */
function pg_test_source_value(array $tokens, &$i)
{
	pg_test_source_skip($tokens, $i);

	$start = $i;
	$token = $tokens[$i] ?? null;
	$found = false;
	$value = null;

	if (pg_test_source_array_starts($tokens, $i)) {
		$close = ($token === '[') ? ']' : ')';

		while ($tokens[$i] !== (($close === ']') ? '[' : '(')) {
			$i++;
		}

		$i++;

		return pg_test_source_elements($tokens, $i, $close);
	}

	$negative = ($token === '-');

	if ($negative) {
		$i++;
		pg_test_source_skip($tokens, $i);
		$token = $tokens[$i] ?? null;
	}

	if (is_array($token) && ($token[0] === T_LNUMBER) && preg_match('/^[0-9]+$/', $token[1])) {
		$value = (int) $token[1];
		$found = true;
	} elseif (is_array($token) && ($token[0] === T_DNUMBER) && is_numeric($token[1])) {
		$value = (float) $token[1];
		$found = true;
	} elseif (!$negative && is_array($token) && ($token[0] === T_CONSTANT_ENCAPSED_STRING)) {
		$value = pg_test_source_string($token[1]);
		$found = true;
	} elseif (!$negative && is_array($token) && ($token[0] === T_STRING) && in_array(strtolower($token[1]), array('true', 'false', 'null'), true)) {
		$value = array('true' => true, 'false' => false, 'null' => null)[strtolower($token[1])];
		$found = true;
	}

	if ($found) {
		$i++;
		$after = $i;
		pg_test_source_skip($tokens, $after);
		$next = $tokens[$after] ?? null;

		if (in_array($next, array(',', ')', ']', ';'), true) || (is_array($next) && ($next[0] === T_DOUBLE_ARROW))) {
			return ($negative && ($value !== null)) ? -$value : $value;
		}
	}

	// Anything else is code: up to the comma, the arrow or the bracket that
	// ends it at its own depth.
	$i = $start;
	$depth = 0;
	$code = '';

	while (isset($tokens[$i])) {
		$token = $tokens[$i];

		if ($depth === 0) {
			if (in_array($token, array(',', ')', ']', ';'), true) || (is_array($token) && ($token[0] === T_DOUBLE_ARROW))) {
				break;
			}
		}

		if (in_array($token, array('(', '[', '{'), true) || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$depth++;
		} elseif (in_array($token, array(')', ']', '}'), true)) {
			$depth--;
		}

		$code .= pg_test_source_text($token);
		$i++;
	}

	if (!isset($tokens[$i])) {
		throw new RuntimeException('The source ends inside a value: ' . trim($code));
	}

	return new PgTestSourceExpression(trim(preg_replace('/\s+/', ' ', $code)));
}

/**
 * The elements of an array literal whose opening bracket is just before $i,
 * up to its closing bracket, which $i ends past.
 *
 * @param array  $tokens
 * @param int    $i
 * @param string $close ')' or ']'
 * @return array
 */
function pg_test_source_elements(array $tokens, &$i, $close)
{
	$out = array();

	while (true) {
		pg_test_source_skip($tokens, $i);

		if (!isset($tokens[$i])) {
			throw new RuntimeException('The source ends inside an array literal.');
		}

		if ($tokens[$i] === $close) {
			$i++;

			return $out;
		}

		$first = pg_test_source_value($tokens, $i);
		pg_test_source_skip($tokens, $i);

		if (is_array($tokens[$i] ?? null) && ($tokens[$i][0] === T_DOUBLE_ARROW)) {
			if (!is_int($first) && !is_string($first)) {
				throw new RuntimeException('An array key that is not a literal: ' . (($first instanceof PgTestSourceExpression) ? $first->code : gettype($first)));
			}

			$i++;
			$out[$first] = pg_test_source_value($tokens, $i);
			pg_test_source_skip($tokens, $i);
		} else {
			$out[] = $first;
		}

		$token = $tokens[$i] ?? null;

		if ($token === ',') {
			$i++;
		} elseif ($token !== $close) {
			throw new RuntimeException('Unexpected "' . pg_test_source_text($token) . '" in an array literal.');
		}
	}
}

/**
 * The array literal a piece of code assigns to a variable (the first
 * "$variable = array(...)" or "$variable = [...]").
 *
 * @param string $code
 * @param string $variable with its $
 * @return array
 */
function pg_test_source_array_in($code, $variable)
{
	$tokens = pg_test_source_tokens($code);
	$count = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_VARIABLE) || ($tokens[$i][1] !== $variable)) {
			continue;
		}

		$j = $i + 1;
		pg_test_source_skip($tokens, $j);

		if (($tokens[$j] ?? null) !== '=') {
			continue;
		}

		$j++;
		pg_test_source_skip($tokens, $j);

		if (pg_test_source_array_starts($tokens, $j)) {
			return pg_test_source_value($tokens, $j);
		}
	}

	throw new RuntimeException('No array literal is assigned to ' . $variable . '.');
}

/**
 * The array literal a loaded function assigns to a variable.
 *
 * @param string $function
 * @param string $variable with its $
 * @return array
 */
function pg_test_source_array($function, $variable)
{
	return pg_test_source_array_in(pg_test_function_source($function), $variable);
}

/**
 * The Workspace's change catalogue as ws_change_types() writes it, before
 * it drops what a site does not run (no shop, no ERP, no calendars) or what
 * an upgrade has not added yet (a column, a table): every kind and every
 * field the code knows. Per kind: label, right, tag, table, key (when the id
 * column is not id), actions, required, names, fields, create, members, as
 * the function's comment describes them; each field [kind, limit, label,
 * column]. Labels (lang() calls) and limits worked out by code are
 * PgTestSourceExpression objects; literal limits are ints or arrays.
 *
 * @return array kind => definition
 */
function pg_test_ws_change_types()
{
	static $types = null;

	if ($types === null) {
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

		$source = pg_test_function_source('ws_change_types');

		// A kind or a field added by an assignment after the literal would be
		// missed here; the reader would have to learn it first.
		if (preg_match('/\$types(?:\[[^\]]*\])+\s*=(?!=)/', $source, $match)) {
			throw new RuntimeException('ws_change_types() adds to $types outside its array literal (' . $match[0] . '): read that too in pg_test_ws_change_types().');
		}

		$types = pg_test_source_array_in($source, '$types');
	}

	return $types;
}

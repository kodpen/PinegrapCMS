<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on get_page.php: its raw queries with values run through
 * db_q() placeholders, not escape() concatenated into the SQL text. The raw
 * queries left are those without values and those completed by a clause
 * built elsewhere with escaped values, which may hold a '?'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each mysqli_query() / db_q() call of the file with the text of its
// arguments and, when the SQL is a variable ($query, $update, $insert), the
// text of that variable's last assignment before the call.
function db_q_get_page_test_calls()
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php'));
	$count = count($tokens);
	$assignments = array();
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		// "$query = ...;" kept by variable name.
		if ($token[0] === T_VARIABLE) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$text = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$text .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
				}

				$assignments[$token[1]] = $text;
			}

			continue;
		}

		if ($token[0] !== T_STRING || !in_array($token[1], array('mysqli_query', 'db_q'), true)) {
			continue;
		}

		$j = $i + 1;

		while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
			$j++;
		}

		if ($j >= $count || $tokens[$j] !== '(') {
			continue;
		}

		$depth = 0;
		$text = '';

		for ($k = $j; $k < $count; $k++) {
			$piece = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];

			if ($piece === '(') {
				$depth++;
			} elseif ($piece === ')') {
				$depth--;
			}

			$text .= $piece;

			if ($depth === 0) {
				break;
			}
		}

		$sql = $text;

		foreach (array('$query', '$update', '$insert') as $variable) {
			if (preg_match('/\(\s*(db::\$con\s*,\s*)?' . preg_quote($variable, '/') . '\b/', $text) && isset($assignments[$variable])) {
				$sql .= $assignments[$variable];
			}
		}

		$calls[] = array('line' => $token[2], 'call' => $token[1], 'text' => $sql);
	}

	return $calls;
}

function test_db_q_get_page_has_no_escaped_values_in_query_text()
{
	foreach (db_q_get_page_test_calls() as $call) {
		pg_assert_false(
			(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['text']),
			'get_page.php line ' . $call['line'] . ' concatenates an escaped value into its SQL');
	}
}

function test_db_q_get_page_value_queries_use_db_q()
{
	$db_q = 0;

	foreach (db_q_get_page_test_calls() as $call) {
		if ($call['call'] === 'db_q') {
			$db_q++;
			continue;
		}

		pg_assert_false(
			(bool) preg_match('/"\s*\.\s*(\(int\)\s*)?\$|\'\$[a-z_]/i', $call['text']),
			'get_page.php line ' . $call['line'] . ' writes a value into a raw query');
	}

	pg_assert_true($db_q >= 30, 'get_page.php runs ' . $db_q . ' queries through db_q()');
}

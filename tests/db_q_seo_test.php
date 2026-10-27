<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on includes/fn/seo.php: its raw queries with values run
 * through db_q() placeholders, not escape()/e() concatenated into the SQL
 * text. The raw queries left are those without values and those completed
 * by a clause built elsewhere ($sql_page_id / $sql_page_name with escaped
 * names, which may hold a '?', and the integer IN lists $id_list,
 * $page_list, $where).
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
function db_q_seo_test_calls()
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/seo.php'));
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

function test_db_q_seo_has_no_escaped_values_in_query_text()
{
	foreach (db_q_seo_test_calls() as $call) {
		if (preg_match('/\$(sql_page_id|sql_page_name)\b/', $call['text'])) {
			continue;
		}

		pg_assert_false(
			(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['text']),
			'includes/fn/seo.php line ' . $call['line'] . ' concatenates an escaped value into its SQL');
	}
}

function test_db_q_seo_value_queries_use_db_q()
{
	$db_q = 0;

	foreach (db_q_seo_test_calls() as $call) {
		if ($call['call'] === 'db_q') {
			$db_q++;
			continue;
		}

		// A raw query writes no value of its own: only a clause built
		// elsewhere may complete it.
		$built = (bool) preg_match('/\$(sql_page_id|sql_page_name|id_list|page_list|where)\b/', $call['text']);

		pg_assert_false(
			!$built && (bool) preg_match('/"\s*\.\s*(\(int\)\s*)?\$|\'\$[a-z_]/i', $call['text']),
			'includes/fn/seo.php line ' . $call['line'] . ' writes a value into a raw query');
	}

	pg_assert_true($db_q >= 33, 'includes/fn/seo.php runs ' . $db_q . ' queries through db_q()');
}

function test_db_q_seo_lock_name_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	pg_assert_same(
		"SELECT GET_LOCK('pg_sfi_12_7', 1)",
		pg_db_bind('SELECT GET_LOCK(?, 1)', array('pg_sfi_' . 12 . '_' . 7), $escape));
	pg_assert_same(
		"SELECT id FROM perf_stats WHERE hour_start < 1700000000 LIMIT 1 OFFSET 200000",
		pg_db_bind('SELECT id FROM perf_stats WHERE hour_start < ? LIMIT 1 OFFSET ?', array((int) '1700000000', (int) '200000'), $escape));
}

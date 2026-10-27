<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on get_page.php: its raw queries with values run through
 * db_q() placeholders, not escape() concatenated into the SQL text. The raw
 * queries left are those without values, the home page lookup completed by
 * the integer folder list $sql_not_binned, and the two submitted form lists
 * of the form list view feed completed by $sql_where / $sql_filters, which
 * are built with escaped filter values and may hold a '?'.
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
		if ($call['call'] === 'mysqli_query' && preg_match('/\$(sql_where|sql_filters)\b/', $call['text'])) {
			continue;
		}

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

		// A raw query writes no value of its own: only a clause built
		// elsewhere may complete it.
		$built = (bool) preg_match('/\$(sql_not_binned|sql_where|sql_filters)\b/', $call['text']);

		pg_assert_false(
			!$built && (bool) preg_match('/"\s*\.\s*(\(int\)\s*)?\$|\'\$[a-z_]/i', $call['text']),
			'get_page.php line ' . $call['line'] . ' writes a value into a raw query');
	}

	pg_assert_true($db_q >= 29, 'get_page.php runs ' . $db_q . ' queries through db_q()');
}

function test_db_q_get_page_calendar_list_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	pg_assert_same(
		"SELECT calendar_id FROM calendar_event_views_calendars_xref WHERE ((calendar_id = '3') OR (calendar_id = '7')) AND (page_id = '12')",
		pg_db_bind(
			'SELECT calendar_id FROM calendar_event_views_calendars_xref WHERE ((calendar_id = ?) OR (calendar_id = ?)) AND (page_id = ?)',
			array((string) 3, (string) 7, (string) 12),
			$escape));
	pg_assert_same(
		"SELECT id FROM page WHERE page_name = 'o\\'brien'",
		pg_db_bind('SELECT id FROM page WHERE page_name = ?', array((string) "o'brien"), $escape));
}

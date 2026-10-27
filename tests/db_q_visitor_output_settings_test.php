<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the visitor text, retention and partition functions,
 * includes/fn/innodb.php, output.php, comments.php, the settings screen
 * (prep.php, firewall.save.php), api/maintenance.php and the dashboard
 * widgets 8 and 23: their queries with values run through db_q()
 * placeholders, not escaped or interpolated values in the SQL text, and
 * every db_q() call passes one value per '?'. The raw queries left are those
 * without values, statements naming a table (ALTER, MIN() of a partitioned
 * table), the visitor move's generic runner and the integer IN list of the
 * visitor text sweep.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each file with the number of db_q() calls it has at least.
function db_q_visitor_output_settings_test_files()
{
	return array(
		'includes/fn/innodb.php' => 4,
		'includes/fn/visitor_texts.php' => 4,
		'includes/fn/visitor_retention.php' => 2,
		'includes/fn/visitor_partitions.php' => 2,
		'includes/fn/output.php' => 1,
		'includes/fn/comments.php' => 3,
		'includes/settings/prep.php' => 1,
		'includes/settings/firewall.save.php' => 1,
		'includes/api/maintenance.php' => 0,
		'includes/dashboard/widgets/widget_23.php' => 4,
		'includes/dashboard/widgets/widget_8.php' => 1,
	);
}

// Each mysqli_query() / db_q() call of a file with the text of its
// arguments, its first argument and, when the SQL is $query, the text of
// that variable's last assignment before the call.
function db_q_visitor_output_settings_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$query = '';
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE && $token[1] === '$query') {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$query = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$query .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
				}
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

		// Arguments split at the commas of the call's own level.
		$depth = 0;
		$text = '';
		$arguments = array('');

		for ($k = $j; $k < $count; $k++) {
			$piece = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];

			if ($piece === '(' || $piece === '[') {
				$depth++;
			} elseif ($piece === ')' || $piece === ']') {
				$depth--;
			}

			$text .= $piece;

			if ($depth === 0) {
				break;
			}

			if ($depth === 1 && $piece === ',') {
				$arguments[] = '';
			} elseif ($k > $j) {
				$arguments[count($arguments) - 1] .= $piece;
			}
		}

		$arguments = array_map('trim', $arguments);
		$sql = $text;

		if (preg_match('/\(\s*(db::\$con\s*,\s*)?\$query\b/', $text)) {
			$sql .= $query;
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => ($token[1] === 'db_q' && $arguments[0] === '$query') ? $query : $arguments[0],
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

// Clauses a raw query may still be completed by: built elsewhere, or an
// identifier, never a value of this call.
function db_q_visitor_output_settings_test_built($text)
{
	return (bool) preg_match('/implode\(\',\', \$ids\)/', $text);
}

function test_db_q_visitor_output_settings_value_queries_use_db_q()
{
	foreach (db_q_visitor_output_settings_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_visitor_output_settings_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_visitor_output_settings_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]|=\s*"\s*\.\s*\$/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}

	// The table names of the schema reads are bound, not escaped into the text.
	foreach (array('includes/fn/innodb.php', 'includes/fn/visitor_partitions.php') as $path) {
		pg_assert_false(
			(bool) preg_match('/mysqli_real_escape_string/', file_get_contents(PG_FUNCTIONS_DIR . '/' . $path)),
			$path . ' escapes a value for SQL text');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_visitor_output_settings_values_fit_placeholders()
{
	foreach (array_keys(db_q_visitor_output_settings_test_files()) as $path) {
		foreach (db_q_visitor_output_settings_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q' || strpos($call['values'], 'array(') !== 0) {
				continue;
			}

			// SQL held in another variable or completed by a clause variable
			// (the not-found counter's $stale) is checked by its bound text below.
			if (preg_match('/^\$(?!query\b)\w+$|"\s*\.\s*\$/', trim($call['sql']))) {
				continue;
			}

			$tokens = token_get_all('<?php ' . $call['values'] . ';');
			$depth = 0;
			$elements = 1;

			foreach ($tokens as $token) {
				$piece = is_array($token) ? $token[1] : $token;

				if ($piece === '(' || $piece === '[') {
					$depth++;
				} elseif ($piece === ')' || $piece === ']') {
					$depth--;
				} elseif ($piece === ',' && $depth === 1) {
					$elements++;
				}
			}

			// Only the '?' of the SQL text count: a PHP ternary or '??' adds none.
			$placeholders = 0;

			foreach (token_get_all('<?php ' . $call['sql'] . ';') as $token) {
				if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
					$placeholders += substr_count($token[1], '?');
				}
			}

			pg_assert_same(
				$placeholders,
				$elements,
				$path . ' line ' . $call['line'] . ' passes ' . $elements . ' values for its placeholders');
		}
	}
}

function test_db_q_visitor_output_settings_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The visitor text lookup: the kind bare, the hash quoted inside UNHEX().
	$hash = md5('Mozilla');
	pg_assert_same(
		"SELECT text_id FROM visitor_texts WHERE (kind = 2) AND (text_hash = UNHEX('" . $hash . "'))",
		pg_db_bind('SELECT text_id FROM visitor_texts WHERE (kind = ?) AND (text_hash = UNHEX(?))', array((int) '2', $hash), $escape));
	// The retention delete writes its window as bare numbers.
	pg_assert_same(
		"DELETE FROM visitors WHERE (id > 0) AND (id <= 500) AND (start_timestamp < 1700000000)",
		pg_db_bind('DELETE FROM visitors WHERE (id > ?) AND (id <= ?) AND (start_timestamp < ?)', array((int) 0, (int) '500', (int) 1700000000), $escape));
	// The comment counters quote their ids, as before.
	pg_assert_same(
		"INSERT INTO submitted_form_info (submitted_form_id, page_id, number_of_views, number_of_comments, newest_comment_id) VALUES ('7', '3', '0', '2', '41')",
		pg_db_bind('INSERT INTO submitted_form_info (submitted_form_id, page_id, number_of_views, number_of_comments, newest_comment_id) VALUES (?, ?, ?, ?, ?)', array((string) 7, (string) 3, (string) 0, (string) 2, (string) 41), $escape));
	// A manual firewall entry: address and list quoted, the time bare.
	pg_assert_same(
		"INSERT INTO banned_ip_addresses (ip_address, list_type, source, created_at) VALUES ('10.0.0.*', 'block', 'manual', 1700000000)",
		pg_db_bind("INSERT INTO banned_ip_addresses (ip_address, list_type, source, created_at) VALUES (?, ?, 'manual', ?)", array((string) '10.0.0.*', (string) 'block', 1700000000), $escape));
	// The restored sql_mode is quoted, the lock wait bare.
	pg_assert_same(
		"SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
		pg_db_bind('SET SESSION sql_mode = ?', array((string) 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'), $escape));
}

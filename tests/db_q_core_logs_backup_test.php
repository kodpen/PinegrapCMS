<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on includes/fn/price_history.php, core.php, calendar.php,
 * mail.php, not_found_log.php, db_maintenance.php and backup_restore.php:
 * their queries with values run through db_q() placeholders, not
 * escape() / mysqli_real_escape_string() concatenated into the SQL text, and
 * every db_q() call passes one value per '?'. The raw queries left are those
 * without values, the connection setup and the db helpers in core.php, the
 * SQL built by the pure price history builders, integer IN lists, and the
 * calendar clauses $sql_where and $event_id_where_clause.
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
function db_q_core_logs_backup_test_files()
{
	return array(
		'includes/fn/price_history.php' => 2,
		'includes/fn/core.php' => 4,
		'includes/fn/calendar.php' => 4,
		'includes/fn/mail.php' => 7,
		'includes/fn/not_found_log.php' => 5,
		'includes/fn/db_maintenance.php' => 3,
		'includes/fn/backup_restore.php' => 3,
	);
}

// Each mysqli_query() / db_q() call of a file with the text of its
// arguments, its first argument and, when the SQL is $query, the text of
// that variable's last assignment before the call.
function db_q_core_logs_backup_test_calls($path)
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
function db_q_core_logs_backup_test_built($text)
{
	return (bool) preg_match('/\$(sql_where|event_id_where_clause|mysql_character_set)\b|implode\(\',\', \$chunk\)/', $text);
}

function test_db_q_core_logs_backup_value_queries_use_db_q()
{
	foreach (db_q_core_logs_backup_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_core_logs_backup_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_core_logs_backup_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]|=\s*"\s*\.\s*\$/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}

	// The clauses built before a query hold '?' with their values beside them.
	foreach (array('includes/fn/db_maintenance.php', 'includes/fn/backup_restore.php', 'includes/fn/not_found_log.php') as $path) {
		pg_assert_false(
			(bool) preg_match('/mysqli_real_escape_string/', file_get_contents(PG_FUNCTIONS_DIR . '/' . $path)),
			$path . ' escapes a value for SQL text');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_core_logs_backup_values_fit_placeholders()
{
	foreach (array_keys(db_q_core_logs_backup_test_files()) as $path) {
		foreach (db_q_core_logs_backup_test_calls($path) as $call) {
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

function test_db_q_core_logs_backup_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The not-found counter: the stale check four times, the crawler flag
	// bare, the times and the key quoted.
	pg_assert_same(
		"UPDATE not_found_log SET hit_count = IF(last_seen < '100', 1, hit_count + 1), crawler_hits = IF(last_seen < '100', 1, crawler_hits + 1), first_seen = IF(last_seen < '100', '200', first_seen), referrer = IF(last_seen < '100' OR referrer = '', 'a\\'b', referrer), last_seen = '200' WHERE path_key = 'k'",
		pg_db_bind("UPDATE not_found_log SET hit_count = IF(last_seen < ?, 1, hit_count + 1), crawler_hits = IF(last_seen < ?, ?, crawler_hits + ?), first_seen = IF(last_seen < ?, ?, first_seen), referrer = IF(last_seen < ? OR referrer = '', ?, referrer), last_seen = ? WHERE path_key = ?",
			array('100', '100', (int) 1, (int) 1, '100', (string) 200, '100', "a'b", (string) 200, (string) 'k'), $escape));
	// The processlist LIKE keeps its trailing '%' inside the quoted value.
	pg_assert_same(
		"SELECT ID FROM information_schema.PROCESSLIST WHERE ((INFO LIKE 'OPTIMIZE%') OR (INFO LIKE 'ALTER TABLE%'))",
		pg_db_bind('SELECT ID FROM information_schema.PROCESSLIST WHERE ((INFO LIKE ?) OR (INFO LIKE ?))', array('OPTIMIZE' . '%', 'ALTER TABLE' . '%'), $escape));
	// The lock wait and the price walk's LIMIT stay bare numbers.
	pg_assert_same(
		"SET SESSION lock_wait_timeout = 5",
		pg_db_bind('SET SESSION lock_wait_timeout = ?', array(max(1, (int) '5')), $escape));
	pg_assert_same(
		"SELECT id, price FROM products WHERE id > '0' ORDER BY id LIMIT 500",
		pg_db_bind('SELECT id, price FROM products WHERE id > ? ORDER BY id LIMIT ?', array((string) (int) null, (int) 500), $escape));
	// A notification without ids writes '' for them, as before.
	pg_assert_same(
		"INSERT INTO notifications (action, product_id, order_total) VALUES ('custom', '', '12.5')",
		pg_db_bind('INSERT INTO notifications (action, product_id, order_total) VALUES (?, ?, ?)', array((string) 'custom', (string) '', (string) 12.5), $escape));
}

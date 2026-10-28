<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the log, short link, comment and system screens and on
 * includes/fn/short_link_redirects.php, audit.php and privacy_retention.php:
 * their queries with values run through db_q() placeholders, not escape() /
 * e() / mysqli_real_escape_string() concatenated into the SQL text, and every
 * db_q() call passes one value per '?'. The raw queries left are those
 * without values and those completed by a clause built elsewhere ($where,
 * $stats_where, $sql_live, the log list's $order_by).
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
function db_q_logs_short_links_system_test_files()
{
	return array(
		'view_performance_log.php' => 0,
		'view_log.php' => 0,
		'edit_short_link.php' => 3,
		'add_short_link.php' => 1,
		'includes/forms/short_link_form.php' => 3,
		'view_short_links.php' => 0,
		'includes/fn/short_link_redirects.php' => 3,
		'allow_or_disallow_new_comments.php' => 4,
		'view_comments.php' => 0,
		'clean_up.php' => 0,
		'software_update_check.php' => 0,
		'software_update.php' => 0,
		'update_search_index.php' => 2,
		'includes/fn/system_status.php' => 0,
		'includes/fn/audit.php' => 2,
		'includes/fn/privacy_retention.php' => 2,
		'includes/panel/software.php' => 0,
		'includes/panel/dashboard.php' => 1,
		'welcome.php' => 0,
		'view_sessions.php' => 1,
	);
}

// Each mysqli_query() / db_q() call of a file with the text of its
// arguments, its first argument and, when the SQL is $query, the text of
// that variable's last assignment before the call.
function db_q_logs_short_links_system_test_calls($path)
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
function db_q_logs_short_links_system_test_built($text)
{
	return (bool) preg_match('/\$(where|stats_where|sql_live|order_by)\b/', $text);
}

function test_db_q_logs_short_links_system_value_queries_use_db_q()
{
	foreach (db_q_logs_short_links_system_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_logs_short_links_system_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_logs_short_links_system_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]|=\s*"\s*\.\s*\$/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}

	// These files no longer escape a value into any SQL text.
	foreach (array('edit_short_link.php', 'add_short_link.php', 'includes/forms/short_link_form.php', 'allow_or_disallow_new_comments.php', 'includes/fn/audit.php', 'includes/fn/privacy_retention.php') as $path) {
		pg_assert_false(
			(bool) preg_match('/mysqli_real_escape_string|(?<![\w>$])(escape|e)\(/', file_get_contents(PG_FUNCTIONS_DIR . '/' . $path)),
			$path . ' escapes a value for SQL text');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_logs_short_links_system_values_fit_placeholders()
{
	foreach (array_keys(db_q_logs_short_links_system_test_files()) as $path) {
		foreach (db_q_logs_short_links_system_test_calls($path) as $call) {
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

function test_db_q_logs_short_links_system_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The name key keeps its character set introducer before the quoted name.
	pg_assert_same(
		"SELECT HEX(WEIGHT_STRING(CONVERT(_utf8mb4'a\\'b' USING utf8mb4) COLLATE utf8mb4_unicode_ci)), HEX(WEIGHT_STRING(CONVERT(_utf8mb4'c?' USING utf8mb4) COLLATE utf8mb4_unicode_ci))",
		pg_db_bind("SELECT HEX(WEIGHT_STRING(CONVERT(_utf8mb4? USING utf8mb4) COLLATE utf8mb4_unicode_ci)), HEX(WEIGHT_STRING(CONVERT(_utf8mb4? USING utf8mb4) COLLATE utf8mb4_unicode_ci))", array("a'b", 'c?'), $escape));
	// The use counter: time and id quoted, as before.
	pg_assert_same(
		"UPDATE short_links SET use_count = use_count + 1, last_used_at = '1700000000' WHERE id = '7'",
		pg_db_bind('UPDATE short_links SET use_count = use_count + 1, last_used_at = ? WHERE id = ?', array((string) 1700000000, (string) (int) '7'), $escape));
	// Retention: the cutoff quoted, the batch a bare LIMIT, the consent ids a
	// quoted list.
	pg_assert_same(
		"DELETE FROM audit_log WHERE created_at < '1600000000' LIMIT 1000",
		pg_db_bind('DELETE FROM audit_log WHERE created_at < ? LIMIT ?', array((string) 1600000000, (int) 1000), $escape));
	pg_assert_same(
		"DELETE FROM consent_log WHERE id IN ('3', '4')",
		pg_db_bind('DELETE FROM consent_log WHERE id IN (?, ?)', array_map('strval', array_map('intval', array('3', '4x'))), $escape));
	// The audit line: NULL bare, an int quoted; the read by ids a bare list.
	pg_assert_same(
		"INSERT INTO audit_log (user_id, label, before_json) VALUES ('5', 'x\\'y', NULL)",
		pg_db_bind('INSERT INTO audit_log (user_id, label, before_json) VALUES (?, ?, ?)', array((string) 5, (string) "x'y", null), $escape));
	pg_assert_same(
		'SELECT * FROM `page` WHERE `page_id` IN (1,2)',
		pg_db_bind('SELECT * FROM `page` WHERE `page_id` IN (?,?)', array(1, 2), $escape));
	// The short link update keeps each redirect answer column quoted.
	pg_assert_same(
		"UPDATE short_links SET name = 'n', redirect_code = '301', last_modified_user_id = '' WHERE id = '9'",
		pg_db_bind('UPDATE short_links SET name = ?, redirect_code = ?, last_modified_user_id = ? WHERE id = ?', array_merge(array('n'), array((string) (int) '301'), array((string) '', '9')), $escape));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on includes/fn/auth.php, includes/fn/contacts.php,
 * includes/fn/designer.php and the clauses of includes/fn/forms.php: their
 * queries with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, and every db_q() call passes one value
 * per '?'. The raw queries left are those without values and, in
 * forms.php, those completed by $sql_where, $sql_collection, $sql_update,
 * the INSERT's $values or pg_catalog_bin_live_sql().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each mysqli_query() / db_q() call of a file with the text of its
// arguments, its first argument and, when the SQL is $query, the text of
// that variable's last assignment before the call.
function db_q_auth_contacts_designer_test_calls($path)
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

// Clause variables a raw query may still be completed by.
function db_q_auth_contacts_designer_test_built($text)
{
	return (bool) preg_match('/\$(sql_where|sql_collection|sql_update|values)\b|pg_catalog_bin_live_sql\(/', $text);
}

function test_db_q_auth_contacts_designer_value_queries_use_db_q()
{
	$expected = array(
		'includes/fn/auth.php' => 14,
		'includes/fn/contacts.php' => 17,
		'includes/fn/designer.php' => 3,
		'includes/fn/forms.php' => 24,
	);

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_auth_contacts_designer_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_auth_contacts_designer_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}
}

// The WHERE pieces these files build before the query hold '?' with a value
// list beside them, never an escaped value.
function test_db_q_auth_contacts_designer_clauses_hold_placeholders()
{
	foreach (array('includes/fn/auth.php', 'includes/fn/contacts.php', 'includes/fn/designer.php', 'includes/fn/forms.php') as $path) {
		foreach (file(PG_FUNCTIONS_DIR . '/' . $path) as $number => $line) {
			if (!preg_match('/\$(where|form_type_filter|\w+_where_statement|sql_columns_to_update)\s*\.?=/', $line)) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $line),
				$path . ' line ' . ($number + 1) . ' builds a clause with an escaped value');
		}
	}

	// The asset sync of the designer passes the names as values.
	pg_assert_false(
		(bool) preg_match('/mysqli_real_escape_string/', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php')),
		'designer.php escapes a value for SQL text');
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_auth_contacts_designer_values_fit_placeholders()
{
	foreach (array('includes/fn/auth.php', 'includes/fn/contacts.php', 'includes/fn/designer.php') as $path) {
		foreach (db_q_auth_contacts_designer_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q' || strpos($call['values'], 'array(') !== 0) {
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

function test_db_q_auth_contacts_designer_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// validate_user() quoted the cast session id and still does.
	pg_assert_same(
		"SELECT * FROM user WHERE user_id = '0'",
		pg_db_bind('SELECT * FROM user WHERE user_id = ?', array((string) (int) 'x'), $escape));
	// validate_username() matches one escaped value against both columns.
	pg_assert_same(
		"SELECT user_id FROM user WHERE (user_username = 'o\\'neil') OR (user_email = 'o\\'neil')",
		pg_db_bind('SELECT user_id FROM user WHERE (user_username = ?) OR (user_email = ?)', array((string) "o'neil", (string) "o'neil"), $escape));
	// A null id escaped to '' and binds to '' through (string).
	pg_assert_same(
		"SELECT calendar_id FROM calendar_events_calendars_xref WHERE calendar_event_id = ''",
		pg_db_bind('SELECT calendar_id FROM calendar_events_calendars_xref WHERE calendar_event_id = ?', array((string) null), $escape));
	// The contact merge OR list keeps one placeholder per merged orphan.
	pg_assert_same(
		"DELETE FROM contacts  WHERE ((id = '4') OR (id = '9'))",
		pg_db_bind('DELETE FROM contacts  WHERE ((id = ?) OR (id = ?))', array((string) 4, (string) 9), $escape));
	// The designer asset row keeps its quoted size and user.
	pg_assert_same(
		"INSERT INTO files (name, folder, description, type, size, user, timestamp) VALUES ('site.css', '0', '', 'css', '120', '3', UNIX_TIMESTAMP())",
		pg_db_bind("INSERT INTO files (name, folder, description, type, size, user, timestamp) VALUES (?, '0', '', ?, ?, ?, UNIX_TIMESTAMP())", array('site.css', (string) 'css', (string) 120, (string) 3), $escape));
}

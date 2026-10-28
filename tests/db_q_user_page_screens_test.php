<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the user and page screens (edit_user.php, add_user.php,
 * import_users.php, edit_pages.php, edit_page.php): their raw queries with
 * values run through db_q() placeholders, not escape()/e() concatenated
 * into the SQL text, and every db_q() call passes one value per '?'. The
 * raw queries left are those without values and those completed by a
 * clause built elsewhere with escaped values, which may hold a '?': the
 * role, right and page type pieces of the user UPDATE and INSERT
 * ($sql_role, $sql_set_page_types, $sql_ws_values, $sql_password, ...), the
 * page UPDATE of edit_page.php ($sql_page_type, $sql_style_fields, ...) and
 * the page type table deletes named by $page_type_table_name.
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
function db_q_user_page_screens_test_calls($path)
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
function db_q_user_page_screens_test_built($text)
{
	return (bool) preg_match('/\$(sql_(role|offline_payment|set_page_types|ws_rights|erp_readonly|password|erp_readonly_column|erp_readonly_value|ws_columns|ws_values|set_page_type_columns|set_page_type_values|page_type|home_page|style_fields|noindex|seo_extras|ai_fields|seo_analysis_current)|page_type_table_name)\b/', $text);
}

function test_db_q_user_page_screens_value_queries_use_db_q()
{
	$expected = array('edit_user.php' => 21, 'add_user.php' => 2, 'includes/forms/user_form.php' => 8, 'import_users.php' => 8, 'edit_pages.php' => 30, 'edit_page.php' => 30);

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_user_page_screens_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_user_page_screens_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(|\'"\s*\.|\'\$[a-z_]/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_user_page_screens_values_fit_placeholders()
{
	foreach (array('edit_user.php', 'add_user.php', 'includes/forms/user_form.php', 'import_users.php', 'edit_pages.php', 'edit_page.php') as $path) {
		foreach (db_q_user_page_screens_test_calls($path) as $call) {
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

function test_db_q_user_page_screens_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The folder grant quoted its ids and rights; they stay quoted.
	pg_assert_same(
		"INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES ('5', '12', '1', '')",
		pg_db_bind('INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES (?, ?, ?, ?)', array((string) 5, (string) 12, (string) 1, (string) ''), $escape));
	// A posted name with a quote and a '?' is escaped once and not expanded.
	pg_assert_same(
		"SELECT user_id FROM user WHERE (user_username = 'o\\'brien?') OR (user_email = 'o\\'brien?')",
		pg_db_bind('SELECT user_id FROM user WHERE (user_username = ?) OR (user_email = ?)', array((string) "o'brien?", (string) "o'brien?"), $escape));
	// The grant delete keeps the integer list of converted regions after its placeholder.
	pg_assert_same(
		"DELETE FROM users_common_regions_xref WHERE user_id = '5' AND (common_region_id NOT IN (4,9))",
		pg_db_bind('DELETE FROM users_common_regions_xref WHERE user_id = ? AND ' . '(common_region_id NOT IN (4,9))', array((string) 5), $escape));
}

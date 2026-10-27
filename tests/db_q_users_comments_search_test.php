<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the users list, account profile, comment, search and
 * sitemap files (view_users.php, delete_users.php, my_account_profile.php,
 * add_comment.php, edit_comment.php, get_search_results.php,
 * get_sitemap_info.php): their raw queries with values run through db_q()
 * placeholders, not escape()/e() concatenated into the SQL text, and every
 * db_q() call passes one value per '?'. The raw queries left are those
 * without values (the search's integer page list, the registration contact
 * group constant) and those completed by a clause built elsewhere with
 * escaped values, which may hold a '?': the users list ($where, $order_by,
 * ...), the account profile update ($sql_profile_set, $sql_timezone), the
 * comment update ($sql_file_id, $sql_seller_reply) and the sitemap's
 * calendar condition ($sql_calendar_condition).
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
function db_q_users_comments_search_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$assignments = array();
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE && preg_match('/^\$(query|query2|db_query)$/', $token[1])) {
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

		$variable = '';

		if (preg_match('/^\(\s*(db::\$con\s*,\s*)?(\$(query|query2|db_query))\b/', $text, $match) && isset($assignments[$match[2]])) {
			$variable = $match[2];
			$sql .= $assignments[$variable];
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => ($variable !== '' && $arguments[0] === $variable) ? $assignments[$variable] : $arguments[0],
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

// Clause variables a raw query may still be completed by.
function db_q_users_comments_search_test_built($text)
{
	return (bool) preg_match('/\$(where|order_by|sql_select_id|sql_from_table|sql_join_user_table|sql_join_contacts_table|sql_select_column|sql_profile_set|sql_profile_tax|sql_timezone|sql_file_id|sql_seller_reply|sql_calendar_condition)\b|REGISTRATION_CONTACT_GROUP_ID/', $text);
}

function test_db_q_users_comments_search_value_queries_use_db_q()
{
	$expected = array('view_users.php' => 5, 'delete_users.php' => 9, 'my_account_profile.php' => 4, 'add_comment.php' => 12, 'edit_comment.php' => 7, 'get_search_results.php' => 5, 'get_sitemap_info.php' => 4);

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_users_comments_search_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_users_comments_search_test_built($call['text'])) {
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
function test_db_q_users_comments_search_values_fit_placeholders()
{
	foreach (array('view_users.php', 'delete_users.php', 'my_account_profile.php', 'add_comment.php', 'edit_comment.php', 'get_search_results.php', 'get_sitemap_info.php') as $path) {
		foreach (db_q_users_comments_search_test_calls($path) as $call) {
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

function test_db_q_users_comments_search_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The search term gets escape_like() then one escape, as before; the
	// wildcards stay around it.
	pg_assert_same(
		"SELECT id FROM page WHERE (page_id NOT IN (3, 8)) AND (page_search_keywords LIKE '%o\\'brien?\\\\%%')",
		pg_db_bind('SELECT id FROM page WHERE (page_id NOT IN (' . '3, 8' . ')) AND (page_search_keywords LIKE ?)', array('%' . addcslashes("o'brien?%", '%_\\') . '%'), $escape));
	// An id written unescaped into quotes stays quoted.
	pg_assert_same(
		"SELECT folder.folder_name FROM folder WHERE ((aclfolder_user = '12') AND (aclfolder_rights = '1'))",
		pg_db_bind("SELECT folder.folder_name FROM folder WHERE ((aclfolder_user = ?) AND (aclfolder_rights = '1'))", array((string) 12), $escape));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the address book, e-mail preferences, form view, catalog
 * detail and small front files (get_email_preferences.php,
 * update_address_book.php, get_update_address_book.php,
 * get_form_list_view.php, get_custom_form_confirmation_screen_content.php,
 * get_catalog_detail.php, order_form.php, remove_recipient.php,
 * reserve_calendar_event.php, print_packing_slip.php, approve_affiliate.php,
 * includes/local_sale.php): their raw queries with values run through db_q()
 * placeholders, not escape()/e() concatenated into the SQL text, and every
 * db_q() call passes one value per '?'. The raw queries left are those
 * without values (the next_order_number lock of local_sale.php among them)
 * and the submitted form count of get_form_list_view.php, completed by
 * clauses built with escaped filter values ($sql_where, $sql_search,
 * $sql_viewer_filter, $sql_join_for_count).
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
// arguments, its first argument and, when the SQL is a variable ($query,
// $item_images), the text of that variable's last assignment before the call.
function db_q_small_front_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$assigned = array();
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$assigned[$token[1]] = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$assigned[$token[1]] .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
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

		$variable = ($token[1] === 'db_q') ? $arguments[0] : (isset($arguments[1]) ? $arguments[1] : '');
		$query = isset($assigned[$variable]) ? $assigned[$variable] : '';

		if (preg_match('/^\$\w+$/', $variable)) {
			$sql .= $query;
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => ($token[1] === 'db_q' && $query !== '') ? $query : $arguments[0],
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

// Clause variables a raw query may still be completed by.
function db_q_small_front_test_built($text)
{
	return (bool) preg_match('/\$(sql_where|sql_search|sql_viewer_filter|sql_join_for_count)\b/', $text);
}

// Each converted file with the number of its queries that run through db_q().
function db_q_small_front_test_files()
{
	return array(
		'get_email_preferences.php' => 6,
		'update_address_book.php' => 5,
		'get_update_address_book.php' => 2,
		'get_form_list_view.php' => 3,
		'get_custom_form_confirmation_screen_content.php' => 4,
		'get_catalog_detail.php' => 4,
		'order_form.php' => 4,
		'remove_recipient.php' => 4,
		'reserve_calendar_event.php' => 3,
		'print_packing_slip.php' => 3,
		'approve_affiliate.php' => 5,
		'includes/local_sale.php' => 0,
	);
}

function test_db_q_small_front_value_queries_use_db_q()
{
	foreach (db_q_small_front_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_small_front_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_small_front_test_built($call['text'])) {
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
function test_db_q_small_front_values_fit_placeholders()
{
	foreach (array_keys(db_q_small_front_test_files()) as $path) {
		foreach (db_q_small_front_test_calls($path) as $call) {
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

function test_db_q_small_front_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The address book update sets the columns the form drew, each with its
	// value beside it, then the address type when that field is enabled.
	$set = "city = ?,\nphone_number = ?,\n";
	pg_assert_same(
		"UPDATE address_book SET city = 'X\\'', phone_number = '1', address_type = 'home', ship_to_name = 'Me' WHERE id = '5' AND user = '7'",
		str_replace("\n", ' ', pg_db_bind("UPDATE address_book SET {$set}address_type = ?, ship_to_name = ? WHERE id = ? AND user = ?", array_merge(array("X'", '1'), array('home'), array('Me', (string) (int) '5abc', (string) 7)), $escape)));
	// The calendar OR list of a reservation: one value per calendar.
	pg_assert_same(
		"SELECT calendar_id FROM calendar_event_views_calendars_xref WHERE ((calendar_id = '1') OR (calendar_id = '2')) AND (page_id = '4')",
		pg_db_bind('SELECT calendar_id FROM calendar_event_views_calendars_xref WHERE ((calendar_id = ?) OR (calendar_id = ?)) AND (page_id = ?)', array_merge(array('1', '2'), array('4')), $escape));
}

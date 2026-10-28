<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on submit_order.php and includes/fn/forms.php: their raw
 * queries with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, and every db_q() call passes one value
 * per '?'. The raw queries left are those without values and those
 * completed by a clause built elsewhere with escaped values, which may hold
 * a '?' (the billing contact update with $sql_fax / $sql_tax, the order
 * update with $sql_erp_on_account, the visitor update that adds the bare
 * $total, and in forms.php $sql_where, $sql_collection and $sql_update).
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
function db_q_submit_order_forms_test_calls($path)
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
function db_q_submit_order_forms_test_built($text)
{
	return (bool) preg_match('/\$(sql_fax|sql_tax|sql_erp_on_account|sql_where|sql_collection|sql_update)\b|order_total \+ \$total/', $text);
}

function test_db_q_submit_order_forms_value_queries_use_db_q()
{
	// The order line stages (stock, reservations, membership, folder access)
	// took 8 of submit_order.php's db_q() calls to includes/fn/order_completion.php.
	$expected = array('submit_order.php' => 59, 'includes/fn/forms.php' => 20);

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_submit_order_forms_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_submit_order_forms_test_built($call['text'])) {
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
function test_db_q_submit_order_forms_values_fit_placeholders()
{
	foreach (array('submit_order.php', 'includes/fn/forms.php') as $path) {
		foreach (db_q_submit_order_forms_test_calls($path) as $call) {
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

function test_db_q_submit_order_forms_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The reservation spots decrement wrote its quantity bare and still does.
	pg_assert_same(
		"UPDATE remaining_reservation_spots SET number_of_remaining_spots = (number_of_remaining_spots - 2) WHERE (calendar_event_id = '7') AND (recurrence_number = '0')",
		pg_db_bind('UPDATE remaining_reservation_spots SET number_of_remaining_spots = (number_of_remaining_spots - ?) WHERE (calendar_event_id = ?) AND (recurrence_number = ?)', array((int) '2', (string) '7', (string) '0'), $escape));
	// The 3-D Secure state row: cents cast to int, then quoted as before.
	pg_assert_same(
		"INSERT INTO iyzipay_3ds_state (conversation_id, order_id, base_total_cents) VALUES ('c-1', '15', '9900')",
		pg_db_bind('INSERT INTO iyzipay_3ds_state (conversation_id, order_id, base_total_cents) VALUES (?, ?, ?)', array((string) 'c-1', (string) (int) '15', (string) (int) (10900 - 1000)), $escape));
	// A log entry without a remote address writes '' as before.
	pg_assert_same(
		"INSERT INTO log (log_description, log_ip, log_user, log_timestamp) VALUES ('Saved', '', 'admin', UNIX_TIMESTAMP())",
		pg_db_bind('INSERT INTO log (log_description, log_ip, log_user, log_timestamp) VALUES (?, ?, ?, UNIX_TIMESTAMP())', array((string) 'Saved', (string) '', (string) 'admin'), $escape));
}

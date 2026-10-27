<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the order preview, receipt and history files
 * (get_order_preview.php, get_order_receipt.php,
 * get_order_receipt_in_plain_text.php, get_shipping_address_and_arrival.php,
 * order_history_reorder.php, order_history_retrieve_order.php,
 * remove_item_from_cart.php): their raw queries with values run through
 * db_q() placeholders, not escape()/e() concatenated into the SQL text, and
 * every db_q() call passes one value per '?'. The raw queries left are those
 * of get_shipping_address_and_arrival.php without values, the arrival date
 * list among them (ECOMMERCE_END_OF_DAY_TIME). The retrieve order update
 * carries the values of its clauses ($sql_tracking_code, $sql_utm) beside
 * them.
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
function db_q_order_front_test_calls($path)
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
function db_q_order_front_test_built($text)
{
	return (bool) preg_match('/\$(sql_tracking_code|sql_utm)\b|ECOMMERCE_END_OF_DAY_TIME/', $text);
}

// Each converted file with the number of its queries that run through db_q().
function db_q_order_front_test_files()
{
	return array(
		'get_order_preview.php' => 12,
		'get_order_receipt.php' => 11,
		'get_order_receipt_in_plain_text.php' => 12,
		'get_shipping_address_and_arrival.php' => 5,
		'order_history_reorder.php' => 10,
		'order_history_retrieve_order.php' => 7,
		'remove_item_from_cart.php' => 9,
	);
}

function test_db_q_order_front_value_queries_use_db_q()
{
	foreach (db_q_order_front_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_order_front_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_order_front_test_built($call['text'])) {
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
function test_db_q_order_front_values_fit_placeholders()
{
	foreach (array_keys(db_q_order_front_test_files()) as $path) {
		foreach (db_q_order_front_test_calls($path) as $call) {
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

function test_db_q_order_front_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The ship to lookup of the preview and the receipts wrote the id bare
	// and still does; a ship to id is an integer from order_items.
	pg_assert_same(
		'SELECT ship_tos.ship_to_name FROM ship_tos WHERE ship_tos.id = 0',
		pg_db_bind('SELECT ship_tos.ship_to_name FROM ship_tos WHERE ship_tos.id = ?', array((int) '0'), $escape));
	// A session value that is not set gives '' without a warning.
	$session = array();
	pg_assert_same(
		"SELECT id FROM order_items WHERE order_id = ''",
		pg_db_bind('SELECT id FROM order_items WHERE order_id = ?', array((string) ($session['order_id'] ?? '')), $escape));
	// The retrieve order update: the tracking code and the UTM values go
	// beside their clauses, before the address and the order id.
	foreach (array(true, false) as $utm) {
		$sql_utm = $utm ? 'utm_source = ?, utm_medium = ?,' : '';
		pg_assert_same(
			"UPDATE orders SET tracking_code = 't\\'1', " . ($utm ? "utm_source = 's', utm_medium = 'm'," : '') . " ip_address = IFNULL(INET_ATON('1.2.3.4'), 0) WHERE id = '12'",
			pg_db_bind("UPDATE orders SET tracking_code = ?, $sql_utm ip_address = IFNULL(INET_ATON(?), 0) WHERE id = ?", array_merge(array("t'1"), $utm ? array('s', 'm') : array(), array('1.2.3.4', '12')), $escape));
	}
}

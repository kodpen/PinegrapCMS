<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the express order and checkout step files
 * (express_order.php, get_express_order.php, get_shipping_method.php,
 * billing_information.php, shipping_address_and_arrival.php): their raw
 * queries with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, and every db_q() call passes one value
 * per '?'. The raw queries left are those without values (bare column
 * names such as $address_type, the ECOMMERCE_END_OF_DAY_TIME constant, the
 * bare $ship_to_id / $contact_id ids) and those completed by a clause built
 * elsewhere with escaped values, which may hold a '?'
 * ($sql_offline_payment_allowed, $sql_fax, $sql_tax, $sql_zones,
 * $sql_address_type).
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
function db_q_express_checkout_test_calls($path)
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
function db_q_express_checkout_test_built($text)
{
	return (bool) preg_match('/\$(sql_offline_payment_allowed|sql_fax|sql_tax|sql_zones|sql_address_type)\b|ECOMMERCE_END_OF_DAY_TIME/', $text);
}

function test_db_q_express_checkout_value_queries_use_db_q()
{
	$expected = array('express_order.php' => 25, 'get_express_order.php' => 16, 'get_shipping_method.php' => 14, 'billing_information.php' => 11, 'shipping_address_and_arrival.php' => 9);

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_express_checkout_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_express_checkout_test_built($call['text'])) {
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
function test_db_q_express_checkout_values_fit_placeholders()
{
	foreach (array('express_order.php', 'get_express_order.php', 'get_shipping_method.php', 'billing_information.php', 'shipping_address_and_arrival.php') as $path) {
		foreach (db_q_express_checkout_test_calls($path) as $call) {
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

function test_db_q_express_checkout_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// A recipient or page id posted as an array is no string: '' as before.
	$posted = array('ship_to_id' => array('1'));
	pg_assert_same(
		"SELECT order_id FROM ship_tos WHERE id = ''",
		pg_db_bind('SELECT order_id FROM ship_tos WHERE id = ?', array((string) ((isset($posted['ship_to_id']) && is_string($posted['ship_to_id'])) ? $posted['ship_to_id'] : '')), $escape));
	// The zone check wrote the shipping method id bare and still does.
	pg_assert_same(
		"SELECT shipping_method_id FROM shipping_methods_zones_xref WHERE (shipping_method_id = 3) AND (zone_id = '8')",
		pg_db_bind('SELECT shipping_method_id FROM shipping_methods_zones_xref WHERE (shipping_method_id = ?) AND (zone_id = ?)', array((int) '3', (string) '8'), $escape));
}

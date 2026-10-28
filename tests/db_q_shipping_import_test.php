<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the shipping, zone, arrival date, commission, commerce
 * settings and product import files (edit_shipping_method.php,
 * shipping_method.php, shipping.php, edit_zone.php, edit_arrival_date.php,
 * view_commissions.php, includes/settings/commerce.save.php,
 * import_products.php): their raw queries with values run through db_q()
 * placeholders, not escape()/e() concatenated into the SQL text, every
 * db_q() call passes one value per '?', and no '?' stands inside a quoted
 * SQL literal. The raw queries left are those without values and those
 * completed by a clause built elsewhere, which may hold a '?' ($sql_zones
 * with $address_type and $day_of_week, $sql_status, $order_by,
 * $update_values, $insert_values).
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
// arguments, its first argument and, when that argument is a variable, the
// text of the variable's last assignment before the call.
function db_q_shipping_import_test_calls($path)
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
				$text = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$text .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
				}

				$assigned[$token[1]] = $text;
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

		if ($token[1] === 'mysqli_query') {
			array_shift($arguments);
		}

		$sql = $arguments[0];

		if (preg_match('/^\$\w+$/', $sql) && isset($assigned[$sql])) {
			$sql = $assigned[$sql];
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $text . $sql,
			'sql' => $sql,
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

function db_q_shipping_import_test_files()
{
	return array(
		'edit_shipping_method.php' => 9,
		'includes/forms/shipping_method_form.php' => 3,
		'shipping_method.php' => 7,
		'shipping.php' => 6,
		'edit_zone.php' => 11,
		'edit_arrival_date.php' => 6,
		'includes/forms/arrival_date_form.php' => 1,
		'view_commissions.php' => 4,
		'includes/settings/commerce.save.php' => 3,
		'import_products.php' => 6,
	);
}

// Clause variables a raw query may still be completed by.
function db_q_shipping_import_test_built($text)
{
	return (bool) preg_match('/\$(sql_zones|sql_status|order_by|update_values|insert_values)\b/', $text);
}

// The SQL text of a call's string literals, PHP code between them left out.
function db_q_shipping_import_test_literals($sql)
{
	$text = '';

	foreach (token_get_all('<?php ' . $sql . ';') as $token) {
		if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
			$text .= $token[1];
		}
	}

	return $text;
}

function test_db_q_shipping_import_value_queries_use_db_q()
{
	foreach (db_q_shipping_import_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_shipping_import_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_shipping_import_test_built($call['text'])) {
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
// elements as the SQL text has placeholders, and no '?' is quoted.
function test_db_q_shipping_import_values_fit_placeholders()
{
	foreach (array_keys(db_q_shipping_import_test_files()) as $path) {
		foreach (db_q_shipping_import_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q') {
				continue;
			}

			$literals = db_q_shipping_import_test_literals($call['sql']);

			pg_assert_false(
				(bool) preg_match('/\'\?|\?\'/', $literals),
				$path . ' line ' . $call['line'] . ' quotes a placeholder');

			// The shipping method save binds a column map built in one
			// place (pg_shipping_method_form_columns()); its placeholders
			// are written from the same map.
			if (preg_match('/^\$\w+$/', trim($call['values'])) && (strpos($call['sql'], 'implode(') !== false)) {
				continue;
			}

			// The arrival date save binds the twelve column values of the
			// shared form (pg_arrival_date_form_values()) and the id.
			if (strpos($call['values'], 'array_merge(pg_arrival_date_form_values(') === 0) {
				pg_assert_same(13, substr_count($literals, '?'), $path . ' line ' . $call['line'] . ' has a placeholder for each arrival date value');
				continue;
			}

			pg_assert_same(
				0,
				strpos($call['values'], 'array('),
				$path . ' line ' . $call['line'] . ' passes its values as a literal array');

			$depth = 0;
			$elements = 1;

			foreach (token_get_all('<?php ' . $call['values'] . ';') as $token) {
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
			pg_assert_same(
				substr_count($literals, '?'),
				$elements,
				$path . ' line ' . $call['line'] . ' passes ' . $elements . ' values for its placeholders');
		}
	}
}

function test_db_q_shipping_import_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The zone check wrote the shipping method id bare and still does.
	pg_assert_same(
		"WHERE (shipping_method_id = 3) AND (zone_id = '8')",
		pg_db_bind('WHERE (shipping_method_id = ?) AND (zone_id = ?)', array((int) '3', (string) '8'), $escape));
	// A rounded shipping cost interpolated as "$x" is the same text as (string).
	$shipping_cost_for_item_per_unit = round(1999 / 2);
	pg_assert_same(
		"UPDATE order_items SET shipping = '$shipping_cost_for_item_per_unit' WHERE id = '5'",
		pg_db_bind('UPDATE order_items SET shipping = ? WHERE id = ?', array((string) $shipping_cost_for_item_per_unit, (string) 5), $escape));
	// A failed mktime() wrote '' and still does.
	pg_assert_same(
		"WHERE (created_timestamp >= '')",
		pg_db_bind('WHERE (created_timestamp >= ?)', array((string) false), $escape));
}

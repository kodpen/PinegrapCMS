<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the order and product screens (view_orders.php,
 * view_order.php, print_order.php, view_order_report.php,
 * view_products.php, edit_product_group.php, duplicate_products.php): their
 * raw queries with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, every db_q() call passes one value per
 * '?', and no '?' stands inside a quoted SQL literal. The raw queries left
 * are those without values and those completed by a clause built elsewhere
 * with escaped values, which may hold a '?' ($where, $sql_status,
 * $sql_type, $order_by, $sql_summarize_by_1..3, $disallowed_zones_sql_where,
 * and the products INSERT of duplicate_products() with $compare_at_value /
 * $ai_fields_value); the product counts appending $live_products_sql hold
 * no value.
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
function db_q_order_screens_test_calls($path)
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

function db_q_order_screens_test_files()
{
	return array(
		'view_orders.php' => 6,
		'view_order.php' => 13,
		// Its parcel query moved to pg_shipment_rows() with the shipping card.
		'print_order.php' => 12,
		'view_order_report.php' => 10,
		// Its row queries moved to includes/fn/product_list.php with the
		// server paging; the screen keeps only value-free counts.
		'view_products.php' => 0,
		'edit_product_group.php' => 14,
		'includes/forms/product_group_form.php' => 1,
		'duplicate_products.php' => 8,
	);
}

// Clause variables a raw query may still be completed by.
function db_q_order_screens_test_built($text)
{
	return (bool) preg_match('/\$(where|sql_status|sql_type|order_by|sql_summarize_by_[123]|live_products_sql|disallowed_zones_sql_where|compare_at_value|ai_fields_value)\b/', $text);
}

// The SQL text of a call's string literals, PHP code between them left out.
function db_q_order_screens_test_literals($sql)
{
	$text = '';

	foreach (token_get_all('<?php ' . $sql . ';') as $token) {
		if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
			$text .= $token[1];
		}
	}

	return $text;
}

function test_db_q_order_screens_value_queries_use_db_q()
{
	foreach (db_q_order_screens_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_order_screens_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_order_screens_test_built($call['text'])) {
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
function test_db_q_order_screens_values_fit_placeholders()
{
	foreach (array_keys(db_q_order_screens_test_files()) as $path) {
		foreach (db_q_order_screens_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q') {
				continue;
			}

			$literals = db_q_order_screens_test_literals($call['sql']);

			pg_assert_false(
				(bool) preg_match('/\'\?|\?\'/', $literals),
				$path . ' line ' . $call['line'] . ' quotes a placeholder');

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

function test_db_q_order_screens_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The order id the inventory check cast to int is still quoted.
	pg_assert_same(
		"WHERE order_items.order_id = '12'",
		pg_db_bind('WHERE order_items.order_id = ?', array((string) (int) '12abc'), $escape));
	// The zone copy of a duplicated product wrote both ids bare and still does.
	pg_assert_same(
		'INSERT INTO products_zones_xref (product_id, zone_id) VALUES (41, 7)',
		pg_db_bind('INSERT INTO products_zones_xref (product_id, zone_id) VALUES (?, ?)', array((int) 41, (int) '7'), $escape));
	// A missing posted group id is '' as escape(null) gave before.
	$posted = array();
	pg_assert_same(
		"DELETE FROM product_groups WHERE id = ''",
		pg_db_bind('DELETE FROM product_groups WHERE id = ?', array((string) ($posted['id'] ?? '')), $escape));
	// A NULL column of a copied form field stays '' rather than NULL.
	$row = array('default_value' => null, 'label' => "O'Neil");
	pg_assert_same(
		"VALUES ('', 'O\\'Neil')",
		pg_db_bind('VALUES (?, ?)', array((string) $row['default_value'], (string) $row['label']), $escape));
}

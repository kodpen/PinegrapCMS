<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the tax zone, state, currency, add, order edit and
 * payment job screens (edit_tax_zone.php and its shared form, edit_state.php,
 * edit_currency.php, update_currency.php, add_zone.php,
 * add_shipping_method.php, add_arrival_date.php, add_product_group.php,
 * edit_orders.php, view_orders_for_contact.php, membership_job.php,
 * recurring_payment_job.php, view_recurring_commission_profiles.php): their
 * raw queries with values run through db_q() placeholders, not
 * escape()/e() concatenated into the SQL text, and every db_q() call passes
 * one value per '?'. The raw queries left have no values; the commission
 * profile list keeps its integer LIMIT. The orders of a contact share the
 * $where clause, which holds one '?' bound by both queries.
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
function db_q_zones_currencies_jobs_test_calls($path)
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

function db_q_zones_currencies_jobs_test_files()
{
	return array(
		'edit_tax_zone.php' => 4,
		'includes/forms/tax_zone_form.php' => 4,
		'edit_state.php' => 6,
		'edit_currency.php' => 4,
		'update_currency.php' => 3,
		'add_zone.php' => 3,
		'add_shipping_method.php' => 1,
		'add_arrival_date.php' => 2,
		'add_product_group.php' => 2,
		'edit_orders.php' => 4,
		'view_orders_for_contact.php' => 3,
		'membership_job.php' => 6,
		'recurring_payment_job.php' => 4,
		'view_recurring_commission_profiles.php' => 0,
	);
}

function test_db_q_zones_currencies_jobs_value_queries_use_db_q()
{
	foreach (db_q_zones_currencies_jobs_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_zones_currencies_jobs_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
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
function test_db_q_zones_currencies_jobs_values_fit_placeholders()
{
	foreach (array_keys(db_q_zones_currencies_jobs_test_files()) as $path) {
		foreach (db_q_zones_currencies_jobs_test_calls($path) as $call) {
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

			// A clause built before the query brings its own '?'.
			foreach (array('$where' => 1) as $clause => $count) {
				if (preg_match('/' . preg_quote($clause, '/') . '\\b/', $call['sql'])) {
					$placeholders += $count;
				}
			}

			pg_assert_same(
				$placeholders,
				$elements,
				$path . ' line ' . $call['line'] . ' passes ' . $elements . ' values for its placeholders');
		}
	}
}

function test_db_q_zones_currencies_jobs_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The new zone and shipping method ids and the user id stay bare numbers.
	pg_assert_same(
		"INSERT INTO zones_countries_xref (zone_id, country_id) VALUES (12, '7')",
		pg_db_bind('INSERT INTO zones_countries_xref (zone_id, country_id) VALUES (?, ?)', array((int) 12, (string) '7'), $escape));
	pg_assert_same(
		"UPDATE arrival_dates SET default_selected = 0 WHERE id != 3",
		pg_db_bind('UPDATE arrival_dates SET default_selected = 0 WHERE id != ?', array((int) 3), $escape));
	// A rate keeps its decimal text inside quotes.
	pg_assert_same(
		"INSERT INTO zones (name, base_rate, user) VALUES ('EU', '12.50', 4)",
		pg_db_bind('INSERT INTO zones (name, base_rate, user) VALUES (?, ?, ?)', array('EU', (string) '12.50', (int) '4'), $escape));
	// The session order id is now escaped like the other values.
	pg_assert_same(
		"UPDATE orders SET currency_code = 'EUR' WHERE id = ''",
		pg_db_bind('UPDATE orders SET currency_code = ? WHERE id = ?', array((string) 'EUR', (string) (null ?? '')), $escape));
}

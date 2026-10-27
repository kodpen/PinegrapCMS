<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the commerce panel tail (tax zone, referral source,
 * featured items, commission, product, barcode, currency, country and state
 * screens, add to cart, private label, the panel product preview, dashboard
 * widgets 5, 21 and 22, the iyzico 3-D Secure state row): their raw queries
 * with values run through db_q() placeholders, not escape()/e() or
 * mysqli_real_escape_string() concatenated into the SQL text, and every
 * db_q() call passes one value per '?'. The raw queries left (list screens,
 * config reads, widgets 16, 18 and 20) have no values in their own text;
 * widget 18 is completed by clauses built beside it and an integer LIMIT.
 * The barcode screens and the featured items screen build their WHERE with
 * '?' and pass its values beside it.
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
function db_q_commerce_tail_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$assigned = array('$query' => '', '$item_images' => '');
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE && isset($assigned[$token[1]])) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$variable = $token[1];
				$assigned[$variable] = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$assigned[$variable] .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
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

		if (preg_match('/\(\s*(db::\$con\s*,\s*)?(\$query|\$item_images)\b/', $text, $variable_match)) {
			$sql .= $assigned[$variable_match[2]];
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => ($token[1] === 'db_q' && isset($assigned[$arguments[0]])) ? $assigned[$arguments[0]] : $arguments[0],
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

function db_q_commerce_tail_test_files()
{
	return array(
		'add_tax_zone.php' => 3,
		'edit_referral_source.php' => 3,
		'add_referral_source.php' => 1,
		'view_referral_sources.php' => 0,
		'edit_featured_and_new_items.php' => 3,
		'view_product_attributes.php' => 0,
		'view_order_reports.php' => 0,
		'view_gift_cards.php' => 0,
		'edit_recurring_commission_profile.php' => 2,
		'edit_products.php' => 2,
		'edit_commission.php' => 2,
		'catalog_detail.php' => 2,
		'barcode_increase_inventory.php' => 2,
		'barcode_decrease_inventory.php' => 2,
		'add_currency.php' => 2,
		'add_country.php' => 2,
		'add_state.php' => 1,
		'view_zones.php' => 0,
		'view_states.php' => 0,
		'view_shipping_methods.php' => 0,
		'view_currencies.php' => 0,
		'view_countries.php' => 0,
		'view_arrival_dates.php' => 0,
		'duplicate_product_group.php' => 0,
		'delete_key_codes.php' => 0,
		'add_to_cart.php' => 1,
		'private_label.php' => 1,
		'includes/panel/commerce.php' => 2,
		'includes/settings/general.save.php' => 0,
		'includes/dashboard/widgets/widget_5.php' => 1,
		'includes/dashboard/widgets/widget_16.php' => 0,
		'includes/dashboard/widgets/widget_18.php' => 0,
		'includes/dashboard/widgets/widget_20.php' => 0,
		'includes/dashboard/widgets/widget_21.php' => 2,
		'includes/dashboard/widgets/widget_22.php' => 2,
		'includes/payments/iyzico.php' => 3,
	);
}

function test_db_q_commerce_tail_value_queries_use_db_q()
{
	foreach (db_q_commerce_tail_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_commerce_tail_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}
}

// The WHERE clauses built beside their values hold only '?' and column names.
function test_db_q_commerce_tail_where_clauses_hold_no_values()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php', 'edit_featured_and_new_items.php') as $path) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);

		pg_assert_true(preg_match_all('/\$sql_where = "[^"]*";/', $source, $matches) >= 2, $path . ' builds its WHERE as a literal');

		foreach ($matches[0] as $clause) {
			pg_assert_false(strpos($clause, "'") !== false, $path . ' quotes a value in ' . $clause);
		}

		pg_assert_same(count($matches[0]), substr_count($source, '$where_values = array('), $path . ' passes values with each WHERE');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_commerce_tail_values_fit_placeholders()
{
	foreach (array_keys(db_q_commerce_tail_test_files()) as $path) {
		foreach (db_q_commerce_tail_test_calls($path) as $call) {
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

function test_db_q_commerce_tail_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// A bare user id and zone id stay bare numbers.
	pg_assert_same(
		"INSERT INTO tax_zones_countries_xref (tax_zone_id, country_id) VALUES (12, '7')",
		pg_db_bind('INSERT INTO tax_zones_countries_xref (tax_zone_id, country_id) VALUES (?, ?)', array((int) 12, (string) '7'), $escape));
	// A scanned barcode is quoted and escaped as before.
	pg_assert_same(
		"SELECT id FROM products WHERE name = 'AB\\'12'",
		pg_db_bind('SELECT id FROM products WHERE name = ?', array((string) "AB'12"), $escape));
	// The 3-D Secure row keeps its cent figures quoted, as the old SQL wrote them.
	pg_assert_same(
		"VALUES ('c-1', '42', '5792')",
		pg_db_bind('VALUES (?, ?, ?)', array((string) 'c-1', (string) (int) '42', (string) (int) 5792), $escape));
	// The firewall widgets compare the day boundary as a bare number.
	pg_assert_same(
		'WHERE log_timestamp >= 1761523200',
		pg_db_bind('WHERE log_timestamp >= ?', array((int) 1761523200), $escape));
}

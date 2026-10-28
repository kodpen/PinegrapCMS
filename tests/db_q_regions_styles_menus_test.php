<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the calendar location, ad, login and common region,
 * style, design file, menu item, field and file screens
 * (edit_calendar_event_location.php, duplicate_calendar_event.php,
 * edit_ad.php, view_ads.php, edit_login_region.php, edit_common_region.php,
 * view_styles.php, duplicate_style.php, edit_design_file.php,
 * view_menu_items.php, add_menu_item.php, view_fields.php, edit_files.php):
 * their raw queries with values run through db_q() placeholders, not
 * escape()/e() concatenated into the SQL text, and every db_q() call passes
 * one value per '?'. The raw queries left are those without values and
 * those completed by a clause built elsewhere, which may hold a '?': the
 * list filters and sort ($where, $sql_join, $order_by, $form_type_filter),
 * the region and design file updates ($sql_content, $sql_theme) and the
 * form name read from the table named by $form_settings_table.
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
function db_q_regions_styles_menus_test_calls($path)
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
function db_q_regions_styles_menus_test_built($text)
{
	return (bool) preg_match('/\$(where|sql_join|order_by|form_type_filter|sql_content|sql_theme|form_settings_table)\b/', $text);
}

function test_db_q_regions_styles_menus_value_queries_use_db_q()
{
	$expected = array('edit_calendar_event_location.php' => 6, 'duplicate_calendar_event.php' => 6, 'edit_ad.php' => 5, 'view_ads.php' => 1, 'edit_login_region.php' => 4, 'edit_common_region.php' => 3, 'view_styles.php' => 0, 'duplicate_style.php' => 4, 'edit_design_file.php' => 5, 'view_menu_items.php' => 4, 'add_menu_item.php' => 2, 'view_fields.php' => 2, 'edit_files.php' => 4);

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_regions_styles_menus_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_regions_styles_menus_test_built($call['text'])) {
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
function test_db_q_regions_styles_menus_values_fit_placeholders()
{
	foreach (array('edit_calendar_event_location.php', 'duplicate_calendar_event.php', 'edit_ad.php', 'view_ads.php', 'edit_login_region.php', 'edit_common_region.php', 'view_styles.php', 'duplicate_style.php', 'edit_design_file.php', 'view_menu_items.php', 'add_menu_item.php', 'view_fields.php', 'edit_files.php') as $path) {
		foreach (db_q_regions_styles_menus_test_calls($path) as $call) {
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

function test_db_q_regions_styles_menus_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// A style copied without a tree keeps NULL; one with a tree keeps its quoted JSON.
	$cases = array(
		array(null, 'NULL'),
		array('{"a":1}', "'{\\\"a\\\":1}'"),
	);

	foreach ($cases as $case) {
		list($tree, $expected) = $case;
		pg_assert_same(
			'INSERT INTO style (style_tree_json, style_user) VALUES (' . $expected . ", '4')",
			pg_db_bind('INSERT INTO style (style_tree_json, style_user) VALUES (?, ?)', array((($tree === null) ? null : (string) $tree), (string) 4), $escape));
	}
	// An id written unescaped into quotes stays quoted.
	pg_assert_same(
		"SELECT COUNT(*) FROM menu_items WHERE parent_id = '9'",
		pg_db_bind('SELECT COUNT(*) FROM menu_items WHERE parent_id = ?', array((string) 9), $escape));
}

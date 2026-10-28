<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the region, menu, form list view and calendar screens
 * (save_region_content.php, edit_ad_region.php, edit_menu.php,
 * edit_menu_item.php, edit_form_list_view.php, edit_calendar.php,
 * add_calendar_event.php and its shared form, update_calendar_event_exception.php): their raw
 * queries with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, and every db_q() call passes one value
 * per '?'. The raw queries left have no values. Optional SQL pieces carry
 * their values beside them ($name_values, $publish_values);
 * $sql_seo_analysis_current holds no value.
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
function db_q_regions_menus_calendars_test_calls($path)
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

function db_q_regions_menus_calendars_test_files()
{
	return array(
		'save_region_content.php' => 10,
		'edit_ad_region.php' => 7,
		'includes/forms/ad_region_form.php' => 2,
		'edit_menu.php' => 6,
		'includes/forms/menu_form.php' => 2,
		'edit_menu_item.php' => 9,
		'includes/forms/menu_item_form.php' => 2,
		'edit_form_list_view.php' => 11,
		'edit_calendar.php' => 10,
		'add_calendar_event.php' => 3,
		'includes/forms/calendar_event_form.php' => 4,
		'update_calendar_event_exception.php' => 7,
	);
}

function test_db_q_regions_menus_calendars_value_queries_use_db_q()
{
	foreach (db_q_regions_menus_calendars_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_regions_menus_calendars_test_calls($path) as $call) {
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
function test_db_q_regions_menus_calendars_values_fit_placeholders()
{
	foreach (array_keys(db_q_regions_menus_calendars_test_files()) as $path) {
		foreach (db_q_regions_menus_calendars_test_calls($path) as $call) {
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

function test_db_q_regions_menus_calendars_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The ad region name goes first only when a designer may change it.
	pg_assert_same(
		"UPDATE ad_regions SET name = 'Top', display_type = 'static' WHERE id = '4'",
		pg_db_bind('UPDATE ad_regions SET name = ?, display_type = ? WHERE id = ?', array_merge(array('Top'), array('static', (string) 4)), $escape));
	pg_assert_same(
		"UPDATE ad_regions SET  display_type = 'static' WHERE id = '4'",
		pg_db_bind('UPDATE ad_regions SET  display_type = ? WHERE id = ?', array_merge(array(), array('static', (string) 4)), $escape));
	// The menu item position compares a bare number as before.
	pg_assert_same(
		"SELECT id FROM menu_items WHERE (menu_id = '2') AND (sort_order < 5) AND (parent_id = '0')",
		pg_db_bind('SELECT id FROM menu_items WHERE (menu_id = ?) AND (sort_order < ?) AND (parent_id = ?)', array((string) 2, (int) '5', (string) '0'), $escape));
	// The event notes are bound raw, so a quote is escaped once.
	pg_assert_same(
		"INSERT INTO calendar_events (notes) VALUES ('It\\'s')",
		pg_db_bind('INSERT INTO calendar_events (notes) VALUES (?)', array((string) "It's"), $escape));
}

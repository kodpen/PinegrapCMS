<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the panel screens listed in
 * db_q_contacts_forms_test_files() (contacts, contact groups, form fields,
 * submitted forms, theme and file editors, custom styles, the explorer
 * panel, the page form and the firewall log): their raw queries with values
 * run through db_q() placeholders, not escape()/e() concatenated into the
 * SQL text, and every db_q() call passes one value per '?'. The raw queries
 * left are those without values (the bare column $date_type_column and the
 * bare insert id $theme_id included) and those completed by a clause built
 * elsewhere with escaped values, which may hold a '?'; their variables are
 * listed in db_q_contacts_forms_test_built().
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
// arguments, its SQL argument and, when the SQL is a variable named $query
// or $…_query, the text of that variable's last assignment before the call.
function db_q_contacts_forms_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$queries = array();
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE && preg_match('/^\$(\w+_)?query$/', $token[1])) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$queries[$token[1]] = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$queries[$token[1]] .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
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
		$sql_argument = ($token[1] === 'mysqli_query') ? (isset($arguments[1]) ? $arguments[1] : '') : $arguments[0];
		$sql = $text;

		if (isset($queries[$sql_argument])) {
			$sql .= $queries[$sql_argument];
			$sql_argument = $queries[$sql_argument];
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => $sql_argument,
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

// Each file with the number of its queries that run through db_q().
function db_q_contacts_forms_test_files()
{
	return array(
		'edit_contact.php' => 7,
		'view_contacts.php' => 7,
		'edit_submitted_form.php' => 13,
		'view_submitted_forms.php' => 11,
		'includes/fn/submitted_form_list.php' => 4,
		'includes/fn/contact_list.php' => 5,
		'edit_field.php' => 8,
		'edit_contact_group.php' => 10,
		'edit_contacts.php' => 8,
		'add_contact.php' => 1,
		'includes/forms/contact_form.php' => 13,
		'import_contacts.php' => 4,
		'add_field.php' => 2,
		'includes/forms/field_form.php' => 9,
		'delete_fields.php' => 8,
		'add_submitted_form.php' => 1,
		'import_submitted_forms.php' => 7,
		'edit_theme_file.php' => 9,
		'add_theme_file.php' => 5,
		'edit_file.php' => 7,
		'edit_custom_style.php' => 6,
		'includes/panel/explorer.php' => 9,
		'includes/forms/page_form.php' => 3,
		'includes/forms/submitted_form_form.php' => 4,
		'view_waf_log.php' => 5,
	);
}

// Clause variables a raw query may still be completed by.
function db_q_contacts_forms_test_built($text)
{
	return (bool) preg_match('/\$(sql_affiliate|sql_file_id|sql_image|where|my_contacts_where|sql_form_editor|sql_data_joins|form_type_filter|sql_upload_folder_id|sql_translate_values|sql_upload_folder_id_value|sql_translate_values_value|data_list|sql_design|sql_size|sql_social_networking_position|system_theme_properties_for_sql|in_list|quoted_v4|date_type_column)\b/', $text);
}

function test_db_q_contacts_forms_value_queries_use_db_q()
{
	$expected = db_q_contacts_forms_test_files();

	foreach ($expected as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_contacts_forms_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_contacts_forms_test_built($call['text'])) {
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
function test_db_q_contacts_forms_values_fit_placeholders()
{
	foreach (array_keys(db_q_contacts_forms_test_files()) as $path) {
		foreach (db_q_contacts_forms_test_calls($path) as $call) {
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

function test_db_q_contacts_forms_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// A missing posted id was escape(null), '' as before.
	pg_assert_same(
		"DELETE FROM opt_in WHERE contact_id = ''",
		pg_db_bind('DELETE FROM opt_in WHERE contact_id = ?', array((string) (null ?? '')), $escape));
	// Ids and counters the old SQL wrote unescaped inside quotes stay quoted.
	pg_assert_same(
		"UPDATE form_fields SET sort_order = '3' WHERE id = '12'",
		pg_db_bind('UPDATE form_fields SET sort_order = ? WHERE id = ?', array((string) 3, (string) '12'), $escape));
	// A form data value with a quote is escaped once.
	pg_assert_same(
		"INSERT INTO form_data (data) VALUES ('O\\'Brien')",
		pg_db_bind('INSERT INTO form_data (data) VALUES (?)', array((string) "O'Brien"), $escape));
}

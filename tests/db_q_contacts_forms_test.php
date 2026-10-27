<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the contact and submitted form screens (edit_contact.php,
 * view_contacts.php, edit_submitted_form.php, view_submitted_forms.php,
 * edit_field.php, edit_contact_group.php, edit_contacts.php,
 * add_contact.php, import_contacts.php, add_field.php, delete_fields.php,
 * add_submitted_form.php, import_submitted_forms.php): their raw queries
 * with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, and every
 * db_q() call passes one value per '?'. The raw queries left are those
 * without values (the bare column $date_type_column included) and those
 * completed by a clause built elsewhere with escaped values, which may hold
 * a '?' ($sql_affiliate, $sql_file_id, $sql_image, $where,
 * $my_contacts_where, $sql_form_editor, $sql_data_joins,
 * $form_type_filter, $sql_upload_folder_id, $sql_translate_values,
 * $sql_upload_folder_id_value, $sql_translate_values_value, $data_list).
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
function db_q_contacts_forms_test_calls($path)
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
function db_q_contacts_forms_test_built($text)
{
	return (bool) preg_match('/\$(sql_affiliate|sql_file_id|sql_image|where|my_contacts_where|sql_form_editor|sql_data_joins|form_type_filter|sql_upload_folder_id|sql_translate_values|sql_upload_folder_id_value|sql_translate_values_value|data_list|date_type_column)\b/', $text);
}

function test_db_q_contacts_forms_value_queries_use_db_q()
{
	$expected = array('edit_contact.php' => 18, 'view_contacts.php' => 7, 'edit_submitted_form.php' => 18, 'view_submitted_forms.php' => 11, 'edit_field.php' => 14, 'edit_contact_group.php' => 10, 'edit_contacts.php' => 8, 'add_contact.php' => 6, 'import_contacts.php' => 4, 'add_field.php' => 7, 'delete_fields.php' => 8, 'add_submitted_form.php' => 6, 'import_submitted_forms.php' => 7);

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
	foreach (array('edit_contact.php', 'view_contacts.php', 'edit_submitted_form.php', 'view_submitted_forms.php', 'edit_field.php', 'edit_contact_group.php', 'edit_contacts.php', 'add_contact.php', 'import_contacts.php', 'add_field.php', 'delete_fields.php', 'add_submitted_form.php', 'import_submitted_forms.php') as $path) {
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

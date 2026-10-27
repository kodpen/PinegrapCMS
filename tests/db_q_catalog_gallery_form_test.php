<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on get_catalog.php, get_photo_gallery.php and
 * custom_form.php: their raw queries with values run through db_q()
 * placeholders, not escape()/e() or bare variables written into the SQL
 * text, and every db_q() call passes one value per '?'. The raw queries left
 * are those without values and, in custom_form.php, the two completed by a
 * clause built elsewhere with escaped values, which may hold a '?' (the
 * e-mail option lookup with $where, the contact update with
 * $sql_update_contact). The db() / db_value() helper calls with e() are
 * left as they are.
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
function db_q_catalog_gallery_form_test_calls($path)
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

		if ($token[0] === T_VARIABLE && in_array($token[1], array('$query', '$db_query'), true)) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$assigned[$token[1]] = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$assigned[$token[1]] .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
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
		$first = $arguments[0];

		foreach ($assigned as $variable => $assignment) {
			if (preg_match('/\(\s*(db::\$con\s*,\s*)?' . preg_quote($variable, '/') . '\b/', $text)) {
				$sql .= $assignment;
			}

			if ($token[1] === 'db_q' && $arguments[0] === $variable) {
				$first = $assignment;
			}
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => $first,
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

// Clause variables a raw query may still be completed by.
function db_q_catalog_gallery_form_test_built($text)
{
	return (bool) preg_match('/\$(where|sql_update_contact)\b/', $text);
}

function db_q_catalog_gallery_form_test_files()
{
	return array('get_catalog.php' => 15, 'get_photo_gallery.php' => 14, 'custom_form.php' => 18);
}

function test_db_q_catalog_gallery_form_value_queries_use_db_q()
{
	foreach (db_q_catalog_gallery_form_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_catalog_gallery_form_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(|\'"\s*\.|\'\$[a-z_]/i', $call['sql']),
					$path . ' line ' . $call['line'] . ' writes a value into its db_q() SQL');
				continue;
			}

			if (db_q_catalog_gallery_form_test_built($call['text'])) {
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
function test_db_q_catalog_gallery_form_values_fit_placeholders()
{
	foreach (db_q_catalog_gallery_form_test_files() as $path => $minimum) {
		foreach (db_q_catalog_gallery_form_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q' || strpos($call['values'], 'array(') !== 0) {
				continue;
			}

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

function test_db_q_catalog_gallery_form_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The quiz score is a rounded float, written as before.
	pg_assert_same(
		"UPDATE forms SET quiz_score = '67' WHERE id = '9'",
		pg_db_bind('UPDATE forms SET quiz_score = ? WHERE id = ?', array((string) round(2 / 3 * 100), (string) 9), $escape));
	// A submission without a signed-in user writes '' for the user, as before.
	pg_assert_same(
		"INSERT INTO contacts (user, timestamp) VALUES ('', UNIX_TIMESTAMP())",
		pg_db_bind('INSERT INTO contacts (user, timestamp) VALUES (?, UNIX_TIMESTAMP())', array((string) ''), $escape));
	pg_assert_same(
		"SELECT id FROM product_groups WHERE address_name = 'o\\'brien'",
		pg_db_bind('SELECT id FROM product_groups WHERE address_name = ?', array((string) "o'brien"), $escape));
}

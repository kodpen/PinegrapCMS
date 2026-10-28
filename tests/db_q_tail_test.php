<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the installer, the designer panel actions and the
 * TinyMCE image and link upload pickers: their queries with values run
 * through db_q() placeholders, not escape() / e() concatenated into the SQL
 * text, every db_q() call passes one value per '?', and the raw
 * mysqli_query() calls left (none of them with a value) do not grow in
 * number. includes/authentication.php is also loaded by get_file.php, which
 * has no db_q(), so it must not call it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each file with the number of db_q() calls it has at least and the number
// of raw mysqli_query() calls it has at most.
function db_q_tail_test_files()
{
	return array(
		'install/index.php' => array(22, 83),
		'includes/panel/designer.php' => array(2, 0),
		'assets/lib/tiny_mce_3_5_10/plugins/advimage/add_file.php' => array(4, 0),
		'assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php' => array(4, 0),
	);
}

// Each mysqli_query() / db_q() call of a file with the text of its
// arguments, its first argument and, when the SQL is $query, the text of
// that variable's last assignment before the call.
function db_q_tail_test_calls($path)
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

		// A function declaration is not a call.
		$before = $i - 1;

		while ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_WHITESPACE) {
			$before--;
		}

		if ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_FUNCTION) {
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

function test_db_q_tail_value_queries_use_db_q()
{
	foreach (db_q_tail_test_files() as $path => $limits) {
		$db_q = 0;
		$raw = 0;

		foreach (db_q_tail_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\./', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates a value into its db_q() SQL');
				continue;
			}

			$raw++;

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]|=\s*"\s*\.\s*\$/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $limits[0], $path . ' runs ' . $db_q . ' queries through db_q()');
		pg_assert_true($raw <= $limits[1], $path . ' has ' . $raw . ' raw mysqli_query() calls');
	}

	// The pickers no longer escape a value into any SQL text.
	foreach (array('assets/lib/tiny_mce_3_5_10/plugins/advimage/add_file.php', 'assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php') as $path) {
		pg_assert_false(
			(bool) preg_match('/"\s*\.\s*escape\(\$(file_name|_POST|file_extension|file_size)/', file_get_contents(PG_FUNCTIONS_DIR . '/' . $path)),
			$path . ' escapes an upload value for SQL text');
	}
}

// get_file.php loads includes/authentication.php without functions.php and
// defines only db(), db_value(), db_item(), db_items() and escape(), so a
// db_q() call there would end every signed-in download.
function test_db_q_tail_authentication_has_no_db_q()
{
	foreach (db_q_tail_test_calls('includes/authentication.php') as $call) {
		pg_assert_true($call['call'] !== 'db_q', 'includes/authentication.php line ' . $call['line'] . ' calls db_q(), which get_file.php does not define');
	}
}

// Code that runs before functions.php is loaded keeps its raw query: the
// installer's language probe asks its own connection.
function test_db_q_tail_installer_loads_db_q_first()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	$loaded = strpos($source, "require (dirname(__FILE__) . '/../functions.php');");
	$first = strpos($source, 'db_q(');

	pg_assert_true($loaded !== false, 'install/index.php loads functions.php');
	pg_assert_true($first !== false && $first > $loaded, 'install/index.php calls db_q() only after functions.php is loaded');
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_tail_values_fit_placeholders()
{
	foreach (array_keys(db_q_tail_test_files()) as $path) {
		foreach (db_q_tail_test_calls($path) as $call) {
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

function test_db_q_tail_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The designer region: the user id bare, as before.
	pg_assert_same(
		"INSERT INTO cregion (cregion_name, cregion_content, cregion_designer_type, cregion_user, cregion_timestamp) VALUES ('n', 'c?', 'yes', 4, UNIX_TIMESTAMP())",
		pg_db_bind("INSERT INTO cregion (cregion_name, cregion_content, cregion_designer_type, cregion_user, cregion_timestamp) VALUES (?, ?, 'yes', ?, UNIX_TIMESTAMP())", array('n', 'c?', (int) '4'), $escape));
	// The installer's administrator check: the session id quoted.
	pg_assert_same(
		"SELECT user_id FROM user WHERE (user_role = '0') AND (user_id = '0')",
		pg_db_bind("SELECT user_id FROM user WHERE (user_role = '0') AND (user_id = ?)", array((string) (int) 'x'), $escape));
	// The picker upload: every column quoted, the folder empty when none came.
	pg_assert_same(
		"INSERT INTO files (name, folder, type, size, user, timestamp, design) VALUES ('a.png', '', 'png', '12', '3', UNIX_TIMESTAMP(), '0')",
		pg_db_bind("INSERT INTO files (name, folder, type, size, user, timestamp, design) VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP(), '0')", array('a.png', (string) null, 'png', (string) 12, (string) 3), $escape));
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the page, region, theme, menu, file editor and panel
 * content screens (add_page.php, the add/edit region screens, the theme and
 * JavaScript editors, includes/fn/page_revisions.php, includes/panel/
 * content.php, ...): their raw queries with values run
 * through db_q() placeholders, not escape()/e() concatenated into the SQL
 * text, and every db_q() call passes one value per '?'. The raw queries
 * left have no values or are completed by a clause built elsewhere
 * ($where, $order_by). Optional SQL pieces carry their values beside them
 * ($style_values, $social_networking_position_values, $query_values);
 * add_page.php's noindex/nofollow and sitemap stay PHP ints written inline.
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
function db_q_pages_regions_test_calls($path)
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

function db_q_pages_regions_test_files()
{
	return array(
		'add_page.php' => 4,
		'view_pages.php' => 1,
		'mass_edit.php' => 0,
		'preview_theme.php' => 0,
		'get_region_content.php' => 3,
		'edit_dynamic_region.php' => 3,
		'edit_designer_region.php' => 3,
		'view_themes.php' => 0,
		'view_menus.php' => 0,
		'add_menu.php' => 1,
		'add_login_region.php' => 1,
		'includes/forms/login_region_form.php' => 1,
		'add_folder.php' => 2,
		'view_files.php' => 0,
		'view_design_files.php' => 0,
		'get_theme_designer_module.php' => 1,
		'editor_edit_file.php' => 2,
		'editor_add_file.php' => 1,
		'edit_theme_css.php' => 2,
		'edit_javascript.php' => 2,
		'view_system_style_source.php' => 1,
		'generate_system_theme_css.php' => 1,
		'add_ad_region.php' => 1,
		'add_ad.php' => 1,
		'includes/forms/ad_form.php' => 1,
		'add_dynamic_region.php' => 1,
		'add_designer_region.php' => 1,
		'add_common_region.php' => 1,
		'add_custom_style.php' => 1,
		'includes/fn/page_revisions.php' => 2,
		'includes/fn/page_cache.php' => 0,
		'includes/fn/meta_tags.php' => 0,
		'includes/fn/image.php' => 0,
		'includes/fn/editor.php' => 0,
		'includes/fn/design_themes.php' => 0,
		'includes/panel/content.php' => 2,
	);
}

function test_db_q_pages_regions_value_queries_use_db_q()
{
	foreach (db_q_pages_regions_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_pages_regions_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(|\'"\s*\.|\'\$[a-z_]|\{\$user/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_pages_regions_values_fit_placeholders()
{
	foreach (array_keys(db_q_pages_regions_test_files()) as $path) {
		foreach (db_q_pages_regions_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q' || strpos($call['values'], 'array(') !== 0) {
				continue;
			}

			// Whitespace dropped, so a trailing comma is the one right before ')'.
			$tokens = array_values(array_filter(token_get_all('<?php ' . $call['values'] . ';'), function ($token) {
				return !is_array($token) || $token[0] !== T_WHITESPACE;
			}));
			$depth = 0;
			$elements = 1;

			foreach ($tokens as $index => $token) {
				$piece = is_array($token) ? $token[1] : $token;

				if ($piece === '(' || $piece === '[') {
					$depth++;
				} elseif ($piece === ')' || $piece === ']') {
					$depth--;
				} elseif ($piece === ',' && $depth === 1 && ($tokens[$index + 1] ?? '') !== ')') {
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

function test_db_q_pages_regions_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The page INSERT puts the style values between the fixed ones.
	pg_assert_same(
		"INSERT INTO page (page_name, page_style, mobile_style_id, comments_disallow_new_comment_message) VALUES ('a_b', '3', '', 'We\\'re sorry.')",
		pg_db_bind('INSERT INTO page (page_name, page_style, mobile_style_id, comments_disallow_new_comment_message) VALUES (?, ?, ?, ?)', array_merge(array('a_b'), array((string) '3', (string) null), array("We're sorry.")), $escape));
	// The region user written bare before stays bare: bound as an int.
	pg_assert_same(
		"INSERT INTO dregion (dregion_name, dregion_code, dregion_user) VALUES ('n', '<b>', 7)",
		pg_db_bind('INSERT INTO dregion (dregion_name, dregion_code, dregion_user) VALUES (?, ?, ?)', array('n', '<b>', (int) '7'), $escape));
	// A page revision's ints stay quoted, as the old SQL wrote them.
	pg_assert_same(
		"SELECT '5', '0', COALESCE(MAX(revision_no), 0) + 1 FROM page_revisions WHERE page_id = '5'",
		pg_db_bind('SELECT ?, ?, COALESCE(MAX(revision_no), 0) + 1 FROM page_revisions WHERE page_id = ?', array((string) 5, (string) (int) null, (string) 5), $escape));
}

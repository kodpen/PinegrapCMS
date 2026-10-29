<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel screen titles: every pg_page_shell() call passes a
 * 'title' and a 'heading' (the tab title and the top bar's screen name), and
 * the ERP record screens escape the stored record name they put in 'title',
 * because output_header() prints 'title' into <title> as given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_screen_titles_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * Returns, for each pg_page_shell() call in a PHP source, the top-level keys
 * of its array argument, or null when the argument is not an array literal.
 */
function panel_screen_titles_calls($source)
{
	$tokens = token_get_all($source);
	$count = count($tokens);
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'pg_page_shell') {
			continue;
		}

		// Skip the definition itself.
		$prev = $i - 1;
		while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
			$prev--;
		}
		if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_FUNCTION) {
			continue;
		}

		$j = $i + 1;
		while ($j < $count && (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT), true))) {
			$j++;
		}
		if ($j >= $count || $tokens[$j] !== '(') {
			continue;
		}
		$j++;
		while ($j < $count && (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT), true))) {
			$j++;
		}

		$line = is_array($tokens[$i]) ? $tokens[$i][2] : 0;
		$is_array_literal = ($tokens[$j] === '[') || (is_array($tokens[$j]) && $tokens[$j][0] === T_ARRAY);
		if (!$is_array_literal) {
			$calls[] = array('line' => $line, 'keys' => null);
			continue;
		}

		if ($tokens[$j] !== '[') {
			$j++;
			while ($tokens[$j] !== '(') {
				$j++;
			}
		}

		// Walk the array, collecting string keys at depth 1.
		$depth = 0;
		$keys = array();
		$last_string = null;
		for (; $j < $count; $j++) {
			$token = $tokens[$j];
			if ($token === '(' || $token === '[' || $token === '{') {
				$depth++;
			} elseif ($token === ')' || $token === ']' || $token === '}') {
				$depth--;
				if ($depth === 0) {
					break;
				}
			} elseif (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) {
				$depth++;
			}

			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$last_string = ($depth === 1) ? substr($token[1], 1, -1) : null;
			} elseif (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
				if ($last_string !== null) {
					$keys[] = $last_string;
				}
				$last_string = null;
			} elseif (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT), true)) {
				$last_string = null;
			}
		}

		$calls[] = array('line' => $line, 'keys' => $keys);
	}

	return $calls;
}

function test_panel_screen_titles_tokenizer_reads_top_level_keys()
{
	$calls = panel_screen_titles_calls("<?php\npg_page_shell(array('cancel' => array('title' => 'x'), 'heading' => h(\$a['b'])));\n");
	pg_assert_same(array('cancel', 'heading'), $calls[0]['keys'], 'nested title is not top level');

	$calls = panel_screen_titles_calls("<?php\npg_page_shell(\$shell);\n");
	pg_assert_same(null, $calls[0]['keys'], 'variable argument');
}

function test_panel_screen_titles_every_shell_has_title_and_heading()
{
	// These build the properties in a variable or pick one of two arrays.
	$variable_callers = array('view_not_found_log.php', 'view_accessibility.php', 'view_folders.php');

	$root = dirname(__DIR__) . '/pinegrap/';
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$found = 0;

	foreach ($iterator as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
		if (substr($path, -4) !== '.php' || strpos($path, 'assets/lib/') === 0 || strpos($path, 'vendor/') !== false) {
			continue;
		}
		$source = (string) file_get_contents($file->getPathname());
		if (strpos($source, 'pg_page_shell') === false) {
			continue;
		}

		foreach (panel_screen_titles_calls($source) as $call) {
			$found++;
			$where = $path . ':' . $call['line'];
			if ($call['keys'] === null) {
				pg_assert_true(in_array($path, $variable_callers, true), $where . ': properties passed in a variable');
				continue;
			}
			pg_assert_true(in_array('title', $call['keys'], true), $where . ': no title');
			pg_assert_true(in_array('heading', $call['keys'], true), $where . ': no heading');
		}
	}

	pg_assert_true($found > 200, 'pg_page_shell() calls found: ' . $found);
}

function test_panel_screen_titles_six_screens_have_an_icon()
{
	$paths = array(
		'edit_theme_css.php',
		'edit_javascript.php',
		'edit_custom_style.php',
		'view_fields.php',
		'preview_form.php',
		'view_menu_items.php',
	);

	foreach ($paths as $path) {
		foreach (panel_screen_titles_calls(panel_screen_titles_source($path)) as $call) {
			pg_assert_true(in_array('icon', (array) $call['keys'], true), $path . ':' . $call['line'] . ': no icon');
		}
	}
}

function test_panel_screen_titles_erp_record_titles_are_escaped()
{
	$pins = array(
		'erp_stock_count.php' => "'title' => h((string) \$count['title'])",
		'erp_cheque.php' => "'title' => (\$name !== '') ? h(\$name) : lang('Cheques and notes')",
		'edit_erp_purchase_order.php' => "'title' => h((string) \$order['full_number'])",
		'edit_erp_quote.php' => "'title' => h((string) \$quote['full_number'])",
	);

	foreach ($pins as $path => $needle) {
		pg_assert_contains($needle, panel_screen_titles_source($path), $path . ': record title escaped');
	}
}

function test_panel_screen_titles_menu_reorder_bar_named()
{
	$source = panel_screen_titles_source('view_menu_items.php');
	pg_assert_false(strpos($source, 'aria-label="reorder buttons"') !== false, 'literal English aria-label');
	pg_assert_contains('id="reorder_actions" style="bottom:.5rem;display:none" aria-label="\' . h(lang(\'Reorder Buttons\')) . \'"', $source, 'reorder bar named through lang()');
}

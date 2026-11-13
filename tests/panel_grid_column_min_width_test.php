<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test that a panel stylesheet never lets a grid column grow past the
 * window: a rule that takes a .row child off Bootstrap's own width (width:
 * auto or max-width: none) also gives it a min-width. Without one the
 * column's floor is its content's min-content width, so a value that does
 * not wrap (a combobox listing every chosen state, a long address) widens
 * the column, the row and the whole page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/look_rules.php');

/**
 * The innermost rules of a stylesheet as array(selector, declarations, line),
 * comments removed (their line breaks kept, so lines still count).
 *
 * @param string $css
 * @return array
 */
function panel_grid_column_rules($css)
{
	$css = preg_replace_callback('#/\*.*?\*/#s', function ($comment) {
		return str_repeat("\n", substr_count($comment[0], "\n"));
	}, (string) $css);

	$rules = array();

	if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		foreach ($matches as $match) {
			$selector = trim($match[1][0]);
			$line = substr_count(substr($css, 0, $match[1][1] + strlen($match[1][0]) - strlen(ltrim($match[1][0]))), "\n") + 1;
			$rules[] = array($selector, $match[2][0], $line);
		}
	}

	return $rules;
}

/**
 * Whether a rule frees a grid column from Bootstrap's width without giving
 * it a floor: one of its selectors ends on a direct child of .row, it sets
 * width: auto or max-width: none, and it sets no min-width.
 *
 * @param string $selector
 * @param string $declarations
 * @return bool
 */
function panel_grid_column_rule_overflows($selector, $declarations)
{
	$is_column = false;

	foreach (explode(',', $selector) as $one) {
		if (preg_match('/\.row\s*>\s*[^\s>+~]+$/', trim($one))) {
			$is_column = true;
		}
	}

	if (!$is_column) {
		return false;
	}

	$frees_width = preg_match('/(?<![\w-])width\s*:\s*auto\b/', $declarations)
		|| preg_match('/(?<![\w-])max-width\s*:\s*none\b/', $declarations);

	return $frees_width && !preg_match('/(?<![\w-])min-width\s*:/', $declarations);
}

function test_grid_column_rule_needs_a_floor()
{
	// The add and edit screens' form column as it first shipped: the page
	// grew to the width of the longest combobox value.
	pg_assert_true(panel_grid_column_rule_overflows('body.pg-measure-edit .row > .order-1.lg\:col-8', 'flex: 760 1 0%; width: auto; max-width: none;'), 'a column freed from its width with no floor is refused');
	pg_assert_true(panel_grid_column_rule_overflows('.a, .row > .col', 'max-width: none;'), 'one selector of a list is enough');
	pg_assert_true(panel_grid_column_rule_overflows('.row>.col-6', 'width: auto'), 'no spaces around the child combinator');

	pg_assert_false(panel_grid_column_rule_overflows('body.pg-measure-edit .row > .order-1.lg\:col-8', 'flex: 760 1 0%; width: auto; min-width: 0; max-width: none;'), 'min-width: 0 is the floor');
	pg_assert_false(panel_grid_column_rule_overflows('body.pg-measure-edit .row > .order-3 > nav.buttons.navigation > .container', 'max-width: none;'), 'an element deeper than the column is not a column');
	pg_assert_false(panel_grid_column_rule_overflows('.row > .col', 'padding-inline: 0; min-height: 2rem;'), 'a column keeping Bootstrap width passes');
	pg_assert_false(panel_grid_column_rule_overflows('.pg-edit-columns > *', 'width: auto; max-width: none;'), 'a grid that is not .row passes');
}

function test_panel_stylesheets_keep_grid_columns_in_the_window()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$checked = 0;

	foreach (pg_look_stylesheets() as $path) {
		$css = (string) @file_get_contents($root . $path);
		if ($css === '') {
			continue;
		}

		$checked++;

		foreach (panel_grid_column_rules($css) as $rule) {
			pg_assert_false(panel_grid_column_rule_overflows($rule[0], $rule[1]), $path . ':' . $rule[2] . ' ' . preg_replace('/\s+/', ' ', $rule[0]) . ' sets a min-width when it frees the column from its width');
		}
	}

	pg_assert_true($checked > 0, 'the panel stylesheets were read');
}

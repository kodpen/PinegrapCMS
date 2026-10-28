<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for panel rules in backend.src.css that must follow the
 * panel theme. Bootstrap 6 resolves its colours through light-dark(), so a
 * rule carrying a fixed light fill or black text stays light in the dark
 * theme. The light theme keeps its v5 look: the light value is written
 * where no token gives the same colour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dark_theme_css_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

// The body of the first rule whose selector is exactly $selector.
function dark_theme_css_rule($source, $selector)
{
	if (preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $source, $match) !== 1) {
		return '';
	}

	return $match[1];
}

// The light table tint of table heads (find_and_replace.php,
// view_parasut_inbox.php, erp_expense_categories.php): #f8f9fa and black
// text in the light theme, the panel's raised surface and text in the dark.
function test_dark_theme_css_table_light_follows_theme()
{
	$rule = dark_theme_css_rule(dark_theme_css_source(), '.pg-table-light');

	pg_assert_true($rule !== '', 'backend.src.css has a .pg-table-light rule');
	pg_assert_true(strpos($rule, '--bs-table-bg: var(--pg-bg-1);') !== false, '.pg-table-light takes its fill from --pg-bg-1');
	pg_assert_true(strpos($rule, '--bs-table-color: var(--pg-fg-emphasis);') !== false, '.pg-table-light takes its text from --pg-fg-emphasis');
	pg_assert_true(strpos($rule, 'color: var(--pg-fg-emphasis);') !== false, '.pg-table-light sets its own text colour from the token');
	pg_assert_true(strpos($rule, 'light-dark(#c6c7c8, var(--pg-border-color))') !== false, '.pg-table-light keeps the v5 line in light and the panel line in dark');

	foreach (array('#f8f9fa', '#000') as $fixed) {
		pg_assert_true(strpos($rule, $fixed) === false, '.pg-table-light holds the fixed colour ' . $fixed);
	}
}

// Bootstrap 5 neutral names Bootstrap 6 dropped turn a colour invalid.
function test_dark_theme_css_reads_no_dropped_v5_neutrals()
{
	$source = dark_theme_css_source();

	foreach (array('--bs-body-color', '--bs-body-bg', '--bs-secondary-color', '--bs-tertiary-color', '--bs-tertiary-bg', '--bs-emphasis-color') as $name) {
		pg_assert_true(preg_match('/var\(' . preg_quote($name, '/') . '\b/', $source) !== 1, 'backend.src.css reads ' . $name);
	}
}

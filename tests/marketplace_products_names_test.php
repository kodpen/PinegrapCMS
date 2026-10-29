<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketplace products screen (marketplace_products.php and
 * assets/js/marketplace_products.js): every row tick box is named after its
 * row, a redrawn row takes the focus back, a disabled pager arrow is not a
 * link, status text uses the emphasis inks and the step messages print
 * their counts in the panel's number format.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_products_names_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_products.php');
}

function marketplace_products_names_test_script()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/marketplace_products.js');
}

function marketplace_products_names_test_style()
{
	$source = marketplace_products_names_test_source();
	$start = strpos($source, '<style>');

	pg_assert_true($start !== false, 'the screen style block is there');

	return substr($source, $start, (int) strpos($source, '</style>', $start) - $start);
}

function test_marketplace_products_row_boxes_are_named_per_row()
{
	$source = marketplace_products_names_test_source();

	pg_assert_true(strpos($source, "aria-label=\"' . h(lang('Select')) . '\"") === false, 'no row box is named only Select');
	pg_assert_same(3, substr_count($source, "lang(array('string' => 'Select {var:1}', 'vars' => array("), 'the catalogue, shop and listing rows name their box');
	pg_assert_true(strpos($source, "'Select {var:1}', 'vars' => array(\$row['title'])") !== false, 'the catalogue row by its title');
	pg_assert_true(strpos($source, "'Select {var:1}', 'vars' => array(\$name)") !== false, 'the shop row by its name');
	pg_assert_true(strpos($source, "'Select {var:1}', 'vars' => array(\$title)") !== false, 'the listing card by its title');
}

function test_marketplace_products_disabled_pager_arrow_is_a_span()
{
	$source = marketplace_products_names_test_source();

	pg_assert_true(strpos($source, "' disabled' : '') . '\" href=") === false, 'no disabled arrow is a link');
	pg_assert_same(6, substr_count($source, '. mp_products_pager_arrow('), 'all three pagers draw their arrows through the helper');

	$start = strpos($source, 'function mp_products_pager_arrow(');
	$helper = substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start);

	pg_assert_true((bool) preg_match("/if \\(\\\$disabled\\) \\{\\s*return '<span class=\"[^\"]*disabled\"/", $helper), 'a disabled arrow is a span');
	pg_assert_true(strpos($helper, 'aria-hidden="true"') !== false, 'the glyph is hidden');
}

function test_marketplace_products_status_text_uses_emphasis_inks()
{
	$style = marketplace_products_names_test_style();

	foreach (array('.mpp-stat.good b', '.mpp-score.sure b, .mpp-score.matched', '.mpp-sure-tag') as $selector) {
		pg_assert_true((bool) preg_match('/' . preg_quote($selector, '/') . ' \{[^}]*color: var\(--pg-success-fg-emphasis\)/', $style), $selector . ' in the success ink');
	}

	foreach (array('.mpp-stat.bad b', '.mpp-err') as $selector) {
		pg_assert_true((bool) preg_match('/' . preg_quote($selector, '/') . ' \{[^}]*color: var\(--pg-danger-fg-emphasis\)/', $style), $selector . ' in the danger ink');
	}

	pg_assert_true(!preg_match('/[^-]color: var\(--pg-(success|danger)\);/', $style), 'no text drawn in a fill colour');
	pg_assert_true((bool) preg_match('/\.mpp-row\.is-dismissed \.mpp-side:not\(\.shop\) \{ color: var\(--pg-fg-2\); \}/', $style), 'a dismissed row uses the secondary colour');
	pg_assert_true(!preg_match('/is-dismissed[^{]*\{[^}]*opacity/', $style), 'not an opacity');
}

function test_marketplace_products_step_counts_are_formatted()
{
	$source = marketplace_products_names_test_source();

	foreach (array(
		"'vars' => array(\$done['matched'])",
		"'vars' => array(\$queued)",
		"'vars' => array(count(\$ids) - \$queued)",
		"'vars' => array(mp_matches_remove(",
		"'vars' => array(\$set['count'])",
		"'They go out on the next run.', 'vars' => array(\$done['queued'])",
	) as $raw) {
		pg_assert_true(strpos($source, $raw) === false, 'raw count: ' . $raw);
	}

	pg_assert_true(strpos($source, "'vars' => array(pg_format_number(\$queued, 0))") !== false, 'queued counts are formatted');
}

function test_marketplace_products_redrawn_row_keeps_the_focus()
{
	$script = marketplace_products_names_test_script();

	$start = strpos($script, 'function replaceRow(');
	$replace = substr($script, $start, (int) strpos($script, "\n    }\n", $start) - $start);

	pg_assert_true(strpos($replace, 'row.contains(active)') !== false, 'it notes whether the old row held the focus');
	pg_assert_true(strpos($replace, "getAttribute('data-mp-act')") !== false, 'and which action had it');
	pg_assert_true(strpos($replace, 'focusRow(fresh, act)') !== false, 'the fresh row takes it');
	pg_assert_true(strpos($script, "pick('[data-mp-act]') || pick('[data-mp-select]')") !== false, 'else its first action or its box');
	pg_assert_true((bool) preg_match("/cell\\.innerHTML = before;\\s*focusRow\\(row, 'code'\\);/", $script), 'Cancel in the code box gives the focus to the code button');
	pg_assert_true(strpos($script, "replaceRow(row, answer.rows[id], 'code')") !== false, 'so does Save');
	pg_assert_true((bool) preg_match('/Promise\.resolve\(pgUI\.dialog\.hide\(picker\.root\)\)\.then\(function \(\) \{\s*focusRow\(/', $script), 'the picker hands the focus to the fresh row once it has closed');
}

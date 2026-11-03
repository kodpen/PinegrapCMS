<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comment line of a custom look or palette stylesheet
 * (includes/fn/design_themes.php): the name the operator typed goes into a
 * CSS comment and must never close it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// What is left of $css outside its /* ... */ comments.
function design_themes_comment_test_outside($css)
{
	return preg_replace('~/\*.*?\*/~s', '', $css);
}

function test_design_themes_comment_palette_name_never_closes_the_comment()
{
	foreach (array('a **// b', 'a */ b', '*/*/', '***///', 'x*/', '*/') as $name) {
		$css = pg_design_palette_css('#2563eb', '#475569', $name);
		$outside = design_themes_comment_test_outside($css);
		pg_assert_same(0, strpos(ltrim($outside), ':root'), 'palette CSS starts with its rule for name ' . $name);
	}
}

function test_design_themes_comment_look_name_never_closes_the_comment()
{
	foreach (array('a **// b', 'a */ b', '*/*/', '***///') as $name) {
		$css = pg_design_custom_look_css('minimal', array(), $name);
		$outside = design_themes_comment_test_outside($css);
		pg_assert_same(0, strpos(ltrim($outside), ':root'), 'look CSS starts with its rule for name ' . $name);
	}
}

function test_design_themes_comment_plain_name_is_kept()
{
	pg_assert_contains('palette: Sea & Sand.', pg_design_palette_css('#2563eb', '#475569', 'Sea & Sand'));
	pg_assert_contains('look "My * look / one"', pg_design_custom_look_css('minimal', array(), 'My * look / one'));
}

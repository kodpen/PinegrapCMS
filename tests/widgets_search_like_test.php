<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the system widgets' searches (the form data list search and
 * the simple site search) build their LIKE pattern with
 * pg_list_like_pattern(): a typed backslash, % or _ is a plain character.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_widgets_search_like_no_escape_like_pattern()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_same(0, substr_count($source, 'escape_like('), 'widgets.php: no hand-built escape_like() pattern');
	pg_assert_same(2, substr_count($source, 'e(pg_list_like_pattern('), 'widgets.php: form data search and simple site search');
}

function test_widgets_search_like_backslash_is_plain()
{
	pg_assert_same('%C:\\\\temp%', pg_list_like_pattern('C:\\temp'), 'a backslash is escaped for LIKE');
	pg_assert_same('%50\\%%', pg_list_like_pattern('50%'), 'a % is plain');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the theme reload screen's query string (reload_theme.php): a
 * name sent as an array (?name[]=x) must not reach h(), which throws a
 * TypeError on PHP 8 and turns the screen, which needs no sign-in, into a
 * 500 page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_reload_theme_name_is_read_once_as_a_scalar()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/reload_theme.php');

	$read = "\$theme_file_name = is_scalar(\$_GET['name'] ?? null) ? (string) \$_GET['name'] : '';";

	pg_assert_contains($read, $source, 'an array name counts as not given');
	pg_assert_same(0, substr_count(str_replace($read, '', $source), "\$_GET['name']"), 'no other line reads name from the query string');
}

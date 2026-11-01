<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the system theme CSS write on Add Theme File
 * (add_theme_file.php): the generated CSS is written as a whole and a failed
 * write removes the theme's record and rules and sends the user back, so no
 * record names a file that is not on the disk.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_theme_system_css_write_checked()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_theme_file.php');

	pg_assert_same(false, strpos($source, "fopen(FILE_DIRECTORY_PATH . '/' . \$file_name, 'w')"), 'the CSS is not written through an unchecked fopen()');

	$write = strpos($source, "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$file_name, generate_system_theme_css(\$css_properties))) {");
	pg_assert_same(true, $write !== false, 'the CSS write result is read');

	$tail = ($write !== false) ? substr($source, $write, 900) : '';
	pg_assert_contains("DELETE FROM system_theme_css_rules WHERE file_id = ?", $tail, 'a failed write removes the theme rules');
	pg_assert_contains("DELETE FROM files WHERE id = ?", $tail, 'a failed write removes the theme record');
	pg_assert_contains("lang(array('string' => '{var:1} could not be written to disk.', 'vars' => h(\$file_name)))", $tail, 'a failed write says so');
}

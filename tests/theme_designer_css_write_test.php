<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the CSS writes of the Theme Designer (theme_designer.php): Save
 * no longer deletes the stored file before writing, both Save and Duplicate
 * read the write result, and a failed Duplicate leaves no record behind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_theme_designer_css_writes_checked()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/theme_designer.php');

	pg_assert_same(false, strpos($source, "unlink(FILE_DIRECTORY_PATH . '/' . \$file_name);"), 'Save does not delete the stored file before writing');
	pg_assert_same(false, strpos($source, "fopen(FILE_DIRECTORY_PATH"), 'no css file is written through an unchecked fopen()');

	pg_assert_contains("if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$file_name, \$_SESSION['software']['theme_designer'][\$_POST['id']]['code'])) {\n            theme_designer_write_failed(", $source, 'Save reads the write result');
	pg_assert_contains("if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$new_file_name, \$_SESSION['software']['theme_designer'][\$_POST['id']]['code'])) {\n            db_q(\"DELETE FROM files WHERE id = ?\", array((string) \$new_theme_id));", $source, 'a failed Duplicate removes the new record');
	pg_assert_contains("\$liveform->add_error(lang(array('string' => '{var:1} could not be written to disk.', 'vars' => h(\$file_name))));", $source, 'a failed write says so on the designer');
}

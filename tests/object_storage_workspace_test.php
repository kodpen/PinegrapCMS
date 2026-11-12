<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace and ERP files are files rows the move job sends to the object
 * store like any other: every place in those modules that names a path in
 * the file directory must be a writer (a new file, a save in place, a
 * discard) or a list that fetches nothing. A reader goes through
 * pg_file_reader_path() or pg_file_local_copy().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The lines allowed to build a path in the file directory, by file: each
// writes there, or lists without reading.
function object_storage_workspace_test_allowed()
{
	return array(
		'includes/workspace/files.php'   => array(
			"\$stored_path = FILE_DIRECTORY_PATH . '/' . \$stored_name;",
			"@unlink(FILE_DIRECTORY_PATH . '/' . basename(\$stored));",
		),
		'includes/workspace/canvas.php'  => array("\$path = FILE_DIRECTORY_PATH . '/' . \$name;"),
		// A new version of a live page written over its file, as the canvas.
		'includes/workspace/page.php'    => array("\$path = FILE_DIRECTORY_PATH . '/' . \$name;"),
		'includes/workspace/notes.php'   => array("\$path = FILE_DIRECTORY_PATH . '/' . \$stored_name;"),
		'includes/workspace/changes.php' => array("\$path = FILE_DIRECTORY_PATH . '/' . \$record['name'];"),
		'includes/erp/archive.php'       => array("\$rows[\$key]['path'] = FILE_DIRECTORY_PATH . '/' . \$row['name'];"),
	);
}

function test_object_storage_workspace_readers_use_the_copy()
{
	$allowed = object_storage_workspace_test_allowed();
	$files = array_merge(
		glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php'),
		glob(PG_FUNCTIONS_DIR . '/includes/erp/*.php'),
		glob(PG_FUNCTIONS_DIR . '/workspace*.php'),
		glob(PG_FUNCTIONS_DIR . '/*erp*.php')
	);

	pg_assert_true(count($files) > 10, 'the modules are found');

	foreach ($files as $file) {
		$relative = substr($file, strlen(PG_FUNCTIONS_DIR) + 1);

		foreach (explode("\n", (string) file_get_contents($file)) as $number => $line) {
			if (strpos($line, "FILE_DIRECTORY_PATH . '/'") === false) {
				continue;
			}

			$ok = isset($allowed[$relative]) && in_array(trim($line), $allowed[$relative], true);
			pg_assert_true($ok, $relative . ':' . ($number + 1) . ' reads the file directory directly: ' . trim($line));
		}
	}
}

function test_object_storage_workspace_archive_counts_stored_rows()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/archive.php');

	pg_assert_contains("pg_file_local_copy((int) \$row['id'])", $source, 'a kept document is read through the copy');
	pg_assert_contains("(pg_file_storage(\$row) === 's3')", $source, 'a stored receipt is listed as kept');
}

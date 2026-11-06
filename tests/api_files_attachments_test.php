<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the External API files resource (includes/api/resources/files.php):
 * the list and the single read name only files the site serves to anyone. A
 * file uploaded with a submitted form or a comment (files.attachment = 1) sits
 * in the field's upload folder, which may be public, yet get_file.php opens it
 * only to people who may see its record; the API must not list it either.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/files.php');

// The shared condition keeps the public-folder rule and leaves attachments out.
function test_api_files_public_clause_leaves_out_attachments()
{
	$clause = api_files_public_clause();

	pg_assert_contains("folder.folder_access_control_type = 'public'", $clause, 'public folders only');
	pg_assert_contains("files.attachment = '0'", $clause, 'form and comment uploads left out');
}

// The list, its count and the single read all go through that condition.
function test_api_files_reads_use_the_public_clause()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/files.php');

	foreach (array('api_files_list', 'api_files_get') as $function) {
		$start = strpos($source, 'function ' . $function . '(');
		pg_assert_true($start !== false, $function . ' exists');

		$end = strpos($source, "\nfunction ", $start + 10);
		$body = substr($source, $start, ($end === false ? strlen($source) : $end) - $start);

		pg_assert_contains('api_files_public_clause()', $body, $function . ' uses the public clause');
	}
}

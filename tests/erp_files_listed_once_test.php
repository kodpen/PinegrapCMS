<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for erp_files_list() in includes/erp/files.php: an invoice,
 * delivery note, e-document or letter with more than one kept files row is
 * joined to its newest kept copy only, so it is listed once and the folder
 * total matches erp_files_count().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of erp_files_list(), up to the next function.
function erp_files_listed_once_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/files.php');
	$start = strpos($source, 'function erp_files_list(');
	pg_assert_true($start !== false, 'erp_files_list() exists');
	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_erp_files_list_joins_only_the_newest_kept_copy()
{
	$body = erp_files_listed_once_test_body();

	pg_assert_contains("LEFT JOIN files kf ON kf.id = (SELECT MAX(f2.id) FROM files f2 WHERE f2.erp_doc_type = '", $body, 'joins the newest kept row');
	pg_assert_contains("' AND f2.erp_doc_id = \" . \$alias . \".id)", $body, 'by the document id');
}

function test_erp_files_list_does_not_join_every_kept_copy()
{
	$body = erp_files_listed_once_test_body();

	pg_assert_false(strpos($body, 'ON kf.erp_doc_type =') !== false, 'no join over every kept row of a document');
}

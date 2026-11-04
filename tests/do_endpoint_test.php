<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for do.php: every parameter is read as one text value, so a
 * missing action or an array (?field_name[]=x, ?page_id[]=1) neither stops
 * the request nor lands in the session for a later screen to trip over.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_do_endpoint_reads_single_text_values()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/do.php');

	pg_assert_contains("pg_request_single_values(array('action', 'watcher', 'field_name', 'field_value', 'page_id', 'url'));", $source, 'arrays turned away');
	pg_assert_contains("switch (\$_GET['action'] ?? '')", $source, 'a missing action is no warning');
	pg_assert_contains("trim(\$_GET['field_name'] ?? '')", $source, 'field name read as text');
	pg_assert_contains("trim(\$_GET['field_value'] ?? '')", $source, 'field value read as text');
	pg_assert_contains("(int) (\$_GET['page_id'] ?? 0)", $source, 'start page read as a number');
	pg_assert_contains("(string) (\$_GET['watcher'] ?? '')", $source, 'watcher read as text');
}

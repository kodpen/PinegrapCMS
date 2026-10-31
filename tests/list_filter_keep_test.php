<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a list filter select carries along the other narrowing on its
 * screen: on ERP > Cheques and notes the Status and Direction filters keep
 * the toolbar search, and on ERP > Invoices the Due date filter keeps the
 * e-Document view the register combines it with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The names in the 'keep' array of the pg_filter_select() call for $name in
// $file, or null when the call or its keep array is not found.
function list_filter_keep_test_names($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "pg_filter_select('" . $name . "'");
	if ($start === false) {
		return null;
	}

	// A call takes at most two lines: the options on the first, the opts on
	// the first or the second.
	$lines = explode("\n", substr($source, $start), 3);
	$call = $lines[0] . "\n" . (isset($lines[1]) ? $lines[1] : '');
	if (!preg_match("/'keep'\\s*=>\\s*array\\(([^)]*)/", $call, $match)) {
		return null;
	}

	preg_match_all("/'([^']*)'/", $match[1], $names);
	return $names[1];
}

function test_list_filter_keep_cheque_filters_keep_the_search()
{
	pg_assert_same(array('direction', 'search'), list_filter_keep_test_names('erp_cheques.php', 'status'), 'Status filter keeps the direction and the search');
	pg_assert_same(array('status', 'search'), list_filter_keep_test_names('erp_cheques.php', 'direction'), 'Direction filter keeps the status and the search');
}

function test_list_filter_keep_invoice_due_date_keeps_the_edocument_view()
{
	pg_assert_same(array('edoc'), list_filter_keep_test_names('erp_invoices.php', 'filter'), 'Due date filter keeps the e-Document view');
}

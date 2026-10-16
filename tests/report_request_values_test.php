<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the report screens and bad values from the request. The order
 * and visitor report forms passed every posted field to escape(), so a field
 * sent as an array (name[]=x) ended in an error page, and an array filter
 * count (last_filter_number[]=x) kept the filter loop running until the time
 * limit. A date part sent as an array was read as 1, a valid year, so the
 * report range jumped to the year 0001 and stayed in the session. The visitor
 * screen printed an array visitor_report_id with h(). The shipping report
 * stored any date or status from the address in the session, where a bad one
 * broke every later visit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The POST branch drops every array field before liveform copies the request
// into the session, so no array reaches escape() or the filter loop.
function test_report_request_values_post_fields_single()
{
	foreach (array('view_order_report', 'view_visitor_report') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
		$post_branch = strpos($source, 'validate_token_field();');
		$call = strpos($source, 'pg_request_single_values(array_keys($_GET + $_POST));', (int) $post_branch);
		$session = strpos($source, '$liveform->add_fields_to_session();', (int) $post_branch);

		pg_assert_true($post_branch !== false, $screen . ': no POST branch');
		pg_assert_true($call !== false, $screen . ': POST fields not limited to one value');
		pg_assert_true($session !== false && $call < $session, $screen . ': fields reach the session before the check');
	}
}

// The date changer reads each part only when it is one value; an array part
// is 0, checkdate() fails and the previous range stays.
function test_report_request_values_date_parts()
{
	foreach (array('view_order_report', 'view_visitor_report') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		pg_assert_true(strpos($source, 'is_scalar($_GET[$date_part] ?? null) ? (int) $_GET[$date_part] : 0') !== false, $screen . ': date part not checked');
		pg_assert_false(strpos($source, '(int) ($_GET[$date_part] ?? 0)') !== false, $screen . ': array date part read as 1');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor_report.php');
	pg_assert_true(strpos($source, "is_scalar(\$_GET['detail_page'] ?? null)") !== false, 'view_visitor_report: detail_page not checked');
}

function test_report_request_values_visitor_report_link()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor.php');
	$check = strpos($source, "is_scalar(\$_GET['visitor_report_id'] ?? null)");
	$output = strpos($source, "h(\$_GET['visitor_report_id'])");

	pg_assert_true($check !== false, 'view_visitor: visitor_report_id not checked');
	pg_assert_true($output !== false && $check < $output, 'view_visitor: visitor_report_id printed before the check');
}

// Only a real Y-m-d date and one of the three statuses reach the session.
function test_report_request_values_shipping_report_filters()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_shipping_report.php');
	$store = strpos($source, "\$_SESSION['software']['ecommerce']['view_shipping_report'][\$key] = \$value;");

	pg_assert_true($store !== false, 'view_shipping_report: request values not stored');

	$loop = substr($source, 0, (int) $store);
	pg_assert_true(strpos($loop, "in_array(\$value, array('any', 'unshipped', 'shipped'), true)") !== false, 'view_shipping_report: status not checked');
	pg_assert_true(strpos($loop, 'validate_date($value)') !== false, 'view_shipping_report: date not checked');
}

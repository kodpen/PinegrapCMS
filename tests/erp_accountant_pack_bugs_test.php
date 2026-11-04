<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Accountant pack and audit trail: a pack with till sheets is kept from a
 * user without the cash right, building and downloading a pack leave an
 * audit line, a download by link is not shown as a scheduled job, an empty
 * lock date is refused, the size of a small pack is not "0.0 MB", the period
 * is written for people, and long words break inside the audit table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accountant.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/audit.php');

function erp_accountant_pack_bugs_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The summary says whether the till sheets went in; a row from before the
// flag counts as carrying them.
function test_erp_accountant_package_has_cash()
{
	pg_assert_true(erp_accountant_package_has_cash(array('summary' => json_encode(array('with_cash' => true)))), 'flag on');
	pg_assert_false(erp_accountant_package_has_cash(array('summary' => json_encode(array('with_cash' => false)))), 'flag off');
	pg_assert_true(erp_accountant_package_has_cash(array('summary' => json_encode(array('output_vat' => 5)))), 'old row without the flag');
	pg_assert_true(erp_accountant_package_has_cash(array('summary' => 'not json')), 'unreadable summary');
	pg_assert_true(erp_accountant_package_has_cash(array()), 'no summary');
}

function test_erp_accountant_size_text()
{
	$decimal = erp_number_separators()['decimal'];

	pg_assert_same('9 KB', erp_accountant_size_text(8833));
	pg_assert_same('13 KB', erp_accountant_size_text(12544));
	pg_assert_same('1 KB', erp_accountant_size_text(10));
	pg_assert_same('0 KB', erp_accountant_size_text(0));
	pg_assert_same('1' . $decimal . '5 MB', erp_accountant_size_text(1572864));
}

// A request with no user that is not a background run came through a link.
function test_erp_audit_actor_kind()
{
	pg_assert_same('job', erp_audit_actor_kind(array('source' => 'job', 'user_id' => 0, 'username' => '')));
	pg_assert_same('api', erp_audit_actor_kind(array('source' => 'api', 'user_id' => 3, 'username' => 'App')));
	pg_assert_same('link', erp_audit_actor_kind(array('source' => 'panel', 'user_id' => 0, 'username' => '')));
	pg_assert_same('panel', erp_audit_actor_kind(array('source' => 'panel', 'user_id' => 4, 'username' => 'admin')));

	$audit = erp_accountant_pack_bugs_source('includes/erp/audit.php');
	pg_assert_contains("pg_cron_is_background_run()", $audit, 'only a background run is a job');
	pg_assert_contains("source = 'panel' AND user_id = 0 AND username = ''", $audit, 'a filter for link rows');
}

function test_erp_accountant_screen_guards()
{
	$screen = erp_accountant_pack_bugs_source('erp_accountant.php');

	pg_assert_true(substr_count($screen, 'erp_accountant_package_has_cash(') >= 4, 'download, link, send and the rows ask about the till sheets');
	pg_assert_contains("'with_cash'", erp_accountant_pack_bugs_source('includes/erp/accountant.php'), 'the build records the flag');
	pg_assert_contains("'Accountant pack for {var:1} built", $screen, 'building is logged');
	pg_assert_contains("'Accountant pack for {var:1} downloaded.'", $screen, 'a panel download is logged');
	pg_assert_contains("(\$action === 'lock') && (trim((string) (\$_POST['lock_date'] ?? '')) === '')", $screen, 'an empty lock date is refused');
	pg_assert_contains('erp_profit_period_title(', $screen, 'the period is written for people');
	pg_assert_false(strpos($screen, "/ 1048576") !== false, 'no bare MB division');
	pg_assert_contains("check_field_error('accountant_email')", $screen, 'the refused address is marked');
	pg_assert_contains('range_dates_fields', $screen, 'touching the dates picks the date range');

	$download = erp_accountant_pack_bugs_source('erp_accountant_download.php');
	pg_assert_false(strpos($download, "), 'ERP');") !== false, 'the link download is not logged as the ERP user');
}

function test_erp_audit_long_words_break()
{
	$screen = erp_accountant_pack_bugs_source('erp_audit.php');
	pg_assert_contains('text-break', $screen, 'long words break inside a cell');
}

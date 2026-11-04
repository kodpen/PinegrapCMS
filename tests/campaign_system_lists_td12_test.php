<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The campaign, report and system lists (e-mail and SMS campaigns, visitor
 * reports, the 404 log, backups, Cloudflare DNS records, the database
 * tables) on the list table driver, printed whole in browser mode; the gift
 * card redemption history as a plain ledger table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';

function campaign_system_lists_td12_files()
{
	return array(
		'view_email_campaigns' => 'view_email_campaigns.php',
		'view_email_campaign_history' => 'view_email_campaign_history.php',
		'view_email_campaign_profiles' => 'view_email_campaign_profiles.php',
		'view_sms_campaigns' => 'view_sms_campaigns.php',
		'view_visitor_reports' => 'view_visitor_reports.php',
		'view_not_found_log' => 'view_not_found_log.php',
		'backups' => 'backups.php',
		'cloudflare_dns' => 'cloudflare.php',
		'database_engine' => 'database_engine.php',
	);
}

function test_campaign_system_lists_td12_printed()
{
	foreach (campaign_system_lists_td12_files() as $list => $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list . ' keeps a heading sort');
		pg_assert_false(strpos($source, 'class="chart') !== false, $file . ': no DataTables table left');
		pg_assert_false(strpos($source, 'table chart') !== false, $file . ': no DataTables table left');
		pg_assert_false(strpos($source, 'get_column_heading(') !== false, $file . ': no heading links left');
		pg_assert_contains("pg_list_state('" . $list . "'", $source, $file . ': sort kept under its list name');
		pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $source, $file . ': driver loaded');

		if ($list !== 'view_not_found_log') {
			// The 404 log keeps its server search, which also finds the
			// addresses past the rows read.
			pg_assert_contains("pg_list_table_search_box('" . $list . "')", $source, $file . ': search box');
		}
	}
}

function test_campaign_system_lists_td12_email_campaigns_share_module()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_list.php');

	foreach (array('view_email_campaigns.php', 'view_email_campaign_history.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("includes/fn/email_campaign_list.php", $source, $file);
		pg_assert_contains('pg_email_campaign_list_delete_button()', $source, $file . ': Delete Selected in the bar');
		pg_assert_contains('delete_email_campaigns.php', $source, $file . ': same bulk endpoint');
	}

	pg_assert_contains("'ids_field' => 'email_campaigns[]'", $module, 'the endpoint reads the same ids');
	pg_assert_contains('data-pg-open', $module, 'the subject opens the campaign');
}

function test_campaign_system_lists_td12_sort_codes_are_columns()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_list.php';

	foreach (array(true, false) as $job) {
		$columns = pg_email_campaign_list_columns($job);

		foreach (array_keys(pg_email_campaign_list_sort_columns($job)) as $code) {
			pg_assert_true(isset($columns[$code]), 'sort code ' . $code . ' is a column');
		}

		pg_assert_same($job, isset($columns['scheduled_time']), 'Scheduled Time only with the job on');
		pg_assert_same('subject', array_keys($columns)[0], 'the subject is the name column');

		foreach (array('to', 'progress', 'delivery') as $key) {
			pg_assert_false(isset(pg_email_campaign_list_sort_columns($job)[$key]), $key . ' has no sort code');
			pg_assert_true(($columns[$key]['sortable'] ?? true) === false, $key . ' does not sort');
		}
	}

	pg_assert_same(array('sort' => 'scheduled_time', 'order' => 'asc'), pg_email_campaign_list_sort_default(true));
	pg_assert_same(array('sort' => 'last_modified', 'order' => 'desc'), pg_email_campaign_list_sort_default(false));
}

function test_campaign_system_lists_td12_no_form_in_rows()
{
	$cloudflare = (string) file_get_contents(PG_FUNCTIONS_DIR . '/cloudflare.php');

	pg_assert_contains('id="pg-cf-delete-form"', $cloudflare, 'one delete form below the list');
	pg_assert_false(strpos($cloudflare, '<form method="post" style="display:inline"') !== false, 'no form inside a DNS row');
}

function test_campaign_system_lists_td12_gift_card_ledger()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');

	pg_assert_false(strpos($source, 'class="chart') !== false, 'no DataTables table left');
	pg_assert_contains('<table class="table table-hover mb-0">', $source, 'redemption history is a plain table');
	pg_assert_false(strpos($source, 'data-pg-sort') !== false, 'a ledger does not sort');
}

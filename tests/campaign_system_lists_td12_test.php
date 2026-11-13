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
		'view_email_campaign_profiles' => 'view_email_campaign_profiles.php',
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
	// The Campaigns tab lists e-mail and SMS campaigns together and deletes
	// both through the shared delete functions; delete_email_campaigns.php
	// keeps its address and calls the same e-mail one.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_email_campaigns.php');
	$merged = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/messages_campaigns.php');

	pg_assert_contains('pg_email_campaign_list_delete_button()', $source, 'Delete Selected in the bar');
	pg_assert_contains('pg_email_campaigns_delete(', $source, 'e-mail campaigns deleted by the shared function');
	pg_assert_contains('pg_sms_campaigns_delete(', $source, 'SMS campaigns deleted by the shared function');
	pg_assert_contains('pg_email_campaigns_delete(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_email_campaigns.php'), 'the old endpoint deletes the same way');
	pg_assert_contains("'ids_field' => 'campaigns[]'", $merged, 'the screen reads the merged ids');
	pg_assert_contains('data-pg-open', $merged, 'the name opens the campaign');
}

function test_campaign_system_lists_td12_sort_codes_are_columns()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_campaigns.php';

	$columns = pg_messages_campaign_columns();
	pg_assert_same('name', array_keys($columns)[0], 'the name is the first column');
	pg_assert_false(isset(pg_list_table_printed_sort_columns($columns)['result']), 'Result does not sort');
	pg_assert_true(isset(pg_list_table_printed_sort_columns($columns)['last_modified']), 'the default sort is a column');
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

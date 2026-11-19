<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's page widths in backend.src.css: the measures
 * are tokens with the decided values, an add or edit screen and a list take
 * them through their body class, and the first two screens carry it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_page_widths_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function look_page_widths_test_block()
{
	$css = look_page_widths_test_source('assets/css/backend.src.css');
	$start = strpos($css, 'Look A: page widths.');

	return $start === false ? '' : substr($css, $start);
}

function test_page_width_tokens_hold_the_decided_measures()
{
	$block = look_page_widths_test_block();

	foreach (array('--pg-measure-edit: 1700px;', '--pg-measure-form: 1300px;', '--pg-measure-status: 376px;', '--pg-measure-gap: 24px;', '--pg-measure-list-min: 1700px;', '--pg-measure-details: 20rem;') as $token) {
		pg_assert_contains($token, $block, $token);
	}
}

function test_page_width_rules_read_the_tokens()
{
	$block = look_page_widths_test_block();

	pg_assert_contains('body.pg-measure-edit #content {', $block, 'an edit screen holds its content box');
	pg_assert_contains('grid-template-columns: minmax(0, var(--pg-measure-form)) minmax(0, var(--pg-measure-status));', $block, 'form and status columns from the tokens');
	pg_assert_contains('.pg-edit-columns > .order-3 {', $block, 'the save bar spans both columns');
	pg_assert_contains('min-width: min(100%, max(var(--pg-list-held, 0px), var(--pg-measure-list-min) + 2rem));', $block, 'a list is at least the list measure (or its held width) or the window');
	pg_assert_contains('body.pg-measure-list #content:has(.pg-list-table-details:not([hidden])) {', $block, 'an open details panel widens the list');
	pg_assert_contains('min-width: min(100%, max(var(--pg-list-held, 0px), var(--pg-measure-list-min) + 2rem) + var(--pg-measure-details));', $block, 'the panel is added to the closed width, up to the window');
	pg_assert_contains('flex: 0 0 var(--pg-measure-details);', look_page_widths_test_source('assets/css/backend.src.css'), 'the panel takes its width from the token');
	pg_assert_contains('body.pg-measure-list .pg-list-table-grid > thead > tr > th:first-child {', $block, 'the name column takes the slack');
}

function test_first_screens_take_the_measures()
{
	$builder = look_page_widths_test_source('product_builder.php');

	pg_assert_contains("'extra classes' => 'products pg-measure-edit',", $builder, 'the product screen is an edit screen');
	pg_assert_contains('<div class="row pg-edit-columns">', $builder, 'its column row');
	pg_assert_contains("'extra classes'=>'products pg-measure-list',", look_page_widths_test_source('view_products.php'), 'the products list is a list');
}

function test_lists_off_the_table_driver_take_the_list_measure()
{
	$screens = array('calendars', 'view_menu_items', 'view_sessions', 'view_performance_log', 'view_accessibility', 'backups', 'translations', 'marketplace_products', 'erp_purchase_orders', 'erp_quotes', 'erp_expenses', 'erp_expense_categories', 'erp_cheques', 'erp_bank_statements', 'erp_stock_counts', 'erp_invoice_recurrences', 'erp_audit');

	foreach ($screens as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-list\\b/", look_page_widths_test_source($screen . '.php')), $screen . ' is a list');
	}
}

function test_reports_take_the_report_measure()
{
	$screens = array('erp_dashboard', 'erp_aging', 'erp_cashflow', 'erp_profit', 'search_console', 'search_audits');

	foreach ($screens as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-report\\b/", look_page_widths_test_source($screen . '.php')), $screen . ' is a report');
	}
	pg_assert_true(substr_count(look_page_widths_test_source('erp_cashflow.php'), 'pg-measure-report') === 2, 'the cash flow and the cash forecast are reports');
}

function test_settings_tool_and_import_pages_take_the_edit_measure()
{
	$two = array('api_settings', 'marketplace_settings', 'erp_settings', 'connections', 'database_engine', 'clean_up', 'page_experiments', 'create_file', 'find_and_replace', 'mass_edit', 'privacy_erase', 'privacy_export', 'barcode_increase_inventory', 'barcode_decrease_inventory', 'import', 'import_contacts', 'import_email_campaign_profiles', 'import_key_codes', 'import_products', 'import_shipments', 'import_short_links', 'import_submitted_forms', 'import_zip', 'erp_export', 'erp_accounts_import', 'erp_account_sync_item', 'erp_bank_statement', 'erp_inbox_document', 'erp_reconciliation', 'erp_stock_count');
	$one = array('connected_apps', 'device_pairing', 'developer_lock', 'system_tools', 'migration', 'experiments', 'api_docs', 'import_users', 'erp_accountant', 'approve_affiliate');

	foreach (array_merge($two, $one) as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-edit\\b/", look_page_widths_test_source($screen . '.php')), $screen . ' takes the edit measure');
	}
	foreach ($one as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-single\\b/", look_page_widths_test_source($screen . '.php')), $screen . ' holds its form at the form column');
	}
	foreach ($two as $screen) {
		pg_assert_true(strpos(look_page_widths_test_source($screen . '.php'), 'pg-measure-single') === false, $screen . ' keeps its status column');
	}
	pg_assert_contains("body.pg-measure-edit:has(#pg-chat-root) .row > .order-3 > nav.buttons.navigation > .container {\n    margin-inline-end: var(--pg-chat-clearance);", look_page_widths_test_source('assets/css/backend.src.css'), 'the save bar buttons end before the chat launcher');
	pg_assert_contains("body.pg-measure-edit.pg-measure-single #content {\n  max-width: calc(var(--pg-measure-form) + 2rem);", look_page_widths_test_block(), 'a one-column page is the form column wide');
}

function test_communication_tab_strip_sits_centred_in_the_window()
{
	$css = look_page_widths_test_source('assets/css/backend.src.css');

	pg_assert_contains(".pg-area-tabs {\n  display: flex;", $css, 'the strip is a flex row');
	pg_assert_contains(".pg-area-tabs > .pg-seg {\n  margin-inline: auto;\n}", $css, 'the segmented control is centred in it, still scrollable from its first tab');
	pg_assert_contains('<nav class="pg-area-tabs pg-messages-tabs"', look_page_widths_test_source('includes/fn/messages_area.php'), 'Communication takes the shared class');
	pg_assert_contains("  .pg-messages-tabs .pg-seg {\n    display: flex;\n    flex: 1 1 auto;", $css, 'on a phone the strip fills the line as before');
	pg_assert_contains("body.pg-measure-list #content {\n  width: fit-content;", $css, 'a list box is fit-content');
	pg_assert_contains("  max-width: 100%;\n  margin-inline: auto;\n}", $css, 'and centred, so its centre is the window\'s');

	// Every screen printing the strip has a #content centred in the window:
	// the full width, or the list measure (fit-content, auto margins). An edit
	// measure with a section rail would shift the centre, so none is allowed.
	$screens = array('messages_mail', 'view_email_campaigns', 'messages_sends', 'view_email_campaign_profiles');

	foreach ($screens as $screen) {
		$source = look_page_widths_test_source($screen . '.php');
		pg_assert_contains('pg_messages_tab_strip_for(', $source, $screen . ' prints the strip');
		pg_assert_true(strpos($source, 'pg-measure-edit') === false && strpos($source, 'pg-has-rail') === false, $screen . ' keeps its content box centred');
	}
	pg_assert_true(strpos(look_page_widths_test_source('includes/fn/messages_area.php'), 'pg-measure-') === false, 'a new tab screen fills the window');
}

function test_area_tab_strips_share_one_class()
{
	$css = look_page_widths_test_source('assets/css/backend.src.css');

	// The place is the shared class's alone: no area strip centres itself.
	pg_assert_true(strpos($css, '.pg-messages-tabs > .pg-seg {') === false, 'Communication has no rule of its own for the place');
	pg_assert_true(preg_match('/\.pg-area-tabs[^{]*\{[^}]*justify-content:\s*center/', $css) === 0, 'no centring that cuts off a scrolled strip\'s start');

	// The marketplace products screen's views (the store area): the strip,
	// then the messages, on a list box centred in the window.
	$source = look_page_widths_test_source('marketplace_products.php');
	pg_assert_contains('<nav class="pg-area-tabs mpp-modes" aria-label="\' . h(lang(\'Display\')) . \'"><div class="pg-seg pg-list-seg">', $source, 'the store views take the shared class');
	pg_assert_true(strpos($source, '.mpp-modes { margin-bottom: 14px; }') !== false, 'the screen only spaces its strip');
	$strip = strpos($source, '<nav class="pg-area-tabs mpp-modes"');
	$flash = strpos($source, '<div id="mpp-flash"');
	pg_assert_true($strip !== false && $flash !== false && $strip < $flash, 'the messages come under the strip');
	pg_assert_true(strpos(substr($source, strpos($source, '<main id="content"'), $strip - strpos($source, '<main id="content"')), 'alert') === false, 'nothing is printed above the strip');
	pg_assert_contains("'extra classes'       => 'mp-screen pg-measure-list'", $source, 'a list box, centred');
	pg_assert_true(strpos($source, 'pg-measure-edit') === false && strpos($source, 'pg-has-rail') === false, 'no edit measure or rail shifts the centre');
}

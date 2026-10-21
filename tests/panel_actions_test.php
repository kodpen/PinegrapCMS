<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel action table in includes/panel/actions.php: every row
 * points at a handler that exists, its flags agree with the handler's source,
 * the general gate built from the table lets through exactly the actions it
 * always did, and api.php carries no switch of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

function pg_test_panel_api_source()
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/api.php');
}

function pg_test_panel_function_source($function_name)
{
	$function = new ReflectionFunction($function_name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The named actions the general gate lets through to their own checks. This
// is the list api.php carried as `($action != '...')` terms until the gate
// was built from the table; a change here changes who reaches a handler, so
// it is made on purpose, in both places. The chat_, site_chat_ and ws_
// families are exempt as a whole and checked apart.
function pg_test_panel_exempt_names()
{
	return array(
		'add_to_cart', 'ai_write', 'backend_search', 'bulk_assign_barcodes',
		'check_unread_notifications', 'contact_quick_save',
		'database_deep_check', 'delete_product_barcode', 'designer',
		'designer_file', 'edit_notifications', 'eo_default_tree',
		'eo_get_installments', 'eo_required_sections', 'erp_refund_preview',
		'file_explorer',
		'generate_product_barcode', 'get_cross_sell_for_product',
		'get_cross_sell_items', 'get_dashboard_brief', 'get_delivery_date',
		'get_installment_options', 'get_notifications', 'get_product',
		'get_product_barcodes', 'get_shipping_methods',
		'get_unselected_products', 'get_widget_data', 'get_widgets_data',
		'offer_editor',
		'purge_cache', 'push_config', 'push_pending', 'push_subscribe',
		'push_test', 'push_unsubscribe', 'remove_notifications',
		'save_barcode_template', 'save_product_barcode',
		'server_config_repair', 'shared_component', 'sitemap_check',
		'software_backup', 'software_update', 'software_update_check',
		'sort_menu_items', 'tour_seen', 'update_dashboard_widgets',
		'update_product_group_status', 'update_product_status',
		'update_toolbar_properties', 'upload_file', 'user_online_check',
		'user_pinned_app_update', 'write_permissions_repair',
	);
}

function pg_test_panel_family($action)
{
	foreach (pg_panel_exempt_prefixes() as $prefix) {
		if (strpos($action, $prefix) === 0) {
			return $prefix;
		}
	}

	return '';
}

// Each row names a file under includes/panel/ that defines its handler.
function test_panel_actions_every_row_has_file_and_handler()
{
	foreach (pg_panel_actions() as $action => $entry) {
		$file = PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file'];

		if (!pg_assert_true(is_file($file), 'file ' . $action)) {
			continue;
		}

		require_once($file);

		pg_assert_true(function_exists($entry['handler']), 'handler ' . $action);
	}
}

// exempt, token and write - and any_role and public where a row has them -
// are booleans, never truthy strings.
function test_panel_actions_flags_are_booleans()
{
	foreach (pg_panel_actions() as $action => $entry) {
		foreach (array('exempt', 'token', 'write') as $flag) {
			pg_assert_true(isset($entry[$flag]) && is_bool($entry[$flag]), $action . ' ' . $flag);
		}

		foreach (array('any_role', 'public') as $flag) {
			if (array_key_exists($flag, $entry)) {
				pg_assert_true(is_bool($entry[$flag]), $action . ' ' . $flag);
			}
		}
	}
}

// A row that says token => true has a handler that calls validate_token().
// A row with token_by hands the request to that function, which calls it.
function test_panel_actions_token_flag_matches_handler_source()
{
	foreach (pg_panel_actions() as $action => $entry) {
		require_once(PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file']);

		if (!function_exists($entry['handler'])) {
			continue;
		}

		$source = pg_test_panel_function_source($entry['handler']);

		pg_assert_same($entry['token'], strpos($source, 'validate_token(') !== false, $action);
	}
}

function test_panel_actions_token_by_calls_validate_token()
{
	require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');

	foreach (pg_panel_actions() as $action => $entry) {
		if (!isset($entry['token_by'])) {
			continue;
		}

		require_once(PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file']);

		if (!pg_assert_true(function_exists($entry['token_by']), $action . ' token_by exists')) {
			continue;
		}

		pg_assert_contains($entry['token_by'] . '(', pg_test_panel_function_source($entry['handler']), $action . ' handler');
		pg_assert_contains('validate_token(', pg_test_panel_function_source($entry['token_by']), $action . ' token_by');
	}
}

// Nothing the general gate waves through may write without the form token,
// unless it is a visitor endpoint that has none to hold.
function test_panel_actions_exempt_writers_check_the_token()
{
	foreach (pg_panel_actions() as $action => $entry) {
		if ($entry['write'] && $entry['exempt'] && empty($entry['public'])) {
			pg_assert_true($entry['token'] || isset($entry['token_by']), $action);
		}
	}
}

// A visitor endpoint is exempt: the general gate would ask it for a login.
function test_panel_actions_public_rows_are_exempt()
{
	foreach (pg_panel_actions() as $action => $entry) {
		if (!empty($entry['public'])) {
			pg_assert_true($entry['exempt'], $action);
		}
	}
}

// api.php keeps no switch of its own: every action is a table row.
function test_panel_actions_api_has_no_switch_left()
{
	$source = pg_test_panel_api_source();

	pg_assert_false(strpos($source, 'switch ($action)') !== false, 'switch');
	pg_assert_false((bool) preg_match("/^\\s*case '/m", $source), 'case');
	pg_assert_contains('pg_panel_gate($action)', $source, 'gate');
	pg_assert_contains('pg_panel_dispatch($action, $request)', $source, 'dispatch');
}

// The named exempt rows are exactly the pinned list; the families are
// exempt and answered by their shared handler.
function test_panel_actions_exempt_matches_pinned_list()
{
	$exempt = array();
	$handlers = array('chat_' => 'pg_panel_chat', 'site_chat_' => 'pg_panel_site_chat', 'ws_' => 'pg_panel_ws');

	foreach (pg_panel_actions() as $action => $entry) {
		$family = pg_test_panel_family($action);

		if ($family !== '') {
			pg_assert_true($entry['exempt'], $action . ' exempt');
			pg_assert_same($handlers[$family], $entry['handler'], $action . ' handler');
			continue;
		}

		if ($entry['exempt']) {
			$exempt[] = $action;
		}
	}

	$pinned = pg_test_panel_exempt_names();
	sort($exempt);
	sort($pinned);

	pg_assert_same($pinned, $exempt);
}

// What the general gate asks of each kind of action.
function test_panel_gate_answers()
{
	pg_assert_same('exempt', pg_panel_gate('designer'), 'exempt row');
	pg_assert_same('exempt', pg_panel_gate('ws_send'), 'family row');
	pg_assert_same('exempt', pg_panel_gate('ws_nope'), 'unknown family name');
	pg_assert_same('exempt', pg_panel_gate('site_chat_nope'), 'unknown site chat name');
	pg_assert_same('session', pg_panel_gate('get_form'), 'any_role');
	pg_assert_same('session', pg_panel_gate('get_forms'), 'any_role');
	pg_assert_same('role', pg_panel_gate('get_page'), 'gated row');
	pg_assert_same('role', pg_panel_gate('ca_bundle_update'), 'gated row');
	pg_assert_same('role', pg_panel_gate('nope'), 'unknown');
	pg_assert_same('role', pg_panel_gate('Null'), 'no action');
	pg_assert_same('role', pg_panel_gate(array('designer')), 'array');
	pg_assert_same('role', pg_panel_gate(0), 'integer');
	pg_assert_same('role', pg_panel_gate(null), 'null');
}

// Anything that is not a listed action name has no row.
function test_panel_action_unknown_or_not_a_string_is_null()
{
	pg_assert_same(null, pg_panel_action('nope'), 'unknown');
	pg_assert_same(null, pg_panel_action(array()), 'array');
	pg_assert_same(null, pg_panel_action(null), 'null');
	pg_assert_same('pg_panel_backend_search', pg_panel_action('backend_search')['handler'], 'known');
}

// An unlisted action is handed back to api.php untouched.
function test_panel_dispatch_unknown_action_returns_false()
{
	pg_assert_same(false, pg_panel_dispatch('nope', array()));
}

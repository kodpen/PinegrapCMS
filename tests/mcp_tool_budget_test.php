<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the size budget of the MCP tool list: a tool's description at
 * most 600 characters, a whole tool at most 4,096 bytes as JSON. The tools
 * over a budget today are listed here; the lists may only shrink, so a
 * listed tool that fits fails until it is taken off, and a new tool over a
 * budget fails at once. Reference detail goes into a route's 'details' key,
 * which only the OpenAPI document reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// Tools whose description passes 600 characters today. Take a tool off when
// its description fits; never add one.
function mcp_tool_budget_test_long_descriptions()
{
	return array(
		'products_update',
		'products_delete',
		'inventory_transfer',
		'products_price_history',
		'products_marketplaces',
		'product_groups_variant_sets_create',
		'product_groups_delete',
		'orders_list',
		'orders_create',
		'orders_get',
		'orders_update',
		'orders_labels_create',
		'orders_documents',
		'orders_returns',
		'orders_returns_create',
		'returns_list',
		'returns_update',
		'cart_quotes_list',
		'cart_quotes_create',
		'store_shipping_methods',
		'store_feeds',
		'campaigns_create',
		'campaigns_update',
		'campaigns_sms_list',
		'contact_groups_list',
		'contact_groups_members_update',
		'contact_groups_refresh',
		'fediverse_deliveries',
		'audit_log_list',
		'commissions_list',
		'commissions_recurring_profiles_list',
		'affiliates_approve',
		'calendars_event_tickets',
		'webhooks_update',
		'customers_wishlist',
		'customers_consents',
		'customers_subscriptions',
		'customers_credit',
		'customers_credit_issue',
		'customers_allow_mail',
		'customers_subscriptions_cancel',
		'subscriptions_list',
		'users_list',
		'users_create',
		'system_status',
		'reports_sales',
		'reports_cart_recovery',
		'reports_customer_value',
		'reports_visitors',
		'reports_search',
		'reports_pages',
		'reports_ai_visibility',
		'reports_vitals',
		'reports_page_clicks',
		'seo_not_found',
		'content_review_list',
		'content_review_update',
		'forms_get',
		'forms_submissions',
		'forms_submission_update',
		'forms_fields',
		'forms_submission_create',
		'forms_submission_revisions',
		'forms_submission_revision_get',
		'forms_submission_revision_restore',
		'pages_update',
		'pages_delete',
		'folders_update',
		'comments_list',
		'comments_answer',
		'experiments_list',
		'short_links_list',
		'short_links_create',
		'short_links_update',
		'offers_create',
		'design_designs_list',
		'design_pages_create',
		'design_pages_get',
		'design_pages_update',
		'design_revisions_list',
		'design_revisions_restore',
		'design_bundles_list',
		'design_bundles_pages_add',
		'design_bundles_publish',
		'design_bundles_take_back',
		'design_proposals_create',
		'notifications_list',
		'bookings_list',
		'bookings_slots',
		'erp_accounts_create',
		'erp_accounts_update',
		'erp_accounts_late_fee',
		'erp_accounts_late_fee_invoice',
		'erp_invoices_issue',
		'erp_invoices_cancel',
		'erp_invoices_return',
		'erp_receipts_create',
		'erp_receipts_cancel',
		'erp_cash_transfers_create',
		'erp_waybills_create',
		'erp_waybills_invoice',
		'erp_expenses_create',
		'erp_quote_templates_save',
		'erp_purchase_orders_list',
		'erp_purchase_orders_status',
		'erp_purchase_orders_invoice',
		'erp_cheques_create',
		'erp_cheques_step',
		'erp_stock_plan_list',
		'erp_stock_counts_items',
		'erp_reports_vat',
		'erp_reports_profit',
		'erp_orders_profit',
		'erp_profitability',
		'erp_reports_cashflow',
		'erp_reports_cash_forecast',
		'erp_dashboard',
		'workspace_channels_list',
		'workspace_channels_members_remove',
		'workspace_channels_customer',
		'workspace_channels_customer_history',
		'workspace_messages_list',
		'workspace_inbox_list',
		'workspace_inbox_read',
		'workspace_messages_create',
		'workspace_messages_forward',
		'workspace_files_create',
		'workspace_messages_voice',
		'workspace_files_content',
		'workspace_channels_read',
		'workspace_channel_groups_list',
		'workspace_channel_groups_create',
		'workspace_channel_groups_update',
		'workspace_channel_groups_delete',
		'workspace_channel_groups_access_grant',
		'workspace_channel_groups_access_revoke',
		'workspace_channels_group',
		'workspace_threads_start',
		'workspace_channels_status',
		'workspace_checkins_start',
		'workspace_messages_check',
		'workspace_messages_readers',
		'workspace_messages_mention_invite',
		'workspace_polls_create',
		'workspace_approvals_list',
		'workspace_approvals_create',
		'workspace_decisions_list',
		'workspace_changes_list',
		'workspace_assistant_requests_list',
		'workspace_notes_list',
		'workspace_notes_update',
		'workspace_tasks_list',
		'workspace_tasks_get',
		'workspace_messages_item_task',
		'workspace_tasks_delegate',
		'workspace_refs_get',
		'workspace_plan_get',
		'workspace_plan_items_create',
		'workspace_search',
		'workspace_my_day_get',
		'workspace_channels_apply_template',
		'workspace_flows_list',
		'workspace_flows_get',
		'workspace_flows_create',
		'workspace_flows_update',
		'workspace_flow_versions_restore',
		'workspace_flow_templates_list',
		'workspace_flow_kinds_list',
		'workspace_flows_validate',
		'workspace_flow_shares_list',
		'workspace_flow_runs_list',
		'workspace_flow_runs_get',
		'workspace_channels_get',
		'workspace_channels_board_get',
		'workspace_channels_activity_get',
		'workspace_channels_context',
		'workspace_changes_propose',
		'workspace_tasks_changes_propose',
		'workspace_tables_list',
		'workspace_tables_create',
		'workspace_tables_import',
		'workspace_table_columns_update',
		'workspace_table_columns_retype',
		'workspace_table_rows_list',
		'workspace_table_rows_delete',
		'workspace_tables_flow',
		'workspace_mail_conversations_list',
		'workspace_mail_conversations_get',
		'workspace_mail_items_get',
		'workspace_mail_conversations_reply',
		'workspace_mail_messages_send',
		'workspace_mail_drafts_send',
		'workspace_personal_plans_list',
		'workspace_personal_plans_create',
		'workspace_personal_plans_update',
		'workspace_channels_agents_list',
		'workspace_channels_agents_card',
		'workspace_channels_agents_set',
		'workspace_messages_mark',
		'workspace_memory_general_list',
		'workspace_memory_general_create',
		'workspace_memory_move_general',
		'workspace_memory_create',
		'workspace_memory_update',
		'workspace_channels_canvases_create',
		'workspace_messages_canvas_get',
		'workspace_messages_canvas_edit',
		'workspace_messages_canvas_save',
		'workspace_canvas_templates',
		'workspace_channels_pages_create',
		'workspace_messages_page_edit',
		'workspace_canvases_get',
		'workspace_canvases_tables_bind',
		'workspace_canvases_page_put',
		'workspace_canvases_drawing_put',
		'workspace_channels_members_card',
		'workspace_channels_library_files_create',
		'workspace_meeting_link_get',
		'workspace_channels_notes_create',
		'translations_strings_list',
		'translations_refresh',
		'translations_status',
	);
}

// Tools larger than 4,096 bytes as JSON today. Take a tool off when it fits;
// never add one.
function mcp_tool_budget_test_large_tools()
{
	return array(
		'products_create',
		'products_update',
		'returns_update',
		'store_shipping_methods_create',
		'store_shipping_methods_update',
		'offers_create',
		'workspace_inbox_list',
		'workspace_flows_create',
		'workspace_flows_update',
		'workspace_changes_propose',
		'workspace_tasks_changes_propose',
		'workspace_channels_canvases_create',
		'workspace_messages_canvas_edit',
	);
}

// Every route of the core and of the modules, as tools for a caller holding
// every scope they use, on a site with every module on; an application
// agent's own routes too, which only its own key is offered.
function mcp_tool_budget_test_tools()
{
	static $tools = null;

	if ($tools === null) {
		$all = array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes());

		$scopes = array();

		foreach ($all as $route) {
			if (isset($route['scope']) && ($route['scope'] !== '')) {
				$scopes[$route['scope']] = true;
			}
		}

		$registry = pg_agent_guide_registry();

		$modules = array('store', 'workspace', 'erp');

		$tools = array();

		foreach (pg_mcp_routes_for($all, array_keys($scopes), true) as $route) {
			$tool = pg_mcp_tool_from_route($route);

			// The guide line depends on the modules a site runs, read once a
			// request: measure every tool as a site with every module on sends it.
			$topic = pg_agent_guide_topic_of_route($registry, pg_agent_guide_listed($registry, array($route['id']), $modules), $route['id']);

			$tool['description'] = trim((string)preg_replace('/ ?Guide: [a-z_]+\.$/', '', $tool['description'])
				. (($topic !== '') ? ' Guide: ' . $topic . '.' : ''));

			$tools[] = $tool;
		}
	}

	return $tools;
}

// The tools over the named budget, by name.
function mcp_tool_budget_test_over($budget)
{
	$over = array();

	foreach (mcp_tool_budget_test_tools() as $tool) {
		if (in_array($budget, pg_mcp_tool_over_budget($tool), true)) {
			$over[] = $tool['name'];
		}
	}

	return $over;
}

function mcp_tool_budget_test_check($budget, $listed, $what)
{
	$over = mcp_tool_budget_test_over($budget);

	pg_assert_same(count($listed), count(array_unique($listed)), 'the ' . $what . ' list names each tool once');

	$new = array_values(array_diff($over, $listed));

	pg_assert_same(array(), $new, 'tools over the ' . $what . ' budget that are not on its list: fit them, do not list them');

	$fits = array_values(array_diff($listed, $over));

	pg_assert_same(array(), $fits, 'listed tools that fit the ' . $what . ' budget now: take them off the list');
}

function test_mcp_tool_budget_values()
{
	pg_assert_same(array('description' => 600, 'tool' => 4096), pg_mcp_tool_budgets());

	$short = array('name' => 'a', 'description' => str_repeat('é', 600));

	pg_assert_same(array(), pg_mcp_tool_over_budget($short), '600 characters fit, counted as characters');

	$short['description'] .= 'x';

	pg_assert_same(array('description'), pg_mcp_tool_over_budget($short));

	$large = array('name' => 'a', 'description' => 'b', 'inputSchema' => array('x' => str_repeat('y', 4096)));

	pg_assert_same(array('tool'), pg_mcp_tool_over_budget($large));
}

function test_mcp_tool_budget_descriptions_only_shrink()
{
	mcp_tool_budget_test_check('description', mcp_tool_budget_test_long_descriptions(), 'description');
}

function test_mcp_tool_budget_tools_only_shrink()
{
	mcp_tool_budget_test_check('tool', mcp_tool_budget_test_large_tools(), 'tool size');
}

// A route's details reach the OpenAPI operation, after its description and
// scope, and never the MCP tool.
function test_mcp_tool_budget_details_are_for_the_document_only()
{
	$route = array(
		'id'          => 'products.budget_probe',
		'method'      => 'GET',
		'path'        => '/products/budget-probe',
		'scope'       => 'products:read',
		'summary'     => 'Probe',
		'description' => 'Short text a caller needs.',
		'details'     => 'Reference detail looked up rather than needed.',
		'params'      => array()
	);

	pg_assert_false(strpos(json_encode(pg_mcp_tool_from_route($route)), 'Reference detail') !== false, 'the tool does not carry the details');

	pg_assert_same(
		"Short text a caller needs. Requires the products:read scope.\n\nReference detail looked up rather than needed.",
		api_openapi_operation_description($route)
	);

	unset($route['description']);

	pg_assert_same("Requires the products:read scope.\n\nReference detail looked up rather than needed.", api_openapi_operation_description($route));

	$route['scope'] = '';
	unset($route['details']);

	pg_assert_same('', api_openapi_operation_description($route));
}

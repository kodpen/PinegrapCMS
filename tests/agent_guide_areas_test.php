<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guide's content, forms and store topics
 * (includes/fn/agent_guide_content.php, agent_guide_forms.php,
 * agent_guide_store.php): each has its file and sections, says the records,
 * jobs, writes and mistakes of its area, carries the values its registry
 * owns as that registry lists them, and names every tool of its area that
 * moves money.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/folders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/forms.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// A topic's whole text on a site running every module.
function agent_guide_areas_test_text($id)
{
	return pg_agent_guide_topic_text(pg_agent_guide_registry(), $id, array('store', 'workspace', 'erp'));
}

function test_agent_guide_areas_have_their_files_and_sections()
{
	$registry = pg_agent_guide_registry();
	$route_ids = array();

	foreach (api_schema() as $route) {
		$route_ids[] = $route['id'];
	}

	$listed = pg_agent_guide_listed($registry, $route_ids, array('store'));

	foreach (array('content', 'forms', 'store') as $id) {
		pg_assert_true(is_file($registry[$id]['file']), $id . ': its file');
		pg_assert_true(in_array($id, $listed, true), $id . ': listed to a caller holding its tools');

		$text = agent_guide_areas_test_text($id);

		pg_assert_true(is_string($text), $id . ': has text');

		foreach (array('records', 'jobs', 'writes', 'mistakes') as $section) {
			pg_assert_contains('(#' . $section . ')', (string)$text, $id . ': section ' . $section);
		}
	}

	pg_assert_false(in_array('store', pg_agent_guide_listed($registry, $route_ids, array()), true), 'store: not listed with the module off');
	pg_assert_same(array('start', 'content'), array_values(array_intersect(pg_agent_guide_listed($registry, array('pages.list'), array('store')), array('start', 'content', 'forms', 'store'))), 'a caller holding only pages.list gets content alone');
}

// The values a topic carries are the ones their registry lists, in its order,
// so a changed list fails here rather than leaving the guide behind.
function test_agent_guide_areas_values_follow_their_registries()
{
	$content = (string)agent_guide_areas_test_text('content');

	pg_assert_contains(implode(', ', array_keys(api_folder_access_ranks())), $content, 'content: the folder access rules');
	pg_assert_contains(implode(', ', api_files_allowed_types()), $content, 'content: the upload types');

	$forms = (string)agent_guide_areas_test_text('forms');

	foreach (api_form_text_field_types() as $type) {
		pg_assert_contains($type, $forms, 'forms: the update writes ' . $type);
	}

	pg_assert_true(api_form_field_updatable(array('type' => 'radio button', 'multiple' => 0)), 'a radio button takes an update');
	pg_assert_false(api_form_field_updatable(array('type' => 'check box', 'multiple' => 0)), 'a check box group does not');

	$store = (string)agent_guide_areas_test_text('store');

	pg_assert_contains(implode(', ', api_order_statuses()), $store, 'store: the order statuses');
}

// Every tool of an area that moves money is named in the area's topic, so an
// agent knows which call is a sale or a purchase before it makes one.
function test_agent_guide_areas_name_the_money_tools()
{
	$registry = pg_agent_guide_registry();

	foreach (array('content', 'forms', 'store') as $id) {
		$named = pg_agent_guide_named_routes(pg_agent_guide_sections($registry, $id));

		foreach (api_schema() as $route) {
			if (!empty($route['moves_money']) && pg_mcp_route_is_offered($route) && pg_agent_guide_holds($registry[$id]['tools'], array($route['id']))) {
				pg_assert_true(in_array($route['id'], $named, true), $id . ' names the money tool ' . $route['id']);
			}
		}
	}

	$store = (string)agent_guide_areas_test_text('store');

	pg_assert_contains('orders_create', $store);
	pg_assert_contains('orders_labels_create', $store);
}

function test_agent_guide_areas_say_the_rules_of_their_writes()
{
	$content = (string)agent_guide_areas_test_text('content');

	foreach (array('design_pages_create', 'pages_update', 'folders_create', 'files_create', 'access_widening_refused', 'permanent', 'guide topic designer') as $needle) {
		pg_assert_contains($needle, $content, 'content says ' . $needle);
	}

	$forms = (string)agent_guide_areas_test_text('forms');

	foreach (array('forms_fields', 'forms_submission_create', 'forms_submission_update', 'is_collection', 'html_allowed', 'option value', 'send_notifications') as $needle) {
		pg_assert_contains($needle, $forms, 'forms says ' . $needle);
	}

	$store = (string)agent_guide_areas_test_text('store');

	foreach (array('inventory_write', 'products_prices', 'Idempotency-Key', 'minor units', 'orders_ship', 'name_taken') as $needle) {
		pg_assert_contains($needle, $store, 'store says ' . $needle);
	}
}

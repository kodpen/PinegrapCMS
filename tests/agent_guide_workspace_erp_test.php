<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guide's workspace and erp topics
 * (includes/fn/agent_guide_workspace.php, agent_guide_erp.php): each is
 * listed only while its module is on and to a caller holding one of its
 * tools, says the area's records, jobs and rules, and the workspace topic
 * points at the canvas guide rather than copying it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');

function test_agent_guide_workspace_and_erp_follow_their_module()
{
	$registry = pg_agent_guide_registry();
	$routes = array('workspace.channels.list', 'erp.invoices.list');

	$all = pg_agent_guide_listed($registry, $routes, array('workspace', 'erp'));

	pg_assert_true(in_array('workspace', $all, true), 'workspace listed with its module and a tool');
	pg_assert_true(in_array('erp', $all, true), 'erp listed with its module and a tool');

	$none = pg_agent_guide_listed($registry, $routes, array());

	pg_assert_false(in_array('workspace', $none, true), 'workspace off, not listed');
	pg_assert_false(in_array('erp', $none, true), 'erp off, not listed');

	pg_assert_same(array('workspace'), array_values(array_intersect(array('workspace', 'erp'), pg_agent_guide_listed($registry, $routes, array('workspace')))), 'erp off alone');

	$no_tool = pg_agent_guide_listed($registry, array('meta.read'), array('workspace', 'erp'));

	pg_assert_false(in_array('erp', $no_tool, true), 'no erp tool, no erp topic');
	pg_assert_false(in_array('workspace', $no_tool, true), 'no workspace tool, no workspace topic');

	pg_assert_same('erp', pg_agent_guide_topic_of_route($registry, $all, 'erp.receipts.create'));
	pg_assert_same('workspace', pg_agent_guide_topic_of_route($registry, $all, 'workspace.tasks.create'));
}

function test_agent_guide_workspace_says_its_records_and_rules()
{
	$text = pg_agent_guide_topic_text(pg_agent_guide_registry(), 'workspace', array('workspace'));

	pg_assert_true(is_string($text), 'the workspace topic has text');

	foreach (array('Channel:', 'Message:', 'Task:', 'Proposal:', 'Approval:', 'Table:', 'Note:', 'Memory:', 'Flow:', 'workspace_channels_context', 'workspace_changes_propose', 'dry_run', 'since_id', '<@user:12>', 'minor units') as $needle) {
		pg_assert_contains($needle, (string)$text, 'workspace says ' . $needle);
	}

	// The drawing rules live in the canvas guide; the topic names its tool
	// before the drawing tool and copies none of its values.
	pg_assert_true(strpos((string)$text, 'workspace_canvas_guide') < strpos((string)$text, 'workspace_channels_canvases_create'), 'the canvas guide before drawing');
	pg_assert_same(0, preg_match('/#[0-9a-fA-F]{6}\b/', (string)$text), 'no palette colour copied');

	foreach (array('records', 'jobs', 'writes', 'mistakes') as $section) {
		pg_assert_contains('(#' . $section . ')', (string)$text, 'section ' . $section);
	}
}

function test_agent_guide_erp_says_its_records_and_rules()
{
	$text = pg_agent_guide_topic_text(pg_agent_guide_registry(), 'erp', array('erp'));

	pg_assert_true(is_string($text), 'the erp topic has text');

	foreach (array('Account:', 'Invoice:', 'Quote', 'Waybill:', 'Receipt:', 'Till:', 'Stock:', 'erp_invoices_issue', 'erp_orders_invoice', 'tax_number', 'minor units', 'withholding', 'period_locked', 'dry_run', 'own name') as $needle) {
		pg_assert_contains($needle, (string)$text, 'erp says ' . $needle);
	}

	pg_assert_true(strpos((string)$text, 'erp_invoices_create') < strpos((string)$text, 'erp_invoices_issue'), 'draft before issue');

	foreach (array('records', 'jobs', 'writes', 'mistakes') as $section) {
		pg_assert_contains('(#' . $section . ')', (string)$text, 'section ' . $section);
	}
}

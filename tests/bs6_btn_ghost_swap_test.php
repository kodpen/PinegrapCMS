<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the .btn-ghost to .btn-text.theme-secondary swap in the
 * Workspace and ERP screens, the designer list and the shared list helpers:
 * the converted files carry no .btn-ghost, apart from the buttons of the rich
 * text tool rows (.ws-rich-tools), whose pressed and disabled looks are still
 * written against .btn-ghost.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Occurrences of the .btn-ghost class token in a file (not .sd-ai-btn-ghost).
function bs6_btn_ghost_test_count($path)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);

	return preg_match_all('/(?<![\w-])btn-ghost(?![\w-])/', $source);
}

function test_bs6_btn_ghost_swap_workspace_and_erp()
{
	$files = array(
		'assets/js/workspace_templates.js',
		'assets/js/workspace_approvals.js',
		'assets/js/workspace_voice.js',
		'assets/js/workspace_task_delegate.js',
		'assets/js/workspace_task_time.js',
		'assets/js/workspace_task_approval.js',
		'assets/js/workspace_task_links.js',
		'assets/js/workspace_status.js',
		'assets/js/workspace_palette.js',
		'assets/js/workspace_board_channel.js',
		'assets/js/workspace_recurrence.js',
		'assets/js/workspace_acks.js',
		'assets/js/designer_ai.js',
		'workspace_settings.php',
		'workspace_mcp.php',
		'includes/workspace/workdays.php',
		'includes/workspace/templates.php',
		'includes/workspace/tasks.php',
		'includes/workspace/claude.php',
		'includes/workspace/mcp.php',
		'includes/workspace/mcp_catalogue.php',
		'workspace_template.php',
		'erp_cashflow.php',
		'erp_aging.php',
		'erp_invoices.php',
		'erp_inbox_document.php',
		'erp_account_sync_item.php',
		'includes/erp/waybill_form.php',
		'includes/erp/invoice_form.php',
		'includes/erp/account_quick.php',
	);

	foreach ($files as $path) {
		pg_assert_same(0, bs6_btn_ghost_test_count($path), $path . ' carries no .btn-ghost');
	}

	// Only the buttons that end up in a PGWsEditor.toolbar() row keep it.
	pg_assert_same(12, bs6_btn_ghost_test_count('assets/js/workspace.js'), 'workspace.js: the rich tool rows only');
	pg_assert_same(1, bs6_btn_ghost_test_count('assets/js/workspace_editor.js'), 'workspace_editor.js: the toolbar\'s own buttons only');
}

function test_bs6_btn_ghost_swap_designer_and_list_helpers()
{
	// The designer list (cards, template gallery) and the period and sort
	// controls output.php draws for the list screens.
	foreach (array('includes/designer_screen.php', 'includes/fn/output.php') as $path) {
		pg_assert_same(0, bs6_btn_ghost_test_count($path), $path . ' carries no .btn-ghost');
	}
}

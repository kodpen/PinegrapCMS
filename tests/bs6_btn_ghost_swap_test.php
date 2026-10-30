<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the .btn-ghost to .btn-text.theme-secondary swap: the
 * converted Workspace and ERP screens (the rich text tool rows included), the
 * designer list and the shared list helpers carry no .btn-ghost, and neither
 * does any other panel file outside the flow canvas and the stylesheet.
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
		'assets/js/workspace.js',
		'assets/js/workspace_editor.js',
	);

	foreach ($files as $path) {
		pg_assert_same(0, bs6_btn_ghost_test_count($path), $path . ' carries no .btn-ghost');
	}

	// The rich tool rows' pressed and disabled looks follow their buttons.
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	pg_assert_same(0, preg_match_all('/\.ws-rich-tools \.btn-ghost/', $css), 'no .ws-rich-tools .btn-ghost rule is left');
	pg_assert_same(1, preg_match_all('/\.ws-rich-tools \.btn-text\.theme-secondary\[aria-pressed="true"\]/', $css), 'pressed tool rule');
	pg_assert_same(1, preg_match_all('/\.ws-rich-tools \.btn-text\.theme-secondary:disabled/', $css), 'disabled tool rule');
}

function test_bs6_btn_ghost_swap_designer_and_list_helpers()
{
	// The designer list (cards, template gallery) and the period and sort
	// controls output.php draws for the list screens.
	foreach (array('includes/designer_screen.php', 'includes/fn/output.php') as $path) {
		pg_assert_same(0, bs6_btn_ghost_test_count($path), $path . ' carries no .btn-ghost');
	}
}

function test_bs6_btn_ghost_swap_whole_panel()
{
	// Every product file outside the libraries, the flow canvas (its own
	// work converts it) and the stylesheet that still defines the rules.
	$skip = array(
		'assets/js/workspace_flow.js',
		'assets/js/workspace_flow.min.js',
		'assets/css/backend.src.css',
		'assets/css/backend.min.css',
	);
	$root = dirname(__DIR__) . '/pinegrap/';
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$found = array();

	foreach ($iterator as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));

		if (!preg_match('/\.(php|js|css)$/', $path) || strpos($path, 'assets/lib/') === 0 || in_array($path, $skip, true)) {
			continue;
		}

		if (bs6_btn_ghost_test_count($path) > 0) {
			$found[] = $path;
		}
	}

	pg_assert_same(array(), $found, 'no panel file carries .btn-ghost');
}

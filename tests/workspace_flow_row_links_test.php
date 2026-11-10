<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for row links in flows: a row link cell reads as the linked row's
 * id, which a next Read a row box finds by id; a refused link is worded for
 * the flow's owner; the canvas keeps a typed id as a number and never
 * groups it; Pinegrap AI's catalogue says how to follow a link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_draft.php');

// A link reads as the linked row's id, as {{trigger.cell_N}} and Read a row give it.
function test_workspace_flow_row_links_cell_is_the_row_id()
{
	pg_assert_same('12', ws_flow_row_cell_text(12));
	pg_assert_same('', ws_flow_row_cell_text(null));
	pg_assert_same(12, ws_step_row_id('12'), 'Read a row by id takes it');
}

// A link the owner may not write is worded for the owner; a missing row by the table's text.
function test_workspace_flow_row_links_refusal_words()
{
	pg_assert_contains('flow\'s owner', ws_step_row_cell_reason('row_table_unread'));
	pg_assert_same(ws_wtable_screen_error_text('row_not_found'), ws_step_row_cell_reason('row_not_found'));
	pg_assert_same(ws_wtable_screen_error_text('not_row'), ws_step_row_cell_reason('not_row'));
}

// Pinegrap AI's catalogue tells how to follow a link and how to write one.
function test_workspace_flow_row_links_ai_hints()
{
	$source = pg_test_function_source('ws_flow_ai_catalogue_hints');

	pg_assert_contains('a row link column\\\'s the linked row\\\'s id, which a next row_get with by: id and that table reads', $source);
	pg_assert_contains('a row link cell takes the id of a row of its linked table', $source);
}

// The canvas keeps a typed id as a number, shows it ungrouped and helps with it.
function test_workspace_flow_row_links_canvas()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_contains("((column.type === 'person') || (column.type === 'row')) && /^\\d+$/.test(typed)", $script);
	pg_assert_contains("(typeof value === 'number') && (column.type !== 'row')", $script);
	pg_assert_contains("row: t('fl_rows_row_help')", $script);
	pg_assert_true(isset(ws_flow_js_strings()['fl_rows_row_help']));
}

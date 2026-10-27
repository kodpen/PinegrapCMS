<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's short-link sub-action handlers
 * (includes/explorer/handle_short_links.php): the handlers live there, behind
 * the gate, pg_explorer_handle() calls each one for its sub-action, and the
 * destination columns an update writes are unchanged by the move.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// Each short-link sub-action has its own handler, loaded by the explorer's
// functions and called from pg_explorer_handle().
function test_explorer_short_link_handlers_live_in_their_own_file()
{
	$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_short_links.php');
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$actions = array(
		'explorer_short_links_list' => 'pg_explorer_handle_short_links_list',
		'explorer_short_link_options' => 'pg_explorer_handle_short_link_options',
		'explorer_short_link_create' => 'pg_explorer_handle_short_link_create',
		'explorer_short_link_rename' => 'pg_explorer_handle_short_link_rename',
		'explorer_short_link_update' => 'pg_explorer_handle_short_link_update',
		'explorer_short_link_duplicate' => 'pg_explorer_handle_short_link_duplicate',
		'explorer_short_link_delete' => 'pg_explorer_handle_short_link_delete');

	foreach ($actions as $action => $function) {
		pg_assert_contains('function ' . $function . '(', $handlers, $function);
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_contains("case '" . $action . "':\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $action . ' calls its handler');
	}

	pg_assert_true(function_exists('pg_explorer_short_link_destination_columns'), 'destination columns helper loaded');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, 'gate');
	pg_assert_false(strpos($handlers, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_short_links.php');", $explorer, 'loaded by the explorer');
}

// An update writes every destination column: the ones the new type does not
// use go back to empty, so a link turned from a product into an address keeps
// no product id.
function test_explorer_short_link_destination_columns()
{
	pg_assert_same(
		array('product_group_id' => 0, 'product_id' => 0, 'url' => '', 'file_id' => 0),
		pg_explorer_short_link_destination_columns(array()),
		'page link clears every other destination');

	pg_assert_same(
		array('product_group_id' => 0, 'product_id' => 0, 'url' => 'https://example.com/', 'file_id' => 0),
		pg_explorer_short_link_destination_columns(array('url' => 'https://example.com/')),
		'address link keeps only its address');

	pg_assert_same(
		array('product_group_id' => 3, 'product_id' => 7, 'url' => '', 'file_id' => 0),
		pg_explorer_short_link_destination_columns(array('product_id' => 7, 'product_group_id' => 3)),
		'product link keeps its product and group, in the written order');
}

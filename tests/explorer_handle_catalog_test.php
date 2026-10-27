<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's catalog sub-action handlers
 * (includes/explorer/handle_catalog.php): the handlers live there, behind the
 * gate, pg_explorer_handle() calls each one for its sub-action, and the pure
 * parts (how long a binned row has left, when a group's short description
 * follows its new name, the recycle_bin type of a grid kind, how refusals
 * join the bin message) behave as before.
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

// Each sub-action has its own handler, loaded by the explorer's functions and
// called from pg_explorer_handle().
function test_explorer_catalog_handlers_live_in_their_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog.php');

	$actions = array(
		'catalog_list',
		'catalog_tree',
		'catalog_create_group',
		'catalog_rename',
		'catalog_membership_remove',
		'catalog_pages',
		'catalog_paste',
		'catalog_recycle',
		'catalog_restore',
		'catalog_purge',
		'catalog_enable');

	foreach ($actions as $action) {
		$function = 'pg_explorer_handle_' . $action;
		pg_assert_contains('function ' . $function . '(', $handlers, $function);
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_contains("case 'explorer_" . $action . "':\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $function . ' is called by its case');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, 'gate');
	pg_assert_false(strpos($handlers, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog.php');", $explorer, 'loaded by the explorer');
}

// A binned row is keyed the way the grid keys it, and counts down whole days
// from its retention period, never below zero.
function test_explorer_catalog_bin_entries()
{
	$now = 1700000000;
	$rows = array(
		array('item_type' => 'product_group', 'item_id' => '7', 'deleted_at' => (string) ($now - 86400 * 3 - 5)),
		array('item_type' => 'product', 'item_id' => '12', 'deleted_at' => (string) ($now - 3600)),
		array('item_type' => 'product', 'item_id' => '13', 'deleted_at' => (string) ($now - 86400 * 40)));

	$entries = pg_explorer_catalog_bin_entries($rows, 30, $now);

	pg_assert_same(array('group:7', 'product:12', 'product:13'), array_keys($entries), 'keys');
	pg_assert_same(array('deleted_at' => $now - 86400 * 3 - 5, 'days_left' => 27, 'origin_name' => ''), $entries['group:7'], 'group');
	pg_assert_same(30, $entries['product:12']['days_left'], 'less than a day old');
	pg_assert_same(0, $entries['product:13']['days_left'], 'past the period');
	pg_assert_same(array(), pg_explorer_catalog_bin_entries(array(), 30, $now), 'no rows');
}

// A rename reaches the short description only while it is empty or still the
// group's name; one written by hand is left alone.
function test_explorer_catalog_short_description_follows_name()
{
	pg_assert_true(pg_explorer_catalog_short_description_follows_name(array('name' => 'Shoes', 'short_description' => '')), 'empty');
	pg_assert_true(pg_explorer_catalog_short_description_follows_name(array('name' => 'Shoes ', 'short_description' => ' Shoes')), 'same name');
	pg_assert_false(pg_explorer_catalog_short_description_follows_name(array('name' => 'Shoes', 'short_description' => 'Our shoes')), 'written by hand');
	pg_assert_false(pg_explorer_catalog_short_description_follows_name(false), 'no row');
}

// The grid's kinds name the recycle_bin types the purge deletes; anything else
// is no type at all.
function test_explorer_catalog_bin_item_type()
{
	pg_assert_same('product_group', pg_explorer_catalog_bin_item_type('group'), 'group');
	pg_assert_same('product', pg_explorer_catalog_bin_item_type('product'), 'product');
	pg_assert_same('', pg_explorer_catalog_bin_item_type('folder'), 'other kind');
	pg_assert_same('', pg_explorer_catalog_bin_item_type(''), 'no kind');
}

// Refusals follow the count when something was binned and replace it when
// nothing was, each reason said once.
function test_explorer_catalog_recycle_message()
{
	pg_assert_same('2 binned.', pg_explorer_catalog_recycle_message('2 binned.', 2, array()), 'no refusals');
	pg_assert_same('2 binned. Root. Other.', pg_explorer_catalog_recycle_message('2 binned.', 2, array('Root.', 'Other.', 'Root.')), 'after the count');
	pg_assert_same('Root.', pg_explorer_catalog_recycle_message('Sorry.', 0, array('Root.', 'Root.')), 'instead of the count');
}

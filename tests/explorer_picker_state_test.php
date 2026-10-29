<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the File and Catalog Manager pickers and menu ticks: the
 * Move/Copy/Add to group and Send to channel rows say which one is picked,
 * keep focus on a pick and announce an empty list; the Filters, Columns,
 * View, Sort, scope, Preview panel and Grid/List controls expose their state.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_picker_state_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_folders.php');
}

function test_explorer_picker_state_transfer_rows_are_pressed()
{
	$source = explorer_picker_state_source();
	pg_assert_true(strpos($source, "' active\" aria-pressed=\"true' : '\" aria-pressed=\"false') +\n                '\" style=\"' + indent + '\" data-target-id=") !== false, 'transfer rows carry aria-pressed beside .active');
	pg_assert_true(strpos($source, "focusPickerRow('transfer_targets', 'data-target-id', transferState.picked);") !== false, 'a transfer pick puts focus back on its row');
	pg_assert_true(strpos($source, "announcePickerEmpty('transfer', (shown > 0) ? '' : L.no_target_match);") !== false, 'an empty transfer list is announced');
}

function test_explorer_picker_state_channel_rows_are_pressed()
{
	$source = explorer_picker_state_source();
	pg_assert_true(strpos($source, "' active\" aria-pressed=\"true' : '\" aria-pressed=\"false') +\n                '\" data-channel-id=") !== false, 'channel rows carry aria-pressed beside .active');
	pg_assert_true(strpos($source, "focusPickerRow('ws_send_channels', 'data-channel-id', wsSendState.picked);") !== false, 'a channel pick puts focus back on its row');
	pg_assert_true(strpos($source, "announcePickerEmpty('ws_send', empty);") !== false, 'an empty channel list is announced');
	pg_assert_true(strpos($source, "document.getElementById('explorer_search_status')") !== false, 'announcements use the existing status region');
}

function test_explorer_picker_state_filters_say_which_are_on()
{
	$source = explorer_picker_state_source();
	pg_assert_true(strpos($source, "data-filter=\"' + esc(def.key) + '\" aria-pressed=\"' + (state.filters[def.key] ? 'true' : 'false') + '\">'") !== false, 'filter menu items carry aria-pressed');
	pg_assert_true(strpos($source, "'remove_filter' => lang('Remove filter: {var:1}')") !== false, 'label map carries the chip name');
	pg_assert_true(strpos($source, "aria-label=\"' + esc(fmt(L.remove_filter, [def.label()])) + '\"") !== false, 'each active-filter chip says it removes its filter');
}

function test_explorer_picker_state_menus_and_toggles()
{
	$source = explorer_picker_state_source();
	pg_assert_true(strpos($source, "data-menu-action=\"' + action + '\" aria-pressed=\"' + (checked ? 'true' : 'false') + '\">'") !== false, 'menuCheckItem prints aria-pressed');
	pg_assert_true(strpos($source, "id=\"preview_toggle_button\" aria-pressed=\"false\"") !== false, 'Preview panel item starts with aria-pressed');
	pg_assert_true(strpos($source, "document.getElementById('preview_toggle_button').setAttribute('aria-pressed', previewOpen ? 'true' : 'false');") !== false, 'Preview panel item keeps aria-pressed in step');
	pg_assert_true(strpos($source, "id=\"view_grid_button\" class=\"btn btn-text theme-secondary no-popover\" aria-pressed=\"false\"") !== false, 'Grid button prints aria-pressed');
	pg_assert_true(strpos($source, "id=\"view_list_button\" class=\"btn btn-text theme-secondary no-popover\" aria-pressed=\"false\"") !== false, 'List button prints aria-pressed');
	pg_assert_true(strpos($source, "document.getElementById('view_grid_button').setAttribute('aria-pressed', (state.viewType === 'grid') ? 'true' : 'false');") !== false, 'Grid button keeps aria-pressed in step');
	pg_assert_true(strpos($source, "document.getElementById('view_list_button').setAttribute('aria-pressed', (state.viewType === 'list') ? 'true' : 'false');") !== false, 'List button keeps aria-pressed in step');
	pg_assert_true(strpos($source, "if (current) { button.setAttribute('aria-current', 'true'); } else { button.removeAttribute('aria-current'); }") !== false, 'current file scope carries aria-current');
}

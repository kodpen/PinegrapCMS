<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel tab rows: each says which tab is open (aria-selected
 * on a role=tab, a named role=tabpanel) and moves with the arrow keys, and a
 * tab hidden from the row cannot be reached by those keys.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_tab_rows_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_tab_rows_api_drawer_tabs_name_their_panes()
{
	$source = panel_tab_rows_source('api_settings.php');

	// Bootstrap gives a pane aria-labelledby only when its tab has an id.
	foreach (array('general', 'perms', 'security', 'devices', 'hooks', 'log') as $pane) {
		pg_assert_contains('data-bs-target="#d_' . $pane . '" type="button" id="d_' . $pane . '_label" aria-controls="d_' . $pane . '"', $source, $pane . ' tab id');
	}
}

function test_panel_tab_rows_api_drawer_hidden_tab_is_disabled()
{
	$source = panel_tab_rows_source('api_settings.php');

	// Bootstrap's arrow keys skip disabled tabs only, not hidden ones.
	pg_assert_contains('document.getElementById("d_devices_tab").classList.toggle("d-none", !keyless);', $source, 'devices hidden');
	pg_assert_contains('devicesLabel.disabled = !keyless;', $source, 'devices disabled');
	pg_assert_contains('document.getElementById("d_hooks_tab").classList.toggle("d-none", keyless);', $source, 'events hidden');
	pg_assert_contains('document.getElementById("d_hooks_label").disabled = keyless;', $source, 'events disabled');
}

function test_panel_tab_rows_image_picker_tabs()
{
	$source = panel_tab_rows_source('editor_select_image.php');

	pg_assert_contains('aria-selected="true" aria-controls="esi_tab_files"', $source, 'files tab');
	pg_assert_contains('aria-selected="false" aria-controls="esi_tab_unsplash" tabindex="-1"', $source, 'unsplash tab');
	pg_assert_contains("' role=\"tabpanel\" aria-labelledby=\"esi_tab_files_btn\"'", $source, 'files pane');
	pg_assert_contains('id="esi_tab_unsplash" role="tabpanel" aria-labelledby="esi_tab_unsplash_btn"', $source, 'unsplash pane');
	pg_assert_contains("b.setAttribute(\\'aria-selected\\', on ? \\'true\\' : \\'false\\');", $source, 'switch keeps aria-selected');
	pg_assert_contains("b.setAttribute(\\'tabindex\\', on ? \\'0\\' : \\'-1\\');", $source, 'switch keeps roving tabindex');
	pg_assert_contains("e.key === \\'ArrowRight\\'", $source, 'arrow keys');
	pg_assert_contains("e.key === \\'End\\'", $source, 'end key');
}

function test_panel_tab_rows_image_picker_sort_menu_current()
{
	$source = panel_tab_rows_source('editor_select_image.php');

	pg_assert_contains("((\$sort_key == \$current_sort) ? ' active\" aria-current=\"page' : '')", $source, 'sort menu');
}

function test_panel_tab_rows_spreadsheet_sheet_tabs()
{
	$source = panel_tab_rows_source('assets/js/pg_office_view.js');

	pg_assert_contains("tabs.setAttribute('role', 'tablist');", $source, 'tablist');
	pg_assert_contains("tabs.setAttribute('aria-label', strings.sheets || '');", $source, 'tablist name');
	pg_assert_contains("pane.setAttribute('role', 'tabpanel');", $source, 'tabpanel');
	pg_assert_contains("tab.setAttribute('role', 'tab');", $source, 'tab role');
	pg_assert_contains("tab.setAttribute('aria-controls', paneId);", $source, 'aria-controls');
	pg_assert_contains("tab.setAttribute('aria-selected', 'true');", $source, 'selected');
	pg_assert_contains("pane.setAttribute('aria-labelledby', tab.id);", $source, 'pane named by open tab');
	pg_assert_contains("event.key === 'ArrowRight'", $source, 'arrow keys');
	pg_assert_contains("tab.appendChild(el('span', 'visually-hidden', ', ' + (strings.hidden || '')));", $source, 'hidden sheet in words');
	pg_assert_contains('mark.hidden = !edited;', $source, 'changed in words, kept in step');

	$strings = panel_tab_rows_source('includes/fn/office.php');
	pg_assert_contains("'sheets'            => lang('Sheets'),", $strings, 'sheets string');
	pg_assert_contains("'changed_mark'      => lang('Changed'),", $strings, 'changed string');
}

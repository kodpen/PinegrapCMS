<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager's details panel and the Pages list: the
 * folder, address, home page, design file, optimized and form attachment
 * rows, the exact time and the editor's name beside a change, the impact
 * figure in the SEO section with its window, the style inheritance wording,
 * and the Pages view's columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_details_rows_screen()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// The payloads carry the exact time, the editor's name and the attachment
// flag; the list queries select the flag.
function test_explorer_payload_fields()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	pg_assert_same(3, substr_count($source, "'modified_exact' => pg_explorer_exact_time("), 'pages, files and folders');
	pg_assert_same(3, substr_count($source, "'editor' => pg_explorer_editor_name("), 'pages, files and folders');
	pg_assert_contains("'attachment' => (isset(\$file['attachment']) && (\$file['attachment'] == '1')),", $source);
	pg_assert_contains('LEFT JOIN contacts ON contacts.id = user.user_contact', $source);
	pg_assert_same('', pg_explorer_exact_time(0));
	pg_assert_same('', pg_explorer_editor_name(''));

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_list.php');
	pg_assert_same(2, substr_count($list, 'files.attachment,'), 'the Files view and the folder listing select the flag');
}

function test_explorer_details_rows()
{
	$source = explorer_details_rows_screen();

	pg_assert_contains('data-role="open-folder"', $source);
	pg_assert_contains("exitAllFiles(item.folder_id);", $source);
	pg_assert_contains("esc(fullAddress(item.url))", $source);
	pg_assert_contains("rows += '<tr><th>' + esc(L.home_page) + '</th>", $source);
	pg_assert_contains("rows += '<tr><th>' + esc(L.design_file) + '</th>", $source);
	pg_assert_contains("rows += '<tr><th>' + esc(L.optimized) + '</th>", $source);
	pg_assert_contains("esc(L.form_attachment_hint)", $source);
	pg_assert_contains("(item.modified_exact ? esc(item.modified_exact) + ' · ' : '')", $source);
	pg_assert_false(strpos($source, "fmt(L.modified_by, [esc(item.username)])") !== false, 'the editor name is written through modifiedByHtml()');
	pg_assert_contains("var who = item.editor || item.username || '';", $source);
	pg_assert_contains("esc(L.inherited_style)", $source);
}

function test_explorer_impact_in_seo_section()
{
	$source = explorer_details_rows_screen();

	pg_assert_contains("impactCellHtml(item, true)", $source);
	pg_assert_contains("esc(L.impact_hint)", $source);
	pg_assert_contains("fmt(L.views_in_window, [item.views, L.impact_window_days])", $source);
	pg_assert_contains("if (!item.seo) {\n                rows += '<tr><th>' + esc(L.impact) + '</th>", $source);
}

function test_explorer_pages_view_columns()
{
	$source = explorer_details_rows_screen();

	pg_assert_contains("var pagesColumns = (state.mode === 'all') && (state.allFilter === 'pages');", $source);
	pg_assert_contains("if (!pagesColumns) columns.push({\n            id: 'size'", $source);
	pg_assert_contains("if (pagesColumns && (item.kind === 'page') && !item.type_name) { return ''; }", $source);
	pg_assert_contains("w: (pagesColumns ? 150 : 92)", $source);
	pg_assert_contains("columns = rest.slice(0, at).concat(leading, rest.slice(at));", $source);
	pg_assert_false(strpos($source, "'<div class=\"small fg-secondary\">' + esc(fmt(L.views_label") !== false, 'the views stay on the impact line');
}

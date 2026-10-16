<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the short link screens: a value posted as a list counts as not
 * given (the File Manager's wizard, pg_short_link_read_request() and the rest
 * in view_folder_and_files_f.php, and the classic add and edit screens); a
 * product, a product group, a page or a file in the Recycle Bin is refused as
 * a new destination while the one a link already points at stays, in the
 * wizard's lists and on save; and the classic edit screen's Delete puts the
 * link in the Recycle Bin, as the File Manager does.
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

function short_link_requests_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of one function in a source file, up to the next top-level
// function.
function short_link_requests_test_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// Cast, a list read as "Array" (a link named Array) or, as an id, as 1: the
// rename, the change, the copy and the delete then landed on link 1.
function test_short_link_list_values_are_not_given()
{
	pg_assert_same('', pg_short_link_clean_name(array('probe')), 'a name posted as a list is no name');
	pg_assert_same('a_b', pg_short_link_clean_name(' a b '), 'a name is cleaned as before');
	pg_assert_same(null, pg_short_link_by_id(array('role' => 0), array(3)), 'an id posted as a list is no id');

	$type = pg_short_link_read_request(array('destination_type' => array('product')), null);

	pg_assert_same('destination_type', $type['error'], 'a destination type posted as a list');

	$product = pg_short_link_read_request(array('destination_type' => 'product', 'catalog_detail_page_id' => '986', 'product_id' => array('31')), null);

	pg_assert_same('product_id', $product['error'], 'a product posted as a list is asked for');

	$url = pg_short_link_read_request(array('destination_type' => 'url', 'url' => array('https://example.test/')), null);

	pg_assert_same('url', $url['error'], 'an address posted as a list is asked for');
}

function test_short_link_wizard_reads_the_rest_of_a_request_as_one_value()
{
	$explorer = short_link_requests_test_source('view_folder_and_files_f.php');

	pg_assert_contains("(isset(\$request[\$key]) && is_scalar(\$request[\$key])) ? trim((string) \$request[\$key]) : ''", short_link_requests_test_function_body($explorer, 'pg_short_link_read_request'), 'every field the wizard posts');
	pg_assert_contains("(pg_short_link_modes_ready() && is_scalar(\$request['link_mode'] ?? null)) ? (string) \$request['link_mode'] : 'permanent'", $explorer, 'how it opens');
	pg_assert_contains("array_key_exists('name', \$request) && is_scalar(\$request['name'])", $explorer, 'a change keeps the name a list does not give');
}

// A list drawn before the product or the group was deleted can still post it;
// the one a link already points at stays, as the classic edit screen keeps it.
function test_short_link_save_refuses_the_bin_but_keeps_its_own()
{
	$body = short_link_requests_test_function_body(short_link_requests_test_source('view_folder_and_files_f.php'), 'pg_short_link_read_request');

	pg_assert_contains("function pg_short_link_read_request(\$request, \$user, \$current = null)", $body, 'the stored link is passed in');
	pg_assert_contains("((\$product_id !== (int) (\$current['product_id'] ?? 0)) && pg_catalog_product_binned(\$product_id))", $body, 'product');
	pg_assert_contains("((\$group_id !== (int) (\$current['product_group_id'] ?? 0)) && pg_catalog_group_binned(\$group_id))", $body, 'product group');

	$explorer = short_link_requests_test_source('view_folder_and_files_f.php');

	pg_assert_contains('$short_link_read = pg_short_link_read_request($request, $user, $short_link_row);', $explorer, 'the wizard\'s change');
	pg_assert_contains('$short_link_read = pg_short_link_read_request($request, $user);', $explorer, 'the wizard\'s create keeps nothing');
	pg_assert_contains('pg_short_link_read_request($request, null, $row);', short_link_requests_test_source('includes/api/resources/short_links.php'), 'the external API');

	$add = short_link_requests_test_source('add_short_link.php');

	pg_assert_contains("&& pg_catalog_product_binned(\$liveform->get_field_value('product_id'))", $add, 'classic add: product');
	pg_assert_contains("&& pg_catalog_group_binned(\$liveform->get_field_value('product_group_id'))", $add, 'classic add: product group');

	$edit = short_link_requests_test_source('edit_short_link.php');

	pg_assert_contains("((int) \$liveform->get_field_value('product_id') !== (int) \$short_link['product_id'])", $edit, 'classic edit: its own product stays');
	pg_assert_contains("((int) \$liveform->get_field_value('product_group_id') !== (int) \$short_link['product_group_id'])", $edit, 'classic edit: its own group stays');
}

function test_short_link_wizard_lists_leave_the_bin_out_but_keep_the_links_own()
{
	$explorer = short_link_requests_test_source('view_folder_and_files_f.php');

	pg_assert_contains("get_product_group_options(0, 0, 0, 0, array(), TRUE, 'array', TRUE, TRUE, FALSE, \$short_link_keep_group)", $explorer, 'groups: the bin is left out, the link\'s own group stays');
	pg_assert_contains('get_product_options(true, $short_link_keep_product)', $explorer, 'products: the link\'s own product stays');
	pg_assert_contains("'kept' =>", $explorer, 'the screen is told the lists are for one link');

	$screen = short_link_requests_test_source('view_folders.php');

	pg_assert_contains("item_id: shortLinkEditing ? shortLinkEditing.id : 0", $screen, 'asked with the link');
	pg_assert_contains('if (!response.kept) { shortLinkOptions = lists; }', $screen, 'lists kept for one link are not kept for the next window');
	pg_assert_contains('shortLinkOptions && !shortLinkTargetMissing(shortLinkEditing)', $screen, 'a link whose product or group is not on the kept lists asks again');
}

// liveform keeps a list as it came; escape() and preg_match() then stopped
// the request with a 500.
function test_short_link_classic_screens_read_one_value_per_field()
{
	pg_assert_contains("pg_liveform_single_values(\$liveform, array('name', 'destination_type', 'page_id', 'catalog_page_id', 'catalog_detail_page_id', 'product_group_id', 'product_id', 'url', 'file', 'tracking_code', 'link_mode', 'duration'));", short_link_requests_test_source('add_short_link.php'), 'add');
	pg_assert_contains("pg_liveform_single_values(\$liveform, array('name', 'destination_type', 'page_id', 'catalog_page_id', 'catalog_detail_page_id', 'product_group_id', 'product_id', 'url', 'file', 'tracking_code'));", short_link_requests_test_source('edit_short_link.php'), 'edit');
}

// Pages and files go to the Recycle Bin by folder: the bin folder, and the
// folders that went in with a folder sent there. A list drawn before one was
// deleted can still post it; the one a link already points at stays.
function test_short_link_save_refuses_a_binned_page_or_file_but_keeps_its_own()
{
	$body = short_link_requests_test_function_body(short_link_requests_test_source('view_folder_and_files_f.php'), 'pg_short_link_read_request');

	pg_assert_contains("((\$page_id > 0) && (\$page_id !== (int) (\$current['page_id'] ?? 0)) && pg_recycle_page_binned(\$page_id))", $body, 'page');
	pg_assert_contains("((\$file_id !== (int) (\$current['file_id'] ?? 0)) && pg_recycle_file_binned(\$file_id))", $body, 'file');

	$designer = short_link_requests_test_source('includes/fn/designer.php');

	pg_assert_contains('in_array((int)$folder, pg_recycle_bin_folder_ids(), true)', short_link_requests_test_function_body($designer, 'pg_recycle_page_binned'), 'a page is binned by its folder');
	pg_assert_contains("SELECT folder FROM files WHERE id", short_link_requests_test_function_body($designer, 'pg_recycle_file_binned'), 'a file is binned by its folder');

	$add = short_link_requests_test_source('add_short_link.php');

	pg_assert_contains("&& pg_recycle_page_binned(\$page_id)", $add, 'classic add: page');
	pg_assert_contains("&& pg_recycle_file_binned(\$liveform->get_field_value('file'))", $add, 'classic add: file');

	$edit = short_link_requests_test_source('edit_short_link.php');

	pg_assert_contains("((int) \$page_id !== (int) \$short_link['page_id'])", $edit, 'classic edit: its own page stays');
	pg_assert_contains("((int) \$liveform->get_field_value('file') !== (int) \$short_link['file_id'])", $edit, 'classic edit: its own file stays');
}

function test_short_link_lists_leave_binned_pages_and_files_out_but_keep_the_links_own()
{
	$content = short_link_requests_test_function_body(short_link_requests_test_source('includes/fn/content.php'), 'get_page_options');

	pg_assert_contains("function get_page_options(\$page_id = '', \$page_type = '', \$access = 'edit', \$include_recycled = true, \$keep_id = 0)", $content, 'every other list is drawn as before');
	pg_assert_contains("if ((((string) \$row['folder_archived'] !== '0') || (\$binned && (\$include_recycled == false))) && !\$kept) {", $content, 'archived and binned pages are left out unless kept');
	pg_assert_contains("' [' . lang('IN RECYCLE BIN') . ']'", $content, 'the kept page is marked');

	$files = short_link_requests_test_function_body(short_link_requests_test_source('includes/fn/forms.php'), 'get_file_options');

	pg_assert_contains("function get_file_options(\$design = FALSE, \$keep_id = 0)", $files, 'files');
	pg_assert_contains("if ((int) \$row['id'] !== \$keep_id) {", $files, 'binned files are left out unless kept');
	pg_assert_contains('pg_recycle_parked_original($file_name, $row[\'id\'])', $files, 'the kept file is shown under the name it had');

	$explorer = short_link_requests_test_source('view_folder_and_files_f.php');

	pg_assert_contains("get_page_options('', '', 'edit', false, \$short_link_keep_page)", $explorer, 'wizard: pages');
	pg_assert_contains("get_page_options('', 'catalog detail', 'edit', false, \$short_link_keep_page)", $explorer, 'wizard: catalog detail pages');
	pg_assert_contains('get_file_options(true, $short_link_keep_file)', $explorer, 'wizard: files');
	pg_assert_contains('pg_recycle_page_binned($short_link_keep_page)', $explorer, 'wizard: lists kept for a binned page are for one link');
	pg_assert_contains('pg_recycle_file_binned($short_link_keep_file)', $explorer, 'wizard: lists kept for a binned file are for one link');

	$screen = short_link_requests_test_source('view_folders.php');

	pg_assert_contains("return !listed(shortLinkOptions.page, item.page_id);", $screen, 'a page link asks again when its page is not on the kept lists');
	pg_assert_contains("return !listed(shortLinkOptions.file, item.file_id);", $screen, 'a file link asks again when its file is not on the kept lists');

	$edit = short_link_requests_test_source('edit_short_link.php');

	pg_assert_contains("get_page_options('', '', 'edit', FALSE, \$short_link['page_id'])", $edit, 'classic edit: pages');
	pg_assert_contains("get_file_options(\$design=true, \$short_link['file_id'])", $edit, 'classic edit: files');
	pg_assert_contains("get_page_options('', 'catalog', 'edit', FALSE)", short_link_requests_test_source('add_short_link.php'), 'classic add: pages');
}

// The classic edit screen deleted the row outright while the File Manager and
// the External API put the link in the Recycle Bin.
function test_short_link_classic_delete_goes_to_the_bin()
{
	$edit = short_link_requests_test_source('edit_short_link.php');

	pg_assert_contains('pg_short_link_to_bin($short_link[\'id\'], $user);', $edit, 'Delete bins the link');
	pg_assert_contains("lang('This short link will be moved to the Recycle Bin.')", $edit, 'the confirmation says so');
	pg_assert_contains("lang('This short link is in the recycle bin. Restore it in the file manager before changing it.')", $edit, 'a binned link is not opened');

	$explorer = short_link_requests_test_source('view_folder_and_files_f.php');

	pg_assert_contains('pg_short_link_to_bin($short_link_row[\'id\'], $user);', $explorer, 'the File Manager uses the same step');
	pg_assert_contains("db(\"UPDATE short_links SET recycled = '1' WHERE id = '\" . e(\$short_link_id) . \"'\");", short_link_requests_test_function_body($explorer, 'pg_short_link_to_bin'), 'the row is flagged');

	pg_assert_contains("' AND ' . pg_short_link_live_filter()", short_link_requests_test_source('view_short_links.php'), 'the classic list leaves binned links out');
}

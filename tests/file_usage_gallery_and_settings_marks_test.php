<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the file manager's "Where used" gallery photo sources (product
 * and product group photos kept by name in the *_images_xref tables) and for
 * the Settings image pickers that mark a stored file that no longer exists.
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

// Both gallery tables are sources, matched on file_name and linked to the
// screen that edits the owner.
function test_file_usage_gallery_sources()
{
	$sources = pg_explorer_file_usage_gallery_sources();

	pg_assert_same(2, count($sources), 'two gallery sources');
	pg_assert_same('products_images_xref', $sources[0]['xref'], 'product photos');
	pg_assert_same('product', $sources[0]['owner_column'], 'product owner column');
	pg_assert_same('products', $sources[0]['owner_table'], 'product owner table');
	pg_assert_same('edit_product.php?id=', $sources[0]['url'], 'product screen');
	pg_assert_same('product_groups_images_xref', $sources[1]['xref'], 'group photos');
	pg_assert_same('product_group', $sources[1]['owner_column'], 'group owner column');
	pg_assert_same('product_groups', $sources[1]['owner_table'], 'group owner table');
	pg_assert_same('edit_product_group.php?id=', $sources[1]['url'], 'group screen');
}

// The match is exact: a gallery row holds one file name, so a LIKE would
// list photo.jpg for photo.jpg.bak.
function test_file_usage_gallery_where_is_exact()
{
	pg_assert_same(
		"products_images_xref.file_name = 'photo.jpg'",
		pg_explorer_file_usage_exact_where('products_images_xref', 'file_name', 'photo.jpg'),
		'exact match');
}

// The item links only when the commerce screen admits the viewer.
function test_file_usage_gallery_item()
{
	$sources = pg_explorer_file_usage_gallery_sources();
	$row = array('id' => '7', 'name' => 'Blue Mug');

	pg_assert_same(
		array('name' => 'Blue Mug', 'note' => '', 'url' => 'edit_product.php?id=7'),
		pg_explorer_file_usage_gallery_item($row, $sources[0], true),
		'linked');

	pg_assert_same(
		array('name' => 'Blue Mug', 'note' => '', 'url' => ''),
		pg_explorer_file_usage_gallery_item($row, $sources[0], false),
		'plain text');
}

// The handler scans the gallery tables under the commerce gate, with the
// commerce link rule.
function test_file_usage_handler_scans_galleries()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');
	$commerce = strpos($handler, "if (defined('ECOMMERCE') && ECOMMERCE === true) {");
	$gallery = strpos($handler, 'pg_explorer_file_usage_gallery_sources() as');
	$ads = strpos($handler, "if (defined('ADS') && ADS === true) {");

	pg_assert_true(($commerce !== false) && ($gallery !== false) && ($commerce < $gallery) && ($gallery < $ads), 'inside the commerce block');
	pg_assert_contains("\$usage_open('commerce', \$usage_commerce)", substr($handler, $gallery, $ads - $gallery), 'commerce link rule');
}

// A stored file name that names no file is marked; an address, an existing
// file and an empty value are not.
function test_settings_stored_file_option()
{
	pg_assert_same(
		'<option value="old.png" selected="selected">old.png ' . lang('(file not found)') . '</option>',
		pg_stored_file_option('old.png', false),
		'gone file marked');

	pg_assert_same(
		'<option value="logo.svg" selected="selected">logo.svg</option>',
		pg_stored_file_option('logo.svg', true),
		'existing file not in the list is not marked');

	pg_assert_same(
		'<option value="https://cdn.example.com/a.png" selected="selected">https://cdn.example.com/a.png</option>',
		pg_stored_file_option('https://cdn.example.com/a.png', false),
		'an address is not a file');

	pg_assert_same(
		'<option value="&lt;b&gt;.png" selected="selected">&lt;b&gt;.png ' . lang('(file not found)') . '</option>',
		pg_stored_file_option('<b>.png', false),
		'escaped');

	pg_assert_false(pg_stored_file_needs_check('https://x.test/a.png'), 'address skips the query');
	pg_assert_false(pg_stored_file_needs_check('HTTP://x.test/a.png'), 'any case');
	pg_assert_true(pg_stored_file_needs_check('a.png'), 'file name is checked');
}

// The four pickers append their stored value through the helper.
function test_settings_pickers_use_the_mark()
{
	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');

	foreach (array('$site_icon', '$og_default_image', '$organization_logo', '$app_icon') as $value) {
		// Through the public picture pickers' own mark, which falls back to
		// pg_stored_file_option() (includes/fn/files.php).
		pg_assert_contains('pg_public_image_stored_option(' . $value . ')', $prep, $value);
	}
}

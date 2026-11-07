<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for file paths built from a request or a saved design: the path of
 * a local asset address (pg_asset_decoded_path(), includes/fn/output.php)
 * is decoded before it is checked, and the visual designer writes a design's
 * CSS/JS/JSON asset files only under a plain file name
 * (pg_designer_sync_asset_files(), includes/fn/designer.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_asset_path_refuses_parent_folders_after_decoding()
{
	foreach (array('%2e%2e/x.css', '..%2fx.css', '%2E%2E%2Fetc%2Fx.css', '../x.css', 'a\\..\\b.css', 'a%5cb.css', 'a%00.css', '') as $path) {
		pg_assert_same('', pg_asset_decoded_path($path), $path);
	}
	pg_assert_same('css/site.css', pg_asset_decoded_path('css/site.css'));
	pg_assert_same('my file.css', pg_asset_decoded_path('my%20file.css'), 'an encoded space is decoded');
}

function test_designer_asset_file_names()
{
	foreach (array('../../x.js', '/etc/x.css', '.hidden.css', 'a\\b.js', 'x.php', 'sub/site.css', '') as $name) {
		pg_assert_false(pg_migration_design_file_name_valid($name), $name);
	}
	foreach (array('site.css', 'theme.json', 'app.js') as $name) {
		pg_assert_true(pg_migration_design_file_name_valid($name), $name);
	}
}

// A design save hands the asset lists over as they came from the editor; a
// name with a folder in it would be written outside the files directory.
function test_designer_sync_writes_only_plain_asset_names()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$from = strpos($source, 'function pg_designer_sync_asset_files(');
	$body = substr($source, $from, strpos($source, "\n}\n", $from) - $from);
	$check = strpos($body, 'pg_migration_design_file_name_valid($asset[\'name\'])');
	pg_assert_true($check !== false, 'the name is checked');
	pg_assert_true($check < strpos($body, 'pg_file_write_whole('), 'before the file is written');
}

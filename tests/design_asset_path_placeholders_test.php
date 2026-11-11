<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for {path} and {software_directory} in a visual design's addresses:
 * the address a CSS or JS asset row loads on the page
 * (pg_design_asset_address()), filled before the renderer's address check,
 * and the editor's twin that fills them on the canvas (asset links, inline
 * CSS, a node's addresses and attributes) while the tree and the rows keep
 * them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- the page: an asset row's address ------------------------------------------

function test_design_asset_address_fills_the_placeholders()
{
	pg_assert_same('/css/style.css', pg_design_asset_address('{path}css/style.css', '/', 'pinegrap'));
	pg_assert_same('/site/style.css', pg_design_asset_address('{PATH}style.css', '/site/', 'pinegrap'));
	pg_assert_same('/pinegrap/frontend.min.js', pg_design_asset_address('{path}{software_directory}/frontend.min.js', '/', 'pinegrap'));
	pg_assert_same('/site/pg/x.js?v=2', pg_design_asset_address(' {Path}{SOFTWARE_DIRECTORY}/x.js?v=2 ', '/site/', 'pg'));
}

// Addresses the renderer printed before keep printing as they are.
function test_design_asset_address_keeps_other_addresses()
{
	pg_assert_same('https://cdn.jsdelivr.net/npm/x@1/x.min.css', pg_design_asset_address('https://cdn.jsdelivr.net/npm/x@1/x.min.css', '/', 'pinegrap'));
	pg_assert_same('/style.css', pg_design_asset_address('/style.css', '/', 'pinegrap'));
	pg_assert_same('/a/b.css', pg_design_asset_address("  /a/b.css\n", '/', 'pinegrap'));
}

// What the check refused before is still refused, with or without a
// placeholder: a relative address and an empty row print nothing.
function test_design_asset_address_refuses_what_is_no_address()
{
	pg_assert_same('', pg_design_asset_address('style.css', '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address('{software_directory}/x.js', '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address('', '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address(null, '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address('   ', '/', 'pinegrap'));
}

// A path holding $ or \ is copied as written.
function test_design_asset_address_path_is_literal()
{
	pg_assert_same('/a$1\\b/x.css', pg_design_asset_address('{path}x.css', '/a$1\\b/', ''));
}

// Both linked rows of the page go through it, with the raw path: the
// address is escaped once when it is printed.
function test_design_asset_address_wired_into_the_page()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_contains("\$_url = pg_design_asset_address(isset(\$_cf['content']) ? \$_cf['content'] : '', PATH, SOFTWARE_DIRECTORY);", $src);
	pg_assert_contains("\$_url = pg_design_asset_address(isset(\$_jf['content']) ? \$_jf['content'] : '', PATH, SOFTWARE_DIRECTORY);", $src);
	pg_assert_same(0, substr_count($src, "strpos(\$_url, '/') === 0"), 'the address check lives in pg_design_asset_address()');
}

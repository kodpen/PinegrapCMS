<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pages a design asset loads on: the `pages` list of a
 * style_custom_css / style_custom_js row (pg_design_asset_on_page()), how
 * its page references follow a save, a template and a copy of the design
 * (pg_designer_resolve_asset_page_refs(), pg_dtc_asset_page_keys()), how an
 * import scopes a file to the pages that used it (includes/designer_import.php),
 * and the one Bootstrap Icons sheet the site loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

// The `pages` of each row of an asset JSON, keyed by name.
function _test_asset_scope_pages($json)
{
	$out = array();
	foreach (json_decode($json, true) as $row) {
		$out[$row['name']] = isset($row['pages']) ? $row['pages'] : null;
	}
	return $out;
}

function test_designer_asset_scope_unscoped_rows_load_everywhere()
{
	pg_assert_true(pg_design_asset_on_page(array('name' => 'a.js'), 12), 'no list');
	pg_assert_true(pg_design_asset_on_page(array('name' => 'a.js', 'pages' => array()), 12), 'empty list');
	pg_assert_true(pg_design_asset_on_page('not a row', 12), 'not a row');
}

function test_designer_asset_scope_scoped_rows_load_on_their_pages()
{
	$row = array('name' => 'harita.js', 'pages' => array('12', 14));
	pg_assert_true(pg_design_asset_on_page($row, 12), 'listed as a string');
	pg_assert_true(pg_design_asset_on_page($row, '14'), 'listed as a number');
	pg_assert_false(pg_design_asset_on_page($row, 13), 'not listed');
	pg_assert_false(pg_design_asset_on_page(array('pages' => array('tab:new1_1')), 0), 'an unsaved tab matches no page');
}

function test_designer_asset_scope_save_turns_tab_refs_into_ids()
{
	$json = json_encode(array(
		array('name' => 'harita.js', 'pages' => array('tab:new1_1', '7', 'tab:gone')),
		array('name' => 'site.js'),
	));
	$out = pg_designer_resolve_asset_page_refs($json, 'tab:', array('new1_1' => 31, 'other' => 32));
	$pages = _test_asset_scope_pages($out);
	pg_assert_same(array('31', '7', 'tab:gone'), $pages['harita.js'], 'mapped, kept and unmatched refs');
	pg_assert_same(null, $pages['site.js'], 'a row on every page stays so');
}

function test_designer_asset_scope_resolve_leaves_unchanged_json_alone()
{
	$json = '[{"name":"harita.js","pages":["7"]}]';
	pg_assert_same($json, pg_designer_resolve_asset_page_refs($json, 'tab:', array('x' => 9)), 'nothing to map');
	pg_assert_same('', pg_designer_resolve_asset_page_refs('', 'tab:', array('x' => 9)), 'no assets');
	pg_assert_same($json, pg_designer_resolve_asset_page_refs($json, 'tab:', array()), 'no ids');
}

function test_designer_asset_scope_copy_maps_old_ids_to_new()
{
	$json = json_encode(array(array('name' => 'harita.js', 'pages' => array('5', '9', 'tab:k'))));
	$out = pg_designer_resolve_asset_page_refs($json, '', array('5' => 9, '9' => 12));
	$pages = _test_asset_scope_pages($out);
	pg_assert_same(array('9', '12', 'tab:k'), $pages['harita.js'], 'each id maps once, in one pass');
}

function test_designer_asset_scope_resolve_drops_repeats()
{
	$json = json_encode(array(array('name' => 'a.js', 'pages' => array('tab:a', '31'))));
	$pages = _test_asset_scope_pages(pg_designer_resolve_asset_page_refs($json, 'tab:', array('a' => 31)));
	pg_assert_same(array('31'), $pages['a.js'], 'the same page once');
}

function test_designer_asset_scope_template_names_pages_by_key()
{
	$json = json_encode(array(
		array('name' => 'harita.js', 'pages' => array('7', '8')),
		array('name' => 'eski.js', 'pages' => array('99')),
		array('name' => 'site.js'),
	));
	$pages = _test_asset_scope_pages(pg_dtc_asset_page_keys($json, array('page_ids' => array(7 => 'iletisim', 8 => 'hakkimizda'))));
	pg_assert_same(array('key:iletisim', 'key:hakkimizda'), $pages['harita.js'], 'design pages become template keys');
	pg_assert_same(array('key:'), $pages['eski.js'], 'a page the template lacks matches nothing on the new site');
	pg_assert_same(null, $pages['site.js'], 'a row on every page stays so');
}

function test_designer_asset_scope_template_keys_resolve_to_new_ids()
{
	$json = json_encode(array(array('name' => 'harita.js', 'pages' => array('key:iletisim', 'key:'))));
	$pages = _test_asset_scope_pages(pg_designer_resolve_asset_page_refs($json, 'key:', array('iletisim' => 41)));
	pg_assert_same(array('41', 'key:'), $pages['harita.js'], 'the bare key stays unmatched');
}

function test_designer_asset_scope_import_keeps_shared_files_global()
{
	$css = array(
		array('type' => 'external-css', 'name' => 'x', 'content' => 'https://cdn.example/site.css', 'page_names' => array('index')),
		array('type' => 'external-css', 'name' => 'x', 'content' => 'https://cdn.example/site.css', 'page_names' => array('iletisim')),
		array('type' => 'css', 'name' => 'harita.css', 'content' => '#map{}', 'page_names' => array('iletisim')),
	);
	$out = pg_di_scope_assets(pg_di_dedupe_assets($css), 2);
	pg_assert_same(2, count($out), 'the shared sheet once');
	pg_assert_false(isset($out[0]['page_names']), 'used by every page: loads everywhere');
	pg_assert_same(array('iletisim'), $out[1]['page_names'], 'used by one page: kept to it');
}

function test_designer_asset_scope_import_names_become_ids()
{
	$list = array(
		array('name' => 'harita.js', 'page_names' => array('iletisim', 'yok')),
		array('name' => 'kayip.js', 'page_names' => array('yok')),
		array('name' => 'site.js'),
	);
	$out = pg_di_asset_pages_by_id($list, array('iletisim' => 41));
	pg_assert_same(array('41'), $out[0]['pages'], 'saved page names become ids');
	pg_assert_false(isset($out[0]['page_names']), 'names dropped');
	pg_assert_false(isset($out[1]['pages']), 'none of its pages saved: loads everywhere');
	pg_assert_false(isset($out[2]['pages']), 'unscoped stays unscoped');
}

function test_designer_asset_scope_detached_page_keeps_what_it_loaded()
{
	$json = json_encode(array(
		array('name' => 'site.js'),
		array('name' => 'harita.js', 'pages' => array('7', '8')),
		array('name' => 'hesap.js', 'pages' => array('9')),
	));
	$pages = _test_asset_scope_pages(pg_design_assets_for_page($json, 7));
	pg_assert_same(array('site.js' => null, 'harita.js' => null), $pages, 'other pages\' files stay behind, its own load everywhere');
	$plain = '[{"name":"site.js"}]';
	pg_assert_same($plain, pg_design_assets_for_page($plain, 7), 'nothing limited: untouched');
}

function test_designer_asset_scope_one_icons_sheet()
{
	pg_assert_contains('bootstrap-icons@1.13.1', pg_design_icons_url(), 'the version the icon picker lists');
	pg_assert_true(pg_design_is_icons_url('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'), 'a design\'s own icons sheet');
	pg_assert_false(pg_design_is_icons_url('https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css'), 'the framework sheet');
}

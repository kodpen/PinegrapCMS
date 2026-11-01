<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for opening a design template and its first save: the rows already
 * placed somewhere are found in one reading of the trees
 * (_pg_tpl_used_shared_ids()), the page names chosen at opening move on to
 * the next free name when somebody took them in the meantime
 * (pg_design_template_free_page_names()), and the links between the
 * template's pages follow the new names (pg_design_template_relink()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_template_open_save_used_ids_from_pages_and_components()
{
	$pages = array(
		'{"type":"root","children":[{"type":"shared_ref","props":{"sharedId":12}}]}',
		'{"type":"root","children":[{"type":"shared_ref","props":{"sharedId":"7","x":1}}]}',
		'{"type":"root","children":[]}',
		null,
	);
	$components = array(
		array('id' => 30, 'tree_json' => '{"type":"root","children":[{"type":"shared_ref","props":{"sharedId":31}}]}'),
		// A component naming itself does not make itself used.
		array('id' => 40, 'tree_json' => '{"type":"shared_ref","props":{"sharedId":40}}'),
		array('id' => 41, 'tree_json' => ''),
	);
	$used = _pg_tpl_used_shared_ids($pages, $components);
	pg_assert_true(isset($used[12]));
	pg_assert_true(isset($used[7]));
	pg_assert_true(isset($used[31]));
	pg_assert_false(isset($used[40]));
	pg_assert_false(isset($used[1]), 'id 1 is not read out of 12');
	pg_assert_false(isset($used[30]));
}

function test_design_template_open_save_free_names_keep_free_ones()
{
	$taken = function ($name) { return false; };
	$out = pg_design_template_free_page_names(array('home' => 'anasayfa-4', 'about' => 'hakkimizda'), $taken);
	pg_assert_same(array('home' => 'anasayfa-4', 'about' => 'hakkimizda'), $out);
}

function test_design_template_open_save_free_names_move_to_next_free()
{
	$site = array('anasayfa-4' => 1, 'anasayfa-5' => 1, 'hakkimizda' => 1, 'iletisim-4' => 1);
	$taken = function ($name) use ($site) { return isset($site[mb_strtolower($name, 'UTF-8')]); };
	$out = pg_design_template_free_page_names(
		array('home' => 'anasayfa-4', 'about' => 'hakkimizda', 'contact' => 'iletisim-4', 'faq' => 'sss'),
		$taken
	);
	pg_assert_same('anasayfa-6', $out['home']);
	pg_assert_same('hakkimizda-2', $out['about']);
	pg_assert_same('iletisim-5', $out['contact']);
	pg_assert_same('sss', $out['faq']);
}

function test_design_template_open_save_free_names_never_collide_with_each_other()
{
	// iletisim-4 is taken on the site; the next free one, iletisim-5, is the
	// name another tab of the same save already carries.
	$taken = function ($name) { return $name === 'iletisim-4'; };
	$out = pg_design_template_free_page_names(array('a' => 'iletisim-4', 'b' => 'iletisim-5'), $taken);
	pg_assert_same('iletisim-6', $out['a']);
	pg_assert_same('iletisim-5', $out['b']);
}

function test_design_template_open_save_relink_follows_new_addresses()
{
	$map = array('/anasayfa-4' => '/anasayfa-6', '/iletisim-4' => '/iletisim-5');
	$tree = array(
		'type' => 'root',
		'props' => array('href' => '/anasayfa-4'),
		'children' => array(
			array('type' => 'link', 'props' => array('href' => '/iletisim-4?ref=1', 'other' => '/iletisim-40')),
			array('type' => 'text', 'props' => array('html' => '<a href="/iletisim-4#form">x</a> <a href=\'/anasayfa-4\'>y</a>')),
			array('type' => 'text', 'props' => array('html' => 'see /anasayfa-4 page', 'n' => 4)),
		),
	);
	$out = pg_design_template_relink($tree, $map);
	pg_assert_same('/anasayfa-6', $out['props']['href']);
	pg_assert_same('/iletisim-5?ref=1', $out['children'][0]['props']['href']);
	pg_assert_same('/iletisim-40', $out['children'][0]['props']['other']);
	pg_assert_same('<a href="/iletisim-5#form">x</a> <a href=\'/anasayfa-6\'>y</a>', $out['children'][1]['props']['html']);
	pg_assert_same('see /anasayfa-4 page', $out['children'][2]['props']['html']);
	pg_assert_same(4, $out['children'][2]['props']['n']);
}

function test_design_template_open_save_relink_json_text()
{
	$map = array('/anasayfa-4' => '/anasayfa-6');
	$json = '{"props":{"href":"/anasayfa-4","html":"<a href=\"/anasayfa-4\">x</a>"}}';
	pg_assert_same('{"props":{"href":"/anasayfa-6","html":"<a href=\"/anasayfa-6\">x</a>"}}', pg_design_template_relink($json, $map));
	pg_assert_same($json, pg_design_template_relink($json, array()));
}

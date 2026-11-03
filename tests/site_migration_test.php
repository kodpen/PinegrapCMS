<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Migration screen's design carry (includes/fn/site_migration.php):
 * the skipped folder subtrees, the managed file names of a design, the
 * check of a received design and which classic rows a design run leaves out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_site_migration_folder_subtrees()
{
	$rows = array(
		array('folder_id' => 1, 'folder_parent' => 0),
		array('folder_id' => 2, 'folder_parent' => 1),
		array('folder_id' => 3, 'folder_parent' => 2),
		array('folder_id' => 4, 'folder_parent' => 1),
		array('folder_id' => 5, 'folder_parent' => 4),
		array('folder_id' => 6, 'folder_parent' => 1),
	);

	pg_assert_same(array(2 => true, 3 => true), pg_migration_folder_subtrees($rows, array(2)), 'a root and the folder below it');
	pg_assert_same(array(2 => true, 3 => true, 4 => true, 5 => true), pg_migration_folder_subtrees($rows, array(2, 4, 0)), 'two roots; 0 (no such folder) is ignored');
	pg_assert_same(array(), pg_migration_folder_subtrees($rows, array(0, -1)), 'nothing to skip');
	pg_assert_same(array(2 => true, 3 => true), pg_migration_folder_subtrees($rows, array(3, 2)), 'a root inside another root is not listed twice');

	// A broken parent chain (a folder its own grandchild) ends.
	$loop = array(array('folder_id' => 7, 'folder_parent' => 8), array('folder_id' => 8, 'folder_parent' => 7));
	pg_assert_same(array(7 => true, 8 => true), pg_migration_folder_subtrees($loop, array(7)));
}

function test_site_migration_design_file_names()
{
	$css = json_encode(array(
		array('type' => 'external-css', '_file' => true, 'name' => 'site.css', 'content' => '/site.css'),
		array('type' => 'css', 'name' => 'inline.css', 'content' => 'body{}'),
		array('type' => 'external-css', 'content' => 'https://cdn.example.com/x.css'),
		array('type' => 'external-css', '_file' => true, 'name' => '../config.php'),
		array('type' => 'external-css', '_file' => true, 'name' => '.htaccess.css'),
	));
	$js = json_encode(array(
		array('type' => 'external-js', '_file' => true, 'name' => 'app.js'),
		array('type' => 'external-js', '_file' => true, 'name' => 'site.css'),
		array('type' => 'external-js', '_file' => true, 'name' => 'data.JSON'),
	));

	pg_assert_same(array('site.css', 'app.js', 'data.JSON'), pg_migration_design_file_names($css, $js), 'managed entries only, each once, no path or hidden name');
	pg_assert_same(array(), pg_migration_design_file_names('', 'not json'));
}

function test_site_migration_design_entry()
{
	$tree = array('type' => 'root', 'children' => array());
	$good = array(
		'name' => ' Shop ',
		'template' => array(
			'name' => 'Old',
			'pages' => array(array('key' => 'home', 'tree' => $tree)),
			'widgets' => array('cart' => array('tree' => $tree, 'config' => array())),
		),
		'files' => array(
			array('name' => 'site.css', 'content' => 'a{}'),
			array('name' => 'evil.php', 'content' => '<?php'),
			array('name' => 'sub/site.css', 'content' => 'a{}'),
			array('name' => 'n.js', 'content' => array()),
		),
	);
	$entry = pg_migration_design_entry($good);
	pg_assert_same('Shop', $entry['name']);
	pg_assert_same('Shop', $entry['template']['name'], 'the design keeps its own name');
	pg_assert_same(array(array('name' => 'site.css', 'content' => 'a{}')), $entry['files'], 'only managed design files');

	pg_assert_same(null, pg_migration_design_entry(array('name' => 'x')), 'no template');
	pg_assert_same(null, pg_migration_design_entry(array('template' => array('pages' => array()))), 'no pages');
	pg_assert_same(null, pg_migration_design_entry(array('template' => array('pages' => array(array('key' => 'a', 'tree' => 'x'))))), 'a page without a tree');
	$starter = $good;
	$starter['template']['widgets']['cart']['tree'] = 'starter';
	pg_assert_same(null, pg_migration_design_entry($starter), 'a widget left to the editor cannot be installed on the server');
	$noname = $good;
	unset($noname['name']);
	pg_assert_same('Old', pg_migration_design_entry($noname)['name'], 'the template name when the entry has none');
}

function test_site_migration_rows_carried_by_design()
{
	pg_assert_true(pg_migration_page_carried_by_design(array('visual_design' => 1), true));
	pg_assert_false(pg_migration_page_carried_by_design(array('visual_design' => 1), false), 'without the designs a page comes classic, as before');
	pg_assert_false(pg_migration_page_carried_by_design(array('name' => 'x'), true), 'an older source marks nothing');

	pg_assert_true(pg_migration_style_carried_by_design(array('layout' => 'visual_designer'), true));
	pg_assert_false(pg_migration_style_carried_by_design(array('layout' => 'visual_designer'), false));
	pg_assert_false(pg_migration_style_carried_by_design(array('layout' => 'one_column'), true));
}

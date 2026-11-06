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

// The install writes every css/js/json asset of the template's lists to
// FILE_DIRECTORY_PATH under its own name (pg_designer_sync_asset_files()), so
// a name the source site sends with a folder in it, a leading dot or a
// refused extension inside it is dropped from the lists before anything is
// stored.
function test_site_migration_design_entry_asset_names()
{
	$tree = array('type' => 'root', 'children' => array());
	$css = array(
		array('id' => 'a', 'type' => 'css', 'name' => 'User Styles', 'content' => 'a{}'),
		array('id' => 'b', 'type' => 'css', 'name' => 'site.css', 'content' => 'b{}'),
		array('id' => 'c', 'type' => 'css', 'name' => '../../index.css', 'content' => 'c{}'),
		array('id' => 'd', 'type' => 'css', 'name' => 'sub\\x.css', 'content' => 'd{}'),
		array('id' => 'e', 'type' => 'external-css', 'name' => '../cdn.css', 'content' => 'https://cdn.example.com/x.css'),
	);
	$js = array(
		array('id' => 'f', 'type' => 'js', 'name' => 'app.js', 'content' => 'f()'),
		array('id' => 'g', 'type' => 'js', 'name' => '../../../pinegrap/assets/js/backend.min.js', 'content' => 'g()'),
		array('id' => 'h', 'type' => 'js', 'name' => 'shell.php.js', 'content' => 'h()'),
		array('id' => 'i', 'type' => 'json', 'name' => '.hidden.json', 'content' => '{}'),
		array('id' => 'j', 'type' => 'json', 'name' => 'data.JSON', 'content' => '{}'),
	);
	$entry = pg_migration_design_entry(array('template' => array(
		'name' => 'Shop',
		'pages' => array(array('key' => 'home', 'tree' => $tree)),
		'assets' => array('css' => json_encode($css), 'js' => json_encode($js), 'head' => '<meta>'),
	)));
	$ids = function ($json) {
		return array_map(function ($a) { return $a['id']; }, json_decode($json, true));
	};

	pg_assert_same(array('a', 'b', 'e'), $ids($entry['template']['assets']['css']), 'a css asset named with a path is dropped; an inline one without a file name and an external one stay');
	pg_assert_same(array('f', 'j'), $ids($entry['template']['assets']['js']), 'a path, a refused extension and a hidden name are dropped');
	pg_assert_same('<meta>', $entry['template']['assets']['head'], 'the other assets are left as they are');

	// A list that is not JSON is not a list the install writes from.
	$odd = pg_migration_design_entry(array('template' => array(
		'pages' => array(array('key' => 'home', 'tree' => $tree)),
		'assets' => array('css' => 'not json', 'js' => array('x')),
	)));
	pg_assert_same('', $odd['template']['assets']['css']);
	pg_assert_same('', $odd['template']['assets']['js']);
}

function test_site_migration_rows_carried_by_design()
{
	pg_assert_true(pg_migration_page_carried_by_design(array('visual_design' => 1)), 'a design page never comes as an empty classic page');
	pg_assert_false(pg_migration_page_carried_by_design(array('name' => 'x')), 'an older source marks nothing');

	pg_assert_true(pg_migration_style_carried_by_design(array('layout' => 'visual_designer')));
	pg_assert_false(pg_migration_style_carried_by_design(array('layout' => 'one_column')));
}

function test_site_migration_design_only_folders()
{
	$folders = array(
		array('id' => 10, 'parent_folder_id' => 0),   // design pages only
		array('id' => 11, 'parent_folder_id' => 10),  // empty, below a design folder
		array('id' => 20, 'parent_folder_id' => 0),   // mixed
		array('id' => 21, 'parent_folder_id' => 20),  // design pages only, below a mixed one
		array('id' => 30, 'parent_folder_id' => 0),   // empty
		array('id' => 40, 'parent_folder_id' => 0),   // a classic page deeper down
		array('id' => 41, 'parent_folder_id' => 40),
	);
	$pages = array(
		array('folder_id' => 10, 'visual_design' => 1),
		array('folder_id' => 20, 'visual_design' => 0),
		array('folder_id' => 21, 'visual_design' => 1),
		array('folder_id' => 40, 'visual_design' => 1),
		array('folder_id' => 41),
	);
	$skip = pg_migration_design_only_folder_ids($folders, $pages);
	ksort($skip);
	pg_assert_same(array(10, 11, 21), array_keys($skip));
	pg_assert_same(array(), pg_migration_design_only_folder_ids(array(), $pages), 'no folders');
	pg_assert_same(array(), pg_migration_design_only_folder_ids($folders, array()), 'no pages: every folder stays');
}

function test_site_migration_uses_the_template_export_both_ways()
{
	$mig = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/site_migration.php');
	pg_assert_contains("pg_design_template_build((int)\$s['style_id']", $mig, 'the source builds a design with the template export');
	pg_assert_contains("'explicit_trees' => true", $mig, 'a widget placeholder stays a tree, so the server can install it');
	pg_assert_contains('pg_design_template_install(', $mig, 'the target installs it the way a template is installed');

	$dtc = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/design_templates_custom.php');
	pg_assert_contains('$built = pg_design_template_build($style_id, $meta);', $dtc, 'turning a design into a template uses the same builder');

	$dsg = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('pg_design_template_custom_get($id)', $dsg, 'a template written in this request is found past the cached list');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_contains("'get_designs' => array('file' => 'designer.php', 'handler' => 'pg_panel_get_designs', 'exempt' => false, 'token' => false, 'write' => false)", $actions, 'designers and administrators only, read-only');

	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/content.php');
	pg_assert_same(2, substr_count($content, "pg_migration_skipped_folders_sql("), 'get_folders and get_pages leave the bin, drafts and Workspace out');
}

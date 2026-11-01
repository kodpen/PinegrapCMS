<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photo Gallery system widget
 * (includes/fn/widgets_photo_gallery.php): which folder it shows, its
 * album and photo rows, its flags and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_photo_gallery_widget_folder_shown()
{
	// 1 the page's folder; 2 below it, 3 below 2; 4 beside it; 5 and 6 a loop.
	$parents = array(1 => 0, 2 => 1, 3 => 2, 4 => 0, 5 => 6, 6 => 5);
	$parent_of = function ($id) use ($parents) {
		return isset($parents[$id]) ? $parents[$id] : 0;
	};
	pg_assert_same(1, pg_gal_folder_shown(1, null, $parent_of), 'no album asked for');
	pg_assert_same(2, pg_gal_folder_shown(1, '2', $parent_of), 'an album of the gallery');
	pg_assert_same(3, pg_gal_folder_shown(1, '3', $parent_of), 'an album deeper down');
	pg_assert_same(1, pg_gal_folder_shown(1, '4', $parent_of), 'a folder outside the gallery is never opened');
	pg_assert_same(1, pg_gal_folder_shown(1, '5', $parent_of), 'a loop in the parents ends the walk');
	pg_assert_same(1, pg_gal_folder_shown(1, '2 OR 1', $parent_of), 'only a number');
	pg_assert_same(1, pg_gal_folder_shown(1, array('2'), $parent_of), 'not an array');
}

// A gallery whose top is the site's root holds the Recycle Bin: neither the
// bin nor a folder in it is ever opened.
function test_photo_gallery_widget_folder_shown_never_opens_the_bin()
{
	// 1 the root; 9 the bin under it, 10 binned, 11 below 10; 2 an album.
	$parents = array(1 => 0, 2 => 1, 9 => 1, 10 => 9, 11 => 10);
	$parent_of = function ($id) use ($parents) {
		return isset($parents[$id]) ? $parents[$id] : 0;
	};
	pg_assert_same(2, pg_gal_folder_shown(1, '2', $parent_of, 9), 'an album beside the bin');
	pg_assert_same(1, pg_gal_folder_shown(1, '9', $parent_of, 9), 'not the bin');
	pg_assert_same(1, pg_gal_folder_shown(1, '10', $parent_of, 9), 'not a binned folder');
	pg_assert_same(1, pg_gal_folder_shown(1, '11', $parent_of, 9), 'not a folder below a binned one');
	pg_assert_same(10, pg_gal_folder_shown(1, '10', $parent_of), 'no bin: the walk is as before');
}

function test_photo_gallery_widget_folder_setting()
{
	pg_assert_same(0, pg_gal_folder_setting(array()), 'none chosen');
	pg_assert_same(0, pg_gal_folder_setting(null), 'no settings');
	pg_assert_same(7, pg_gal_folder_setting(array('folder_id' => 7)), 'a number');
	pg_assert_same(7, pg_gal_folder_setting(array('folder_id' => '7')), 'a numeric text');
	pg_assert_same(0, pg_gal_folder_setting(array('folder_id' => -3)), 'never below 0');
	pg_assert_same(0, pg_gal_folder_setting(array('folder_id' => '7 OR 1')), 'only digits');
	pg_assert_same(0, pg_gal_folder_setting(array('folder_id' => '{{folder:photos}}')), 'an unresolved template token');
	pg_assert_same(0, pg_gal_folder_setting(array('folder_id' => array(7))), 'not a list');
}

// Empty keeps the page's folder; a chosen folder is the top; a folder that
// is gone or binned shows nothing, never the page's folder.
function test_photo_gallery_widget_top_folder()
{
	// 1 the root; 3 the page's folder; 5 a chosen folder under 1; 9 the bin, 10 binned.
	$parents = array(1 => 0, 3 => 1, 5 => 1, 9 => 1, 10 => 9, 20 => 21, 21 => 20);
	$parent_of = function ($id) use ($parents) {
		return isset($parents[$id]) ? $parents[$id] : null;
	};
	pg_assert_same(3, pg_gal_top_folder(0, 3, $parent_of, 9), 'none chosen: the page\'s folder');
	pg_assert_same(5, pg_gal_top_folder(5, 3, $parent_of, 9), 'the chosen folder');
	pg_assert_same(1, pg_gal_top_folder(1, 3, $parent_of, 9), 'the root may be chosen');
	pg_assert_same(0, pg_gal_top_folder(42, 3, $parent_of, 9), 'a deleted folder shows nothing');
	pg_assert_same(0, pg_gal_top_folder(10, 3, $parent_of, 9), 'a binned folder shows nothing');
	pg_assert_same(0, pg_gal_top_folder(9, 3, $parent_of, 9), 'the bin itself shows nothing');
	pg_assert_same(10, pg_gal_top_folder(10, 3, $parent_of, 0), 'no bin on the site');
	pg_assert_same(20, pg_gal_top_folder(20, 3, $parent_of, 9), 'a loop in the parents ends the walk');
	pg_assert_same(0, pg_gal_top_folder(0, 0, $parent_of, 9), 'no page, nothing chosen');
}

// Where used: the designed pages whose gallery widget shows the file.
function test_photo_gallery_widget_pages_showing()
{
	$chain = array(5, 1);
	$placements = array(
		array('page_id' => 11, 'page_name' => 'own-folder', 'page_folder' => 5, 'folder_id' => 0),
		array('page_id' => 12, 'page_name' => 'chosen', 'page_folder' => 3, 'folder_id' => 5),
		array('page_id' => 13, 'page_name' => 'elsewhere', 'page_folder' => 5, 'folder_id' => 3),
		array('page_id' => 14, 'page_name' => 'gone', 'page_folder' => 5, 'folder_id' => 42),
		array('page_id' => 12, 'page_name' => 'chosen', 'page_folder' => 3, 'folder_id' => 5),
	);
	$top_of = function ($folder_id, $page_folder) {
		if ($folder_id === 42) return 0;
		return ($folder_id > 0) ? $folder_id : $page_folder;
	};
	$pages = pg_gal_pages_showing($chain, $placements, $top_of);
	$ids = array();
	foreach ($pages as $p) $ids[] = $p['page_id'];
	pg_assert_same(array(11, 12), $ids, 'the page\'s folder, a chosen folder; not another folder, not a lost one; a page once');
	pg_assert_same(array(), pg_gal_pages_showing(array(), $placements, $top_of), 'no folder shows the file');
}

function test_photo_gallery_widget_folder_choices()
{
	$rows = array(
		array('id' => '1', 'name' => 'Root', 'parent_id' => '0', 'archived' => '0'),
		array('id' => '5', 'name' => 'Summer', 'parent_id' => '1', 'archived' => '1'),
		array('id' => '9', 'name' => 'Recycle Bin', 'parent_id' => '1', 'archived' => '1'),
		array('id' => '10', 'name' => 'Binned', 'parent_id' => '9', 'archived' => '0'),
	);
	pg_assert_same(array(
		array('id' => 1, 'name' => 'Root', 'parent_id' => 0, 'archived' => false),
		array('id' => 5, 'name' => 'Summer', 'parent_id' => 1, 'archived' => true),
	), pg_gal_folder_choices($rows, array(9, 10)), 'the bin and what is in it are left out');
}

function test_photo_gallery_widget_rows()
{
	$rows = pg_gal_rows(
		array(
			array('id' => 7, 'name' => 'Summer', 'photo' => 'beach day.jpg', 'photos' => 3),
			array('id' => 8, 'name' => 'Empty', 'photo' => '', 'photos' => 0),
			array('id' => 9, 'name' => '', 'photo' => '', 'photos' => 0),
		),
		array(array('id' => 20, 'name' => 'cover.png', 'description' => 'Our team')),
		'/gallery', '/');
	pg_assert_same(3, count($rows), 'albums with a name, then the photos');
	pg_assert_same('/gallery?folder_id=7', $rows[0]['__gallery_item_url'], 'an album opens on the page');
	pg_assert_same('/beach%20day.jpg', $rows[0]['__gallery_item_image_url'], 'its first photo');
	pg_assert_same('3', $rows[0]['__gallery_item_photo_count'], 'its number of photos');
	pg_assert_same('1', $rows[0]['__gallery_item_is_album'], 'an album');
	pg_assert_same('', $rows[1]['__gallery_item_image_url'], 'an empty album has no picture');
	pg_assert_same('/cover.png', $rows[2]['__gallery_item_url'], 'a photo opens the image');
	pg_assert_same('Our team', $rows[2]['__gallery_item_description'], 'its description');
	pg_assert_same('1', $rows[2]['__gallery_item_is_photo'], 'a photo');
	pg_assert_same('', $rows[2]['__gallery_item_count_label'], 'a photo has no count');
}

function test_photo_gallery_widget_flags()
{
	$album = array('__gallery_item_is_album' => '1');
	$photo = array('__gallery_item_is_album' => '');
	pg_assert_same(array('has_gallery_items' => true, 'no_gallery_items' => false, 'has_albums' => true, 'has_photos' => true, 'in_album' => false),
		pg_gal_flags(array($album, $photo), false), 'albums and photos');
	pg_assert_same(array('has_gallery_items' => false, 'no_gallery_items' => true, 'has_albums' => false, 'has_photos' => false, 'in_album' => true),
		pg_gal_flags(array(), true), 'an empty album');
}

function test_photo_gallery_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['photo_gallery']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('photo_gallery', $map['photo gallery'], 'stands in for the classic type');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_photo_gallery(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'photo_gallery'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'photo_gallery': {", $js, 'has a starter tree');
	pg_assert_contains("photo_gallery:     SW_PHOTO_GALLERY_TOKEN_GROUPS", $js, 'has a token palette');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_photo_gallery.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'photo_gallery')", $source, 'Messages named after the classic liveform');
}

function test_photo_gallery_image_types_take_webp_and_avif()
{
	$types = pg_gal_image_types();
	foreach (pg_public_image_file_types() as $type) {
		pg_assert_true(in_array($type, $types, true), 'a public image type is a photo: ' . $type);
	}
	pg_assert_true(in_array('tiff', $types, true), 'the classic TIFF types stay');
}

function test_photo_gallery_photo_alt()
{
	pg_assert_same('Our team at the lake', pg_gal_photo_alt('  Our team at the lake ', 'team.jpg'), 'the description');
	pg_assert_same('pg test galeri 1', pg_gal_photo_alt('', 'pg-test-galeri-1.jpg'), 'no description: the readable file name');
	pg_assert_same('summer day', pg_gal_photo_alt(null, 'summer__day.webp'), 'underscores too');
	pg_assert_same('ıspanak börek', pg_gal_photo_alt('', 'ıspanak-börek.png'), 'a name starting with a non-ASCII letter keeps it');
}

function test_photo_gallery_folders_showing()
{
	$parents = array(3 => 2, 2 => 1, 1 => 0, 5 => 6, 6 => 5);
	$parent_of = function ($id) use ($parents) {
		return isset($parents[$id]) ? $parents[$id] : 0;
	};
	pg_assert_same(array(3, 2, 1), pg_gal_folders_showing(3, $parent_of), 'the folder and those above it');
	pg_assert_same(array(5, 6), pg_gal_folders_showing(5, $parent_of), 'a loop ends the walk');
	pg_assert_same(array(), pg_gal_folders_showing(0, $parent_of), 'no folder');
	// Folder 2 is the Recycle Bin: no gallery opens it or a folder in it,
	// so a file there is shown by none, not by the galleries above the bin.
	pg_assert_same(array(), pg_gal_folders_showing(3, $parent_of, 2), 'a file in a folder of the bin');
	pg_assert_same(array(), pg_gal_folders_showing(2, $parent_of, 2), 'a file in the bin');
	pg_assert_same(array(5, 6), pg_gal_folders_showing(5, $parent_of, 2), 'a folder outside the bin');
}

// The classic page reads the widget's list, writes the photo's alt text
// on the image and none on the link; the custom layout's template leaves
// the image's alt empty beside the name in the link.
function test_photo_gallery_classic_page_types_and_alt()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_photo_gallery.php');
	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/photo_gallery.php');
	pg_assert_same(2, substr_count($page, 'type IN (" . pg_gal_image_types_sql() . ")'), 'both layouts read the list');
	pg_assert_false(strpos($page, "(type = 'gif')") !== false, 'no fixed list left');
	pg_assert_contains("alt=\"' . h(pg_gal_photo_alt(\$photo['description'], \$photo['name'])) . '\"", $page, 'the image alt');
	pg_assert_false(strpos($page, "'\" alt=\"' . h(\$photo['name'])") !== false, 'no alt on any link');
	pg_assert_same(2, substr_count($template, 'center-block" alt="">'), 'empty alt in the template');
}

// Where used lists the photo gallery pages that show the file.
function test_photo_gallery_where_used()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');
	pg_assert_contains('pg_gal_folders_showing($usage_file[\'folder\']', $handler, 'walks the folders above the file');
	pg_assert_contains("WHERE page_type = 'photo gallery'", $handler, 'reads the gallery pages');
	pg_assert_contains('pg_recycle_bin_folder_ids()', $handler, 'leaves the Recycle Bin out');
	pg_assert_contains('}, (int) pg_recycle_bin_folder_id());', $handler, 'a file in the bin is shown by no gallery');
	pg_assert_contains('pg_gal_widget_pages_showing($usage_gallery_folders)', $handler, 'and the designed pages whose widget shows the file');
}

// The renderer takes the widget's folder setting, checks the chosen top
// against the visitor, and never opens the Recycle Bin.
function test_photo_gallery_widget_renderer_uses_the_setting()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_photo_gallery.php');
	pg_assert_contains('$chosen = pg_gal_folder_setting($cfg);', $source, 'reads the setting');
	pg_assert_contains("pg_gal_top_folder(\$chosen, \$page_folder, '_pg_gal_parent_or_null', \$bin_id)", $source, 'the top folder');
	pg_assert_contains('if ($chosen > 0 && $root_id > 0 && !check_view_access($root_id)) $root_id = 0;', $source, 'a chosen folder the visitor may not view shows nothing');
	pg_assert_contains("\$_GET['folder_id'] : null, \$parent_of, \$bin_id)", $source, 'albums walk with the bin');
}

// The panel's Folder picker (_swFolderOptions): the folders as a tree under
// their parents, the chosen one selected, a lost choice reported.
function test_photo_gallery_widget_folder_picker_twin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, "\n    function _swFolderOptions(");
	pg_assert_true($from !== false, 'the designer has _swFolderOptions()');
	$to = strpos($source, "\n    }\n", $from + 1);
	$folders = array(
		array('id' => 1, 'name' => 'Root', 'parent_id' => 0, 'archived' => false),
		array('id' => 5, 'name' => 'Summer <b>', 'parent_id' => 1, 'archived' => true),
		array('id' => 6, 'name' => 'Orphan', 'parent_id' => 99, 'archived' => false),
		array('id' => 7, 'name' => 'Loop A', 'parent_id' => 8, 'archived' => false),
		array('id' => 8, 'name' => 'Loop B', 'parent_id' => 7, 'archived' => false),
	);
	$script = 'var esc = function (s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); };'
		. 'var _sdT = function (s) { return s; };'
		. substr($source, $from, $to + 7 - $from)
		. 'var f = ' . json_encode($folders) . ';'
		. 'console.log(JSON.stringify({ chosen: _swFolderOptions(f, 5), lost: _swFolderOptions(f, 42), none: _swFolderOptions(f, 0) }));';
	$file = tempnam(sys_get_temp_dir(), 'pggallery');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$r = json_decode(implode("\n", $out), true);
	$nbsp = "\u{00a0}\u{00a0}";
	pg_assert_same('<option value="1">Root</option><option value="5" selected>' . $nbsp . 'Summer &lt;b&gt; [ARCHIVED]</option><option value="6">Orphan</option>',
		$r['chosen']['html'], 'a tree, escaped, archived marked, an orphan at the top, a loop left out');
	pg_assert_true($r['chosen']['found'], 'the chosen folder is listed');
	pg_assert_false($r['lost']['found'], 'a gone or binned folder is reported');
	pg_assert_false(strpos($r['none']['html'], 'selected') !== false, 'nothing chosen: no folder selected');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager's and the Catalog Manager's tree in their main
 * menu panels: who may read it (pg_explorer_side_allowed()), the branches a
 * request may ask for, the fixed entries and their gates, the box the menu
 * prints, the rows, and the read-only explorer_side action.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_explorer_side_gate_matches_the_rows_and_screens()
{
	// The files: role < 3, or role 3 with a folder edit right.
	pg_assert_true(pg_explorer_side_allowed('files', 0, false, false, false), 'administrator');
	pg_assert_true(pg_explorer_side_allowed('files', 2, false, false, false), 'manager');
	pg_assert_true(pg_explorer_side_allowed('files', 3, false, true, false), 'role 3 with an edit right');
	pg_assert_false(pg_explorer_side_allowed('files', 3, true, false, true), 'role 3 without one, whatever else they have');

	// The catalog: the store switched on and the commerce right.
	pg_assert_true(pg_explorer_side_allowed('catalog', 1, false, false, true), 'designer, store on');
	pg_assert_true(pg_explorer_side_allowed('catalog', 3, true, false, true), 'role 3 with the commerce right');
	pg_assert_false(pg_explorer_side_allowed('catalog', 3, false, true, true), 'role 3 without it');
	pg_assert_false(pg_explorer_side_allowed('catalog', 0, true, true, false), 'store off: nobody');

	pg_assert_false(pg_explorer_side_allowed('erp', 0, true, true, true), 'no other area');
	pg_assert_false(pg_explorer_side_allowed('', 0, true, true, true), 'no area');
}

function test_explorer_side_ids_are_whole_numbers_once_and_capped()
{
	pg_assert_same(array(0, 5, 12), pg_explorer_side_ids(array('0', 5, '12', '5')), 'each once');
	pg_assert_same(array(3), pg_explorer_side_ids(array('-1', '2.5', 'x', '1 OR 1', null, array(4), '3')), 'nothing but whole numbers');
	pg_assert_same(array(7), pg_explorer_side_ids('7'), 'a single value');
	pg_assert_same(array(), pg_explorer_side_ids(''), 'nothing');
	pg_assert_same(25, count(pg_explorer_side_ids(range(1, 60))), 'at most 25');
	pg_assert_same(array(1, 2), pg_explorer_side_ids(array(1, 2, 3), 2), 'its own limit');
}

function test_explorer_side_quick_entries_follow_the_screen_gates()
{
	$keys = function ($items) {
		return array_map(function ($item) { return $item['key']; }, $items);
	};

	pg_assert_same(array('files', 'images', 'pages', 'short_links', 'shared', 'backups'), $keys(pg_explorer_side_quick('files', 2, false)), 'staff');
	pg_assert_same(array('files', 'images', 'pages'), $keys(pg_explorer_side_quick('files', 3, false)), 'role 3: no short links, shared folders or backups');
	pg_assert_same(array('files', 'images', 'pages', 'erp'), $keys(pg_explorer_side_quick('files', 3, true)), 'the ERP section where it is drawn');
	pg_assert_same(array('products', 'variants'), $keys(pg_explorer_side_quick('catalog', 0, true)), 'the catalog has no ERP and no file views');

	foreach (array_merge(pg_explorer_side_quick('files', 0, true), pg_explorer_side_quick('catalog', 0, false)) as $item) {
		pg_assert_true((bool) preg_match('/^view_(folders|product_groups)\.php\?view=[a-z_]+$/', $item['url']), $item['key'] . ' is a view of its screen');
	}
}

function test_explorer_side_screen_is_the_explorer_only()
{
	pg_assert_same('files', pg_explorer_side_screen('/pinegrap/view_folders.php'), 'the file manager');
	pg_assert_same('catalog', pg_explorer_side_screen('/pinegrap/view_product_groups.php'), 'the catalog manager');
	pg_assert_same('', pg_explorer_side_screen('/pinegrap/view_folders.php', true), 'not in the toolbar frame');
	pg_assert_same('', pg_explorer_side_screen('/pinegrap/view_pages.php'), 'elsewhere');
	pg_assert_same('', pg_explorer_side_screen(''), 'nothing');
}

function test_explorer_side_markup_links_and_inplace_box()
{
	$args = array(
		'area' => 'files', 'api' => '/pinegrap/api.php', 'base' => '/pinegrap/', 'target' => '',
		'quick' => pg_explorer_side_quick('files', 3, false),
		'loading' => 'Loading <now>', 'error' => 'Oops & "so"', 'retry' => 'Again');
	$html = pg_explorer_side_markup($args);

	pg_assert_contains('id="explorer-side-files"', $html, 'its id');
	pg_assert_contains('<a class="tree-row" href="/pinegrap/view_folders.php?view=files" data-pg-menu-skip>', $html, 'the fixed entries are links the current mark passes by');
	pg_assert_false(strpos($html, 'view=short_links') !== false, 'no short links for role 3');
	pg_assert_false(strpos($html, 'data-pg-menu-auto') !== false, 'links never open by themselves');
	pg_assert_contains('data-explorer-side-error="Oops &amp; &quot;so&quot;"', $html, 'escaped');
	pg_assert_contains('Loading &lt;now&gt;', $html, 'escaped skeleton');
	pg_assert_contains('data-explorer-side-root', $html, 'the tree it fills');

	$frame = pg_explorer_side_markup(array_merge($args, array('target' => '_parent')));
	pg_assert_contains('data-pg-menu-skip', $frame, 'still skipped');
	pg_assert_contains(' target="_parent"', $frame, 'the toolbar frame opens them in the page');

	$inplace = pg_explorer_side_markup(array('area' => 'catalog', 'inplace' => true));
	pg_assert_same('<div id="explorer-side-catalog" class="pg-explorer-side" data-explorer-side="catalog" data-explorer-side-inplace data-pg-menu-auto></div>', $inplace, 'on its own screen an empty box that opens by itself');
}

function test_explorer_side_rows()
{
	pg_assert_same(
		array('id' => 4, 'name' => 'Docs <b>', 'access' => 'private', 'archived' => true, 'children' => true),
		pg_explorer_side_folder_row(array('folder_id' => '4', 'folder_name' => 'Docs <b>', 'folder_archived' => '1'), 'private', true),
		'a folder');
	pg_assert_same('', pg_explorer_side_folder_row(array('folder_id' => '4', 'folder_name' => 'x'), 'private" onload=', false)['access'], 'only a known access type becomes a class');
	pg_assert_same(
		array('id' => 9, 'name' => 'Shoes', 'variants' => true, 'enabled' => false, 'children' => false),
		pg_explorer_side_group_row(array('id' => 9, 'name' => 'Shoes', 'display_type' => 'select', 'enabled' => 0), false),
		'a variant set, unpublished');
}

function test_explorer_side_action_is_registered_read_only()
{
	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['explorer_side']), 'listed');
	pg_assert_same('explorer_side.php', $actions['explorer_side']['file'], 'its file');
	pg_assert_true($actions['explorer_side']['exempt'] && $actions['explorer_side']['token'], 'its own checks, with the token');
	pg_assert_false($actions['explorer_side']['write'], 'read only');
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/panel/explorer_side.php'), 'the file is there');
}

function test_explorer_side_handler_checks_and_reads_only()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/explorer_side.php');

	pg_assert_true(strpos($source, '$user = validate_user();') < strpos($source, 'validate_token();'), 'the session, then the token');
	pg_assert_true(strpos($source, 'validate_token();') < strpos($source, 'pg_explorer_side_allowed('), 'then the gate');
	pg_assert_contains("respond(array('status' => 'error', 'message' => lang('Access denied')));", $source, 'a refusal in JSON');
	pg_assert_contains('global $user, $folders_that_user_has_access_to;', $source, 'the globals the access checks read');
	pg_assert_contains('pg_explorer_release_session();', $source, 'the session let go');
	pg_assert_contains("header('Cache-Control: private, no-store');", $source, 'never cached');
	pg_assert_false(strpos($source, "\$_SESSION['software']") !== false, 'never the explorer\'s place in the session');
	pg_assert_contains('pg_explorer_child_folders($node, $user, $folders_that_user_has_access_to)', $source, 'folders only from the explorer\'s rule');
	pg_assert_contains('pg_catalog_child_groups($node)', $source, 'live groups only');
}

function test_explorer_side_in_the_menu_rows_only_under_their_gates()
{
	$menu = pg_test_function_source('output_menu');

	// Inside the File Manager row's gate ((role < 3) || no_acl_check()) and
	// the Catalog Manager row's (ECOMMERCE, USER_MANAGE_ECOMMERCE).
	$files = strpos($menu, "pg_menu_explorer_side('files'");
	$catalog = strpos($menu, "pg_menu_explorer_side('catalog'");
	pg_assert_true(($files !== false) && ($files > strpos($menu, "if ((\$user['role'] < 3) || (no_acl_check(\$user['id']) == true)) {")) && ($files < strpos($menu, '// PAGES')), 'the files tree in the File Manager row');
	pg_assert_true(($catalog !== false) && ($catalog > strpos($menu, "\$menu_items[12]['id'] = 12;")) && ($catalog < strpos($menu, '// ECOMMERCE > Offers')), 'the catalog tree in the Catalog Manager row');
	pg_assert_same(1, substr_count($menu, "pg_menu_explorer_side('files'"), 'once');
	pg_assert_same(1, substr_count($menu, "pg_menu_explorer_side('catalog'"), 'once');

	// No tree query with the page: the branches are asked for later.
	foreach (array('pg_explorer_child_folders', 'pg_catalog_child_groups', 'explorer_side.php') as $call) {
		pg_assert_false(strpos($menu . pg_test_function_source('pg_menu_explorer_side'), $call) !== false, 'the menu does not call ' . $call);
	}

	$side = pg_test_function_source('pg_menu_explorer_side');
	pg_assert_contains("pg_explorer_side_quick(\$area, (int) \$user['role'], \$erp)", $side, 'the fixed entries under the screen\'s gates');
	pg_assert_contains("\$erp = defined('ERP_ENABLED') && ERP_ENABLED && ((\$user['role'] < 3) || !empty(\$user['manage_erp']));", $side, 'the ERP section\'s own rule (view_folders.php)');
	pg_assert_contains("'target'  => (\$toolbar == true) ? '_parent' : '',", $side, 'the toolbar frame opens them in the page');
	pg_assert_contains('pg_menu_navigator(true);', $side, 'its own screen is a navigator');

	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/explorer_side.js'), $bundles['assets/js/explorer_side.min.js'] ?? null, 'its bundle');
}

function test_explorer_screen_moves_its_tree_into_the_menu()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	// Moved whole, right after the pane and before the screen's script, into
	// the box its own area's panel prints.
	$pane = strpos($source, '<div id="explorer_tree_pane" class="d-none lg:d-block" data-area="<?php echo $explorer_area; ?>">');
	$move = strpos($source, "var box = document.getElementById('explorer-side-<?php echo \$explorer_area; ?>');");
	pg_assert_true(($pane !== false) && ($move !== false) && ($pane < $move) && ($move < strpos($source, "var state = {")), 'the move runs after the pane, before the explorer starts');
	pg_assert_contains("if (pane && box && box.hasAttribute('data-explorer-side-inplace')) {", $source, 'only into the in-place box');

	// The areas hide each other's rows wherever the pane is.
	pg_assert_contains(':is(#explorer_app, #explorer_tree_pane)[data-area="catalog"] .area-files { display: none !important; }', $source, 'files rows off in the catalog');
	pg_assert_contains(':is(#explorer_app, #explorer_tree_pane)[data-area="files"] .area-catalog { display: none !important; }', $source, 'catalog rows off in the files');
	pg_assert_contains('#explorer_main > #explorer_tree_pane, #explorer_preview_pane { display: none !important; }', $source, 'the phone hides only a pane left in the screen');

	// No switch for it any more, and no stored "off" to obey.
	pg_assert_false(strpos($source, 'tree_toggle_button') !== false, 'no Folder tree switch');
	pg_assert_false(strpos($source, 'pg_explorer_tree_open') !== false, 'no stored tree switch');

	// Its own key per area, shared with explorer_side.js.
	pg_assert_contains("var TREE_KEY = 'pg_explorer_tree_' + state.area;", $source, 'expansion kept per area');
	pg_assert_contains("this.openKey = 'pg_explorer_tree_' + this.area;", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/explorer_side.js'), 'the same key in the links tree');

	// The tree menu keeps a widened rail open; a navigating click puts it away.
	pg_assert_contains('<ul id="explorer_menu" class="menu" tabindex="-1" data-pg-menu-hold></ul>', $source, 'the tree menu holds the menu');
	pg_assert_contains('window.pgMenu.dismiss();', $source, 'the drawer and a widened rail make way');
	pg_assert_contains("#pg-chat-root, #explorer_menu, .menu.show, #menu');", $source, 'keys in the main menu are its own');

	// The tour says where the tree is now and is seen once more.
	pg_assert_contains("'explorer_catalog.4' : 'explorer_files.4'", $source, 'tour keys raised');
	pg_assert_false(strpos($source, 'The folder tree and the preview panel are switched on and off') !== false, 'no word of the old switch');
	pg_assert_contains("if ((entry.at === 'tree') || (entry.at === 'all_row')) {", $source, 'tree steps skip a tree off the screen');
}

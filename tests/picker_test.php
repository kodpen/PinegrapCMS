<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the picker's registry and endpoint (includes/fn/picker.php,
 * picker.php): the kinds and their fields, request checks and page sizes,
 * who may ask for which kind, the folder rule, the conditions each kind
 * builds, the folded name match, paging, items and stored value states.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function picker_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_picker_kinds()
{
	$kinds = pg_picker_kinds();

	pg_assert_same(array('file', 'image', 'public_image', 'site_icon', 'page'), array_keys($kinds));

	foreach ($kinds as $name => $kind) {
		foreach (array('source', 'types', 'public', 'design', 'archived', 'value', 'role', 'tree', 'pictures', 'upload') as $field) {
			pg_assert_true(array_key_exists($field, $kind), $name . ' names ' . $field);
		}
	}

	pg_assert_same('files', $kinds['file']['source']);
	pg_assert_same('pages', $kinds['page']['source']);
	pg_assert_same('id', $kinds['file']['value'], 'a short link stores the file id');
	pg_assert_same('id', $kinds['page']['value'], 'a page field stores the page id');
	pg_assert_same('name', $kinds['image']['value'], 'a picture field stores the name');
	pg_assert_same('name', $kinds['public_image']['value']);
	pg_assert_same(array('png', 'svg'), $kinds['site_icon']['types']);
	pg_assert_same(pg_public_image_file_types(), $kinds['public_image']['types']);
	pg_assert_same(pg_picker_image_types(), $kinds['image']['types']);
	pg_assert_true($kinds['site_icon']['public'] && $kinds['public_image']['public'], 'the settings pictures are public');
	pg_assert_false($kinds['image']['public'] || $kinds['file']['public']);
	pg_assert_same(2, $kinds['site_icon']['role'], 'behind the settings gate');
	pg_assert_same(3, $kinds['page']['role']);
	pg_assert_true($kinds['image']['pictures'] && !$kinds['file']['pictures'] && !$kinds['page']['pictures']);
	pg_assert_true($kinds['file']['upload'] && !$kinds['page']['upload'], 'no upload of pages');
	pg_assert_true($kinds['page']['archived'], 'pages in archived folders are listed, marked and not choosable');

	// The search's kinds are the registry's under their names.
	foreach (pg_file_picker_kinds() as $name => $kind) {
		pg_assert_same($kinds[$name], $kind, $name . ' moved into the registry');
	}

	pg_assert_same(array('list', 'search', 'tree', 'resolve', 'upload'), pg_picker_requests());
}

function test_picker_access_verdict()
{
	foreach (array(0, 1, 2) as $role) {
		foreach (array_keys(pg_picker_kinds()) as $kind) {
			pg_assert_same('ok', pg_picker_access_verdict($kind, $role, false), $kind . ' for role ' . $role);
		}
	}

	foreach (array('file', 'image', 'page') as $kind) {
		pg_assert_same('ok', pg_picker_access_verdict($kind, 3, true), $kind . ': a User with edit rights');
		pg_assert_same('denied', pg_picker_access_verdict($kind, '3', false), $kind . ': a User without edit rights');
	}

	pg_assert_same('denied', pg_picker_access_verdict('site_icon', 3, true), 'a User never picks the settings pictures');
	pg_assert_same('denied', pg_picker_access_verdict('public_image', 3, true));
	pg_assert_same('unknown', pg_picker_access_verdict('', 0, false));
	pg_assert_same('unknown', pg_picker_access_verdict('folder', 0, false), 'no kind the registry lacks');
	pg_assert_same('unknown', pg_picker_access_verdict(array('file'), 0, false), 'a kind posted as a list');
	pg_assert_same('unknown', pg_picker_access_verdict('page', 0, false, pg_file_picker_kinds()), 'a narrower list');
}

function test_picker_request_checks()
{
	pg_assert_same('kind', pg_picker_request(array())['error']);
	pg_assert_same('kind', pg_picker_request(array('kind' => 'attachments', 'request' => 'list'))['error'], 'an unknown kind');
	pg_assert_same('kind', pg_picker_request(array('kind' => array('file'), 'request' => 'list'))['error']);
	pg_assert_same('request', pg_picker_request(array('kind' => 'file'))['error']);
	pg_assert_same('request', pg_picker_request(array('kind' => 'file', 'request' => 'delete'))['error'], 'an unknown request');
	pg_assert_same('request', pg_picker_request(array('kind' => 'file', 'request' => array('list')))['error']);

	$request = pg_picker_request(array('kind' => 'page', 'request' => 'search', 'q' => '  Hakkımızda  '));

	pg_assert_same('', $request['error']);
	pg_assert_same('Hakkımızda', $request['q']);
	pg_assert_same(0, $request['folder']);
	pg_assert_same(1, $request['page']);
	pg_assert_same(50, $request['per_page'], 'the default page size');
	pg_assert_same(array(0), $request['node_ids'], 'the tree starts at the top');
	pg_assert_same(100, mb_strlen(pg_picker_request(array('kind' => 'file', 'request' => 'search', 'q' => str_repeat('ş', 150)))['q'], 'UTF-8'), 'the text is cut');
	pg_assert_same('', pg_picker_request(array('kind' => 'file', 'request' => 'search', 'q' => array('a')))['q']);
}

function test_picker_request_page_size_bounds()
{
	$read = function ($input) {
		return pg_picker_request(array_merge(array('kind' => 'file', 'request' => 'list'), $input));
	};

	pg_assert_same(100, $read(array('per_page' => '5000'))['per_page'], 'held at the largest');
	pg_assert_same(50, $read(array('per_page' => '0'))['per_page'], 'none asked');
	pg_assert_same(50, $read(array('per_page' => '-3'))['per_page']);
	pg_assert_same(50, $read(array('per_page' => '1e3'))['per_page'], 'not a whole number');
	pg_assert_same(20, $read(array('per_page' => '20'))['per_page']);
	pg_assert_same(1, $read(array('page' => '0'))['page']);
	pg_assert_same(3, $read(array('page' => '3'))['page']);
	pg_assert_same(1000, $read(array('page' => '999999'))['page'], 'held at the highest page');
	pg_assert_same(7, $read(array('folder' => '7'))['folder']);
	pg_assert_same(0, $read(array('folder' => '7 OR 1=1'))['folder']);
	pg_assert_same(0, $read(array('folder' => array(7)))['folder']);
	pg_assert_same(array(0, 4, 9), $read(array('node_ids' => array('0', '4', 'x', '4', 9, -2)))['node_ids']);
	pg_assert_same(25, count($read(array('node_ids' => range(1, 60)))['node_ids']));
}

function test_picker_request_values()
{
	pg_assert_same(array(4, 9), pg_picker_request(array('kind' => 'page', 'request' => 'resolve', 'values' => array('4', '0', 'x', 9, '4')))['values'], 'whole ids for an id kind');
	pg_assert_same(array(12), pg_picker_request(array('kind' => 'file', 'request' => 'resolve', 'value' => '12'))['values'], 'one value');
	pg_assert_same(array('logo.png', 'Işık.svg'), pg_picker_request(array('kind' => 'image', 'request' => 'resolve', 'values' => array('logo.png', ' ', 'Işık.svg', array('x'), str_repeat('a', 256))))['values'], 'names for a name kind');
	pg_assert_same(50, count(pg_picker_values(range(1, 80), 'id')));
}

// Never the bin; a role 3 user only within the folders they may edit.
function test_picker_folder_allowed()
{
	pg_assert_true(pg_picker_folder_allowed(4, array(9, 10), null), 'a manager uses any folder');
	pg_assert_false(pg_picker_folder_allowed(9, array(9, 10), null), 'never the Recycle Bin');
	pg_assert_false(pg_picker_folder_allowed('10', array('9', '10'), null), 'nor a folder inside it');
	pg_assert_false(pg_picker_folder_allowed(0, array(), null), 'no folder 0');
	pg_assert_true(pg_picker_folder_allowed('5', array(9), array('3', 5)), 'a granted folder');
	pg_assert_false(pg_picker_folder_allowed(6, array(9), array(3, 5)), 'not a folder not granted');
	pg_assert_false(pg_picker_folder_allowed(9, array(9), array(9)), 'a grant never opens the bin');
	pg_assert_false(pg_picker_folder_allowed(3, array(), array()), 'no grant, no folder');
}

function test_picker_files_where()
{
	$kinds = pg_picker_kinds();

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (type IN ('png', 'svg')) AND ((attachment = 0) AND (folder IN (1, 4))) AND (folder NOT IN (9, 10)) AND (folder = '4')",
		pg_picker_files_where($kinds['site_icon'], '(attachment = 0) AND (folder IN (1, 4))', array(9, '10', 9), null, '4'),
		'one folder of the site icon kind');

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (folder NOT IN (9)) AND (folder IN (3, 5))",
		pg_picker_files_where($kinds['file'], '', array(9), array('3', 5, 3)),
		'a User kept to their folders');

	pg_assert_contains("(type IN ('gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'svg', 'webp', 'avif'))", pg_picker_files_where($kinds['image'], '', array(), null));
	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (design = 0) AND (type IN ('jpg'))",
		pg_picker_files_where(array('types' => array('JPG', "png') OR (1=1"), 'design' => false), '', array(), null),
		'only plain types; design files left out when the kind says so');
	pg_assert_contains('(folder IN (-1))', pg_picker_files_where($kinds['public_image'], '', array(), null), 'a public kind without its condition lists nothing');
}

function test_picker_pages_where()
{
	$kinds = pg_picker_kinds();

	pg_assert_same(
		"(page.page_name != '') AND (page.page_folder NOT IN (9, 10)) AND (page.page_folder IN (3)) AND (page.page_folder = '3')",
		pg_picker_pages_where($kinds['page'], array(9, 10), array(3), 3),
		'a User\'s folder, never the bin; an archived folder\'s pages are listed to be marked');

	pg_assert_same(
		"(page.page_name != '') AND (page.page_folder IN (-1))",
		pg_picker_pages_where($kinds['page'], array(), array()),
		'no grant lists nothing');

	pg_assert_same(
		"(page.page_name != '') AND (folder.folder_archived = '0')",
		pg_picker_pages_where(array('archived' => false), array(), null),
		'a kind without archived pages leaves them out');

	pg_assert_same(
		"(page.page_name != '') AND (page.page_type IN ('standard', 'form list view'))",
		pg_picker_pages_where(array('types' => array('standard', 'Form List View', "x') OR ('1"), 'archived' => true), array(), null),
		'page types of the kind; anything but a plain word dropped');
}

// The same match as the file search: folded, Turkish İ/ı included, names
// starting with the text first.
function test_picker_folded_match()
{
	pg_assert_same('_s_k', pg_picker_like_term('Işık'));
	pg_assert_same('_stanbul', pg_picker_like_term(' İstanbul '));
	pg_assert_same('50\\%\\_off', pg_picker_like_term('50%_off'));

	$rows = array(
		array('id' => 1, 'name' => 'acon.png'),
		array('id' => 2, 'name' => 'dark-icon.png'),
		array('id' => 3, 'name' => 'Işık-icon.svg'),
		array('id' => 4, 'name' => 'icon.png'),
		array('id' => 5, 'name' => ''),
		array('id' => 6, 'name' => 'İstanbul.jpg'),
	);

	pg_assert_same(array(4, 2, 3), array_column(pg_picker_matches($rows, 'İCON'), 'id'));
	pg_assert_same(array(3), array_column(pg_picker_matches($rows, 'isik'), 'id'), '"isik" finds "Işık"');
	pg_assert_same(array(3), array_column(pg_picker_matches($rows, 'IŞIK'), 'id'));
	pg_assert_same(array(6), array_column(pg_picker_matches($rows, 'ist'), 'id'), '"ist" finds "İstanbul"');
	pg_assert_same(array(), pg_picker_matches($rows, '  '));

	$pages = array(array('page_id' => 8, 'page_name' => 'Hakkımızda'), array('page_id' => 9, 'page_name' => 'İletişim'));

	pg_assert_same(array(9), array_column(pg_picker_matches($pages, 'iletisim', 'page_name'), 'page_id'), 'pages by page_name');
	pg_assert_same(array(8), array_column(pg_picker_matches($pages, 'HAKKIMIZ', 'page_name'), 'page_id'));

	// The file search answers the same names.
	pg_assert_same(
		array_column(pg_picker_matches($rows, 'icon'), 'name'),
		array_column(pg_file_picker_results($rows, 'icon', 'name', 30), 'text'));
}

function test_picker_paging()
{
	pg_assert_same(0, pg_picker_offset(1, 50));
	pg_assert_same(100, pg_picker_offset(3, 50));
	pg_assert_same(0, pg_picker_offset(0, 0));
	pg_assert_same(array('items' => array(1, 2), 'more' => true), pg_picker_page_slice(array(1, 2, 3), 2), 'the extra row tells a next page');
	pg_assert_same(array('items' => array(1, 2), 'more' => false), pg_picker_page_slice(array(1, 2), 2));
	pg_assert_same(array('items' => array(), 'more' => false), pg_picker_page_slice(array(), 50));
}

function test_picker_items_and_states()
{
	$kinds = pg_picker_kinds();

	pg_assert_same(
		array('id' => 7, 'value' => 7, 'text' => 'a.pdf', 'type' => 'pdf', 'folder' => 3, 'size' => 0, 'width' => 0, 'height' => 0, 'modified' => 0),
		pg_picker_item($kinds['file'], array('id' => '7', 'name' => 'a.pdf', 'type' => 'pdf', 'folder' => '3')));
	pg_assert_same(
		array('size' => 2048, 'width' => 1200, 'height' => 900, 'modified' => 1762700000),
		array_intersect_key(pg_picker_item($kinds['image'], array('id' => '8', 'name' => 'mug.jpg', 'type' => 'jpg', 'folder' => '3', 'size' => '2048', 'image_width' => '1200', 'image_height' => '900', 'timestamp' => '1762700000')), array_flip(array('size', 'width', 'height', 'modified'))),
		'a file item carries what the dialog shows beside the name');
	pg_assert_same('logo.png', pg_picker_item($kinds['image'], array('id' => '7', 'name' => 'logo.png', 'type' => 'png', 'folder' => '3'))['value'], 'a name kind stores the name');
	pg_assert_same(
		array('id' => 12, 'value' => 12, 'text' => 'Home', 'type' => 'standard', 'folder' => 1, 'modified' => 0, 'mark' => ''),
		pg_picker_item($kinds['page'], array('page_id' => '12', 'page_name' => 'Home', 'page_type' => 'standard', 'page_folder' => '1')));

	pg_assert_same('missing', pg_picker_value_state(false, false, false, false));
	pg_assert_same('hidden', pg_picker_value_state(true, false, true, false), 'a folder the User may not edit tells nothing');
	pg_assert_same('binned', pg_picker_value_state(true, true, true, true));
	pg_assert_same('offline', pg_picker_value_state(true, true, false, true));
	pg_assert_same('ok', pg_picker_value_state(true, true, false, false));
}

// A page in an archived folder or in the Recycle Bin is listed with its mark
// and cannot be chosen; a binned page is told under its own name, in the
// folder it came from. The type chips group every page type.
function test_picker_page_marks_and_groups()
{
	$kinds = pg_picker_kinds();

	pg_assert_same('', pg_picker_page_mark(false, false));
	pg_assert_same('archived', pg_picker_page_mark(true, false));
	pg_assert_same('binned', pg_picker_page_mark(false, true));
	pg_assert_same('binned', pg_picker_page_mark(true, true), 'the bin, itself archived, is told as the bin');

	pg_assert_same(
		array('modified' => 1762700000, 'mark' => 'archived'),
		array_intersect_key(pg_picker_item($kinds['page'], array('page_id' => '12', 'page_name' => 'Kampanya', 'page_type' => 'standard', 'page_folder' => '4', 'page_timestamp' => '1762700000', 'folder_archived' => '1')), array_flip(array('modified', 'mark'))),
		'a page item carries when it changed and its mark');

	$binned = pg_picker_binned_page_row(array('page_id' => '15', 'page_name' => 'pgbin_15_Hakkımızda', 'page_type' => 'standard', 'page_folder' => '99', 'folder_archived' => '1', 'original_parent_id' => '4'));
	pg_assert_same(array('page_name' => 'Hakkımızda', 'page_folder' => 4, 'binned' => true), array_intersect_key($binned, array_flip(array('page_name', 'page_folder', 'binned'))), 'its own name, in the folder it came from');
	pg_assert_same(array('text' => 'Hakkımızda', 'folder' => 4, 'mark' => 'binned'), array_intersect_key(pg_picker_item($kinds['page'], $binned), array_flip(array('text', 'folder', 'mark'))));
	pg_assert_same('pgbin_7_Eski', pg_picker_binned_page_row(array('page_id' => '15', 'page_name' => 'pgbin_7_Eski', 'original_parent_id' => '4'))['page_name'], 'another row\'s parked name is kept as it is');

	pg_assert_same('standard', pg_picker_page_type_group('standard'));
	pg_assert_same('form', pg_picker_page_type_group('custom form'));
	pg_assert_same('form', pg_picker_page_type_group('form list view'));
	pg_assert_same('catalog', pg_picker_page_type_group('catalog detail'));
	pg_assert_same('calendar', pg_picker_page_type_group('calendar event view'));
	pg_assert_same('checkout', pg_picker_page_type_group('shopping cart'));
	pg_assert_same('checkout', pg_picker_page_type_group('order receipt'));
	pg_assert_same('search', pg_picker_page_type_group('search results'));
	pg_assert_same('account', pg_picker_page_type_group('login'));
	pg_assert_same('other', pg_picker_page_type_group('error'));
	pg_assert_same('other', pg_picker_page_type_group('a type of tomorrow'), 'a type no group names is Other');

	$groups = pg_picker_page_groups();
	pg_assert_same(array('standard', 'form', 'catalog', 'calendar', 'checkout', 'search', 'account', 'other'), array_keys($groups));

	foreach (array_keys(get_page_type_names()) as $type) {
		pg_assert_true(isset($groups[pg_picker_page_type_group($type)]), $type . ' has a chip');
	}
}

// The endpoint refuses in JSON before validate_user(), checks the request
// before the verdict, and the module is loaded with the file search.
function test_picker_endpoint_wired()
{
	$endpoint = picker_test_source('picker.php');

	pg_assert_true(strpos($endpoint, '(USER_LOGGED_IN !== true)') < strpos($endpoint, '$user = validate_user();'), 'no session answers in JSON');
	pg_assert_contains('$request = pg_picker_request($_GET);', $endpoint);
	pg_assert_contains("pg_picker_access_verdict(\$request['kind'], \$user['role'], ((int) \$user['role'] >= 3) && no_acl_check(\$user['id']))", $endpoint);
	pg_assert_contains("header('Content-Type: application/json; charset=utf-8');", $endpoint);
	pg_assert_contains('pg_picker_answer($request, $user)', $endpoint);
	pg_assert_true(strpos($endpoint, 'pg_picker_access_verdict(') < strpos($endpoint, 'pg_picker_answer($request'), 'the verdict before the answer');

	$module = picker_test_source('includes/fn/picker.php');

	pg_assert_contains('pg_recycle_bin_folder_ids()', $module);
	pg_assert_contains("e(pg_picker_like_term(\$query))", $module, 'the typed text is escaped');
	pg_assert_contains("array_map('e', \$request['values'])", $module, 'resolved names are escaped');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/picker.php');", picker_test_source('includes/fn/file_picker_search.php'));
}

// The breadcrumb and a search result's folder line, from the top down.
function test_picker_folder_path()
{
	$map = array(
		1 => array('folder_name' => 'Files', 'folder_parent' => 0),
		4 => array('folder_name' => 'Pictures', 'folder_parent' => 1),
		9 => array('folder_name' => 'Products', 'folder_parent' => 4),
		20 => array('folder_name' => 'Loop A', 'folder_parent' => 21),
		21 => array('folder_name' => 'Loop B', 'folder_parent' => 20),
	);

	pg_assert_same(array(array('id' => 1, 'name' => 'Files'), array('id' => 4, 'name' => 'Pictures'), array('id' => 9, 'name' => 'Products')), pg_picker_folder_path(9, $map));
	pg_assert_same(array(array('id' => 1, 'name' => 'Files')), pg_picker_folder_path('1', $map));
	pg_assert_same(array(), pg_picker_folder_path(0, $map), 'the top has no crumb');
	pg_assert_same(array(), pg_picker_folder_path(77, $map), 'a folder the map lacks');
	pg_assert_same(2, count(pg_picker_folder_path(20, $map)), 'a parent loop ends');
}

function test_picker_has_picture()
{
	$kinds = pg_picker_kinds();

	pg_assert_true(pg_picker_has_picture($kinds['site_icon'], 'icon.PNG'));
	pg_assert_true(pg_picker_has_picture($kinds['image'], 'logo.svg'));
	pg_assert_true(!pg_picker_has_picture($kinds['image'], 'notes.pdf'), 'only pictures are drawn');
	pg_assert_true(!pg_picker_has_picture($kinds['file'], 'photo.jpg'), 'a kind without pictures draws icons');
	pg_assert_true(!pg_picker_has_picture($kinds['image'], 'noextension'));
}

// Every word the dialog asks for is in the texts it gets, translated by
// lang(); the script and its settings are printed once.
function test_picker_dialog_texts_and_script()
{
	$script = picker_test_source('assets/js/pg_picker.js');
	$texts = pg_picker_texts();

	preg_match_all("/\\bt\\('([^']*)'/u", $script, $found);

	foreach (array_unique($found[1]) as $text) {
		pg_assert_true(array_key_exists($text, $texts), 'pg_picker_texts() holds ' . $text);
	}

	pg_assert_true(count($found[1]) > 30, 'the script reads its words through t()');

	$module = picker_test_source('includes/fn/picker.php');
	pg_assert_contains("'assets/js/pg_picker.min.js'              => array('assets/js/pg_picker.js')", picker_test_source('includes/fn/output.php'));
	pg_assert_contains('id="pg_picker_config"', $module);
	pg_assert_contains('JSON_HEX_TAG', $module, 'the settings cannot close their script tag');
	pg_assert_contains("pg_picker_field('site_icon', 'site_icon', pg_site_icon_name()", picker_test_source('includes/settings/prep.php'));
	pg_assert_false(strpos(picker_test_source('includes/settings/prep.php'), 'pg_picker_script()') !== false, 'prep.php runs before every settings screen: the screens print the script');
	pg_assert_contains('pgUI.dialog.hide(self.root)', $script);
	pg_assert_contains("root.pgUI.on(picker.root, 'dialog', 'hidden'", $script, 'the pick is handed over once the dialog has closed');
}

// A branch's count holds the folders under it.
function test_picker_subtree_counts()
{
	$map = array(
		1 => array('folder_parent' => 0),
		4 => array('folder_parent' => 1),
		9 => array('folder_parent' => 4),
		5 => array('folder_parent' => 1),
		20 => array('folder_parent' => 21),
		21 => array('folder_parent' => 20),
	);

	pg_assert_same(array(9 => 3, 4 => 5, 1 => 6, 5 => 1), pg_picker_subtree_counts(array(9 => 3, 4 => 2, 5 => 1), $map));
	pg_assert_same(array(), pg_picker_subtree_counts(array(), $map));
	pg_assert_same(array(20 => 2, 21 => 2), pg_picker_subtree_counts(array(20 => 2), $map), 'a parent loop ends');
	pg_assert_same(array(77 => 1), pg_picker_subtree_counts(array(77 => 1), $map), 'a folder the map lacks keeps its own count');
}

// Upload goes only into a folder the user may add to, and a public kind only
// into a public folder, where its list would show the file.
function test_picker_upload_verdict()
{
	$kinds = pg_picker_kinds();

	pg_assert_same('', pg_picker_upload_verdict($kinds['image'], 4, true, true, false));
	pg_assert_same('', pg_picker_upload_verdict($kinds['file'], '4', true, true, false));
	pg_assert_same('kind', pg_picker_upload_verdict($kinds['page'], 4, true, true, true), 'pages take no upload');
	pg_assert_same('folder', pg_picker_upload_verdict($kinds['image'], 0, true, true, true), 'the top is no folder');
	pg_assert_same('folder', pg_picker_upload_verdict($kinds['image'], 4, false, true, true), 'a folder out of reach');
	pg_assert_same('folder', pg_picker_upload_verdict($kinds['image'], 4, true, false, true), 'no edit right');
	pg_assert_same('public', pg_picker_upload_verdict($kinds['site_icon'], 4, true, true, false));
	pg_assert_same('', pg_picker_upload_verdict($kinds['public_image'], 4, true, true, true));

	foreach (array('kind', 'folder', 'public') as $verdict) {
		pg_assert_true(pg_picker_upload_reason($verdict) !== '', 'a reason for ' . $verdict);
	}

	pg_assert_same('', pg_picker_upload_reason(''));
}

function test_picker_upload_type_and_token()
{
	$kinds = pg_picker_kinds();

	pg_assert_true(pg_picker_upload_type_ok($kinds['site_icon'], 'icon.PNG'));
	pg_assert_false(pg_picker_upload_type_ok($kinds['site_icon'], 'icon.jpg'));
	pg_assert_false(pg_picker_upload_type_ok($kinds['image'], 'noextension'));
	pg_assert_true(pg_picker_upload_type_ok($kinds['file'], 'notes'), 'a kind without types takes any name');

	pg_assert_true(pg_picker_token_valid('abc123', 'abc123'));
	pg_assert_false(pg_picker_token_valid('abc123', 'abc124'));
	pg_assert_false(pg_picker_token_valid('', ''), 'a session without a token');
	pg_assert_false(pg_picker_token_valid('abc123', array('abc123')), 'an array sent as the token');
	pg_assert_false(pg_picker_token_valid(null, 'abc123'));
}

// An upload is a POST with the session's token; a body PHP threw away is
// named before the token is read, as the token is lost with the file.
function test_picker_upload_wired()
{
	$endpoint = picker_test_source('picker.php');

	pg_assert_contains("(\$_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'", $endpoint);
	pg_assert_contains("pg_picker_token_valid(\$_SESSION['software']['token'] ?? '', \$_POST['token'] ?? '')", $endpoint);
	pg_assert_true(strpos($endpoint, 'pg_post_body_discarded()') < strpos($endpoint, 'pg_picker_token_valid('), 'the lost body before the token');
	pg_assert_true(strpos($endpoint, 'pg_picker_token_valid(') < strpos($endpoint, 'pg_picker_upload_answer('), 'the token before the file');

	$module = picker_test_source('includes/fn/picker.php');

	pg_assert_contains('pg_file_store_upload(', $module, 'stored as Upload File stores');
	pg_assert_contains("\$answer['upload'] = array('ok'", $module, 'the list says whether Upload is offered');
	pg_assert_contains("data.append('token'", picker_test_source('assets/js/pg_picker.js'));
}

// The picker field: what a stored value shows, and the markup the script
// reads.
function test_picker_field_view_and_markup()
{
	$kinds = pg_picker_kinds();

	pg_assert_same('bi-file-earmark-pdf', pg_picker_file_icon('PDF'));
	pg_assert_same('bi-file-earmark-image', pg_picker_file_icon('webp'));
	pg_assert_same('bi-file-earmark', pg_picker_file_icon('xyz'));
	pg_assert_same('Logos · 512 × 512 · 48 KB', pg_picker_field_meta(array('path' => array(array('name' => 'Files'), array('name' => 'Logos')), 'width' => 512, 'height' => 512, 'size_label' => '48 KB')));
	pg_assert_same('/about · Company', pg_picker_field_meta(array('address' => '/about', 'path' => array(array('name' => 'Pages'), array('name' => 'Company')))));

	$empty = pg_picker_field_view($kinds['public_image'], '', null);
	pg_assert_same('empty', $empty['state']);
	pg_assert_same('', $empty['note']);

	$missing = pg_picker_field_view($kinds['public_image'], 'old-logo.png', array('value' => 'old-logo.png', 'state' => 'missing'));
	pg_assert_same(array('missing', 'old-logo.png', 'bi-image', 'warning'), array($missing['state'], $missing['text'], $missing['icon'], $missing['tone']), 'a lost name stays as stored');
	pg_assert_true($missing['note'] !== '');

	$gone = pg_picker_field_view($kinds['file'], 42, null);
	pg_assert_same(array('missing', '#42', 'bi-file-earmark-x'), array($gone['state'], $gone['text'], $gone['icon']), 'an id kind has no name left to tell');

	$ok = array('value' => 'logo.png', 'text' => 'logo.png', 'state' => 'ok', 'thumb' => '/thumb/logo.png', 'type' => 'png', 'path' => array(array('name' => 'Files')), 'width' => 64, 'height' => 64, 'size_label' => '2 KB');
	$view = pg_picker_field_view($kinds['public_image'], 'logo.png', $ok);
	pg_assert_same(array('ok', 'logo.png', 'Files · 64 × 64 · 2 KB', '/thumb/logo.png', '', ''), array($view['state'], $view['text'], $view['meta'], $view['thumb'], $view['note'], $view['tone']));

	$binned = pg_picker_field_view($kinds['file'], 7, array('state' => 'binned', 'text' => 'price-list.pdf', 'type' => 'pdf'));
	pg_assert_same(array('danger', 'bi-file-earmark-pdf'), array($binned['tone'], $binned['icon']));
	pg_assert_same('warning', pg_picker_field_view($kinds['public_image'], 'x.png', array('state' => 'offline', 'text' => 'x.png'))['tone']);

	$hidden = pg_picker_field_view($kinds['file'], 9, array('state' => 'hidden'));
	pg_assert_same(array('bi-lock', ''), array($hidden['icon'], $hidden['tone']), 'a file in a closed folder is not named');

	$html = pg_picker_field_markup('public_image', 'app_icon', 'old-logo.png', $missing, array('title' => 'Choose a picture', 'describedby' => 'app_icon_help'));
	pg_assert_contains('<input type="hidden" name="app_icon" value="old-logo.png" data-pg-picker-value/>', $html, 'the stored value is posted unchanged');
	pg_assert_contains('class="pg-pfield is-warning" data-pg-picker-field data-pg-picker-kind="public_image" data-pg-picker-title="Choose a picture"', $html);
	pg_assert_contains('id="app_icon" class="pg-pfield-input" value="old-logo.png"', $html, 'the label names the text input');
	pg_assert_contains('aria-controls="app_icon_list" aria-describedby="app_icon_meta app_icon_help app_icon_note"', $html);
	pg_assert_contains('id="app_icon_note"', $html);
	pg_assert_contains('<span class="bi bi-image"></span>', $html);

	$html = pg_picker_field_markup('file', 'file_id', '', $empty, array('id' => 'sl_file', 'invalid' => true));
	pg_assert_contains('class="pg-pfield is-empty is-error"', $html);
	pg_assert_contains('name="file_id" value=""', $html);
	pg_assert_contains('id="sl_file" class="pg-pfield-input" value=""', $html);
	pg_assert_contains('aria-invalid="true"', $html);
	pg_assert_false(strpos($html, 'pg-pfield-note') !== false, 'no note for an empty field');

	$html = pg_picker_field_markup('public_image', 'logo', 'logo.png', $view);
	pg_assert_contains('<img src="/thumb/logo.png" alt="" loading="lazy"/>', $html);
	pg_assert_false(strpos($html, 'pg_picker_config') !== false, 'the field never prints the script');
	pg_assert_same('', pg_picker_field('nope', 'x', ''), 'an unknown kind draws nothing');
}

function test_picker_field_box_and_typed_state()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$start = strpos($css, '.pg-pfield-box {');
	$box = substr($css, $start, strpos($css, '}', $start) - $start);
	pg_assert_contains('background-color: var(--bs-btn-input-bg);', $box, 'the box has the input background');
	pg_assert_contains('border: var(--bs-border-width) solid var(--bs-border-color);', $box, 'and the input border');
	pg_assert_contains('.pg-pfield-input:focus-visible {', $css, 'one focus ring, drawn by the box');
	pg_assert_contains('.collapse.show:has(.pg-pfield.is-open) {', $css, 'an open list is not clipped by a collapse');

	// A paste fires no keydown before its input event, so the Field may be
	// made after the text changed: the chosen name comes from the attribute.
	pg_assert_contains('this.text = this.input.defaultValue;', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_picker.js'));
}

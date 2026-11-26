<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests "New collection with pages" (includes/fn/collection_pages.php and
 * the designer part 'collection'): a make request checked before anything
 * is written (pg_coll_pages_check()), the page placed around the widget
 * (pg_coll_pages_page_tree()), the fields the design save takes
 * (pg_coll_pages_page_fields()) and the endpoint's sub action.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/collection_pages.php');

// A make request for $what that passes every check.
function collection_pages_request($what)
{
	$kinds = pg_coll_pages_widget_kinds();
	$config = array('regionType' => $kinds[$what]);

	if ($what !== 'collection') {
		$config['custom_form_page_id'] = 12;
	}

	return array(
		'what' => $what, 'title' => 'Teachers', 'name' => 'teachers', 'collection' => 'Teachers', 'collection_page_id' => 12,
		'widget_tree' => json_encode(array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array())),
		'widget_config' => json_encode($config),
	);
}

function test_collection_pages_check_passes()
{
	foreach (array('collection', 'list', 'detail') as $what) {
		$res = pg_coll_pages_check(collection_pages_request($what));
		pg_assert_true($res['ok'], $what . ' passes');
		pg_assert_same('root', $res['tree']['type'], $what . ' tree decoded');
	}
}

function test_collection_pages_check_refusals()
{
	$cases = array(
		'what'          => array('collection', array('what' => 'page')),
		'title'         => array('list', array('title' => '  ')),
		'name'          => array('list', array('name' => 'a b')),
		'collection'    => array('collection', array('collection' => '')),
		'widget_tree'   => array('list', array('widget_tree' => '{"type":"row"}')),
		'widget_config' => array('detail', array('widget_config' => json_encode(array('regionType' => 'form_list_view', 'custom_form_page_id' => 12)))),
	);

	foreach ($cases as $field => $case) {
		$res = pg_coll_pages_check(array_merge(collection_pages_request($case[0]), $case[1]));
		pg_assert_true(!$res['ok'], $field . ' refused');
		pg_assert_same($field, $res['field'], $field . ' names its field');
	}

	// A list bound to another form than the collection asked for.
	$res = pg_coll_pages_check(array_merge(collection_pages_request('list'), array('collection_page_id' => 13)));
	pg_assert_same('widget_config', $res['field'], 'a list of another form is refused');
	$res = pg_coll_pages_check(array_merge(collection_pages_request('detail'), array('collection_page_id' => 0)));
	pg_assert_same('collection', $res['field'], 'a detail without a collection is refused');
	foreach (array('a/b', 'a?b', 'a#b', 'a%b', str_repeat('a', 101)) as $bad) {
		$res = pg_coll_pages_check(array_merge(collection_pages_request('list'), array('name' => $bad)));
		pg_assert_same('name', $res['field'], 'the address ' . $bad . ' is refused');
	}
}

function test_collection_pages_page_tree()
{
	$list = pg_coll_pages_page_tree('list', 41, 'teachers-form-list-view-widget', 'Teachers');
	pg_assert_same('root', $list['type']);
	pg_assert_same(2, count($list['children']), 'a list page: heading and widget');
	pg_assert_same('Teachers', $list['children'][0]['children'][0]['props']['text']);
	pg_assert_same('h1', $list['children'][0]['children'][0]['props']['tag']);
	pg_assert_same(41, $list['children'][1]['props']['sharedId']);
	pg_assert_same(array(), $list['children'][1]['children'], 'a shared_ref keeps no children');

	foreach (array('collection', 'detail') as $what) {
		$t = pg_coll_pages_page_tree($what, 7, 'w', 'X');
		pg_assert_same(1, count($t['children']), $what . ': the widget alone');
		pg_assert_same('shared_ref', $t['children'][0]['type']);
	}
}

function test_collection_pages_page_fields()
{
	$tree = pg_coll_pages_page_tree('detail', 7, 'w', '');
	$f = pg_coll_pages_page_fields('teacher', 'Teacher', 0, $tree, true);
	pg_assert_same(0, $f['page_id'], 'a new page');
	pg_assert_same('teacher', $f['page_name'], 'the address is the page name');
	pg_assert_same('Teacher', $f['page_title'], 'the name is the title');
	pg_assert_same(1, $f['page_draft']);
	pg_assert_same(0, $f['page_home'], 'never the home page');
	pg_assert_same($tree, json_decode($f['tree_json'], true));
	pg_assert_same(0, pg_coll_pages_page_fields('a', 'A', 0, $tree, false)['page_draft']);
}

function test_collection_pages_endpoint()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_contains("case 'collection_pages':", $panel, 'the designer endpoint has the sub action');

	// Design-level: not in the list a content-level operator may call.
	preg_match('/\$content_ok = array\(([^;]*)\);/s', $panel, $m);
	pg_assert_true(isset($m[1]) && strpos($m[1], 'collection_pages') === false, 'closed to the content roles');
	$res = pg_coll_pages_panel(array('op' => 'nothing'), array('id' => 1, 'role' => 1));
	pg_assert_same('error', $res['status'], 'an unknown op is refused');
}

// Runs a Node script after the named functions of the part (with _sdT, esc
// and its kind table) and returns its JSON output. Null without Node.
function collection_pages_node($names, $script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$js = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_collection.js'));
	preg_match('/\n    var FIELD_TYPES = \{.*?\n    \};\n/s', $js, $m);
	$src = "var _sdT = function (s) { return s; };\nvar esc = function (s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\"/g, '&quot;'); };\n"
		. (isset($m[0]) ? $m[0] : '') . "\n";

	foreach ($names as $name) {
		$start = strpos($js, "\n    function " . $name . '(');
		$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

		if (!pg_assert_true($end !== false, 'the part has ' . $name . '()')) {
			return null;
		}

		$src .= substr($js, $start, $end - $start + 6) . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgcollpages');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function test_collection_pages_part_addresses()
{
	$res = collection_pages_node(array('_ncSlug', '_ncFree', '_ncKey'),
		'var used = ["title"];'
		. 'console.log(JSON.stringify({ a: _ncSlug("Öğretmen Listesi!"), b: _ncSlug("  Teachers  collection "), c: _ncSlug("???"),'
		. ' d: _ncFree("teachers", ["Teachers", "teachers-2"]), e: _ncFree("x", []), f: _ncFree("", ["page"]),'
		. ' g: [_ncKey("Photo", used), _ncKey("photo", used), _ncKey("Title", used), _ncKey("!!", used)] }));');

	if ($res === null) {
		return;
	}

	pg_assert_same('ogretmen-listesi', $res['a'], 'Turkish letters folded, a hyphen between words');
	pg_assert_same('teachers-collection', $res['b']);
	pg_assert_same('', $res['c'], 'nothing left to make an address of');
	pg_assert_same('teachers-3', $res['d'], 'the first free address, case folded');
	pg_assert_same('x', $res['e']);
	pg_assert_same('page-2', $res['f'], 'an empty name is a page');
	pg_assert_same(array('photo', 'photo_2', 'title_2', 'field'), $res['g'], 'field names kept apart');
}

function test_collection_pages_part_step1()
{
	$res = collection_pages_node(array('_ncCheck'),
		'var colls = [{ name: "Workshops", page_id: 4 }];'
		. 'function C(s) { return _ncCheck(s, colls); }'
		. 'console.log(JSON.stringify({'
		. ' none: C({ choice: "new", name: " ", fields: [] }),'
		. ' ok: C({ choice: "new", name: "Teachers", fields: [{ name: "Photo" }, { name: "" }] }),'
		. ' taken: C({ choice: "new", name: "workshops", fields: [] }),'
		. ' dup: C({ choice: "new", name: "T", fields: [{ name: "Photo" }, { name: " photo " }] }),'
		. ' title: C({ choice: "new", name: "T", fields: [{ name: "Title" }] }),'
		. ' there: C({ choice: "there", chosen: null }), chosen: C({ choice: "there", chosen: colls[0] }) }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(false, $res['none']['next'], 'Next is off without a name');
	pg_assert_same(true, $res['ok']['next'], 'a name and fields; a line without a name is no field');
	pg_assert_same(4, $res['taken']['taken']['page_id'], 'a taken collection name names the collection');
	pg_assert_same(false, $res['taken']['next']);
	pg_assert_same('photo', $res['dup']['dup'], 'a field name used twice');
	pg_assert_same('Title', $res['title']['dup'], 'the Title is a field already');
	pg_assert_same(false, $res['there']['next'], 'one that is there: Next waits for a choice');
	pg_assert_same(true, $res['chosen']['next']);
}

function test_collection_pages_part_field_defs()
{
	$res = collection_pages_node(array('_ncSlug', '_ncKey', '_ncFieldDefs', '_ncListDefs', '_ncShowDef'),
		'var d = _ncFieldDefs([{ name: "Photo", kind: "image" }, { name: "Bio", kind: "rich" }, { name: "Styles", kind: "option", options: "Hatha, Yin, ,Hatha" },'
		. ' { name: "Teaches", kind: "reference", ref: "9" }, { name: "Lost", kind: "reference", ref: "" }, { name: "Age", kind: "number" }, { name: "Since", kind: "date" }, { name: "" }]);'
		. 'console.log(JSON.stringify({ d: d, l: _ncListDefs(d).map(function (x) { return [x.name, x.rss_field || ""]; }), stored: d[1].rss_field || "" }));');

	if ($res === null) {
		return;
	}

	$d = $res['d'];
	pg_assert_same(8, count($d), 'the Title and seven named lines');
	pg_assert_same(array('title', 'text box', 'title', true), array($d[0]['name'], $d[0]['type'], $d[0]['rss_field'], $d[0]['required']), 'the Title first, required, the record\'s title');
	pg_assert_same(array('file upload', 'image'), array($d[1]['type'], $d[1]['kind']));
	pg_assert_same(array('text area', 1), array($d[2]['type'], $d[2]['wysiwyg']));
	pg_assert_same(array(array('value' => 'Hatha', 'label' => 'Hatha'), array('value' => 'Yin', 'label' => 'Yin')), $d[3]['options'], 'options split, blanks and repeats dropped');
	pg_assert_same(array('pick list', 'reference', 9), array($d[4]['type'], $d[4]['kind'], $d[4]['reference_page_id']));
	pg_assert_same(array('text box', false), array($d[5]['type'], isset($d[5]['kind'])), 'a reference without a collection is text');
	pg_assert_same('number', $d[6]['kind']);
	pg_assert_same('date', $d[7]['type']);
	pg_assert_same(array(array('title', 'title'), array('photo', 'media'), array('bio', '')), $res['l'], 'a card: the Title, the first image, the first text');
	pg_assert_same('', $res['stored'], 'the image reads as media only for the layout');
}

function test_collection_pages_part_closing_line()
{
	$res = collection_pages_node(array('_ncMadeHtml'),
		'var made = { collection: { page_id: 3 }, list: { page_id: 4, title: "Teachers" }, detail: { page_id: 5, title: "<b>" } };'
		. 'console.log(JSON.stringify({ all: _ncMadeHtml(made, { name: "Teachers", page_id: 3 }, 5, true),'
		. ' half: _ncMadeHtml({ collection: made.collection, list: made.list }, { name: "Teachers", page_id: 3 }, 5, true),'
		. ' there: _ncMadeHtml({ list: made.list, detail: made.detail }, { name: "Workshops", page_id: 8 }, 5, false),'
		. ' none: _ncMadeHtml({}, { name: "T" }, 1, true) }));');

	if ($res === null) {
		return;
	}

	pg_assert_same('Made the collection <a href="#" data-open="3">“Teachers”</a> with 5 fields, the list page <a href="#" data-open="4">“Teachers”</a> and the detail page <a href="#" data-open="5">“&lt;b>”</a>.', $res['all'], 'one line, each name a link, names escaped');
	pg_assert_same('Made the collection <a href="#" data-open="3">“Teachers”</a> with 5 fields and the list page <a href="#" data-open="4">“Teachers”</a>.', $res['half'], 'after a stop: what there is');
	pg_assert_contains('for the collection <a href="#" data-open="8">“Workshops”</a>.', $res['there'], 'a collection that is there');
	pg_assert_same('', $res['none']);
}

function test_collection_pages_part_wiring()
{
	$pages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_pages.js');
	pg_assert_contains("_sdT('New collection with pages')", $pages, 'the Pages window has the row');
	pg_assert_contains('if (r && r.newColl) { _pwCollOpen(); return; }', $pages, 'Enter on the row opens the second view');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("id: 'act_new_collection'", $core, 'a command palette row');
	pg_assert_contains("_sdPartCall('collection', 'open'", $core, 'the core loads the part on first use');
	pg_assert_true(strpos($core, "function _ncSlug(") === false, 'the part is not in the core');
}

// The folders directly under the top folder (12), as the finder reads them.
function collection_pages_folders()
{
	return array(
		array('folder_id' => 20, 'folder_name' => 'Collections', 'folder_parent' => 12, 'folder_access_control_type' => 'public'),
		array('folder_id' => 21, 'folder_name' => 'Collections', 'folder_parent' => 12, 'folder_access_control_type' => 'private'),
		array('folder_id' => 22, 'folder_name' => 'Collections', 'folder_parent' => 12, 'folder_access_control_type' => 'private'),
		array('folder_id' => 23, 'folder_name' => 'Drafts', 'folder_parent' => 12, 'folder_access_control_type' => 'private'),
	);
}

function test_collection_pages_folder_found()
{
	pg_assert_same(21, pg_coll_pages_folder_pick(collection_pages_folders(), 'Collections', 12, array()), 'the first private Collections folder');
	pg_assert_same(22, pg_coll_pages_folder_pick(collection_pages_folders(), 'Collections', 12, array(21)), 'one in the recycle bin is not taken');
	pg_assert_same(0, pg_coll_pages_folder_pick(collection_pages_folders(), 'Collections', 12, array(21, 22)), 'none left: one is made');
	pg_assert_same(0, pg_coll_pages_folder_pick(array_slice(collection_pages_folders(), 0, 1), 'Collections', 12, array()), 'the same name but not private is not taken');
	pg_assert_same(0, pg_coll_pages_folder_pick(collection_pages_folders(), 'Koleksiyonlar', 12, array()), 'another name is not taken');
	pg_assert_same(0, pg_coll_pages_folder_pick(collection_pages_folders(), 'Collections', 13, array()), 'only directly under the top folder');

	// Made once: the folder made is the one found the next time.
	$rows = collection_pages_folders();
	$rows[] = array('folder_id' => 30, 'folder_name' => 'Collections', 'folder_parent' => 12, 'folder_access_control_type' => 'private');
	pg_assert_same(30, pg_coll_pages_folder_pick($rows, 'Collections', 12, array(21, 22)), 'the folder made is found again');
}

function test_collection_pages_upload_folder()
{
	$file = function ($cf) {
		return array('_id' => 'f', 'type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(array('name' => 'type', 'value' => 'file'), array('name' => 'name', 'value' => 'photo')), '_cf' => $cf), 'children' => array());
	};
	$text = array('_id' => 't', 'type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(array('name' => 'type', 'value' => 'text'))), 'children' => array());
	$tree = array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'c', 'type' => 'container', 'props' => array(), 'children' => array($file(array('kind' => 'image')), $text, $file(array()))),
	));
	$out = pg_coll_pages_upload_folder($tree, 7);
	$kids = $out['children'][0]['children'];
	pg_assert_same(7, $kids[0]['props']['_cf']['upload_folder_id'], 'an image field uploads into the pages\' folder');
	pg_assert_same('image', $kids[0]['props']['_cf']['kind'], 'its kind is kept');
	pg_assert_same(7, $kids[2]['props']['_cf']['upload_folder_id'], 'a file field too, from an empty record');
	pg_assert_true(!isset($kids[1]['props']['_cf']), 'a text field is left alone');
}

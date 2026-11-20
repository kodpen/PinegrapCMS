<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the designer's Pages window: the part 'pages'
 * (assets/js/style_designer_pages.js) listing the pages by group under the
 * filters All, Changed, Draft and Recently opened (_pwList()), finding a
 * page by name or address with Turkish letters folded (_pwMatch()), the
 * keys Up, Down, Enter and F2 (_pwKeyAction(), _pwRenameValue()), a row
 * dropped on a group (_pwDropGroup()) written through designer/tab_layout
 * by the core (_pgPagesMoveToGroup()), and "Recently opened" kept per
 * browser (_pgPagesRecentPush()). Runs the functions under Node; skipped
 * where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One four-space function of a designer script, from its declaration to its
// closing brace.
function designer_pages_window_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the named functions of `$file` and returns its
// JSON output. Null without Node or when a function is missing.
function designer_pages_window_run($file, $names, $script)
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

	$js = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
	$src = '';

	foreach ($names as $name) {
		$fn = designer_pages_window_fn($js, $name);

		if (!pg_assert_true($fn !== '', $file . ' has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgpageswin');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// Seven pages in two groups and "Other": two changed, two drafts.
function designer_pages_window_pages_script()
{
	return 'function R(key, name, address, group, dirty, draft) { return { key: key, name: name, address: address, group: group, dirty: !!dirty, draft: !!draft }; }' . "\n"
		. 'var rows = [R("a", "anasayfa", "/", "shop"), R("b", "Ürün Detay", "/urun-detay", "shop", 1), R("c", "odeme", "/odeme", "shop", 0, 1),' . "\n"
		. '  R("d", "giris", "/giris", "acc"), R("e", "Hesabım", "/hesabım", "acc", 1, 1), R("f", "blog", "/blog", ""), R("g", "İletişim", "/iletisim", "gone")];' . "\n"
		. 'var groups = [{ id: "shop", name: "Mağaza", color: "green" }, { id: "acc", name: "Hesap", color: "blue" }, { id: "", name: "Other", color: "grey" }];' . "\n"
		. 'function L(f, q, recent) { var r = _pwList(rows, groups, recent || [], { filter: f, q: q }); return { counts: r.counts, sections: r.sections.map(function (s) { return [s.id, s.rows.map(function (x) { return x.key; })]; }) }; }' . "\n";
}

function test_designer_pages_window_filters()
{
	$res = designer_pages_window_run('style_designer_pages.js', array('_pwFold', '_pwMatch', '_pwList'),
		designer_pages_window_pages_script()
		. 'console.log(JSON.stringify({ all: L("all", ""), changed: L("changed", ""), draft: L("draft", ""), recent: L("recent", "", ["e", "zz", "a", "e"]), none: L("recent", "") }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('all' => 7, 'changed' => 2, 'draft' => 2, 'recent' => 2), $res['recent']['counts'], 'the filters are counted');
	pg_assert_same(array(array('shop', array('a', 'b', 'c')), array('acc', array('d', 'e')), array('', array('f', 'g'))), $res['all']['sections'], 'All lists every page by group in strip order, a page of an unknown group under Other');
	pg_assert_same(array(array('shop', array('b')), array('acc', array('e'))), $res['changed']['sections'], 'Changed keeps the changed pages, groups without one left out');
	pg_assert_same(array(array('shop', array('c')), array('acc', array('e'))), $res['draft']['sections'], 'Draft keeps the drafts');
	pg_assert_same(array(array(null, array('e', 'a'))), $res['recent']['sections'], 'Recently opened lists the pages in the order they were opened, once, without groups');
	pg_assert_same(array(), $res['none']['sections'], 'nothing opened yet lists nothing');
}

function test_designer_pages_window_search_folds_turkish_letters()
{
	$res = designer_pages_window_run('style_designer_pages.js', array('_pwFold', '_pwMatch', '_pwList'),
		designer_pages_window_pages_script()
		. 'console.log(JSON.stringify({ urun: L("all", "urun"), hesabim: L("all", "HESABIM"), addr: L("all", "/odeme"), il: L("all", "ileti"), ILETI: L("all", "İLETİ"), draft: L("draft", "hesab"), blank: L("all", "   ").counts.all, miss: L("all", "yok") }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(array('shop', array('b'))), $res['urun']['sections'], '"urun" finds "Ürün Detay"');
	pg_assert_same(array(array('acc', array('e'))), $res['hesabim']['sections'], '"HESABIM" finds "Hesabım"');
	pg_assert_same(array(array('shop', array('c'))), $res['addr']['sections'], 'an address finds its page');
	pg_assert_same(array(array('', array('g'))), $res['il']['sections'], '"ileti" finds "İletişim"');
	pg_assert_same(array(array('', array('g'))), $res['ILETI']['sections'], '"İLETİ" finds "/iletisim"');
	pg_assert_same(array(array('acc', array('e'))), $res['draft']['sections'], 'the search works inside a filter');
	pg_assert_same(7, $res['blank'], 'blanks search nothing');
	pg_assert_same(array(), $res['miss']['sections'], 'no match, no section');
}

function test_designer_pages_window_keys_and_f2()
{
	$res = designer_pages_window_run('style_designer_pages.js', array('_pwKeyAction', '_pwRenameValue'),
		'console.log(JSON.stringify({'
		. ' down: _pwKeyAction("ArrowDown", 1, 3, true), last: _pwKeyAction("ArrowDown", 2, 3, true), up: _pwKeyAction("ArrowUp", 0, 3, true),'
		. ' enter: _pwKeyAction("Enter", 1, 3, true), f2: _pwKeyAction("F2", 1, 3, true), f2off: _pwKeyAction("F2", 1, 3, false),'
		. ' empty: _pwKeyAction("Enter", 0, 0, true), other: _pwKeyAction("a", 0, 3, true),'
		. ' typed: _pwRenameValue("sepet", "  sepetim ", true), esc: _pwRenameValue("sepet", "sepetim", false) }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('act' => 'go', 'to' => 2), $res['down'], 'Down walks to the next row');
	pg_assert_same(array('act' => 'go', 'to' => 2), $res['last'], 'and stops at the last');
	pg_assert_same(array('act' => 'go', 'to' => 0), $res['up'], 'Up stops at the first');
	pg_assert_same(array('act' => 'open'), $res['enter'], 'Enter opens');
	pg_assert_same(array('act' => 'rename'), $res['f2'], 'F2 renames');
	pg_assert_same(null, $res['f2off'], 'F2 does nothing where a page cannot be renamed');
	pg_assert_same(null, $res['empty'], 'no row, no key');
	pg_assert_same(null, $res['other'], 'a letter stays in the search box');
	pg_assert_same('sepetim', $res['typed'], 'the typed name is trimmed');
	pg_assert_same('sepet', $res['esc'], 'Escape keeps the old name');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($core, "rename: function (key, val) {\n                var p = _pgPageByKey(key);\n                if (!p) return;\n                _pgPageNameSet(p, val);") !== false, 'the window renames through the strip rename\'s own setter');
	pg_assert_true(strpos($core, 'var apply = function (val) { _pgPageNameSet(p, val); };') !== false, 'which the strip rename uses');
}

function test_designer_pages_window_drag_onto_a_group_writes_the_tab_layout()
{
	$names = array('_pgPageByKey', '_pgTabGroupMembers', '_pgTabGroupAdd', '_pgTabGroupMoveOut', '_pgTabGroupRemove',
		'_pgTabsLayoutSerialize', '_pgTabsLayoutSave', '_pgTabsLayoutChanged', '_pgPagesMoveToGroup');
	$res = designer_pages_window_run('style_designer.src.js', $names,
		designer_pages_window_fn_part_drop() . "\n"
		. 'var _sdT = function (s) { return s; }, esc = String, sdToast = function () {}, rendered = 0, posts = [];' . "\n"
		. 'function _pgTabsRender() { rendered++; }' . "\n"
		. 'var editable = true; function _pgTabLayoutEditable() { return editable; }' . "\n"
		. 'function _pgApiPost(ep, body) { posts.push([ep, JSON.parse(JSON.stringify(body))]); return Promise.resolve({ status: "success" }); }' . "\n"
		. 'var _design = { styleId: 7 }, _pgTabProto = {}, _pgTabLayoutTimer = 0;' . "\n"
		. 'var _pgTabGroups = { shop: { name: "Shop", color: "green" }, acc: { name: "Account", color: "blue" } };' . "\n"
		. 'var _pages = [{ key: "a", page_id: 1, tabGroup: "shop" }, { key: "b", page_id: 2, tabGroup: "shop" }, { key: "c", page_id: 3, tabGroup: "acc" }, { key: "d", page_id: 4, tabGroup: "" }];' . "\n"
		. 'var drop = [];' . "\n"
		. 'function row(k) { var p = _pgPageByKey(k); return { key: k, group: p.tabGroup }; }' . "\n"
		. 'var to = _pwDropGroup(row("d"), "acc"); drop.push(to); _pgPagesMoveToGroup("d", to);' . "\n"
		. 'drop.push(_pwDropGroup(row("a"), "shop"), _pwDropGroup(row("a"), null));' . "\n"
		. 'setTimeout(function () {' . "\n"
		. '  var first = posts.slice();' . "\n"
		. '  _pgPagesMoveToGroup("a", ""); _pgPagesMoveToGroup("b", "shop");' . "\n"
		. '  editable = false; _pgPagesMoveToGroup("c", "shop");' . "\n"
		. '  setTimeout(function () { console.log(JSON.stringify({ drop: drop, first: first, posts: posts, order: _pages.map(function (p) { return p.key + ":" + p.tabGroup; }), rendered: rendered })); }, 700);' . "\n"
		. '}, 700);');

	if ($res === null) {
		return;
	}

	// _pwDropGroup() comes from the part; the core functions run beside it.
	pg_assert_same(array('acc', null, null), $res['drop'], 'a drop on another group moves; on its own group or outside a group, nothing');
	pg_assert_same(1, count($res['first']), 'one write');
	pg_assert_same('tab_layout', $res['first'][0][0], 'through designer/tab_layout');
	pg_assert_same(array('style_id' => 7, 'layout' => array('v' => 1, 'groups' => array('shop' => array('name' => 'Shop', 'color' => 'green'), 'acc' => array('name' => 'Account', 'color' => 'blue')),
		'tabs' => array(array('id' => 1, 'g' => 'shop'), array('id' => 2, 'g' => 'shop'), array('id' => 3, 'g' => 'acc'), array('id' => 4, 'g' => 'acc')))), $res['first'][0][1], 'the page is stored in its new group, at its end');
	pg_assert_same(array('b:shop', 'a:', 'c:acc', 'd:acc'), $res['order'], 'dropped on Other the page leaves its group; on its own group, or without the right to arrange, nothing changes');
	pg_assert_same(2, count($res['posts']), 'only the moves that changed something were written');
}

// The JS part's drop function is added to the core run above.
function designer_pages_window_fn_part_drop()
{
	return designer_pages_window_fn((string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_pages.js'), '_pwDropGroup');
}

function test_designer_pages_window_keeps_recently_opened_per_browser()
{
	$res = designer_pages_window_run('style_designer.src.js', array('_pgPagesRecentPush'),
		'var a = _pgPagesRecentPush([], 5, 3), b = _pgPagesRecentPush(a, 6, 3), c = _pgPagesRecentPush(b, 5, 3), d = _pgPagesRecentPush([1, 2, 3], 4, 3);' . "\n"
		. 'console.log(JSON.stringify([a, b, c, d]));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(array(5), array(6, 5), array(5, 6), array(4, 1, 2)), $res, 'the latest first, once, at most the limit');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($core, "function _pgPagesRecentKey() { return 'pg_sd_recent_' + ((_design && _design.styleId) || 0); }") !== false, 'kept in the browser, per design');
	pg_assert_true(strpos($core, "_pgTabsSyncUrl();\n        _pgPagesRecentNote(next);") !== false, 'noted when a page is opened');
}

function test_designer_pages_window_is_a_part_opened_by_ctrl_p()
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_pages.js');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_true(strpos($part, "StyleDesigner.definePart('pages', function (K) {") !== false, 'the part registers');
	pg_assert_same('assets/js/style_designer_pages.min.js', pg_designer_parts()['pages'], 'and is a part of its own');
	pg_assert_true(strpos($screen, "'pages'), !empty(" . '$sd_ai') !== false, 'handed to every editor');

	foreach (array('_pgTabsAllToggle', '_pgTabsAllFill', '_pgTabsAllPageMenu') as $name) {
		pg_assert_false(strpos($core, 'function ' . $name . '(') !== false, $name . ' is gone');
	}

	pg_assert_false(strpos($screen, 'sd-tabs-all-filter') !== false, 'the All pages menu is gone');
	pg_assert_true(strpos($screen, 'id="sd-tabs-all-btn" aria-haspopup="dialog"') !== false, 'the Pages button opens a window');
	pg_assert_true(strpos($core, "all.addEventListener('click', _pgPagesOpen);") !== false, 'on a click');
	pg_assert_true(strpos($core, "if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'p') { e.preventDefault(); e.stopPropagation(); _pgPagesOpen(); return; }") !== false, 'Ctrl+P opens it');
	pg_assert_true(strpos($core, "if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'p') { e.preventDefault(); e.stopPropagation(); openCommandPalette(); return; }") !== false, 'Ctrl+Shift+P stays the command palette');
	pg_assert_true(strpos($core, "if ((e.ctrlKey || e.metaKey) && e.altKey && !e.shiftKey && e.key.toLowerCase() === 'p') { e.preventDefault(); openPreview(); }") !== false, 'Ctrl+Alt+P stays the preview');
}

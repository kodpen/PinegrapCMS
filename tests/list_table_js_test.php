<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The list table driver's selection model and sort clicks
 * (assets/js/pg_list_table.js, window.PGListTable) run in Node: a plain
 * click, Ctrl and Shift clicks, Ctrl+A with exceptions, the undo of a
 * replaced selection, the payload pg_list_table_selection_parse() reads,
 * and the sort levels after a heading click. Skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

// Runs the script after the driver (both sources of its bundle) in Node and answers what it printed as
// JSON; null when Node cannot run.
function list_table_js_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pglt');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_list_table.js')
		. "\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_list_table_edit.js')
		. "\nvar T = window.PGListTable;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_list_table_js_clicks()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
var order = ['10', '9', '8', '7', '6'];
s.only(9); out.only = s.payload({});
s.toggle('7'); out.toggle = s.payload({}).ids.sort();
s.toggle(9); out.untoggle = s.payload({}).ids;
s.only('9'); s.range(order, '6', false); out.range = s.payload({}).ids.sort();
s.only('10'); s.range(order, '8', false); s.range(order, '6', true); out.additive = s.payload({}).ids.sort();
s.clear(); s.range(order, '7', false); out.noAnchor = s.payload({}).ids;
s.range(order, 'gone', false); out.unknown = s.payload({}).ids;
out.has = [s.has(7), s.has('8')];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ids' => array('9')), $result['only']);
	pg_assert_same(array('7', '9'), $result['toggle']);
	pg_assert_same(array('7'), $result['untoggle']);
	pg_assert_same(array('6', '7', '8', '9'), $result['range'], 'from the anchor down');
	pg_assert_same(array('10', '6', '7', '8', '9'), $result['additive'], 'Ctrl+Shift keeps the first range');
	pg_assert_same(array('7'), $result['noAnchor'], 'no anchor: the row alone');
	pg_assert_same(array('7'), $result['unknown'], 'a row not on screen changes nothing');
	pg_assert_same(array(true, false), $result['has']);
}

function test_list_table_js_select_all_with_exceptions()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
s.selectAll(1700000000.7);
s.toggle('4'); s.toggle('5'); s.toggle('5');
out.payload = s.payload({ search: 'bag', params: { filter: 'all' } });
out.count = s.count(250);
out.has = [s.has('4'), s.has('5'), s.has('999')];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	$payload = array('condition' => array('search' => 'bag', 'params' => array('filter' => 'all')), 'except' => array('4'), 'as_of' => 1700000000);

	pg_assert_same($payload, $result['payload']);
	pg_assert_same(249, $result['count'], 'rows not loaded yet count too');
	pg_assert_same(array(false, true, true), $result['has']);

	// The server reads the same payload.
	$selection = pg_list_table_selection_parse($result['payload'], 1800000000);
	pg_assert_same(array(4), $selection['except']);
	pg_assert_same(1700000000, $selection['as_of']);
	pg_assert_same('bag', $selection['search']);
}

function test_list_table_js_undo_of_replaced_selection()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
s.only('1');
out.single = s.replaced(10, function () { this.only('2'); });
s.toggle('3');
var before = s.replaced(10, function () { this.clear(); });
out.before = before.ids.sort();
out.cleared = s.count(10);
s.restore(before);
out.restored = s.payload({}).ids.sort();
s.selectAll(5);
var all = s.replaced(10, function () { this.only('1'); });
s.restore(all);
out.all = s.payload({ search: '' });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(null, $result['single'], 'one row replaced: no undo');
	pg_assert_same(array('2', '3'), $result['before']);
	pg_assert_same(0, $result['cleared']);
	pg_assert_same(array('2', '3'), $result['restored']);
	pg_assert_same(array('condition' => array('search' => ''), 'except' => array(), 'as_of' => 5), $result['all'], 'a whole-list selection comes back');
}

function test_list_table_js_sort_clicks_and_texts()
{
	$result = list_table_js_test_node(<<<'JS'
var out = {};
var levels = T.sortParse('name:asc,price:desc');
out.parse = levels;
out.plainNew = T.sortAfterClick(levels, 'price', false);
out.plainTurn = T.sortAfterClick(levels, 'name', false);
out.shiftTurn = T.sortAfterClick(levels, 'name', true);
out.shiftDrop = T.sortAfterClick(levels, 'price', true);
out.shiftAdd = T.sortAfterClick(levels, 'folder', true);
out.shiftOnly = T.sortAfterClick([{ sort: 'name', order: 'desc' }], 'name', true);
out.value = T.sortValue(levels);
out.text = T.text('%n seçili', 3);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc'), array('sort' => 'price', 'order' => 'desc')), $result['parse']);
	pg_assert_same(array(array('sort' => 'price', 'order' => 'asc')), $result['plainNew']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'desc')), $result['plainTurn']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'desc'), array('sort' => 'price', 'order' => 'desc')), $result['shiftTurn']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc')), $result['shiftDrop'], 'a descending level leaves');
	pg_assert_same('folder', $result['shiftAdd'][2]['sort']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc')), $result['shiftOnly'], 'the only level turns back');
	pg_assert_same('name:asc,price:desc', $result['value']);
	pg_assert_same('3 seçili', $result['text']);
}

function test_list_table_js_sort_menu_edits_and_marks()
{
	$result = list_table_js_test_node(<<<'JS'
var out = {};
var levels = T.sortParse('name:asc,price:desc');
out.reverse = T.sortEdit(levels, 'reverse', 1);
out.remove = T.sortEdit(levels, 'remove', 0);
out.removeLast = T.sortEdit([{ sort: 'name', order: 'asc' }], 'remove', 0);
out.add = T.sortEdit(levels, 'add', -1, 'folder', 'desc');
out.addAgain = T.sortEdit(levels, 'add', -1, 'price', 'asc');
out.untouched = levels;
out.marksOne = T.sortMarks([{ sort: 'name', order: 'desc' }]);
out.marksTwo = T.sortMarks(levels);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc'), array('sort' => 'price', 'order' => 'asc')), $result['reverse']);
	pg_assert_same(array(array('sort' => 'price', 'order' => 'desc')), $result['remove'], 'the lead level leaves, the next one leads');
	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc')), $result['removeLast'], 'the last level stays');
	pg_assert_same(array('sort' => 'folder', 'order' => 'desc'), $result['add'][2]);
	pg_assert_same(2, count($result['addAgain']), 'a column already sorted by is not added twice');
	pg_assert_same('asc', $result['untouched'][0]['order'], 'the levels given are not changed');
	pg_assert_same(array('name' => array('level' => 1, 'order' => 'desc', 'numbered' => false)), $result['marksOne']);
	pg_assert_same(array('level' => 2, 'order' => 'desc', 'numbered' => true), $result['marksTwo']['price']);
}

function test_list_table_js_select_all_button_state()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
out.none = T.allSelected(s, ['1', '2']);
s.selectIds(['1', '2']);
out.shownAll = T.allSelected(s, ['1', '2']);
out.serverIds = T.allSelected(s, null);
s.toggle('2');
out.shownSome = T.allSelected(s, ['1', '2']);
out.emptyList = T.allSelected(new T.Selection(), []);
s.selectAll(1700000000);
s.toggle('5');
out.condition = T.allSelected(s, null);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(false, $result['none']);
	pg_assert_same(true, $result['shownAll'], 'every shown row of a list printed whole');
	pg_assert_same(false, $result['serverIds'], 'loaded rows of a server list are not all of it');
	pg_assert_same(false, $result['shownSome']);
	pg_assert_same(false, $result['emptyList']);
	pg_assert_same(true, $result['condition'], 'the whole condition, exceptions or not');
}

function test_list_table_js_column_preferences()
{
	$result = list_table_js_test_node(<<<'JS'
var cols = [
	{ key: 'name', hideable: false, hidden: false },
	{ key: 'folder', hideable: true, hidden: false },
	{ key: 'style', hideable: true, hidden: true },
	{ key: 'type', hideable: true, hidden: false },
	{ key: 'actions', hideable: false, hidden: false }
];
function view(state) { return T.columnsView(cols, state).map(function (c) { return c.key + (c.hidden ? '-' : ''); }).join(','); }
var out = {}, s = T.prefsClean(null);
out.plain = view(s);
s = T.columnsToggle(cols, s, 'style'); out.shown = view(s);
s = T.columnsToggle(cols, s, 'folder'); out.hidden = view(s);
out.name = view(T.columnsToggle(cols, s, 'name'));
out.actions = view(T.columnsToggle(cols, s, 'actions'));
s = T.columnsMove(cols, s, 'type', -2); out.moved = view(s);
out.notFirst = view(T.columnsMove(cols, s, 'type', -1));
out.nameStays = view(T.columnsMove(cols, s, 'name', 1));
out.order = s.order;
out.stored = view(T.prefsClean(JSON.parse(JSON.stringify(s))));
out.newColumn = T.columnsView(cols.concat([{ key: 'late', hideable: true }]).slice(0, 3).concat([{ key: 'late', hideable: true }], cols.slice(3)), s).map(function (c) { return c.key; }).join(',');
out.gone = view({ order: ['gone', 'type'], hidden: { gone: true, name: true }, details: false });
out.junk = T.prefsClean({ order: 'x', hidden: { a: 1, b: true }, details: 'yes' });
s.details = true; out.reset = T.columnsReset(s);
out.key = T.prefsKey(7, 'view_pages');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same('name,folder,style-,type,actions', $result['plain'], 'a column the model hides starts hidden');
	pg_assert_same('name,folder,style,type,actions', $result['shown']);
	pg_assert_same('name,folder-,style,type,actions', $result['hidden']);
	pg_assert_same($result['hidden'], $result['name'], 'the name never hides');
	pg_assert_same($result['hidden'], $result['actions'], 'a column that is not hideable stays');
	pg_assert_same('name,type,folder-,style,actions', $result['moved']);
	pg_assert_same($result['moved'], $result['notFirst'], 'nothing moves before the name');
	pg_assert_same($result['moved'], $result['nameStays'], 'the name does not move');
	pg_assert_same(array('type', 'folder', 'style', 'actions'), $result['order']);
	pg_assert_same($result['moved'], $result['stored'], 'stored and read back');
	pg_assert_same('name,type,folder,style,late,actions', $result['newColumn'], 'a new column comes after its model neighbour');
	pg_assert_same('name,folder,style-,type,actions', $result['gone'], 'a stored key the list lost is dropped; the name is never hidden');
	pg_assert_same(array('order' => array(), 'hidden' => array('b' => true), 'details' => false), $result['junk']);
	pg_assert_same(array('order' => array(), 'hidden' => array(), 'details' => true), $result['reset']);
	pg_assert_same('pg.list.7.view_pages', $result['key']);
}

// A list printed whole keeps the rows whose filtered cell carries the
// value, beside the search words; a server list sends the filter with its
// parameters and asks again.
function test_list_table_js_filter()
{
	$result = list_table_js_test_node(<<<'JS'
function row(text, type) {
    return { childNodes: [{ nodeType: 3, nodeValue: text }], hidden: false, querySelector: function (selector) {
        return (selector === 'td[data-pg-col="type"]') ? { getAttribute: function () { return type; } } : null;
    } };
}
var rows = [row('saved page', 'site'), row('PHP Warning', 'warn'), row('PHP Fatal', 'error')];
var list = { browser: true, search: '', filters: {}, params: {}, selection: new T.Selection(), tbody: { rows: rows }, paint: function () {}, holdWidth: function () {}, showHere: T.Driver.prototype.showHere };
var out = {};
function shown() { return rows.filter(function (r) { return !r.hidden; }).length; }
T.Driver.prototype.setFilter.call(list, 'type', 'error');
out.error = [shown(), list.total];
list.search = 'php';
T.Driver.prototype.setFilter.call(list, 'type', '');
out.all = shown();
list.search = 'saved';
T.Driver.prototype.showHere.call(list);
out.searched = shown();
var server = { browser: false, filters: {}, params: { start_time: '1' }, selection: new T.Selection(), loads: 0, load: function () { this.loads++; }, holdWidth: function () {} };
T.Driver.prototype.setFilter.call(server, 'type', 'warn');
T.Driver.prototype.setFilter.call(server, 'type', 'warn');
out.server = [server.params.type, server.loads];
T.Driver.prototype.setFilter.call(server, 'type', '');
out.cleared = [server.params.hasOwnProperty('type'), server.loads];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(1, 1), $result['error'], 'only the error row, counted');
	pg_assert_same(2, $result['all'], 'every type, the search words still apply');
	pg_assert_same(1, $result['searched']);
	pg_assert_same(array('warn', 1), $result['server'], 'sent with the parameters, asked once');
	pg_assert_same(array(false, 2), $result['cleared']);
}

// A cell may stand in several groups of the same buttons: its values are
// separated by spaces, and a filter keeps it when one of them is the value.
function test_list_table_js_filter_several_values()
{
	$result = list_table_js_test_node(<<<'JS'
console.log(JSON.stringify([
    T.filterHas('active code incomplete', 'code'),
    T.filterHas('active code', 'incomplete'),
    T.filterHas('error', 'error'),
    T.filterHas('error', 'err'),
    T.filterHas(null, 'active')
]));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, false, true, false, false), $result);
}

// The details panel's sections: the name first, then the pictures, the
// label/value lines and the buttons; an empty cell is left out.
function test_list_table_js_details_sections()
{
	$result = list_table_js_test_node(<<<'JS'
var T = window.PGListTable;
var cols = [{ key: 'name' }, { key: 'pic', kind: 'image' }, { key: 'price', kind: 'money' }, { key: 'note' }, { key: 'actions', kind: 'actions' }, { key: 'seo', kind: 'badge' }];
var filled = { name: 1, pic: 1, price: 1, actions: 1, seo: 1 };
var out = {};
out.full = T.detailsSections(cols, function (key) { return !!filled[key]; });
out.bare = T.detailsSections(cols, function () { return false; });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('title' => 'name', 'pictures' => array('pic'), 'fields' => array('price', 'seo'), 'actions' => array('actions')), $result['full'], 'in the user\'s order, the empty note left out');
	pg_assert_same(array('title' => 'name', 'pictures' => array(), 'fields' => array(), 'actions' => array()), $result['bare'], 'the name always leads');
}

// The search words are matched against what a person reads in a row: an
// icon font's mark reads as its label ("Evet"), never its ligature word
// ("task_alt", which kept every checked row for "alt"); a quick edit field
// reads as its value, a choice list as its choice, not every option.
function test_list_table_js_search_text()
{
	$result = list_table_js_test_node(<<<'JS'
function el(tag, attrs, kids, extra) {
    var node = { nodeType: 1, tagName: tag, childNodes: kids || [], getAttribute: function (name) { return (attrs && (name in attrs)) ? attrs[name] : null; } };
    for (var key in (extra || {})) { node[key] = extra[key]; }
    return node;
}
function txt(value) { return { nodeType: 3, nodeValue: value }; }
function clean(text) { return text.replace(/\s+/g, ' ').trim(); }
var out = {};
var check = el('TD', {}, [el('SPAN', { 'class': 'material-icons', role: 'img', 'aria-label': 'Evet' }, [txt('task_alt')])]);
var row = el('TR', {}, [el('TD', {}, [txt('Merhaba dünya')]), check, el('TD', {}, [el('SPAN', { 'class': 'material-symbols-outlined' }, [txt('close')])])]);
out.check = clean(T.searchText(row));
out.drawn = clean(T.searchText(el('TD', {}, [el('svg', { role: 'img', 'aria-label': 'Hayır' }, [el('text', {}, [txt('x')])])])));
out.hiddenWord = clean(T.searchText(el('TD', {}, [el('SPAN', { 'class': 'visually-hidden' }, [txt('Kaldırıldı: ')]), txt('Toplantı')])));
var select = el('SELECT', {}, [], { options: [{ selected: false, textContent: 'Ana' }, { selected: true, textContent: 'Taslaklar' }, { selected: false, textContent: 'Arşiv' }] });
var fields = el('TR', {}, [
    el('TD', {}, [el('INPUT', { type: 'text' }, [], { value: 'pg-test-alt' })]),
    el('TD', {}, [el('INPUT', {}, [], { value: 'typeless' })]),
    el('TD', {}, [el('INPUT', { type: 'checkbox' }, [], { value: 'on' })]),
    el('TD', {}, [select]),
    el('TD', {}, [el('TEXTAREA', {}, [], { value: 'satır' })]),
    el('TD', {}, [el('SCRIPT', {}, [txt('var hidden;')]), el('TEMPLATE', {}, [txt('şablon')])])
]);
out.fields = clean(T.searchText(fields));
out.cells = clean(T.searchText(el('TR', {}, [el('TD', {}, [txt('(kaydedildi)')]), el('TD', {}, [txt('11 gün önce')])])));
out.held = T.heldWidth(2050.4);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same('Merhaba dünya Evet', $result['check'], 'the check mark reads Evet, the bare icon nothing');
	pg_assert_same('Hayır', $result['drawn'], 'a drawn mark reads its label');
	pg_assert_same('Kaldırıldı: Toplantı', $result['hiddenWord'], 'visually hidden words are text');
	pg_assert_same('pg-test-alt typeless Taslaklar satır', $result['fields'], 'field values and the chosen option only');
	pg_assert_same('(kaydedildi) 11 gün önce', $result['cells'], 'two cells are two words');
	pg_assert_same('min(100%, max(2051px, var(--pg-measure-list-min, 1280px) + 2rem))', $result['held'], 'the box keeps its width, never more than the window');
}

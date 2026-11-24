<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Artifact Canvas's Data menu and the data layer's panel: the
 * kinds a table's columns fit and the starting descriptions (the model under
 * Node), the picker and the panel on a bare editor, a kind changed in the
 * panel, and the server's list of tables (includes/fn/image_editor_data.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_chart.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor_data.php');

// Runs $script with the model as M and the fixture as F; with $editor the
// core, layers, draw and data parts loaded on a stub DOM and fake() a bare
// editor. Answers the script's JSON, null without Node.
function image_editor_data_menu_node($script, $editor = false)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiedm');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . ');' . "\n"
		. 'var F = require(' . json_encode(dirname(__FILE__) . '/fixtures/canvas_data/layers.json') . ');' . "\n";

	if ($editor) {
		// A DOM of plain objects: enough for el(), the panel and the picker.
		$head .= 'function N(tag) { var n = { tagName: String(tag).toUpperCase(), children: [], attrs: {}, listeners: {}, style: {}, classList: { toggle: function () {}, add: function () {}, remove: function () {} },' . "\n"
			. ' setAttribute: function (k, v) { this.attrs[k] = String(v); }, getAttribute: function (k) { return this.attrs.hasOwnProperty(k) ? this.attrs[k] : null; },' . "\n"
			. ' appendChild: function (c) { c.parentNode = this; this.children.push(c); return c; }, insertBefore: function (c, ref) { var i = this.children.indexOf(ref); c.parentNode = this; this.children.splice(i < 0 ? this.children.length : i, 0, c); return c; },' . "\n"
			. ' removeChild: function (c) { this.children.splice(this.children.indexOf(c), 1); return c; }, addEventListener: function (k, fn) { this.listeners[k] = fn; }, focus: function () {},' . "\n"
			. ' contains: function (c) { while (c) { if (c === this) { return true; } c = c.parentNode; } return false; },' . "\n"
			. ' get firstChild() { return this.children[0] || null; }, set textContent(v) { this.children = []; this.text = v; }, get textContent() { return this.text || this.children.map(function (c) { return c.textContent; }).join(""); } }; return n; }' . "\n"
			. 'global.window = global; global.navigator = { platform: "Linux" };' . "\n"
			. 'global.document = { activeElement: null, addEventListener: function () {}, removeEventListener: function () {}, createElement: N, createElementNS: function (ns, tag) { return N(tag); }, createTextNode: function (v) { return { textContent: v }; }, documentElement: N("html") };' . "\n"
			. 'window.PgImageEditorModel = M; window.pgImageEditorConfig = { api: "/api", strings: { dataRowsCount: "%d rows", dataNoChannel: "No channel" } };' . "\n";

		foreach (array('', '_layers', '_draw', '_artboard', '_data') as $part) {
			$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
		}

		$head .= 'var X = window.PgImageEditor.parts, Editor = X.Editor, none = function () {};' . "\n"
			. 'function all(n, out) { out = out || []; out.push(n); (n.children || []).forEach(function (c) { all(c, out); }); return out; }' . "\n"
			. 'function cls(n, c) { return all(n).filter(function (x) { return (" " + (x.className || (x.attrs && x.attrs["class"]) || "") + " ").indexOf(" " + c + " ") >= 0; }); }' . "\n"
			. 'function fake(answers) {' . "\n"
			. ' var e = Object.create(Editor.prototype);' . "\n"
			. ' Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
			. ' e.doc = M.newDocument({ width: 1600, height: 900 }); e.doc.artboard = { width: 1600, height: 900, background: "#ffffff" }; e.doc.layers = [];' . "\n"
			. ' e.tool = "select"; e.sel = []; e.history = { key: null }; e.steps = 0; e.asked = []; e.root = N("div"); e.dialog = e.root; e.stage = N("div"); e.importBtn = N("button");' . "\n"
			. ' e.render = e.renderView = e.renderPanel = none; e.cropping = function () { return false; };' . "\n"
			. ' e.change = function (fn) { this.steps++; fn(this.doc); }; e.select = function (ids) { this.sel = ids; };' . "\n"
			. ' e.request = function (body) { e.asked.push(body); return { then: function (ok) { ok(answers[body.action]); } }; };' . "\n"
			. ' return e;' . "\n"
			. '}' . "\n";
	}

	file_put_contents($file, $head . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The columns are read as numbers or words; the kinds a table fits; the
// starting description of each kind; a kind changed keeps what it can.
function test_image_editor_data_menu_fits()
{
	$r = image_editor_data_menu_node(
		'var words = { a: { name: "Name" }, b: { name: "City" } }, wrows = [{ cells: { a: "x", b: "y" } }, { cells: { a: "z", b: "" } }];'
		. 'var one = [{ cells: { "11": "North", "12": 5, "13": 1, "14": "n" } }];'
		. 'var single = { "11": { name: "Region" }, "12": { name: "Revenue" } };'
		. 'var bar = M.dataStart(7, "bar", F.columns, F.rows, "Sales\nby region");'
		. 'console.log(JSON.stringify({'
		. ' columns: M.dataColumns(F.columns, F.rows),'
		. ' all: M.dataFits(F.columns, F.rows),'
		. ' words: M.dataFits(words, wrows),'
		. ' oneRow: M.dataFits(F.columns, one),'
		. ' oneNumber: M.dataFits(single, F.rows),'
		. ' none: M.dataFits({}, []),'
		. ' bar: bar,'
		. ' table: M.dataStart(7, "table", F.columns, F.rows, "Rows"),'
		. ' donut: M.dataStart(7, "donut", F.columns, F.rows, "").values,'
		. ' pie: M.dataStart(7, "pie", F.columns, F.rows, ""),'
		. ' toDonut: M.dataWithKind(bar, "donut", F.columns, F.rows),'
		. ' toTable: M.dataWithKind(bar, "table", F.columns, F.rows).show,'
		. ' back: M.dataWithKind(M.dataWithKind(bar, "table", F.columns, F.rows), "line", F.columns, F.rows).values }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('id' => '11', 'name' => 'Region', 'number' => false), array('id' => '12', 'name' => 'Revenue', 'number' => true),
		array('id' => '13', 'name' => 'Cost', 'number' => false), array('id' => '14', 'name' => 'Note', 'number' => false)), $r['columns'], 'a column with a cell that is no number is words');
	pg_assert_same(array('table', 'bar', 'line', 'area', 'donut'), $r['all'], 'one number column: no stacked bar');
	pg_assert_same(array('table'), $r['words'], 'no number column: only a table');
	pg_assert_same(array('table', 'bar', 'stacked_bar', 'donut'), $r['oneRow'], 'a line or an area needs two rows');
	pg_assert_same(array('table', 'bar', 'line', 'area', 'donut'), $r['oneNumber']);
	pg_assert_same(array(), $r['none'], 'a table of no column fits nothing');
	pg_assert_same(array('11', array('12'), 'sum', 'Sales by region', 'bar'), array($r['bar']['label'], $r['bar']['values'], $r['bar']['group'], $r['bar']['title'], $r['bar']['kind']), 'a label that comes again is summed');
	pg_assert_same(array('table', '11', array('12', '13', '14')), array($r['table']['kind'], $r['table']['label'], $r['table']['values']), 'a plain table takes any columns');
	pg_assert_same(array('12'), $r['donut']);
	pg_assert_same(null, $r['pie']);
	pg_assert_same(array('donut', array('12'), 'sum'), array($r['toDonut']['kind'], $r['toDonut']['values'], $r['toDonut']['group']), 'a kind changed keeps the grouping');
	pg_assert_same('table', $r['toTable']);
	pg_assert_same(array('12'), $r['back'], 'from a table of word columns back to a chart: the number columns only');
}

// The row limit keeps that many labels; a donut keeps its five and Other.
function test_image_editor_data_menu_limit()
{
	$r = image_editor_data_menu_node(
		'function s(d) { return M.dataSeries(M.dataCheck(d).data, F.columns, F.rows, F.words); }'
		. 'console.log(JSON.stringify({ two: s({ table: 7, label: "11", values: ["12"], group: "sum", limit: 2 }),'
		. ' table: s({ table: 7, show: "table", label: "11", values: ["12"], limit: 3 }).labels.length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('North', 'South'), $r['two']['labels']);
	pg_assert_same(2, $r['two']['cut'], 'the rest is counted as left out');
	pg_assert_same(3, $r['table']);
}

// Data on the top bar, right after Import, opens the picker (the Import
// menu no longer lists it): the tables as rows, then a card
// for each kind the table fits; a card puts the layer in the middle of the
// canvas and selects it; Change gives a layer another table in its box.
function test_image_editor_data_menu_picker()
{
	$r = image_editor_data_menu_node(
		'var answers = { data_tables: { status: "success", tables: [{ id: 7, name: "Sales", channel: "team", rows: 6, tone: null }, { id: 9, name: "Stock", channel: "", rows: 2, tone: null }] },'
		. ' data_rows: { status: "success", read: { columns: F.columns, rows: F.rows } } };'
		. 'var e = fake(answers), out = {};'
		. 'out.item = Editor.importItems.map(function (fn) { var it = fn.call(e); return it ? it[0] : null; }).filter(Boolean);'
		. 'out.after = Editor.topItems.indexOf(Editor.topData) - Editor.topItems.indexOf(Editor.topImport);'
		. 'e.tools = { list: ["select"] }; var b = Editor.topData.call(e); out.btn = [b.className || b.attrs["class"], b.attrs["aria-label"], b.children[1].textContent];'
		. 'e.tools = { list: ["crop"] }; out.mini = Editor.topData.call(e);'
		. 'e.tools = { list: ["select"] }; window.pgImageEditorConfig.api = ""; out.noApi = Editor.topData.call(e); window.pgImageEditorConfig.api = "/api";'
		. 'b.listeners.click();'
		. 'out.rows = cls(e.confirmBox, "pgie-datatable").map(function (n) { return n.textContent; });'
		. 'e.dataPick.search = "sto"; e.dataTableRows(cls(e.confirmBox, "pgie-datatables")[0]);'
		. 'out.found = cls(e.confirmBox, "pgie-datatable").length;'
		. 'e.dataPick.search = ""; e.dataTableRows(cls(e.confirmBox, "pgie-datatables")[0]);'
		. 'cls(e.confirmBox, "pgie-datatable")[0].listeners.click();'
		. 'out.cards = cls(e.confirmBox, "pgie-datacard").map(function (n) { return n.children[n.children.length - 1].textContent; });'
		. 'out.samples = cls(e.confirmBox, "pgie-datasample").length;'
		. 'cls(e.confirmBox, "pgie-datacard")[1].listeners.click();'
		. 'out.closed = e.confirmBox === null; out.layer = JSON.parse(JSON.stringify(e.doc.layers[0])); out.sel = e.sel;'
		. 'out.asked = e.asked.map(function (b) { return b.action + (b.table ? ":" + b.table : ""); });'
		. 'e.openDataPicker(e.doc.layers[0].id); cls(e.confirmBox, "pgie-datatable")[0].listeners.click(); cls(e.confirmBox, "pgie-datacard")[0].listeners.click();'
		. 'out.changed = [e.doc.layers.length, e.doc.layers[0].data.kind, e.doc.layers[0].x];'
		. 'console.log(JSON.stringify(out));', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false(in_array('layer_data', $r['item'], true), 'Data is no longer in the Import menu');
	pg_assert_same(1, $r['after'], 'Data comes right after Import on the top bar');
	pg_assert_same(array('pgie-btn pgie-databtn', 'layer_data', 'layer_data'), $r['btn'], 'its icon and word, named');
	pg_assert_same(null, $r['mini'], 'not where layers cannot be added');
	pg_assert_same(null, $r['noApi'], 'not without the editor\'s API');
	pg_assert_same(array('Sales#team · 6 rows', 'StockNo channel · 2 rows'), $r['rows'], 'name, channel and row count');
	pg_assert_same(1, $r['found'], 'the search narrows the rows');
	pg_assert_same(array('data_kind_table', 'data_kind_bar', 'data_kind_line', 'data_kind_area', 'data_kind_donut'), $r['cards'], 'a card for each kind the columns fit');
	pg_assert_same(5, $r['samples'], 'each card draws a sample');
	pg_assert_true($r['closed'], 'a card closes the picker');
	pg_assert_same(array('data', 'bar', 7, 960, 540, 320, 180), array($r['layer']['type'], $r['layer']['data']['kind'], $r['layer']['data']['table'],
		$r['layer']['w'], $r['layer']['h'], $r['layer']['x'], $r['layer']['y']), 'a bar layer in the middle, 60 % of the canvas wide');
	pg_assert_same(array($r['layer']['id']), $r['sel'], 'the new layer is selected');
	pg_assert_same(array('data_tables', 'data_rows:7'), $r['asked'], 'the tables once, the rows once');
	pg_assert_same(array(1, 'table', 320), $r['changed'], 'Change keeps the layer and its box');
}

// The panel's groups fold one at a time; Data shows the columns, Look the
// kinds and colours, Text the title; a donut has no sort; a kind card and a
// colour choice change the description.
function test_image_editor_data_menu_panel()
{
	$r = image_editor_data_menu_node(
		'var answers = { data_tables: { status: "success", tables: [{ id: 7, name: "Sales", channel: "team", rows: 6, tone: { light: "#2255aa", dark: "#6699ee" } }] } };'
		. 'var e = fake(answers), out = {}, d = M.dataStart(7, "stacked_bar", { "11": { name: "Region" }, "12": { name: "Revenue" }, "15": { name: "Units" } }, [{ cells: { "11": "N", "12": 1, "15": 2 } }, { cells: { "11": "S", "12": 3, "15": 4 } }], "Sales");'
		. 'e.doc.layers = [{ id: 1, type: "data", x: 0, y: 0, w: 600, h: 338, rot: 0, opacity: 1, data: d }];'
		. 'e.dataRead(7, { columns: { "11": { name: "Region" }, "12": { name: "Revenue" }, "15": { name: "Units" } }, rows: [{ cells: { "11": "N", "12": 1, "15": 2 } }, { cells: { "11": "S", "12": 3, "15": 4 } }] });'
		. 'function panel(fold) { var b = N("div"); e.dataFold = fold; Editor.layerTypes.data.panel.call(e, b, [e.doc.layers[0]]); return b; }'
		. 'function labels(b) { return cls(b, "pgie-lbl").map(function (n) { return n.textContent; }); }'
		. 'var b = panel("data"); out.folds = cls(b, "pgie-datafold").map(function (n) { return [n.textContent, n.attrs["aria-expanded"]]; }); out.data = labels(b);'
		. 'out.look = labels(panel("look")); out.text = labels(panel("text"));'
		. 'var look = panel("look"); out.kinds = cls(look, "pgie-datacard").map(function (n) { return [n.children[n.children.length - 1].textContent, n.attrs["aria-pressed"] || null]; });'
		. 'cls(look, "pgie-datacard")[0].listeners.click(); out.toTable = e.doc.layers[0].data.kind;'
		. 'e.doc.layers[0].data = d; look = panel("look");'
		. 'var seg = cls(look, "pgie-seg")[0]; seg.children[1].listeners.click(); out.channel = e.doc.layers[0].data.colours;'
		. 'e.doc.layers[0].data = M.dataWithKind(d, "donut", { "11": { name: "Region" }, "12": { name: "Revenue" }, "15": { name: "Units" } }, []); out.donut = labels(panel("data"));'
		. 'e.doc.layers[0].data = d; e.dataRead(7, { state: "refused" }); out.refused = cls(panel("data"), "pgie-note").map(function (n) { return n.textContent; });'
		. 'console.log(JSON.stringify(out));', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('layer_data', 'true'), array('dataGroupLook', 'false'), array('dataGroupText', 'false')), $r['folds'], 'Data, Look and Text, one open');
	pg_assert_same(array('dataTable', 'dataLabel', 'dataValues', 'dataGrouping', 'dataSort', 'dataLimit'), array_map(function ($s) {
		return preg_replace('/[0-9]+$/', '', $s);
	}, $r['data']));
	pg_assert_same(array('dataKind', 'dataColours', 'dataLegend'), $r['look']);
	pg_assert_same(array('dataTitle', 'dataFormatNumbers', 'dataSize'), $r['text']);
	pg_assert_same(array(array('data_kind_table', null), array('data_kind_bar', null), array('data_kind_stacked_bar', 'true'), array('data_kind_line', null),
		array('data_kind_area', null), array('data_kind_donut', null)), $r['kinds'], 'the kind as sample cards, the layer\'s pressed');
	pg_assert_same('table', $r['toTable'], 'a card changes the kind');
	pg_assert_same(array('#2255aa'), $r['channel'], 'the channel\'s tone on the light board');
	pg_assert_false(in_array('dataSort', $r['donut'], true), 'a donut has no sort');
	pg_assert_same(array('dataRefused'), $r['refused'], 'a table the reader may not read says so in Data');
}

// A column the layer names was deleted from the table: the panel says so,
// the label select offers every column (a blank first, so picking the first
// column is a change), and the next change drops the gone value column.
function test_image_editor_data_menu_panel_column_gone()
{
	$r = image_editor_data_menu_node(
		'var answers = { data_tables: { status: "success", tables: [{ id: 7, name: "Sales", channel: "", rows: 2, tone: null }] } };'
		. 'var e = fake(answers), out = {}, cols = { "11": { name: "Region" }, "12": { name: "Revenue" }, "15": { name: "Units" } };'
		. 'var rows = [{ cells: { "11": "N", "12": 1, "15": 2 } }, { cells: { "11": "S", "12": 3, "15": 4 } }];'
		. 'e.doc.layers = [{ id: 1, type: "data", x: 0, y: 0, w: 600, h: 338, rot: 0, opacity: 1, data: M.dataCheck({ table: 7, kind: "bar", label: "10", values: ["12", "16"] }).data }];'
		. 'e.dataRead(7, { columns: cols, rows: rows });'
		. 'function panel() { var b = N("div"); e.dataFold = "data"; Editor.layerTypes.data.panel.call(e, b, [e.doc.layers[0]]); return b; }'
		. 'var b = panel(); out.notes = cls(b, "pgie-note").map(function (n) { return n.textContent; });'
		. 'var sel = all(b).filter(function (n) { return n.tagName === "SELECT"; })[0];'
		. 'out.options = sel.children.map(function (o) { return [o.value === undefined ? o.attrs.value : o.value, o.attrs.hasOwnProperty("selected")]; });'
		. 'sel.value = "11"; sel.listeners.change(); out.label = e.doc.layers[0].data.label;'
		. 'b = panel(); var box = all(b).filter(function (n) { return n.tagName === "INPUT" && (n.type || n.attrs.type) === "checkbox"; });'
		. 'out.boxes = box.length; box[1].checked = true; box[1].listeners.change(); out.values = e.doc.layers[0].data.values;'
		. 'out.after = cls(panel(), "pgie-note").map(function (n) { return n.textContent; });'
		. 'console.log(JSON.stringify(out));', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true(in_array('dataColumnGone', $r['notes'], true), 'the panel says a column is gone');
	pg_assert_same(array('', true), $r['options'][0], 'a blank first option stands for the gone label column');
	pg_assert_same('11', $r['label'], 'the first column can be picked as the label');
	pg_assert_same(2, $r['boxes'], 'a value checkbox for each number column left');
	pg_assert_same(array('12', '15'), $r['values'], 'the gone value column is dropped by the next change');
	pg_assert_false(in_array('dataColumnGone', $r['after'], true), 'no note once every column is there');
}

// A table with no column left: "Show on a canvas" and the Data picker say
// so in place of the cards, and draw nothing that would fail.
function test_image_editor_data_menu_picker_no_columns()
{
	$r = image_editor_data_menu_node(
		'var answers = { data_tables: { status: "success", tables: [{ id: 9, name: "Empty", channel: "", rows: 0, tone: null }] },'
		. ' data_rows: { status: "success", read: { columns: {}, rows: [] } } };'
		. 'var e = fake(answers), out = {};'
		. 'e.openDataPicker(null, { table: { id: 9, name: "Empty" }, show: "chart" });'
		. 'out.chart = [cls(e.confirmBox, "pgie-datacard").length, cls(e.confirmBox, "pgie-note").map(function (n) { return n.textContent; })];'
		. 'e.closeConfirm = function () { e.confirmBox = null; }; e.confirmBox = null; e.dataReads = {}; e.dataAsked = {};'
		. 'e.openDataPicker(null); cls(e.confirmBox, "pgie-datatable")[0].listeners.click();'
		. 'out.pick = [cls(e.confirmBox, "pgie-datacard").length, cls(e.confirmBox, "pgie-note").map(function (n) { return n.textContent; })];'
		. 'console.log(JSON.stringify(out));', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, array('dataNoColumns')), $r['chart'], 'Show on a canvas says the table has no column');
	pg_assert_same(array(0, array('dataNoColumns')), $r['pick'], 'the picker says so too');
}

// The server's rows of the tables: the channel's name and its tone on each
// board, none for a table of no channel.
function test_image_editor_data_menu_tables_shape()
{
	$palettes = ws_canvas_guide_values()['palettes'];
	$rows = pg_image_editor_data_tables_shape(array(
		array('id' => '7', 'name' => 'Sales', 'channel_id' => 3, 'row_count' => '12'),
		array('id' => 9, 'name' => 'Mine', 'channel_id' => 0, 'row_count' => 0),
	), function ($id) {
		return ($id === 3) ? array('name' => 'team', 'color' => '#ffd400') : null;
	}, $palettes);

	pg_assert_same(array('id' => 7, 'name' => 'Sales', 'channel' => 'team', 'rows' => 12), array_diff_key($rows[0], array('tone' => 1)));
	pg_assert_same(ws_canvas_chart_tone('#ffd400', $palettes['light']), $rows[0]['tone']['light'], 'a pale colour is darkened on a light board');
	pg_assert_same('#ffd400', $rows[0]['tone']['dark']);
	pg_assert_same(array('id' => 9, 'name' => 'Mine', 'channel' => '', 'rows' => 0, 'tone' => null), $rows[1]);
}

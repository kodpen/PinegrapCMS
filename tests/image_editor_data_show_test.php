<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Show on a canvas" on the Data screen
 * (includes/fn/image_editor_data.php, pg_image_editor_data.js): who may,
 * the channel the canvas goes to, the table bound for reading only, the
 * chart kinds only when the columns fit, the picker opened on the table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__FILE__) . '/image_editor_data_menu_test.php');

// Who may: someone who reads the table, whose table belongs to a channel,
// and who may post in it; each refusal in words already said elsewhere.
function test_image_editor_data_show_refusal()
{
	$may = array('table' => true, 'channel' => true, 'may_post' => true);

	pg_assert_same('', pg_image_editor_data_show_refusal($may), 'a reader who may post in the table\'s channel');
	pg_assert_same('table', pg_image_editor_data_show_refusal(array('table' => false) + $may), 'a table they may not read');
	pg_assert_same('channel', pg_image_editor_data_show_refusal(array('channel' => false) + $may), 'a channel not found, or not the table\'s own');
	pg_assert_same('post', pg_image_editor_data_show_refusal(array('may_post' => false) + $may), 'a reader who may not post there');
	pg_assert_same('table', pg_image_editor_data_show_refusal(array()), 'nothing known is refused');

	pg_assert_same('You cannot read this table.', pg_image_editor_data_show_text('table'));
	pg_assert_same('That channel could not be found.', pg_image_editor_data_show_text('channel'));
	pg_assert_same('You cannot post in that channel.', pg_image_editor_data_show_text('post'));
}

// The channel chosen: the table's own; a viewer who is no member is refused
// before anything is read.
function test_image_editor_data_show_check_no_member()
{
	$r = pg_image_editor_data_show_check(array('id' => 3, 'member' => false), 7);

	pg_assert_same('table', $r['code']);
	pg_assert_same(null, $r['channel']);

	$config = pg_image_editor_data_show_config(array('id' => 7, 'name' => 'Sales', 'row_count' => 6, 'channel_id' => 4), array('id' => 4, 'name' => 'team'));

	pg_assert_same(4, $config['channel'], 'the canvas goes to the table\'s channel');
	pg_assert_true(substr($config['save'], -strlen('workspace_file_save.php?table=7&channel=4')) === 'workspace_file_save.php?table=7&channel=4', $config['save']);
	pg_assert_same(array(7, 'Sales', 'team', 6), array($config['table']['id'], $config['table']['name'], $config['table']['channel'], $config['table']['rows']), 'the table as the picker lists it');
	pg_assert_same(array(), $config['channels'], 'nothing to ask');
}

// A table of no channel: the channel is asked at the send, from the ones the
// person may post in; the addresses end where its id is added.
function test_image_editor_data_show_config_no_channel()
{
	$config = pg_image_editor_data_show_config(array('id' => 7, 'name' => 'Sales', 'row_count' => 6, 'channel_id' => 0), null,
		array(array('id' => 4, 'name' => 'team', 'icon' => 'x', 'hex' => '#fff'), array('id' => '5', 'name' => 'sales')));

	pg_assert_same(0, $config['channel'], 'no channel yet');
	pg_assert_true(substr($config['save'], -strlen('workspace_file_save.php?table=7&channel=')) === 'workspace_file_save.php?table=7&channel=', $config['save']);
	pg_assert_true(substr($config['open'], -strlen('workspace.php?channel=')) === 'workspace.php?channel=', $config['open']);
	pg_assert_same(array(array('id' => 4, 'name' => 'team'), array('id' => 5, 'name' => 'sales')), $config['channels'], 'the channels to ask from, id and name');
	pg_assert_same('', $config['table']['channel'], 'the picker lists it in no channel');
}

// The table bound read-only, and only when the saved canvas shows it.
function test_image_editor_data_show_binding()
{
	$row = pg_image_editor_data_show_binding(41, 7, 3);

	pg_assert_same(array('message_id' => 41, 'table_id' => 7, 'access' => 'read', 'bound_by_kind' => 'user', 'bound_by_id' => 3), $row, 'read only, in the person\'s name');

	$doc = array('layers' => array(array('type' => 'text', 'text' => 'x'), array('type' => 'data', 'data' => array('table' => 7, 'show' => 'chart'))));

	pg_assert_true(pg_image_editor_data_doc_shows($doc, 7), 'a data layer of the table');
	pg_assert_true(pg_image_editor_data_doc_shows(json_decode(json_encode($doc)), 7), 'as an object too');
	pg_assert_false(pg_image_editor_data_doc_shows($doc, 9), 'another table is not bound');
	pg_assert_false(pg_image_editor_data_doc_shows(array('layers' => array(array('type' => 'data', 'data' => array('table' => 0)))), 0), 'no table');
	pg_assert_false(pg_image_editor_data_doc_shows(null, 7), 'no document');
}

// The menu on the table's head: the Menus pattern, two entries, the
// editor's loader and what it is handed.
function test_image_editor_data_show_menu()
{
	$html = pg_image_editor_data_show_menu();
	$assets = pg_image_editor_data_show_assets(array('table' => array('id' => 7, 'name' => 'Sa<les'), 'channel' => 4, 'save' => '/s', 'open' => '/o'));

	pg_assert_same(1, substr_count($html, 'class="pg-menu-wrap"'));
	pg_assert_same(1, substr_count($html, 'data-bs-toggle="menu"'));
	pg_assert_same(2, substr_count($html, 'class="menu-item"'), 'As a chart and As a table');
	pg_assert_true(strpos($html, 'data-ws-wt-canvas="chart"') !== false);
	pg_assert_true(strpos($html, 'data-ws-wt-canvas="table"') !== false);
	pg_assert_false(strpos($html, 'pg-button-fill') !== false || strpos($html, 'btn-solid') !== false, 'no filled button on the head');
	pg_assert_true(strpos($html, 'class="pg-button pg-button-ghost"') !== false, 'a button with its word, not a quiet small one');
	pg_assert_true(strpos($html, '>Show on a canvas<') !== false);
	pg_assert_false(strpos($html, '<script') !== false, 'nothing but the menu in the button bar');
	pg_assert_false(strpos($assets, 'Sa<les') !== false, 'the name is escaped in the JSON');
	pg_assert_true(strpos($assets, 'id="ws_wt_canvas_config"') !== false);
	pg_assert_true(strpos($assets, '"label":"Send the canvas"') !== false);
}

// The chart kinds only when the columns fit: As a table gives the table
// alone, As a chart the chart kinds, or the table alone with the reason.
function test_image_editor_data_show_kinds()
{
	$r = image_editor_data_menu_node(
		'var words = { a: { name: "Name" } }, wrows = [{ cells: { a: "x" } }];'
		. 'console.log(JSON.stringify({ chart: M.dataShowKinds("chart", F.columns, F.rows), table: M.dataShowKinds("table", F.columns, F.rows),'
		. ' words: M.dataShowKinds("chart", words, wrows) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('bar', 'line', 'area', 'donut'), $r['chart']);
	pg_assert_same(array('table'), $r['table']);
	pg_assert_same(array('table'), $r['words'], 'no number column: the table alone');
}

// The editor opened with dataShow: step two at once on the table, no Back,
// the cards of the kind asked; a card puts the one layer on the stage.
function test_image_editor_data_show_picker()
{
	$r = image_editor_data_menu_node(
		'var answers = { data_rows: { status: "success", read: { columns: F.columns, rows: F.rows } } };'
		. 'var x = { id: 7, name: "Sales", channel: "team", rows: 6, tone: null }, out = {};'
		. 'function cards(e) { return cls(e.confirmBox, "pgie-datacard").map(function (n) { return n.children[n.children.length - 1].textContent; }); }'
		. 'var e = fake(answers); e.opts = {}; e.openDataPicker(null, { table: x, show: "chart" });'
		. 'out.chart = cards(e); out.rows = cls(e.confirmBox, "pgie-datatable").length;'
		. 'out.back = all(e.confirmBox).filter(function (n) { return n.attrs && n.attrs["aria-label"] === "dataBack"; }).length;'
		. 'out.notes = cls(e.confirmBox, "pgie-note").length;'
		. 'cls(e.confirmBox, "pgie-datacard")[0].listeners.click();'
		. 'out.layers = e.doc.layers.map(function (l) { return [l.type, l.data.table, l.data.kind]; });'
		. 'var f = fake(answers); f.opts = {}; f.openDataPicker(null, { table: x, show: "table" }); out.table = cards(f); out.tnotes = cls(f.confirmBox, "pgie-note").length;'
		. 'var w = fake({ data_rows: { status: "success", read: { columns: { a: { name: "Name" } }, rows: [{ cells: { a: "x" } }] } } }); w.opts = {};'
		. 'w.openDataPicker(null, { table: x, show: "chart" }); out.words = cards(w); out.wnote = cls(w.confirmBox, "pgie-note").map(function (n) { return n.textContent; });'
		. 'var g = Object.create(Editor.prototype); g.opts = { dataShow: { table: x, show: "table" } }; Editor.inits.forEach(function (fn) { try { fn.call(g); } catch (err) {} }); out.start = g.dataShow.show;'
		. 'console.log(JSON.stringify(out));', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('data_kind_bar', 'data_kind_line', 'data_kind_area', 'data_kind_donut'), $r['chart'], 'As a chart: the chart kinds the columns fit');
	pg_assert_same(0, $r['rows'], 'the table is chosen: no list of tables');
	pg_assert_same(0, $r['back'], 'no way back to the list');
	pg_assert_same(0, $r['notes']);
	pg_assert_same(array(array('data', 7, 'bar')), $r['layers'], 'one layer of the table');
	pg_assert_same(array('data_kind_table'), $r['table'], 'As a table: the table alone');
	pg_assert_same(0, $r['tnotes'], 'As a table says nothing of charts');
	pg_assert_same(array('data_kind_table'), $r['words'], 'no number column: the table');
	pg_assert_same(array('dataOnlyTable'), $r['wnote'], 'and the line saying why');
	pg_assert_same('table', $r['start'], 'dataShow is kept for the first view');
}

// "Send the canvas" of a table of no channel (save.ask): the main button
// asks for the channel in the send list, a find field above, and saves
// there; it offers no other channel to send a copy to.
function test_image_editor_data_show_asks_channel()
{
	$r = image_editor_data_menu_node(
		'var e = fake({}), out = {}, sent = [];'
		. 'e.opts = { save: { mode: "url", ask: true, label: "Send the canvas" }, send: { current: 0, channels: function () { return [{ id: 4, name: "team" }, { id: 5, name: "sales" }]; }, url: function (id) { return "/s?table=7&channel=" + id; } } };'
		. 'e.saveMode = "url"; e.ready = true; e.saving = false; e.dirty = true; e.saveBox = N("div"); e.saveMenu = null; e.menuBtn = null;'
		. 'e.hideMessage = e.setSaving = none; e.layersReady = function () { return Promise.resolve(); }; e.send = function (mode) { sent.push([mode, this.opts.save.url]); };'
		. 'out.asks = e.asksChannel(); out.ctx = e.sendContext().send; out.label = e.saveLabel();'
		. 'e.save(); out.menu = !!e.saveMenu; out.before = sent.length;'
		. 'setTimeout(function () {'
		. ' out.find = cls(e.saveMenu, "pgie-sendfind").length;'
		. ' var items = all(e.saveMenu).filter(function (n) { return n.attrs && n.attrs.role === "menuitem"; });'
		. ' out.items = items.map(function (n) { return n.textContent; });'
		. ' items[1].listeners.click();'
		. ' setTimeout(function () { out.closed = e.saveMenu === null; out.sent = sent; console.log(JSON.stringify(out)); }, 0);'
		. '}, 0);', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['asks'], 'the main button asks for the channel');
	pg_assert_false($r['ctx'], 'no other channel to send a copy to');
	pg_assert_same('Send the canvas', $r['label']);
	pg_assert_true($r['menu'], 'the send list opens under the main button');
	pg_assert_same(0, $r['before'], 'nothing is sent before a channel is chosen');
	pg_assert_same(1, $r['find'], 'a find field above, however few channels');
	pg_assert_same(array('team', 'sales'), $r['items'], 'the channels the person may post in');
	pg_assert_true($r['closed'], 'a choice closes the list');
	pg_assert_same(array(array('url', '/s?table=7&channel=5')), $r['sent'], 'and the canvas is saved in that channel');
}

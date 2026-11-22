<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a document with data layers saved out of the Artifact Canvas
 * (pg_image_editor_model.js, pg_image_editor_data.js): the file the
 * Workspace keeps draws its data layers with no rows, a copy into Files, a
 * File Manager save and a download keep the rows of now and say so.
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

// The tables a document's data layers name, the other scenes' too, once
// each; which saves are the Workspace's file.
function test_image_editor_data_still_model()
{
	$r = image_editor_data_menu_node(
		'var doc = { layers: [{ type: "data", data: { table: 7 } }, { type: "text" }, { type: "data", data: { table: "3" } }, { type: "data", data: { table: 7 } }, { type: "data" }, null],'
		. ' scenes: [{ layers: [{ type: "data", data: { table: 11 } }, { type: "data", data: { table: 3 } }] }, null, { layers: "x" }] };'
		. 'console.log(JSON.stringify({ tables: M.dataTablesOf(doc), none: M.dataTablesOf({ layers: [{ type: "text" }] }), bad: M.dataTablesOf(null),'
		. ' bare: [M.dataSendBare("url"), M.dataSendBare("new", { url: "/w?channel=2", name: "x" }), M.dataSendBare("new", { folder: { id: 4 } }), M.dataSendBare("new"), M.dataSendBare("replace"), M.dataSendBare("url", { folder: { id: 4 } })] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('7', '3', '11'), $r['tables'], 'every scene, once each');
	pg_assert_same(array(), $r['none']);
	pg_assert_same(array(), $r['bad']);
	pg_assert_same(array(true, true, false, false, false, false), $r['bare'], 'a channel\'s or a note\'s file and a copy sent to a channel; Files and a download are stills');
}

// A bare drawing gives the layer no rows and the line saying where they
// show, then the editor draws its rows again; a painter with no reads
// (a language's picture) draws bare and asks nothing; the still's line
// shows only with a data layer, and the toast once.
function test_image_editor_data_still_editor()
{
	$r = image_editor_data_menu_node(
		'window.pgImageEditorConfig.strings.dataBare = "BARE"; window.pgImageEditorConfig.strings.dataStill = "STILL";'
		. 'var cols = { "11": { name: "Region" }, "12": { name: "Revenue" } }, rows = [{ cells: { "11": "North", "12": 5 } }, { cells: { "11": "South", "12": 9 } }];'
		. 'var e = fake({}), out = {}, d = M.dataStart(7, "bar", cols, rows, "Sales");'
		. 'function texts(m) { return m.filter(function (x) { return x.t === "text"; }).map(function (x) { return x.v; }); }'
		. 'out.line = e.dataStillLine();'
		. 'e.doc.layers = [{ id: 1, type: "data", x: 0, y: 0, w: 600, h: 338, rot: 0, opacity: 1, data: d }];'
		. 'e.dataRead(7, { columns: cols, rows: rows });'
		. 'var seen = []; e.drawArtboard = function () { seen.push(texts(this.dataMarksOf(this.doc.layers[0]))); return "png"; };'
		. 'out.png = e.drawArtboardBare(); out.bare = seen[0]; out.flag = !!e.dataBareDraw; out.rows = texts(e.dataMarksOf(e.doc.layers[0]));'
		. 'var p = Object.create(Editor.prototype); p.doc = e.doc; p.asked = []; p.request = function (b) { p.asked.push(b); return { then: function () {} }; };'
		. 'out.painter = texts(p.dataMarksOf(e.doc.layers[0])); out.painterAsked = p.asked.length;'
		. 'var l = e.dataStillLine(); out.still = [l.className, l.textContent];'
		. 'var said = []; e.showMessage = function (m) { said.push(m); }; e.dataStillSay(); e.dataStillSay(); out.said = said;'
		. 'console.log(JSON.stringify(out));', true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(null, $r['line'], 'no data layer, no line');
	pg_assert_same('png', $r['png']);
	pg_assert_true(in_array('BARE', $r['bare'], true), 'the bare drawing says where the rows show');
	pg_assert_false(in_array('North', $r['bare'], true), 'and draws no row');
	pg_assert_false($r['flag'], 'the flag is put back');
	pg_assert_true(in_array('North', $r['rows'], true), 'the editor draws its rows again');
	pg_assert_true(in_array('BARE', $r['painter'], true), 'a painter with no reads draws bare');
	pg_assert_same(0, $r['painterAsked'], 'and asks nothing');
	pg_assert_same(array('pgie-note pgie-datastill', 'STILL'), $r['still']);
	pg_assert_same(array('STILL'), $r['said'], 'said once');
}

// The save draws the Workspace's file bare; the pending canvas's picture is
// drawn bare without reading rows; the export panel, Save animation and the
// Save to Files window carry the line; the labels are translated.
function test_image_editor_data_still_wiring()
{
	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$core = file_get_contents($js . 'pg_image_editor.js');
	$picture = file_get_contents($js . 'workspace_canvas_picture.js');

	pg_assert_contains('M.dataSendBare(mode, to)) ? this.drawArtboardBare() : this.drawArtboard()', $core, 'the save');
	pg_assert_contains('self.dataStillSay()', $core, 'the download says so');
	pg_assert_contains('this.dataStillLine ? this.dataStillLine() : null', $core, 'the Save to Files window');
	pg_assert_contains('this.dataStillLine()', file_get_contents($js . 'pg_image_editor_export.js'), 'the export panel');
	pg_assert_contains('this.dataStillLine()', file_get_contents($js . 'pg_image_editor_render.js'), 'Save animation');
	pg_assert_contains('render(loaded[1], null, true)', $picture, 'the pending picture is bare');
	pg_assert_false(strpos($picture, 'file.canvas_data ? fetchReads') !== false, 'and reads no rows');
	pg_assert_contains('painter.dataBareDraw = !!bare', $picture);

	$strings = pg_image_editor_strings_data();

	pg_assert_true(isset($strings['dataBare'], $strings['dataStill']), 'the labels');
}

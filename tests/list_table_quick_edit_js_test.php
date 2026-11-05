<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The list table driver's quick edit mode (assets/js/pg_list_table.js,
 * window.PGListTable) in Node: the last save and its undo, the rows a
 * saved value is applied to, the value a cell saves. Skipped where Node
 * cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/list_table_js_test.php');

function test_list_table_quick_edit_history()
{
	$result = list_table_js_test_node(<<<'JS'
var h = new T.QuickEdits(), out = {};
out.empty = [h.undoable(), h.takeUndo()];
h.record([{ id: '7', field: 'price', before: '10', after: '12' }]);
out.one = h.undoable();
h.record([{ id: '7', field: 'price', before: '12', after: '12' }]);
out.unchanged = h.undoable();
h.record([{ id: '7', field: 'price', before: '12', after: '15' }, { id: '8', field: 'price', before: '9', after: '15' }, { id: '9', field: 'price', before: '15', after: '15' }]);
out.group = h.takeUndo();
out.afterTake = [h.undoable(), h.takeUndo()];
h.record([{ id: '1', field: 'a', before: 'x', after: 'y' }]);
h.drop();
out.dropped = h.undoable();
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(false, array()), $result['empty']);
	pg_assert_same(true, $result['one']);
	pg_assert_same(true, $result['unchanged'], 'a save that changed nothing keeps the one before undoable');
	pg_assert_same(array(
		array('id' => '7', 'field' => 'price', 'value' => '12'),
		array('id' => '8', 'field' => 'price', 'value' => '9'),
	), $result['group'], 'the whole last save goes back, rows it did not change left out');
	pg_assert_same(array(false, array()), $result['afterTake'], 'one undo, then nothing');
	pg_assert_same(false, $result['dropped']);
}

function test_list_table_quick_edit_targets()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
var rows = [{ id: '1', editable: true }, { id: '2', editable: false }, { id: '3', editable: true }, { id: '4', editable: true }];
s.only('1');
out.alone = T.applyTargets(rows, s, '1');
s.toggle('2'); s.toggle('3');
out.ids = T.applyTargets(rows, s, '1');
out.notSelected = T.applyTargets(rows, s, '4');
s.selectAll(1000); s.toggle('4');
out.all = T.applyTargets(rows, s, '3');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(), $result['alone'], 'a row selected alone offers nothing');
	pg_assert_same(array('3'), $result['ids'], 'the other selected rows that can change, in screen order');
	pg_assert_same(array(), $result['notSelected'], 'a save on a row outside the selection offers nothing');
	pg_assert_same(array('1'), $result['all'], 'Ctrl+A: the loaded rows less the exceptions');
}

function test_list_table_quick_edit_value()
{
	$result = list_table_js_test_node(<<<'JS'
console.log(JSON.stringify([
  T.quickValue('text', '  Blue shirt '),
  T.quickValue('select', ' a '),
  T.quickValue('money', '12,50'),
  T.quickChanged('text', ' 5 ', '5'),
  T.quickChanged('money', '12,50', '12,5'),
  T.quickValue('textarea', ' One\r\ntwo \n'),
  T.quickChanged('textarea', 'One\ntwo', 'One\r\ntwo'),
  T.quickChanged('textarea', 'One\ntwo!', 'One\r\ntwo')
]));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('Blue shirt', ' a ', '12,50', false, true, "One\ntwo", false, true), $result);
}

/**
 * A quick edit field is named by its row's first cell as shown: the gift
 * card list's notes field was labelled "Notes: 6579-6686-6645-5163Expired"
 * (textContent ran the code and the word under it together).
 */
function test_list_table_quick_edit_row_name()
{
	$result = list_table_js_test_node(<<<'JS'
var text = function (value) { return { nodeType: 3, nodeValue: value }; };
var el = function (tag, children, attrs) {
  attrs = attrs || {};
  return { nodeType: 1, tagName: tag, childNodes: children, hidden: !!attrs.hidden, getAttribute: function (name) { return (name in attrs) ? attrs[name] : null; } };
};
var cell = el('TD', [
  el('A', [el('SPAN', [text('6579-6686-6645-5163')])]),
  el('SPAN', [text('6579668666455163')], { hidden: true }),
  el('DIV', [text('Expired')])
]);
var icon = el('TD', [el('I', [text('edit')], { 'aria-hidden': 'true' }), text(' Blue '), el('B', [text('shirt')]), text('s\n  ')]);
console.log(JSON.stringify([T.rowNameText(cell), T.rowNameText(icon), T.rowNameText(null)]));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('6579-6686-6645-5163 Expired', 'Blue shirts', ''), $result);
}

/**
 * A select whose options carry a class (the Pages list's folders, coloured
 * by their access type) wears the chosen option's class, and only that one:
 * it used to take the cell's colour for itself and every option.
 */
function test_list_table_quick_edit_select_tint()
{
	$result = list_table_js_test_node(<<<'JS'
var list = function (names) {
  var set = names.slice();
  return {
    add: function (n) { if (set.indexOf(n) < 0) { set.push(n); } },
    remove: function (n) { set = set.filter(function (m) { return m !== n; }); },
    all: function () { return set.slice(); }
  };
};
var select = { classList: list(['form-control', 'pg-list-table-field']), selectedIndex: 1,
  options: [{ className: 'public' }, { className: 'private' }, { className: '' }] };
var out = {};
T.tintSelect(select); out.first = select.classList.all();
select.selectedIndex = 0; T.tintSelect(select); out.changed = select.classList.all();
select.selectedIndex = 2; T.tintSelect(select); out.plain = select.classList.all();
select.selectedIndex = -1; T.tintSelect(select); out.none = select.classList.all();
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('form-control', 'pg-list-table-field', 'private'), $result['first'], 'the chosen option\'s class');
	pg_assert_same(array('form-control', 'pg-list-table-field', 'public'), $result['changed'], 'the old class goes when the choice changes');
	pg_assert_same(array('form-control', 'pg-list-table-field'), $result['plain'], 'an option without a class leaves the select plain');
	pg_assert_same(array('form-control', 'pg-list-table-field'), $result['none'], 'nothing chosen');
}

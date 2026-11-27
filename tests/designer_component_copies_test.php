<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Copies of a shared component's elements in the designer: an element
 * duplicated or pasted inside a component gets field ids of its own, a copy
 * on a page keeps no open field, and Detach copies the component as the
 * place draws it (its own values and variant) without the component's
 * marks. The core functions of assets/js/style_designer.src.js run in Node
 * with the model (assets/js/pg_component_overrides.js) and a stub kit;
 * skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node after the named four-space functions of the core,
// the model as MODEL (not loaded for the core until the script says so)
// and the stubs; the script prints its own JSON. Null without Node or when
// a function is missing.
function designer_component_copies_node($code)
{
	$core = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
	$fns = '';

	foreach (array('_sdCoEntries', '_sdCoNewFieldId', '_sdCoCopyFields', '_findParentInShared', '_sdAnyParent', 'onDuplicate', '_sdPasteNodes', 'detachShared', '_sdDetachPlace') as $name) {
		$start = strpos($core, "\n    function " . $name . '(');
		$end = ($start === false) ? false : strpos($core, "\n    }\n", $start);

		if (!pg_assert_true($end !== false, 'the core has ' . $name . '()')) {
			return null;
		}

		$fns .= substr($core, $start, $end - $start + 6) . "\n";
	}

	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$stubs = <<<'JS'
var MODEL = window.PGComponentOverrides; delete window.PGComponentOverrides;
var loaded = false, loadFails = false, loads = 0, failed = 0, saved = 0, n = 0;
var tree, _sharedCache, _sharedDirty, selectedNode = null, selectedNodes = [], _sdCoVar = null, spot = null;
function _sdT(s) { return s; }
function cloneTree(x) { return JSON.parse(JSON.stringify(x)); }
function gid() { return 'g' + (++n); }
function saveState() { saved++; }
function render() {}
function expandAncestors() {}
function _sdMayEdit() { return true; }
function _blockDuplicateWithToast() { return false; }
function _sdCoVarFinish() {}
function _sdPasteSpot() { return spot; }
function _sdPartFailed() { failed++; }
function _sdPartLoaded(name) { return name === 'overrides' && loaded; }
function _sdPartLoad(name) {
    loads++;
    return new Promise(function (ok, no) {
        setTimeout(function () {
            if (loadFails) { no(new Error('no part')); return; }
            loaded = true; window.PGComponentOverrides = MODEL; ok();
        }, 0);
    });
}
function useModel() { loaded = true; window.PGComponentOverrides = MODEL; }
function findParent(node, root) {
    if (!root || !root.children) return null;
    for (var i = 0; i < root.children.length; i++) {
        if (root.children[i] === node) return root;
        var f = findParent(node, root.children[i]);
        if (f) return f;
    }
    return null;
}
// A component (7) with an open heading text and an open note, an order and
// the variant "Dark"; a system widget (9); a page placing 7 with its own
// heading, the note hidden and "Dark"; an element left on the page by an
// earlier detach with the component's marks.
function fresh() {
    var h = { _id: 'h', type: 'semantic', props: { tag: 'h2', text: 'Title', _open: [{ id: 'of_title001', kind: 'text', label: 'Heading' }] }, children: [] };
    var note = { _id: 'n', type: 'semantic', props: { tag: 'p', text: 'Note', _open: [{ id: 'of_note0001', kind: 'shown', label: 'Note' }] }, children: [] };
    var card = { _id: 'c', type: 'semantic', props: { tag: 'div', className: 'card' }, children: [h, note] };
    var comp = { _id: 'r', type: 'root', props: { _openOrder: ['of_note0001', 'of_title001'], _variants: [{ name: 'Dark', nodes: { c: { className: 'card bg-dark' } } }] }, children: [card] };
    var sys = { _id: 's', type: 'root', props: {}, children: [] };
    var place = { _id: 'ref', type: 'shared_ref', props: { sharedId: 7, _place: { of_title001: 'Own title', of_note0001: false }, _variant: 'Dark' }, children: [] };
    var stale = { _id: 'old', type: 'semantic', props: { tag: 'div', _open: [{ id: 'of_title001', kind: 'text', label: 'Heading' }], _openOrder: ['of_title001'], _variants: [] }, children: [] };
    tree = { _id: 'p', type: 'root', props: {}, children: [place, stale] };
    _sharedCache = { 7: { tree: comp }, 9: { tree: sys, system_region_config: { kind: 'login' } } };
    _sharedDirty = {};
    selectedNode = null; saved = 0; failed = 0; loads = 0; loadFails = false; n = 0;
    loaded = false; delete window.PGComponentOverrides;
    return { comp: comp, card: card, h: h, note: note, sys: sys, place: place, stale: stale };
}
function marks(x) { var s = JSON.stringify(x); return ['"_open"', '"_openOrder"', '"_variants"'].filter(function (k) { return s.indexOf(k) !== -1; }); }
function openIds(x) {
    var ids = [];
    (function walk(nd) { ((nd.props && nd.props._open) || []).forEach(function (e) { ids.push(e.id); }); (nd.children || []).forEach(walk); })(x);
    return ids;
}
var tick = function () { return new Promise(function (ok) { setTimeout(ok, 5); }); };
JS;

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_component_overrides.js') . "\n"
		. $stubs . "\n" . $fns . "\n" . $code . "\n";
	$file = (string) tempnam(sys_get_temp_dir(), 'pgcc');
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return pg_assert_true(is_array($res), 'JSON from Node') ? $res : null;
}

// An element duplicated inside a component gets its own field ids, so a
// place's value for the original no longer goes to the first of two nodes
// sharing one id; the label stays. The model need not be loaded (#11999).
function test_designer_component_copies_duplicate_gets_own_field_ids()
{
	$res = designer_component_copies_node(<<<'JS'
var f = fresh(), out = {};
onDuplicate(f.h);
var copy = f.card.children[1];
out.one = { order: f.card.children.map(function (x) { return x._id; }), orig: f.h.props._open[0].id, copy: copy.props._open[0],
    valid: MODEL.validFieldId(copy.props._open[0].id), fields: MODEL.openFields(f.comp).length, dirty: !!_sharedDirty[7], saved: saved };
f = fresh();
onDuplicate(f.card);
var ids = openIds(f.comp);
out.card = { ids: ids.length, distinct: Object.keys(ids.reduce(function (m, id) { m[id] = 1; return m; }, {})).length, fields: MODEL.openFields(f.comp).length,
    order: f.comp.props._openOrder, rootMarks: marks(f.comp.children[1]).sort() };
f = fresh();
onDuplicate(f.stale);
out.page = { copy: marks(tree.children[2]), orig: marks(f.stale).length };
console.log(JSON.stringify(out));
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('h', 'g1', 'n'), $res['one']['order'], 'the copy right after the original');
	pg_assert_same('of_title001', $res['one']['orig'], 'the original keeps its field');
	pg_assert_true($res['one']['copy']['id'] !== 'of_title001', 'the copy has a field id of its own');
	pg_assert_true($res['one']['valid'], 'in the model\'s form');
	pg_assert_same(array('text', 'Heading'), array($res['one']['copy']['kind'], $res['one']['copy']['label']), 'same kind, same label');
	pg_assert_same(3, $res['one']['fields'], 'two text fields and the note');
	pg_assert_true($res['one']['dirty'], 'the component is changed');
	pg_assert_same(1, $res['one']['saved'], 'one undo step');

	pg_assert_same(4, $res['card']['ids'], 'a card copied with its two fields');
	pg_assert_same(4, $res['card']['distinct'], 'each id once');
	pg_assert_same(4, $res['card']['fields']);
	pg_assert_same(array('of_note0001', 'of_title001'), $res['card']['order'], 'the order is the component root\'s and stays');
	pg_assert_same(array('"_open"'), $res['card']['rootMarks'], 'the copy keeps its open fields only');

	pg_assert_same(array(), $res['page']['copy'], 'a copy on a page has no open field, order or variants');
	pg_assert_same(3, $res['page']['orig'], 'the original is left as it is');
}

// Pasted copies: new ids in an ordinary component (two copies of one
// element two different ids), no open field on a page or in a system
// widget (#11999).
function test_designer_component_copies_paste_gets_own_field_ids()
{
	$res = designer_component_copies_node(<<<'JS'
var f = fresh(), out = {};
spot = { parent: f.card, index: 2 };
_sdPasteNodes([f.h, f.h, f.note]);
var ids = openIds(f.comp);
out.comp = { kids: f.card.children.length, ids: ids, fields: MODEL.openFields(f.comp).length, dirty: !!_sharedDirty[7], selected: selectedNode === f.card.children[4] };
f = fresh();
spot = { parent: tree, index: 0 };
_sdPasteNodes([f.h]);
out.page = { marks: marks(tree.children[0]), text: tree.children[0].props.text };
f = fresh();
spot = { parent: f.sys, index: 0 };
_sdPasteNodes([f.card]);
out.sys = { marks: marks(f.sys.children[0]), dirty: !!_sharedDirty[9] };
console.log(JSON.stringify(out));
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(5, $res['comp']['kids']);
	pg_assert_same(5, count(array_unique($res['comp']['ids'])), 'every pasted copy has ids of its own');
	pg_assert_same(array('of_title001', 'of_note0001'), array_slice($res['comp']['ids'], 0, 2), 'the originals keep theirs');
	pg_assert_same(5, $res['comp']['fields']);
	pg_assert_true($res['comp']['dirty']);
	pg_assert_true($res['comp']['selected'], 'the last copy is selected');

	pg_assert_same(array(), $res['page']['marks'], 'pasted on a page: no open field');
	pg_assert_same('Title', $res['page']['text']);
	pg_assert_same(array(), $res['sys']['marks'], 'nor in a system widget');
	pg_assert_true($res['sys']['dirty']);
}

// Detach copies the component as the place draws it: the place's own text,
// a hidden element left out, the variant's classes; no _open, _openOrder or
// _variants on the page; new ids; the component untouched. The model is
// loaded first when the place has values, and a failed load leaves the
// place as it was (#12000).
function test_designer_component_copies_detach_keeps_the_place()
{
	$res = designer_component_copies_node(<<<'JS'
var out = {};
function shape(x) { return { id: x._id, cls: x.props.className || '', text: x.props.text || '', kids: (x.children || []).map(shape) }; }
var f = fresh();
useModel();
detachShared(f.place);
var copy = tree.children[0];
out.loaded = { type: copy.type, shape: shape(copy), marks: marks(copy), selected: selectedNode === copy, saved: saved, compText: f.h.props.text, compMarks: marks(f.comp).length };
f = fresh();
detachShared(f.place);
out.waits = { first: tree.children[0] === f.place, loads: loads };
tick().then(function () {
    out.waits.after = shape(tree.children[0]);
    out.waits.marks = marks(tree.children[0]);
    f = fresh();
    loadFails = true;
    detachShared(f.place);
    return tick();
}).then(function () {
    out.fails = { kept: tree.children[0] === f.place, failed: failed, saved: saved };
    f = fresh();
    delete f.place.props._place;
    delete f.place.props._variant;
    detachShared(f.place);
    out.plain = { shape: shape(tree.children[0]), marks: marks(tree.children[0]), loads: loads };
    console.log(JSON.stringify(out));
});
JS
	);

	if ($res === null) {
		return;
	}

	$placed = array('id' => 'g1', 'cls' => '', 'text' => '', 'kids' => array(
		array('id' => 'g2', 'cls' => 'card bg-dark', 'text' => '', 'kids' => array(
			array('id' => 'g3', 'cls' => '', 'text' => 'Own title', 'kids' => array()),
		)),
	));
	pg_assert_same('root', $res['loaded']['type']);
	pg_assert_same($placed, $res['loaded']['shape'], 'own text, the note hidden, the variant\'s class, new ids');
	pg_assert_same(array(), $res['loaded']['marks'], 'none of the component\'s marks');
	pg_assert_true($res['loaded']['selected'], 'the copy is selected');
	pg_assert_same(1, $res['loaded']['saved'], 'one undo step');
	pg_assert_same('Title', $res['loaded']['compText'], 'the component keeps its own text');
	pg_assert_same(3, $res['loaded']['compMarks'], 'and its marks');

	pg_assert_true($res['waits']['first'], 'the place stays while the model loads');
	pg_assert_same(1, $res['waits']['loads']);
	pg_assert_same($placed, $res['waits']['after'], 'then it is detached as the place draws');
	pg_assert_same(array(), $res['waits']['marks']);

	pg_assert_true($res['fails']['kept'], 'a failed load never detaches without the place\'s values');
	pg_assert_same(1, $res['fails']['failed'], 'and says so');
	pg_assert_same(0, $res['fails']['saved'], 'with no undo step');

	pg_assert_same(0, $res['plain']['loads'], 'a place with nothing of its own needs no model');
	pg_assert_same(array('id' => 'g1', 'cls' => '', 'text' => '', 'kids' => array(
		array('id' => 'g2', 'cls' => 'card', 'text' => '', 'kids' => array(
			array('id' => 'g3', 'cls' => '', 'text' => 'Title', 'kids' => array()),
			array('id' => 'g4', 'cls' => '', 'text' => 'Note', 'kids' => array()),
		)),
	)), $res['plain']['shape'], 'the component as it is');
	pg_assert_same(array(), $res['plain']['marks'], 'without its marks');
}

// The other copy paths take the same step right after the copy is in its
// tree: the Duplicate rows of the canvas and Overview menus (inline
// handlers) and Alt+Drag in handleDrop().
function test_designer_component_copies_every_copy_path_takes_new_ids()
{
	$core = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));

	pg_assert_true(strpos($core, "owner.parent.children.splice(idx + 1, 0, cloned);\n                        _sdCoCopyFields(cloned, owner.sid);") !== false,
		'the canvas menu\'s Duplicate gives the copy its own field ids');
	pg_assert_true(strpos($core, "owner.parent.children.splice(owner.parent.children.indexOf(tgt) + 1, 0, cl);\n                    _sdCoCopyFields(cl, owner.sid);") !== false,
		'the Overview menu\'s Duplicate too');

	$at = strpos($core, "\n    function handleDrop(");
	$end = ($at === false) ? false : strpos($core, "\n    }\n", $at);
	if (!pg_assert_true($end !== false, 'the core has handleDrop()')) {
		return;
	}
	$fn = substr($core, $at, $end - $at);
	$put = strpos($fn, 'target.children.push(n);');
	$copy = strpos($fn, 'if (altCopy) _sdCoCopyFields(n, _targetOwnerSid);');
	pg_assert_true($put !== false && $copy !== false && $copy > $put, 'an Alt+Drag copy gets its own field ids once it is in place');
}

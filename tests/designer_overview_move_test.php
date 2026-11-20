<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests moving an element of the designer's Overview without dragging: the
 * keys on a focused row (_sdTreeRowKey()), where a key move goes
 * (_sdTreeKeyPlan()), each move judged by canDrop() before anything changes
 * (_sdMoveCheck()), one undo step with the moved element selected and shown
 * (_sdMoveNodes()), a refused move saying why and changing nothing; the
 * "Move…" window part finding a target by name with its path. Runs the
 * functions under Node; skipped where there is no Node.
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
function designer_overview_move_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

function designer_overview_move_js($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
}

// Runs a Node script after the named functions of `$file`; one JSON value
// per output line. Null without Node or when a function is missing.
function designer_overview_move_run($file, $names, $script)
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

	$js = designer_overview_move_js($file);
	$src = '';

	foreach ($names as $name) {
		$fn = designer_overview_move_fn($js, $name);

		if (!pg_assert_true($fn !== '', $file . ' has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgovmove');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return array_map(function ($l) { return json_decode($l, true); }, $out);
}

// The core's editor around the move: a page tree, canDrop() refusing what
// `refuse` names (a parent's name => true), and records of the undo steps,
// the toasts, the selection and the canvas scroll.
function designer_overview_move_stubs()
{
	return <<<'JS'
function _sdT(s, v) { if (Array.isArray(v)) { v.forEach(function (x, i) { s = s.replace('{var:' + (i + 1) + '}', x); }); return s; } return v === undefined ? s : s.replace('{var}', v); }
function esc(s) { return String(s); }
function N(name, kids, props) { var n = { name: name, type: 'semantic', props: props || {}, children: kids || [] }; return n; }
var log, refuse, tree, _sharedCache = {}, _sharedDirty = {}, _collab = { mayEdit: true };
function reset() { log = { steps: 0, toasts: [], asked: [], selected: null, shown: null }; refuse = {}; }
function findParentIn(n, at) { for (var i = 0; i < (at.children || []).length; i++) { if (at.children[i] === n) return at; var p = findParentIn(n, at.children[i]); if (p) return p; } return null; }
function _findParentAnywhere(n) { var p = findParentIn(n, tree); return p ? { parent: p, sid: 0 } : null; }
function hasLockedAncestor(n) { for (var p = findParentIn(n, tree); p; p = findParentIn(p, tree)) if (p.props._locked) return true; return false; }
function _sdCanEditNode(n) { return !n.props._noEdit; }
function _sdMayEdit(n) { return !n.props._noEdit; }
function canDrop(data, parent) { log.asked.push(data.node.name + '>' + parent.name); return !refuse[parent.name]; }
function _sdTreeDropCode() { return 'rule'; }
function _sdTreeDropName(p) { return p === tree ? '<body>' : p.name; }
function _sdTreeRowName(n) { return n.name; }
function _resolveSharedSid() { return 0; }
function saveState() { log.steps++; }
function sdToast(m, t) { log.toasts.push(m); }
function _sdReadOnlyNudge() { log.toasts.push('read-only'); }
function _sdTreeMoveSelect(nodes) { log.selected = nodes.map(function (n) { return n.name; }); }
function scrollCanvasToNode(n) { log.shown = n.name; }
function names(n) { return (n.children || []).map(function (c) { return c.name; }); }
function out(v) { console.log(JSON.stringify(v)); }
JS;
}

// The keys on a focused row.
function test_designer_overview_move_row_keys()
{
	$r = designer_overview_move_run('style_designer.src.js', array('_sdTreeRowKey'), <<<'JS'
function K(key, m) { m = m || {}; return _sdTreeRowKey({ key: key, altKey: !!m.alt, ctrlKey: !!m.ctrl, metaKey: !!m.meta, shiftKey: !!m.shift }); }
console.log(JSON.stringify([K('ArrowUp'), K('ArrowDown'), K('ArrowLeft'), K('ArrowRight')]));
console.log(JSON.stringify([K('ArrowUp', { alt: 1 }), K('ArrowDown', { alt: 1 }), K('ArrowLeft', { alt: 1 }), K('ArrowRight', { alt: 1 })]));
console.log(JSON.stringify([K('x', { ctrl: 1 }), K('v', { meta: 1 }), K('X', { ctrl: 1 }), K('Escape')]));
console.log(JSON.stringify([K('ArrowUp', { shift: 1 }), K('ArrowUp', { ctrl: 1 }), K('ArrowUp', { ctrl: 1, alt: 1 }), K('c', { ctrl: 1 }), K('m'), K('Enter'), K('v', { ctrl: 1, shift: 1 })]));
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('prev', 'next', 'close', 'open'), $r[0], 'Up and Down walk the rows, Left and Right close and open');
	pg_assert_same(array('up', 'down', 'out', 'in'), $r[1], 'Alt with an arrow moves among siblings and one level out and in');
	pg_assert_same(array('cut', 'paste', 'cut', 'uncut'), $r[2], 'Ctrl+X cuts, Ctrl+V (Cmd+V) puts, Esc drops the cut');
	pg_assert_same(array('', '', '', '', '', '', ''), $r[3], 'other keys stay the editor\'s: Shift and Ctrl arrows, Ctrl+C, M, Enter, Ctrl+Shift+V');
}

// Where a key move goes, before canDrop() is asked.
function test_designer_overview_move_key_plan()
{
	$r = designer_overview_move_run('style_designer.src.js', array('_sdTreeKeyPlan'), <<<'JS'
console.log(JSON.stringify([_sdTreeKeyPlan('up', 2, 4, true), _sdTreeKeyPlan('up', 0, 4, true)]));
console.log(JSON.stringify([_sdTreeKeyPlan('down', 2, 4, true), _sdTreeKeyPlan('down', 3, 4, true)]));
console.log(JSON.stringify([_sdTreeKeyPlan('out', 1, 4, true), _sdTreeKeyPlan('out', 1, 4, false)]));
console.log(JSON.stringify([_sdTreeKeyPlan('in', 1, 4, true), _sdTreeKeyPlan('in', 0, 4, true)]));
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('ref' => 1, 'pos' => 'before'), array('refuse' => 'first')), $r[0], 'up: before the sibling above; the first refuses');
	pg_assert_same(array(array('ref' => 3, 'pos' => 'after'), array('refuse' => 'last')), $r[1], 'down: after the sibling below; the last refuses');
	pg_assert_same(array(array('ref' => 'parent', 'pos' => 'after'), array('refuse' => 'top')), $r[2], 'out: after its parent; the top level refuses');
	pg_assert_same(array(array('ref' => 0, 'pos' => 'inside'), array('refuse' => 'above')), $r[3], 'in: last inside the sibling above; with none it refuses');
}

// Each key's move goes through canDrop(), is one undo step, leaves the moved
// element selected and shown; a refused one changes nothing and says why.
function test_designer_overview_move_keys_move()
{
	$fns = array('_sdTreeKeyPlan', '_sdTreeKeyWhy', '_sdTreeDropWhy', '_sdMoveCheck', '_sdMoveNodes', '_sdTreeKeyMove', 'isDescendant');
	$r = designer_overview_move_run('style_designer.src.js', $fns, designer_overview_move_stubs() . <<<'JS'
var a, b, c, d, box, sec;
function page() {
    a = N('A'); b = N('B'); c = N('C'); d = N('D', [N('D1')]);
    box = N('Box', [a, b, c, d]); sec = N('Sec', [box]);
    tree = N('body', [sec]); tree.type = 'root';
    reset();
}
page(); _sdTreeKeyMove(b, 'up');
out([names(box), log.steps, log.selected, log.shown, log.asked]);
page(); _sdTreeKeyMove(b, 'down');
out([names(box), log.steps, log.asked]);
page(); _sdTreeKeyMove(b, 'out');
out([names(box), names(sec), log.steps, log.asked]);
page(); _sdTreeKeyMove(b, 'in');
out([names(box), names(a), log.steps, log.asked]);
page(); _sdTreeKeyMove(c, 'in');
out([names(b), log.steps, log.selected]);
page(); _sdTreeKeyMove(a, 'up');
out([names(box), log.steps, log.toasts, log.asked]);
page(); _sdTreeKeyMove(sec, 'out');
out([log.steps, log.toasts]);
page(); refuse.A = true; _sdTreeKeyMove(b, 'in');
out([names(box), names(a), log.steps, log.toasts, log.selected]);
page(); b.props._locked = true; _sdTreeKeyMove(b, 'up');
out([names(box), log.steps, log.toasts]);
page(); a.props._noEdit = true; _sdTreeKeyMove(b, 'up');
out([names(box), log.steps, log.toasts]);
page(); _collab.mayEdit = false; _sdTreeKeyMove(b, 'up'); _collab.mayEdit = true;
out([names(box), log.steps, log.toasts]);
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('B', 'A', 'C', 'D'), 1, array('B'), 'B', array('B>Box')), $r[0], 'Alt+Up: before the sibling above, through canDrop(), one undo step, selected and shown');
	pg_assert_same(array(array('A', 'C', 'B', 'D'), 1, array('B>Box')), $r[1], 'Alt+Down: after the sibling below');
	pg_assert_same(array(array('A', 'C', 'D'), array('Box', 'B'), 1, array('B>Sec')), $r[2], 'Alt+Left: after its parent, canDrop() asked of the grandparent');
	pg_assert_same(array(array('A', 'C', 'D'), array('B'), 1, array('B>A')), $r[3], 'Alt+Right: last inside the sibling above');
	pg_assert_same(array(array('C'), 1, array('C')), $r[4], 'into a sibling that already holds elements: at its end');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), 0, array('The element is already the first one here'), array()), $r[5], 'the first one up: nothing moves, the reason is said, canDrop() not asked');
	pg_assert_same(array(0, array('The element is already at the top level')), $r[6], 'a section cannot go one level out');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), array(), 0, array('A does not take this element'), null), $r[7], 'canDrop() refusing: nothing moves, no undo step, the reason is said');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), 0, array('B is locked')), $r[8], 'a locked element does not move');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), 0, array('Box is outside the areas you may edit')), $r[9], 'a place outside the editable areas refuses');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), 0, array('read-only')), $r[10], 'a session that may not edit moves nothing');
}

// The window's and Ctrl+V's moves: before, into, after any element, several
// at once; one put back in its place, or into itself, refused.
function test_designer_overview_move_window_moves()
{
	$fns = array('_sdTreeDropWhy', '_sdMoveCheck', '_sdMoveNodes', 'isDescendant');
	$r = designer_overview_move_run('style_designer.src.js', $fns, designer_overview_move_stubs() . <<<'JS'
var a, b, c, d, box, sec, foot;
function page() {
    a = N('A'); b = N('B'); c = N('C'); d = N('D', [N('D1')]);
    box = N('Box', [a, b, c, d]); sec = N('Sec', [box]); foot = N('Foot');
    tree = N('body', [sec, foot]); tree.type = 'root';
    reset();
}
page(); _sdMoveNodes([a, c], foot, 'inside');
out([names(box), names(foot), log.steps, log.selected, log.shown, log.asked]);
page(); _sdMoveNodes([d], foot, 'after');
out([names(tree), names(box), log.steps]);
page(); _sdMoveNodes([foot], sec, 'before');
out([names(tree), log.steps]);
page(); _sdMoveNodes([b], a, 'after');
out([names(box), log.steps, log.toasts]);
page(); _sdMoveNodes([d], d.children[0], 'inside');
out([log.steps, log.toasts]);
page(); _sdMoveNodes([b], tree, 'before');
out([log.steps, log.toasts]);
page(); _sdMoveNodes([b], tree, 'inside');
out([names(tree), log.steps]);
page(); refuse.Foot = true; _sdMoveNodes([a, c], foot, 'inside');
out([names(box), names(foot), log.steps, log.toasts]);
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('B', 'D'), array('A', 'C'), 1, array('A', 'C'), 'C', array('A>Foot', 'C>Foot')), $r[0], 'two elements into another: each asked of canDrop(), one undo step, both selected');
	pg_assert_same(array(array('Sec', 'Foot', 'D'), array('A', 'B', 'C'), 1), $r[1], 'after an element of another branch');
	pg_assert_same(array(array('Foot', 'Sec'), 1), $r[2], 'before a section');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), 0, array('The element is already here')), $r[3], 'put back where it is: refused as already here');
	pg_assert_same(array(0, array('An element cannot go inside itself')), $r[4], 'into its own child: refused');
	pg_assert_same(array(0, array('Nothing can go before or after <body>')), $r[5], 'before the page body: refused');
	pg_assert_same(array(array('Sec', 'Foot', 'B'), 1), $r[6], 'into the page body: at the end of the page');
	pg_assert_same(array(array('A', 'B', 'C', 'D'), array(), 0, array('Foot does not take this element')), $r[7], 'one refused by canDrop() keeps all in place');
}

// The "Move…" window's targets: by name with Turkish letters folded, each
// with its path; the moved element and what it holds are left out.
function test_designer_overview_move_window_search()
{
	$r = designer_overview_move_run('style_designer_move.js', array('_mvFold', '_mvIndex', '_mvMatch', '_mvPathCut'), <<<'JS'
function N(name, kids) { return { name: name, children: kids || [] }; }
var moved = N('Moved', [N('Inside')]);
var root = N('body', [N('Hesap Çubuğu', [N('Hesap alanı', [N('İkon Kutusu'), moved, N('Menü Öğesi', [N('Bağlantı')])])]), N('Footer')]);
var entries = _mvIndex(root, function (n) { return n.children; }, function (n) { return n.name; }, [moved]);
console.log(JSON.stringify(entries.map(function (e) { return [e.name, e.path]; })));
console.log(JSON.stringify(_mvMatch(entries, 'ikon').map(function (h) { return [entries[h.i].name, h.at, h.len]; })));
console.log(JSON.stringify(_mvMatch(entries, 'menu oge').map(function (h) { return entries[h.i].name; })));
console.log(JSON.stringify(_mvMatch(entries, 'alan').map(function (h) { return entries[h.i].name; })));
console.log(JSON.stringify([_mvMatch(entries, '').length, _mvMatch(entries, 'zzz').length]));
console.log(JSON.stringify([_mvPathCut(['a', 'b', 'c', 'd', 'e', 'f']), _mvPathCut(['a', 'b'])]));
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		array('<body>', array()),
		array('Hesap Çubuğu', array()),
		array('Hesap alanı', array('Hesap Çubuğu')),
		array('İkon Kutusu', array('Hesap Çubuğu', 'Hesap alanı')),
		array('Menü Öğesi', array('Hesap Çubuğu', 'Hesap alanı')),
		array('Bağlantı', array('Hesap Çubuğu', 'Hesap alanı', 'Menü Öğesi')),
		array('Footer', array()),
	), $r[0], 'every element in the Overview\'s order with its path, the body first, the moved element and its inside left out');
	pg_assert_same(array(array('İkon Kutusu', 0, 4)), $r[1], '"ikon" finds "İkon", marked from its start');
	pg_assert_same(array('Menü Öğesi'), $r[2], 'every word, letters with marks folded');
	pg_assert_same(array('Hesap alanı'), $r[3], 'a word inside the name');
	pg_assert_same(array(7, 0), $r[4], 'an empty search lists all; no match lists none');
	pg_assert_same(array(array('a', '…', 'e', 'f'), array('a', 'b')), $r[5], 'a long path: its section, a gap, the last two');
}

// Where the window and the keys are reached from, and the part's place.
function test_designer_overview_move_wiring()
{
	$core = designer_overview_move_js('style_designer.src.js');
	$part = designer_overview_move_js('style_designer_move.js');

	pg_assert_true(strpos($part, "StyleDesigner.definePart('move', function (K) {") !== false, 'the move window is a part');
	pg_assert_same('assets/js/style_designer_move.min.js', pg_designer_parts()['move'] ?? '', 'the part is listed');
	pg_assert_same(array('assets/js/style_designer_move.js'), pg_js_bundles()['assets/js/style_designer_move.min.js'] ?? array(), 'built from its file');
	pg_assert_true(strpos($core, "_sdPartCall('move', 'open'") !== false, 'the core opens the window through the part');
	pg_assert_same(2, substr_count($core, '_sdMoveKey(e)) {'), 'M opens the window from the canvas and from the editor');
	pg_assert_true(strpos($core, "mvBtn.addEventListener('click', function (e) { e.stopPropagation(); _sdMoveOpen([node]); });") !== false, 'the canvas toolbar has the Move… button');
	pg_assert_true(strpos($core, "label: _sdT('Move…'), handler: function() { _sdMoveOpen(targets); }") !== false, 'an Overview row\'s menu has Move…');
	pg_assert_true(strpos(designer_overview_move_fn($core, 'renderTree'), '_sdTreeKeysBind(list);') !== false, 'each drawing of the tree binds the keys and places the row buttons');
	pg_assert_true(strpos(designer_overview_move_fn($core, 'buildTreeItems'), "li.tabIndex = (selectedNode === node) ? 0 : -1;") !== false, 'the selected row is the tree\'s tab stop');

	// The window's moves and the key moves go through the one move.
	pg_assert_true(strpos(designer_overview_move_fn($core, '_sdMoveHost'), '_sdMoveNodes(nodes, ref, pos)') !== false, 'the window moves through _sdMoveNodes()');
	pg_assert_true(strpos(designer_overview_move_fn($core, '_sdTreeKeyMove'), '_sdMoveNodes([node]') !== false, 'a key moves through _sdMoveNodes()');
}

test_designer_overview_move_row_keys();
test_designer_overview_move_key_plan();
test_designer_overview_move_keys_move();
test_designer_overview_move_window_moves();
test_designer_overview_move_window_search();
test_designer_overview_move_wiring();

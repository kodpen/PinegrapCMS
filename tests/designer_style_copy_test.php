<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Copy style" and "Paste style" of the Visual Page Editor and
 * the quick strip of the Options panel's Content tab: what is taken from
 * an element (its changed Style rows, never its content, link or data
 * binding), how it is fitted to another kind (a row it lacks left out and
 * counted), the paste as one undo step on one or several elements, and the
 * three rows of the quick strip per kind. Run in Node with the editor's own
 * helpers; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The block of the editor source from $from to $to; '' when it is not there.
function style_copy_test_block($from, $to)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$at = strpos($source, $from);
	$end = ($at === false) ? false : strpos($source, $to, $at);

	return ($end === false) ? '' : substr($source, $at, $end - $at);
}

// Runs `$code` in Node after the editor's blocks of what counts as changed,
// the panel controls, several elements and copy and paste style; its `out`
// comes back decoded. Null without Node.
function style_copy_test_node($code)
{
	$blocks = array(
		style_copy_test_block('    // ── What counts as changed: pure helpers', '    // ── end of what counts as changed'),
		style_copy_test_block('    // ── The Options panel controls: pure helpers', '    // ── end of the Options panel controls'),
		style_copy_test_block('    // ── Several elements selected: pure helpers', '    // ── end of several elements selected'),
		style_copy_test_block('    // ── Copy and paste style: pure helpers', '    // ── end of copy and paste style'),
	);
	pg_assert_true(!in_array('', $blocks, true), 'the editor has its copy and paste style helpers');

	if (in_array('', $blocks, true) || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. "function esc(s) { return String(s == null ? '' : s); }\n"
		. "function _sdT(s) { return s; }\n"
		. implode("\n", $blocks) . "\n"
		. "var out = null;\n"
		. "function g(list) { return { group: list }; }\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgstylecopy');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Copy takes the changed rows of Style only: a row at its default, a row of
// Content or Advanced (the text, the link, the data binding), an attribute
// and a prop that holds content are never taken, even when a Style row
// writes it; a row met twice (its per-size box) is taken once.
function test_style_copy_takes_changed_style_rows_only()
{
	$res = style_copy_test_node(<<<'JS'
var rows = [
    { tab: 'style', label: 'Alignment', spec: g(['text-start', 'text-center', 'text-end']), value: 'text-center', def: '' },
    { tab: 'style', label: 'Font Size', spec: g(['fs-1', 'fs-2', 'fs-3']), value: '', def: '' },
    { tab: 'style', label: 'Color', spec: g(['text-primary', 'text-danger']), value: 'text-danger', def: '' },
    { tab: 'style', label: 'Bottom', spec: { prefix: 'mb-' }, value: 'mb-4', def: '' },
    { tab: 'style', label: 'Bottom', spec: { prefix: 'mb-' }, value: 'mb-4', def: '' },
    { tab: 'style', label: 'Gradient', spec: { toggle: 'bg-gradient' }, value: '1', def: '' },
    { tab: 'style', label: 'Placeholder', spec: { prop: 'placeholderMode', bool: true }, value: '1', def: false },
    { tab: 'style', label: 'Focus Ring', spec: { prop: 'focusRing', bool: true }, value: '', def: false },
    { tab: 'style', label: 'Width', spec: { prop: 'width' }, value: '240px', def: '' },
    // Content, link and binding: on another tab, or a prop that holds them.
    { tab: 'content', label: 'Text', spec: { prop: 'text' }, value: 'Hello', def: '' },
    { tab: 'content', label: 'URL', spec: { prop: 'href' }, value: '/about', def: '' },
    { tab: 'content', label: 'Bound field', spec: { prop: '_bindings' }, value: 'title', def: '' },
    { tab: 'style', label: 'Link', spec: { prop: 'href' }, value: '/x', def: '' },
    { tab: 'style', label: 'Bind', spec: { prop: 'bindField' }, value: 'name', def: '' },
    { tab: 'style', label: 'Image', spec: { prop: 'src' }, value: '/a.png', def: '' },
    { tab: 'style', label: 'Role', spec: { attr: 'role' }, value: 'note', def: '' },
    { tab: 'advanced', label: 'Shadow', spec: g(['shadow-sm', 'shadow']), value: 'shadow', def: '' },
    null
];
var took = _sdStyleTake(rows);
out = {
    labels: took.map(function (r) { return r.label; }),
    values: took.map(function (r) { return r.value; }),
    keys: took.map(function (r) { return r.key; }),
    empty: _sdStyleTake([]).length,
    none: _sdStyleTake(null).length
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Alignment', 'Color', 'Bottom', 'Gradient', 'Placeholder', 'Width'), $res['labels'], 'the changed Style rows, each once, in the panel\'s order');
	pg_assert_same(array('text-center', 'text-danger', 'mb-4', '1', '1', '240px'), $res['values'], 'with their values');
	pg_assert_same('g:text-start,text-center,text-end', $res['keys'][0], 'a class group is keyed by its classes');
	pg_assert_same('u:mb-', $res['keys'][2], 'a spacing value by its prefix');
	pg_assert_same(0, $res['empty'], 'nothing to take');
	pg_assert_same(0, $res['none'], 'nothing given');
}

// Fitting to the target's Style rows: the same row is kept; a class group
// of another kind that holds the value takes it (written through the
// target's group); a row the target lacks is left out and counted.
function test_style_copy_fits_to_the_target_kind()
{
	$res = style_copy_test_node(<<<'JS'
var copied = _sdStyleTake([
    { tab: 'style', label: 'Alignment', spec: g(['text-start', 'text-center', 'text-end']), value: 'text-center', def: '' },
    { tab: 'style', label: 'Font Size', spec: g(['display-1', 'fs-1', 'fs-2']), value: 'display-1', def: '' },
    { tab: 'style', label: 'Color', spec: g(['text-primary', 'text-danger']), value: 'text-danger', def: '' },
    { tab: 'style', label: 'Bottom', spec: { prefix: 'mb-' }, value: 'mb-4', def: '' },
    { tab: 'style', label: 'Placeholder', spec: { prop: 'placeholderMode', bool: true }, value: '1', def: false }
]);
// An image: no text rows but a colour group of its own, spacing, and no
// placeholder.
var image = [
    g(['text-primary', 'text-danger', 'text-success']),
    { prefix: 'mb-' },
    { prefix: 'mt-' },
    g(['rounded-1', 'rounded-3'])
];
var fit = _sdStyleFit(copied, image);
// A heading has every row.
var heading = [g(['text-start', 'text-center', 'text-end']), g(['display-1', 'fs-1', 'fs-2']), g(['text-primary', 'text-danger']), { prefix: 'mb-' }, { prop: 'placeholderMode', bool: true }];
var all = _sdStyleFit(copied, heading);
out = {
    kept: fit.rows.map(function (r) { return r.label; }),
    group: fit.rows[0].spec.group,
    left: fit.left,
    leftLabels: fit.leftLabels,
    allLeft: all.left,
    allKept: all.rows.length,
    nothing: _sdStyleFit(copied, []).left
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Color', 'Bottom'), $res['kept'], 'the rows the image has');
	pg_assert_same(array('text-primary', 'text-danger', 'text-success'), $res['group'], 'a colour written through the image\'s own group');
	pg_assert_same(3, $res['left'], 'alignment, size and the placeholder are left out');
	pg_assert_same(array('Alignment', 'Font Size', 'Placeholder'), $res['leftLabels'], 'and named');
	pg_assert_same(0, $res['allLeft'], 'the same kind leaves nothing out');
	pg_assert_same(5, $res['allKept'], 'and keeps every row');
	pg_assert_same(5, $res['nothing'], 'a target without Style rows takes nothing');
}

// The paste writes every row to every selected element inside one
// _sdUndoAsOne(): one snapshot for one element and for three; the classes
// of a group are swapped, other classes kept; the edit after it is a step
// of its own.
function test_style_paste_is_one_undo_step_on_one_or_several()
{
	$res = style_copy_test_node(<<<'JS'
var run = { key: null, at: 0, one: null, seq: 0 };
var snaps = 0;
function saveState() { if (_sdStateRunTakes(run, run.one || null, 0, 700)) snaps++; }
function write(n, s, v) {
    if (s.group || s.prefix || s.toggle) n.props.cssClass = _sdMcClassWrite(n.props.cssClass, s, v);
    else n.props[s.prop] = s.bool ? v === '1' : v;
    saveState();
}
var rows = _sdStyleFit(_sdStyleTake([
    { tab: 'style', label: 'Alignment', spec: g(['text-start', 'text-center', 'text-end']), value: 'text-center', def: '' },
    { tab: 'style', label: 'Bottom', spec: { prefix: 'mb-' }, value: 'mb-4', def: '' },
    { tab: 'style', label: 'Placeholder', spec: { prop: 'placeholderMode', bool: true }, value: '1', def: false }
]), [g(['text-start', 'text-center', 'text-end']), { prefix: 'mb-' }, { prop: 'placeholderMode', bool: true }]).rows;
function paste(nodes) {
    _sdUndoAsOne(run, function () {
        _sdMcApply(nodes, saveState, function (n) { _sdStyleWrite(n, rows, write); });
    });
}
var one = [{ props: { cssClass: 'lead text-end mb-1' } }];
paste(one);
var afterOne = snaps;
var three = [{ props: { cssClass: 'text-start' } }, { props: { cssClass: '' } }, { props: { cssClass: 'mb-2 x' } }];
paste(three);
var afterThree = snaps;
saveState();
out = {
    one: afterOne,
    three: afterThree - afterOne,
    next: snaps - afterThree,
    cls: one[0].props.cssClass,
    clsThree: three.map(function (n) { return n.props.cssClass; }),
    prop: three.map(function (n) { return n.props.placeholderMode; })
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(1, $res['one'], 'one element: one undo step');
	pg_assert_same(1, $res['three'], 'three elements: one undo step');
	pg_assert_same(1, $res['next'], 'the edit after it is a step of its own');
	pg_assert_same('lead text-center mb-4', $res['cls'], 'a group\'s and a prefix\'s class swapped, the others kept');
	pg_assert_same(array('text-center mb-4', 'text-center mb-4', 'x text-center mb-4'), $res['clsThree'], 'every selected element gets the style');
	pg_assert_same(array(true, true, true), $res['prop'], 'a prop as well');
}

// What the browser keeps: a stored copy is read back only in its own shape;
// anything else reads as nothing copied.
function test_style_copy_clipboard_shape()
{
	$res = style_copy_test_node(<<<'JS'
var good = { v: 1, kind: 'content:heading', rows: [{ key: 'u:mb-', spec: { prefix: 'mb-' }, value: 'mb-4', label: 'Bottom' }] };
out = {
    good: _sdStyleClipRead(JSON.stringify(good)) !== null,
    rows: (_sdStyleClipRead(JSON.stringify(good)) || {}).rows.length,
    bad: [
        _sdStyleClipRead(null), _sdStyleClipRead(''), _sdStyleClipRead('{'),
        _sdStyleClipRead(JSON.stringify({ v: 2, rows: [] })),
        _sdStyleClipRead(JSON.stringify({ v: 1, rows: [{ spec: { prop: 'href' }, value: '/x' }] })),
        _sdStyleClipRead(JSON.stringify({ v: 1, rows: [{ spec: { attr: 'role' }, value: 'x' }] })),
        _sdStyleClipRead(JSON.stringify({ v: 1, rows: [] }))
    ]
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['good'], 'its own shape is read back');
	pg_assert_same(1, $res['rows'], 'with its rows');
	pg_assert_same(array(null, null, null, null, null, null, null), $res['bad'], 'nothing, broken text, another version, content, an attribute or no row read as nothing');
}

// The quick strip: three rows from the fixed list of the element's family
// (a heading: alignment, size, colour), found in the Style tab by a class
// or a prefix; once the browser counted changes for the kind, the rows
// changed most often first, the fixed list filling the rest; a row the
// panel lacks is skipped.
function test_style_quick_strip_rows_per_kind()
{
	$res = style_copy_test_node(<<<'JS'
function rowsOf(list) { return list.map(function (s) { return { key: _sdStyleKey(s.spec), spec: s.spec, label: s.label }; }); }
var heading = rowsOf([
    { label: 'Alignment', spec: g(['text-start', 'text-center', 'text-end']) },
    { label: 'Color', spec: g(['text-primary', 'text-danger']) },
    { label: 'Font Size', spec: g(['display-1', 'fs-1', 'fs-4']) },
    { label: 'Font Weight', spec: g(['fw-bold', 'fw-light']) },
    { label: 'Bottom', spec: { prefix: 'mb-' } },
    { label: 'Background Color', spec: g(['bg-primary', 'bg-dark']) },
    { label: 'Radius', spec: g(['rounded-1', 'rounded-3']) },
    { label: 'Shadow', spec: g(['shadow-sm', 'shadow']) }
]);
var labels = function (list) { return list.map(function (r) { return r.label; }); };
var weight = _sdStyleKey(g(['fw-bold', 'fw-light']));
var counts = {};
counts[weight] = 5;
counts[_sdStyleKey({ prefix: 'mb-' })] = 2;
counts['g:gone'] = 9;
out = {
    families: [
        _sdQuickFamily('content', '', 'heading'), _sdQuickFamily('semantic', 'p', ''), _sdQuickFamily('content', '', 'button'),
        _sdQuickFamily('content', '', 'image'), _sdQuickFamily('semantic', 'img', ''),
        _sdQuickFamily('semantic', 'section', ''), _sdQuickFamily('row', '', '')
    ],
    text: labels(_sdQuickPick(heading, 'text', {})),
    image: labels(_sdQuickPick(heading, 'image', {})),
    box: labels(_sdQuickPick(heading, 'box', {})),
    counted: labels(_sdQuickPick(heading, 'text', counts)),
    short: labels(_sdQuickPick(rowsOf([{ label: 'Color', spec: g(['text-primary']) }]), 'text', {})),
    none: _sdQuickPick([], 'text', counts).length,
    tally: _sdQuickTally(_sdQuickTally({}, 'u:mb-'), 'u:mb-')['u:mb-']
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('text', 'text', 'text', 'image', 'image', 'box', 'box'), $res['families'], 'a text, an image or a box');
	pg_assert_same(array('Alignment', 'Font Size', 'Color'), $res['text'], 'a heading: alignment, size, colour');
	pg_assert_same(array('Radius', 'Shadow', 'Bottom'), $res['image'], 'an image: corner, shadow, spacing');
	pg_assert_same(array('Background Color', 'Radius', 'Shadow'), $res['box'], 'a box: background, corner, shadow');
	pg_assert_same(array('Font Weight', 'Bottom', 'Alignment'), $res['counted'], 'the rows changed most often first, a row no longer drawn skipped');
	pg_assert_same(array('Color'), $res['short'], 'a row the panel lacks is skipped');
	pg_assert_same(0, $res['none'], 'no Style row, no strip');
	pg_assert_same(2, $res['tally'], 'each change counted');
}

// The core's hooks: the brush on the identity row and on the canvas
// toolbar, the words, the paste through one undo step, the browser's copy.
function test_style_copy_core_hooks()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdT('Copy style')", $core, 'Copy style');
	pg_assert_contains("_sdT('Paste style')", $core, 'Paste style');
	pg_assert_contains('id="sd-id-brush"', $core, 'the brush on the identity row');
	pg_assert_contains("sd-tb-brush-btn", $core, 'the brush on the canvas toolbar');
	pg_assert_contains('_sdMcCommit(nodes, function (n) { _sdStyleWrite(n, fit.rows, _sdMcWrite); });', $core, 'the paste is one undo step on every selected element');
	pg_assert_contains("'sd_style_copy'", $core, 'the copy is kept in the browser');
	pg_assert_contains('sd-quick', $core, 'the quick strip');

	$review = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_review.js');
	pg_assert_contains('host.copyStyle', $review, 'Copy style under the review list');
}

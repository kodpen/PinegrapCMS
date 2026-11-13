<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the chips and bands the visual designer draws on its canvas: an
 * element's chips (a value condition, a switched-off feature, a form field's
 * "Shown when") are one row in the flow at the top of its wrapper, so a chip
 * never lies on another chip, a loop band or the content, and each keeps its
 * full text in its title; a see-through wrapper's row goes over its
 * element's corner only where its layout parent sets children side by side;
 * every loop band keeps its full text in its title; the red section warning
 * is left out where the page keeps the section's children. _sdChipRow() and
 * _sdChipRowBeside() are cut out of assets/js/style_designer.src.js and run
 * under Node (skipped on a machine without Node); the rest is read from the
 * source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_canvas_chips_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function designer_canvas_chips_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// The literal assigned to `var <name> = …;` on one line.
function designer_canvas_chips_test_var($source, $name)
{
	pg_assert_true(preg_match('/\n    var ' . $name . ' = [^\n]*;/', $source, $m) === 1, 'the designer has ' . $name);

	return trim($m[0]);
}

// Runs $body under Node after the given definitions; the JSON it prints, or null without Node.
function designer_canvas_chips_test_node($definitions, $body)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgchips');
	file_put_contents($file, $definitions . "\n" . $body);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A wrapper's chips in the order condition, switched off, "Shown when", each
// with its full text as its title; none without one of the three.
function test_designer_canvas_chip_row()
{
	$source = designer_canvas_chips_test_source();
	$defs = designer_canvas_chips_test_var($source, '_SD_CHIP_KINDS') . "\n"
		. designer_canvas_chips_test_function($source, '_sdChipRow');
	$doc = 'function El(tag) { this.tag = tag; this.attrs = {}; this.children = []; this.className = ""; this.textContent = ""; this.title = ""; }'
		. 'El.prototype.setAttribute = function (k, v) { this.attrs[k] = String(v); };'
		. 'El.prototype.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; };'
		. 'El.prototype.appendChild = function (c) { this.children.push(c); return c; };'
		. 'var doc = { createElement: function (t) { return new El(t); } };'
		. 'function wrap(a) { var w = new El("div"); for (var k in a) w.setAttribute(k, a[k]); return w; }'
		. 'function shape(r) { return r ? { cls: r.className, band: r.getAttribute("data-sd-band"), edit: r.getAttribute("contenteditable"),'
		. ' chips: r.children.map(function (c) { return [c.className, c.textContent, c.title]; }) } : null; }';
	$answer = designer_canvas_chips_test_node($defs . "\n" . $doc, 'console.log(JSON.stringify(['
		. 'shape(_sdChipRow(doc, wrap({}))),'
		. 'shape(_sdChipRow(doc, wrap({ "data-sd-cf-shown": "Shown when topic", "data-sd-off-reason": "Off — settings", "data-sd-cond": "Only when: Label is not empty" })))'
		. ']));');

	if ($answer === null) {
		return;
	}

	pg_assert_same(null, $answer[0], 'no chip, no row');
	pg_assert_same('sd-chip-row', $answer[1]['cls'], 'the row');
	pg_assert_same('1', $answer[1]['band'], 'canvas-only, like a loop band');
	pg_assert_same('false', $answer[1]['edit'], 'never edited in place');
	pg_assert_same(array(
		array('sd-chip sd-chip-cond', 'Only when: Label is not empty', 'Only when: Label is not empty'),
		array('sd-chip sd-chip-off', 'Off — settings', 'Off — settings'),
		array('sd-chip sd-chip-shown', 'Shown when topic', 'Shown when topic'),
	), $answer[1]['chips'], 'condition, off, shown when; the full text in each title');
}

// A see-through wrapper's row leaves the flow only under a grid, a table or a flex row.
function test_designer_canvas_chip_row_beside()
{
	$source = designer_canvas_chips_test_source();
	$defs = 'var canvasDoc = { defaultView: { getComputedStyle: function (el) { return el.cs; } } };'
		. 'function _sdLayoutParent(w) { return w.lp; }'
		. designer_canvas_chips_test_function($source, '_sdChipRowBeside');
	$cases = array(
		array('block', 'row'), array('flow-root', 'row'), array('inline', 'row'),
		array('flex', 'column'), array('flex', 'column-reverse'),
		array('flex', 'row'), array('inline-flex', 'row-reverse'),
		array('grid', 'row'), array('inline-grid', 'row'), array('table-row', 'row'), array('table-cell', 'row'),
	);
	$answer = designer_canvas_chips_test_node($defs, 'console.log(JSON.stringify([null].concat(' . json_encode($cases)
		. ').map(function (c) { return _sdChipRowBeside({ lp: c ? { cs: { display: c[0], flexDirection: c[1] } } : null }); })));');

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, false, false, false, true, true, true, true, true, true), $answer,
		'stacked parents keep the row in the flow; side-by-side ones put it over the corner');
}

// The rules the chips, the bands and the section hint keep.
function test_designer_canvas_chip_and_band_rules()
{
	$source = designer_canvas_chips_test_source();

	// The row adds nothing to its wrapper's minimum width (a table column
	// widened by a long chip), fills it, wraps, and cuts a chip with "…".
	pg_assert_contains("'.sd-chip-row { display:flex; flex-wrap:wrap; gap:2px; width:0; min-width:100%;", $source, 'the chip row wraps and never widens its wrapper');
	pg_assert_contains('text-overflow:ellipsis; white-space:nowrap;', $source, 'a chip that does not fit ends in an ellipsis');
	pg_assert_contains("'.sd-wrap-inline > .sd-chip-row { display:inline-flex;", $source, 'an inline element keeps its chips on its line');

	// No chip drawn over the element's corner any more but on a summary.
	pg_assert_true(strpos($source, "'.sd-wrap.sd-cond::before") === false, 'the condition chip is no longer a pseudo element over the content');
	pg_assert_true(strpos($source, "'.sd-wrap.sd-binding-off::after") === false, 'the off reason is no longer a pseudo element over the content');
	pg_assert_true(strpos($source, 'sd-cf-shown-chip:not(.sd-cond)::before') === false, 'Shown when is no longer a pseudo element over the content');
	pg_assert_contains("'.sd-wrap[data-sd-self].sd-cond::before", $source, 'a summary, its own wrapper, keeps its chip');

	// The row is chrome: never taken for the element, the lifted child or a sibling.
	pg_assert_contains("var _SD_FX_CHROME = '.sd-tb, .sd-persistent-note, .sd-issue-badge, .sd-chip-row';", $source, '_sdWrapEl() skips the row');
	pg_assert_contains(':not(.sd-issue-badge):not(.sd-chip-row)', $source, 'the element rules skip the row');
	pg_assert_contains('.sd-issue-badge, .sd-chip-row, .sd-loop-band,', $source, 'the sibling rules skip the row');
	pg_assert_contains("var _SD_CANVAS_AUDIT_SKIP = '.sd-tb, .sd-persistent-note, .sd-issue-badge, .sd-chip-row,", $source, 'the audit skips the row');
	pg_assert_contains('var _chipRow = _selfWrap ? null : _sdChipRow(doc, wrapper);', $source, 'every wrapper but a summary gets its row');
	pg_assert_contains(":scope > .sd-issue-badge, :scope > .sd-chip-row')", $source, 'the chrome placement sees the row');

	// The loop band fills the room the loop area keeps for it, so the first
	// child's chips start under it; every band keeps its text in its title.
	pg_assert_contains("'.sd-loop-band { position:absolute; top:0; left:0; right:0; box-sizing:border-box; height:22px;", $source, 'the band is as tall as the room above the content');
	pg_assert_contains('padding:22px 0 4px', $source, 'the loop area keeps 22px for its band');
	pg_assert_contains('laBand.title = laBand.textContent;', $source, 'the loop band keeps its text in its title');
	pg_assert_contains('rlaBand.title = rlaBand.textContent;', $source, 'the recipient band keeps its text in its title');
	pg_assert_contains('bandInner.title = bandInner.textContent;', $source, 'the table band keeps its text in its title');

	// The red section warning only where the page replaces the children.
	pg_assert_contains('var _SD_SECTION_KEEPS_CHILDREN = { recipient_picker: 1, comment_watchers: 1 };', $source, 'sections whose children the page keeps');
	pg_assert_contains('if (_hasChildren && _SD_SECTION_KEEPS_CHILDREN[_sec]) {', $source, 'no warning on a section that keeps its children');
	pg_assert_contains("_hint.title = _sdT('The child elements here are replaced with BACKEND HTML at render time');", $source, 'the warning says in its title what is wrong');
}

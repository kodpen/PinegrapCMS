<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Options panel of the Visual Page Editor with several
 * elements selected (assets/js/style_designer.src.js): the shared options
 * by kind, Content left out for different kinds, a mixed value and how it
 * is spelled, the changed dot full or a ring and its tip, a change written
 * to every element as one undo step, the reset to the default, the
 * identity row and the strip, and a closed section's "multiple" summary.
 * The helpers are cut out of the source and run under Node; without Node
 * those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The block between two marker comments of the editor source; '' when absent.
function designer_options_multiple_test_block($from_marker, $to_marker)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    // ── ' . $from_marker);
	$to = ($from === false) ? false : strpos($source, '    // ── ' . $to_marker, $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code after the pure blocks; its `out` comes back decoded. Null without Node.
function designer_options_multiple_test_run($code)
{
	$blocks = array(
		designer_options_multiple_test_block('What counts as changed: pure helpers', 'end of what counts as changed'),
		designer_options_multiple_test_block('The Options tabs: pure helpers', 'end of the Options tabs'),
		designer_options_multiple_test_block('The Options panel controls: pure helpers', 'end of the Options panel controls'),
		designer_options_multiple_test_block('Several elements selected: pure helpers', 'end of several elements selected'),
	);

	foreach ($blocks as $i => $block) {
		pg_assert_true($block !== '', 'the editor has pure block ' . $i . ' the several-element panel needs');
		if ($block === '') {
			return null;
		}
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

	$stubs = "function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s, v) {\n"
		. "  if (Array.isArray(v)) return s.replace(/\\{var:(\\d+)\\}/g, function (m, i) { return v[i - 1]; });\n"
		. "  return v === undefined ? s : s.replace('{var}', v);\n"
		. "}\n";
	$file = tempnam(sys_get_temp_dir(), 'pgmul');
	file_put_contents($file, $stubs . implode('', $blocks) . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The shared options go by kind: three headings share a heading's options
// and keep the tab of their kind; a heading, a paragraph and a button have
// no Content tab and open Style.
function test_designer_options_multiple_shared_by_kind()
{
	$answer = designer_options_multiple_test_run(<<<'JS'
var h = function (tag) { return { type: 'content', props: { contentType: 'heading', tag: tag } }; };
var alert = { type: 'semantic', props: { tag: 'div', cssClass: 'alert alert-info' } };
var pieces = [
	{ key: 'own', html: '<own>' }, { key: 'text', html: '<text>' }, { key: 'spacing', html: '<spacing>' },
	{ key: 'flexbox', html: '<flexbox>' }, { key: 'tooltip', html: '<tooltip>' }
];
var alike = _sdOptPlan(_sdMcPieces(true, pieces));
var differ = _sdOptPlan(_sdMcPieces(false, pieces));
out = {
	roles: [h('h2'), h('h3')].map(_sdMcRole),
	alertRole: _sdMcRole(alert),
	divRole: _sdMcRole({ type: 'semantic', props: { tag: 'div', cssClass: 'alert alert-heading' } }),
	kindAlike: _sdMcKind(['content:heading', 'content:heading', 'content:heading']),
	kindDiffer: _sdMcKind(['content:heading', 'content:paragraph', 'content:button']),
	alikeShown: _sdOptTabsShown(alike),
	alikeContent: alike.content,
	differShown: _sdOptTabsShown(differ),
	differOpens: _sdOptTabPick(_sdOptTabKeep(_sdMcKind(['content:heading', 'content:button']), '', 'content'), _sdOptTabsShown(differ)),
	keptForKind: _sdOptTabKeep(_sdMcKind(['content:heading', 'content:heading']), 'several:content:heading', 'advanced'),
	notForSingle: _sdOptTabKeep(_sdMcKind(['content:heading', 'content:heading']), 'content:heading', 'advanced'),
	text: [
		_sdMcHasText('content', '', 'heading'), _sdMcHasText('content', '', 'button'),
		_sdMcHasText('semantic', 'span', ''), _sdMcHasText('content', '', 'image'), _sdMcHasText('row', '', '')
	],
	levels: _sdSelParse('<select class="form-control form-control-sm" data-mc-prop="tag"><option value="h2" selected>H2</option><option value="h3">H3</option></select>'),
	variant: _sdSelParse('<select class="form-control form-control-sm" data-mc-stylegroup="alert-info,alert-dark"><option value="">Default</option><option value="alert-info">Info</option></select>')
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('c:heading', 'c:heading'), $answer['roles'], 'two headings of other levels offer the same options');
	pg_assert_same('s:alert', $answer['alertRole'], 'an alert box offers its own options');
	pg_assert_same('s:div', $answer['divRole'], 'an alert heading is a plain box');
	pg_assert_same('several:content:heading', $answer['kindAlike'], 'three headings are one kind');
	pg_assert_same('several:mixed', $answer['kindDiffer'], 'a heading, a paragraph and a button are of different kinds');
	pg_assert_same(array('content', 'style', 'advanced'), $answer['alikeShown'], 'elements alike draw Content, Style and Advanced');
	pg_assert_same('<own>', $answer['alikeContent'], 'Content holds the kind\'s own section');
	pg_assert_same(array('style', 'advanced'), $answer['differShown'], 'elements of different kinds draw no Content');
	pg_assert_same('style', $answer['differOpens'], 'elements of different kinds open Style');
	pg_assert_same('advanced', $answer['keptForKind'], 'the same kind selected again keeps its tab');
	pg_assert_same('content', $answer['notForSingle'], 'several elements do not keep the tab of a single one');
	pg_assert_same(array(true, true, true, false, false), $answer['text'], 'the Text section belongs to headings, buttons and spans');
	pg_assert_same(' data-mc-prop="tag"', $answer['levels']['attrs'] ?? null, 'a shared option\'s select becomes a button group as the single element\'s does');
	pg_assert_same(2, count($answer['variant']['opts'] ?? array()), 'a shared class group\'s select is read too');
}

// A value alike is the single element's; values that differ are mixed,
// each one once, counted, and spelled under the row.
function test_designer_options_multiple_mixed_value()
{
	$answer = designer_options_multiple_test_run(<<<'JS'
var group = _sdMcSpec({ stylegroup: 'fw-light,fw-bold,ratio ratio-1x1' });
var prefix = _sdMcSpec({ util: 'p-' });
var toggle = _sdMcSpec({ toggle: 'text-nowrap' });
var prop = _sdMcSpec({ prop: 'tag', mc: true });
var sw = _sdMcSpec({ prop: 'aspectRatioEnabled', bool: true });
var attr = _sdMcSpec({ attr: 'role' });
var labels = { h2: 'H2', h3: 'H3', '': 'Default' };
var three = _sdMcVals(['h2', 'h3', 'h2']);
out = {
	alike: _sdMcVals(['fw-bold', 'fw-bold']),
	three: three,
	spell: _sdMcSpell(three, function (v) { return labels[v]; }),
	group: [_sdMcValOf({ cssClass: 'x fw-bold' }, group), _sdMcValOf({ cssClass: 'ratio ratio-1x1' }, group), _sdMcValOf({ cssClass: 'ratio' }, group), _sdMcValOf({}, group)],
	prefix: [_sdMcValOf({ cssClass: 'p-3 p-sm-2' }, prefix), _sdMcValOf({ cssClass: 'p-sm-2 pt-3' }, prefix)],
	toggle: [_sdMcValOf({ cssClass: 'a text-nowrap' }, toggle), _sdMcValOf({ cssClass: 'a' }, toggle)],
	prop: [_sdMcValOf({ tag: 'h2' }, prop), _sdMcValOf({}, prop), _sdMcValOf({ zIndex: 10 }, _sdMcSpec({ prop: 'zIndex' }))],
	sw: [_sdMcValOf({ aspectRatioEnabled: true }, sw), _sdMcValOf({ aspectRatioEnabled: false }, sw), _sdMcValOf({}, sw)],
	attr: [_sdMcValOf({ _attrs: [{ name: 'role', value: 'banner' }] }, attr), _sdMcValOf({ _attrs: [] }, attr)],
	none: _sdMcSpec({})
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(false, $answer['alike']['mixed'], 'values alike are not mixed');
	pg_assert_same('fw-bold', $answer['alike']['value'], 'values alike keep the value');
	pg_assert_same(true, $answer['three']['mixed'], 'values that differ are mixed');
	pg_assert_same('', $answer['three']['value'], 'a mixed control holds no value');
	pg_assert_same(array('h2', 'h3'), $answer['three']['present'], 'each value present once, in the order met');
	pg_assert_same(array('h2' => 2, 'h3' => 1), $answer['three']['counts'], 'each value counted');
	pg_assert_same('— Multiple —: 2 × H2, 1 × H3', $answer['spell'], 'the muted line spells the values, most held first');
	pg_assert_same(array('fw-bold', 'ratio ratio-1x1', '', ''), $answer['group'], 'a group reads the entry whose words are all there');
	pg_assert_same(array('p-3', ''), $answer['prefix'], 'a spacing prefix reads its own value, not a screen size\'s or another side\'s');
	pg_assert_same(array('1', ''), $answer['toggle'], 'a toggle reads on or off');
	pg_assert_same(array('h2', '', '10'), $answer['prop'], 'a prop reads as text');
	pg_assert_same(array('1', '', ''), $answer['sw'], 'a switch reads on or off');
	pg_assert_same(array('banner', ''), $answer['attr'], 'an attribute reads its value');
	pg_assert_same(null, $answer['none'], 'a control that writes nothing has no spec');
}

// The dot is full when every element changed the row, a ring when some
// did, and its tip says in how many.
function test_designer_options_multiple_dot_ring()
{
	$answer = designer_options_multiple_test_run(<<<'JS'
var all = _sdMcDot([true, true, true]);
var some = _sdMcDot([true, false, true]);
var none = _sdMcDot([false, false]);
var cls = _sdMcSpec({ stylegroup: 'fw-bold' });
var num = _sdMcSpec({ prop: 'zIndex' });
var sw = _sdMcSpec({ prop: 'aspectRatioEnabled', bool: true });
out = {
	all: all, some: some, none: none,
	tips: [_sdMcDotTip(all), _sdMcDotTip(some), _sdMcDotTip(none)],
	changed: [
		_sdMcChanged(cls, 'fw-bold', ''), _sdMcChanged(cls, '', ''),
		_sdMcChanged(num, '10', ''), _sdMcChanged(num, '', ''),
		_sdMcChanged(_sdMcSpec({ prop: 'tag', mc: true }), 'h3', 'h2'), _sdMcChanged(_sdMcSpec({ prop: 'tag', mc: true }), 'h2', 'h2'),
		_sdMcChanged(sw, '1', false), _sdMcChanged(sw, '', false),
		_sdMcChanged(_sdMcSpec({ prop: 'tooltipEnabled', bool: true }), '1', undefined)
	]
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('kind' => 'full', 'n' => 3, 'of' => 3), $answer['all'], 'every element changed it: a full dot');
	pg_assert_same(array('kind' => 'ring', 'n' => 2, 'of' => 3), $answer['some'], 'some changed it: a ring');
	pg_assert_same('', $answer['none']['kind'], 'none changed it: no dot');
	pg_assert_same(array('Changed in all 3 elements', 'Changed in 2 of 3 elements', ''), $answer['tips'], 'the tip says in how many');
	pg_assert_same(array(true, false, true, false, true, false, true, false, false), $answer['changed'], 'what counts as changed for one element');
}

// A change is one snapshot, then a write to every element: undo takes it
// back from all of them at once. The reset writes the default to all.
function test_designer_options_multiple_one_undo_step()
{
	$answer = designer_options_multiple_test_run(<<<'JS'
var log = [];
var nodes = [{ props: { cssClass: 'fw-light p-3' } }, { props: { cssClass: 'fw-bold' } }, { props: {} }];
var group = _sdMcSpec({ stylegroup: 'fw-light,fw-bold' });
_sdMcApply(nodes, function () { log.push('save'); }, function (n, i) {
	log.push('write ' + i);
	n.props.cssClass = _sdMcClassWrite(n.props.cssClass, group, 'fw-bold');
});
var after = nodes.map(function (n) { return n.props.cssClass; });
var run = { key: null, at: 0, one: null, seq: 0 };
var snaps = 0;
var save = function () { if (_sdStateRunTakes(run, run.one || null, 0, 600)) snaps++; };
_sdUndoAsOne(run, function () { _sdMcApply(nodes, save, function (n) { save(); }); });
out = {
	log: log,
	after: after,
	snaps: snaps,
	prefix: _sdMcClassWrite('p-3 p-sm-2 pt-1', _sdMcSpec({ util: 'p-' }), 'p-5'),
	clear: _sdMcClassWrite('a fw-bold b', group, ''),
	toggleOn: _sdMcClassWrite('a', _sdMcSpec({ toggle: 'text-nowrap' }), '1'),
	toggleOff: _sdMcClassWrite('a text-nowrap', _sdMcSpec({ toggle: 'text-nowrap' }), ''),
	words: _sdMcClassWrite('ratio ratio-4x3 x', _sdMcSpec({ stylegroup: 'ratio ratio-1x1,ratio ratio-4x3' }), 'ratio ratio-1x1')
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('save', 'write 0', 'write 1', 'write 2'), $answer['log'], 'one snapshot before the writes, then every element');
	pg_assert_same(array('p-3 fw-bold', 'fw-bold', 'fw-bold'), $answer['after'], 'the choice is written to all');
	pg_assert_same(1, $answer['snaps'], 'the writes inside one change make one undo step');
	pg_assert_same('p-sm-2 pt-1 p-5', $answer['prefix'], 'a spacing value replaces its own class only');
	pg_assert_same('a b', $answer['clear'], 'the default takes the group\'s class out');
	pg_assert_same('a text-nowrap', $answer['toggleOn'], 'a toggle turned on');
	pg_assert_same('a', $answer['toggleOff'], 'a toggle turned off');
	pg_assert_same('x ratio ratio-1x1', $answer['words'], 'an entry of several words is replaced whole');
}

// The identity row: the count on the kind's icon, the kind or "mixed",
// the shared parent or the kinds; the strip says a change goes to all.
function test_designer_options_multiple_identity_and_strip()
{
	$answer = designer_options_multiple_test_run(<<<'JS'
out = {
	alike: _sdMcIdRow({ count: 3, icon: 'bi-type-h2', kindName: 'Heading', parent: 'Row', kinds: ['Heading'] }),
	differ: _sdMcIdRow({ count: 3, icon: 'bi-type-h2', kindName: '', parent: '', kinds: ['Heading', 'Paragraph', 'Button'] }),
	escaped: _sdMcIdRow({ count: 2, icon: 'bi-box', kindName: '<b>', parent: '<i>', kinds: ['<b>'] }),
	strip: _sdMcStrip(3)
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_contains('bi-type-h2', $answer['alike'], 'elements alike show their kind\'s icon');
	pg_assert_contains('<span class="sd-id-count">3</span>', $answer['alike'], 'the count sits on the icon');
	pg_assert_contains('3 elements selected', $answer['alike'], 'the row says how many are selected');
	pg_assert_contains('>Heading</span>', $answer['alike'], 'the badge names the kind');
	pg_assert_contains('All in the same parent: Row', $answer['alike'], 'a shared parent is named');
	pg_assert_contains('bi-layers', $answer['differ'], 'different kinds show the layers icon');
	pg_assert_contains('>mixed</span>', $answer['differ'], 'the badge says mixed');
	pg_assert_contains('Heading · Paragraph · Button', $answer['differ'], 'without a shared parent the kinds are listed');
	pg_assert_true(strpos($answer['escaped'], '<b>') === false && strpos($answer['escaped'], '<i>') === false, 'names are escaped');
	pg_assert_contains('A change is written to all 3 elements at once', $answer['strip'], 'the strip says a change goes to all');
	pg_assert_contains('sd-mc-strip', $answer['strip'], 'the strip has its own look');
}

// A closed section whose first changed row is mixed reads "multiple";
// the kind's own section keeps "shared options" in its head while open.
function test_designer_options_multiple_summaries()
{
	$answer = designer_options_multiple_test_run(<<<'JS'
var mixed = _sdChgSummary([{ changed: false }, { changed: true, mixed: true, text: '— Multiple —', swatch: 'bg-primary' }, { changed: true, text: 'Bold' }]);
var plain = _sdChgSummary([{ changed: true, text: 'Bold' }]);
out = {
	mixed: mixed,
	plain: plain,
	mixedHead: _sdSectHeadExtras(mixed),
	keepHead: _sdSectHeadExtras({ count: 1, kind: 'keep', text: 'shared options' })
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('count' => 2, 'kind' => 'mixed', 'text' => '', 'swatch' => ''), $answer['mixed'], 'a mixed first row makes the summary mixed');
	pg_assert_same('value', $answer['plain']['kind'], 'a row alike keeps its value');
	pg_assert_contains('sd-sect-sum-whole">multiple</span>', $answer['mixedHead'], 'the closed head reads multiple, never cut');
	pg_assert_contains('<span class="sd-sect-count">2</span>', $answer['mixedHead'], 'the badge counts rows changed in at least one element');
	pg_assert_contains('sd-sect-sum sd-sect-sum-keep', $answer['keepHead'], 'the own section\'s summary stays while open');
	pg_assert_contains('shared options', $answer['keepHead'], 'the own section says its options are shared');
}

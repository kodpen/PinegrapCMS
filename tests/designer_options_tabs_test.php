<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the three tabs of the Visual Page Editor's Options panel
 * (assets/js/style_designer.src.js): the tab each section goes to, every
 * section of a heading, a row, a picture, a link and a form field reached
 * in a tab and none dropped, Content's order, a tab without a section not
 * drawn, the tab kept for the same kind of element and Content for
 * another, Left and Right, the strip's badges, and the closed Translation
 * and Bind data summaries. The helpers are cut out of the source and run
 * under Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_options_tabs_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The block between two marker comments of the editor source; '' when absent.
function designer_options_tabs_test_block($from_marker, $to_marker)
{
	$source = designer_options_tabs_test_source();
	$from = strpos($source, '    // ── ' . $from_marker);
	$to = ($from === false) ? false : strpos($source, '    // ── ' . $to_marker, $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code after the two pure blocks; its `out` comes back decoded. Null without Node.
function designer_options_tabs_test_run($code)
{
	$tabs = designer_options_tabs_test_block('The Options tabs: pure helpers', 'end of the Options tabs');
	$changed = designer_options_tabs_test_block('What counts as changed: pure helpers', 'end of what counts as changed');
	pg_assert_true($tabs !== '', 'the editor has the block of the Options tabs');
	pg_assert_true($changed !== '', 'the editor has the block of what counts as changed');

	if ($tabs === '' || $changed === '' || !function_exists('exec')) {
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
	$file = tempnam(sys_get_temp_dir(), 'pgtab');
	file_put_contents($file, $stubs . $changed . $tabs . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The table of the plan: each of today's sections and the tab it goes to.
function test_designer_options_tabs_section_places()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var keys = ['shortcut', 'own', 'element', 'icon', 'link', 'context', 'tr', 'bind',
	'text', 'spacing', 'colors', 'border', 'display', 'styling',
	'flexbox', 'visit', 'accessibility', 'tooltip', 'search', 'interactions', 'something new'];
out = {};
keys.forEach(function (k) { out[k] = _sdOptTabOf(k); });
JS
	);

	if ($answer === null) {
		return;
	}

	foreach (array('shortcut', 'own', 'element', 'icon', 'link', 'context', 'tr', 'bind') as $key) {
		pg_assert_same('content', $answer[$key], $key . ' is in Content');
	}

	foreach (array('text', 'spacing', 'colors', 'border', 'display', 'styling') as $key) {
		pg_assert_same('style', $answer[$key], $key . ' is in Style');
	}

	foreach (array('flexbox', 'visit', 'accessibility', 'tooltip', 'search', 'interactions') as $key) {
		pg_assert_same('advanced', $answer[$key], $key . ' is in Advanced');
	}

	pg_assert_same('content', $answer['something new'], 'a section without a key stays with the element, in Content');
}

// Every section a heading, a row, a picture, a link and a form field get
// today lands in one tab, once, and Content opens with the element's own
// section and ends with Translation and Bind data.
function test_designer_options_tabs_every_section_reached()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var appearance = ['colors', 'display', 'border', 'styling', 'spacing', 'flexbox', 'tooltip', 'accessibility'];
var kinds = {
	heading: ['tr', 'visit', 'own', 'text', 'search', 'interactions'].concat(appearance),
	row: ['shortcut', 'tr', 'bind', 'own', 'element', 'search', 'interactions'].concat(appearance),
	picture: ['tr', 'context', 'visit', 'icon', 'own', 'element', 'search', 'interactions'].concat(appearance),
	link: ['tr', 'visit', 'own', 'link', 'text', 'link', 'search', 'interactions'].concat(appearance),
	field: ['shortcut', 'tr', 'bind', 'own', 'element', 'element', 'search', 'interactions'].concat(appearance)
};
out = {};
Object.keys(kinds).forEach(function (kind) {
	var pieces = kinds[kind].map(function (k, i) { return { key: k, html: '<' + k + i + '>' }; });
	pieces.push({ key: 'element', html: '' });
	var plan = _sdOptPlan(pieces);
	var all = plan.content + plan.style + plan.advanced;
	out[kind] = {
		each: pieces.filter(function (p) { return p.html; }).every(function (p) {
			return all.split(p.html).length === 2 && plan[_sdOptTabOf(p.key)].indexOf(p.html) !== -1;
		}),
		length: all.length === pieces.reduce(function (n, p) { return n + p.html.length; }, 0),
		content: (plan.content.match(/<([a-z]+)\d+>/g) || []).map(function (t) { return t.replace(/[<>\d]/g, ''); }),
		style: (plan.style.match(/<([a-z]+)\d+>/g) || []).map(function (t) { return t.replace(/[<>\d]/g, ''); }),
		advanced: (plan.advanced.match(/<([a-z]+)\d+>/g) || []).map(function (t) { return t.replace(/[<>\d]/g, ''); })
	};
});
JS
	);

	if ($answer === null) {
		return;
	}

	foreach (array('heading', 'row', 'picture', 'link', 'field') as $kind) {
		pg_assert_true($answer[$kind]['each'], 'every section of a ' . $kind . ' is in its tab, once');
		pg_assert_true($answer[$kind]['length'], 'nothing of a ' . $kind . ' is dropped or added');
		pg_assert_same(array('flexbox', 'tooltip', 'accessibility'), array_values(array_intersect($answer[$kind]['advanced'], array('flexbox', 'tooltip', 'accessibility'))), 'Advanced keeps the order they are built in');
	}

	pg_assert_same(array('own', 'tr'), $answer['heading']['content'], 'a heading: its own section, then Translation');
	pg_assert_same(array('text', 'colors', 'display', 'border', 'styling', 'spacing'), $answer['heading']['style'], 'a heading: Text first in Style');
	pg_assert_same(array('visit', 'search', 'interactions', 'flexbox', 'tooltip', 'accessibility'), $answer['heading']['advanced'], 'a heading: Show by the visit in Advanced');
	pg_assert_same(array('shortcut', 'own', 'element', 'tr', 'bind'), $answer['row']['content'], 'a row: its own section, Quick Layouts, Translation, Bind data');
	pg_assert_same(array('own', 'element', 'icon', 'context', 'tr'), $answer['picture']['content'], 'a picture: the parent\'s options after its own');
	pg_assert_same(array('own', 'link', 'link', 'tr'), $answer['link']['content'], 'a link: Link and the links in its text in Content');
	pg_assert_same(array('shortcut', 'own', 'element', 'element', 'tr', 'bind'), $answer['field']['content'], 'a form field: its sections in the order built');
}

// The keys the panel's builders give their sections are the table's.
function test_designer_options_tabs_builders_keyed()
{
	$source = designer_options_tabs_test_source();

	// rowCss(): each Appearance section's variable, and the key its return gives it.
	preg_match_all("/var ([a-zA-Z]+) = as\\(_sdT\\('([^']+)'\\)/", $source, $calls, PREG_SET_ORDER);
	$vars = array();

	foreach ($calls as $call) {
		$vars[$call[2]] = $call[1];
	}

	$want = array('Display &amp; Sizing' => 'display', 'Flexbox' => 'flexbox', 'Spacing' => 'spacing', 'Border' => 'border',
		'Colors' => 'colors', 'Tooltip' => 'tooltip', 'Styling' => 'styling', 'Accessibility' => 'accessibility');
	pg_assert_same(array_keys($want), array_keys($vars), 'the eight Appearance sections are found in rowCss()');
	pg_assert_true(strpos($source, 'var lHtml = dsHtml;') !== false, 'Display and size is drawn as lHtml');
	$vars['Display &amp; Sizing'] = 'lHtml';

	foreach ($want as $title => $key) {
		pg_assert_true(strpos($source, '_sdSectKey(' . $vars[$title] . ", '" . $key . "')") !== false, $title . ' is given its key');
	}

	foreach (array(
		"return _sdSectKey(sect('bi-type', _sdT('Text Options'),",
		"return _sdSectKey(sect('bi-link-45deg', _sdT('Link Options'),",
		"return _sdSectKey(sect('bi-stars', _sdT('Interactions'), '<div data-sd-fx-body></div>'), 'interactions');",
		"_sdSectKey(_sdBiIconSection(selectedNode), 'icon')",
		"_sdSectKey(propsSearchSection(selectedNode), 'search')",
		"_sdSectKey(_sdTrSection(selectedNode), 'tr')",
		"_sdBindKeyed(propsDataBinding(selectedNode))",
	) as $needle) {
		pg_assert_true(strpos($source, $needle) !== false, 'the source has: ' . $needle);
	}
}

// A tab without a section is not drawn, and the tab asked for gives way
// to the first one drawn.
function test_designer_options_tabs_empty_tab_not_drawn()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = {
	shown: _sdOptTabsShown({ content: '<a>', style: '', advanced: '  ' }),
	all: _sdOptTabsShown({ content: '<a>', style: '<b>', advanced: '<c>' }),
	none: _sdOptTabsShown({ content: '', style: '', advanced: '' }),
	pick: _sdOptTabPick('style', ['content']),
	keep: _sdOptTabPick('advanced', ['content', 'style', 'advanced']),
	pickNone: _sdOptTabPick('style', []),
	strip: _sdOptTabStrip(['content'], 'content', {}),
	stripNone: _sdOptTabStrip([], 'content', {})
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('content'), $answer['shown'], 'Style and Advanced without a section are not drawn');
	pg_assert_same(array('content', 'style', 'advanced'), $answer['all']);
	pg_assert_same(array(), $answer['none']);
	pg_assert_same('content', $answer['pick'], 'a kept tab that is not drawn gives way to the first drawn');
	pg_assert_same('advanced', $answer['keep']);
	pg_assert_same('content', $answer['pickNone']);
	pg_assert_same(1, substr_count($answer['strip'], 'role="tab"'), 'one tab drawn');
	pg_assert_same('', $answer['stripNone'], 'no strip without a tab');
}

// The same kind of element keeps the tab; another kind, or a panel of
// another sort before it, opens Content (its scroll goes to the top with
// the kind, _sdPanelKind()).
function test_designer_options_tabs_kept_per_kind()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = [
	_sdOptTabKeep('content:heading', 'content:heading', 'style'),
	_sdOptTabKeep('content:image', 'content:heading', 'style'),
	_sdOptTabKeep('content:heading', '', 'advanced'),
	_sdOptTabKeep('', '', 'style'),
	_sdOptTabKeep('row:', 'row:', 'nonsense')
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('style', 'content', 'content', 'content', 'content'), $answer);

	$source = designer_options_tabs_test_source();
	$keep = strpos($source, '_sdOptTab = _sdOptTabKeep(_kind, _sdPanelLastKind, _sdOptTab);');
	$reset = strpos($source, "        _sdPanelLastKind = '';\n        _sdPanelLazy = {};");
	pg_assert_true($keep !== false && $reset !== false && $keep < $reset, 'the panel reads the kept tab before it forgets the last kind');
}

// Left and Right move between the drawn tabs, round from the last to the first.
function test_designer_options_tabs_left_right()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var all = ['content', 'style', 'advanced'];
out = [
	_sdOptTabNext('content', all, 1),
	_sdOptTabNext('advanced', all, 1),
	_sdOptTabNext('content', all, -1),
	_sdOptTabNext('style', all, -1),
	_sdOptTabNext('content', ['content', 'advanced'], 1),
	_sdOptTabNext('style', ['content'], 1),
	_sdOptTabNext('content', [], 1)
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('style', 'content', 'advanced', 'content', 'advanced', 'content', 'content'), $answer);
}

// The strip: a tab list; the open tab selected and the only one reached
// with Tab; a badge counts a tab's changed rows and is absent at 0.
function test_designer_options_tabs_strip()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = _sdOptTabStrip(['content', 'style', 'advanced'], 'style', { content: 0, style: 4, advanced: 1 });
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_contains('role="tablist"', $answer);
	pg_assert_contains('aria-label="Option tabs"', $answer);
	pg_assert_same(3, substr_count($answer, 'role="tab"'));
	pg_assert_contains('class="sd-otab active" role="tab" data-otab="style" aria-controls="sd-properties" aria-selected="true" tabindex="0" aria-label="Style, 4 changed"', $answer, 'the open tab');
	pg_assert_contains('class="sd-otab" role="tab" data-otab="content" aria-controls="sd-properties" aria-selected="false" tabindex="-1">', $answer, 'a tab without a change has no count in its name');
	pg_assert_contains('aria-label="Advanced, 1 changed"', $answer);
	pg_assert_same(2, substr_count($answer, 'class="sd-otab-count"'), 'no badge at 0');
	pg_assert_contains('<span class="sd-otab-txt">Style</span><span class="sd-otab-count" aria-hidden="true">4</span>', $answer);
	pg_assert_contains('<span class="bi bi-card-text" aria-hidden="true"></span><span class="sd-otab-txt">Content</span></button>', $answer);
}

// Translation and Bind data, closed, say their state in the head.
function test_designer_options_tabs_closed_summaries()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var seg = function (tr) { return { source: 'Hello', translations: tr }; };
out = {
	missing: _sdTrState([seg({ de: { text: 'Hallo' } })], ['en', 'de']),
	two: _sdTrState([seg([]), seg({ en: { text: '' } })], ['en', 'de']),
	done: _sdTrState([seg({ en: { text: 'Hi', state: 'machine' } })], ['en']),
	unbound: _sdBindSum(['', '  ']),
	bound: _sdBindSum(['', ' Product name ', 'Price']),
	stateHead: _sdSectHeadExtras(_sdTrState([seg({})], ['en'])),
	doneHead: _sdSectHeadExtras(_sdTrState([seg({ en: { text: 'Hi' } })], ['en'])),
	wordHead: _sdSectHeadExtras(_sdBindSum([])),
	keyed: _sdSectCard({ sid: 'sdscx', cls: 'sd-props-section', hdrCls: 'h', head: 'Bind Data', body: '<i>', open: false, lazy: true, key: 'bind' }),
	plain: _sdSectCard({ sid: 'sdscx', cls: 'sd-props-section', hdrCls: 'h', head: 'Bind Data', body: '<i>', open: false, lazy: true })
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('count' => 0, 'kind' => 'state', 'warn' => true, 'text' => 'EN · not translated'), $answer['missing']);
	pg_assert_same('EN, DE · not translated', $answer['two']['text'], 'every language a segment misses');
	pg_assert_same(array('count' => 0, 'kind' => 'state', 'warn' => false, 'text' => 'translated'), $answer['done']);
	pg_assert_same(array('count' => 0, 'kind' => 'word', 'text' => 'not bound', 'swatch' => ''), $answer['unbound']);
	pg_assert_same(array('count' => 0, 'kind' => 'value', 'text' => 'Product name', 'swatch' => ''), $answer['bound'], 'the first bound field');
	pg_assert_contains('<span class="sd-sect-state is-warn">EN · not translated</span>', $answer['stateHead'], 'a missing language is a warning badge');
	pg_assert_contains('<span class="sd-sect-state">translated</span>', $answer['doneHead']);
	pg_assert_contains('<span class="sd-sect-sum-txt sd-sect-sum-whole">not bound</span>', $answer['wordHead'], 'a word is never cut');
	pg_assert_contains('data-sd-card="sdscx" data-sd-key="bind"', $answer['keyed'], 'a keyed card says its key');
	pg_assert_false(strpos($answer['plain'], 'data-sd-key') !== false, 'a card without a key has none');
}

// How deep a cut of markup leaves its <div>s: a keyed card is taken out
// of the panel's markup only at the top level.
function test_designer_options_tabs_html_depth()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = [
	_sdHtmlDepth(''),
	_sdHtmlDepth('<div class="a"><div>x</div>'),
	_sdHtmlDepth('<div class="a"><div>x</div></div>'),
	_sdHtmlDepth('<divider><div>'),
	_sdHtmlDepth('<span></span>')
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(0, 1, 0, 1, 0), $answer);
}

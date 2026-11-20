<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the three tabs of the Visual Page Editor's Options panel
 * (assets/js/style_designer.src.js): the tab each section goes to, every
 * section of a heading, a row, a picture, a link and a form field reached
 * in a tab and none dropped, each tab's order, the names of Style and
 * Advanced and the card ids they keep, which of their sections open, a
 * content-level user's hidden sections left out, a tab without a section
 * not drawn, the tab kept for the same kind of element and Content for
 * another, Left and Right, the strip's badges, the closed Translation
 * and Bind data summaries, and the rows Advanced › Layout draws by the
 * element's context with the note naming the rows left out. The helpers
 * are cut out of the source and run under Node; without Node those cases
 * are skipped.
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

// Runs $code after the two pure blocks, and the block of Layout by context
// when $layout; its `out` comes back decoded. Null without Node.
function designer_options_tabs_test_run($code, $layout = false)
{
	$tabs = designer_options_tabs_test_block('The Options tabs: pure helpers', 'end of the Options tabs');
	$changed = designer_options_tabs_test_block('What counts as changed: pure helpers', 'end of what counts as changed');
	pg_assert_true($tabs !== '', 'the editor has the block of the Options tabs');
	pg_assert_true($changed !== '', 'the editor has the block of what counts as changed');

	if ($layout) {
		$lay = designer_options_tabs_test_block('Layout by context: pure helpers', 'end of layout by context');
		pg_assert_true($lay !== '', 'the editor has the block of Layout by context');
		$tabs .= $lay;
	}

	if ($tabs === '' || $changed === '' || ($layout && $lay === '') || !function_exists('exec')) {
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

	$style = array('text', 'spacing', 'colors', 'border', 'display', 'styling');
	$advanced = array('flexbox', 'visit', 'accessibility', 'tooltip', 'search', 'interactions');
	$has_text = array('heading' => true, 'row' => false, 'picture' => false, 'link' => true, 'field' => false);
	$has_visit = array('heading' => true, 'row' => false, 'picture' => true, 'link' => true, 'field' => false);

	foreach (array('heading', 'row', 'picture', 'link', 'field') as $kind) {
		pg_assert_true($answer[$kind]['each'], 'every section of a ' . $kind . ' is in its tab, once');
		pg_assert_true($answer[$kind]['length'], 'nothing of a ' . $kind . ' is dropped or added');
		pg_assert_same(array_values(array_diff($style, $has_text[$kind] ? array() : array('text'))), $answer[$kind]['style'],
			'a ' . $kind . ': Text, Spacing, Color and background, Border and corner, Size and display, Shadow and effect');
		pg_assert_same(array_values(array_diff($advanced, $has_visit[$kind] ? array() : array('visit'))), $answer[$kind]['advanced'],
			'a ' . $kind . ': Layout, Show by the visit, Accessibility, Tooltip, Search and SERP, Interactions');
	}

	pg_assert_same(array('own', 'tr'), $answer['heading']['content'], 'a heading: its own section, then Translation');
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

	$want = array('Size and display' => 'display', 'Layout' => 'flexbox', 'Spacing' => 'spacing', 'Border and corner' => 'border',
		'Color and background' => 'colors', 'Tooltip' => 'tooltip', 'Shadow and effect' => 'styling', 'Accessibility' => 'accessibility');
	pg_assert_same(array_keys($want), array_keys($vars), 'the eight Appearance sections are found in rowCss()');
	pg_assert_true(strpos($source, 'var lHtml = dsHtml;') !== false, 'Size and display is drawn as lHtml');
	$vars['Size and display'] = 'lHtml';

	foreach ($want as $title => $key) {
		pg_assert_true(strpos($source, '_sdSectKey(' . $vars[$title] . ", '" . $key . "')") !== false, $title . ' is given its key');
	}

	foreach (array(
		"return _sdSectKey(sect('bi-type', _sdT('Text'),",
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

// Style's and Advanced's sections carry the plan's names; a renamed one
// keeps the card id its former name gave it, so the open state a browser
// kept under sd_cs_<sid> stays. The shadow moved from Color and background
// to Shadow and effect.
function test_designer_options_tabs_style_advanced_names()
{
	$source = designer_options_tabs_test_source();

	pg_assert_contains("function sect(icon, title, body, idName) {\n        var sid = 'sdsc' + (icon + (idName || title))", $source, 'sect() makes the id from idName when given');
	pg_assert_contains("function as(title, content, idName) {\n            var sid = 'sdsc-ap-' + (idName || title)", $source, 'as() makes the id from idName when given');

	$calls = array(
		'Text' => array("/return _sdSectKey\\(sect\\('bi-type', _sdT\\('Text'\\),.*?,\\s*_sdT\\('Text Options'\\)\\s*\\), 'text'\\);/s"),
		'Size and display' => array("/var dsHtml = as\\(_sdT\\('Size and display'\\), _dsBody, _sdT\\('Display &amp; Sizing'\\)\\);/"),
		'Layout' => array("/var fxHtml = as\\(_sdT\\('Layout'\\), _fxBody, _sdT\\('Flexbox'\\)\\);/"),
		'Border and corner' => array("/var borderHtml = as\\(_sdT\\('Border and corner'\\),.*?,\\s*_sdT\\('Border'\\)\\s*\\);/s"),
		'Color and background' => array("/var colorsHtml = as\\(_sdT\\('Color and background'\\),.*?,\\s*_sdT\\('Colors'\\)\\s*\\);/s"),
		'Shadow and effect' => array("/var stylingHtml = as\\(_sdT\\('Shadow and effect'\\),.*?,\\s*_sdT\\('Styling'\\)\\s*\\);/s"),
	);
	$bodies = array();

	foreach ($calls as $name => $pattern) {
		pg_assert_same(1, preg_match($pattern[0], $source, $match), $name . ' is drawn under its name with its former card id');
		$bodies[$name] = isset($match[0]) ? $match[0] : '';
	}

	pg_assert_true(strpos($bodies['Shadow and effect'], "row(_sdT('Shadow'),") !== false, 'the shadow is in Shadow and effect');
	pg_assert_false(strpos($bodies['Color and background'], "row(_sdT('Shadow'),") !== false, 'and no longer in Color and background');
	pg_assert_same(1, substr_count($source, "selStyleGroup(n.props, ['shadow-none','shadow-sm','shadow','shadow-lg'], 'Default')"), 'rowCss() draws the shadow row once');

	foreach (array('Spacing', 'Tooltip', 'Accessibility') as $kept) {
		pg_assert_contains("as(_sdT('" . $kept . "'),", $source, $kept . ' keeps its name');
	}
}

// Which Style and Advanced sections open while the browser kept nothing:
// Text on a text element, Spacing always, Layout on a flex box or its
// child, Show by the visit with a condition set; a kept state wins.
function test_designer_options_tabs_open_rules()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var keys = ['text', 'spacing', 'colors', 'border', 'display', 'styling', 'flexbox', 'visit', 'accessibility', 'tooltip', 'search', 'interactions'];
var all = { text: true, flex: true, parentFlex: true, visit: true };
out = {
	none: keys.map(function (k) { return _sdOptOpenByDefault(k, {}); }),
	all: keys.map(function (k) { return _sdOptOpenByDefault(k, all); }),
	noCtx: _sdOptOpenByDefault('spacing'),
	ownFlex: _sdOptOpenByDefault('flexbox', { flex: true }),
	parentFlex: _sdOptOpenByDefault('flexbox', { parentFlex: true }),
	keptClosed: _sdSectOpenRead('1', _sdOptOpenByDefault('spacing', {})),
	keptOpen: _sdSectOpenRead('0', _sdOptOpenByDefault('border', {})),
	nothingKept: [_sdSectOpenRead(null, _sdOptOpenByDefault('spacing', {})), _sdSectOpenRead(null, _sdOptOpenByDefault('border', all))],
	text: [
		_sdOptIsText('semantic', 'h2', ''), _sdOptIsText('semantic', 'p', ''), _sdOptIsText('semantic', 'span', ''),
		_sdOptIsText('content', '', 'heading'), _sdOptIsText('content', '', 'paragraph'),
		_sdOptIsText('semantic', 'a', ''), _sdOptIsText('semantic', 'button', ''), _sdOptIsText('content', '', 'link'),
		_sdOptIsText('content', '', 'button'), _sdOptIsText('semantic', 'div', ''), _sdOptIsText('semantic', 'h7', ''), _sdOptIsText('row', 'h2', '')
	],
	flex: [
		_sdOptIsFlex('row', ''), _sdOptIsFlex('semantic', 'row g-3'), _sdOptIsFlex('semantic', 'p-2 d-flex gap-2'),
		_sdOptIsFlex('semantic', 'd-md-inline-flex'), _sdOptIsFlex('container', 'd-inline-flex'),
		_sdOptIsFlex('container', 'd-block'), _sdOptIsFlex('semantic', 'flex-row'), _sdOptIsFlex('semantic', 'rowx row-cols-2'),
		_sdOptIsFlex('col', 'col-md-6'), _sdOptIsFlex('semantic', 'd-flex-wrap'), _sdOptIsFlex('semantic', undefined)
	]
};
JS
	);

	if ($answer !== null) {
		pg_assert_same(array(false, true, false, false, false, false, false, false, false, false, false, false), $answer['none'], 'with no context only Spacing opens');
		pg_assert_same(array(true, true, false, false, false, false, true, true, false, false, false, false), $answer['all'], 'Text, Spacing, Layout and Show by the visit open by their rule; the rest stay closed');
		pg_assert_true($answer['noCtx']);
		pg_assert_true($answer['ownFlex'], 'Layout opens on a flex container');
		pg_assert_true($answer['parentFlex'], 'and on the child of one');
		pg_assert_false($answer['keptClosed'], 'a section this browser closed stays closed');
		pg_assert_true($answer['keptOpen'], 'a section this browser opened stays open');
		pg_assert_same(array(true, false), $answer['nothingKept']);
		pg_assert_same(array(true, true, true, true, true, false, false, false, false, false, false, false), $answer['text'], 'a heading, a paragraph and a span are text elements; a link, a button and a box are not');
		pg_assert_same(array(true, true, true, true, true, false, false, false, false, false, false), $answer['flex'], 'a grid row and the d-flex classes make a flex box');
	}

	$source = designer_options_tabs_test_source();
	pg_assert_contains('_sdSectBuild = { cards: {}, lazy: _allBodies ? null : {}, ctx: _sdOptCtx(selectedNode) };', $source, 'the single element\'s panel reads the context');
	pg_assert_contains('o.open = _sdSectOpenRead(o.stored, _sdOptOpenByDefault(key, b.ctx));', $source, 'a keyed Style or Advanced section takes its rule');
	pg_assert_contains("if (b.ctx && _sdOptTabOf(key) !== 'content') {", $source, 'Content keeps its own rules');
}

// A content-level user who may edit the element gets the panel with the
// sections their level shows today: a section hidden from them is drawn
// as nothing and is in no tab, and a tab left without a section is not
// drawn. An element they may not edit keeps its read-only panel, without
// tabs.
function test_designer_options_tabs_content_level()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var hidden = '';
var plan = _sdOptPlan([
	{ key: 'shortcut', html: hidden },
	{ key: 'own', html: '<own0>' },
	{ key: 'element', html: hidden },
	{ key: 'text', html: '<text1>' },
	{ key: 'spacing', html: '<spacing2>' },
	{ key: 'flexbox', html: hidden },
	{ key: 'search', html: hidden },
	{ key: 'interactions', html: hidden }
]);
var noStyle = _sdOptPlan([{ key: 'own', html: '<own0>' }, { key: 'text', html: hidden }, { key: 'visit', html: '<visit1>' }]);
out = {
	plan: plan,
	shown: _sdOptTabsShown(plan),
	noStyle: _sdOptTabsShown(noStyle),
	strip: _sdOptTabStrip(_sdOptTabsShown(noStyle), 'content', {})
};
JS
	);

	if ($answer !== null) {
		pg_assert_same(array('content' => '<own0>', 'style' => '<text1><spacing2>', 'advanced' => ''), $answer['plan'], 'a hidden section is in no tab');
		pg_assert_same(array('content', 'style'), $answer['shown'], 'Advanced, left without a section, is not drawn');
		pg_assert_same(array('content', 'advanced'), $answer['noStyle'], 'nor is Style');
		pg_assert_false(strpos($answer['strip'], 'data-otab="style"') !== false, 'the strip has no Style tab');
	}

	$source = designer_options_tabs_test_source();
	pg_assert_contains("function _swPlacementSection(n, sid, cfg, orderOpts) {\n        if (_sdIsContentLevel()) return '';", $source, 'This placement stays hidden at content level');
	$read_only = strpos($source, 'if (_sdIsContentLevel() && !_sdCanEditNode(selectedNode)) {');
	$tabs = strpos($source, 'var _plan = _sdOptPlan([');
	pg_assert_true($read_only !== false && $tabs !== false && $read_only < $tabs, 'an element the user may not edit gets the read-only panel before any tab is planned');
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

// Advanced › Layout reads the element's context (D19): a flex box gets
// its box rows, the child of one its own rows under "parent: flex", both
// get both, neither gets only "Make it a container". A row holding a
// value is drawn whatever the context.
function test_designer_options_layout_by_context()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = {
	box: _sdLayPlan({ flex: true, parentFlex: false }, {}),
	item: _sdLayPlan({ flex: false, parentFlex: true }, {}),
	both: _sdLayPlan({ flex: true, parentFlex: true }, {}),
	neither: _sdLayPlan({}, {}),
	held: _sdLayPlan({ flex: false, parentFlex: false }, { gap: true, order: true, justify: true }),
	heldBox: _sdLayPlan({ flex: false, parentFlex: false }, { container: true }),
	noCtx: _sdLayPlan(),
	of: ['container', 'gap', 'alignSelf', 'fill', 'nothing'].map(_sdLayOf)
};
JS
		, true);

	if ($answer === null) {
		return;
	}

	$box = array('container', 'direction', 'wrap', 'justify', 'alignItems', 'alignContent', 'gap');
	$item = array('alignSelf', 'order', 'grow', 'shrink', 'fill');

	pg_assert_same($box, $answer['box']['rows'], 'a flex container: Flex Container, direction, wrap, alignment and gap');
	pg_assert_false($answer['box']['make'], 'a flex container has no "Make it a container"');
	pg_assert_false($answer['box']['tag'], 'nor the tag of a flex parent');
	pg_assert_same(array(), $answer['box']['hidden']['box']);
	pg_assert_same(array('self', 'order', 'grow', 'shrink'), $answer['box']['hidden']['item'], 'its own alignment, order, grow and shrink are left out');

	pg_assert_same($item, $answer['item']['rows'], 'the child of a flex box: its own alignment, order, grow, shrink and fill');
	pg_assert_true($answer['item']['make'], 'and "Make it a container"');
	pg_assert_true($answer['item']['tag'], 'under the tag "parent: flex"');
	pg_assert_same(array('direction', 'wrap', 'alignment', 'gap'), $answer['item']['hidden']['box'], 'direction, wrap, alignment and gap are left out');
	pg_assert_same(array(), $answer['item']['hidden']['item']);

	pg_assert_same(array_merge($box, $item), $answer['both']['rows'], 'both: the box rows, then the element\'s own');
	pg_assert_false($answer['both']['make']);
	pg_assert_true($answer['both']['tag']);
	pg_assert_same(array('box' => array(), 'item' => array()), $answer['both']['hidden'], 'nothing is left out');

	pg_assert_same(array(), $answer['neither']['rows'], 'neither: no row');
	pg_assert_true($answer['neither']['make'], 'only "Make it a container"');
	pg_assert_false($answer['neither']['tag']);
	pg_assert_same(array('direction', 'wrap', 'alignment', 'gap'), $answer['neither']['hidden']['box']);
	pg_assert_same(array('self', 'order', 'grow', 'shrink'), $answer['neither']['hidden']['item']);
	pg_assert_same($answer['neither'], $answer['noCtx'], 'no context reads as neither');

	pg_assert_same(array('justify', 'gap', 'order'), $answer['held']['rows'], 'a row holding a value is drawn, in its place');
	pg_assert_same(array('direction', 'wrap', 'alignment'), $answer['held']['hidden']['box'], 'alignment is named while two of its rows are left out');
	pg_assert_same(array('self', 'grow', 'shrink'), $answer['held']['hidden']['item']);
	pg_assert_false($answer['heldBox']['make'], 'a Flex Container row holding a value stands for the button');

	pg_assert_same(array('box', 'box', 'item', 'item', ''), $answer['of']);
}

// Which rows hold a value: the row's class at the base or at any screen
// size, never a class that only starts like it.
function test_designer_options_layout_held()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
var gap = [['0','0'],['1','1'],['2','2'],['3','3'],['4','4'],['5','5']];
var grow = [['grow-0','0'],['grow-1','1']];
var dir = [['row','Row'],['column','Column'],['row-reverse','Row Reverse'],['column-reverse','Column Reverse']];
out = [
	_sdLayHeld('p-2 gap-3', 'gap-', gap),
	_sdLayHeld('gap-md-2', 'gap-', gap),
	_sdLayHeld('row-gap-3 gap-x', 'gap-', gap),
	_sdLayHeld('flex-grow-1', 'flex-', grow),
	_sdLayHeld('flex-lg-grow-0', 'flex-', grow),
	_sdLayHeld('flex-row', 'flex-', grow),
	_sdLayHeld('flex-xxl-column-reverse', 'flex-', dir),
	_sdLayHeld('', 'gap-', gap),
	_sdLayHeld(undefined, 'gap-', gap)
];
JS
		, true);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, false, true, true, false, true, false, false), $answer);
}

// The note names the rows left out and why (D20), in the panel's words;
// "Make it a container" stands in it while the element is not a flex box.
function test_designer_options_layout_note()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = {
	item: _sdLayNoteHtml(_sdLayPlan({ parentFlex: true }, {}), false),
	box: _sdLayNoteHtml(_sdLayPlan({ flex: true }, {}), false),
	neither: _sdLayNoteHtml(_sdLayPlan({}, {}), false),
	several: _sdLayNoteHtml(_sdLayPlan({}, {}), true),
	both: _sdLayNoteHtml(_sdLayPlan({ flex: true, parentFlex: true }, {}), false),
	one: _sdLayList(['gap']),
	two: _sdLayList(['direction', 'gap']),
	none: _sdLayList([])
};
JS
		, true);

	if ($answer === null) {
		return;
	}

	pg_assert_contains('This element is not a flex container; the direction, line wrapping, alignment and gap rows are hidden.', $answer['item']);
	pg_assert_contains('data-sd-lay-make', $answer['item'], 'the child of a flex box can be made a container');
	pg_assert_contains('>Make it a container</button>', $answer['item']);
	pg_assert_contains('role="note"', $answer['item']);
	pg_assert_false(strpos($answer['item'], 'Its parent') !== false, 'its parent is a flex box');

	pg_assert_contains('Its parent is not a flex container; the own alignment, flex order, grow and shrink rows are hidden.', $answer['box']);
	pg_assert_false(strpos($answer['box'], 'data-sd-lay-make') !== false, 'a flex container has no button');

	pg_assert_contains('This element is not a flex container; the direction, line wrapping, alignment and gap rows are hidden. Its parent is not a flex container; the own alignment, flex order, grow and shrink rows are hidden.', $answer['neither']);
	pg_assert_contains('Not every selected element is a flex container;', $answer['several']);
	pg_assert_contains('Not every selected element has a flex parent;', $answer['several']);
	pg_assert_contains('>Make them containers</button>', $answer['several']);
	pg_assert_same('', $answer['both'], 'nothing left out: no note');

	pg_assert_same('gap', $answer['one']);
	pg_assert_same('direction and gap', $answer['two']);
	pg_assert_same('', $answer['none']);
}

// "Make it a container" writes today's class: d-flex, or the flex form of
// a display class at its own screen size, so a hidden element stays
// hidden where it was.
function test_designer_options_layout_make_container()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = [
	_sdLayMakeCls(''),
	_sdLayMakeCls(undefined),
	_sdLayMakeCls('p-3 d-block mb-2'),
	_sdLayMakeCls('d-none d-md-block'),
	_sdLayMakeCls('d-inline-block d-lg-none'),
	_sdLayMakeCls('d-md-grid'),
	_sdLayMakeCls('d-none'),
	_sdLayMakeCls('d-print-block text-end'),
	_sdLayMakeCls('d-block d-sm-table d-sm-table-cell')
];
JS
		, true);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'd-flex',
		'd-flex',
		'p-3 d-flex mb-2',
		'd-none d-md-flex',
		'd-flex d-lg-none',
		'd-md-flex d-flex',
		'd-none d-flex',
		'd-print-block text-end d-flex',
		'd-flex d-sm-flex',
	), $answer);
}

// A flex box is read from the element's own classes: Bootstrap's
// components drawn as one count, unless a display class at the base
// draws them otherwise and no flex class holds at any size.
function test_designer_options_layout_flex_components()
{
	$answer = designer_options_tabs_test_run(<<<'JS'
out = [
	_sdOptIsFlex('semantic', 'nav nav-pills'), _sdOptIsFlex('semantic', 'navbar navbar-expand-lg'), _sdOptIsFlex('semantic', 'btn-group'),
	_sdOptIsFlex('semantic', 'card h-100'), _sdOptIsFlex('semantic', 'list-group'), _sdOptIsFlex('semantic', 'input-group mb-3'),
	_sdOptIsFlex('semantic', 'hstack gap-3'), _sdOptIsFlex('semantic', 'vstack'), _sdOptIsFlex('semantic', 'pagination'),
	_sdOptIsFlex('semantic', 'nav d-none d-md-flex'), _sdOptIsFlex('row', 'g-3'),
	_sdOptIsFlex('row', 'd-block'), _sdOptIsFlex('semantic', 'card d-block'), _sdOptIsFlex('semantic', 'nav d-none'), _sdOptIsFlex('semantic', 'card-body'),
	_sdOptIsFlex('semantic', 'list-group-item'), _sdOptIsFlex('semantic', 'navx'), _sdOptIsFlex('semantic', 'nav-link'),
	_sdOptIsFlex('semantic', 'row d-grid')
];
JS
		, true);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		true, true, true,
		true, true, true,
		true, true, true,
		true, true,
		false, false, false, false,
		false, false, false,
		false,
	), $answer);
}

// The panel draws Layout from the plan: the rows by context, the tag in
// its head, the note under the rows, the context of several elements, the
// button's click.
function test_designer_options_layout_wired()
{
	$source = designer_options_tabs_test_source();

	pg_assert_contains('var _layPlan = _sdLayPlan(_layCtx, _layHeld);', $source, 'rowCss() plans Layout by the context');
	pg_assert_contains("if (_layPlan.tag) fxHtml = _sdSectTag(fxHtml, _sdT('parent: flex'));", $source, 'the tag stands in the section\'s head');
	pg_assert_contains('_sdLayNoteHtml(_layPlan, _laySeveral)', $source, 'the note is drawn under the rows');
	pg_assert_contains("var mk = t.closest('[data-sd-lay-make]');", $source, 'the panel handles "Make it a container"');
	pg_assert_contains("lay: { several: true, cls: nodes.map(function (n) { return (n.props || {}).cssClass || ''; }).join(' ') }", $source, 'several elements: rows held by any of them');
	pg_assert_contains("(o.tag ? '<span class=\"sd-sect-tag\">' + esc(o.tag) + '</span>' : '')", $source, 'a card draws its tag after its name');
}

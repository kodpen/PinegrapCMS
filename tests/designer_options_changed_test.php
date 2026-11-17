<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the Visual Page Editor's Options panel counts as changed
 * and how a section card reads (assets/js/style_designer.src.js): a row
 * differing from its default is counted, a section's and a tab's counts,
 * the closed section's summary of each kind (a colour, one value, none,
 * default), which section opens from the state this browser kept, the
 * kind of element that keeps or resets the panel's scroll, the path's last
 * steps, and that a closed card carries no body. The helpers are cut out
 * of the source and run under Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The pure helpers' block of the editor source; '' when it is not there.
function designer_options_changed_test_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    // ── What counts as changed: pure helpers');
	$to = ($from === false) ? false : strpos($source, '    // ── end of what counts as changed', $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code after the block; its `out` comes back decoded. Null without Node.
function designer_options_changed_test_run($code)
{
	$block = designer_options_changed_test_block();
	pg_assert_true($block !== '', 'the editor has the block of what counts as changed');

	if ($block === '' || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$esc = "function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n";
	$file = tempnam(sys_get_temp_dir(), 'pgchg');
	file_put_contents($file, $esc . $block . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A row is changed when its value differs from its default, compared as
// the panel always has: a checkbox by its state, a boolean default by
// 'true' / '1', a number default as a number, anything else as text.
function test_designer_options_row_changed()
{
	$answer = designer_options_changed_test_run(<<<'JS'
out = [
	_sdChgDiffers('select', 'h2', 'h2'),
	_sdChgDiffers('select', 'h3', 'h2'),
	_sdChgDiffers('checkbox', true, false),
	_sdChgDiffers('checkbox', false, false),
	_sdChgDiffers('select', '1', true),
	_sdChgDiffers('select', 'false', true),
	_sdChgDiffers('text', '50', 50),
	_sdChgDiffers('text', '50.0', 50),
	_sdChgDiffers('text', '60', 50),
	_sdChgDiffers('text', '', ''),
	_sdChgDiffers('select', 'false', 'false')
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, true, true, false, false, true, false, false, true, false, false), $answer);
}

// A section counts its changed rows; a tab adds its sections' counts.
function test_designer_options_section_and_tab_counts()
{
	$answer = designer_options_changed_test_run(<<<'JS'
var a = _sdChgSummary([{ changed: true, text: 'Center' }, { changed: false, text: 'x' }, { changed: true, text: 'Bold' }]);
var b = _sdChgSummary([{ changed: false, text: 'x' }]);
var c = _sdChgSummary([{ changed: true, text: '' , swatch: 'bg-primary' }]);
out = [a.count, b.count, c.count, _sdChgTotal([a, b, c]), _sdChgTotal([]), _sdChgTotal([b])];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(2, 0, 1, 3, 0, 0), $answer);
}

// The closed section's summary: the first changed row's colour square with
// its name, else its value as text, 'none' when that value is empty, and
// 'default' when no row is changed.
function test_designer_options_section_summary_kinds()
{
	$answer = designer_options_changed_test_run(<<<'JS'
out = [
	_sdChgSummary([{ changed: false, text: 'a' }, { changed: true, text: '  Primary ', swatch: 'bg-primary' }]),
	_sdChgSummary([{ changed: true, text: 'Large   heading 4' }, { changed: true, text: 'Bold', swatch: 'bg-danger' }]),
	_sdChgSummary([{ changed: true, text: '' }]),
	_sdChgSummary([{ changed: false, text: 'H2' }]),
	_sdChgSummary([])
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('count' => 1, 'kind' => 'color', 'text' => 'Primary', 'swatch' => 'bg-primary'), $answer[0]);
	pg_assert_same(array('count' => 2, 'kind' => 'value', 'text' => 'Large heading 4', 'swatch' => ''), $answer[1]);
	pg_assert_same(array('count' => 1, 'kind' => 'none', 'text' => '', 'swatch' => ''), $answer[2]);
	pg_assert_same(array('count' => 0, 'kind' => 'default', 'text' => '', 'swatch' => ''), $answer[3]);
	pg_assert_same(array('count' => 0, 'kind' => 'default', 'text' => '', 'swatch' => ''), $answer[4]);
}

// What this browser kept under sd_cs_<sid> wins ('1' closed, '0' open);
// with nothing kept, the element's own section opens and a general one
// stays closed.
function test_designer_options_section_open_state()
{
	$answer = designer_options_changed_test_run(<<<'JS'
out = [
	_sdSectOpenRead(null, true), _sdSectOpenRead(null, false),
	_sdSectOpenRead('1', true), _sdSectOpenRead('0', false),
	_sdSectOpenRead('x', false), _sdSectOpenRead(undefined, true)
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, false, false, true, false, true), $answer);
}

// A closed card carries its head, its summary and its count, never its
// body; an open one carries the body and no summary. A count of 0 draws
// no badge; the summary's text is escaped.
function test_designer_options_closed_card_has_no_body()
{
	$answer = designer_options_changed_test_run(<<<'JS'
var body = '<div class="sd-prop-row"><label class="sd-prop-label">Size</label></div>';
var shut = _sdSectCard({ sid: 'sdscx', cls: 'sd-props-section', hdrCls: 'sd-props-section-title', head: 'Colors', body: body, open: false, lazy: true,
	sum: { count: 2, kind: 'color', text: 'Pri<mary', swatch: 'bg-primary' } });
var open = _sdSectCard({ sid: 'sdscy', cls: 'sd-props-section', hdrCls: 'sd-props-section-title', head: 'Heading', body: body, open: true, lazy: true,
	sum: { count: 0, kind: 'default', text: '', swatch: '' } });
var none = _sdSectCard({ sid: 'sdscz', cls: 'sd-app-sect', hdrCls: 'sd-app-sect-hdr', head: 'Border', body: body, open: false, lazy: true,
	sum: { count: 0, kind: 'default', text: '', swatch: '' } });
out = {
	shutHasBody: shut.indexOf('sd-prop-row') !== -1,
	shutLazy: shut.indexOf('data-sd-lazy="sdscx"') !== -1,
	shutCount: /sd-sect-count[^>]*>2</.test(shut),
	shutSwatch: shut.indexOf('sd-color-preview bg-primary') !== -1,
	shutEscaped: shut.indexOf('Pri&lt;mary') !== -1,
	shutClosed: shut.indexOf('aria-expanded="false"') !== -1,
	openHasBody: open.indexOf('sd-prop-row') !== -1,
	openBadge: open.indexOf('sd-sect-count') !== -1 && /sd-sect-count[^>]*>0</.test(open),
	openExpanded: open.indexOf('aria-expanded="true"') !== -1,
	noneDefault: none.indexOf('>default<') !== -1,
	noneHasBody: none.indexOf('sd-prop-row') !== -1
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'shutHasBody' => false,
		'shutLazy' => true,
		'shutCount' => true,
		'shutSwatch' => true,
		'shutEscaped' => true,
		'shutClosed' => true,
		'openHasBody' => true,
		'openBadge' => false,
		'openExpanded' => true,
		'noneDefault' => true,
		'noneHasBody' => false,
	), $answer);
}

// The kind of element: another kind opens the panel at the top, a second
// element of the same kind keeps the scroll. The path shows its last three
// steps, nearest last.
function test_designer_options_kind_and_path()
{
	$answer = designer_options_changed_test_run(<<<'JS'
out = [
	_sdPanelKind({ type: 'content', props: { contentType: 'heading', tag: 'h2' } }),
	_sdPanelKind({ type: 'content', props: { contentType: 'heading', tag: 'h3' } }),
	_sdPanelKind({ type: 'component', props: { componentType: 'btn' } }),
	_sdPanelKind({ type: 'semantic', props: { tag: 'section' } }),
	_sdPanelKind({ type: 'row', props: {} }),
	_sdPanelKind(null),
	_sdPathTail(['Body', 'Hero', 'Container', 'Row'], 3),
	_sdPathTail(['Hero'], 3),
	_sdPathTail([], 3)
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'content:heading', 'content:heading', 'component:btn', 'semantic:section', 'row:', '',
		array('Hero', 'Container', 'Row'), array('Hero'), array(),
	), $answer);
}

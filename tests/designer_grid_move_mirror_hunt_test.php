<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in the designer's grid, move and mirror parts:
 * area names CSS reads as keywords, the areas tinted over a right-to-left
 * grid, the tint kept out of the Side by side mirrors, a changed track
 * count on a grid whose other axis has no count, the line under the
 * areas after an undo, and Enter in the "Move…" window after a new
 * search. The code runs under Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_gmm_hunt_node()
{
	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	return $code === 0;
}

function designer_gmm_hunt_js($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
}

// One four-space function of a part, from its declaration to its closing
// brace; '' when it is not there.
function designer_gmm_hunt_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs $script after the grid models (S size writer, G tracks, A areas)
// and the named functions of `$file`; returns what `out` holds.
function designer_gmm_hunt_run($file, $names, $script)
{
	if (!designer_gmm_hunt_node()) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	$src = "global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'require(' . json_encode($dir . '/pg_custom_style.js') . ");\n"
		. 'var Z = require(' . json_encode($dir . '/pg_size_style.js') . ");\n"
		. 'var G = require(' . json_encode($dir . '/pg_grid_tracks.js') . ");\n"
		. 'var A = require(' . json_encode($dir . '/pg_grid_areas.js') . ");\n"
		. "var _sdT = function (s, v) { if (Array.isArray(v)) { v.forEach(function (x, i) { s = s.replace('{var:' + (i + 1) + '}', x); }); return s; } return String(s).replace('{var}', v); };\n"
		. "var esc = function (s) { return String(s); };\n";

	if ($file !== '') {
		$js = designer_gmm_hunt_js($file);

		foreach ($names as $name) {
			$fn = designer_gmm_hunt_fn($js, $name);

			if (!pg_assert_true($fn !== '', $file . ' has ' . $name . '()')) {
				return null;
			}

			$src .= $fn . "\n";
		}
	}

	$tmp = @tempnam(sys_get_temp_dir(), 'pggmm');

	if ($tmp === false) {
		return null;
	}

	file_put_contents($tmp, $src . "var out = null;\n" . $script . "\nconsole.log(JSON.stringify(out));\n");
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	@unlink($tmp);
	pg_assert_same(0, $code, implode("\n", array_slice($out, 0, 12)));

	return $code === 0 ? json_decode(implode("\n", $out), true) : null;
}

// An area named as a CSS keyword ("inherit", "AUTO") would turn a child's
// `grid-area` into that keyword: the name is refused and never read back.
function test_designer_gmm_hunt_area_keyword_names()
{
	$r = designer_gmm_hunt_run('', array(), <<<'JS'
var words = ['inherit', 'initial', 'unset', 'revert', 'revert-layer', 'default', 'AUTO', 'Span', 'None', 'Inherit'];
out = {
	names: words.map(function (w) { return A.nameError(w, [], -1); }),
	items: words.map(function (w) { return A.item(w); }),
	kept: [A.nameError('header', [], -1), A.item('header'), A.nameError('autoplay', [], -1), A.item('spanner')]
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array_fill(0, 10, 'bad'), $r['names'], 'a CSS keyword in any case is no area name');
	pg_assert_same(array_fill(0, 10, ''), $r['items'], 'a child holding a keyword is in no area');
	pg_assert_same(array('', 'header', '', 'spanner'), $r['kept'], 'a name that only starts like a keyword is kept');
}

// The areas tinted over a grid box on the canvas are the editor's chrome:
// a Side by side mirror neither copies them nor draws again when they move.
function test_designer_gmm_hunt_tint_not_in_mirror()
{
	$part = designer_gmm_hunt_js('style_designer_grid.js');
	pg_assert_true(strpos($part, "old.className = 'sd-ga-overlay';") !== false, 'the tint carries its chrome class');

	$r = designer_gmm_hunt_run('', array(), 'require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_mirror_schedule.js') . ");\n" . <<<'JS'
var S = window.PGMirrorSchedule;
out = {
	strip: S.CHROME_SELECTOR.split(', ').indexOf('.sd-ga-overlay') !== -1,
	added: S.matters({ type: 'childList', cls: '', nodes: ['sd-ga-overlay'] }),
	inside: S.matters({ type: 'childList', cls: 'sd-ga-overlay', nodes: [''] }),
	page: S.matters({ type: 'childList', cls: '', nodes: ['row'] })
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_true($r['strip'], 'the mirror takes the tint out of its copy');
	pg_assert_same(array(false, false, true), array($r['added'], $r['inside'], $r['page']), 'only the page\'s own changes draw the mirror again');
}

// On a right-to-left page a grid's first column is its right one: the
// tint of an area stands over the cells the area holds there.
function test_designer_gmm_hunt_tint_rtl()
{
	$r = designer_gmm_hunt_run('', array(), <<<'JS'
var a = { name: 'side', c: 1, r: 2, w: 1, h: 1 };
var cols = [100, 200, 300], rows = [50, 60];
out = {
	ltr: A.box(a, cols, rows, 10, 5, false, 620),
	rtl: A.box(a, cols, rows, 10, 5, true, 620),
	wide: A.box({ name: 'main', c: 2, r: 1, w: 2, h: 2 }, cols, rows, 10, 5, true, 620)
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('x' => 0, 'y' => 55, 'w' => 100, 'h' => 60), $r['ltr'], 'left to right: column 1 at the left edge');
	pg_assert_same(array('x' => 520, 'y' => 55, 'w' => 100, 'h' => 60), $r['rtl'], 'right to left: column 1 at the right edge');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 510, 'h' => 115), $r['wide'], 'right to left: columns 2 and 3 to the left of it');

	$part = designer_gmm_hunt_js('style_designer_grid.js');
	pg_assert_true(strpos($part, "cs.direction === 'rtl'") !== false, 'the canvas tint reads the grid\'s direction');
}

// The grid part's writers and a one-element size host over User Styles
// text, for the cases below.
function designer_gmm_hunt_grid_fns()
{
	return array('_cls', '_readsCss', '_reads', '_write', '_writeCss', '_writeMany', '_gridState', '_held', '_childWalk',
		'_areasValue', '_setNote', '_noteKey', '_noteHolds', '_commitAreas', '_writeTracks');
}

function designer_gmm_hunt_grid_host()
{
	return <<<'JS'
var _ui = { id: '', sel: '', edit: '', fresh: false, note: '', err: '', key: null };
var child = { id: 'c1', props: { cssClass: 'pgs-bbbbbb' } };
var box = { id: 'g1', props: { cssClass: 'pgs-aaaaaa' }, children: [child] };
var text = '';
var host = { nodes: [box], size: 'xs', text: function () { return text; }, newClass: function () { return ''; },
	commit: function (t) { text = t; }, el: { querySelector: function () { return null; } } };
var at = function (cls, css) { return Z.at(Z.read(text, cls, css), 'xs').value; };
JS;
}

// Rows left on Default (no count) while the grid has areas: a change of
// Columns does not cut the areas by zero rows and take every one away.
function test_designer_gmm_hunt_tracks_without_other_count()
{
	$r = designer_gmm_hunt_run('style_designer_grid.js', designer_gmm_hunt_grid_fns(), designer_gmm_hunt_grid_host() . <<<'JS'
text = Z.write(text, 'pgs-aaaaaa', G.CSS.columns, 'xs', 'repeat(3, minmax(0, 1fr))');
text = Z.write(text, 'pgs-aaaaaa', A.CSS, 'xs', '"head head head" "side main main"');
text = Z.write(text, 'pgs-bbbbbb', A.ITEM, 'xs', 'main');
_writeTracks(host, 'columns', 'repeat(2, minmax(0, 1fr))');
out = { cols: at('pgs-aaaaaa', G.CSS.columns), areas: at('pgs-aaaaaa', A.CSS), child: at('pgs-bbbbbb', A.ITEM) };
// With a count of rows too, the areas are cut to the new columns.
text = Z.write(text, 'pgs-aaaaaa', G.CSS.columns, 'xs', 'repeat(3, minmax(0, 1fr))');
text = Z.write(text, 'pgs-aaaaaa', G.CSS.rows, 'xs', 'repeat(2, minmax(0, 1fr))');
_writeTracks(host, 'columns', 'repeat(2, minmax(0, 1fr))');
out.cut = at('pgs-aaaaaa', A.CSS);
out.note = _ui.note;
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('repeat(2, minmax(0, 1fr))', $r['cols'], 'the columns are written');
	pg_assert_same('"head head head" "side main main"', $r['areas'], 'the areas are kept');
	pg_assert_same('main', $r['child'], 'the child stays in its area');
	pg_assert_same('"head head" "side main"', $r['cut'], 'with rows set, a column taken away cuts the areas');
	pg_assert_contains('Columns went from 3 to 2.', $r['note'], 'the line says what changed');
}

// "Move…": after a target is chosen, a new search and Enter choose the
// row the search lit, never move to the earlier target; the list starts
// on the chosen target's row when it is listed.
function test_designer_gmm_hunt_move_enter_after_search()
{
	$r = designer_gmm_hunt_run('style_designer_move.js', array('_mvFold', '_mvMatch', '_mvStartAt', '_mvEnterAct'), <<<'JS'
var nodes = ['Header', 'Card 1', 'Card 2', 'Card 3', 'Footer'].map(function (n) { return { name: n }; });
var entries = nodes.map(function (n) { return { node: n, name: n.name, fold: _mvFold(n.name) }; });
var cards = _mvMatch(entries, 'card'), foot = _mvMatch(entries, 'foot'), none = _mvMatch(entries, 'zzz');
var target = nodes[3];
out = {
	start: [_mvStartAt(cards, entries, target, 80), _mvStartAt(foot, entries, target, 80), _mvStartAt(none, entries, target, 80), _mvStartAt(cards, entries, target, 2)],
	enter: [_mvEnterAct(foot, entries, 0, target), _mvEnterAct(cards, entries, 2, target), _mvEnterAct(cards, entries, 0, null), _mvEnterAct(none, entries, -1, target), _mvEnterAct(none, entries, -1, null)]
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(2, 0, -1, 0), $r['start'], 'the list starts on the target\'s row when it is shown');
	pg_assert_same(array(4, 'go', 1, 'go', null), $r['enter'], 'Enter chooses the lit row, or moves when it is the target');
}

// The line under the areas tells the last change; once an Undo (or any
// other change of the box's tracks or areas) has taken that change back,
// the line is not drawn again.
function test_designer_gmm_hunt_note_after_undo()
{
	$r = designer_gmm_hunt_run('style_designer_grid.js', designer_gmm_hunt_grid_fns(), designer_gmm_hunt_grid_host() . <<<'JS'
text = Z.write(text, 'pgs-aaaaaa', G.CSS.columns, 'xs', 'repeat(3, minmax(0, 1fr))');
text = Z.write(text, 'pgs-aaaaaa', G.CSS.rows, 'xs', 'repeat(2, minmax(0, 1fr))');
text = Z.write(text, 'pgs-aaaaaa', A.CSS, 'xs', '"head head head" "side main main"');
var before = text;
_writeTracks(host, 'columns', 'repeat(2, minmax(0, 1fr))');
var st = _gridState(host, box, text);
out = { after: _noteHolds(st, text), note: _ui.note };
text = before;
out.undone = _noteHolds(_gridState(host, box, text), text);
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_true($r['note'] !== '', 'the change leaves its line');
	pg_assert_true($r['after'], 'the line stands while the change does');
	pg_assert_false($r['undone'], 'after Undo the line is gone');
}

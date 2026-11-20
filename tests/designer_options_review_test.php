<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Only what I changed", the review view of the Visual Page
 * Editor's Options panel (window.PGOptionsReview, the part 'review'): the
 * list holds the rows of all three tabs the changed dots mark, no more and
 * no fewer, in the tabs' order with their path; the switch's count is the
 * tabs' counts added; "Reset all" resets every row as one undo step and
 * stops on a row that stays changed. Run in Node with the editor's own
 * counting and undo helpers; without Node those cases are skipped.
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
function options_review_test_block($from, $to)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$at = strpos($source, $from);
	$end = ($at === false) ? false : strpos($source, $to, $at);

	return ($end === false) ? '' : substr($source, $at, $end - $at);
}

// Runs `$code` in Node with window.PGOptionsReview loaded as R, after the
// editor's blocks of what counts as changed and of the panel controls
// (_sdChgSummary(), _sdChgTotal(), _sdUndoAsOne(), _sdStateRunTakes());
// its `out` comes back decoded. Null without Node.
function options_review_test_node($code)
{
	$changed = options_review_test_block('    // ── What counts as changed: pure helpers', '    // ── end of what counts as changed');
	$controls = options_review_test_block('    // ── The Options panel controls: pure helpers', '    // ── end of the Options panel controls');
	pg_assert_true($changed !== '' && $controls !== '', 'the editor has its counting and undo helpers');

	$module = PG_FUNCTIONS_DIR . '/assets/js/pg_options_review.js';
	pg_assert_true(is_file($module), 'the review module exists');

	if ($changed === '' || $controls === '' || !is_file($module) || !function_exists('exec')) {
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
		. $changed . "\n" . $controls . "\n"
		. file_get_contents($module) . "\n"
		. "var R = window.PGOptionsReview, out = null;\n"
		. "var NAMES = { content: 'İçerik', style: 'Stil', advanced: 'Gelişmiş' };\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgoptreview');

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

// The rows of the three tabs as the panel draws them, each with its card
// (`top`, the outermost card's key; '' outside a card) and whether its dot
// is on. The tabs' badges count them per card as _sdSectHeadsFill() does:
// _sdChgSummary() of a card's rows, Translation's card left out.
function test_options_review_list_equals_the_dots_of_all_three_tabs()
{
	$res = options_review_test_node(<<<'JS'
function row(tab, top, section, label, changed, value) {
    return { tab: tab, top: top, section: section, label: label, changed: changed, text: value || '', swatch: '' };
}
// Drawn as the panel draws them: Style's pane first here, to show the
// list follows the tabs' order and not the drawing's.
var rows = [
    row('style', 'ap-textoptions', 'Yazı', 'Hizalama', true, 'Orta'),
    row('style', 'ap-textoptions', 'Yazı', 'Kalınlık', false),
    row('style', 'ap-textoptions', 'Yazı', 'Boyut', true, 'fs-1'),
    row('style', 'ap-spacing', 'Boşluk', 'Alt dış boşluk', true, '4'),
    row('content', 'own', 'Başlık', 'Düzey', true, 'H2'),
    row('content', 'own', 'Başlık', 'Metin', false),
    row('content', 'tr', 'Çeviri', 'EN', true, 'x'),
    row('content', '', '', 'Kısayol', true, 'x'),
    row('advanced', 'visit', 'Ziyarete göre göster', 'Koşul', true, 'Sepette ürün var'),
    row('advanced', 'fx', 'Etkileşimler', 'Efekt', false)
];
// The badges: per tab, per card, as the panel counts them.
var counts = {};
['content', 'style', 'advanced'].forEach(function (t) {
    var cards = {};
    rows.forEach(function (r) { if (r.tab === t && r.top && r.top !== 'tr') (cards[r.top] = cards[r.top] || []).push(r); });
    counts[t] = _sdChgTotal(Object.keys(cards).map(function (k) { return _sdChgSummary(cards[k]); }));
});
var dots = rows.filter(function (r) { return r.changed; });
var list = R.list(dots, NAMES);
var per = { content: 0, style: 0, advanced: 0 };
list.forEach(function (e) { per[e.tab]++; });
out = {
    counts: counts,
    per: per,
    total: R.total(counts),
    length: list.length,
    labels: list.map(function (e) { return e.label; }),
    paths: list.map(function (e) { return e.path; }),
    values: list.map(function (e) { return e.value; }),
    back: list.map(function (e) { return dots[e.i].label; }),
    empty: R.list([], NAMES).length,
    zero: R.total({})
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same($res['counts'], $res['per'], 'each tab lists as many rows as its badge counts');
	pg_assert_same($res['total'], $res['length'], 'the switch counts the rows the list holds');
	pg_assert_same(5, $res['length'], 'every changed row of the three tabs, Translation and rows outside a card left out');
	pg_assert_same(array('Düzey', 'Hizalama', 'Boyut', 'Alt dış boşluk', 'Koşul'), $res['labels'], 'Content, Style, Advanced, each in the panel\'s order');
	pg_assert_same(array('İçerik › Başlık', 'Stil › Yazı', 'Stil › Yazı', 'Stil › Boşluk', 'Gelişmiş › Ziyarete göre göster'), $res['paths'], 'the path is tab › section');
	pg_assert_same(array('H2', 'Orta', 'fs-1', '4', 'Sepette ürün var'), $res['values'], 'the value as the row shows it');
	pg_assert_same($res['labels'], $res['back'], 'each entry leads back to its row');
	pg_assert_same(0, $res['empty'], 'nothing changed lists nothing');
	pg_assert_same(0, $res['zero'], 'and counts 0');
}

// Several elements: a row is listed once, with in how many it is changed;
// a value that differs reads as the panel's "— Multiple —"; an empty one
// "none".
function test_options_review_several_elements_and_values()
{
	$res = options_review_test_node(<<<'JS'
var list = R.list([
    { tab: 'style', top: 'ap-textoptions', section: 'Yazı', label: 'Hizalama', changed: true, text: 'Orta', n: 3, of: 3 },
    { tab: 'style', top: 'ap-textoptions', section: 'Yazı', label: 'Boyut', changed: true, text: '', mixed: true, n: 2, of: 3 },
    { tab: 'style', top: 'ap-color', section: 'Renk', label: 'Arka plan', changed: true, text: 'Mor', swatch: 'bg-purple' },
    { tab: 'content', top: 'own', section: 'Bağlantı', label: 'Adres', changed: true, text: '  ' }
], NAMES);
out = list.map(function (e) { return [e.label, e.value, e.kind, e.share, e.swatch]; });
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('Adres', '', 'none', '', ''),
		array('Hizalama', 'Orta', 'value', '3/3', ''),
		array('Boyut', '', 'mixed', '2/3', ''),
		array('Arka plan', 'Mor', 'color', '', 'bg-purple'),
	), $res, 'value, mixed, colour and none; the share of several elements');
}

// "Reset all": every row reset inside one _sdUndoAsOne(), so the saveState()
// calls of all of them make one snapshot; a row that stays changed after
// its reset is tried once, never again; the redraw after each reset (new
// rows, the same keys) is followed.
function test_options_review_reset_all_is_one_undo_step()
{
	$res = options_review_test_node(<<<'JS'
var run = { key: null, at: 0, one: null, seq: 0 };
var snaps = 0;
function saveState() { if (_sdStateRunTakes(run, run.one || null, 0, 700)) snaps++; }
// The panel: four changed rows; 'stuck' keeps its dot after a reset.
var changed = { a: 1, b: 1, stuck: 1, c: 1 };
var tried = [];
function next(done) {
    var k = Object.keys(changed).filter(function (x) { return !done[x]; })[0];
    return k ? { key: k } : null;
}
function reset(it) {
    tried.push(it.key);
    saveState();
    if (it.key !== 'stuck') delete changed[it.key];
}
var n = R.resetAll(function (fn) { _sdUndoAsOne(run, fn); }, next, reset, 200);
var after = snaps;
saveState();
out = { n: n, snaps: after, tried: tried, left: Object.keys(changed), nextEdit: snaps,
    capped: R.resetAll(function (fn) { fn(); }, function () { return { key: String(Math.random()) }; }, function () {}, 5) };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(1, $res['snaps'], 'every reset joins one undo step');
	pg_assert_same(2, $res['nextEdit'], 'the edit after it is a step of its own');
	pg_assert_same(array('a', 'b', 'stuck', 'c'), $res['tried'], 'each row tried once, in order');
	pg_assert_same(4, $res['n'], 'four resets');
	pg_assert_same(array('stuck'), $res['left'], 'a row its reset cannot clear stays');
	pg_assert_same(5, $res['capped'], 'a panel that keeps offering rows stops at the limit');
}

// The part's registration and the core's hooks.
function test_options_review_is_a_part()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/style_designer_review.min.js', isset($parts['review']) ? $parts['review'] : null, 'review is a part');
	pg_assert_same(array('assets/js/pg_options_review.js', 'assets/js/style_designer_review.js'), isset($bundles['assets/js/style_designer_review.min.js']) ? $bundles['assets/js/style_designer_review.min.js'] : null, 'the module, then the part');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'review'", $screen, 'the editor is handed the part');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdT('Only what I changed')", $core, 'the switch\'s words');
	pg_assert_contains("_sdT('What I changed')", $core, 'and its narrow form');
	pg_assert_contains("_sdPartCall('review', 'draw', [_sdReviewHost()])", $core, 'the core draws the list through the part');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_review.js');
	pg_assert_contains("_sdT('Reset all')", $part, 'the list\'s foot');
	pg_assert_contains('host.resetAll(', $part, 'Reset all through the core\'s one undo step');
}

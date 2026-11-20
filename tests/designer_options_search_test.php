<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the Options panel's search of the visual designer
 * (assets/js/pg_options_search.js, window.PGOptionsSearch, the first source
 * of the part 'optsearch'): Turkish letters folded both ways, a row found by
 * its label, its section, its CSS property and its class prefix ("margin"
 * finds the outer spacing rows), the path of a result, what Enter opens for
 * a row in a closed section of another tab, and the class offered when
 * nothing is found. Runs the module under Node; skipped where there is none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGOptionsSearch loaded as S and a small
// panel of entries as E; its `out` comes back decoded. Null without Node.
function options_search_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	// The rows as the part reads them from the panel: Turkish labels, the
	// group of classes of their select, the section, the tab.
	$entries = <<<'JS'
var S = window.PGOptionsSearch, out = null;
var TAB = { content: 'İçerik', style: 'Stil', advanced: 'Gelişmiş' };
function row(label, section, tab, sid, nth, classes, prop) {
    var prefix = S.prefixOf(classes || '');
    return { label: label, section: section, tab: tab, tabName: TAB[tab], sid: sid, nth: nth,
        prefix: prefix, css: S.cssOf(prefix, classes || '', prop) };
}
function util(p) { return [0, 1, 2, 3, 4, 5].map(function (v) { return p + v; }).join(','); }
var E = [
    row('Düzey', 'Başlık', 'content', 'sdsc-heading', 0),
    row('Metin', 'Başlık', 'content', 'sdsc-heading', 1, '', 'text'),
    row('Hizalama', 'Yazı', 'style', 'sdsc-ap-textoptions', 0, 'text-start,text-center,text-end'),
    row('Metin gölgesi', 'Yazı', 'style', 'sdsc-ap-textoptions', 1, 'text-shadow-sm,text-shadow'),
    row('İç boşluk', 'Boşluk', 'style', 'sdsc-ap-spacing', 0, util('p-')),
    row('Üst iç boşluk', 'Boşluk', 'style', 'sdsc-ap-spacing', 1, util('pt-')),
    row('Dış boşluk', 'Boşluk', 'style', 'sdsc-ap-spacing', 2, util('m-')),
    row('Üst dış boşluk', 'Boşluk', 'style', 'sdsc-ap-spacing', 3, util('mt-')),
    row('Alt dış boşluk', 'Boşluk', 'style', 'sdsc-ap-spacing', 4, util('mb-')),
    row('Gölge', 'Gölge ve efekt', 'style', 'sdsc-ap-styling', 0, 'shadow-none,shadow-sm,shadow,shadow-lg'),
    row('Islak zemin', 'Gölge ve efekt', 'style', 'sdsc-ap-styling', 1),
    row('Üzerine gelince gölge', 'Etkileşimler', 'advanced', 'sdsc-fx', 0),
    row('Konum', 'İpucu', 'advanced', 'sdsc-tooltip', 0, '', 'tooltipPlacement')
];
function labels(rs) { return rs.map(function (r) { return r.e.label; }); }
JS;

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_options_search.js') . "\n"
		. $entries . "\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgoptsearch');

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

function test_options_search_folds_turkish_letters_both_ways()
{
	$res = options_search_test_node(
		'out = {'
		. ' fold: [S.fold("İPUCU"), S.fold("ISLAK"), S.fold("ıslak"), S.fold("Şçğöü"), S.fold("İpucu")],'
		. ' sameLength: S.fold("Gölge ve İç Boşluk").length === "Gölge ve İç Boşluk".length,'
		. ' plain: labels(S.match(E, "golge")),'
		. ' marked: labels(S.match(E, "gölge")),'
		. ' caps: labels(S.match(E, "ISLAK")),'
		. ' dotted: labels(S.match(E, "ic bosluk")),'
		. ' section: labels(S.match(E, "ipucu")),'
		. ' at: S.match(E, "gölge").map(function (r) { return [r.field, r.at, r.len]; })'
		. '};'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('ipucu', 'islak', 'islak', 'scgou', 'ipucu'), $res['fold'], 'İ, I and ı read as i; ş ç ğ ö ü as their plain letters');
	pg_assert_true($res['sameLength'], 'one letter for one, so a place found is the place shown');
	pg_assert_same(array('Gölge', 'Metin gölgesi', 'Üzerine gelince gölge', 'Islak zemin'), $res['plain'], 'a plain "golge" finds "Gölge", best first, then its section');
	pg_assert_same($res['plain'], $res['marked'], 'a typed "gölge" finds the same rows');
	pg_assert_same(array('Islak zemin'), $res['caps'], 'capitals and the Turkish I fold to the label');
	pg_assert_same(array('İç boşluk', 'Üst iç boşluk'), $res['dotted'], '"ic bosluk" finds "İç boşluk"');
	pg_assert_same(array('Konum'), $res['section'], 'a section\'s name finds its rows');
	pg_assert_same(array('label', 0, 5), $res['at'][0], 'the place of the words in the label, for the mark');
	pg_assert_same(array('label', 6, 5), $res['at'][1], 'a word inside the label is marked where it stands');
}

function test_options_search_margin_finds_the_outer_spacing_rows()
{
	$res = options_search_test_node(
		'var m = S.match(E, "margin");'
		. 'out = {'
		. ' margin: labels(m),'
		. ' codes: m.map(function (r) { return S.code(r, "margin"); }),'
		. ' paths: m.map(function (r) { return S.path(r.e); }),'
		. ' mt: labels(S.match(E, "mt-")),'
		. ' mt3: labels(S.match(E, "mt-3")),'
		. ' shadow: labels(S.match(E, "box-shadow")),'
		. ' align: labels(S.match(E, "text-align")),'
		. ' prefixes: [S.prefixOf("mt-md-0,mt-md-1"), S.prefixOf("shadow-none,shadow,shadow-lg"), S.prefixOf("sticky-top,fixed-top"), S.prefixOf("btn-sm,btn-lg")],'
		. ' css: [S.cssOf("text-", "text-start,text-end"), S.cssOf("text-", "text-primary"), S.cssOf("", "", "zIndex"), S.cssOf("", "", "text")]'
		. '};'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Dış boşluk', 'Üst dış boşluk', 'Alt dış boşluk'), $res['margin'], '"margin" finds the outer spacing rows and no inner one');
	pg_assert_same(array('margin', 'mt-*', 'mb-*'), $res['codes'], 'the property itself, else the class prefix, at the right');
	pg_assert_same('Stil › Boşluk', $res['paths'][0], 'the path: tab › section');
	pg_assert_same(array('Üst dış boşluk'), $res['mt'], 'the class prefix finds its row');
	pg_assert_same(array('Üst dış boşluk'), $res['mt3'], 'a whole class finds the row writing it');
	pg_assert_same(array('Gölge'), $res['shadow'], 'the CSS property finds a row by its class group');
	pg_assert_same(array('Hizalama'), $res['align'], 'text- classes of alignment are text-align');
	pg_assert_same(array('mt-', 'shadow-', '', 'btn-'), $res['prefixes'], 'the prefix of a group of classes');
	pg_assert_same(array('text-align', 'color', 'z-index', ''), $res['css'], 'the property of a group or of a stored property');
}

function test_options_search_enter_targets_a_closed_section_of_another_tab()
{
	$res = options_search_test_node(
		'var r = S.match(E, "gölge")[0];'
		. 'out = {'
		. ' path: S.path(r.e),'
		. ' closed: S.target(r.e, { tab: "content", open: { "sdsc-heading": true } }),'
		. ' open: S.target(r.e, { tab: "style", open: { "sdsc-ap-styling": true } }),'
		. ' box: [S.boxSide("mt-"), S.boxSide("m-"), S.boxSide("pe-"), S.boxSide("shadow-")]'
		. '};'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same('Stil › Gölge ve efekt', $res['path'], 'a result shows its path');
	pg_assert_same(array(
		'tab' => 'style',
		'switchTab' => true,
		'section' => 'sdsc-ap-styling',
		'openSection' => true,
		'control' => array('sid' => 'sdsc-ap-styling', 'nth' => 0, 'label' => 'Gölge'),
	), $res['closed'], 'Enter switches to Style, opens Shadow and effect and focuses its Shadow row');
	pg_assert_same(false, $res['open']['switchTab'], 'the tab shown stays');
	pg_assert_same(false, $res['open']['openSection'], 'an open section is not marked "just opened"');
	pg_assert_same(array(array('k' => 'm', 's' => 't'), array('k' => 'm', 's' => 't'), array('k' => 'p', 's' => 'e'), null), $res['box'], 'a hidden spacing row focuses its side of the box');
}

function test_options_search_no_result_offers_the_class()
{
	$res = options_search_test_node(
		'out = {'
		. ' none: S.match(E, "parlama").length,'
		. ' glow: S.noMatch("  parlama ", "btn glow"),'
		. ' has: S.noMatch("glow", "btn glow"),'
		. ' words: S.noMatch("my glow"),'
		. ' digit: S.noMatch("3col"),'
		. ' odd: S.noMatch("<b>"),'
		. ' empty: S.match(E, "   ").length'
		. '};'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(0, $res['none'], 'nothing holds "parlama"');
	pg_assert_same(array('say' => 'parlama', 'add' => 'parlama'), $res['glow'], 'the line says the words as typed and offers them as a class');
	pg_assert_same('', $res['has']['add'], 'a class the element holds is not offered');
	pg_assert_same('my-glow', $res['words']['add'], 'words are joined by a dash');
	pg_assert_same('', $res['digit']['add'], 'a class cannot start with a digit');
	pg_assert_same('b', $res['odd']['add'], 'only letters, digits, dashes and underscores');
	pg_assert_same(0, $res['empty'], 'an empty search finds nothing');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_optsearch.js');
	pg_assert_contains("_sdT('No option matches “{var}”.', typed)", $core, 'the line of before keeps its wording');
	pg_assert_contains("_sdT('Add the class “{var}”'", $part, 'the offer under it');
}

function test_options_search_is_a_part()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/style_designer_optsearch.min.js', isset($parts['optsearch']) ? $parts['optsearch'] : null, 'optsearch is a part');
	pg_assert_same(array('assets/js/pg_options_search.js', 'assets/js/style_designer_optsearch.js'), $bundles['assets/js/style_designer_optsearch.min.js'], 'the module, then the part');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'optsearch'", $screen, 'the editor is handed the part');
	pg_assert_contains("'spacing'", $screen, 'and the spacing box\'s part');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdT('Search or add an option…')", $core, 'the box\'s placeholder');
	pg_assert_contains("_sdPartCall('optsearch', 'search', [_sdOptSearchHost(typed)])", $core, 'the core runs the search through the part');
	pg_assert_contains('_SD_OPT_SEARCH_MS = 120', $core, 'results 120 ms after typing');
	pg_assert_contains("e.key !== '/'", $core, '/ focuses the box');
}

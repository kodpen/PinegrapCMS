<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the search box of the designer's Options panel
 * (filterPropertiesPanel() in style_designer.js, run under Node with a
 * small stand-in for the DOM): a typed i finds a label with a Turkish
 * capital İ, a term typed in capitals finds a lower-case label, a section
 * without option rows is found by its title or its buttons, and a search
 * that finds nothing says so under the box.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Whether Node can be run here; the tests pass quietly without it.
function _opts_search_node_ok()
{
	if (!function_exists('exec')) return false;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	return $code === 0;
}

// Per term, in order: [visible rows, shown sections, empty line, quick body display].
function _opts_search_run(array $terms)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$src = '';
	foreach (array('_sdCondFold', '_sdPropsSearchEmpty', 'filterPropertiesPanel') as $fn) {
		$a = strpos($js, "\n    function " . $fn . '(');
		pg_assert_true($a !== false, 'found: ' . $fn);
		$b = strpos($js, "\n    }\n", $a);
		$src .= substr($js, $a, $b + 7 - $a);
	}
	$dom = <<<'JS'
function el(cls, text, tag) {
    var e = { cls: cls, tag: tag || 'div', textContent: text || '', style: {}, kids: [], parentElement: null, attrs: {},
        classList: { contains: function (c) { return e.cls === c; } },
        hasAttribute: function (n) { return n in e.attrs; },
        setAttribute: function (n, v) { e.attrs[n] = v; },
        removeAttribute: function (n) { delete e.attrs[n]; },
        add: function (k) { k.parentElement = e; e.kids.push(k); return k; },
        insertBefore: function (k, before) { k.parentElement = e; e.kids.splice(e.kids.indexOf(before), 0, k); },
        all: function () { var r = []; e.kids.forEach(function (k) { r.push(k); r = r.concat(k.all()); }); return r; },
        querySelectorAll: function (sel) {
            var want = sel.split(',').map(function (s) { return s.trim().split('[')[0]; });
            return e.all().filter(function (k) { return want.indexOf('.' + k.cls) !== -1 || want.indexOf(k.tag) !== -1; });
        },
        querySelector: function (sel) { return e.querySelectorAll(sel)[0] || null; } };
    return e;
}
var root = el('root');
var wrap = root.add(el('sd-props-search-wrap'));
var box = wrap.add(el('x', '', 'input'));
var panel = root.add(el('panel'));
wrap.nextSibling = panel;
function section(title, labels) {
    var s = panel.add(el('sd-props-section'));
    s.add(el('sd-props-section-title', title));
    var body = s.add(el('sd-sect-body'));
    labels.forEach(function (l) { body.add(el('sd-prop-row')).add(el('sd-prop-label', l)); });
    return s;
}
section('Çeviri', []).kids[1].add(el('x', 'Türkçe', 'select'));
section('İpucu', ['Metin', 'Konum', 'Gecikme']);
section('Başlık', ['Etiket', 'İç Boşluk', 'Islak Zemin', 'Text Color']);
var quick = section('Hızlı Düzenler', []);
var qbody = quick.kids[1];
qbody.style.display = 'none';
qbody.add(el('x', '1 Sütun', 'button'));
qbody.add(el('x', '2 Sütun', 'button'));
var ids = { 'sd-properties': panel, 'sd-props-search': box };
var document = {
    getElementById: function (id) { return ids[id] || null; },
    createElement: function () {
        var e = el('');
        Object.defineProperty(e, 'id', { set: function (v) { ids[v] = e; } });
        return e;
    }
};
function _sdT(s, v) { return s.replace('{var}', v); }
// The Options search part has not run: the filter keeps its own line.
function _sdOptSearchRefine() { return false; }
function rows() {
    return panel.querySelectorAll('.sd-prop-row').filter(function (r) {
        return r.style.display !== 'none' && r.parentElement.parentElement.style.display !== 'none';
    }).map(function (r) { return r.kids[0].textContent; }).join(',');
}
function sects() {
    return panel.kids.filter(function (k) { return k.style.display !== 'none'; })
        .map(function (k) { return k.kids[0].textContent; }).join(',');
}
function emptyLine() {
    var l = ids['sd-props-search-empty'];
    return l && l.style.display !== 'none' ? l.textContent : '';
}
JS;
	$script = $dom . "\n" . $src
		. 'var terms = ' . json_encode($terms) . ";\n"
		. "console.log(JSON.stringify(terms.map(function (t) { filterPropertiesPanel(t); return [rows(), sects(), emptyLine(), qbody.style.display]; })));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgopts');
	if ($file === false) return null;
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer) && count($answer) === count($terms), 'Node printed the answers: ' . substr($printed, 0, 300));
	return $answer;
}

function test_designer_options_search_folds_turkish_i_and_capitals()
{
	if (!_opts_search_node_ok()) return;
	$terms = array('ipucu', 'iç boşluk', 'ETİKET', 'İpucu', 'etiket', 'ıslak', 'TEXT', 'color', '');
	$seen = array_combine($terms, array_map(function ($a) { return $a[0]; }, _opts_search_run($terms)));
	pg_assert_same('Metin,Konum,Gecikme', $seen['ipucu'], 'a typed i finds the section titled İpucu');
	pg_assert_same('İç Boşluk', $seen['iç boşluk'], 'a typed i finds the row İç Boşluk');
	pg_assert_same('Etiket', $seen['ETİKET'], 'a term in capitals finds a lower-case label');
	pg_assert_same('Metin,Konum,Gecikme', $seen['İpucu'], 'the capital İ still finds its own label');
	pg_assert_same('Etiket', $seen['etiket'], 'lower case as before');
	pg_assert_same('Islak Zemin', $seen['ıslak'], 'a dotless ı finds a capital I');
	pg_assert_same('Text Color', $seen['TEXT'], 'English capitals');
	pg_assert_same('Text Color', $seen['color'], 'English lower case');
	pg_assert_same('Metin,Konum,Gecikme,Etiket,İç Boşluk,Islak Zemin,Text Color', $seen[''], 'an empty box shows every row again');
}

function test_designer_options_search_finds_sections_without_rows()
{
	if (!_opts_search_node_ok()) return;
	list($ceviri, $hizli, $sutun, $turkce, $cleared) = _opts_search_run(array('çeviri', 'hızlı', 'sütun', 'türkçe', ''));
	pg_assert_same('Çeviri', $ceviri[1], 'a section without option rows is found by its title');
	pg_assert_same('Hızlı Düzenler', $hizli[1], 'the quick layouts by their title');
	pg_assert_same('', $hizli[3], 'its collapsed body is opened for the search');
	pg_assert_same('Hızlı Düzenler', $sutun[1], 'the quick layouts by the text of their buttons');
	pg_assert_same('', $turkce[1], 'the text of a select box is not searched');
	pg_assert_same('Çeviri,İpucu,Başlık,Hızlı Düzenler', $cleared[1], 'an empty box shows every section again');
	pg_assert_same('none', $cleared[3], 'and closes the body it opened');
}

function test_designer_options_search_says_when_nothing_matches()
{
	if (!_opts_search_node_ok()) return;
	list($margin, $found, $cleared) = _opts_search_run(array('  margin ', 'etiket', ''));
	pg_assert_same('', $margin[1], 'no section is shown');
	pg_assert_same('No option matches “margin”.', $margin[2], 'the line names the trimmed word as typed');
	pg_assert_same('', $found[2], 'a search with a match hides the line');
	pg_assert_same('', $cleared[2], 'an empty box hides the line');
}

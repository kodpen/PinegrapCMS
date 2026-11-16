<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the search box of the designer's Options panel
 * (filterPropertiesPanel() in style_designer.js, run under Node with a
 * small stand-in for the DOM): a typed i finds a label with a Turkish
 * capital İ, and a term typed in capitals finds a lower-case label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Whether Node can be run here; the test passes quietly without it.
function _opts_search_node_ok()
{
	if (!function_exists('exec')) return false;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	return $code === 0;
}

// Visible rows per term: [term => 'label,label'] over a panel of two sections.
function _opts_search_run(array $terms)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$src = '';
	foreach (array('_sdCondFold', 'filterPropertiesPanel') as $fn) {
		$a = strpos($js, "\n    function " . $fn . '(');
		pg_assert_true($a !== false, 'found: ' . $fn);
		$b = strpos($js, "\n    }\n", $a);
		$src .= substr($js, $a, $b + 7 - $a);
	}
	$dom = <<<'JS'
function el(cls, text) {
    var e = { cls: cls, textContent: text || '', style: {}, kids: [], parentElement: null, attrs: {},
        classList: { contains: function (c) { return e.cls === c; } },
        hasAttribute: function (n) { return n in e.attrs; },
        setAttribute: function (n, v) { e.attrs[n] = v; },
        removeAttribute: function (n) { delete e.attrs[n]; },
        add: function (k) { k.parentElement = e; e.kids.push(k); return k; },
        all: function () { var r = []; e.kids.forEach(function (k) { r.push(k); r = r.concat(k.all()); }); return r; },
        querySelectorAll: function (sel) {
            var want = sel.split(',').map(function (s) { return s.trim().replace(/^\./, '').split('[')[0]; });
            return e.all().filter(function (k) { return want.indexOf(k.cls) !== -1; });
        },
        querySelector: function (sel) { return e.querySelectorAll(sel)[0] || null; } };
    return e;
}
var panel = el('panel');
function section(title, labels) {
    var s = panel.add(el('sd-props-section'));
    s.add(el('sd-props-section-title', title));
    var body = s.add(el('sd-sect-body'));
    labels.forEach(function (l) { body.add(el('sd-prop-row')).add(el('sd-prop-label', l)); });
}
section('İpucu', ['Metin', 'Konum', 'Gecikme']);
section('Başlık', ['Etiket', 'İç Boşluk', 'Islak Zemin', 'Text Color']);
var document = { getElementById: function () { return panel; } };
JS;
	$script = $dom . "\n" . $src
		. 'var terms = ' . json_encode($terms) . ";\n"
		. "console.log(JSON.stringify(terms.map(function (t) { filterPropertiesPanel(t);\n"
		. "    return panel.querySelectorAll('.sd-prop-row').filter(function (r) { return r.style.display !== 'none' && r.parentElement.parentElement.style.display !== 'none'; })\n"
		. "        .map(function (r) { return r.kids[0].textContent; }).join(','); })));\n";
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
	return array_combine($terms, $answer);
}

function test_designer_options_search_folds_turkish_i_and_capitals()
{
	if (!_opts_search_node_ok()) return;
	$seen = _opts_search_run(array('ipucu', 'iç boşluk', 'ETİKET', 'İpucu', 'etiket', 'ıslak', 'TEXT', 'color', 'nothing', ''));
	pg_assert_same('Metin,Konum,Gecikme', $seen['ipucu'], 'a typed i finds the section titled İpucu');
	pg_assert_same('İç Boşluk', $seen['iç boşluk'], 'a typed i finds the row İç Boşluk');
	pg_assert_same('Etiket', $seen['ETİKET'], 'a term in capitals finds a lower-case label');
	pg_assert_same('Metin,Konum,Gecikme', $seen['İpucu'], 'the capital İ still finds its own label');
	pg_assert_same('Etiket', $seen['etiket'], 'lower case as before');
	pg_assert_same('Islak Zemin', $seen['ıslak'], 'a dotless ı finds a capital I');
	pg_assert_same('Text Color', $seen['TEXT'], 'English capitals');
	pg_assert_same('Text Color', $seen['color'], 'English lower case');
	pg_assert_same('', $seen['nothing'], 'no match shows no row');
	pg_assert_same('Metin,Konum,Gecikme,Etiket,İç Boşluk,Islak Zemin,Text Color', $seen[''], 'an empty box shows every row again');
}

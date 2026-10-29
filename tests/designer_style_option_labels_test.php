<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the options panel selects the visual designer builds from a list
 * of utility classes (selStyleGroup() in assets/js/style_designer.src.js): the
 * placeholder and the option labels read in the panel's language ("Küçük",
 * "Üst 3", "Normal" for the bare shadow class) instead of the class names
 * they are cut from, and a label without a translation keeps its old form.
 * The functions are cut out of the script and run under Node (skipped
 * without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// Cuts the function that starts with $start out of the designer's source,
// up to the next top-level function.
function _test_sol_cut($source, $start)
{
	$from = strpos($source, $start);
	pg_assert_true($from !== false, 'the designer has ' . trim($start));

	if ($from === false) {
		return '';
	}

	return substr($source, $from, strpos($source, "\n    function ", $from + 10) - $from);
}

// Runs each [cssClass, classes, placeholder] through selStyleGroup() with
// the Turkish values of the given keys as the panel's language (none for
// English) and returns each select's options as [value, label, selected];
// null without Node.
function _test_sol_run($cases, $keys = array())
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$language = pg_test_language_map();
	$i18n = array();
	foreach ($keys as $key) {
		pg_assert_true(isset($language[$key]), $key . ' has a Turkish value');
		$i18n[$key] = isset($language[$key]) ? $language[$key] : $key;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$script = 'var sdDesign = {i18n: ' . json_encode((object) $i18n) . "};\n"
		. _test_sol_cut($source, '    function _sdT(key, vars) {') . "\n"
		. _test_sol_cut($source, '    function capFirst(s) {') . "\n"
		. _test_sol_cut($source, '    function _sdStyleOptionLabel(') . "\n"
		. _test_sol_cut($source, '    function selStyleGroup(') . "\n"
		. 'console.log(JSON.stringify(' . json_encode($cases) . '.map(function (c) {'
		. '    var html = selStyleGroup({cssClass: c[0]}, c[1], c[2]);'
		. '    var re = /<option value="([^"]*)"( selected)?>([^<]*)<\/option>/g, m, opts = [];'
		. '    while ((m = re.exec(html)) !== null) { opts.push([m[1], m[3], !!m[2]]); }'
		. '    return {group: (html.match(/data-stylegroup="([^"]*)"/) || ["", ""])[1], options: opts};'
		. '})));';
	$file = tempnam(sys_get_temp_dir(), 'pgsol');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$result = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($result), implode("\n", $out));

	return $result;
}

function _test_sol_keys()
{
	return array('Default', 'None', 'Regular', 'Small', 'Large', 'Circle', 'Pill', 'Top', 'End', 'Bottom', 'Start');
}

// The shadow select: a translated placeholder, "Hiçbiri" / "Küçük" / "Büyük"
// for the sized classes and "Normal" for the bare `shadow`, which had no
// label at all; the node's own class stays selected.
function test_designer_style_option_labels_shadow()
{
	$result = _test_sol_run(array(
		array('card shadow', array('shadow-none', 'shadow-sm', 'shadow', 'shadow-lg'), 'Default'),
	), _test_sol_keys());

	if ($result === null) {
		return;
	}

	pg_assert_same('shadow-none,shadow-sm,shadow,shadow-lg', $result[0]['group']);
	pg_assert_same(array(
		array('', 'Varsayılan', false),
		array('shadow-none', 'Hiçbiri', false),
		array('shadow-sm', 'Küçük', false),
		array('shadow', 'Normal', true),
		array('shadow-lg', 'Büyük', false),
	), $result[0]['options']);
}

// The radius selects: numbers stay numbers, the circle and pill shapes and
// the side of a per-side radius are translated.
function test_designer_style_option_labels_radius()
{
	$result = _test_sol_run(array(
		array('', array('rounded-0', 'rounded-1', 'rounded-5', 'rounded-circle', 'rounded-pill'), 'Default'),
		array('rounded-top-3', array('rounded-top-0', 'rounded-top-3'), 'Default'),
		array('', array('rounded-end-1', 'rounded-bottom-2', 'rounded-start-5'), 'Default'),
		array('', array('border-1', 'border-5'), 'Default'),
	), _test_sol_keys());

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('', 'Varsayılan', false),
		array('rounded-0', '0', false),
		array('rounded-1', '1', false),
		array('rounded-5', '5', false),
		array('rounded-circle', 'Daire', false),
		array('rounded-pill', 'Hap (tam yuvarlak)', false),
	), $result[0]['options']);
	pg_assert_same(array(
		array('', 'Varsayılan', false),
		array('rounded-top-0', 'Üst 0', false),
		array('rounded-top-3', 'Üst 3', true),
	), $result[1]['options']);
	pg_assert_same(array(
		array('', 'Varsayılan', false),
		array('rounded-end-1', 'Bitiş 1', false),
		array('rounded-bottom-2', 'Alt 2', false),
		array('rounded-start-5', 'Başlangıç 5', false),
	), $result[2]['options']);
	pg_assert_same(array(
		array('', 'Varsayılan', false),
		array('border-1', '1', false),
		array('border-5', '5', false),
	), $result[3]['options']);
}

// The three opacity selects show the percentage alone: the text opacity
// read "Opacity 25" because only its `text-` prefix was removed.
function test_designer_style_option_labels_opacity()
{
	$result = _test_sol_run(array(
		array('', array('text-opacity-25', 'text-opacity-100'), 'Default'),
		array('', array('bg-opacity-10', 'bg-opacity-75'), 'Default'),
		array('', array('opacity-0', 'opacity-50'), 'Default'),
	), _test_sol_keys());

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('', 'Varsayılan', false), array('text-opacity-25', '25', false), array('text-opacity-100', '100', false)), $result[0]['options']);
	pg_assert_same(array(array('', 'Varsayılan', false), array('bg-opacity-10', '10', false), array('bg-opacity-75', '75', false)), $result[1]['options']);
	pg_assert_same(array(array('', 'Varsayılan', false), array('opacity-0', '0', false), array('opacity-50', '50', false)), $result[2]['options']);
}

// A placeholder named 'None' is translated too; one the caller already
// translated is printed as given.
function test_designer_style_option_labels_placeholder()
{
	$result = _test_sol_run(array(
		array('', array('text-lowercase'), 'None'),
		array('', array('text-white'), 'Otomatik'),
		array('', array('text-white'), ''),
	), _test_sol_keys());

	if ($result === null) {
		return;
	}

	pg_assert_same(array('', 'Hiçbiri', false), $result[0]['options'][0]);
	pg_assert_same(array('', 'Otomatik', false), $result[1]['options'][0]);
	pg_assert_same(array('', 'Varsayılan', false), $result[2]['options'][0]);
}

// A label with a word that has no translation keeps its class-derived form
// in full: no half-translated "Decoration Hiçbiri". The font style classes
// lose their whole `fst-` prefix (they read "T italic").
function test_designer_style_option_labels_untranslated_words_kept()
{
	$result = _test_sol_run(array(
		array('', array('fw-lighter', 'text-decoration-none', 'object-fit-scale-down', 'fst-italic', 'lh-sm', 'lh-base'), 'Default'),
	), _test_sol_keys());

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('', 'Varsayılan', false),
		array('fw-lighter', 'Lighter', false),
		array('text-decoration-none', 'Decoration none', false),
		array('object-fit-scale-down', 'Fit scale down', false),
		array('fst-italic', 'Italic', false),
		array('lh-sm', 'Küçük', false),
		array('lh-base', 'Base', false),
	), $result[0]['options']);
}

// On an English panel the keys print as they are.
function test_designer_style_option_labels_english()
{
	$result = _test_sol_run(array(
		array('shadow-lg', array('shadow-none', 'shadow-sm', 'shadow', 'shadow-lg'), 'Default'),
		array('', array('rounded-top-2'), 'None'),
	));

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('', 'Default', false),
		array('shadow-none', 'None', false),
		array('shadow-sm', 'Small', false),
		array('shadow', 'Regular', false),
		array('shadow-lg', 'Large', true),
	), $result[0]['options']);
	pg_assert_same(array(array('', 'None', false), array('rounded-top-2', 'Top 2', false)), $result[1]['options']);
}

// The colour rows of the Colors section have keys of their own: 'Text Col'
// is the block templates' column name ("Metin Sütunu"), and the class
// picker and the custom colour both said "Arka Plan Rengi".
function test_designer_style_option_labels_colour_row_keys()
{
	$language = pg_test_language_map();
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("row(_sdT('Text Color'),", $source);
	pg_assert_contains("row(_sdT('Background Color'),", $source);
	pg_assert_contains("esc(_sdT('Custom Background Color'))", $source);
	pg_assert_same(0, preg_match_all("/row\\(_sdT\\('(Text Col|Bg Col|Bg Color)'\\)/", $source));
	pg_assert_same('Metin Rengi', $language['Text Color']);
	pg_assert_same('Arka Plan Rengi', $language['Background Color']);
	pg_assert_same('Özel Arka Plan Rengi', $language['Custom Background Color']);
	pg_assert_same('Metin Sütunu', $language['Text Col']);
	// The colour picker's button and the placement selects say their
	// first option through _sdT() like the hidden select below them.
	pg_assert_same(0, preg_match_all("/: 'None';/", $source));
	pg_assert_same(0, preg_match_all("/\\['top',\\s*'Top'\\]/", $source));
}

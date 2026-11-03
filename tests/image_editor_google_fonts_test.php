<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Google Fonts choice of the image editor's Text tool: the
 * one catalogue the designer and the editor share
 * (assets/js/pg_google_fonts.js), its place in both bundles, and the
 * model's search and recent list (run under Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_fonts_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgiefonts');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var G = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_google_fonts.js') . ');' . "\n"
		. 'var r3 = function (v) { return Math.round(v * 1000) / 1000; };' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// One catalogue: five categories, each family once, every name usable as a
// text layer's 'g:' font as it stands.
function test_image_editor_fonts_catalogue()
{
	$r = image_editor_fonts_node(
		'var all = G.all(), seen = {}, twice = [], odd = [];' . "\n"
		. 'all.forEach(function (f) { if (seen[f]) { twice.push(f); } seen[f] = 1; if (M.textFamilyName(f) !== f) { odd.push(f); } });' . "\n"
		. 'console.log(JSON.stringify({ order: G.order, count: all.length, twice: twice, odd: odd, inter: G.categories.sans.indexOf("Inter"), mono: G.categories.mono.indexOf("Roboto Mono") >= 0 }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('sans', 'serif', 'display', 'hand', 'mono'), $r['order'], 'the five categories in order');
	pg_assert_true($r['count'] >= 200, 'about two hundred families');
	pg_assert_same(array(), $r['twice'], 'each family once');
	pg_assert_same(array(), $r['odd'], 'every name is a valid g: family');
	pg_assert_same(0, $r['inter'], 'the most popular first');
	pg_assert_true($r['mono'], 'monospace families listed');
}

// Both bundles start with the catalogue; the designer keeps no copy.
function test_image_editor_fonts_one_source()
{
	$bundles = pg_js_bundles();

	pg_assert_same('assets/js/pg_google_fonts.js', $bundles['assets/js/style_designer.min.js'][0], 'the designer reads the shared list');
	pg_assert_same('assets/js/pg_google_fonts.js', $bundles['assets/js/pg_image_editor.min.js'][0], 'the editor reads the shared list');

	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_false(strpos($designer, "'Bebas Neue'") !== false, 'no second copy of the list in the designer');
	pg_assert_contains('PgGoogleFonts', $designer, 'the designer takes the shared list');
}

// Search by name in a category, any letter case; the recent list keeps the
// last five families, newest first, each once.
function test_image_editor_fonts_search_and_recent()
{
	$r = image_editor_fonts_node(
		'var hits = M.fontSearch(G, "ROBOTO", ""), sans = M.fontSearch(G, "roboto", "sans"), hand = M.fontSearch(G, "", "hand");' . "\n"
		. 'var recent = ["Lato", "Inter", "Lora", "Oswald", "Anton"];' . "\n"
		. 'console.log(JSON.stringify({ hits: hits.map(function (h) { return h.family + "/" + h.category; }), sans: sans.length, hand: hand.length, handFirst: hand[0].family,'
		. ' none: M.fontSearch(G, "zzzz", "").length, added: M.fontRecent(recent, "Lora"), fresh: M.fontRecent(recent, "Pacifico"), bad: M.fontRecent(recent, "<b>"), empty: M.fontRecent(null, "Inter") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_contains('Roboto Mono/mono', implode(',', $r['hits']), 'a search covers every category');
	pg_assert_same('Roboto/sans', $r['hits'][0], 'in catalogue order');
	pg_assert_same(3, $r['sans'], 'a category narrows it');
	pg_assert_same(32, $r['hand'], 'an empty search lists the category');
	pg_assert_same('Pacifico', $r['handFirst'], 'its most popular first');
	pg_assert_same(0, $r['none'], 'nothing found');
	pg_assert_same(array('Lora', 'Lato', 'Inter', 'Oswald', 'Anton'), $r['added'], 'a used family moves to the front');
	pg_assert_same(array('Pacifico', 'Lato', 'Inter', 'Lora', 'Oswald'), $r['fresh'], 'a new one pushes the oldest out');
	pg_assert_same(array('Lato', 'Inter', 'Lora', 'Oswald', 'Anton'), $r['bad'], 'a name that is not a family is not kept');
	pg_assert_same(array('Inter'), $r['empty'], 'from nothing');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Favourites in the visual editor's Components palette: the row key shared
 * with Recently Used, which rows may be kept, the toggle with its limit run
 * under Node, and source pins for the group's order, the one star and the F
 * key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pfav_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of a top-level `function <name>(` up to its closing "    }" line.
function _pfav_fn($name)
{
	$js = _pfav_js();
	$start = strpos($js, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($js, "\n    }\n", $start + 1);
	return $end === false ? '' : substr($js, $start, $end - $start + 7);
}

// $script run under Node after the pure helpers: its JSON answer, or null
// when Node is missing.
function _pfav_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$helpers = _pfav_fn('_sdPalItemId') . _pfav_fn('_sdPalKeepable') . _pfav_fn('_sdFavToggle') . _pfav_fn('_sdFavHas');
	$file = tempnam(sys_get_temp_dir(), 'pgpfav');
	file_put_contents($file, $helpers . "\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	return json_decode(implode("\n", $out), true);
}

function test_designer_palette_favourites_row_key_and_keepable()
{
	$res = _pfav_node('console.log(JSON.stringify({'
		. 'a: _sdPalItemId("component", { componentType: "hero-centered" }),'
		. 'b: _sdPalItemId("content", { contentType: "image", tag: "img" }),'
		. 'c: _sdPalItemId("semantic"),'
		. 'k: ["component", "widget_part", "library", ""].map(_sdPalKeepable)'
		. '}));');
	if ($res === null) {
		return;
	}
	pg_assert_same('component:hero-centered', $res['a'], 'the component type tells rows apart');
	pg_assert_same('content:image:img', $res['b'], 'content type and tag');
	pg_assert_same('semantic', $res['c'], 'no extra');
	pg_assert_same(array(true, false, false, false), $res['k'], 'a widget part and a library item are not kept');
}

function test_designer_palette_favourites_toggle()
{
	$res = _pfav_node('var a = { id: "x" }, b = { id: "y" }, c = { id: "z" };'
		. 'var one = _sdFavToggle([], a, 2);'
		. 'var two = _sdFavToggle(one, b, 2);'
		. 'var full = _sdFavToggle(two, c, 2);'
		. 'var off = _sdFavToggle(two, a, 2);'
		. 'var junk = _sdFavToggle([null, { id: "" }, 5, { id: "q" }], null, 5);'
		. 'console.log(JSON.stringify({ one: one.map(function (i) { return i.id; }),'
		. ' two: two.map(function (i) { return i.id; }), full: full.map(function (i) { return i.id; }),'
		. ' off: off.map(function (i) { return i.id; }), junk: junk.map(function (i) { return i.id; }),'
		. ' notMutated: one.length, has: [_sdFavHas(two, "y"), _sdFavHas(off, "x"), _sdFavHas(null, "x")] }));');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('x'), $res['one'], 'added');
	pg_assert_same(array('x', 'y'), $res['two'], 'a new one goes last');
	pg_assert_same(array('x', 'y'), $res['full'], 'a full list is answered unchanged');
	pg_assert_same(array('y'), $res['off'], 'one already there is taken out');
	pg_assert_same(array('q'), $res['junk'], 'rows without an id are dropped');
	pg_assert_same(1, $res['notMutated'], 'the list passed in is not changed');
	pg_assert_same(array(true, false, false), $res['has'], '_sdFavHas');
}

function test_designer_palette_favourites_order_and_storage()
{
	$js = _pfav_js();
	pg_assert_contains("var FAV_COMPS_KEY = 'pinegrap_designer_favourite_components_v1';", $js, 'kept in the browser beside Recently Used');

	$recent = _pfav_fn('_renderRecentCompSection');
	pg_assert_contains("querySelector('#sd-fav-comps-section')", $recent, 'Recently Used goes under Favourites');
	pg_assert_contains('_sdPalForDesign(', $recent, 'the Bootstrap filter is shared');

	$fav = _pfav_fn('_renderFavCompSection');
	pg_assert_contains("esc(_sdT('Favourites'))", $fav, 'the group is named');
	pg_assert_contains('_sdPalForDesign(_getFavComps())', $fav, 'a custom design hides Bootstrap favourites');
	pg_assert_contains('compList.insertBefore(sec, compList.firstChild)', $fav, 'Favourites lead the list');

	pg_assert_contains("_sdFavWire(compList);\n        _sdFavWire(blockList);", $js, 'both lists show the star');
	pg_assert_contains("_renderRecentCompSection(compList);\n        _renderFavCompSection(compList);", $js, 'Favourites drawn after, so above, Recently Used');

	$track = _pfav_fn('_trackRecentComp');
	pg_assert_contains('_sdPalKeepable(type)', $track, 'Recently Used keeps the same rows');
	pg_assert_contains('_sdPalItemId(type, extra)', $track, 'and the same key');
}

function test_designer_palette_favourites_star_and_key()
{
	$wire = _pfav_fn('_sdFavWire');
	pg_assert_contains("addEventListener('mouseover'", $wire, 'the star follows the pointer');
	pg_assert_contains("addEventListener('focusin'", $wire, 'and the focus');
	pg_assert_contains('_sdFavStar.tabIndex = -1', $wire, 'the star is no extra tab stop inside the row');
	pg_assert_contains("setAttribute('draggable', 'false')", $wire, 'pressing the star does not start a drag');

	$row = _pfav_fn('_sdFavRowOf');
	pg_assert_contains("closest('#sd-widget-palette')", $row, 'not on a widget part row');
	pg_assert_contains("closest('#sd-comp-list, #sd-blocks-list')", $row, 'only the Components and Blocks lists');

	$keys = _pfav_fn('_sdWirePaletteKeys');
	pg_assert_contains("String(e.key).toLowerCase() === 'f' && _sdFavRowOf(row)", $keys, 'F on a focused row pins it');

	$sync = _pfav_fn('_sdFavStarSync');
	pg_assert_contains("setAttribute('aria-pressed'", $sync, 'the star says whether it is on');
	pg_assert_contains("_sdT('Remove from Favourites')", $sync, 'named when on');
	pg_assert_contains("_sdT('Add to Favourites')", $sync, 'named when off');

	pg_assert_contains("e.target.closest('.sd-fav-btn')) return;", _pfav_fn('_sdWirePaletteItems'), 'a double-click on the star inserts nothing');
}

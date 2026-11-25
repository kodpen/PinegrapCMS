<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Hide on" (Phone, Tablet, Desktop) and "Sticky from" a size in
 * the designer's Size and display section (assets/js/pg_size_style.js):
 * Display None per size read back from Bootstrap's d-* classes and written
 * as the fewest of them, and Position Sticky from a size as sticky-{size}-top.
 * Run under Node; skipped without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the module as S; returns what it prints as JSON, null
// without Node.
function designer_hide_sticky_run($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pghide');

	if ($file === false) {
		return null;
	}

	file_put_contents($file,
		"global.window = {};\n"
		. 'var S = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_size_style.js') . ");\n"
		. $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		pg_assert_false(true, implode("\n", $out));

		return null;
	}

	return json_decode(implode("\n", $out), true);
}

function test_designer_hide_on_read()
{
	$r = designer_hide_sticky_run(
		'console.log(JSON.stringify(['
		. 'S.hideOn(""), S.hideOn("p-3 d-none d-md-block"), S.hideOn("d-md-none d-lg-flex"),'
		. 'S.hideOn("d-lg-none"), S.hideOn("d-none"), S.hideOn("d-sm-none d-md-block"),'
		. 'S.hideOn("d-print-none d-flex")'
		. ']));'
	);

	if ($r === null) {
		return;
	}

	$h = function ($p, $t, $d) {
		return array('phone' => $p, 'tablet' => $t, 'desktop' => $d);
	};
	pg_assert_same($h(false, false, false), $r[0], 'no class hides nothing');
	pg_assert_same($h(true, false, false), $r[1], 'd-none d-md-block hides on phones');
	pg_assert_same($h(false, true, false), $r[2]);
	pg_assert_same($h(false, false, true), $r[3]);
	pg_assert_same($h(true, true, true), $r[4]);
	pg_assert_same($h(false, false, false), $r[5], 'hidden at SM only is not the whole phone range');
	pg_assert_same($h(false, false, false), $r[6], 'print classes are not a size');
}

function test_designer_hide_on_write()
{
	$r = designer_hide_sticky_run(
		'var w = function (c, p, t, d) { return S.hideOnWrite(c, { phone: p, tablet: t, desktop: d }); };'
		. 'console.log(JSON.stringify(['
		. 'w("p-3", true, false, false),'
		. 'w("p-3", false, true, false),'
		. 'w("p-3", false, false, true),'
		. 'w("p-3", true, true, true),'
		. 'w("p-3 d-none d-md-block", false, false, false),'
		. 'w("d-flex gap-2", true, false, false),'
		. 'w("d-flex gap-2", false, true, false),'
		. 'w("d-flex d-md-none d-lg-flex", false, false, false),'
		. 'w("a d-none d-md-block b", true, false, true),'
		. 'w("d-print-none p-3", false, false, true),'
		. 'w("p-3", false, false, false)'
		. ']));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('p-3 d-none d-md-block', $r[0], 'hidden on phones, shown from MD up');
	pg_assert_same('p-3 d-md-none d-lg-block', $r[1]);
	pg_assert_same('p-3 d-lg-none', $r[2]);
	pg_assert_same('p-3 d-none', $r[3]);
	pg_assert_same('p-3', $r[4], 'shown again: the classes go, the element draws as before');
	pg_assert_same('d-none d-md-flex gap-2', $r[5], 'the display in force comes back at the next size');
	pg_assert_same('d-flex d-md-none d-lg-flex gap-2', $r[6]);
	pg_assert_same('d-flex', $r[7], 'the fewest classes');
	pg_assert_same('a d-none d-md-block d-lg-none b', $r[8], 'in the place of the classes it replaces');
	pg_assert_same('d-print-none p-3 d-lg-none', $r[9]);
	pg_assert_same('p-3', $r[10], 'nothing hidden on a plain element changes nothing');
}

function test_designer_sticky_from()
{
	$r = designer_hide_sticky_run(
		'console.log(JSON.stringify({ read: ['
		. 'S.stickyFrom(""), S.stickyFrom("position-sticky"), S.stickyFrom("x sticky-md-top"),'
		. 'S.stickyFrom("sticky-top"), S.stickyFrom("position-relative")'
		. '], write: ['
		. 'S.stickyFromWrite("a position-sticky b", "md"),'
		. 'S.stickyFromWrite("a sticky-md-top", "lg"),'
		. 'S.stickyFromWrite("a sticky-md-top", "xs"),'
		. 'S.stickyFromWrite("a position-sticky", "xs"),'
		. 'S.stickyFromWrite("a sticky-xl-top", "")'
		. '], cls: S.STICKY_FROM }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('', 'xs', 'md', '', ''), $r['read'], 'Position Sticky is xs, sticky-md-top is md');
	pg_assert_same('a sticky-md-top b', $r['write'][0], 'from a size, in the place of position-sticky');
	pg_assert_same('a sticky-lg-top', $r['write'][1]);
	pg_assert_same('a position-sticky', $r['write'][2], 'every size is Position Sticky as today');
	pg_assert_same('a position-sticky', $r['write'][3]);
	pg_assert_same('a', $r['write'][4]);
	pg_assert_same(array('sticky-sm-top', 'sticky-md-top', 'sticky-lg-top', 'sticky-xl-top', 'sticky-xxl-top'), $r['cls']);
}

// Both rows are drawn by the spacing part into places the core holds in
// Size and display; the Position list reads a sticky-{size}-top as Sticky
// and drops it on another choice.
function test_designer_hide_sticky_wiring()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('<div data-sd-hideon hidden></div>', $core, 'Hide on holds its place under Display');
	pg_assert_contains('<div data-sd-stickyfrom hidden></div>', $core, 'Sticky from holds its place under Position');
	pg_assert_contains('_SD_STICKY_FROM', $core, 'the Position list knows the sticky-{size}-top classes');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_spacing.js');
	pg_assert_contains('_hoDraw(host', $part, 'the part draws Hide on');
	pg_assert_contains('_sfDraw(host', $part, 'the part draws Sticky from');
}

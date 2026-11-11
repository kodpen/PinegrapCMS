<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's Video item: the palette gives a <video>
 * with the player's controls and no autoplay, its panel picks the file from
 * the file manager's Files tab and the poster from the image picker, and the
 * "Fill its box" switch lays the video over its box, behind the box's
 * content, the way the full-screen media hero wants it. The fill helpers and
 * propsVideoOptions() are cut out of assets/js/style_designer.src.js and run
 * under Node with stubs for the panel helpers; on a machine without Node
 * those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_video_item_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// Runs each JavaScript expression in $cases against the Video helpers and
// returns one value per case; null without Node.
function designer_video_item_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_video_item_test_source();
	$from = strpos($source, '// ── Video Options ──');
	$to = strpos($source, '// ── Label Options ──', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the Video helpers and panel');

	$script = 'function _sdT(s) { return s; }'
		. 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;"); }'
		. 'function sect(icon, title, body) { return "<section " + icon + ">" + title + body + "</section>"; }'
		. 'function row(label, ctrl) { return "<row>" + label + ":" + ctrl + "</row>"; }'
		. 'function swAttr(a, on) { return "<input type=\\"checkbox\\" " + a + (on ? " checked" : "") + ">"; }'
		. 'function getNodeAttr(n, nm) { var a = n.props._attrs || []; for (var i = 0; i < a.length; i++) if (a[i].name === nm) return a[i].value || ""; return ""; }'
		. 'function hasNodeBoolAttr(n, nm) { return (n.props._attrs || []).some(function (a) { return a.name === nm; }); }'
		. 'function video(cls, attrs, bindings) { return { type: "semantic", props: { tag: "video", cssClass: cls, _attrs: attrs || [], _bindings: bindings } }; }'
		. substr($source, $from, $to - $from)
		. 'console.log(JSON.stringify([' . implode(',', array_map(function ($c) {
			return '(' . $c . ')';
		}, $cases)) . ']));';

	$file = tempnam(sys_get_temp_dir(), 'pgvideoitem');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_designer_video_item_palette_gives_a_player_that_does_not_play_by_itself()
{
	$source = designer_video_item_test_source();

	pg_assert_true(
		(bool) preg_match("/\\{ label: _sdT\\('Video'\\), type: 'semantic', icon: 'bi-camera-video', extra: \\{ tag: 'video', cssClass: 'd-block w-100 h-auto',\\s*customName: _sdT\\('Video'\\), _attrs: \\[([^\\]]*)\\] \\} \\}/", $source, $m),
		'the Content group has a Video item'
	);
	pg_assert_true(strpos($m[1], "name: 'controls'") !== false, 'the Video item has the player controls');
	pg_assert_true(strpos($m[1], "name: 'playsinline'") !== false, 'the Video item stays in the page on a phone');
	pg_assert_true(strpos($m[1], "name: 'preload', value: 'metadata'") !== false, 'the Video item loads only the first frame and the length');
	pg_assert_true(strpos($m[1], 'autoplay') === false, 'the Video item does not play by itself');
	pg_assert_true(strpos($source, "'s:video':") !== false, 'the palette card describes the Video item');
	pg_assert_true(
		strpos($source, "(kind === 'page' || kind === 'file' ? '&type=' + kind : '')") !== false,
		'the file picker opens on the tab it is asked for'
	);
	pg_assert_true(
		strpos($source, "node.type === 'semantic' && !_sdIsSentence(node) && !/^(video|audio)$/i.test(String(node.props.tag || ''))") !== false,
		'a double-click on a player does not start a text edit'
	);
}

function test_designer_video_item_fill_classes()
{
	$r = designer_video_item_test_run(array(
		'_sdVideoFillClass("d-block w-100 h-auto", true)',
		'_sdVideoFills(_sdVideoFillClass("d-block w-100 h-auto", true))',
		'_sdVideoFills("d-block w-100 h-auto")',
		'_sdVideoFillClass(_sdVideoFillClass("d-block w-100 h-auto", true), false)',
		'_sdVideoFillClass("rounded-3 shadow position-relative z-2 h-50 object-fit-contain", true)',
		'_sdVideoFillClass(_sdVideoFillClass("rounded-3", true), true)',
	));

	if ($r === null) {
		return;
	}

	pg_assert_same('d-block position-absolute top-0 start-0 w-100 h-100 object-fit-cover z-n1', $r[0], 'h-auto gives way to the fill');
	pg_assert_same(true, $r[1], 'a filled video reads as filling');
	pg_assert_same(false, $r[2], 'the palette item does not fill');
	pg_assert_same('d-block w-100 h-auto', $r[3], 'turning the fill off gives back the palette size');
	pg_assert_same('rounded-3 shadow position-absolute top-0 start-0 w-100 h-100 object-fit-cover z-n1', $r[4], 'a class of the same family gives way, the others stay');
	pg_assert_same('rounded-3 position-absolute top-0 start-0 w-100 h-100 object-fit-cover z-n1', $r[5], 'turning the fill on twice changes nothing');
}

function test_designer_video_item_fill_host_classes()
{
	$r = designer_video_item_test_run(array(
		'_sdVideoFillHostClass("d-flex overflow-hidden pg-screen pg-bleed")',
		'_sdVideoFillHostClass("position-absolute z-3")',
		'_sdVideoFillHostClass("position-static p-5")',
		'_sdVideoFillHostClass("sticky-top")',
		'_sdVideoFillHostClass(_sdVideoFillHostClass("d-flex"))',
		'_sdVideoFillHostClass("")',
	));

	if ($r === null) {
		return;
	}

	pg_assert_same('d-flex overflow-hidden pg-screen pg-bleed position-relative z-0', $r[0], 'the hero gets a place and a layer');
	pg_assert_same('position-absolute z-3', $r[1], 'a placed box with a layer keeps its classes');
	pg_assert_same('p-5 position-relative z-0', $r[2], 'position-static gives way');
	pg_assert_same('sticky-top z-0', $r[3], 'a sticky box is placed already');
	pg_assert_same('d-flex position-relative z-0', $r[4], 'the box classes are added once');
	pg_assert_same('position-relative z-0', $r[5], 'a box without classes gets both');
}

function test_designer_video_item_panel()
{
	$r = designer_video_item_test_run(array(
		'propsVideoOptions(video("d-block w-100 h-auto", [{ name: "controls", value: "" }, { name: "preload", value: "metadata" }]))',
		'propsVideoOptions(video(_sdVideoFillClass("d-block w-100 h-auto", true), []))',
		'propsVideoOptions(video("d-block", [], { src: "film", poster: "still" }))',
	));

	if ($r === null) {
		return;
	}

	$plain = $r[0];
	pg_assert_true(strpos($plain, "window.sdOpenFilePicker('sd-prop-video-src','src','file')") !== false, 'the file comes from the Files tab');
	pg_assert_true(strpos($plain, "window.sdOpenImagePicker('sd-prop-video-poster')") !== false, 'the poster comes from the image picker');
	pg_assert_true(strpos($plain, 'data-form-bool-attr="controls" checked') !== false, 'the Controls switch shows the attribute');
	pg_assert_true(strpos($plain, '<option value="metadata" selected>') !== false, 'the preload select shows the attribute');
	pg_assert_true(strpos($plain, 'data-video-fill>') !== false, 'the fill switch is off');
	pg_assert_true(stripos($plain, 'autoplay') === false, 'the panel has no autoplay row');

	pg_assert_true(strpos($r[1], 'data-form-bool-attr="controls"') === false, 'a filling video has no Controls row');
	pg_assert_true(strpos($r[1], 'data-video-fill checked') !== false, 'the fill switch is on');

	pg_assert_true(strpos($r[2], 'sd-prop-video-src') === false, 'a bound file has no address field');
	pg_assert_true(strpos($r[2], 'sd-prop-video-poster') === false, 'a bound poster has no address field');
	pg_assert_same(2, substr_count($r[2], 'From a record field'), 'both bound rows say where the value comes from');
}

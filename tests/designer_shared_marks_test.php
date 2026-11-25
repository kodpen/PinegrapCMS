<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How shared components and system widgets are marked on the visual
 * designer's canvas (assets/js/style_designer.src.js): nothing at rest, a
 * frame in the part's colour under the pointer and when chosen, the name tag
 * "Header · Shared" / "Cart · System", and the View menu's "Shared parts"
 * switch with its command palette row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_shared_marks_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_shared_marks_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// The canvas stylesheet's rules for shared components and system widgets.
function designer_shared_marks_css($js)
{
	$start = strpos($js, "\n    var IFRAME_CSS = [");
	$end = ($start === false) ? false : strpos($js, "\n    ];", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start);
}

function test_designer_shared_marks_nothing_at_rest()
{
	$css = designer_shared_marks_css(designer_shared_marks_source());

	pg_assert_true($css !== '', 'style_designer.js has the canvas stylesheet');
	pg_assert_true(strpos($css, '3px rgba(16,185,129') === false, 'the 3px green ring of a shared component is gone');
	pg_assert_true(strpos($css, 'border:2px solid rgba(99,102,241') === false, 'the 2px indigo border of a system widget is gone');
	pg_assert_contains(".sd-wrap.sd-shared-wrap { --sd-part:16,185,129; position:relative !important; box-shadow:none !important; background:none !important; outline:0 solid transparent;", $css, 'at rest a shared component draws nothing');
	pg_assert_contains(".sd-wrap.sd-system-wrap { --sd-part:99,102,241; }", $css, 'a system widget only takes its colour');
	pg_assert_true(strpos($css, '.sd-shared-tb-name') === false && strpos($css, '.sd-system-tb-name') === false, 'the second name badge is gone');
}

function test_designer_shared_marks_frames_take_no_room()
{
	$js = designer_shared_marks_source();
	$css = designer_shared_marks_css($js);

	foreach (array("outline:1px solid rgba(var(--sd-part),.6) !important; outline-offset:-1px", "outline:1.5px solid rgb(var(--sd-part)) !important; outline-offset:-1.5px", "outline:2px solid rgb(var(--sd-part)) !important; outline-offset:-2px") as $frame) {
		pg_assert_contains($frame, $css, 'a frame is an outline drawn inward: ' . $frame);
	}

	pg_assert_contains("[':hover:not(:has(.sd-shared-wrap:hover))']", $css, 'the innermost part under the pointer gets the 1.5px frame');
	pg_assert_contains("':has(.sd-wrap.sd-selected)'", $css, 'the line round a chosen element of a part stays');

	$rule = designer_shared_marks_fn($js, '_sdPartFrameRule');
	pg_assert_contains("' > :not(.sd-tb)'", $rule, 'a boxless wrapper puts the frame on its child');
	pg_assert_contains("'.btn-group', '.btn-group-vertical', '.input-group', '.nav'", $js, 'button groups, input groups and menus are boxless parents');
}

function test_designer_shared_marks_tag_words()
{
	$js = designer_shared_marks_source();

	pg_assert_true(strpos($js, "_sdT('[Shared] ')") === false && strpos($js, "_sdT('[System] ')") === false, 'no "[Shared] Header" / "[System] Cart" is left');

	$fn = designer_shared_marks_fn($js, '_sdPartTag');

	if (!pg_assert_true($fn !== '', 'style_designer.js has _sdPartTag()')) {
		return;
	}

	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgsm');
	file_put_contents($file, "function _sdT(s) { return s; }\n" . $fn . "\n"
		. "console.log(JSON.stringify([_sdPartTag('Header', false), _sdPartTag('Cart', true), _sdPartTag('', false)]));\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs');
	pg_assert_same(array(
		array('name' => 'Header', 'kind' => 'Shared'),
		array('name' => 'Cart', 'kind' => 'System'),
		array('name' => '?', 'kind' => 'Shared'),
	), json_decode(implode("\n", $out), true), 'the tag is the name and the kind');

	pg_assert_contains("_snTag.name + ' · ' + _snTag.kind", $js, 'the node label reads "Header · Shared"');
}

function test_designer_shared_marks_view_switch()
{
	$js = designer_shared_marks_source();
	$bar = designer_shared_marks_fn($js, 'createCanvasIframe');

	pg_assert_true((bool) preg_match('/id="sd-vb-outline".*?<\/button>\' \+\s*(\/\/[^\n]*\n\s*)*\'<button type="button" class="sd-vb-row" id="sd-vb-parts" role="menuitemcheckbox" aria-checked="false"><span class="bi bi-puzzle"><\/span><span class="sd-vb-row-lbl">\' \+ esc\(_sdT\(\'Shared parts\'\)\)/s', $bar), '"Shared parts" is a switch row second in the View menu, under Outline');
	pg_assert_contains("['sd-vb-outline', 'sd-vb-parts', ", designer_shared_marks_fn($js, '_sdCanvasBarSync'), 'the badge counts it');
	pg_assert_contains("=== '1'", designer_shared_marks_fn($js, '_sdPartsLit'), 'off unless this browser turned it on');
	pg_assert_contains("_sdPartsLit() ? _sdT('Hide shared parts') : _sdT('Show shared parts')", $js, 'the command palette shows or hides them');
	pg_assert_contains("'sd-parts-lit'", $js, 'the canvas body keeps the switch across draws');
	pg_assert_contains('body.sd-parts-lit .sd-wrap.sd-shared-wrap::after', designer_shared_marks_css($js), 'on, every part wears a wash and a line');
}

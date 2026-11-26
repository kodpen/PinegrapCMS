<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a design's page transition and the names that keep an element
 * across pages (includes/fn/design_variables.php): the choice made safe,
 * the @view-transition rules inside the no-preference block, the model's
 * "page" entry kept in the variables file and read back, and a design with
 * no transition written byte for byte as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_page_transition_clean()
{
	pg_assert_same(null, pg_design_page_transition_clean(null), 'nothing');
	pg_assert_same(null, pg_design_page_transition_clean(array('kind' => 'none')), 'None');
	pg_assert_same(null, pg_design_page_transition_clean(array('kind' => 'spin')), 'an unknown kind');
	pg_assert_same(array('kind' => 'fade'), pg_design_page_transition_clean(array('kind' => 'fade', 'from' => 'left')), 'Fade has no side');
	pg_assert_same(array('kind' => 'slide', 'from' => 'right'), pg_design_page_transition_clean(array('kind' => 'slide')), 'Slide from the right by default');
	pg_assert_same(array('kind' => 'slide', 'from' => 'below', 'duration' => 1000, 'easing' => 'ease-in'),
		pg_design_page_transition_clean(array('kind' => 'Slide', 'from' => 'below', 'duration' => '4000', 'easing' => 'ease-in')), 'held to 1,000 ms');
	pg_assert_same(array('kind' => 'fade', 'duration' => 0), pg_design_page_transition_clean(array('kind' => 'fade', 'duration' => -5, 'easing' => 'bounce')), 'a refused easing is dropped');
	pg_assert_same(array('kind' => 'fade'), pg_design_page_transition_clean(array('kind' => 'fade', 'duration' => '')), 'an empty duration follows the look');
	pg_assert_same(array('kind' => 'fade', 'easing' => 'var(--pg-var-c-soft, cubic-bezier(0.22, 1, 0.36, 1))'),
		pg_design_page_transition_clean(array('kind' => 'fade', 'easing' => 'var(--pg-var-c-soft, cubic-bezier(.22,1,.36,1))')), 'a stored curve');
}

function test_design_page_transition_css()
{
	pg_assert_same('', pg_design_page_transition_css(null), 'None writes nothing');
	$fade = pg_design_page_transition_css(array('kind' => 'fade'));
	pg_assert_contains("@media (prefers-reduced-motion: no-preference) {\n    @view-transition {\n        navigation: auto;\n    }", $fade, 'opted in under no-preference only');
	pg_assert_contains('animation-duration: var(--pg-duration, 0.25s);', $fade, 'the look\'s duration');
	pg_assert_contains('animation-timing-function: var(--pg-ease, ease);', $fade, 'the look\'s easing');
	pg_assert_true(strpos($fade, '@keyframes') === false, 'Fade is the browser\'s own cross-fade');

	$slide = pg_design_page_transition_css(array('kind' => 'slide', 'from' => 'left', 'duration' => 400, 'easing' => 'ease-out'));
	pg_assert_contains('animation-duration: 400ms;', $slide, 'a duration');
	pg_assert_contains('animation-timing-function: ease-out;', $slide, 'an easing');
	pg_assert_contains('from { transform: translate(-100%, 0); }', $slide, 'the new page comes from the left');
	pg_assert_contains('to { transform: translate(100%, 0); }', $slide, 'the old page leaves to the right');
	$below = pg_design_page_transition_css(array('kind' => 'slide', 'from' => 'below'));
	pg_assert_contains('from { transform: translate(0, 100%); }', $below, 'from below');
	pg_assert_same(substr_count($slide, '{'), substr_count($slide, '}'), 'braces balanced');
}

function test_design_page_transition_in_the_variables_file()
{
	$defaults = pg_design_variables_defaults('', '');
	$plain = pg_design_variables_normalize($defaults, $defaults);
	pg_assert_true(!array_key_exists('page', $plain), 'no transition: no entry');
	$before = pg_design_variables_css($defaults, $defaults);
	pg_assert_true(strpos($before, 'view-transition') === false, 'no transition: nothing written');

	$model = $defaults;
	$model['page'] = array('kind' => 'slide', 'from' => 'right', 'duration' => 300);
	$css = pg_design_variables_css($model, $defaults);
	pg_assert_same(0, strpos($css, '/*! pg-theme '), 'the head first');
	pg_assert_contains('"page":{"kind":"slide","from":"right","duration":300}', $css, 'kept in the head');
	pg_assert_contains('@view-transition', $css, 'written');
	pg_assert_same(array('kind' => 'slide', 'from' => 'right', 'duration' => 300), pg_design_variables_read($css, $defaults)['page'], 'read back');
	pg_assert_same(array('kind' => 'slide', 'from' => 'right', 'duration' => 300), pg_design_variables_page($css), 'the stored transition of a file');
	pg_assert_same(null, pg_design_variables_page($before), 'none stored');

	$model['page'] = array('kind' => 'none');
	pg_assert_same($before, pg_design_variables_css($model, $defaults), 'back to None: byte for byte as before');
}

function test_design_page_transition_merge()
{
	$defaults = pg_design_variables_defaults('', '');
	$stored = $defaults;
	$stored['custom'] = array(array('key' => 'gap', 'name' => 'Gap', 'kind' => 'size', 'value' => '2rem'));
	$m = pg_design_variables_with_page($stored, array('page_only' => 1, 'page' => array('kind' => 'fade')));
	pg_assert_same(array('kind' => 'fade'), $m['page'], 'the page set');
	pg_assert_same($stored['custom'], $m['custom'], 'the stored variables kept');
	pg_assert_same(null, pg_design_variables_with_page(null, array('page_only' => 1, 'page' => array('kind' => 'none'))), 'no file and None: nothing to write');
	$m = pg_design_variables_with_page(null, array('page_only' => 1, 'page' => array('kind' => 'fade')));
	pg_assert_same(array('kind' => 'fade'), $m['page'], 'no file: a model made for the page alone');
	pg_assert_true(pg_design_variables_page_only(array('page_only' => 1, 'page' => array())), 'a page-only post');
	pg_assert_true(!pg_design_variables_page_only($stored), 'a whole model');
}

function test_design_view_transition_name_clean()
{
	$cases = array(
		'hero-picture'   => 'hero-picture',
		' Hero Picture ' => 'hero-picture',
		'page_title'     => 'page-title',
		'2col'           => 'n-2col',
		'none'           => '',
		'auto'           => '',
		'---'            => '',
		''               => '',
	);
	foreach ($cases as $in => $want) {
		pg_assert_same($want, pg_design_view_transition_name_clean($in), 'name: ' . $in);
	}
	pg_assert_same(40, strlen(pg_design_view_transition_name_clean(str_repeat('a', 60))), 'at most 40');
	pg_assert_same('', pg_design_view_transition_name_clean(array('x')), 'not a text');
}

// Runs $script under Node and returns its JSON output, null without Node.
function _test_page_transition_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgpt') . '.js';
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$json = json_decode(implode("\n", $out), true);
	pg_assert_true($code === 0 && $json !== null, 'node ran: ' . implode("\n", $out));
	return $json;
}

// The script's twins give what PHP gives, and a custom style body takes and
// loses its name with every other declaration kept.
function test_design_page_transition_script()
{
	$pages = array(
		null, array('kind' => 'none'), array('kind' => 'fade', 'from' => 'left'), array('kind' => 'slide'),
		array('kind' => 'Slide', 'from' => 'below', 'duration' => '4000', 'easing' => 'ease-in'),
		array('kind' => 'fade', 'duration' => -5, 'easing' => 'bounce'), array('kind' => 'fade', 'duration' => ''),
	);
	$names = array('hero-picture', ' Hero Picture ', 'page_title', '2col', 'none', 'auto', '---', '', str_repeat('a', 60));
	$defaults = pg_design_variables_defaults('', '');
	$model = $defaults;
	$model['page'] = array('kind' => 'slide', 'from' => 'left', 'duration' => 300);
	$file = pg_design_variables_css($model, $defaults);
	$js = 'var P = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_page_transition.js') . ');' . "\n"
		. 'var out = { clean: ' . json_encode($pages) . '.map(function (p) { return P.clean(p); }),' . "\n"
		. ' names: ' . json_encode($names) . '.map(P.nameClean),' . "\n"
		. ' file: P.fromCss(' . json_encode($file) . '), none: P.fromCss("/* x */"),' . "\n"
		. ' of: [P.nameOf("color: red;\nview-transition-name: Hero Pic;"), P.nameOf("view-transition-name:none"), P.nameOf("color: red;")],' . "\n"
		. ' add: P.withName("color: red;", "hero"), add2: P.withName("color: red", "hero"), add3: P.withName("", "hero"),' . "\n"
		. ' swap: P.withName("view-transition-name: old;\ncolor: red;", "new"), drop: P.withName("color: red;\nview-transition-name: old;", ""),' . "\n"
		. ' suggest: [P.suggest("picture", "hero", []), P.suggest("picture", "hero", ["hero-picture", "hero-picture-2"]), P.suggest("", "", [])] };' . "\n"
		. 'console.log(JSON.stringify(out));';
	$r = _test_page_transition_node($js);
	if ($r === null) {
		return;
	}
	foreach ($pages as $i => $p) {
		pg_assert_same(pg_design_page_transition_clean($p), $r['clean'][$i], 'clean twin #' . $i);
	}
	foreach ($names as $i => $n) {
		pg_assert_same(pg_design_view_transition_name_clean($n), $r['names'][$i], 'name twin: ' . $n);
	}
	pg_assert_same(array('kind' => 'slide', 'from' => 'left', 'duration' => 300), $r['file'], 'read from the file');
	pg_assert_same(null, $r['none'], 'no head');
	pg_assert_same(array('hero-pic', '', ''), $r['of'], 'the name a body holds');
	pg_assert_same("color: red;\nview-transition-name: hero;", $r['add'], 'added last');
	pg_assert_same("color: red;\nview-transition-name: hero;", $r['add2'], 'a missing semicolon added');
	pg_assert_same('view-transition-name: hero;', $r['add3'], 'an empty body');
	pg_assert_same("color: red;\nview-transition-name: new;", $r['swap'], 'replaced');
	pg_assert_same('color: red;', $r['drop'], 'removed');
	pg_assert_same(array('hero-picture', 'hero-picture-3', 'element'), $r['suggest'], 'suggested names');
}

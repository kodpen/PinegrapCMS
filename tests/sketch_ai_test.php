<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the two Pinegrap AI actions of the designer's Sketch tool
 * (includes/fn/sketch_ai.php): the answer read out of the model's words,
 * the boxes "Describe the page" draws and the texts "Write the texts" puts
 * in a section, the requests' limits, and the part's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/sketch_ai.php';

/**
 * The JSON object is read out of a think block, a code fence or words
 * around it; anything else is null.
 */
function test_sketch_ai_json()
{
	pg_assert_same(array('a' => 1), pg_sketch_ai_json('<think>hm {"x":2}</think>```json' . "\n" . '{"a":1}' . "\n```"), 'fence and think block');
	pg_assert_same(array('a' => 1), pg_sketch_ai_json('Here: {"a":1} done'), 'words around');
	pg_assert_same(null, pg_sketch_ai_json('no json'), 'no object');
	pg_assert_same(null, pg_sketch_ai_json('{"a":'), 'broken');
}

/**
 * Boxes are kept inside the 100-unit width, rounded, with a heading of at
 * most 80 characters; bad, empty and repeated boxes go, at most 40 stay.
 */
function test_sketch_ai_boxes()
{
	$boxes = pg_sketch_ai_boxes(array('boxes' => array(
		array('x' => 0, 'y' => 0, 'w' => 100, 'h' => 8.4, 'text' => "  Top \n bar "),
		array('x' => 90, 'y' => 10, 'w' => 30, 'h' => 20),
		array('x' => 'a', 'y' => 0, 'w' => 10, 'h' => 10),
		array('x' => 0, 'y' => 0, 'w' => 0, 'h' => 10),
		array('x' => 120, 'y' => 0, 'w' => 10, 'h' => 10),
		array('x' => 0, 'y' => 0, 'w' => 100, 'h' => 8.4),
		array('x' => -5, 'y' => -3, 'w' => 20, 'h' => 10, 'text' => str_repeat('x', 100)),
		'not a box',
	)));

	pg_assert_same(array(
		array('x' => 0.0, 'y' => 0.0, 'w' => 100.0, 'h' => 8.4, 'text' => 'Top bar'),
		array('x' => 90.0, 'y' => 10.0, 'w' => 10.0, 'h' => 20.0, 'text' => ''),
		array('x' => 0.0, 'y' => 0.0, 'w' => 15.0, 'h' => 7.0, 'text' => str_repeat('x', 80)),
	), $boxes, 'boxes tidied');

	$many = array();
	for ($i = 0; $i < 60; $i++) {
		$many[] = array('x' => 0, 'y' => $i * 10, 'w' => 50, 'h' => 8);
	}
	pg_assert_same(40, count(pg_sketch_ai_boxes(array('boxes' => $many))), 'at most 40');
	pg_assert_same(array(), pg_sketch_ai_boxes(null), 'no answer');
}

/**
 * A section's texts come back one for each sample text, in its order, tidy
 * and cut to the kind's length; a wrong count is no answer.
 */
function test_sketch_ai_texts()
{
	$items = array(array('type' => 'heading', 'text' => 'Your heading'), array('type' => 'paragraph', 'text' => 'Sample'), array('type' => 'link', 'text' => 'Go'));
	pg_assert_same(array('Yoga classes', 'Calm hour after work.', 'Book a class'), pg_sketch_ai_texts(array('texts' => array('<b>Yoga</b> classes', " Calm hour \n after work. ", '**Book a class**')), $items), 'tidied');
	pg_assert_same(null, pg_sketch_ai_texts(array('texts' => array('One')), $items), 'too few');
	pg_assert_same(null, pg_sketch_ai_texts(array('texts' => array('a', '', 'c')), $items), 'an empty text');
	$long = pg_sketch_ai_texts(array('texts' => array(str_repeat('word ', 40), 'x', 'y')), $items);
	pg_assert_true(mb_strlen($long[0]) <= 80, 'a heading is cut');
}

/**
 * What a request may send: the words cut, a section's items checked and
 * limited, an unknown mode refused.
 */
function test_sketch_ai_input()
{
	$in = pg_sketch_ai_input(array('mode' => 'describe', 'words' => '  ' . str_repeat('a', 3000)));
	pg_assert_same(2000, mb_strlen($in['words']), 'words cut');
	pg_assert_true(pg_sketch_ai_input(array('mode' => 'describe', 'words' => ' '))['error'] !== '', 'empty words refused');
	pg_assert_true(pg_sketch_ai_input(array('mode' => 'other'))['error'] !== '', 'unknown mode');

	$in = pg_sketch_ai_input(array('mode' => 'texts', 'kind' => 'cards', 'heading' => 'Classes', 'items' => array(
		array('type' => 'heading', 'text' => '<b>Card</b>'), array('type' => 'script', 'text' => 'x'), array('type' => 'paragraph', 'text' => ''),
	), 'page' => array('name' => 'Classes', 'title' => str_repeat('t', 400))));
	pg_assert_same('', $in['error'], 'texts accepted');
	pg_assert_same(array(array('type' => 'heading', 'text' => 'Card')), $in['items'], 'only text kinds with words');
	pg_assert_same(255, mb_strlen($in['page']['title']), 'title cut');
	pg_assert_true(pg_sketch_ai_input(array('mode' => 'texts', 'items' => array()))['error'] !== '', 'no items refused');
}

/**
 * The prompts carry the language and treat the person's words as data.
 */
function test_sketch_ai_messages()
{
	$m = pg_sketch_ai_messages(array('mode' => 'describe', 'words' => 'A yoga studio'), 'Turkish');
	pg_assert_same('system', $m[0]['role'], 'system first');
	pg_assert_true(strpos($m[0]['content'], 'Turkish') !== false, 'language named');
	pg_assert_true(strpos($m[1]['content'], 'A yoga studio') !== false, 'words as the user message');
	$m = pg_sketch_ai_messages(array('mode' => 'texts', 'kind' => 'cards', 'heading' => '', 'items' => array(array('type' => 'heading', 'text' => 'H')), 'page' => array('name' => 'P', 'title' => '', 'description' => '')), 'English');
	pg_assert_true(strpos($m[0]['content'], 'exactly 1 ') !== false, 'count asked');
}

/**
 * The endpoint is wired, cached as read-only for designers, and the part
 * and its rows exist.
 */
function test_sketch_ai_wiring()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_true(strpos($panel, "case 'sketch_ai':") !== false, 'the endpoint');
	$part = include PG_FUNCTIONS_DIR . '/includes/designer_parts/sketchai.php';
	pg_assert_same(array('assets/js/style_designer_sketchai.js'), $part['sources'], 'the part');
	pg_assert_same('designer', $part['when'], 'designers only');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($core, "_sdT('Describe the page')") !== false, 'the row');
	pg_assert_true(strpos($core, "_sdT('Write the texts')") !== false, 'the second row');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'sketchAi'") !== false, 'the switch handed to the editor');
}

/**
 * The part's pure functions in Node: the server's boxes made frame units,
 * a section's sample texts, the built page's sample and edited sections.
 */
function test_sketch_ai_part_pure()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$script = "var part; var StyleDesigner = { definePart: function (n, f) { part = f({ _sdT: function (s) { return s; }, esc: function (s) { return s; }, sdToast: function () {} }); } };\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_sketchai.js') . "\n" . <<<'JS'
var P = part.pure;
console.log(JSON.stringify(P.boxes([{ x: 0, y: 0, w: 100, h: 8, text: 'Top' }, { x: 90, y: 10, w: 30, h: 20 }, { x: 0, y: 20, w: 1, h: 1 }], { w: 800, grid: 8, min: 24, text: 2, boxes: 40 })));
var sec = { type: 'semantic', props: {}, children: [
    { type: 'content', props: { contentType: 'heading', text: 'Your &amp; <b>heading</b>' }, children: [] },
    { type: 'content', props: { contentType: 'image', text: 'x' }, children: [] },
    { type: 'shared_ref', props: {}, children: [{ type: 'content', props: { contentType: 'paragraph', text: 'no' } }] },
    { type: 'content', props: { contentType: 'link', text: '' } }
] };
console.log(JSON.stringify(P.items(sec).map(function (i) { return [i.type, i.text]; })));
var fp = function (n) { return n.fp; };
console.log(JSON.stringify(P.sections({ props: { _sketch: { secs: { 1: { fp: 'a' }, 2: { fp: 'b' }, 3: { fp: 'c' } } } }, children: [
    { type: 'shared_ref', props: { _sketchId: 1 }, fp: 'a' }, { type: 'semantic', props: { _sketchId: 2 }, fp: 'b' }, { type: 'semantic', props: { _sketchId: 3 }, fp: 'x' }, { type: 'semantic', props: {} }
] }, fp)));
JS;
	$tmp = tempnam(sys_get_temp_dir(), 'pgskai');
	file_put_contents($tmp, $script);
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 6)));
	pg_assert_same(array(
		array('x' => 0, 'y' => 0, 'w' => 800, 'h' => 64, 'text' => 'To'),
		array('x' => 720, 'y' => 80, 'w' => 80, 'h' => 160, 'text' => ''),
	), json_decode($out[0], true), 'boxes in frame units, inside the width, tiny ones dropped');
	pg_assert_same(array(array('heading', 'Your & heading')), json_decode($out[1], true), 'only text items with words, none inside a shared component');
	pg_assert_same(array('sample' => array('2'), 'edited' => 1), json_decode($out[2], true), 'shared parts left out, an edited section counted');
}

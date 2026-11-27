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

// Runs `$code` in Node with the part loaded on a small stand-in DOM (El)
// and a fake host for one built sketch page (frame 1, one section whose
// node is `sec`); `out` comes back decoded, null when Node cannot run. The
// code runs after `go()` (the part's texts()) and its answers settle.
function sketch_ai_test_node($answer, $code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = <<<'JS'
function El(tag, cls, text) {
    var self = this;
    this.tagName = String(tag).toUpperCase(); this.className = cls || ''; this.children = []; this.parentNode = null; this.attrs = {}; this.textContent = text || ''; this.innerHTML = '';
    var list = function () { return self.className.split(/\s+/).filter(Boolean); };
    this.classList = {
        contains: function (c) { return list().indexOf(c) !== -1; },
        add: function (c) { if (list().indexOf(c) === -1) self.className = (self.className + ' ' + c).trim(); },
        remove: function (c) { self.className = list().filter(function (x) { return x !== c; }).join(' '); },
        toggle: function (c, on) { if (on) this.add(c); else this.remove(c); }
    };
}
El.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); return c; };
El.prototype.insertBefore = function (c, ref) { c.parentNode = this; var i = ref ? this.children.indexOf(ref) : -1; if (i < 0) this.children.push(c); else this.children.splice(i, 0, c); return c; };
El.prototype.removeChild = function (c) { this.children = this.children.filter(function (x) { return x !== c; }); c.parentNode = null; return c; };
El.prototype.setAttribute = function (k, v) { this.attrs[k] = String(v); };
El.prototype.getAttribute = function (k) { return this.attrs[k] === undefined ? null : this.attrs[k]; };
El.prototype.addEventListener = function () {};
El.prototype.querySelectorAll = function (sel) {
    var cls = sel.replace(/^\./, ''), out = [];
    (function walk(e) { e.children.forEach(function (c) { if (c.classList.contains(cls)) out.push(c); walk(c); }); })(this);
    return out;
};
El.prototype.querySelector = function (sel) { return this.querySelectorAll(sel)[0] || null; };
var part; var StyleDesigner = { definePart: function (n, f) { part = f({ _sdT: function (s, v) { v = [].concat(v === undefined ? [] : v); return String(s).replace(/\{var:(\d+)\}/g, function (m, i) { return v[i - 1]; }); }, esc: function (s) { return String(s); }, sdToast: function () {} }); } };
JS;
	$script .= file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_sketchai.js') . "\n";
	$script .= 'var ANSWER = ' . json_encode($answer) . ";\n";
	$script .= <<<'JS'
var sec = { _id: 'sd_2', type: 'semantic', props: { _sketchId: 2 }, children: [{ _id: 'sd_3', type: 'content', props: { contentType: 'heading', text: 'Your heading here' }, children: [] }] };
var tab = { key: 't', tree: { _id: 'root', type: 'root', props: { _sketch: { uid: 'u', secs: {} } }, children: [sec] } };
var page = { id: 1, boxes: [], built: { uid: 'u' }, details: {} };
var calls = { step: 0, undo: 0 };
var host = {
    page: function () { return page; }, pageOf: function () { return tab; }, treeOf: function (p) { return p.tree; }, name: function () { return 'Home'; },
    fp: function (n) { return JSON.stringify(n); }, el: function (t, c, x) { return new El(t, c, x); },
    redraw: function () {}, announce: function () {}, drawPage: function () {},
    step: function () { calls.step++; }, undoPage: function () { calls.undo++; },
    post: function () { return Promise.resolve(ANSWER); }
};
tab.tree.props._sketch.secs[2] = { sig: 's', fp: host.fp(sec) };
// The frame as the core draws it: head with "More", the body.
function frame(extra) {
    var node = new El('div', 'sd-proto-node'), head = new El('div', 'sd-sketch-head'), body = new El('div', 'sd-sketch-body');
    head.appendChild(new El('button', 'sd-sketch-more'));
    node.appendChild(head); node.appendChild(body);
    (extra || []).forEach(function (e) { node.appendChild(e); });
    part.fill(1, node, head, body);
    return node;
}
function foots(node) { return node.querySelectorAll('.sd-sketch-foot').map(function (f) { var t = f.querySelector('.sd-sketch-foot-text'); return { cls: f.className, text: t ? t.innerHTML : '', acts: f.querySelectorAll('.sd-sketch-act').map(function (b) { return b.textContent; }) }; }); }
var out = null;
part.texts(host, 1);
setTimeout(function () {
JS;
	$script .= $code . "\nconsole.log(JSON.stringify(out));\n}, 20);\n";

	$file = @tempnam(sys_get_temp_dir(), 'pgskai');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

/**
 * The texts' line with its "Undo" goes once the built page changed after
 * the texts were written: its Undo would take back that later edit.
 */
function test_sketch_ai_texts_undo_goes_with_a_later_edit()
{
	$out = sketch_ai_test_node(array('status' => 'success', 'texts' => array('Fresh bread every morning')), <<<'JS'
var before = foots(frame());
tab.tree.children.push({ _id: 'sd_9', type: 'semantic', props: { customName: 'added later' }, children: [] });
var after = foots(frame());
out = [before.length, before[0] ? before[0].acts : null, after.length];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(1, array('Undo'), 0), $out, 'the line with Undo while the page is as written, gone after another edit');
}

/**
 * A line of the AI actions never hides the "Read the picture" proposal's
 * foot with its "Keep" and "Discard", nor draws a second foot beside it.
 */
function test_sketch_ai_line_leaves_the_picture_proposal()
{
	$out = sketch_ai_test_node(array('status' => 'success', 'texts' => array('Fresh bread every morning')), <<<'JS'
var read = new El('div', 'sd-sketch-foot sd-sketch-read-foot');
out = foots(frame([read])).map(function (f) { return f.cls; });
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('sd-sketch-foot sd-sketch-read-foot'), $out, 'the proposal\'s foot alone');
	$read = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_sketchread.js');
	pg_assert_contains("host.el('div', 'sd-sketch-foot sd-sketch-read-foot')", $read, 'the proposal\'s foot is marked');
}

/**
 * A refused request says why: the server's message (the day's drafts used,
 * asked too quickly) leads the line; the plain failure is said once.
 */
function test_sketch_ai_failure_says_the_servers_reason()
{
	$out = sketch_ai_test_node(array('status' => 'error', 'message' => 'This site has used its 20 drafts for today. Writing with AI is open again tomorrow.'), <<<'JS'
var f = foots(frame())[0];
out = [f.text, part.pure.why({ message: 'The texts could not be written.' }, 'The texts could not be written. 0 sections were written.'), part.pure.why(null, 'Plain.')];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'This site has used its 20 drafts for today. Writing with AI is open again tomorrow. The texts could not be written. 0 sections were written, the rest keep their sample content.',
		'The texts could not be written. 0 sections were written.',
		'Plain.',
	), $out, 'the reason first, a generic message not said twice');
}

/**
 * The texts a section sends are the ones the server keeps: at most
 * PG_SKETCH_AI_ITEMS, none that is only Markdown marks; else the answer's
 * count never matches and the section always fails.
 */
function test_sketch_ai_items_match_what_the_server_keeps()
{
	$out = sketch_ai_test_node(array('status' => 'success', 'texts' => array('x')), <<<'JS'
var big = { type: 'semantic', props: {}, children: [] };
for (var i = 0; i < 30; i++) big.children.push({ type: 'content', props: { contentType: 'paragraph', text: 'Card ' + i }, children: [] });
big.children.unshift({ type: 'content', props: { contentType: 'heading', text: '**' }, children: [] });
var items = part.pure.items(big);
out = [items.length, items[0].text];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(PG_SKETCH_AI_ITEMS, 'Card 0'), $out, 'capped at the server\'s limit, a marks-only text left out');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sketch reader of the designer's Prototype view
 * (assets/js/pg_sketch_reader.js, window.PGSketchReader): boxes drawn in a
 * frame in, the page's sections out, in order from the top, each with its
 * kind and an unsure mark where the rules cannot decide. Runs in Node;
 * skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGSketchReader loaded (as S); its `out`
// comes back decoded. Null when Node cannot run.
function sketch_reader_test_node($code)
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

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_sketch_reader.js') . "\n"
		. "var S = window.PGSketchReader, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsketch');

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

// Reads a frame and gives each section back as kind, box ids and unsure.
function sketch_reader_test_read($frame, $boxes, $full = false)
{
	$result = sketch_reader_test_node('out = S.read('
		. json_encode($frame, JSON_UNESCAPED_UNICODE) . ', '
		. json_encode($boxes, JSON_UNESCAPED_UNICODE) . ');');

	if ($result === null || $full) {
		return $result;
	}

	$sections = array();

	foreach ($result['sections'] as $section) {
		$sections[] = array($section['kind'], $section['boxes'], $section['unsure']);
	}

	return $sections;
}

function sketch_reader_test_box($id, $x, $y, $w, $h, $more = array())
{
	return array_merge(array('id' => $id, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h), $more);
}

// A landing page drawn the usual way: a bar on top, a tall first band with a
// button, three equal plain boxes, three equal boxes each with a picture and
// lines, four equal crosses, a band of lines, a bar at the bottom. Given out
// of order: the sections still come from the top.
function test_sketch_reader_reads_a_landing_page()
{
	$b = 'sketch_reader_test_box';
	$boxes = array(
		$b('foot', 0, 2250, 1200, 150),
		$b('head', 0, 0, 1200, 80),
		$b('hero', 0, 100, 1200, 500),
		$b('hero-text', 100, 200, 500, 150, array('mark' => 'lines', 'parent' => 'hero')),
		$b('hero-btn', 100, 400, 200, 60, array('parent' => 'hero')),
		$b('f1', 20, 650, 360, 250),
		$b('f2', 420, 650, 360, 250),
		$b('f3', 820, 650, 360, 250),
		$b('c1', 20, 950, 360, 400),
		$b('c1-pic', 40, 970, 320, 200, array('mark' => 'cross', 'parent' => 'c1')),
		$b('c1-txt', 40, 1190, 320, 120, array('mark' => 'lines', 'parent' => 'c1')),
		$b('c2', 420, 950, 360, 400),
		$b('c2-pic', 440, 970, 320, 200, array('mark' => 'cross', 'parent' => 'c2')),
		$b('c2-txt', 440, 1190, 320, 120, array('mark' => 'lines', 'parent' => 'c2')),
		$b('c3', 820, 950, 360, 400),
		$b('c3-pic', 840, 970, 320, 200, array('mark' => 'cross', 'parent' => 'c3')),
		$b('c3-txt', 840, 1190, 320, 120, array('mark' => 'lines', 'parent' => 'c3')),
		$b('g1', 20, 1400, 270, 270, array('mark' => 'cross')),
		$b('g2', 320, 1400, 270, 270, array('mark' => 'cross')),
		$b('g3', 620, 1400, 270, 270, array('mark' => 'cross')),
		$b('g4', 910, 1400, 270, 270, array('mark' => 'cross')),
		$b('about', 100, 1750, 1000, 400, array('mark' => 'lines')),
	);

	$sections = sketch_reader_test_read(array('w' => 1200, 'h' => 2400), $boxes);

	if ($sections === null) {
		return;
	}

	pg_assert_same(array(
		array('header', array('head'), false),
		array('intro', array('hero'), false),
		array('features', array('f1', 'f2', 'f3'), false),
		array('cards', array('c1', 'c2', 'c3'), false),
		array('gallery', array('g1', 'g2', 'g3', 'g4'), false),
		array('text', array('about'), false),
		array('footer', array('foot'), false),
	), $sections, 'the sections of a landing page');
}

// Children may be given by place alone: a box inside another is its child.
// The section carries its buttons, its columns, its heading and its id (the
// first box's), so a later build can keep what it already made.
function test_sketch_reader_children_by_place_and_section_fields()
{
	$b = 'sketch_reader_test_box';
	$result = sketch_reader_test_read(array('x' => 500, 'y' => 300, 'w' => 1200, 'h' => 1600), array(
		$b('hero', 500, 300, 1200, 600, array('text' => '  Fresh bread  ')),
		$b('btn', 600, 700, 200, 60),
		$b('pic', 1100, 400, 500, 400, array('mark' => 'cross')),
	), true);

	if ($result === null) {
		return;
	}

	$section = $result['sections'][0];
	pg_assert_same(1, count($result['sections']), 'one section: the others are its children');
	pg_assert_same('intro', $section['kind'], 'a tall first band is the intro');
	pg_assert_same('hero', $section['id'], 'the id is the first box');
	pg_assert_same(array('btn'), $section['buttons'], 'the small box is its button');
	pg_assert_same('Fresh bread', $section['heading'], 'the typed word is its heading');
	pg_assert_same(1, $section['columns'], 'one column');
	pg_assert_same(true, $section['picture'], 'it holds a picture');
}

// A typed word names a kind in English or Turkish, any case, any accent; a
// system widget gets its widget. A word that names no kind is a heading only.
function test_sketch_reader_typed_words()
{
	$b = 'sketch_reader_test_box';
	$result = sketch_reader_test_read(array('w' => 1200, 'h' => 3000), array(
		$b('a', 0, 200, 1200, 400, array('text' => 'Our Products')),
		$b('b', 0, 700, 1200, 400, array('text' => 'BLOG')),
		$b('c', 0, 1200, 1200, 400, array('text' => 'Randevu al')),
		$b('d', 0, 1700, 1200, 400, array('text' => 'İLETİŞİM')),
		$b('e', 0, 2200, 1200, 300, array('text' => 'Lorem', 'mark' => 'lines')),
	), true);

	if ($result === null) {
		return;
	}

	$kinds = array();

	foreach ($result['sections'] as $section) {
		$kinds[] = array($section['kind'], $section['widget'], $section['unsure']);
	}

	pg_assert_same(array(
		array('catalog', 'catalog', false),
		array('blog', 'blog', false),
		array('booking', 'booking', false),
		array('form', null, false),
		array('text', null, false),
	), $kinds, 'kinds and widgets from typed words');
}

// A kind chosen from the chip's menu wins over every rule and is sure.
function test_sketch_reader_chosen_kind_wins()
{
	$b = 'sketch_reader_test_box';
	$sections = sketch_reader_test_read(array('w' => 1200, 'h' => 1200), array(
		$b('a', 0, 0, 1200, 80, array('kind' => 'gallery')),
		$b('b', 0, 300, 1200, 300, array('kind' => 'nonsense', 'mark' => 'cross')),
	));

	if ($sections === null) {
		return;
	}

	pg_assert_same(array(
		array('gallery', array('a'), false),
		array('picture', array('b'), false),
	), $sections, 'a chosen kind wins; an unknown one is ignored');
}

// A small box on its own is a button: a lone one makes a call to action; a
// thin top band of small boxes (a logo and menu items) is the header, at the
// bottom the footer; a band with a big box keeps its small ones as buttons.
function test_sketch_reader_small_boxes()
{
	$b = 'sketch_reader_test_box';
	$result = sketch_reader_test_read(array('w' => 1200, 'h' => 2000), array(
		$b('logo', 20, 20, 150, 50),
		$b('m1', 700, 25, 100, 40),
		$b('m2', 820, 25, 100, 40),
		$b('m3', 940, 25, 100, 40),
		$b('txt', 0, 700, 800, 300, array('mark' => 'lines')),
		$b('side-btn', 900, 800, 200, 60),
		$b('go', 500, 1300, 200, 60),
		$b('f1', 20, 1900, 150, 50),
		$b('f2', 900, 1900, 150, 50),
	), true);

	if ($result === null) {
		return;
	}

	$sections = array();

	foreach ($result['sections'] as $section) {
		$sections[] = array($section['kind'], $section['boxes'], $section['buttons']);
	}

	pg_assert_same(array(
		array('header', array('logo', 'm1', 'm2', 'm3'), array()),
		array('text', array('txt'), array('side-btn')),
		array('cta', array('go'), array()),
		array('footer', array('f1', 'f2'), array()),
	), $sections, 'small boxes as header, button, call to action and footer');
}

// Thin stacked fields with a button make a form; a box whose children are an
// equal row is read by that row; a cross is a picture, lines are text.
function test_sketch_reader_form_container_picture()
{
	$b = 'sketch_reader_test_box';
	$sections = sketch_reader_test_read(array('w' => 1200, 'h' => 2600), array(
		$b('top', 0, 0, 1200, 500, array('mark' => 'cross')),
		$b('form', 300, 600, 600, 500),
		$b('in1', 330, 640, 540, 60),
		$b('in2', 330, 730, 540, 60),
		$b('in3', 330, 820, 540, 120),
		$b('send', 330, 980, 160, 60),
		$b('wrap', 0, 1200, 1200, 400),
		$b('w1', 30, 1250, 350, 300),
		$b('w2', 425, 1250, 350, 300),
		$b('w3', 820, 1250, 350, 300),
		$b('pic', 100, 1700, 1000, 400, array('mark' => 'cross')),
		$b('txt', 100, 2150, 1000, 300, array('mark' => 'lines')),
	));

	if ($sections === null) {
		return;
	}

	pg_assert_same(array(
		array('intro', array('top'), false),
		array('form', array('form'), false),
		array('features', array('wrap'), false),
		array('picture', array('pic'), false),
		array('text', array('txt'), false),
	), $sections, 'form, a row inside a box, picture and text');
}

// Where the rules cannot decide, the section says so, with its best guess,
// the reason and the kinds to ask about; nothing is guessed in silence.
function test_sketch_reader_unsure()
{
	$b = 'sketch_reader_test_box';
	$result = sketch_reader_test_read(array('w' => 1200, 'h' => 2000), array(
		$b('head', 0, 0, 1200, 80),
		$b('hero', 0, 100, 1200, 500, array('mark' => 'cross')),
		$b('empty', 0, 700, 1200, 300),
		$b('w1', 0, 1100, 580, 300, array('text' => 'Blog')),
		$b('w2', 620, 1100, 580, 300, array('text' => 'Shop')),
		$b('x1', 0, 1500, 900, 300, array('mark' => 'lines')),
		$b('x2', 950, 1500, 100, 300),
		$b('x3', 1080, 1500, 120, 100, array('mark' => 'cross')),
	), true);

	if ($result === null) {
		return;
	}

	$sections = $result['sections'];
	pg_assert_same(5, count($sections), 'five sections');
	pg_assert_same(array(false, false), array($sections[0]['unsure'], $sections[1]['unsure']), 'header and intro are sure');
	pg_assert_same(array(true, 'empty', 'text'), array($sections[2]['unsure'], $sections[2]['reason'], $sections[2]['kind']), 'an empty box is unsure');
	pg_assert_true(count($sections[2]['options']) >= 2 && in_array('text', $sections[2]['options'], true), 'an empty box offers kinds');
	pg_assert_same(array(true, 'words'), array($sections[3]['unsure'], $sections[3]['reason']), 'words naming two kinds are unsure');
	pg_assert_same(array('blog', 'catalog'), array_values(array_intersect(array('blog', 'catalog'), $sections[3]['options'])), 'both named kinds are offered');
	pg_assert_same(array(true, 'mixed'), array($sections[4]['unsure'], $sections[4]['reason']), 'a row of unlike boxes is unsure');
	pg_assert_same(null, $sections[0]['reason'], 'a sure section has no reason');
	pg_assert_same(array(), $sections[0]['options'], 'a sure section offers nothing');
}

// Boxes outside the frame or too small to mean anything are skipped and
// listed; an empty frame reads as no sections; bad input does not throw.
function test_sketch_reader_skips_and_bad_input()
{
	$b = 'sketch_reader_test_box';
	$result = sketch_reader_test_read(array('w' => 1200, 'h' => 1000), array(
		$b('out', 1300, 100, 200, 200),
		$b('dot', 100, 100, 3, 3),
		$b('ok', 0, 300, 1200, 300, array('mark' => 'lines')),
		array('id' => 'broken', 'x' => 'a'),
	), true);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('out', 'dot', 'broken'), $result['skipped'], 'skipped boxes are listed');
	pg_assert_same(1, count($result['sections']), 'the good box is read');

	$empty = sketch_reader_test_node('out = [S.read({w: 1200, h: 800}, []), S.read(null, null)];');

	if ($empty === null) {
		return;
	}

	pg_assert_same(array(), $empty[0]['sections'], 'an empty frame has no sections');
	pg_assert_same(array(), $empty[1]['sections'], 'no input has no sections');
}

// The kinds the chip's menu offers, and words folded for matching.
function test_sketch_reader_kinds_and_fold()
{
	$result = sketch_reader_test_node('out = [S.KINDS, S.fold("  İletişim  FORMU "), S.wordKinds("Hakkımızda"), S.wordKinds("Products and blog")];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('header', 'intro', 'features', 'cards', 'text', 'picture', 'gallery', 'form', 'cta', 'footer', 'catalog', 'blog', 'booking'), $result[0], 'the kinds');
	pg_assert_same('iletisim formu', $result[1], 'folded: lower case, no accents, single spaces');
	pg_assert_same(array('text'), $result[2], 'a Turkish word');
	pg_assert_same(array('catalog', 'blog'), $result[3], 'two kinds in one phrase');
}

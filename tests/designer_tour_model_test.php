<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the model of the Visual Page Editor's first tour
 * (window.PGDesignerTour, assets/js/pg_designer_tour.js): a designer's five
 * stops and a content editor's three in their order, each on a control the
 * editor's screen has; Next and Back over the stops whose control is on the
 * screen, a control gone since the tour began skipped; what was offered and
 * seen kept per person and read back, garbage read as a first visit; the
 * card never offered twice, never over a dialog, a typed text or a drag;
 * the first-use tips and their switch. Run in Node; without Node those
 * cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGDesignerTour loaded as T; its `out`
// comes back decoded. Null without Node.
function designer_tour_test_node($code)
{
	$module = PG_FUNCTIONS_DIR . '/assets/js/pg_designer_tour.js';
	pg_assert_true(is_file($module), 'the tour model exists');

	if (!is_file($module) || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents($module) . "\n"
		. "var T = window.PGDesignerTour, out = null;\n"
		. "function on(list) { return function (sel) { return list.indexOf(sel) !== -1; }; }\n"
		. "var ALL = ['#sd-components', '#sd-properties', '#sd-canvas', '#sd-preview-menu-btn', '#sd-ajax-save', '#sd-publish-page', '#sd-status-shortcuts'];\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgtour');

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

function test_designer_tour_stops_per_access_in_order()
{
	$res = designer_tour_test_node(<<<'JS'
var a = T.stops('full');
a[0].anchors.push('#x');
out = {
    full: T.stops('full').map(function (s) { return s.id; }),
    content: T.stops('content').map(function (s) { return s.id; }),
    other: T.stops('manager').map(function (s) { return s.id; }),
    none: T.stops().map(function (s) { return s.id; }),
    copied: T.stops('full')[0].anchors,
    anchors: T.stops('full').concat(T.stops('content')).map(function (s) { return s.anchors; })
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('add', 'shape', 'see', 'publish', 'find'), $res['full'], 'a designer has five stops: add, shape, see, publish, find');
	pg_assert_same(array('change', 'edit', 'send'), $res['content'], 'a content editor has three stops of their own');
	pg_assert_same($res['full'], $res['other'], 'an unknown access is the full tour');
	pg_assert_same($res['full'], $res['none'], 'no access is the full tour');
	pg_assert_same(array('#sd-components'), $res['copied'], 'a caller changing a stop does not change the model');

	// Every anchor is an id the editor's screen or its status bar draws.
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php')
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	foreach ($res['anchors'] as $anchors) {
		pg_assert_true(count($anchors) > 0, 'each stop has a control');

		foreach ($anchors as $sel) {
			pg_assert_true(strpos($screen, 'id="' . substr($sel, 1) . '"') !== false, 'the editor draws ' . $sel);
		}
	}
}

function test_designer_tour_skips_a_stop_whose_control_is_not_on_the_screen()
{
	$res = designer_tour_test_node(<<<'JS'
var list = T.stops('full');
var all = on(ALL);
// No palette (a narrow screen), no Options panel: shape falls back to the canvas.
var some = on(['#sd-canvas', '#sd-preview-menu-btn', '#sd-publish-page', '#sd-status-shortcuts']);
var walk = function (has) {
    var ids = [], at = T.first(list, has);
    while (at) { ids.push(at.id + '@' + at.anchor); at = T.move(list, at.i, 1, has); }
    return ids;
};
// Preview went away after the tour reached shape.
var gone = T.move(list, 1, 1, on(['#sd-ajax-save', '#sd-status-shortcuts']));
out = {
    all: walk(all),
    some: walk(some),
    shown: T.shown(list, some).map(function (s) { return s.id + ':' + s.i; }),
    nothing: T.first(list, on([])),
    gone: gone && gone.id,
    back: T.move(list, 3, -1, on(['#sd-components', '#sd-ajax-save'])).id,
    backAtFirst: T.move(list, 0, -1, all).id,
    backNone: T.move(list, 0, -1, on([])),
    pastEnd: T.move(list, 4, 1, all),
    anchorNone: T.anchor(null, all)
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('add@#sd-components', 'shape@#sd-properties', 'see@#sd-preview-menu-btn', 'publish@#sd-ajax-save', 'find@#sd-status-shortcuts'), $res['all'], 'with every control on the screen the tour walks the five stops in order');
	pg_assert_same(array('shape@#sd-canvas', 'see@#sd-preview-menu-btn', 'publish@#sd-publish-page', 'find@#sd-status-shortcuts'), $res['some'], 'a stop with no control is skipped, one with a later control uses it');
	pg_assert_same(array('shape:1', 'see:2', 'publish:3', 'find:4'), $res['shown'], 'shown() keeps each stop\'s place');
	pg_assert_same(null, $res['nothing'], 'with no control on the screen there is no tour');
	pg_assert_same('publish', $res['gone'], 'Next skips a control gone since the tour began');
	pg_assert_same('add', $res['back'], 'Back skips the stops whose control is not there');
	pg_assert_same('add', $res['backAtFirst'], 'Back on the first stop stays there');
	pg_assert_same(null, $res['backNone'], 'Back with nothing left on the screen ends');
	pg_assert_same(null, $res['pastEnd'], 'Next on the last stop ends the tour');
	pg_assert_same('', $res['anchorNone'], 'no stop, no control');
}

function test_designer_tour_keeps_what_was_seen_per_person()
{
	$res = designer_tour_test_node(<<<'JS'
var s0 = T.read(null);
var s1 = T.offered(s0);
var s2 = T.seen(s1, 'content');
var back = T.read(T.write(s2));
out = {
    key: T.key(12), keyStr: T.key('7'), keyNone: T.key(0), keyBad: T.key('x'),
    first: s0,
    garbage: [T.read('{'), T.read('[1]'), T.read('"x"'), T.read(''), T.read(undefined)],
    unchanged: s0.offered,
    back: back,
    odd: T.read('{"offered":"yes","seen":{"full":1},"tips":{"a b":true,"spacing":true,"x":1},"tipsOff":true,"extra":5}')
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same('pg.designer.12.tour', $res['key'], 'kept per person in the browser, as the panel keeps its lists\' choices');
	pg_assert_same('pg.designer.7.tour', $res['keyStr'], 'a user id given as text');
	pg_assert_same('', $res['keyNone'], 'no person, no key');
	pg_assert_same('', $res['keyBad'], 'a bad id, no key');

	$blank = array('v' => 1, 'offered' => false, 'seen' => array('full' => false, 'content' => false), 'tips' => array(), 'tipsOff' => false);
	pg_assert_same($blank, $res['first'], 'nothing stored is a first visit');

	foreach ($res['garbage'] as $g) {
		pg_assert_same($blank, $g, 'an unreadable store is a first visit');
	}

	pg_assert_same(false, $res['unchanged'], 'the state given is not changed');
	pg_assert_same(array('v' => 1, 'offered' => true, 'seen' => array('full' => false, 'content' => true), 'tips' => array(), 'tipsOff' => false), $res['back'], 'what was seen is written and read back');
	pg_assert_same(array('v' => 1, 'offered' => false, 'seen' => array('full' => false, 'content' => false), 'tips' => array('spacing' => true), 'tipsOff' => true), $res['odd'], 'only true values and plain tip names are read');
}

function test_designer_tour_offers_the_card_once_and_never_in_the_way()
{
	$res = designer_tour_test_node(<<<'JS'
var s = T.read(null);
out = {
    fresh: T.offer(s, 'full'),
    dialog: T.offer(s, 'full', { dialog: true }),
    typing: T.offer(s, 'full', { typing: true }),
    dragging: T.offer(s, 'full', { dragging: true }),
    calm: T.offer(s, 'full', { dialog: false, typing: false, dragging: false }),
    afterOffer: T.offer(T.offered(s), 'full'),
    seenFull: T.offer(T.seen(s, 'full'), 'full'),
    seenContentOnly: T.offer({ seen: { content: true } }, 'full'),
    seenContent: T.offer({ seen: { content: true } }, 'content')
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['fresh'], 'a first visit is offered the card');
	pg_assert_true($res['calm'], 'nothing in the way, the card');
	pg_assert_true(!$res['dialog'], 'never over a dialog');
	pg_assert_true(!$res['typing'], 'never while a text is typed');
	pg_assert_true(!$res['dragging'], 'never while something is dragged');
	pg_assert_true(!$res['afterOffer'], 'offered once, whatever the answer');
	pg_assert_true(!$res['seenFull'], 'not after the tour was seen');
	pg_assert_true($res['seenContentOnly'], 'the content editor\'s tour seen leaves the designer\'s to offer');
	pg_assert_true(!$res['seenContent'], 'the content editor\'s tour seen is not offered again');
}

function test_designer_tour_first_use_tips()
{
	$res = designer_tour_test_node(<<<'JS'
var s = T.read(null);
var a = T.tipSeen(s, 'spacing');
var off = T.tipsOn(a, false);
out = {
    due: T.tipDue(s, 'spacing'),
    afterGotIt: T.tipDue(a, 'spacing'),
    other: T.tipDue(a, 'fonts'),
    off: T.tipDue(off, 'fonts'),
    onAgain: T.tipDue(T.tipsOn(off, true), 'fonts'),
    keptSeen: T.tipDue(T.tipsOn(off, true), 'spacing'),
    badName: T.tipDue(s, '__proto__ x'),
    badSeen: T.tipSeen(s, 'a b').tips
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['due'], 'a feature\'s first open shows its tip');
	pg_assert_true(!$res['afterGotIt'], 'never again after "Got it"');
	pg_assert_true($res['other'], 'another feature\'s tip is still due');
	pg_assert_true(!$res['off'], '"Tips" off turns them all off');
	pg_assert_true($res['onAgain'], 'on again, the tips not seen are due');
	pg_assert_true(!$res['keptSeen'], 'a tip seen stays seen');
	pg_assert_true(!$res['badName'], 'a name outside the plain letters is never due');
	pg_assert_same(array(), $res['badSeen'], 'and never stored');
}

function test_designer_tour_part_has_a_sentence_for_every_stop()
{
	$part = PG_FUNCTIONS_DIR . '/assets/js/style_designer_tour.js';
	pg_assert_true(is_file($part), 'the tour part exists');
	$src = (string) @file_get_contents($part);

	$got = designer_tour_test_node('out = T.stops("full").concat(T.stops("content")).map(function (s) { return s.id; });');

	if ($got === null) {
		return;
	}

	foreach ($got as $id) {
		pg_assert_true(
			(bool) preg_match("/case '" . preg_quote($id, '/') . "': return \\[_sdT\\('[^']+'\\), _sdT\\('[^']+'\\)\\];/", $src),
			"stop $id has its bold words and sentence in the part"
		);
	}

	// The part's first source is the model, so the part may use it.
	$def = include PG_FUNCTIONS_DIR . '/includes/designer_parts/tour.php';
	pg_assert_same('assets/js/pg_designer_tour.js', $def['sources'][0], 'the model loads first');
}

function test_designer_tour_how_do_i_tasks_per_access()
{
	$res = designer_tour_test_node(<<<'JS'
var a = T.tasks('full');
a[0].pointers[0].anchors.push('#x');
var sec = T.tasks('full')[0];
var has = on(['#sd-pal-sw-blocks', '#sd-canvas-iframe']);
var walk = [], at = T.first(sec.pointers, has);
while (at) { walk.push(at.id); at = T.move(sec.pointers, at.i, 1, has); }
out = {
    full: T.tasks('full').map(function (t) { return t.id; }),
    content: T.tasks('content').map(function (t) { return t.id; }),
    three: T.tasks('full').every(function (t) { return t.pointers.length === 3; }),
    ids: sec.pointers.map(function (p) { return p.id; }),
    copied: T.tasks('full')[0].pointers[0].anchors,
    walk: walk,
    selectors: T.tasks('full').reduce(function (all, t) {
        t.pointers.forEach(function (p) { all = all.concat(p.anchors); });
        return all;
    }, [])
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('section', 'text', 'colours', 'phone', 'page', 'list', 'form', 'shared', 'history', 'publish'), $res['full'], 'a designer has the ten tasks in the drawing\'s order');
	pg_assert_same(array('text', 'publish'), $res['content'], 'a content editor sees only the tasks they can do');
	pg_assert_true($res['three'], 'each task has three pointers');
	pg_assert_same(array('section.1', 'section.2', 'section.3'), $res['ids'], 'a pointer is named by its task and place');
	pg_assert_same(array('#sd-pal-sw-blocks'), $res['copied'], 'a caller changing a pointer does not change the model');
	pg_assert_same(array('section.1', 'section.3'), $res['walk'], 'a pointer whose control is not on the screen is skipped');

	// Every id and class a pointer names is drawn by the editor.
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php')
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	foreach ($res['selectors'] as $sel) {
		preg_match_all('/#([a-z0-9-]+)/', $sel, $ids);
		preg_match_all('/\.([a-z][a-z0-9-]+)/', $sel, $classes);
		preg_match_all('/\[([a-z-]+)="([a-z_]+)"\]/', $sel, $attrs, PREG_SET_ORDER);

		foreach ($ids[1] as $id) {
			// An id drawn whole, set on an element, or built from its prefix
			// and a key ('id="sd-pal-sw-' + k).
			$prefix = substr($id, 0, (int) strrpos($id, '-') + 1);
			pg_assert_true(
				strpos($screen, 'id="' . $id . '"') !== false || strpos($screen, ".id = '" . $id . "'") !== false
					|| strpos($screen, 'id="' . $prefix . "' + ") !== false,
				'the editor draws #' . $id . ' (' . $sel . ')'
			);
		}

		foreach ($classes[1] as $class) {
			pg_assert_true(strpos($screen, $class) !== false, 'the editor draws .' . $class . ' (' . $sel . ')');
		}

		foreach ($attrs as $a) {
			pg_assert_true(strpos($screen, $a[1]) !== false && strpos($screen, "'" . $a[2] . "'") !== false, 'the editor draws ' . $a[0]);
		}
	}
}

function test_designer_tour_empty_page_starts()
{
	$res = designer_tour_test_node(<<<'JS'
out = {
    all: T.starts('full', { section: true, sketch: true, document: true }),
    some: T.starts('full', { document: true, section: true }),
    none: T.starts('full'),
    truthy: T.starts('full', { section: 1, sketch: 'yes' }),
    content: T.starts('content', { section: true, sketch: true, document: true })
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('section', 'sketch', 'document'), $res['all'], 'the three starts in their order');
	pg_assert_same(array('section', 'document'), $res['some'], 'a start only when its feature is there');
	pg_assert_same(array(), $res['none'], 'no feature, no start');
	pg_assert_same(array(), $res['truthy'], 'only true says a feature is there');
	pg_assert_same(array(), $res['content'], 'a content editor sees no starts');
}

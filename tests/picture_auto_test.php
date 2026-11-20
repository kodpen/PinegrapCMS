<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's background draw of translated pictures
 * (assets/js/picture_auto.js): under Node, the lock one tab takes
 * (navigator.locks, else a lease in localStorage), the rows drawn one at a
 * time in the queue's order, the stop when the tab is hidden, a failed row
 * not tried again in the page view, and the model's plan of a waiting row;
 * in PHP, the footer's tag, printed only while the mark is set and the
 * user's queue is not empty; and the queue's drawing shared by the queue
 * view, the background draw and the Artifact Canvas after a Save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script after a script of assets/js loaded as $name (picture_auto.js
// as A unless named) and answers the JSON it prints; null without Node. The
// script goes through a file: on Windows the shell's quoting would turn the
// double quotes of an inline script into spaces.
function picture_auto_test_node($script, $module = 'picture_auto.js', $name = 'A')
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpicauto');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var ' . $name . ' = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/' . $module) . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Three waiting rows in the queue's order (picture name, language), and an
// idle wait that answers at once.
function picture_auto_test_rows()
{
	return 'var rows = [{ file_id: 1, language: "de" }, { file_id: 1, language: "fr" }, { file_id: 2, language: "de" }];'
		. "\n" . 'function idle() { return Promise.resolve(); }'
		. "\n" . 'function shown() { return true; }' . "\n";
}

// With navigator.locks one tab draws: a second tab asking while the first
// holds the lock does not run, and runs once the first is done.
function test_picture_auto_lock_with_navigator_locks()
{
	$r = picture_auto_test_node(<<<'JS'
function fakeLocks() {
    var held = false;
    return { request: function (name, opts, cb) {
        if (held || !opts || !opts.ifAvailable) {
            return Promise.resolve(cb(null));
        }
        held = true;
        return Promise.resolve().then(function () { return cb({ name: name }); }).then(function (v) { held = false; return v; }, function (e) { held = false; throw e; });
    } };
}
var locks = fakeLocks(), out = { names: [] }, release, gate = new Promise(function (r) { release = r; }), aRan = 0, bRan = 0;
var spy = { request: function (name, opts, cb) { out.names.push(name); return locks.request(name, opts, cb); } };
var a = A.withLock({ locks: spy }, function () { aRan++; return gate; });
A.withLock({ locks: spy }, function () { bRan++; }).then(function (b) {
    out.second = b;
    out.bWhileA = bRan;
    release();
    return a;
}).then(function (end) {
    out.first = end;
    out.aRan = aRan;
    return A.withLock({ locks: spy }, function () { bRan++; });
}).then(function (again) {
    out.after = again;
    out.bAfter = bRan;
    console.log(JSON.stringify(out));
});
JS
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'script loaded');
	pg_assert_same(false, $r['second'], 'a second tab is refused while the first draws');
	pg_assert_same(0, $r['bWhileA'], 'and its work does not run');
	pg_assert_same(true, $r['first'], 'the first tab ran its work');
	pg_assert_same(1, $r['aRan'], 'once');
	pg_assert_same(true, $r['after'], 'the lock is free once the first is done');
	pg_assert_same(1, $r['bAfter'], 'the second tab then draws');
	pg_assert_same(array('pg-picture-draw'), array_values(array_unique($r['names'])), 'one lock name for every tab');
}

// Without navigator.locks a lease in localStorage stands in: another tab's
// running lease refuses, an expired one is taken, only the holder drops it;
// the lease is renewed while the work runs and dropped after.
function test_picture_auto_lock_with_a_lease()
{
	$r = picture_auto_test_node(<<<'JS'
var store = { data: {},
    getItem: function (k) { return Object.prototype.hasOwnProperty.call(this.data, k) ? this.data[k] : null; },
    setItem: function (k, v) { this.data[k] = String(v); },
    removeItem: function (k) { delete this.data[k]; } };
var out = {};
out.aTakes = A.leaseTake(store, 'a', 1000, 60000);
out.bRefused = A.leaseTake(store, 'b', 2000, 60000);
out.aRenews = A.leaseTake(store, 'a', 3000, 60000);
A.leaseDrop(store, 'b');
out.kept = JSON.parse(store.getItem(A.LEASE)).id;
out.bAfterExpiry = A.leaseTake(store, 'b', 63001, 60000);
A.leaseDrop(store, 'b');
out.dropped = store.getItem(A.LEASE);
store.setItem(A.LEASE, 'not json');
out.brokenTaken = A.leaseTake(store, 'b', 1, 60000);
A.leaseDrop(store, 'b');
var timers = [], stopped = [];
function env(id) {
    return { storage: store, id: id, now: function () { return 5000; },
        every: function (fn, ms) { timers.push(ms); return id + '-timer'; }, stop: function (t) { stopped.push(t); } };
}
var release, gate = new Promise(function (r) { release = r; }), dRan = 0;
var c = A.withLock(env('c'), function () { return gate; });
A.withLock(env('d'), function () { dRan++; }).then(function (d) {
    out.dWhileC = d;
    out.dRan = dRan;
    out.holder = JSON.parse(store.getItem(A.LEASE)).id;
    release();
    return c;
}).then(function (end) {
    out.cEnd = end;
    out.timers = timers;
    out.stopped = stopped;
    out.left = store.getItem(A.LEASE);
    return A.withLock({ storage: null }, function () { out.alone = true; });
}).then(function (alone) {
    out.aloneEnd = alone;
    console.log(JSON.stringify(out));
});
JS
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'script loaded');
	pg_assert_same(true, $r['aTakes'], 'a free lease is taken');
	pg_assert_same(false, $r['bRefused'], 'another tab\'s running lease refuses');
	pg_assert_same(true, $r['aRenews'], 'the holder renews it');
	pg_assert_same('a', $r['kept'], 'only the holder drops it');
	pg_assert_same(true, $r['bAfterExpiry'], 'an expired lease is taken');
	pg_assert_same(null, $r['dropped'], 'dropped by its holder');
	pg_assert_same(true, $r['brokenTaken'], 'an unreadable lease counts as free');
	pg_assert_same(false, $r['dWhileC'], 'withLock: refused while another tab holds the lease');
	pg_assert_same(0, $r['dRan'], 'and the refused work does not run');
	pg_assert_same('c', $r['holder'], 'the first tab holds it');
	pg_assert_same(true, $r['cEnd'], 'the holder ran its work');
	pg_assert_same(array(20000), $r['timers'], 'renewed every third of its life while the work runs');
	pg_assert_same(array('c-timer'), $r['stopped'], 'the renewal stops with the work');
	pg_assert_same(null, $r['left'], 'and the lease is dropped');
	pg_assert_same(true, $r['alone'] ?? false, 'with no storage at all the tab draws on its own');
	pg_assert_same(true, $r['aloneEnd'], 'and says it ran');
}

// The rows are drawn in the queue's order, one at a time.
function test_picture_auto_order_one_at_a_time()
{
	$r = picture_auto_test_node(picture_auto_test_rows() . <<<'JS'
var order = [], inFlight = 0, most = 0, tried = {};
function draw(row) {
    inFlight++;
    most = Math.max(most, inFlight);
    order.push(A.rowKey(row));
    return new Promise(function (resolve) { setTimeout(function () { inFlight--; resolve({ status: 'drawn' }); }, 5); });
}
A.run(rows, tried, draw, shown, idle).then(function (n) {
    console.log(JSON.stringify({ n: n, order: order, most: most, tried: tried, next: A.nextRow(rows, tried) }));
});
JS
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'script loaded');
	pg_assert_same(3, $r['n'], 'every row drawn');
	pg_assert_same(array('1:de', '1:fr', '2:de'), $r['order'], 'in the queue\'s order');
	pg_assert_same(1, $r['most'], 'one at a time');
	pg_assert_same(array('1:de' => 'drawn', '1:fr' => 'drawn', '2:de' => 'drawn'), $r['tried'], 'each row\'s end kept for the page view');
	pg_assert_same(null, $r['next'], 'nothing left');
}

// Hidden: the run stops after the row being drawn and goes on from the next
// row once shown again; hidden while it waits for an idle moment, it draws
// nothing.
function test_picture_auto_stops_when_hidden()
{
	$r = picture_auto_test_node(picture_auto_test_rows() . <<<'JS'
var visible = true, calls = [], tried = {}, out = {};
function draw(row) {
    calls.push(A.rowKey(row));
    visible = false;
    return Promise.resolve({ status: 'drawn' });
}
A.run(rows, tried, draw, function () { return visible; }, idle).then(function (n) {
    out.first = n;
    out.firstCalls = calls.slice();
    visible = true;
    return A.run(rows, tried, function (row) { calls.push(A.rowKey(row)); return Promise.resolve({ status: 'drawn' }); }, function () { return visible; }, idle);
}).then(function (n) {
    out.second = n;
    out.calls = calls;
    var waits = {}, drew = 0;
    return A.run(rows, waits, function () { drew++; return Promise.resolve({ status: 'drawn' }); }, function () { return visible; }, function () { visible = false; return Promise.resolve(); }).then(function (n2) {
        out.idleHidden = n2;
        out.idleDrew = drew;
        out.idleTried = Object.keys(waits).length;
    });
}).then(function () {
    console.log(JSON.stringify(out));
});
JS
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'script loaded');
	pg_assert_same(1, $r['first'], 'the row being drawn ends, then the run stops');
	pg_assert_same(array('1:de'), $r['firstCalls'], 'nothing after it while hidden');
	pg_assert_same(2, $r['second'], 'shown again, it goes on');
	pg_assert_same(array('1:de', '1:fr', '2:de'), $r['calls'], 'from the next row, none twice');
	pg_assert_same(0, $r['idleHidden'], 'hidden while waiting for an idle moment: nothing drawn');
	pg_assert_same(0, $r['idleDrew'], 'the draw is not called');
	pg_assert_same(0, $r['idleTried'], 'and the row is still to be drawn');
}

// A failed row (a refusal or an error) is not tried again in that page view;
// the other rows go on.
function test_picture_auto_failed_row_not_tried_again()
{
	$r = picture_auto_test_node(picture_auto_test_rows() . <<<'JS'
var tried = {}, calls = [], out = {};
function draw(row) {
    calls.push(A.rowKey(row));
    if (row.file_id === 1 && row.language === 'de') {
        return Promise.reject(new Error('network'));
    }
    return Promise.resolve((row.file_id === 2) ? { status: 'failed', message: 'No' } : { status: 'look' });
}
A.run(rows, tried, draw, shown, idle).then(function (n) {
    out.n = n;
    out.tried = tried;
    return A.run(rows, tried, draw, shown, idle);
}).then(function (n) {
    out.again = n;
    out.calls = calls;
    out.next = A.nextRow(rows, tried);
    console.log(JSON.stringify(out));
});
JS
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'script loaded');
	pg_assert_same(3, $r['n'], 'a failed row does not stop the run');
	pg_assert_same(array('1:de' => 'failed', '1:fr' => 'look', '2:de' => 'failed'), $r['tried'], 'an error and a refusal both end failed');
	pg_assert_same(0, $r['again'], 'a second run in the page view draws nothing');
	pg_assert_same(array('1:de', '1:fr', '2:de'), $r['calls'], 'no row tried twice');
	pg_assert_same(null, $r['next'], 'nothing left to try');
}

// The model's plan of a waiting row, read again when it is drawn: one drawn
// meanwhile is not drawn again, one with a text no longer translated is
// skipped; an answer gives the row's end; after a Save the picture's rows
// that wait and may be drawn are drawn.
function test_picture_auto_row_plan()
{
	$r = picture_auto_test_node(<<<'JS'
console.log(JSON.stringify({
    plan: [
        M.languageRowPlan(null),
        M.languageRowPlan({ state: 'drawn', total: 2, translated: 2 }),
        M.languageRowPlan({ state: 'look', total: 2, translated: 2 }),
        M.languageRowPlan({ state: 'out_of_date', total: 2, translated: 1 }),
        M.languageRowPlan({ state: 'translated', total: 2, translated: 2 }),
        M.languageRowPlan({ state: 'out_of_date', total: 2, translated: 2 }),
        M.languageRowPlan({ state: 'translated', total: 2, translated: 2, rtl: true })
    ],
    end: [
        M.languageRowStatus({ status: 'success', state: 'drawn' }),
        M.languageRowStatus({ status: 'success', state: 'look' }),
        M.languageRowStatus({ status: 'error', message: 'No' }),
        M.languageRowStatus(null)
    ],
    saved: M.languagesWaiting([
        { code: 'de', queued: true, state: 'translated', total: 2, translated: 2 },
        { code: 'fr', queued: false, state: 'translated', total: 2, translated: 2 },
        { code: 'es', queued: true, state: 'out_of_date', total: 2, translated: 1 },
        { code: 'it', queued: true, state: 'out_of_date', total: 2, translated: 2 },
        { code: 'nl', queued: false, state: 'drawn', total: 2, translated: 2 },
        { code: 'ar', queued: true, state: 'translated', rtl: true, total: 2, translated: 2 }
    ]),
    none: M.languagesWaiting(null)
}));
JS
		, 'pg_image_editor_model.js', 'M');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(array('failed', 'drawn', 'look', 'skipped', 'draw', 'draw', 'skipped'), $r['plan'], 'a language gone fails, one drawn meanwhile keeps its state, an untranslated text skips, right to left skips');
	pg_assert_same(array('drawn', 'look', 'failed', 'failed'), $r['end'], 'an answer gives the row\'s end');
	pg_assert_same(array('de', 'it'), $r['saved'], 'after a Save: the rows that wait and may be drawn, none without a row');
	pg_assert_same(array(), $r['none'], 'no list, nothing');
}

// The footer's tag: no query while the mark is clear, nothing while the
// user's queue is empty, else the script with the rows (picture and language
// only) after the Artifact Canvas's loader.
function test_picture_auto_footer_tag()
{
	$asked = 0;
	$queue = function () use (&$asked) {
		$asked++;

		return array(
			array('file_id' => '4', 'name' => 'banner "sale".png', 'language' => 'de', 'label' => 'German', 'state' => 0),
			array('file_id' => 9, 'name' => 'b.png', 'language' => 'fr', 'label' => 'French', 'state' => 2));
	};
	$render = function ($rows) {
		return 'LOADER' . pg_picture_tr_auto_script('/pinegrap/assets/js/picture_auto.js?v=7', $rows);
	};

	pg_assert_same('', pg_picture_tr_auto_tag_with(function () { return false; }, $queue, $render), 'the mark clear: no tag');
	pg_assert_same(0, $asked, 'and no query');
	pg_assert_same('', pg_picture_tr_auto_tag_with(function () { return true; }, function () { return array(); }, $render), 'the user\'s queue empty: no tag');

	$tag = pg_picture_tr_auto_tag_with(function () { return true; }, $queue, $render);

	pg_assert_same(1, $asked, 'the queue read once');
	pg_assert_same(0, strpos($tag, 'LOADER'), 'the Artifact Canvas\'s loader first');
	pg_assert_contains('<script src="/pinegrap/assets/js/picture_auto.js?v=7" id="pg_picture_auto" data-rows="', $tag, 'the script\'s tag');
	pg_assert_contains(' defer></script>', $tag, 'run after the page');
	pg_assert_contains(h(json_encode(array(array('file_id' => 4, 'language' => 'de'), array('file_id' => 9, 'language' => 'fr')))), $tag, 'the rows: picture and language');
	pg_assert_false(strpos($tag, 'sale') !== false, 'no picture name in the page');

	// The panel's footer prints it, only for a signed-in person.
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$footer = substr($output, strpos($output, "\nfunction output_footer("), 4000);
	$gate = substr($output, strpos($output, "\nfunction pg_picture_tr_auto_tag("), 900);

	pg_assert_contains('pg_picture_tr_auto_tag()', substr($footer, 0, strpos($footer, "\nfunction ", 2)), 'the panel\'s footer prints the tag');
	pg_assert_contains('USER_LOGGED_IN', $gate, 'never for a visitor');
	pg_assert_contains("'pg_picture_tr_marked', 'pg_picture_tr_queue'", $gate, 'the mark first, then the user\'s queue');
	pg_assert_contains('pg_image_editor_includes()', $gate, 'with the Artifact Canvas\'s loader');
	pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php'), 'pg_picture_tr_auto_tag()', strpos($output, "\nfunction output_footer_secure(")) !== false, 'not in the secure footer of the site\'s pages');
}

// The queue's drawing is one part of the Artifact Canvas's bundle, loaded by
// the queue view, the background draw and the Artifact Canvas itself: no copy
// in either script.
function test_picture_auto_shared_draw()
{
	$queue = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/picture_queue.js');
	$auto = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/picture_auto.js');
	$artboard = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_artboard.js');

	pg_assert_contains('X.drawWaiting = function (fileId, code, cache)', $artboard, 'the queue\'s drawing in the bundle');
	pg_assert_contains('X.drawPicture = function (fileId)', $artboard, 'a picture\'s waiting rows, for the Artifact Canvas after a Save');
	pg_assert_contains('X.drawPicture(fileId)', $artboard, 'drawn once the save closes the editor');
	pg_assert_contains('parts.drawWaiting(', $queue, 'the queue view draws with it');
	pg_assert_contains('parts.drawWaiting(', $auto, 'the background draw too');

	foreach (array('picture_queue.js' => $queue, 'picture_auto.js' => $auto) as $name => $source) {
		pg_assert_false(strpos($source, 'drawLanguage(') !== false, $name . ': no drawing of its own');
		pg_assert_false(strpos($source, "'picture_languages'") !== false, $name . ': no read of the languages of its own');
	}

	pg_assert_contains("getElementById('pg_pq')", $auto, 'the queue view draws its rows itself');
	pg_assert_contains('pgImageEditor.load()', $auto, 'the bundle loaded only when a row is drawn');

	// The list says which languages have a row that waits.
	$answer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/picture_translation.php');
	$answer = substr($answer, strpos($answer, "\nfunction pg_picture_tr_languages_answer("), 4000);

	pg_assert_contains("'queued'", $answer, 'a language with a row waiting or out of date');
}

// The screens' words: the Pictures group's note, the queue view's lead and
// the Languages list's note say that a picture is drawn by itself, when, and
// that the button draws it now; a picture is listed from a page's save too.
function test_picture_auto_screen_words()
{
	require_once(__DIR__ . '/helpers/language_map.php');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');
	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	$map = pg_test_language_map('tr');
	$words = array(
		'group note' => array($screen, 'The texts of pictures made in the Artifact Canvas whose words are text layers. A picture is listed once a page that shows it is saved in the Visual Page Editor or opened in another language; a photo or an uploaded picture whose words are in its pixels is not. Once all of a language\'s texts are translated, its picture is drawn by itself: by an open panel, or, with nobody in the panel, by the server where it can. "Draw the waiting pictures" draws them now.'),
		'empty group' => array($screen, 'No picture yet. A picture made in the Artifact Canvas whose words are text layers is listed here once a page that shows it is saved in the Visual Page Editor or opened in another language.'),
		'queue lead' => array($screen, 'The pictures made in the Artifact Canvas whose texts are translated, drawn now in each language one after another in this browser. They are drawn by themselves without this page too: by an open panel, or, with nobody in the panel, by the server where it can. Keep this page open until the count reaches zero.'),
		'languages note' => array($editor, 'Each language\'s picture is drawn by itself from the last saved version once all its texts are translated, and shown on the pages in that language; "Draw" does it now.'),
	);

	foreach ($words as $place => $item) {
		pg_assert_contains("lang('" . addcslashes($item[1], "'\\") . "')", $item[0], $place . ': the words in the source');
		pg_assert_true(isset($map[$item[1]]) && ($map[$item[1]] !== ''), $place . ': the words in Turkish');
	}

	pg_assert_contains("'langNote'          => lang('Each language", $editor, 'the Languages list\'s note keeps its key');
	pg_assert_false(strpos($screen, 'once a page in another language has shown it') !== false, 'no place still says only a visit lists a picture');
}

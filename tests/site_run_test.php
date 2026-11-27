<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the run of the "Describe it" start (the designer part
 * 'siterun', assets/js/style_designer_siterun.js): the words a page is
 * drawn from, the header's and the footer's pages, the plan paired with the
 * sketch pages, where a page starts again, the progress, and in Node a run
 * against stand-in parts (the order of the steps, the header and footer made
 * once, Stop, a refusal and "Go on"); then the wiring of the part, the
 * start and the hooks of the sketch parts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Runs $code in Node after the part is defined (as `part`) with a stand-in
 * kit, a document holding only `body` and a localStorage; `out` comes back
 * decoded, null when Node cannot run (the test is skipped).
 *
 * @param string $code
 * @return mixed
 */
function site_run_test_node($code)
{
	$out = array();
	$status = 0;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		echo "  (skipped: node not available)\n";
		return null;
	}

	$src = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_siterun.js');
	$prelude = <<<'JS'
function El(tag, cls) { this.tagName = tag; this.className = cls || ''; this.children = []; this.parentNode = null; this.attrs = {}; this.style = {}; this.textContent = ''; this.handlers = {}; }
Object.defineProperty(El.prototype, 'innerHTML', { get: function () { return this._html || ''; }, set: function (v) { this._html = v; this.children = []; } });
El.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); return c; };
El.prototype.removeChild = function (c) { this.children = this.children.filter(function (x) { return x !== c; }); c.parentNode = null; };
El.prototype.setAttribute = function (k, v) { this.attrs[k] = v; };
El.prototype.addEventListener = function (k, f) { this.handlers[k] = f; };
El.prototype.querySelector = function () { return { style: {} }; };
var document = { body: new El('body') };
var store = {};
var window = { localStorage: { getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; }, setItem: function (k, v) { store[k] = String(v); }, removeItem: function (k) { delete store[k]; } } };
var part; var StyleDesigner = { definePart: function (n, f) { part = f({ _sdT: function (s, v) { v = [].concat(v === undefined ? [] : v); return String(s).replace(/\{var:(\d+)\}/g, function (m, i) { return v[i - 1]; }); }, esc: function (s) { return String(s); } }); } };
JS;
	$script = $prelude . "\n" . $src . "\nvar out;\n" . $code . "\nif (out !== undefined) console.log(JSON.stringify(out));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgsr') . '.js';
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	$json = json_decode(implode("\n", $out), true);
	pg_assert_true($status === 0 && $json !== null, 'Node ran the part: ' . implode("\n", $out));
	return $json;
}

/**
 * The pure parts: the words (the business cut first, at most 2,000), the
 * menus, the pairing by address, the step to start again and the percent.
 */
function test_site_run_pure()
{
	$out = site_run_test_node(<<<'JS'
var t = { page: 'Page', sections: 'Sections', header: 'Header', footer: 'Footer' };
var P = part.pure;
var page = { name: 'About us', purpose: 'Who we are.', sections: ['Our story', '  The team ', ''] };
var long = P.words(page, { name: 'Fish', what: new Array(800).join('word ') }, t);
out = {
	words: P.words(page, { name: 'Fish Co', what: 'We sell  fresh fish.' }, t),
	noBrief: P.words({ name: 'Home', sections: [] }, null, t),
	longLen: long.length <= 2000,
	longTail: long.slice(-60).indexOf('1. Our story; 2. The team; Footer') !== -1,
	longCut: long.indexOf('…') !== -1,
	menus: P.menus([{ name: 'Home', address: 'home', menu: 'main' }, { name: 'Privacy', address: 'privacy', menu: 'footer' }, { name: 'Thanks', address: 'thanks', menu: 'none' }]),
	pair: P.pair([{ address: 'home' }, { address: 'about' }, { address: 'gone' }], [{ id: 7, details: { address: 'about' } }, { id: 3, details: { address: 'home' } }, { id: 9, details: { address: 'other' } }]).map(function (x) { return [x.id, x.plan.address]; }),
	pairIndex: P.pair([{ address: 'a' }], [{ id: 1 }]).map(function (x) { return x.id; }),
	steps: [P.stepOf({ boxes: [] }, false), P.stepOf({ boxes: [1] }, false), P.stepOf({ boxes: [1] }, true)],
	pct: [P.percent(0, 4, 'draw'), P.percent(1, 4, 'build'), P.percent(3, 4, 'write'), P.percent(0, 0, 'draw')]
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same("Fish Co: We sell fresh fish.\nPage: About us. Who we are.\nSections: Header; 1. Our story; 2. The team; Footer", $out['words'], 'the words: the business, the page, its sections between header and footer');
	pg_assert_same("Page: Home\nSections: Header; Footer", $out['noBrief'], 'no brief, no purpose, no section');
	pg_assert_true($out['longLen'] && $out['longTail'] && $out['longCut'], 'a long business text is cut; the page\'s words stay whole');
	pg_assert_same(array('header' => array(array('name' => 'Home', 'address' => 'home')), 'footer' => array(array('name' => 'Home', 'address' => 'home'), array('name' => 'Privacy', 'address' => 'privacy'))), $out['menus'], 'the header: the main menu; the footer: main and footer');
	pg_assert_same(array(array(7, 'about'), array(3, 'home')), $out['pair'], 'paired by address in the sketch\'s order; an unknown address is left out');
	pg_assert_same(array(1), $out['pairIndex'], 'a sketch page without an address pairs by place');
	pg_assert_same(array('draw', 'build', 'write'), $out['steps'], 'no box: draw; drawn: build; built: write');
	pg_assert_same(array(0, 33, 92, 0), $out['pct'], 'the percent');
}

/**
 * A stand-in run of two pages: draw, build, write each; the plan taken
 * from the browser; the header and footer made once and placed again; the
 * plan's title and purpose in "Page details"; the end line.
 */
function test_site_run_whole()
{
	$out = site_run_test_node(<<<'JS'
var log = [];
var pages = [{ id: 1, name: 'Home', boxes: [], details: { address: 'home' } }, { id: 2, name: 'About', boxes: [], details: { address: 'about', title: 'Kept' } }];
var built = {};
var made = 0;
var sitesSeen = [];
var ai = {
	draw: function (h, f, words) { log.push('draw ' + f + ' ' + words.split('\n')[1]); h.page(f).boxes = [{ id: 1 }]; return Promise.resolve({ ok: true, boxes: 1 }); },
	write: function (h, f) { log.push('write ' + f); return Promise.resolve({ ok: true, written: 3 }); },
	stop: function () {}
};
var build = {
	auto: function (b, f) {
		log.push('build ' + f);
		sitesSeen.push(b.site());
		return b.shared('header', {}).then(function (hd) { return b.shared('footer', {}).then(function (ft) { log.push('refs ' + hd.props.sharedId + ' ' + ft.props.sharedId); var p = pages.filter(function (x) { return 'k:' + x.id === f; })[0]; p.built = { uid: 'u' + p.id }; built['u' + p.id] = true; return { ok: true }; }); });
	}
};
var host = {
	pages: function () { return pages; },
	page: function (f) { return pages.filter(function (p) { return 'k:' + p.id === f; })[0] || null; },
	name: function (p) { return p.name; },
	ai: function () { return { page: host.page, pageOf: function (uid) { return built[uid] ? {} : null; } }; },
	build: function () { return { site: function () { return { name: 'Site', path: '/', pages: [] }; }, shared: function (kind) { made++; log.push('make ' + kind); return Promise.resolve({ props: { sharedId: kind === 'header' ? 11 : 12, sharedName: kind } }); } }; },
	aiOn: function () { return true; },
	part: function (n) { return Promise.resolve(n === 'sketchai' ? ai : build); },
	ref: function (id, name) { return { props: { sharedId: id, sharedName: name } }; },
	zoom: function (f) { log.push('zoom ' + f); },
	el: function (tag, cls, text) { var e = new El(tag, cls); if (text) e.textContent = text; return e; },
	announce: function (t) { log.push('say ' + t); }
};
store.pg_site_plan = JSON.stringify({ brief: { name: 'Fish', what: 'Fresh fish daily.' }, pages: [{ name: 'Home', address: 'home', purpose: 'Welcome.', sections: ['Hero'], menu: 'main' }, { name: 'About', address: 'about', purpose: 'Our story.', sections: ['Story'], menu: 'footer' }] });
part.start(host);
setTimeout(function () {
	var card = document.body.children[0];
	out = { log: log, made: made, kept: store.pg_site_plan === undefined, details: pages.map(function (p) { return [p.details.title, p.details.description]; }), site: sitesSeen[0], card: card ? card.innerHTML.indexOf('Your site is made: 2 pages, as drafts.') !== -1 : false, running: part.running() };
	console.log(JSON.stringify(out));
}, 20);
JS
	. "\n");

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'zoom k:1', 'draw k:1 Page: Home. Welcome.', 'build k:1', 'make header', 'make footer', 'refs 11 12', 'write k:1',
		'zoom k:2', 'draw k:2 Page: About. Our story.', 'build k:2', 'refs 11 12', 'write k:2',
		'say Your site is made: 2 pages, as drafts.',
	), $out['log'], 'each page drawn, built and written in order; the header and footer made once');
	pg_assert_same(2, $out['made'], 'two shared rows made in all');
	pg_assert_true($out['kept'], 'the plan is taken from the browser');
	pg_assert_same(array(array('Home', 'Welcome.'), array('Kept', 'Our story.')), $out['details'], 'the plan\'s name and purpose fill empty page details');
	pg_assert_same(array('name' => 'Fish', 'path' => '/', 'pages' => array(array('name' => 'Home', 'address' => 'home')), 'footer' => array(array('name' => 'Home', 'address' => 'home'), array('name' => 'About', 'address' => 'about'))), $out['site'], 'the builder gets the brief\'s name and the plan\'s menus');
	pg_assert_true($out['card'] && !$out['running'], 'the end line');
}

/**
 * A refused request ends the run with its reason and keeps what is made;
 * "Try again" goes on from the page; Stop ends a run as stopped; no plan
 * or Pinegrap AI off makes nothing.
 */
function test_site_run_refused_stopped()
{
	$out = site_run_test_node(<<<'JS'
var log = [];
var pages = [{ id: 1, name: 'Home', boxes: [], details: { address: 'home' } }, { id: 2, name: 'About', boxes: [], details: { address: 'about' } }];
var refuse = true;
var stopWrite = null;
var ai = {
	draw: function (h, f) {
		log.push('draw ' + f);
		if (f === 'k:2' && refuse) return Promise.resolve({ ok: false, message: 'This site has used its drafts for today.' });
		h.page(f).boxes = [{ id: 1 }];
		return Promise.resolve({ ok: true });
	},
	write: function (h, f) { log.push('write ' + f); if (stopWrite) return new Promise(function (r) { stopWrite = r; }); return Promise.resolve({ ok: true }); },
	stop: function () { log.push('stop'); if (typeof stopWrite === 'function') stopWrite({ stopped: true, written: 1 }); }
};
var build = { auto: function (b, f) { log.push('build ' + f); var p = pages.filter(function (x) { return 'k:' + x.id === f; })[0]; p.built = { uid: 'u' }; return Promise.resolve({ ok: true }); } };
var host = {
	pages: function () { return pages; },
	page: function (f) { return pages.filter(function (p) { return 'k:' + p.id === f; })[0] || null; },
	name: function (p) { return p.name; },
	ai: function () { return { page: host.page, pageOf: function () { return null; } }; },
	build: function () { return { site: function () { return {}; }, shared: function () { return Promise.resolve(null); } }; },
	aiOn: function () { return true; },
	part: function (n) { return Promise.resolve(n === 'sketchai' ? ai : build); },
	ref: function () { return null; },
	zoom: function () {},
	el: function (tag, cls, text) { var e = new El(tag, cls); if (text) e.textContent = text; return e; },
	announce: function (t) { log.push('say ' + t); }
};
function acts(card) { var a = card.children.filter(function (c) { return c.className === 'sd-sr-acts'; })[0]; return a ? a.children : []; }
store.pg_site_plan = JSON.stringify({ brief: {}, pages: [{ name: 'Home', address: 'home', sections: ['A'], menu: 'main' }, { name: 'About', address: 'about', sections: ['B'], menu: 'main' }] });
part.start(host);
var res = {};
setTimeout(function () {
	var card = document.body.children[0];
	res.refused = { title: card.innerHTML.indexOf('This site has used its drafts for today.') !== -1, kept: card.innerHTML.indexOf('1 page is made; the others wait as sketches.') !== -1, acts: acts(card).map(function (b) { return b.textContent; }) };
	refuse = false;
	stopWrite = true;
	acts(card)[0].handlers.click({ stopPropagation: function () {} });
	setTimeout(function () {
		res.waiting = part.running();
		part.stop();
		setTimeout(function () {
			var c2 = document.body.children[0];
			res.stopped = { title: c2.innerHTML.indexOf('Stopped.') !== -1, acts: acts(c2).map(function (b) { return b.textContent; }) };
			res.log = log;
			// Nothing in the browser: nothing runs.
			var before = log.length;
			part.start(host);
			res.again = log.length === before;
			console.log(JSON.stringify(res));
		}, 10);
	}, 10);
}, 10);
JS
	. "\n");

	if ($out === null) {
		return;
	}

	pg_assert_same(array('title' => true, 'kept' => true, 'acts' => array('Try again', 'Close')), $out['refused'], 'a refusal: its reason, the pages made, Try again and Close');
	pg_assert_true($out['waiting'], 'Try again runs on from the refused page');
	pg_assert_same(array('title' => true, 'acts' => array('Go on', 'Close')), $out['stopped'], 'Stop: stopped, Go on and Close');
	pg_assert_same(array('draw k:1', 'build k:1', 'write k:1', 'draw k:2', 'say This site has used its drafts for today.', 'draw k:2', 'build k:2', 'write k:2', 'stop', 'say Stopped.'), $out['log'], 'the steps: refused at the second page, again from there, stopped while writing');
	pg_assert_true($out['again'], 'the run does not start twice');
}

/**
 * The wiring: the part's file, the start's flag, the core's hook and host,
 * the hooks of "Describe the page" / "Write the texts" and "Build the page".
 */
function test_site_run_wiring()
{
	$part = include PG_FUNCTIONS_DIR . '/includes/designer_parts/siterun.php';
	pg_assert_same(array('sources' => array('assets/js/style_designer_siterun.js'), 'when' => 'designer'), $part, 'the part: one file, designers only');

	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_system_style.php');
	pg_assert_true(strpos($add, "'site_run'       => (\$start === 'describe')") !== false, '?start=describe asks for the run');
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'siteRun'       => !empty(\$ctx['site_run'])") !== false, 'the editor is told');

	$core = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($core, "_sdPartCall('siterun', 'start', [_sdSiteRunHost()])") !== false, 'the core starts the part');
	pg_assert_true((bool) preg_match('/if \(item\) _sdProtoZoomTo\(item\.id\);\s*_sdSiteRun\(\);/', $core), 'once the sketch is shown');

	$ai = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_sketchai.js');
	pg_assert_true(strpos($ai, 'draw: function (h, frameId, text)') !== false && strpos($ai, 'write: function (h, frameId)') !== false, 'the AI part answers the run');
	$build = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_sketchbuild.js');
	pg_assert_true(strpos($build, 'auto: auto,') !== false, 'the build part builds without the list to check');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's Accessibility view (the part 'accessview',
 * assets/js/style_designer_accessview.js): a bundle of its own after the pure
 * reader, run when the View menu's switch goes on; what its card says per
 * kind of mark; the kept state of the switch; the marks drawn in the
 * editor's own layer, never in the canvas's page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One four-space function of a designer script, from its declaration to its
// closing brace.
function designer_a11y_view_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the named functions of `$file` and returns its
// JSON output. Null without Node or when a function is missing.
function designer_a11y_view_run($file, $names, $script)
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

	$js = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
	$src = 'var _sdT = function (s) { var a = arguments; return String(s).replace(/\{var:(\d)\}/g, function (m, i) { return a[i]; }); };' . "\n";

	foreach ($names as $name) {
		$fn = designer_a11y_view_fn($js, $name);

		if (!pg_assert_true($fn !== '', $file . ' has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgaccessview');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function test_designer_a11y_view_is_a_part_after_its_reader()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_accessview.min.js', isset($parts['accessview']) ? $parts['accessview'] : null, 'the part accessview');

	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_a11y_reader.js', 'assets/js/style_designer_accessview.js'), $bundles['assets/js/style_designer_accessview.min.js'], 'the reader runs first');
	pg_assert_false(in_array('assets/js/pg_a11y_reader.js', $bundles['assets/js/style_designer.min.js'], true), 'the reader is not in the core bundle');
	pg_assert_false(in_array('assets/js/style_designer_accessview.js', $bundles['assets/js/style_designer.min.js'], true), 'the view is not in the core bundle');
	pg_assert_same('always', pg_designer_part_files()['accessview']['when'], 'handed to every editor');
}

// Each kind's count, marked places and foot, as boards V1 to V4 and V6 say.
function test_designer_a11y_view_card_says_what_each_kind_found()
{
	$res = designer_a11y_view_run('style_designer_accessview.js', array('_avFirstSkip', '_avSummary'),
		'var stops = []; for (var i = 1; i <= 15; i++) stops.push({ index: i });' . "\n"
		. 'var page = { order: stops, jumps: stops.map(function (s, i) { return i === 1; }),' . "\n"
		. '  headings: [{ level: 1 }, { level: 2 }, { level: 4, fault: "a11y_heading_order", from: 2 }, { level: 4 }, { level: 4 }, { level: 2 }],' . "\n"
		. '  regions: [{}, {}, {}, {}, {}], pictures: [{ kind: "text" }, { fault: "a11y_img_alt", kind: "none" }, { kind: "decor" }, { kind: "text" }],' . "\n"
		. '  controls: [{ kind: "button", fault: "a11y_button_name" }, { kind: "field", fault: "a11y_form_label" }, { kind: "link", name: "Prices" }] };' . "\n"
		. 'var none = { order: [], jumps: [], headings: [], regions: [], pictures: [], controls: [] };' . "\n"
		. 'var two = { order: [{ index: 1 }, { index: 2 }], jumps: [false, true], headings: [{ level: 2, assumed: true }], regions: [{}], pictures: [{ kind: "text" }], controls: [{ kind: "link", fault: "a11y_link_name" }] };' . "\n"
		. 'console.log(JSON.stringify({ page: ["order", "headings", "names"].map(function (k) { return _avSummary(k, page); }),' . "\n"
		. '  none: ["order", "headings", "names"].map(function (k) { return _avSummary(k, none); }),' . "\n"
		. '  two: ["order", "headings", "names"].map(function (k) { return _avSummary(k, two); }) }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('count' => 15, 'marked' => 1, 'bold' => '15 stops.', 'rest' => 'One step jumps back on the page.'), $res['page'][0], 'Tab order: 15 stops, one jump');
	pg_assert_same(array('count' => 6, 'marked' => 1, 'bold' => '', 'rest' => ''), $res['page'][1], 'Headings: six, the first skip marked, the outline in the foot\'s place');
	pg_assert_same(array('count' => 9, 'marked' => 3, 'bold' => '5 regions, 4 pictures.', 'rest' => '3 places have no name.'), $res['page'][2], 'Names: regions and pictures counted, three places without a name');

	pg_assert_same('The keyboard reaches nothing on this page.', $res['none'][0]['rest'], 'nothing reached');
	pg_assert_same('This page has no heading.', $res['none'][1]['rest'], 'no heading');
	pg_assert_same('This page has no region, picture or unnamed control.', $res['none'][2]['rest'], 'nothing named');
	pg_assert_same(array(0, 0, 0), array_map(function ($s) { return $s['marked']; }, $res['none']), 'nothing marked on an empty page');

	pg_assert_same(array('count' => 2, 'marked' => 1, 'bold' => '2 stops.', 'rest' => 'One step jumps back on the page.'), $res['two'][0], 'one jump of two stops');
	pg_assert_same(0, $res['two'][1]['marked'], 'an assumed heading is never marked');
	pg_assert_same(array('count' => 2, 'marked' => 1, 'bold' => 'One region, one picture.', 'rest' => 'One place has no name.'), $res['two'][2], 'a link without a name is a place without a name');
}

// The words on the marks.
function test_designer_a11y_view_mark_words()
{
	$res = designer_a11y_view_run('style_designer_accessview.js', array('_avStopText', '_avRoleName', '_avNamed', '_avHeadingTag', '_avNoName', '_avFaultSentence', '_avRegionTagPlace'),
		'console.log(JSON.stringify([' . "\n"
		. '  _avStopText({ index: 2, tabindex: 0 }, true), _avStopText({ index: 1, tabindex: 1 }, false), _avStopText({ index: 7, tabindex: 0 }, false),' . "\n"
		. '  _avNamed("link", "See the timetable"), _avNamed("navigation", "Main menu"), _avNamed("main", ""), _avNamed("contentinfo", ""),' . "\n"
		. '  _avHeadingTag({ level: 4, from: 2 }, true), _avHeadingTag({ level: 2, assumed: true }, false), _avHeadingTag({ level: 3 }, false),' . "\n"
		. '  _avNoName("button"), _avNoName("link"), _avNoName("field"), _avNoName("frame"),' . "\n"
		. '  _avFaultSentence("a11y_img_alt"), _avFaultSentence("a11y_contrast"),' . "\n"
		. '  _avRegionTagPlace("navigation"), _avRegionTagPlace("main"), _avRegionTagPlace("banner")' . "\n"
		. ']));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		'2 · jumps back', '1 · tabindex 1', '7',
		'Link “See the timetable”', 'Navigation “Main menu”', 'Main', 'Content info',
		'H4 · skips H3', 'H2 · assumed', 'H3',
		'Button · no name', 'Link · no name', 'Field · no label', 'Frame · no title',
		'Image without alternative text', '',
		'above', 'right', 'left',
	), $res, 'the numbers, tags and sentences of boards V2 to V4');
}

// The audit's sentence is the one pg_a11y_issue_label() gives each of the
// reader's six rules.
function test_designer_a11y_view_uses_the_audits_sentences()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_accessview.js');

	foreach (array('a11y_heading_order', 'a11y_img_alt', 'a11y_link_name', 'a11y_button_name', 'a11y_form_label', 'a11y_frame_title') as $code) {
		$label = pg_a11y_issue_label($code);
		pg_assert_true($label !== $code && strpos($js, "case '" . $code . "': return _sdT('" . $label . "');") !== false, $code . ' says the audit\'s sentence');
	}
}

// The switch's kept state: off, Tab order and unfolded unless kept
// otherwise; an unknown kind falls back to Tab order.
function test_designer_a11y_view_kept_state()
{
	$res = designer_a11y_view_run('style_designer.src.js', array('_sdA11yStateOf'),
		'console.log(JSON.stringify([_sdA11yStateOf(null), _sdA11yStateOf({ on: true, kind: "names", fold: true }), _sdA11yStateOf({ on: "1", kind: "x" }), _sdA11yStateOf({ kind: "" })]));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('on' => false, 'kind' => 'order', 'fold' => false),
		array('on' => true, 'kind' => 'names', 'fold' => true),
		array('on' => false, 'kind' => 'order', 'fold' => false),
		array('on' => false, 'kind' => '', 'fold' => false),
	), $res, 'kept state');
}

function test_designer_a11y_view_core_hooks()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_true(strpos($core, 'id="sd-vb-a11y" role="menuitemcheckbox"') !== false, 'a switch row of the View menu');
	pg_assert_true(strpos($core, "'sd-vb-motion', 'sd-vb-a11y'") !== false, 'counted in the View button\'s badge');
	pg_assert_true(strpos($core, "_sdT('Show accessibility view')") !== false && strpos($core, "_sdT('Hide accessibility view')") !== false, 'a row of the command palette');
	pg_assert_true(strpos($core, "_sdPartCall('accessview', 'show'") !== false, 'the switch runs the part');
	pg_assert_same(3, substr_count($core, "        _sdA11ySync();\n"), 'a draw, the mode switch and the switch keep the view in step');
}

function test_designer_a11y_view_draws_outside_the_page()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_accessview.js');

	pg_assert_true(strpos($js, "StyleDesigner.definePart('accessview', function (K) {") !== false, 'registers the part');
	pg_assert_true(strpos($js, "closest('.sd-canvas-wrapper')") !== false, 'the layers go into the canvas wrapper of the editor');
	pg_assert_false((bool) preg_match('/\bdoc\.(createElement|body\.appendChild)/', $js), 'nothing is put into the canvas\'s page');
	pg_assert_true(strpos($js, 'V.mo.disconnect()') !== false && strpos($js, 'listen(false)') !== false, 'the observer and the listeners go with the view');
	pg_assert_true(strpos($js, 'setTimeout(refresh, REST_MS)') !== false, 'read again when the hand rests');
}

// "See as" (board V5): the menu's values in order, the two common ones with
// how many people see so, the pill's words and the filter laid on the frame.
function test_designer_a11y_view_see_as_values()
{
	$res = designer_a11y_view_run('style_designer_accessview.js', array('_avSeeName', '_avSeeShare', '_avSeePill', '_avSeeMatrix', '_avSeeFilter'),
		'var all = ["", "red", "green", "blue", "grey", "blur", "x"];' . "\n"
		. 'console.log(JSON.stringify({ name: all.map(_avSeeName), share: all.map(_avSeeShare), pill: all.map(_avSeePill),' . "\n"
		. '  filter: all.map(_avSeeFilter), matrix: all.map(function (k) { return _avSeeMatrix(k).split(/\s+/).filter(Boolean).length; }) }));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('Off', 'Red-weak', 'Green-weak', 'Blue-weak', 'No colour', 'Blurred', 'Off'), $res['name'], 'the menu\'s values; an unknown one is Off');
	pg_assert_same(array('', '1 in 100', '5 in 100', '', '', '', ''), $res['share'], 'only the two common ones say how many see so');
	pg_assert_same(array('', 'Seen as: red-weak', 'Seen as: green-weak', 'Seen as: blue-weak', 'Seen as: no colour', 'Seen as: blurred', ''), $res['pill'], 'the pill names the filter; none while Off');
	pg_assert_same(array('', 'url(#sd-av-see-red)', 'url(#sd-av-see-green)', 'url(#sd-av-see-blue)', 'grayscale(1)', 'blur(1.6px)', ''), $res['filter'], 'a colour matrix, a grey scale or a blur; nothing while Off');
	pg_assert_same(array(0, 20, 20, 20, 0, 0, 0), $res['matrix'], 'a 4x5 matrix for each colour-weak eye');
}

// The filter is style on the editor's frame: no picture is read, nothing
// goes into the canvas's page, and it is never kept.
function test_designer_a11y_view_see_as_is_style_only()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_accessview.js');

	pg_assert_true(strpos($js, 'frame.style.filter = f;') !== false, 'laid on the canvas frame');
	pg_assert_true(strpos($js, "V.seeFrame.style.filter = '';") !== false, 'taken off with the view');
	pg_assert_false((bool) preg_match('/getImageData|toDataURL|drawImage|localStorage|sessionStorage/', $js), 'no picture read, nothing kept');
	pg_assert_true(strpos($js, "var see = V ? V.see : '';") !== false, 'a new canvas document keeps it until the view goes off');
}

// A fake canvas element for the part's functions run in Node: a tag, its
// attributes, its children and its parent.
function designer_a11y_view_fake_dom()
{
	return 'function El(tag, attrs, kids) { this.nodeType = 1; this.tagName = tag.toUpperCase(); this.attrs = attrs || {}; this.children = kids || []; this.parentElement = null;'
		. ' var self = this; this.children.forEach(function (k) { k.parentElement = self; }); }' . "\n"
		. 'El.prototype.hasAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n); };' . "\n"
		. 'El.prototype.getAttribute = function (n) { return this.hasAttribute(n) ? this.attrs[n] : null; };' . "\n"
		. 'El.prototype.querySelector = function (sel) { var m = /^\[([a-z-]+)\]$/.exec(sel); for (var i = 0; i < this.children.length; i++) { var k = this.children[i];'
		. ' if (m && k.hasAttribute(m[1])) return k; var d = k.querySelector(sel); if (d) return d; } return null; };' . "\n";
}

// A shared component's or a system widget's content is drawn inside the
// editor's div.sd-shared-inner: it is the page's, never the editor's chrome.
function test_designer_a11y_view_reads_shared_component_content()
{
	$res = designer_a11y_view_run('style_designer_accessview.js', array('chrome'),
		designer_a11y_view_fake_dom()
		. 'var link = new El("a", { "href": "/", "data-sd-id": "n2" });' . "\n"
		. 'var inner = new El("div", { "class": "sd-shared-inner" }, [new El("nav", { "data-sd-id": "n1" }, [link])]);' . "\n"
		. 'var tb = new El("div", { "class": "sd-tb" }, [new El("button", {})]);' . "\n"
		. 'var badge = new El("span", { "class": "sd-issue-badge" });' . "\n"
		. 'var wrap = new El("div", { "class": "sd-wrap sd-selected", "data-sd-id": "n3" });' . "\n"
		. 'var page = new El("div", { "class": "card" });' . "\n"
		. 'console.log(JSON.stringify([chrome(inner), chrome(tb), chrome(badge), chrome(wrap), chrome(page)]));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(false, true, true, false, false), $res, 'only the editor\'s own elements that hold nothing of the page are left out');
}

// Fakes for listen(): event targets that count their listeners, and a
// MutationObserver that keeps what it watches so a test can call it.
function designer_a11y_view_fake_listen()
{
	return 'function Target() { this.on = {}; }' . "\n"
		. 'Target.prototype.addEventListener = function (t, f) { (this.on[t] = this.on[t] || []).push(f); };' . "\n"
		. 'Target.prototype.removeEventListener = function (t, f) { this.on[t] = (this.on[t] || []).filter(function (g) { return g !== f; }); };' . "\n"
		. 'Target.prototype.fire = function (t, e) { (this.on[t] || []).slice().forEach(function (f) { f(e || {}); }); };' . "\n"
		. 'Target.prototype.count = function () { var o = this.on; return Object.keys(o).reduce(function (n, k) { return n + o[k].length; }, 0); };' . "\n"
		. 'var observers = [];' . "\n"
		. 'function MutationObserver(cb) { this.cb = cb; this.seen = []; this.off = false; observers.push(this); }' . "\n"
		. 'MutationObserver.prototype.observe = function (n, o) { this.seen.push({ n: n, o: o }); };' . "\n"
		. 'MutationObserver.prototype.disconnect = function () { this.off = true; };' . "\n"
		. 'function watching(n) { return observers.filter(function (m) { return !m.off && m.seen.some(function (s) { return s.n === n; }); })[0]; }' . "\n"
		. 'var window = new Target(), document = new Target();' . "\n"
		. 'var calls = { rest: 0, soon: 0 };' . "\n"
		. 'function rest() { calls.rest++; } function soon() { calls.soon++; }' . "\n"
		. 'function onKey() {} function onCanvasMove() {} function onCanvasOut() {} function onDown() {} function onResize() {}' . "\n";
}

// Another screen size resizes the canvas window; a zoom writes the canvas's
// <html> and Fit the frame's box: each moves the marks and reads the page
// again, since what shows and where differ at another width.
function test_designer_a11y_view_follows_screen_size_zoom_and_fit()
{
	$res = designer_a11y_view_run('style_designer_accessview.js', array('chrome', 'inChrome', 'pageChange', 'relayout', 'listen'),
		designer_a11y_view_fake_dom() . designer_a11y_view_fake_listen()
		. 'var box = new Target(); var win = new Target(); win.frameElement = { parentNode: box };' . "\n"
		. 'var html = new Target(); var body = new Target();' . "\n"
		. 'var doc = new Target(); doc.defaultView = win; doc.documentElement = html; doc.body = body;' . "\n"
		. 'var V = { doc: doc };' . "\n"
		. 'listen(true);' . "\n"
		. 'var out = {};' . "\n"
		. 'win.fire("resize"); out.size = [calls.soon, calls.rest];' . "\n"
		. 'calls = { rest: 0, soon: 0 }; watching(html).cb([{ type: "attributes", target: html }]); out.zoom = [calls.soon, calls.rest];' . "\n"
		. 'calls = { rest: 0, soon: 0 }; watching(box).cb([{ type: "attributes", target: box }]); out.fit = [calls.soon, calls.rest];' . "\n"
		. 'out.watched = [watching(html).seen[0].o.attributeFilter, watching(box).seen[0].o.attributeFilter];' . "\n"
		. 'listen(false);' . "\n"
		. 'out.left = [win.count(), doc.count(), window.count(), document.count(), observers.filter(function (m) { return !m.off; }).length];' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(1, 1), $res['size'], 'another screen size moves the marks and reads the page again');
	pg_assert_same(array(1, 1), $res['zoom'], 'a zoom moves the marks');
	pg_assert_same(array(1, 1), $res['fit'], 'Fit moves the marks');
	pg_assert_same(array(array('style'), array('style')), $res['watched'], 'only the style of <html> and of the frame\'s box are watched');
	pg_assert_same(array(0, 0, 0, 0, 0), $res['left'], 'nothing is left listening once the view is off');
}

// The editor writes its own chrome in the canvas on every hover and scroll
// (a toolbar filled, a chip placed): that is no change of the page, and the
// page is not read again for it.
function test_designer_a11y_view_ignores_the_editors_own_changes()
{
	$res = designer_a11y_view_run('style_designer_accessview.js', array('chrome', 'inChrome', 'pageChange', 'relayout', 'listen'),
		designer_a11y_view_fake_dom() . designer_a11y_view_fake_listen()
		. 'var btn = new El("button", {}); var tb = new El("div", { "class": "sd-tb" }, [btn]);' . "\n"
		. 'var a = new El("a", { "href": "/" }); var wrap = new El("div", { "class": "sd-wrap", "data-sd-id": "n1" }, [tb, a]);' . "\n"
		. 'var text = { nodeType: 3, parentElement: a };' . "\n"
		. 'var win = new Target(); win.frameElement = { parentNode: new Target() };' . "\n"
		. 'var body = new Target(); var doc = new Target(); doc.defaultView = win; doc.documentElement = new Target(); doc.body = body;' . "\n"
		. 'var V = { doc: doc };' . "\n"
		. 'listen(true);' . "\n"
		. 'var mo = watching(body), out = [];' . "\n"
		. 'function n(recs) { calls.rest = 0; mo.cb(recs); return calls.rest; }' . "\n"
		. 'out.push(n([{ type: "childList", target: tb, addedNodes: [btn], removedNodes: [] }]));' . "\n"
		. 'out.push(n([{ type: "attributes", target: tb }]));' . "\n"
		. 'out.push(n([{ type: "childList", target: wrap, addedNodes: [tb], removedNodes: [] }]));' . "\n"
		. 'out.push(n([{ type: "attributes", target: a }]));' . "\n"
		. 'out.push(n([{ type: "characterData", target: text }]));' . "\n"
		. 'out.push(n([{ type: "childList", target: wrap, addedNodes: [], removedNodes: [a] }]));' . "\n"
		. 'out.push(n([{ type: "attributes", target: tb }, { type: "attributes", target: a }]));' . "\n"
		. 'out.push(mo.seen[0].o.attributeFilter.indexOf("contenteditable") !== -1);' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(0, 0, 0, 1, 1, 1, 1, true), $res, 'a toolbar filled or placed reads nothing; a change of the page is read');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Accessibility view's reader (assets/js/pg_a11y_reader.js,
 * window.PGA11yReader): the keyboard's order, the heading outline, the
 * landmarks, the pictures' texts and the controls' names over a tree of
 * elements, and its faults beside the audit's PHP rules
 * (pg_a11y_check_html()) on the same markup. Runs in Node; skipped where
 * Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

// The reader's tree of a piece of markup, parsed as the audit parses it.
// An element's designer node is its data-n attribute.
function a11y_reader_test_tree($html)
{
	$previous = libxml_use_internal_errors(true);
	$document = new DOMDocument();
	$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	return a11y_reader_test_element($document->getElementsByTagName('body')->item(0));
}

function a11y_reader_test_element($element)
{
	$attrs = array();

	foreach ($element->attributes as $attribute) {
		$attrs[strtolower($attribute->name)] = $attribute->value;
	}

	$out = array('tag' => strtolower($element->nodeName), 'attrs' => (object) $attrs, 'children' => array(), 'node' => $element->getAttribute('data-n'));

	foreach ($element->childNodes as $child) {
		if ($child->nodeType === XML_TEXT_NODE) {
			$out['children'][] = array('text' => $child->nodeValue);
		} elseif ($child->nodeType === XML_ELEMENT_NODE) {
			$out['children'][] = a11y_reader_test_element($child);
		}
	}

	return $out;
}

// Runs `$code` in Node with window.PGA11yReader loaded as A and the tree of
// `$html` as T; its `out` comes back decoded. Null when Node cannot run.
function a11y_reader_test_node($code, $html = '')
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_a11y_reader.js') . "\n"
		. 'var A = window.PGA11yReader, T = ' . json_encode(a11y_reader_test_tree($html), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ", out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pga11y');

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

// One field of every entry.
function a11y_reader_test_pluck($rows, $key)
{
	return array_map(function ($row) use ($key) {
		return isset($row[$key]) ? $row[$key] : null;
	}, (array) $rows);
}

function test_a11y_reader_tab_order_as_a_browser_walks_it()
{
	$html = '<a data-n="plain" href="/a">A</a>'
		. '<a data-n="anchor">no href</a>'
		. '<div data-n="late" tabindex="2">two</div>'
		. '<button data-n="first" tabindex="1">one</button>'
		. '<input data-n="off" disabled>'
		. '<input type="hidden" name="h">'
		. '<div hidden><a href="/x">hidden</a></div>'
		. '<div style="display: none"><a href="/y">none</a></div>'
		. '<div inert><button>inert</button></div>'
		. '<a data-n="unread" href="/z" aria-hidden="true">aria-hidden stays in the way</a>'
		. '<span data-n="script" tabindex="-1">reached by script only</span>'
		. '<fieldset disabled><legend><a data-n="legend" href="/l">in the legend</a></legend><input data-n="fs"></fieldset>'
		. '<form><input data-n="r1" type="radio" name="g"><input data-n="r2" type="radio" name="g" checked><input data-n="r3" type="radio" name="g"></form>'
		. '<details><summary data-n="sum">More</summary><a data-n="closed" href="/c">inside</a></details>'
		. '<div data-n="edit" contenteditable="true">edit</div>'
		. '<video data-n="video" controls></video><video data-n="still"></video>'
		. '<iframe data-n="frame" title="Map"></iframe>';

	$out = a11y_reader_test_node('out = A.tabOrder(T);', $html);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('first', 'late', 'plain', 'unread', 'legend', 'r2', 'sum', 'edit', 'video', 'frame'), a11y_reader_test_pluck($out, 'node'), 'positive tabindex first, then document order; one radio per group');
	pg_assert_same(range(1, 10), a11y_reader_test_pluck($out, 'index'), 'stops are numbered from one');
	pg_assert_same(array(false, true, true, false, false, false, false, false, false, false), a11y_reader_test_pluck($out, 'back'), 'a stop before the previous one in the document goes back');
	pg_assert_same(array('button', '', 'link', 'link', 'link', 'radio', 'button', '', '', 'frame'), a11y_reader_test_pluck($out, 'role'), 'each stop says what it is');
	pg_assert_same('Map', $out[9]['name'], 'a frame is named by its title');
}

function test_a11y_reader_radio_without_a_checked_one_stops_at_the_first()
{
	$out = a11y_reader_test_node('out = A.tabOrder(T);', '<input data-n="a" type="radio" name="x"><input data-n="b" type="radio" name="x"><input data-n="c" type="radio" name="y">');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('a', 'c'), a11y_reader_test_pluck($out, 'node'), 'each group stops once, at its first radio');
}

function test_a11y_reader_caller_hidden_and_the_designer_node()
{
	$code = 'T.children[0].hidden = true; T.children[1].children[0].ref = 7; out = A.tabOrder(T);';
	$out = a11y_reader_test_node($code, '<div><a href="/a">gone</a></div><p data-n="para"><a href="/b">kept <b>bold</b></a></p>');

	if ($out === null) {
		return;
	}

	pg_assert_same(1, count($out), 'what the caller says is hidden is not reached');
	pg_assert_same('para', $out[0]['node'], 'an element without a node is named by the node it sits in');
	pg_assert_same(7, $out[0]['ref'], 'the caller\'s reference comes back');
	pg_assert_same('kept bold', $out[0]['name'], 'the name is the text, spaces collapsed');
}

function test_a11y_reader_jumps_from_the_boxes()
{
	$code = 'out = [A.jumps([{top: 0, left: 0, width: 100, height: 20}, {top: 0, left: 200, width: 100, height: 20}, {top: 40, left: 0, width: 100, height: 20}, {top: 0, left: 400, width: 50, height: 20}, null, {top: 0, left: 100, width: 50, height: 20}]),'
		. ' A.jumps([{top: 0, left: 300, width: 100, height: 20}, {top: 0, left: 100, width: 100, height: 20}, {top: 0, left: 350, width: 100, height: 20}], true)];';
	$out = a11y_reader_test_node($code);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(false, false, false, true, false, true), $out[0], 'a step above the last one, or before it on its line, goes back; a stop off the page is skipped');
	pg_assert_same(array(false, false, true), $out[1], 'on a right-to-left page a step to the right goes back');
}

function test_a11y_reader_heading_outline()
{
	$html = '<h1 data-n="h1">Title</h1><h3 data-n="h3">Skips</h3><div role="heading" data-n="r">No level</div>'
		. '<h2 hidden>Hidden</h2><h4 data-n="h4" aria-label="Named">text</h4><div role="heading" aria-level="6" data-n="r6">Six</div>';
	$out = a11y_reader_test_node('out = A.headings(T);', $html);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('h1', 'h3', 'r', 'h4', 'r6'), a11y_reader_test_pluck($out, 'node'), 'hidden headings are left out');
	pg_assert_same(array(1, 3, 2, 4, 6), a11y_reader_test_pluck($out, 'level'), 'each heading\'s level; role="heading" alone reads as 2');
	pg_assert_same(array(false, true, false, false, true), a11y_reader_test_pluck($out, 'skipped'), 'a level more than one below the previous one is skipped; the assumed level starts no skip');
	pg_assert_same(array(null, 'a11y_heading_order', null, null, 'a11y_heading_order'), a11y_reader_test_pluck($out, 'fault'), 'the skip carries the audit\'s code');
	pg_assert_same(1, $out[1]['from'], 'the skip names the level before it');
	pg_assert_same('Named', $out[3]['text'], 'a heading\'s text is its name');
}

function test_a11y_reader_regions_and_their_names()
{
	$html = '<header data-n="top"><nav data-n="nav" aria-label="Main menu"></nav></header>'
		. '<main data-n="main"><article><header data-n="ah">not a banner</header></article>'
		. '<section data-n="s1">unnamed</section><section data-n="s2" aria-labelledby="t"><h2 id="t">Prices</h2></section>'
		. '<form data-n="f1"></form><form data-n="f2" aria-label="Search the shop"></form></main>'
		. '<aside data-n="side" title="Related"></aside><div role="search" data-n="srch"></div>'
		. '<nav aria-hidden="true"></nav><footer data-n="foot"></footer>';
	$out = a11y_reader_test_node('out = A.regions(T);', $html);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('top', 'nav', 'main', 's2', 'f2', 'side', 'srch', 'foot'), a11y_reader_test_pluck($out, 'node'), 'landmarks in order; an unnamed section or form is none');
	pg_assert_same(array('banner', 'navigation', 'main', 'region', 'form', 'complementary', 'search', 'contentinfo'), a11y_reader_test_pluck($out, 'role'), 'each landmark\'s role');
	pg_assert_same(array('', 'Main menu', '', 'Prices', 'Search the shop', 'Related', '', ''), a11y_reader_test_pluck($out, 'name'), 'names from aria-label, aria-labelledby, title');
	pg_assert_same(array(0, 1, 0, 1, 1, 0, 0, 0), a11y_reader_test_pluck($out, 'depth'), 'how deep each sits in the others');
}

function test_a11y_reader_pictures()
{
	$html = '<img data-n="alt" src="a.jpg" alt="A red bike"><img data-n="decor" src="b.jpg" alt="">'
		. '<img data-n="none" src="c.jpg"><img data-n="pres" src="d.jpg" role="presentation">'
		. '<img data-n="lab" src="e.jpg" aria-label="Logo"><img src="f.jpg" aria-hidden="true">'
		. '<svg data-n="svg" role="img"><title>Chart</title></svg><input data-n="in" type="image" src="g.png" alt="">'
		. '<map><area data-n="area" href="/x"></map><img data-n="lby" src="h.jpg" aria-labelledby="missing">';
	$out = a11y_reader_test_node('out = A.pictures(T);', $html);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('alt', 'decor', 'none', 'pres', 'lab', 'svg', 'in', 'area', 'lby'), a11y_reader_test_pluck($out, 'node'), 'pictures a screen reader meets');
	pg_assert_same(array('text', 'decor', 'none', 'decor', 'text', 'text', 'none', 'none', 'none'), a11y_reader_test_pluck($out, 'kind'), 'text, decoration or nothing');
	pg_assert_same(array('A red bike', '', '', '', 'Logo', 'Chart', '', '', ''), a11y_reader_test_pluck($out, 'text'), 'what each says');
	pg_assert_same(array(null, null, 'a11y_img_alt', null, null, null, 'a11y_img_alt', 'a11y_img_alt', null), a11y_reader_test_pluck($out, 'fault'), 'faults as the audit raises them');
}

function test_a11y_reader_controls_and_their_names()
{
	$html = '<a data-n="l1" href="/a"><img src="i.png" alt="Home"></a><a data-n="l2" href="/b"><i class="bi"></i></a>'
		. '<button data-n="b1"><span aria-hidden="true">×</span></button><button data-n="b2" aria-label="Close">×</button>'
		. '<input data-n="b3" type="button" value="Go"><input data-n="sub" type="submit">'
		. '<label for="e">E-mail</label><input data-n="f1" id="e"><label>Name <input data-n="f2"></label>'
		. '<input data-n="f3" placeholder="Phone"><select data-n="f4"><option>One</option></select>'
		. '<span id="cap">Notes</span><textarea data-n="f5" aria-labelledby="cap"></textarea>'
		. '<iframe data-n="fr1"></iframe><iframe data-n="fr2" aria-label="Video"></iframe>';
	$out = a11y_reader_test_node('out = A.controls(T);', $html);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('l1', 'l2', 'b1', 'b2', 'b3', 'sub', 'f1', 'f2', 'f3', 'f4', 'f5', 'fr1', 'fr2'), a11y_reader_test_pluck($out, 'node'), 'every control in order');
	pg_assert_same(array('Home', '', '', 'Close', 'Go', '', 'E-mail', 'Name', '', '', 'Notes', '', 'Video'), a11y_reader_test_pluck($out, 'name'), 'the name a screen reader says');
	pg_assert_same(array(null, 'a11y_link_name', 'a11y_button_name', null, null, null, null, null, 'a11y_form_label', 'a11y_form_label', null, 'a11y_frame_title', null), a11y_reader_test_pluck($out, 'fault'), 'a missing name carries the audit\'s code; a placeholder is no label');
}

// The reader raises a fault only where the audit's rule does, on the same
// markup: the view finds nothing the audit does not know.
function test_a11y_reader_faults_match_the_audit()
{
	$cases = array(
		'<h2>a</h2><h4>b</h4><h5 style="display:none">c</h5><h6>d</h6>',
		'<img src="a"><img src="b" alt=""><img src="c" role="none"><img src="d" title="t"><input type="image" src="e" alt="x"><area href="/f">',
		'<a href="/a"></a><a href="/b" title="Title"></a><a href="/c" aria-labelledby="n"></a><span id="n">N</span><a href="/d"><svg><title>Icon</title></svg></a>',
		'<button></button><button><img src="x" alt="Send"></button><div role="button"></div><input type="button" value=" ">',
		'<input><input aria-label="A"><label>L <select></select></label><textarea title="T"></textarea><input type="submit"><label for="q"></label><input id="q">',
		'<iframe></iframe><iframe title="x"></iframe><iframe aria-labelledby="none"></iframe><div aria-hidden="true"><iframe></iframe></div>',
		'<button><span aria-hidden="true">×</span></button><a href="/x"><span hidden>Go</span></a><a href="/y"><span style="display:none">Y</span></a><a href="/z"><i aria-hidden="true"></i>Shop</a><button><img src="i" alt="Send" aria-hidden="true"></button>',
		'<p>No faults at all.</p><h1>One</h1><h2>Two</h2><img src="z" alt="Z"><a href="/">Home</a>',
	);
	$codes = array('a11y_heading_order', 'a11y_img_alt', 'a11y_link_name', 'a11y_button_name', 'a11y_form_label', 'a11y_frame_title');
	$code = 'var r = A.read(T), counts = {};'
		. ' [r.headings, r.pictures, r.controls].forEach(function (list) { list.forEach(function (e) { if (e.fault) { counts[e.fault] = (counts[e.fault] || 0) + 1; } }); });'
		. ' out = counts;';

	foreach ($cases as $html) {
		$out = a11y_reader_test_node($code, $html);

		if ($out === null) {
			return;
		}

		$audit = pg_a11y_check_html($html);

		foreach ($codes as $rule) {
			pg_assert_same(isset($audit[$rule]) ? $audit[$rule]['occurrences'] : 0, isset($out[$rule]) ? $out[$rule] : 0, $rule . ' on ' . $html);
		}
	}
}

function test_a11y_reader_read_answers_every_list()
{
	$out = a11y_reader_test_node('var r = A.read(T); out = Object.keys(r).concat([r.order.length, r.headings.length, r.regions.length, r.pictures.length, r.controls.length]);', '<main><h1>Hi</h1><a href="/">Home</a><img src="x" alt="X"></main>');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('order', 'headings', 'regions', 'pictures', 'controls', 1, 1, 1, 1, 1), $out, 'read() answers the five lists over one walk');
}

function test_a11y_reader_from_dom()
{
	// A DOM of the shape the canvas has, enough for fromDom().
	$code = 'function el(tag, attrs, kids) { var list = Object.keys(attrs).map(function (k) { return { name: k, value: attrs[k] }; });'
		. ' return { nodeType: 1, localName: tag, attributes: list, childNodes: kids, getAttribute: function (n) { return attrs.hasOwnProperty(n) ? attrs[n] : null; } }; }'
		. ' function tx(t) { return { nodeType: 3, nodeValue: t }; }'
		. ' var chrome = el("div", {"class": "sd-handle"}, [el("button", {}, [])]);'
		. ' var dom = el("div", {}, [el("a", {"href": "/a", "data-sd-id": "n1"}, [tx("Go")]), chrome, el("button", {"data-sd-id": "n2", "style": "x"}, [tx("Off")]), {nodeType: 8}]);'
		. ' var tree = A.fromDom(dom, { skip: function (e) { return e === chrome; }, hidden: function (e) { return e.getAttribute("style") === "x"; } });'
		. ' out = A.tabOrder(tree).map(function (s) { return [s.node, s.name, s.ref === dom.childNodes[0]]; });';
	$out = a11y_reader_test_node($code);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('n1', 'Go', true)), $out, 'the canvas\'s elements become the tree; chrome is skipped, hidden ones are not reached, the element comes back as ref');
}

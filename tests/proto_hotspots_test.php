<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Prototype view's hotspot collector (assets/js/pg_proto_hotspots.js,
 * window.PGProtoHotspots): which nodes of a tree are hotspots, shared
 * components once with the tabs placing them, the map read's request, and
 * each hotspot's mark through the resolver's twin. The links of a piece of
 * markup are the hrefs pg_proto_html_hrefs() reads. Runs in Node; skipped
 * where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');

// Runs `$code` in Node with window.PGGraphCanvas, window.PGLinkResolve and
// window.PGProtoHotspots loaded (as H and R); its `out` comes back decoded. Null when Node cannot run.
function proto_hotspots_test_node($code)
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js') . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_link_resolve.js') . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_hotspots.js') . "\n"
		. "var R = window.PGLinkResolve, H = window.PGProtoHotspots, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgproto');

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

function proto_hotspots_test_js($value)
{
	return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// A page of every link kind the designer writes, and of the ones it skips.
function proto_hotspots_test_tree()
{
	return array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'n1', 'type' => 'semantic', 'props' => array('tag' => 'nav'), 'children' => array(
			array('_id' => 'n2', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'About <b>us</b>', 'href' => '/about'), 'children' => array()),
			array('_id' => 'n3', 'type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Shop', '_attrs' => array(array('name' => 'href', 'value' => '/shop'))), 'children' => array()),
			array('_id' => 'n4', 'type' => 'semantic', 'props' => array('tag' => 'a', 'href' => '/blog', '_attrs' => array(array('name' => 'href', 'value' => '/ignored'))), 'children' => array()),
			array('_id' => 'n5', 'type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Menu', '_attrs' => array(array('name' => 'href', 'value' => '#'), array('name' => 'data-bs-toggle', 'value' => 'dropdown'))), 'children' => array()),
			array('_id' => 'n6', 'type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Toggle', 'href' => '#nav', '_attrs' => array(array('name' => 'data-bs-toggle', 'value' => 'collapse'))), 'children' => array()),
		)),
		array('_id' => 'n7', 'type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Buy', 'href' => 'mailto:a@example.com'), 'children' => array()),
		array('_id' => 'n8', 'type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Send', 'btnElement' => 'button', 'href' => '/x'), 'children' => array()),
		array('_id' => 'n9', 'type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'See <a href="/terms">terms</a> and <a href=\'#top\' data-bs-toggle="tab">tab</a> or <a href=/draft-page target=_blank>draft</a>'), 'children' => array()),
		array('_id' => 'n10', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Bound', 'href' => '/x', '_bindings' => array('href' => '__page_link')), 'children' => array()),
		array('_id' => 'n11', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Empty', 'href' => ''), 'children' => array()),
		array('_id' => 'n12', 'type' => 'shared_ref', 'props' => array('sharedId' => 5), 'children' => array()),
		array('_id' => 'n13', 'type' => 'region', 'props' => array('regionType' => 'menu', 'regionName' => 'Top'), 'children' => array()),
		array('_id' => 'n14', 'type' => 'region', 'props' => array('regionType' => 'common', 'regionName' => 'Footer'), 'children' => array()),
		array('_id' => 'n15', 'type' => 'region', 'props' => array('regionType' => 'dynamic', 'regionName' => 'Calc'), 'children' => array()),
		array('_id' => 'n16', 'type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<!-- <a href="/c"> --><script>var a = "<a href=/s>";</script><a href="/x?a=1&amp;b=2">X</a>'), 'children' => array()),
		array('_id' => 'n17', 'type' => 'shared_ref', 'props' => array('sharedId' => 5), 'children' => array()),
	));
}

// Which nodes are hotspots, from where their href is read, in document
// order; toggles, a <button> and region kinds without links are not (a
// link kept by page id with no page is its typed href); shared components, menus and regions are listed once.
function test_proto_hotspots_collect_a_tree()
{
	$result = proto_hotspots_test_node('out = H.collect(' . proto_hotspots_test_js(proto_hotspots_test_tree()) . ');');

	if ($result === null) {
		return;
	}

	$spots = array();

	foreach ($result['hotspots'] as $spot) {
		$spots[] = isset($spot['href'])
			? array($spot['nodeId'], $spot['source'], $spot['href'], $spot['label'], $spot['index'])
			: array($spot['nodeId'], $spot['source'], $spot['name']);
	}

	pg_assert_same(array(
		array('n2', 'href', '/about', 'About us', 0),
		array('n3', 'attrs', '/shop', 'Shop', 0),
		array('n4', 'href', '/blog', '', 0),
		array('n7', 'btn', 'mailto:a@example.com', 'Buy', 0),
		array('n9', 'text', '/terms', 'terms', 0),
		array('n9', 'text', '/draft-page', 'draft', 2),
		array('n10', 'href', '/x', 'Bound', 0),
		array('n11', 'href', '#', 'Empty', 0),
		array('n13', 'menu', 'Top'),
		array('n14', 'region', 'Footer'),
		array('n16', 'text', '/x?a=1&b=2', 'X', 0),
	), $spots, 'the hotspots of the tree');
	pg_assert_same('_blank', $result['hotspots'][5]['target'], 'a text link keeps its target');
	pg_assert_same(array(5), $result['shared'], 'a shared component once');
	pg_assert_same(array('Top'), $result['menus'], 'the menus');
	pg_assert_same(array('Footer'), $result['regions'], 'the regions');
}

// The links of markup carry the hrefs pg_proto_html_hrefs() reads.
function test_proto_hotspots_html_links_as_the_server_reads()
{
	$samples = array(
		'<a href="/a">A</a> <A HREF=\'/b\'>B</A> <a href=/c?x=1>C</a>',
		'<a title="x > y" href="/d">D</a><a data-x=\'<a href="/no">\' href="/e">E</a>',
		'<!-- <a href="/no"> --><a href="/f">F</a><style>a[href="/no"]{}</style><textarea><a href="/no"></textarea>',
		'<a name="x">no href</a><a href>empty</a><a href="">blank</a><a href="/g&amp;h&#47;i&#x2F;j">G</a>',
		'<a\nhref = "/k"\n>K</a><a href="/l" href="/m">L</a><p><a href=\'#top\'>top</a></p>',
		'<a href="/unclosed',
	);
	$expected = array();

	foreach ($samples as $html) {
		$expected[] = pg_proto_html_hrefs($html);
	}

	$result = proto_hotspots_test_node('out = ' . proto_hotspots_test_js($samples) . '.map(function (html) { return H.htmlLinks(html).map(function (l) { return l.href; }); });');

	if ($result === null) {
		return;
	}

	foreach ($samples as $i => $html) {
		pg_assert_same($expected[$i], $result[$i], 'the same hrefs in ' . json_encode($html));
	}
}

// Every tab once, a shared component once with the number of tabs placing
// it (through another shared component too), one not fetched yet marked
// so; the request names every href, menu and region once.
function test_proto_hotspots_design_frames_and_request()
{
	$pages = array(
		array('key' => 'p1', 'page_id' => 1, 'page_name' => 'home', 'tree' => proto_hotspots_test_tree()),
		array('key' => 'p2', 'page_id' => 2, 'page_name' => 'about', 'tree' => array('_id' => 'r', 'type' => 'root', 'children' => array(
			array('_id' => 'a1', 'type' => 'shared_ref', 'props' => array('sharedId' => 6), 'children' => array()),
			array('_id' => 'a2', 'type' => 'shared_ref', 'props' => array('sharedId' => 9), 'children' => array()),
		))),
		array('key' => 'new1', 'page_id' => 0, 'page_name' => '', 'tree' => array('_id' => 'r', 'type' => 'root', 'children' => array(
			array('_id' => 'b1', 'type' => 'content', 'props' => array('contentType' => 'link', 'href' => '/about'), 'children' => array()),
		))),
	);
	$shared = array(
		5 => array('name' => 'Header', 'tree' => array('_id' => 's5', 'type' => 'root', 'children' => array(
			array('_id' => 'h1', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Home', 'href' => '/'), 'children' => array()),
		))),
		6 => array('name' => 'Frame', 'tree' => array('_id' => 's6', 'type' => 'root', 'children' => array(
			array('_id' => 'f1', 'type' => 'shared_ref', 'props' => array('sharedId' => 5), 'children' => array()),
			array('_id' => 'f2', 'type' => 'shared_ref', 'props' => array('sharedId' => 6), 'children' => array()),
		))),
		9 => array('name' => 'Later', 'tree' => null),
	);
	$result = proto_hotspots_test_node('out = H.design(' . proto_hotspots_test_js($pages) . ', ' . proto_hotspots_test_js($shared) . ');');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('p1', 'p2', 'new1'), array_column($result['pages'], 'key'), 'every tab once');
	pg_assert_same(array(5, 6, 9), array_column($result['shared'], 'id'), 'every shared component once');
	pg_assert_same(array(2, 1, 1), array_column($result['shared'], 'pages'), 'the tabs placing each');
	pg_assert_same(array(true, true, false), array_column($result['shared'], 'loaded'), 'one not fetched yet');
	pg_assert_same('/', $result['shared'][0]['hotspots'][0]['href'], 'a shared component holds its own links');
	pg_assert_same(array('/about', '/shop', '/blog', 'mailto:a@example.com', '/terms', '/draft-page', '/x', '#', '/x?a=1&b=2', '/'), $result['request']['hrefs'], 'each href once');
	pg_assert_same(array('Top'), $result['request']['menus'], 'the menus asked');
	pg_assert_same(array('Footer'), $result['request']['regions'], 'the regions asked');
}

// The map read's answer as pg_proto_map() gives it.
function proto_hotspots_test_read()
{
	$maps = array(
		'pages' => array(
			'home' => array('id' => 1, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 1),
			'about' => array('id' => 2, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 1),
			'shop' => array('id' => 3, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 1),
			'draft-page' => array('id' => 4, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 7),
			'old' => array('id' => 5, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 9),
		),
		'files' => array(), 'short_links' => array(), 'products' => array(), 'product_groups' => array(),
		'home_page_id' => 1,
	);

	return array(
		'env' => array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		'maps' => pg_link_maps_fold($maps),
		'form_names' => new stdClass(),
		'hidden' => array('Secret'),
		'draft_folder' => 7,
		'bin_folders' => array(9),
		'menus' => array(
			'Top' => array(
				array('id' => 11, 'parent' => 0, 'name' => 'Home', 'href' => '/home', 'target' => '', 'page_id' => 1, 'hidden' => false),
				array('id' => 12, 'parent' => 11, 'name' => 'Private', 'href' => '', 'target' => '', 'page_id' => 0, 'hidden' => true),
			),
			'Gone' => null,
		),
		'regions' => array('Footer' => array('kind' => 'common', 'hrefs' => array('tel:+90', '/old'))),
	);
}

// Each href's mark: a tab by its page id, an unsaved tab by name, a site
// page, a draft, a binned or unknown page broken, a hidden page hidden,
// external, contact and anchor.
function test_proto_hotspots_mark_each_href()
{
	$tabs = array(
		array('key' => 'p1', 'page_id' => 1, 'page_name' => 'home'),
		array('key' => 'new1', 'page_id' => 0, 'page_name' => 'contact-us'),
	);
	$hrefs = array('/', '/home', '/Contact-Us', '/about', '/draft-page', '/old', '/nothing', '/secret', 'https://other.org/', 'mailto:a@b.c', 'tel:1', '#top', '');
	$result = proto_hotspots_test_node('var read = ' . proto_hotspots_test_js(proto_hotspots_test_read()) . ', tabs = ' . proto_hotspots_test_js($tabs) . ';'
		. 'out = ' . proto_hotspots_test_js($hrefs) . '.map(function (h) { var m = H.markHref(h, read, tabs); return [m.mark, m.draft, m.tab, m.pageId]; });');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('tab', false, 'p1', 1),
		array('tab', false, 'p1', 1),
		array('tab', false, 'new1', 0),
		array('page', false, '', 2),
		array('page', true, '', 4),
		array('broken', false, '', 5),
		array('broken', false, '', 0),
		array('hidden', false, '', 0),
		array('external', false, '', 0),
		array('contact', false, '', 0),
		array('contact', false, '', 0),
		array('anchor', false, '', 0),
		array('broken', false, '', 0),
	), $result, 'the mark of each href');
}

// mark() marks every frame's hotspots and expands a menu or region into its
// links; a hidden menu item keeps no address; a name the read does not
// know gets null.
function test_proto_hotspots_mark_a_design()
{
	$pages = array(array('key' => 'p1', 'page_id' => 1, 'page_name' => 'home', 'tree' => array('_id' => 'r', 'type' => 'root', 'children' => array(
		array('_id' => 'm', 'type' => 'region', 'props' => array('regionType' => 'menu', 'regionName' => 'Top'), 'children' => array()),
		array('_id' => 'g', 'type' => 'region', 'props' => array('regionType' => 'menu_sequence', 'regionName' => 'Gone'), 'children' => array()),
		array('_id' => 'f', 'type' => 'region', 'props' => array('regionType' => 'designer', 'regionName' => 'Footer'), 'children' => array()),
		array('_id' => 'l', 'type' => 'content', 'props' => array('contentType' => 'link', 'href' => '/draft-page'), 'children' => array()),
	))));
	$result = proto_hotspots_test_node('var read = ' . proto_hotspots_test_js(proto_hotspots_test_read()) . ';'
		. 'var d = H.design(' . proto_hotspots_test_js($pages) . ', {});'
		. 'var m = H.mark(d, read, [{ key: "p1", page_id: 1, page_name: "home" }]);'
		. 'out = { marked: m.pages[0].hotspots, untouched: d.pages[0].hotspots[3].mark === undefined };');

	if ($result === null) {
		return;
	}

	$spots = $result['marked'];

	pg_assert_same(array(array('tab', 'Home', 11, 0), array('hidden', 'Private', 12, 11)), array_map(function ($l) {
		return array($l['mark'], $l['label'], $l['menuItemId'], $l['parent']);
	}, $spots[0]['links']), 'a menu expanded into its marked items');
	pg_assert_same('', $spots[0]['links'][1]['href'], 'a hidden item keeps no address');
	pg_assert_same(null, $spots[1]['links'], 'a menu the read does not know');
	pg_assert_same(array('contact', 'broken'), array_column($spots[2]['links'], 'mark'), 'a region expanded into its links');
	pg_assert_same(array('page', true), array($spots[3]['mark'], $spots[3]['draft']), 'a draft link');
	pg_assert_true($result['untouched'], 'the design handed in is not changed');
}

// layout(): frames as tall as their rows, the shared ones in a column left
// of the pages, a menu expanded into rows, an unknown menu one broken row,
// one chip per mark and href in a column right of them grouped by mark, an
// arrow from each row to its tab frame or chip, saved positions kept.
function test_proto_hotspots_layout()
{
	$marked = array(
		'pages' => array(
			array('key' => 'p1', 'name' => 'home', 'hotspots' => array(
				array('nodeId' => 'a', 'source' => 'href', 'href' => '/about', 'label' => 'About', 'mark' => 'tab', 'tab' => 'p2', 'draft' => false),
				array('nodeId' => 'b', 'source' => 'btn', 'href' => 'https://x.org/', 'label' => '', 'mark' => 'external', 'tab' => '', 'draft' => false),
				array('nodeId' => 'm', 'source' => 'menu', 'name' => 'Top', 'links' => array(
					array('href' => '/home', 'label' => 'Home', 'mark' => 'tab', 'tab' => 'p1'),
					array('href' => '', 'label' => 'Private', 'mark' => 'hidden', 'tab' => ''),
				)),
				array('nodeId' => 'g', 'source' => 'menu', 'name' => 'Gone', 'links' => null),
			)),
			array('key' => 'p2', 'name' => 'about', 'hotspots' => array()),
		),
		'shared' => array(
			array('id' => 5, 'name' => 'Header', 'pages' => 2, 'loaded' => true, 'hotspots' => array(
				array('nodeId' => 'h', 'source' => 'href', 'href' => 'https://x.org/', 'label' => 'X', 'mark' => 'external', 'tab' => '', 'draft' => false),
				array('nodeId' => 'i', 'source' => 'href', 'href' => '/old', 'label' => 'Old', 'mark' => 'broken', 'tab' => '', 'draft' => false),
				array('nodeId' => 'j', 'source' => 'text', 'href' => '#top', 'label' => 'Top', 'mark' => 'anchor', 'tab' => '', 'draft' => false),
			)),
		),
	);
	$result = proto_hotspots_test_node('var l = H.layout(' . proto_hotspots_test_js($marked) . ', { "s:5": { x: 10, y: 900 }, "c:0": { x: "bad" } });'
		. 'out = { frames: l.frames.map(function (f) { return [f.id, f.x, f.y, f.h, f.rows.map(function (r) { return r.port + " " + r.label + " " + r.mark; })]; }),'
		. ' chips: l.chips.map(function (c) { return [c.id, c.mark, c.href, c.x, c.y]; }), edges: l.edges.map(function (e) { return e.id + ">" + e.to; }) };');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('p:p1', 408, 0, 44 + 30 + 10, array('h0 About tab', 'h1 https://x.org/ external', 'h2 Home tab', 'h3 Private hidden', 'h4 Gone broken')),
		array('p:p2', 752, 0, 44 + 30 + 10, array()),
		array('s:5', 10, 900, 44 + 30 + 10, array('h0 X external', 'h1 Old broken', 'h2 Top anchor')),
	), $result['frames'], 'the frames, their rows and places; the rows take no room (the list carries them)');
	pg_assert_same(array(
		array('c:2', 'anchor', '#top', 1160, 0),
		array('c:0', 'external', 'https://x.org/', 1160, 48),
		array('c:1', 'broken', '/old', 1160, 96),
	), $result['chips'], 'one chip per mark and href, grouped by mark; a bad saved place ignored');
	pg_assert_same(array('p:p1|h0>p:p2', 'p:p1|h1>c:0', 'p:p1|h2>p:p1', 's:5|h0>c:0', 's:5|h1>c:1', 's:5|h2>c:2'), $result['edges'], 'an arrow per row that leads somewhere');

	// With the view's labels: a heading over each side column.
	$labelled = proto_hotspots_test_node('var l = H.layout(' . proto_hotspots_test_js($marked) . ', {}, { shared: "Shared parts", outside: "Outside this design" });'
		. 'out = { at: ["p:p1", "s:5", "c:2", "c:0", "c:1"].map(function (id) { var f = l.frames.concat(l.chips).filter(function (i) { return i.id === id; })[0]; return [f.x, f.y]; }), headings: l.headings, start: l.start };');

	pg_assert_same(array(array(408, 0), array(0, 40), array(1160, 40), array(1160, 88), array(1160, 136)), $labelled['at'], 'shared frames left under their heading, chips right under theirs');
	pg_assert_same(array(
		array('id' => 'shared', 'label' => 'Shared parts', 'x' => 0, 'y' => 0, 'w' => 248, 'kind' => 'side'),
		array('id' => 'outside', 'label' => 'Outside this design', 'x' => 1160, 'y' => 0, 'w' => 200, 'kind' => 'side'),
	), $labelled['headings'], 'the side columns\' headings');
	pg_assert_same('p:p1', $labelled['start'], 'the first tab is the start');
}

// The arrangement of a large design: forty pages in three sections (two
// tab groups and the pages in none) give eight a row in three bands, each
// band headed; inside a band the flow from the first tab, breadth-first,
// the strip's order between equals, unreached pages last; the start frame
// is marked; a saved place wins.
function test_proto_hotspots_layout_arranges_sections_in_flow_order()
{
	$pages = array();

	for ($i = 1; $i <= 40; $i++) {
		$pages[] = array('key' => 't' . $i, 'name' => 'page' . $i, 'hotspots' => array());
	}

	$link = function ($to) {
		return array('nodeId' => 'n' . $to, 'source' => 'href', 'href' => '/page' . $to, 'label' => 'Go', 'mark' => 'tab', 'tab' => 't' . $to, 'draft' => false);
	};
	$pages[0]['hotspots'] = array($link(5), $link(30), $link(1));
	// The start has a tree: its frame, the picture's height, is the tallest.
	$pages[0]['sketch'] = array();
	$pages[4]['hotspots'] = array($link(2));
	$sections = array(
		array('id' => 'g1', 'label' => 'Shop', 'keys' => array_map(function ($i) { return 't' . $i; }, range(1, 20))),
		array('id' => 'g2', 'label' => 'Blog', 'keys' => array_map(function ($i) { return 't' . $i; }, range(21, 32))),
	);
	$result = proto_hotspots_test_node('var d = ' . proto_hotspots_test_js(array('pages' => $pages, 'shared' => array())) . ';'
		. ' var opts = { sections: ' . proto_hotspots_test_js($sections) . ', rest: "Pages" };'
		. ' var l = H.layout(d, { "p:t40": { x: 5, y: 6 } }, opts);'
		. ' var at = {}; l.frames.forEach(function (f) { at[f.id.substr(2)] = [f.x, f.y]; });'
		. ' out = { at: at, headings: l.headings.map(function (h) { return [h.id, h.label, h.x, h.y, h.w]; }), start: l.frames.filter(function (f) { return f.start; }).map(function (f) { return f.id; }),'
		. ' again: JSON.stringify(H.layout(d, {}, opts)) === JSON.stringify(H.layout(d, {}, opts)), plain: H.layout(d, {}).headings.length };');

	if ($result === null) {
		return;
	}

	$at = $result['at'];
	pg_assert_same(array(0, 40), $at['t1'], 'the start first');
	pg_assert_same(array(array(344, 40), array(688, 40), array(1032, 40)), array($at['t5'], $at['t2'], $at['t3']), 'then the flow, then the unreached in the strip\'s order');
	pg_assert_same(array(array(0, 488), array(0, 668)), array($at['t9'], $at['t17']), 'eight a row, a row as high as its tallest frame');
	pg_assert_same(array(array(0, 888), array(344, 888), array(344, 1068)), array($at['t30'], $at['t21'], $at['t29']), 'a page reached from the start leads its own band');
	pg_assert_same(array(0, 1288), $at['t33'], 'the pages in no group last');
	pg_assert_same(array(5, 6), $at['t40'], 'a saved place wins');
	pg_assert_same(array(array('g1', 'Shop', 0, 0, 2656), array('g2', 'Blog', 0, 848, 2656), array('pages', 'Pages', 0, 1248, 2656)), $result['headings'], 'a heading for each band');
	pg_assert_same(array('p:t1'), $result['start'], 'only the first tab is the start');
	pg_assert_true($result['again'], 'the same answer for the same design');
	pg_assert_same(0, $result['plain'], 'without sections one band and no heading');

	// A first tab in no group: the pages in none lead, the start in the
	// grid's first cell; the groups follow in the strip's order.
	$lead = proto_hotspots_test_node('var d = { pages: ["home", "a1", "a2", "about"].map(function (k) { return { key: k, name: k, hotspots: [] }; }), shared: [] };'
		. ' var l = H.layout(d, {}, { sections: [{ id: "g", label: "Group", keys: ["a1", "a2"] }], rest: "Pages" });'
		. ' out = { at: l.frames.map(function (f) { return [f.id, f.x, f.y]; }), headings: l.headings.map(function (h) { return [h.id, h.y]; }) };');
	pg_assert_same(array(array('p:home', 0, 40), array('p:a1', 0, 260), array('p:a2', 344, 260), array('p:about', 344, 40)), $lead['at'], 'the start\'s band first, the start in its first cell');
	pg_assert_same(array(array('pages', 0), array('g', 220)), $lead['headings'], '"Pages" first, then the group');
}

// Many shared parts (F4): fifty shared frames beside forty pages wrap
// into columns no taller than the grid, at least four frames each, under
// one heading; at 1440 that design then fits whole; one too large for the
// smallest zoom fits to the start frame's corner (its band's heading
// included), a small one to the middle;
// the view hands the corner to the core.
function test_proto_hotspots_many_shared_parts()
{
	$result = proto_hotspots_test_node(<<<'JS'
var pages = [], shared = [];
for (var i = 1; i <= 40; i++) { pages.push({ key: 't' + i, name: 'page' + i, sketch: [], hotspots: [] }); }
for (var j = 1; j <= 50; j++) { shared.push({ id: j, name: 'Part ' + j, pages: 2, loaded: true, hotspots: [] }); }
var l = H.layout({ pages: pages, shared: shared }, {}, { shared: 'Shared parts', sections: [{ id: 'g', label: 'Shop', keys: ['t1', 't2'] }], rest: 'Pages' });
var parts = l.frames.filter(function (f) { return f.kind === 'shared'; });
var tabs = l.frames.filter(function (f) { return f.kind === 'page'; });
var xs = {};
parts.forEach(function (f) { xs[f.x] = (xs[f.x] || 0) + 1; });
var gridBottom = Math.max.apply(null, tabs.map(function (f) { return f.y + f.h; }));
var partsBottom = Math.max.apply(null, parts.map(function (f) { return f.y + f.h; }));
function fitOn(layout, width) {
    var state = { view: { x: 0, y: 0, k: 1 } };
    function Box() { this.style = {}; }
    var ui = { viewport: new Box(), layer: new Box(), zoomLabel: new Box() };
    ui.viewport.getBoundingClientRect = function () { return { left: 0, top: 0, width: width, height: 700 }; };
    var view = window.PGGraphCanvas.viewport({
        ui: ui,
        view: function (next) { if (next) { state.view = next; } return state.view; },
        items: function () { return layout.frames.concat(layout.chips); },
        size: function (item) { return { w: item.w, h: item.h }; },
        zoomMin: 0.1,
        start: function () { return H.fitStart(layout); }
    });
    view.fit();
    return [Math.round(state.view.x * 10) / 10, Math.round(state.view.y * 10) / 10, state.view.k];
}
var small = H.layout({ pages: pages.slice(0, 2), shared: [] }, {}, {});
out = {
    columns: Object.keys(xs).map(Number).sort(function (a, b) { return a - b; }).map(function (x) { return xs[x]; }),
    below: partsBottom > gridBottom,
    heading: l.headings.filter(function (h) { return h.id === 'shared'; })[0].w,
    start: l.frames.filter(function (f) { return f.start; }).map(function (f) { return [f.id, f.x, f.y]; })[0],
    corner: H.fitStart(l),
    none: H.fitStart({ frames: [], headings: [] }),
    wide: fitOn(l, 1440),
    phone: fitOn(l, 390),
    small: fitOn(small, 1440)
};
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(count($result['columns']) > 1, 'fifty shared frames go on in further columns');
	pg_assert_true(min($result['columns']) >= 4, 'at least four frames a column');
	pg_assert_false($result['below'], 'no shared frame below the page grid');
	pg_assert_same(count($result['columns']) * 248 + (count($result['columns']) - 1) * 96, $result['heading'], 'one heading over every column');
	$start = $result['start'];
	pg_assert_same(array('x' => $start[1], 'y' => $start[2] - 40), $result['corner'], 'the corner is the start frame\'s, with its band\'s heading');
	pg_assert_same(null, $result['none'], 'no start frame, no corner');
	pg_assert_true($result['wide'][2] > 0.1, 'at 1440 the wrapped design fits whole');
	pg_assert_same(array((int) round(48 - $start[1] * 0.1), (int) round(48 - ($start[2] - 40) * 0.1), 0.1), $result['phone'], 'at 390 too large: the corner 48 px in at 10%');
	pg_assert_same(1.25, $result['small'][2], 'a small design fits as before');
	pg_assert_true($result['small'][0] > 48, 'and stands in the middle');
}

// The list beside the canvas (F15) and the problems (F19, F20), from the
// marked design: the selected page's links out with their targets, a
// broken one with why; the links into it, a shared part's said so; the
// shared parts on the page, nested ones too; nothing selected and a chip
// give none; every broken link of the design and each frame's count.
function test_proto_hotspots_list_and_problems()
{
	$tab = function ($node, $label, $key) {
		return array('nodeId' => $node, 'source' => 'href', 'href' => '/' . $key, 'label' => $label, 'mark' => 'tab', 'tab' => $key, 'draft' => false);
	};
	$marked = array(
		'pages' => array(
			array('key' => 'p1', 'name' => 'home', 'shared' => array(5), 'hotspots' => array(
				$tab('a', 'About', 'p2'),
				array('nodeId' => 'b', 'source' => 'btn', 'href' => 'https://x.org/', 'label' => '', 'mark' => 'external', 'tab' => '', 'draft' => false),
				array('nodeId' => 'm', 'source' => 'menu', 'name' => 'Top', 'links' => array(
					array('href' => '/home', 'label' => 'Home', 'mark' => 'tab', 'tab' => 'p1'),
				)),
				array('nodeId' => 'g', 'source' => 'menu', 'name' => 'Gone', 'links' => null),
			)),
			array('key' => 'p2', 'name' => 'about', 'shared' => array(5), 'hotspots' => array()),
			array('key' => 'p3', 'name' => '', 'shared' => array(), 'hotspots' => array($tab('c', 'Back', 'p2'))),
		),
		'shared' => array(
			array('id' => 5, 'name' => 'Header', 'pages' => 2, 'loaded' => true, 'shared' => array(6), 'hotspots' => array(
				$tab('h', 'Contact', 'p2'),
				array('nodeId' => 'i', 'source' => 'href', 'href' => '/old', 'label' => 'Old', 'mark' => 'broken', 'tab' => '', 'draft' => false),
				array('nodeId' => 'j', 'source' => 'href', 'href' => '/kept', 'label' => 'Kept', 'mark' => 'broken', 'tab' => '', 'draft' => false, 'bound' => '77'),
				array('nodeId' => 'k', 'source' => 'btn', 'href' => '', 'label' => 'Empty', 'mark' => 'broken', 'tab' => '', 'draft' => false),
			)),
			array('id' => 6, 'name' => 'Logo', 'pages' => 2, 'loaded' => true, 'shared' => array(), 'hotspots' => array()),
		),
	);
	$result = proto_hotspots_test_node('var l = H.layout(' . proto_hotspots_test_js($marked) . ', {});'
		. ' out = { home: H.listOf(l, "p:p1"), about: H.listOf(l, "p:p2"), header: H.listOf(l, "s:5"), none: H.listOf(l, null), chip: H.listOf(l, "c:0"), gone: H.listOf(l, "p:zz"), broken: H.brokenOf(l) };');

	if ($result === null) {
		return;
	}

	$out = function ($list) {
		return array_map(function ($row) {
			return array($row['port'], $row['label'], $row['mark'], $row['to'], $row['target'], $row['why'], $row['drawable']);
		}, $list['outgoing']);
	};
	pg_assert_same(array('id' => 'p:p1', 'kind' => 'page', 'name' => 'home'), array_intersect_key($result['home'], array('id' => 1, 'kind' => 1, 'name' => 1)), 'the selected frame');
	pg_assert_same(array(
		array('h0', 'About', 'tab', 'p:p2', 'about', '', true),
		array('h1', 'https://x.org/', 'external', 'c:0', 'https://x.org/', '', true),
		array('h2', 'Top › Home', 'tab', 'p:p1', 'home', '', false),
		array('h3', 'Gone', 'broken', '', '', 'menu', false),
	), $out($result['home']), 'outgoing: label, mark, target; a menu link read-only; a menu not read is broken by its menu');
	pg_assert_same(array(), $result['home']['incoming'], 'nothing leads into it but itself');
	pg_assert_same(array(array('id' => 's:5', 'name' => 'Header'), array('id' => 's:6', 'name' => 'Logo')), $result['home']['parts'], 'its shared parts, nested ones too');
	pg_assert_same(array(
		array('frame' => 'p:p1', 'port' => 'h0', 'label' => 'About', 'source' => 'home', 'shared' => false),
		array('frame' => 'p:p3', 'port' => 'h0', 'label' => 'Back', 'source' => '', 'shared' => false),
		array('frame' => 's:5', 'port' => 'h0', 'label' => 'Contact', 'source' => 'Header', 'shared' => true),
	), $result['about']['incoming'], 'incoming: source and label, a shared part said so');
	pg_assert_same(array(), $result['about']['outgoing'], 'a page with no links: none out');
	pg_assert_same(array('page', 'kept', 'address'), array_map(function ($row) { return $row['why']; }, array_slice($result['header']['outgoing'], 1)), 'why each is broken: no page at it, its page gone, no address');
	pg_assert_same(array(), $result['header']['parts'], 'a shared frame lists no parts');
	pg_assert_same(array(null, null, null), array($result['none'], $result['chip'], $result['gone']), 'nothing selected, a chip or no such frame: no list');
	pg_assert_same(array(
		array('frame' => 'p:p1', 'port' => 'h3', 'label' => 'Gone', 'href' => '', 'why' => 'menu'),
		array('frame' => 's:5', 'port' => 'h1', 'label' => 'Old', 'href' => '/old', 'why' => 'page'),
		array('frame' => 's:5', 'port' => 'h2', 'label' => 'Kept', 'href' => '/kept', 'why' => 'kept'),
		array('frame' => 's:5', 'port' => 'h3', 'label' => 'Empty', 'href' => '', 'why' => 'address'),
	), $result['broken']['list'], 'every broken link of the design, in the frames\' order');
	pg_assert_same(array('p:p1' => 1, 's:5' => 3), $result['broken']['count'], 'each frame\'s count');
}

// The view's links panel and problems (F15-F20, F24): an aside beside the
// stage drawn from listOf(), never from the canvas; a frame row and a list
// row pick one link; the target control and "Open in the editor" go through
// today's menu and row opening; "N broken" and its list and each frame's
// badge from brokenOf(); the panel's open state per browser, closed under
// 1,100 px; Play through _pgPrototypeUrl().
function test_proto_hotspots_view_list_and_problems()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	pg_assert_contains("ui.list = _sdProtoEl('aside', 'sd-proto-list');", $source, 'the panel is an aside');
	pg_assert_contains('ui.body.appendChild(ui.stage);', $source, 'beside the stage');
	pg_assert_contains('window.PGProtoHotspots.listOf(_sdProto.layout, canvas.focused())', $source, 'its rows from the marked layout');
	pg_assert_contains("if (done.kind === 'row' && !done.moved && event.type === 'pointerup') _sdProtoPickRow(done.row);", $source, 'a link\'s mark on the picture picks the link');
	pg_assert_contains('var change = function (anchor) { return _sdProtoMenuOpen(data.id, row.port, anchor); };', $source, 'the row\'s target control opens today\'s target menu at the list row, whether or not the picture shows the link');
	pg_assert_contains('_sdProtoOpenRow(data.id, row.port);', $source, '"Open in the editor" opens the element by its frame and port');
	pg_assert_contains('if (other) canvas.view.reveal(other);', $source, 'a picked link\'s other end comes into view');
	pg_assert_contains('_sdProto.issues = H.problemsOf(l);', $source, 'the broken links and what to check from the marked layout');
	pg_assert_contains("var badge = _sdProtoEl('span', 'sd-proto-badge', String(brokenHere));", $source, 'a frame\'s badge');
	pg_assert_contains("ui.broken.textContent = broken ? _sdT('{var:1} broken', broken) : '';", $source, '"N broken" in the bar');
	pg_assert_contains("window.localStorage.getItem('pg_proto_list')", $source, 'the panel\'s state per browser');
	pg_assert_contains("window.matchMedia('(min-width: 1100px)').matches", $source, 'closed by default under 1,100 px');
	pg_assert_contains('var url = page ? _pgPrototypeUrl(page) : \'\';', $source, 'Play opens the viewer at the selected page');
	pg_assert_contains('ui.play.hidden = !_sdProtoPlayUrl();', $source, 'and is not shown without an address');
	pg_assert_contains(".sd-proto-bar { flex-wrap: wrap; row-gap: 2px; }", $css, 'on a phone the bar wraps, so every tool stays in reach');
	foreach (array('.sd-proto-list-open .sd-proto-list', '.sd-proto-broken', '.sd-proto-problems', '.sd-proto-badge', '.sd-proto-hot.sd-proto-hot-picked', '.sd-proto-edge-picked') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

// The view turns on the core's right-angle arrows (F14).
function test_proto_hotspots_view_routes_arrows()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($source, '_sdProto.canvas = G.create({');
	$adapter = substr($source, (int) $start, 4000);
	pg_assert_true($start !== false, 'the view makes its canvas');
	pg_assert_contains("route: 'elbow',", $adapter, 'the view asks for right-angle arrows');
	pg_assert_contains('routeMargin: 40,', $adapter, 'arrows keep 40 clear of a frame, room for lanes in a 96 gap');
	pg_assert_contains('size: function (item) { return { w: item.w, h: item.h }; },', $adapter, 'the core knows each frame\'s size to keep clear of it');
}

// The view hands the start corner to the core's fit.
function test_proto_hotspots_view_fits_to_the_start_corner()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('start: function () { return _sdProto.layout ? window.PGProtoHotspots.fitStart(_sdProto.layout) : null; },', $source, 'the view passes the start frame\'s corner');
	pg_assert_contains("before: { id: 'shared', label: str(opts.shared), w: FRAME.w, maxH: 'grid', items: sharedIds },", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_hotspots.js'), 'the shared column wraps at the grid\'s height');
}

// Which rows an arrow may be drawn from and where it may end: a link of
// the tree (not a classic menu or region), to a tab frame by its page name
// under the site path, or to a chip of a site page, another site or a
// contact address; never to a shared frame, an anchor, a hidden or broken
// chip, or a tab without a name.
function test_proto_hotspots_draw_targets()
{
	$result = proto_hotspots_test_node('var env = { path: "/site/" };'
		. 'out = { rows: ["href", "attrs", "btn", "text", "menu", "region"].map(function (s) { return H.drawable({ source: s, from: (s === "menu" || s === "region") ? "Top" : "" }); }),'
		. ' menuLink: H.drawable({ source: "href", from: "Top" }),'
		. ' to: [{ kind: "page", name: "about" }, { kind: "page", name: "Our Team & Co" }, { kind: "page", name: "" }, { kind: "shared", name: "Header" },'
		. '  { kind: "chip", mark: "page", href: "/shop" }, { kind: "chip", mark: "external", href: "https://x.org/" }, { kind: "chip", mark: "contact", href: "mailto:a@b.c" },'
		. '  { kind: "chip", mark: "anchor", href: "#top" }, { kind: "chip", mark: "hidden", href: "/secret" }, { kind: "chip", mark: "broken", href: "/old" }, null].map(function (t) { return H.hrefTo(t, env); }),'
		. ' bare: H.hrefTo({ kind: "page", name: "about" }, {}) };');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true, false, false), $result['rows'], 'links of the tree are drawn, menus and regions stay read-only');
	pg_assert_same(false, $result['menuLink'], 'a row of a menu is read-only');
	pg_assert_same(array('/site/about', '/site/Our%20Team%20%26%20Co', null, null, '/shop', 'https://x.org/', 'mailto:a@b.c', null, null, null, null), $result['to'], 'the href each target gives');
	pg_assert_same('/about', $result['bare'], 'the site root when the read names no path');
}

// The keyboard menu lists the tab frames, then the chips an arrow may end
// on, each href once.
function test_proto_hotspots_draw_target_list()
{
	$laid = array(
		'frames' => array(
			array('id' => 'p:a', 'kind' => 'page', 'name' => 'home'),
			array('id' => 'p:b', 'kind' => 'page', 'name' => ''),
			array('id' => 's:5', 'kind' => 'shared', 'name' => 'Header'),
			array('id' => 'p:c', 'kind' => 'page', 'name' => 'Home'),
		),
		'chips' => array(
			array('id' => 'c:0', 'kind' => 'chip', 'mark' => 'external', 'href' => 'https://x.org/'),
			array('id' => 'c:1', 'kind' => 'chip', 'mark' => 'broken', 'href' => '/old'),
			array('id' => 'c:2', 'kind' => 'chip', 'mark' => 'page', 'href' => '/home'),
		),
	);
	$result = proto_hotspots_test_node('out = H.drawTargets(' . proto_hotspots_test_js($laid) . ', { path: "/" });');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('id' => 'p:a', 'href' => '/home'),
		array('id' => 'p:c', 'href' => '/Home'),
		array('id' => 'c:0', 'href' => 'https://x.org/'),
	), $result, 'frames first, then chips, each href once');
}

// The nth link of a piece of markup gets a new href, in double quotes and
// escaped; the rest of the markup stays byte for byte; a link that is not
// there answers null.
function test_proto_hotspots_set_html_href()
{
	$html = 'See <a href="/terms">terms</a>, <!-- <a href="/c"> --><a href=\'#top\' data-bs-toggle="tab">tab</a> or <A HREF=/draft-page target=_blank>draft</A>';
	$result = proto_hotspots_test_node('var h = ' . proto_hotspots_test_js($html) . ';'
		. 'out = [H.setHtmlHref(h, 0, "/a?x=1&y=\"2\""), H.setHtmlHref(h, 2, "/new"), H.setHtmlHref(h, 3, "/none"), H.htmlLinks(H.setHtmlHref(h, 0, "/a?x=1&y=2"))[0].href];');

	if ($result === null) {
		return;
	}

	pg_assert_same('See <a href="/a?x=1&amp;y=&quot;2&quot;">terms</a>, <!-- <a href="/c"> --><a href=\'#top\' data-bs-toggle="tab">tab</a> or <A HREF=/draft-page target=_blank>draft</A>', $result[0], 'the first link, escaped');
	pg_assert_same('See <a href="/terms">terms</a>, <!-- <a href="/c"> --><a href=\'#top\' data-bs-toggle="tab">tab</a> or <A HREF="/new" target=_blank>draft</A>', $result[1], 'an unquoted href, the comment skipped');
	pg_assert_same(null, $result[2], 'no such link');
	pg_assert_same('/a?x=1&y=2', $result[3], 'the collector reads the new href back');
}

// What a drawn arrow writes into its node: the href prop, the _attrs href
// (other attributes kept), a button's href, or the text or custom HTML with
// the nth link changed; null when the node no longer holds the href the
// map showed, so a stale map never writes over a newer link.
function test_proto_hotspots_href_patch()
{
	$tree = proto_hotspots_test_tree();
	$result = proto_hotspots_test_node('var t = ' . proto_hotspots_test_js($tree) . ', c = t.children, n = c[0].children;'
		. 'out = ['
		. ' H.hrefPatch(n[0], { source: "href", href: "/about", index: 0 }, "/team"),'
		. ' H.hrefPatch(n[1], { source: "attrs", href: "/shop", index: 0 }, "/store"),'
		. ' H.hrefPatch(c[1], { source: "btn", href: "mailto:a@example.com", index: 0 }, "/buy"),'
		. ' H.hrefPatch(c[3], { source: "text", href: "/draft-page", index: 2 }, "/final"),'
		. ' H.hrefPatch(c[10], { source: "text", href: "/x?a=1&b=2", index: 0 }, "/y"),'
		. ' H.hrefPatch(c[5], { source: "href", href: "#", index: 0 }, "/filled"),'
		. ' H.hrefPatch(n[0], { source: "href", href: "/old", index: 0 }, "/team"),'
		. ' H.hrefPatch(c[3], { source: "text", href: "/terms", index: 2 }, "/final"),'
		. ' H.hrefPatch(c[7], { source: "menu", href: "", index: 0 }, "/x"),'
		. ' n[1].props._attrs'
		. '];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('key' => 'href', 'value' => '/team'), $result[0], 'the href prop');
	pg_assert_same(array('key' => '_attrs', 'value' => array(array('name' => 'href', 'value' => '/store'))), $result[1], 'the _attrs href, a copy');
	pg_assert_same(array(array('name' => 'href', 'value' => '/shop')), $result[9], 'the node itself is not changed');
	pg_assert_same(array('key' => 'href', 'value' => '/buy'), $result[2], 'a button');
	pg_assert_same(array('key' => 'text', 'value' => 'See <a href="/terms">terms</a> and <a href=\'#top\' data-bs-toggle="tab">tab</a> or <a href="/final" target=_blank>draft</a>'), $result[3], 'the nth link of a text');
	pg_assert_same(array('key' => 'html', 'value' => '<!-- <a href="/c"> --><script>var a = "<a href=/s>";</script><a href="/y">X</a>'), $result[4], 'a link of custom HTML');
	pg_assert_same(array('key' => 'href', 'value' => '/filled'), $result[5], 'an empty address shown as #');
	pg_assert_same(null, $result[6], 'a stale href');
	pg_assert_same(null, $result[7], 'a stale text link');
	pg_assert_same(null, $result[8], 'a menu is not written');
}

// The designer prints the canvas core, the resolver and the collector
// without defer before style_designer.min.js, which reads them; the view
// has its toolbar button and reads the prototype_map answer.
function test_proto_hotspots_printed_before_the_designer()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	$designer = strpos($screen, "pg_js_bundle_tags('assets/js/style_designer.min.js', false)");

	foreach (array('pg_graph_canvas', 'pg_link_resolve', 'pg_proto_hotspots') as $name) {
		$at = strpos($screen, "pg_js_bundle_tags('assets/js/" . $name . ".min.js', false)");
		pg_assert_true(($at !== false) && ($designer !== false) && ($at < $designer), $name . ' is printed without defer before the designer');
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains('id="sd-vb-proto"', $source, 'the toolbar button');
	pg_assert_contains("_pgApiPost('prototype_map', designed.request)", $source, 'the view asks the map read');
}

// The view draws arrows through the core's connect drag, on the row's tab,
// and keeps its keys from the editor's document shortcuts.
function test_proto_hotspots_view_draws_arrows()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains('canConnect: function (from, to, port) { return _sdProtoArrowHref(from, to, port) !== null; }', $source, 'the core asks the view where an arrow may end');
	pg_assert_contains('connected: function (from, to, port) { _sdProtoConnect(from, to, port); }', $source, 'a drawn arrow writes the link');
	pg_assert_contains("if (e.target.closest && e.target.closest('#sd-proto')) return;", $source, 'the document shortcuts leave the view alone');
	pg_assert_contains('var patch = node ? H.hrefPatch(node, row, href, to) : null;', $source, 'the node gets the collector\'s patch');
	pg_assert_contains('H.applyPatch(node.props, patch);', $source, 'the patch is written with its page binding');
	pg_assert_contains('if (menuKey && row.drawable && change(main)) event.preventDefault();', $source, 'L on a links panel row opens its target menu');
	pg_assert_contains('_sdProtoConnect(item, target, row.port);', $source, 'a menu choice goes the drawn arrow\'s way');
}

// The view hands layout() the tab groups as sections and the side
// columns' headings, says "Start" on the start frame, and Tidy up clears
// the places after a confirmation: the shared ones only for an operator who
// may arrange the tabs, else only this browser's, saying so.
function test_proto_hotspots_view_arrangement_and_tidy_up()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
	$start = strpos($source, '    function _sdProtoTidy() {');
	$tidy = ($start !== false) ? substr($source, $start, strpos($source, "\n    }\n", $start) - $start) : '';

	pg_assert_contains('var l = H.layout(marked, _sdProtoSaved(), _sdProtoSections());', $source, 'the view hands the sections');
	pg_assert_contains("byGroup[gid] = { id: gid, label: _pgTabGroupLabel(gid), keys: [] };", $source, 'a section per tab group, named as the strip names it');
	pg_assert_contains("return { sections: sections, rest: _sdT('Pages'), shared: _sdT('Shared parts'), outside: _sdT('Outside this design'), partH: P.partH, openH: openH };", $source, 'the headings are translated');
	pg_assert_contains("if (item.start) head.appendChild(_sdProtoEl('span', 'sd-proto-start', _sdT('Start')));", $source, 'the start frame says so');
	pg_assert_contains("var tidy = _sdProtoButton('bi-grid-3x3-gap', _sdT('Tidy up'), function () { _sdProtoTidy(); });\n        tidy.appendChild(_sdProtoEl('span', 'sd-proto-btn-label', _sdT('Tidy up')));", $source, 'Tidy up sits in the bar with its name');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	pg_assert_true((bool) preg_match('/@media \(max-width: 767\.98px\) \{\s*\.sd-proto-btn-label \{ display: none; \}/', $css), 'the name is hidden on a phone only');
	pg_assert_contains('var shared = _pgTabLayoutEditable() && !!(_design && _design.styleId) && _collab.mayEdit !== false;', $tidy, 'the shared places only for an operator who may arrange the tabs, never in view mode');
	pg_assert_contains('if (!window.confirm(msg)) return;', $tidy, 'nothing before the confirmation');
	pg_assert_contains("_pgTabProto = {};\n            _pgTabsLayoutSave();", $tidy, 'the shared places cleared through the tab layout save');
	pg_assert_contains('_sdProtoLocalWrite({});', $tidy, 'and this browser\'s');
	pg_assert_contains("if (!shared) sdToast(esc(_sdT('Only the places in this browser were cleared.'))", $tidy, 'anyone else is told only the browser\'s were');
}

// focusOf(): what the selected frame shows. A page: its arrows out, the
// arrows into it from other pages, and a shared part's link into it as no
// arrow but a note and a row mark; the rows on other frames that start a
// drawn arrow. A shared frame: its own arrows out. None: nothing.
function test_proto_hotspots_focus_of_a_frame()
{
	$link = function ($node, $label, $tab) {
		return array('nodeId' => $node, 'source' => 'href', 'href' => '/' . $tab, 'label' => $label, 'mark' => 'tab', 'tab' => $tab, 'draft' => false);
	};
	$marked = array(
		'pages' => array(
			array('key' => 'home', 'name' => 'home', 'hotspots' => array($link('a', 'Shop', 'shop'), $link('b', 'Cart', 'cart'), array('nodeId' => 'x', 'source' => 'href', 'href' => 'https://x.org/', 'label' => 'X', 'mark' => 'external', 'tab' => '', 'draft' => false))),
			array('key' => 'shop', 'name' => 'shop', 'hotspots' => array($link('c', 'Buy', 'cart'), $link('d', 'Self', 'shop'))),
			array('key' => 'cart', 'name' => 'cart', 'hotspots' => array()),
		),
		'shared' => array(
			array('id' => 5, 'name' => 'Header', 'pages' => 3, 'loaded' => true, 'hotspots' => array($link('h', 'Cart icon', 'cart'), $link('i', 'Home', 'home'))),
		),
	);
	$out = proto_hotspots_test_node('var l = H.layout(' . proto_hotspots_test_js($marked) . ', {});'
		. ' out = { cart: H.focusOf(l, "p:cart"), shop: H.focusOf(l, "p:shop"), header: H.focusOf(l, "s:5"), none: H.focusOf(l, null), gone: H.focusOf(l, "p:nope") };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'out' => array(),
		'in' => array('p:home|h1', 'p:shop|h0'),
		'shared' => array(array('frame' => 's:5', 'port' => 'h0', 'edge' => 's:5|h0', 'label' => 'Cart icon', 'to' => 'cart')),
		'lit' => array('p:home' => array('h1'), 'p:shop' => array('h0')),
	), $out['cart'], 'a page: arrows in from pages, a shared part\'s link as a note and no arrow');
	pg_assert_same(array(
		'out' => array('p:shop|h0', 'p:shop|h1'),
		'in' => array('p:home|h0'),
		'shared' => array(),
		'lit' => array('p:home' => array('h0')),
	), $out['shop'], 'its arrows out (a link to itself too) and in');
	pg_assert_same(array('out' => array('s:5|h0', 's:5|h1'), 'in' => array(), 'shared' => array(), 'lit' => array()), $out['header'], 'a shared frame draws its own arrows');
	$empty = array('out' => array(), 'in' => array(), 'shared' => array(), 'lit' => array());
	pg_assert_same($empty, $out['none'], 'nothing selected, nothing drawn');
	pg_assert_same($empty, $out['gone'], 'a frame no longer there, nothing drawn');
}

// The view draws only the selected frame's arrows: it passes the core's
// focus, hears each change to mark the rows, draws the section headings,
// selects the open tab's frame on opening, keeps "All arrows" per browser,
// hides a shared part's arrow into the selected page and themes the three
// kinds of arrow with their own heads.
function test_proto_hotspots_view_focus_and_all_arrows()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains('focus: true,', $source, 'the view passes the focus');
	pg_assert_contains('focusChanged: function (id) { _sdProtoFocused(id); },', $source, 'a change of focus marks the rows again and draws the links panel');
	pg_assert_contains('groups: function () { return _sdProto.layout ? _sdProto.layout.headings : []; },', $source, 'the section headings come from the layout');
	pg_assert_contains("canvas.groups();", $source, 'and are drawn with each draw');
	pg_assert_contains("var focusOn = (first || _sdProto.focusOpen) ? 'p:' + _activeKey : canvas.focused();", $source, 'opening selects the open tab\'s frame');
	pg_assert_contains("window.localStorage.getItem('pg_proto_all_edges') === '1'", $source, '"All arrows" is kept in this browser');
	pg_assert_contains("_sdProtoButton('bi-diagram-3', _sdT('All arrows')", $source, '"All arrows" sits in the bar');
	pg_assert_contains("if (_sdProto.byId[e.from].kind === 'shared') drawn.group.classList.add('sd-proto-from-shared');", $source, 'a shared part\'s arrows are told apart');
	pg_assert_contains('var f = window.PGProtoHotspots.focusOf(_sdProto.layout, id);', $source, 'the marks come from focusOf()');
	pg_assert_contains('.sd-proto .pgc-edge.pgc-edge-in.sd-proto-from-shared { display: none; }', $css, 'a shared part\'s arrow into the selected page is not drawn');
	pg_assert_contains('.sd-proto .pgc-edge.pgc-edge-in .pgc-edge-line { stroke: var(--pgc-in); stroke-dasharray: 6 4; marker-end: url(#sd-proto-arrow-in); }', $css, 'an arrow in is dashed amber with its head');
	pg_assert_contains('.sd-proto .pgc-edge.sd-proto-edge-broken .pgc-edge-line { marker-end: url(#sd-proto-arrow-broken); }', $css, 'a broken one has its red head');
	foreach (array('sd-proto-arrow', 'sd-proto-arrow-on', 'sd-proto-arrow-in', 'sd-proto-arrow-broken') as $marker) {
		pg_assert_contains('<marker id="' . $marker . '"', $source, 'the ' . $marker . ' head is drawn');
	}
}

// The collector is a bundle of its own.
function test_proto_hotspots_is_bundled()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_proto_hotspots.js'), isset($bundles['assets/js/pg_proto_hotspots.min.js']) ? $bundles['assets/js/pg_proto_hotspots.min.js'] : null, 'the collector has its bundle');
}

// The frame places shared in the design's tab layout read as layout()'s
// `saved`, the twin of pg_designer_tab_layout_proto(), and the key a frame
// is shared under.
function test_proto_hotspots_shared_places()
{
	$proto = array(
		'p12' => array('x' => 10.6, 'y' => -20),
		's5'  => array('x' => '300', 'y' => 40),
		'c:0' => array('x' => 1, 'y' => 1),
		'p13' => array('x' => 'a', 'y' => 1),
		's0'  => array('x' => 1, 'y' => 1),
		'p14' => array('x' => 5e9, 'y' => -5e9),
		's6'  => 'junk',
	);
	$out = proto_hotspots_test_node('out = { places: H.places(' . proto_hotspots_test_js($proto) . '), none: H.places(null), keys: ["p:p12", "s:5", "p:new1_2", "c:0", "s:0", "x"].map(H.placeKey) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'p:p12' => array('x' => 11, 'y' => -20),
		's:5'   => array('x' => 300, 'y' => 40),
		'p:p14' => array('x' => 1000000, 'y' => -1000000),
	), $out['places'], 'the shared places by frame id');
	pg_assert_same(array(), $out['none'], 'no layout, no places');
	pg_assert_same(array('p12', 's5', '', '', '', ''), $out['keys'], 'saved tabs and shared components are shared, the rest not');

	// The PHP normaliser keeps the same places for the design's pages.
	$php = pg_designer_tab_layout_normalize(array('proto' => $proto), array(12, 14));
	$back = array();
	foreach ($php['proto'] as $key => $at) {
		$back[($key[0] === 'p' ? 'p:p' : 's:') . substr($key, 1)] = $at;
	}
	pg_assert_same($back, $out['places'], 'twin of pg_designer_tab_layout_proto()');
}

function test_proto_hotspots_layout_takes_shared_places()
{
	$out = proto_hotspots_test_node('var d = { pages: [{ key: "p12", name: "home", hotspots: [] }, { key: "p13", name: "about", hotspots: [] }], shared: [] };'
		. ' var l = H.layout(d, H.places({ p13: { x: 900, y: 50 } }));'
		. ' out = l.frames.map(function (f) { return [f.id, f.x, f.y]; });');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('p:p12', 0, 0), array('p:p13', 900, 50)), $out, 'a shared place moves its frame');
}

// A frame's thumbnail: one band per top-level node, through a lone wrapper.
function test_proto_hotspots_sketch_bands()
{
	$tree = array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'w', 'type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
			array('_id' => 'a', 'type' => 'semantic', 'props' => array('tag' => 'nav'), 'children' => array(
				array('_id' => 'a1', 'type' => 'content', 'props' => array('contentType' => 'link', 'href' => '/x'), 'children' => array()),
				array('_id' => 'a2', 'type' => 'content', 'props' => array('contentType' => 'link', 'href' => '/y'), 'children' => array()),
			)),
			array('_id' => 'b', 'type' => 'semantic', 'props' => array('tag' => 'section'), 'children' => array()),
			array('_id' => 'c', 'type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array()),
			array('_id' => 'd', 'type' => 'region', 'props' => array('regionType' => 'menu', 'regionName' => 'Top'), 'children' => array()),
			array('_id' => 'e', 'type' => 'component', 'props' => array('componentType' => 'footer-minimal'), 'children' => array()),
		)),
	));
	$many = array('_id' => 'root', 'type' => 'root', 'children' => array());
	for ($i = 0; $i < 14; $i++) {
		$many['children'][] = array('_id' => 'n' . $i, 'type' => 'semantic', 'props' => array('tag' => $i === 0 ? 'header' : 'section'), 'children' => array());
	}

	$out = proto_hotspots_test_node('out = { one: H.sketch(' . proto_hotspots_test_js($tree) . '), many: H.sketch(' . proto_hotspots_test_js($many) . '), none: H.sketch(null), empty: H.sketch({ type: "root", children: [] }) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array('role' => 'nav', 'weight' => 3, 'links' => 2),
		array('role' => 'block', 'weight' => 1, 'links' => 0),
		array('role' => 'shared', 'weight' => 1, 'links' => 0),
		array('role' => 'region', 'weight' => 1, 'links' => 1),
		array('role' => 'footer', 'weight' => 1, 'links' => 0),
	), $out['one'], 'the wrapper is looked through; roles, nodes and links per band');
	pg_assert_same(12, count($out['many']), 'at most twelve bands');
	pg_assert_same('header', $out['many'][0]['role'], 'header role');
	pg_assert_same(array('role' => 'block', 'weight' => 3, 'links' => 0), $out['many'][11], 'the rest joined into the last band');
	pg_assert_same(null, $out['none'], 'no tree, no sketch');
	pg_assert_same(array(), $out['empty'], 'an empty page has no bands');
}

function test_proto_hotspots_sketch_boxes_fill_the_box()
{
	$out = proto_hotspots_test_node('out = { boxes: H.sketchBoxes([{ role: "nav", weight: 1, links: 2 }, { role: "block", weight: 16, links: 0 }, { role: "footer", weight: 4, links: 1 }], 228, 71), none: H.sketchBoxes([], 228, 71), tight: H.sketchBoxes([{ role: "block", weight: 1, links: 0 }, { role: "block", weight: 100, links: 0 }], 100, 8) };');

	if ($out === null) {
		return;
	}

	$boxes = $out['boxes'];
	pg_assert_same(3, count($boxes), 'one box per band');
	pg_assert_same(0, $boxes[0]['y'], 'first at the top');
	pg_assert_same(71, $boxes[2]['y'] + $boxes[2]['h'], 'last ends at the bottom');
	pg_assert_same($boxes[0]['y'] + $boxes[0]['h'] + 2, $boxes[1]['y'], 'two apart');
	pg_assert_true($boxes[1]['h'] > $boxes[2]['h'] && $boxes[2]['h'] > $boxes[0]['h'], 'heavier bands are taller');
	pg_assert_same(array('nav', 2), array($boxes[0]['role'], $boxes[0]['links']), 'role and links carried');
	pg_assert_same(array(), $out['none'], 'no bands, no boxes');
	pg_assert_true($out['tight'][0]['h'] >= 3 && $out['tight'][1]['h'] >= 3, 'each at least 3 high');
}

// A frame is its head and its picture (1280 wide, cut at 1600, scaled to
// 228 units); a shared part's picture is as tall as the part, at least the
// sketch strip and at most the cut. A frame with no tree is its head and
// one line. No row takes room: the list carries the links.
function test_proto_hotspots_layout_makes_room_for_the_picture()
{
	$out = proto_hotspots_test_node('var d = { pages: [{ key: "p1", name: "a", sketch: [{ role: "block", weight: 1, links: 0 }], hotspots: [{ nodeId: "n", source: "href", href: "/x", label: "X", mark: "page" }] }, { key: "p2", name: "b", sketch: [], hotspots: [] }, { key: "p3", name: "c", sketch: null, hotspots: [] }],'
		. ' shared: [{ id: 4, name: "s", loaded: true, pages: 1, sketch: [{ role: "block", weight: 1, links: 0 }], hotspots: [] }, { id: 5, name: "t", loaded: false, pages: 1, sketch: null, hotspots: [] }, { id: 6, name: "u", loaded: true, pages: 1, sketch: [], hotspots: [] }] };'
		. ' var f = H.layout(d, {}).frames; var g = H.layout(d, {}, { partH: { 4: 800, 6: 99999 } }).frames;'
		. ' out = { size: H.picSize(), pic: f.map(function (x) { return x.pic; }), thumb: f.map(function (x) { return x.thumb; }), h: f.map(function (x) { return x.h; }), part: [g[3].thumb, g[3].h, g[5].thumb],'
		. ' box: [H.picPartBox(0), H.picPartBox(200), H.picPartBox(800), H.picPartBox(5000), H.picPartBox(null)] };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('w' => 228, 'h' => 285, 'scale' => 0.178125, 'box' => 298, 'page' => 1280, 'cut' => 1600), $out['size'], 'the picture: 1280 x 1600 at 228 wide, its box 13 more');
	pg_assert_same(array(true, true, false, true, false, true), $out['pic'], 'a page or a shared part with a tree gets a picture');
	pg_assert_same(array(298, 298, 0, 84, 0, 84), $out['thumb'], 'a page\'s box is the cut\'s; a shared part\'s the sketch strip until measured');
	pg_assert_same(array(44 + 298 + 10, 44 + 298 + 10, 44 + 30 + 10, 44 + 84 + 10, 44 + 30 + 10, 44 + 84 + 10), $out['h'], 'the head, the box and the padding: no rows');
	pg_assert_same(array(156, 44 + 156 + 10, 298), $out['part'], 'a measured part at its own height, at most the cut');
	pg_assert_same(array(84, 84, 156, 298, 84), $out['box'], 'a part\'s box: at least the strip, at most the cut');
}

// A link's rectangle read from the picture, in units of the picture inside
// its box: scaled from page pixels, cut at the cut; the first element seen
// is taken. Not seen: under the cut, of no size, outside the page's width,
// not written (a menu or region has no marker). A page frame reads no
// shared component's marker; a shared frame only its own.
function test_proto_hotspots_mark_rects_in_frame_units()
{
	$rows = array(
		array('port' => 'h0', 'nodeId' => 'a', 'source' => 'href'),
		array('port' => 'h1', 'nodeId' => 't', 'source' => 'text', 'index' => 1),
		array('port' => 'h2', 'nodeId' => 'm', 'source' => 'menu'),
		array('port' => 'h3', 'nodeId' => 'b', 'source' => 'btn'),
		array('port' => 'h4', 'nodeId' => 'c', 'source' => 'href'),
		array('port' => 'h5', 'nodeId' => 'd', 'source' => 'attrs'),
	);
	$marks = array(
		array('pic' => 'a', 'left' => 640, 'top' => 160, 'w' => 320, 'h' => 80),
		array('pic' => 'a', 'left' => 0, 'top' => 0, 'w' => 10, 'h' => 10),
		array('pic' => 't.1', 'left' => 5, 'top' => 5, 'w' => 0, 'h' => 0),
		array('pic' => 't.1', 'left' => 0, 'top' => 1500, 'w' => 100, 'h' => 200),
		array('pic' => 'b', 'left' => 0, 'top' => 1600, 'w' => 80, 'h' => 20),
		array('pic' => 'c', 'left' => -200, 'top' => 10, 'w' => 100, 'h' => 20),
		array('pic' => 's3:d', 'left' => 0, 'top' => 10, 'w' => 100, 'h' => 20),
	);
	$out = proto_hotspots_test_node('out = { page: H.picRects(' . proto_hotspots_test_js($rows) . ', ' . proto_hotspots_test_js($marks) . ', 0),'
		. ' shared: H.picRects([{ port: "h0", nodeId: "d", source: "attrs" }, { port: "h1", nodeId: "a", source: "href" }], ' . proto_hotspots_test_js($marks) . ', 3), none: H.picRects(null, null, 0) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'h0' => array('x' => 114, 'y' => 28.5, 'w' => 57, 'h' => 14.25),
		'h1' => array('x' => 0, 'y' => 267.19, 'w' => 17.81, 'h' => 17.81),
	), $out['page'], 'a and the second element of t.1 (cut at 1600) are seen; the rest are not');
	pg_assert_same(array('h0' => array('x' => 0, 'y' => 1.78, 'w' => 17.81, 'h' => 3.56)), $out['shared'], 'a shared frame reads its own markers only');
	pg_assert_same(array(), $out['none'], 'nothing read');
}

// Where an arrow leaves a frame: by the side facing its target, at the
// mark's height (left, right) or across from it (top, bottom); a link the
// picture cannot show leaves from the foot; before the picture has loaded,
// from the middle of the box.
function test_proto_hotspots_mark_port_side()
{
	$frame = array('x' => 0, 'y' => 0, 'w' => 248, 'h' => 352, 'thumb' => 298);
	$rect = array('x' => 114, 'y' => 28.5, 'w' => 57, 'h' => 14.25);
	$f = proto_hotspots_test_js($frame);
	$r = proto_hotspots_test_js($rect);
	$out = proto_hotspots_test_node('var t = { right: { x: 400, y: 0, w: 248, h: 352 }, left: { x: -400, y: 300, w: 248, h: 352 }, below: { x: 100, y: 500, w: 248, h: 352 }, above: { x: -100, y: -500, w: 200, h: 36 } };'
		. ' out = { right: H.markPort(' . $f . ', ' . $r . ', t.right), left: H.markPort(' . $f . ', ' . $r . ', t.left), below: H.markPort(' . $f . ', ' . $r . ', t.below), above: H.markPort(' . $f . ', ' . $r . ', t.above),'
		. ' self: H.markPort(' . $f . ', ' . $r . ', ' . $f . '), none: H.markPort(' . $f . ', ' . $r . ', null),'
		. ' foot: H.markPort(' . $f . ', null, t.right), footDown: H.markPort(' . $f . ', null, t.below),'
		. ' early: H.markPort(' . $f . ', undefined, t.left), bare: H.markPort({ x: 0, y: 0, w: 248, h: 84, thumb: 0 }, undefined, t.right) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('x' => 248, 'y' => 85.63, 'side' => 'right'), $out['right'], 'right of the frame: from its right edge at the mark\'s middle');
	pg_assert_same(array('x' => 0, 'y' => 85.63, 'side' => 'left'), $out['left'], 'left of it: its left edge');
	pg_assert_same(array('x' => 152.5, 'y' => 352, 'side' => 'bottom'), $out['below'], 'in its column below: the bottom edge under the mark');
	pg_assert_same(array('x' => 152.5, 'y' => 0, 'side' => 'top'), $out['above'], 'in its column above: the top edge over the mark');
	pg_assert_same(array('x' => 248, 'y' => 85.63, 'side' => 'right'), $out['self'], 'to its own frame: right');
	pg_assert_same(array('x' => 248, 'y' => 85.63, 'side' => 'right'), $out['none'], 'no target: right');
	pg_assert_same(array('x' => 248, 'y' => 324, 'side' => 'right'), $out['foot'], 'not seen: from the foot on the fade');
	pg_assert_same(array('x' => 124, 'y' => 352, 'side' => 'bottom'), $out['footDown'], 'the foot\'s middle, down');
	pg_assert_same(array('x' => 0, 'y' => 193, 'side' => 'left'), $out['early'], 'no picture yet: the middle of the box');
	pg_assert_same(array('x' => 248, 'y' => 42, 'side' => 'right'), $out['bare'], 'a frame with no box: its middle');
}

// An arrow's leg inside its frame: from the middle of its link's mark to
// the port markPort() gives on the frame's edge, level with the mark on a
// side edge, under it on the bottom; a link with no mark has none.
function test_proto_hotspots_mark_leg_from_the_mark_to_the_edge()
{
	$frame = proto_hotspots_test_js(array('x' => 0, 'y' => 0, 'w' => 248, 'h' => 352, 'thumb' => 298));
	$r = proto_hotspots_test_js(array('x' => 114, 'y' => 28.5, 'w' => 57, 'h' => 14.25));
	$out = proto_hotspots_test_node('var t = { right: { x: 400, y: 0, w: 248, h: 352 }, below: { x: 100, y: 500, w: 248, h: 352 } };'
		. ' out = { right: H.markLeg(' . $r . ', H.markPort(' . $frame . ', ' . $r . ', t.right)), below: H.markLeg(' . $r . ', H.markPort(' . $frame . ', ' . $r . ', t.below)),'
		. ' foot: H.markLeg(null, H.markPort(' . $frame . ', null, t.right)), early: H.markLeg(undefined, H.markPort(' . $frame . ', undefined, t.right)), noPort: H.markLeg(' . $r . ', null) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('x1' => 152.5, 'y1' => 85.63, 'x2' => 248, 'y2' => 85.63), $out['right'], 'from the mark\'s middle across to the right edge, level with it');
	pg_assert_same(array('x1' => 152.5, 'y1' => 85.63, 'x2' => 152.5, 'y2' => 352), $out['below'], 'down to the bottom edge under it');
	pg_assert_same(null, $out['foot'], 'a link under the cut leaves from the foot: no leg');
	pg_assert_same(null, $out['early'], 'no picture yet: no leg');
	pg_assert_same(null, $out['noPort'], 'no port: no leg');
}

// The view draws the leg of each arrow shown in its frame, in the arrow's
// colour, after the core routes it, and lifts and fades it with the arrow.
function test_proto_hotspots_view_draws_the_leg_on_the_frame()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js');

	pg_assert_contains("_sdProtoLegsSoon();\n        return _sdProtoPortAt(item, port);", $source, 'every route of an arrow redraws the legs');
	pg_assert_contains("var leg = H.markLeg(rects[e.port], _sdProtoPortAt(from, e.port));", $source, 'the leg ends where the arrow leaves the frame');
	pg_assert_contains("if (!group || window.getComputedStyle(group).display === 'none') return;", $source, 'only the arrows shown have a leg');
	pg_assert_contains("var state = list.contains('pgc-edge-in') ? 'in' : (list.contains('pgc-edge-faint') ? 'faint' : 'out');", $source, 'the leg takes its arrow\'s state');
	pg_assert_contains("ui.boxes.classList.toggle('sd-proto-lifting', !!group);", $source, 'the pointer lifts the leg with its arrow');
	pg_assert_contains("if (leg) leg.classList.add('sd-proto-leg-lift');", $source, 'the lifted arrow\'s leg is drawn thicker');
	pg_assert_contains("ui.boxes.classList.toggle('sd-proto-drawing', !!on);", $source, 'the legs fade while an arrow is drawn');
	pg_assert_false(strpos($core, 'sd-proto') !== false, 'the core knows nothing of the view');
	foreach (array('.sd-proto-legs {', '.sd-proto-leg-out .sd-proto-leg-line', '.sd-proto-leg-in .sd-proto-leg-line', '.sd-proto-leg.sd-proto-leg-broken .sd-proto-leg-line', '.sd-proto .pgc-boxes.sd-proto-lifting .sd-proto-leg:not(.sd-proto-leg-lift)', '.sd-proto .pgc-boxes.sd-proto-drawing .sd-proto-leg') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

// The view puts the marks on a loaded picture, starts each arrow at its
// mark (portAt), draws a shared part's own picture and lays the frames out
// again once a part is measured; the link rows are gone from the frames.
function test_proto_hotspots_view_marks_on_the_picture()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains('portAt: function (item, name) { return item.rows ? _sdProtoPort(item, name) : null; },', $source, 'an arrow leaves where markPort() says');
	pg_assert_contains('return window.PGProtoHotspots.markPort(item, rects ? (rects[port] || null) : undefined, to ? _sdProto.byId[to] : null);', $source, 'the mark, the foot or the middle, by the side facing the target');
	pg_assert_contains('var rects = H.picRects(item.rows, pic.marks, item.sharedId, pic.cut);', $source, 'the marks are read from the picture');
	pg_assert_contains('canvas.reroute([item.id]);', $source, 'the frame\'s arrows move to its marks once loaded');
	pg_assert_contains('var mark = window.PGProtoHotspots.markState(_sdProto.layout, id);', $source, 'which marks show follows the selected frame');
	pg_assert_contains("children: [{ _id: 'pic-part', type: 'shared_ref', props: { sharedId: item.sharedId }, children: [] }] };", $source, 'a shared part is drawn alone');
	pg_assert_contains('if (H.picPartBox(height) !== item.thumb) _sdProtoPicSettle();', $source, 'a measured part gets its own height');
	pg_assert_false(strpos($source, "_sdProtoEl('div', 'sd-proto-row ") !== false, 'no link rows on a frame');
	foreach (array('.sd-proto-hots', '.sd-proto-hot.sd-proto-mark-broken', '.sd-proto-hot.sd-proto-hot-shared') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

// After a pan or zoom, the pictures near the view come back: a frame in
// view is never left a sketch once the queue is empty while no more frames
// are near than the cap; past the cap, the nearest first, as the cap drops.
function test_proto_hotspots_picture_refill_after_the_view_moves()
{
	$out = proto_hotspots_test_node('var b = [{ id: "far", x: 2000, y: 0, w: 100, h: 100 }, { id: "mid", x: 400, y: 0, w: 100, h: 100 }, { id: "in", x: 0, y: 0, w: 100, h: 100 }, { id: "live", x: 50, y: 0, w: 100, h: 100 }]; var v = { x: 0, y: 0, w: 100, h: 100 };'
		. ' out = { all: H.picRefill(b, v, { live: true }, 48), capped: H.picRefill(b, v, { live: true }, 2), none: H.picRefill(b, v, {}, 0), empty: H.picRefill([], v, {}, 48) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('in', 'mid', 'far'), $out['all'], 'under the cap every frame near the view that is not alive comes back, nearest first');
	pg_assert_same(array('in'), $out['capped'], 'past the cap only the nearest, the alive one counted');
	pg_assert_same(array(), $out['none'], 'a cap of none');
	pg_assert_same(array(), $out['empty'], 'nothing near');
}

// A link without words of its own is named by what its children show (a
// logo link: the icon has none, the name's text and a picture's alt do),
// not by its address; its own text, aria-label or alt still come first.
function test_proto_hotspots_link_named_by_its_children()
{
	$logo = array('_id' => 'l', 'type' => 'semantic', 'props' => array('tag' => 'a', 'href' => '/anasayfa'), 'children' => array(
		array('_id' => 'i', 'type' => 'content', 'props' => array('contentType' => 'icon', 'icon' => 'bi-heart'), 'children' => array()),
		array('_id' => 's', 'type' => 'semantic', 'props' => array('tag' => 'span', 'text' => 'Clinic <b>Care</b>'), 'children' => array(
			array('_id' => 'g', 'type' => 'content', 'props' => array('contentType' => 'image', 'alt' => 'Logo'), 'children' => array()),
		)),
	));
	$own = $logo;
	$own['props']['ariaLabel'] = 'Home';
	$bare = array('_id' => 'b', 'type' => 'semantic', 'props' => array('tag' => 'a', 'href' => '/x'), 'children' => array());
	$out = proto_hotspots_test_node('out = [H.collect(' . proto_hotspots_test_js(array('_id' => 'r', 'type' => 'root', 'props' => array(), 'children' => array($logo, $own, $bare))) . ').hotspots.map(function (h) { return h.label; })];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('Clinic Care Logo', 'Home', ''), $out[0], 'the children\'s words, its own first, nothing for a bare link (the address stands in)');
}

// Drawing from a mark (F21, F22, F23): a press on an arrow's head re-points
// it within 14 screen pixels at any zoom; the keys of a mark.
function test_proto_hotspots_head_and_mark_keys()
{
	$out = proto_hotspots_test_node('var e = { x: 100, y: 100 };'
		. ' out = { near: [H.nearHead(e, { x: 110, y: 100 }, 1), H.nearHead(e, { x: 115, y: 100 }, 1), H.nearHead(e, { x: 120, y: 100 }, 0.5), H.nearHead(e, { x: 110, y: 100 }, 2), H.nearHead(null, e, 1)],'
		. ' keys: [{ key: "Enter" }, { key: " " }, { key: "l" }, { key: "L" }, { key: "l", ctrlKey: true }, { key: "ContextMenu" }, { key: "F10", shiftKey: true }, { key: "F10" }, { key: "x" }, null].map(H.markKey) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(true, false, true, false, false), $out['near'], 'within 14 screen pixels of the head, by the zoom');
	pg_assert_same(array('pick', 'pick', 'menu', 'menu', '', 'menu', 'menu', '', '', ''), $out['keys'], 'Enter and Space pick, L and the menu key open the target menu');
}

// Drawing from a mark (F21, F22, F23): a mark's drag and an arrow head's
// drag become the core's connect once they move; the selected frame's
// marks take Tab, Enter and L; the pointer on a mark or a row lifts its
// arrow.
function test_proto_hotspots_view_draws_from_a_mark()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("if ((drag.kind !== 'row' && drag.kind !== 'head') || !drag.link) return;", $source, 'a mark or a head turns into a drawn arrow only once it moves');
	pg_assert_contains("drag.kind = 'connect';", $source, 'the core draws it, lights its targets and calls connected()');
	pg_assert_contains("if (done.kind === 'row' && !done.moved && event.type === 'pointerup') _sdProtoPickRow(done.row);", $source, 'a click on a mark still picks its link');
	pg_assert_contains('if (!H.nearHead({ x: end.x, y: end.y }, at, k)) return;', $source, 'a press within reach of an arrow\'s head takes the nearest one');
	pg_assert_contains('var act = hot ? window.PGProtoHotspots.markKey(event) : \'\';', $source, 'a mark\'s keys');
	pg_assert_contains("return link ? _sdProtoMenuOpen(link.frame, link.port, hot) : false;", $source, 'L on a mark opens its target menu');
	pg_assert_contains("if (el && state === 'own') el.tabIndex = 0;", $source, 'Tab reaches only the selected frame\'s marks');
	pg_assert_contains('_sdProtoLift(id || \'\');', $source, 'the pointer lifts an arrow');
	pg_assert_false(strpos($source, "var dot = canvas.port('out', row.port);") !== false, 'no port dot: the whole mark draws');
	pg_assert_contains('_sdProtoDrawing(true);', $source, 'while an arrow is drawn the others fade');
	pg_assert_contains("window.addEventListener(type, function () { if (_sdProto.drawing) _sdProtoDrawing(false); }, true);", $source, 'and come back when it is let go');
	pg_assert_contains('var at = window.PGProtoHotspots.menuPlace(', $source, 'the target menu is placed inside the canvas');
	pg_assert_contains('.sd-proto .pgc-edges.sd-proto-drawing .pgc-edge-temp {', $css, 'the drawn arrow at full strength');
	foreach (array('.sd-proto-hot:focus-visible', '.sd-proto .pgc-edges.sd-proto-lifting .pgc-edge:not(.sd-proto-edge-lift)') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

// The target menu stays inside the canvas under the view's bar: under its
// mark when there is room, else above it, else beside it, clamped; never
// over the bar's zoom controls.
function test_proto_hotspots_menu_inside_the_canvas()
{
	$out = proto_hotspots_test_node('var area = { left: 0, top: 40, right: 600, bottom: 440 }, menu = { w: 260, h: 200 };'
		. ' out = { below: H.menuPlace({ left: 100, top: 60, right: 140, bottom: 80 }, menu, area),'
		. ' above: H.menuPlace({ left: 100, top: 300, right: 140, bottom: 320 }, menu, area),'
		. ' beside: H.menuPlace({ left: 100, top: 230, right: 140, bottom: 250 }, menu, area),'
		. ' left: H.menuPlace({ left: 500, top: 230, right: 540, bottom: 250 }, menu, area),'
		. ' edge: H.menuPlace({ left: 480, top: 60, right: 520, bottom: 80 }, menu, area),'
		. ' tall: H.menuPlace({ left: 100, top: 230, right: 140, bottom: 250 }, { w: 260, h: 500 }, area) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('left' => 100, 'top' => 82), $out['below'], 'under the mark when there is room');
	pg_assert_same(array('left' => 100, 'top' => 98), $out['above'], 'above it when not, never above the canvas\'s top');
	pg_assert_same(array('left' => 142, 'top' => 230), $out['beside'], 'beside it when neither fits');
	pg_assert_same(array('left' => 238, 'top' => 230), $out['left'], 'on its left when the right has no room');
	pg_assert_same(array('left' => 336, 'top' => 82), $out['edge'], 'clamped inside the canvas\'s width');
	pg_assert_same(array('left' => 142, 'top' => 44), $out['tall'], 'a menu taller than the canvas starts at its top');
}

// An empty link (F26): its address is empty or '#' alone; '#name' stays an
// anchor, a toggle is no link at all, a link kept by page id reads its page.
// An empty link gets no chip and no arrow.
function test_proto_hotspots_empty_links_marked()
{
	$pages = array(array('key' => 'p1', 'page_id' => 1, 'page_name' => 'home', 'tree' => array('_id' => 'r', 'type' => 'root', 'children' => array(
		array('_id' => 'e1', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Blank', 'href' => ''), 'children' => array()),
		array('_id' => 'e2', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Hash', 'href' => '#'), 'children' => array()),
		array('_id' => 'a1', 'type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Top', 'href' => '#top'), 'children' => array()),
		array('_id' => 't1', 'type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Menu', 'href' => '#', '_attrs' => array(array('name' => 'data-bs-toggle', 'value' => 'collapse'))), 'children' => array()),
		array('_id' => 'b1', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Kept', 'href' => '#', '_bindings' => array('href' => '__page_link'), '_bindParams' => array('href' => array('page_id' => 1))), 'children' => array()),
		array('_id' => 'x1', 'type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'See <a href="#">this</a> and <a href=" # ">that</a>'), 'children' => array()),
	))));
	$out = proto_hotspots_test_node('var read = ' . proto_hotspots_test_js(proto_hotspots_test_read()) . ';'
		. ' var m = H.mark(H.design(' . proto_hotspots_test_js($pages) . ', {}), read, [{ key: "p1", page_id: 1, page_name: "home" }]);'
		. ' var l = H.layout(m, {});'
		. ' out = { marks: m.pages[0].hotspots.map(function (h) { return h.nodeId + (h.source === "text" ? "." + h.index : "") + " " + h.mark; }), edges: l.edges.map(function (e) { return e.id; }), chips: l.chips.map(function (c) { return c.mark; }) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('e1 empty', 'e2 empty', 'a1 anchor', 'b1 tab', 'x1.0 empty', 'x1.1 empty'), $out['marks'], 'empty and \'#\' are empty, \'#top\' an anchor, the toggle no link, the kept link its page');
	pg_assert_same(array('p:p1|h2', 'p:p1|h3'), $out['edges'], 'no arrow from an empty link');
	pg_assert_same(array('anchor'), $out['chips'], 'and no chip');
}

// The problems of a design (F27, F28, F29): broken links, empty links with
// each frame's count, the pages nothing reaches. A page reached only by a
// shared part's link is reached; the Start page never counts, linked or
// not; shared parts and chips are never unreached. "To check" is empty
// links plus unreached pages.
function test_proto_hotspots_problems_of_a_design()
{
	$laid = array(
		'start' => 'p:a',
		'frames' => array(
			array('id' => 'p:a', 'kind' => 'page', 'name' => 'Home', 'rows' => array(array('port' => 'h0', 'label' => 'Go', 'href' => '', 'mark' => 'empty'), array('port' => 'h1', 'label' => 'Old', 'href' => '/old', 'mark' => 'broken'), array('port' => 'h2', 'label' => 'B', 'href' => '/b', 'mark' => 'tab'))),
			array('id' => 'p:b', 'kind' => 'page', 'name' => 'B', 'rows' => array(array('port' => 'h0', 'label' => 'More', 'href' => '#', 'mark' => 'empty'), array('port' => 'h1', 'label' => 'Self', 'href' => '/b', 'mark' => 'tab'))),
			array('id' => 'p:c', 'kind' => 'page', 'name' => 'C', 'rows' => array()),
			array('id' => 'p:d', 'kind' => 'page', 'name' => 'D', 'rows' => array(array('port' => 'h0', 'label' => 'To B', 'href' => '/b', 'mark' => 'tab'))),
			array('id' => 's:5', 'kind' => 'shared', 'name' => 'Header', 'rows' => array(array('port' => 'h0', 'label' => 'C', 'href' => '/c', 'mark' => 'tab'), array('port' => 'h1', 'label' => 'Cart', 'href' => '', 'mark' => 'empty'))),
		),
		'chips' => array(array('id' => 'c:0', 'kind' => 'chip', 'mark' => 'broken')),
		'edges' => array(
			array('id' => 'p:a|h1', 'from' => 'p:a', 'port' => 'h1', 'to' => 'c:0'),
			array('id' => 'p:a|h2', 'from' => 'p:a', 'port' => 'h2', 'to' => 'p:b'),
			array('id' => 'p:b|h1', 'from' => 'p:b', 'port' => 'h1', 'to' => 'p:b'),
			array('id' => 'p:d|h0', 'from' => 'p:d', 'port' => 'h0', 'to' => 'p:b'),
			array('id' => 's:5|h0', 'from' => 's:5', 'port' => 'h0', 'to' => 'p:c'),
		),
	);
	$unlinked = $laid;
	$unlinked['edges'] = array($laid['edges'][1]);
	$out = proto_hotspots_test_node('var l = ' . proto_hotspots_test_js($laid) . ', u = ' . proto_hotspots_test_js($unlinked) . ';'
		. ' var p = H.problemsOf(l), q = H.problemsOf(u);'
		. ' out = { unreached: p.unreached, empty: p.empty.list.map(function (e) { return e.frame + "|" + e.port + " " + e.label; }), count: p.empty.count, broken: p.broken.list.length, check: p.check,'
		. ' unlinked: q.unreached, none: H.problemsOf(null) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('p:d'), $out['unreached'], 'D: nothing leads there; C is reached by the shared part; B by Home; Home is the start');
	pg_assert_same(array('p:a|h0 Go', 'p:b|h0 More', 's:5|h1 Cart'), $out['empty'], 'every empty link, a shared part\'s too');
	pg_assert_same(array('p:a' => 1, 'p:b' => 1, 's:5' => 1), $out['count'], 'each frame\'s amber count');
	pg_assert_same(1, $out['broken'], 'the broken links as before');
	pg_assert_same(4, $out['check'], '"N to check": three empty links and one page');
	pg_assert_same(array('p:c', 'p:d'), $out['unlinked'], 'without links the pages but the start are unreached; a self link reaches nothing');
	pg_assert_same(array('broken' => array('list' => array(), 'count' => array()), 'empty' => array('list' => array(), 'count' => array()), 'unreached' => array(), 'check' => 0), $out['none'], 'nothing laid out');
}

// What to check in the view (F26 to F29): the bar's amber "N to check",
// the problems list's three groups, a frame's amber count and "No link
// leads here", a link without a target saying so in the list; all amber.
function test_proto_hotspots_view_what_to_check()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("ui.check.textContent = toCheck ? _sdT('{var:1} to check', toCheck) : '';", $source, '"N to check" in the bar');
	pg_assert_contains("bar.appendChild(ui.check);", $source, 'after "N broken" in the view\'s own bar');
	foreach (array("group(_sdT('Broken links'), 'sd-proto-problem-broken'", "group(_sdT('Pages no link leads to'), 'sd-proto-problem-unreached'", "group(_sdT('Links without a target'), 'sd-proto-problem-empty'") as $group) {
		pg_assert_contains($group, $source, 'a group of the problems list');
	}
	pg_assert_contains("if (!rows.length) return;", $source, 'an empty group left out');
	pg_assert_contains("var amber = _sdProtoEl('span', 'sd-proto-badge sd-proto-badge-empty', String(emptyHere));", $source, 'a frame\'s amber count beside the red one');
	pg_assert_contains("head.appendChild(_sdProtoEl('span', 'sd-proto-unreached', _sdT('No link leads here')));", $source, 'a page nothing reaches says so');
	pg_assert_contains("shows.state === 'empty' ? _sdT('No target') : ", $source, 'a read-only list row without a target says so');
	pg_assert_contains("if (el.scrollWidth > el.clientWidth + 1) el.classList.add('sd-proto-status-out');", $source, 'a link count that does not fit is left out whole');
	pg_assert_contains('el.parentNode.title = el.textContent;', $source, 'its words stay in the bar\'s title');
	pg_assert_contains('.sd-proto-status.sd-proto-status-out { visibility: hidden; }', $css, 'never cut with an ellipsis');
	pg_assert_contains('.sd-proto-menu-head.sd-proto-problem-broken { color: #ff6b6b; }', $css, '"Broken links" in its button\'s red');
	foreach (array('.sd-proto-check {', '.sd-proto-badge.sd-proto-badge-empty', '.sd-proto-unreached {', '.sd-proto-hot.sd-proto-mark-empty', '.sd-proto-li-empty .sd-proto-li-to') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

// The links the designer's canvas marks (canvasMarks()): of the drawable
// links of a marked design, page frames first, then shared components, in
// document order, the broken ones with why they are broken and the ones
// without a target ('' or '#'). A link kept by a page that is gone is
// broken as 'kept'. A tab, a site page, an anchor, a toggle, a classic
// menu and a region's links get none. Without the map read (`known`
// false) a link cannot be called broken: only the empty ones are marked.
// The read is asked for the addresses but the empty ones and for the kept
// pages (canvasRequest()), never for a menu or a region.
function test_proto_hotspots_canvas_marks()
{
	$link = function ($id, $text, $href) {
		return array('_id' => $id, 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => $text, 'href' => $href), 'children' => array());
	};
	$tree = array('_id' => 'r', 'type' => 'root', 'props' => array(), 'children' => array(
		$link('l1', 'Old', '/old'),
		$link('l2', 'Lost', '/nothing'),
		$link('l3', 'Blank', ''),
		array('_id' => 'l4', 'type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Buy'), 'children' => array()),
		array('_id' => 'l5', 'type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Top', 'href' => '#top'), 'children' => array()),
		$link('l6', 'About', '/about'),
		$link('l7', 'Home', '/home'),
		array('_id' => 't1', 'type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'See <a href="/nothing">x</a>, <a href="/about">y</a>, <a href="#" data-bs-toggle="tab">t</a> and <a href="#">z</a>'), 'children' => array()),
		proto_hotspots_test_bound(10),
		array('_id' => 'm', 'type' => 'region', 'props' => array('regionType' => 'menu', 'regionName' => 'Gone'), 'children' => array()),
		array('_id' => 'f', 'type' => 'region', 'props' => array('regionType' => 'common', 'regionName' => 'Footer'), 'children' => array()),
		array('_id' => 's', 'type' => 'shared_ref', 'props' => array('sharedId' => 5), 'children' => array()),
	));
	$shared = array(5 => array('name' => 'Header', 'tree' => array('_id' => 'sr', 'type' => 'root', 'props' => array(), 'children' => array(
		$link('se', 'Cart', ''),
		$link('so', 'Gone', '/old'),
	))));
	$out = proto_hotspots_test_node('var read = ' . proto_hotspots_test_js(proto_hotspots_test_read()) . ', tabs = [{ key: "p1", page_id: 1, page_name: "home" }];'
		. ' var d = H.design([{ key: "p1", page_id: 1, page_name: "home", tree: ' . proto_hotspots_test_js($tree) . ' }], ' . proto_hotspots_test_js($shared) . ');'
		. ' var line = function (m) { return m.nodeId + " " + m.source + "." + m.index + " " + m.mark + (m.why ? " " + m.why : "") + (m.sharedId ? " @" + m.sharedId : ""); };'
		. ' var all = H.canvasMarks(H.mark(d, read, tabs), true);'
		. ' out = { known: all.map(line), first: all[0], unknown: H.canvasMarks(H.mark(d, {}, tabs), false).map(line), none: [H.canvasMarks(null, true), H.canvasMarks({ pages: [], shared: [] }, true)],'
		. ' request: H.canvasRequest(d), asked: d.request.menus.concat(d.request.regions), nothing: H.canvasRequest(null) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'l1 href.0 broken page',
		'l2 href.0 broken page',
		'l3 href.0 empty',
		'l4 btn.0 empty',
		't1 text.0 broken page',
		't1 text.3 empty',
		'b10 href.0 broken kept',
		'se href.0 empty @5',
		'so href.0 broken page @5',
	), $out['known'], 'the broken and the empty links, a shared component\'s with its id');
	pg_assert_same(array('nodeId' => 'l1', 'sharedId' => 0, 'source' => 'href', 'index' => 0, 'mark' => 'broken', 'label' => 'Old', 'href' => '/old', 'why' => 'page'), $out['first'], 'what a mark carries');
	pg_assert_same(array('l3 href.0 empty', 'l4 btn.0 empty', 't1 text.3 empty', 'se href.0 empty @5'), $out['unknown'], 'without the read only the empty links');
	pg_assert_same(array(array(), array()), $out['none'], 'nothing marked, nothing drawn');
	pg_assert_same(array('hrefs' => array('/old', '/nothing', '#top', '/about', '/home'), 'page_ids' => array(10), 'menus' => array(), 'regions' => array()), $out['request'], 'the read asked without the empty addresses, menus and regions');
	pg_assert_same(array('Gone', 'Footer'), $out['asked'], 'which the Prototype view asks');
	pg_assert_same(array('hrefs' => array(), 'page_ids' => array(), 'menus' => array(), 'regions' => array()), $out['nothing'], 'no design, nothing asked');
}

// A system widget leads to pages its settings name (the login widget's
// "Giriş Yap" to the login page): those pages are reached as by a shared
// part's link. A form's data page and an e-mail's page are no links; a
// register link that is not shown leads nowhere.
function test_proto_hotspots_widget_settings_reach_pages()
{
	$config = '{"regionType":"login_region","login_page_id":12,"register_page_id":13,"show_register_link":false,"account_page_id":"0","welcome_email_page_id":14,"custom_form_page_id":15,"detail_page_id":16,"redirect_page_id":12}';
	$pages = array();
	foreach (array(10 => 'home', 12 => 'giris', 13 => 'kayit-ol', 14 => 'e-posta', 16 => 'detay') as $id => $name) {
		$pages[] = array('key' => 'p' . $id, 'page_id' => $id, 'page_name' => $name, 'tree' => array('_id' => 'r' . $id, 'type' => 'root', 'children' => $id === 10 ? array(array('_id' => 'w', 'type' => 'shared_ref', 'props' => array('sharedId' => 1), 'children' => array())) : array()));
	}
	$out = proto_hotspots_test_node('var d = H.design(' . proto_hotspots_test_js($pages) . ', { 1: { name: "login", tree: null, system_region_config: ' . proto_hotspots_test_js($config) . ' } });'
		. ' var l = H.layout(H.mark(d, ' . proto_hotspots_test_js(proto_hotspots_test_read()) . ', []), {});'
		. ' out = { reaches: d.shared[0].reaches, frame: l.frames.filter(function (f) { return f.id === "s:1"; })[0].reaches, unreached: H.problemsOf(l).unreached,'
		. ' none: H.widgetPages(null), object: H.widgetPages({ login_page_id: 7, staff_page_id: 7, my_account_page_id: 8 }), bad: H.widgetPages("{bad") };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(12, 16), $out['reaches'], 'the login page and the detail page; no hidden register link, no e-mail, no data page, no zero');
	pg_assert_same(array(12, 16), $out['frame'], 'the widget\'s frame keeps them');
	pg_assert_same(array('p:p13', 'p:p14'), $out['unreached'], 'giris and detay are reached by the widget; the hidden register page and the e-mail page are not');
	pg_assert_same(array(), $out['none'], 'no settings');
	pg_assert_same(array(7, 8), $out['object'], 'settings as an object, each page once');
	pg_assert_same(array(), $out['bad'], 'settings that do not parse');
}

// A link the server fills per request (the account widget's "Profile",
// href bound to __profile_url) leads to the page placing the widget of that
// kind: that page is reached. A field with no such page, or one the map
// does not know, reaches nothing; the link is still no hotspot.
function test_proto_hotspots_bound_fields_reach_widget_pages()
{
	$ref = function ($sid) { return array('_id' => 'r' . $sid, 'type' => 'shared_ref', 'props' => array('sharedId' => $sid), 'children' => array()); };
	$link = function ($id, $field) { return array('_id' => $id, 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => $id, 'href' => '#', '_bindings' => array('href' => $field)), 'children' => array()); };
	$pages = array(
		array('key' => 'p10', 'page_id' => 10, 'page_name' => 'home', 'tree' => array('_id' => 'a', 'type' => 'root', 'children' => array($ref(2)))),
		array('key' => 'p11', 'page_id' => 11, 'page_name' => 'hesabim', 'tree' => array('_id' => 'b', 'type' => 'root', 'children' => array($ref(3)))),
		array('key' => 'p12', 'page_id' => 12, 'page_name' => 'profilim', 'tree' => array('_id' => 'c', 'type' => 'root', 'children' => array($ref(4)))),
		array('key' => 'p13', 'page_id' => 13, 'page_name' => 'cikis', 'tree' => array('_id' => 'd', 'type' => 'root', 'children' => array($ref(5)))),
		array('key' => 'p14', 'page_id' => 14, 'page_name' => 'bulten', 'tree' => array('_id' => 'e', 'type' => 'root', 'children' => array($link('x', '__unknown_url')))),
	);
	$shared = array(
		2 => array('name' => 'Header', 'tree' => array('_id' => 'h', 'type' => 'root', 'children' => array($link('m', '__my_account_url')))),
		3 => array('name' => 'Account', 'tree' => array('_id' => 'w', 'type' => 'root', 'children' => array($link('p', '__profile_url'), $link('q', '__change_password_url'))), 'system_region_config' => '{"regionType":"my_account"}'),
		4 => array('name' => 'Profile', 'tree' => null, 'system_region_config' => '{"regionType":"account_profile"}'),
		5 => array('name' => 'Logout', 'tree' => null, 'system_region_config' => '{"regionType":"logout"}'),
	);
	$out = proto_hotspots_test_node('var d = H.design(' . proto_hotspots_test_js($pages) . ', ' . proto_hotspots_test_js($shared) . ');'
		. ' var l = H.layout(H.mark(d, ' . proto_hotspots_test_js(proto_hotspots_test_read()) . ', []), {});'
		. ' var f = {}; l.frames.forEach(function (x) { f[x.id] = x; });'
		. ' out = { urls: f["s:3"].urls, widget: [f["s:3"].widget, f["s:4"].widget, f["s:2"].widget], spots: d.shared.filter(function (s) { return s.id === 3; })[0].hotspots.length, unreached: H.problemsOf(l).unreached };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('__profile_url', '__change_password_url'), $out['urls'], 'the widget\'s fields, each once');
	pg_assert_same(array('my_account', 'account_profile', ''), $out['widget'], 'a system widget\'s kind');
	pg_assert_same(0, $out['spots'], 'a field-bound link is still no hotspot');
	pg_assert_same(array('p:p13', 'p:p14'), $out['unreached'], 'hesabim by the header\'s My account, profilim by the widget\'s Profile; no field leads to cikis; an unknown field leads nowhere');
}

// Which marks a picture shows (F5, F13): the selected frame all of its own;
// another frame those starting an arrow into it; a shared part's link into
// the selected page in amber. Nothing selected shows none.
function test_proto_hotspots_mark_state()
{
	$laid = array(
		'frames' => array(
			array('id' => 'p:a', 'kind' => 'page', 'name' => 'A', 'rows' => array(array('port' => 'h0'), array('port' => 'h1'))),
			array('id' => 'p:b', 'kind' => 'page', 'name' => 'B', 'rows' => array(array('port' => 'h0'), array('port' => 'h1'))),
			array('id' => 's:5', 'kind' => 'shared', 'name' => 'Header', 'rows' => array(array('port' => 'h0', 'label' => 'Cart'))),
		),
		'chips' => array(array('id' => 'c:0', 'kind' => 'chip')),
		'edges' => array(
			array('id' => 'p:a|h0', 'from' => 'p:a', 'port' => 'h0', 'to' => 'p:b'),
			array('id' => 'p:a|h1', 'from' => 'p:a', 'port' => 'h1', 'to' => 'c:0'),
			array('id' => 'p:b|h0', 'from' => 'p:b', 'port' => 'h0', 'to' => 'p:a'),
			array('id' => 'p:b|h1', 'from' => 'p:b', 'port' => 'h1', 'to' => 'c:0'),
			array('id' => 's:5|h0', 'from' => 's:5', 'port' => 'h0', 'to' => 'p:a'),
		),
	);
	$l = proto_hotspots_test_js($laid);
	$out = proto_hotspots_test_node('out = { a: H.markState(' . $l . ', "p:a"), s: H.markState(' . $l . ', "s:5"), none: H.markState(' . $l . ', null), gone: H.markState(' . $l . ', "p:x") };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('p:a|h0' => 'own', 'p:a|h1' => 'own', 'p:b|h0' => 'lit', 's:5|h0' => 'shared'), $out['a'], 'its own, the one into it, the shared part\'s in amber');
	pg_assert_same(array('s:5|h0' => 'own'), $out['s'], 'a shared frame selected: its own');
	pg_assert_same(array(), $out['none'], 'nothing selected');
	pg_assert_same(array(), $out['gone'], 'an unknown frame');
}

// The marker of a link's element: the node id, '.n' for the n-th link of a
// text, 's<id>:' inside a shared component; what a node writes.
function test_proto_hotspots_picture_marker()
{
	$text = array('_id' => 't1', 'type' => 'content', 'props' => array('contentType' => 'text', 'text' => '<p><a href="/a">A</a> <a href="#" data-bs-toggle="collapse">T</a> <a href="/b">B</a></p>'), 'children' => array());
	$link = array('_id' => 'l1', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Go', 'href' => '/go'), 'children' => array());
	$btn = array('_id' => 'b1', 'type' => 'component', 'props' => array('componentType' => 'btn', 'btnElement' => 'button'), 'children' => array());
	$out = proto_hotspots_test_node('out = { own: H.picMark("n5"), text: H.picMark(7, 2), shared: H.picMark("n5", null, 12), sharedText: H.picMark("n5", "0", "12"), none: H.picMark(""),'
		. ' marks: [H.picMarks(' . proto_hotspots_test_js($text) . '), H.picMarks(' . proto_hotspots_test_js($link) . '), H.picMarks(' . proto_hotspots_test_js($btn) . ')] };');

	if ($out === null) {
		return;
	}

	pg_assert_same('n5', $out['own'], 'a node\'s own link: its id');
	pg_assert_same('7.2', $out['text'], 'the n-th link of a text');
	pg_assert_same('s12:n5', $out['shared'], 'inside a shared component');
	pg_assert_same('s12:n5.0', $out['sharedText'], 'both');
	pg_assert_same('', $out['none'], 'no id, no marker');
	pg_assert_same(array('own' => false, 'text' => array(0, 2)), $out['marks'][0], 'a text marks its links by htmlLinks() index, the toggle skipped');
	pg_assert_same(array('own' => true, 'text' => array()), $out['marks'][1], 'a link marks its own tag');
	pg_assert_same(array('own' => false, 'text' => array()), $out['marks'][2], 'a button element is no link');
}

// The foot: the links a picture cannot show (under the cut, of no size, not
// written at all, in a menu or region, which has no marker) are counted;
// one element seen is enough; a page frame skips shared markers.
function test_proto_hotspots_picture_foot_counts_links_below_the_cut()
{
	$rows = array(
		array('nodeId' => 'a', 'source' => 'href'),
		array('nodeId' => 'b', 'source' => 'btn'),
		array('nodeId' => 't', 'source' => 'text', 'index' => 0),
		array('nodeId' => 't', 'source' => 'text', 'index' => 1),
		array('nodeId' => 'h', 'source' => 'attrs'),
		array('nodeId' => 'gone', 'source' => 'href'),
		array('nodeId' => 'm', 'source' => 'menu'),
		array('nodeId' => 'a', 'source' => 'href'),
	);
	$marks = array(
		array('pic' => 'a', 'top' => 100, 'w' => 80, 'h' => 20),
		array('pic' => 'b', 'top' => 1600, 'w' => 80, 'h' => 20),
		array('pic' => 't.0', 'top' => 1599, 'w' => 10, 'h' => 10),
		array('pic' => 't.1', 'top' => 1700, 'w' => 10, 'h' => 10),
		array('pic' => 't.1', 'top' => 20, 'w' => 10, 'h' => 10),
		array('pic' => 'h', 'top' => 10, 'w' => 0, 'h' => 0),
		array('pic' => 's3:a', 'top' => 5000, 'w' => 10, 'h' => 10),
	);
	$shared = array(array('nodeId' => 'a', 'source' => 'href'), array('nodeId' => 'z', 'source' => 'href'));
	$out = proto_hotspots_test_node('out = { page: H.picFoot(' . proto_hotspots_test_js($rows) . ', ' . proto_hotspots_test_js($marks) . ', 0),'
		. ' shared: H.picFoot(' . proto_hotspots_test_js($shared) . ', [{ pic: "s3:a", top: 10, w: 5, h: 5 }, { pic: "z", top: 10, w: 5, h: 5 }], 3), none: H.picFoot([], [], 0) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('links' => 7, 'below' => 4), $out['page'], 'b at the cut, h of no size, the unwritten link and the menu\'s count; t.1 is seen once');
	pg_assert_same(array('links' => 2, 'below' => 1), $out['shared'], 'a shared frame reads its own markers only');
	pg_assert_same(array('links' => 0, 'below' => 0), $out['none'], 'nothing to count');
}

// The fade only when the page runs past the cut: a page that ends exactly
// at 1600 (a short min-vh-100 page in the 1600-high frame) gets none.
function test_proto_hotspots_picture_fade_only_past_the_cut()
{
	$out = proto_hotspots_test_node('out = [H.picLong(900), H.picLong(1600), H.picLong(1601), H.picLong("2400"), H.picLong(null)];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(false, false, true, true, false), $out, 'a short page and one ending at the cut get no fade');
}

// Pictures nearest the view's centre first; the farthest is dropped first.
function test_proto_hotspots_picture_order_by_distance()
{
	$out = proto_hotspots_test_node('out = H.picOrder([{ id: "far", x: 2000, y: 0, w: 100, h: 100 }, { id: "mid", x: 400, y: 0, w: 100, h: 100 }, { id: "in", x: 0, y: 0, w: 100, h: 100 }, { id: "twin", x: 0, y: 0, w: 100, h: 100 }], { x: 0, y: 0, w: 100, h: 100 });');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('in', 'twin', 'mid', 'far'), $out, 'by distance, equals in the given order');
}

// A link kept by page id ('__page_link', #6233): collected with the page it
// opens, asked for by id, marked by the page's current name.
function proto_hotspots_test_bound($id, $href = '/old')
{
	return array('_id' => 'b' . $id, 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Go', 'href' => $href,
		'_bindings' => array('href' => '__page_link'), '_bindParams' => array('href' => array('page_id' => $id, 'params' => array()))), 'children' => array());
}

function test_proto_hotspots_bound_links_collected_and_asked_by_id()
{
	$tree = array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
		proto_hotspots_test_bound(7), proto_hotspots_test_bound('7'), proto_hotspots_test_bound('tab:k2', '/new'), proto_hotspots_test_bound('x', '/typed'),
	));
	$result = proto_hotspots_test_node('out = H.design([{ key: "k1", page_id: 1, page_name: "home", tree: ' . proto_hotspots_test_js($tree) . ' }], {});');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('7', '7', 'tab:k2', ''), array_column($result['pages'][0]['hotspots'], 'bound'), 'the page each opens');
	pg_assert_same(array(true, true, true, true), array_column($result['pages'][0]['hotspots'], 'pageLink'));
	pg_assert_same(array(7), $result['request']['page_ids'], 'a saved page asked for by id, once');
	pg_assert_same(array('/new', '/typed'), $result['request']['hrefs'], 'the rest by their typed href');
}

function test_proto_hotspots_bound_links_marked()
{
	$read = array(
		'env' => array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		'maps' => array('pages' => array('about-us' => array('id' => 7, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1), 'gone' => array('id' => 8, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 99)), 'files' => array(), 'short_links' => array(), 'products' => array(), 'product_groups' => array(), 'home_page_id' => 0),
		'form_names' => new stdClass(),
		'hidden' => array(),
		'hidden_ids' => array(9),
		'draft_folder' => 0,
		'bin_folders' => array(99),
	);
	$tabs = array(array('key' => 'k1', 'page_id' => 3, 'page_name' => 'Contact'), array('key' => 'k2', 'page_id' => 0, 'page_name' => 'fresh'));
	$code = 'var read = ' . proto_hotspots_test_js($read) . ', tabs = ' . proto_hotspots_test_js($tabs) . ';'
		. 'out = ["7", "3", "tab:k2", "8", "9", "10", "tab:zz"].map(function (b) { var m = H.markBound({ bound: b, href: "/old" }, read, tabs, R); return [m.mark, m.href, m.pageId, m.tab]; });';
	$result = proto_hotspots_test_node($code);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('page', '/about-us', 7, ''),
		array('tab', '/Contact', 3, 'k1'),
		array('tab', '/fresh', 0, 'k2'),
		array('broken', '/gone', 8, ''),
		array('hidden', '/old', 9, ''),
		array('broken', '/old', 10, ''),
		array('broken', '/old', 0, ''),
	), $result, 'current name, tab, binned, hidden, missing');
}

function test_proto_hotspots_arrow_keeps_a_link_by_page_id()
{
	$code = 'var node = ' . proto_hotspots_test_js(array('_id' => 'n', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Go', 'href' => '/old', '_bindings' => array('text' => 'x')), 'children' => array())) . ';'
		. 'var row = { source: "href", index: 0, href: "/old", bound: "" };'
		. 'var tab = { kind: "page", key: "k1", pageId: 3 }, fresh = { kind: "page", key: "k2", pageId: 0 }, chip = { kind: "chip", mark: "external", href: "https://x.org/" };'
		. 'var p1 = H.hrefPatch(node, row, "/Contact", tab); H.applyPatch(node.props, p1);'
		. 'var after = JSON.parse(JSON.stringify(node.props));'
		. 'node.props._bindParams.href.params = [{ name: "a", text: "b" }];'
		. 'var stale = H.hrefPatch(node, row, "/x", tab);'
		. 'var p2 = H.hrefPatch(node, { source: "href", index: 0, href: "/Contact", bound: "3" }, "/fresh", fresh); H.applyPatch(node.props, p2);'
		. 'var kept = JSON.parse(JSON.stringify(node.props._bindParams));'
		. 'var p3 = H.hrefPatch(node, { source: "href", index: 0, href: "/fresh", bound: "tab:k2" }, "https://x.org/", chip); H.applyPatch(node.props, p3);'
		. 'out = { p1: p1, after: after, stale: stale, kept: kept, p3: p3, last: node.props, boundTo: [H.boundTo(tab), H.boundTo(fresh), H.boundTo(chip), H.boundTo(null)] };';
	$out = proto_hotspots_test_node($code);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('key' => 'href', 'value' => '/Contact', 'bind' => array('page_id' => 3)), $out['p1'], 'a tab arrow keeps the page by id');
	pg_assert_same(array('text' => 'x', 'href' => '__page_link'), $out['after']['_bindings'], 'other bindings stay');
	pg_assert_same(array('href' => array('page_id' => 3, 'params' => array())), $out['after']['_bindParams']);
	pg_assert_same('/Contact', $out['after']['href'], 'the address is the fallback');
	pg_assert_same(null, $out['stale'], 'a row read before the binding no longer matches');
	pg_assert_same(array('href' => array('page_id' => 'tab:k2', 'params' => array(array('name' => 'a', 'text' => 'b')))), $out['kept'], 'an unsaved tab by key; params kept');
	pg_assert_same(false, $out['p3']['bind'], 'a chip arrow ends the page binding');
	pg_assert_same(array('text' => 'x'), $out['last']['_bindings']);
	pg_assert_same(false, isset($out['last']['_bindParams']), 'no empty spec left');
	pg_assert_same('https://x.org/', $out['last']['href']);
	pg_assert_same(array(3, 'tab:k2', null, null), $out['boundTo']);
}

// The Link panel's note of a link kept by page id, and its way back to a
// typed address.
function test_proto_hotspots_panel_page_link_notice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("if (tok === '__page_link' && !_sdSystemWidgetCfgOf(node)) return _sdPageLinkNotice(node);", $source, 'outside a widget the note names the page');
	pg_assert_contains("window.PGProtoHotspots.applyPatch(selectedNode.props, { key: 'href', value: selectedNode.props.href || '', bind: false });", $source, 'the button drops the binding, the address stays');
}

// Which nodes Bind Data offers a page for outside a widget: a link of its
// own drawn as <a> (content link, btn drawn as <a>, semantic a), whatever
// its href is bound to now; never a toggle, a <button>, a text or a picture.
function test_proto_hotspots_page_linkable()
{
	$nodes = array(
		array('type' => 'content', 'props' => array('contentType' => 'link', 'href' => '/a')),
		array('type' => 'content', 'props' => array('contentType' => 'link', 'href' => '', '_bindings' => array('href' => '__record__url'))),
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'href' => '/b')),
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'btnElement' => 'button')),
		array('type' => 'semantic', 'props' => array('tag' => 'A', 'href' => '/c')),
		array('type' => 'semantic', 'props' => array('tag' => 'a')),
		array('type' => 'content', 'props' => array('contentType' => 'link', 'href' => '#m', '_attrs' => array(array('name' => 'data-bs-toggle', 'value' => 'modal')))),
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => '<a href="/d">d</a>')),
		array('type' => 'content', 'props' => array('contentType' => 'image', 'src' => '/x.png')),
		null,
	);
	$out = proto_hotspots_test_node('out = ' . proto_hotspots_test_js($nodes) . '.map(function (n) { return H.pageLinkable(n); });');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(true, true, true, false, true, true, false, false, false, false), $out);
}

// Bind Data outside a widget offers a page for a link that may hold one,
// with the page select under it, and starts no value rows there.
function test_proto_hotspots_bind_data_offers_a_page_outside_widgets()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("var linkable = !!(window.PGProtoHotspots && window.PGProtoHotspots.pageLinkable(node));", $source, 'the panel asks the collector which links may be kept by id');
	pg_assert_contains("var extra = (withPage && current === SW_PAGE_LINK_TOKEN) ? _sdPageLinkPageRow(node) : '';", $source, 'the page select sits under the chosen option');
	pg_assert_contains("params: inWidget ? [{ name: '', token: '' }] : []", $source, 'outside a widget no value row is started');
}

// A picture's frame: exactly `allow-same-origin`, no focus, no pointer,
// hidden from screen readers; its document's head locks scripts, plugins,
// frames, media and form posts out and stills every animation, first.
function test_proto_hotspots_picture_frame_and_head()
{
	$out = proto_hotspots_test_node('out = { frame: H.PIC_FRAME, head: H.picHead(), doc: H.picDoc("<!DOCTYPE html><html><head>\n<meta charset=\"utf-8\"><link rel=\"stylesheet\" href=\"/a.css\"><script>var a = \"<\\/script>\";</script></head><body><header>x</header><SCRIPT src=\"/b.js\" defer></SCRIPT></body></html>"), none: H.picDoc("<p>x</p>") };');

	if ($out === null) {
		return;
	}

	pg_assert_same('allow-same-origin', $out['frame']['sandbox'], 'the sandbox allows nothing but reading the document');
	pg_assert_same('-1', $out['frame']['tabindex'], 'no focus');
	pg_assert_same('true', $out['frame']['aria-hidden'], 'hidden from screen readers');
	pg_assert_same('pointer-events:none', $out['frame']['style'], 'no pointer');

	foreach (array("script-src 'none'", "object-src 'none'", "frame-src 'none'", "media-src 'none'", "form-action 'none'") as $directive) {
		pg_assert_true(strpos($out['head'], $directive) !== false, 'the CSP carries ' . $directive);
	}

	pg_assert_true(strpos($out['head'], 'animation:none!important;transition:none!important') !== false, 'the stilling rule');
	pg_assert_same(0, strpos($out['doc'], '<!DOCTYPE html><html><head>' . $out['head'] . "\n<meta charset"), 'the head goes first, before what the page loads');
	pg_assert_same(1, substr_count($out['doc'], 'Content-Security-Policy'), 'once, not into <header>');
	pg_assert_same(0, stripos($out['doc'], '<script') === false ? 0 : 1, 'no script element is left');
	pg_assert_true(strpos($out['doc'], '<header>x</header></body>') !== false, 'the rest of the page stays');
	pg_assert_same('', $out['none'], 'no head, no picture');
}

// The source of one function of style_designer.src.js (four-space indent).
function proto_hotspots_test_designer_fn($name)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
	$at = strpos($source, "\n    function " . $name . '(');

	if ($at === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", $at);

	return ($end === false) ? '' : substr($source, $at, $end + 7 - $at);
}

// The link marker is written only for the picture: set and cleared in
// try/finally around the picture's own build, so a build that throws
// leaves it off, and the emitter writes it only while it is on.
function test_proto_hotspots_picture_marker_flag_never_reaches_save()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$raw = proto_hotspots_test_designer_fn('_sdProtoPicRaw');
	$mark = proto_hotspots_test_designer_fn('_sdPicMarkHtml');

	pg_assert_true($raw !== '' && $mark !== '', 'both functions found');
	pg_assert_same(1, substr_count($source, '_sdPicMarkers = true'), 'one place turns the marker on');
	pg_assert_true(strpos($source, 'if (_sdPicMarkers && !sink && h) h = _sdPicMarkHtml(n, h);') !== false, 'the emitter marks only while it is on');

	$out = proto_hotspots_test_node('var _sdPreviewWidgetMarkers = false, _sdPicMarkers = false, _sdPicShared = 0, _sharedCache = {}, seen = null;'
		. ' var document = { querySelector: function () { return null; } };'
		. ' function _pgPageByKey() { return { key: "k" }; } function _pgTreeOf() { return { children: [] }; }'
		. ' function generateHTML() { seen = [_sdPicMarkers, _sdPreviewWidgetMarkers]; _sdPicShared = 4; throw new Error("x"); }'
		. ' function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;"); }'
		. $raw . $mark
		. ' var threw = false; try { _sdProtoPicRaw({ kind: "page", key: "k" }); } catch (e) { threw = true; }'
		. ' var link = { _id: "l1", type: "content", props: { contentType: "link", href: "/go" } };'
		. ' var text = { _id: "t1", type: "content", props: { contentType: "text", text: "<p><a href=\"/a\">A</a> <a href=\"/b\">B</a></p>" } };'
		. ' var plain = { _id: "p1", type: "content", props: { contentType: "text", text: "Hi" } };'
		. ' var during = _sdPicMarkHtml(link, "  <a class=\"x\" href=\"/go\">Go</a>\n");'
		. ' _sdPicShared = 9; var shared = _sdPicMarkHtml(link, "<a href=\"/go\">Go</a>"); _sdPicShared = 0;'
		. ' out = { threw: threw, seen: seen, after: [_sdPicMarkers, _sdPreviewWidgetMarkers, _sdPicShared], own: during, shared: shared,'
		. ' text: _sdPicMarkHtml(text, "<div class=\"t\"><p><a href=\"/a\">A</a> <a href=\"/b\">B</a></p></div>"),'
		. ' extra: _sdPicMarkHtml(text, "<div><a href=\"/z\">Z</a><p><a href=\"/a\">A</a> <a href=\"/b\">B</a></p></div>"),'
		. ' plain: _sdPicMarkHtml(plain, "<p>Hi</p>") };');

	if ($out === null) {
		return;
	}

	pg_assert_true($out['threw'], 'the build threw');
	pg_assert_same(array(true, true), $out['seen'], 'both markers were on during the build');
	pg_assert_same(array(false, false, 0), $out['after'], 'and are off after it threw');
	pg_assert_same("  <a data-pg-pic=\"l1\" class=\"x\" href=\"/go\">Go</a>\n", $out['own'], 'a link\'s own tag');
	pg_assert_same('<a data-pg-pic="s9:l1" href="/go">Go</a>', $out['shared'], 'inside a shared component');
	pg_assert_same('<div class="t"><p><a data-pg-pic="t1.0" href="/a">A</a> <a data-pg-pic="t1.1" href="/b">B</a></p></div>', $out['text'], 'each link of a text by its index');
	pg_assert_same('<div><a href="/z">Z</a><p><a href="/a">A</a> <a href="/b">B</a></p></div>', $out['extra'], 'markup with links the text lacks gets none');
	pg_assert_same('<p>Hi</p>', $out['plain'], 'no link, unchanged');
}

// The target suggested for a link without one (F31): label, page name and
// page title folded (lower case, Turkish letters plain, punctuation to
// spaces, the filler words dropped); sure, unsure with at most three
// candidates best first, or nothing; the link's own page and a page in the
// bin never offered.
function test_proto_hotspots_suggest_targets()
{
	$pages = array(
		array('id' => 'p:home', 'name' => 'anasayfa', 'title' => 'Ana Sayfa'),
		array('id' => 'p:cart', 'name' => 'sepet', 'title' => 'Alışveriş Sepeti'),
		array('id' => 'p:blog', 'name' => 'blog', 'title' => 'Blog'),
		array('id' => 'p:archive', 'name' => 'blog-arsivi', 'title' => 'Blog arşivi'),
		array('id' => 'p:about', 'name' => 'hakkimizda', 'title' => 'Hakkımızda'),
		array('id' => 'p:old', 'name' => 'iletisim', 'title' => 'İletişim', 'bin' => true),
		array('id' => 'p:firm', 'name' => 'ŞİRKET', 'title' => ''),
	);
	$cases = array(
		'sure' => array('label' => 'Sepete git', 'page' => 'p:home'),
		'two' => array('label' => 'Blog', 'page' => 'p:home'),
		'heading' => array('label' => 'Daha fazlası', 'page' => 'p:home', 'heading' => 'Blog'),
		'none' => array('label' => 'Zzz qqq', 'page' => 'p:home'),
		'own' => array('label' => 'Sepet', 'page' => 'p:cart'),
		'bin' => array('label' => 'İletişim', 'page' => 'p:home'),
		'folded' => array('label' => 'HAKKIMIZDA', 'page' => 'p:home'),
		'upper' => array('label' => 'şirket sayfası', 'page' => 'p:home'),
		'title' => array('label' => 'Alışveriş sepeti!', 'page' => 'p:home'),
		'fillers' => array('label' => 'Go to the page', 'page' => 'p:home'),
	);
	$out = proto_hotspots_test_node('var pages = ' . proto_hotspots_test_js($pages) . ', cases = ' . proto_hotspots_test_js($cases) . '; out = { list: H.SUGGEST_FILLER };'
		. ' Object.keys(cases).forEach(function (k) { var s = H.suggest(cases[k], pages); out[k] = { sure: s.sure ? s.sure.id : null, candidates: s.candidates.map(function (p) { return p.id; }) }; });'
		. ' out.fold = H.foldWords("Ödeme, ŞİMDİ git: Sayfa-gör İncele ılık");');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('go', 'to', 'the', 'page', 'view', 'see', 'git', 'sayfa', 'gör', 'incele'), $out['list'], 'one list of filler words');
	pg_assert_same(array('sure' => 'p:cart', 'candidates' => array('p:cart')), $out['sure'], '"Sepete git" finds Sepet: its one word begins a word of the label');
	pg_assert_same(array('sure' => null, 'candidates' => array('p:blog', 'p:archive')), $out['two'], '"Blog": two pages match, the equal one first');
	pg_assert_same(array('sure' => null, 'candidates' => array('p:blog', 'p:archive')), $out['heading'], 'no match by the label: the heading above it finds unsure candidates');
	pg_assert_same(array('sure' => null, 'candidates' => array()), $out['none'], 'a label that matches nothing and no heading');
	pg_assert_same(array('sure' => null, 'candidates' => array()), $out['own'], 'the link\'s own page is never a candidate');
	pg_assert_same(array('sure' => null, 'candidates' => array()), $out['bin'], 'a page in the bin is never a candidate');
	pg_assert_same(array('sure' => 'p:about', 'candidates' => array('p:about')), $out['folded'], 'upper case and dotless i fold to the name');
	pg_assert_same(array('sure' => 'p:firm', 'candidates' => array('p:firm')), $out['upper'], 'a page name in Turkish capitals folds too; "sayfası" begins with a filler only as a word');
	pg_assert_same(array('sure' => 'p:cart', 'candidates' => array('p:cart')), $out['title'], 'the page title counts; punctuation is a space');
	pg_assert_same(array('sure' => null, 'candidates' => array()), $out['fillers'], 'filler words alone match nothing');
	pg_assert_same(array('odeme', 'simdi', 'ilik'), $out['fold'], 'folded words: Turkish letters plain, punctuation a space, fillers dropped');
}

// The dialog's rows (F30, F32): one per link without a target in the
// frames' order, a shared part's once; the nearest heading of a link's
// block comes from the tree (headingOf()); the head's three counts.
function test_proto_hotspots_suggest_rows()
{
	$laid = array(
		'frames' => array(
			array('id' => 'p:a', 'kind' => 'page', 'name' => 'anasayfa', 'rows' => array(
				array('port' => 'h0', 'nodeId' => '3', 'source' => 'href', 'label' => 'Sepete git', 'href' => '', 'mark' => 'empty'),
				array('port' => 'h1', 'nodeId' => '4', 'source' => 'href', 'label' => 'Daha fazlası', 'href' => '#', 'mark' => 'empty'),
				array('port' => 'h2', 'nodeId' => '5', 'source' => 'href', 'label' => 'Blog', 'href' => '/blog', 'mark' => 'tab'),
				array('port' => 'h3', 'nodeId' => '6', 'source' => 'href', 'label' => 'Old', 'href' => '/old', 'mark' => 'broken'),
			)),
			array('id' => 'p:b', 'kind' => 'page', 'name' => 'sepet', 'rows' => array()),
			array('id' => 'p:c', 'kind' => 'page', 'name' => 'blog', 'rows' => array()),
			array('id' => 's:7', 'kind' => 'shared', 'name' => 'Header', 'rows' => array(
				array('port' => 'h0', 'nodeId' => '9', 'source' => 'href', 'label' => 'Xyz', 'href' => '', 'mark' => 'empty'),
			)),
		),
		'chips' => array(),
		'edges' => array(),
	);
	$tree = array('_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => '1', 'type' => 'semantic', 'props' => array('tag' => 'section'), 'children' => array(
			array('_id' => '2', 'type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h2', 'text' => 'Son <b>Blog</b> yazıları'), 'children' => array()),
			array('_id' => '10', 'type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
				array('_id' => '4', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Daha fazlası', 'href' => '#'), 'children' => array()),
			)),
		)),
		array('_id' => '11', 'type' => 'semantic', 'props' => array('tag' => 'section'), 'children' => array(
			array('_id' => '3', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Sepete git', 'href' => ''), 'children' => array()),
		)),
	));
	$pages = array(
		array('id' => 'p:a', 'name' => 'anasayfa', 'title' => ''),
		array('id' => 'p:b', 'name' => 'sepet', 'title' => ''),
		array('id' => 'p:c', 'name' => 'blog', 'title' => ''),
	);
	$out = proto_hotspots_test_node('var l = ' . proto_hotspots_test_js($laid) . ', t = ' . proto_hotspots_test_js($tree) . ', pages = ' . proto_hotspots_test_js($pages) . ';'
		. ' var heads = { "p:a|h1": H.headingOf(t, "4"), "p:a|h0": H.headingOf(t, "3") };'
		. ' var s = H.suggestRows(l, pages, heads);'
		. ' out = { heads: heads, rows: s.rows.map(function (r) { return r.frame + "|" + r.port + " " + r.label + " " + r.kind + " " + (r.page || "-") + " " + r.candidates.join(","); }), count: s.count, missing: H.headingOf(t, "99"), none: H.suggestRows(null, pages, {}) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('p:a|h1' => 'Son Blog yazıları', 'p:a|h0' => ''), $out['heads'], 'the heading above a link in its block; none in a block without one');
	pg_assert_same(array(
		'p:a|h0 Sepete git sure p:b p:b',
		'p:a|h1 Daha fazlası ask - p:c',
		's:7|h0 Xyz none - ',
	), $out['rows'], 'one row per link without a target, the shared part\'s once; a target, a broken link untouched');
	pg_assert_same(array('sure' => 1, 'ask' => 1, 'none' => 1), $out['count'], 'the head\'s counts');
	pg_assert_same('', $out['missing'], 'a node not in the tree has no heading');
	pg_assert_same(array('rows' => array(), 'count' => array('sure' => 0, 'ask' => 0, 'none' => 0)), $out['none'], 'nothing laid out');
}

// Apply's batch (F34): the ticked rows grouped by the tab they are written
// on, a tab once in the order of its first row (one undo entry each); a row
// on a tab closed to the operator, on no tab or with no page chosen stays
// unwritten with its reason.
function test_proto_hotspots_suggest_batch()
{
	$picks = array(
		array('frame' => 'p:a', 'port' => 'h0', 'page' => 'p:c', 'tab' => 'a', 'locked' => false),
		array('frame' => 's:5', 'port' => 'h1', 'page' => 'p:a', 'tab' => 'b', 'locked' => false),
		array('frame' => 'p:a', 'port' => 'h2', 'page' => 'p:b', 'tab' => 'a', 'locked' => false),
		array('frame' => 'p:d', 'port' => 'h0', 'page' => 'p:a', 'tab' => 'd', 'locked' => true),
		array('frame' => 's:6', 'port' => 'h0', 'page' => 'p:a', 'tab' => '', 'locked' => false),
		array('frame' => 'p:a', 'port' => 'h3', 'page' => '', 'tab' => 'a', 'locked' => false),
	);
	$out = proto_hotspots_test_node('var b = H.suggestBatch(' . proto_hotspots_test_js($picks) . ');'
		. ' out = { tabs: b.tabs.map(function (t) { return t.key + ": " + t.picks.map(function (p) { return p.frame + "|" + p.port; }).join(" "); }),'
		. ' refused: b.refused.map(function (r) { return r.pick.frame + "|" + r.pick.port + " " + r.why; }), none: H.suggestBatch(null) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('a: p:a|h0 p:a|h2', 'b: s:5|h1'), $out['tabs'], 'three rows on two tabs: two groups, two undo entries');
	pg_assert_same(array('p:d|h0 locked', 's:6|h0 tab', 'p:a|h3 target'), $out['refused'], 'a locked tab\'s row, a row on no tab and one without a page stay unwritten');
	pg_assert_same(array('tabs' => array(), 'refused' => array()), $out['none'], 'nothing ticked');
}

// The Suggest targets view (F30 to F34): the bar's button, the dialog from
// the panel's dialog parts without a backdrop, the proposed arrows through
// the core's dashed(), Apply writing one undo entry per tab, and the
// dialog closed with the view.
function test_proto_hotspots_view_suggest_targets()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$at = strpos($source, 'function _sdProtoSuggestOpen()');
	$end = strpos($source, 'function _sdProtoMenuOpen(', (int) $at);
	pg_assert_true($at !== false && $end !== false, 'the dialog\'s functions sit before the target menu');
	$block = substr($source, (int) $at, (int) $end - (int) $at);

	pg_assert_contains("_sdT('Suggest targets · {var}', toSuggest)", $source, 'the bar says "Suggest targets · n"');
	pg_assert_contains('H.suggestRows(_sdProto.layout, pages, _sdProtoSuggestHeadings())', $block, 'the rows come from the pure match');
	pg_assert_contains("el.setAttribute('data-bs-modal', 'false');", $block, 'a dialog without a backdrop: the map stays in sight');
	pg_assert_contains('canvas.dashed(', $block, 'the proposed arrows go through the core');
	pg_assert_false(strpos($block, "G.svgEl('path'") !== false, 'and no arrow is drawn beside it');
	pg_assert_contains('H.suggestBatch(picks)', $block, 'Apply groups its rows by tab');
	$tab = substr($block, (int) strpos($block, 'function _sdProtoSuggestTab('));
	pg_assert_same(1, substr_count($tab, 'saveState();'), 'one undo entry per tab');
	pg_assert_contains("_sdT('{var} links connected', done)", $block, 'the toast counts what was written');
	pg_assert_contains("_sdProtoSuggestClose(false);\n        _sdProto.open = false;", $source, 'closing the view closes the dialog');
	pg_assert_contains("if (_sdProto.suggest) _sdProtoSuggestArrows();", $source, 'another selected frame shows its own proposed arrows');
}

// The links panel as the place to edit: an outgoing row's target is a
// control (a box with a caret) opening today's target menu, "Choose a
// page" for a link without a target; a row that may not be drawn keeps its
// target as text; "Open in the editor" stays an icon.
function test_proto_hotspots_row_target()
{
	$out = proto_hotspots_test_node('out = ['
		. ' H.rowTarget({ drawable: true, mark: "tab", why: "" }),'
		. ' H.rowTarget({ drawable: true, mark: "empty", why: "" }),'
		. ' H.rowTarget({ drawable: true, mark: "broken", why: "gone" }),'
		. ' H.rowTarget({ drawable: false, mark: "tab", why: "" }),'
		. ' H.rowTarget({ drawable: false, mark: "empty", why: "" }),'
		. ' H.rowTarget(null) ];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array('control' => true, 'state' => 'set'),
		array('control' => true, 'state' => 'empty'),
		array('control' => true, 'state' => 'broken'),
		array('control' => false, 'state' => 'set'),
		array('control' => false, 'state' => 'empty'),
		array('control' => false, 'state' => 'set'),
	), $out, 'a drawable row\'s target is a control, a read-only row\'s text; empty, broken or set');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	$at = strpos($source, 'function _sdProtoListDraw()');
	$end = strpos($source, 'function _sdProtoProblemsOpen(', (int) $at);
	pg_assert_true($at !== false && $end !== false, 'the panel\'s drawing function');
	$block = substr($source, (int) $at, (int) $end - (int) $at);

	pg_assert_contains('var shows = window.PGProtoHotspots.rowTarget(row);', $block, 'each row asks the pure helper how its target shows');
	pg_assert_contains("var pick = _sdProtoEl('button', 'sd-proto-li-target sd-proto-li-target-' + shows.state);", $block, 'the target is a button');
	pg_assert_contains("pick.setAttribute('aria-haspopup', 'menu');", $block, 'that opens a menu');
	pg_assert_contains("pick.addEventListener('click', function () { change(pick); });", $block, 'today\'s target menu, at the control');
	pg_assert_contains("shows.state === 'empty' ? _sdT('Choose a page') : where", $block, 'a link without a target reads "Choose a page"');
	pg_assert_contains("'bi bi-chevron-down sd-proto-li-caret'", $block, 'with a caret');
	pg_assert_contains("if (!shows.control) text.appendChild(_sdProtoEl('span', 'sd-proto-li-to'", $block, 'a read-only row keeps its target as text');
	pg_assert_contains("_sdT('Open in the editor')", $block, '"Open in the editor" stays an icon');
	pg_assert_false(strpos($block, 'bi-signpost-split') !== false, 'no second "Change target" icon beside the control');
	pg_assert_contains('.sd-proto-li-out { flex-wrap: wrap; }', $css, 'the control takes a line of its own');
	pg_assert_contains('display: flex; align-items: center; gap: 4px; flex: 1 1 100%; min-width: 0;', $css, 'as wide as the row, never wider');
	pg_assert_contains('.sd-proto-li-target-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }', $css, 'a long name is cut, not pushed past the panel\'s edge');
}

// The selected page opened whole: as tall as its page between the cut and
// the limit, every link above the limit marked, the frames under it moved
// down, the foot a button only on the selected long page and the open one,
// the viewport heights pinned before the frame grows.
function test_proto_hotspots_page_opened_whole()
{
	$out = proto_hotspots_test_node(<<<'JS'
var pages = [];
for (var i = 0; i < 9; i++) pages.push({ key: "t" + i, name: "P" + i, hotspots: [], sketch: [] });
var d = { pages: pages, shared: [] };
var shut = H.layout(d, {});
var open = H.layout(d, {}, { openH: { "p:t0": 4000 } });
var rows = [{ port: "h0", nodeId: "a", source: "href" }, { port: "h1", nodeId: "b", source: "btn" }, { port: "h2", nodeId: "c", source: "href" }];
var marks = [{ pic: "a", left: 0, top: 100, w: 100, h: 40 }, { pic: "b", left: 0, top: 3000, w: 100, h: 40 }, { pic: "c", left: 0, top: 7000, w: 100, h: 40 }];
out = {
    cut: [H.picOpenCut(4000), H.picOpenCut(900), H.picOpenCut(99999), H.picOpenCut(null), H.picOpenCut("2500.4")],
    box: [H.picOpenBox(1600) === H.picSize().box, H.picOpenBox(4000), H.picOpenBox(99999)],
    thumb: [shut.frames[0].thumb, open.frames[0].thumb, open.frames[1].thumb],
    below: [open.frames[8].y - shut.frames[8].y, open.frames[0].y === shut.frames[0].y],
    rects: [Object.keys(H.picRects(rows, marks, 0)), Object.keys(H.picRects(rows, marks, 0, 6400)), H.picRects(rows, marks, 0, 6400).h1],
    foot: [H.picFoot(rows, marks, 0), H.picFoot(rows, marks, 0, 6400)],
    long: [H.picLong(4000), H.picLong(4000, 4000), H.picLong(7000, 6400)],
    state: [
        H.picFootState({ page: true, selected: true, long: true, below: 2 }),
        H.picFootState({ page: true, selected: false, long: true, below: 2 }),
        H.picFootState({ page: true, selected: true, long: false, below: 0 }),
        H.picFootState({ page: true, open: true, below: 0 }),
        H.picFootState({ page: false, selected: true, long: true, below: 1 }),
        H.picFootState(null)
    ],
    vh: [H.picVh("100vh", 1600), H.picVh("calc(50vh - 10px)", 1600), H.picVh("10svh 20dvh 30lvh 5vmax", 1600), H.picVh(".5vh", 1600), H.picVh("1px 2rem", 1600), H.picVh("min-vh-100", 1600), H.picVh(null, 1600)]
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(4000, 1600, 6400, 1600, 2500), $out['cut'], 'the page\'s height, at least the cut, at most 6400');
	pg_assert_same(array(true, 726, 1153), $out['box'], 'the box is the shown page pixels scaled, with its edge');
	pg_assert_same(array(298, 726, 298), $out['thumb'], 'only the open page grows');
	pg_assert_true($out['below'][0] >= 726 - 298, 'the frames under the open page move down by its growth');
	pg_assert_true($out['below'][1], 'the open page keeps its place');
	pg_assert_same(array(array('h0'), array('h0', 'h1'), array('x' => 0, 'y' => 534.38, 'w' => 17.81, 'h' => 7.13)), $out['rects'], 'opened whole, a link under the cut gets its mark');
	pg_assert_same(array(array('links' => 3, 'below' => 2), array('links' => 3, 'below' => 1)), $out['foot'], 'the foot counts only what lies past what is shown');
	pg_assert_same(array(true, false, true), $out['long'], 'long against what is shown');
	pg_assert_same(array(
		array('hidden' => false, 'action' => 'open', 'below' => 2),
		array('hidden' => false, 'action' => '', 'below' => 2),
		array('hidden' => true, 'action' => '', 'below' => 0),
		array('hidden' => false, 'action' => 'close', 'below' => 0),
		array('hidden' => false, 'action' => '', 'below' => 1),
		array('hidden' => true, 'action' => '', 'below' => 0),
	), $out['state'], 'the selected long page opens, the open page closes, a shared part never');
	pg_assert_same(array('1600px', 'calc(800px - 10px)', '160px 320px 480px 80px', '8px', '1px 2rem', 'min-vh-100', ''), $out['vh'], 'viewport heights become pixels, a class name stays');
}

// The view's side: the foot is a button the canvas leaves alone, one page
// open at a time, closed by another selection and with the view, measured
// at the cut, the frames placed again as for a measured part.
function test_proto_hotspots_view_opens_the_page_whole()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("ownClicks: '.sd-proto-pic-foot-on',", $source, 'the canvas takes no click from the foot button');
	pg_assert_contains("_sdProtoPicFull(_sdProto.pics.full === item.id ? null : item.id);", $source, 'the foot opens and closes the page');
	pg_assert_contains("if (_sdProto.pics.full && _sdProto.pics.full !== id) _sdProtoPicFull(null);", $source, 'another selection closes it');
	pg_assert_contains("if (all) P.full = null;", $source, 'closing the view closes it; a redraw keeps it');
	pg_assert_contains("if (pic.frame.getAttribute('height') !== H.PIC_FRAME.height) pic.frame.setAttribute('height', H.PIC_FRAME.height);", $source, 'measured at the cut');
	pg_assert_contains("_sdProtoPicPinVh(doc);\n            pic.frame.setAttribute('height', String(pic.cut));", $source, 'viewport heights pinned before the frame grows');
	pg_assert_contains("var rects = H.picRects(item.rows, pic.marks, item.sharedId, pic.cut);", $source, 'the marks down to what is shown');
	pg_assert_contains("partH: P.partH, openH: openH };", $source, 'the layout gets the open page\'s height');
	pg_assert_contains("_sdProtoPicSettle();\n    }", $source, 'the frames are placed again');
	pg_assert_contains("if (id === P.full) return;", $source, 'the cap keeps the open picture');
	pg_assert_contains("_sdT('Show the whole page')", $source, 'the open button\'s words');
	pg_assert_contains("_sdT('Show less')", $source, 'the close button\'s words');
	pg_assert_contains('.sd-proto-pic-foot.sd-proto-pic-foot-on {', $css, 'the button is styled');
}

// A mark under 12 screen pixels wide or high (drawn at least 6) takes
// presses 4 screen pixels around it at any zoom: the reach in frame units.
function test_proto_hotspots_mark_reach_for_a_small_mark()
{
	$out = proto_hotspots_test_node('out = [H.markReach({ w: 6, h: 6 }, 1), H.markReach({ w: 57, h: 14 }, 1), H.markReach({ w: 57, h: 14 }, 0.5), H.markReach({ w: 2, h: 2 }, 2),'
		. ' H.markReach({ w: 57, h: 14 }, 0.3), H.markReach({ w: 10, h: 40 }, 1), H.markReach(null, 1), H.markReach({ w: 2, h: 2 }, 0)];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(4, 0, 8, 0, 13.33, 4, 0, 4), $out, 'small on screen: 4 screen pixels in frame units; 12 or more: none; drawn at least 6; no zoom reads as 1');
}

// The "+" handle beside a drawable mark: 20 screen pixels and 4 from the
// mark at any zoom (its box in screen pixels, scaled by 1 / k), right of
// the mark, else left when the right runs past the picture's box, else
// under or over it when a neighbour's mark is in the way; level with the
// mark, kept inside the box.
function test_proto_hotspots_mark_grip_beside_the_mark()
{
	$room = proto_hotspots_test_js(array('w' => 228, 'h' => 285));
	$nav = proto_hotspots_test_js(array(array('x' => 20, 'y' => 4, 'w' => 30, 'h' => 6), array('x' => 88, 'y' => 4, 'w' => 30, 'h' => 6)));
	$out = proto_hotspots_test_node('var room = ' . $room . ';'
		. ' out = { right: H.markGrip({ x: 20, y: 40, w: 57, h: 14 }, room, 1, []), edge: H.markGrip({ x: 200, y: 40, w: 20, h: 14 }, room, 1),'
		. ' nav: H.markGrip({ x: 54, y: 4, w: 30, h: 6 }, room, 1, ' . $nav . '),'
		. ' out: H.markGrip({ x: 20, y: 40, w: 57, h: 14 }, room, 0.5, []), in: H.markGrip({ x: 20, y: 40, w: 57, h: 14 }, room, 2, []),'
		. ' top: H.markGrip({ x: 20, y: 0, w: 30, h: 6 }, room, 0.5, []), tiny: H.markGrip({ x: 100, y: 100, w: 2, h: 2 }, room, 1, []),'
		. ' wide: H.markGrip({ x: -10, y: 0, w: 248, h: 285 }, room, 1, []), none: H.markGrip(null, room, 1, []) };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('side' => 'right', 'x' => 77, 'y' => 37, 'w' => 24, 'h' => 20, 'scale' => 1), $out['right'], 'right of the mark, level with its middle');
	pg_assert_same(array('side' => 'left', 'x' => 176, 'y' => 37, 'w' => 24, 'h' => 20, 'scale' => 1), $out['edge'], 'at the picture\'s right edge: left of it');
	pg_assert_same(array('side' => 'below', 'x' => 59, 'y' => 10, 'w' => 20, 'h' => 24, 'scale' => 1), $out['nav'], 'between two neighbours in a menu: under it');
	pg_assert_same(array('side' => 'right', 'x' => 77, 'y' => 27, 'w' => 24, 'h' => 20, 'scale' => 2), $out['out'], 'zoomed out: twice as large in frame units, the same on screen');
	pg_assert_same(array('side' => 'right', 'x' => 77, 'y' => 42, 'w' => 24, 'h' => 20, 'scale' => 0.5), $out['in'], 'zoomed in: half as large');
	pg_assert_same(array('side' => 'right', 'x' => 50, 'y' => -6, 'w' => 24, 'h' => 20, 'scale' => 2), $out['top'], 'kept inside the picture\'s box');
	pg_assert_same(array('side' => 'right', 'x' => 106, 'y' => 93, 'w' => 24, 'h' => 20, 'scale' => 1), $out['tiny'], 'beside the mark as drawn, at least 6');
	pg_assert_same(array('side' => 'right', 'x' => 214, 'y' => 132.5, 'w' => 24, 'h' => 20, 'scale' => 1), $out['wide'], 'no room on any side: over the mark\'s right end, inside the box');
	pg_assert_same(null, $out['none'], 'no mark, no handle');
}

// The view's side: the pointer or the focus on a drawable mark of the
// selected frame shows the handle, which stands for its mark (a press on
// it is the mark's: a click picks, a drag draws); the handle and the small
// marks' reach follow the zoom; nothing new is written.
function test_proto_hotspots_view_grip_and_reach()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("var row = grip ? _sdProto.grip.hot : (event.target.closest ? event.target.closest('.sd-proto-hot') : null);", $source, 'a press on the handle is a press on its mark');
	pg_assert_contains("return !!(el && ui && el.classList && el.classList.contains('sd-proto-hot-own') && el.classList.contains('sd-proto-hot-draw') && ui.boxes.contains(el));", $source, 'only a drawable mark of the selected frame gets the handle');
	pg_assert_contains("_sdProtoGrip(_sdProtoGripOk(g.hover) ? g.hover : (_sdProtoGripOk(focus) ? focus : null));", $source, 'the pointer first, else the focus');
	pg_assert_contains("var at = window.PGProtoHotspots.markGrip(_sdProtoHotRect(hot), { w: layer.clientWidth, h: layer.clientHeight }, _sdProto.view.k, others);", $source, 'placed by markGrip() at the zoom');
	pg_assert_contains("el.style.transform = 'scale(' + at.scale + ')';", $source, '20 screen pixels at any zoom');
	pg_assert_contains("var reach = window.PGProtoHotspots.markReach(_sdProtoHotRect(hot), k);", $source, 'the reach of the marks shown');
	pg_assert_contains("_sdProtoZoomSoon();", $source, 'both follow the zoom');
	pg_assert_contains("ui.boxes.addEventListener('focusin', function () { _sdProtoGripFor(); });", $source, 'the focus shows the handle');
	foreach (array('.sd-proto-grip {', '.sd-proto-grip-dot {', '.sd-proto-grip-below {', '.sd-proto-hot.sd-proto-hot-reach::before {', '.sd-proto .pgc-boxes.sd-proto-drawing .sd-proto-grip {') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

// Two taps zoom to a frame only when both ended on that frame, the second
// less than ZOOM_TAP (450) ms after the first (a tap on a link mark, its
// handle, an arrow or the empty canvas has no id), and only while the frame
// is in the layout; a chip is no frame.
function test_proto_hotspots_zoom_tap_of_a_double_click()
{
	$out = proto_hotspots_test_node('var laid = { frames: [{ id: "p:t1" }, { id: "s:5" }], chips: [{ id: "c:1" }] };'
		. ' function t(id, time) { return { id: id, time: time }; }'
		. ' out = [H.ZOOM_TAP, H.zoomTap(t("p:t1", 1000), t("p:t1", 1300), laid), H.zoomTap(t("s:5", 1000), t("s:5", 1449), laid),'
		. ' H.zoomTap(t("p:t1", 1000), t("p:t1", 1450), laid), H.zoomTap(t("p:t1", 1300), t("p:t1", 1000), laid),'
		. ' H.zoomTap(t("s:5", 1000), t("p:t1", 1100), laid), H.zoomTap(t("", 1000), t("p:t1", 1100), laid), H.zoomTap(t("", 1000), t("", 1100), laid),'
		. ' H.zoomTap(t("c:1", 1000), t("c:1", 1100), laid), H.zoomTap(t("p:gone", 1000), t("p:gone", 1100), laid),'
		. ' H.zoomTap(null, t("p:t1", 1100), laid), H.zoomTap(t("p:t1", 1000), t("p:t1", 1100), null)];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(450, 'p:t1', 's:5', null, null, null, null, null, null, null, null, null), $out, 'two taps on one frame of the layout within 450 ms, else none');
}

// The view's side of the zoom to a frame (PX1): every release on the
// canvas is a tap or breaks a pair, two taps or Enter on a frame zoom to it
// with the core's zoomTo(), Esc and "All" put back the view from before;
// Fit, a new arrangement and closing the view forget it.
function test_proto_hotspots_view_zooms_to_a_frame()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("_sdProtoTap((done.kind === 'move' && !done.moved && event.type === 'pointerup') ? done.item : null);", $source, 'a frame let go without a move is a tap');
	pg_assert_contains("var id = window.PGProtoHotspots.zoomTap(_sdProto.tap, tap, _sdProto.layout);", $source, 'two taps on the same frame');
	pg_assert_contains("_sdProto.tap = id ? null : tap;", $source, 'a zoom starts a new pair');
	pg_assert_contains("_sdProto.canvas.view.zoomTo(item);", $source, 'the core zooms to the frame');
	pg_assert_contains("if (!_sdProto.zoom) _sdProto.zoom = { id: '', back: { x: v.x, y: v.y, k: v.k } };", $source, 'the view from before the first zoom is kept');
	pg_assert_contains("if (event.key === 'Escape') { if (!_sdProtoZoomBack()) _sdProtoClose(); return true; }", $source, 'Esc goes back first, then closes');
	pg_assert_contains("if (event.key === 'Enter' && event.target.classList && event.target.classList.contains('sd-proto-frame')) return _sdProtoZoomTo(event.target.getAttribute('data-node'));", $source, 'Enter on a frame zooms to it');
	pg_assert_contains("_sdT('All')", $source, 'the "All" control');
	pg_assert_contains("_sdProtoZoomForget();\n            _sdProto.canvas.view.fit();", $source, 'Fit forgets the zoom');
	pg_assert_contains("if (event.key === '0') _sdProtoZoomForget();", $source, 'the 0 key fits and forgets it');
	pg_assert_contains("if (first) {\n            _sdProtoZoomForget();", $source, 'a new arrangement forgets it');
	pg_assert_contains("_sdProtoZoomBack(true);\n        _sdProtoPicReset(true);", $source, 'closing the view puts the view back');
	foreach (array('.sd-proto-zoomed {', '.sd-proto-zoomed[hidden] {', '.sd-proto-zoomed-all {', '.sd-proto .pgc-boxes {') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}
}

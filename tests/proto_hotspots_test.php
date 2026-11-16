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
		. ' chips: l.chips.map(function (c) { return [c.id, c.mark, c.href, c.x, c.y]; }), edges: l.edges.map(function (e) { return e.id + ">" + e.to; }), y: H.rowY(l.frames[0], "h2") };');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('p:p1', 408, 0, 44 + 5 * 30 + 10, array('h0 About tab', 'h1 https://x.org/ external', 'h2 Home tab', 'h3 Private hidden', 'h4 Gone broken')),
		array('p:p2', 752, 0, 44 + 30 + 10, array()),
		array('s:5', 10, 900, 44 + 3 * 30 + 10, array('h0 X external', 'h1 Old broken', 'h2 Top anchor')),
	), $result['frames'], 'the frames, their rows and places');
	pg_assert_same(array(
		array('c:2', 'anchor', '#top', 1160, 0),
		array('c:0', 'external', 'https://x.org/', 1160, 48),
		array('c:1', 'broken', '/old', 1160, 96),
	), $result['chips'], 'one chip per mark and href, grouped by mark; a bad saved place ignored');
	pg_assert_same(array('p:p1|h0>p:p2', 'p:p1|h1>c:0', 'p:p1|h2>p:p1', 's:5|h0>c:0', 's:5|h1>c:1', 's:5|h2>c:2'), $result['edges'], 'an arrow per row that leads somewhere');
	pg_assert_same(44 + 2 * 30 + 15, $result['y'], 'a row port in the middle of its row');

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
	pg_assert_same(array(array(0, 280), array(0, 460)), array($at['t9'], $at['t17']), 'eight a row, a row as high as its tallest frame');
	pg_assert_same(array(array(0, 680), array(344, 680), array(344, 860)), array($at['t30'], $at['t21'], $at['t29']), 'a page reached from the start leads its own band');
	pg_assert_same(array(0, 1080), $at['t33'], 'the pages in no group last');
	pg_assert_same(array(5, 6), $at['t40'], 'a saved place wins');
	pg_assert_same(array(array('g1', 'Shop', 0, 0, 2656), array('g2', 'Blog', 0, 640, 2656), array('pages', 'Pages', 0, 1040, 2656)), $result['headings'], 'a heading for each band');
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
	pg_assert_contains('if (row && menuKey) return _sdProtoMenuOpen(row);', $source, 'L on a row opens its target menu');
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
	pg_assert_contains("return { sections: sections, rest: _sdT('Pages'), shared: _sdT('Shared parts'), outside: _sdT('Outside this design') };", $source, 'the headings are translated');
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
	pg_assert_contains('focusChanged: function (id) { _sdProtoFocusMarks(id); },', $source, 'a change of focus marks the rows again');
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

// A page frame is its head and its picture (1280 wide, cut at 1600, scaled
// to 228 units); a shared frame keeps its sketch strip, one not fetched the
// head alone. The rows' ports sit below the picture.
function test_proto_hotspots_layout_makes_room_for_the_picture()
{
	$out = proto_hotspots_test_node('var d = { pages: [{ key: "p1", name: "a", sketch: [{ role: "block", weight: 1, links: 0 }], hotspots: [{ nodeId: "n", source: "href", href: "/x", label: "X", mark: "page" }] }, { key: "p2", name: "b", sketch: [], hotspots: [] }, { key: "p3", name: "c", sketch: null, hotspots: [] }],'
		. ' shared: [{ id: 4, name: "s", loaded: true, pages: 1, sketch: [{ role: "block", weight: 1, links: 0 }], hotspots: [] }, { id: 5, name: "t", loaded: false, pages: 1, sketch: null, hotspots: [] }] };'
		. ' var l = H.layout(d, {}); var f = l.frames;'
		. ' out = { size: H.picSize(), pic: f.map(function (x) { return x.pic; }), thumb: f.map(function (x) { return x.thumb; }), h: [f[0].h, f[1].h, f[2].h], port: H.rowY(f[0], "h0"), plain: H.rowY(f[2], "h0") };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('w' => 228, 'h' => 285, 'scale' => 0.178125, 'box' => 298, 'page' => 1280, 'cut' => 1600), $out['size'], 'the picture: 1280 x 1600 at 228 wide, its box 13 more');
	pg_assert_same(array(true, true, false, false, false), $out['pic'], 'pages with a tree get a picture');
	pg_assert_same(array(298, 298, 0, 84, 0), $out['thumb'], 'a picture frame makes room for the box, an empty page too; a shared frame keeps the strip');
	pg_assert_same(array(44 + 298 + 30 + 10, 44 + 298 + 30 + 10, 44 + 30 + 10), $out['h'], 'its height grows by it');
	pg_assert_same(44 + 298 + 15, $out['port'], 'its row ports sit below the picture');
	pg_assert_same(44 + 15, $out['plain'], 'a frame without a tree is unchanged');
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
// written at all) are counted; one element seen is enough; menus and
// regions are not marked; a page frame skips shared markers.
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

	pg_assert_same(array('links' => 6, 'below' => 3), $out['page'], 'b at the cut, h of no size and the unwritten link count; t.1 is seen once');
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

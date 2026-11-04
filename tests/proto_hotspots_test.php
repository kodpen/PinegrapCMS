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

// Runs `$code` in Node with window.PGLinkResolve and window.PGProtoHotspots
// loaded (as H and R); its `out` comes back decoded. Null when Node cannot run.
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

// layout(): frames side by side as tall as their rows, a menu expanded
// into rows, an unknown menu one broken row, one chip per mark and href
// grouped by mark, an arrow from each row to its tab frame or chip, saved
// positions kept.
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
		array('p:p1', 0, 0, 44 + 5 * 30 + 10, array('h0 About tab', 'h1 https://x.org/ external', 'h2 Home tab', 'h3 Private hidden', 'h4 Gone broken')),
		array('p:p2', 408, 0, 44 + 30 + 10, array()),
		array('s:5', 10, 900, 44 + 3 * 30 + 10, array('h0 X external', 'h1 Old broken', 'h2 Top anchor')),
	), $result['frames'], 'the frames, their rows and places');
	pg_assert_same(array(
		array('c:2', 'anchor', '#top', 1224, 0),
		array('c:0', 'external', 'https://x.org/', 1224, 48),
		array('c:1', 'broken', '/old', 1224, 96),
	), $result['chips'], 'one chip per mark and href, grouped by mark; a bad saved place ignored');
	pg_assert_same(array('p:p1|h0>p:p2', 'p:p1|h1>c:0', 'p:p1|h2>p:p1', 's:5|h0>c:0', 's:5|h1>c:1', 's:5|h2>c:2'), $result['edges'], 'an arrow per row that leads somewhere');
	pg_assert_same(44 + 2 * 30 + 15, $result['y'], 'a row port in the middle of its row');
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

function test_proto_hotspots_layout_makes_room_for_the_thumbnail()
{
	$out = proto_hotspots_test_node('var d = { pages: [{ key: "p1", name: "a", sketch: [{ role: "block", weight: 1, links: 0 }], hotspots: [{ nodeId: "n", source: "href", href: "/x", label: "X", mark: "page" }] }, { key: "p2", name: "b", sketch: [], hotspots: [] }], shared: [] };'
		. ' var l = H.layout(d, {}); var f = l.frames;'
		. ' out = { thumb: [f[0].thumb, f[1].thumb], h: [f[0].h, f[1].h], port: H.rowY(f[0], "h0"), plain: H.rowY(f[1], "h0") };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(84, 0), $out['thumb'], 'a frame with bands gets the strip');
	pg_assert_same(array(44 + 84 + 30 + 10, 44 + 30 + 10), $out['h'], 'its height grows by it');
	pg_assert_same(44 + 84 + 15, $out['port'], 'its row ports sit below the strip');
	pg_assert_same(44 + 15, $out['plain'], 'a frame without bands is unchanged');
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

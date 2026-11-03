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
// order; toggles, a <button>, a bound href and region kinds without links
// are not; shared components, menus and regions are listed once.
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
	pg_assert_same(array('/about', '/shop', '/blog', 'mailto:a@example.com', '/terms', '/draft-page', '#', '/x?a=1&b=2', '/'), $result['request']['hrefs'], 'each href once');
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
	pg_assert_contains('var patch = node ? H.hrefPatch(node, row, href) : null;', $source, 'the node gets the collector\'s patch');
	pg_assert_contains('if (row && menuKey) return _sdProtoMenuOpen(row);', $source, 'L on a row opens its target menu');
	pg_assert_contains('_sdProtoConnect(item, target, row.port);', $source, 'a menu choice goes the drawn arrow\'s way');
}

// The collector is a bundle of its own.
function test_proto_hotspots_is_bundled()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_proto_hotspots.js'), isset($bundles['assets/js/pg_proto_hotspots.min.js']) ? $bundles['assets/js/pg_proto_hotspots.min.js'] : null, 'the collector has its bundle');
}

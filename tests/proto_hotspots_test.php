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

// The collector is a bundle of its own.
function test_proto_hotspots_is_bundled()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_proto_hotspots.js'), isset($bundles['assets/js/pg_proto_hotspots.min.js']) ? $bundles['assets/js/pg_proto_hotspots.min.js'] : null, 'the collector has its bundle');
}

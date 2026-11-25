<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Publish check's Content group (assets/js/
 * pg_publish_content.js): the sample words and pictures the starters write,
 * found still on a page; a link that leads to the page it sits on; the
 * canvas audit's empty heading moved into the Content group; the findings
 * in the Publish check's finding shape. The script runs under Node; on a
 * machine without Node the runs are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decoded JSON `$expr` prints after the content script is loaded; null
// without Node.
function designer_publish_content_test_eval($expr)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsdcont');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_publish_content.js')
		. "\nvar STARTERS = " . designer_publish_content_test_starters() . ";"
		. "\nconsole.log(JSON.stringify(" . $expr . '));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Two starters as the palette builds them: a block's tree and a
// component's default props.
function designer_publish_content_test_starters()
{
	return '['
		. '{ _id: "s1", type: "semantic", props: { tag: "section", cssClass: "py-5 bg-light text-center", customName: "Hero band",'
		. '  _attrs: [{ name: "style", value: "background-image: url(https://cdn.example.net/hero-band.jpg)" }] }, children: ['
		. '  { _id: "s2", type: "content", props: { contentType: "heading", tag: "h2", text: "Ready when <b>you</b>&nbsp;are", cssClass: "h6 mb-0 me-auto" }, children: [] },'
		. '  { _id: "s3", type: "content", props: { contentType: "paragraph", text: "Get Started", cssClass: "lead" }, children: [] },'
		. '  { _id: "s4", type: "content", props: { contentType: "image", src: "/files/starter-team.jpg", alt: "Team at work" }, children: [] }'
		. ']},'
		. '{ title: "Welcome!", subtitle: "Build something amazing", btnText: "Get Started", bgColor: "#6366f1" }'
		. ']';
}

// What the starters write: texts of three words or more, normalised (tags
// and entities gone, spaces collapsed, lower case), and every picture
// address; class lists, colours and editor names are no sample words.
function test_designer_publish_content_samples()
{
	$got = designer_publish_content_test_eval('PgPublishContent.samples(STARTERS)');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('build something amazing', 'ready when you are', 'team at work'), $got['texts']);
	pg_assert_same(array('/files/starter-team.jpg', 'https://cdn.example.net/hero-band.jpg'), $got['images']);
}

// A page that kept a starter's words or picture: one finding per node and
// kind; a changed text, a short one, a bound one and the person's own
// picture are not; lorem ipsum and example.com are sample words at any
// length; the sample picture hosts count without a starter writing them.
function test_designer_publish_content_sample_values()
{
	$got = designer_publish_content_test_eval('PgPublishContent.check({ _id: "root", type: "root", props: {}, children: ['
		. '{ _id: "h1", type: "content", props: { contentType: "heading", tag: "h1", text: "  Ready when you   ARE " }, children: [] },'
		. '{ _id: "h2", type: "content", props: { contentType: "heading", tag: "h2", text: "Ready when we are" }, children: [] },'
		. '{ _id: "p1", type: "content", props: { contentType: "paragraph", text: "Get Started" }, children: [] },'
		. '{ _id: "p2", type: "content", props: { contentType: "paragraph", text: "Build something amazing", _bindings: { text: "summary" } }, children: [] },'
		. '{ _id: "p3", type: "content", props: { contentType: "paragraph", text: "Lorem ipsum." }, children: [] },'
		. '{ _id: "p4", type: "content", props: { contentType: "paragraph", text: "Write to info@example.com" }, children: [] },'
		. '{ _id: "i1", type: "content", props: { contentType: "image", src: "/files/starter-team.jpg", alt: "Team at work" }, children: [] },'
		. '{ _id: "i2", type: "content", props: { contentType: "image", src: "https://picsum.photos/seed/a/600/400", alt: "Our shop" }, children: [] },'
		. '{ _id: "i3", type: "content", props: { contentType: "image", src: "/files/our-shop.jpg", alt: "Our shop" }, children: [] },'
		. '{ _id: "c1", type: "component", props: { componentType: "hero", title: "Welcome!", subtitle: "Build something amazing", customName: "Main hero" }, children: [] },'
		. '{ _id: "b1", type: "semantic", props: { tag: "section", _attrs: [{ name: "style", value: "background: url(\'https://placehold.co/200x80\')" }] }, children: [] },'
		. '{ _id: "r1", type: "shared_ref", props: { sharedId: 3 }, children: [] }'
		. ']}, { samples: PgPublishContent.samples(STARTERS) })');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(
		array('sample_text', 'h1'), array('sample_text', 'p3'), array('sample_text', 'p4'),
		array('sample_text', 'i1'), array('sample_image', 'i1'), array('sample_image', 'i2'),
		array('sample_text', 'c1'), array('sample_image', 'b1'),
	), array_map(function ($r) {
		return array($r['code'], $r['node']);
	}, $got));
	pg_assert_same('Ready when you ARE', $got[0]['detail'], 'the text as the page has it, spaces collapsed');
	pg_assert_same(1, $got[0]['count']);
	pg_assert_same('/files/starter-team.jpg', $got[4]['detail']);
	pg_assert_same('Main hero', $got[6]['where'], 'an editor name names the node');
	pg_assert_same('Paragraph', $got[1]['where']);
}

// A link that opens the page it sits on, read from the page's marked link
// rows: by the tab's key or by the saved page's id. An anchor on the same
// page, a link in the page's navigation, header or footer, and a link to
// another page are not.
function test_designer_publish_content_link_self()
{
	$got = designer_publish_content_test_eval('PgPublishContent.check({ _id: "root", type: "root", props: {}, children: ['
		. '{ _id: "a1", type: "content", props: { contentType: "link", text: "About us", href: "/about" }, children: [] },'
		. '{ _id: "t1", type: "content", props: { contentType: "paragraph", text: "See <a href=\"/x\">a</a> and <a href=\"/about\">b</a>" }, children: [] },'
		. '{ _id: "a2", type: "content", props: { contentType: "link", text: "Team", href: "/about#team" }, children: [] },'
		. '{ _id: "a3", type: "content", props: { contentType: "link", text: "Home", href: "/" }, children: [] },'
		. '{ _id: "n1", type: "semantic", props: { tag: "nav" }, children: ['
		. '  { _id: "a4", type: "content", props: { contentType: "link", text: "About", href: "/about" }, children: [] }]},'
		. '{ _id: "c1", type: "component", props: { componentType: "navbar" }, children: ['
		. '  { _id: "a5", type: "content", props: { contentType: "link", text: "About", href: "/about" }, children: [] }]}'
		. ']}, { page: { id: 12, tab: "t_about" }, links: ['
		. '{ nodeId: "a1", source: "href", index: 0, label: "About us", href: "/about", mark: "tab", pageId: 12, tab: "t_about" },'
		. '{ nodeId: "t1", source: "text", index: 0, label: "a", href: "/x", mark: "page", pageId: 30, tab: "" },'
		. '{ nodeId: "t1", source: "text", index: 1, label: "b", href: "/about", mark: "page", pageId: 12, tab: "" },'
		. '{ nodeId: "a2", source: "href", index: 0, label: "Team", href: "/about#team", mark: "tab", pageId: 12, tab: "t_about" },'
		. '{ nodeId: "a3", source: "href", index: 0, label: "Home", href: "/", mark: "tab", pageId: 1, tab: "t_home" },'
		. '{ nodeId: "a4", source: "href", index: 0, label: "About", href: "/about", mark: "tab", pageId: 12, tab: "t_about" },'
		. '{ nodeId: "a5", source: "href", index: 0, label: "About", href: "/about", mark: "tab", pageId: 12, tab: "t_about" },'
		. '{ nodeId: "zz", source: "menu", index: 0, label: "Main", href: "", mark: "", pageId: 0, tab: "" }'
		. '] })');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(array('link_self', 'a1', 0), array('link_self', 't1', 1)), array_map(function ($r) {
		return array($r['code'], $r['node'], $r['index']);
	}, $got));
	pg_assert_same(array('About us', '/about'), array($got[0]['where'], $got[0]['detail']));

	// A tab not saved yet is matched by its key alone.
	$got = designer_publish_content_test_eval('PgPublishContent.check({ _id: "root", type: "root", props: {}, children: ['
		. '{ _id: "a1", type: "content", props: { contentType: "link", text: "Here", href: "/new" }, children: [] }]},'
		. '{ page: { id: 0, tab: "t_new" }, links: [{ nodeId: "a1", source: "href", index: 0, label: "Here", href: "/new", mark: "tab", pageId: 0, tab: "t_new" },'
		. '{ nodeId: "a1", source: "href", index: 1, label: "Other", href: "/n2", mark: "page", pageId: 0, tab: "" }] })');
	pg_assert_same(1, count($got));
}

// The rows in the Publish check's finding shape: Content group, to look
// at, a stable id, the sheet's translated sentence over the English one.
function test_designer_publish_content_findings()
{
	$got = designer_publish_content_test_eval('PgPublishContent.findings(['
		. '{ code: "sample_text", node: "h1", shared: 0, index: 0, where: "Heading", detail: "Ready when you are", count: 1 },'
		. '{ code: "link_self", node: "t1", shared: 4, index: 1, where: "b", detail: "/about", count: 1 },'
		. 'null], { link_self: "Bu bağlantı bulunduğu sayfayı açıyor" })');

	if ($got === null) {
		return;
	}

	pg_assert_same(2, count($got));
	pg_assert_same(array(
		'id' => 'content:sample_text:h1:0', 'source' => 'content', 'code' => 'sample_text', 'group' => 'content', 'level' => 'look',
		'text' => "A starter's sample words are still on the page", 'node' => 'h1', 'shared' => 0, 'where' => 'Heading', 'count' => 1,
		'detail' => 'Ready when you are', 'seo' => '', 'concept' => '',
	), $got[0]);
	pg_assert_same(array('content:link_self:t1:1', 'Bu bağlantı bulunduğu sayfayı açıyor', 4), array($got[1]['id'], $got[1]['text'], $got[1]['shared']));
}

// The canvas audit's empty heading belongs to Content: the finding read
// from it moves there, a copy, nothing else changes; the content check
// does not find it a second time.
function test_designer_publish_content_regroup()
{
	$got = designer_publish_content_test_eval('(function () {'
		. 'var found = [{ id: "audit:heading_empty:h9:0", source: "audit", code: "heading_empty", group: "a11y", level: "look" },'
		. '{ id: "audit:img_alt:i1:0", source: "audit", code: "img_alt", group: "a11y", level: "look" },'
		. '{ id: "seo:heading_empty::0", source: "seo", code: "heading_empty", group: "seo", level: "look" }];'
		. 'var out = PgPublishContent.regroup(found);'
		. 'var empty = PgPublishContent.check({ _id: "h9", type: "content", props: { contentType: "heading", tag: "h2", text: "" }, children: [] }, {});'
		. 'return [out.map(function (f) { return f.group; }), found[0].group, empty.length];'
		. '}())');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(array('content', 'a11y', 'seo'), 'a11y', 0), $got);
}

// Nothing to read: no tree, no samples, no links.
function test_designer_publish_content_empty()
{
	$got = designer_publish_content_test_eval('[PgPublishContent.check(null, null), PgPublishContent.samples(null), PgPublishContent.findings(null), PgPublishContent.regroup(null)]');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(array(), array('texts' => array(), 'images' => array()), array(), array()), $got);
}

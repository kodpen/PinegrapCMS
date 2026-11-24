<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Publish check's finding shape (assets/js/
 * pg_publish_findings.js): the designer audit's issues, the SEO check's
 * answer, the rendered draft's accessibility rows and the canvas link
 * marks read into one list, the same concept found twice kept once, the
 * blocking table, a check that failed to run, and a page's summary. The
 * script runs under Node; on a machine without Node the runs are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decoded JSON `$expr` prints after the findings script is loaded; null
// without Node.
function designer_publish_findings_test_eval($expr)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsdfind');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_publish_findings.js')
		. "\nconsole.log(JSON.stringify(" . $expr . '));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// An audit issue keeps its rule, label and node id; its group comes from the
// rule; a page-level one has no node.
function test_designer_publish_findings_from_audit()
{
	$got = designer_publish_findings_test_eval('PgPublishFindings.fromAudit(['
		. '{ rule: "img_alt", label: "img alt is missing", node: { _id: "n1" }, nodeLabel: "Image", seo: "" },'
		. '{ rule: "h1_missing", label: "The page has no H1", node: null, nodeLabel: "Page", seo: "h1_missing" },'
		. '{ rule: "link_blank_noopener", label: "noopener", node: { _id: "n2" }, nodeLabel: "Link", seo: "link_blank_no_noopener" },'
		. '{ rule: "form_no_action", label: "The form is sent nowhere", node: { _id: "n3" }, nodeLabel: "Form" },'
		. 'null'
		. '])');

	if ($got === null) {
		return;
	}

	pg_assert_same(4, count($got), 'a missing issue is skipped');
	pg_assert_same(array(
		'id' => 'audit:img_alt:n1:0', 'source' => 'audit', 'code' => 'img_alt', 'group' => 'a11y', 'level' => 'look',
		'text' => 'img alt is missing', 'node' => 'n1', 'shared' => 0, 'where' => 'Image', 'count' => 1, 'detail' => '', 'seo' => '', 'concept' => '',
	), $got[0]);
	pg_assert_same(array('seo', ''), array($got[1]['group'], $got[1]['node']), 'a page-level issue');
	pg_assert_same('h1_missing', $got[1]['seo']);
	pg_assert_same('links', $got[2]['group']);
	pg_assert_same(array('links', 'block'), array($got[3]['group'], $got[3]['level']), 'a form sent nowhere blocks');
}

// The SEO check's failed meta checks and its findings; a passing or warned
// meta check is no finding; a page without a title blocks.
function test_designer_publish_findings_from_seo()
{
	$got = designer_publish_findings_test_eval('PgPublishFindings.fromSeo({ status: "success",'
		. 'checks: [{ code: "title_present", status: "fail", label: "No title" },'
		. '{ code: "keywords", status: "warn", label: "Few keywords" },'
		. '{ code: "desc_present", status: "ok", label: "Description" },'
		. '{ code: "desc_unique", status: "fail", label: "Same description" }],'
		. 'findings: [{ code: "img_no_alt", severity: "error", occurrences: 3, detail: "a.jpg", label: "3 images" },'
		. '{ code: "title_tag_missing", severity: "error", occurrences: 1, detail: "", label: "No title tag", stored: true }]'
		. '})');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('title_present', 'desc_unique', 'img_no_alt', 'title_tag_missing'), array_column($got, 'code'));
	pg_assert_same(array('block', 'look', 'look', 'block'), array_column($got, 'level'));
	pg_assert_same(array('seo', 'seo', 'seo', 'seo'), array_column($got, 'group'));
	pg_assert_same(3, $got[2]['count']);
	pg_assert_same('a.jpg', $got[2]['detail']);
	pg_assert_same('seo:img_no_alt::0', $got[2]['id']);
}

// The accessibility rows: one finding per row on its first node; a row
// without nodes belongs to the page; no rendered draft (null) gives none.
function test_designer_publish_findings_from_a11y()
{
	$got = designer_publish_findings_test_eval('[PgPublishFindings.fromA11y(['
		. '{ concept: "contrast", code: "a11y_contrast", label: "Low contrast", severity: "warning", occurrences: 2, detail: "#777", nodes: ["n7", "n8"] },'
		. '{ concept: "page_title", code: "a11y_page_title", label: "No title", severity: "error", occurrences: 1, detail: "", nodes: [] }'
		. ']), PgPublishFindings.fromA11y(null)]');

	if ($got === null) {
		return;
	}

	pg_assert_same(2, count($got[0]));
	pg_assert_same(array('a11y', 'look', 'n7', 2, 'contrast'), array($got[0][0]['group'], $got[0][0]['level'], $got[0][0]['node'], $got[0][0]['count'], $got[0][0]['concept']));
	pg_assert_same(array('', 'block'), array($got[0][1]['node'], $got[0][1]['level']), 'no title blocks from the accessibility side too');
	pg_assert_same(array(), $got[1]);
}

// The canvas link marks: a broken link blocks, a link without a target is
// to look at; a link in a shared component keeps its id; a page no link
// leads to is one page-level finding.
function test_designer_publish_findings_from_links()
{
	$got = designer_publish_findings_test_eval('PgPublishFindings.fromLinks(['
		. '{ nodeId: "b1", sharedId: 0, source: "href", index: 0, mark: "broken", label: "Old offer", href: "/old-offer", why: "page" },'
		. '{ nodeId: "b2", sharedId: 4, source: "text", index: 2, mark: "empty", label: "More", href: "#", why: "" },'
		. '{ nodeId: "b3", sharedId: 0, source: "href", index: 0, mark: "broken", label: "Team", href: "/team", why: "kept" }'
		. '], { unreached: true }, { link_broken: "This link leads to a page that is gone" })');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('link_broken', 'link_empty', 'link_broken', 'page_unreached'), array_column($got, 'code'));
	pg_assert_same(array('block', 'look', 'block', 'look'), array_column($got, 'level'));
	pg_assert_same('This link leads to a page that is gone', $got[0]['text'], 'the sheet hands the sentence');
	pg_assert_same('The link has no target', $got[1]['text'], 'the English fallback');
	pg_assert_same(array('b2', 4, 'More', 'links:link_empty:b2:2'), array($got[1]['node'], $got[1]['shared'], $got[1]['where'], $got[1]['id']));
	pg_assert_same('/team', $got[2]['detail']);
	pg_assert_same('', $got[3]['node']);
}

// A component field closed after a place changed it leaves a value behind:
// it blocks.
function test_designer_publish_findings_from_leftovers()
{
	$got = designer_publish_findings_test_eval('PgPublishFindings.fromLeftovers([{ node: "p1", shared: 9, field: "f2", label: "Card › Title" }])');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('component', 'field_leftover', 'content', 'block', 'p1', 9, 'f2'), array($got[0]['source'], $got[0]['code'], $got[0]['group'], $got[0]['level'], $got[0]['node'], $got[0]['shared'], $got[0]['detail']));
}

// The blocking table is short and fixed.
function test_designer_publish_findings_blocking_table()
{
	$got = designer_publish_findings_test_eval('PgPublishFindings.blocking()');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(
		'links:link_broken', 'audit:form_no_action', 'seo:title_present', 'seo:title_tag_missing',
		'a11y:a11y_page_title', 'component:field_leftover',
	), $got);
}

// read(): every source in one list; a concept the canvas found on its node
// is not listed again from the SEO check or the draft; the SEO and the
// accessibility side of one concept are kept once and a blocking one stays
// blocking; a check that failed to run says so in its group and blocks
// nothing; blocking first, then the groups' order.
function test_designer_publish_findings_read()
{
	$got = designer_publish_findings_test_eval('PgPublishFindings.read({'
		. 'audit: [{ rule: "img_alt", label: "img alt is missing", node: { _id: "n1" }, nodeLabel: "Image", seo: "" }],'
		. 'seo: { status: "success", checks: [], findings: ['
		. '  { code: "img_no_alt", severity: "error", occurrences: 1, detail: "", label: "1 image" },'
		. '  { code: "title_tag_missing", severity: "error", occurrences: 1, detail: "", label: "No title tag" },'
		. '  { code: "canonical_missing", severity: "warning", occurrences: 1, detail: "", label: "No canonical" }],'
		. '  a11y: [{ concept: "img_alt", code: "a11y_img_alt", label: "Alt", severity: "error", occurrences: 1, detail: "", nodes: ["n1"] },'
		. '    { concept: "page_title", code: "a11y_page_title", label: "No title", severity: "error", occurrences: 1, detail: "", nodes: [] }],'
		. '  concepts: [{ concept: "img_alt", seo: "img_no_alt", a11y: "a11y_img_alt", editor: ["img_alt"] },'
		. '    { concept: "page_title", seo: "title_tag_missing", a11y: "a11y_page_title", editor: [] }] },'
		. 'links: false'
		. '})');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('a11y_page_title', 'link_failed', 'img_alt', 'canonical_missing'), array_column($got, 'code'));
	pg_assert_same(array('block', 'failed', 'look', 'look'), array_column($got, 'level'));
	pg_assert_same(array('a11y', 'links', 'a11y', 'seo'), array_column($got, 'group'));
}

// The SEO check that answered with an error, or no answer: one failed
// finding in Search engines and one in Accessibility, nothing blocks.
function test_designer_publish_findings_read_failed_checks()
{
	$got = designer_publish_findings_test_eval('[PgPublishFindings.read({ seo: { status: "error", message: "Not available" } }),'
		. 'PgPublishFindings.read({ seo: null, audit: null, links: null })]');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('a11y:a11y_failed', 'seo:seo_failed'), array_map(function ($f) { return $f['group'] . ':' . $f['code']; }, $got[0]));
	pg_assert_same('Not available', $got[0][1]['detail']);
	pg_assert_same(array(), $got[1], 'a check not asked for is no finding');
}

// summary(): "Ready", "n to look at" or "n blocking", with a count per
// group; a failed check is counted on its own.
function test_designer_publish_findings_summary()
{
	$got = designer_publish_findings_test_eval('(function () {'
		. 'var f = PgPublishFindings.read({ audit: ['
		. '  { rule: "img_alt", label: "a", node: { _id: "n1" } },'
		. '  { rule: "form_no_action", label: "b", node: { _id: "n2" } }], links: false });'
		. 'return [PgPublishFindings.summary([]), PgPublishFindings.summary(f), PgPublishFindings.summary(f.slice(2))];'
		. '})()');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('state' => 'ready', 'block' => 0, 'look' => 0, 'failed' => 0,
		'groups' => array('links' => 0, 'a11y' => 0, 'seo' => 0, 'weight' => 0, 'content' => 0)), $got[0]);
	pg_assert_same(array('state' => 'block', 'block' => 1, 'look' => 1, 'failed' => 1,
		'groups' => array('links' => 2, 'a11y' => 1, 'seo' => 0, 'weight' => 0, 'content' => 0)), $got[1]);
	pg_assert_same('look', $got[2]['state']);
}

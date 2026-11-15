<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The comment e-mail pages of the design templates: their Comments widget
 * card always draws the comment, and their System region stays as the
 * fallback for a page whose card cannot (the fixed comment markup); the
 * canvas note a page that keeps its System region gets
 * (_sdCommentsEmailPageOf(), style_designer.src.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/comments_widget_designer_test.php');
require_once(__DIR__ . '/comments_widget_email_test.php');

// Every shipped template, read once: id => the template array.
function comments_widget_email_pages_test_templates()
{
	static $out = null;
	if ($out !== null) {
		return $out;
	}
	$out = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$tpl = _pg_tpl_include($file);
		if (is_array($tpl) && !empty($tpl['pages']) && is_array($tpl['pages'])) {
			$out[basename($file, '.php')] = $tpl;
		}
	}
	return $out;
}

// The nodes of a tree, depth first.
function comments_widget_email_pages_test_nodes($node, &$out)
{
	if (!is_array($node)) {
		return;
	}
	$out[] = $node;
	foreach (isset($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
		comments_widget_email_pages_test_nodes($child, $out);
	}
}

// The template widgets a page tree places (templateWidget refs).
function comments_widget_email_pages_test_widgets($tree)
{
	$nodes = array();
	comments_widget_email_pages_test_nodes($tree, $nodes);
	$keys = array();
	foreach ($nodes as $n) {
		if (isset($n['type'], $n['props']['templateWidget']) && $n['type'] === 'shared_ref') {
			$keys[] = (string) $n['props']['templateWidget'];
		}
	}
	return $keys;
}

// Whether a page tree holds the page's own System region (the unnamed one,
// where get_page_content() puts a comment e-mail's fixed markup).
function comments_widget_email_pages_test_has_system($tree)
{
	$nodes = array();
	comments_widget_email_pages_test_nodes($tree, $nodes);
	foreach ($nodes as $n) {
		if (isset($n['type']) && $n['type'] === 'region' && isset($n['props']['regionType']) && $n['props']['regionType'] === 'system'
			&& (!isset($n['props']['regionName']) || (string) $n['props']['regionName'] === '')) {
			return true;
		}
	}
	return false;
}

// The comment e-mail pages of a template: e-mail page key => the keys of
// the pages naming it ('{{tab:<key>}}' in their comment settings).
function comments_widget_email_pages_test_mail_pages($tpl)
{
	$out = array();
	foreach ($tpl['pages'] as $page) {
		$ref = isset($page['comments']['email_page']) ? (string) $page['comments']['email_page'] : '';
		if (preg_match('/^\{\{tab:([a-z0-9_-]+)\}\}$/', $ref, $m) === 1) {
			$out[$m[1]][] = isset($page['key']) ? (string) $page['key'] : '';
		}
	}
	return $out;
}

// Every comment e-mail page of every template has a Comments widget that
// always draws the comment: the pages naming it hold their own thread or a
// record's (no product page: under one a review may be refused, as
// pg_comments_email_decision() refuses an unverified one), and the card
// keeps its link to the comment (pg_comments_email_drew()). The page keeps
// its System region (owner, 2026-11-01): the fixed markup's place for a
// comment the card does not draw.
function test_comments_widget_email_pages_templates_draw_the_comment()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_drew', 'pg_comments_email_strip', 'pg_comments_widget_draw', '_pg_tpl_include'))) {
		return;
	}

	$data = comments_widget_email_test_data();
	$url = $data['rows'][0]['values']['__comment_url'];
	$found = 0;

	foreach (comments_widget_email_pages_test_templates() as $id => $tpl) {
		$pages = array();
		foreach ($tpl['pages'] as $page) {
			if (isset($page['key'])) {
				$pages[(string) $page['key']] = $page;
			}
		}
		$widgets = isset($tpl['widgets']) && is_array($tpl['widgets']) ? $tpl['widgets'] : array();
		$kind = function ($key) use ($widgets) {
			return isset($widgets[$key]['config']['regionType']) ? (string) $widgets[$key]['config']['regionType'] : '';
		};

		foreach (comments_widget_email_pages_test_mail_pages($tpl) as $mail => $naming) {
			if (!pg_assert_true(isset($pages[$mail]), "$id: the comment e-mail page $mail is a page")) {
				continue;
			}
			$found++;
			$tree = isset($pages[$mail]['tree']) ? $pages[$mail]['tree'] : null;

			foreach ($naming as $key) {
				$on = isset($pages[$key]['tree']) ? comments_widget_email_pages_test_widgets($pages[$key]['tree']) : array();
				pg_assert_false(in_array('catalog_item_view', array_map($kind, $on), true), "$id: $key, which names $mail, shows no product");
			}

			$cards = array_values(array_filter(comments_widget_email_pages_test_widgets($tree), function ($key) use ($kind) {
				return $kind($key) === 'comments';
			}));
			pg_assert_true(count($cards) > 0, "$id: $mail places a Comments widget");

			foreach ($cards as $key) {
				$card = isset($widgets[$key]['tree']) ? $widgets[$key]['tree'] : pg_comments_default_tree();
				list($html) = comments_widget_email_test_drawn($card, $data);
				pg_assert_true(pg_comments_email_drew($html, 12, $url), "$id: the $key card draws the comment with its link");
			}

			pg_assert_true(comments_widget_email_pages_test_has_system($tree), "$id: $mail keeps its System region");
		}
	}

	pg_assert_true($found >= 57, 'the comment e-mail pages are found');
}

// The canvas note: a page another open page names as its comment e-mail
// page, by id or, before the first save, by its tab reference.
function test_comments_widget_email_pages_canvas_note()
{
	$pages = array(
		array('key' => 'post', 'page_id' => 4, 'pg_comments_email_page' => 9),
		array('key' => 'talk', 'page_id' => 0, 'pg_comments_email_page' => 'tab:mail2'),
		array('key' => 'mail', 'page_id' => 9, 'pg_comments_email_page' => 0),
		array('key' => 'mail2', 'page_id' => 0, 'pg_comments_email_page' => 0),
		array('key' => 'home', 'page_id' => 2, 'pg_comments_email_page' => '0'),
		array('key' => 'self', 'page_id' => 7, 'pg_comments_email_page' => 7),
	);
	$res = comments_widget_designer_test_node(array('_sdCommentsEmailPageOf'),
		'var pages = ' . json_encode($pages) . ";\nvar out = {};\n"
		. "pages.forEach(function (p) { out[p.key] = _sdCommentsEmailPageOf(p, pages); });\n"
		. "out.none = _sdCommentsEmailPageOf(null, pages);\n"
		. "out.alone = _sdCommentsEmailPageOf(pages[2], null);\n"
		. "process.stdout.write(JSON.stringify(out));\n");
	if ($res === null) {
		return;
	}

	pg_assert_same(true, $res['mail'], 'a saved page named by id');
	pg_assert_same(true, $res['mail2'], 'a new page named by its tab');
	pg_assert_same(false, $res['post'], 'the page with the thread');
	pg_assert_same(false, $res['home'], 'a page nobody names');
	pg_assert_same(true, $res['self'], 'a page naming itself');
	pg_assert_same(false, $res['none'], 'no page');
	pg_assert_same(false, $res['alone'], 'no open pages');

	$js = comments_widget_designer_test_source('assets/js/style_designer.src.js');
	pg_assert_contains("if (_sdLegacyRegionOwn(node)) _sdAppendSystemRegionEmailNote(doc, rg);", $js, 'the page\'s own System region gets the note');
	pg_assert_contains('.sd-region-email-note', $js, 'the note has its style');
}

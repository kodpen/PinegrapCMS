<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's HTML, ZIP and paste importer
 * (includes/designer_import.php): which scripts an imported page keeps,
 * what a pasted fragment drops, which archive entries reach the file
 * manager and how the folders and file names they get are chosen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

// The context a page conversion needs, with no archive and no database.
function _test_dih_ctx()
{
	$ctx = new stdClass();
	$ctx->user_id = 1;
	$ctx->files = array();
	$ctx->folders = array();
	$ctx->warnings = array();
	$ctx->root_id = 0;
	$ctx->project = 'test';
	return $ctx;
}

// The script entries a page brings, by name.
function _test_dih_js_names($html)
{
	$page = pg_designer_import_html($html, 'index.html', _test_dih_ctx());
	$names = array();
	foreach ($page['js'] as $a) {
		$names[] = $a['name'];
	}
	return $names;
}

function test_designer_import_hunt_consent_script_not_run()
{
	// A script a consent manager holds back until the visitor agrees
	// (type="text/plain") is not one the page runs.
	$names = _test_dih_js_names('<html><head>'
		. '<script type="text/plain" data-cookieconsent="statistics" src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>'
		. '<script src="https://cdn.example.com/app.js"></script>'
		. '<script type="module" src="https://cdn.example.com/mod.js"></script>'
		. '<script type="text/javascript" src="https://cdn.example.com/old.js"></script>'
		. '</head><body><p>x</p></body></html>');

	pg_assert_same(array('app.js', 'mod.js', 'old.js'), $names);
}

function test_designer_import_hunt_consent_script_warned()
{
	$page = pg_designer_import_html('<html><head>'
		. '<script type="text/plain" src="https://cdn.example.com/track.js"></script>'
		. '</head><body></body></html>', 'index.html', _test_dih_ctx());

	pg_assert_contains('track.js', implode("\n", $page['warnings']));
}

// Every node of a converted tree, depth first.
function _test_dih_nodes($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) {
			$out = array_merge($out, _test_dih_nodes($n['children']));
		}
	}
	return $out;
}

// The tree of a converted node list as one string of its props.
function _test_dih_props_text($nodes)
{
	$s = '';
	foreach (_test_dih_nodes($nodes) as $n) {
		$s .= json_encode($n['props'], JSON_UNESCAPED_SLASHES) . "\n";
	}
	return $s;
}

function test_designer_import_hunt_paste_drops_script_inside_text()
{
	// The paste says its scripts were left out: one inside a paragraph,
	// heading or link is left out too.
	$out = pg_designer_import_fragment('<p>&copy; <script>document.write(2026)</script> Firma</p>'
		. '<h2>Title<span><script>alert(1)</script></span></h2><a href="/x">Go<script>alert(2)</script></a>', array('id' => 1));
	$text = _test_dih_props_text($out['children']);

	pg_assert_false(stripos($text, '<script') !== false, 'a script stayed in the text');
	pg_assert_contains('Firma', $text);
	pg_assert_contains('Title', $text);
	pg_assert_contains('3 <script> blocks left out.', implode("\n", $out['warnings']));
}

function test_designer_import_hunt_page_script_inside_text_runs_once()
{
	// On a page import the script becomes the page's script; left in the
	// paragraph as well, it ran twice.
	$page = pg_designer_import_html('<html><body><p>&copy; <script>var y = 2026;</script> Firma</p></body></html>', 'index.html', _test_dih_ctx());
	$text = _test_dih_props_text(array($page['tree']));

	pg_assert_false(stripos($text, '<script') !== false, 'a script stayed in the text');
	pg_assert_same(1, count($page['js']));
	pg_assert_contains('var y = 2026;', $page['js'][0]['content']);
}

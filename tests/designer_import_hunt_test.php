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

function test_designer_import_hunt_blocked_entry_not_written()
{
	// The upload rule every other door asks: an entry it refuses is not a
	// file of the import, under its own name or a defused one.
	foreach (array('assets/shell.phtml', 'cgi-bin/run.cgi', 'x/web.config', 'a/.user.ini', 'p/page.asp', 'i/inc.php5', 'evil.php.png') as $path) {
		pg_assert_same('blocked', pg_di_entry_type($path), $path);
	}
	pg_assert_same('html', pg_di_entry_type('about/index.HTML'));
	pg_assert_same('css', pg_di_entry_type('css/site.css'));
	pg_assert_same('js', pg_di_entry_type('js/app.js'));
	pg_assert_same('asset', pg_di_entry_type('img/logo.svg'));
	pg_assert_same('skip', pg_di_entry_type('__MACOSX/img/._logo.png'));
	pg_assert_same('skip', pg_di_entry_type('src/site.scss'));
}

function test_designer_import_hunt_blocked_entry_warned()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

	pg_assert_contains("pg_di_entry_type(\$norm)", $src);
	pg_assert_contains("files of that type are not allowed", $src);
}

function test_designer_import_hunt_deep_entry_left_out()
{
	// Every folder of an entry's path becomes a folder row: a path of
	// thousands of folders made thousands of rows per entry.
	pg_assert_same('deep', pg_di_entry_type(str_repeat('a/', 5000) . 'x.png'));
	pg_assert_same('deep', pg_di_entry_type(str_repeat('a/', PG_DI_MAX_DEPTH + 1) . 'index.html'));
	pg_assert_same('asset', pg_di_entry_type(str_repeat('a/', PG_DI_MAX_DEPTH) . 'x.png'));
}

function test_designer_import_hunt_folder_segment_cut_before_lookup()
{
	// folder_name holds 100 characters: a longer folder name was stored cut
	// and looked up whole, so it was never found and made again.
	pg_assert_same(str_repeat('ğ', 100), pg_di_folder_segment('  ' . str_repeat('ğ', 140) . ' '));
	pg_assert_same('css', pg_di_folder_segment(' css '));
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_import.php');
	pg_assert_contains('$seg = pg_di_folder_segment($seg);', $src);
}

function test_designer_import_hunt_second_same_name_checks_library()
{
	// img/logo.png and icons/logo.png: the second took logo[1].png without
	// asking the library, and overwrote a logo[1].png already there.
	$library = array('logo[1].png' => true);
	$free = function ($name) use ($library) {
		return !isset($library[$name]);
	};
	$reserved = array();
	$first = pg_di_free_file_name('logo.png', $reserved, $free);
	$reserved[$first] = true;
	$second = pg_di_free_file_name('logo.png', $reserved, $free);

	pg_assert_same('logo.png', $first);
	pg_assert_same('logo[2].png', $second);
	pg_assert_same('readme[1]', pg_di_free_file_name('readme', array('readme' => true), $free));
}

function test_designer_import_hunt_single_page_size_ceiling()
{
	// A page in an archive is read to 8 MB at most, as libxml's memory for
	// its DOM is not counted against memory_limit; a page uploaded on its
	// own was parsed at any size.
	$html = '<html><body><p>' . str_repeat('x', PG_DI_MAX_HTML) . '</p></body></html>';
	$res = pg_designer_import_single_html($html, 'big.html', array('id' => 1));

	pg_assert_same(array(), $res['pages']);
	pg_assert_same(1, count($res['errors']));
	pg_assert_contains('8', $res['errors'][0]);
}

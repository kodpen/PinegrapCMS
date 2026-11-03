<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names Import My Site and the ZIP import write: a name cut
 * to its 100-character column before the uniqueness check (a long tag gave
 * two pages the same cut name), the folder name kept with the scan, the
 * scans kept per form, and the HTML check that refuses an archive before
 * anything is written.
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

function test_import_name_fit_cuts_to_the_column()
{
	pg_assert_same('home-tag', pg_import_name_fit('home-tag'), 'a short name stays');
	$long = 'home-pg-test-' . str_repeat('u', 120);
	pg_assert_same(100, mb_strlen(pg_import_name_fit($long), 'UTF-8'), 'a long name is cut to 100');
	pg_assert_same(mb_substr($long, 0, 100), pg_import_name_fit($long), 'the end (the tag) gives way');
	pg_assert_same(100, mb_strlen(pg_import_name_fit(str_repeat('ğ', 130)), 'UTF-8'), 'counted in characters');
	pg_assert_same('abc', pg_import_name_fit('abc---def', false, 4), 'no separator left at the end');
}

function test_import_name_fit_keeps_the_extension()
{
	$fit = pg_import_name_fit(str_repeat('a', 120) . '-tag.woff2', true);
	pg_assert_same(100, mb_strlen($fit, 'UTF-8'), 'a file name is cut to 100');
	pg_assert_same('.woff2', substr($fit, -6), 'its extension stays');
	pg_assert_same(100, mb_strlen(pg_import_name_fit(str_repeat('a', 120) . '.notanextension', true), 'UTF-8'), 'a long "extension" is cut with the rest');
}

function test_site_import_folder_name()
{
	pg_assert_same('example.com/ Tue 3 Nov 2026 03:05-mytag', pg_site_import_folder_name('https://example.com/', 'Tue 3 Nov 2026 03:05', 'mytag'), 'address, time and tag');
	pg_assert_same('example.com/ T', pg_site_import_folder_name('http://example.com/', 'T', ''), 'no tag');

	$tag = str_repeat('t', pg_site_import_tag_max_length());
	$name = pg_site_import_folder_name('https://example.com/' . str_repeat('p', 120), 'Tue 3 Nov 2026 03:05', $tag);
	pg_assert_same(100, mb_strlen($name, 'UTF-8'), 'a long address is cut to fit');
	pg_assert_same('Tue 3 Nov 2026 03:05-' . $tag, substr($name, -strlen('Tue 3 Nov 2026 03:05-' . $tag)), 'the time and the tag stay whole');
}

function test_site_import_scans_add()
{
	$scans = pg_site_import_scans_add(null, 'a', array('url' => 'https://a.example/'));
	$scans = pg_site_import_scans_add($scans, 'b', array('url' => 'https://b.example/'));
	pg_assert_same('https://a.example/', $scans['a']['url'], 'the first tab keeps its own scan');
	pg_assert_same('https://b.example/', $scans['b']['url'], 'the second tab has its own');

	for ($i = 0; $i < 6; $i++) {
		$scans = pg_site_import_scans_add($scans, 'n' . $i, array(), 3);
	}
	pg_assert_same(array('n3', 'n4', 'n5'), array_keys($scans), 'the oldest beyond the limit are dropped');
}

function test_designer_import_entries_have_html()
{
	pg_assert_same(false, pg_di_entries_have_html(array('css/a.css' => array('type' => 'css', 'index' => 0), 'img/b.svg' => array('type' => 'asset', 'index' => 1))), 'no page: refused before writing');
	pg_assert_same(true, pg_di_entries_have_html(array('a.css' => array('type' => 'css'), 'index.html' => array('type' => 'html'))), 'a page is there');
	pg_assert_same(false, pg_di_entries_have_html(array()), 'empty');
}

// The source pins of the import screen and the undo of a refused ZIP import.
function test_site_import_source_pins()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_design.php');
	pg_assert_contains("lang('Scan mode is required.')", $src, 'the error names the field shown');
	pg_assert_contains('<fieldset class="col-12 my-5"', $src, 'the scan modes are a fieldset');
	pg_assert_contains("h(lang('Import My Site'))", $src, 'the tree root is translated');
	pg_assert_contains("['scans'][\$scan_id]", $src, 'the import takes the scan of its own form');
	pg_assert_contains("'maxlength'=>pg_site_import_tag_max_length()", $src, 'the tag box has a limit');
	pg_assert_true(strpos($src, "\$url_parts['query'] ?? ''") !== false, 'a missing query part reads as empty');

	$zip = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_design_zip.php');
	pg_assert_contains('pg_designer_import_undo($res);', $zip, 'a design that could not be made takes its files back');
}

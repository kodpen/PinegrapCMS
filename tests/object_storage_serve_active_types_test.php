<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Content-Disposition get_file.php asks the object store to
 * answer with (pg_file_serve_disposition()): HTML, XHTML, SVG and XML are
 * documents a browser runs when opened by their own address, and the store
 * cannot add the sandbox policy a local answer carries, so they download.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/file_store_serve.php');

function test_object_storage_serve_active_types_download()
{
	foreach (array('page.html', 'page.HTM', 'a.xhtml', 'a.xht', 'logo.svg', 'logo.svgz', 'feed.xml', 'style.xsl') as $name) {
		pg_assert_same('attachment; filename="' . $name . '"', pg_file_serve_disposition($name, true), $name . ' is never shown inline from the store');
	}
}

function test_object_storage_serve_passive_types_stay_inline()
{
	pg_assert_same('inline; filename="report.pdf"', pg_file_serve_disposition('report.pdf', true));
	pg_assert_same('inline; filename="photo.jpg"', pg_file_serve_disposition('photo.jpg', true));
	pg_assert_same('inline; filename="svg.txt"', pg_file_serve_disposition('svg.txt', true), 'only the extension counts');
	pg_assert_same('attachment; filename="a.zip"', pg_file_serve_disposition('a.zip', false));
}

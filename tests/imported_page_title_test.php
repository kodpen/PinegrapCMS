<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_imported_page_take_title() (update_imported_items.php): the
 * title Import ZIP File reads from an imported HTML file for its page, and
 * the title elements it takes out of the markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/update_imported_items.php');

function test_imported_page_title_on_one_line()
{
	list($title, $html) = pg_imported_page_take_title("<head><title>About &amp; Us</title></head>");
	pg_assert_same('About & Us', $title, 'entities decoded');
	pg_assert_same('<head></head>', $html, 'title element taken out');
}

// An editor that formats the head puts the title on lines of its own.
function test_imported_page_title_over_several_lines()
{
	list($title, $html) = pg_imported_page_take_title("<head>\n<title>\n  Hakkımızda |\n  Örnek Site\n</title>\n</head>");
	pg_assert_same('Hakkımızda | Örnek Site', $title, 'title read across lines, white space collapsed');
	pg_assert_same(false, stripos($html, '<title'), 'no title element left beside the page\'s own');
}

function test_imported_page_title_with_attributes()
{
	list($title, $html) = pg_imported_page_take_title('<head><TITLE lang="tr">Ana Sayfa</TITLE ></head>');
	pg_assert_same('Ana Sayfa', $title, 'attributes and case');
	pg_assert_same('<head></head>', $html, 'title element taken out');
}

function test_imported_page_title_missing()
{
	list($title, $html) = pg_imported_page_take_title('<head><titles>x</titles></head>');
	pg_assert_same('', $title, 'no title');
	pg_assert_same('<head><titles>x</titles></head>', $html, 'markup untouched');
}

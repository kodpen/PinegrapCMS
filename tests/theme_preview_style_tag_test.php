<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The theme designer preview (get_page.php ?edit_theme=true): the session's
 * CSS goes into a <style> tag as CSS, not HTML-escaped (a "'" or ">" there
 * is not decoded, so quoted fonts and child selectors broke), only a closing
 * </style is defused; a theme_id that is not a scalar key is not read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_pg_style_tag_css_keeps_css_text()
{
	$css = "body { font-family: 'Open Sans', sans-serif; }\nul > li { content: \"&\"; }";
	pg_assert_same($css, pg_style_tag_css($css), 'quotes, > and & stay as written');
}

function test_pg_style_tag_css_defuses_closing_tag()
{
	pg_assert_same('a{}<\\/style><script>x</script>', pg_style_tag_css('a{}</style><script>x</script>'), 'closing style tag defused');
	pg_assert_same('a{}<\\/style >', pg_style_tag_css('a{}</STYLE >'), 'case-insensitive');
	pg_assert_same('', pg_style_tag_css(null), 'null gives empty text');
}

function test_get_page_theme_preview_uses_style_tag_css()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_page.php');

	pg_assert_same(false, strpos($source, "'<style type=\"text/css\">' . h(\$code)") !== false, 'no HTML-escaped CSS in the preview');
	pg_assert_contains("'<style type=\"text/css\">' . pg_style_tag_css(\$code)", $source, 'preview CSS through pg_style_tag_css()');
	pg_assert_same(false, strpos($source, "[\$_GET['theme_id']]['code']") !== false, 'theme_id not used as a raw key');
}

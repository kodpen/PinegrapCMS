<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the text the site search index stores for a drawn page
 * (pg_search_index_text() in includes/fn/site_search.php): the page's own
 * content only, without the cookie window, the shared header, menu and
 * footer, or link addresses, so a word found only in those regions no
 * longer finds every page. The index job needs a database, so its call
 * sites are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A visual-design page as the site draws it, with the cookie window injected.
function _site_search_index_text_page()
{
	return '<!DOCTYPE html><html><head><title>Hakkımızda</title>'
		. '<style>.x{color:red}</style><script>var menu = "Kariyer";</script></head><body>'
		. '<header class="site-header"><div class="strip">Sonbahar menüsü geldi. <a href="/menumuz">Menüyü görün</a></div>'
		. '<a href="/giris">Giriş Yap</a> <a href="/kayit-ol">Kayıt Ol</a>'
		. '<nav><ul><li><a href="/menumuz">Menü</a></li><li><a href="/kariyer">Kariyer</a></li></ul></nav></header>'
		. '<main><header><h1>Biz Kimiz</h1></header>'
		. '<section><p>Kahvelerimizi <a href="/etiyopya">Etiyopya</a>&#039;dan getiriyoruz &amp; kavuruyoruz.</p></section>'
		. '<!-- Misafirler --></main>'
		. '<footer><h5>Misafirler</h5><ul><li><a href="/sss">SSS</a></li></ul></footer>'
		. "\n" . '<link rel="stylesheet" href="/pinegrap/assets/css/pg_consent.css">'
		. '<div id="pg-cc" class="pg-cc" data-path="/"><div class="pg-cc-card"><strong>Çerezler</strong>'
		. '<div class="pg-cc-actions"><button>Tümünü reddet</button><button>Tümünü kabul et</button></div></div></div>'
		. '<script src="/pinegrap/assets/js/pg_consent.js" defer></script>'
		. '</body></html>';
}

// With a <main>, only its content is kept: its own <header> with the title
// stays, the shared regions, the cookie window and the head go.
function test_site_search_index_text_main_only()
{
	$text = pg_search_index_text(_site_search_index_text_page());
	pg_assert_contains('Biz Kimiz', $text);
	pg_assert_contains('Kahvelerimizi Etiyopya\'dan getiriyoruz & kavuruyoruz.', $text);
	foreach (array('Çerezler', 'reddet', 'kabul', 'Sonbahar', 'Giriş Yap', 'Kayıt Ol', 'Menü', 'Kariyer', 'Misafirler', 'SSS', 'Hakkımızda', 'color', '/etiyopya', '/menumuz', '(', 'http') as $gone) {
		pg_assert_false(mb_strpos($text, $gone) !== false, var_export($gone, true) . ' is not in the index text');
	}
}

// Without a <main>, header, nav and footer go, wherever they are nested;
// one inside an article or section is the content's own and stays.
function test_site_search_index_text_without_main()
{
	$html = '<body><div class="wrap"><header>Logo <nav><a href="/a">Ana Sayfa</a></nav></header>'
		. '<div class="content"><article><header><h2>Yazı Başlığı</h2></header><p>Gövde metni</p>'
		. '<footer>Yazar: Ayşe</footer></article><nav>Sayfalar 1 2</nav></div>'
		. '<footer><header>İletişim</header> <nav>Harita</nav> Adres</footer></div></body>';
	$text = pg_search_index_text($html);
	foreach (array('Yazı Başlığı', 'Gövde metni', 'Yazar: Ayşe') as $kept) {
		pg_assert_contains($kept, $text);
	}
	foreach (array('Logo', 'Ana Sayfa', 'Sayfalar', 'İletişim', 'Harita', 'Adres') as $gone) {
		pg_assert_false(mb_strpos($text, $gone) !== false, var_export($gone, true) . ' is not in the index text');
	}
}

// Blocks are kept apart, so words of two cells or items do not run together;
// spaces are folded and entities read.
function test_site_search_index_text_spacing()
{
	$text = pg_search_index_text('<main><ul><li>Bir</li><li>İki</li></ul><table><tr><td>Üç</td><td>Dört</td></tr></table>'
		. '<p>Beş&nbsp;&nbsp; altı<br>yedi</p><img src="/x.jpg" alt="foto"></main>');
	pg_assert_false(strpos($text, 'Birİki') !== false, 'list items apart');
	pg_assert_false(strpos($text, 'ÜçDört') !== false, 'cells apart');
	pg_assert_contains('Beş altı', $text);
	pg_assert_false(strpos($text, 'altıyedi') !== false, 'a line break parts words');
	pg_assert_false(strpos($text, '  ') !== false, 'no double space');
	pg_assert_same($text, trim($text));
	pg_assert_false(strpos($text, 'foto') !== false || strpos($text, 'x.jpg') !== false, 'no image addresses');
}

// An unclosed region runs to the end and an empty input gives ''.
function test_site_search_index_text_edges()
{
	pg_assert_same('', pg_search_index_text(''));
	pg_assert_same('', pg_search_index_text('<div id="pg-cc"><p>Çerezler</p></div>'));
	pg_assert_same('Metin', pg_search_index_text('<p>Metin</p><footer>Alt bilgi'));
	pg_assert_same('İçerik', pg_search_index_text('<main data-x="1"><p>İçerik</p>'));
	pg_assert_same('Düz metin', pg_search_index_text('Düz metin'));
	// <mainframe>, <navbar> or <headers> are not the elements.
	pg_assert_same('a b c', pg_search_index_text('<navbar>a</navbar> <headers>b</headers> <mainx>c</mainx>'));
}

// Every index path takes its text from the same extraction, and no drawn
// page reaches convert_html_to_text() (the e-mail body converter) any more.
function test_site_search_index_text_call_sites()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');
	pg_assert_same(4, substr_count($src, 'pg_search_index_text($content)'), 'pages, calendar events, form records, designer records');
	pg_assert_false((bool) preg_match('/convert_html_to_text\(\$content\)/', $src), 'no drawn page through the e-mail converter');
}

// The cookie window is left out of a page drawn for the index or for the
// SEO structure pass, and kept for every visitor render.
function test_site_search_index_text_no_consent_in_background_render()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_true((bool) preg_match('/if \(\$mode != \'edit\' && !pg_seo_rendering\(\)\s*&& !\(defined\(\'UPDATE_SEARCH_INDEX\'\) && UPDATE_SEARCH_INDEX === true\)\) \{\s*\$content = pg_consent_inject\(\$content\);/', $src), 'consent guard');
}

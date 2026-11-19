<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page pass of picture text translation: which addresses of a
 * page name this site's pictures, each place a language's picture is swapped
 * in (src, srcset, a source's srcset, the share picture, a link to a
 * picture, url() in a style attribute) and the places left alone, that only
 * a drawn row is swapped, the waiting rows a first visit notes, the one
 * query a page costs and the folder rule that keeps the source's rights.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The site's hosts the tests read addresses against.
function picture_translation_page_hosts()
{
	return array('example.com' => true, 'www.example.com' => true);
}

// banner.jpg and hero.png have an English picture each.
function picture_translation_page_map()
{
	return array('banner.jpg' => 'banner-en-12.jpg', 'hero.png' => 'hero en.png');
}

// The swap of one stretch of HTML with the tests' map, base '/'.
function picture_translation_page_swap($html)
{
	return pg_picture_tr_page_swap($html, '/', picture_translation_page_hosts(), picture_translation_page_map());
}

// An address names a picture of this site: root-relative or on its own host,
// with a width or a format, never another host, a folder or another type.
function test_picture_translation_page_url_name()
{
	$hosts = picture_translation_page_hosts();

	pg_assert_same('banner.jpg', pg_picture_tr_url_name('/banner.jpg', '/', $hosts), 'root-relative');
	pg_assert_same('banner.jpg', pg_picture_tr_url_name('/banner.jpg?w=960&amp;f=webp', '/', $hosts), 'with a width and a format');
	pg_assert_same('banner.jpg', pg_picture_tr_url_name('https://www.example.com/banner.jpg', '/', $hosts), 'on its own host');
	pg_assert_same('hero en.png', pg_picture_tr_url_name('/hero%20en.png', '/', $hosts), 'an encoded name is decoded');
	pg_assert_same('banner.jpg', pg_picture_tr_url_name('/site/banner.jpg', '/site/', $hosts), 'below the site path');
	pg_assert_same('', pg_picture_tr_url_name('https://cdn.other.com/banner.jpg', '/', $hosts), 'another host');
	pg_assert_same('', pg_picture_tr_url_name('/pinegrap/assets/x.png', '/', $hosts), 'a folder');
	pg_assert_same('', pg_picture_tr_url_name('/document.pdf', '/', $hosts), 'not a picture');
	pg_assert_same('', pg_picture_tr_url_name('banner.jpg', '/', $hosts), 'a relative address');
	pg_assert_same('', pg_picture_tr_url_name('data:image/png;base64,AAAA', '/', $hosts), 'a data address');
	pg_assert_same('', pg_picture_tr_url_name('/about', '/', $hosts), 'a page');
}

// Every place the pass reads is swapped; host, width, format and the quote
// stay.
function test_picture_translation_page_swaps_each_place()
{
	pg_assert_same('<img src="/banner-en-12.jpg" alt="Sale">', picture_translation_page_swap('<img src="/banner.jpg" alt="Sale">'), 'img src');
	pg_assert_same('<img srcset="/banner-en-12.jpg?w=480 480w, /banner-en-12.jpg?w=960 960w, /banner-en-12.jpg 1200w" src="/banner-en-12.jpg">',
		picture_translation_page_swap('<img srcset="/banner.jpg?w=480 480w, /banner.jpg?w=960 960w, /banner.jpg 1200w" src="/banner.jpg">'), 'img srcset');
	pg_assert_same('<source type="image/webp" srcset="/banner-en-12.jpg?w=960&amp;f=webp 960w">',
		picture_translation_page_swap('<source type="image/webp" srcset="/banner.jpg?w=960&amp;f=webp 960w">'), 'a source srcset');
	pg_assert_same('<meta property="og:image" content="https://example.com/banner-en-12.jpg">',
		picture_translation_page_swap('<meta property="og:image" content="https://example.com/banner.jpg">'), 'the share picture');
	pg_assert_same('<a href="/banner-en-12.jpg" data-fancybox>', picture_translation_page_swap('<a href="/banner.jpg" data-fancybox>'), 'a link to the picture');
	pg_assert_same('<div style="background-image:url(\'/hero%20en.png\')">', picture_translation_page_swap('<div style="background-image:url(\'/hero.png\')">'), 'url() in a style attribute');
	pg_assert_same('<div style="background:url(&quot;/hero%20en.png?w=1440&quot;) center">', picture_translation_page_swap('<div style="background:url(&quot;/hero.png?w=1440&quot;) center">'), 'url() quoted as an entity');
	pg_assert_same("<img src='/banner-en-12.jpg'>", picture_translation_page_swap("<img src='/banner.jpg'>"), 'single quotes');
}

// The places the pass leaves alone keep the source picture.
function test_picture_translation_page_leaves_other_places()
{
	$untouched = array(
		'a style block'     => '<style>.hero{background:url(/hero.png)}</style>',
		'JSON-LD'           => '<script type="application/ld+json">{"image":"https://example.com/banner.jpg"}</script>',
		'variant data'      => '<script>pgCivVariants={"photo":"/banner.jpg"}</script>',
		'another meta'      => '<meta name="thumbnail" content="/banner.jpg">',
		'a data attribute'  => '<img data-src="/banner.jpg" src="/other.jpg">',
		'a picture of no row' => '<img src="/other.jpg">',
		'another host'      => '<img src="https://cdn.other.com/banner.jpg">',
		'text'              => '<p>/banner.jpg</p>',
	);

	foreach ($untouched as $label => $html) {
		pg_assert_same($html, picture_translation_page_swap($html), $label);
	}
}

// The names a page shows, once each, from every place the pass reads.
function test_picture_translation_page_names()
{
	$html = '<head><meta property="og:image" content="https://example.com/share.jpg"></head>'
		. '<img src="/banner.jpg" srcset="/banner.jpg?w=480 480w">'
		. '<div style="background:url(/hero.png)"></div><a href="/about">x</a>'
		. '<script>var x = "/hidden.jpg";</script>';

	pg_assert_same(array('share.jpg', 'banner.jpg', 'hero.png'), pg_picture_tr_page_names($html, '/', picture_translation_page_hosts()), 'names in order, once each');
}

// Only a drawn row whose picture sits in the source's folder is swapped; a
// picture with uses and no row in the language gets a waiting row, never in
// a right-to-left language.
function test_picture_translation_page_plan()
{
	$rows = array(
		array('id' => '1', 'name' => 'banner.jpg', 'state' => '1', 'picture' => 'banner-en.jpg', 'has_uses' => '1'),
		array('id' => '2', 'name' => 'old.jpg', 'state' => '2', 'picture' => 'old-en.jpg', 'has_uses' => '1'),
		array('id' => '3', 'name' => 'wait.jpg', 'state' => '0', 'picture' => null, 'has_uses' => '1'),
		array('id' => '4', 'name' => 'new.jpg', 'state' => null, 'picture' => null, 'has_uses' => '1'),
		array('id' => '5', 'name' => 'photo.jpg', 'state' => null, 'picture' => null, 'has_uses' => null),
		array('id' => '6', 'name' => 'moved.jpg', 'state' => '1', 'picture' => null, 'has_uses' => '1'),
	);

	$plan = pg_picture_tr_page_plan($rows, false);
	pg_assert_same(array('banner.jpg' => 'banner-en.jpg'), $plan['map'], 'only the drawn row with its picture in the folder');
	pg_assert_same(array(4), $plan['waiting'], 'a picture with words met for the first time');

	$rtl = pg_picture_tr_page_plan($rows, true);
	pg_assert_same(array(), $rtl['waiting'], 'no waiting row for a right-to-left language');
}

// The query finds the language's picture only in the source's folder, so
// it is served by the source's rights (a private folder stays private).
function test_picture_translation_page_query_keeps_the_folder()
{
	$sql = pg_picture_tr_page_sql(array('banner.jpg', "o'neil.png"), 'en');

	pg_assert_contains('p.folder = f.folder', $sql, 'the picture in the source folder');
	pg_assert_contains("ft.language = 'en'", $sql, 'the language');
	pg_assert_contains("'o\\'neil.png'", $sql, 'names escaped');
	pg_assert_contains("u.owner_type = 'file'", $sql, 'whether the picture has words');
}

// One query reads a page; a first visit adds one insert; a page with no
// picture of this site asks nothing.
function test_picture_translation_page_pass_queries()
{
	$calls = array();
	$query = function ($sql) use (&$calls) {
		$calls[] = $sql;

		if (strpos($sql, 'SELECT') === 0) {
			return array(
				array('id' => '1', 'name' => 'banner.jpg', 'state' => '1', 'picture' => 'banner-en.jpg', 'has_uses' => '1'),
				array('id' => '4', 'name' => 'new.jpg', 'state' => null, 'picture' => null, 'has_uses' => '1'),
			);
		}

		return true;
	};

	$html = '<img src="/banner.jpg"><img src="/new.jpg"><img src="/banner.jpg?w=480">';
	$out = pg_picture_tr_page_apply($html, 'en', '/', picture_translation_page_hosts(), false, $query);

	pg_assert_same('<img src="/banner-en.jpg"><img src="/new.jpg"><img src="/banner-en.jpg?w=480">', $out, 'swapped');
	pg_assert_same(2, count($calls), 'one read and one insert');
	pg_assert_contains('INSERT IGNORE INTO file_translation', $calls[1], 'the waiting row');
	pg_assert_contains("('4', 'en', 0,", $calls[1], 'for the new picture, waiting');

	$calls = array();
	pg_assert_same('<p>No pictures</p>', pg_picture_tr_page_apply('<p>No pictures</p>', 'en', '/', picture_translation_page_hosts(), false, $query), 'nothing to swap');
	pg_assert_same(0, count($calls), 'no query for a page with no picture');
}

// The pass runs in the finished-page pass of a page in another language,
// after the links are rewritten.
function test_picture_translation_page_pass_hooked()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/render.php');
	$links = strpos($source, '$content = pg_tr_rewrite_links($content, $prefix);');
	$pass = strpos($source, 'pg_picture_tr_page_pass($content, $current)');

	pg_assert_true($pass !== false, 'called');
	pg_assert_true($pass > $links, 'after the links are made root-relative');
}

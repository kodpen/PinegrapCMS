<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Speculation Rules prefetch of menu links
 * (includes/fn/speculation.php): which menu and navbar addresses are listed,
 * when a page gets no block, and that the block is valid JSON no address can
 * break out of.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/render.php');

function speculation_test_path($href)
{
	return pg_speculation_path($href, array('example.com', 'www.example.com'), '/', 'pinegrap');
}

// Same-site page addresses pass as they are written, without the fragment.
function test_speculation_path_keeps_same_site_pages()
{
	pg_assert_same('/about', speculation_test_path('/about'));
	pg_assert_same('/about', speculation_test_path('/about#team'));
	pg_assert_same('/', speculation_test_path('/'));
	pg_assert_same('/shop/shoes', speculation_test_path('https://example.com/shop/shoes'));
	pg_assert_same('/', speculation_test_path('https://WWW.example.com'));
	pg_assert_same('/news.html', speculation_test_path('/news.html'));
	pg_assert_same('/site/blog', pg_speculation_path('/site/blog', array('example.com'), '/site/', 'pinegrap'));
}

// A query string, another host, another scheme, an anchor, a relative path,
// the panel, a file and an address outside the site's path are left out.
function test_speculation_path_leaves_out_query_offsite_and_acting_links()
{
	foreach (array(
		'/catalog?sort=new',
		'/search?q=x',
		'?page=2',
		'https://other.example/about',
		'//other.example/about',
		'https://example.com.evil.test/about',
		'mailto:info@example.com',
		'tel:+90',
		'javascript:void(0)',
		'#top',
		'',
		'about',
		'../about',
		'/pinegrap/logout.php',
		'/pinegrap/',
		'/PINEGRAP/view_pages.php',
		'/pinegrap',
		'/brochure.pdf',
		'/archive.zip',
		'/a/../pinegrap/x',
		'https://user:pass@example.com/about',
		'/a b',
	) as $href) {
		pg_assert_same('', speculation_test_path($href), $href);
	}

	pg_assert_same('', pg_speculation_path('/other/about', array('example.com'), '/site/', 'pinegrap'), 'outside the site path');
}

// Only public content pages are kept; sign-in, cart, checkout and account
// pages are not, nor a type nobody named.
function test_speculation_page_types()
{
	foreach (array('standard', 'catalog', 'catalog detail', 'form list view', 'photo gallery') as $type) {
		pg_assert_true(pg_speculation_page_type_kept($type), $type);
	}

	foreach (array('login', 'logout', 'registration entrance', 'shopping cart', 'express order', 'order form', 'order preview',
		'billing information', 'order receipt', 'my account', 'set password', 'forgot password', 'search results', 'error', 'new type') as $type) {
		pg_assert_false(pg_speculation_page_type_kept($type), $type);
	}
}

// The list keeps the menu's order, drops unknown, private and acting pages,
// the page shown and repeats.
function test_speculation_pick_from_menu_items()
{
	$pages = array(
		''        => array('type' => 'standard', 'public' => true, 'home' => true),
		'home'    => array('type' => 'standard', 'public' => true, 'home' => true),
		'about'   => array('type' => 'standard', 'public' => true, 'home' => false),
		'shop'    => array('type' => 'catalog', 'public' => true, 'home' => false),
		'members' => array('type' => 'standard', 'public' => false, 'home' => false),
		'cart'    => array('type' => 'shopping cart', 'public' => true, 'home' => false),
		'login'   => array('type' => 'login', 'public' => true, 'home' => false),
		'çay'     => array('type' => 'standard', 'public' => true, 'home' => false),
	);

	$paths = array('/shop', '/about', '/members', '/cart', '/login', '/missing', '/', '/shop', '/%C3%A7ay');

	pg_assert_same(array('/shop', '/about', '/', '/%C3%A7ay'), pg_speculation_pick($paths, '/', $pages, 'contact', false));
	pg_assert_same(array('/shop', '/%C3%A7ay'), pg_speculation_pick($paths, '/', $pages, 'about', true), 'the page shown and the home page left out');
	pg_assert_same(array('/About', '/'), pg_speculation_pick(array('/About', '/'), '/', $pages, 'contact', false), 'names compare in lower case');
}

// The list is cut at the limit.
function test_speculation_pick_limit()
{
	$pages = array();
	$paths = array();

	for ($i = 0; $i < 40; $i++) {
		$pages['p' . $i] = array('type' => 'standard', 'public' => true, 'home' => false);
		$paths[] = '/p' . $i;
	}

	pg_assert_same(PG_SPECULATION_MAX_URLS, count(pg_speculation_pick($paths, '/', $pages, '', false)));
}

// No block while the switch is off or for a signed-in member or staff; a
// page in another language gets one (its addresses carry the prefix).
function test_speculation_rules_wanted()
{
	pg_assert_true(pg_speculation_rules_wanted(true, false));
	pg_assert_false(pg_speculation_rules_wanted(false, false), 'switch off');
	pg_assert_false(pg_speculation_rules_wanted(true, true), 'signed in');
}

// The prefix rule of the translation module, with a site whose pages are
// about, shop and çay and whose languages are served under /en/ and /de/.
function speculation_test_prefix_path($path, $prefix)
{
	return pg_tr_prefix_path_rule($path, $prefix, '/', array('en' => 'en', 'de' => 'de'),
		array('about' => 1, 'shop' => 2, 'çay' => 3, 'cart' => 4), function ($rest) {
			return false;
		});
}

// On a page in a second language a link that already carries the page's
// prefix names the page without it; the exclusions run on what is left.
function test_speculation_path_drops_the_language_prefix()
{
	$hosts = array('example.com');

	pg_assert_same('/about', pg_speculation_path('/en/about', $hosts, '/', 'pinegrap', 'en'));
	pg_assert_same('/about', pg_speculation_path('/EN/about', $hosts, '/', 'pinegrap', 'en'));
	pg_assert_same('/about', pg_speculation_path('https://example.com/en/about#x', $hosts, '/', 'pinegrap', 'en'));
	pg_assert_same('/', pg_speculation_path('/en/', $hosts, '/', 'pinegrap', 'en'));
	pg_assert_same('/', pg_speculation_path('/en', $hosts, '/', 'pinegrap', 'en'));
	pg_assert_same('/site/about', pg_speculation_path('/site/en/about', $hosts, '/site/', 'pinegrap', 'en'));
	pg_assert_same('/about', pg_speculation_path('/about', $hosts, '/', 'pinegrap', 'en'), 'unprefixed link');
	pg_assert_same('/english', pg_speculation_path('/english', $hosts, '/', 'pinegrap', 'en'), 'a page whose name starts like the prefix');
	pg_assert_same('/en/about', pg_speculation_path('/en/about', $hosts, '/', 'pinegrap'), 'default language: no prefix to drop');

	foreach (array('/en/pinegrap/', '/en/pinegrap/logout.php', '/en/brochure.pdf', '/en/about?x=1', '/en/../pinegrap/x') as $href) {
		pg_assert_same('', pg_speculation_path($href, $hosts, '/', 'pinegrap', 'en'), $href);
	}
}

// A page in a second language lists its addresses with the language prefix,
// once; the default language's list is left as it is.
function test_speculation_localize()
{
	$paths = array('/about', '/', '/%C3%A7ay', '/shop/item');

	pg_assert_same(array('/en/about', '/en/', '/en/%C3%A7ay', '/en/shop/item'),
		pg_speculation_localize($paths, 'en', 'speculation_test_prefix_path'));
	pg_assert_same($paths, pg_speculation_localize($paths, '', 'speculation_test_prefix_path'), 'default language');
	pg_assert_same(array('/en/about'), pg_speculation_localize(array('/en/about'), 'en', 'speculation_test_prefix_path'), 'not doubled');
}

// The whole way on a page in English: menu links with and without the
// prefix, an acting page and a file are left out as on the default page.
function test_speculation_second_language_block()
{
	$pages = array(
		''      => array('type' => 'standard', 'public' => true, 'home' => true),
		'about' => array('type' => 'standard', 'public' => true, 'home' => false),
		'shop'  => array('type' => 'catalog', 'public' => true, 'home' => false),
		'cart'  => array('type' => 'shopping cart', 'public' => true, 'home' => false),
	);

	$paths = array();

	foreach (array('/about', '/en/shop', '/en/about', '/en/cart', '/cart', '/en/files/a.pdf', '/') as $href) {
		$path = pg_speculation_path($href, array('example.com'), '/', 'pinegrap', 'en');

		if ($path !== '') {
			$paths[] = $path;
		}
	}

	pg_assert_same(array('/en/about', '/en/shop', '/en/'), pg_speculation_localize(
		pg_speculation_pick($paths, '/', $pages, 'contact', false), 'en', 'speculation_test_prefix_path'));
}

// The block is one prefetch rule with a list source and moderate eagerness,
// valid JSON inside the script element; nothing for an empty list.
function test_speculation_rules_html_is_valid_json()
{
	pg_assert_same('', pg_speculation_rules_html(array()));

	$html = pg_speculation_rules_html(array('/about', '/shop', '/about'));

	pg_assert_true(preg_match('~^<script type="speculationrules">(.*)</script>$~s', $html, $m) === 1, 'script element');

	$rules = json_decode($m[1], true);

	pg_assert_same(array('prefetch' => array(array(
		'source'    => 'list',
		'urls'      => array('/about', '/shop'),
		'eagerness' => 'moderate',
	))), $rules);
}

// No address can close the element or open another tag.
function test_speculation_rules_html_escapes()
{
	$evil = '/a</script><script>alert(1)</script>&"\'';
	$html = pg_speculation_rules_html(array($evil, '/çay'));

	pg_assert_same(1, substr_count(strtolower($html), '</script'), 'one closing tag');

	$json = substr($html, strlen('<script type="speculationrules">'), -strlen('</script>'));

	pg_assert_same(0, preg_match('~[<>&\'"]~', str_replace('"', '', $json)), 'no <, >, & or quote inside the values');
	$rules = json_decode($json, true);

	pg_assert_same(array($evil, '/çay'), $rules['prefetch'][0]['urls']);
}

// Navbar links are read by their nav-link class; toggles and other links
// are not.
function test_speculation_nav_hrefs()
{
	$html = '<nav class="navbar"><a class="navbar-brand" href="/">Brand</a><ul class="navbar-nav">'
		. '<li><a class="nav-link active" href="/about" aria-current="page">About</a></li>'
		. '<li><a href=\'/shop?x=1&amp;y=2\' class=\'nav-link\'>Shop</a></li>'
		. '<li><a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">More</a></li>'
		. '<li><a class="nav-linked" href="/no">No</a></li>'
		. '</ul></nav><a href="/body-link">Body</a>';

	pg_assert_same(array('/about', '/shop?x=1&y=2'), pg_speculation_nav_hrefs($html));
	pg_assert_same(array(), pg_speculation_nav_hrefs('<p>no links</p>'));
}

// The menus note their addresses while they are drawn, and a take forgets
// them.
function test_speculation_note_and_take()
{
	pg_speculation_take();
	pg_speculation_note(array('/about', '', 5, '/shop'));

	pg_assert_same(array('/about', '/shop'), pg_speculation_take());
	pg_assert_same(array(), pg_speculation_take());
}

// The renderers note what they draw, and the page splices the block outside
// edit mode and e-mail.
function test_speculation_wired_into_the_renderers()
{
	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content.php');
	$widget  = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_menu.php');
	$page    = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_true(strpos($content, 'pg_speculation_note(') !== false, 'get_menu_content() notes its links');
	pg_assert_true(strpos($widget, 'pg_speculation_note(') !== false, 'the Menu widget notes its links');
	pg_assert_true(preg_match("~if \(\(\\\$mode != 'edit'\) && \(\\\$email == FALSE\)\) \{\s*\\\$content = pg_speculation_rules_inject\(~", $page) === 1, 'spliced outside edit mode and e-mail');
}

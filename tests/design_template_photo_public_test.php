<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photography Studio design template's public pages
 * (includes/design_templates/pinegrap-photo/pages_public.php): the five
 * pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the home places are the ones
 * the other parts define, the price table opens the booking request with
 * the package chosen and shows each price and deposit as the shop writes
 * it, the category tiles open the gallery list filtered, search anchors
 * and classes are the template's own, no fixed date, and every literal has
 * its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_php_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/pages_public.php';
	$out = array('part' => $public_part, 'packages' => $packages, 'package_prices' => $package_prices,
		'package_deposits' => $package_deposits, 'booking_package_choices' => $booking_package_choices,
		'category_titles' => $category_titles, 'money' => $money);
	return $out;
}

// Every node of a tree, depth first.
function _test_php_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_php_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_php_pages()
{
	$pages = array();
	foreach (_test_php_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_photo_public_pages_are_the_plans()
{
	$part = _test_php_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'packages', 'experience', 'about', 'faq'), array_keys(_test_php_pages()), 'the five public pages');
	$names = array();
	foreach (_test_php_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name once");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_photo_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'packages', 'experience', 'about', 'faq',
		'galleries', 'gallery', 'gallery_new', 'book_online',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'client_area', 'proofing_sample', 'selection', 'selection_view', 'my_selections', 'staff_selections',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_php_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('vouchers', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured galleries, the Google reviews and the
// journal, the widgets the galleries and the standard parts define; this
// part defines none.
function test_photo_public_home_widgets()
{
	pg_assert_same(array(), _test_php_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_php_pages() as $key => $p) {
		$nodes = array();
		_test_php_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_galleries', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A row of the price table for each package, with its price and deposit
// as the shop writes them and a button opening the booking request with
// the package chosen in the words of the Package list.
function test_photo_public_price_table_opens_the_request()
{
	$d = _test_php_load();
	$nodes = array();
	_test_php_nodes(_test_php_pages()['packages']['tree'], $nodes);
	$chosen = array();
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:book}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:book}}?')), $q);
		pg_assert_true(isset($q['package']) && in_array($q['package'], $d['booking_package_choices'], true), 'the link names a Package answer');
		$chosen[] = $q['package'];
	}
	pg_assert_same(count($d['packages']), count(array_unique($chosen)), 'every package can be chosen');
	foreach ($d['packages'] as $key => $p) {
		pg_assert_true(in_array(call_user_func($d['money'], $d['package_prices'][$key]), $texts, true), "package $key: its price in the table");
		pg_assert_true(in_array(call_user_func($d['money'], $d['package_deposits'][$key]), $texts, true), "package $key: its deposit in the table");
	}
}

// The home's category tiles open the gallery list on each category, in the
// words the Category list uses.
function test_photo_public_category_tiles_filter_the_galleries()
{
	$d = _test_php_load();
	$nodes = array();
	_test_php_nodes(_test_php_pages()['home']['tree'], $nodes);
	$seen = array();
	$prefix = '{{page:galleries}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		pg_assert_true(isset($q['category']) && in_array($q['category'], $d['category_titles'], true), 'the tile names a Category answer');
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['category_titles']), count($seen), 'a tile per category');
}

// The deposit question names the smallest and the largest deposit as the
// shop writes them.
function test_photo_public_faq_names_the_deposits()
{
	$d = _test_php_load();
	$json = json_encode(_test_php_pages()['faq']['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	foreach (array('mini', 'wedding') as $key) {
		pg_assert_contains(call_user_func($d['money'], $d['package_deposits'][$key]), $json, "the questions name the $key deposit");
	}
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_photo_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_php_pages() as $key => $p) {
		$nodes = array();
		_test_php_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'ph-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_photo_public_no_fixed_dates()
{
	$json = json_encode(_test_php_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a gallery reads "galeri", a package
// "paket", a shoot "çekim" and a deposit "kapora".
function test_photo_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bGaller(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
		if (preg_match('/\b[Pp]ackages?\b/', $key)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
		if (preg_match('/\b[Ss]hoot\b/', $key)) pg_assert_true(mb_stripos($tr, 'çekim', 0, 'UTF-8') !== false, "'$key' says çekim");
		if (preg_match('/\b[Dd]eposits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
}

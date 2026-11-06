<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Wedding Planner and Venue design template's public pages
 * (includes/design_templates/pinegrap-wedding/pages_public.php): the
 * plan's eight pages in the public folder and none needing the shop, every
 * link naming a page of the finished template, the home placing the
 * widgets the other parts define, the package table opening the enquiry
 * with each package chosen at its price, the gallery showing every
 * picture under its space, the timeline showing every stage, the deposit
 * reading one amount, the template's own ids and classes, no fixed date
 * and every literal with its Turkish.
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
function _test_wdp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/pages_public.php';
	$out = array('part' => $public_part, 'packages' => $packages, 'package_prices' => $package_prices,
		'package_choices' => $package_choices, 'spaces' => $spaces, 'gallery' => $gallery, 'timeline' => $timeline,
		'deposit_text' => $money($deposit), 'money' => $money);
	return $out;
}

// Every node of a tree, depth first.
function _test_wdp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_wdp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_wdp_pages()
{
	$pages = array();
	foreach (_test_wdp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The nodes of one page that carry a given label.
function _test_wdp_labelled($key, $label)
{
	$nodes = array();
	_test_wdp_nodes(_test_wdp_pages()[$key]['tree'], $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (($n['props']['_label'] ?? '') === $label) $out[] = $n;
	}
	return $out;
}

function test_wedding_public_pages_are_the_plans()
{
	$part = _test_wdp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'services', 'packages', 'venue', 'gallery', 'timeline', 'faq'), array_keys(_test_wdp_pages()), 'the eight public pages');
	$names = array();
	foreach (_test_wdp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name is its own");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_wedding_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'about', 'services', 'packages', 'venue', 'gallery', 'timeline', 'faq',
		'weddings', 'wedding', 'wedding_new',
		'enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'privacy', 'email_reply', 'book_visit',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_wdp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('shop', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured weddings, the Google reviews and the
// journal, the widgets the Real Weddings and the standard parts define;
// this part defines none.
function test_wedding_public_home_widgets()
{
	pg_assert_same(array(), _test_wdp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_wdp_pages() as $key => $p) {
		$nodes = array();
		_test_wdp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_weddings', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A row of the package table for each package, with its price as the
// shop writes it and a button opening the enquiry with the package chosen
// in the words of the Package list.
function test_wedding_public_package_table_opens_the_enquiry()
{
	$d = _test_wdp_load();
	$rows = 0;
	foreach (_test_wdp_labelled('packages', 'Table Body') as $tbody) {
		foreach ($tbody['children'] as $tr) {
			$rows++;
			$nodes = array();
			_test_wdp_nodes($tr, $nodes);
			$texts = array();
			$choices = array();
			foreach ($nodes as $n) {
				if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
				$href = (string)($n['props']['href'] ?? '');
				if (strpos($href, '{{page:enquiry}}?') !== 0) continue;
				parse_str(substr($href, strlen('{{page:enquiry}}?')), $q);
				$choices[] = $q['package'] ?? '';
			}
			pg_assert_same(1, count($choices), 'one enquiry link per row');
			pg_assert_true(in_array($choices[0], $d['package_choices'], true), 'the link names a Package answer');
			$key = null;
			foreach ($d['packages'] as $k => $p) if ($p['title'] === $choices[0]) $key = $k;
			pg_assert_true(in_array(call_user_func($d['money'], $d['package_prices'][$key]), $texts, true), "package $key: its price in its row");
		}
	}
	pg_assert_same(count($d['packages']), $rows, 'a row per package');
}

// The gallery shows every picture once, under the space it was taken in;
// the timeline page every stage in its order.
function test_wedding_public_gallery_and_timeline_show_all()
{
	$d = _test_wdp_load();
	$json = json_encode(_test_wdp_pages()['gallery']['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	foreach ($d['gallery'] as $n => $g) {
		pg_assert_same(1, substr_count($json, 'gallery-' . $g['seed'] . '/'), "picture $n once");
	}
	pg_assert_same(count($d['gallery']), count(_test_wdp_labelled('gallery', 'Gallery Picture')), 'every picture in the gallery');
	foreach (array_unique(array_column($d['gallery'], 'space')) as $space) {
		pg_assert_contains('"wd-gallery-' . $space . '"', $json, "a section for $space");
	}
	$stages = _test_wdp_labelled('timeline', 'Timeline Stage');
	pg_assert_same(count($d['timeline']), count($stages), 'every stage on the timeline page');
	foreach ($d['timeline'] as $n => $t) {
		pg_assert_contains($t['when'], json_encode($stages[$n], JSON_UNESCAPED_UNICODE), "stage $n in its place");
	}
}

// The deposit reads the same amount on the services, packages and
// questions pages, written as the shop writes it.
function test_wedding_public_deposit_reads_one_amount()
{
	$d = _test_wdp_load();
	$pages = _test_wdp_pages();
	foreach (array('services', 'packages', 'faq') as $key) {
		pg_assert_contains($d['deposit_text'], json_encode($pages[$key]['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "$key names the deposit");
	}
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_wedding_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_wdp_pages() as $key => $p) {
		$nodes = array();
		_test_wdp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^ratio-(3x4|2x3|1x2)$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'wd-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_wedding_public_no_fixed_dates()
{
	$json = json_encode(_test_wdp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a wedding reads "düğün", the venue
// "mekân", a package "paket" and a deposit "kapora". Page names are
// address words and keep their own spelling.
function test_wedding_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bweddings?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'düğün', 0, 'UTF-8') !== false, "'$key' says düğün");
		if (preg_match('/\bvenue\b/i', $key)) pg_assert_true(mb_stripos($tr, 'mekân', 0, 'UTF-8') !== false, "'$key' says mekân");
		if (preg_match('/\bPackages?\b/', $key)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
		if (preg_match('/\b[Dd]eposit\b/', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist design template's public pages
 * (includes/design_templates/pinegrap-florist/pages_public.php): the
 * plan's nine pages in the public folder and none needing the shop, every
 * link naming a page of the finished template, the home placing the
 * widgets the other parts define, each page showing every item of its
 * data (bouquets, occasions, plans, event services, delivery areas and
 * times, care steps, seasons), the bouquet of one's own opening the order
 * request with a Bouquet answer, the cut-off reading the same on every
 * page, the template's own ids and classes, no fixed date and every
 * literal with its Turkish.
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
function _test_flp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/pages_public.php';
	$out = array('part' => $public_part, 'bouquets' => $bouquets, 'occasions' => $occasions, 'plans' => $plans,
		'event_services' => $event_services, 'zones' => $zones, 'slots' => $slots, 'care' => $care, 'seasons' => $seasons,
		'sizes' => $sizes, 'bouquet_choices' => $bouquet_choices, 'cutoff' => $cutoff_text());
	return $out;
}

// Every node of a tree, depth first.
function _test_flp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_flp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_flp_pages()
{
	$pages = array();
	foreach (_test_flp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The nodes of one page that carry a given label.
function _test_flp_labelled($key, $label)
{
	$nodes = array();
	_test_flp_nodes(_test_flp_pages()[$key]['tree'], $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (($n['props']['_label'] ?? '') === $label) $out[] = $n;
	}
	return $out;
}

function test_florist_public_pages_are_the_plans()
{
	$part = _test_flp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'bouquets', 'occasions', 'subscriptions', 'events', 'delivery', 'care', 'about', 'faq'), array_keys(_test_flp_pages()), 'the nine public pages');
	$names = array();
	foreach (_test_flp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z-]+$/', $p['name']), "$key: its page name is an address word");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name is its own");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_florist_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'bouquets', 'occasions', 'subscriptions', 'events', 'delivery', 'care', 'about', 'faq',
		'work', 'work_item', 'work_new',
		'order_request', 'request_view', 'my_requests', 'staff_requests', 'privacy', 'email_reply',
		'event_enquiry', 'event_enquiry_view', 'staff_events', 'subscribe', 'subscription_view', 'consultation',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_flp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('shop', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured work, the Google reviews and the journal,
// the widgets Our Work and the standard parts define; this part defines
// none.
function test_florist_public_home_widgets()
{
	pg_assert_same(array(), _test_flp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_flp_pages() as $key => $p) {
		$nodes = array();
		_test_flp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_work', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// Each page shows every item of its data once; the home shows the
// featured bouquets only.
function test_florist_public_pages_show_their_data()
{
	$d = _test_flp_load();
	$count = function ($key, $label) {
		return count(_test_flp_labelled($key, $label));
	};
	$featured = count(array_filter(array_column($d['bouquets'], 'featured')));
	pg_assert_same(count($d['bouquets']), $count('bouquets', 'Bouquet Card'), 'every bouquet on the bouquets page');
	pg_assert_same($featured, $count('home', 'Bouquet Card'), 'the featured bouquets on the home');
	pg_assert_same(count($d['occasions']), $count('occasions', 'Special Occasion Card'), 'every occasion on the occasions page');
	pg_assert_same(count($d['occasions']), $count('home', 'Special Occasion Card'), 'every occasion on the home');
	pg_assert_same(count($d['plans']), $count('subscriptions', 'Plan Card'), 'every plan on the subscriptions page');
	pg_assert_same(count($d['event_services']), $count('events', 'Event Service Card'), 'every event service on the events page');
	pg_assert_same(count($d['zones']), $count('delivery', 'Delivery Area Card'), 'every area on the delivery page');
	pg_assert_same(count($d['seasons']), $count('care', 'Season Card'), 'every season on the care page');
	$json = json_encode(_test_flp_pages()['delivery']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['slots'] as $key => $s) pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, "delivery time $key on the delivery page");
	$json = json_encode(_test_flp_pages()['care']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['care'] as $n => $c) pg_assert_contains(json_encode($c['title'], JSON_UNESCAPED_UNICODE), $json, "care step $n on the care page");
	$json = json_encode(_test_flp_pages()['bouquets']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['sizes'] as $key => $s) pg_assert_contains(json_encode($s['title'], JSON_UNESCAPED_UNICODE), $json, "size $key on the bouquets page");
}

// The bouquet of one's own opens the order request with an answer of the
// Bouquet list.
function test_florist_public_custom_bouquet_opens_the_request()
{
	$d = _test_flp_load();
	$nodes = array();
	_test_flp_nodes(_test_flp_pages()['bouquets']['tree'], $nodes);
	$found = 0;
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:order_request}}?') !== 0 || ($n['props']['_label'] ?? '') === 'Link' && strpos($href, 'bouquet=') === false) continue;
		parse_str(substr($href, strlen('{{page:order_request}}?')), $q);
		pg_assert_true(isset($q['bouquet']) && in_array($q['bouquet'], $d['bouquet_choices'], true), 'the link names a Bouquet answer');
		if ($q['bouquet'] === end($d['bouquet_choices'])) $found++;
	}
	pg_assert_same(1, $found, 'one link asks for a bouquet of one\'s own');
}

// The same-day cut-off reads the same on the home, delivery and questions
// pages.
function test_florist_public_cutoff_reads_one_time()
{
	$d = _test_flp_load();
	$pages = _test_flp_pages();
	foreach (array('home', 'delivery', 'faq') as $key) {
		pg_assert_contains($d['cutoff'], json_encode($pages[$key]['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "$key names the cut-off");
	}
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_florist_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_flp_pages() as $key => $p) {
		$nodes = array();
		_test_flp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmk|pmu|pmz|plx|pdl|plw|psl|psh|psc|pco|pcl|pcn|pgy|pch|ptv|pph|pwd|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^ratio-(3x4|2x3|1x2)$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'fl-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_florist_public_no_fixed_dates()
{
	$json = json_encode(_test_flp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a bouquet reads "buket", a subscription
// "abonelik", delivery "teslimat", an occasion "özel gün". Page names are
// address words and keep their own spelling.
function test_florist_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bbouquets?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'buket', 0, 'UTF-8') !== false, "'$key' says buket");
		if (preg_match('/\bsubscriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'abonel', 0, 'UTF-8') !== false, "'$key' says abonelik");
		if (preg_match('/\b[Dd]elivery\b/', $key)) pg_assert_true(mb_stripos($tr, 'teslim', 0, 'UTF-8') !== false, "'$key' says teslimat");
		if (preg_match('/\bOccasions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'özel gün', 0, 'UTF-8') !== false, "'$key' says özel gün");
	}
}

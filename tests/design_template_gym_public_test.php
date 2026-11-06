<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym design template's public pages
 * (includes/design_templates/pinegrap-gym/pages_public.php): the eight
 * pages of the plan in the public folder and without the shop, links that
 * name template pages and no shop page, the widgets only the home places,
 * a timetable that offers every class of the week to book and a page of
 * classes that shows every class once, the template's ids and classes, no
 * fixed dates and every literal in Turkish.
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
function _test_gyp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/pages_public.php';
	$out = array('part' => $public_part, 'classes' => $classes, 'timetable' => $timetable, 'plans' => $plans,
		'class_names' => $class_names, 'join_choices' => $join_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_gyp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gyp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_gyp_pages()
{
	$pages = array();
	foreach (_test_gyp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_gyp_queries($tree, $page)
{
	$nodes = array();
	_test_gyp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_gym_public_pages_are_the_plans()
{
	$part = _test_gyp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'classes', 'timetable', 'plans', 'facilities', 'about', 'faq', 'location'), array_keys(_test_gyp_pages()), 'the eight public pages');
	$names = array();
	foreach (_test_gyp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_gym_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'classes', 'timetable', 'plans', 'facilities', 'about', 'faq', 'location',
		'trainers', 'trainer', 'trainer_new', 'book_online',
		'class_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'join', 'join_view', 'staff_joins', 'members', 'workouts',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_gyp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('memberships', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the trainers list, the Google reviews and the notes, the
// widgets the trainers and the standard parts define; this part defines
// none.
function test_gym_public_home_widgets()
{
	pg_assert_same(array(), _test_gyp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_gyp_pages() as $key => $p) {
		$nodes = array();
		_test_gyp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_trainers', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// The timetable offers every class of the week to book, each with its
// class, day and time; the class page shows every class once, under its
// focus, each opening the booking request with it chosen.
function test_gym_public_classes_and_timetable_open_the_request()
{
	$d = _test_gyp_load();
	$pages = _test_gyp_pages();
	$week = _test_gyp_queries($pages['timetable']['tree'], 'class_booking');
	pg_assert_same(count($d['timetable']), count($week), 'a booking link per class of the week');
	foreach ($week as $q) {
		pg_assert_true(in_array($q['class'], $d['class_names'], true), 'the link names a Class answer');
		pg_assert_true(isset($q['day'], $q['time']), 'the link names the day and the time');
	}
	$named = array();
	foreach (_test_gyp_queries($pages['classes']['tree'], 'class_booking') as $q) $named[] = $q['class'];
	sort($named);
	$all = $d['class_names'];
	sort($all);
	pg_assert_same($all, $named, 'every class once on the class page');
	foreach (array('home', 'classes', 'plans') as $key) {
		foreach (_test_gyp_queries($pages[$key]['tree'], 'join') as $q) {
			pg_assert_true(in_array($q['plan'], $d['join_choices'], true), "$key: a join link names a Plan answer");
		}
	}
	pg_assert_same(count($d['plans']), count(_test_gyp_queries($pages['plans']['tree'], 'join')), 'a join link per plan');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_gym_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_gyp_pages() as $key => $p) {
		$nodes = array();
		_test_gyp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'gy-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_gym_public_no_fixed_dates()
{
	$json = json_encode(_test_gyp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, a class reads "ders", a trainer
// "antrenör", a membership "üyelik"; shared keys whose Turkish names
// another thing are not used.
function test_gym_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bClass(es)?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
		if (preg_match('/\bTrainers?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'antrenör', 0, 'UTF-8') !== false, "'$key' says antrenör");
		if (preg_match('/\bMembership\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'üyelik', 0, 'UTF-8') !== false, "'$key' says üyelik");
	}
	// Classes reads Sınıflar, Strength Yoğunluk, Testimonials Müşteri
	// Yorumları: a school's, a density, a shop's customers.
	foreach (array('Classes', 'Strength', 'Testimonials') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}

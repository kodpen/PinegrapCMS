<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's menu and events
 * (includes/design_templates/pinegrap-restaurant/menu.php): the six pages
 * of the plan, the menu with a tab per course each listing the Menu form's
 * dishes of that course, the Menu and Events forms as collections with
 * their sample records (dishes in the form's own answers, events still to
 * come), the controls named for the fields the cards and the record pages
 * read, the home's favourites and next events, the event links that open
 * the reservation with the event chosen, the template's ids and classes,
 * and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the menu in one scope, as the template file reads them.
// Read once per run.
function _test_rtm_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/parts.php';
	$menu_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/menu.php';
	$out = array('part' => $menu_part, 'courses' => $courses, 'dishes' => $dishes, 'events' => $events,
		'course_titles' => $course_titles, 'diet_titles' => $diet_titles, 'badges' => $badges);
	return $out;
}

// Every node of a tree, depth first.
function _test_rtm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rtm_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_rtm_pages()
{
	$pages = array();
	foreach (_test_rtm_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of a tree's form controls.
function _test_rtm_control_names($tree)
{
	$nodes = array();
	_test_rtm_nodes($tree, $nodes);
	$names = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $names[$a['value']] = true;
		}
	}
	return array_keys($names);
}

// The tokens a tree binds.
function _test_rtm_tokens($tree)
{
	$nodes = array();
	_test_rtm_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_bindings'] ?? array() as $t) $tokens[$t] = true;
	}
	return $tokens;
}

function test_restaurant_menu_pages_are_the_plans()
{
	$part = _test_rtm_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('menu', 'dish', 'dish_new', 'events', 'event', 'event_new'), array_keys(_test_rtm_pages()), 'the six pages');
	foreach (_test_rtm_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'tree') as $field) pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		$staff = substr($key, -4) === '_new';
		pg_assert_same($staff ? 'private' : 'public', $p['folder'], "$key: its folder");
		if ($staff) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: kept out of search");
		} else {
			pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		}
	}
	foreach (array('dish', 'event') as $key) pg_assert_same(false, _test_rtm_pages()[$key]['sitemap'], "$key: a record page is not in the sitemap itself");
	// Every widget sits on a page of this part or the home, and every
	// widget a page of this part places is defined here.
	$widgets = $part['widgets'];
	$placed = array();
	foreach (_test_rtm_pages() as $key => $p) {
		$nodes = array();
		_test_rtm_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = $key;
		}
	}
	foreach ($placed as $w => $key) pg_assert_true(isset($widgets[$w]), "$key places $w, defined here");
	foreach ($widgets as $w => $def) {
		if ($def['page'] === 'home') continue;
		pg_assert_same($def['page'], $placed[$w] ?? '', "$w is placed on its page");
	}
	pg_assert_same('home', $widgets['home_dishes']['page'], 'the home\'s favourites');
	pg_assert_same('home', $widgets['home_events']['page'], 'the home\'s next events');
}

// A tab per course, in order, each a list of the Menu form filtered by
// that course's words, the dish page its detail.
function test_restaurant_menu_tabs_list_each_course()
{
	$d = _test_rtm_load();
	$w = $d['part']['widgets'];
	$json = json_encode(_test_rtm_pages()['menu']['tree']);
	foreach ($d['courses'] as $key => $c) {
		pg_assert_true(isset($w['menu_' . $key]), "a list for $key");
		$cfg = $w['menu_' . $key]['config'];
		pg_assert_same('{{tab:dish_new}}', $cfg['custom_form_page_id'], "$key: the Menu form");
		pg_assert_same('{{tab:dish}}', $cfg['detail_page_id'], "$key: the dish page");
		pg_assert_same(array(array('field' => 'course', 'operator' => 'is equal to', 'value' => $c['title'], 'dynamic_value' => '')), $cfg['filters'], "$key: filtered by its course");
		pg_assert_true(in_array($c['title'], $d['course_titles'], true), "$key: a Course answer");
		pg_assert_contains('"#rt-menu-' . $key . '"', $json, "$key: its tab opens its pane");
	}
	pg_assert_same(array('featured' => lang('Yes')), array($w['home_dishes']['config']['filters'][0]['field'] => $w['home_dishes']['config']['filters'][0]['value']), 'the home shows the dishes marked for it');
}

// The Menu form: a collection, its controls named for the fields the dish
// card, the tabs and the dish page read, its sample dishes in its own
// answers; the dish page reads the same fields.
function test_restaurant_menu_form_and_dishes()
{
	$d = _test_rtm_load();
	$pages = _test_rtm_pages();
	$w = $d['part']['widgets'];
	$form = $pages['dish_new']['form'];
	pg_assert_same(1, $form['collection'], 'the Menu form is a collection');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$names = _test_rtm_control_names($w['dish_form']['tree']);
	foreach (array('name', 'course', 'price', 'description', 'diet', 'badge', 'featured', 'photo') as $f) {
		pg_assert_true(in_array($f, $names, true), "the form asks for $f");
	}
	pg_assert_same(count($d['dishes']), count($form['sample_records']), 'a sample record per dish');
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same(array(), array_diff(array_keys($r), $names), "record $n: only the form's fields");
		pg_assert_true(in_array($r['course'], $d['course_titles'], true), "record $n: a Course answer");
		foreach ($r['diet'] as $tag) pg_assert_true(in_array($tag, $d['diet_titles'], true), "record $n: a Diet answer");
		pg_assert_true($r['badge'] === '' || in_array($r['badge'], $d['badges'], true), "record $n: a Badge answer");
	}
	$tokens = _test_rtm_tokens($w['dish_detail']['tree']);
	foreach (array('photo', 'name', 'course', 'price', 'description', 'diet', 'badge', '__edit_url', 'not_found', 'record_found', 'record_not_found') as $t) {
		pg_assert_true(isset($tokens[$t]), "the dish page reads $t");
	}
	pg_assert_same('{{tab:dish_new}}', $w['dish_detail']['config']['custom_form_page_id'], 'the dish page reads the Menu form');
}

// The Events form: a collection, its sample events still to come and in
// its own kinds; the lists show the events from today on, the nearest
// first; every event link opens the reservation with the event's title.
function test_restaurant_events_form_lists_and_links()
{
	$d = _test_rtm_load();
	$pages = _test_rtm_pages();
	$w = $d['part']['widgets'];
	$form = $pages['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'the Events form is a collection');
	$names = _test_rtm_control_names($w['event_form']['tree']);
	foreach (array('title', 'event_date', 'event_time', 'kind', 'price', 'summary', 'image') as $f) {
		pg_assert_true(in_array($f, $names, true), "the form asks for $f");
	}
	pg_assert_same(count($d['events']), count($form['sample_records']), 'a sample record per event');
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same(array(), array_diff(array_keys($r), $names), "record $n: only the form's fields");
		pg_assert_true($r['event_date'] > date('Y-m-d'), "record $n: still to come");
	}
	foreach (array('events_list', 'home_events') as $key) {
		$cfg = $w[$key]['config'];
		pg_assert_same('event_date', $cfg['order_by_field'], "$key: by date");
		pg_assert_same('ASC', $cfg['order_by_direction'], "$key: the nearest first");
		pg_assert_same('current date', $cfg['filters'][0]['dynamic_value'], "$key: from today on");
		pg_assert_same('{{tab:event}}', $cfg['detail_page_id'], "$key: the event page");
	}
	pg_assert_same(3, $w['home_events']['config']['max_results'], 'three on the home');
	$links = 0;
	foreach (array($w['events_list']['tree'], $w['event_detail']['tree']) as $tree) {
		$nodes = array();
		_test_rtm_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['_bindParams']['href'])) continue;
			$p = $n['props']['_bindParams']['href'];
			pg_assert_same('{{tab:reserve}}', $p['page_id'], 'the link opens the reservation');
			pg_assert_same(array(array('name' => 'event', 'token' => 'title')), $p['params'], 'with the event chosen');
			$links++;
		}
	}
	pg_assert_same(2, $links, 'the card and the event page each reserve');
	pg_assert_same('event', pg_sw_url_param_name('event'), '?event= is a usable name');
	$tokens = _test_rtm_tokens($w['event_detail']['tree']);
	foreach (array('image', 'title', 'kind', 'event_date', 'event_time', 'price', 'summary', '__edit_url', 'record_found', 'record_not_found') as $t) {
		pg_assert_true(isset($tokens[$t]), "the event page reads $t");
	}
}

// Every link names a page the finished template carries; ids and classes
// are the template's own; no fixed date in the words.
function test_restaurant_menu_links_ids_and_classes()
{
	$known = array_flip(array(
		'home', 'tasting', 'wine', 'about', 'faq', 'location', 'menu', 'dish', 'dish_new', 'events', 'event', 'event_new',
		'reserve', 'my_reservations', 'book_online', 'private_dining', 'private_enquiry', 'privacy', 'contact', 'blog',
		'newsletter', 'my_account', 'staff', 'staff_reservations', 'staff_enquiries', 'blog_new', 'inbox',
	));
	$part = _test_rtm_load()['part'];
	$json = json_encode($part);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (array('vouchers', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no page here links $key");
	}
	$trees = array();
	foreach ($part['pages'] as $p) $trees[] = $p['tree'];
	foreach ($part['widgets'] as $w) if (is_array($w['tree'])) $trees[] = $w['tree'];
	foreach ($trees as $tree) {
		$nodes = array();
		_test_rtm_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai|pmz)-/', $c), "class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '') pg_assert_true(strpos($id, 'rt-') === 0, "id $id is the template's");
		}
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/menu.php');
	pg_assert_false((bool)preg_match("/lang\\('[^']*\\b20[0-9]{2}\\b/", $src), 'no year in the words');
}

// Every literal has its Turkish; shared keys whose Turkish names another
// thing are not used.
function test_restaurant_menu_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/menu.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (isset($map[$key]) && preg_match('/\bReservations?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
	}
	// Their Turkish names a course of study or a shop's product.
	foreach (array('Starters', 'Course', 'Courses', 'Glass', 'Dish', 'Find Us', 'Category', 'Choose a course…', 'The dish is on the menu.') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "menu.php does not use lang('$k')");
	}
}

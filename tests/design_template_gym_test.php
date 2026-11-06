<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym design template's parts
 * (includes/design_templates/pinegrap-gym/): the gym's data hangs together
 * (classes naming a known focus and level, trainers teaching known
 * classes, every class of the timetable inside the opening hours with no
 * trainer or studio in two places at once, plans with a price and a
 * term, the forms' pick lists without an answer twice), a plan's price is
 * written as the shop writes it, every page link of the shared parts names
 * a page of the finished template and none names a shop page, the footer
 * opens the trainer list filtered, a trainer card reads the Trainers
 * form's fields, the class, plan and timetable links make their choice in
 * the request forms, the classes are the template's own, the sample
 * carries no fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them. Read once per run.
function _test_gy_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/parts.php';
	$class_cards = array();
	foreach (array_keys($classes) as $key) $class_cards[$key] = $class_card($key);
	$trainer_cards = array();
	foreach (array_keys($trainers) as $key) $trainer_cards[$key] = $trainer_card($key);
	$plan_cards = array();
	foreach (array_keys($plans) as $key) $plan_cards[$key] = $plan_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$day_trees = array();
	foreach (array_keys($days) as $day) $day_trees[$day] = $timetable_day($day);
	$out = array(
		'gym'               => $gym,
		'days'              => $days,
		'opening_hours'     => $opening_hours,
		'focuses'           => $focuses,
		'levels'            => $levels,
		'studios'           => $studios,
		'classes'           => $classes,
		'trainers'          => $trainers,
		'timetable'         => $timetable,
		'plans'             => $plans,
		'money'             => $money,
		'plan_price'        => $plan_price,
		'facilities'        => $facilities,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'focus_titles'     => $focus_titles,
			'class_names'      => $class_names,
			'day_titles'       => $day_titles,
			'time_choices'     => $time_choices,
			'trainer_names'    => $trainer_names,
			'plan_choices'     => $plan_choices,
			'join_choices'     => $join_choices,
			'goal_choices'     => $goal_choices,
			'booking_statuses' => $booking_statuses,
			'join_statuses'    => $join_statuses,
		),
		'radios'            => array('experience_choices' => $experience_choices, 'contact_choices' => $contact_choices),
		'class_cards'       => $class_cards,
		'trainer_cards'     => $trainer_cards,
		'plan_cards'        => $plan_cards,
		'testimonial_cards' => $testimonial_cards,
		'day_trees'         => $day_trees,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $join_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_gy_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gy_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_gy_trees()
{
	$d = _test_gy_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['class_cards']),
		array_values($d['trainer_cards']), array_values($d['plan_cards']), $d['testimonial_cards'], array_values($d['day_trees']));
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_gy_page_keys()
{
	return array_merge(array(
		'home', 'classes', 'timetable', 'plans', 'facilities', 'about', 'faq', 'location',
		'trainers', 'trainer', 'trainer_new', 'book_online',
		'class_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'join', 'join_view', 'staff_joins', 'members', 'workouts',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_gy_shop_keys());
}

// The pages that exist only with the shop.
function _test_gy_shop_keys()
{
	return array('memberships', 'product', 'cart', 'checkout', 'order', 'email_order');
}

// Minutes since midnight of an HH:MM time.
function _test_gy_minutes($time)
{
	list($h, $m) = explode(':', $time);
	return (int)$h * 60 + (int)$m;
}

function test_gym_data_hangs_together()
{
	$d = _test_gy_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['gym'][$f]) !== '', "gym: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['gym']['phone']), 'gym: phone number');
	pg_assert_true(strpos($d['gym']['email'], '@example.com') !== false, 'gym: a sample address');
	pg_assert_same(array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'), array_keys($d['days']), 'the week from Monday');
	foreach ($d['days'] as $key => $day) {
		pg_assert_true(_test_gy_minutes($day['open']) < _test_gy_minutes($day['close']), "day $key: opens before it closes");
	}
	$focuses = array();
	foreach ($d['classes'] as $key => $c) {
		pg_assert_true(isset($d['focuses'][$c['focus']]), "class $key: known focus");
		pg_assert_true(isset($d['levels'][$c['level']]), "class $key: known level");
		pg_assert_true(is_int($c['minutes']) && $c['minutes'] >= 30 && $c['minutes'] <= 90, "class $key: minutes");
		pg_assert_true(is_int($c['intensity']) && $c['intensity'] >= 1 && $c['intensity'] <= 5, "class $key: intensity 1 to 5");
		foreach (array('name', 'summary', 'icon') as $f) pg_assert_true(trim($c[$f]) !== '', "class $key: $f");
		$focuses[$c['focus']] = true;
	}
	pg_assert_same(count($d['focuses']), count($focuses), 'a class of every focus');
	$taught = array();
	foreach ($d['trainers'] as $key => $t) {
		pg_assert_true(isset($d['focuses'][$t['focus']]), "trainer $key: known focus");
		pg_assert_true(is_int($t['years']) && $t['years'] > 0, "trainer $key: years");
		foreach (array('name', 'role', 'summary', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "trainer $key: $f");
		foreach ($t['classes'] as $c) {
			pg_assert_true(isset($d['classes'][$c]), "trainer $key: teaches a known class");
			$taught[$c] = true;
		}
	}
	pg_assert_same(count($d['classes']), count($taught), 'every class has a trainer');
	// Every class on the timetable: a known day, class, trainer who teaches
	// it and studio, inside the day's opening hours, and no trainer or
	// studio in two classes at once.
	$on_timetable = array();
	$busy = array();
	foreach ($d['timetable'] as $n => $s) {
		pg_assert_true(isset($d['days'][$s['day']]), "session $n: known day");
		pg_assert_true(isset($d['classes'][$s['class']]), "session $n: known class");
		pg_assert_true(isset($d['trainers'][$s['trainer']]), "session $n: known trainer");
		pg_assert_true(isset($d['studios'][$s['studio']]), "session $n: known studio");
		pg_assert_true(in_array($s['class'], $d['trainers'][$s['trainer']]['classes'], true), "session $n: the trainer teaches the class");
		$start = _test_gy_minutes($s['time']);
		$end = $start + $d['classes'][$s['class']]['minutes'];
		$day = $d['days'][$s['day']];
		pg_assert_true($start >= _test_gy_minutes($day['open']), "session $n: starts after the gym opens");
		pg_assert_true($end <= _test_gy_minutes($day['close']), "session $n: ends before the gym closes");
		foreach (array('trainer', 'studio') as $who) {
			foreach (isset($busy[$s['day']][$who][$s[$who]]) ? $busy[$s['day']][$who][$s[$who]] : array() as $other) {
				pg_assert_true($end <= $other[0] || $start >= $other[1], "session $n: the $who is not in two classes at once");
			}
			$busy[$s['day']][$who][$s[$who]][] = array($start, $end);
		}
		$on_timetable[$s['class']] = true;
	}
	pg_assert_same(count($d['classes']), count($on_timetable), 'every class is on the timetable');
	$skus = array();
	$featured = 0;
	foreach ($d['plans'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^GYM-[A-Z0-9-]+$/', $p['sku']), "plan $key: the template's SKU");
		pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "plan $key: a price");
		pg_assert_true(is_int($p['days']) && $p['days'] > 0, "plan $key: a term in days");
		pg_assert_true(count($p['points']) >= 3, "plan $key: points");
		if ($p['featured']) $featured++;
		$skus[$p['sku']] = true;
	}
	pg_assert_same(count($d['plans']), count($skus), 'no SKU twice');
	pg_assert_same(1, $featured, 'one plan marked as the most chosen');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['classes'][$t['class']]), "testimonial $n: about a class");
		pg_assert_contains($d['classes'][$t['class']]['name'], $t['role'], "testimonial $n: names the class");
	}
	// The pick lists: one answer per focus, class, day, trainer and plan
	// (plus the free trial pass), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['focuses']), count($l['focus_titles']), 'an answer per focus');
	pg_assert_same(count($d['classes']), count($l['class_names']), 'an answer per class');
	pg_assert_same(count($d['days']), count($l['day_titles']), 'an answer per day');
	pg_assert_same(count($d['trainers']), count($l['trainer_names']), 'an answer per trainer');
	pg_assert_same(count($d['plans']) + 1, count($l['join_choices']), 'join: an answer per plan and the trial pass');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	$sorted = $l['time_choices'];
	sort($sorted);
	pg_assert_same($sorted, $l['time_choices'], 'the times in order');
	foreach ($d['timetable'] as $n => $s) pg_assert_true(in_array($s['time'], $l['time_choices'], true), "session $n: its time is an answer");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A plan shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_gym_plan_price_is_written_as_the_shop_writes_it()
{
	$d = _test_gy_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['plans']['unlimited']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s a month'), pg_money_text($amount, $symbol)), call_user_func($d['plan_price'], 'unlimited'), 'the price line');
}

function test_gym_page_links_name_template_pages()
{
	$known = array_flip(_test_gy_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_gy_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_gym_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_gy_trees());
	foreach (_test_gy_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_gy_load();
	pg_assert_contains('{{page:class_booking}}', json_encode($d['header']), 'the header books a class');
	$nodes = array();
	_test_gy_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer's focus links open the trainer list filtered on each focus,
// in the words the Focus list uses.
function test_gym_footer_filters_the_trainer_list()
{
	$d = _test_gy_load();
	$nodes = array();
	_test_gy_nodes($d['footer'], $nodes);
	$seen = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:trainers}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:trainers}}?')), $q);
		pg_assert_true(in_array($q['focus'], $d['lists']['focus_titles'], true), 'the link names a Focus answer');
		$seen[$q['focus']] = true;
	}
	pg_assert_same(count($d['focuses']), count($seen), 'a link per focus');
}

// A trainer card reads every value from the Trainers form's fields, with
// the sample's own words on the canvas.
function test_gym_trainer_card_reads_the_trainer()
{
	$d = _test_gy_load();
	foreach ($d['trainer_cards'] as $key => $card) {
		$nodes = array();
		_test_gy_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo', 'name', 'form_item_view', 'focus', 'role', 'summary', 'years') as $t) {
			pg_assert_true(isset($tokens[$t]), "trainer $key: card reads $t");
		}
		$t = $d['trainers'][$key];
		pg_assert_true(in_array($t['name'], $texts, true), "trainer $key: sample name on the canvas");
		pg_assert_true(in_array($d['focuses'][$t['focus']]['title'], $texts, true), "trainer $key: sample focus on the canvas");
	}
}

// The links found in a tree that open the given page with a query.
function _test_gy_query_links($tree, $page)
{
	$nodes = array();
	_test_gy_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// A class card opens the booking request with the class chosen, a
// timetable entry with the class, the day and the time, a plan card the
// join request with the plan: in the words the forms' lists use (the
// forms start from ?class=, ?day=, ?time=, ?plan=).
function test_gym_links_choose_in_the_request_forms()
{
	$d = _test_gy_load();
	$l = $d['lists'];
	foreach ($d['class_cards'] as $key => $card) {
		$q = _test_gy_query_links($card, 'class_booking');
		pg_assert_same(1, count($q), "class $key: one booking link");
		pg_assert_same($d['classes'][$key]['name'], $q[0]['class'], "class $key: the link names the class");
	}
	$count = 0;
	foreach ($d['day_trees'] as $day => $tree) {
		foreach (_test_gy_query_links($tree, 'class_booking') as $q) {
			pg_assert_true(in_array($q['class'], $l['class_names'], true), "$day: names a Class answer");
			pg_assert_same($d['days'][$day]['title'], $q['day'], "$day: names its day");
			pg_assert_true(in_array($q['time'], $l['time_choices'], true), "$day: names a Time answer");
			$count++;
		}
	}
	pg_assert_same(count($d['timetable']), $count, 'a booking link per class of the week');
	foreach ($d['plan_cards'] as $key => $card) {
		$q = _test_gy_query_links($card, 'join');
		pg_assert_same(1, count($q), "plan $key: one join link");
		pg_assert_true(in_array($q[0]['plan'], $l['join_choices'], true), "plan $key: the link names a Plan answer");
	}
	foreach (array(_test_gy_load()['shared'][1], _test_gy_load()['shared'][2]) as $n => $tree) {
		$q = _test_gy_query_links($tree, 'join');
		pg_assert_true(count($q) >= 1, "trial link $n");
		pg_assert_same(lang('Free trial pass'), $q[0]['plan'], "trial link $n: asks for the pass");
		pg_assert_true(in_array($q[0]['plan'], $l['join_choices'], true), "trial link $n: a Plan answer");
	}
}

// Classes the template adds of its own start with pgy-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with gy-.
function test_gym_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_gy_trees() as $t) _test_gy_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'pgy-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'gy-') === 0, "id $id is the template's");
	}
	foreach (array('pgy-brand', 'pgy-display', 'pgy-eyebrow', 'pgy-price', 'pgy-lift') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_gym_no_fixed_dates()
{
	$d = _test_gy_load();
	$json = json_encode(array($d['classes'], $d['trainers'], $d['plans'], $d['facilities'], _test_gy_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a class is "ders", a trainer "antrenör", a
// membership "üyelik".
function test_gym_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bClass(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
		if (preg_match('/\bTrainers?\b/', $key)) pg_assert_true(mb_stripos($tr, 'antrenör', 0, 'UTF-8') !== false, "'$key' says antrenör");
		if (preg_match('/\bMembership\b/', $key)) pg_assert_true(mb_stripos($tr, 'üyelik', 0, 'UTF-8') !== false, "'$key' says üyelik");
	}
	// The shared keys this template must not use: their Turkish names
	// another thing (Classes reads Sınıflar, a school's; Strength reads
	// Yoğunluk).
	foreach (array('Classes', 'Strength') as $k) {
		pg_assert_false(strpos($src, "lang('$k')") !== false, "the template does not say '$k' alone");
	}
}

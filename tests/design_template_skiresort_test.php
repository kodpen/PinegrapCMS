<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's data and page helpers
 * (includes/design_templates/pinegrap-skiresort/parts.php): the resort's
 * data hangs together (slopes and lifts naming a known difficulty, area,
 * kind, state and lift; every pass, rental item, lesson and stay with a
 * price in every currency; lessons naming a known level and age group;
 * events with a known kind falling after today), the pick lists follow the
 * data without an answer twice, a price is written as the shop writes it,
 * the page shell places the shared parts, the helpers link only template
 * pages, the classes and ids are the template's own, the sample carries no
 * fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The data and helpers as the template file reads them. Read once per run.
function _test_sk_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/parts.php';
	$words = array('slope' => array(), 'lift' => array());
	foreach (array_keys($slopes) as $key) {
		$words['slope'][$key] = array($slope_difficulty($key), $slope_area($key), $slope_status($key));
	}
	foreach (array_keys($lifts) as $key) $words['lift'][$key] = $lift_status($key);
	$dates = array();
	foreach ($events as $key => $e) $dates[$key] = $event_on($e['weekday'], $e['weeks']);
	$out = array(
		'resort'             => $resort,
		'lift_hours'         => $lift_hours,
		'difficulties'       => $difficulties,
		'areas'              => $areas,
		'run_statuses'       => $run_statuses,
		'lift_kinds'         => $lift_kinds,
		'lifts'              => $lifts,
		'slopes'             => $slopes,
		'conditions'         => $conditions,
		'lift_passes'        => $lift_passes,
		'lift_pass_prices'   => $lift_pass_prices,
		'season_passes'      => $season_passes,
		'season_pass_prices' => $season_pass_prices,
		'rental'             => $rental,
		'rental_prices'      => $rental_prices,
		'levels'             => $levels,
		'age_groups'         => $age_groups,
		'lessons'            => $lessons,
		'lesson_prices'      => $lesson_prices,
		'lodging'            => $lodging,
		'lodging_prices'     => $lodging_prices,
		'event_kinds'        => $event_kinds,
		'events'             => $events,
		'event_dates'        => $dates,
		'people'             => $people,
		'figures'            => $figures,
		'testimonials'       => $testimonials,
		'details_text'       => $details_text,
		'money'              => $money,
		'query'              => $query,
		'price_texts'        => array(
			'season'  => $season_price_text('adult'),
			'rental'  => $rental_price_text('ski_set'),
			'lesson'  => $lesson_price_text('junior'),
			'lodging' => $lodging_price_text('hotel'),
		),
		'words'              => $words,
		'lists'              => array(
			'difficulty_titles'  => $difficulty_titles,
			'area_titles'        => $area_titles,
			'status_titles'      => $status_titles,
			'lift_kind_titles'   => $lift_kind_titles,
			'level_titles'       => $level_titles,
			'age_titles'         => $age_titles,
			'lesson_titles'      => $lesson_titles,
			'lesson_choices'     => $lesson_choices,
			'lesson_day_choices' => $lesson_day_choices,
			'skier_counts'       => $skier_counts,
			'stay_titles'        => $stay_titles,
			'stay_choices'       => $stay_choices,
			'group_kinds'        => $group_kinds,
			'night_choices'      => $night_choices,
			'event_kind_titles'  => $event_kind_titles,
			'lesson_statuses'    => $lesson_statuses,
			'enquiry_statuses'   => $enquiry_statuses,
		),
		'radios'             => array('contact_choices' => $contact_choices, 'sport_choices' => $sport_choices),
		'helpers'            => array(
			'page'           => $page(array($para('x')), true),
			'form_page'      => $page(array($para('x')), false),
			'page_header'    => $page_header(lang('Slopes'), lang('Lift Passes')),
			'section_header' => $section_header(lang('Slopes'), lang('Open Today'), lang('All Slopes'), '{{page:slopes}}'),
			'icon_tile'      => $icon_tile('bi-snow'),
			'step'           => $step(1, lang('Ski'), lang('Snowboard')),
			'check_list'     => $check_list($season_passes['adult']['benefits']),
			'faq'            => $faq(1, lang('Ski'), lang('Snowboard')),
		),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_sk_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sk_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_sk_page_keys()
{
	return array_merge(array(
		'home', 'conditions', 'passes', 'rental', 'ski_school', 'lodging', 'about', 'faq',
		'slopes', 'slope', 'slope_new', 'lifts', 'lift_new',
		'lesson_request', 'lesson_view', 'my_lessons', 'staff_lessons', 'privacy', 'email_reply',
		'email_lesson_new', 'email_lesson_received',
		'group_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'email_enquiry_new', 'email_enquiry_received',
		'events', 'event', 'event_new',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome', 'email_account_welcome', 'email_password_reset',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_sk_shop_keys());
}

// The pages that exist only with the shop.
function _test_sk_shop_keys()
{
	return array('buy_passes', 'rental_shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_skiresort_data_hangs_together()
{
	$d = _test_sk_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'season', 'altitude', 'licence') as $f) pg_assert_true(trim($d['resort'][$f]) !== '', "resort: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['resort']['phone']), 'resort: a phone number');
	pg_assert_true(strpos($d['resort']['email'], '@example.com') !== false, 'resort: a sample address');
	pg_assert_same(4, count($d['lift_hours']), 'four lines of hours');
	foreach ($d['lift_hours'] as $n => $h) pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2} - [0-9]{2}:[0-9]{2}$/', $h['hours']), "hours $n: from and to");
	pg_assert_same(array('green', 'blue', 'red', 'black'), array_keys($d['difficulties']), 'the four piste colours');
	pg_assert_same(array('open', 'closed', 'on_hold'), array_keys($d['run_statuses']), 'the three states of a slope or lift');
	$lift_states = array();
	foreach ($d['lifts'] as $key => $l) {
		pg_assert_true(isset($d['lift_kinds'][$l['kind']]), "lift $key: a known kind");
		pg_assert_true(isset($d['areas'][$l['area']]), "lift $key: a known area");
		pg_assert_true(isset($d['run_statuses'][$l['status']]), "lift $key: a known state");
		foreach (array('title', 'ride', 'capacity') as $f) pg_assert_true(trim($l[$f]) !== '', "lift $key: $f");
		$lift_states[$l['status']] = true;
	}
	pg_assert_same(3, count($lift_states), 'a lift in each state');
	$used = array('difficulty' => array(), 'area' => array(), 'status' => array());
	$featured = 0;
	foreach ($d['slopes'] as $key => $s) {
		pg_assert_true(isset($d['difficulties'][$s['difficulty']]), "slope $key: a known difficulty");
		pg_assert_true(isset($d['areas'][$s['area']]), "slope $key: a known area");
		pg_assert_true(isset($d['lifts'][$s['lift']]), "slope $key: served by a known lift");
		pg_assert_true(isset($d['run_statuses'][$s['status']]), "slope $key: a known state");
		foreach (array('title', 'length', 'drop', 'summary') as $f) pg_assert_true(trim($s[$f]) !== '', "slope $key: $f");
		pg_assert_same(4, count($s['details']), "slope $key: four details");
		pg_assert_same(implode("\n", $s['details']), call_user_func($d['details_text'], $s['details']), "slope $key: a detail line per item");
		list($difficulty, $area, $status) = $d['words']['slope'][$key];
		pg_assert_true(in_array($difficulty, $d['lists']['difficulty_titles'], true), "slope $key: its difficulty is an answer of the list");
		pg_assert_true(in_array($area, $d['lists']['area_titles'], true), "slope $key: its area is an answer of the list");
		pg_assert_true(in_array($status, $d['lists']['status_titles'], true), "slope $key: its state is an answer of the list");
		$used['difficulty'][$s['difficulty']] = true;
		$used['area'][$s['area']] = true;
		$used['status'][$s['status']] = true;
		if ($s['featured']) $featured++;
	}
	pg_assert_same(count($d['difficulties']), count($used['difficulty']), 'a slope of each difficulty');
	pg_assert_same(count($d['areas']), count($used['area']), 'a slope in each area');
	pg_assert_same(3, count($used['status']), 'a slope in each state');
	pg_assert_same(3, $featured, 'three slopes on the home');
	pg_assert_same(6, count($d['conditions']), 'six lines of conditions');
	foreach ($d['conditions'] as $n => $c) {
		foreach (array('icon', 'label', 'value') as $f) pg_assert_true(trim($c[$f]) !== '', "condition $n: $f");
	}
	// Every pass, rental item, lesson and stay has a price in each
	// currency the catalog knows.
	foreach (array('lift_passes' => 'lift_pass_prices', 'season_passes' => 'season_pass_prices', 'rental' => 'rental_prices',
		'lessons' => 'lesson_prices', 'lodging' => 'lodging_prices') as $list => $prices) {
		pg_assert_same(array_keys($d[$list]), array_keys($d[$prices]), "$list: a price each");
		foreach ($d[$prices] as $key => $p) {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p[$c]) && $p[$c] > 0, "$list $key: $c price");
		}
	}
	foreach ($d['lift_passes'] as $key => $p) {
		foreach (array('title', 'who', 'includes') as $f) pg_assert_true(trim($p[$f]) !== '', "lift pass $key: $f");
	}
	foreach ($d['season_passes'] as $key => $p) {
		pg_assert_true(trim($p['title']) !== '' && trim($p['who']) !== '', "season pass $key: title and who");
		pg_assert_same(4, count($p['benefits']), "season pass $key: four benefits");
	}
	foreach ($d['rental'] as $key => $r) {
		foreach (array('icon', 'title', 'includes') as $f) pg_assert_true(trim($r[$f]) !== '', "rental $key: $f");
	}
	$levels = array();
	$ages = array();
	foreach ($d['lessons'] as $key => $l) {
		pg_assert_true(isset($d['levels'][$l['level']]), "lesson $key: a known level");
		pg_assert_true(isset($d['age_groups'][$l['age']]), "lesson $key: a known age group");
		foreach (array('title', 'length', 'summary') as $f) pg_assert_true(trim($l[$f]) !== '', "lesson $key: $f");
		$levels[$l['level']] = true;
		$ages[$l['age']] = true;
	}
	pg_assert_same(count($d['levels']), count($levels), 'a lesson at each level');
	pg_assert_same(count($d['age_groups']), count($ages), 'a lesson for each age group');
	foreach ($d['lodging'] as $key => $s) {
		foreach (array('icon', 'title', 'sleeps', 'where', 'summary') as $f) pg_assert_true(trim($s[$f]) !== '', "stay $key: $f");
	}
	foreach ($d['price_texts'] as $kind => $text) pg_assert_true((bool)preg_match('/[0-9]/', $text), "$kind: a price is written with its figure");
	$featured = 0;
	$today = date('Y-m-d');
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_kinds'][$e['kind']]), "event $key: a known kind");
		foreach (array('title', 'place', 'price', 'summary') as $f) pg_assert_true(trim($e[$f]) !== '', "event $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $e['time']), "event $key: a time");
		$date = $d['event_dates'][$key];
		pg_assert_true($date > $today, "event $key: still to come");
		pg_assert_same((int)$e['weekday'], (int)date('N', strtotime($date)), "event $key: on its weekday");
		if ($e['featured']) $featured++;
	}
	pg_assert_same(3, $featured, 'three events on the home');
	pg_assert_same(4, count($d['people']), 'four people');
	foreach ($d['people'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "person $n: $f");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_same(3, count($d['testimonials']), 'three guests\' words');
}

// The pick lists: one answer per difficulty, area, state, kind, level, age
// group, lesson and stay (plus the extra answers), no answer twice.
function test_skiresort_pick_lists_follow_the_data()
{
	$d = _test_sk_load();
	$l = $d['lists'];
	pg_assert_same(count($d['difficulties']), count($l['difficulty_titles']), 'an answer per difficulty');
	pg_assert_same(count($d['areas']), count($l['area_titles']), 'an answer per area');
	pg_assert_same(count($d['run_statuses']), count($l['status_titles']), 'an answer per state');
	pg_assert_same(count($d['lift_kinds']), count($l['lift_kind_titles']), 'an answer per lift kind');
	pg_assert_same(count($d['levels']), count($l['level_titles']), 'an answer per level');
	pg_assert_same(count($d['age_groups']), count($l['age_titles']), 'an answer per age group');
	pg_assert_same(count($d['lessons']) + 1, count($l['lesson_choices']), 'lesson: each by title and a private lesson');
	pg_assert_same(count($d['lodging']) + 1, count($l['stay_choices']), 'stay: each by title and not decided');
	pg_assert_same(count($d['event_kinds']), count($l['event_kind_titles']), 'an answer per event kind');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(4, count($l['lesson_statuses']), 'four states of a lesson request');
	pg_assert_same(4, count($l['enquiry_statuses']), 'four states of an enquiry');
	// A form link's query keeps the answer's own words.
	$params = array('lesson' => $l['lesson_titles'][0], 'level' => $l['level_titles'][2], 'age' => $l['age_titles'][0], 'difficulty' => $l['difficulty_titles'][0]);
	parse_str(call_user_func($d['query'], $params), $q);
	pg_assert_same($params, $q, 'the choices survive the query');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_skiresort_price_is_written_as_the_shop_writes_it()
{
	$d = _test_sk_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['lift_pass_prices']['day'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	$rent = $d['rental_prices']['ski_set'];
	$amount = isset($rent[$base]) ? $rent[$base] : $rent['USD'];
	pg_assert_same(sprintf(lang('%s a day'), pg_money_text($amount, $symbol)), $d['price_texts']['rental'], 'the rental price a day');
}

// The page shell places the shared parts by key, the band only on pages
// that are not forms; the helpers link only template pages.
function test_skiresort_page_shell_and_helpers()
{
	$d = _test_sk_load();
	$h = $d['helpers'];
	$shared = function ($tree) {
		$out = array();
		foreach ($tree['children'] as $c) if ($c['type'] === 'shared_ref') $out[] = $c['props']['templateShared'];
		return $out;
	};
	pg_assert_same(array('account_bar', 'header', 'snow_band', 'footer'), $shared($h['page']), 'a page with the band');
	pg_assert_same(array('account_bar', 'header', 'footer'), $shared($h['form_page']), 'a form page without it');
	$known = array_flip(_test_sk_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($h), $m);
	pg_assert_true(count($m[1]) > 0, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	pg_assert_same(count(_test_sk_page_keys()), count(array_unique(_test_sk_page_keys())), 'no page key twice');
	$nodes = array();
	_test_sk_nodes($h['faq'], $nodes);
	pg_assert_same('skr-faq-1-item', $nodes[0]['props']['id'], 'a question is its own search section');
	pg_assert_true(!empty($nodes[0]['props']['searchSection']), 'searchable');
}

// Classes the template adds of its own start with psk-; field and section
// ids with skr-.
function test_skiresort_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_sk_load()['helpers'] as $t) _test_sk_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'psk-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'skr-') === 0, "id $id is the template's");
	}
	foreach (array('psk-display', 'psk-eyebrow') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// The sample is never dated: a year or a month would read as an old
// season a year later. Event dates are counted at opening and not part of
// the data.
function test_skiresort_no_fixed_dates()
{
	$d = _test_sk_load();
	$json = json_encode(array($d['resort'], $d['slopes'], $d['lifts'], $d['conditions'], $d['lift_passes'], $d['season_passes'],
		$d['rental'], $d['lessons'], $d['lodging'], $d['events'], $d['people'], $d['testimonials'], $d['helpers']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a slope is "pist", a season pass "sezon
// kartı", a lift pass "skipass", the ski school "kayak okulu".
function test_skiresort_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bSlopes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'pist', 0, 'UTF-8') !== false, "'$key' says pist");
		if (preg_match('/\bSeason Pass\b/', $key)) pg_assert_true(mb_stripos($tr, 'sezon kart', 0, 'UTF-8') !== false, "'$key' says sezon kartı");
		if (preg_match('/^(Half-Day|One-Day|Three-Day|Six-Day) Pass$/', $key)) pg_assert_true(mb_stripos($tr, 'skipass', 0, 'UTF-8') !== false, "'$key' says skipass");
		if (preg_match('/\bski school\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kayak okul', 0, 'UTF-8') !== false, "'$key' says kayak okulu");
	}
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pharmacy design template's parts
 * (includes/design_templates/pinegrap-pharmacy/): the pharmacy's data
 * hangs together (pharmacists working known days and doing known services,
 * every service done by a pharmacist, every service, check and range
 * complete, priced services and checks with a price, the forms' pick lists
 * without an answer twice), a price is written as the shop writes it,
 * every page link of the shared parts names a page of the finished
 * template and none names a shop page, the on-duty page and the emergency
 * number are on every page, a pharmacist card reads the Pharmacists form's
 * fields, the service cards make their choice in the request forms, the
 * classes are the template's own, the sample carries no fixed date and
 * every literal has its Turkish.
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
function _test_rx_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/parts.php';
	$service_cards = array();
	foreach (array_keys($services) as $key) $service_cards[$key] = $service_card($key);
	$check_cards = array();
	foreach (array_keys($checks) as $key) $check_cards[$key] = $check_card($key);
	$pharmacist_cards = array();
	foreach (array_keys($pharmacists) as $key) $pharmacist_cards[$key] = $pharmacist_card($key);
	$day_trees = array();
	foreach (array_keys($pharmacists) as $key) $day_trees[$key] = $pharmacist_days($key);
	$range_cards = array();
	foreach (array_keys($ranges) as $key) $range_cards[$key] = $range_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'pharmacy'           => $pharmacy,
		'days'               => $days,
		'opening_hours'      => $opening_hours,
		'duty_facts'         => $duty_facts,
		'duty_tips'          => $duty_tips,
		'services'           => $services,
		'checks'             => $checks,
		'pharmacists'        => $pharmacists,
		'ranges'             => $ranges,
		'topics'             => $topics,
		'money'              => $money,
		'service_price'      => $service_price,
		'check_price'        => $check_price,
		'prescription_steps' => $prescription_steps,
		'figures'            => $figures,
		'testimonials'       => $testimonials,
		'lists'              => array(
			'service_titles'        => $service_titles,
			'request_choices'       => $request_choices,
			'pharmacist_names'      => $pharmacist_names,
			'pharmacist_choices'    => $pharmacist_choices,
			'day_titles'            => $day_titles,
			'time_choices'          => $time_choices,
			'topic_titles'          => $topic_titles,
			'topic_choices'         => $topic_choices,
			'check_titles'          => $check_titles,
			'prescription_statuses' => $prescription_statuses,
			'question_statuses'     => $question_statuses,
		),
		'radios'             => array('collect_choices' => $collect_choices, 'contact_choices' => $contact_choices),
		'service_cards'      => $service_cards,
		'check_cards'        => $check_cards,
		'pharmacist_cards'   => $pharmacist_cards,
		'day_trees'          => $day_trees,
		'range_cards'        => $range_cards,
		'testimonial_cards'  => $testimonial_cards,
		'header'             => $header(),
		'footer'             => $footer(),
		'notice'             => $notice(),
		'shared'             => array($account_bar_tree(), $notice(), $booking_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_rx_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rx_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_rx_trees()
{
	$d = _test_rx_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['service_cards']),
		array_values($d['check_cards']), array_values($d['pharmacist_cards']),
		array_values($d['day_trees']), array_values($d['range_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_rx_page_keys()
{
	$services = array();
	foreach (array_keys(_test_rx_load()['services']) as $key) $services[] = 'service_' . $key;
	return array_merge(array(
		'home', 'services', 'health_checks', 'on_duty', 'about', 'faq', 'location',
		'team', 'pharmacist', 'pharmacist_new', 'book_online',
		'prescription', 'prescription_view', 'my_prescriptions', 'staff_prescriptions', 'privacy', 'email_reply',
		'email_prescription_new', 'email_prescription_received',
		'ask', 'ask_view', 'my_questions', 'staff_questions', 'email_question_new', 'email_question_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), $services, _test_rx_shop_keys());
}

// The pages that exist only with the shop.
function _test_rx_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_pharmacy_data_hangs_together()
{
	$d = _test_rx_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['pharmacy'][$f]) !== '', "pharmacy: $f");
	foreach (array('phone', 'delivery') as $f) pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['pharmacy'][$f]), "pharmacy: $f number");
	pg_assert_true($d['pharmacy']['phone'] !== $d['pharmacy']['delivery'], 'the delivery line is its own number');
	pg_assert_true((bool)preg_match('/^[0-9]{3}$/', $d['pharmacy']['emergency']), 'the public emergency number');
	pg_assert_true(strpos($d['pharmacy']['email'], '@example.com') !== false, 'pharmacy: a sample address');
	pg_assert_same(3, count($d['opening_hours']), 'three lines of opening hours');
	pg_assert_same(array('ours', 'closed', 'emergency'), array_keys($d['duty_facts']), 'the three on-duty answers');
	pg_assert_true(count($d['duty_tips']) >= 3, 'what to bring at night');
	$actions = array();
	foreach ($d['services'] as $key => $s) {
		foreach (array('title', 'summary', 'text', 'icon') as $f) pg_assert_true(trim($s[$f]) !== '', "service $key: $f");
		pg_assert_true($s['from'] === null || (isset($s['from']['USD']) && $s['from']['USD'] > 0), "service $key: free or priced");
		pg_assert_true(count($s['points']) >= 3, "service $key: points");
		pg_assert_true(in_array($s['action'], array('prescription', 'book_online', 'ask'), true), "service $key: a known next step");
		if ($s['action'] === 'ask') pg_assert_true(isset($d['topics'][$s['topic']]), "service $key: a known topic");
		$actions[$s['action']] = true;
	}
	pg_assert_same(3, count($actions), 'each next step used');
	foreach ($d['checks'] as $key => $c) {
		foreach (array('title', 'text', 'icon') as $f) pg_assert_true(trim($c[$f]) !== '', "check $key: $f");
		pg_assert_true(isset($c['price']['USD']) && $c['price']['USD'] > 0, "check $key: a price");
		pg_assert_true(is_int($c['minutes']) && $c['minutes'] > 0, "check $key: minutes");
	}
	foreach ($d['ranges'] as $key => $r) {
		foreach (array('title', 'text', 'icon') as $f) pg_assert_true(trim($r[$f]) !== '', "range $key: $f");
	}
	$done = array();
	foreach ($d['pharmacists'] as $key => $p) {
		pg_assert_true(isset($d['services'][$p['service']]), "pharmacist $key: leads a known service");
		pg_assert_true(in_array($p['service'], $p['services'], true), "pharmacist $key: does the service they lead");
		pg_assert_true(is_int($p['years']) && $p['years'] > 0, "pharmacist $key: years");
		foreach (array('name', 'role', 'summary', 'bio') as $f) pg_assert_true(trim($p[$f]) !== '', "pharmacist $key: $f");
		pg_assert_true(count($p['days']) >= 3, "pharmacist $key: works at least three days");
		foreach ($p['days'] as $day) pg_assert_true(isset($d['days'][$day]), "pharmacist $key: a known day");
		foreach ($p['services'] as $s) {
			pg_assert_true(isset($d['services'][$s]), "pharmacist $key: a known service");
			$done[$s] = true;
		}
	}
	pg_assert_same(count($d['services']), count($done), 'every service has a pharmacist');
	// Every opening day has at least two pharmacists at work.
	foreach (array_keys($d['days']) as $day) {
		$on = 0;
		foreach ($d['pharmacists'] as $p) if (in_array($day, $p['days'], true)) $on++;
		pg_assert_true($on >= 2, "day $day: two pharmacists at work");
	}
	pg_assert_same(4, count($d['prescription_steps']), 'four prescription steps');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) pg_assert_true(isset($d['services'][$t['service']]), "testimonial $n: about a service");
	// The pick lists: one answer per service, pharmacist, day, topic and
	// check (plus the extra answers), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['services']), count($l['service_titles']), 'an answer per service');
	pg_assert_same(count($d['pharmacists']) + 1, count($l['pharmacist_choices']), 'pharmacist: any and each by name');
	pg_assert_same(count($d['days']), count($l['day_titles']), 'an answer per day');
	pg_assert_same(count($d['topics']) + 1, count($l['topic_choices']), 'topic: the topics and something else');
	pg_assert_same(count($d['checks']), count($l['check_titles']), 'an answer per check');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list; a free service says so.
function test_pharmacy_price_is_written_as_the_shop_writes_it()
{
	$d = _test_rx_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['checks']['cholesterol']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['check_price'], 'cholesterol'), 'the check price');
	$from = $d['services']['vaccinations']['from'];
	$amount = isset($from[$base]) ? $from[$base] : $from['USD'];
	pg_assert_same(sprintf(lang('From %s'), pg_money_text($amount, $symbol)), call_user_func($d['service_price'], 'vaccinations'), 'the service line');
	pg_assert_same(lang('Free of charge'), call_user_func($d['service_price'], 'prescriptions'), 'a free service');
}

function test_pharmacy_page_links_name_template_pages()
{
	$known = array_flip(_test_rx_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_rx_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$d = _test_rx_load();
	foreach (array_keys($d['services']) as $key) {
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($d['service_cards'][$key]), "service $key: links its page");
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($d['footer']), "service $key: in the footer");
	}
	foreach ($d['check_cards'] as $key => $card) pg_assert_contains('{{page:book_online}}', json_encode($card), "check $key: books online");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_pharmacy_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_rx_trees());
	foreach (_test_rx_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_rx_load();
	pg_assert_contains('{{page:prescription}}', json_encode($d['header']), 'the header asks for a prescription');
	$nodes = array();
	_test_rx_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The account bar's notice, on every page, links the on-duty page and calls
// the emergency number; the footer carries the same number.
function test_pharmacy_on_duty_and_emergency_on_every_page()
{
	$d = _test_rx_load();
	$tel = 'tel:' . $d['pharmacy']['emergency'];
	foreach (array('notice' => $d['notice'], 'footer' => $d['footer']) as $name => $tree) {
		$hrefs = array();
		$nodes = array();
		_test_rx_nodes($tree, $nodes);
		foreach ($nodes as $n) if (isset($n['props']['href'])) $hrefs[] = $n['props']['href'];
		pg_assert_true(in_array($tel, $hrefs, true), "$name: calls the emergency number");
		pg_assert_true(in_array('{{page:on_duty}}', $hrefs, true), "$name: links the on-duty page");
	}
}

// A pharmacist card reads every value from the Pharmacists form's fields,
// with the sample's own words on the canvas.
function test_pharmacy_pharmacist_card_reads_the_pharmacist()
{
	$d = _test_rx_load();
	foreach ($d['pharmacist_cards'] as $key => $card) {
		$nodes = array();
		_test_rx_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo', 'name', 'form_item_view', 'service', 'role', 'summary', 'years') as $t) {
			pg_assert_true(isset($tokens[$t]), "pharmacist $key: card reads $t");
		}
		$p = $d['pharmacists'][$key];
		pg_assert_true(in_array($p['name'], $texts, true), "pharmacist $key: sample name on the canvas");
		pg_assert_true(in_array($d['services'][$p['service']]['title'], $texts, true), "pharmacist $key: sample service on the canvas");
		pg_assert_true(in_array($d['services'][$p['service']]['title'], $d['lists']['service_titles'], true), "pharmacist $key: a Service answer");
	}
	// A pharmacist's days: one badge per day, the working ones in the palette's colour.
	foreach ($d['day_trees'] as $key => $tree) {
		pg_assert_same(count($d['days']), count($tree['children']), "pharmacist $key: a badge per day");
		$on = 0;
		foreach ($tree['children'] as $badge) if (strpos($badge['props']['cssClass'], 'text-bg-primary') !== false) $on++;
		pg_assert_same(count($d['pharmacists'][$key]['days']), $on, "pharmacist $key: the working days marked");
	}
}

// The links found in a tree that open the given page with a query.
function _test_rx_query_links($tree, $page)
{
	$nodes = array();
	_test_rx_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// A service card's next step: the prescription request with the service
// chosen, a question with its topic, or online booking, in the words the
// forms' lists use (the forms start from ?service= and ?topic=).
function test_pharmacy_service_cards_choose_in_the_request_forms()
{
	$d = _test_rx_load();
	$l = $d['lists'];
	foreach ($d['service_cards'] as $key => $card) {
		$s = $d['services'][$key];
		$rx = _test_rx_query_links($card, 'prescription');
		$ask = _test_rx_query_links($card, 'ask');
		if ($s['action'] === 'prescription') {
			pg_assert_same(1, count($rx), "service $key: one prescription link");
			pg_assert_same($s['title'], $rx[0]['service'], "service $key: the link names the service");
			pg_assert_true(in_array($rx[0]['service'], $l['request_choices'], true), "service $key: a Request answer");
		} elseif ($s['action'] === 'ask') {
			pg_assert_same(1, count($ask), "service $key: one question link");
			pg_assert_true(in_array($ask[0]['topic'], $l['topic_choices'], true), "service $key: a Topic answer");
		} else {
			pg_assert_same(0, count($rx) + count($ask), "service $key: no request link");
			pg_assert_contains('{{page:book_online}}', json_encode($card), "service $key: books online");
		}
	}
}

// Classes the template adds of its own start with prx-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with rx-.
function test_pharmacy_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_rx_trees() as $t) _test_rx_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai|pvt)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'prx-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'rx-') === 0, "id $id is the template's");
	}
	foreach (array('prx-brand', 'prx-cross', 'prx-display', 'prx-eyebrow', 'prx-price', 'prx-lift') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later, and the duty rota changes every month.
function test_pharmacy_no_fixed_dates()
{
	$d = _test_rx_load();
	$json = json_encode(array($d['services'], $d['checks'], $d['pharmacists'], $d['duty_facts'], $d['prescription_steps'], $d['testimonials'], _test_rx_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a pharmacy is "eczane", a pharmacist "eczacı",
// a prescription "reçete", being on duty "nöbet", a vaccination "aşı".
function test_pharmacy_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bPharmacists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczacı', 0, 'UTF-8') !== false, "'$key' says eczacı");
		elseif (preg_match('/\bPharmac(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczane', 0, 'UTF-8') !== false, "'$key' says eczane");
		if (preg_match('/\bPrescriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'reçete', 0, 'UTF-8') !== false, "'$key' says reçete");
		if (preg_match('/\bon[ -]duty\b|\bduty (nights|days)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'nöbet', 0, 'UTF-8') !== false, "'$key' says nöbet");
		if (preg_match('/\bVaccinations?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'aşı', 0, 'UTF-8') !== false, "'$key' says aşı");
	}
}

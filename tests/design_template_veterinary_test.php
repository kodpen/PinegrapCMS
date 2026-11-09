<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Veterinary design template's parts
 * (includes/design_templates/pinegrap-veterinary/): the practice's data
 * hangs together (services and plans for known pets, vets consulting on
 * known days and doing known services, every service done by a vet and
 * every pet seen by one, plans and services with a price, the forms' pick
 * lists without an answer twice), a price is written as the shop writes
 * it, every page link of the shared parts names a page of the finished
 * template and none names a shop page, the emergency line is on every
 * page, the footer opens the vet list filtered, a vet card reads the Vets
 * form's fields, the service, pet and plan links make their choice in the
 * request forms, the classes are the template's own, the sample carries
 * no fixed date and every literal has its Turkish.
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
function _test_vt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/parts.php';
	$service_cards = array();
	foreach (array_keys($services) as $key) $service_cards[$key] = $service_card($key);
	$species_cards = array();
	foreach (array_keys($species) as $key) $species_cards[$key] = $species_card($key);
	$vet_cards = array();
	foreach (array_keys($vets) as $key) $vet_cards[$key] = $vet_card($key);
	$vet_day_trees = array();
	foreach (array_keys($vets) as $key) $vet_day_trees[$key] = $vet_days($key);
	$plan_cards = array();
	foreach (array_keys($plans) as $key) $plan_cards[$key] = $plan_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'practice'          => $practice,
		'days'              => $days,
		'opening_hours'     => $opening_hours,
		'species'           => $species,
		'services'          => $services,
		'vets'              => $vets,
		'plans'             => $plans,
		'money'             => $money,
		'plan_price'        => $plan_price,
		'service_price'     => $service_price,
		'emergency_signs'   => $emergency_signs,
		'first_visit'       => $first_visit,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'species_titles'       => $species_titles,
			'pet_choices'          => $pet_choices,
			'service_titles'       => $service_titles,
			'reason_choices'       => $reason_choices,
			'vet_names'            => $vet_names,
			'vet_choices'          => $vet_choices,
			'day_titles'           => $day_titles,
			'time_choices'         => $time_choices,
			'plan_choices'         => $plan_choices,
			'age_choices'          => $age_choices,
			'appointment_statuses' => $appointment_statuses,
			'plan_join_statuses'   => $plan_join_statuses,
		),
		'radios'            => array('visit_choices' => $visit_choices, 'contact_choices' => $contact_choices),
		'service_cards'     => $service_cards,
		'species_cards'     => $species_cards,
		'vet_cards'         => $vet_cards,
		'vet_day_trees'     => $vet_day_trees,
		'plan_cards'        => $plan_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'notice'            => $notice(),
		'shared'            => array($account_bar_tree(), $notice(), $booking_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_vt_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vt_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_vt_trees()
{
	$d = _test_vt_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['service_cards']),
		array_values($d['species_cards']), array_values($d['vet_cards']), array_values($d['vet_day_trees']),
		array_values($d['plan_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_vt_page_keys()
{
	$services = array();
	foreach (array_keys(_test_vt_load()['services']) as $key) $services[] = 'service_' . $key;
	return array_merge(array(
		'home', 'services', 'pets', 'emergency', 'new_clients', 'health_plans', 'about', 'faq', 'location',
		'vets', 'vet', 'vet_new', 'book_online',
		'appointment', 'appointment_view', 'my_appointments', 'staff_appointments', 'privacy', 'email_reply',
		'email_appointment_new', 'email_appointment_received',
		'plan_join', 'plan_join_view', 'staff_plan_joins', 'email_plan_join_new', 'email_plan_join_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
		'email_account_welcome', 'email_password_reset',
	), $services, _test_vt_shop_keys());
}

// The pages that exist only with the shop.
function _test_vt_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_veterinary_data_hangs_together()
{
	$d = _test_vt_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['practice'][$f]) !== '', "practice: $f");
	foreach (array('phone', 'emergency') as $f) pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['practice'][$f]), "practice: $f number");
	pg_assert_true($d['practice']['phone'] !== $d['practice']['emergency'], 'the emergency line is its own number');
	pg_assert_true(strpos($d['practice']['email'], '@example.com') !== false, 'practice: a sample address');
	pg_assert_same(3, count($d['opening_hours']), 'three lines of opening hours');
	$offered = array();
	foreach ($d['services'] as $key => $s) {
		foreach (array('title', 'summary', 'text', 'icon') as $f) pg_assert_true(trim($s[$f]) !== '', "service $key: $f");
		pg_assert_true(isset($s['from']['USD']) && $s['from']['USD'] > 0, "service $key: a starting price");
		pg_assert_true(count($s['points']) >= 3, "service $key: points");
		pg_assert_true(count($s['species']) >= 1, "service $key: offered for a pet");
		foreach ($s['species'] as $sp) {
			pg_assert_true(isset($d['species'][$sp]), "service $key: known pet");
			$offered[$sp] = true;
		}
	}
	pg_assert_same(count($d['species']), count($offered), 'a service for every pet');
	$done = array();
	$seen = array();
	foreach ($d['vets'] as $key => $v) {
		pg_assert_true(isset($d['species'][$v['species']]), "vet $key: known pet");
		pg_assert_true(is_int($v['years']) && $v['years'] > 0, "vet $key: years");
		foreach (array('name', 'role', 'summary', 'bio') as $f) pg_assert_true(trim($v[$f]) !== '', "vet $key: $f");
		pg_assert_true(count($v['days']) >= 3, "vet $key: consults at least three days");
		foreach ($v['days'] as $day) pg_assert_true(isset($d['days'][$day]), "vet $key: a known day");
		foreach ($v['services'] as $s) {
			pg_assert_true(isset($d['services'][$s]), "vet $key: a known service");
			$done[$s] = true;
		}
		$seen[$v['species']] = true;
	}
	pg_assert_same(count($d['services']), count($done), 'every service has a vet');
	// Every opening day has at least two vets consulting.
	foreach (array_keys($d['days']) as $day) {
		$on = 0;
		foreach ($d['vets'] as $v) if (in_array($day, $v['days'], true)) $on++;
		pg_assert_true($on >= 2, "day $day: two vets consult");
	}
	$skus = array();
	$featured = 0;
	foreach ($d['plans'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^VET-[A-Z0-9-]+$/', $p['sku']), "plan $key: the template's SKU");
		pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "plan $key: a price");
		pg_assert_same(12, $p['months'], "plan $key: a year");
		pg_assert_true(count($p['points']) >= 3, "plan $key: points");
		foreach ($p['species'] as $sp) pg_assert_true(isset($d['species'][$sp]), "plan $key: known pet");
		if ($p['featured']) $featured++;
		$skus[$p['sku']] = true;
	}
	pg_assert_same(count($d['plans']), count($skus), 'no SKU twice');
	pg_assert_same(1, $featured, 'one plan marked as the most chosen');
	pg_assert_true(count($d['emergency_signs']) >= 4, 'emergency signs');
	pg_assert_same(4, count($d['first_visit']), 'four first-visit steps');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) pg_assert_true(isset($d['services'][$t['service']]), "testimonial $n: about a service");
	// The pick lists: one answer per pet, service, vet, day and plan (plus
	// the extra answers), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['species']), count($l['species_titles']), 'an answer per pet');
	pg_assert_same(count($d['species']) + 1, count($l['pet_choices']), 'pet: an answer per pet and another pet');
	pg_assert_same(count($d['services']), count($l['service_titles']), 'an answer per service');
	pg_assert_same(count($d['services']) + 2, count($l['reason_choices']), 'reason: the services and two more');
	pg_assert_same(count($d['vets']) + 1, count($l['vet_choices']), 'vet: any vet and each by name');
	pg_assert_same(count($d['days']), count($l['day_titles']), 'an answer per day');
	pg_assert_same(count($d['plans']), count($l['plan_choices']), 'an answer per plan');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_veterinary_price_is_written_as_the_shop_writes_it()
{
	$d = _test_vt_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['plans']['adult']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s a month'), pg_money_text($amount, $symbol)), call_user_func($d['plan_price'], 'adult'), 'the plan line');
	$from = $d['services']['dental']['from'];
	$amount = isset($from[$base]) ? $from[$base] : $from['USD'];
	pg_assert_same(sprintf(lang('From %s'), pg_money_text($amount, $symbol)), call_user_func($d['service_price'], 'dental'), 'the service line');
}

function test_veterinary_page_links_name_template_pages()
{
	$known = array_flip(_test_vt_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_vt_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$d = _test_vt_load();
	foreach (array_keys($d['services']) as $key) {
		pg_assert_contains('{{page:service_' . $key . '}}', json_encode($d['service_cards'][$key]), "service $key: links its page");
	}
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_veterinary_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_vt_trees());
	foreach (_test_vt_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_vt_load();
	pg_assert_contains('{{page:appointment}}', json_encode($d['header']), 'the header asks for an appointment');
	$nodes = array();
	_test_vt_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The account bar's notice, on every page, calls the emergency line and
// links the emergency page; the footer carries the same number.
function test_veterinary_emergency_line_on_every_page()
{
	$d = _test_vt_load();
	$tel = 'tel:' . preg_replace('/[^0-9+]/', '', $d['practice']['emergency']);
	foreach (array('notice' => $d['notice'], 'footer' => $d['footer']) as $name => $tree) {
		$hrefs = array();
		$nodes = array();
		_test_vt_nodes($tree, $nodes);
		foreach ($nodes as $n) if (isset($n['props']['href'])) $hrefs[] = $n['props']['href'];
		pg_assert_true(in_array($tel, $hrefs, true), "$name: calls the emergency line");
	}
	pg_assert_contains('{{page:emergency}}', json_encode($d['notice']), 'the notice links the emergency page');
}

// The footer's pet links open the vet list filtered on each pet, in the
// words the Pets list uses.
function test_veterinary_footer_filters_the_vet_list()
{
	$d = _test_vt_load();
	$seen = array();
	foreach (_test_vt_query_links($d['footer'], 'vets') as $q) {
		pg_assert_true(in_array($q['species'], $d['lists']['species_titles'], true), 'the link names a Pets answer');
		$seen[$q['species']] = true;
	}
	pg_assert_same(count($d['species']), count($seen), 'a link per pet');
}

// A vet card reads every value from the Vets form's fields, with the
// sample's own words on the canvas; its portrait leaves the title out.
function test_veterinary_vet_card_reads_the_vet()
{
	$d = _test_vt_load();
	foreach ($d['vet_cards'] as $key => $card) {
		$nodes = array();
		_test_vt_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		$src = '';
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
			if (isset($node['props']['src'])) $src = $node['props']['src'];
		}
		foreach (array('photo', 'name', 'form_item_view', 'species', 'role', 'summary', 'years') as $t) {
			pg_assert_true(isset($tokens[$t]), "vet $key: card reads $t");
		}
		$v = $d['vets'][$key];
		pg_assert_true(in_array($v['name'], $texts, true), "vet $key: sample name on the canvas");
		pg_assert_true(in_array($d['species'][$v['species']]['title'], $texts, true), "vet $key: sample pet on the canvas");
		// No sample vet's first name starts with D: a D would be the title's.
		pg_assert_false(strpos($src, 'text=D') !== false, "vet $key: no title in the initials");
	}
	// A vet's days: one badge per day, the working ones in the palette's colour.
	foreach ($d['vet_day_trees'] as $key => $tree) {
		pg_assert_same(count($d['days']), count($tree['children']), "vet $key: a badge per day");
		$on = 0;
		foreach ($tree['children'] as $badge) if (strpos($badge['props']['cssClass'], 'text-bg-primary') !== false) $on++;
		pg_assert_same(count($d['vets'][$key]['days']), $on, "vet $key: the consulting days marked");
	}
}

// The links found in a tree that open the given page with a query.
function _test_vt_query_links($tree, $page)
{
	$nodes = array();
	_test_vt_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// A service card opens the appointment request with the service chosen, a
// pet card with the pet and the vet list filtered, a plan card the sign-up
// with the plan: in the words the forms' lists use (the forms start from
// ?service=, ?species=, ?plan=).
function test_veterinary_links_choose_in_the_request_forms()
{
	$d = _test_vt_load();
	$l = $d['lists'];
	foreach ($d['service_cards'] as $key => $card) {
		$q = _test_vt_query_links($card, 'appointment');
		pg_assert_same(1, count($q), "service $key: one appointment link");
		pg_assert_same($d['services'][$key]['title'], $q[0]['service'], "service $key: the link names the service");
		pg_assert_true(in_array($q[0]['service'], $l['reason_choices'], true), "service $key: a Service answer");
	}
	foreach ($d['species_cards'] as $key => $card) {
		$q = _test_vt_query_links($card, 'appointment');
		pg_assert_same(1, count($q), "pet $key: one appointment link");
		pg_assert_true(in_array($q[0]['species'], $l['pet_choices'], true), "pet $key: a Pet answer");
		$q = _test_vt_query_links($card, 'vets');
		pg_assert_same($d['species'][$key]['title'], $q[0]['species'], "pet $key: filters the vet list");
	}
	foreach ($d['plan_cards'] as $key => $card) {
		$q = _test_vt_query_links($card, 'plan_join');
		pg_assert_same(1, count($q), "plan $key: one sign-up link");
		pg_assert_true(in_array($q[0]['plan'], $l['plan_choices'], true), "plan $key: the link names a Plan answer");
	}
}

// Classes the template adds of its own start with pvt-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with vt-.
function test_veterinary_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_vt_trees() as $t) _test_vt_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'pvt-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'vt-') === 0, "id $id is the template's");
	}
	foreach (array('pvt-brand', 'pvt-display', 'pvt-eyebrow', 'pvt-price', 'pvt-lift') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_veterinary_no_fixed_dates()
{
	$d = _test_vt_load();
	$json = json_encode(array($d['services'], $d['vets'], $d['plans'], $d['first_visit'], $d['testimonials'], _test_vt_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a vet is "veteriner", a pet "evcil hayvan", an
// appointment "randevu", a health plan "sağlık planı", an emergency "acil".
function test_veterinary_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bVets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veteriner', 0, 'UTF-8') !== false, "'$key' says veteriner");
		if (preg_match('/\bPets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'evcil hayvan', 0, 'UTF-8') !== false, "'$key' says evcil hayvan");
		if (preg_match('/\bAppointments?\b/', $key)) pg_assert_true(mb_stripos($tr, 'randevu', 0, 'UTF-8') !== false, "'$key' says randevu");
		if (preg_match('/\bHealth Plans?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sağlık plan', 0, 'UTF-8') !== false, "'$key' says sağlık planı");
		if (preg_match('/\bEmergenc(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'acil', 0, 'UTF-8') !== false, "'$key' says acil");
	}
}

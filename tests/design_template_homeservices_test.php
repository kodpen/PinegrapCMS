<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's parts
 * (includes/design_templates/pinegrap-homeservices/): the company's data
 * hangs together (sample services naming a known category with a price,
 * areas, team members with a known role, testimonials about a sample
 * service, care plans with the template's SKUs, the forms' pick lists
 * without an answer twice), a price is written as the shop writes it,
 * every page link of the shared parts names a page of the finished
 * template and none names a shop page, the footer opens the service list
 * filtered and the areas at their cards, the cards read their form's
 * fields, an area card opens the visit booking and the estimate request
 * with the area chosen, the classes are the template's own, the sample
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

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_hs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/parts.php';
	$service_cards = array();
	foreach (array_keys($services) as $key) $service_cards[$key] = $service_card($key);
	$category_cards = array();
	foreach (array_keys($categories) as $key) $category_cards[$key] = $category_card($key);
	$area_cards = array();
	foreach (array_keys($areas) as $key) $area_cards[$key] = $area_card($key);
	$team_cards = array();
	foreach (array_keys($team) as $n) $team_cards[$n] = $team_card($n);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'company'           => $company,
		'categories'        => $categories,
		'services'          => $services,
		'service_prices'    => $service_prices,
		'call_out'          => $call_out,
		'money'             => $money,
		'price_text'        => $price_text,
		'crew_text'         => $crew_text,
		'includes_text'     => $includes_text,
		'areas'             => $areas,
		'team'              => $team,
		'how_it_works'      => $how_it_works,
		'why_us'            => $why_us,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'care_plans'        => $care_plans,
		'lists'             => array(
			'category_titles'   => $category_titles,
			'service_names'     => $service_names,
			'service_choices'   => $service_choices,
			'area_names'        => $area_names,
			'team_roles'        => $team_roles,
			'time_windows'      => $time_windows,
			'property_types'    => $property_types,
			'property_sizes'    => $property_sizes,
			'urgency_choices'   => $urgency_choices,
			'visit_statuses'    => $visit_statuses,
			'estimate_statuses' => $estimate_statuses,
		),
		'radios'            => array('access_choices' => $access_choices, 'contact_choices' => $contact_choices),
		'service_cards'     => $service_cards,
		'category_cards'    => $category_cards,
		'area_cards'        => $area_cards,
		'team_cards'        => $team_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $visit_band(), $staff_bar(), $post_card(), $ai_summary(), $service_facts()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_hs_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_hs_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_hs_trees()
{
	$d = _test_hs_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['service_cards']),
		array_values($d['category_cards']), array_values($d['area_cards']), array_values($d['team_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_hs_page_keys()
{
	return array_merge(array(
		'home', 'about', 'areas', 'prices', 'faq', 'reviews',
		'services', 'service', 'service_new', 'team', 'team_member', 'team_new',
		'book', 'visit_view', 'my_visits', 'staff_visits', 'privacy', 'email_reply', 'email_visit_new', 'email_visit_received',
		'estimate', 'estimate_view', 'my_estimates', 'staff_estimates', 'email_estimate_new', 'email_estimate_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_hs_shop_keys());
}

// The pages that exist only with the shop.
function _test_hs_shop_keys()
{
	return array('plans', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_homeservices_data_hangs_together()
{
	$d = _test_hs_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['company'][$f]) !== '', "company: $f");
	foreach (array('phone', 'emergency') as $f) pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['company'][$f]), "company: $f number");
	pg_assert_true($d['company']['phone'] !== $d['company']['emergency'], 'company: an emergency line of its own');
	pg_assert_true(strpos($d['company']['email'], '@example.com') !== false, 'company: a sample address');
	pg_assert_same(4, count($d['categories']), 'four categories');
	pg_assert_same(8, count($d['services']), 'eight sample services');
	$per_category = array();
	$featured = 0;
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(isset($d['categories'][$s['category']]), "service $key: known category");
		pg_assert_true(in_array($s['unit'], array('visit', 'hour'), true), "service $key: priced per visit or per hour");
		pg_assert_true(is_int($s['crew']) && $s['crew'] >= 1 && $s['crew'] <= 4, "service $key: a crew");
		foreach (array('name', 'summary', 'duration') as $f) pg_assert_true(trim($s[$f]) !== '', "service $key: $f");
		pg_assert_true(count($s['includes']) >= 4, "service $key: what it includes");
		pg_assert_true(isset($d['service_prices'][$key]['USD']), "service $key: has a price");
		if ($s['featured']) $featured++;
		$per_category[$s['category']] = isset($per_category[$s['category']]) ? $per_category[$s['category']] + 1 : 1;
	}
	foreach ($d['categories'] as $key => $c) {
		pg_assert_true(!empty($per_category[$key]) && $per_category[$key] >= 2, "category $key: two services or more");
		pg_assert_true(trim($c['text']) !== '', "category $key: a line");
	}
	pg_assert_same(3, $featured, 'three services on the home');
	pg_assert_same(array_keys($d['services']), array_keys($d['service_prices']), 'a price per service and no other');
	foreach ($d['service_prices'] as $key => $p) {
		pg_assert_same(array_keys($d['call_out']), array_keys($p), "service $key: the same currencies as the call-out charge");
		foreach ($d['call_out'] as $cur => $amount) pg_assert_true($p[$cur] > $amount, "service $key: the price is more than the call-out charge in $cur");
	}
	pg_assert_same(6, count($d['areas']), 'six areas');
	$core = 0;
	foreach ($d['areas'] as $key => $a) {
		foreach (array('name', 'days', 'places') as $f) pg_assert_true(trim($a[$f]) !== '', "area $key: $f");
		if ($a['core']) $core++;
	}
	pg_assert_true($core > 0 && $core < count($d['areas']), 'areas with and without a call-out charge');
	pg_assert_same(4, count($d['team']), 'four team members');
	foreach ($d['team'] as $n => $m) {
		pg_assert_true(in_array($m['role'], $d['lists']['team_roles'], true), "team $n: a known role");
		pg_assert_true(is_int($m['years']) && $m['years'] > 0, "team $n: years in the trade");
	}
	pg_assert_same(4, count($d['how_it_works']), 'four steps');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_same((string)count($d['areas']), $d['figures'][2]['value'], 'the areas figure counts the areas');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['services'][$t['service']]), "testimonial $n: about a sample service");
		pg_assert_contains($d['services'][$t['service']]['name'], $t['role'], "testimonial $n: names the service");
	}
	$skus = array();
	foreach ($d['care_plans'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^HOMESVC-[A-Z0-9-]+$/', $p['sku']), "care plan $key: the template's SKU");
		pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "care plan $key: a price");
		$skus[$p['sku']] = true;
	}
	pg_assert_same(count($d['care_plans']), count($skus), 'no SKU twice');
	// The pick lists: one answer per category, service (plus "something
	// else") and area, no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['categories']), count($l['category_titles']), 'an answer per category');
	pg_assert_same(count($d['services']) + 1, count($l['service_choices']), 'an answer per service and something else');
	pg_assert_same(count($d['areas']), count($l['area_names']), 'an answer per area');
	pg_assert_same(count($d['categories']) + 1, count($l['team_roles']), 'a role per category and the office');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(3, count($l['time_windows']), 'three time windows');
	pg_assert_same(5, count($l['visit_statuses']), 'five states of a visit booking');
	pg_assert_same(4, count($l['estimate_statuses']), 'four states of an estimate request');
}

// A service's price shows one amount: the base currency's figure, the USD
// figure for a currency the table does not list; per visit or per hour as
// the service is priced.
function test_homeservices_price_is_written_as_the_shop_writes_it()
{
	$d = _test_hs_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$amount = function ($prices) use ($base) {
		return isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	};
	$prices = $d['service_prices']['leak_repair'];
	pg_assert_same(pg_money_text($amount($prices), $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('From %s per visit'), pg_money_text($amount($prices), $symbol)), call_user_func($d['price_text'], 'leak_repair'), 'per visit');
	$prices = $d['service_prices']['handyman'];
	pg_assert_same(sprintf(lang('From %s per hour'), pg_money_text($amount($prices), $symbol)), call_user_func($d['price_text'], 'handyman'), 'per hour');
	pg_assert_same(lang('1 technician'), call_user_func($d['crew_text'], 'leak_repair'), 'one technician');
	pg_assert_same(sprintf(lang('%d technicians'), 3), call_user_func($d['crew_text'], 'move_out'), 'a crew of three');
	pg_assert_same(implode("\n", $d['services']['deep_clean']['includes']), call_user_func($d['includes_text'], 'deep_clean'), 'a line per included item');
}

function test_homeservices_page_links_name_template_pages()
{
	$known = array_flip(_test_hs_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_hs_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_homeservices_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_hs_trees());
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_hs_load();
	$header = json_encode($d['header']);
	pg_assert_contains('{{page:book}}', $header, 'the header books a visit');
	pg_assert_contains('{{page:estimate}}', $header, 'the header asks for an estimate');
	$nodes = array();
	_test_hs_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the service list filtered on each category, in the
// words the Category list uses, and each area at its card on the areas
// page.
function test_homeservices_footer_filters_the_lists()
{
	$d = _test_hs_load();
	$nodes = array();
	_test_hs_nodes($d['footer'], $nodes);
	$seen = array();
	$anchors = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href'])) continue;
		$href = $node['props']['href'];
		if (strpos($href, '{{page:services}}?') === 0) {
			parse_str(substr($href, strlen('{{page:services}}?')), $q);
			pg_assert_true(isset($q['category']) && in_array($q['category'], $d['lists']['category_titles'], true), 'the link names a category answer');
			$seen[$q['category']] = true;
		}
		if (strpos($href, '{{page:areas}}#') === 0) $anchors[] = substr($href, strlen('{{page:areas}}#'));
	}
	pg_assert_same(count($d['categories']), count($seen), 'a link per category');
	pg_assert_same(count($d['areas']), count($anchors), 'a link per area');
	$ids = array();
	foreach ($d['area_cards'] as $card) {
		$cn = array();
		_test_hs_nodes($card, $cn);
		foreach ($cn as $n) {
			if (isset($n['props']['id'])) $ids[] = $n['props']['id'];
		}
	}
	foreach ($anchors as $a) pg_assert_true(in_array($a, $ids, true), "#$a is an area card's id");
}

// The values a card reads from its form's fields, and its texts.
function _test_hs_card_reads($card)
{
	$nodes = array();
	_test_hs_nodes($card, $nodes);
	$tokens = array();
	$texts = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['_bindings'])) {
			foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
		}
		if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
	}
	return array($tokens, $texts);
}

// A service card reads every value from the Services form's fields, a team
// card from the Team form's, with the sample's own words on the canvas.
function test_homeservices_cards_read_their_records()
{
	$d = _test_hs_load();
	foreach ($d['service_cards'] as $key => $card) {
		list($tokens, $texts) = _test_hs_card_reads($card);
		foreach (array('photo', 'name', 'form_item_view', 'category', 'summary', 'duration', 'crew', 'price') as $t) {
			pg_assert_true(isset($tokens[$t]), "service $key: card reads $t");
		}
		$s = $d['services'][$key];
		pg_assert_true(in_array($s['name'], $texts, true), "service $key: sample name on the canvas");
		pg_assert_true(in_array($d['categories'][$s['category']]['title'], $texts, true), "service $key: sample category on the canvas");
		pg_assert_true(in_array(call_user_func($d['price_text'], $key), $texts, true), "service $key: sample price on the canvas");
		pg_assert_true(in_array(call_user_func($d['crew_text'], $key), $texts, true), "service $key: sample crew on the canvas");
	}
	foreach ($d['team_cards'] as $n => $card) {
		list($tokens, $texts) = _test_hs_card_reads($card);
		foreach (array('photo', 'name', 'form_item_view', 'role', 'years', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "team $n: card reads $t");
		}
		pg_assert_true(in_array($d['team'][$n]['name'], $texts, true), "team $n: sample name on the canvas");
	}
}

// An area card opens the visit booking and the estimate request with the
// area chosen, in the words the Area list uses (the forms start from
// ?area=); a category card opens the service list filtered on it.
function test_homeservices_area_card_chooses_in_the_forms()
{
	$d = _test_hs_load();
	foreach ($d['area_cards'] as $key => $card) {
		$nodes = array();
		_test_hs_nodes($card, $nodes);
		$found = array();
		foreach ($nodes as $node) {
			if (!isset($node['props']['href'])) continue;
			foreach (array('book', 'estimate') as $form) {
				$prefix = '{{page:' . $form . '}}?';
				if (strpos($node['props']['href'], $prefix) !== 0) continue;
				parse_str(substr($node['props']['href'], strlen($prefix)), $q);
				pg_assert_same($d['areas'][$key]['name'], $q['area'], "area $key: $form names the area");
				pg_assert_true(in_array($q['area'], $d['lists']['area_names'], true), "area $key: an answer of the list");
				$found[$form] = true;
			}
		}
		pg_assert_same(2, count($found), "area $key: opens both forms");
	}
	foreach ($d['category_cards'] as $key => $card) {
		pg_assert_contains('{{page:services}}?' . http_build_query(array('category' => $d['categories'][$key]['title']), '', '&', PHP_QUERY_RFC3986),
			json_encode($card, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "category $key: opens the list filtered");
	}
}

// Classes the template adds of its own start with phs-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field and section ids start with hs-.
function test_homeservices_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_hs_trees() as $t) _test_hs_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "class $c is not another template's");
			if (strpos($c, 'phs-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'hs-') === 0, "id $id is the template's");
	}
	foreach (array('phs-brand', 'phs-display', 'phs-eyebrow', 'phs-price', 'phs-lift', 'phs-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later; visit days are words.
function test_homeservices_no_fixed_dates()
{
	$d = _test_hs_load();
	$json = json_encode(array($d['services'], $d['areas'], $d['team'], $d['care_plans'], _test_hs_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a service is "hizmet", a visit "ziyaret", an
// estimate "teklif", a booking never "rezervasyon".
function test_homeservices_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bServices?\b/', $key)) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
		if (preg_match('/\bVisits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		if (preg_match('/\bEstimates?\b/', $key)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
		pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
	}
}

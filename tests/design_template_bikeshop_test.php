<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bike Shop design template's data and page helpers
 * (includes/design_templates/pinegrap-bikeshop/parts.php): the shop's data
 * hangs together (every job, fit package and product with a price in every
 * currency; products in a known group with a code of their group; bikes of
 * a known kind, only bikes kept from the post; rides with a known pace and
 * drop rule falling after today), the pick lists follow the data without
 * an answer twice, a price is written as the shop writes it, the page
 * shell places the shared parts, the helpers link only template pages, the
 * classes and ids are the template's own, the sample carries no fixed date
 * and every literal has its Turkish.
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
function _test_bikeshop_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/parts.php';
	$ride_words = array();
	$dates = array();
	foreach ($rides as $key => $r) {
		$ride_words[$key] = array($ride_pace($key), $ride_drop($key));
		$dates[$key] = $ride_on($r['weekday'], $r['weeks']);
	}
	$product_words = array();
	foreach (array_keys($products) as $key) $product_words[$key] = array($product_group($key), $product_sku($key));
	$out = array(
		'shop'           => $shop,
		'opening_hours'  => $opening_hours,
		'job_kinds'      => $job_kinds,
		'job_prices'     => $job_prices,
		'bike_kinds'     => $bike_kinds,
		'frame_sizes'    => $frame_sizes,
		'fit_kinds'      => $fit_kinds,
		'fit_prices'     => $fit_prices,
		'riding_styles'  => $riding_styles,
		'product_groups' => $product_groups,
		'products'       => $products,
		'product_prices' => $product_prices,
		'paces'          => $paces,
		'drop_rules'     => $drop_rules,
		'rides'          => $rides,
		'ride_dates'     => $dates,
		'ride_words'     => $ride_words,
		'product_words'  => $product_words,
		'people'         => $people,
		'brands'         => $brands,
		'details_text'   => $details_text,
		'money'          => $money,
		'query'          => $query,
		'price_texts'    => array('job' => $job_price_text('service'), 'fit' => $fit_price_text('full')),
		'lists'          => array(
			'job_titles'       => $job_titles,
			'job_choices'      => $job_choices,
			'bike_titles'      => $bike_titles,
			'fit_titles'       => $fit_titles,
			'size_titles'      => $size_titles,
			'group_titles'     => $group_titles,
			'pace_titles'      => $pace_titles,
			'drop_titles'      => $drop_titles,
			'riding_styles'    => $riding_styles,
			'service_statuses' => $service_statuses,
			'fit_statuses'     => $fit_statuses,
		),
		'radios'         => array('contact_choices' => $contact_choices),
		'helpers'        => array(
			'page'           => $page(array($para('x'))),
			'page_header'    => $page_header(lang('Bikes'), lang('Parts')),
			'section_header' => $section_header(lang('Bikes'), lang('Parts'), lang('Workshop Vouchers'), '{{page:workshop}}'),
			'icon_tile'      => $icon_tile('bi-tools'),
			'pill'           => $pill('bi-clock', lang('Same day')),
			'facts'          => $facts(array(lang('Bikes') => '45 km', lang('Parts') => '350 m')),
			'step'           => $step(1, lang('Bikes'), lang('Parts')),
			'check_list'     => $check_list($job_kinds['service']['includes']),
			'faq'            => $faq(1, lang('Bikes'), lang('Parts')),
			'rise'           => $rise($el('div', 'row', lang('Row'))),
			'fade_in'        => $fade_in($image($photo('fit', 800, 600), lang('Bike Fitter'), 'img-fluid')),
		),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_bikeshop_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bikeshop_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_bikeshop_page_keys()
{
	return array_merge(array(
		'home', 'workshop', 'sizes', 'fit', 'about',
		'service_request', 'service_view', 'my_services', 'staff_services',
		'fit_request', 'fit_view', 'my_fits', 'staff_fits', 'book_online', 'privacy', 'email_reply',
		'rides', 'ride', 'ride_new',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome', 'email_account_welcome', 'email_password_reset',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_bikeshop_shop_keys());
}

// The pages that exist only with the shop.
function _test_bikeshop_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order', 'address_book');
}

function test_bikeshop_data_hangs_together()
{
	$d = _test_bikeshop_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'season', 'workshop', 'collect') as $f) pg_assert_true(trim($d['shop'][$f]) !== '', "shop: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['shop']['phone']), 'shop: a phone number');
	pg_assert_true(strpos($d['shop']['email'], '@example.com') !== false, 'shop: a sample address');
	pg_assert_same(4, count($d['opening_hours']), 'four lines of hours');
	foreach ($d['opening_hours'] as $n => $h) pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2} - [0-9]{2}:[0-9]{2}$/', $h['hours']), "hours $n: from and to");
	// The plan's jobs, bike kinds and fit packages, in its order.
	pg_assert_same(array('puncture', 'safety', 'service', 'overhaul', 'suspension', 'ebike'), array_keys($d['job_kinds']), 'the six workshop jobs');
	pg_assert_same(array('basic', 'full', 'cleat'), array_keys($d['fit_kinds']), 'the three fit packages');
	pg_assert_same(array('S', 'M', 'L', 'XL'), array_keys($d['frame_sizes']), 'the four frame sizes');
	pg_assert_same(array('social', 'steady', 'fast'), array_keys($d['paces']), 'the three paces');
	pg_assert_same(array('nodrop', 'drop'), array_keys($d['drop_rules']), 'the two drop rules');
	foreach ($d['job_kinds'] as $key => $j) {
		foreach (array('icon', 'title', 'turnaround', 'summary') as $f) pg_assert_true(trim($j[$f]) !== '', "job $key: $f");
		pg_assert_true(count($j['includes']) >= 3, "job $key: what is done");
	}
	foreach ($d['fit_kinds'] as $key => $f) {
		foreach (array('title', 'length', 'summary') as $n) pg_assert_true(trim($f[$n]) !== '', "fit $key: $n");
		pg_assert_true(count($f['includes']) >= 3, "fit $key: what is included");
	}
	foreach ($d['bike_kinds'] as $key => $b) pg_assert_true(strpos($b['icon'], 'bi-') === 0 && trim($b['title']) !== '', "bike kind $key: icon and title");
	// Every job, fit package and product has a price in each currency the
	// catalog knows.
	foreach (array('job_kinds' => 'job_prices', 'fit_kinds' => 'fit_prices', 'products' => 'product_prices') as $list => $prices) {
		pg_assert_same(array_keys($d[$list]), array_keys($d[$prices]), "$list: a price each");
		foreach ($d[$prices] as $key => $p) {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p[$c]) && $p[$c] > 0, "$list $key: $c price");
		}
	}
	foreach ($d['price_texts'] as $kind => $text) pg_assert_true((bool)preg_match('/[0-9]/', $text), "$kind: a price is written with its figure");
	// The catalog: its four groups with their codes; bikes collected in the
	// shop, the rest posted.
	pg_assert_same(array('BKS-BIKE-', 'BKS-PART-', 'BKS-ACC-', 'BKS-GIFT-'), array_column($d['product_groups'], 'sku'), 'the group codes');
	foreach ($d['product_groups'] as $key => $g) pg_assert_same($key !== 'bikes', $g['shippable'], "group $key: posted unless bikes");
	$groups = array();
	$skus = array();
	$featured = 0;
	foreach ($d['products'] as $key => $p) {
		pg_assert_true(isset($d['product_groups'][$p['group']]), "product $key: a known group");
		foreach (array('title', 'summary') as $f) pg_assert_true(trim($p[$f]) !== '', "product $key: $f");
		pg_assert_true(count($p['details']) >= 2, "product $key: its details");
		pg_assert_same(implode("\n", $p['details']), call_user_func($d['details_text'], $p['details']), "product $key: a detail line per item");
		list($group, $sku) = $d['product_words'][$key];
		pg_assert_true(in_array($group, $d['lists']['group_titles'], true), "product $key: its group is an answer of the list");
		pg_assert_true(strpos($sku, $d['product_groups'][$p['group']]['sku']) === 0, "product $key: its code starts with its group's");
		pg_assert_true((bool)preg_match('/^[A-Z0-9-]+$/', $sku), "product $key: a plain code");
		pg_assert_false(isset($skus[$sku]), "product $key: its code is its own");
		$skus[$sku] = true;
		$groups[$p['group']] = true;
		if ($p['group'] === 'bikes') {
			pg_assert_true(isset($d['bike_kinds'][$p['kind']]), "bike $key: a known kind");
		}
		if ($p['featured']) {
			pg_assert_same('bikes', $p['group'], "product $key: only bikes are the newest on the home");
			$featured++;
		}
	}
	pg_assert_same(count($d['product_groups']), count($groups), 'a product in each group');
	pg_assert_same(3, $featured, 'three bikes on the home');
	// Rides: still to come, on their weekday, with a known pace and rule.
	$today = date('Y-m-d');
	$featured = 0;
	$paces = array();
	foreach ($d['rides'] as $key => $r) {
		pg_assert_true(isset($d['paces'][$r['pace']]), "ride $key: a known pace");
		pg_assert_true(isset($d['drop_rules'][$r['drop']]), "ride $key: a known drop rule");
		foreach (array('title', 'place', 'summary') as $f) pg_assert_true(trim($r[$f]) !== '', "ride $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $r['time']), "ride $key: a start time");
		pg_assert_true((bool)preg_match('/^[0-9,]+ km$/', $r['distance']), "ride $key: a distance");
		pg_assert_true((bool)preg_match('/^[0-9,]+ m$/', $r['climbing']), "ride $key: the climbing");
		list($pace, $drop) = $d['ride_words'][$key];
		pg_assert_true(in_array($pace, $d['lists']['pace_titles'], true), "ride $key: its pace is an answer of the list");
		pg_assert_true(in_array($drop, $d['lists']['drop_titles'], true), "ride $key: its drop rule is an answer of the list");
		$date = $d['ride_dates'][$key];
		pg_assert_true($date > $today, "ride $key: still to come");
		pg_assert_same((int)$r['weekday'], (int)date('N', strtotime($date)), "ride $key: on its weekday");
		$paces[$r['pace']] = true;
		if ($r['featured']) $featured++;
	}
	pg_assert_same(count($d['paces']), count($paces), 'a ride of each pace');
	pg_assert_same(3, $featured, 'three rides marked for the home');
	pg_assert_same(4, count($d['people']), 'four people');
	foreach ($d['people'] as $n => $t) {
		foreach (array('name', 'role', 'bio') as $f) pg_assert_true(trim($t[$f]) !== '', "person $n: $f");
	}
	pg_assert_same(6, count(array_unique($d['brands'])), 'six brands stocked');
}

// The pick lists: one answer per job (plus the extra answer), bike kind,
// fit package, size, group, pace and drop rule; no answer twice.
function test_bikeshop_pick_lists_follow_the_data()
{
	$d = _test_bikeshop_load();
	$l = $d['lists'];
	pg_assert_same(count($d['job_kinds']) + 1, count($l['job_choices']), 'job: each by title and something else');
	pg_assert_same(count($d['bike_kinds']), count($l['bike_titles']), 'an answer per bike kind');
	pg_assert_same(count($d['fit_kinds']), count($l['fit_titles']), 'an answer per fit package');
	pg_assert_same(array_keys($d['frame_sizes']), $l['size_titles'], 'the size codes');
	pg_assert_same(count($d['product_groups']), count($l['group_titles']), 'an answer per group');
	pg_assert_same(count($d['paces']), count($l['pace_titles']), 'an answer per pace');
	pg_assert_same(count($d['drop_rules']), count($l['drop_titles']), 'an answer per drop rule');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(6, count($l['service_statuses']), 'six states of a service request');
	pg_assert_same(4, count($l['fit_statuses']), 'four states of a fit request');
	// A form link's query keeps the answer's own words.
	$params = array('job' => $l['job_titles'][2], 'bike' => $l['bike_titles'][4], 'fit' => $l['fit_titles'][1], 'pace' => $l['pace_titles'][0]);
	parse_str(call_user_func($d['query'], $params), $q);
	pg_assert_same($params, $q, 'the choices survive the query');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_bikeshop_price_is_written_as_the_shop_writes_it()
{
	$d = _test_bikeshop_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['product_prices']['gravel'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	$job = $d['job_prices']['service'];
	$amount = isset($job[$base]) ? $job[$base] : $job['USD'];
	pg_assert_same(sprintf(lang('From %s'), pg_money_text($amount, $symbol)), $d['price_texts']['job'], 'a job from its price');
	$fit = $d['fit_prices']['full'];
	$amount = isset($fit[$base]) ? $fit[$base] : $fit['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), $d['price_texts']['fit'], 'a fit at its price');
}

// The page shell places the shared parts by key; the helpers link only
// template pages; the motion stays in the interactions.
function test_bikeshop_page_shell_and_helpers()
{
	$d = _test_bikeshop_load();
	$h = $d['helpers'];
	$shared = array();
	foreach ($h['page']['children'] as $c) if ($c['type'] === 'shared_ref') $shared[] = $c['props']['templateShared'];
	pg_assert_same(array('account_bar', 'header', 'footer'), $shared, 'a page between the header and the footer');
	$known = array_flip(_test_bikeshop_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($h), $m);
	pg_assert_true(count($m[1]) > 0, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	pg_assert_same(count(_test_bikeshop_page_keys()), count(array_unique(_test_bikeshop_page_keys())), 'no page key twice');
	$nodes = array();
	_test_bikeshop_nodes($h['faq'], $nodes);
	pg_assert_same('bikeshop-faq-1-item', $nodes[0]['props']['id'], 'a question is its own search section');
	pg_assert_true(!empty($nodes[0]['props']['searchSection']), 'searchable');
	// A pill says its words beside its icon.
	$nodes = array();
	_test_bikeshop_nodes($h['pill'], $nodes);
	$texts = array_filter(array_map(function ($n) { return $n['props']['text'] ?? ''; }, $nodes));
	pg_assert_same(array(lang('Same day')), array_values($texts), 'a pill has its words');
	pg_assert_same('dl', $h['facts']['props']['tag'], 'facts are a description list');
	pg_assert_same(2, count($h['facts']['children']), 'a fact per pair');
	pg_assert_same(array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 80), $h['rise']['props']['_fx'], 'an entrance through the interactions');
	$fx = pg_fx_normalize($h['rise']['props']['_fx']);
	pg_assert_same(array('slide-up', 'view', 80), array($fx['effect'], $fx['trigger'], $fx['stagger']), 'the entrance survives the normalizer');
	$fx = pg_fx_normalize($h['fade_in']['props']['_fx']);
	pg_assert_same(array('fade', 'view'), array($fx['effect'], $fx['trigger']), 'the photo fades in on view');
}

// Classes the template adds of its own start with pbs-; field and section
// ids with bikeshop-. The block family's pg-bike- rules are shared.
function test_bikeshop_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_bikeshop_load()['helpers'] as $t) _test_bikeshop_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pzo|psk|prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pbk|pbw|pcn|pcp|pcr|pcw|pcy|pdl|pdp|pdr|pfl|pfm|pfy|pgy|phs|pht|pma|pmu|ppd|prc|psh|psp|pth|ptv|pwd|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false((bool)preg_match('/^pg-(?!bike-)[a-z]+-/', $c), "only the bike block family's rules ($c)");
			if (strpos($c, 'pbs-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bikeshop-') === 0, "id $id is the template's");
	}
	foreach (array('pbs-display', 'pbs-hero', 'pbs-mark') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// The sample is never dated: a year or a month would read as an old
// season a year later. Ride dates are counted at opening and not part of
// the data.
function test_bikeshop_no_fixed_dates()
{
	$d = _test_bikeshop_load();
	$json = json_encode(array($d['shop'], $d['job_kinds'], $d['fit_kinds'], $d['products'], $d['rides'], $d['paces'],
		$d['people'], $d['helpers']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: the workshop is "atölye", a service "bakım",
// a group ride "grup sürüşü", a bike fit keeps "bike fit", a part is
// "yedek parça".
function test_bikeshop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bWorkshop\b/', $key)) pg_assert_true(mb_stripos($tr, 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bService\b/', $key)) pg_assert_true(mb_stripos($tr, 'bakım', 0, 'UTF-8') !== false, "'$key' says bakım");
		if (preg_match('/\bGroup Ride\b/', $key)) pg_assert_true(mb_stripos($tr, 'grup sürüş', 0, 'UTF-8') !== false, "'$key' says grup sürüşü");
		if (preg_match('/\bBike Fit\b/', $key)) pg_assert_true(mb_stripos($tr, 'bike fit', 0, 'UTF-8') !== false, "'$key' says bike fit");
		if (preg_match('/^Parts$/', $key)) pg_assert_true(mb_stripos($tr, 'yedek parça', 0, 'UTF-8') !== false, "'$key' says yedek parça");
	}
}

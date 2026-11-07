<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental design template's parts
 * (includes/design_templates/pinegrap-carrental/): the company's data
 * hangs together (sample cars naming a known class, fuel and gearbox, with
 * the class's rate as their lowest, a sample of every class, desks with
 * hours, extras and deals with their words, testimonials about a known
 * car, the forms' pick lists without an answer twice), a rate is written
 * as the shop writes it, every page link of the shared parts names a page
 * of the finished template, the shared parts carry no class of another
 * template, the footer and the class tiles filter the fleet list in the
 * words its lists use, a car card reads the Fleet form's fields, the
 * location, deal and business cards make their choice in the request
 * forms, the footer's desk links find their card and the sample carries no
 * fixed year.
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
function _test_cr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/parts.php';
	$car_cards = array();
	$titles = array();
	$rates = array();
	foreach (array_keys($cars) as $key) {
		$car_cards[$key] = $car_card($key);
		$titles[$key] = $car_title($key);
		$rates[$key] = $car_rate($key);
	}
	$class_tiles = array();
	$class_rates = array();
	foreach (array_keys($classes) as $key) {
		$class_tiles[$key] = $class_tile($key);
		$class_rates[$key] = $class_rate($key);
	}
	$location_cards = array();
	foreach (array_keys($locations) as $key) $location_cards[$key] = $location_card($key);
	$extra_cards = array();
	$extra_prices = array();
	foreach (array_keys($extras) as $key) {
		$extra_cards[$key] = $extra_card($key);
		$extra_prices[$key] = $extra_price($key);
	}
	$deal_cards = array();
	foreach (array_keys($deals) as $key) $deal_cards[$key] = $deal_card($key);
	$business_cards = array();
	foreach (array_keys($business_plans) as $key) $business_cards[$key] = $business_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'company'           => $company,
		'classes'           => $classes,
		'fuels'             => $fuels,
		'transmissions'     => $transmissions,
		'cars'              => $cars,
		'titles'            => $titles,
		'rates'             => $rates,
		'class_rates'       => $class_rates,
		'money'             => $money,
		'locations'         => $locations,
		'extras'            => $extras,
		'extra_prices'      => $extra_prices,
		'deals'             => $deals,
		'terms'             => $terms,
		'business_plans'    => $business_plans,
		'team'              => $team,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'class_titles'        => $class_titles,
			'fuel_titles'         => $fuel_titles,
			'transmission_titles' => $transmission_titles,
			'car_titles'          => $car_titles,
			'location_titles'     => $location_titles,
			'extra_titles'        => $extra_titles,
			'deal_choices'        => $deal_choices,
			'plan_choices'        => $plan_choices,
			'time_choices'        => $time_choices,
			'age_choices'         => $age_choices,
			'fleet_size_choices'  => $fleet_size_choices,
			'rental_term_choices' => $rental_term_choices,
			'booking_statuses'    => $booking_statuses,
			'enquiry_statuses'    => $enquiry_statuses,
		),
		'contact_choices'   => $contact_choices,
		'car_cards'         => $car_cards,
		'class_tiles'       => $class_tiles,
		'location_cards'    => $location_cards,
		'extra_cards'       => $extra_cards,
		'deal_cards'        => $deal_cards,
		'business_cards'    => $business_cards,
		'testimonial_cards' => $testimonial_cards,
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $header(), $footer(), $booking_band(), $notice(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_cr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cr_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_cr_trees()
{
	$d = _test_cr_load();
	return array_merge($d['shared'], array_values($d['car_cards']), array_values($d['class_tiles']), array_values($d['location_cards']),
		array_values($d['extra_cards']), array_values($d['deal_cards']), array_values($d['business_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_cr_page_keys()
{
	return array(
		'home', 'locations', 'extras', 'deals', 'terms', 'about', 'faq',
		'fleet', 'car', 'car_new',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'business', 'business_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

// The query of a link to a page, or null when it links elsewhere.
function _test_cr_query($href, $page)
{
	$prefix = '{{page:' . $page . '}}?';
	if (strpos($href, $prefix) !== 0) return null;
	parse_str(substr($href, strlen($prefix)), $q);
	return $q;
}

function test_carrental_data_hangs_together()
{
	$d = _test_cr_load();
	foreach (array('tagline', 'bio', 'address') as $f) pg_assert_true(trim($d['company'][$f]) !== '', "company: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['company']['phone']), 'company: phone number');
	pg_assert_true(strpos($d['company']['email'], '@example.com') !== false, 'company: a sample address');
	pg_assert_same(6, count($d['classes']), 'six car classes');
	foreach (array_merge($d['classes'], $d['fuels']) as $key => $c) {
		pg_assert_true(strpos($c['icon'], 'bi-') === 0 && trim($c['title']) !== '', "$key: icon and words");
	}
	foreach ($d['classes'] as $key => $c) {
		foreach (array('rate', 'deposit') as $f) {
			pg_assert_same(array('USD', 'EUR', 'GBP', 'TRY'), array_keys($c[$f]), "class $key: $f per currency");
			foreach ($c[$f] as $amount) pg_assert_true(is_int($amount) && $amount > 0, "class $key: $f is a whole number");
		}
		pg_assert_true($c['deposit']['USD'] > $c['rate']['USD'], "class $key: the deposit is more than a day");
	}
	pg_assert_same(6, count($d['cars']), 'six sample cars');
	$seen = array('class' => array(), 'fuel' => array(), 'gearbox' => array());
	$featured = 0;
	foreach ($d['cars'] as $key => $c) {
		pg_assert_true((bool)preg_match('/^[a-z]+-[a-z]+$/', $key), "car $key: key");
		pg_assert_true(isset($d['classes'][$c['class']]), "car $key: known class");
		pg_assert_true(isset($d['fuels'][$c['fuel']]), "car $key: known fuel");
		pg_assert_true(isset($d['transmissions'][$c['gearbox']]), "car $key: known gearbox");
		foreach (array('seats', 'doors', 'bags', 'min_age') as $f) pg_assert_true(is_int($c[$f]) && $c[$f] > 0, "car $key: $f");
		pg_assert_true($c['seats'] >= 2 && $c['seats'] <= 9, "car $key: seats");
		pg_assert_true($c['min_age'] >= 21 && $c['min_age'] <= 25, "car $key: the minimum age the terms give");
		pg_assert_same($d['classes'][$c['class']]['rate'], $c['rate'], "car $key: its class's lowest rate");
		foreach (array('make', 'model', 'summary', 'description') as $f) pg_assert_true(trim($c[$f]) !== '', "car $key: $f");
		pg_assert_true(count($c['features']) >= 3, "car $key: features");
		foreach (array_keys($seen) as $f) $seen[$f][$c[$f]] = true;
		if ($c['featured']) $featured++;
	}
	pg_assert_same(count($d['classes']), count($seen['class']), 'a sample of every class');
	pg_assert_same(count($d['fuels']), count($seen['fuel']), 'a sample of every fuel');
	pg_assert_same(count($d['transmissions']), count($seen['gearbox']), 'a sample of every gearbox');
	pg_assert_same(3, $featured, 'three featured cars for the home');
	pg_assert_same(count($d['titles']), count(array_unique($d['titles'])), 'no two cars share a title');
	foreach ($d['cars'] as $key => $c) {
		pg_assert_true($c['fuel'] !== 'electric' || $c['class'] === 'electric', "car $key: an electric car is in the Electric class");
	}
	pg_assert_same(3, count($d['locations']), 'three desks');
	foreach ($d['locations'] as $key => $l) {
		foreach (array('icon', 'title', 'kind', 'address', 'text') as $f) pg_assert_true(trim($l[$f]) !== '', "desk $key: $f");
		pg_assert_true(count($l['hours']) >= 1 && count($l['points']) >= 3, "desk $key: hours and points");
		foreach ($l['hours'] as $h) pg_assert_same(2, count($h), "desk $key: days and hours");
	}
	pg_assert_same(6, count($d['extras']), 'six extras');
	foreach ($d['extras'] as $key => $e) {
		pg_assert_true(in_array($e['unit'], array('day', 'rental'), true), "extra $key: a price a day or a rental");
		pg_assert_same(array('USD', 'EUR', 'GBP', 'TRY'), array_keys($e['price']), "extra $key: price per currency");
		pg_assert_true(trim($e['text']) !== '' && strpos($e['icon'], 'bi-') === 0, "extra $key: words and icon");
	}
	pg_assert_same(3, count($d['deals']), 'three deals');
	foreach ($d['deals'] as $key => $deal) {
		pg_assert_true(trim($deal['badge']) !== '' && trim($deal['text']) !== '' && count($deal['points']) >= 3, "deal $key: badge, words and points");
	}
	pg_assert_same(6, count($d['terms']), 'six rental terms');
	pg_assert_same(3, count($d['business_plans']), 'three business plans');
	$most = 0;
	foreach ($d['business_plans'] as $key => $p) {
		pg_assert_true(count($p['points']) >= 3 && trim($p['text']) !== '', "plan $key: words and points");
		if ($p['featured']) $most++;
	}
	pg_assert_same(1, $most, 'one plan marked most chosen');
	pg_assert_same(3, count($d['team']), 'three people');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['cars'][$t['car']]), "testimonial $n: about a sample car");
		pg_assert_true(strpos($t['role'], $d['titles'][$t['car']]) !== false, "testimonial $n: names the car");
	}
}

function test_carrental_pick_lists()
{
	$d = _test_cr_load();
	$l = $d['lists'];
	pg_assert_same(count($d['classes']), count($l['class_titles']), 'an answer per class');
	pg_assert_same(count($d['cars']), count($l['car_titles']), 'an answer per car');
	pg_assert_same(count($d['locations']), count($l['location_titles']), 'an answer per desk');
	pg_assert_same(count($d['extras']), count($l['extra_titles']), 'an answer per extra');
	pg_assert_same(count($d['deals']) + 1, count($l['deal_choices']), 'an answer per deal and one for none');
	pg_assert_same(count($d['business_plans']) + 1, count($l['plan_choices']), 'an answer per plan and one for not sure');
	foreach ($l as $name => $list) pg_assert_same(array_values($list), array_values(array_unique($list)), "$name unique");
	foreach ($l['time_choices'] as $t) pg_assert_true((bool)preg_match('/^([01][0-9]|2[0-3]):00$/', $t), "time $t: on the hour");
	foreach ($d['contact_choices'] as $o) pg_assert_same($o[0], $o[1], "contact choice: the value is the answer's own words");
	pg_assert_same(5, count($l['booking_statuses']), 'five states of a booking');
	pg_assert_same(4, count($l['enquiry_statuses']), 'four states of an enquiry');
}

// A rate is the shop's money text of the base currency's figure.
function test_carrental_rates_follow_the_base_currency()
{
	$d = _test_cr_load();
	$money = $d['money'];
	foreach ($d['cars'] as $key => $c) {
		pg_assert_same(sprintf(lang('From %s a day'), $money($c['rate'])), $d['rates'][$key], "car $key: rate words");
		pg_assert_true(strpos($d['rates'][$key], $money($c['rate'])) !== false, "car $key: the rate names the amount");
	}
	foreach ($d['classes'] as $key => $c) {
		pg_assert_true(strpos($d['class_rates'][$key], $money($c['rate'])) !== false, "class $key: the rate names the amount");
	}
	foreach ($d['extras'] as $key => $e) {
		pg_assert_true(strpos($d['extra_prices'][$key], $money($e['price'])) !== false, "extra $key: the price names the amount");
	}
	pg_assert_same($money(array('USD' => 7, 'EUR' => 6)), $money(array('USD' => 7, 'EUR' => 6, 'XXX' => 1)), 'a currency not listed falls back the same way');
}

function test_carrental_shared_parts_link_template_pages()
{
	$known = array_flip(_test_cr_page_keys());
	$nodes = array();
	foreach (_test_cr_trees() as $tree) _test_cr_nodes($tree, $nodes);
	$links = 0;
	foreach ($nodes as $node) {
		foreach (array('href', 'text') as $prop) {
			if (!isset($node['props'][$prop]) || !is_string($node['props'][$prop])) continue;
			preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $node['props'][$prop], $m);
			foreach ($m[1] as $key) {
				$links++;
				pg_assert_true(isset($known[$key]), "link to a page of the template: $key");
			}
		}
		$class = isset($node['props']['cssClass']) ? (string)$node['props']['cssClass'] : '';
		pg_assert_false((bool)preg_match('/\b(pdl|pht|pgy|plw|psl|pre|pco|pev|pcl|pm|pma|plx|pch|ptv)-[a-z]/', $class), 'no class of another template: ' . $class);
		$id = isset($node['props']['id']) ? (string)$node['props']['id'] : '';
		pg_assert_false((bool)preg_match('/^(dl|ht|gy|lw|re|pd|ma|lx|ch)-/', $id), 'no field id of another template: ' . $id);
		pg_assert_false(isset($node['props']['cssClass']) && (bool)preg_match('/\bopacity-/', $node['props']['cssClass']) && isset($node['props']['text']), 'no faded text: ' . $class);
	}
	pg_assert_true($links > 30, 'the shared parts link the pages');
}

// The footer's fleet links and the class tiles open the fleet list
// filtered on each class, in the words the Class list uses; the footer's
// desk links find a desk card's anchor on the locations page.
function test_carrental_links_filter_the_fleet_list()
{
	$d = _test_cr_load();
	$nodes = array();
	_test_cr_nodes($d['footer'], $nodes);
	foreach ($d['class_tiles'] as $tile) _test_cr_nodes($tile, $nodes);
	$seen = array();
	$desks = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href'])) continue;
		$href = $node['props']['href'];
		if (strpos($href, '{{page:locations}}#') === 0) $desks[] = substr($href, strlen('{{page:locations}}#'));
		$q = _test_cr_query($href, 'fleet');
		if ($q === null) continue;
		pg_assert_true(in_array($q['class'], $d['lists']['class_titles'], true), 'the link names a Class answer');
		$seen[$q['class']] = isset($seen[$q['class']]) ? $seen[$q['class']] + 1 : 1;
	}
	pg_assert_same(count($d['classes']), count($seen), 'a link per class');
	foreach ($seen as $title => $n) pg_assert_same(2, $n, "class $title: a footer link and a tile");
	pg_assert_same(count($d['locations']), count($desks), 'a footer link per desk');
	foreach ($desks as $anchor) {
		pg_assert_true(isset($d['locations'][substr($anchor, strlen('cr-loc-'))]) && strpos($anchor, 'cr-loc-') === 0, "desk anchor $anchor: names a desk");
	}
}

// A car card reads every value from the Fleet form's fields, with the
// sample's own words on the canvas.
function test_carrental_car_card_reads_the_car()
{
	$d = _test_cr_load();
	foreach ($d['car_cards'] as $key => $card) {
		$nodes = array();
		_test_cr_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo_1', 'title', 'form_item_view', 'class', 'rate_label', 'seats', 'bags', 'transmission', 'fuel') as $t) {
			pg_assert_true(isset($tokens[$t]), "car $key: card reads $t");
		}
		$c = $d['cars'][$key];
		pg_assert_true(in_array($d['titles'][$key], $texts, true), "car $key: sample title on the canvas");
		pg_assert_true(in_array($d['rates'][$key], $texts, true), "car $key: sample rate on the canvas");
		pg_assert_true(in_array($d['classes'][$c['class']]['title'], $texts, true), "car $key: sample class on the canvas");
		pg_assert_true(in_array($d['fuels'][$c['fuel']]['title'], $texts, true), "car $key: sample fuel on the canvas");
		pg_assert_true(in_array(lang('or similar'), $texts, true), "car $key: a class is promised, not the model");
	}
}

// The location, deal and business cards open their request form with the
// choice made, in the words its list uses.
function test_carrental_cards_make_the_choice()
{
	$d = _test_cr_load();
	$cases = array(
		array('location_cards', 'book', 'location', 'location_titles'),
		array('deal_cards', 'book', 'deal', 'deal_choices'),
		array('business_cards', 'business_enquiry', 'plan', 'plan_choices'),
	);
	foreach ($cases as $c) {
		list($cards, $page, $param, $list) = $c;
		$chosen = array();
		foreach ($d[$cards] as $key => $card) {
			$nodes = array();
			_test_cr_nodes($card, $nodes);
			$found = 0;
			foreach ($nodes as $node) {
				if (!isset($node['props']['href'])) continue;
				$q = _test_cr_query($node['props']['href'], $page);
				if ($q === null) continue;
				$found++;
				pg_assert_true(in_array($q[$param], $d['lists'][$list], true), "$cards $key: names a $param answer");
				$chosen[$q[$param]] = true;
			}
			pg_assert_same(1, $found, "$cards $key: one link to $page");
		}
		pg_assert_same(count($d[$cards]), count($chosen), "$cards: each makes its own choice");
	}
}

// The sample names no year: a template opened in any year reads as new.
function test_carrental_sample_has_no_fixed_year()
{
	$nodes = array();
	foreach (_test_cr_trees() as $tree) _test_cr_nodes($tree, $nodes);
	$d = _test_cr_load();
	$words = array();
	array_walk_recursive($d['cars'], function ($v) use (&$words) { if (is_string($v)) $words[] = $v; });
	foreach (array('deals', 'locations', 'terms', 'testimonials', 'company') as $k) {
		array_walk_recursive($d[$k], function ($v) use (&$words) { if (is_string($v)) $words[] = $v; });
	}
	foreach ($nodes as $node) {
		if (isset($node['props']['text']) && is_string($node['props']['text'])) $words[] = $node['props']['text'];
	}
	foreach ($words as $w) pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $w), 'no fixed year: ' . $w);
}

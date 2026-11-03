<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Dealership design template's parts
 * (includes/design_templates/pinegrap-dealership/): the dealer's data
 * hangs together (sample vehicles naming a known make, body style, fuel,
 * condition and gearbox, the price label following the price, new cars
 * with no miles, testimonials about a known vehicle, the ways to pay with
 * what they give, the forms' pick lists without an answer twice), every
 * page link of the shared parts names a page of the finished template, the
 * shared parts carry no class of another template, the footer and the body
 * style tiles filter the stock list in the words its lists use, a vehicle
 * card reads the Vehicles form's fields and a finance card opens the
 * finance request with the plan chosen.
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
function _test_dl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	$vehicle_cards = array();
	$titles = array();
	foreach (array_keys($vehicles) as $key) {
		$vehicle_cards[$key] = $vehicle_card($key);
		$titles[$key] = $vehicle_title($key);
	}
	$body_tiles = array();
	foreach (array_keys($bodies) as $key) $body_tiles[$key] = $body_tile($key);
	$finance_cards = array();
	foreach (array_keys($finance_plans) as $key) $finance_cards[$key] = $finance_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'dealer'            => $dealer,
		'makes'             => $makes,
		'bodies'            => $bodies,
		'fuels'             => $fuels,
		'conditions'        => $conditions,
		'transmissions'     => $transmissions,
		'vehicle_statuses'  => $vehicle_statuses,
		'vehicles'          => $vehicles,
		'titles'            => $titles,
		'services'          => $services,
		'finance_plans'     => $finance_plans,
		'team'              => $team,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'make_titles'       => $make_titles,
		'body_titles'       => $body_titles,
		'fuel_titles'       => $fuel_titles,
		'condition_titles'  => $condition_titles,
		'transmission_titles' => $transmission_titles,
		'plan_choices'      => $plan_choices,
		'time_windows'      => $time_windows,
		'contact_choices'   => $contact_choices,
		'tradein_conditions' => $tradein_conditions,
		'history_choices'   => $service_history_choices,
		'deposit_choices'   => $deposit_choices,
		'term_choices'      => $term_choices,
		'income_choices'    => $income_choices,
		'employment_choices' => $employment_choices,
		'test_drive_statuses' => $test_drive_statuses,
		'tradein_statuses'  => $tradein_statuses,
		'finance_statuses'  => $finance_statuses,
		'vehicle_cards'     => $vehicle_cards,
		'body_tiles'        => $body_tiles,
		'finance_cards'     => $finance_cards,
		'testimonial_cards' => $testimonial_cards,
		'shared'            => array($account_bar_tree(), $header(), $footer(), $test_drive_band(), $notice(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_dl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dl_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_dl_page_keys()
{
	return array(
		'home', 'service', 'finance', 'about', 'visit', 'faq',
		'vehicle_new', 'vehicles', 'new_cars', 'used_cars', 'vehicle',
		'test_drive', 'test_drive_view', 'my_test_drives', 'staff_test_drives',
		'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins', 'privacy', 'email_reply',
		'finance_apply', 'finance_view', 'my_finance', 'staff_finance',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_dealership_data_hangs_together()
{
	$d = _test_dl_load();
	foreach (array('since', 'tagline', 'bio', 'address') as $f) pg_assert_true(trim($d['dealer'][$f]) !== '', "dealer: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['dealer']['phone']), 'dealer: phone number');
	pg_assert_true(strpos($d['dealer']['email'], '@example.com') !== false, 'dealer: a sample address');
	pg_assert_same(4, count($d['makes']), 'four makes');
	pg_assert_same(5, count($d['bodies']), 'five body styles');
	foreach (array_merge($d['bodies'], $d['fuels']) as $key => $b) {
		pg_assert_true(strpos($b['icon'], 'bi-') === 0 && trim($b['title']) !== '', "$key: icon and words");
	}
	pg_assert_same(6, count($d['vehicles']), 'six sample vehicles');
	$seen = array('condition' => array(), 'fuel' => array(), 'make' => array());
	$featured = 0;
	foreach ($d['vehicles'] as $key => $v) {
		pg_assert_true((bool)preg_match('/^[a-z]+-[a-z]+$/', $key), "vehicle $key: key");
		pg_assert_true(isset($d['makes'][$v['make']]), "vehicle $key: known make");
		pg_assert_true(isset($d['bodies'][$v['body']]), "vehicle $key: known body style");
		pg_assert_true(isset($d['fuels'][$v['fuel']]), "vehicle $key: known fuel");
		pg_assert_true(isset($d['conditions'][$v['condition']]), "vehicle $key: known condition");
		pg_assert_true(isset($d['transmissions'][$v['gearbox']]), "vehicle $key: known gearbox");
		pg_assert_true(isset($d['vehicle_statuses'][$v['status']]), "vehicle $key: known status");
		pg_assert_true(is_int($v['year']) && $v['year'] >= 2015 && $v['year'] <= 2027, "vehicle $key: a model year");
		pg_assert_true(is_int($v['mileage']) && is_int($v['power']) && $v['power'] > 0, "vehicle $key: numbers");
		if ($v['condition'] === 'new') pg_assert_true($v['mileage'] < 100, "vehicle $key: a new car has delivery miles only");
		else pg_assert_true($v['mileage'] >= 1000, "vehicle $key: a used or demonstrator car has been driven");
		pg_assert_true(is_int($v['price']) && $v['price'] > 0, "vehicle $key: price is a whole number");
		pg_assert_same('$' . number_format($v['price']), $v['price_label'], "vehicle $key: the label says the price");
		foreach (array('model', 'colour', 'summary', 'description') as $f) pg_assert_true(trim($v[$f]) !== '', "vehicle $key: $f");
		pg_assert_true(count($v['features']) >= 3, "vehicle $key: features");
		pg_assert_same(0, strpos($d['titles'][$key], $d['makes'][$v['make']]['title'] . ' '), "vehicle $key: the title starts with the make");
		foreach (array_keys($seen) as $f) $seen[$f][$v[$f]] = true;
		if ($v['featured']) $featured++;
	}
	pg_assert_same(count($d['conditions']), count($seen['condition']), 'a sample of every condition');
	pg_assert_same(count($d['fuels']), count($seen['fuel']), 'a sample of every fuel');
	pg_assert_same(count($d['makes']), count($seen['make']), 'a sample of every make');
	pg_assert_same(3, $featured, 'three featured cars for the home');
	pg_assert_same(count($d['titles']), count(array_unique($d['titles'])), 'no two vehicles share a title');
	pg_assert_same(4, count($d['services']), 'four services');
	pg_assert_same(3, count($d['finance_plans']), 'three ways to pay');
	$most = 0;
	foreach ($d['finance_plans'] as $key => $p) {
		pg_assert_true(count($p['points']) >= 3 && trim($p['text']) !== '', "plan $key: words and points");
		if ($p['featured']) $most++;
	}
	pg_assert_same(1, $most, 'one plan marked most chosen');
	pg_assert_same(3, count($d['team']), 'three people');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['vehicles'][$t['vehicle']]), "testimonial $n: about a sample vehicle");
		$make = $d['makes'][$d['vehicles'][$t['vehicle']]['make']]['title'];
		pg_assert_true(strpos($t['role'], $make) !== false, "testimonial $n: names the make");
	}
	// The pick lists: one answer per make, body, fuel, condition and plan
	// (plus "not sure"), no answer twice.
	pg_assert_same(count($d['makes']), count($d['make_titles']), 'an answer per make');
	pg_assert_same(count($d['bodies']), count($d['body_titles']), 'an answer per body style');
	pg_assert_same(count($d['fuels']), count($d['fuel_titles']), 'an answer per fuel');
	pg_assert_same(count($d['conditions']), count($d['condition_titles']), 'an answer per condition');
	pg_assert_same(count($d['finance_plans']) + 1, count($d['plan_choices']), 'an answer per plan and one for not sure');
	foreach (array('make_titles', 'body_titles', 'fuel_titles', 'condition_titles', 'transmission_titles', 'vehicle_statuses', 'plan_choices',
	               'time_windows', 'history_choices', 'deposit_choices', 'term_choices', 'income_choices', 'employment_choices',
	               'test_drive_statuses', 'tradein_statuses', 'finance_statuses') as $list) {
		pg_assert_same(array_values($d[$list]), array_values(array_unique($d[$list])), "$list unique");
	}
	foreach (array('contact_choices', 'tradein_conditions') as $list) {
		foreach ($d[$list] as $o) pg_assert_same($o[0], $o[1], "$list: the value is the answer's own words");
	}
	pg_assert_same(4, count($d['test_drive_statuses']), 'four states of a test drive');
	pg_assert_same(5, count($d['tradein_statuses']), 'five states of a trade-in');
	pg_assert_same(5, count($d['finance_statuses']), 'five states of a finance request');
}

function test_dealership_shared_parts_link_template_pages()
{
	$d = _test_dl_load();
	$known = array_flip(_test_dl_page_keys());
	$nodes = array();
	foreach (array_merge($d['shared'], array_values($d['vehicle_cards']), array_values($d['body_tiles']),
	                     array_values($d['finance_cards']), $d['testimonial_cards']) as $tree) _test_dl_nodes($tree, $nodes);
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
		pg_assert_false((bool)preg_match('/\b(plw|psl|pre|pco|pev|pcl|pf|pdp|pm|pma|plx)-[a-z]/', $class), 'no class of another template: ' . $class);
		$id = isset($node['props']['id']) ? (string)$node['props']['id'] : '';
		pg_assert_false((bool)preg_match('/^(lw|re|pd|ma|lx)-/', $id), 'no field id of another template: ' . $id);
	}
	pg_assert_true($links > 20, 'the shared parts link the pages');
}

// The footer's car links and the body style tiles open the stock list
// filtered on each body style and fuel, in the words the Body and Fuel
// lists use.
function test_dealership_links_filter_the_stock_list()
{
	$d = _test_dl_load();
	$nodes = array();
	_test_dl_nodes($d['shared'][2], $nodes);
	foreach ($d['body_tiles'] as $tile) _test_dl_nodes($tile, $nodes);
	$seen = array('body' => array(), 'fuel' => array());
	$prefix = '{{page:vehicles}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		if (isset($q['body'])) {
			pg_assert_true(in_array($q['body'], $d['body_titles'], true), 'the link names a Body answer');
			$seen['body'][$q['body']] = isset($seen['body'][$q['body']]) ? $seen['body'][$q['body']] + 1 : 1;
		} else {
			pg_assert_true(in_array($q['fuel'], $d['fuel_titles'], true), 'the link names a Fuel answer');
			$seen['fuel'][$q['fuel']] = true;
		}
	}
	pg_assert_same(count($d['bodies']), count($seen['body']), 'a link per body style');
	foreach ($seen['body'] as $title => $n) pg_assert_same(2, $n, "body style $title: a footer link and a tile");
	pg_assert_same(count($d['fuels']), count($seen['fuel']), 'a link per fuel');
}

// A vehicle card reads every value from the Vehicles form's fields, with
// the sample's own words on the canvas.
function test_dealership_vehicle_card_reads_the_vehicle()
{
	$d = _test_dl_load();
	foreach ($d['vehicle_cards'] as $key => $card) {
		$nodes = array();
		_test_dl_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo_1', 'title', 'form_item_view', 'condition', 'status', 'price_label', 'make', 'body',
		               'year', 'mileage', 'fuel', 'transmission') as $t) {
			pg_assert_true(isset($tokens[$t]), "vehicle $key: card reads $t");
		}
		$v = $d['vehicles'][$key];
		pg_assert_true(in_array($d['titles'][$key], $texts, true), "vehicle $key: sample title on the canvas");
		pg_assert_true(in_array($v['price_label'], $texts, true), "vehicle $key: sample price on the canvas");
		pg_assert_true(in_array($d['conditions'][$v['condition']], $texts, true), "vehicle $key: sample condition on the canvas");
		pg_assert_true(in_array($d['fuels'][$v['fuel']]['title'], $texts, true), "vehicle $key: sample fuel on the canvas");
	}
}

// A finance card opens the finance request with that plan chosen, in the
// words the Way to Pay list uses (the form starts from ?plan=).
function test_dealership_finance_card_chooses_the_plan()
{
	$d = _test_dl_load();
	foreach ($d['finance_cards'] as $key => $card) {
		$nodes = array();
		_test_dl_nodes($card, $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:finance_apply}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "plan $key: one finance link");
		parse_str(substr($hrefs[0], strlen('{{page:finance_apply}}?')), $q);
		pg_assert_true(in_array($q['plan'], $d['plan_choices'], true), "plan $key: the link names a Way to Pay answer");
	}
}

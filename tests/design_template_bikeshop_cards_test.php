<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bike Shop design template's shared parts and cards
 * (includes/design_templates/pinegrap-bikeshop/cards.php): every page link
 * names a page of the finished template, the shared parts never link a
 * shop page, the footer, the job cards, the bike kind and pace pills and
 * the fit cards open their list or form in the list's own words, the
 * record cards read their form's fields, the classes are the template's
 * own, the sample carries no fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_bikeshop_test.php');

// The parts as the template file reads them: parts.php, then cards.php in
// the same scope. Read once per run.
function _test_bikeshopc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$cards = array('job' => array(), 'fit' => array(), 'ride' => array(), 'person' => array());
	foreach (array_keys($job_kinds) as $key) $cards['job'][$key] = $job_card($key);
	foreach (array_keys($fit_kinds) as $key) $cards['fit'][$key] = $fit_card($key, $key === 'full');
	foreach (array_keys($rides) as $key) $cards['ride'][$key] = $ride_card($key);
	foreach (array_keys($people) as $n) $cards['person'][] = $person_card($n);
	$out = array(
		'rides'    => $rides,
		'jobs'     => $job_kinds,
		'fits'     => $fit_kinds,
		'words'    => array('job_titles' => $job_titles, 'job_choices' => $job_choices, 'bike_titles' => $bike_titles,
			'fit_titles' => $fit_titles, 'pace_titles' => $pace_titles),
		'cards'    => $cards,
		'facts'    => array('service' => $request_facts('service'), 'fit' => $request_facts('fit')),
		'header'   => $header(),
		'footer'   => $footer(),
		'pills'    => array('bike' => $bike_kind_pills(), 'pace' => $pace_pills()),
		'shared'   => array(
			'account_bar' => $account_bar_tree(), 'notice' => $notice(), 'staff_bar' => $staff_bar(),
		),
		'parts'    => array($post_card(), $ai_summary(), $hours_list(), $status_badge(lang('In the Workshop')),
			$staff_page(array($para('x'))),
			$comment_thread(array('title' => lang('Messages'), 'empty' => lang('No messages yet.'), 'heading' => lang('Send a Message'),
				'label' => lang('Your Message'), 'sign_in' => lang('Sign in to send a message.'), 'submit' => lang('Send'),
				'watch_box' => lang('E-mail me when a new message arrives'), 'follow' => lang('Follow'), 'unfollow' => lang('Unfollow'),
				'close' => lang('Close'), 'reopen' => lang('Reopen'), 'reply' => true, 'watch' => true)),
			$newsletter_line('bikeshop-newsletter-email'), $newsletter_consent('bikeshop-newsletter-consent'),
			$field('bikeshop-x', lang('Name'), $input('bikeshop-x', 'text', 'name')), $select('bikeshop-y', 'job', lang('Choose'), $job_choices),
			$radios('bikeshop-z', 'contact', lang('Contact'), $contact_choices, $contact_choices[0][0]), $file('bikeshop-f', 'file', 'private')),
	);
	return $out;
}

// Every tree the parts and cards make.
function _test_bikeshopc_trees()
{
	$d = _test_bikeshopc_load();
	$trees = array_merge(array($d['header'], $d['footer']), array_values($d['pills']), array_values($d['facts']), array_values($d['shared']), $d['parts']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The links of a tree that open $page with a query, as name => values.
function _test_bikeshopc_query_links($tree, $page)
{
	$nodes = array();
	_test_bikeshop_nodes($tree, $nodes);
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_bikeshop_card_links_name_template_pages()
{
	$known = array_flip(_test_bikeshop_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_bikeshopc_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Bikes, parts and accessories are the shop's only with the shop on: no
// shared part or card links a shop page, so every part stands on a site
// without it (the account bar's cart leaves by itself). The menu marks the
// current page itself.
function test_bikeshop_shared_parts_never_link_the_shop()
{
	$json = json_encode(_test_bikeshopc_trees());
	foreach (_test_bikeshop_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no part links to $key");
	$d = _test_bikeshopc_load();
	pg_assert_contains('"cart_link"', json_encode($d['shared']['account_bar']), 'the account bar carries the cart');
	pg_assert_contains('"templateShared":"notice"', json_encode($d['shared']['account_bar']), 'the account bar places the notice');
	pg_assert_contains('{{page:workshop}}', json_encode($d['shared']['notice']), 'the notice opens the workshop');
	pg_assert_contains('{{page:service_request}}', json_encode($d['header']), 'the header asks for a service');
	foreach (array('staff_services', 'staff_fits', 'ride_new', 'blog_new', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', json_encode($d['shared']['staff_bar']), "the staff bar opens $key");
	}
	$nodes = array();
	_test_bikeshop_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the service request on each job and the rides on each
// pace, in the words the lists use; a job card and a fit card open their
// request with an answer of its list; the pills do the same for the bike
// kinds and the paces.
function test_bikeshop_links_filter_in_the_lists_words()
{
	$d = _test_bikeshopc_load();
	$w = $d['words'];
	pg_assert_same($w['job_titles'], _test_bikeshopc_query_links($d['footer'], 'service_request')['job'], 'a footer link per job');
	pg_assert_same($w['pace_titles'], _test_bikeshopc_query_links($d['footer'], 'rides')['pace'], 'a footer link per pace');
	foreach ($d['cards']['job'] as $key => $card) {
		$q = _test_bikeshopc_query_links($card, 'service_request');
		pg_assert_same(array($d['jobs'][$key]['title']), $q['job'], "job $key: the card opens the request with its job");
		pg_assert_true(in_array($q['job'][0], $w['job_choices'], true), "job $key: an answer of the list");
	}
	foreach ($d['cards']['fit'] as $key => $card) {
		$q = _test_bikeshopc_query_links($card, 'fit_request');
		pg_assert_same(array($d['fits'][$key]['title']), $q['fit'], "fit $key: the card opens the request with its package");
	}
	pg_assert_same($w['bike_titles'], _test_bikeshopc_query_links($d['pills']['bike'], 'service_request')['bike'], 'a button per bike kind');
	pg_assert_same($w['pace_titles'], _test_bikeshopc_query_links($d['pills']['pace'], 'rides')['pace'], 'a button per pace');
	// One fit package is set apart.
	$featured = 0;
	foreach ($d['cards']['fit'] as $card) if (strpos(json_encode($card), 'border-primary border-2') !== false) $featured++;
	pg_assert_same(1, $featured, 'one fit package is set apart');
}

// The values a card reads from its form's fields, and its texts.
function _test_bikeshopc_card_reads($card)
{
	$nodes = array();
	_test_bikeshop_nodes($card, $nodes);
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

// A ride card reads every value from the Group Rides form's fields, with
// the sample's own words on the canvas; the request facts read every fact
// field of their form.
function test_bikeshop_cards_read_their_records()
{
	$d = _test_bikeshopc_load();
	foreach ($d['cards']['ride'] as $key => $card) {
		list($tokens, $texts) = _test_bikeshopc_card_reads($card);
		foreach (array('title', 'form_item_view', 'ride_date', 'pace', 'drop_rule', 'start_time', 'meeting_point', 'distance', 'climbing', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "ride $key: card reads $t");
		}
		pg_assert_true(in_array($d['rides'][$key]['title'], $texts, true), "ride $key: sample title on the canvas");
	}
	$reads = array(
		'service' => array('job', 'bike_kind', 'make_model', 'dropoff_date'),
		'fit'     => array('fit', 'height', 'inseam', 'riding_style', 'preferred_date'),
	);
	foreach ($reads as $kind => $fields) {
		list($tokens) = _test_bikeshopc_card_reads($d['facts'][$kind]);
		foreach ($fields as $t) pg_assert_true(isset($tokens[$t]), "$kind request: facts read $t");
	}
}

// Classes the template adds of its own start with pbs-; another template's
// prefix copied in would stay unstyled. The block family's pg-bike- rules
// are shared. Field and section ids start with bikeshop-; no text is
// faded; no eyebrow line.
function test_bikeshop_card_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_bikeshopc_trees() as $t) _test_bikeshop_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pzo|psk|prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pbk|pbw|pcn|pcp|pcr|pcw|pcy|pdl|pdp|pdr|pfl|pfm|pfy|pgy|phs|pht|pma|pmu|ppd|prc|psh|psp|pth|ptv|pwd|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false((bool)preg_match('/-eyebrow$/', $c), "no eyebrow line ($c)");
			pg_assert_false((bool)preg_match('/^pg-(?!bike-|comment-|field-value$|hide-if-empty$)[a-z]+-/', $c), "only the bike block family's rules ($c)");
			if (strpos($c, 'pbs-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bikeshop-') === 0, "id $id is the template's");
	}
	foreach (array('pbs-brand', 'pbs-display', 'pbs-price', 'pbs-ride') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// The parts are never dated. A ride card's date block and the request
// facts show the sample's date counted from today; everything else has no
// year or month.
function test_bikeshop_cards_no_fixed_dates()
{
	$d = _test_bikeshopc_load();
	$dated = array_merge(array_values($d['cards']['ride']), array_values($d['facts']));
	$plain = array();
	foreach (_test_bikeshopc_trees() as $t) {
		if (in_array($t, $dated, true)) continue;
		$plain[] = $t;
	}
	// The comment stars' SVG names its namespace (www.w3.org/2000/svg),
	// which is no date; links are read decoded.
	$json = rawurldecode(str_replace('http://www.w3.org/2000/svg', '', json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the parts');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the parts');
	foreach ($d['cards']['ride'] as $key => $card) {
		pg_assert_true(strpos(json_encode($card), '"ride_date"') !== false, "ride $key: the date block reads the record's date");
	}
}

// Every literal of the cards has its Turkish, and the Turkish names the
// template's things one way: the workshop is "atölye", a service "bakım",
// a group ride "grup sürüşü", a bike fit keeps "bike fit".
function test_bikeshop_cards_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/cards.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bWorkshop\b/i', $key)) pg_assert_true(mb_stripos($tr, 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bService\b/', $key)) pg_assert_true(mb_stripos($tr, 'bakım', 0, 'UTF-8') !== false, "'$key' says bakım");
		if (preg_match('/\bGroup Rides?\b/', $key)) pg_assert_true(mb_stripos($tr, 'grup sürüş', 0, 'UTF-8') !== false, "'$key' says grup sürüşü");
		if (preg_match('/\bBike Fit\b/', $key)) pg_assert_true(mb_stripos($tr, 'bike fit', 0, 'UTF-8') !== false, "'$key' says bike fit");
	}
}

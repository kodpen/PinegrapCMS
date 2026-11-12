<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Public Library design template
 * (includes/design_templates/pinegrap-library.php): it is offered as a
 * template that opens without the shop and has no catalog, carries every
 * part's pages in order and each widget once, only the address book needs
 * the shop, every placed widget, shared part and page placeholder names
 * something the template defines, the contact page's parts are search
 * sections, the plb- classes equal their rules, the comment threads run on
 * the Comments widget with the reply e-mail's card, the sample titles,
 * events and branches survive the assembly, the Google reviews sit
 * under the sample testimonials, and version 2.0.0 draws the home, the
 * visit page, the Central Library's page and the catalogue on the
 * Library blocks and the general hours and floor blocks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/template_threads.php');

function _test_lb_asm_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_lb_asm_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_lb_asm_tpl_nodes($child, $out);
	}
}

function test_library_assembly_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-library']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['catalogue']) && !isset($all['membership']), 'the parts folder is not read as a template');
	$tpl = _test_lb_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '1.0.0', '>='), 'a version');
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-book', $tpl['icon']);
	pg_assert_same(103, $tpl['order']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_true(!isset($tpl['catalog']), 'no catalog: the library takes no payment online');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_same('elegant', $tpl['look']);
	pg_assert_same('forest', $tpl['palette'], 'the palette the e-mails\' green is made for');
	pg_assert_same(array('newsletter'), array_keys($tpl['contact_groups']), 'the newsletter is the only contact group');
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter takes subscriptions');
}

function test_library_assembly_template_pages_and_widgets()
{
	$tpl = _test_lb_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/catalogue.php',
		include $dir . '/events.php',
		include $dir . '/membership.php',
		include $dir . '/rooms.php',
		include $dir . '/standard.php',
		include $dir . '/account.php',
	);
	$expected = array();
	$widget_count = 0;
	foreach ($parts as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'join_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));
	// Only the address book needs the shop.
	foreach ($tpl['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book', isset($p['requires']), $p['key'] . ': needs the shop only if it is the address book');
	}

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_lb_asm_tpl_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) pg_assert_true(isset($tpl['shared'][$n['props']['templateShared']]), 'shared part ' . $n['props']['templateShared']);
		}
	}
	foreach (array_keys($placed) as $key) pg_assert_true(isset($tpl['widgets'][$key]), 'placed widget ' . $key . ' is defined');
	foreach (array_keys($tpl['widgets']) as $key) pg_assert_true(isset($placed[$key]), 'widget ' . $key . ' is placed');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(in_array($key, $keys, true), 'placeholder names page ' . $key);
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['folders'][$key]), 'placeholder names folder ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_library_assembly_template_search_and_look()
{
	$tpl = _test_lb_asm_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_lb_asm_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(plb-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	$classes = '';
	foreach (array($tpl['pages'], $tpl['widgets'], $tpl['shared']) as $set) {
		foreach ($set as $item) {
			$nodes = array();
			_test_lb_asm_tpl_nodes($item['tree'], $nodes);
			foreach ($nodes as $n) if (isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) $classes .= ' ' . $n['props']['cssClass'];
		}
	}
	preg_match_all('/\bplb-[a-z0-9-]+/', $classes, $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'plb- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}

function _test_lb_asm_threads()
{
	$request = array('reply' => false, 'watch' => false,
		'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0));
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'booking_messages' => array('page' => 'booking_view', 'view' => 'booking_detail') + $request,
		'membership_messages' => array('page' => 'membership_view', 'view' => 'membership_detail') + $request,
		'reservation_messages' => array('page' => 'reservation_view', 'view' => 'reservation_detail') + $request,
		'room_booking_messages' => array('page' => 'room_booking_view', 'view' => 'room_booking_detail') + $request,
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation') + $request,
	);
}

// No fixed comments block; each thread keeps its page's settings, is drawn
// for a member, a visitor and the staff, and a request's messages are as
// private as the request.
function test_library_assembly_comment_threads()
{
	$tpl = _tpl_threads_tpl('pinegrap-library');
	_tpl_threads_check_no_fixed_block('pinegrap-library', array(
		'booking_messages' => 'comments', 'membership_messages' => 'comments', 'reservation_messages' => 'comments',
		'room_booking_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments',
		'post_comments' => 'comments',
	));
	$labels = array('blog_post' => lang('Comment'), 'booking_view' => lang('Message'), 'membership_view' => lang('Message'),
		'reservation_view' => lang('Message'), 'room_booking_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
	foreach (_test_lb_asm_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-library', $key, $t);
		_tpl_threads_check_draws('pinegrap-library', $key, $t);
	}
	foreach (array('booking_detail', 'membership_detail', 'reservation_detail', 'room_booking_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the member\'s own');
	}
	_tpl_threads_check_email_card('pinegrap-library', 'email_comment', 'email_reply',
		array('blog_post', 'booking_view', 'membership_view', 'reservation_view', 'room_booking_view', 'conversation'));
}

// The samples are parts.php's $catalogue_items, $events and $branches; the
// assembly keeps every part's return under another name, so the
// collections still seed every title and event and every branch has its
// page.
function test_library_assembly_samples()
{
	$tpl = _test_lb_asm_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	pg_assert_true(count($catalogue_items) >= 6 && count($events) >= 4, 'sample records in parts.php');
	pg_assert_same(3, count($branches), 'three branches in parts.php');
	$json = json_encode(array($tpl['pages'], $tpl['widgets']), JSON_UNESCAPED_UNICODE);
	foreach ($catalogue_items as $key => $t) {
		pg_assert_contains(json_encode($t['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach ($events as $key => $e) {
		pg_assert_contains(json_encode($e['summary'], JSON_UNESCAPED_UNICODE), $json, $key . ': its summary is in the template');
	}
	foreach (array_keys($branches) as $key) {
		pg_assert_contains('"key":"branch_' . $key . '"', $json, $key . ': its branch page');
	}
}

// The library's Google reviews sit under the sample testimonials on the home
// page, with the layout Google asks for.
function test_library_assembly_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-library', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('pinegrap-library');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_true(strpos($home, '"templateWidget":"google_reviews"') !== false, 'the home places the Google reviews');
	pg_assert_true(strpos($home, '"templateWidget":"home_arrivals"') !== false && strpos($home, '"templateWidget":"home_events"') !== false && strpos($home, '"templateWidget":"home_posts"') !== false, 'the home places the new arrivals, the events and the news');
}

// Every node of a tree whose class list holds the class.
function _test_lb_asm_with_class($tree, $class)
{
	$nodes = array();
	_test_lb_asm_tpl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '')) $out[] = $n;
	}
	return $out;
}

// Every node of a tree with the tag.
function _test_lb_asm_with_tag($tree, $tag)
{
	$nodes = array();
	_test_lb_asm_tpl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === $tag) $out[] = $n;
	return $out;
}

// The words of a tree, joined.
function _test_lb_asm_words($tree)
{
	$nodes = array();
	_test_lb_asm_tpl_nodes($tree, $nodes);
	$words = array();
	foreach ($nodes as $n) if (isset($n['props']['text']) && is_string($n['props']['text'])) $words[] = $n['props']['text'];
	return implode(' | ', $words);
}

// The node's attribute, or null.
function _test_lb_asm_attr($node, $name)
{
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) if ($a['name'] === $name) return $a['value'];
	return null;
}

// The section of a page with the label, or null.
function _test_lb_asm_section($tree, $label)
{
	foreach (_test_lb_asm_with_tag($tree, 'section') as $s) if (isset($s['props']['_label']) && $s['props']['_label'] === $label) return $s;
	return null;
}

// Version 2.0.0 draws the home on the Shelf Hero and the Card and Loans,
// the visit page on Hours: Seasons (a column per branch), the Central
// Library's page on the Floor Directory and the catalogue on the Shelf
// Mark Guide. Every block class they place ships its rule in the
// template's User Styles, every interaction is kept as written by
// pg_fx_normalize(), each page has one h1, and the blocks read the
// library's own data: the formats, the weekday hours, what a card lends,
// every branch's hours day by day, the floors and a sample title's mark.
function test_library_assembly_pages_on_library_blocks()
{
	$tpl = _test_lb_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	// Small coloured text on the dark tertiary bands and the hero's tinted
	// corner: Forest's text colour gives 4.1:1 there, the emphasis tint 4.7:1.
	pg_assert_contains('[data-bs-theme=dark] :is(.bg-body-tertiary,.plb-hero) :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}', $css, 'primary text on a dark band');
	pg_assert_contains('[data-bs-theme=dark] :is(.bg-body-tertiary,.plb-hero) .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}', $css, 'outline buttons on a dark band');
	pg_assert_contains('[data-bs-theme=dark] .bg-body-tertiary .link-secondary{color:var(--bs-secondary-color)!important}', $css, 'secondary links on a dark band');
	pg_assert_contains('[data-bs-theme=dark] .bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--bs-body-color)}', $css, 'secondary buttons on a dark band');
	// A long site name shrinks in the header instead of widening the page.
	pg_assert_contains('.navbar-brand{min-width:0}', $css, 'the brand may shrink');
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/parts.php';
	pg_assert_contains('plb-brand fs-4 text-truncate', json_encode($header()), 'the name ends with an ellipsis');
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[$p['key']] = $p['tree'];
	$wanted = array(
		'home'           => array('pg-library-shelf', 'pg-library-spine', 'pg-library-mid', 'pg-library-low', 'pg-library-title', 'pg-library-figure',
			'pg-library-card', 'pg-library-barcode'),
		'visit'          => array('pg-hours-table'),
		'branch_central' => array('pg-floor-level'),
		'catalogue'      => array('pg-library-label', 'pg-library-figure'),
	);
	$blocks = array(
		'home'           => array('Library Hero', 'Library Card and Loans'),
		'visit'          => array('Hours (Seasons)'),
		'branch_central' => array('Locations (Floor Directory)'),
		'catalogue'      => array('Library Shelf Mark Guide'),
	);
	$search = array(
		'home'           => array('lib-home-arrivals', 'lib-home-card', 'lib-home-branches'),
		'visit'          => array('lib-hours', 'lib-branches', 'lib-getting-here'),
		'branch_central' => array('lib-branch-central', 'lib-floors-central'),
		'catalogue'      => array('lib-catalogue', 'lib-shelf-marks'),
	);
	foreach ($wanted as $key => $classes) {
		$nodes = array();
		_test_lb_asm_tpl_nodes($trees[$key], $nodes);
		$class_list = '';
		$labels = array();
		$ids = array();
		$effects = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$class_list .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-(?:library|hours|floor)(?:-[a-z0-9]+)*(?![\w-])/', $class_list, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($classes as $class) {
			pg_assert_true((bool)preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', $class_list), $key . ': places ' . $class);
		}
		foreach ($blocks[$key] as $label) pg_assert_true(in_array($label, $labels, true), $key . ': the ' . $label . ' block');
		foreach ($search[$key] as $id) pg_assert_true(in_array($id, $ids, true), $key . ': the search section ' . $id);
		pg_assert_same(1, $h1, $key . ': one h1');
		pg_assert_true($effects >= 3, $key . ': scrolls in motion');
	}

	// The hero holds the home's h1; eleven spines on a shelf a screen
	// reader skips, set down on load; the Central Library's weekday hours
	// as two times; a link per format, each opening the catalogue with
	// its filter set.
	$hero = _test_lb_asm_section($trees['home'], 'Library Hero');
	pg_assert_same(1, count(_test_lb_asm_with_tag($hero, 'h1')), 'the hero holds the h1');
	$shelf = _test_lb_asm_with_class($hero, 'pg-library-shelf');
	pg_assert_same(1, count($shelf), 'one shelf');
	pg_assert_same('true', _test_lb_asm_attr($shelf[0], 'aria-hidden'), 'the shelf is hidden from screen readers');
	pg_assert_same(11, count(_test_lb_asm_with_class($shelf[0], 'pg-library-spine')), 'eleven spines');
	pg_assert_same('load', $shelf[0]['props']['_fx']['trigger'], 'the books are set down on load');
	$times = _test_lb_asm_with_tag($hero, 'time');
	pg_assert_same(array('09:00', '20:00'), array(_test_lb_asm_attr($times[0], 'datetime'), _test_lb_asm_attr($times[1], 'datetime')), 'the weekday hours as times');
	pg_assert_same('9:00 - 20:00', $branches['central']['hours'][0]['hours'], 'the Central Library\'s weekday hours');
	pg_assert_contains($branches['central']['hours'][0]['days'], _test_lb_asm_words($hero), 'the days of those hours');
	$nodes = array();
	_test_lb_asm_tpl_nodes($hero, $nodes);
	$prefix = '{{page:catalogue}}?';
	$seen = array();
	foreach ($nodes as $n) {
		$href = isset($n['props']['href']) ? (string)$n['props']['href'] : '';
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$seen[] = $q['format'];
	}
	pg_assert_same($format_titles, $seen, 'a link per format, in their order');

	// The card band: the card a screen reader skips, with its barcode; a
	// row per loan rule with its heading cell; the table in a named,
	// positioned scroll box.
	$band = _test_lb_asm_section($trees['home'], 'Library Card and Loans');
	$card = _test_lb_asm_with_class($band, 'pg-library-card');
	pg_assert_same('true', _test_lb_asm_attr($card[0], 'aria-hidden'), 'the card is hidden from screen readers');
	pg_assert_same(1, count(_test_lb_asm_with_class($card[0], 'pg-library-barcode')), 'the card\'s barcode');
	pg_assert_same(1, count(_test_lb_asm_with_tag($band, 'caption')), 'the table\'s caption');
	$heads = _test_lb_asm_with_tag(_test_lb_asm_with_tag($band, 'thead')[0], 'th');
	pg_assert_same(4, count($heads), 'four columns');
	foreach ($heads as $th) pg_assert_same('col', _test_lb_asm_attr($th, 'scope'), 'a column heading');
	$rows = _test_lb_asm_with_tag(_test_lb_asm_with_tag($band, 'tbody')[0], 'tr');
	pg_assert_same(count($loan_rules), count($rows), 'a row per loan rule');
	foreach ($loan_rules as $i => $r) {
		$words = _test_lb_asm_words($rows[$i]);
		foreach (array('format', 'count', 'loan', 'renew') as $f) pg_assert_contains($r[$f], $words, $r['format'] . ': ' . $f);
		pg_assert_same('row', _test_lb_asm_attr(_test_lb_asm_with_tag($rows[$i], 'th')[0], 'scope'), $r['format'] . ': a row heading');
	}
	$box = _test_lb_asm_with_class($band, 'table-responsive');
	pg_assert_contains('position-relative', $box[0]['props']['cssClass'], 'the scroll box holds its hidden caption');
	pg_assert_same('region', _test_lb_asm_attr($box[0], 'role'), 'the scroll box is a named region');

	// The week: every branch's hours rows cover each day once; a row per
	// day, a column per branch, each cell that branch's hours that day or
	// the word Closed.
	$week = _test_lb_asm_section($trees['visit'], 'Hours (Seasons)');
	pg_assert_same(1 + count($branches), count(_test_lb_asm_with_tag(_test_lb_asm_with_tag($week, 'thead')[0], 'th')), 'a column per branch');
	$days = _test_lb_asm_with_tag(_test_lb_asm_with_tag($week, 'tbody')[0], 'tr');
	pg_assert_same(7, count($days), 'a row per day');
	foreach ($branches as $key => $b) {
		$on = array();
		foreach ($b['hours'] as $h) $on = array_merge($on, $h['on']);
		sort($on);
		pg_assert_same(range(0, 6), $on, $key . ': its hours cover each day once');
	}
	foreach ($week_days as $n => $day) {
		$cells = $days[$n]['children'];
		pg_assert_same($day, _test_lb_asm_words($cells[0]), 'day ' . $n);
		$c = 1;
		foreach ($branches as $key => $b) {
			$expected = lang('Closed');
			foreach ($b['hours'] as $h) {
				if (in_array($n, $h['on'], true) && $h['hours'] !== lang('Closed')) $expected = str_replace(' - ', ' – ', $h['hours']);
			}
			pg_assert_same($expected, _test_lb_asm_words($cells[$c]), $day . ' at ' . $key);
			$c++;
		}
	}
	$box = _test_lb_asm_with_class($week, 'table-responsive');
	pg_assert_contains('position-relative', $box[0]['props']['cssClass'], 'the scroll box holds its hidden heading');

	// The floors: a level per floor from the top down; a room named on a
	// floor is one of the branch's own; only a branch with floors has the
	// directory.
	$floor_dir = _test_lb_asm_section($trees['branch_central'], 'Locations (Floor Directory)');
	$levels = _test_lb_asm_with_class($floor_dir, 'pg-floor-level');
	pg_assert_same(count($floors['central']), count($levels), 'a level per floor');
	$last = PHP_INT_MAX;
	foreach ($levels as $l) {
		pg_assert_true((int)$l['props']['text'] < $last, 'the floors from the top down');
		$last = (int)$l['props']['text'];
	}
	$own = array();
	$all = array();
	foreach ($rooms as $r) {
		$all[] = $r['title'];
		if ($r['branch'] === 'central') $own[] = $r['title'];
	}
	$named = 0;
	foreach ($floors['central'] as $f) {
		foreach ($f['items'] as $item) {
			if (!in_array($item, $all, true)) continue;
			pg_assert_true(in_array($item, $own, true), $item . ' is a room of the Central Library');
			$named++;
		}
	}
	pg_assert_true($named >= 2, 'the floors name the branch\'s rooms');
	foreach (array_keys($branches) as $key) {
		if (isset($floors[$key])) continue;
		pg_assert_same(null, _test_lb_asm_section($trees['branch_' . $key], 'Locations (Floor Directory)'), $key . ': no floor directory');
	}

	// The shelf marks: a sample title's spine a screen reader skips, its
	// mark read part by part, the fiction mark and the separate shelves
	// the sample titles' own; ten subjects, the example's marked once.
	$guide = _test_lb_asm_section($trees['catalogue'], 'Library Shelf Mark Guide');
	$spine = _test_lb_asm_with_class($guide, 'pg-library-label');
	pg_assert_same('true', _test_lb_asm_attr($spine[0], 'aria-hidden'), 'the spine is hidden from screen readers');
	$sample = $catalogue_items['garden'];
	$words = _test_lb_asm_words($spine[0]);
	pg_assert_contains($sample['title'], $words, 'the spine names the sample title');
	foreach (explode(' ', $sample['shelf']) as $part) pg_assert_contains($part, $words, 'the spine shows ' . $part);
	$terms = _test_lb_asm_words(_test_lb_asm_with_tag($guide, 'dl')[0]);
	foreach (array('harbour', 'stars', 'river', 'quiet') as $key) {
		$mark = explode(' ', $catalogue_items[$key]['shelf']);
		pg_assert_contains($mark[0], $terms, $key . ': its shelf is read');
	}
	$subjects = _test_lb_asm_with_tag($guide, 'ol');
	pg_assert_same(10, count($subjects[0]['children']), 'ten subjects');
	$examples = array();
	foreach ($subjects[0]['children'] as $li) {
		if (strpos(_test_lb_asm_words($li), lang('Example')) !== false) $examples[] = $li['props']['_label'];
	}
	pg_assert_same(array(substr($sample['shelf'], 0, 1) . '00'), $examples, 'the sample\'s subject is the example');
}

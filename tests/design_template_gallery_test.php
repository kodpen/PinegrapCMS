<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's data and helpers
 * (includes/design_templates/pinegrap-gallery/parts.php): the gallery's
 * data hangs together (every work by a known artist in a known exhibition,
 * every artist with a work, every exhibition with its artists and a work
 * when it is on, prices per currency), a price is written as the shop
 * writes it, the forms' pick lists hold no answer twice and match the
 * data, the helpers use the template's own classes and ids, the sample
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

// The data and helpers as the template file reads them. Read once per run.
function _test_gl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/parts.php';
	$out = array(
		'gallery'             => $gallery,
		'opening_hours'       => $opening_hours,
		'mediums'             => $mediums,
		'artists'             => $artists,
		'exhibition_statuses' => $exhibition_statuses,
		'exhibitions'         => $exhibitions,
		'availability'        => $availability,
		'works'               => $works,
		'advisory'            => $advisory,
		'topics'              => $topics,
		'money'               => $money,
		'work_price'          => $work_price,
		'buying_steps'        => $buying_steps,
		'figures'             => $figures,
		'testimonials'        => $testimonials,
		'query'               => $query,
		'lists'               => array(
			'artist_names'        => $artist_names,
			'medium_titles'       => $medium_titles,
			'work_titles'         => $work_titles,
			'work_choices'        => $work_choices,
			'enquiry_choices'     => $enquiry_choices,
			'exhibition_titles'   => $exhibition_titles,
			'exhibition_choices'  => $exhibition_choices,
			'status_titles'       => $status_titles,
			'availability_titles' => $availability_titles,
			'time_choices'        => $time_choices,
			'party_choices'       => $party_choices,
			'topic_titles'        => $topic_titles,
			'enquiry_statuses'    => $enquiry_statuses,
			'viewing_statuses'    => $viewing_statuses,
		),
		'radios'              => array('contact_choices' => $contact_choices),
		'helpers'             => array(
			'page'           => $page(array($para('x')), true),
			'form_page'      => $page(array($para('x')), false),
			'page_header'    => $page_header(lang('Artists'), lang('Collecting')),
			'section_header' => $section_header(lang('Exhibitions'), lang('Now On'), lang('Artists'), '{{page:artists}}'),
			'step'           => $step(1, $buying_steps[0]['title'], $buying_steps[0]['text']),
			'check_list'     => $check_list(array(lang('Painting'), lang('Sculpture'))),
			'faq'            => $faq(1, lang('Collecting'), lang('Collecting')),
		),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_gl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gl_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_gl_page_keys()
{
	return array_merge(array(
		'home', 'about', 'visit', 'advisory', 'faq',
		'artists', 'artist', 'artist_new', 'works', 'work', 'work_new',
		'exhibitions', 'exhibition', 'exhibition_new',
		'enquire', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'privacy', 'email_reply',
		'email_enquiry_new', 'email_enquiry_received',
		'viewing', 'viewing_view', 'my_viewings', 'staff_viewings', 'email_viewing_new', 'email_viewing_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
		'email_account_welcome', 'email_password_reset',
	), _test_gl_shop_keys());
}

// The pages that exist only with the shop.
function _test_gl_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_gallery_data_hangs_together()
{
	$d = _test_gl_load();
	foreach (array('tagline', 'story', 'address', 'access') as $f) pg_assert_true(trim($d['gallery'][$f]) !== '', "gallery: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['gallery']['phone']), 'gallery: a phone number');
	pg_assert_true(strpos($d['gallery']['email'], '@example.com') !== false, 'gallery: a sample address');
	pg_assert_same(3, count($d['opening_hours']), 'three lines of opening hours');
	pg_assert_same(array('now', 'coming', 'past'), array_keys($d['exhibition_statuses']), 'the three exhibition states');
	pg_assert_same(array('available', 'reserved', 'sold'), array_keys($d['availability']), 'the three states of a work');
	$used_mediums = array();
	foreach ($d['artists'] as $key => $a) {
		pg_assert_true(isset($d['mediums'][$a['medium']]), "artist $key: a known medium");
		$used_mediums[$a['medium']] = true;
		foreach (array('name', 'based', 'summary', 'statement', 'bio') as $f) pg_assert_true(trim($a[$f]) !== '', "artist $key: $f");
	}
	pg_assert_same(count($d['mediums']), count($used_mediums), 'every medium has an artist');
	$shown = array();
	$states = array();
	foreach ($d['exhibitions'] as $key => $x) {
		pg_assert_true(isset($d['exhibition_statuses'][$x['status']]), "exhibition $key: a known state");
		$states[$x['status']] = true;
		pg_assert_true(count($x['artists']) >= 1, "exhibition $key: an artist");
		foreach ($x['artists'] as $a) pg_assert_true(isset($d['artists'][$a]), "exhibition $key: a known artist");
		foreach (array('title', 'dates', 'opening', 'summary', 'text') as $f) pg_assert_true(trim($x[$f]) !== '', "exhibition $key: $f");
	}
	pg_assert_same(3, count($states), 'an exhibition in each state');
	$by_artist = array();
	$by_exhibition = array();
	$work_states = array();
	$on_request = 0;
	foreach ($d['works'] as $key => $w) {
		pg_assert_true(isset($d['artists'][$w['artist']]), "work $key: a known artist");
		pg_assert_true(isset($d['availability'][$w['state']]), "work $key: a known state");
		foreach (array('title', 'technique', 'size') as $f) pg_assert_true(trim($w[$f]) !== '', "work $key: $f");
		pg_assert_true((bool)preg_match('/^[0-9]+( × [0-9]+){1,2} cm$/u', $w['size']), "work $key: a size in cm");
		if ($w['price'] === null) {
			$on_request++;
		} else {
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($w['price'][$c]) && $w['price'][$c] > 0, "work $key: a $c price");
		}
		if ($w['exhibition'] !== '') {
			pg_assert_true(isset($d['exhibitions'][$w['exhibition']]), "work $key: a known exhibition");
			pg_assert_true(in_array($w['artist'], $d['exhibitions'][$w['exhibition']]['artists'], true), "work $key: its artist shows in its exhibition");
			$by_exhibition[$w['exhibition']] = true;
		}
		$by_artist[$w['artist']] = true;
		$work_states[$w['state']] = true;
	}
	pg_assert_same(count($d['artists']), count($by_artist), 'every artist has a work');
	pg_assert_same(count($d['exhibitions']), count($by_exhibition), 'every exhibition shows a work');
	pg_assert_same(3, count($work_states), 'a work in each state');
	pg_assert_true($on_request >= 1, 'a work with its price on request');
	foreach ($d['advisory'] as $key => $s) {
		foreach (array('title', 'text', 'icon') as $f) pg_assert_true(trim($s[$f]) !== '', "advisory $key: $f");
	}
	pg_assert_same(4, count($d['buying_steps']), 'four buying steps');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) pg_assert_true(isset($d['artists'][$t['artist']]), "testimonial $n: about an artist");
}

// The pick lists: one answer per artist, medium, work and exhibition (plus
// the extra answers), the viewing offering only exhibitions not yet past,
// no answer twice.
function test_gallery_pick_lists_follow_the_data()
{
	$d = _test_gl_load();
	$l = $d['lists'];
	pg_assert_same(count($d['artists']), count($l['artist_names']), 'an answer per artist');
	pg_assert_same(count($d['mediums']), count($l['medium_titles']), 'an answer per medium');
	pg_assert_same(count($d['works']) + 1, count($l['work_choices']), 'work: each by title and another');
	pg_assert_same(count($d['exhibitions']), count($l['exhibition_titles']), 'an answer per exhibition');
	foreach ($d['exhibitions'] as $key => $x) {
		pg_assert_same($x['status'] !== 'past', in_array($x['title'], $l['exhibition_choices'], true), "exhibition $key: offered for a viewing only while on or coming");
	}
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	// A form link's query keeps the answer's own words.
	parse_str(call_user_func($d['query'], array('artwork' => $l['work_titles'][0], 'kind' => $l['enquiry_choices'][0])), $q);
	pg_assert_same($l['work_titles'][0], $q['artwork'], 'the artwork survives the query');
	pg_assert_same($l['enquiry_choices'][0], $q['kind'], 'the kind survives the query');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list; a work without one is on request.
function test_gallery_price_is_written_as_the_shop_writes_it()
{
	$d = _test_gl_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['works']['ash_vessel']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['work_price'], 'ash_vessel'), 'the work price');
	pg_assert_same(lang('Price on request'), call_user_func($d['work_price'], 'harbour_beam'), 'a price on request');
}

// The page shell places the shared parts by key, the band only on pages
// that are not forms; the helpers link only template pages.
function test_gallery_page_shell_and_helpers()
{
	$d = _test_gl_load();
	$h = $d['helpers'];
	$shared = function ($tree) {
		$out = array();
		foreach ($tree['children'] as $c) if ($c['type'] === 'shared_ref') $out[] = $c['props']['templateShared'];
		return $out;
	};
	pg_assert_same(array('account_bar', 'header', 'viewing_band', 'footer'), $shared($h['page']), 'a page with the band');
	pg_assert_same(array('account_bar', 'header', 'footer'), $shared($h['form_page']), 'a form page without it');
	$known = array_flip(_test_gl_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($h), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	pg_assert_same(count(_test_gl_page_keys()), count(array_unique(_test_gl_page_keys())), 'no page key twice');
	$nodes = array();
	_test_gl_nodes($h['faq'], $nodes);
	pg_assert_same('gl-faq-1-item', $nodes[0]['props']['id'], 'a question is its own search section');
	pg_assert_true(!empty($nodes[0]['props']['searchSection']), 'searchable');
}

// Classes the template adds of its own start with pgl-; field ids with gl-.
function test_gallery_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_gl_load()['helpers'] as $t) _test_gl_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'pgl-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
	foreach (array('pgl-display', 'pgl-eyebrow') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// The sample is never dated: a year or a month would read as a past season
// a year later.
function test_gallery_no_fixed_dates()
{
	$d = _test_gl_load();
	// The prices are amounts, never dates.
	$works = array();
	foreach ($d['works'] as $key => $w) {
		unset($w['price']);
		$works[$key] = $w;
	}
	$json = json_encode(array($d['gallery'], $d['artists'], $d['exhibitions'], $works, $d['advisory'], $d['buying_steps'], $d['testimonials'], $d['helpers']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: an artist is "sanatçı", a gallery "galeri".
function test_gallery_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bartists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sanatçı', 0, 'UTF-8') !== false, "'$key' says sanatçı");
		if (preg_match('/\bgallery\b/i', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
	}
}

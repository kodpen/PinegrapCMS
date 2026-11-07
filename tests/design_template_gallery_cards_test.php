<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's shared parts and cards
 * (includes/design_templates/pinegrap-gallery/cards.php): every page link
 * names a page of the finished template and none names a shop page, the
 * header marks the current page on the server, the cards read the
 * collections' fields with the sample on the canvas, the advisory cards
 * choose a kind the enquiry offers, the exhibition filters use the
 * states' own words, the classes and ids are the template's own, the
 * sample carries no fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/design_template_gallery_test.php';

// The shared parts and cards as the template file builds them. Read once
// per run.
function _test_glc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/cards.php';
	$cards = array('artist' => array(), 'exhibition' => array(), 'work' => array(), 'advisory' => array(), 'testimonial' => array());
	foreach (array_keys($artists) as $key) $cards['artist'][$key] = $artist_card($key);
	foreach (array_keys($exhibitions) as $key) $cards['exhibition'][$key] = $exhibition_card($key);
	foreach (array_keys($works) as $key) $cards['work'][$key] = $work_card($key);
	foreach (array_keys($advisory) as $key) $cards['advisory'][$key] = $advisory_card($key);
	foreach (array_keys($testimonials) as $n) $cards['testimonial'][$n] = $testimonial_card($n);
	$thread = $comment_thread(array(
		'title' => lang('Messages'), 'empty' => lang('No messages yet.'), 'heading' => lang('Send a message'),
		'label' => lang('Message'), 'sign_in' => lang('Sign in'), 'submit' => lang('Send'), 'watch_box' => lang('Watch'),
		'follow' => lang('Watch'), 'unfollow' => lang('Watch'), 'close' => lang('Close'), 'reopen' => lang('Reopen'),
		'reply' => true, 'watch' => true,
	));
	$form = array(
		$field('gl-x-name', lang('Your Name'), $input('gl-x-name', 'text', 'name', 'name')),
		$field('gl-x-work', lang('Works'), $select('gl-x-work', 'work', lang('Works'), $work_choices)),
		$textarea('gl-x-note', 'note', 4),
		$radios('gl-x-reply', 'reply', lang('Phone'), $contact_choices, $contact_choices[0][0]),
		$agree('gl-x-consent', 'consent', lang('Privacy Policy'), true),
		$newsletter_line('gl-x-email'), $newsletter_consent('gl-x-news'),
		$file('gl-x-file', 'file', 'gallery'), $form_step(1, lang('Works')), $submit(lang('Send')), $captcha(),
	);
	$out = array(
		'exhibition_statuses' => $exhibition_statuses,
		'enquiry_choices'     => $enquiry_choices,
		'artists'             => $artists,
		'mediums'             => $mediums,
		'exhibitions'         => $exhibitions,
		'works'               => $works,
		'availability'        => $availability,
		'work_price'          => $work_price,
		'header'              => $header(),
		'footer'              => $footer(),
		'notice'              => $notice(),
		'viewing_band'        => $viewing_band(),
		'staff_bar'           => $staff_bar(),
		'shared'              => array($account_bar_tree(), $post_card(), $ai_summary(), $edit_button(), $comments_section(lang('Messages'), 'comments'),
			$staff_page(array($para('x'))), $optional_image('photo', 'title', '', '', $photo('x', 10, 10), lang('Picture'))),
		'thread'              => $thread,
		'form'                => $form,
		'cards'               => $cards,
	);
	return $out;
}

// All the trees the shared parts make, cards included.
function _test_glc_trees()
{
	$d = _test_glc_load();
	$trees = array_merge(array($d['header'], $d['footer'], $d['notice'], $d['viewing_band'], $d['staff_bar'], $d['thread']), $d['shared'], $d['form']);
	foreach ($d['cards'] as $list) $trees = array_merge($trees, array_values($list));
	return $trees;
}

// The bindings and texts of a tree.
function _test_glc_reads($tree)
{
	$nodes = array();
	_test_gl_nodes($tree, $nodes);
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

function test_gallery_cards_page_links_name_template_pages()
{
	$known = array_flip(_test_gl_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_glc_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop, and the menu marks
// the current page itself.
function test_gallery_cards_stand_without_the_shop()
{
	$json = json_encode(_test_glc_trees());
	foreach (_test_gl_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_glc_load();
	pg_assert_contains('{{page:viewing}}', json_encode($d['header']), 'the header asks for a private viewing');
	pg_assert_contains('{{page:enquire}}', json_encode($d['viewing_band']), 'the band asks about a work');
	$nodes = array();
	_test_gl_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The exhibition filters of the notice and the footer use the states' own
// words, the words the Exhibitions form's status answers carry.
function test_gallery_cards_exhibition_filters()
{
	$d = _test_glc_load();
	$found = array();
	foreach (array($d['notice'], $d['footer']) as $tree) {
		$nodes = array();
		_test_gl_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['href']) || strpos($n['props']['href'], '{{page:exhibitions}}?') !== 0) continue;
			parse_str(substr($n['props']['href'], strlen('{{page:exhibitions}}?')), $q);
			pg_assert_true(in_array($q['status'], $d['exhibition_statuses'], true), 'a known state: ' . $q['status']);
			$found[$q['status']] = true;
		}
	}
	pg_assert_same(count($d['exhibition_statuses']), count($found), 'every state has its link');
}

// The collection cards read every value from their form's fields, with the
// sample's own words on the canvas.
function test_gallery_cards_read_the_collections()
{
	$d = _test_glc_load();
	foreach ($d['cards']['artist'] as $key => $card) {
		list($tokens, $texts) = _test_glc_reads($card);
		foreach (array('photo', 'name', 'form_item_view', 'medium', 'based', 'summary') as $t) pg_assert_true(isset($tokens[$t]), "artist $key: reads $t");
		$a = $d['artists'][$key];
		pg_assert_true(in_array($a['name'], $texts, true), "artist $key: sample name");
		pg_assert_true(in_array($d['mediums'][$a['medium']], $texts, true), "artist $key: sample medium");
	}
	foreach ($d['cards']['exhibition'] as $key => $card) {
		list($tokens, $texts) = _test_glc_reads($card);
		foreach (array('photo_1', 'title', 'form_item_view', 'status', 'dates', 'opening', 'summary') as $t) pg_assert_true(isset($tokens[$t]), "exhibition $key: reads $t");
		$x = $d['exhibitions'][$key];
		pg_assert_true(in_array($d['exhibition_statuses'][$x['status']], $texts, true), "exhibition $key: sample state");
		pg_assert_true(in_array($x['opening'], $texts, true), "exhibition $key: sample opening night");
	}
	foreach ($d['cards']['work'] as $key => $card) {
		list($tokens, $texts) = _test_glc_reads($card);
		foreach (array('photo', 'title', 'form_item_view', 'artist', 'technique', 'size', 'price', 'availability') as $t) pg_assert_true(isset($tokens[$t]), "work $key: reads $t");
		$w = $d['works'][$key];
		pg_assert_true(in_array($d['artists'][$w['artist']]['name'], $texts, true), "work $key: sample artist");
		pg_assert_true(in_array(call_user_func($d['work_price'], $key), $texts, true), "work $key: sample price");
		pg_assert_true(in_array($d['availability'][$w['state']], $texts, true), "work $key: sample availability");
	}
}

// An advisory card opens the enquiry with a kind the enquiry offers.
function test_gallery_cards_advisory_chooses_a_kind()
{
	$d = _test_glc_load();
	foreach ($d['cards']['advisory'] as $key => $card) {
		$nodes = array();
		_test_gl_nodes($card, $nodes);
		$links = 0;
		foreach ($nodes as $n) {
			if (!isset($n['props']['href']) || strpos($n['props']['href'], '{{page:enquire}}?') !== 0) continue;
			parse_str(substr($n['props']['href'], strlen('{{page:enquire}}?')), $q);
			pg_assert_true(in_array($q['kind'], $d['enquiry_choices'], true), "advisory $key: a Kind answer");
			$links++;
		}
		pg_assert_same(1, $links, "advisory $key: one enquiry link");
	}
}

// Classes the template adds of its own start with pgl-; field ids with gl-.
function test_gallery_cards_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_glc_trees() as $t) _test_gl_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false(strpos($c, 'ratio-') === 0 && !in_array($c, array('ratio-1x1', 'ratio-4x3', 'ratio-16x9', 'ratio-21x9'), true), "a Bootstrap ratio ($c)");
			if (strpos($c, 'pgl-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
	foreach (array('pgl-brand', 'pgl-display', 'pgl-eyebrow', 'pgl-frame', 'pgl-lift', 'pgl-price', 'pgl-caption', 'pgl-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

function test_gallery_cards_no_fixed_dates()
{
	$json = json_encode(_test_glc_trees(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the cards has its Turkish; an artist is "sanatçı", a
// gallery "galeri".
function test_gallery_cards_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/cards.php');
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

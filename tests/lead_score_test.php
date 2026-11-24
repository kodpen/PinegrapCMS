<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor journey's scores (includes/fn/lead_score.php): the
 * settings made safe, the pricing and contact pages, the behaviour events of
 * a journey, the fit score with free mail domains, and the interests from
 * the folders of the pages seen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_score.php');

// 2026-11-20 12:00:00 UTC.
define('LEAD_SCORE_TEST_NOW', 1795176000);

function test_lead_score_settings_defaults_and_bounds()
{
	$defaults = pg_lead_score_settings('');
	pg_assert_same(60, $defaults['threshold'], 'hot from 60 by default');
	pg_assert_same(30, $defaults['half_life'], 'half-life 30 days');
	pg_assert_same(13, $defaults['keep_months'], 'kept 13 months');
	pg_assert_same(25, $defaults['weights']['form'], 'code weights');
	pg_assert_same(array(), $defaults['pricing_pages'], 'no pages chosen');

	$set = pg_lead_score_settings(json_encode(array(
		'threshold' => 500,
		'half_life' => 0,
		'keep_months' => '6',
		'weights' => array('form' => 40, 'unknown' => 9, 'download' => -5),
		'pricing_pages' => array(3, '3', 0, -1, 'x', 7),
	)));
	pg_assert_same(100, $set['threshold'], 'threshold held at 100');
	pg_assert_same(1, $set['half_life'], 'half-life at least a day');
	pg_assert_same(6, $set['keep_months'], 'keep period read');
	pg_assert_same(40, $set['weights']['form'], 'site weight');
	pg_assert_same(0, $set['weights']['download'], 'never below 0');
	pg_assert_true(!isset($set['weights']['unknown']), 'unknown kind dropped');
	pg_assert_same(array(3, 7), $set['pricing_pages'], 'page ids cleaned');
	pg_assert_same(60, pg_lead_score_settings('{broken')['threshold'], 'broken text gives defaults');
}

function test_lead_score_page_kind_guess_and_choice()
{
	pg_assert_same('pricing_page', pg_lead_score_page_kind('Fiyatlar', array(), array()), 'pricing guessed');
	pg_assert_same('contact_page', pg_lead_score_page_kind('iletisim', array(), array()), 'contact guessed');
	pg_assert_same('', pg_lead_score_page_kind('about', array(), array()), 'other page');
	pg_assert_same('', pg_lead_score_page_kind('pricing', array('our-plans'), array()), 'a choice replaces the guess');
	pg_assert_same('pricing_page', pg_lead_score_page_kind('our-plans', array('our-plans'), array()), 'chosen page');
	pg_assert_same('contact_page', pg_lead_score_page_kind('contact', array('our-plans'), array()), 'the other kind still guessed');
}

function test_lead_score_events_of_a_journey()
{
	$day = 86400;
	$now = LEAD_SCORE_TEST_NOW;
	$rows = array(
		array('kind' => 'visit', 'at' => $now - 2 * $day, 'pages' => array(array('home', 30), array('pricing', 40), array('pricing', 5))),
		array('kind' => 'form', 'at' => $now - 2 * $day),
		array('kind' => 'visit', 'at' => $now - 20 * $day, 'pages' => array(array('contact', 10))),
		array('kind' => 'visit', 'at' => $now - 25 * $day, 'pages' => array()),
		array('kind' => 'order', 'at' => $now - $day),
		array('kind' => 'campaign_click', 'at' => $now - 3 * $day),
	);

	$events = pg_lead_score_events($rows);
	$kinds = array_column($events, 'kind');
	sort($kinds);

	pg_assert_same(array('campaign_click', 'contact_page', 'form', 'pricing_page', 'return_7d'), $kinds, 'pricing once a visit, one quick return, no order');

	foreach ($events as $event) {
		if ($event['kind'] === 'return_7d') {
			pg_assert_same($now - 20 * $day, $event['at'], 'the visit 5 days after the one before is the return');
		}
	}
}

function test_lead_score_fit_score()
{
	$full = pg_lead_fit_score(array('email' => 'Ayse@Firma.com.tr', 'company' => 'Firma', 'title' => 'Buyer', 'groups' => 2));
	pg_assert_same(100, $full['score'], 'all four');
	pg_assert_same(array('company_domain', 'company', 'title'), array_column($full['reasons'], 'kind'), 'top three reasons');

	pg_assert_same(0, pg_lead_fit_score(array('email' => 'ayse@gmail.com'))['score'], 'free mail is no company');
	pg_assert_same(0, pg_lead_fit_score(array('email' => 'not-an-address'))['score'], 'no domain');
	pg_assert_same(40, pg_lead_fit_score(array('email' => 'a@kodpen.com', 'company' => '  '))['score'], 'blank company');
	pg_assert_same(15, pg_lead_fit_score(array('email' => 'a@HOTMAIL.com', 'groups' => 1))['score'], 'a group');
	pg_assert_true(pg_lead_free_mail_domain('Yandex.com.tr'), 'free list ignores case');
}

function test_lead_score_interest_topics()
{
	$day = 86400;
	$now = LEAD_SCORE_TEST_NOW;
	$folders = array(
		'tractor-a' => array(4, 'Tractors'),
		'tractor-b' => array(4, 'Tractors'),
		'seed' => array(9, 'Seeds'),
	);
	$rows = array(
		array('kind' => 'visit', 'at' => $now, 'pages' => array(array('tractor-a', 100), array('home', 900), array('seed', 2))),
		array('kind' => 'visit', 'at' => $now - 30 * $day, 'pages' => array(array('tractor-b', 40), array('seed', 60))),
		array('kind' => 'form', 'at' => $now),
	);

	$topics = pg_lead_interest_topics($rows, $folders, $now, 30);

	pg_assert_same(array('Tractors', 'Seeds'), array_column($topics, 'name'), 'heaviest first, the root page counts nothing');
	pg_assert_same(120.0, $topics[0]['weight'], '100 now and 40 faded by one half-life');
	pg_assert_same(40.0, $topics[1]['weight'], 'at least 10 seconds a page, 60 halved');

	$many = array();
	for ($i = 1; $i <= 7; $i++) {
		$many['p' . $i] = array($i, 'T' . $i);
	}
	$pages = array();
	foreach (array_keys($many) as $page) {
		$pages[] = array($page, 20);
	}
	pg_assert_same(5, count(pg_lead_interest_topics(array(array('kind' => 'visit', 'at' => $now, 'pages' => $pages)), $many, $now)), 'top five');
}

function test_lead_score_interest_topics_product_category()
{
	$day = 86400;
	$now = LEAD_SCORE_TEST_NOW;
	$folders = array('product-detail' => array(3, 'Shop'), 'tractor-a' => array(4, 'Tractors'));
	$categories = array(
		'product:12' => array(array(20, 'Seeds'), array(21, 'Organic')),
		'product_group:20' => array(array(20, 'Seeds')),
	);
	$rows = array(
		array('kind' => 'visit', 'at' => $now, 'pages' => array(
			array('product-detail', 100, 'product', 12),
			array('catalog', 30, 'product_group', 20),
			array('product-detail', 50, 'product', 99),
			array('tractor-a', 20),
		)),
	);

	$topics = pg_lead_interest_topics($rows, $folders, $now, 30, $categories);
	$by_name = array_column($topics, null, 'name');

	pg_assert_same(80.0, $by_name['Seeds']['weight'], 'half of the product and the whole group page');
	pg_assert_same('category', $by_name['Seeds']['kind'], 'a product group is a category');
	pg_assert_same(50.0, $by_name['Organic']['weight'], 'a product in two groups shares its seconds');
	pg_assert_same(50.0, $by_name['Shop']['weight'], 'a product no longer in the catalogue falls back to the page folder');
	pg_assert_same('folder', $by_name['Tractors']['kind'], 'a plain page keeps its folder');
	pg_assert_same(false, isset($by_name['Shop']) && ($by_name['Shop']['weight'] > 50.0), 'the product page with a category is not counted to its folder too');

	pg_assert_same(array('product' => array(12, 99), 'product_group' => array(20)), pg_lead_interest_items($rows + array(1 => array('kind' => 'form', 'at' => $now))), 'the items of the visits');
	pg_assert_same(array(), pg_lead_interest_items(array(array('kind' => 'visit', 'at' => $now - $day, 'pages' => array(array('home', 5), array('x', 5, 'order', 3))))), 'an unknown item type is not looked up');
}

function test_lead_score_list_cell()
{
	pg_assert_same('', pg_lead_score_list_cell(null, 60), 'not scored: empty');
	pg_assert_same('42', pg_lead_score_list_cell('42', 60), 'the figure alone below the threshold');
	pg_assert_true(strpos(pg_lead_score_list_cell(60, 60), 'pg-pill') !== false, 'hot from the threshold on');
	pg_assert_same('0', pg_lead_score_list_cell(0, 60), 'a scored contact with no interest shows 0');
}

function test_lead_score_settings_input()
{
	$post = array(
		'lead_score_threshold' => '70',
		'lead_score_pricing_pages' => array('4', '4', 'x', '9'),
		'lead_score_half_life' => '14',
		'lead_score_keep_months' => '6',
	);
	foreach (pg_lead_score_weights() as $kind => $points) {
		$post['lead_score_weight_' . $kind] = (string) $points;
	}
	$post['lead_score_weight_form'] = '40';

	$input = pg_lead_score_settings_input($post);
	pg_assert_same(array(), $input['errors'], 'all in bounds');
	pg_assert_same(70, $input['settings']['threshold']);
	pg_assert_same(40, $input['settings']['weights']['form']);
	pg_assert_same(array(4, 9), $input['settings']['pricing_pages'], 'page ids once, nothing else');
	pg_assert_same(array(), $input['settings']['contact_pages'], 'no page chosen');
	pg_assert_same(14, $input['settings']['half_life']);
	pg_assert_same(6, $input['settings']['keep_months']);

	$bad = pg_lead_score_settings_input(array('lead_score_threshold' => '0', 'lead_score_weight_form' => '101', 'lead_score_half_life' => 'abc', 'lead_score_keep_months' => '37') + $post);
	pg_assert_same(array('lead_score_weight_form', 'lead_score_threshold', 'lead_score_half_life', 'lead_score_keep_months'), array_keys($bad['errors']), 'each field out of bounds is named');
}

function test_lead_score_settings_text()
{
	pg_assert_same('', pg_lead_score_settings_text(pg_lead_score_settings(array())), 'the defaults store nothing');
	$text = pg_lead_score_settings_text(array('threshold' => 80));
	pg_assert_same(80, pg_lead_score_settings($text)['threshold'], 'read back as written');
}

function test_lead_score_interest_points_by_kind()
{
	$score = pg_lead_interest_score(array(array('kind' => 'form', 'at' => LEAD_SCORE_TEST_NOW), array('kind' => 'download', 'at' => LEAD_SCORE_TEST_NOW)), LEAD_SCORE_TEST_NOW);
	pg_assert_same(array('form' => 25.0, 'download' => 15.0), $score['points'], 'each kind with its points, most first');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the plan of pages the "Describe it" start asks Pinegrap AI for
 * (includes/fn/site_plan.php): the addresses, the pages and the theme read
 * out of the answer and cut, the drafts a run takes, the request's checks,
 * the messages and the endpoint's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_plan.php');

function _site_plan_brief()
{
	return array(
		'name' => 'Ekmek Evi', 'what' => 'We bake sourdough bread and cakes to order.', 'kind' => 'shop',
		'customers' => 'Families nearby', 'tone' => 'friendly', 'pages' => array('Home', 'About', 'Contact'), 'colour' => '',
	);
}

/**
 * An address is lower case letters, digits and hyphens, Turkish letters
 * folded, at most 60 characters; nothing left gives 'page'.
 */
function test_site_plan_address()
{
	pg_assert_same('hakkimizda', pg_site_plan_address('Hakkımızda'), 'Turkish letters folded');
	pg_assert_same('our-work-2026', pg_site_plan_address('  Our   Work / 2026! '), 'spaces and marks to one hyphen');
	pg_assert_same('cakes-to-order', pg_site_plan_address('/cakes_to_order/'), 'slashes and underscores');
	pg_assert_same('page', pg_site_plan_address('!!!'), 'nothing left');
	pg_assert_same('page', pg_site_plan_address(array('x')), 'not text');
	pg_assert_same(60, strlen(pg_site_plan_address(str_repeat('a', 80))), 'cut to 60');
	pg_assert_same(str_repeat('ab-', 19) . 'ab', pg_site_plan_address(str_repeat('ab ', 40)), 'no hyphen at the end after the cut');
	pg_assert_same('istanbul', pg_site_plan_address('İSTANBUL'), 'dotted capital I');
}

/**
 * Pages: a name is needed, the address comes from the answer or the name
 * and is never used twice, the purpose and sections are plain and cut, a
 * page without a section goes, the menu falls back to the main menu, at
 * most PG_SITE_PLAN_PAGES pages and PG_SITE_PLAN_SECTIONS sections.
 */
function test_site_plan_pages()
{
	$pages = pg_site_plan_pages(array('pages' => array(
		array('name' => ' <b>Home</b> ', 'address' => 'Home', 'purpose' => 'Welcome **visitors**', 'sections' => array('Intro with a button', '', 'Three best sellers'), 'menu' => 'main'),
		array('name' => 'About us', 'address' => '', 'purpose' => '', 'sections' => array('Our story'), 'menu' => 'footer'),
		array('name' => 'About us', 'address' => 'about-us', 'sections' => array('Again'), 'menu' => 'sidebar'),
		array('name' => '', 'sections' => array('No name')),
		array('name' => 'Empty', 'sections' => array('', '  ')),
		array('name' => 'Bad sections', 'sections' => 'one string'),
		array('name' => 'Contact', 'address' => 'contact', 'sections' => array(array('words' => 'not text'), 'The form'), 'menu' => 'none'),
		'not a page',
	)));

	pg_assert_same(array(
		array('name' => 'Home', 'address' => 'home', 'purpose' => 'Welcome visitors', 'sections' => array('Intro with a button', 'Three best sellers'), 'menu' => 'main'),
		array('name' => 'About us', 'address' => 'about-us', 'purpose' => '', 'sections' => array('Our story'), 'menu' => 'footer'),
		array('name' => 'About us', 'address' => 'about-us-2', 'purpose' => '', 'sections' => array('Again'), 'menu' => 'main'),
		array('name' => 'Contact', 'address' => 'contact', 'purpose' => '', 'sections' => array('The form'), 'menu' => 'none'),
	), $pages, 'pages checked');

	$many = array();
	for ($i = 0; $i < 20; $i++) {
		$many[] = array('name' => 'Page ' . $i, 'sections' => array_fill(0, 15, 'A band of cards'));
	}
	$pages = pg_site_plan_pages(array('pages' => $many));
	pg_assert_same(PG_SITE_PLAN_PAGES, count($pages), 'at most PG_SITE_PLAN_PAGES pages');
	pg_assert_same(PG_SITE_PLAN_SECTIONS, count($pages[0]['sections']), 'at most PG_SITE_PLAN_SECTIONS sections');

	$limits = pg_site_plan_limits();
	$long = pg_site_plan_pages(array('pages' => array(array('name' => str_repeat('word ', 30), 'purpose' => str_repeat('word ', 100), 'sections' => array(str_repeat('word ', 100))))));
	pg_assert_true(mb_strlen($long[0]['name']) <= $limits['name'], 'name cut');
	pg_assert_true(mb_strlen($long[0]['purpose']) <= $limits['purpose'], 'purpose cut');
	pg_assert_true(mb_strlen($long[0]['sections'][0]) <= $limits['section'], 'section cut');

	pg_assert_same(array(), pg_site_plan_pages(null), 'no answer');
	pg_assert_same(array(), pg_site_plan_pages(array('pages' => 'x')), 'pages not a list');
}

/**
 * The theme is the five questions' answers: unknown choices are skipped
 * (''), the brief's brand colour wins over the model's.
 */
function test_site_plan_theme()
{
	$theme = pg_site_plan_theme(array('theme' => array('brand' => '#2563EB', 'accent' => 'auto', 'look' => 'minimal', 'type' => 'classic', 'room' => 'roomy')), '');
	pg_assert_same(array('brand' => '#2563eb', 'accent' => 'auto', 'look' => 'minimal', 'type' => 'classic', 'room' => 'roomy'), $theme, 'all five');

	$theme = pg_site_plan_theme(array('theme' => array('brand' => 'red', 'accent' => '#12345', 'look' => 'neon', 'type' => 'comic', 'room' => 'huge')), '#0f766e');
	pg_assert_same(array('brand' => '#0f766e', 'accent' => '', 'look' => '', 'type' => '', 'room' => ''), $theme, 'unknown skipped, the brief\'s colour');

	pg_assert_same(array('brand' => '', 'accent' => '', 'look' => '', 'type' => '', 'room' => ''), pg_site_plan_theme(null, ''), 'no answer');
}

/**
 * A run takes one draft a page (its sketch) and one a section (its texts).
 */
function test_site_plan_cost()
{
	pg_assert_same(0, pg_site_plan_cost(array()), 'no pages');
	pg_assert_same(7, pg_site_plan_cost(array(
		array('sections' => array('a', 'b', 'c')),
		array('sections' => array('a', 'b')),
	)), 'two pages, five sections');
	pg_assert_same(1 + PG_SITE_PLAN_GUESS, pg_site_plan_cost(array(array('name' => 'Added', 'sections' => array()))), 'a page added on the plan screen is counted with the guess');
}

/**
 * The request's brief is checked as the pane checks it, sent as an array
 * or as JSON; a brief with a mistake is refused with its first words.
 */
function test_site_plan_input()
{
	$in = pg_site_plan_input(array('brief' => _site_plan_brief()));
	pg_assert_same('', $in['error'], 'a good brief');
	pg_assert_same(array('Home', 'About', 'Contact'), $in['brief']['pages'], 'its pages');

	$in = pg_site_plan_input(array('brief' => json_encode(_site_plan_brief())));
	pg_assert_same('', $in['error'], 'a brief as JSON');

	$bad = _site_plan_brief();
	$bad['what'] = 'Short';
	$in = pg_site_plan_input(array('brief' => $bad));
	$words = pg_site_brief_texts();
	pg_assert_same($words['errors']['what'], $in['error'], 'the first mistake\'s words');

	$in = pg_site_plan_input(array());
	pg_assert_true($in['error'] !== '', 'no brief');
}

/**
 * The messages: the rules name the language, the looks, the type feels,
 * the rooms and the answer's shape last; the user's message is the brief
 * as JSON.
 */
function test_site_plan_messages()
{
	$in = pg_site_plan_input(array('brief' => _site_plan_brief()));
	$messages = pg_site_plan_messages($in['brief'], 'Turkish');

	pg_assert_same('system', $messages[0]['role'], 'system first');
	pg_assert_contains('Turkish', $messages[0]['content'], 'the language');
	pg_assert_contains('modern-soft', $messages[0]['content'], 'the looks');
	pg_assert_contains('technical', $messages[0]['content'], 'the type feels');
	pg_assert_contains('compact', $messages[0]['content'], 'the rooms');
	$lines = explode("\n", $messages[0]['content']);
	pg_assert_contains('Answer only with JSON', end($lines), 'the shape last');

	$user = json_decode($messages[1]['content'], true);
	pg_assert_same('Ekmek Evi', $user['name'], 'the brief sent');
	pg_assert_same(array('Home', 'About', 'Contact'), $user['pages'], 'its pages');
}

/**
 * The editor's endpoint calls the module, for designers only, and is on
 * the page cache's read-only list.
 */
function test_site_plan_wiring()
{
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_contains("case 'site_plan':", $designer, 'the sub action');
	pg_assert_contains("pg_site_plan_request(\$request, \$user)", $designer, 'calls the module');

	$cache = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php');
	pg_assert_contains("'site_plan'", $cache, 'read-only for the page cache');
}

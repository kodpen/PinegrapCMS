<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant block family (assets/js/style_designer_blocks_
 * restaurant.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, a section per block
 * with one top heading and no skipped level, no fixed colour or template
 * class, every {var} slot filled, no year or month, every entrance kept by
 * the interaction normalizer; the evening hero's slip and plate, the tasting
 * menu card's courses, wines and prices, the chef's counter's seats,
 * stations and evening; its rules reaching the block stylesheet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/designer_family_run.php');

function test_designer_blocks_restaurant_family_registers()
{
	$got = pg_test_designer_family_run('restaurant');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('restaurant-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Restaurant', $got['group'], 'its own palette group');
	pg_assert_same(array('restaurant-hero', 'restaurant-tasting', 'restaurant-counter'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_restaurant_blocks_draw_sound_trees()
{
	$got = pg_test_designer_family_run('restaurant');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The evening hero is a page's way in, so its line is the h1; the
		// menu card and the counter are sections under a page's own h1.
		$top = $ctype === 'restaurant-hero' ? 1 : 2;
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(prt|rt)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s interactions survive the normalizer');
		// Small text in the theme's colour is under 4.5:1 on a tinted
		// surface or a dark band; the blocks write its darker shade, and
		// their second buttons take the body's colour.
		pg_assert_same(array(), array_values(array_intersect(array('text-primary', 'link-primary', 'btn-outline-primary', 'btn-outline-secondary'), $b['classes'])), $ctype . ' writes no light primary text or outline button');
		pg_assert_true(in_array('btn-primary', $b['classes'], true) && in_array('link-body-emphasis', $b['classes'], true), $ctype . ' has a primary and a second button');
	}

	$hero = $got['blocks']['restaurant-hero'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $hero['attrs'], 'the hero is a dark band');
	foreach (array('bg-body', 'text-body', 'overflow-hidden', 'pg-display-xl', 'pg-restaurant-plate', 'pg-restaurant-slip', 'pg-restaurant-tear', 'text-primary-emphasis') as $class) {
		pg_assert_true(in_array($class, $hero['classes'], true), 'the hero writes ' . $class);
	}
	foreach (array('Neighbourhood restaurant', 'Cooked with the seasons', 'Reserve a Table', 'See the menu', 'This evening', 'Dinner', 'First and last table', '18:30', '21:30', 'Kitchen', '23:30', 'Two seats at the counter are kept for guests who walk in.', 'Slow-Roasted Lamb Shoulder') as $text) {
		pg_assert_true(in_array($text, $hero['texts'], true), 'the hero says ' . $text);
	}
	pg_assert_same(array('h1', 'h2'), $hero['headings'], 'the line, then the evening\'s slip');
	pg_assert_same(3, count($hero['fx']), 'the slip fades in, the words rise, the plate grows');

	$menu = $got['blocks']['restaurant-tasting'];
	foreach (array('pg-restaurant-menu', 'pg-restaurant-courses', 'pg-restaurant-price', 'bg-body-tertiary', 'order-1', 'order-lg-2') as $class) {
		pg_assert_true(in_array($class, $menu['classes'], true), 'the menu card writes ' . $class);
	}
	pg_assert_same(array('h2', 'h3', 'h3', 'h3', 'h3', 'h3', 'h3', 'h3'), $menu['headings'], 'the menu\'s name, then a heading per course');
	// Each course is followed by the wine poured with it, in the order the
	// template's Seasons Menu serves them.
	$courses = array('Oysters with Green Apple', 'Valley Brut', 'Sea Bass Crudo', 'Hillside White', 'Wild Mushroom Broth', 'Stone Terrace', 'Catch of the Day', 'Summer Garden',
		'Slow-Roasted Lamb Shoulder', 'Old Vines Red', 'Cheese from the Valley', 'River Bend', 'Dark Chocolate Tart', 'Late Harvest');
	pg_assert_same($courses, array_values(array_intersect($menu['texts'], $courses)), 'seven courses, each with its wine');
	foreach (array('Tasting menu', 'Seasons Menu', 'Menu', '$95', 'Wine pairing', '$60', 'per guest', 'Reserve with the menu chosen', 'Wine List') as $text) {
		pg_assert_true(in_array($text, $menu['texts'], true), 'the menu card says ' . $text);
	}
	pg_assert_same(4, count($menu['fx']), 'the card rises, its courses follow one by one, the two pictures drift');

	$counter = $got['blocks']['restaurant-counter'];
	foreach (array('pg-restaurant-counter', 'pg-restaurant-kitchen', 'pg-restaurant-bar', 'pg-restaurant-seats', 'pg-restaurant-evening') as $class) {
		pg_assert_true(in_array($class, $counter['classes'], true), 'the counter writes ' . $class);
	}
	// The template's chef's table has six seats.
	pg_assert_same(6, count(array_keys($counter['classes'], 'pg-restaurant-seat', true)), 'six seats at the counter');
	pg_assert_same(array('1', '2', '3', '4', '5', '6'), array_values(array_intersect($counter['texts'], array('1', '2', '3', '4', '5', '6'))), 'the seats are numbered in order');
	pg_assert_same(array('icon+heading', 'icon+heading', 'icon+heading', 'icon+heading'), $counter['badges'], 'four stations, each an icon and its name in a text span');
	foreach (array('The Chef\'s Table', 'Six seats beside the fire', 'The evening', '19:00', '21:45', 'The kitchen', 'Wood oven', 'The pass', 'Starting from', '$95', 'per guest', 'Book the chef\'s table', 'See the tasting menus') as $text) {
		pg_assert_true(in_array($text, $counter['texts'], true), 'the counter says ' . $text);
	}
	pg_assert_same(array('h2', 'h3'), $counter['headings'], 'the title, then the evening');
	pg_assert_same(3, count($counter['fx']), 'the words rise, the drawing fades in, the seats grow one by one');
}

// The times, the lists and the hidden drawing are read from the file, as
// the run flattens only classes and texts.
function test_designer_blocks_restaurant_markup_from_the_file()
{
	$js = pg_test_designer_family_file('restaurant');
	foreach (array("kit.time('18:30', '18:30'", "kit.time('21:30', '21:30'", "kit.time('23:30', '23:30'") as $time) {
		pg_assert_true(strpos($js, $time) !== false, 'the slip\'s times are times: ' . $time);
	}
	pg_assert_true(strpos($js, "return kit.tag('section', 'py-5 bg-body text-body overflow-hidden', _sdT('Restaurant (Evening Hero)'),") !== false, 'the hero band clips the plate that runs past its edge');
	pg_assert_true(strpos($js, "fx(kit.tag('figure', 'm-0', _sdT('Signature Dish'), [") !== false && strpos($js, "kit.tag('figcaption', ") !== false, 'the dish is a figure with its caption');
	pg_assert_true(strpos($js, "fx(kit.tag('ol', 'pg-restaurant-courses', _sdT('Course List'), [") !== false, 'the courses are an ordered list');
	pg_assert_true(strpos($js, "kit.tag('ol', 'pg-restaurant-evening mb-4', _sdT('Evening'), [") !== false, 'the evening is an ordered list');
	pg_assert_same(4, substr_count($js, "                moment('"), 'four moments of the evening');
	pg_assert_true(strpos($js, "return kit.time(time, time, '', _sdT('Time'))") === false && strpos($js, "kit.time(time, time, '', _sdT('Time')),") !== false, 'each moment starts with a time');
	pg_assert_true(strpos($js, "kit.p(_sdT('Every seat faces the fire.'), 'small text-body-secondary text-center mt-3 mb-0')\n        ], [{ name: 'aria-hidden', value: 'true' }])") !== false, 'the counter drawing is hidden: the words beside it say the same');
	pg_assert_true(strpos($js, "{ effect: 'parallax', speed: 12 }") !== false, 'the pictures beside the menu card drift');
	// A picture column is half a phone's width and a quarter from lg up; a
	// col node's own xs would otherwise be 12.
	pg_assert_true(strpos($js, "kit.createNode('col', { xs: '6', sm: '', md: '', lg: '3',") !== false, 'the pictures sit side by side on a phone');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_restaurant_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(pg_test_designer_family_file('restaurant'));
	pg_assert_same(array('pg-restaurant-plate', 'pg-restaurant-slip', 'pg-restaurant-menu', 'pg-restaurant-counter', 'pg-restaurant-evening'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-display-xl', 'pg-restaurant-plate', 'pg-restaurant-slip'), $part['needs']['restaurant-hero'], 'the hero needs the giant type, the plate and the slip');
	pg_assert_same(array('pg-restaurant-menu'), $part['needs']['restaurant-tasting'], 'the menu card needs its frame');
	pg_assert_same(array('pg-restaurant-counter', 'pg-restaurant-evening'), $part['needs']['restaurant-counter'], 'the counter needs its drawing and the evening');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-restaurant-counter']), 'the block stylesheet holds the family\'s rules');
	pg_assert_true(isset($source['rules']['pg-display-xl']), 'the giant type is a core rule the hero takes');
	foreach ($part['rules'] as $name => $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $name);
		pg_assert_same(false, strpos($css, '!important'), 'a rule never forces: ' . $name);
		// The canvas wraps every node in an .sd-wrap, so a child or sibling
		// selector would miss there what it matches on the page.
		pg_assert_same(0, preg_match('/[>+~]\s*(li|div|dt|dd|time|span|img|p)\b/', $css), 'no child or sibling selector: ' . $name);
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $css), 'no fixed colour: ' . $name);
	}
	// The plate runs past its column only from lg up, where the band clips
	// it; below lg it fits the column.
	pg_assert_true(strpos($part['rules']['pg-restaurant-plate'], '.pg-restaurant-plate { width: min(100%, 30rem);') !== false
		&& strpos($part['rules']['pg-restaurant-plate'], '@media (min-width: 992px) { .pg-restaurant-plate { width: 115%;') !== false, 'the plate fits a phone and runs past the column from lg up');
	// The kitchen's warm wash mixes the theme's colour only where the
	// browser can; an older one shows the plain surface.
	pg_assert_true(strpos($part['rules']['pg-restaurant-counter'], '@supports (background: color-mix(in srgb, red 50%, blue))') !== false, 'the warm wash is behind @supports');
	// Six seats fit a 320 px phone: their size and gap follow the window.
	pg_assert_true(strpos($part['rules']['pg-restaurant-counter'], 'width: clamp(2rem, 9vw, 3.25rem)') !== false && strpos($part['rules']['pg-restaurant-counter'], 'gap: clamp(.5rem, 3vw, 1.75rem)') !== false, 'the seats follow the window');
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the plan screen of the "Describe it" start of the New design
 * window: its place in the pane and its words (includes/fn/site_plan.php),
 * the address that opens the new design, and the pure parts of
 * assets/js/designer_describe.js run in Node when it is there: the
 * addresses and the drafts as the server counts them, the rows' check,
 * the sketch and the theme's answers "Make my site" hands over.
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
require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

/**
 * Runs `$body` in Node after the part, with `d` its pure parts; the JSON it
 * prints, or null when Node is not there.
 */
function _site_plan_screen_node($body)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_describe.js');
	$file = tempnam(sys_get_temp_dir(), 'pgsp') . '.js';
	file_put_contents($file, "var window = {};\n" . $js . "\nvar d = window.sdStartParts.describe;\n" . $body . "\n");
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $lines));

	return json_decode(implode("\n", $lines), true);
}

function _site_plan_screen_limits()
{
	$limits = pg_site_plan_limits();

	return array('pages' => PG_SITE_PLAN_PAGES, 'name' => $limits['name'], 'address' => $limits['address'], 'guess' => PG_SITE_PLAN_GUESS);
}

// The address and the drafts are counted in the browser as on the server.
function test_site_plan_screen_twins()
{
	$names = array('Hakkımızda', '  Our   Work / 2026! ', '/cakes_to_order/', '!!!', str_repeat('a', 80), str_repeat('ab ', 40), 'İSTANBUL', 'Café Crème', 'Straße', 'Çiçekçi Şükrü', 42);
	$plans = array(
		array(),
		array(array('sections' => array('a', 'b', 'c')), array('sections' => array('a', 'b'))),
		array(array('sections' => array()), array('sections' => array('a'))),
	);
	$r = _site_plan_screen_node('console.log(JSON.stringify({addresses: ' . json_encode($names, JSON_UNESCAPED_UNICODE) . '.map(function (n) { return d.address(n, 60); }),'
		. ' costs: ' . json_encode($plans) . '.map(function (p) { return d.cost(p, ' . PG_SITE_PLAN_GUESS . '); })}));');
	if ($r === null) {
		return;
	}
	foreach ($names as $i => $name) {
		pg_assert_same(pg_site_plan_address($name), $r['addresses'][$i], 'the address of ' . $name);
	}
	foreach ($plans as $i => $plan) {
		pg_assert_same(pg_site_plan_cost($plan), $r['costs'][$i], 'the drafts of plan ' . $i);
	}
}

// The rows "Make my site" takes: names plain, a renamed or added row's
// address from its name, every address once, an empty name marked, no
// page or too many pages refused.
function test_site_plan_screen_check_rows()
{
	$rows = array(
		array('name' => ' Home ', 'address' => 'home', 'purpose' => 'Welcome', 'sections' => array('Intro'), 'menu' => 'main', 'auto' => false),
		array('name' => 'Ana sayfa', 'address' => 'home', 'sections' => array('Again'), 'menu' => 'footer', 'auto' => true),
		array('name' => 'Home', 'address' => 'home', 'sections' => array('Copy'), 'menu' => 'sidebar', 'auto' => false),
		array('name' => '  ', 'address' => '', 'sections' => array(), 'menu' => 'none', 'auto' => true),
	);
	$limits = _site_plan_screen_limits();
	$many = array_fill(0, PG_SITE_PLAN_PAGES + 1, array('name' => 'Page', 'sections' => array('a')));
	$r = _site_plan_screen_node('var L = ' . json_encode($limits) . ";\n"
		. 'console.log(JSON.stringify({rows: d.checkPlan(' . json_encode($rows, JSON_UNESCAPED_UNICODE) . ', L), none: d.checkPlan([], L).codes,'
		. ' many: d.checkPlan(' . json_encode($many) . ', L).codes}));');
	if ($r === null) {
		return;
	}
	$pages = $r['rows']['pages'];
	pg_assert_same(array('home', 'ana-sayfa', 'home-2', 'page'), array_column($pages, 'address'), 'a renamed row follows its name, a repeat gets -2');
	pg_assert_same('Home', $pages[0]['name'], 'names trimmed');
	pg_assert_same(array('main', 'footer', 'main', 'none'), array_column($pages, 'menu'), 'an unknown menu place is the main menu');
	pg_assert_same(array('3' => 'name'), $r['rows']['codes']['rows'], 'the row without a name');
	pg_assert_same(array('pages' => 'none'), $r['none'], 'no page');
	pg_assert_same(array('pages' => 'many'), $r['many'], 'more pages than a plan holds');
}

// The sketch the new design opens with: one empty frame a page, side by
// side as the Canva start places them, its address in the page's details;
// the theme's answers given only.
function test_site_plan_screen_sketch_and_answers()
{
	$pages = array(array('name' => 'Home', 'address' => 'home'), array('name' => 'Contact', 'address' => 'contact'));
	$r = _site_plan_screen_node('console.log(JSON.stringify({sketch: d.sketchOf(' . json_encode($pages) . '),'
		. ' answers: d.answersOf({brand: "#2563eb", accent: "auto", look: "", type: "classic", room: null})}));');
	if ($r === null) {
		return;
	}
	pg_assert_same(3, $r['sketch']['n'], 'the next id');
	pg_assert_same(array(1, 'Home', 640, 0, array(), array('address' => 'home', 'typed' => true, 'type' => 'standard')), array_values($r['sketch']['pages'][0]), 'the first frame');
	pg_assert_same(640 + 576, $r['sketch']['pages'][1]['x'], 'the second beside it');
	pg_assert_same(array('brand' => '#2563eb', 'accent' => 'auto', 'type' => 'classic'), $r['answers'], 'a skipped answer is left out');
}

// The pane holds the plan screen's place with its words, the theme's
// choices and the address of a new design on this start.
function test_site_plan_screen_place()
{
	$html = pg_designer_describe_pane(true);
	pg_assert_true(preg_match('/<section class="sd-sp" hidden aria-labelledby="sd-sp-title" data-texts="[^"]+" data-theme="[^"]+" data-limits="[^"]+" data-href="([^"]+)">/', $html, $m) === 1, 'the plan screen\'s place');
	pg_assert_same(h('add_system_style.php?framework=bootstrap5&start=describe&from=pages'), $m[1], 'opens a Bootstrap 5 design on this start');
	pg_assert_same('add_system_style.php?framework=bootstrap5&start=describe', pg_designer_start_go_href('describe', 'bootstrap5', false), 'its address');

	$texts = pg_site_plan_texts();
	foreach (array('heading', 'lead', 'name', 'sections', 'menuLabel', 'up', 'down', 'remove', 'add', 'newPage', 'theme', 'drafts', 'tooMany', 'nameEmpty', 'noPages', 'back', 'make', 'planning', 'network') as $key) {
		pg_assert_true(isset($texts[$key]) && $texts[$key] !== '', $key . ': its words');
	}
	pg_assert_same(pg_site_plan_menus(), array_keys($texts['menus']), 'a name a menu place');

	$choices = pg_site_plan_theme_choices();
	pg_assert_same(array('brand', 'accent', 'look', 'type', 'room'), array_keys($choices['steps']), 'the five questions');
	pg_assert_same(array_keys(pg_design_looks()), array_column($choices['looks'], 'key'), 'every look');
	pg_assert_same(array('clean', 'classic', 'friendly', 'technical'), array_column($choices['types'], 'key'), 'the type feels');
	pg_assert_same(array('roomy', 'compact'), array_column($choices['rooms'], 'key'), 'the rooms');
}

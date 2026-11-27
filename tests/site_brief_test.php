<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the brief of the "Describe it" start of the New design window:
 * the pure checks of includes/fn/site_brief.php, their twin in
 * assets/js/designer_describe.js (run in Node when it is there), the
 * start's row, its disabled state and its pane.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_brief.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

function _site_brief_ok()
{
	return array(
		'name' => '  Ekmek   Evi ', 'what' => "We bake sourdough bread  \n\n\n\n and cakes to order.", 'kind' => 'shop',
		'customers' => 'Families nearby', 'tone' => 'formal', 'pages' => array('home', 'About', 'about', ' ', 'Our ovens'), 'colour' => '2563EB',
	);
}

// The briefs both checks are run on, with the codes they must give.
function _site_brief_cases()
{
	$ok = _site_brief_ok();
	$with = function ($changes) use ($ok) {
		return array_merge($ok, $changes);
	};

	return array(
		'ok'          => array($ok, array()),
		'empty'       => array(array(), array('name' => 'name', 'what' => 'what', 'pages' => 'pages')),
		'not a brief' => array('text', array('name' => 'name', 'what' => 'what', 'pages' => 'pages')),
		'long name'   => array($with(array('name' => str_repeat('ü', 81))), array('name' => 'nameLong')),
		'short what'  => array($with(array('what' => 'Bread.')), array('what' => 'what')),
		'long what'   => array($with(array('what' => str_repeat('a', 2001))), array('what' => 'whatLong')),
		'customers'   => array($with(array('customers' => str_repeat('a', 301))), array('customers' => 'customers')),
		'many pages'  => array($with(array('pages' => array_map(function ($n) { return 'Page ' . $n; }, range(1, 13)))), array('pages' => 'pagesMany')),
		'long page'   => array($with(array('pages' => array('home', str_repeat('a', 41)))), array('pages' => 'pageLong')),
		'no pages'    => array($with(array('pages' => array('', '  '))), array('pages' => 'pages')),
		'bad colour'  => array($with(array('colour' => 'blue')), array('colour' => 'colour')),
		'no colour'   => array($with(array('colour' => '  ')), array()),
		'odd kind'    => array($with(array('kind' => 'castle', 'tone' => 'loud')), array()),
	);
}

function test_site_brief_check_cleans()
{
	$r = pg_site_brief_check(_site_brief_ok());
	pg_assert_same(array(), $r['errors'], 'a good brief');
	pg_assert_same(array(
		'name' => 'Ekmek Evi', 'what' => "We bake sourdough bread\n\nand cakes to order.", 'kind' => 'shop', 'customers' => 'Families nearby',
		'tone' => 'formal', 'pages' => array('home', 'About', 'Our ovens'), 'colour' => '#2563eb',
	), $r['brief'], 'spaces joined, a page once in any case, the colour as #rrggbb');

	$odd = pg_site_brief_check(array_merge(_site_brief_ok(), array('kind' => 'castle', 'tone' => 'loud')));
	pg_assert_same('other', $odd['brief']['kind'], 'an unknown kind is other');
	pg_assert_same('friendly', $odd['brief']['tone'], 'an unknown tone is the first');
}

function test_site_brief_check_codes_and_words()
{
	foreach (_site_brief_cases() as $name => $case) {
		$r = pg_site_brief_check($case[0]);
		pg_assert_same($case[1], $r['codes'], $name . ': the fields to change');
		pg_assert_same(array_keys($case[1]), array_keys($r['errors']), $name . ': words for each');
	}
	$texts = pg_site_brief_texts();
	foreach (array('name', 'nameLong', 'what', 'whatLong', 'customers', 'pages', 'pagesMany', 'pageLong', 'colour') as $code) {
		pg_assert_true(isset($texts['errors'][$code]) && $texts['errors'][$code] !== '', $code . ': its words');
	}
	pg_assert_false(strpos($texts['errors']['what'], '{var:') !== false, 'the limits are written in');
}

function test_site_brief_colour()
{
	pg_assert_same('#2563eb', pg_site_brief_colour('#2563EB'), 'lower case');
	pg_assert_same('#2563eb', pg_site_brief_colour(' 2563eb '), 'the # may be left out');
	pg_assert_same('', pg_site_brief_colour('#fff'), 'three digits are not taken');
	pg_assert_same('', pg_site_brief_colour(array('#2563eb')), 'not text');
}

// Every kind starts with Home and Contact-like pages from the chips' list.
function test_site_brief_pages_for()
{
	$keys = pg_site_brief_page_keys();
	foreach (pg_launch_journey_purposes() as $purpose) {
		$pages = pg_site_brief_pages_for($purpose);
		pg_assert_same('home', $pages[0], $purpose . ': Home first');
		pg_assert_same(array(), array_values(array_diff($pages, $keys)), $purpose . ': chips the pane shows');
		pg_assert_true(count($pages) <= pg_site_brief_limits()['pages'], $purpose . ': within the limit');
	}
	pg_assert_same(pg_site_brief_pages_for('other'), pg_site_brief_pages_for('castle'), 'an unknown kind');
	pg_assert_same($keys, array_keys(pg_site_brief_page_names()), 'a name a chip');
}

// The browser's check gives what the server's gives.
function test_site_brief_check_twin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$cases = _site_brief_cases();
	$inputs = array();
	foreach ($cases as $name => $case) {
		$inputs[$name] = $case[0];
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_describe.js');
	$script = "var window = {};\n" . $js . "\nvar d = window.sdStartParts.describe;\n"
		. 'var inputs = ' . json_encode($inputs) . ";\n"
		. 'var limits = ' . json_encode(pg_site_brief_limits()) . ";\n"
		. "var out = {};\nObject.keys(inputs).forEach(function (k) { out[k] = d.check(inputs[k], limits); });\n"
		. "console.log(JSON.stringify(out));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgsb') . '.js';
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $lines));
	$r = json_decode(implode("\n", $lines), true);

	foreach ($cases as $name => $case) {
		$php = pg_site_brief_check($case[0]);
		pg_assert_same($php['brief'], $r[$name]['brief'], $name . ': the same brief');
		pg_assert_same($php['codes'], is_array($r[$name]['codes']) ? $r[$name]['codes'] : array(), $name . ': the same codes');
	}
}

// "Describe it" sits in "Start new" after Template; without Pinegrap AI it
// is shown disabled with the reason, as Template without templates.
function test_site_brief_start_row()
{
	$kinds = pg_designer_start_kinds(true, false, '');
	$keys = array_map(function ($k) { return $k['key']; }, $kinds);
	pg_assert_same(array('blank', 'template', 'describe', 'canva', 'document', 'import', 'paste'), $keys, 'after Template');
	pg_assert_same('new', $kinds[2]['group'], 'in Start new');
	pg_assert_same('bi-stars', $kinds[2]['icon'], 'its icon');
	pg_assert_false($kinds[2]['disabled'], 'open with Pinegrap AI');
	pg_assert_same('', $kinds[2]['go'], 'its part draws the foot');
	pg_assert_same('describe', pg_designer_start_key('describe', $kinds), '?start=describe');

	$off = pg_designer_start_kinds(true, false, 'Pinegrap AI is not set up.');
	pg_assert_true($off[2]['disabled'], 'disabled without Pinegrap AI');
	pg_assert_same('blank', pg_designer_start_key('describe', $off), '?start=describe falls back to Blank');

	$html = pg_designer_start_window(array(), false, 'Pinegrap AI is not set up.');
	pg_assert_true(preg_match('/<button[^>]*id="sd-ns-tab-describe"[^>]*aria-disabled="true" title="Pinegrap AI is not set up\."/', $html) === 1, 'the reason on the row');
	pg_assert_contains(h(lang('Needs Pinegrap AI')), $html);
	pg_assert_false(strpos($html, 'class="sd-sb"') !== false, 'no fields while disabled');
	pg_assert_contains(h(lang('There are no templates.')), $html);

	$on = pg_designer_start_window(array(), false, '');
	pg_assert_true(preg_match('/id="sd-ns-pane-describe"[^>]*data-part="describe" data-part-stamp="[^"]*"/', $on) === 1, 'the pane loads its part');
}

// The pane: every field of the brief, the kind's pages pressed, the
// language as a line.
function test_site_brief_pane()
{
	$html = pg_site_brief_pane('Ekmek "Evi"', 'clinic', 'English');
	foreach (array('name', 'kind', 'what', 'customers', 'colour', 'page') as $id) {
		pg_assert_contains('id="sd-sb-' . $id . '"', $html);
	}
	pg_assert_contains('value="Ekmek &quot;Evi&quot;"', $html);
	pg_assert_true(preg_match('/<option value="clinic" selected>/', $html) === 1, 'the set-up card\'s purpose chosen');
	pg_assert_same(3, substr_count($html, 'data-tone='), 'three tones');
	pg_assert_true(preg_match('/data-tone="friendly" aria-pressed="true"/', $html) === 1, 'friendly first');
	pg_assert_same(count(pg_site_brief_page_keys()), substr_count($html, 'class="pg-button pg-button-quiet pg-button-sm sd-sb-chip"'), 'a chip a page');
	foreach (pg_site_brief_pages_for('clinic') as $key) {
		pg_assert_true(strpos($html, 'data-page="' . $key . '" aria-pressed="true"') !== false, $key . ': pressed for a clinic');
	}
	pg_assert_true(strpos($html, 'data-page="products" aria-pressed="false"') !== false, 'products not pressed for a clinic');
	pg_assert_contains('English', $html);
	pg_assert_same(0, preg_match('/\bbtn btn-|nav-tabs|nav-pills|form-control/', $html), 'look A parts only');
	$none = pg_site_brief_pane('', '', 'English');
	pg_assert_true(preg_match('/<option value="other" selected>/', $none) === 1, 'no purpose: other');
}

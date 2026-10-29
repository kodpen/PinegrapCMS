<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visual Page Editor home (view_system_styles.php): which
 * design is shown large (pg_designer_home_featured_id()) and how a name
 * typed into the rename box is stored (pg_designer_design_name_clean()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

function _test_designer_home_row($id, $home)
{
	return array('style_id' => $id, 'home_count' => $home);
}

function test_designer_home_featured_is_the_only_design()
{
	pg_assert_same(7, pg_designer_home_featured_id(array(_test_designer_home_row(7, 0))), 'one design, home page or not');
	pg_assert_same(0, pg_designer_home_featured_id(array()), 'no designs');
}

function test_designer_home_featured_is_the_one_with_the_home_page()
{
	$designs = array(_test_designer_home_row(3, 0), _test_designer_home_row(5, 1), _test_designer_home_row(9, 0));
	pg_assert_same(5, pg_designer_home_featured_id($designs), 'the design the home page is in');
}

function test_designer_home_featured_is_none_without_a_single_holder()
{
	pg_assert_same(0, pg_designer_home_featured_id(array(_test_designer_home_row(3, 0), _test_designer_home_row(5, 0))), 'none holds the home page');
	pg_assert_same(0, pg_designer_home_featured_id(array(_test_designer_home_row(3, 1), _test_designer_home_row(5, 2))), 'two hold one');
}

function test_designer_home_name_clean_keeps_one_line()
{
	pg_assert_same('Kafe sitesi', pg_designer_design_name_clean("  Kafe \t\n  sitesi  "), 'spaces, tabs and line breaks become one space');
	pg_assert_same('Yaz İndirimi', pg_designer_design_name_clean("Yaz\x07\x00İndirimi"), 'control characters become a space');
	pg_assert_same('', pg_designer_design_name_clean('   '), 'only spaces');
}

function test_designer_home_name_clean_cuts_at_the_column_size()
{
	$long = str_repeat('ş', 120);
	pg_assert_same(100, mb_strlen(pg_designer_design_name_clean($long), 'UTF-8'), 'one hundred characters, not bytes');
	pg_assert_same('abc', pg_designer_design_name_clean(str_repeat(' ', 5) . 'abc'), 'leading spaces do not count');
}

function test_designer_home_name_clean_refuses_what_is_not_text()
{
	pg_assert_same('', pg_designer_design_name_clean(array('a')), 'an array');
	pg_assert_same('', pg_designer_design_name_clean(null), 'null');
	pg_assert_same('', pg_designer_design_name_clean("\xC3\x28"), 'broken UTF-8');
	pg_assert_same('12', pg_designer_design_name_clean(12), 'a number is its digits');
}

// The start drawings are the panel's greys and its one accent: every shape
// takes its colour from a class in designer_home.css, none carries one.
function _test_designer_home_drawing_classes()
{
	return array('p', 'g', 'l', 'k', 's', 'sk', 'dash', 'cursor', 'a', 'as');
}

function test_designer_home_drawings_carry_no_colour_of_their_own()
{
	foreach (array('blank', 'import', 'paste', 'template') as $kind) {
		$svg = pg_designer_home_drawing($kind, array());
		pg_assert_true(strpos($svg, '<svg') !== false, $kind . ': a drawing');
		pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|style=|stop-color|flood-color/i', $svg), $kind . ': no colour value');
		preg_match_all('/\s(fill|stroke)="([^"]*)"/', $svg, $m);
		foreach ($m[2] as $value) {
			pg_assert_true(in_array($value, array('none', 'currentColor'), true), $kind . ': fill or stroke "' . $value . '" is none or currentColor');
		}
		pg_assert_same(0, preg_match('/(fill|stroke)-opacity=/', $svg), $kind . ': no opacity tint');
	}
}

function test_designer_home_drawings_use_only_the_token_classes()
{
	$allowed = _test_designer_home_drawing_classes();
	foreach (array('blank', 'import', 'paste', 'template') as $kind) {
		$svg = pg_designer_home_drawing($kind, array());
		$inner = substr($svg, strpos($svg, '<svg'));
		preg_match_all('/class="([^"]*)"/', $inner, $m);
		pg_assert_true(count($m[1]) > 0, $kind . ': shapes have classes');
		foreach ($m[1] as $list) {
			foreach (preg_split('/\s+/', trim($list)) as $class) {
				pg_assert_true(in_array($class, $allowed, true), $kind . ': class "' . $class . '" is a drawing token');
			}
		}
	}
}

function test_designer_home_drawings_hold_at_most_the_one_accent()
{
	foreach (array('blank', 'import', 'paste') as $kind) {
		$svg = pg_designer_home_drawing($kind, array());
		preg_match_all('/class="(?:a|as)"/', $svg, $m);
		pg_assert_true(count($m[0]) >= 1 && count($m[0]) <= 2, $kind . ': one accented part (a fill and its soft surface)');
	}
	$none = pg_designer_home_drawing('template', array());
	pg_assert_same(0, preg_match('/class="(?:a|as)"/', $none), 'no template to show: no accent');
}

function _test_designer_home_status_row($pages, $drafts, $home)
{
	return array('page_count' => $pages, 'draft_count' => $drafts, 'home_count' => $home, 'scheduled_count' => 0);
}

// A state is words and a mark of its own shape, not a coloured badge.
function test_designer_home_status_is_words_and_a_shaped_mark()
{
	$cases = array(
		'live'  => array(_test_designer_home_status_row(3, 0, 1), 'bi-circle-fill'),
		'draft' => array(_test_designer_home_status_row(3, 3, 0), 'bi-circle"'),
		'mixed' => array(_test_designer_home_status_row(3, 1, 1), 'bi-circle-half'),
		'empty' => array(_test_designer_home_status_row(0, 0, 0), 'bi-dash-circle-dotted'),
	);
	foreach ($cases as $state => $case) {
		$html = pg_designer_list_status_cell($case[0]);
		pg_assert_true(strpos($html, 'data-state="' . $state . '"') !== false, $state . ': the state rides along for the script');
		pg_assert_true(strpos($html, 'class="sdh-state"><i class="bi ' . rtrim($case[1], '"')) !== false, $state . ': its own mark');
		pg_assert_same(0, preg_match('/\bbadge\b|theme-(success|warning|info|danger|secondary)/', $html), $state . ': no coloured badge');
		pg_assert_true(preg_match('/<\/i>[^<]+<\/span>/', $html) === 1, $state . ': the state in words');
	}
}

// Every start card: its drawing, then the icon and title, then one sentence.
function test_designer_home_start_card_order()
{
	$html = pg_designer_home_start_card(pg_designer_home_drawing('blank'), 'bi-file-earmark-plus', 'Create New', 'An empty page.', array('modal' => '#sdNewDesignModal'));
	$ill = strpos($html, 'sdh-ill');
	$title = strpos($html, 'sdh-start-title');
	$text = strpos($html, 'sdh-start-text');
	pg_assert_true($ill !== false && $title > $ill && $text > $title, 'drawing, title, sentence');
	pg_assert_true(strpos($html, '<div class="col-6 xl:col-3">') === 0, 'two by two on phones, four in a row on wide screens');
}

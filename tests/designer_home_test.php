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
	$html = pg_designer_home_start_card(pg_designer_home_drawing('blank'), 'bi-file-earmark-plus', 'Create New', 'An empty page.', array('modal' => '#sdStartModal', 'start' => 'blank'));
	$ill = strpos($html, 'sdh-ill');
	$title = strpos($html, 'sdh-start-title');
	$text = strpos($html, 'sdh-start-text');
	pg_assert_true($ill !== false && $title > $ill && $text > $title, 'drawing, title, sentence');
	pg_assert_true(strpos($html, '<div class="col-6 xl:col-3">') === 0, 'two by two on phones, four in a row on wide screens');
}

function test_designer_home_template_card_theme_follows_the_choice()
{
	$own = array('look' => 'organic', 'palette' => 'cocoa');
	pg_assert_same(array('look' => 'organic', 'palette' => 'cocoa'), pg_designer_template_card_theme($own, '@template', '@template'), 'the template\'s own theme');
	pg_assert_same(array('look' => 'minimal', 'palette' => 'cocoa'), pg_designer_template_card_theme($own, 'minimal', '@template'), 'another look, its own palette');
	pg_assert_same(array('look' => '', 'palette' => ''), pg_designer_template_card_theme($own, '', ''), 'plain Bootstrap');
	// A template that names no look opens in the default look; no palette named stays plain.
	pg_assert_same(array('look' => pg_design_default_look(), 'palette' => ''), pg_designer_template_card_theme(array('look' => null, 'palette' => null), '@template', '@template'), 'nothing named');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($source, "' data-look=\"' . h((\$s['look'] !== null) ? (string)\$s['look'] : \$look0)") !== false, 'the card tells the script the default look when the template names none');
}

function test_designer_home_template_picture_address()
{
	$tpl = null;
	foreach (pg_design_templates() as $t) {
		if (!empty($t['builtin']) && pg_design_template_preview_pages($t)) { $tpl = $t; break; }
	}
	pg_assert_true($tpl !== null, 'a shipped template with pages');
	$src = pg_designer_template_picture_src($tpl, 'organic', 'cocoa');
	pg_assert_contains('design_preview.php?template=' . $tpl['id'] . '&still=1&look=organic&palette=cocoa', $src);
	pg_assert_false(strpos($src, '&page=') !== false, 'the home page: no page key');
	// The script's twin builds the same address from the card's base.
	$base = pg_design_preview_address('panel', array('template' => $tpl['id'], 'still' => true));
	pg_assert_same($src, pg_designer_template_theme_query($base, 'organic', 'cocoa'));
	pg_assert_same($base, pg_designer_template_theme_query($base, 'nope', 'file-3'), 'unknown look and file palette dropped');
	// No page a preview can show: the drawing stays.
	$empty = $tpl;
	$empty['pages'] = array();
	pg_assert_same('', pg_designer_template_picture_src($empty));
	pg_assert_same('', pg_designer_template_picture_src(null));
}

function test_designer_home_design_picture_is_the_page_edit_opens()
{
	pg_assert_contains('design_preview.php?page_id=12', pg_designer_design_picture_src(array('id' => 12, 'locked' => false)));
	pg_assert_same('', pg_designer_design_picture_src(null), 'no page to open');
	pg_assert_same('', pg_designer_design_picture_src(array('id' => 0)), 'no id');
	$d = array('style_name' => 'Kafe', 'template' => '', 'framework_bootstrap' => true, 'look' => '', 'palette' => '');
	$html = pg_designer_list_thumb($d, array('id' => 12));
	pg_assert_contains('data-pg-live-picture', $html);
	pg_assert_contains('pg-live-picture-fallback', $html);
	pg_assert_contains('sdh-blank', $html);
	$none = pg_designer_list_thumb($d, null);
	pg_assert_false(strpos($none, 'data-pg-live-picture') !== false, 'no page: the outline alone');
	pg_assert_contains('sdh-blank', $none);
}

// The New design window lists its starts in one order; a start a later step
// adds takes its place in this list.
function test_designer_start_kinds_order()
{
	$keys = array_map(function ($k) { return $k['key']; }, pg_designer_start_kinds(true));
	pg_assert_same(array('blank', 'template', 'import', 'paste'), $keys, 'blank, template, ZIP file, pasted markup');
	foreach (pg_designer_start_kinds(true) as $k) {
		pg_assert_true($k['title'] !== '' && $k['line'] !== '' && $k['icon'] !== '', $k['key'] . ': a title, one line and an icon');
		pg_assert_same(false, $k['disabled'], $k['key'] . ': open while there are templates');
	}
}

function test_designer_start_kinds_template_waits_for_templates()
{
	foreach (pg_designer_start_kinds(false) as $k) {
		pg_assert_same($k['key'] === 'template', $k['disabled'], $k['key'] . ': only the template start waits for a template');
	}
}

// ?start= and a card's data-sd-start name the start the window opens on.
function test_designer_start_key_falls_back_to_blank()
{
	$kinds = pg_designer_start_kinds(true);
	pg_assert_same('import', pg_designer_start_key('import', $kinds), 'a known start');
	pg_assert_same('paste', pg_designer_start_key('paste', $kinds), 'a known start');
	pg_assert_same('blank', pg_designer_start_key('figma', $kinds), 'a start not built yet');
	pg_assert_same('blank', pg_designer_start_key(array('x'), $kinds), 'not text');
	pg_assert_same('blank', pg_designer_start_key('template', pg_designer_start_kinds(false)), 'a start that waits');
}

// Where the window's main button leads for a start and a framework.
function test_designer_start_go_href()
{
	pg_assert_same('add_system_style.php?framework=bootstrap5', pg_designer_start_go_href('blank', 'bootstrap5', false), 'blank');
	pg_assert_same('add_system_style.php?framework=custom&from=pages', pg_designer_start_go_href('blank', 'custom', true), 'from the pages screen');
	pg_assert_same('add_system_style.php?framework=bootstrap5&start=import', pg_designer_start_go_href('import', 'bootstrap5', false), 'a ZIP file');
	pg_assert_same('add_system_style.php?framework=custom&start=paste', pg_designer_start_go_href('paste', 'custom', false), 'pasted markup');
	pg_assert_same('add_system_style.php?framework=bootstrap5', pg_designer_start_go_href('blank', 'tailwind', false), 'an unknown framework is Bootstrap 5');
	pg_assert_same('', pg_designer_start_go_href('template', 'bootstrap5', false), 'a template card carries its own address');
}

// The window: one tab a start, each with its drawing, title and line, each
// pointing at its own pane; one filled button in the foot.
function test_designer_start_window_markup()
{
	$html = pg_designer_start_window(array(), false);
	pg_assert_true(strpos($html, 'id="sdStartModal"') !== false, 'the window');
	pg_assert_same(1, preg_match_all('/role="tablist"/', $html), 'one list of starts');
	foreach (pg_designer_start_kinds(false) as $k) {
		$tab = 'id="sd-ns-tab-' . $k['key'] . '"';
		pg_assert_true(strpos($html, $tab) !== false, $k['key'] . ': its tab');
		pg_assert_true(strpos($html, 'id="sd-ns-pane-' . $k['key'] . '"') !== false, $k['key'] . ': its pane');
		pg_assert_true(preg_match('/<button[^>]*' . preg_quote($tab, '/') . '[^>]*>.*?<svg.*?<\/button>/s', $html) === 1, $k['key'] . ': a drawing in its tab');
	}
	pg_assert_same(1, preg_match_all('/pg-button-fill" id="sd-ns-go"/', $html), 'one filled button in the foot');
	pg_assert_same(0, preg_match('/\bbtn btn-/', substr($html, 0, (int) strpos($html, 'id="sd-ns-pane-template"'))), 'look A buttons only');
	pg_assert_true(strpos($html, 'data-href="add_system_style.php?framework=custom&amp;start=import"') !== false, 'a framework choice carries its address');
}

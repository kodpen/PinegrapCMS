<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the edit chip a signed-in editor sees on a record
 * (pg_sw_add_edit_chip(), includes/fn/widgets.php): its look and its place
 * are written on the chip itself with !important, so a design's rules for
 * links, svg and images cannot turn it into a picture-sized pencil; no
 * stylesheet travels with a record; and the page gets the small stylesheet
 * for hover, focus and print once, in its head
 * (pg_sw_edit_chip_css_inject()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The style attribute of the first element with this tag in $html, '' when
// it has none.
function _widget_edit_chip_style_of($html, $tag)
{
	if (!preg_match('/<' . $tag . '\b[^>]*\sstyle="([^"]*)"/i', $html, $m)) return '';
	return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

// Each declaration of a style attribute, property => value (with any
// !important kept on the value).
function _widget_edit_chip_declarations($style)
{
	$out = array();
	foreach (explode(';', $style) as $part) {
		$part = trim($part);
		if ($part === '' || strpos($part, ':') === false) continue;
		list($property, $value) = explode(':', $part, 2);
		$out[strtolower(trim($property))] = trim($value);
	}
	return $out;
}

function _widget_edit_chip_card()
{
	return '<div class="col"><article class="card"><img src="/cover.jpg" alt=""><h3>Issue</h3></article></div>';
}

// A record carries the chip, never a stylesheet: a stylesheet written with
// the first chip of a request went away with a list that drew its rows and
// was then hidden, and every later chip lost its look.
function test_widget_edit_chip_no_stylesheet_in_the_record()
{
	$first  = pg_sw_add_edit_chip(_widget_edit_chip_card(), '/pinegrap/edit_submitted_form.php?id=1', 'Edit the record', 'forms');
	$second = pg_sw_add_edit_chip(_widget_edit_chip_card(), '/pinegrap/edit_submitted_form.php?id=2', 'Edit the record', 'forms');
	pg_assert_false(stripos($first, '<style') !== false);
	pg_assert_false(stripos($second, '<style') !== false);
	pg_assert_same(0, strpos($first, '<div class="col pg-edit-host"><a class="pg-edit-chip pg-edit-chip-forms" '));
}

// The chip's box: a 1.9rem circle taken out of the flow, .5rem inside the
// record's top-left corner in either direction (right auto: a chip that
// followed a right-to-left page would cover the comment card's share button,
// which Bootstrap's physical utilities keep top right), every property a
// design sets on links and inline elements pinned with !important.
function test_widget_edit_chip_box_is_inline_and_important()
{
	$out = pg_sw_add_edit_chip(_widget_edit_chip_card(), '/e?id=1', 'Edit the record', 'forms');
	$d = _widget_edit_chip_declarations(_widget_edit_chip_style_of($out, 'a'));
	$expect = array(
		'position' => 'absolute', 'top' => '.5rem', 'right' => 'auto', 'bottom' => 'auto', 'left' => '.5rem',
		'z-index' => '6', 'display' => 'flex', 'box-sizing' => 'border-box',
		'width' => '1.9rem', 'height' => '1.9rem', 'min-width' => '0', 'min-height' => '0',
		'max-width' => 'none', 'max-height' => 'none', 'margin' => '0', 'padding' => '0',
		'border' => '0', 'border-radius' => '50%', 'color' => '#fff', 'font-size' => '1rem',
		'line-height' => '1', 'text-decoration' => 'none', 'float' => 'none',
		'justify-self' => 'start', 'align-self' => 'start',
	);
	foreach ($expect as $property => $value) {
		pg_assert_same($value . '!important', isset($d[$property]) ? $d[$property] : '(missing ' . $property . ')');
	}
}

// The fade and the hover zoom stay plain declarations: the page's
// stylesheet raises them with !important on hover and focus, which an
// !important style attribute would not let it do. A design's own rules
// still lose to a style attribute.
function test_widget_edit_chip_fade_is_left_to_the_stylesheet()
{
	$out = pg_sw_add_edit_chip(_widget_edit_chip_card(), '/e?id=1', 'Edit', 'page');
	$d = _widget_edit_chip_declarations(_widget_edit_chip_style_of($out, 'a'));
	pg_assert_same('.55', isset($d['opacity']) ? $d['opacity'] : '');
	pg_assert_same('none', isset($d['transform']) ? $d['transform'] : '');
	pg_assert_false(isset($d['visibility']));
	pg_assert_false(isset($d['outline']));
}

// The pencil keeps its own size: a design's "svg {width:100%}" made it as
// wide as the card's picture.
function test_widget_edit_chip_svg_is_inline_and_important()
{
	$out = pg_sw_add_edit_chip(_widget_edit_chip_card(), '/e?id=1', 'Edit', 'ecommerce');
	$d = _widget_edit_chip_declarations(_widget_edit_chip_style_of($out, 'svg'));
	$expect = array(
		'display' => 'block', 'width' => '.9rem', 'height' => '.9rem', 'max-width' => 'none',
		'max-height' => 'none', 'margin' => '0', 'position' => 'static', 'fill' => 'currentColor',
		'pointer-events' => 'none', 'float' => 'none', 'transform' => 'none',
	);
	foreach ($expect as $property => $value) {
		pg_assert_same($value . '!important', isset($d[$property]) ? $d[$property] : '(missing ' . $property . ')');
	}
	$p = _widget_edit_chip_declarations(_widget_edit_chip_style_of($out, 'path'));
	pg_assert_same('currentColor!important', isset($p['fill']) ? $p['fill'] : '');
}

// Each tone keeps its colour; an unknown tone is the page's.
function test_widget_edit_chip_tone_colours()
{
	$tones = array(
		'page'      => 'rgba(25,118,210,.92)!important',
		'ecommerce' => 'rgba(85,139,47,.94)!important',
		'forms'     => 'rgba(128,95,167,.94)!important',
		'calendar'  => 'rgba(93,64,55,.94)!important',
		'other'     => 'rgba(25,118,210,.92)!important',
	);
	foreach ($tones as $tone => $background) {
		$out = pg_sw_add_edit_chip('<div>x</div>', '/e?id=1', 'Edit', $tone);
		$d = _widget_edit_chip_declarations(_widget_edit_chip_style_of($out, 'a'));
		pg_assert_same($background, isset($d['background']) ? $d['background'] : '');
		pg_assert_contains('pg-edit-chip-' . ($tone === 'other' ? 'page' : $tone) . '"', $out);
	}
}

// The record only gets the class: a position written on it would override
// the design's own positioning of the record.
function test_widget_edit_chip_host_gets_no_style()
{
	$out = pg_sw_add_edit_chip('<article class="card" style="color:red">x</article>', '/e?id=1', 'Edit', 'page');
	pg_assert_same(0, strpos($out, '<article class="card pg-edit-host" style="color:red"><a '));
	$out = pg_sw_add_edit_chip('<section>x</section>', '/e?id=1', 'Edit', 'page');
	pg_assert_same(0, strpos($out, '<section class="pg-edit-host"><a '));
}

// The address and the title are escaped once.
function test_widget_edit_chip_escapes_url_and_title()
{
	$out = pg_sw_add_edit_chip('<div>x</div>', '/e.php?id=1&send_to=%2Fa', 'Edit "this"', 'page');
	pg_assert_contains('href="/e.php?id=1&amp;send_to=%2Fa"', $out);
	pg_assert_contains('title="Edit &quot;this&quot;"', $out);
	pg_assert_contains('aria-label="Edit &quot;this&quot;"', $out);
}

// A record that cannot hold a link, or has no address to go to, is left as
// it is.
function test_widget_edit_chip_skips_what_cannot_hold_it()
{
	foreach (array('<a href="/p">x</a>', '<button>x</button>', '<tr><td>x</td></tr>', '<img src="/a.jpg">', 'text only') as $html) {
		pg_assert_same($html, pg_sw_add_edit_chip($html, '/e?id=1', 'Edit', 'page'));
	}
	pg_assert_same('<div>x</div>', pg_sw_add_edit_chip('<div>x</div>', '', 'Edit', 'page'));
}

// The page's stylesheet: only what a style attribute cannot say.
function test_widget_edit_chip_stylesheet_rules()
{
	$css = pg_sw_edit_chip_css();
	pg_assert_same(0, strpos($css, '<style id="pg-edit-chip-css">'));
	// Zero specificity: a design that positions the record wins.
	pg_assert_contains(':where(.pg-edit-host){position:relative}', $css);
	pg_assert_contains('.pg-edit-host:hover>.pg-edit-chip,.pg-edit-chip:focus-visible{opacity:1!important}', $css);
	pg_assert_contains('.pg-edit-chip:hover{transform:scale(1.08)!important}', $css);
	pg_assert_contains('@media print{.pg-edit-chip{visibility:hidden!important}}', $css);
	pg_assert_contains('.pg-edit-chip::before,.pg-edit-chip::after{content:none!important;display:none!important}', $css);
}

// Once per page, in its head, and only on a page that shows a chip.
function test_widget_edit_chip_css_inject_once_in_head()
{
	$chip = pg_sw_add_edit_chip('<div>x</div>', '/e?id=1', 'Edit', 'page');
	$page = '<html><head><title>t</title></head><body>' . $chip . $chip . '</body></html>';
	$once = pg_sw_edit_chip_css_inject($page);
	pg_assert_same(1, substr_count($once, 'id="pg-edit-chip-css"'));
	pg_assert_contains(pg_sw_edit_chip_css() . '</head>', $once);
	pg_assert_same($once, pg_sw_edit_chip_css_inject($once));

	$plain = '<html><head></head><body><p class="pg-edit-chip-like">x</p></body></html>';
	pg_assert_same($plain, pg_sw_edit_chip_css_inject($plain));

	pg_assert_same(pg_sw_edit_chip_css() . $chip, pg_sw_edit_chip_css_inject($chip));
}

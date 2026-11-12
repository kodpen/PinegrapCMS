<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the pages of the ferry to photo design templates fit a
 * browser: a long site name ends with an ellipsis in the header; the
 * headers whose links ran past the container open out from 1200 px; small
 * coloured text on a tertiary band is mixed darker or lighter with the
 * theme; an image's width and height are props (the renderer drops them
 * from _attrs); a paragraph holds no block; every sideways scroll box is
 * positioned; the notice line wraps as text; the ferry wake keeps clear of
 * the text around it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The templates and the prefix of their User Styles classes.
function _test_tpl_fit_ids()
{
	return array(
		'pinegrap-ferry' => 'pfy', 'pinegrap-filmstudio' => 'pfm', 'pinegrap-florist' => 'pfl',
		'pinegrap-gallery' => 'pgl', 'pinegrap-gym' => 'pgy', 'pinegrap-homeservices' => 'phs',
		'pinegrap-hotel' => 'pht', 'pinegrap-launch' => 'plx', 'pinegrap-law' => 'plw',
		'pinegrap-library' => 'plb', 'pinegrap-marina' => 'pmn', 'pinegrap-market' => 'pmk',
		'pinegrap-museum' => 'pmz', 'pinegrap-music' => 'pmu', 'pinegrap-pharmacy' => 'prx',
		'pinegrap-photo' => 'pph',
	);
}

function _test_tpl_fit_tpl($id)
{
	static $cache = array();
	if (!isset($cache[$id])) $cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	return $cache[$id];
}

// The template's User Styles.
function _test_tpl_fit_css($tpl)
{
	$styles = json_decode($tpl['assets']['css'], true);
	return $styles[0]['content'];
}

// Calls $fn($node, $parent) for every node of the trees in $value.
function _test_tpl_fit_walk($value, $fn, $parent = null)
{
	if (!is_array($value)) return;
	if (isset($value['type']) && isset($value['props']) && is_array($value['props'])) {
		$fn($value, $parent);
		$parent = $value;
	}
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_fit_walk($child, $fn, $parent);
	}
}

// The class list of a node, padded with spaces, or ''.
function _test_tpl_fit_class($node)
{
	return isset($node['props']['cssClass']) && is_string($node['props']['cssClass']) ? ' ' . $node['props']['cssClass'] . ' ' : '';
}

// Every page, widget and shared part tree of a template.
function _test_tpl_fit_trees($tpl)
{
	return array($tpl['pages'], $tpl['widgets'], $tpl['shared']);
}

function test_design_template_fit_brand_ellipsis()
{
	foreach (_test_tpl_fit_ids() as $id => $prefix) {
		$tpl = _test_tpl_fit_tpl($id);
		pg_assert_contains('.navbar-brand{min-width:0;max-width:calc(100% - 5rem)}', _test_tpl_fit_css($tpl), $id . ': the brand may shrink');
		$truncated = 0;
		_test_tpl_fit_walk($tpl['shared']['header']['tree'], function ($n) use (&$truncated) {
			if (strpos(_test_tpl_fit_class($n), ' navbar-brand ') === false) return;
			if (strpos(json_encode($n), 'text-truncate') !== false) $truncated++;
		});
		pg_assert_true($truncated > 0, $id . ': the name in the header ends with an ellipsis');
	}
}

// From 992 px the brand, the links and the button ran past the container
// in Turkish; these headers open out from 1200 px.
function test_design_template_fit_open_bar()
{
	$lg = array('pinegrap-filmstudio', 'pinegrap-music', 'pinegrap-photo');
	foreach (_test_tpl_fit_ids() as $id => $prefix) {
		$bars = array();
		_test_tpl_fit_walk(_test_tpl_fit_tpl($id)['shared']['header']['tree'], function ($n) use (&$bars) {
			if (preg_match('/ (navbar-expand-[a-z]+) /', _test_tpl_fit_class($n), $m)) $bars[] = $m[1];
		});
		pg_assert_same(array(in_array($id, $lg, true) ? 'navbar-expand-lg' : 'navbar-expand-xl'), $bars, $id . ': the open bar');
		if (in_array($id, $lg, true)) continue;
		$early = array();
		_test_tpl_fit_walk(_test_tpl_fit_tpl($id)['shared']['header']['tree'], function ($n) use (&$early) {
			if (preg_match('/ [a-z-]+-lg-[a-z0-9]+ /', _test_tpl_fit_class($n))) $early[] = trim(_test_tpl_fit_class($n));
		});
		pg_assert_same(array(), $early, $id . ': the menu lays out at the same breakpoint as the bar');
	}
}

// A palette's text colour reads on the page background only; on a tertiary
// band small coloured text is mixed 10% darker in the light theme and 15%
// lighter in the dark one.
function test_design_template_fit_band_colours()
{
	$pf = 'var(--pg-primary-fg,var(--bs-primary))';
	$sf = 'var(--pg-secondary-fg,var(--bs-secondary))';
	foreach (_test_tpl_fit_ids() as $id => $p) {
		$css = _test_tpl_fit_css(_test_tpl_fit_tpl($id));
		$band = $id === 'pinegrap-library' ? ':is(.bg-body-tertiary,.plb-hero)' : '.bg-body-tertiary';
		pg_assert_contains('--' . $p . '-band-primary:color-mix(in srgb,' . $pf . ' 90%,black);--' . $p . '-band-secondary:color-mix(in srgb,' . $sf . ' 90%,black)', $css, $id . ': darker in the light theme');
		pg_assert_contains('--' . $p . '-band-primary:color-mix(in srgb,' . $pf . ' 85%,white);--' . $p . '-band-secondary:color-mix(in srgb,' . $sf . ' 85%,white)', $css, $id . ': lighter in the dark theme');
		pg_assert_contains($band . ' :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--' . $p . '-band-primary,' . $pf . ')!important}', $css, $id . ': primary text');
		pg_assert_contains($band . ' :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--' . $p . '-band-secondary,' . $sf . ')!important}', $css, $id . ': secondary text');
		pg_assert_contains($band . ' .btn-outline-primary{--bs-btn-color:var(--' . $p . '-band-primary,' . $pf . ')}', $css, $id . ': outline buttons');
		pg_assert_contains($band . ' .btn-outline-secondary{--bs-btn-color:var(--' . $p . '-band-secondary,' . $sf . ')}', $css, $id . ': secondary outline buttons');
		pg_assert_contains($band . ' .nav{--bs-nav-link-color:var(--' . $p . '-band-primary,var(--bs-link-color))}', $css, $id . ': section menus');
		pg_assert_false((bool)preg_match('/tertiary[^{]*\{[^}]*--bs-(primary|secondary)-text-emphasis/', $css), $id . ': no emphasis tint, too dark with some palettes');
	}
}

// On the palette's soft surface the colour as text fell to 3.2:1 in the
// light theme (the account card's Log out with Graphite); the emphasis tint
// reads with every palette in both themes. The rules come after the band's,
// so a soft box on a grey band takes its own.
function test_design_template_fit_soft_surface_colours()
{
	$rules = array(
		'.bg-primary-subtle :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--bs-primary-text-emphasis)!important}',
		'.bg-primary-subtle :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--bs-secondary-text-emphasis)!important}',
		'.bg-primary-subtle .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
		'.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
	);
	foreach (_test_tpl_fit_ids() as $id => $p) {
		$css = _test_tpl_fit_css(_test_tpl_fit_tpl($id));
		foreach ($rules as $rule) pg_assert_contains($rule, $css, $id . ': ' . $rule);
		pg_assert_true(strpos($css, $rules[0]) > strpos($css, '-band-primary,var(--bs-link-color))}'), $id . ': after the band rules');
	}
	pg_assert_contains('.bg-danger-subtle .btn-outline-danger{--bs-btn-color:var(--bs-danger-text-emphasis)}',
		_test_tpl_fit_css(_test_tpl_fit_tpl('pinegrap-pharmacy')), 'the on-duty button in the red boxes');
}

// The renderer leaves width and height out of an image's _attrs: a face
// given 48 px showed at the file's 96 px.
function test_design_template_fit_image_sizes()
{
	foreach (_test_tpl_fit_ids() as $id => $p) {
		$bad = array();
		_test_tpl_fit_walk(_test_tpl_fit_trees(_test_tpl_fit_tpl($id)), function ($n) use (&$bad) {
			if ($n['type'] !== 'content' || !isset($n['props']['contentType']) || $n['props']['contentType'] !== 'image') return;
			foreach (isset($n['props']['_attrs']) ? (array)$n['props']['_attrs'] : array() as $a) {
				if (in_array($a['name'], array('width', 'height'), true)) $bad[] = $n['props']['src'];
			}
		});
		pg_assert_same(array(), array_values(array_unique($bad)), $id . ': image sizes are props');
	}
}

// A browser closes a <p> at the first block inside it, which leaves the rest
// of the line outside the paragraph's spacing.
function test_design_template_fit_paragraph_holds_text()
{
	$blocks = array('div', 'p', 'ul', 'ol', 'dl', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'table', 'figure', 'blockquote', 'form', 'header', 'footer', 'nav');
	foreach (_test_tpl_fit_ids() as $id => $p) {
		$bad = array();
		_test_tpl_fit_walk(_test_tpl_fit_trees(_test_tpl_fit_tpl($id)), function ($n, $parent) use (&$bad, $blocks) {
			if (!$parent || $parent['type'] !== 'semantic' || !isset($parent['props']['tag']) || $parent['props']['tag'] !== 'p') return;
			$block = in_array($n['type'], array('container', 'row', 'col'), true)
				|| ($n['type'] === 'semantic' && isset($n['props']['tag']) && in_array($n['props']['tag'], $blocks, true))
				|| ($n['type'] === 'content' && isset($n['props']['contentType']) && in_array($n['props']['contentType'], array('paragraph', 'heading'), true));
			if ($block) $bad[] = isset($n['props']['_label']) ? $n['props']['_label'] : $n['type'];
		});
		pg_assert_same(array(), $bad, $id . ': no block inside a paragraph');
	}
}

// A hidden caption or heading is absolutely positioned: in a scroll box
// that is not positioned it escapes the box and widens the page.
function test_design_template_fit_scroll_boxes_positioned()
{
	foreach (_test_tpl_fit_ids() as $id => $p) {
		$bad = array();
		_test_tpl_fit_walk(_test_tpl_fit_trees(_test_tpl_fit_tpl($id)), function ($n) use (&$bad) {
			$class = _test_tpl_fit_class($n);
			if (strpos($class, ' table-responsive ') !== false && strpos($class, ' position-relative ') === false) $bad[] = trim($class);
		});
		pg_assert_same(array(), $bad, $id . ': every scroll box is positioned');
	}
}

// The notice is one sentence with a link at its end: in a wrapping flex
// row the link dropped under the text on a phone, alone on its line. The
// pharmacy's notice is two parts (the on-duty link, the emergency number)
// and wraps between them.
function test_design_template_fit_notice_wraps_as_text()
{
	foreach (_test_tpl_fit_ids() as $id => $p) {
		$tpl = _test_tpl_fit_tpl($id);
		if ($id === 'pinegrap-pharmacy' || !isset($tpl['shared']['notice'])) continue;
		_test_tpl_fit_walk($tpl['shared']['notice']['tree'], function ($n) use ($id) {
			if ($n['type'] === 'semantic' && isset($n['props']['tag']) && $n['props']['tag'] === 'p') {
				pg_assert_false(strpos(_test_tpl_fit_class($n), ' flex-wrap ') !== false, $id . ': the notice line wraps as text');
			}
		});
	}
}

// The ferry wake (a dashed line under a route) sat against the text above
// and below it.
function test_design_template_fit_ferry_wake_spacing()
{
	$tpl = _test_tpl_fit_tpl('pinegrap-ferry');
	$wakes = 0;
	_test_tpl_fit_walk($tpl['pages'], function ($n) use (&$wakes) {
		$class = _test_tpl_fit_class($n);
		if (strpos($class, ' pg-ferry-wake ') === false) return;
		$wakes++;
		pg_assert_contains(' my-3 ', $class, 'the wake keeps clear of the text');
	});
	pg_assert_true($wakes > 0, 'the routes draw their wake');
}

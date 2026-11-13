<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the design templates from App Studio to Events fit the
 * window and the dark theme: the header's menu opens out only where its
 * links fit beside the name and wraps instead of running off the page, an
 * image's size is in the props the page writes (not in its attributes,
 * which the page drops), and the grey sections and primary-tinted cards give
 * their primary and second coloured text a readable tint in both themes
 * (Bookshop and Boutique too).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The templates these rules were applied to, by id.
function _test_tpl_fit_ids()
{
	return array(
		'pinegrap-appstudio', 'pinegrap-architecture', 'pinegrap-bakery', 'pinegrap-brewery',
		'pinegrap-campsite', 'pinegrap-carrental', 'pinegrap-charity', 'pinegrap-cityhall',
		'pinegrap-construction', 'pinegrap-courses', 'pinegrap-coworking', 'pinegrap-dealership',
		'pinegrap-drivingschool', 'pinegrap-events',
	);
}

function _test_tpl_fit_template($id)
{
	static $cache = array();
	if (!isset($cache[$id])) {
		$cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $cache[$id];
}

// Calls $fn($node, $ancestors) for every node of the trees in $value.
function _test_tpl_fit_walk($value, $fn, $ancestors = array())
{
	if (!is_array($value)) return;
	if (isset($value['type']) && isset($value['props']) && is_array($value['props'])) {
		$fn($value, $ancestors);
		$ancestors[] = $value;
	}
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_fit_walk($child, $fn, $ancestors);
	}
}

function _test_tpl_fit_classes($node)
{
	$class = isset($node['props']['cssClass']) && is_string($node['props']['cssClass']) ? $node['props']['cssClass'] : '';
	return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

// The site header's navbar: the nav with a navbar-expand-* class inside the
// shared 'header' part.
function _test_tpl_fit_header_nav($tpl)
{
	$found = null;
	$tree = isset($tpl['shared']['header']['tree']) ? $tpl['shared']['header']['tree'] : null;
	_test_tpl_fit_walk($tree, function ($node) use (&$found) {
		if ($found !== null) return;
		foreach (_test_tpl_fit_classes($node) as $c) {
			if (strpos($c, 'navbar-expand') === 0) {
				$found = $node;
				return;
			}
		}
	});
	return $found;
}

// From 992 px the name, six or seven links and one or two buttons ran past
// the 960 px container (bakery by 305 px in Turkish), so the menu opens out
// at 1200 px; between, it stays behind the toggler.
function test_design_template_fit_header_opens_out_at_1200()
{
	foreach (_test_tpl_fit_ids() as $id) {
		$nav = _test_tpl_fit_header_nav(_test_tpl_fit_template($id));
		pg_assert_true($nav !== null, "$id has a header navbar");
		if ($nav === null) continue;
		$classes = _test_tpl_fit_classes($nav);
		pg_assert_contains('navbar-expand-xl', $classes, "$id: the menu opens out at 1200 px");
		$lg = array();
		_test_tpl_fit_walk($nav, function ($node) use (&$lg) {
			foreach (_test_tpl_fit_classes($node) as $c) {
				if (preg_match('/-lg-|-lg$/', $c)) $lg[] = $c;
			}
		});
		pg_assert_same(array(), $lg, "$id: no class in the header switches at 992 px");
	}
}

// A long site name would push the row past the container: it wraps under
// the name instead.
function test_design_template_fit_header_row_wraps()
{
	foreach (_test_tpl_fit_ids() as $id) {
		$nav = _test_tpl_fit_header_nav(_test_tpl_fit_template($id));
		if ($nav === null) continue;
		$wraps = false;
		foreach ((array)$nav['children'] as $child) {
			if (in_array('container', _test_tpl_fit_classes($child), true) && in_array('flex-xl-wrap', _test_tpl_fit_classes($child), true)) $wraps = true;
		}
		pg_assert_true($wraps, "$id: the navbar's container wraps");
	}
}

// The page writes an image's width and height from its props only
// (_render_extra_attrs() leaves them out of the attributes), so a size in
// _attrs was lost and the picture showed at its file's size.
function test_design_template_fit_image_size_in_props()
{
	foreach (_test_tpl_fit_ids() as $id) {
		$tpl = _test_tpl_fit_template($id);
		$lost = array();
		$sized = 0;
		_test_tpl_fit_walk(array($tpl['pages'], $tpl['shared'], $tpl['widgets']), function ($node) use (&$lost, &$sized) {
			if (!isset($node['props']['contentType']) || $node['props']['contentType'] !== 'image') return;
			if (isset($node['props']['width'])) $sized++;
			foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
				if (isset($a['name']) && in_array($a['name'], array('width', 'height'), true)) $lost[] = (isset($node['props']['alt']) ? $node['props']['alt'] : '?') . ' ' . $a['name'];
			}
		});
		pg_assert_same(array(), array_values(array_unique($lost)), "$id: no image size in attributes");
		pg_assert_true($sized > 0, "$id has sized images");
	}
}

// A palette's primary and second text colours are made readable on the
// page background only: across all palettes they fell to 3.9:1 on
// bg-body-tertiary in the dark theme, to 4.4:1 in the light one and to 3.2:1
// on bg-primary-subtle. The template's User Styles give them a tint there.
function test_design_template_fit_tinted_surfaces()
{
	// The two shop templates of the first third have another header, so only
	// this check reads them.
	foreach (array_merge(_test_tpl_fit_ids(), array('pinegrap-bookshop', 'pinegrap-boutique')) as $id) {
		$tpl = _test_tpl_fit_template($id);
		$css = isset($tpl['assets']['css']) ? (string)$tpl['assets']['css'] : '';
		$css = str_replace('\\/', '/', $css);
		foreach (array(
			':is(.bg-body-tertiary,.bg-primary-subtle){--tpl-tinted-fg:var(--bs-primary-text-emphasis)}',
			'.bg-body-tertiary{--tpl-tinted-fg:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,var(--bs-emphasis-color))}',
			'[data-bs-theme=dark] .bg-body-tertiary{--tpl-tinted-fg:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}',
			':is(.bg-body-tertiary,.bg-primary-subtle) :is(.text-primary,.link-primary){color:var(--tpl-tinted-fg)!important}',
			':is(.bg-body-tertiary,.bg-primary-subtle) .btn-outline-primary{--bs-btn-color:var(--tpl-tinted-fg)}',
			':is(.bg-body-tertiary,.bg-primary-subtle) .nav{--bs-nav-link-color:var(--tpl-tinted-fg);',
			':is(.bg-body-tertiary,.bg-primary-subtle) :is(.text-secondary,.link-secondary){color:var(--bs-secondary-color)!important}',
			':is(.bg-body-tertiary,.bg-primary-subtle) .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
		) as $rule) {
			pg_assert_contains($rule, $css, "$id: $rule");
		}
		// The mixed tints sit behind @supports: an older browser keeps the
		// emphasis tint.
		pg_assert_true(strpos($css, '@supports (color:color-mix(in srgb,red 50%,blue)){.bg-body-tertiary{--tpl-tinted-fg:') !== false, "$id: the mixed tints are behind @supports");
	}
}

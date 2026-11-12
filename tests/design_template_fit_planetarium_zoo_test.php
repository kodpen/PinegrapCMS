<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the design templates from Planetarium to Zoo fit the window
 * and the dark theme: the header's menu opens out only where its links fit
 * beside the name, is set a size smaller where the row is tight and wraps
 * instead of running off the page, an image's size is in the props the page
 * writes (not in its attributes, which the page drops), and the grey
 * sections give their primary and second coloured text a readable tint in
 * the dark theme.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The templates with a navbar header these rules were applied to, by id.
// The Online Store's header is a bar of its own with the category menu
// under it; it is checked for image sizes only.
function _test_tpl_fitpz_ids()
{
	return array(
		'pinegrap-planetarium', 'pinegrap-podcast', 'pinegrap-portfolio', 'pinegrap-realestate',
		'pinegrap-recruitment', 'pinegrap-restaurant', 'pinegrap-salon', 'pinegrap-school',
		'pinegrap-skiresort', 'pinegrap-social', 'pinegrap-sportsclub', 'pinegrap-theatre',
		'pinegrap-travel', 'pinegrap-veterinary', 'pinegrap-wedding', 'pinegrap-zoo',
	);
}

function _test_tpl_fitpz_template($id)
{
	static $cache = array();
	if (!isset($cache[$id])) {
		$cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $cache[$id];
}

// Calls $fn($node) for every node of the trees in $value.
function _test_tpl_fitpz_walk($value, $fn)
{
	if (!is_array($value)) return;
	if (isset($value['type']) && isset($value['props']) && is_array($value['props'])) $fn($value);
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_fitpz_walk($child, $fn);
	}
}

function _test_tpl_fitpz_classes($node)
{
	$class = isset($node['props']['cssClass']) && is_string($node['props']['cssClass']) ? $node['props']['cssClass'] : '';
	return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

// The site header's navbar: the nav with a navbar-expand-* class inside the
// shared 'header' part.
function _test_tpl_fitpz_header_nav($tpl)
{
	$found = null;
	$tree = isset($tpl['shared']['header']['tree']) ? $tpl['shared']['header']['tree'] : null;
	_test_tpl_fitpz_walk($tree, function ($node) use (&$found) {
		if ($found !== null) return;
		foreach (_test_tpl_fitpz_classes($node) as $c) {
			if (strpos($c, 'navbar-expand') === 0) {
				$found = $node;
				return;
			}
		}
	});
	return $found;
}

function _test_tpl_fitpz_css($tpl)
{
	$css = isset($tpl['assets']['css']) ? (string)$tpl['assets']['css'] : '';
	return str_replace('\\/', '/', $css);
}

// From 992 px the name, six to eight links and one or two buttons were laid
// out in the 960 px container and ran past it, so the menu opens out at
// 1200 px; between, it stays behind the toggler.
function test_design_template_fit_pz_header_opens_out_at_1200()
{
	foreach (_test_tpl_fitpz_ids() as $id) {
		$nav = _test_tpl_fitpz_header_nav(_test_tpl_fitpz_template($id));
		pg_assert_true($nav !== null, "$id has a header navbar");
		if ($nav === null) continue;
		pg_assert_contains('navbar-expand-xl', _test_tpl_fitpz_classes($nav), "$id: the menu opens out at 1200 px");
		$lg = array();
		_test_tpl_fitpz_walk($nav, function ($node) use (&$lg) {
			foreach (_test_tpl_fitpz_classes($node) as $c) {
				if (preg_match('/-lg-|-lg$/', $c)) $lg[] = $c;
			}
		});
		pg_assert_same(array(), $lg, "$id: no class in the header switches at 992 px");
	}
}

// A long site name would push the row past the container: it wraps under
// the name instead. The social network's header has a search field that
// gives up its width first, and wrapping there moved the links under the
// name at every width.
function test_design_template_fit_pz_header_row_wraps()
{
	foreach (_test_tpl_fitpz_ids() as $id) {
		$nav = _test_tpl_fitpz_header_nav(_test_tpl_fitpz_template($id));
		if ($nav === null) continue;
		$wraps = false;
		foreach ((array)$nav['children'] as $child) {
			$classes = _test_tpl_fitpz_classes($child);
			if (in_array('container', $classes, true) && in_array('flex-xl-wrap', $classes, true)) $wraps = true;
		}
		pg_assert_same($id !== 'pinegrap-social', $wraps, "$id: the navbar's container wraps");
	}
}

// From 1200 to 1399 px the 1140 px container has too little room for the
// name, the links and the buttons in one row: the menu's own class sets the
// links a size smaller there. The salon's links and the social network's
// search field leave room.
function test_design_template_fit_pz_tight_row_smaller_links()
{
	foreach (_test_tpl_fitpz_ids() as $id) {
		if ($id === 'pinegrap-salon' || $id === 'pinegrap-social') continue;
		$tpl = _test_tpl_fitpz_template($id);
		$nav = _test_tpl_fitpz_header_nav($tpl);
		if ($nav === null) continue;
		$own = null;
		_test_tpl_fitpz_walk($nav, function ($node) use (&$own) {
			$classes = _test_tpl_fitpz_classes($node);
			if ($own !== null || !in_array('navbar-nav', $classes, true)) return;
			foreach ($classes as $c) {
				if (preg_match('/^p[a-z]{2}-nav$/', $c)) $own = $c;
			}
		});
		pg_assert_true($own !== null, "$id: the header menu has its own class");
		if ($own === null) continue;
		$css = _test_tpl_fitpz_css($tpl);
		pg_assert_contains('@media (min-width:1200px)', $css, "$id: the rule starts at 1200 px");
		pg_assert_contains('{.' . $own . '{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}', $css, "$id: .$own sets the links a size smaller");
	}
}

// The page writes an image's width and height from its props only
// (_render_extra_attrs() leaves them out of the attributes), so a size in
// _attrs was lost and the picture showed at its file's size.
function test_design_template_fit_pz_image_size_in_props()
{
	foreach (array_merge(_test_tpl_fitpz_ids(), array('pinegrap-store')) as $id) {
		$tpl = _test_tpl_fitpz_template($id);
		$lost = array();
		_test_tpl_fitpz_walk(array($tpl['pages'], $tpl['shared'], isset($tpl['widgets']) ? $tpl['widgets'] : array()), function ($node) use (&$lost) {
			if (!isset($node['props']['contentType']) || $node['props']['contentType'] !== 'image') return;
			foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
				if (isset($a['name']) && in_array($a['name'], array('width', 'height'), true)) $lost[] = (isset($node['props']['alt']) ? $node['props']['alt'] : '?') . ' ' . $a['name'];
			}
		});
		pg_assert_same(array(), array_values(array_unique($lost)), "$id: no image size in attributes");
	}
}

// In the dark theme a palette's primary and second text colours fall to
// about 4:1 on bg-body-tertiary; the template's User Styles lift them there.
function test_design_template_fit_pz_dark_grey_sections()
{
	foreach (_test_tpl_fitpz_ids() as $id) {
		$css = _test_tpl_fitpz_css(_test_tpl_fitpz_template($id));
		foreach (array(
			'[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
			'[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
			'color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))',
			'[data-bs-theme=dark] .bg-body-tertiary .link-secondary{color:var(--bs-secondary-color)!important}',
			'[data-bs-theme=dark] .bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
		) as $rule) {
			pg_assert_contains($rule, $css, "$id: $rule");
		}
	}
}

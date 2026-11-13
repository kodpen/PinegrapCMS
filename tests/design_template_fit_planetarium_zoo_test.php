<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the design templates from Planetarium to Zoo fit the window
 * and the dark theme: the header's menu opens out only where its links fit
 * beside the name, is set a size smaller where the row is tight and wraps
 * instead of running off the page, an image's size is in the props the page
 * writes (not in its attributes, which the page drops), the grey
 * sections give their primary and second coloured text a readable tint in
 * the dark theme, and so does the primary's soft tint in both themes; the
 * account bar's links wrap as a whole on a phone with their labels kept on
 * one line, and a table's scroll wrapper holds its visually hidden labels.
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
			'[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:var(--bs-primary-text-emphasis)}',
		) as $rule) {
			pg_assert_contains($rule, $css, "$id: $rule");
		}
	}
	// The Online Store had no User Styles; its account bar's Log In is a
	// btn-link, which takes the tint with the outline buttons.
	$css = _test_tpl_fitpz_css(_test_tpl_fitpz_template('pinegrap-store'));
	pg_assert_contains('[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}', $css, 'store: dark grey text');
	pg_assert_contains('[data-bs-theme=dark] .bg-body-tertiary :is(.btn-outline-primary,.btn-link){--bs-btn-color:var(--bs-primary-text-emphasis)}', $css, 'store: dark grey buttons');
	// Planetarium's account bar carries the dark theme on the grey bar
	// itself, on a light site too.
	$css = _test_tpl_fitpz_css(_test_tpl_fitpz_template('pinegrap-planetarium'));
	pg_assert_contains('.bg-body-tertiary[data-bs-theme=dark] :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}', $css, 'planetarium: the dark account bar');
}

// Two templates colour with Bootstrap's fixed red or green, which no palette
// changes: the veterinary's emergency line and band, the zoo's links and
// outline buttons (2.94:1 on the dark grey before).
function test_design_template_fit_pz_fixed_colours()
{
	$css = _test_tpl_fitpz_css(_test_tpl_fitpz_template('pinegrap-veterinary'));
	pg_assert_contains('.bg-body-tertiary .link-danger{color:color-mix(in srgb,var(--bs-danger) 80%,black)!important}', $css, 'veterinary: emergency line on grey');
	pg_assert_contains('[data-bs-theme=dark] .bg-body-tertiary .link-danger{color:var(--bs-danger-text-emphasis)!important}', $css, 'veterinary: emergency line, dark');
	pg_assert_contains('[data-bs-theme=dark] .bg-danger-subtle .btn-outline-danger{--bs-btn-color:var(--bs-danger-text-emphasis)}', $css, 'veterinary: emergency band, dark');
	$css = _test_tpl_fitpz_css(_test_tpl_fitpz_template('pinegrap-zoo'));
	pg_assert_contains('.bg-success-subtle .btn-outline-success{--bs-btn-color:color-mix(in srgb,var(--bs-success) 80%,black)}', $css, 'zoo: outline buttons on the soft green');
	pg_assert_contains('[data-bs-theme=dark] :is(.text-success,.link-success){color:var(--bs-success-text-emphasis)!important}', $css, 'zoo: green text, dark');
	pg_assert_contains('[data-bs-theme=dark] .btn-outline-success{--bs-btn-color:var(--bs-success-text-emphasis)}', $css, 'zoo: outline buttons, dark');
}

// On the primary's soft tint (the account card with its Log out button,
// soft bands) a palette's primary and second colours fall under 4.5:1 with
// several palettes; their emphasis tints are 7.35:1 or more in light.
function test_design_template_fit_pz_soft_tint()
{
	foreach (array_merge(_test_tpl_fitpz_ids(), array('pinegrap-store')) as $id) {
		$css = _test_tpl_fitpz_css(_test_tpl_fitpz_template($id));
		foreach (array(
			'.bg-primary-subtle :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
			'.bg-primary-subtle :is(.btn-outline-primary,.btn-link){--bs-btn-color:var(--bs-primary-text-emphasis)}',
			'.bg-primary-subtle .link-secondary{color:var(--bs-secondary-text-emphasis)!important}',
			'.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
		) as $rule) {
			pg_assert_contains($rule, $css, "$id: $rule");
		}
	}
}

// The account bar's row (language switcher, login region, cart) did not
// wrap: on a phone, with more than one site language, the store's Log In and
// Sign Up broke onto two lines each and the other templates' Sign Up ran
// past the page. The row wraps as a whole now and no label breaks.
function test_design_template_fit_pz_account_bar_wraps()
{
	foreach (array_merge(_test_tpl_fitpz_ids(), array('pinegrap-store')) as $id) {
		$tpl = _test_tpl_fitpz_template($id);
		$row = null;
		$switcher = null;
		_test_tpl_fitpz_walk(isset($tpl['shared']['account_bar']['tree']) ? $tpl['shared']['account_bar']['tree'] : null, function ($node) use (&$row, &$switcher) {
			foreach ($node['children'] as $child) {
				if (isset($child['props']['componentType']) && $child['props']['componentType'] === 'language_switcher') {
					$row = $node;
					$switcher = $child;
				}
			}
		});
		pg_assert_true($row !== null, "$id: the account bar holds the language switcher");
		if ($row === null) continue;
		foreach (array('flex-wrap', 'justify-content-end', 'row-gap-1', 'ms-auto') as $c) {
			pg_assert_contains($c, _test_tpl_fitpz_classes($row), "$id: account links $c");
		}
		pg_assert_contains('text-nowrap', _test_tpl_fitpz_classes($switcher), "$id: the switcher's label stays on one line");

		$links = array();
		_test_tpl_fitpz_walk(isset($tpl['widgets']['login_region']['tree']) ? $tpl['widgets']['login_region']['tree'] : null, function ($node) use (&$links) {
			$href = isset($node['props']['_bindings']['href']) ? $node['props']['_bindings']['href'] : '';
			if ($href === '__login_url' || $href === '__register_url') $links[$href] = $node;
		});
		pg_assert_same(array('__login_url', '__register_url'), array_keys($links), "$id: Log In and Sign Up in the login region");
		foreach ($links as $href => $node) {
			pg_assert_contains('text-nowrap', _test_tpl_fitpz_classes($node), "$id: $href stays on one line");
		}
	}
}

// A visually hidden label is absolutely positioned: in a table wider than
// the phone, inside a static .table-responsive, it was placed against the
// card or the page and widened it (the travel price table made the deals
// page 616 px wide at 390 px). The wrapper is position-relative.
function test_design_template_fit_pz_table_wrapper_holds_hidden_labels()
{
	foreach (array_merge(_test_tpl_fitpz_ids(), array('pinegrap-store')) as $id) {
		$tpl = _test_tpl_fitpz_template($id);
		$loose = array();
		_test_tpl_fitpz_walk(array($tpl['pages'], $tpl['shared'], isset($tpl['widgets']) ? $tpl['widgets'] : array()), function ($node) use (&$loose) {
			$classes = _test_tpl_fitpz_classes($node);
			if (!in_array('table-responsive', $classes, true) || in_array('position-relative', $classes, true)) return;
			$hidden = false;
			_test_tpl_fitpz_walk($node['children'], function ($child) use (&$hidden) {
				if (in_array('visually-hidden', _test_tpl_fitpz_classes($child), true)) $hidden = true;
			});
			if ($hidden) $loose[] = isset($node['props']['customName']) ? $node['props']['customName'] : '?';
		});
		pg_assert_same(array(), $loose, "$id: a table wrapper with hidden labels is position-relative");
	}
}

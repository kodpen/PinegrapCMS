<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the finish pass of system widget renders
 * (includes/fn/widgets_finish.php) and its hooks: a node with a value
 * condition is drawn between markers only inside a widget scope, the
 * renderer's own fill writes each row's value into the span's carrier, and
 * the pass keeps or cuts each span and leaves no marker behind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_finish_spec($token, $op = 'not_empty', $operand = '')
{
	return array('token' => $token, 'op' => $op, 'operand' => $operand);
}

// A node bound to $text_token, shown only when $show_if passes.
function _widget_finish_node($text_token, $show_if = null, $params = null, $class = 'v')
{
	$props = array('contentType' => 'paragraph', 'text' => 'Sample', 'cssClass' => $class, '_bindings' => array('text' => $text_token));
	if ($show_if !== null) $props['_bindings']['show_if'] = $show_if;
	if ($params !== null) $props['_bindParams'] = array('show_if' => $params);
	return array('type' => 'content', 'props' => $props, 'children' => array());
}

// $fn run inside a widget scope of $kind, closed whatever happens.
function _widget_finish_in_scope($kind, $fn)
{
	$scope = pg_sw_finish_open(array('sid' => 7, 'kind' => $kind, 'email' => false));
	try {
		return $fn($scope);
	} finally {
		pg_sw_finish_close();
	}
}

function _widget_finish_no_markers($html, $label = '')
{
	pg_assert_same(false, strpos($html, 'pg-if:'), $label . ' no pg-if');
	pg_assert_same(false, strpos($html, 'pg-cv:'), $label . ' no pg-cv');
}

// A span is kept or cut by the value the fill wrote into its carrier, with
// str_replace (the catalog) and with strtr (_pg_sw_fill_tokens()).
function test_widget_finish_wrap_fill_resolve()
{
	$scope = array('kind' => 'catalog_listing');
	$html  = 'a' . pg_sw_finish_wrap('<span>^^__d^^</span>', _widget_finish_spec('__d')) . 'b';

	$kept = pg_sw_finish_widget_html(str_replace('^^__d^^', '10%', $html), $scope);
	pg_assert_same('a<span>10%</span>b', $kept, 'str_replace, kept');

	$cut = pg_sw_finish_widget_html(strtr($html, array('^^__d^^' => '')), $scope);
	pg_assert_same('ab', $cut, 'strtr, cut');

	$filled = _pg_sw_fill_tokens($html, array('__d' => '5'));
	pg_assert_same('a<span>5</span>b', pg_sw_finish_widget_html($filled, $scope), '_pg_sw_fill_tokens');
}

// The operand travels in the marker and is compared as typed.
function test_widget_finish_operand()
{
	$scope = array('kind' => 'shopping_cart');
	$html  = pg_sw_finish_wrap('<p>Discount ^^__x^^</p>', _widget_finish_spec('__x', 'gt', '0'));
	pg_assert_same('', pg_sw_finish_widget_html(str_replace('^^__x^^', '₺0,00', $html), $scope), 'zero is not above zero');
	pg_assert_same('<p>Discount ₺5,00</p>', pg_sw_finish_widget_html(str_replace('^^__x^^', '₺5,00', $html), $scope));

	$html = pg_sw_finish_wrap('<p>x</p>', _widget_finish_spec('__city', 'eq', 'İstanbul / "A&B" <x>'));
	pg_assert_same('<p>x</p>', pg_sw_finish_widget_html(str_replace('^^__city^^', h('istanbul / "a&b" <X>'), $html), $scope), 'any operand text survives the marker');
}

// Spans nest: a cut span inside a kept one, a kept span inside a cut one.
function test_widget_finish_nested_spans()
{
	$scope = array('kind' => 'catalog_item_view');
	$inner = pg_sw_finish_wrap('<i>^^__b^^</i>', _widget_finish_spec('__b'));
	$outer = pg_sw_finish_wrap('<div>^^__a^^' . $inner . '</div>', _widget_finish_spec('__a'));

	pg_assert_same('<div>A</div>', pg_sw_finish_widget_html(strtr($outer, array('^^__a^^' => 'A', '^^__b^^' => '')), $scope), 'cut inside kept');
	pg_assert_same('', pg_sw_finish_widget_html(strtr($outer, array('^^__a^^' => '', '^^__b^^' => 'B')), $scope), 'kept inside cut');
	pg_assert_same('<div>A<i>B</i></div>', pg_sw_finish_widget_html(strtr($outer, array('^^__a^^' => 'A', '^^__b^^' => 'B')), $scope), 'both kept');
}

// A span copied into three rows carries each row's own value.
function test_widget_finish_row_copies()
{
	$row  = '<li>^^__name^^' . pg_sw_finish_wrap('<b>^^__d^^</b>', _widget_finish_spec('__d')) . '</li>';
	$html = '<ul>';
	foreach (array(array('A', '10%'), array('B', ''), array('C', '5%')) as $values) {
		$html .= strtr($row, array('^^__name^^' => $values[0], '^^__d^^' => $values[1]));
	}
	$html .= '</ul>';
	pg_assert_same('<ul><li>A<b>10%</b></li><li>B</li><li>C<b>5%</b></li></ul>', pg_sw_finish_widget_html($html, array('kind' => 'catalog_listing')));
}

// A token nothing filled reads as empty, not as '^^x^^'.
function test_widget_finish_unfilled_reads_empty()
{
	$html = pg_sw_finish_wrap('<p>Shown</p>', _widget_finish_spec('__unknown'));
	pg_assert_same('', pg_sw_finish_widget_html($html, array('kind' => 'calendar_view')), 'not_empty on an unfilled token');
	$html = pg_sw_finish_wrap('<p>Shown</p>', _widget_finish_spec('__unknown', 'empty'));
	pg_assert_same('<p>Shown</p>', pg_sw_finish_widget_html($html, array('kind' => 'calendar_view')), 'empty on an unfilled token');
	$html = pg_sw_finish_wrap('<p>Shown</p>', _widget_finish_spec('__fmt'));
	pg_assert_same('', pg_sw_finish_widget_html(str_replace('^^__fmt^^', '^^__other^^%%d.m.Y%%', $html), array('kind' => 'calendar_view')), 'a value left as another token');
}

// A value may hold '-->' or a comment of its own; neither ends the carrier.
function test_widget_finish_values_with_comments()
{
	$scope = array('kind' => 'form_list_view');
	$html  = pg_sw_finish_wrap('<div>^^__body^^</div>', _widget_finish_spec('__body', 'contains', 'end'));

	$value = 'a --> b <!-- c --> the end';
	pg_assert_same('<div>' . $value . '</div>', pg_sw_finish_widget_html(str_replace('^^__body^^', $value, $html), $scope), 'raw -->');

	$word = '<!--[if gte mso 9]><xml><o:x>end</o:x></xml><![endif]--><p>Start</p>';
	pg_assert_same('', pg_sw_finish_widget_html(str_replace('^^__body^^', $word, $html), $scope), 'a Word comment is no text');
	pg_assert_same('<div>' . $word . 'end</div>', pg_sw_finish_widget_html(str_replace('^^__body^^', $word . 'end', $html), $scope), 'Word comment then text');
}

// Markers of another nonce, in a value or anywhere, do nothing and stay.
function test_widget_finish_forged_marker_inert()
{
	$forged = '<!--pg-if:0000000000000000:1:empty:--><p>keep</p><!--pg-cv:0000000000000000:1-->x<!--/pg-cv:0000000000000000:1--><!--/pg-if:0000000000000000:1-->';
	pg_assert_same($forged, pg_sw_finish_widget_html($forged, array('kind' => 'comments')), 'no nonce of this request: untouched');

	$html = pg_sw_finish_wrap('<div>^^__c^^</div>', _widget_finish_spec('__c'));
	$out  = pg_sw_finish_widget_html(str_replace('^^__c^^', $forged, $html), array('kind' => 'comments'));
	pg_assert_same('<div>' . $forged . '</div>', $out, 'a forged span inside a value stays as written');
}

// Markers left without their pair are taken out; a carrier with its value.
function test_widget_finish_strips_leftovers()
{
	$nonce = pg_sw_marker_nonce();
	$html  = '<!--pg-if:' . $nonce . ':900:not_empty:-->'
		. '<p>a</p><!--pg-cv:' . $nonce . ':901-->secret<!--/pg-cv:' . $nonce . ':901-->'
		. '<!--/pg-if:' . $nonce . ':902-->';
	pg_assert_same('<p>a</p>', pg_sw_finish_widget_html($html, array('kind' => 'order_view')));
	pg_assert_same('<p>x</p>', pg_sw_finish_strip_carriers('<p>x</p><!--pg-cv:' . $nonce . ':3-->v<!--/pg-cv:' . $nonce . ':3-->'));
}

// Markup with no marker of this request comes back as it was.
function test_widget_finish_no_nonce_unchanged()
{
	$html = '<p>^^__x^^</p><!-- a comment -->';
	pg_assert_same($html, pg_sw_finish_widget_html($html, array('kind' => 'catalog_listing')));
	pg_assert_same('', pg_sw_finish_widget_html('', null));
}

// The scope stack: kinds are normalised, scopes nest, closing pops.
function test_widget_finish_scope_stack()
{
	pg_assert_false(pg_sw_finish_active(), 'nothing open');
	pg_assert_same(null, pg_sw_finish_scope());
	$outer = pg_sw_finish_open(array('sid' => '12', 'kind' => 'blog_list', 'email' => 1));
	try {
		pg_assert_same(array('sid' => 12, 'kind' => 'form_list_view', 'email' => true), $outer);
		pg_assert_true(pg_sw_finish_active());
		pg_sw_finish_open(array('sid' => 3, 'kind' => 'catalog_listing'));
		try {
			pg_assert_same(array('sid' => 3, 'kind' => 'catalog_listing', 'email' => false), pg_sw_finish_scope());
		} finally {
			pg_sw_finish_close();
		}
		pg_assert_same($outer, pg_sw_finish_scope());
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_false(pg_sw_finish_active(), 'all closed');
	foreach (array('submitted_forms_list', 'form_list', 'blog_list', 'form_list_view') as $kind) {
		pg_assert_same('form_list_view', pg_sw_finish_kind($kind), $kind);
	}
	pg_assert_same('', pg_sw_finish_kind(null));
}

// A node with a condition inside a widget scope: wrapped, then resolved.
function test_widget_finish_render_tree_node_in_scope()
{
	$node = _widget_finish_node('__event_location', '__event_location');
	$html = _widget_finish_in_scope('calendar_view', function ($scope) use ($node) {
		return array(_render_tree_node($node), $scope);
	});
	pg_assert_contains('<!--pg-if:' . pg_sw_marker_nonce() . ':', $html[0]);
	pg_assert_contains('-->^^__event_location^^<!--/pg-cv:', $html[0], 'the carrier asks for the bound token');
	pg_assert_same(false, strpos($html[0], 'show_if'), 'the binding is not a prop');
	pg_assert_same(1, preg_match('/^<!--pg-if:[^>]*-->\s*<p /', $html[0]), 'the element comes first after the open marker');

	$empty = pg_sw_finish_widget_html(str_replace('^^__event_location^^', '', $html[0]), $html[1]);
	pg_assert_same('', $empty, 'an empty location cuts the paragraph');
	$full = pg_sw_finish_widget_html(str_replace('^^__event_location^^', 'Hall A', $html[0]), $html[1]);
	pg_assert_same("<p class=\"v\" data-pg-bind=\"text:event_location\">Hall A</p>\n", $full);
}

// An invalid condition draws nothing inside a widget.
function test_widget_finish_invalid_spec_renders_nothing()
{
	$bad = array(
		_widget_finish_node('title', 'title', array('op' => 'between', 'value' => '1')),
		_widget_finish_node('title', 'title', array('op' => 'gt')),
		_widget_finish_node('title', '__edit_url'),
		_widget_finish_node('title', '__breadcrumb_html'),
	);
	foreach ($bad as $i => $node) {
		pg_assert_same('', _widget_finish_in_scope('form_list_view', function () use ($node) {
			return _render_tree_node($node);
		}), 'case ' . $i);
	}
}

// A tree without conditions draws byte-identical inside and outside a scope.
function test_widget_finish_tree_without_conditions_identical()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array('cssClass' => 'box'), 'children' => array(
			_widget_finish_node('__name'),
			array('type' => 'semantic', 'props' => array('tag' => 'span', 'text' => 'x', 'cssClass' => 'badge',
				'_bindings' => array('text' => '__d'), '_bindParams' => array('href' => array('page_id' => 3))), 'children' => array()),
			array('type' => 'loop_area', 'props' => array(), 'children' => array(_widget_finish_node('__row'))),
		)),
	));
	$outside = _render_tree_node($tree);
	$inside  = _widget_finish_in_scope('catalog_listing', function () use ($tree) {
		return _render_tree_node($tree);
	});
	pg_assert_same($outside, $inside);
	pg_assert_contains('^^__name^^', $outside);
}

// Outside a widget a node with a condition draws as it is, with no marker
// and without the binding as a prop: a saved page body never holds one.
function test_widget_finish_condition_outside_scope()
{
	$plain = _render_tree_node(_widget_finish_node('__x'));
	$cond  = _render_tree_node(_widget_finish_node('__x', '__x', array('op' => 'gt', 'value' => '1')));
	pg_assert_same($plain, $cond);
	_widget_finish_no_markers($cond);
	pg_assert_same($plain, _render_tree_node(_widget_finish_node('__x', '__edit_url')), 'an invalid condition outside a scope');
}

// Containers that only pass their children through take no condition.
function test_widget_finish_transparent_types_unwrapped()
{
	$loop = array('type' => 'loop_area', 'props' => array('_bindings' => array('show_if' => '__x')), 'children' => array(_widget_finish_node('__y')));
	$html = _widget_finish_in_scope('catalog_listing', function () use ($loop) {
		return _render_tree_node($loop);
	});
	_widget_finish_no_markers($html, 'loop_area');
	pg_assert_contains('^^__y^^', $html);
}

// What a visitor sees never counts a carrier.
function test_widget_finish_has_content_ignores_carriers()
{
	$nonce = pg_sw_marker_nonce();
	$html  = '<span class="pg-field-value"></span><!--pg-cv:' . $nonce . ':5-->Hall A<!--/pg-cv:' . $nonce . ':5-->';
	pg_assert_false(pg_sw_has_content($html));
	pg_assert_true(pg_sw_has_content('<span>Hall A</span><!--pg-cv:' . $nonce . ':5--><!--/pg-cv:' . $nonce . ':5-->'));
}

// pg-hide-if-empty is honoured again after a cut in the kinds that run it
// (the early form list names count as the form list), not in the others.
function test_widget_finish_hide_empty_rerun()
{
	$html = '<div class="box pg-hide-if-empty">'
		. pg_sw_finish_wrap('<p>^^__d^^</p>', _widget_finish_spec('__d', 'gt', '0'))
		. '</div><p>after</p>';
	$filled = str_replace('^^__d^^', '0', $html);

	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'blog_list')), 'blog_list');
	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'form_item_view')), 'form_item_view');
	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'comments')), 'comments');
	pg_assert_same('<div class="box pg-hide-if-empty"></div><p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'catalog_listing')), 'catalog_listing');
}

// The edit pencil still finds a row's first element when the row starts
// with a condition.
function test_widget_finish_edit_chip_finds_first_element()
{
	$row = pg_sw_finish_wrap('<div class="card">^^__d^^</div>', _widget_finish_spec('__d'));
	$out = pg_sw_add_edit_chip(str_replace('^^__d^^', 'x', $row), '/edit?id=1', 'Edit', 'page');
	pg_assert_contains('<div class="card pg-edit-host"', $out);
	pg_assert_same('<div class="card pg-edit-host"', substr(pg_sw_finish_widget_html($out, array('kind' => 'catalog_listing')), 0, 30));
}

// The hooks: the dispatcher's callback finishes both the e-mail and the page
// output inside a scope, and functions.php loads both modules.
function test_widget_finish_hooks_in_source()
{
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('function ($m) use ($render, $email, $configs) {', $designer);
	pg_assert_contains('if ($email) return pg_sw_finish_widget_html($out, $scope);', $designer, 'e-mail branch');
	pg_assert_contains('return pg_img_responsive_html(pg_img_fluid_alias(pg_sw_finish_widget_html($out, $scope)));', $designer, 'page branch');
	pg_assert_same(1, preg_match('/pg_sw_finish_open\([^;]*;\s*try \{\s*\$out = \$render\(\$m\);\s*\} finally \{\s*pg_sw_finish_close\(\);/', $designer), 'scope around the render');

	$functions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_conditions.php');", $functions);
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_finish.php');", $functions);
}

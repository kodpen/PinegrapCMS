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
		_widget_finish_node('title', '__group_tree_html'),
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

// pg-hide-if-empty is honoured again after a cut in the kinds whose
// renderer judges it (the early form list names count as the form list),
// and for the first time in every other kind.
function test_widget_finish_hide_empty_rerun()
{
	$html = '<div class="box pg-hide-if-empty">'
		. pg_sw_finish_wrap('<p>^^__d^^</p>', _widget_finish_spec('__d', 'gt', '0'))
		. '</div><p>after</p>';
	$filled = str_replace('^^__d^^', '0', $html);

	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'blog_list')), 'blog_list');
	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'form_item_view')), 'form_item_view');
	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'comments')), 'comments');
	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'comments_recent')), 'comments_recent');
	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html($filled, array('kind' => 'catalog_listing')), 'catalog_listing');
}

// The form starters bind the value element itself inside a pg-hide-if-empty
// "label: value" row. A condition that cuts that element leaves the row
// with an empty value, so the row goes as it does for a blank field, in
// every kind.
function test_widget_finish_field_value_cut_drops_row()
{
	$row  = '<p class="card-text small mb-1 pg-hide-if-empty"><span class="fw-semibold me-1">Section:</span>'
		. pg_sw_finish_wrap('<span class="pg-field-value">^^section^^</span>', _widget_finish_spec('section', 'eq', 'kültür'))
		. '</p>';
	$html = '<div class="card">' . str_replace('^^section^^', 'Tarih', $row) . str_replace('^^section^^', 'Kültür', $row) . '</div>';
	$kept = '<p class="card-text small mb-1 pg-hide-if-empty"><span class="fw-semibold me-1">Section:</span><span class="pg-field-value">Kültür</span></p>';

	pg_assert_same('<div class="card">' . $kept . '</div>', pg_sw_finish_widget_html($html, array('kind' => 'form_list_view')), 'form_list_view');
	pg_assert_same('<div class="card">' . $kept . '</div>', pg_sw_finish_widget_html($html, array('kind' => 'form_item_view')), 'form_item_view');
	pg_assert_same('<div class="card">' . $kept . '</div>', pg_sw_finish_widget_html($html, array('kind' => 'catalog_listing')), 'catalog_listing');

	// The same row drawn from the starter's tree.
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'mb-3 pg-hide-if-empty'), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Section', 'cssClass' => 'small mb-0'), 'children' => array()),
		_widget_finish_node('section', 'section', array('op' => 'eq', 'value' => 'kültür'), 'mb-0 pg-field-value'),
	));
	$tpl = _widget_finish_in_scope('form_item_view', function () use ($tree) {
		return _render_tree_node($tree);
	});
	$out = pg_sw_finish_widget_html(_pg_sw_fill_tokens($tpl, array('section' => 'Tarih')), array('kind' => 'form_item_view'));
	pg_assert_same('', trim($out), 'the starter row of a failing value goes');
	$out = pg_sw_finish_widget_html(_pg_sw_fill_tokens($tpl, array('section' => 'Kültür')), array('kind' => 'form_item_view'));
	pg_assert_contains('Kültür</p>', $out);
	pg_assert_contains('>Section</p>', $out);
	_widget_finish_no_markers($out);
}

// Outside the judged kinds every marked element is judged, with or without
// a marker in the markup: an empty box goes with its heading when the
// heading's box names its value (pg-field-value), a filled one stays, and
// an element no value left empty is kept.
function test_widget_finish_hide_empty_other_kinds()
{
	$box = function ($value) {
		return '<section class="border p-3 pg-hide-if-empty"><h3>Related</h3><div class="row pg-field-value">' . $value . '</div></section>';
	};
	$scope = array('kind' => 'catalog_item_view');
	pg_assert_same('<p>x</p>', pg_sw_finish_widget_html($box('') . '<p>x</p>', $scope), 'no marker: the empty box goes, heading and frame');
	pg_assert_same($box('<a href="/p">P</a>'), pg_sw_finish_widget_html($box('<a href="/p">P</a>'), $scope), 'a filled box stays');
	pg_assert_same('<p class="pg-hide-if-empty">Words</p>', pg_sw_finish_widget_html('<p class="pg-hide-if-empty">Words</p>', $scope), 'designed words alone stay');
	pg_assert_same('', pg_sw_finish_widget_html('<p class="pg-hide-if-empty"><span>^^__loop_index^^</span></p>', array('kind' => 'shopping_cart')), 'after the positions');

	$html = '<div class="pg-hide-if-empty"><b>Off:</b>' . pg_sw_finish_wrap('<span class="pg-field-value">^^__d^^</span>', _widget_finish_spec('__d', 'gt', '0')) . '</div>';
	pg_assert_same('', pg_sw_finish_widget_html(str_replace('^^__d^^', '0', $html), array('kind' => 'shopping_cart')), 'a cut value takes its row');
	pg_assert_same('<div class="pg-hide-if-empty"><b>Off:</b><span class="pg-field-value">5</span></div>',
		pg_sw_finish_widget_html(str_replace('^^__d^^', '5', $html), array('kind' => 'shopping_cart')), 'a passing value keeps it');

	pg_assert_same($box(''), pg_sw_finish_widget_html($box(''), array('kind' => 'form_item_view')), 'a judged kind without a cut: as its renderer left it');
}

// On a product page with the variant picker, an empty box holding a value
// the picker redraws waits hidden; one holding none goes; the rule that
// keeps it hidden is printed once.
function test_widget_finish_hide_empty_waits_for_a_variant()
{
	$script = '<script>window.pgCivVariants.push({});</script>';
	$html   = '<div class="d-flex pg-hide-if-empty"><s data-pg-bind="text:original_price_formatted"></s></div>'
		. '<p class="pg-hide-if-empty"><span></span></p>'
		. '<img class="pg-hide-if-empty" src="" data-pg-bind="src:image_url">' . $script;
	$out = pg_sw_finish_widget_html($html, array('kind' => 'catalog_item_view'));
	pg_assert_same('<style>[data-pg-empty-wait][hidden]{display:none!important}</style>'
		. '<div class="d-flex pg-hide-if-empty" hidden data-pg-empty-wait><s data-pg-bind="text:original_price_formatted"></s></div>'
		. '<img class="pg-hide-if-empty" src="" data-pg-bind="src:image_url" hidden data-pg-empty-wait>' . $script, $out);
	pg_assert_same($out, pg_sw_finish_widget_html($out, array('kind' => 'catalog_listing')), 'a waiting element is left by an outer pass');

	$plain = '<div class="pg-hide-if-empty"><s data-pg-bind="text:original_price_formatted"></s></div><p>x</p>';
	pg_assert_same('<p>x</p>', pg_sw_finish_widget_html($plain, array('kind' => 'catalog_item_view')), 'no variant picker: it goes');
	pg_assert_same('<p>x</p>' . $script, pg_sw_finish_widget_html($plain . $script, array('kind' => 'catalog_listing')), 'only the product page waits');
	pg_assert_same('<br class="pg-hide-if-empty" hidden data-pg-empty-wait/>', pg_sw_empty_wait_tag('<br class="pg-hide-if-empty"/>'));

	// A filled box a variant may empty gets the rule too, for the script.
	$filled = '<div class="d-flex pg-hide-if-empty"><s data-pg-bind="text:original_price_formatted">₺9</s></div>' . $script;
	pg_assert_same('<style>[data-pg-empty-wait][hidden]{display:none!important}</style>' . $filled,
		pg_sw_finish_widget_html($filled, array('kind' => 'catalog_item_view')));
}

// After a cut only the pg-hide-if-empty elements around a cut are judged
// again: an empty box elsewhere (the static part of a form list, which the
// renderer never judges) stays as it was.
function test_widget_finish_rerun_only_around_a_cut()
{
	$html = '<div class="head pg-hide-if-empty"></div>'
		. '<ul><li class="pg-hide-if-empty"><div class="pg-hide-if-empty">'
		. pg_sw_finish_wrap('<b>^^__d^^</b>', _widget_finish_spec('__d', 'gt', '5'))
		. '</div></li><li class="pg-hide-if-empty">x</li></ul>';
	$out = pg_sw_finish_widget_html(str_replace('^^__d^^', '1', $html), array('kind' => 'form_list_view'));
	pg_assert_same('<div class="head pg-hide-if-empty"></div><ul><li class="pg-hide-if-empty">x</li></ul>', $out);
	_widget_finish_no_markers($out);
	pg_assert_same(false, strpos($out, 'pg-cut:'), 'no cut point left');
}

// pg_sw_hide_empty_fields() with a cut point judges only the elements that
// hold it, and one that goes leaves the point for the element around it.
function test_widget_finish_hide_empty_with_mark()
{
	$html = '<div class="a pg-hide-if-empty"></div>'
		. '<div class="b pg-hide-if-empty"><p class="pg-hide-if-empty"><!--m--></p></div>'
		. '<div class="c pg-hide-if-empty"><p class="pg-hide-if-empty"><!--m--></p>text</div>'
		. '<img class="pg-hide-if-empty" src="">';
	pg_assert_same('<div class="a pg-hide-if-empty"></div><!--m--><div class="c pg-hide-if-empty"><!--m-->text</div><img class="pg-hide-if-empty" src="">',
		pg_sw_hide_empty_fields($html, '<!--m-->'));
	pg_assert_same('<div class="c pg-hide-if-empty">text</div>', pg_sw_hide_empty_fields($html), 'without a mark every element is judged');
	pg_assert_same('<div class="a pg-hide-if-empty"></div>', pg_sw_hide_empty_fields('<div class="a pg-hide-if-empty"></div>', '<!--m-->'), 'no mark in the markup: as it was');
}

// A span may hold a body of any size: it is found without a pattern that
// could run out of backtracking, so a big span and a small one beside it
// are both judged.
function test_widget_finish_large_span_body()
{
	$scope = array('kind' => 'form_list_view');
	$big   = '<div>' . str_repeat('<p>Long body text, row after row.</p>', 60000) . '</div>';
	pg_assert_true(strlen($big) > 2000000, 'the body is over 2 MB');
	$html  = pg_sw_finish_wrap('<i>^^__a^^</i>', _widget_finish_spec('__a'))
		. pg_sw_finish_wrap($big . '^^__b^^', _widget_finish_spec('__b'))
		. '<p>after</p>';

	pg_assert_same('<p>after</p>', pg_sw_finish_widget_html(strtr($html, array('^^__a^^' => '', '^^__b^^' => '')), $scope), 'both cut');
	pg_assert_same($big . 'B<p>after</p>', pg_sw_finish_widget_html(strtr($html, array('^^__a^^' => '', '^^__b^^' => 'B')), $scope), 'the small cut, the big kept');

	$nonce = pg_sw_marker_nonce();
	pg_assert_same('<p>x</p><p>y</p>', pg_sw_finish_strip_carriers('<p>x</p><!--pg-cv:' . $nonce . ':4-->' . $big . '<!--/pg-cv:' . $nonce . ':4--><p>y</p>'), 'a big carrier');
}

// A row template placed inside a copy of itself (a reply in its comment)
// nests a span in a span of the same number; each keeps its own value.
function test_widget_finish_span_nested_in_its_own_copy()
{
	$tpl  = pg_sw_finish_wrap('<div>^^__a^^[replies]</div>', _widget_finish_spec('__a'));
	$fill = function ($a, $replies) use ($tpl) {
		return strtr($tpl, array('^^__a^^' => $a, '[replies]' => $replies));
	};
	$scope = array('kind' => 'catalog_listing');

	pg_assert_same('<div>P</div>', pg_sw_finish_widget_html($fill('P', $fill('', '')), $scope), 'the reply cut, its parent kept');
	pg_assert_same('', pg_sw_finish_widget_html($fill('', $fill('R', '')), $scope), 'the parent cut with its reply');
	pg_assert_same('<div>P<div>R</div></div><div>Q</div>', pg_sw_finish_widget_html($fill('P', $fill('R', '')) . $fill('Q', ''), $scope), 'both kept, then a sibling');
}

// The pair finder counts an open marker of its number inside the pair, and
// only there: one after the pair's close belongs to a later pair.
function test_widget_finish_pair_end()
{
	$open  = '<!--o:1:';
	$close = '<!--/c:1-->';
	$h     = 'x<!--o:1:-->a<!--o:1:-->b' . $close . 'c' . $close . 'd<!--o:1:-->e' . $close;
	$from  = strlen('x<!--o:1:-->');
	$at    = strpos($h, $close . 'd');
	pg_assert_same(array($at, $at + strlen($close)), _pg_sw_finish_pair_end($h, $open, $close, $from), 'a nested pair of the same number is skipped');

	$h = 'a' . $close . 'b<!--o:1:-->c' . $close;
	pg_assert_same(array(1, 1 + strlen($close)), _pg_sw_finish_pair_end($h, $open, $close, 0), 'an open marker after the close is not counted');
	pg_assert_same(array(0, strlen($close)), _pg_sw_finish_pair_end($close . 'z', $open, $close, 0), 'an empty body');
	pg_assert_same(null, _pg_sw_finish_pair_end('a<!--o:1:-->b' . $close, $open, $close, 0), 'a nested pair without its own close');
	pg_assert_same(null, _pg_sw_finish_pair_end('no close here', $open, $close, 0), 'no close marker');
}

// The edit pencil still finds a row's first element when the row starts
// with a condition.
function test_widget_finish_edit_chip_finds_first_element()
{
	$row = pg_sw_finish_wrap('<div class="card">^^__d^^</div>', _widget_finish_spec('__d'));
	$out = pg_sw_add_edit_chip(str_replace('^^__d^^', 'x', $row), '/edit?id=1', 'Edit', 'page');
	pg_assert_same('<div class="card pg-edit-host"', substr(pg_sw_finish_widget_html($out, array('kind' => 'catalog_listing')), 0, 30));
}

function _widget_finish_chips($html)
{
	return substr_count($html, 'class="pg-edit-chip ');
}

// Whether the pencil to $href is the first child of an element with tag
// $host that carries pg-edit-host.
function _widget_finish_chip_in($html, $host, $href)
{
	return (bool)preg_match('#<' . $host . '\b[^>]*\bclass="[^"]*\bpg-edit-host"[^>]*><a class="pg-edit-chip [^"]*" href="' . preg_quote($href, '#') . '"#', $html);
}

// The form item view's order: fill, pg-hide-if-empty, the pencil on the
// record's first element, then the pass. A condition that cuts the
// starter's first "label: value" row takes the row away, not the pencil:
// the pencil goes in the first element the record keeps.
function test_widget_finish_edit_chip_survives_a_cut_first_row()
{
	$row = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'mb-3 pg-hide-if-empty'), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Section', 'cssClass' => 'small mb-0'), 'children' => array()),
		_widget_finish_node('section', 'section', array('op' => 'eq', 'value' => 'kültür'), 'mb-0 pg-field-value'),
	));
	$title = array('type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h2', 'text' => 'T', '_bindings' => array('text' => 'title')), 'children' => array());
	$href  = '/pinegrap/edit_submitted_form.php?id=1';

	foreach (array('Kültür' => 'div', 'Yemek' => 'h2') as $section => $host) {
		$html = _widget_finish_in_scope('form_item_view', function () use ($row, $title, $section, $href) {
			$tpl  = _render_tree_node($row) . _render_tree_node($title);
			$html = pg_sw_hide_empty_fields(pg_sw_sweep_tokens(_pg_sw_fill_tokens($tpl, array('section' => $section, 'title' => 'A title'))));
			return pg_sw_add_edit_chip($html, $href, 'Edit the record', 'forms');
		});
		$out = pg_sw_finish_widget_html($html, array('kind' => 'form_item_view'));
		pg_assert_same(1, _widget_finish_chips($out), $section . ': one pencil');
		pg_assert_true(_widget_finish_chip_in($out, $host, $href), $section . ': in the first element kept');
		pg_assert_contains('A title', $out, $section);
		_widget_finish_no_markers($out, $section);
		pg_assert_same(false, strpos($out, 'pg-chip:'), $section . ': no pencil marker left');
	}
	pg_assert_same(false, strpos($out, '>Section</p>'), 'the cut row went');
}

// A catalog card whose first element (a badge) is cut keeps its pencil on
// the next element; a card whose badge stays has it in the badge, as
// before; a record cut whole takes its pencil with it, never handing it to
// the next record.
function test_widget_finish_edit_chip_catalog_cut_first_element()
{
	$tpl = pg_sw_finish_wrap('<span class="badge">^^__d^^</span>', _widget_finish_spec('__d', 'gt', '0'))
		. '<div class="card">^^__name^^</div>';
	$whole = pg_sw_finish_wrap('<div class="card">^^__name^^</div>', _widget_finish_spec('__d', 'gt', '0'));
	$rows  = '';
	foreach (array(array($tpl, '0', 'Chair'), array($tpl, '10%', 'Desk'), array($whole, '0', 'Lamp'), array($whole, '5', 'Shelf')) as $i => $r) {
		$record = strtr($r[0], array('^^__d^^' => $r[1], '^^__name^^' => $r[2]));
		$rows  .= pg_sw_add_edit_chip($record, '/edit_product.php?id=' . ($i + 1), 'Edit the product', 'ecommerce');
	}
	$out = pg_sw_finish_widget_html($rows, array('kind' => 'catalog_listing'));

	pg_assert_same(3, _widget_finish_chips($out), 'one pencil per record left');
	pg_assert_true(_widget_finish_chip_in($out, 'div', '/edit_product.php?id=1'), 'badge cut: the card');
	pg_assert_true(_widget_finish_chip_in($out, 'span', '/edit_product.php?id=2'), 'badge kept: the badge');
	pg_assert_same(false, strpos($out, 'id=3"'), 'the record cut whole has none');
	pg_assert_same(false, strpos($out, 'Lamp'));
	pg_assert_true(_widget_finish_chip_in($out, 'div', '/edit_product.php?id=4'), 'the next record keeps its own');
	pg_assert_same(1, substr_count($out, '<div class="card pg-edit-host"><a class="pg-edit-chip pg-edit-chip-ecommerce" href="/edit_product.php?id=4"'), 'and only its own');
	_widget_finish_no_markers($out);
}

// A box whose rows were all cut goes in the pg-hide-if-empty rerun; the
// pencil does not keep it standing as if it were content.
function test_widget_finish_edit_chip_does_not_keep_an_empty_box()
{
	$tpl = '<div class="card pg-hide-if-empty">'
		. pg_sw_finish_wrap('<p>^^__a^^</p>', _widget_finish_spec('__a', 'gt', '5'))
		. '</div><p class="foot">^^title^^</p>';
	$html = pg_sw_hide_empty_fields(strtr($tpl, array('^^__a^^' => '1', '^^title^^' => 'T')));
	$out  = pg_sw_finish_widget_html(pg_sw_add_edit_chip($html, '/e?id=7', 'Edit the record', 'forms'), array('kind' => 'form_list_view'));
	pg_assert_same(false, strpos($out, 'class="card'), 'the emptied box went');
	pg_assert_same(1, _widget_finish_chips($out));
	pg_assert_true(_widget_finish_chip_in($out, 'p', '/e?id=7'), 'the pencil is on the element the record kept');
}

// Once its conditions are resolved, a record whose first element cannot
// hold a pencil (a link) or that kept nothing gets none, as
// pg_sw_add_edit_chip() decides for any record.
function test_widget_finish_edit_chip_follows_the_insert_rules()
{
	$scope = array('kind' => 'catalog_listing');
	$tpl   = pg_sw_finish_wrap('<div>^^__a^^</div>', _widget_finish_spec('__a')) . '<a href="/p">^^__b^^</a>';
	$out   = pg_sw_finish_widget_html(pg_sw_add_edit_chip(strtr($tpl, array('^^__a^^' => '', '^^__b^^' => 'B')), '/e?id=1', 'Edit', 'page'), $scope);
	pg_assert_same('<a href="/p">B</a>', $out, 'a link cannot hold one');

	$tpl = pg_sw_finish_wrap('<div>^^__a^^</div>', _widget_finish_spec('__a'));
	$out = pg_sw_finish_widget_html(' ' . pg_sw_add_edit_chip(strtr($tpl, array('^^__a^^' => '')), '/e?id=1', 'Edit', 'page') . ' ', $scope);
	pg_assert_same('  ', $out, 'a record that kept nothing');
}

// The pencil of a record with no condition goes in at once, as outside a
// widget; a kept record gets the same markup either way.
function test_widget_finish_edit_chip_unchanged_without_a_cut()
{
	$wrapped = pg_sw_finish_wrap('<b>x</b>', _widget_finish_spec('__a'));
	pg_assert_true(pg_sw_finish_holds_condition($wrapped));
	pg_assert_false(pg_sw_finish_holds_condition('<b>x</b><!--pg-if:0000000000000000:1:not_empty:-->'), 'another nonce');
	pg_assert_same(0, strpos(pg_sw_finish_defer_chip('<b>x</b>', '/e', 'E', 'page'), '<!--pg-chip:' . pg_sw_marker_nonce() . ':'));
	pg_assert_same('<b>x</b>', pg_sw_finish_place_chips('<b>x</b>', pg_sw_marker_nonce()));

	$plain = '<div class="card">x</div>';
	$out   = _widget_finish_in_scope('catalog_listing', function () use ($plain) {
		return pg_sw_add_edit_chip($plain, '/e?id=1', 'Edit', 'ecommerce');
	});
	pg_assert_same(0, strpos($out, '<div class="card pg-edit-host"><a class="pg-edit-chip '), 'no condition: at once');

	$tpl    = '<div class="card">' . pg_sw_finish_wrap('<b>^^__a^^</b>', _widget_finish_spec('__a')) . '</div>';
	$record = strtr($tpl, array('^^__a^^' => 'A'));
	$scope  = array('kind' => 'form_list_view');
	pg_assert_same(
		pg_sw_add_edit_chip(pg_sw_finish_widget_html($record, $scope), '/e?id=1', 'Edit "it"', 'forms'),
		pg_sw_finish_widget_html(pg_sw_add_edit_chip($record, '/e?id=1', 'Edit "it"', 'forms'), $scope),
		'kept: the same markup'
	);
}

// A record inside a record (a reply in its comment): each keeps its own
// pencil in its own first element kept.
function test_widget_finish_edit_chip_nested_records()
{
	$tpl = pg_sw_finish_wrap('<b>^^__flag^^</b>', _widget_finish_spec('__flag')) . '<div class="body">^^__text^^[replies]</div>';
	$record = function ($text, $replies, $id) use ($tpl) {
		return pg_sw_add_edit_chip(strtr($tpl, array('^^__flag^^' => '', '^^__text^^' => $text, '[replies]' => $replies)), '/c?id=' . $id, 'Edit the comment', 'page');
	};
	$out = pg_sw_finish_widget_html($record('P', $record('R', '', 2), 1), array('kind' => 'comments'));
	pg_assert_same(2, _widget_finish_chips($out));
	pg_assert_same(1, preg_match('#^<div class="body pg-edit-host"><a class="pg-edit-chip pg-edit-chip-page" href="/c\?id=1".*?</a>P<div class="body pg-edit-host"><a class="pg-edit-chip pg-edit-chip-page" href="/c\?id=2".*?</a>R</div></div>$#s', $out));
}

// A pencil marker without its pair is taken out; a forged one in a value
// (another nonce) stays text.
function test_widget_finish_edit_chip_marker_leftovers()
{
	$nonce = pg_sw_marker_nonce();
	pg_assert_same('<p>a</p>', pg_sw_finish_widget_html('<!--pg-chip:' . $nonce . ':999:e30--><p>a</p>', array('kind' => 'catalog_listing')));
	pg_assert_same('<p>a</p>', pg_sw_finish_widget_html('<p>a</p><!--/pg-chip:' . $nonce . ':998-->', array('kind' => 'catalog_listing')));
	$forged = '<!--pg-chip:0000000000000000:1:e30--><p>a</p><!--/pg-chip:0000000000000000:1-->';
	pg_assert_same($forged, pg_sw_finish_widget_html($forged, array('kind' => 'catalog_listing')));
}

// The hooks: the dispatcher's callback finishes both the e-mail and the page
// output inside a scope, and functions.php loads both modules.
function test_widget_finish_hooks_in_source()
{
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('function ($m) use ($render, $email, $configs, &$parts_pending, &$parts_drawn) {', $designer);
	pg_assert_contains('$out = $email ? pg_sw_finish_widget_html($out, $scope)', $designer, 'e-mail branch');
	pg_assert_contains(': pg_img_responsive_html(pg_img_fluid_alias(pg_sw_finish_widget_html($out, $scope)));', $designer, 'page branch');
	pg_assert_same(1, preg_match('/pg_sw_finish_open\([^;]*;\s*try \{\s*\$out = \$render\(\$m(, \$placement(, \$own)?)?\);\s*\} finally \{\s*pg_sw_finish_close\(\);/', $designer), 'scope around the render');

	$functions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_conditions.php');", $functions);
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_finish.php');", $functions);
}

<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the classic site converters in the visual designer: every
 * classic page type a system widget stands in for (pg_classic_widget_kinds(),
 * includes/fn/layouts.php) is offered "Convert to design elements" on its
 * page's own system region, custom layout or not; the login region, the cart
 * region and the comments block get "Make designable" (SD_REGION_WIDGETS_PLAIN
 * in assets/js/style_designer.src.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function test_classic_widget_kinds_cover_every_widget_page_type()
{
	$kinds = pg_classic_widget_kinds();
	$labels = pg_sw_type_labels();

	foreach (pg_layout_widget_kinds() as $type => $kind) {
		pg_assert_same($kind, isset($kinds[$type]) ? $kinds[$type] : null, $type . ': the layout offer\'s kind');
	}
	foreach (pg_sw_page_type_widgets() as $type => $kind) {
		pg_assert_same($kind, isset($kinds[$type]) ? $kinds[$type] : null, $type . ': the widget page\'s kind');
	}
	foreach ($kinds as $type => $kind) {
		pg_assert_true(isset($labels[$kind]), $type . ': ' . $kind . ' is a registered kind');
	}

	// The types the layout offer alone left without a converter.
	foreach (array('calendar view' => 'calendar_view', 'calendar event view' => 'calendar_event_view', 'folder view' => 'folder_view',
		'logout' => 'logout', 'affiliate sign up form' => 'affiliate_sign_up_form') as $type => $kind) {
		pg_assert_same($kind, isset($kinds[$type]) ? $kinds[$type] : null, $type . ' converts');
	}

	// No widget: listed for the architect, never offered.
	foreach (array('standard', 'custom form', 'email a friend', 'form view directory', 'affiliate sign up confirmation',
		'affiliate welcome', 'order receipt') as $type) {
		pg_assert_true(!isset($kinds[$type]), $type . ' gets no converter');
	}
}

function test_classic_widget_offers_reach_the_editor()
{
	pg_assert_same(array(), pg_classic_widget_offers(array()), 'no pages, no offers');
	pg_assert_same(array(), pg_classic_widget_offers(array(0, -3, 'x')), 'no real page ids, no query');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'classicWidgets' => ((int)\$user['role'] <= 1) ? pg_classic_widget_offers(\$tab_page_ids) : array(),", $screen, 'every page of the design, for the layout roles');

	$layouts = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
	pg_assert_contains('return _pg_widget_offers($layout_page_ids, pg_layout_widget_kinds());', $layouts, 'the layout offer keeps its kinds');
}

function test_classic_widget_convert_editor_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("var SD_REGION_WIDGETS_PLAIN = { login: 'login_region', cart: 'cart_link', comments_block: 'comments' };", $js, 'the session regions and the comments block');
	$labels = pg_sw_type_labels();
	foreach (array('login_region', 'cart_link', 'comments') as $kind) {
		pg_assert_true(isset($labels[$kind]), $kind . ' is a registered kind');
	}

	pg_assert_contains("_sdClassicConvertPropsHtml(n) + _sdLayoutPropsHtml() + _sdLayoutWidgetPropsHtml(n)", $js, 'the system region panel offers the converter first');
	pg_assert_contains("_sdClassicConvert(selectedNode); });", $js, 'the button is wired');
	pg_assert_contains("var o = _design.classicWidgets[p.page_id];", $js, 'the offer comes from the design');
	pg_assert_contains("_sdRegionToWidget(regionNode, o.kind, _sdLayoutWidgetCfg(o), function (newName) {", $js, 'the carried settings, then the defaults');
	pg_assert_contains("parent.children.splice(idx, 1, ref);", $js, 'the widget takes the region\'s place');
}

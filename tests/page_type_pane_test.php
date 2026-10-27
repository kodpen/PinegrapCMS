<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Page Type pane of the designer's settings modal: the types
 * it handles, the rights it shares with edit_page.php, the layout type a
 * save leaves, and the order of the endpoint's checks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');

function test_page_type_pane_has_fields()
{
	pg_assert_false(pg_page_type_pane_has_fields('standard'));
	pg_assert_false(pg_page_type_pane_has_fields('logout'), 'no fields, no layout');
	pg_assert_false(pg_page_type_pane_has_fields('error'));
	pg_assert_true(pg_page_type_pane_has_fields('my account'), 'a layout only');

	foreach (array('email a friend', 'folder view', 'photo gallery', 'search results', 'update address book',
		'custom form', 'custom form confirmation', 'form list view', 'form item view', 'form view directory',
		'calendar view', 'calendar event view', 'catalog', 'catalog detail', 'express order', 'order form',
		'shopping cart', 'shipping address and arrival', 'shipping method', 'billing information', 'order preview',
		'order receipt', 'affiliate sign up form') as $type) {
		pg_assert_true(pg_page_type_pane_has_fields($type), $type);
	}
}

function test_page_type_user_may_set_follows_the_role_ladder()
{
	$basic = array('role' => 3, 'manage_calendars' => 0, 'set_page_type_catalog' => 1, 'set_page_type_photo_gallery' => 0,
		'set_page_type_calendar_event_view' => 1);

	pg_assert_true(pg_page_type_user_may_set(array('role' => 0), 'order preview'), 'administrator');
	pg_assert_true(pg_page_type_user_may_set(array('role' => 2), 'search results'), 'manager');
	pg_assert_true(pg_page_type_user_may_set($basic, 'standard'));
	pg_assert_true(pg_page_type_user_may_set($basic, 'catalog'));
	pg_assert_true(!pg_page_type_user_may_set($basic, 'photo gallery'), 'right off');
	pg_assert_true(!pg_page_type_user_may_set($basic, 'order form'), 'right missing');
	pg_assert_true(!pg_page_type_user_may_set($basic, 'search results'), 'no right exists for it');
	pg_assert_true(!pg_page_type_user_may_set($basic, 'calendar event view'), 'calendars right off');

	$basic['manage_calendars'] = 1;
	pg_assert_true(pg_page_type_user_may_set($basic, 'calendar event view'));
}

function test_page_type_pane_layout_type()
{
	pg_assert_same('custom', pg_page_type_pane_layout_type('catalog', 'custom', 'system', 1), 'designer');
	pg_assert_same('system', pg_page_type_pane_layout_type('catalog', 'system', 'custom', 2), 'back to system');
	pg_assert_same('system', pg_page_type_pane_layout_type('catalog', 'custom', 'system', 2), 'manager keeps system');
	pg_assert_same('custom', pg_page_type_pane_layout_type('catalog', null, 'custom', 0), 'not posted');
	pg_assert_same('custom', pg_page_type_pane_layout_type('catalog', 'other', 'custom', 0), 'unknown value');
	pg_assert_same('custom', pg_page_type_pane_layout_type('catalog', array('system'), 'custom', 0), 'not a string');
	pg_assert_same('system', pg_page_type_pane_layout_type('standard', 'custom', 'system', 0), 'no layout');
}

function test_page_type_pane_endpoint_checks_before_it_writes()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');
	$body = substr($source, strpos($source, 'function pg_page_type_pane_request('));

	$token = strpos($body, 'hash_equals(');
	$folder = strpos($body, 'check_edit_access(');
	$right = strpos($body, 'pg_page_type_user_may_set(');
	$types = strpos($body, 'pg_page_type_pane_has_fields(');
	$lock = strpos($body, 'pg_collab_may_edit_page(');
	$form = strpos($body, 'pg_page_type_custom_form_errors(');
	$checked = strpos($body, 'pg_page_type_properties_for_save(');
	$write = strpos($body, 'pg_page_form_save_type_links(');

	pg_assert_true($token !== false && $token < $folder, 'token first');
	pg_assert_true($folder < $right && $right < $types && $types < $lock && $lock < $form && $form < $write, 'every check before the first write');
	pg_assert_true($checked < $write, 'the access rules of edit_page.php before the write');
	pg_assert_true(strpos($body, 'output_error(') === false, 'refuses in JSON');
}

function test_page_type_pane_wiring()
{
	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php');
	$hook = strpos($edit, 'pg_page_type_pane_request(');
	pg_assert_true($hook !== false && $hook < strpos($edit, 'pg_page_is_visual_design('), 'answered before the redirect to the designer');
	pg_assert_same(2, substr_count($edit, 'pg_page_type_user_may_set('), 'the form and the handler share the rights');
	pg_assert_same(1, substr_count($edit, 'pg_page_type_properties_for_save('), 'the handler saves the type through the shared checks');
	pg_assert_same(0, substr_count($edit, 'validate_contact_group_access('), 'no second copy of the checks');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "\$rail('page-type', 'bi-sliders', lang('Page Type'), 'page', false, true)") !== false, 'pill hidden until a classic page is open');

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true(strpos($js, '_sdPageTypeSync(p);') !== false, 'the pill follows the page on screen');
}

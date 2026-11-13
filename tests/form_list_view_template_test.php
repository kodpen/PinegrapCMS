<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form list view's default template: the browse and search
 * controls are read by Bootstrap 3 and 5.3.8 alike, with no icon font.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_flv_template_code()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/templates/form_list_view.php');
}

function test_flv_template_draws_no_icon_font()
{
	$code = _test_flv_template_code();
	pg_assert_same(false, strpos($code, 'glyphicon'), 'no Glyphicons, which a 5.3.8 site does not load');
	pg_assert_same(5, substr_count($code, '<svg '), 'each toggle, search and clear icon is inline SVG');
}

function test_flv_template_buttons_fill_their_group()
{
	$code = _test_flv_template_code();
	// Every button inside the groups keeps Bootstrap 3's span and gets the
	// 5.3.8 names that size it to the field beside it.
	pg_assert_same(4, substr_count($code, 'class="btn btn-default btn-secondary h-100'), 'four buttons sized to the group');
	pg_assert_same(4, substr_count($code, 'input-group-btn'), 'Bootstrap 3 spans kept (three spans and the comment)');
	pg_assert_true(strpos($code, 'class="form-control form-select"') !== false, 'the browse select has 5.3.8\'s arrow');
	pg_assert_same(2, substr_count($code, 'form-group mb-3 input-group'), 'both groups keep their bottom margin on 5.3.8');
}

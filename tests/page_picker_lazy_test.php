<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page pickers of the Create Page and Edit Page screens
 * (pg_page_select_options_html(), pg_page_select_lazy_html()): the list of
 * every page is printed once in a template and each untyped picker carries
 * only its stored page until it is opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_picker_lazy_test_options()
{
	return array(
		array('id' => 3, 'label' => 'about', 'selected' => false),
		array('id' => 7, 'label' => 'contact <us>', 'selected' => true),
		array('id' => 9, 'label' => 'old [ARCHIVED]', 'selected' => false),
	);
}

function test_page_picker_lazy_options_html_lists_every_page()
{
	pg_assert_same(
		'<option value="3">about</option><option value="7" selected="selected">contact &lt;us&gt;</option><option value="9">old [ARCHIVED]</option>',
		pg_page_select_options_html(page_picker_lazy_test_options()),
		'every page is an option, the stored one selected, names escaped');
	pg_assert_same('', pg_page_select_options_html(array()), 'no pages, no options');
}

function test_page_picker_lazy_html_keeps_only_the_stored_page()
{
	$html = pg_page_select_lazy_html(page_picker_lazy_test_options());
	pg_assert_contains('<option value="7" selected="selected">contact &lt;us&gt;</option>', $html, 'the stored page stays selected, so a plain Save keeps it');
	pg_assert_false(strpos($html, 'value="3"') !== false, 'other pages wait for the picker to open');
	pg_assert_contains('data-pg-page-fill', $html, 'a marker tells the script to fill the picker');
	pg_assert_contains('disabled', $html, 'the marker is never sent with the form');
}

function test_page_picker_lazy_html_without_a_stored_page()
{
	$options = page_picker_lazy_test_options();
	$options[1]['selected'] = false;
	pg_assert_same('<option value="" hidden disabled data-pg-page-fill></option>', pg_page_select_lazy_html($options), 'only the marker');
}

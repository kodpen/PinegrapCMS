<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page names mass_edit.php saves: the rules the page screens
 * apply (pg_page_form_bulk_name()), so a name that is blank once trimmed, or
 * a catalog or catalog detail page name with a forward slash, is not saved.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

function test_mass_edit_page_name_cleaned_as_the_page_screen()
{
	pg_assert_same('my_page_x', pg_page_form_bulk_name(' my page&x ', 'standard'), 'trimmed, spaces and ampersands become underscores');
	pg_assert_same('a/b', pg_page_form_bulk_name('a/b', 'standard'), 'a slash is kept on other types');
	pg_assert_same('shop', pg_page_form_bulk_name('shop', 'catalog'));
}

function test_mass_edit_page_name_refuses_what_the_page_screen_refuses()
{
	pg_assert_same(null, pg_page_form_bulk_name('   ', 'standard'), 'only spaces is a blank name');
	pg_assert_same(null, pg_page_form_bulk_name('', 'standard'));
	pg_assert_same(null, pg_page_form_bulk_name('a/b', 'catalog'), 'catalog refuses a slash');
	pg_assert_same(null, pg_page_form_bulk_name('a/b', 'catalog detail'), 'catalog detail refuses a slash');
	pg_assert_same(null, pg_page_form_bulk_name(array('x'), 'standard'), 'a list is no name');
}

function test_mass_edit_saves_names_through_the_shared_rule()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mass_edit.php');

	pg_assert_same(2, substr_count($source, 'pg_page_form_bulk_name('), 'the save and Find & Replace clean the name the same way');
	pg_assert_same(0, substr_count($source, "str_replace(' ', '_'"), 'no copy of the cleaning of its own');
}

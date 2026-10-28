<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_cf_template_schema_author() (includes/fn/custom_form.php):
 * the BlogPosting author a design template's form names, written as the
 * custom_form_pages.schema_author value with a field name turned into the
 * id of the form's field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A field named by the template becomes field:<id> of the form's field,
// whatever the case of the name.
function test_custom_form_schema_author_field_name_to_id()
{
	$ids = array('title' => 11, 'byline' => 14);
	pg_assert_same('field:14', pg_cf_template_schema_author('field:byline', $ids));
	pg_assert_same('field:14', pg_cf_template_schema_author(' field:Byline ', $ids));
}

// A field the form does not have, or no name, keeps the default: the contact.
function test_custom_form_schema_author_missing_field_is_contact()
{
	$ids = array('title' => 11);
	pg_assert_same('contact', pg_cf_template_schema_author('field:byline', $ids));
	pg_assert_same('contact', pg_cf_template_schema_author('field:', $ids));
	pg_assert_same('contact', pg_cf_template_schema_author('field:byline', 'not a list'));
}

// The two other choices pass through; anything else reads as the contact.
function test_custom_form_schema_author_plain_choices()
{
	pg_assert_same('organization', pg_cf_template_schema_author('organization', array()));
	pg_assert_same('contact', pg_cf_template_schema_author('contact', array()));
	pg_assert_same('contact', pg_cf_template_schema_author('field:14', array()));
	pg_assert_same('contact', pg_cf_template_schema_author('someone', array()));
	pg_assert_same('contact', pg_cf_template_schema_author(array('x'), array()));
}

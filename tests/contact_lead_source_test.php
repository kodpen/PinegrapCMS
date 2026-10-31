<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lead source of a contact chosen from the referral sources
 * (includes/fn/contacts.php, includes/forms/contact_form.php): the choices
 * the contact form offers, a stored value outside the list kept as its own
 * choice, and the check that refuses an unknown new value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

function contact_lead_source_test_sources()
{
	return array(
		array('name' => 'Word of Mouth', 'code' => 'Referral'),
		array('name' => 'Internet Search', 'code' => 'Internet Search'),
	);
}

function test_contact_lead_source_options_list_the_sources()
{
	$options = pg_contact_lead_source_options(contact_lead_source_test_sources(), '');

	pg_assert_same(array(
		array('value' => '', 'label' => '', 'kept' => false),
		array('value' => 'Referral', 'label' => 'Word of Mouth', 'kept' => false),
		array('value' => 'Internet Search', 'label' => 'Internet Search', 'kept' => false),
	), $options, 'an empty choice, then each source by its code');

	// A stored code is one of the list, not added again.
	pg_assert_same(3, count(pg_contact_lead_source_options(contact_lead_source_test_sources(), 'Referral')), 'stored code not repeated');
}

function test_contact_lead_source_options_keep_an_old_value()
{
	$options = pg_contact_lead_source_options(contact_lead_source_test_sources(), 'Trade fair 2019');

	pg_assert_same(4, count($options), 'the old value is one more choice');
	pg_assert_same(array('value' => 'Trade fair 2019', 'label' => 'Trade fair 2019', 'kept' => true), $options[3], 'kept as it is');

	// A stored name (typed by hand before the list) is not the code: kept too.
	$options = pg_contact_lead_source_options(contact_lead_source_test_sources(), 'Word of Mouth');
	pg_assert_same('Word of Mouth', $options[3]['value'], 'name typed by hand kept');

	// No sources at all: the empty choice and the stored value.
	pg_assert_same(2, count(pg_contact_lead_source_options(array(), 'Fair')), 'no list');
}

function test_contact_lead_source_check()
{
	$sources = contact_lead_source_test_sources();

	pg_assert_same(array('value' => '', 'error' => ''), pg_contact_lead_source_check('', $sources, 'Fair'), 'emptied');
	pg_assert_same(array('value' => 'Referral', 'error' => ''), pg_contact_lead_source_check('Referral', $sources, ''), 'a code');
	pg_assert_same(array('value' => 'Referral', 'error' => ''), pg_contact_lead_source_check(' word of mouth ', $sources, ''), 'a name, stored as its code');
	pg_assert_same(array('value' => 'Fair', 'error' => ''), pg_contact_lead_source_check('Fair', $sources, 'Fair'), 'the current old value kept');

	$check = pg_contact_lead_source_check('Fair', $sources, 'Other fair');
	pg_assert_same('Fair', $check['value'], 'refused value returned as sent');
	pg_assert_contains('Fair', $check['error'], 'refusal names the value');

	$check = pg_contact_lead_source_check(array('x'), $sources, '');
	pg_assert_true($check['error'] !== '', 'an array is refused');
}

function test_contact_form_lead_source_field()
{
	$html = pg_contact_form_lead_source_field(array('lead_source' => 'Fair <2019>'), contact_lead_source_test_sources());

	pg_assert_contains('<select name="lead_source" id="lead_source" class="form-control">', $html, 'a select');
	pg_assert_contains('<option value="Referral">Word of Mouth</option>', $html, 'a source');
	pg_assert_contains('<option value="Fair &lt;2019&gt;" selected="selected">Fair &lt;2019&gt;</option>', $html, 'old value kept, selected, escaped');
	pg_assert_contains('class="form-text"', $html, 'the kept value is explained');

	$html = pg_contact_form_lead_source_field(array(), contact_lead_source_test_sources());
	pg_assert_contains('<option value="" selected="selected"></option>', $html, 'new contact: the empty choice');
	pg_assert_false(strpos($html, 'form-text') !== false, 'no note without a kept value');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');
	pg_assert_contains('pg_contact_form_lead_source_field($values, pg_referral_sources())', $source, 'the detail cards draw the select');
}

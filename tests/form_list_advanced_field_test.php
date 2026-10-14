<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_form_list_advanced_field_properties() (includes/fn/forms.php),
 * which reads one field tag of a form list's advanced search layout: strict
 * JSON, and the JavaScript object form the panel lists the field tags in
 * ({{name: 'subject', dynamic: true}}). Text that cannot be read gives null.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Strict JSON comes back exactly as json_decode() gives it.
function test_form_list_advanced_field_strict_json()
{
	$tags = array(
		'{"name":"subject"}',
		'{"name": "subject", "size": 20, "dynamic": true}',
		'{"name":"newest_activity_date_and_time","operator":"is greater than or equal to","help":false,"size":18}',
		'{"name":"submit_button","label":"Ara \"şimdi\"","class":"btn"}',
		'{}',
	);

	foreach ($tags as $tag) {
		pg_assert_same(json_decode($tag, true), pg_form_list_advanced_field_properties($tag), $tag);
	}
}

// The tags the panel lists and the sample data lists use.
function test_form_list_advanced_field_object_form()
{
	pg_assert_same(
		array('name' => 'submitter', 'dynamic' => true),
		pg_form_list_advanced_field_properties("{name: 'submitter', dynamic: true}"));

	pg_assert_same(
		array('name' => 'submit_button'),
		pg_form_list_advanced_field_properties("{name:'submit_button'}"));

	pg_assert_same(
		array('name' => 'subject', 'size' => 20),
		pg_form_list_advanced_field_properties("{name: 'subject',size: 20}"));

	pg_assert_same(
		array('name' => 'newest_activity_date_and_time', 'operator' => 'is less than or equal to', 'help' => false, 'size' => 18),
		pg_form_list_advanced_field_properties("{name: 'newest_activity_date_and_time', operator: 'is less than or equal to', help: false, size: 18}"));

	// Keys and values in any script, spread over lines.
	pg_assert_same(
		array('name' => 'e-mail', 'operatör' => 'x', 'label' => 'Gönderen'),
		pg_form_list_advanced_field_properties("{\n\tname: 'e-mail',\n\toperatör: \"x\",\n\tlabel: 'Gönderen'\n}"));
}

// Trailing commas are dropped, in the object and in a list inside it.
function test_form_list_advanced_field_trailing_commas()
{
	pg_assert_same(
		array('name' => 'subject', 'size' => 5),
		pg_form_list_advanced_field_properties("{name: 'subject', size: 5, }"));

	pg_assert_same(
		array('name' => 'subject', 'list' => array(1, 2)),
		pg_form_list_advanced_field_properties("{name: 'subject', list: [1, 2,],\n}"));
}

// Quotes, escapes and key-like text inside strings come through unchanged.
function test_form_list_advanced_field_strings()
{
	pg_assert_same(
		array('label' => 'Say "hi"'),
		pg_form_list_advanced_field_properties("{label: 'Say \"hi\"'}"));

	pg_assert_same(
		array('label' => "It's"),
		pg_form_list_advanced_field_properties("{label: 'It\\'s'}"));

	pg_assert_same(
		array('label' => 'a\\b'),
		pg_form_list_advanced_field_properties("{label: 'a\\\\b'}"));

	pg_assert_same(
		array('label' => 'size: 5, }', 'help' => "name: 'x',]"),
		pg_form_list_advanced_field_properties("{label: 'size: 5, }', help: \"name: 'x',]\"}"));
}

// Text that is neither JSON nor the object form gives null, without a warning
// (the runner turns a warning into an error).
function test_form_list_advanced_field_malformed()
{
	$tags = array(
		'',
		'{',
		"{name: 'subject'",
		"{name: 'subject}",
		'{name: subject}',
		"{name 'subject'}",
		"{1: 'subject'}",
		"{name: 'a' 'b'}",
		"{name: 'a\\x41'}",
		"{name: 'subject', size: 0x10}",
		"{name: '\xff'}",
		"{\xff: 'subject'}",
		'not a tag',
	);

	foreach ($tags as $tag) {
		pg_assert_same(null, pg_form_list_advanced_field_properties($tag), var_export($tag, true));
	}
}

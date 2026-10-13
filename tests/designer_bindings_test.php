<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for data binding tokens in designer widgets: a field bound from the
 * binding dropdown keeps its name as the form wrote it (_apply_bindings()),
 * and a form's data answers that name (pg_sw_index_form_data()). Field
 * names may carry a hyphen ("publish-date"); the token used to lose it and
 * the field printed nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The field name the dropdown offers is written into the token unchanged.
function test_designer_bindings_keep_hyphen_in_field_name()
{
	$props = _apply_bindings(array('_bindings' => array('text' => 'publish-date')));
	pg_assert_same('^^publish-date^^', $props['text']);
}

// The date format beside a binding still rides along after the name.
function test_designer_bindings_hyphen_name_keeps_format()
{
	$props = _apply_bindings(array(
		'_bindings'    => array('text' => 'publish-date'),
		'_bindFormats' => array('text' => 'd.m.Y'),
	));
	pg_assert_same('^^publish-date^^%%d.m.Y%%', $props['text']);
}

// Anything that could break out of the token is still dropped.
function test_designer_bindings_strip_markup_from_field_name()
{
	$props = _apply_bindings(array('_bindings' => array('text' => 'a"><b>^^x')));
	pg_assert_same('^^abx^^', $props['text']);
}

// A form's data answers the field name as written and, for a binding typed
// before hyphens were kept, the name without them.
function test_designer_bindings_form_data_answers_hyphen_name()
{
	$index = pg_sw_index_form_data(array(array(
		'form_id'       => 7,
		'data'          => '2020-04-17',
		'data_name'     => 'publish-date',
		'data_file_id'  => 0,
		'field_name'    => 'publish-date',
		'field_label'   => 'Yayınlanma Tarihi',
		'field_type'    => 'date',
		'field_wysiwyg' => 0,
		'file_name'     => null,
	)));
	pg_assert_true(isset($index[7]['publish-date']));
	pg_assert_true(isset($index[7]['publishdate']));
	pg_assert_same('2020-04-17', $index[7]['publish-date']['data']);
	pg_assert_same('date', $index[7]['publishdate']['type']);
}

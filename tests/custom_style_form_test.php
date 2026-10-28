<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the custom page style form shared by the create and the edit
 * screens (includes/forms/custom_style_form.php): a new style's defaults,
 * the selected options, the cards, the saved columns, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/custom_style_form.php');

function test_custom_style_form_defaults()
{
	$defaults = pg_custom_style_form_defaults();

	pg_assert_same('', $defaults['style_name'], 'a new style has no name');
	pg_assert_contains('<system></system>', $defaults['style_code'], 'the skeleton holds the system region');
	pg_assert_same('a', $defaults['collection'], 'collection A');
	pg_assert_same('', $defaults['layout_type'], 'no layout override');
	pg_assert_same('bottom_left', $defaults['social_networking_position'], 'social networking bottom left');
}

function test_custom_style_form_social_networking_position()
{
	$select = pg_custom_style_form_social_networking_position('top_right');

	pg_assert_contains('<option value="top_right" selected="selected">', $select, 'the stored position is selected');
	pg_assert_same(1, substr_count($select, 'selected="selected"'), 'only one position is selected');
	pg_assert_same(5, substr_count($select, '<option '), 'five positions');
	pg_assert_same(0, substr_count(pg_custom_style_form_social_networking_position(''), 'selected="selected"'), 'nothing selected without a position');
}

function test_custom_style_form_cards()
{
	$new = pg_custom_style_form_cards(pg_custom_style_form_defaults(), true);
	pg_assert_contains('value="a" checked="checked" class="radio"', $new, 'collection A on create');
	pg_assert_contains('<option value="bottom_left" selected="selected">', $new, 'bottom left on create');
	pg_assert_contains('&lt;system&gt;&lt;/system&gt;', $new, 'the skeleton is escaped into the editor');

	$stored = array('style_name' => 'Home "wide"', 'style_code' => '<p>', 'collection' => 'b', 'layout_type' => 'custom', 'social_networking_position' => 'disabled');
	$edit = pg_custom_style_form_cards($stored, false);
	pg_assert_contains('value="Home &quot;wide&quot;" name="name"', $edit, 'the stored name is escaped');
	pg_assert_contains('value="b" checked="checked" class="radio"', $edit, 'collection B');
	pg_assert_false(strpos($edit, 'value="a" checked') !== false, 'collection A not checked');
	pg_assert_contains('<option value="custom" selected="selected">', $edit, 'the layout override');
	pg_assert_false(strpos($edit, 'social_networking_position') !== false, 'no position list while social networking is off');
}

function test_custom_style_form_values()
{
	$post = array('name' => '  Home  ', 'code' => '<html>', 'social_networking_position' => 'top_left', 'collection' => 'b', 'layout_type' => 'system', 'id' => '4');

	pg_assert_same(
		array('style_name' => 'Home', 'style_code' => '<html>', 'social_networking_position' => 'top_left', 'collection' => 'b', 'layout_type' => 'system'),
		pg_custom_style_form_values($post, true),
		'the trimmed name and the columns in save order'
	);
	pg_assert_same(
		array('style_name' => 'Home', 'style_code' => '<html>', 'collection' => 'b', 'layout_type' => 'system'),
		pg_custom_style_form_values($post, false),
		'no position while social networking is off'
	);
}

function test_custom_style_form_screens_use_shared_form()
{
	foreach (array('add_custom_style.php', 'edit_custom_style.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/custom_style_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_custom_style_form_cards(', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_custom_style_form_values($_POST, SOCIAL_NETWORKING == TRUE)', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'designer')", $source, $file . ' keeps the designer gate');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Override Layout Type') !== false, $file . ' keeps no copy of the cards');
	}
}

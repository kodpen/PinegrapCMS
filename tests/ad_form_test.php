<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ad form shared by the create and the edit screens
 * (includes/forms/ad_form.php): the values a save writes, and that both
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

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');

function ad_form_test_liveform($fields)
{
	return new class($fields) {
		private $fields;

		public function __construct($fields)
		{
			$this->fields = $fields;
		}

		public function get_field_value($name)
		{
			return isset($this->fields[$name]) ? $this->fields[$name] : null;
		}
	};
}

function test_ad_form_values_keep_the_insert_and_update_order()
{
	$liveform = ad_form_test_liveform(array(
		'name' => 'Spring',
		'content' => '<a href="/site/sale">Sale</a>',
		'caption' => '<img src="/site/banner.png">',
		'ad_region_id' => 7,
		'label' => 'Tab',
		'sort_order' => '',
	));

	pg_assert_same(
		array('Spring', '<a href="{path}sale">Sale</a>', '<img src="{path}banner.png">', '7', 'Tab', ''),
		pg_ad_form_values($liveform, '/site/'),
		'name, content, caption, region, label, sort order; content and caption store the site path as {path}');
}

function test_ad_form_screens_use_shared_form()
{
	foreach (array('add_ad.php', 'edit_ad.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/ad_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_ad_form_cards($liveform)', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_ad_form_validate($liveform, $user, ', $source, $file . ' runs the shared checks');
		pg_assert_contains('array_merge(pg_ad_form_values($liveform), ', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, 'Dynamic Ad Region Display Options') !== false, $file . ' keeps no copy of the cards');
	}

	pg_assert_contains('pg_ad_form_validate($liveform, $user, null)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_ad.php'), 'create checks the name against every ad');
	pg_assert_contains("pg_ad_form_validate(\$liveform, \$user, (string) \$liveform->get_field_value('id'))", (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad.php'), 'edit leaves its own ad out of the name check');
}

function test_ad_form_region_hint_is_empty_when_a_region_exists()
{
	pg_assert_same('', pg_ad_form_region_hint(array('' => '', 'Sidebar' => '3'), 1), 'a designer with a region sees no hint');
	pg_assert_same('', pg_ad_form_region_hint(array('' => '', 'Sidebar' => '3'), 2), 'a manager with a region sees no hint');
}

function test_ad_form_region_hint_links_designers_to_create_ad_region()
{
	foreach (array(0, 1) as $role) {
		$hint = pg_ad_form_region_hint(array('' => ''), $role);

		pg_assert_contains(lang('No ad region exists yet.'), $hint, 'role ' . $role . ' is told no ad region exists');
		pg_assert_contains('<a href="add_ad_region.php"', $hint, 'role ' . $role . ' gets a link to Create Ad Region');
		pg_assert_contains(lang('Create Ad Region'), $hint, 'role ' . $role . ' link says Create Ad Region');
	}
}

function test_ad_form_region_hint_tells_managers_a_designer_creates_regions()
{
	$hint = pg_ad_form_region_hint(array('' => ''), 2);

	pg_assert_contains(lang('No ad region exists yet.'), $hint, 'a manager is told no ad region exists');
	pg_assert_contains(lang('A designer creates ad regions.'), $hint, 'a manager is told who creates ad regions');
	pg_assert_false(strpos($hint, 'add_ad_region.php') !== false, 'a manager gets no link to a screen it cannot open');
}

function test_ad_form_cards_print_the_region_hint_under_the_select()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');

	pg_assert_contains('pg_ad_form_region_hint($region_options, $user[\'role\'])', $source, 'the cards print the hint for the signed-in role');
}

// Sort Order is blank (random order) or a whole number; anything else used
// to be saved as 0 by MySQL, so the ad silently went back to random order.
function test_ad_form_sort_order_accepts_blank_and_whole_numbers()
{
	foreach (array('', '0', '1', '25', '9999', ' 7 ') as $value) {
		pg_assert_same('', pg_ad_form_sort_order_error($value), var_export($value, true));
	}
}

function test_ad_form_sort_order_refuses_anything_else()
{
	foreach (array('abc', '1.5', '-3', '1e3', '0x1A', '+4') as $value) {
		pg_assert_same(lang('Sort Order must be a whole number, or blank for random order.'), pg_ad_form_sort_order_error($value), var_export($value, true));
	}
}

function test_ad_form_validate_checks_the_sort_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');

	pg_assert_contains("pg_ad_form_sort_order_error(\$liveform->get_field_value('sort_order'))", $source);
	pg_assert_contains("\$liveform->mark_error('sort_order', ", $source);
}

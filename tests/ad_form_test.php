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

		public function output_field($attributes)
		{
			return '<input id="' . $attributes['id'] . '">';
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
		array('name' => 'Spring', 'content' => '<a href="{path}sale">Sale</a>', 'caption' => '<img src="{path}banner.png">', 'ad_region_id' => '7', 'label' => 'Tab', 'sort_order' => ''),
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
		pg_assert_contains('pg_ad_form_values($liveform), $user[\'id\']) or output_error(', $source, $file . ' saves the shared values through pg_ad_save()');
		pg_assert_false(preg_match('/\\b(INSERT|UPDATE|DELETE)\\b[^;]*\\bads\\b/', $source) === 1, $file . ' keeps no SQL write of its own');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, 'Dynamic Ad Region Display Options') !== false, $file . ' keeps no copy of the cards');
	}

	pg_assert_contains('pg_ad_form_validate($liveform, $user, null)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_ad.php'), 'create checks the name against every ad');
	pg_assert_contains('pg_ad_form_validate($liveform, $user, (string) $id)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad.php'), 'edit leaves the ad it checked at the top out of the name check');
	pg_assert_contains('pg_ad_save($id, ', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad.php'), 'edit saves the ad it checked at the top');
	pg_assert_contains('pg_ad_delete($id)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad.php'), 'edit deletes the ad it checked at the top');
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

function test_ad_form_validate_refuses_long_names_and_missing_regions()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');

	pg_assert_contains("pg_name_too_long(\$liveform->get_field_value('name'), 100)", $form, 'a name over 100 characters is refused, not cut');
	pg_assert_contains("pg_ad_region_exists(\$liveform->get_field_value('ad_region_id'))", $form, 'the chosen ad region must exist for every role');
	pg_assert_contains('SELECT id FROM ad_regions WHERE id = ?', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ads.php'), 'pg_ad_region_exists() reads the region');
	pg_assert_contains("'inputmode'=>'numeric'", $form, 'Sort Order opens a number keyboard');
	pg_assert_contains("lang('Caption') . ' (' . lang('Add Caption on Top of Content') . ')", $form, 'no spaces inside the caption brackets');
}

function test_view_ads_filter_and_alignment()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_ads.php');
	$cells = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/region_list.php');

	pg_assert_false(strpos($source, 'Content that viewed') !== false, 'the region filter is named Ad Region');
	pg_assert_contains("\$types[\$row['ad_region_display_type']] ?? ''", $cells, 'an ad without a region shows no display type');
	pg_assert_contains("\$dynamic = (\$row['ad_region_display_type'] === 'dynamic');", $cells, 'label and sort order show for a dynamic region only');
}

function test_ad_region_options_keep_regions_that_share_a_name()
{
	$options = pg_ad_region_option_list(array(
		array('id' => '4', 'name' => 'banner'),
		array('id' => '7', 'name' => 'banner'),
		array('id' => '9', 'name' => 'side'),
	));

	pg_assert_same(array('' => '', 'banner' => '4', 'banner (#7)' => '7', 'side' => '9'), $options);
}

function test_ad_region_options_escape_the_name()
{
	$options = pg_ad_region_option_list(array(array('id' => '3', 'name' => 'a&b')));

	pg_assert_same(array('' => '', 'a&amp;b' => '3'), $options);
}

function test_ad_form_wide_layout()
{
	$liveform = ad_form_test_liveform(array('ad_region_id' => '3', 'sort_order' => ''));

	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', pg_ad_form_cards($liveform), 'the cards sit in the form column');
	pg_assert_same('Sidebar <A>', pg_ad_form_region_label(array('' => '', 'Sidebar <A>' => '3'), '3'), 'the chosen region is named');
	pg_assert_same("\u{2014}", pg_ad_form_region_label(array('' => '', 'Sidebar' => '3'), ''), 'no region reads a dash');
	pg_assert_same("\u{2014}", pg_ad_form_region_label(array('' => '', 'Sidebar' => '3'), '9'), 'a region not in the list reads a dash');
	pg_assert_same(lang('Random'), pg_ad_form_sort_order_label(' '), 'a blank sort order reads Random');
	pg_assert_same('12', pg_ad_form_sort_order_label('12'), 'a sort order is shown as typed');

	$row = pg_ad_form_status_row('Extra <b>', 'x', false);
	pg_assert_contains('<dt class="fw-normal">Extra &lt;b&gt;</dt>', $row, 'the label is escaped');
	pg_assert_contains('border-bottom', $row, 'a row that is not the last draws a line');

	$layout = pg_ad_form_layout('<div class="order-1"></div><div class="order-2"></div>', '<button>Go</button>');
	pg_assert_contains('col-12 order-3 position-sticky" style="bottom:0; z-index:3;"', $layout, 'the save bar sticks on its own column');
	pg_assert_contains('<button>Go</button>', $layout, 'the buttons sit in the bar');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $source, 'the region and Summary sit in the status column');
	pg_assert_contains('class="position-sticky" style="top:3.3rem;"', $source, 'the status box sticks below the top bar');
}

function test_ad_screens_use_wide_layout()
{
	pg_assert_contains('pg_ad_form_cards($liveform) . pg_ad_form_status($liveform)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_ad.php'), 'create prints the form and status columns');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_ad.php');
	pg_assert_contains("pg_ad_form_status(\$liveform, pg_ad_form_status_row(lang('Last Modified')", $edit, 'edit shows the last change');
	foreach (array('add_ad.php', 'edit_ad.php') as $file) {
		pg_assert_contains('pg_ad_form_layout(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' draws the shared save bar');
	}
}

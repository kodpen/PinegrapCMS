<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the defaults a page save keeps (includes/forms/page_form.php):
 * an emptied or zero Photo Gallery Number of Columns, Form View Directory
 * Date Range or Maximum Number of Results is stored as the default the form
 * shows (4, 30, 5), since the site reads 0 as none.
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

function test_page_form_photo_gallery_columns_keep_default()
{
	foreach (array('' => 'an emptied box', '0' => 'a typed 0', '-2' => 'a negative number') as $value => $label) {
		$properties = pg_page_form_type_properties_from_post('photo gallery', 3, array('photo_gallery_number_of_columns' => (string) $value));
		pg_assert_same(4, $properties['number_of_columns'], $label);
	}

	pg_assert_same(4, pg_page_form_type_properties_from_post('photo gallery', 3, array())['number_of_columns'], 'not posted');

	$properties = pg_page_form_type_properties_from_post('photo gallery', 3, array('photo_gallery_number_of_columns' => '6', 'photo_gallery_thumbnail_max_size' => '0'));
	pg_assert_same('6', $properties['number_of_columns'], 'a typed value is kept');
	pg_assert_same('0', $properties['thumbnail_max_size'], 'a zero thumbnail size still means a full-size image');
}

function test_page_form_photo_gallery_columns_shown()
{
	// A stored count below 1 (older saves, the starter's gallery pages) is
	// drawn as the default a save would store, which the check accepts.
	foreach (array('0' => 'a stored 0', '' => 'no stored count') as $stored => $label) {
		pg_assert_same('4', pg_page_form_photo_gallery_columns_shown((string) $stored), $label);
	}
	pg_assert_same('4', pg_page_form_photo_gallery_columns_shown(null), 'no gallery row');
	pg_assert_same('4', pg_page_form_photo_gallery_columns_shown(array('8')), 'not a value');
	pg_assert_same('8', pg_page_form_photo_gallery_columns_shown('8'), 'a stored count is kept');
	pg_assert_same('8', pg_page_form_photo_gallery_columns_shown(8), 'as a number too');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
	pg_assert_true(strpos($source, "\$photo_gallery_number_of_columns = pg_page_form_photo_gallery_columns_shown(\$photo_gallery_properties['number_of_columns'] ?? null);") !== false, 'the box draws the shown count');

	// The thumbnail size box says what 0 does, tied to the box by hand: the
	// help binder skips a .form-ghost input.
	pg_assert_true(strpos($source, 'aria-describedby="photo_gallery_thumbnail_max_size_help"') !== false, 'the box names its help');
	pg_assert_true(strpos($source, '<div class="form-text" id="photo_gallery_thumbnail_max_size_help">\' . h(lang(\'0: full-size image\')) . \'</div>') !== false, 'the help says what 0 does');
}

function test_page_form_directory_summary_keeps_defaults()
{
	foreach (array('' => 'an emptied box', '0' => 'a typed 0') as $value => $label) {
		$properties = pg_page_form_type_properties_from_post('form view directory', 3, array(
			'form_view_directory_summary' => '1',
			'form_view_directory_summary_days' => (string) $value,
			'form_view_directory_summary_maximum_number_of_results' => (string) $value
		));
		pg_assert_same(30, $properties['summary_days'], $label);
		pg_assert_same(5, $properties['summary_maximum_number_of_results'], $label);
	}

	$properties = pg_page_form_type_properties_from_post('form view directory', 3, array(
		'form_view_directory_summary_days' => '7',
		'form_view_directory_summary_maximum_number_of_results' => '12'
	));
	pg_assert_same('7', $properties['summary_days'], 'a typed range is kept');
	pg_assert_same('12', $properties['summary_maximum_number_of_results'], 'a typed maximum is kept');
}

function test_page_form_calendar_upcoming_events_zero_kept()
{
	$properties = pg_page_form_type_properties_from_post('calendar view', 3, array('calendar_view_number_of_upcoming_events' => '0'));
	pg_assert_same('0', $properties['number_of_upcoming_events'], '0 still means all events');
}

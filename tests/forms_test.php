<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for prepare_form_data_for_input() in includes/fn/forms.php: the form
 * list search runs every search term through it for each date and time field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function forms_test_date_format()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
}

// A search term with no date or time parts is converted without a warning:
// the missing parts count as empty.
function test_forms_input_date_and_time_without_parts()
{
	forms_test_date_format();

	pg_assert_same('0000-00-blog 00:00', prepare_form_data_for_input('blog', 'date and time'));
}

// A date with no time is converted without a warning; the time is midnight.
function test_forms_input_date_and_time_without_time()
{
	forms_test_date_format();

	pg_assert_same('2016-03-03 00:00', prepare_form_data_for_input('03/03/2016', 'date and time'));
}

// A complete date and time is converted to the database format.
function test_forms_input_date_and_time_complete()
{
	forms_test_date_format();

	pg_assert_same('2016-03-03 21:30', prepare_form_data_for_input('03/03/2016 9:30 PM', 'date and time'));
}

// A search term that is not a time is converted without a warning.
function test_forms_input_time_without_parts()
{
	pg_assert_same('00:00', prepare_form_data_for_input('blog', 'time'));
}

// A time with AM/PM is converted to the 24-hour format.
function test_forms_input_time_complete()
{
	pg_assert_same('00:05', prepare_form_data_for_input('12:05 AM', 'time'));
}

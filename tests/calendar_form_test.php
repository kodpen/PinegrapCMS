<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar form shared by the create and the edit screens
 * (includes/forms/calendar_form.php): the Options card it prints and that
 * both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_form.php');

function test_calendar_form_card_prints_the_name_field()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$form = new liveform('calendar_form_test');
	$form->set('name', 'Events & "Talks"');
	$card = pg_calendar_form_card($form);

	pg_assert_contains('name="name"', $card, 'the name input');
	pg_assert_contains('maxlength="100"', $card, 'names keep their 100 character limit');
	pg_assert_contains('required="required"', $card, 'the name is required in the browser on both screens');
	pg_assert_contains('value="Events &amp; &quot;Talks&quot;"', $card, 'the value is escaped');
	pg_assert_contains('class="invalid-feedback"', $card, 'the required message');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_calendar_form_name_check_skips_own_row_on_edit()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_form.php');

	pg_assert_contains('SELECT id FROM calendars WHERE (name = ?)"', $form, 'create looks the name up');
	pg_assert_contains('WHERE (name = ?) AND (id != ?)', $form, 'edit skips its own name');
	pg_assert_contains("check_field_error('name') == true", $form, 'a missing name skips the lookup');
}

function test_calendar_form_screens_use_shared_form()
{
	foreach (array('add_calendar.php' => 'null', 'edit_calendar.php' => "(\$_POST['id'] ?? '')") as $file => $id) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/calendar_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_calendar_form_card($liveform)', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_calendar_form_validate_name($liveform, ' . $id . ')', $source, $file . ' checks the name the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, "'maxlength'=>'100'") !== false, $file . ' keeps no copy of the card');
	}
}

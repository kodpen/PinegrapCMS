<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar form shared by the create and the edit screens
 * (includes/forms/calendar_form.php): the columns and the save bar it
 * prints, and that both screens use the shared form.
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
	$card = pg_calendar_form_cards($form);

	pg_assert_contains('name="name"', $card, 'the name input');
	pg_assert_contains('maxlength="100"', $card, 'names keep their 100 character limit');
	pg_assert_contains('required="required"', $card, 'the name is required in the browser on both screens');
	pg_assert_contains('value="Events &amp; &quot;Talks&quot;"', $card, 'the value is escaped');
	pg_assert_true(strpos($card, 'invalid-feedback') === false, 'no fixed message beside the one liveform prints for a refused name');
	pg_assert_contains('<span id="calendar_summary_name">Events &amp; &quot;Talks&quot;</span>', $card, 'the Summary repeats the escaped name');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_calendar_form_name_check_skips_own_row_on_edit()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/calendar_form.php');
	$writes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/calendar_writes.php');

	pg_assert_contains("pg_calendar_name_taken(\$liveform->get_field_value('name'), (int) \$id)", $form, 'the form asks the shared lookup');
	pg_assert_contains('SELECT id FROM calendars WHERE (name = ?)"', $writes, 'create looks the name up');
	pg_assert_contains('WHERE (name = ?) AND (id != ?)', $writes, 'edit skips its own name');
	pg_assert_contains("check_field_error('name') == true", $form, 'a missing name skips the lookup');
}

function test_calendar_form_screens_use_shared_form()
{
	foreach (array('add_calendar.php' => 'null', 'edit_calendar.php' => "(\$_POST['id'] ?? '')") as $file => $id) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/calendar_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_calendar_form_cards($liveform, ', $source, $file . ' prints the shared columns');
		pg_assert_contains('pg_calendar_form_layout(', $source, $file . ' prints the shared save bar');
		pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, $file . ' keeps no copy of the old save bar');
		pg_assert_contains('pg_calendar_form_validate_name($liveform, ' . $id . ')', $source, $file . ' checks the name the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, "'maxlength'=>'100'") !== false, $file . ' keeps no copy of the card');
	}
}

function test_calendar_form_columns_and_save_bar()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$form = new liveform('calendar_form_test');
	$row = pg_calendar_form_status_row('Events', '<a href="calendars.php?calendar_id=7">3</a>');
	$cards = pg_calendar_form_cards($form, $row);
	$layout = pg_calendar_form_layout($cards, '<button>Save</button>', '<div id="note">n</div>');

	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $cards, 'the form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $cards, 'the status column');
	pg_assert_true(strpos($cards, 'order-1') < strpos($cards, 'order-2'), 'the status column comes after the form column, so it stacks below on a phone');
	pg_assert_contains('position-sticky" style="top:3.3rem;"', $cards, 'the status box sticks below the top bar');
	pg_assert_contains('<dd class="mb-0 fw-bold text-end text-break"><a href="calendars.php?calendar_id=7">3</a></dd>', $cards, 'the screen rows follow the name');
	pg_assert_contains('py-2 border-bottom', $cards, 'the name row is underlined when more rows follow');
	pg_assert_contains('col-12 order-3 position-sticky" style="bottom:0; z-index:3;"', $layout, 'the sticky save bar');
	pg_assert_contains('<button>Save</button>', $layout, 'the buttons');
	pg_assert_contains('<div id="note">n</div>', $layout, 'the note under the buttons');
	pg_assert_true(strpos(pg_calendar_form_cards($form), 'border-bottom') === false, 'a lone name row has no line under it');
	pg_assert_contains('<dt class="fw-normal">&lt;b&gt;</dt>', pg_calendar_form_status_row('<b>', 'x'), 'the label is escaped');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_calendar_form_edit_summary_reads_the_last_change()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar.php');

	pg_assert_contains('LEFT JOIN user ON calendars.last_modified_user_id = user.user_id', $source, 'the last change names its user');
	pg_assert_contains("number_format(\$number_of_calendar_events)", $source, 'the event count');
	pg_assert_contains("get_relative_time(array('timestamp' => (int) \$row['last_modified_timestamp']))", $source, 'when it was last saved');
}

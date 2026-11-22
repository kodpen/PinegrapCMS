<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form handlers a signed-out visitor posts to (order_form.php,
 * custom_form.php, add_comment.php, add_or_remove_watcher.php,
 * reserve_calendar_event.php): the refusals they log, a field posted as a
 * list, and the visitor's ids in the rows a guest submission writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The refusals a signed-out visitor reaches (a private form, a closed comment
// thread, a watcher or reservation form, a session that expired) log the
// session username, which such a visitor does not have: each reads it with
// ?? '' or logged "Undefined array key" on every refused post.
function test_visitor_refusal_logs_read_the_username_guarded()
{
	$refusals = array(
		'order_form.php' => array('because visitor does not have access to view order form'),
		'custom_form.php' => array(
			'membership trial custom form ({var:1}) because user is not logged in',
			'that grants private access because user is not logged in',
			"'access denied to submit data to custom form ({var:1})'",
			'that grants an offer, was submitted',
		),
		'add_comment.php' => array(
			'because comments are disabled',
			'because new comments are not allowed',
			'because visitor was not logged in',
			'because visitor does not have view access to page',
			'because the submitted form could not be found',
			'did not have view access to submitted form',
		),
		'add_or_remove_watcher.php' => array(
			'because comments are disabled',
			'because watching comments is disabled',
			'because visitor is not logged in',
		),
		'reserve_calendar_event.php' => array(
			'because page does not exist',
			'because page is not a calendar event view',
			'because calendar event is not published',
			'not allowed in this calendar event view',
			'reservations are not enabled',
			'product no longer exists',
			'the occurrence does not take place',
			'the calendar event has ended',
			'visitor does not have view access to page',
		),
	);

	foreach ($refusals as $file => $texts) {
		$lines = file(PG_FUNCTIONS_DIR . '/' . $file);

		foreach ($texts as $text) {
			$found = 0;

			foreach ($lines as $line) {
				if ((strpos($line, 'log_activity(') !== false) && (strpos($line, $text) !== false)) {
					$found++;
					pg_assert_contains("\$_SESSION['sessionusername'] ?? '')", $line, $file . ': ' . $text);
				}
			}

			pg_assert_true($found > 0, $file . ' logs: ' . $text);
		}
	}
}

// The posted id names the form's session entry. Posted as a list it stopped
// the request with a TypeError ("Cannot access offset of type array"); left
// out, it logged "Undefined array key". Both now count as not given.
function test_reservation_and_watcher_forms_take_one_id()
{
	$reserve = file_get_contents(PG_FUNCTIONS_DIR . '/reserve_calendar_event.php');

	pg_assert_contains("pg_request_single_values(array('calendar_event_id', 'page_id', 'recurrence_number', 'waiting_list', 'email_address'));", $reserve, 'reservation fields');
	pg_assert_contains("new liveform('reserve_calendar_event', \$_POST['calendar_event_id'] ?? 0);", $reserve, 'reservation form entry');
	pg_assert_contains("get_calendar_event(\$calendar_event_id, \$recurrence_number) ?: array('published' => 0, 'name' => '');", $reserve, 'an unknown event stops as an unpublished one');

	$watcher = file_get_contents(PG_FUNCTIONS_DIR . '/add_or_remove_watcher.php');

	pg_assert_contains("pg_request_single_values(array('page_id', 'id', 'item_id', 'item_type', 'action', 'username_or_email_address', 'management'));", $watcher, 'watcher fields');
	pg_assert_contains("\$liveform = new liveform('add_or_remove_watcher', \$_POST['page_id'] ?? 0);\n\$liveform->add_fields_to_session();", $watcher, 'watcher form entry');
	pg_assert_contains("\$row = mysqli_fetch_assoc(\$result) ?: array('page_name' => '', 'page_type' => '', 'folder_id' => 0, 'comments' => 0,", $watcher, 'an unknown page stops as one with comments off');
}

// A guest has no user id (USER_ID is ''). Bound as text into the INT columns
// of a submission's rows it was stored as 0 with warning 1366 on every guest
// submission; an offer code also had '' for its DATE and no notes.
function test_custom_form_guest_rows_bind_numbers()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php');

	pg_assert_contains("(string) \$complete,\n        (int) \$user_id,", $source, 'submission user');
	pg_assert_contains("(string) \$_SERVER['REMOTE_ADDR'],\n        (int) \$user_id))", $source, 'submission last modified by');
	pg_assert_contains("(string) \$_FILES[\$field['id']]['size'],\n            (int) \$user_id))", $source, 'uploaded file owner');
	pg_assert_contains("(int) \$file_id,", $source, 'field row file id');
	pg_assert_contains("user = '\" . (int) \$user_id . \"',", $source, 'contact update');
	pg_assert_contains("VALUES ('', ?, UNIX_TIMESTAMP())\";\n            \$result = db_q(\$query, array((int) \$user_id))", $source, 'new contact');
	pg_assert_contains("\$expiration_date = '0000-00-00';", $source, 'offer code with no end date');
	pg_assert_contains("'\" . (int) USER_ID . \"',", $source, 'offer code user');
}

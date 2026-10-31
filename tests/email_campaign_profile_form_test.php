<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the campaign profile form shared by the create and the edit
 * screens (includes/forms/email_campaign_profile_form.php): which rows an
 * action opens, which record an action is tied to, the schedule values a
 * profile is saved with, and that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');

function test_email_campaign_profile_action_item_field()
{
	pg_assert_same('calendar_event_id', pg_email_campaign_profile_action_item_field('calendar_event_reserved'), 'calendar event');
	pg_assert_same('custom_form_page_id', pg_email_campaign_profile_action_item_field('custom_form_submitted'), 'custom form');
	pg_assert_same('email_campaign_profile_id', pg_email_campaign_profile_action_item_field('email_campaign_sent'), 'campaign sent');
	pg_assert_same('product_id', pg_email_campaign_profile_action_item_field('product_ordered'), 'product');
	pg_assert_same('', pg_email_campaign_profile_action_item_field('order_completed'), 'order action has no item');
	pg_assert_same('', pg_email_campaign_profile_action_item_field(''), 'no action');
}

function test_email_campaign_profile_action_rows()
{
	$rows = pg_email_campaign_profile_action_rows('calendar_event_reserved');
	pg_assert_same('show', $rows['calendar_event_id'], 'calendar picker shown');
	pg_assert_same('show', $rows['calendar_schedule'], 'before/after shown');
	pg_assert_same('', $rows['standard_schedule'], 'after action label hidden');

	$rows = pg_email_campaign_profile_action_rows('product_ordered');
	pg_assert_same('show', $rows['product_id'], 'product picker shown');
	pg_assert_same('', $rows['calendar_event_id'], 'calendar picker hidden');
	pg_assert_same('', $rows['calendar_schedule'], 'before/after hidden');
	pg_assert_same('show', $rows['standard_schedule'], 'after action label shown');

	$rows = pg_email_campaign_profile_action_rows('order_shipped');
	pg_assert_same(array('', '', '', ''), array($rows['calendar_event_id'], $rows['custom_form_page_id'], $rows['email_campaign_profile_id'], $rows['product_id']), 'order action opens no picker');
	pg_assert_same('show', $rows['standard_schedule'], 'order action: after action label');

	$rows = pg_email_campaign_profile_action_rows('');
	pg_assert_same('show', $rows['standard_schedule'], 'no action: after action label');
	pg_assert_same('', $rows['calendar_schedule'], 'no action: before/after hidden');
}

function test_email_campaign_profile_action_values()
{
	$fields = array(
		'calendar_event_id' => '7',
		'custom_form_page_id' => '8',
		'email_campaign_profile_id' => '9',
		'product_id' => '10',
		'schedule_period' => 'before',
		'schedule_base' => 'calendar_event_start_time',
	);

	pg_assert_same(
		array('action_item_id' => '7', 'schedule_period' => 'before', 'schedule_base' => 'calendar_event_start_time'),
		pg_email_campaign_profile_action_values('calendar_event_reserved', $fields),
		'calendar event keeps the chosen period and base');

	pg_assert_same(
		array('action_item_id' => '8', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('custom_form_submitted', $fields),
		'custom form');

	pg_assert_same(
		array('action_item_id' => '9', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('email_campaign_sent', $fields),
		'campaign sent');

	pg_assert_same(
		array('action_item_id' => '10', 'schedule_period' => 'after', 'schedule_base' => 'action'),
		pg_email_campaign_profile_action_values('product_ordered', $fields),
		'product ordered');

	foreach (array('order_abandoned', 'order_completed', 'order_review_request', 'order_shipped', 'order_delivered') as $action) {
		pg_assert_same(
			array('action_item_id' => '0', 'schedule_period' => 'after', 'schedule_base' => 'action'),
			pg_email_campaign_profile_action_values($action, $fields),
			$action);
	}

	pg_assert_same(
		array('action_item_id' => '', 'schedule_period' => '', 'schedule_base' => ''),
		pg_email_campaign_profile_action_values('unknown', $fields),
		'unknown action');
}

function test_email_campaign_profile_schedule_time_midnight()
{
	pg_assert_same('00:01:00', pg_email_campaign_profile_schedule_time('12:00 AM'), '12:00 AM is not "any time"');
	pg_assert_same('00:01:00', pg_email_campaign_profile_schedule_time('12:00 am'), 'lower case');
}

function test_email_campaign_profile_screens_use_shared_form()
{
	foreach (array('add_email_campaign_profile.php', 'edit_email_campaign_profile.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/email_campaign_profile_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_email_campaign_profile_form_cards(', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_email_campaign_profile_form_validate(', $source, $file . ' validates with the shared rules');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
	}
}

function test_email_campaign_profile_downstream()
{
	// 2 follows 1, 3 follows 2, 4 follows 3; 5 follows 9; 6 follows itself.
	$follows = array(2 => 1, 3 => 2, 4 => 3, 5 => 9, 6 => 6);

	pg_assert_same(array(1, 2, 3, 4), pg_email_campaign_profile_downstream(1, $follows), 'whole chain below 1');
	pg_assert_same(array(3, 4), pg_email_campaign_profile_downstream(3, $follows), 'only below 3');
	pg_assert_same(array(4), pg_email_campaign_profile_downstream(4, $follows), 'nothing follows 4');
	pg_assert_same(array(6), pg_email_campaign_profile_downstream(6, $follows), 'a self-loop ends');
	pg_assert_same(array(), pg_email_campaign_profile_downstream(0, $follows), 'a new profile has none');

	// An existing ring 7 -> 8 -> 7 ends through the visited guard.
	pg_assert_same(array(7, 8), pg_email_campaign_profile_downstream(7, array(7 => 8, 8 => 7)), 'ring ends');
}

function test_email_campaign_profile_closes_ring()
{
	$follows = array(2 => 1, 3 => 2, 4 => 3, 5 => 9, 7 => 8, 8 => 7);

	pg_assert_true(pg_email_campaign_profile_closes_ring(1, 1, $follows), 'itself');
	pg_assert_true(pg_email_campaign_profile_closes_ring(1, 2, $follows), 'a direct follower');
	pg_assert_true(pg_email_campaign_profile_closes_ring(1, 4, $follows), 'a follower further down');
	pg_assert_true(pg_email_campaign_profile_closes_ring(1, '4', $follows), 'posted as a string');
	pg_assert_false(pg_email_campaign_profile_closes_ring(4, 1, $follows), 'a profile upstream');
	pg_assert_false(pg_email_campaign_profile_closes_ring(1, 5, $follows), 'an unrelated chain');
	pg_assert_false(pg_email_campaign_profile_closes_ring(1, 7, $follows), 'an existing ring elsewhere ends');
	pg_assert_false(pg_email_campaign_profile_closes_ring(0, 1, $follows), 'a new profile');
	pg_assert_false(pg_email_campaign_profile_closes_ring(1, '', $follows), 'no pick');
}

function test_email_campaign_profile_picker_leaves_out_downstream()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
	pg_assert_contains('get_email_campaign_profile_options($exclude_ids', $form, 'picker leaves the downstream profiles out');
	pg_assert_contains('A campaign profile cannot follow itself or a profile that follows it.', $form, 'save refuses a ring');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_email_campaign_profile.php');
	pg_assert_contains("'email_campaign_profile_id' =>", $edit, 'edit keeps the stored pick');
	pg_assert_contains('), $id) .', $edit, 'edit passes the profile id to the cards');
}

// Statement-level db_q() writes in $source whose result is dropped: the call
// starts a statement and is followed straight by ';'. String literals are
// skipped while the parentheses are counted.
function email_campaign_profile_test_dropped_writes($source)
{
	$dropped = array();

	if (!preg_match_all('/(?:^|[;{}])\s*db_q\(/', $source, $matches, PREG_OFFSET_CAPTURE)) {
		return $dropped;
	}

	foreach ($matches[0] as $match) {
		$start = $match[1] + strlen($match[0]);
		$depth = 1;
		$quote = '';
		$length = strlen($source);

		for ($i = $start; ($i < $length) && ($depth > 0); $i++) {
			$char = $source[$i];

			if ($quote !== '') {
				if ($char === '\\') {
					$i++;
				} elseif ($char === $quote) {
					$quote = '';
				}
			} elseif (($char === '"') || ($char === "'")) {
				$quote = $char;
			} elseif ($char === '(') {
				$depth++;
			} elseif ($char === ')') {
				$depth--;
			}
		}

		$call = substr($source, $start, $i - $start);

		if (preg_match('/^\s*"\s*(INSERT|UPDATE|DELETE)\b/i', $call) && preg_match('/^\s*;/', substr($source, $i))) {
			$dropped[] = trim(substr($call, 0, 60));
		}
	}

	return $dropped;
}

// A failed create, save or delete ends the request with 'Query failed.'
// instead of logging and announcing that it worked.
function test_email_campaign_profile_writes_are_checked()
{
	foreach (array('/add_email_campaign_profile.php', '/edit_email_campaign_profile.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . $file);
		pg_assert_same(array(), email_campaign_profile_test_dropped_writes($source), $file . ' drops no write result');
	}
}

// An imported "campaign sent" trigger follows the edit screen's rule: the
// followed profile exists and no ring is closed.
function test_email_campaign_profile_follow_problem()
{
	$follows = array(2 => 1, 3 => 2, 12 => 5);
	$ids = array_flip(array(1, 2, 3, 5, 12));
	$ring = lang('A campaign profile cannot follow itself or a profile that follows it.');
	$missing = lang('The campaign profile it is to follow does not exist.');

	pg_assert_same($ring, pg_email_campaign_profile_follow_problem(12, '12', $follows, $ids), 'follows itself');
	pg_assert_same($ring, pg_email_campaign_profile_follow_problem(1, 3, $follows, $ids), 'follows a profile further down');
	pg_assert_same($missing, pg_email_campaign_profile_follow_problem(1, 99, $follows, $ids), 'no such profile');
	pg_assert_same($missing, pg_email_campaign_profile_follow_problem(1, '', $follows, $ids), 'no pick');
	pg_assert_same($missing, pg_email_campaign_profile_follow_problem(1, '2abc', $follows, $ids), 'not a number');
	pg_assert_same('', pg_email_campaign_profile_follow_problem(3, 1, $follows, $ids), 'a profile upstream');
	pg_assert_same('', pg_email_campaign_profile_follow_problem(0, 12, $follows, $ids), 'a new profile');
}

// The import writes no row the shared check refuses and keeps its map current.
function test_email_campaign_profile_import_checks_rings()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_email_campaign_profiles.php');

	pg_assert_contains('pg_email_campaign_profile_follow_map()', $source, 'reads the follow map');
	pg_assert_contains('pg_email_campaign_profile_follow_problem(', $source, 'runs the shared check');
	pg_assert_contains('$follows[$id] = (int) $action_item_id;', $source, 'updates the map with each row');
	pg_assert_contains('pg_csv_problem_list($problems)', $source, 'names the rows not written');
}

// A page a record points at: its name, its former name while binned,
// "(deleted)" once its row is gone.
function test_page_record_label()
{
	$bin = ' [' . lang('IN RECYCLE BIN') . ']';

	pg_assert_same('', pg_page_record_label(0, null, false), 'no page stored');
	pg_assert_same(lang('(deleted)'), pg_page_record_label(1071, null, false), 'row gone');
	pg_assert_same('contact', pg_page_record_label(5, array('page_name' => 'contact'), false), 'plain page');
	pg_assert_same('pg-test-form-klasor' . $bin, pg_page_record_label(1626, array('page_name' => 'pgbin_1626_pg-test-form-klasor'), true), 'binned page unparked');
	pg_assert_same('inner' . $bin, pg_page_record_label(7, array('page_name' => 'inner'), true), 'page inside a binned folder keeps its name');
	pg_assert_same('pgbin_9_x' . $bin, pg_page_record_label(7, array('page_name' => 'pgbin_9_x'), true), 'another row\'s prefix is not stripped');
}

// The Custom Form picker lists every form the Forms screen lists.
function test_email_campaign_profile_form_picker_lists_every_form()
{
	pg_assert_same('', pg_page_options_type_sql(''), 'no type');

	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content.php');
	pg_assert_contains("if (\$page_type === 'any form') {\n        return pg_form_page_sql('page');", $content, 'every form is the Forms screen set');

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
	pg_assert_contains("get_page_options('', 'any form', 'edit', false,", $form, 'picker asks for every form, bin left out');
}

// The list, the CSV and the edit screen name a binned or gone page; sending
// skips an HTML profile whose page is gone.
function test_email_campaign_profile_page_states()
{
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_email_campaign_profiles.php');
	pg_assert_same(2, substr_count($view, 'pg_page_record_name($email_campaign_profile[\'page_id\'])'), 'list and CSV use the page label');

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
	pg_assert_contains('pg_page_record_label($action_item_id, $row,', $form, 'trigger form page label');
	pg_assert_contains('$page_gone_notice', $form, 'notice above the Page picker');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_email_campaign_profile.php');
	pg_assert_contains("'page_gone' => \$page_gone", $edit, 'edit passes the gone page');

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	pg_assert_contains("} else if (\$email_campaign_profile['page_name'] === null) {", $mail, 'gone page is skipped');
}

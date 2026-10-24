<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/comments.php: which thread a comment posted to a page
 * may join (page type x record view widget x posted item type), the record a
 * record view page holds a post to, and that add_comment.php asks before it
 * stores anything and sends a refused post back to its form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decision table. Each row: page type, record view widget on the page,
// posted item type, expected refusal ('' = accepted). The posted id is 12 and
// the widget shows record 12 to this visitor, so only the type decides.
function test_comments_thread_decision_table()
{
	$types = array('', 'submitted_form', 'product', 'product_group', 'calendar_event');
	$expected = array(
		// A legacy form item view page takes a submitted form only, widget or not.
		'form item view|0' => array('' => 'page_type', 'submitted_form' => '', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => 'page_type'),
		'form item view|1' => array('' => 'page_type', 'submitted_form' => '', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => 'page_type'),
		// A page with a record view widget takes its record only.
		'standard|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type'),
		'catalog detail|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type'),
		'calendar event view|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type'),
		// Any other page takes what was posted, except a submitted form.
		'standard|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => '', 'product_group' => '', 'calendar_event' => ''),
		'catalog|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => '', 'product_group' => '', 'calendar_event' => ''),
		'catalog detail|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => '', 'product_group' => '', 'calendar_event' => ''),
		'calendar event view|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => '', 'product_group' => '', 'calendar_event' => ''),
	);

	foreach ($expected as $key => $row) {
		list($page_type, $widget) = explode('|', $key);

		foreach ($types as $type) {
			$decision = pg_comment_thread_decision($page_type, $widget === '1', $type, '12', 12, true);
			$label = $page_type . ($widget === '1' ? ' with' : ' without') . ' a record view, item type "' . $type . '"';

			pg_assert_same($row[$type], $decision['refusal'], $label);
			pg_assert_same($row[$type] === '', $decision['accept'], $label . ' (accept)');
		}
	}
}

// The symptom: a comment posted with an empty item type to a designed record
// page landed on the page's own thread, which that page never shows.
function test_comments_thread_page_thread_refused_on_record_page()
{
	$decision = pg_comment_thread_decision('standard', true, '', '0', 0, false);

	pg_assert_false($decision['accept'], 'the page itself');
	pg_assert_same('item_type', $decision['refusal']);

	$decision = pg_comment_thread_decision('standard', true, '', '12', 12, true);

	pg_assert_same('item_type', $decision['refusal'], 'even with the record id beside it');
}

// On a record view page the post joins the record the widget shows, by the
// record's own id; anything else is refused as 'record'.
function test_comments_thread_record_rules()
{
	$decision = pg_comment_thread_decision('standard', true, 'submitted_form', '12', 12, true);

	pg_assert_true($decision['accept'], 'the record the widget shows');
	pg_assert_same('submitted_form', $decision['item_type']);
	pg_assert_same(12, $decision['item_id'], 'stored under the record id');

	$refused = array(
		'a record the visitor may not see' => array('12', 12, false),
		'a record of another form, or none' => array('12', 0, false),
		'no record id' => array('0', 0, false),
		'an empty record id' => array('', 0, false),
		'a negative id' => array('-12', 0, false),
		'an id the widget did not resolve' => array('12', 13, true),
		'an id that is not a value' => array(array('12'), 12, true),
	);

	foreach ($refused as $label => $case) {
		$decision = pg_comment_thread_decision('standard', true, 'submitted_form', $case[0], $case[1], $case[2]);

		pg_assert_false($decision['accept'], $label);
		pg_assert_same('record', $decision['refusal'], $label);
	}

	// A record id read from the database is a string.
	$decision = pg_comment_thread_decision('standard', true, 'submitted_form', '12', '12', true);

	pg_assert_true($decision['accept'], 'a record id as a string');
	pg_assert_same(12, $decision['item_id']);
}

// A page without a record view widget, and a legacy form item view page,
// keep the posted values as they came.
function test_comments_thread_classic_keeps_posted_values()
{
	$decision = pg_comment_thread_decision('catalog detail', false, 'product', '7', 0, false);

	pg_assert_true($decision['accept'], 'a product on a catalog detail page');
	pg_assert_same('product', $decision['item_type']);
	pg_assert_same('7', $decision['item_id'], 'the posted id, unchanged');

	$decision = pg_comment_thread_decision('standard', false, '', '0', 0, false);

	pg_assert_same('', $decision['item_type'], 'the page itself');
	pg_assert_same('0', $decision['item_id']);

	// The legacy page's own row decides who may see the record; the widget
	// numbers are not asked there.
	$decision = pg_comment_thread_decision('form item view', false, 'submitted_form', '31', 0, false);

	pg_assert_true($decision['accept'], 'a legacy form item view page');
	pg_assert_same('31', $decision['item_id']);
}

// A type that is not a value is no submitted form.
function test_comments_thread_non_scalar_type()
{
	pg_assert_same('item_type', pg_comment_thread_decision('standard', true, array('submitted_form'), '12', 12, true)['refusal'], 'on a record view page');
	pg_assert_same('page_type', pg_comment_thread_decision('form item view', false, array('submitted_form'), '12', 0, false)['refusal'], 'on a legacy form item view page');
}

// Without a page there is no widget to ask, and no query is run.
function test_comments_thread_verify_without_page()
{
	$verify = pg_comment_thread_verify(0, 'standard', '', '0');

	pg_assert_true($verify['accept']);
	pg_assert_same(null, $verify['record']);

	$verify = pg_comment_thread_verify(0, 'form item view', '', '0');

	pg_assert_same('page_type', $verify['refusal']);
	pg_assert_same(null, $verify['record']);
}

// add_comment.php asks before it validates and stores, and answers a refusal
// only after the page's own gates (comments on, new comments allowed, login,
// view access): a visitor who may not comment on the page gets the same
// answer whatever record id was posted. Another item type on a record view
// page goes back to its form with what was typed, without the posted thread.
// A record the page does not show gets an error page with the form state
// dropped: that record's page draws no comment form to carry the message,
// and the typed text must not turn up on another record's form.
function test_comments_thread_add_comment_wiring()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	$verify = strpos($add, 'pg_comment_thread_verify($page_id, $page_type, $item_type, $item_id)');
	$gates = array(
		'comments on' => strpos($add, 'if ($comments == 0)'),
		'new comments allowed' => strpos($add, 'if ($allow_new_comments == FALSE)'),
		'login' => strpos($add, '($comments_require_login_to_comment == 1)'),
		'view access' => strpos($add, 'if (check_view_access($folder_id) == false)'),
	);
	$type_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'item_type')");
	$record_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'record')");
	$page_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'page_type')");
	$insert = strpos($add, 'INSERT INTO comments');
	$found = ($verify !== false) && ($type_refusal !== false) && ($record_refusal !== false) && ($page_refusal !== false) && ($insert !== false);

	pg_assert_true($found, 'add_comment.php asks and answers each refusal');
	pg_assert_same(1, substr_count($add, "\$comment_thread['refusal'] === 'item_type'"), 'the item type refusal is answered in one place');
	pg_assert_same(1, substr_count($add, "\$comment_thread['refusal'] === 'record'"), 'the record refusal too');

	foreach ($gates as $label => $gate) {
		pg_assert_true($found && ($gate !== false) && ($verify < $gate) && ($gate < $type_refusal) && ($gate < $record_refusal), 'a refusal is answered after the gate: ' . $label);
	}

	pg_assert_true($found && ($type_refusal < $record_refusal) && ($record_refusal < $page_refusal) && ($page_refusal < $insert), 'before anything is stored');

	if (!$found || ($record_refusal < $type_refusal) || ($page_refusal < $record_refusal)) {
		return;
	}

	$type_block = substr($add, $type_refusal, $record_refusal - $type_refusal);

	pg_assert_contains('$liveform->add_error(', $type_block, 'another item type: a form error');
	pg_assert_contains("\$liveform->set_field('item_type', array())", $type_block, 'the posted type is dropped from the form state');
	pg_assert_contains("\$liveform->set_field('item_id', array())", $type_block, 'the posted id too');
	pg_assert_contains("header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(", $type_block, 'back to the form');
	pg_assert_contains('exit();', $type_block, 'and nothing after it');

	$record_block = substr($add, $record_refusal, $page_refusal - $record_refusal);
	$remove = strpos($record_block, '$liveform->remove_form();');
	$error = strpos($record_block, 'output_error(');

	pg_assert_true(($remove !== false) && ($error !== false) && ($remove < $error), 'a record the page does not show: the form state dropped, then an error page');
	pg_assert_false(strpos($record_block, 'add_error(') !== false, 'no form error a page without a form would keep');
	pg_assert_false(strpos($record_block, 'header(') !== false, 'no redirect to a page without a form');

	pg_assert_false(strpos($add, "\$comment_record['visible'] == false") !== false, 'record visibility is decided by pg_comment_thread_verify() only');
	pg_assert_false(strpos($add, "(\$item_type == 'submitted_form') && function_exists('pg_sw_record_comment_context')") !== false, 'the widget is no longer asked only for a posted submitted form');
}
